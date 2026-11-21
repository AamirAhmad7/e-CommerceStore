<?php

namespace App\Console\Commands;

use App\Models\Collection;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\Inventory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class StoreSmokeFixture extends Command
{
    protected $signature = 'store:smoke-fixture';

    protected $description = 'Prepare a browser smoke manifest using isolated demo fixtures only';

    public function handle(): int
    {
        if (DB::connection()->getDatabaseName() !== 'leather_store_demo' || config('store.media_disk') !== 'demo' || config('mail.default') !== 'log' || rtrim(config('app.url'), '/') !== 'http://127.0.0.1:8001') {
            throw new \RuntimeException('Only the isolated localhost demo with logged mail is allowed.');
        }
        $this->call('db:seed', ['--class' => 'StorefrontLaunchDemoSeeder', '--no-interaction' => true]);
        $product = Product::where('slug', 'demo-launch-jacket')->with('colors.variants', 'colors.media')->firstOrFail();
        $collection = Collection::where('slug', 'demo-launch-jackets')->where('is_published', true)->firstOrFail();
        $variants = $product->colors->flatMap->variants;
        $available = $variants->first(fn ($v) => Inventory::purchasable($v, 'PKR', $product));
        $soldOut = $variants->first(fn ($v) => $v->is_active && $v->on_hand !== null && Inventory::available($v) === 0);
        if (! $available || ! $soldOut || ! $product->size_guide_id || ! $product->colors->contains(fn ($c) => $c->hasMedia('gallery'))) {
            throw new \RuntimeException('Demo needs an available SKU, sold-out SKU, guide and photo. Existing stock is not reset automatically.');
        }
        $offer = Promotion::firstOrCreate(['code' => 'SMOKE10'], ['name' => 'Isolated browser smoke offer', 'automatic' => false, 'type' => 'percentage', 'percent_basis_points' => 1000, 'scope' => 'products', 'target_ids' => [$product->id], 'status' => 'active']);
        if ($offer->availability()) {
            throw new \RuntimeException('SMOKE10 is unavailable; review this demo offer before the smoke journey.');
        }
        $manifest = ['origin' => 'http://127.0.0.1:8001', 'database' => 'leather_store_demo', 'generated_at' => now()->toIso8601String(), 'collection' => '/collections/'.$collection->slug, 'product' => '/products/'.$product->slug, 'color' => (string) $available->product_color_id, 'size' => $available->size, 'sold_color' => (string) $soldOut->product_color_id, 'sold_size' => $soldOut->size, 'code' => $offer->code];
        File::put(storage_path('app/private/demo-smoke.json'), json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info('Ready: storage/app/private/demo-smoke.json. Browser smoke places one fictional COD order; no stock or existing orders were reset.');

        return self::SUCCESS;
    }
}
