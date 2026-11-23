<?php

namespace Tests\Feature;

use App\Filament\Pages\CatalogAttention;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Dispatch;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Dispatching;
use App\Services\Inventory;
use App\Support\ProductPresentation;
use App\Support\ProductWorkspace;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OwnerWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame(':memory:', config('database.connections.'.config('database.default').'.database'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.env' => 'local']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
    }

    private function product(): Product
    {
        $category = Category::create(['name' => 'Jackets', 'slug' => 'jackets', 'is_active' => true]);

        return Product::create(['category_id' => $category->id, 'name' => 'Classic Biker Jacket', 'slug' => 'classic-biker-jacket', 'is_active' => true]);
    }

    public function test_stock_snapshots_are_batched_and_invalidated_after_adjustment(): void
    {
        $product = $this->product();
        $color = $product->colors()->create(['name' => 'Black']);
        foreach (range(1, 24) as $size) {
            $color->variants()->create(['size' => (string) $size, 'sku' => 'CBJ-BLK-'.$size, 'is_active' => true]);
        }
        $variants = $color->variants()->get();
        ProductWorkspace::forget();
        DB::enableQueryLog();
        DB::flushQueryLog();
        foreach ($variants as $variant) {
            $this->assertNull(ProductWorkspace::variant($variant)->on_hand);
            ProductWorkspace::variant($variant);
        }
        $this->assertCount(1, DB::getQueryLog());
        DB::disableQueryLog();
        app(Inventory::class)->adjust($variants->first()->id, 10, 'Owner workspace regression', auth()->id());
        $this->assertSame(10, ProductWorkspace::variant($variants->first())->on_hand);
        $findings = ProductPresentation::readiness($product->fresh());
        $this->assertStringContainsString('0 available to order', collect($findings)->firstWhere('kind', 'Color summary')['text']);
        $this->assertContains('Optional improvement', array_column($findings, 'kind'));
    }

    public function test_attention_finds_exact_active_skus_and_opens_price_fix_preserving_pending_input(): void
    {
        $product = $this->product();
        $color = $product->colors()->create(['name' => 'Black']);
        $zero = $color->variants()->create(['size' => 'M', 'sku' => 'CBJ-BLK-M', 'is_active' => true, 'pkr_price_minor' => 0]);
        $sold = $color->variants()->create(['size' => 'L', 'sku' => 'CBJ-BLK-L', 'is_active' => true, 'pkr_price_minor' => 10000]);
        $inactive = $color->variants()->create(['size' => 'S', 'sku' => 'CBJ-BLK-S', 'is_active' => false]);
        app(Inventory::class)->adjust($zero->id, 5, 'Workspace fixture', auth()->id());
        app(Inventory::class)->adjust($sold->id, 0, 'Workspace fixture', auth()->id());
        $this->assertSame([$zero->id], ProductWorkspace::attention('price')->pluck('id')->all());
        $this->assertSame([$sold->id], ProductWorkspace::attention('stock')->pluck('id')->all());
        $rowPath = 'colors.record-'.$color->id.'.variants';
        $rowPage = Livewire::test(EditProduct::class, ['record' => $product->id])->set('data.description', 'Pending row edits');
        $rowPage->callFormComponentAction($rowPath, 'rowStock', [], ['item' => 'record-'.$sold->id])
            ->assertSet('mountedActions.0.name', 'manageStock')->assertSet('mountedActions.0.data.focus', $sold->id)
            ->unmountAction()->assertSet('data.description', 'Pending row edits');
        $rowPage->callFormComponentAction($rowPath, 'rowPrice', [], ['item' => 'record-'.$zero->id])
            ->assertSet('mountedActions.0.name', 'managePrices')->assertSet('mountedActions.0.data.mode', 'replace')
            ->unmountAction()->assertSet('data.description', 'Pending row edits');
        Livewire::withQueryParams(['issue' => 'price'])->test(CatalogAttention::class)->assertCanSeeTableRecords([$zero])->assertCanNotSeeTableRecords([$sold, $inactive]);
        $page = Livewire::withQueryParams(['action' => 'managePrices', 'actionArguments' => ['variant' => $zero->id]])
            ->test(EditProduct::class, ['record' => $product->id])->call('mountDefaultActions')->assertSet('mountedActions.0.name', 'managePrices')
            ->assertSet('mountedActions.0.data.mode', 'replace')
            ->assertSee('id="color-'.$color->id.'-sizes"', false)
            ->assertSee('id="color-'.$color->id.'-photos"', false);
        $this->assertEquals([$zero->id], collect($page->get('mountedActions.0.data.selection'))->flatten()->all());
        $page->unmountAction()->set('data.description', 'Unsaved owner text')->mountAction('manageStock', ['variant' => $sold->id])
            ->assertSet('mountedActions.0.data.focus', $sold->id)->unmountAction()->assertSet('data.description', 'Unsaved owner text');
        $product->update(['is_active' => false]);
        $this->assertSame(0, ProductWorkspace::attention('price')->count());
        Gate::define('manage-commerce', fn (): bool => false);
        Livewire::test(CatalogAttention::class)->assertForbidden();
    }

    private function order(string $method = 'cod', string $payment = 'unpaid'): Order
    {
        return Order::create(['number' => 'TEST-'.Str::random(8), 'guest_hash' => hash('sha256', Str::random()), 'access_token' => Str::random(64), 'payment_method' => $method, 'payment_status' => $payment, 'currency' => 'PKR', 'customer' => ['name' => 'Isolated fixture'], 'subtotal_minor' => 10000, 'discount_minor' => 0, 'tax_minor' => 0, 'shipping_minor' => 0, 'total_minor' => 10000, 'tax_basis_points' => 0, 'shipping_name' => 'Test shipping']);
    }

    public function test_dashboard_counts_match_filtered_destinations_and_exclude_blocked_packing(): void
    {
        $cod = $this->order();
        $bank = $this->order('bank', 'proof_submitted');
        $paid = $this->order('bank', 'paid');
        $cancel = $this->order();
        $cancel->requests()->create(['submission_key' => Str::uuid(), 'type' => 'cancellation', 'status' => 'pending', 'reason' => 'Fixture']);
        $cod->requests()->create(['submission_key' => Str::uuid(), 'type' => 'return', 'status' => 'pending', 'reason' => 'Fixture']);
        $this->assertEqualsCanonicalizing([$cod->id, $paid->id], Dispatching::readyToPack()->pluck('id')->all());
        $cards = collect(app(Dashboard::class)->attention())->keyBy('label');
        $this->assertSame(1, $cards['Bank proofs awaiting review']['count']);
        $this->assertSame(2, $cards['Orders ready to pack']['count']);
        $this->assertSame(1, $cards['Orders with pending returns']['count']);
        foreach (['Bank proofs awaiting review' => [$bank], 'Orders with pending returns' => [$cod]] as $label => $expected) {
            parse_str(parse_url($cards[$label]['url'], PHP_URL_QUERY), $parameters);
            Livewire::withQueryParams($parameters)->test(ListOrders::class)->assertCountTableRecords(1)->assertCanSeeTableRecords($expected);
        }
        Livewire::withQueryParams(['stage' => 'packable'])->test(Dispatch::class)->assertCountTableRecords(2)->assertCanSeeTableRecords([$cod, $paid])->assertCanNotSeeTableRecords([$bank, $cancel]);
        Livewire::test(Dashboard::class)->assertSee('What needs attention today?');
        Gate::define('manage-commerce', fn (): bool => false);
        $this->assertSame([], app(Dashboard::class)->attention());
    }
}
