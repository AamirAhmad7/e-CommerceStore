<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\CommerceSettings;
use App\Services\Fulfillment;
use App\Services\Inventory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OrderCareDemoSeeder extends Seeder
{
    public function run(): void
    {
        $connection = config('database.default');
        if (config("database.connections.$connection.database") !== 'leather_store_demo') {
            throw new \RuntimeException('Only leather_store_demo is allowed.');
        }
        if (! Product::where('slug', 'demo-biker-jacket')->exists()) {
            $this->call(CommerceDemoSeeder::class);
        }
        CommerceSettings::save(array_replace(CommerceSettings::get(), ['returns_enabled' => true, 'return_days' => 14, 'contact_email' => 'demo@example.test']));
        $product = Product::where('slug', 'demo-biker-jacket')->with('colors.variants')->sole();
        $black = $product->colors->firstWhere('name', 'Black')->variants->firstWhere('size', 'M');
        $brown = $product->colors->firstWhere('name', 'Brown')->variants->firstWhere('size', 'L');
        $actor = User::where('email', 'demo@example.test')->sole()->id;
        foreach (['DEMO-CARE-COD' => ['cod', [$black->id => 1]], 'DEMO-CARE-TRANSFER' => ['bank', [$brown->id => 1]], 'DEMO-CARE-RETURN' => ['cod', [$black->id => 2, $brown->id => 1]]] as $number => [$method,$items]) {
            $order = Order::where('number', $number)->first();
            if (! $order) {
                $order = DB::transaction(function () use ($number, $method, $items, $actor): Order {
                    foreach ($items as $id => $quantity) {
                        $variant = ProductVariant::lockForUpdate()->findOrFail($id);
                        $missing = $quantity - Inventory::available($variant);
                        if ($missing > 0) {
                            app(Inventory::class)->adjust($id, $missing, 'DEMO ONLY service fixture top-up', $actor);
                        }
                    }
                    $quote = app(Cart::class)->quote($items);
                    $order = app(Checkout::class)->create(hash('sha256', $number), (string) Str::uuid(), ['name' => $number.' test customer', 'email' => 'demo-buyer@example.test', 'phone' => '03000000000', 'address' => 'DEMO ONLY - no delivery', 'city' => 'Sialkot', 'region' => 'Punjab', 'country' => 'PK', 'payment_method' => $method], ['items' => $items, 'fingerprint' => Cart::fingerprint($quote, CommerceSettings::totals($quote['subtotal']))]);
                    $oldNumber = $order->number;
                    $order->update(['number' => $number]);
                    $confirmation = $order->events()->where('event_key', 'confirmation')->sole();
                    $confirmation->update(['subject' => str_replace($oldNumber, $number, $confirmation->subject), 'body' => str_replace($oldNumber, $number, $confirmation->body)]);
                    if ($number === 'DEMO-CARE-TRANSFER') {
                        $path = 'demo-bank-proofs/care-proof.png';
                        $image = imagecreatetruecolor(40, 40);
                        ob_start();
                        imagepng($image);
                        $bytes = ob_get_clean();
                        imagedestroy($image);
                        Storage::disk('local')->put($path, $bytes);
                        $order->update(['proof_path' => $path, 'payment_status' => 'proof_submitted', 'expires_at' => now()->addDays(7)]);
                        $order->histories()->create(['status' => 'pending', 'payment_status' => 'proof_submitted', 'note' => 'DEMO ONLY test proof; no money received.']);
                    }
                    if ($number === 'DEMO-CARE-RETURN') {
                        foreach (['confirmed', 'processing', 'shipped', 'delivered'] as $state) {
                            app(Fulfillment::class)->transition($order->id, $state, $actor);
                        }
                        app(Fulfillment::class)->verifyPayment($order->id, $actor, 'DEMO ONLY fictitious COD collection');
                    }

                    return $order->fresh();
                });
            }
            $this->command?->line($number.' | '.route('filament.admin.resources.orders.view', $order).' | '.route('orders.show',$order->access_token));
        }
        $this->command?->info('DEMO ONLY: 14-day return window. Messages are captured with log transport; no real email or money movement. Existing fixtures were not reset.');
    }
}
