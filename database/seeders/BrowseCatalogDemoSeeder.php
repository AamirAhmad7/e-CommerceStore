<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\SizeGuide;
use App\Services\Inventory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class BrowseCatalogDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::connection()->getDatabaseName() !== 'leather_store_demo' || config('store.media_disk') !== 'demo' || config('mail.default') !== 'log') {
            throw new \RuntimeException('This sample catalog can only be added to the isolated demo store.');
        }
        $photos = Media::where('disk', 'demo')->where('collection_name', 'gallery')->orderBy('id')->get()->filter(fn (Media $media): bool => is_file($media->getPath()))->take(6)->values();
        if ($photos->isEmpty()) {
            throw new \RuntimeException('Add a local demo gallery image before creating sample products.');
        }
        config(['media-library.queue_connection_name' => 'sync']);
        $guide = SizeGuide::where('name', 'like', 'DEMO%')->first();
        $collection = Collection::firstOrCreate(['slug' => 'sample-jacket-edit'], ['title' => 'Sample jacket edit', 'is_published' => true, 'introduction' => 'Nine fictional products for testing browsing, filters and related products. Images are reused demo photographs, not exact product or color representations.']);
        $groups = [
            'Leather jackets' => [['Urban Biker', 12500], ['Heritage Rider', 16500], ['Classic Leather Bomber', 14900]],
            'Windbreakers' => [['City Windbreaker', 6500], ['Trail Shell', 7800], ['Everyday Wind Jacket', 5900]],
            'Winter jackets' => [['Alpine Winter Jacket', 11900], ['Weekend Puffer', 13900], ['Summit Parka', 17900]],
        ];
        $index = 0;
        foreach ($groups as $categoryName => $products) {
            $category = Category::firstOrCreate(['slug' => 'sample-'.Str::slug($categoryName)], ['name' => 'Sample '.$categoryName, 'is_active' => true, 'description' => 'Fictional demo category.']);
            foreach ($products as [$name, $price]) {
                $index++;
                $product = Product::firstOrCreate(['slug' => 'sample-'.Str::slug($name)], ['name' => $name.' (Sample)', 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => $price * 100, 'purchase_cost_minor' => (int) ($price * 55), 'delivery_charge_minor' => $index % 3 === 0 ? 0 : 35000, 'size_guide_id' => $guide?->id, 'description' => 'Fictional product for storefront testing only. Reused demo photos do not represent this exact design or color. Sample stock, prices and measurements are not real offers.', 'fit' => 'Sample regular fit', 'care' => 'Demo care information; confirm actual instructions before selling.']);
                foreach (['Black' => '#242424', 'Brown' => '#795548'] as $colorName => $hex) {
                    $color = $product->colors()->firstOrCreate(['name' => $colorName], ['swatch_hex' => $hex, 'sort_order' => $colorName === 'Black' ? 0 : 1]);
                    foreach (['S', 'M', 'L', 'XL'] as $sizeIndex => $size) {
                        $variant = $color->variants()->firstOrCreate(['size' => $size], ['sku' => 'SAMPLE-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).'-'.($colorName === 'Black' ? 'BLK' : 'BRN').'-'.$size, 'is_active' => true]);
                        if ($variant->on_hand === null) {
                            $quantity = $size === 'XL' || ($colorName === 'Brown' && $index % 3 === 0) ? 0 : 2 + (($index + $sizeIndex) % 6);
                            app(Inventory::class)->adjust($variant->id, $quantity, 'Opening sample catalog stock - demo only', null);
                        }
                    }
                    if (! $color->hasMedia('gallery')) {
                        $source = $photos[($index - 1) % $photos->count()];
                        $copy = $source->copy($color, 'gallery', 'demo');
                        $copy->setCustomProperty('alt', 'Reused demo photograph for '.$name.'; sample image only')->save();
                    }
                }
                $collection->items()->firstOrCreate(['product_id' => $product->id], ['sort_order' => $index]);
                $this->command?->line(route('products.show', $product->slug));
            }
        }
        $this->command?->info('Ready: '.route('collections.show', $collection->slug).' (9 products, 3 categories, 72 size/color combinations). Existing demo edits and stock are preserved on reruns.');
    }
}
