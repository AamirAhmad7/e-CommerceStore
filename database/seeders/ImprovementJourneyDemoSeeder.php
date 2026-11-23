<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\CommerceSettings;
use App\Services\InternationalInquiry;
use App\Services\Inventory;
use App\Services\Promotions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ImprovementJourneyDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (config('database.connections.'.config('database.default').'.database') !== 'leather_store_demo' || config('store.media_disk') !== 'demo' || config('mail.default') !== 'log') {
            throw new \RuntimeException('This journey requires the isolated demo database, demo media disk and log mailer.');
        }
        $category = Category::firstOrCreate(['slug' => 'demo-owner-workflow'], ['name' => 'Demo owner workflow', 'is_active' => true]);
        $product = Product::firstOrCreate(['slug' => 'demo-workflow-jacket'], [
            'name' => 'Demo Workflow Jacket', 'category_id' => $category->id, 'is_active' => true,
            'pkr_price_minor' => 1200000, 'usd_price_minor' => 15000, 'delivery_charge_minor' => 25000,
            'description' => 'Fictional jacket for isolated workflow acceptance. No real purchase or shipment.',
            'material' => 'Demo leather specification', 'lining' => 'Demo polyester lining', 'fit' => 'Regular', 'care' => 'Demo care information: consult the supplied garment label.',
        ]);
        foreach (['Black' => '#222222', 'Brown' => '#805538'] as $name => $hex) {
            $color = $product->colors()->firstOrCreate(['name' => $name], ['swatch_hex' => $hex]);
            foreach (['M', 'L'] as $size) {
                $variant = $color->variants()->firstOrCreate(['sku' => 'DEMO-WORKFLOW-'.strtoupper($name).'-'.$size], ['size' => $size, 'is_active' => true]);
                if ($variant->wasRecentlyCreated) {
                    app(Inventory::class)->adjust($variant->id, $name === 'Black' ? 6 : 0, 'Isolated owner journey opening stock', null);
                }
            }
        }
        $black = $product->colors()->where('name', 'Black')->firstOrFail();
        $source = Product::where('slug', 'demo-launch-jacket')->first();
        if ($source && ! $product->size_guide_id) {
            $product->update(['size_guide_id' => $source->size_guide_id]);
        }
        if ($black->getMedia('gallery')->isEmpty()) {
            $sourceColor = $source?->colors()->first();
            foreach ($sourceColor?->getMedia('gallery') ?? [] as $photo) {
                $photo->copy($black, 'gallery', 'demo');
            }
        }
        $blocked = $black->variants()->firstOrCreate(['sku' => 'DEMO-WORKFLOW-BLACK-XL'], ['size' => 'XL', 'is_active' => true, 'pkr_price_minor' => 0]);
        if ($blocked->wasRecentlyCreated) {
            app(Inventory::class)->adjust($blocked->id, 3, 'Demo price-fix exercise', null);
        }
        $settings = CommerceSettings::get();
        $settings['cod_enabled'] = true;
        CommerceSettings::save($settings);
        $items = $black->variants()->whereIn('size', ['M', 'L'])->pluck('id')->mapWithKeys(fn ($id): array => [$id => 1])->all();
        ksort($items);
        $order = Order::where('customer->email', 'workflow-demo@example.test')->first();
        if (! $order) {
            $quote = app(Cart::class)->quote($items);
            $promotion = app(Promotions::class)->resolve($quote, CommerceSettings::get());
            $order = app(Checkout::class)->create(hash('sha256', 'isolated-workflow-demo'), (string) Str::uuid(), [
                'name' => 'Demo Buyer', 'email' => 'workflow-demo@example.test', 'phone' => '03000000000',
                'address' => 'Demo house, Example Street', 'city' => 'Sialkot', 'region' => 'Punjab', 'country' => 'PK', 'payment_method' => 'cod',
            ], ['items' => $items, 'fingerprint' => Cart::fingerprint($quote, $promotion['totals']), 'promotion_snapshot' => $promotion['snapshot']]);
        }
        $shipping = InternationalInquiry::shipping(30000, 2, 'United Kingdom', CommerceSettings::get());
        $this->command->info('Demo category: '.$category->id.'; product: '.$product->id.'; order: '.$order->id.' / '.$order->number);
        $this->command->info('Demo PKR order total: '.$order->total_minor.' minor units. Two-jacket international shipping: '.json_encode($shipping));
        $this->command->info('Try /products/demo-workflow-jacket and /admin/products/'.$product->id.'/edit and /admin/orders/'.$order->id);
    }
}
