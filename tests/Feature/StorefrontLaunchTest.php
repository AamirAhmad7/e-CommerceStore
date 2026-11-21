<?php

namespace Tests\Feature;

use App\Filament\Pages\CommerceSettings as CommercePage;
use App\Filament\Pages\LaunchReadiness as ReadinessPage;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\SizeGuides\Pages\CreateSizeGuide;
use App\Jobs\OrderNotification;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Homepage;
use App\Models\Order;
use App\Models\Product;
use App\Models\SizeGuide;
use App\Models\User;
use App\Services\CommerceSettings;
use App\Services\Inventory;
use App\Support\LaunchReadiness;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StorefrontLaunchTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;

    protected function beforeRefreshingDatabase(): void
    {
        $c = config('database.default');
        $this->assertContains(config("database.connections.$c.database"), [':memory:', 'leather_store_variant_regression_test']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        Storage::fake(config('store.media_disk'));
        config(['mail.default' => 'array']);
        $category = Category::create(['name' => 'Jackets', 'slug' => 'jackets', 'is_active' => true]);
        $this->product = Product::create(['name' => 'Launch Jacket', 'slug' => 'launch-jacket', 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 100000, 'usd_price_minor' => 1900]);
        $black = $this->product->colors()->create(['name' => 'Black']);
        $variant = $black->variants()->create(['size' => 'M', 'sku' => 'LAUNCH-BLK-M', 'is_active' => true]);
        app(Inventory::class)->adjust($variant->id, 5, 'Test fixture', null);
        CommerceSettings::save(['shipping_minor' => 25000, 'tax_basis_points' => 0, 'cod_enabled' => true]);
    }

    private function guideData(): array
    {
        return ['unit' => 'cm', 'basis' => 'garment', 'note' => 'TEST ONLY: lay a jacket flat.', 'measurements' => [['label' => 'Chest', 'method' => 'flat_width', 'values' => [['size' => 'M', 'value' => '53'], ['size' => 'L', 'value' => '56']]]]];
    }

    private function staff(): void
    {
        config(['app.env' => 'local']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
    }

    public function test_owner_creates_reuses_and_overrides_guide_without_losing_existing_image_guides(): void
    {
        $this->staff();
        Livewire::test(CreateSizeGuide::class)->fillForm(['name' => 'Test jacket guide'] + $this->guideData())->call('create')->assertHasNoFormErrors();
        $guide = SizeGuide::sole();
        $image = $this->product->addMedia(UploadedFile::fake()->image('legacy-guide.jpg'))->withCustomProperties(['placement' => 'size_guide', 'alt' => 'Legacy measurement drawing'])->toMediaCollection('shared');
        $page = Livewire::test(EditProduct::class, ['record' => $this->product->id])->fillForm(['size_guide_id' => $guide->id, 'size_guide_mode' => 'shared', 'material' => 'Owner supplied material', 'care' => 'Owner supplied care'])->call('save')->assertHasNoFormErrors();
        $other = Product::create(['name' => 'Other jacket', 'slug' => 'other', 'category_id' => $this->product->category_id, 'is_active' => true, 'size_guide_id' => $guide->id]);
        $this->get('/products/launch-jacket')->assertOk()->assertSee('Garment measurements')->assertSee('Flat width (not circumference)')->assertSee('data-measure-value="53"', false)->assertSee('Legacy measurement drawing')->assertSee('Owner supplied care');
        $this->get('/products/other')->assertSee('data-measure-value="53"', false);
        $override = $this->guideData();
        $override['unit'] = 'in';
        $override['basis'] = 'body';
        $override['measurements'][0]['method'] = 'circumference';
        $override['measurements'][0]['values'][0]['value'] = '40';
        $page->fillForm(['size_guide_mode' => 'override', 'size_guide_override' => $override])->call('save')->assertHasNoFormErrors()->call('save')->assertHasNoFormErrors();
        $this->get('/products/launch-jacket')->assertSee('Body measurements')->assertSee('Full circumference')->assertSee('data-measure-value="40"', false);
        $this->assertSame('cm', $guide->fresh()->unit);
        $this->assertSame($image->id, $this->product->fresh()->getFirstMedia('shared')->id);
        $page->fillForm(['size_guide_mode' => 'shared'])->call('save')->assertHasNoFormErrors();
        $this->get('/products/launch-jacket')->assertSee('data-measure-value="53"', false);
    }

    public function test_policy_content_approval_and_window_follow_commerce_settings(): void
    {
        $this->get('/delivery-and-returns')->assertOk()->assertSee('not currently offered')->assertDontSee('14 days');
        $settings = array_replace(CommerceSettings::get(), ['delivery_information' => 'Owner delivery note', 'returns_information' => 'Owner return conditions', 'returns_enabled' => true, 'return_days' => 21]);
        CommerceSettings::save($settings);
        $this->get('/delivery-and-returns')->assertSee('Owner delivery note')->assertDontSee('21 days')->assertDontSee('Owner return conditions');
        $this->staff();
        Livewire::test(CommercePage::class)->set('data.returns_policy_approved', true)->call('save')->assertHasNoErrors();
        $this->get('/delivery-and-returns')->assertSee('21 days')->assertSee('Owner return conditions')->assertSee('PKR');
        CommerceSettings::save(array_replace(CommerceSettings::get(), ['returns_enabled' => false]));
        $this->get('/delivery-and-returns')->assertDontSee('21 days');
    }

    public function test_readiness_is_advisory_efficient_and_distinguishes_sold_out_from_missing_price(): void
    {
        $before = DB::table('store_settings')->get()->toJson();
        $this->get('/admin/launch-readiness')->assertRedirect();
        $this->staff();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $findings = collect(LaunchReadiness::findings());
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(15, $queryCount);
        $this->assertSame('Ready', $findings->firstWhere('title', 'Published purchasable products')['status']);
        $this->assertSame('Needs configuration', $findings->firstWhere('title', 'Published homepage')['status']);
        $this->assertSame('Manual check', $findings->firstWhere('title', 'Backups')['status']);
        Livewire::test(ReadinessPage::class)->assertOk()->assertSee('Customer contact')->assertSee('WhatsApp is optional');
        $this->assertSame($before, DB::table('store_settings')->get()->toJson());
        $this->assertSame(0, Homepage::count());
        $variant = $this->product->variants()->first();
        app(Inventory::class)->adjust($variant->id, -5, 'Test sold out', null);
        $findings = collect(LaunchReadiness::findings());
        $this->assertSame('Ready', $findings->firstWhere('title', 'PKR prices')['status']);
        $this->assertStringContainsString('1 are out of stock', $findings->firstWhere('title', 'Inventory')['detail']);
        $this->assertSame('Needs configuration', $findings->firstWhere('title', 'Published purchasable products')['status']);
        $this->product->update(['pkr_price_minor' => null]);
        $this->assertSame('Needs configuration', collect(LaunchReadiness::findings())->firstWhere('title', 'PKR prices')['status']);
    }

    public function test_fallback_homepage_uses_only_public_catalog_and_pending_photos_are_real_thumbnails(): void
    {
        $photo = $this->product->colors->first()->addMedia(UploadedFile::fake()->image('front.jpg'))->withCustomProperties(['alt' => 'Public jacket image'])->toMediaCollection('gallery');
        $this->get('/')->assertOk()->assertSee('Public jacket image')->assertSee('Shop all products')->assertSee('Launch Jacket');
        $r = $this->get('/products/launch-jacket')->assertOk()->assertDontSee('<span>Photo 1</span>', false)->assertDontSee('<summary>Size guide</summary>', false);
        $this->assertSame($photo->getUrl(), $r->viewData('selected')['photos'][0]['thumbnail']);
        $this->product->update(['is_active' => false]);
        $this->get('/')->assertDontSee('Public jacket image')->assertDontSee('Launch Jacket');
    }

    public function test_home_collection_cart_cod_email_admin_and_order_recovery_agree(): void
    {
        $guide = SizeGuide::create(['name' => 'Test guide'] + $this->guideData());
        $this->product->update(['size_guide_id' => $guide->id]);
        $collection = Collection::create(['title' => 'Test collection', 'slug' => 'test-collection', 'is_published' => true]);
        $collection->items()->create(['product_id' => $this->product->id]);
        Homepage::create(['key' => 'main', 'published' => ['headline' => 'Test home', 'featured' => [['collection_id' => $collection->id]], 'primary_target' => 'collection', 'primary_collection_id' => $collection->id]]);
        $this->get('/')->assertOk()->assertSee('/collections/test-collection');
        $variant = $this->product->variants()->first();
        $this->get('/collections/test-collection?utm_source=test')->assertOk()->assertSee('Launch Jacket')->assertSee('data-share-url="'.rtrim(config('app.url'), '/').'/collections/test-collection"', false);
        $this->get('/products/launch-jacket?color='.$variant->product_color_id.'&size=M&currency=USD')->assertOk()->assertSee('Size guide')->assertSee('USD 19.00');
        $this->post('/cart', ['product_id' => $this->product->id, 'variant_id' => $variant->id, 'quantity' => 1])->assertRedirect(route('cart.show'));
        $this->patch('/cart/'.$variant->id, ['quantity' => 2])->assertRedirect(route('cart.show'));
        $this->get('/cart')->assertSee('(2)')->assertSee('USD 38.00')->assertSee('PKR');
        $checkout = $this->get('/checkout')->assertOk()->assertSee('PKR 2250.00');
        $key = $checkout->viewData('key');
        $data = ['checkout_key' => $key, 'name' => 'Demo Buyer', 'email' => 'buyer@example.test', 'phone' => '03000000000', 'address' => 'Test street', 'city' => 'Lahore', 'region' => 'Punjab', 'country' => 'PK', 'payment_method' => 'cod', 'reviewed' => 1];
        $this->post('/checkout', $data)->assertRedirect();
        $order = Order::sole();
        $line = $order->lines()->sole();
        $this->assertSame('Black', $line->color);
        $this->assertSame('M', $line->size);
        $this->assertEquals(2, $line->quantity);
        $this->assertEquals(225000, $order->total_minor);
        $this->assertSame('unpaid', $order->payment_status);
        $this->get(route('orders.show', $order->access_token))->assertOk()->assertSee('PKR 2250.00')->assertSee('LAUNCH-BLK-M');
        (new OrderNotification($order->id))->handle();
        $mail = Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage()->getTextBody();
        $this->assertStringContainsString('LAUNCH-BLK-M', $mail);
        $this->assertStringContainsString('PKR 2250.00', $mail);
        $this->assertStringContainsString(route('orders.show', $order->access_token), $mail);
        $this->app['session']->flush();
        $this->from('/find-order')->post('/find-order', ['number' => $order->number, 'email' => 'buyer@example.test'])->assertRedirect('/find-order');
        $event = $order->events()->where('event_key', 'like', 'lookup-%')->sole();
        (new OrderNotification($order->id, $event->id))->handle();
        $mail = Mail::mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage()->getTextBody();
        $this->assertStringContainsString(route('orders.show', $order->access_token), $mail);
        $this->get(route('orders.show', $order->access_token))->assertSee('unpaid');
        $this->staff();
        Livewire::test(ViewOrder::class, ['record' => $order->id])->assertOk()->assertSee('LAUNCH-BLK-M')->assertSee('2250.00');
    }

    public function test_changed_stock_rejects_checkout_without_order_and_offers_cart_review(): void
    {
        $variant = $this->product->variants()->first();
        $this->post('/cart', ['product_id' => $this->product->id, 'variant_id' => $variant->id, 'quantity' => 1]);
        $key = $this->get('/checkout')->viewData('key');
        app(Inventory::class)->adjust($variant->id, -5, 'Test concurrent stock change', null);
        $this->from('/checkout')->post('/checkout', ['checkout_key' => $key, 'name' => 'Buyer', 'email' => 'buyer@example.test', 'phone' => '03000000000', 'address' => 'Street', 'city' => 'Lahore', 'region' => 'Punjab', 'country' => 'PK', 'payment_method' => 'cod', 'reviewed' => 1])->assertSessionHasErrors('checkout');
        $this->assertSame(0,Order::count());
        $this->get('/cart')->assertSee('This item cannot be ordered')->assertSee('Remove it or reduce');
        $this->get('/products/launch-jacket?size=M')->assertSee('Sold out')->assertSee('id="cart-submit" disabled',false);
    }
}
