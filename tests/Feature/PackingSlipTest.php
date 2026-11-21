<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\CommerceSettings;
use App\Services\Inventory;
use App\Support\StoreSettings;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PackingSlipTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $this->assertContains(config("database.connections.$connection.database"), [':memory:', 'leather_store_variant_regression_test']);
    }

    public function test_two_sizes_print_saved_order_details_without_exposing_private_data(): void
    {
        $this->withoutVite();
        Queue::fake();
        StoreSettings::setBrandName('Demo Sialkot Fashion');
        $category = Category::create(['name' => 'Demo jackets', 'slug' => 'demo-jackets', 'is_active' => true]);
        $product = Product::create(['name' => 'Saved demo jacket', 'slug' => 'demo-jacket', 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 1850000]);
        $color = $product->colors()->create(['name' => 'Black']);
        $items = [];
        foreach (['M' => 1, 'L' => 2] as $size => $quantity) {
            $variant = $color->variants()->create(['size' => $size, 'sku' => 'DEMO-BLK-'.$size, 'is_active' => true]);
            app(Inventory::class)->adjust($variant->id, 5, 'Isolated packing fixture', null);
            $items[$variant->id] = $quantity;
        }
        CommerceSettings::save(['shipping_minor' => 0, 'tax_basis_points' => 0, 'cod_enabled' => true]);
        $quote = app(Cart::class)->quote($items, 'PKR');
        $order = app(Checkout::class)->create(hash('sha256', 'packing-demo'), (string) Str::uuid(), ['name' => 'Demo Recipient', 'email' => 'private-buyer@example.test', 'phone' => '03000000000', 'address' => "42 Demo Road\nUnit 3", 'city' => 'Lahore', 'region' => 'Punjab', 'postal_code' => '54000', 'country' => 'PK', 'payment_method' => 'cod'], ['items' => $items, 'fingerprint' => Cart::fingerprint($quote, CommerceSettings::totals($quote['subtotal']))]);
        $order->update(['proof_path' => 'private/secret-payment.jpg']);
        $order->histories()->create(['status' => 'pending', 'payment_status' => 'unpaid', 'note' => 'Secret internal packing note']);
        $product->update(['name' => 'Renamed catalog jacket']);
        $color->update(['name' => 'Renamed color']);
        $variant->update(['size' => 'Renamed size', 'sku' => 'RENAMED-SKU']);
        $url = route('orders.packing-slip', $order);
        $this->get($url)->assertForbidden();
        config(['app.env' => 'local']);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ViewOrder::class, ['record' => $order->id])->assertActionExists('printPackingSlip', fn (Action $action): bool => $action->getUrl() === $url && $action->shouldOpenUrlInNewTab());
        $response = $this->get($url)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee('Demo Sialkot Fashion')->assertSee($order->number)->assertSee('Demo Recipient')->assertSee('42 Demo Road')->assertSee('Unit 3')->assertSee('Lahore, Punjab, 54000')
            ->assertSee('DEMO-BLK-M')->assertSee('DEMO-BLK-L')->assertSee('Black')->assertSee('<td>M</td>', false)->assertSee('<td>L</td>', false)
            ->assertSee('Total units: <strong>3</strong>', false)->assertSee('window.print()', false)->assertSee('@media print', false)
            ->assertDontSee('Renamed catalog jacket')->assertDontSee('Renamed color')->assertDontSee('RENAMED-SKU')
            ->assertDontSee('secret-payment')->assertDontSee('Secret internal packing note')->assertDontSee('private-buyer@example.test')->assertDontSee($order->access_token);
        $this->assertSame(2, substr_count($response->getContent(), '<td>Saved demo jacket</td>'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('pending', $order->fresh()->status);
        config(['app.env' => 'production']);
        $this->get($url)->assertForbidden();
    }
}
