<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\CommerceSettings;
use App\Services\Inventory;
use App\Services\Pricing;
use App\Services\VariantPricing;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class VariantPricingTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $this->assertContains(config("database.connections.$connection.database"), [':memory:', 'leather_store_variant_regression_test']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        config(['app.env' => 'local']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $category = Category::create(['name' => 'Pricing', 'slug' => 'pricing', 'is_active' => true]);
        $this->product = Product::create(['name' => 'Price jacket', 'slug' => 'price-jacket', 'category_id' => $category->id, 'is_active' => true]);
        foreach (['Black', 'Brown'] as $name) {
            $color = $this->product->colors()->create(['name' => $name]);
            foreach (['S' => null, 'M' => 1850000, 'L' => null, 'XXL' => 2000000] as $size => $price) {
                $color->variants()->create(['size' => $size, 'sku' => strtoupper($name).'-'.$size, 'pkr_price_minor' => $price, 'usd_price_minor' => 9900, 'is_active' => true]);
            }
        }
    }

    private function black(): array
    {
        return $this->product->colors()->where('name', 'Black')->first()->variants()->pluck('id')->all();
    }

    public function test_fill_missing_preserves_exceptions_other_colors_and_usd_and_inherited_prices(): void
    {
        $service = app(VariantPricing::class);
        $ids = $this->black();
        $plan = $service->preview($this->product, $ids, 'PKR', 'missing', '18500');
        $this->assertCount(2, array_filter($plan['rows'], fn ($row) => $row['change']));
        $service->apply($this->product, $ids, 'PKR', 'missing', '18500', $plan['token']);
        $this->assertSame([1850000, 1850000, 1850000, 2000000], ProductVariant::whereIn('id', $ids)->orderBy('id')->pluck('pkr_price_minor')->map(fn ($v) => (int) $v)->all());
        $this->assertSame(9900, (int) ProductVariant::where('sku', 'BLACK-S')->first()->usd_price_minor);
        $this->assertNull(ProductVariant::where('sku', 'BROWN-S')->first()->pkr_price_minor);
        $this->reject(fn () => $service->apply($this->product, $ids, 'PKR', 'missing', '18500', $plan['token']));
        $this->assertSame(8, ProductVariant::count());
        $this->product->update(['pkr_price_minor' => 1700000]);
        $brown = ProductVariant::where('sku', 'BROWN-S')->first();
        $plan = $service->preview($this->product, [$brown->id], 'PKR', 'missing', '18500');
        $this->assertFalse($plan['rows'][0]['change']);
        $this->assertSame(1700000, $plan['rows'][0]['current']);
        foreach (['', '0', '-1', '12.345'] as $invalid) {
            $this->reject(fn () => $service->preview($this->product, $ids, 'PKR', 'replace', $invalid));
        }
    }

    public function test_replace_is_atomic_checks_ownership_and_conflicts_and_retains_order_snapshot(): void
    {
        $service = app(VariantPricing::class);
        $variant = ProductVariant::where('sku', 'BLACK-M')->first();
        app(Inventory::class)->adjust($variant->id, 5, 'Pricing test fixture', null);
        CommerceSettings::save(['shipping_minor' => 0, 'tax_basis_points' => 0, 'cod_enabled' => true]);
        $quote = app(Cart::class)->quote([$variant->id => 1], 'PKR');
        $order = app(Checkout::class)->create(hash('sha256', 'pricing guest'), (string) Str::uuid(), ['name' => 'Test Buyer', 'email' => 'price@example.test', 'phone' => '+923001234567', 'address' => 'Test street', 'city' => 'Lahore', 'region' => 'Punjab', 'postal_code' => '54000', 'country' => 'PK', 'payment_method' => 'cod'], ['items' => [$variant->id => 1], 'fingerprint' => Cart::fingerprint($quote, CommerceSettings::totals($quote['subtotal']))]);
        $plan = $service->preview($this->product, [$variant->id], 'PKR', 'replace', '19000.50');
        $service->apply($this->product, [$variant->id], 'PKR', 'replace', '19000.50', $plan['token']);
        $this->assertSame(1900050, Pricing::resolve($variant->fresh(), 'PKR'));
        $this->assertSame(9900, Pricing::resolve($variant->fresh(), 'USD'));
        $this->assertSame(2000000, (int) ProductVariant::where('sku', 'BLACK-XXL')->first()->pkr_price_minor);
        $this->assertSame(1850000, (int) ProductVariant::where('sku', 'BROWN-M')->first()->pkr_price_minor);
        $this->assertSame(1850000, (int) $order->lines()->first()->unit_price_minor);
        $this->assertSame(1850000, (int) $order->fresh()->total_minor);
        $this->get(route('products.show', $this->product).'?color='.$variant->product_color_id.'&size=M')->assertOk()->assertSee('PKR 19,000.50');
        $this->assertSame(1900050, app(Cart::class)->quote([$variant->id => 1], 'PKR')['subtotal']);
        $plan = $service->preview($this->product, $this->black(), 'PKR', 'replace', '21000');
        $variant->update(['pkr_price_minor' => 1950000]);
        $this->reject(fn () => $service->apply($this->product, $this->black(), 'PKR', 'replace', '21000', $plan['token']));
        $this->assertNull(ProductVariant::where('sku', 'BLACK-S')->first()->pkr_price_minor);
        $other = Product::create(['name' => 'Other', 'slug' => 'other', 'category_id' => $this->product->category_id]);
        $foreign = $other->colors()->create(['name' => 'Red'])->variants()->create(['size' => 'M', 'sku' => 'OTHER-M']);
        $this->reject(fn () => $service->preview($this->product, [$foreign->id], 'PKR', 'replace', '100'));
        Gate::define('manage-commerce', fn () => false);
        $this->expectException(AuthorizationException::class);
        $service->preview($this->product, $this->black(), 'PKR', 'replace', '100');
    }

    public function test_modal_preview_confirm_cancel_and_main_save_preserve_pending_edits(): void
    {
        $black = $this->product->colors()->where('name', 'Black')->first();
        $page = Livewire::test(EditProduct::class, ['record' => $this->product->id])->set('data.description', 'Still pending')
            ->mountAction('managePrices')->unmountAction()->assertSet('data.description', 'Still pending')
            ->mountAction('managePrices')->set('mountedActions.0.data.scope', (string) $black->id)
            ->set('mountedActions.0.data.amount', '18500');
        $page->call('mountAction', 'previewPrices', [], ['schemaComponent' => 'mountedActionSchema0']);
        $this->assertNotEmpty($page->get('mountedActions.0.data.token'));
        $this->assertStringContainsString('BLACK-XXL', $page->get('mountedActions.0.data.preview'));
        $page->callMountedAction()->assertNotified('Updated 2 PKR prices; skipped 2 existing or unchanged prices')->assertSet('data.description', 'Still pending');
        $this->assertNull($this->product->fresh()->description);
        $page->call('save')->assertHasNoFormErrors()->call('save')->assertHasNoFormErrors();
        $this->assertSame(1850000, (int) ProductVariant::where('sku', 'BLACK-S')->first()->pkr_price_minor);
        $this->assertSame(2000000, (int) ProductVariant::where('sku', 'BLACK-XXL')->first()->pkr_price_minor);
        $this->assertSame('Still pending', $this->product->fresh()->description);
    }

    public function test_modal_conflict_can_be_reviewed_and_individual_usd_edit_preserves_pkr(): void
    {
        $variant = ProductVariant::where('sku', 'BLACK-M')->first();
        $page = Livewire::test(EditProduct::class, ['record' => $this->product->id])
            ->set('data.description', 'Keep while reviewing conflict')->mountAction('managePrices')
            ->set('mountedActions.0.data.selection', [$variant->product_color_id => [$variant->id]])
            ->set('mountedActions.0.data.currency', 'USD')->set('mountedActions.0.data.mode', 'replace')
            ->set('mountedActions.0.data.amount', '120.25')
            ->call('mountAction', 'previewPrices', [], ['schemaComponent' => 'mountedActionSchema0']);
        $variant->update(['usd_price_minor' => 11000]);
        $page->callMountedAction()->assertNotified('Review prices')->assertSet('data.description', 'Keep while reviewing conflict');
        $this->assertSame(11000, (int) $variant->fresh()->usd_price_minor);
        $page->call('mountAction', 'previewPrices', [], ['schemaComponent' => 'mountedActionSchema0']);
        $this->assertStringContainsString('USD 110 -> USD 120.25', $page->get('mountedActions.0.data.preview'));
        $page->callMountedAction()->assertNotified('Updated 1 USD prices; skipped 0 existing or unchanged prices');
        $this->assertSame(12025, (int) $variant->fresh()->usd_price_minor);
        $this->assertSame(1850000, (int) $variant->fresh()->pkr_price_minor);
        $this->assertSame(9900, (int) ProductVariant::where('sku', 'BROWN-M')->first()->usd_price_minor);
        $path = 'data.colors.record-'.$variant->product_color_id.'.variants.record-'.$variant->id.'.pkr_price_minor';
        $page->set($path, '22222')->mountAction('managePrices')
            ->set('mountedActions.0.data.scope', 'all')->set('mountedActions.0.data.amount', '19000')
            ->call('mountAction', 'previewPrices', [], ['schemaComponent' => 'mountedActionSchema0'])->assertNotified('Review prices')
            ->assertSet($path, '22222');
        $this->assertEmpty($page->get('mountedActions.0.data.token'));
        $this->assertCount(8, collect($page->get('mountedActions.0.data.selection'))->flatten());
        $this->assertSame(1850000, (int) $variant->fresh()->pkr_price_minor);
    }

    private function reject(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected pricing validation rejection');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }
}
