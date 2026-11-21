<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseReceiving;
use App\Services\Purchasing;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchasingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $connection = config('database.default');
        if (config("database.connections.$connection.database") !== 'leather_store_demo' || config('mail.default') !== 'log') {
            throw new \RuntimeException('Purchasing fixtures require leather_store_demo and MAIL_MAILER=log.');
        }
        auth()->login(User::where('email', 'demo@example.test')->firstOrFail());
        DB::transaction(function (): void {
            if (PurchaseOrder::where('reference', 'DEMO-PO-JACKETS-001')->exists()) {
                return;
            }
            $category = Category::firstOrCreate(['slug' => 'demo-purchasing'], ['name' => 'Demo purchasing', 'is_active' => true]);
            $product = Product::firstOrCreate(['slug' => 'demo-purchasing-jacket'], ['name' => 'Demo Purchasing Jacket', 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 950000, 'description' => 'Fictitious purchasing example.']);
            $supplier = Supplier::firstOrCreate(['name' => 'Example Sialkot Jacket Workshop'], ['contact_person' => 'Demo Supplier', 'email' => 'supplier@example.test', 'city' => 'Sialkot', 'notes' => 'Fictitious training supplier. No real outreach.', 'is_active' => true]);
            $lines = [];
            foreach (['Black', 'Brown'] as $colorName) {
                $color = $product->colors()->firstOrCreate(['name' => $colorName]);
                foreach (['S', 'M', 'L'] as $size) {
                    $variant = $color->variants()->firstOrCreate(['sku' => 'DEMO-BUY-'.strtoupper($colorName).'-'.$size], ['size' => $size, 'is_active' => true]);
                    if ($colorName === 'Black' && $size === 'M') {
                        $variant->update(['reorder_point' => 5, 'reorder_target' => 12]);
                    }
                    app(Purchasing::class)->source(['supplier_id' => $supplier->id, 'product_variant_id' => $variant->id, 'supplier_sku' => 'EX-'.$colorName.'-'.$size, 'pack_size' => 2, 'minimum_quantity' => 4, 'lead_days' => 7, 'is_preferred' => true, 'quote' => '3500']);
                    $lines[] = ['variant_id' => $variant->id, 'quantity' => $colorName === 'Black' && $size === 'M' ? 5 : 2, 'cost' => '3400'];
                }
            }
            $order = app(Purchasing::class)->save(['supplier_id' => $supplier->id, 'expected_at' => today()->subDay()->format('Y-m-d'), 'notes' => 'Isolated training purchase; do not contact a supplier.', 'freight' => '500', 'lines' => $lines]);
            $order->update(['reference' => 'DEMO-PO-JACKETS-001']);
            $order = app(Purchasing::class)->issue($order->id, $order->version);
            $line = $order->lines()->where('sku', 'DEMO-BUY-BLACK-M')->firstOrFail();
            $rows = [$line->id => ['accepted' => 2, 'damaged_replace' => 1, 'cost' => '3400']];
            $plan = app(PurchaseReceiving::class)->preview($order->id, PurchaseReceiving::baseline($order), $rows);
            app(PurchaseReceiving::class)->post($order->id, (string) Str::uuid(), $plan['token'], $rows, 'DEMO-CARTON-001', 'One damaged M jacket; supplier replacement still expected.');
        });
        $this->command?->info('Open /admin/purchase-orders: DEMO-PO-JACKETS-001. Black M: 2 available + 3 incoming; reorder suggestion 8.');
    }
}
