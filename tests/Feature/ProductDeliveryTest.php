<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CommerceSettings;
use App\Services\Inventory;
use App\Services\Pricing;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ProductDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame(':memory:', config('database.connections.'.config('database.default').'.database'));
    }

    public function test_product_delivery_uses_highest_once_and_rechecks_changes_before_ordering(): void
    {
        $this->withoutVite();
        Queue::fake();
        CommerceSettings::save(['shipping_minor' => null, 'cod_enabled' => true]);
        $category = Category::create(['name' => 'Jackets', 'slug' => 'jackets', 'is_active' => true]);
        $products = [];
        foreach ([25000, 40000] as $index => $delivery) {
            $product = Product::create(['name' => 'Jacket '.$index, 'slug' => 'jacket-'.$index, 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 100000, 'delivery_charge_minor' => $delivery]);
            $color = $product->colors()->create(['name' => 'Black']);
            $variant = $color->variants()->create(['size' => 'M', 'sku' => 'J-'.$index, 'is_active' => true]);
            app(Inventory::class)->adjust($variant->id, 4, 'Test stock', null);
            $this->get('/products/'.$product->slug)->assertOk()->assertSee('id="add-to-cart"', false);
            $this->get('/products/'.$product->slug)->assertSee('Delivery '.Pricing::format($delivery));
            $this->post('/cart', ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 2])->assertRedirect()->assertSessionHasNoErrors();
            $products[] = $product;
        }
        $this->get('/cart')->assertSee('Proceed to checkout');
        $response = $this->get('/checkout')->assertOk();
        $this->assertSame(40000, $response->viewData('totals')['shipping_minor']);
        $data = ['checkout_key' => $response->viewData('key'), 'name' => 'Buyer', 'email' => 'buyer@example.test', 'phone' => '03001234567', 'address' => 'Test street', 'city' => 'Sialkot', 'region' => 'Punjab', 'country' => 'PK', 'payment_method' => 'cod', 'reviewed' => 1];
        $products[1]->update(['delivery_charge_minor' => 50000]);
        $this->post('/checkout', $data)->assertSessionHasErrors('checkout');
        $this->assertSame(0, Order::count());
        $data['checkout_key'] = $this->get('/checkout')->viewData('key');
        $this->post('/checkout', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(50000, Order::sole()->shipping_minor);
        $this->assertSame(450000, Order::sole()->total_minor);
    }

    public function test_product_field_saves_free_delivery_and_blank_restores_store_default(): void
    {
        $this->withoutVite();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $category = Category::create(['name' => 'Jackets', 'slug' => 'jackets']);
        $product = Product::create(['name' => 'Jacket', 'slug' => 'jacket', 'category_id' => $category->id]);
        $page = Livewire::test(EditProduct::class, ['record' => $product->id]);
        $page->set('data.delivery_charge_minor', '250')->call('save')->assertHasNoFormErrors();
        $this->assertEquals(25000, $product->fresh()->delivery_charge_minor);
        $page->set('data.delivery_charge_minor', '0')->call('save')->assertHasNoFormErrors();
        $this->assertEquals(0, $product->fresh()->delivery_charge_minor);
        $page->set('data.delivery_charge_minor', '')->call('save')->assertHasNoFormErrors();
        $this->assertNull($product->fresh()->delivery_charge_minor);
        $quote = ['lines' => [['product' => $product->fresh(), 'quantity' => 3]]];
        $settings = CommerceSettings::forQuote($quote, array_replace(CommerceSettings::defaults(), ['shipping_minor' => 30000, 'free_shipping_minor' => 200000]));
        $this->assertSame(30000, CommerceSettings::totals(100000, $settings)['shipping_minor']);
        $this->assertSame(0, CommerceSettings::totals(200000, $settings)['shipping_minor']);
    }
}
