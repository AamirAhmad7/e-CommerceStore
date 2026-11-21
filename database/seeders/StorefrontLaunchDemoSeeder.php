<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Homepage;
use App\Models\Product;
use App\Models\ProductColor;
use App\Models\SizeGuide;
use App\Services\CommerceSettings;
use App\Services\Inventory;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class StorefrontLaunchDemoSeeder extends Seeder
{
    public function run(): void
    {
        $connection = config('database.default');
        if (config("database.connections.$connection.database") !== 'leather_store_demo' || config('store.media_disk') !== 'demo') {
            throw new \RuntimeException('Only isolated demo database and media disk are allowed.');
        }
        $guide = SizeGuide::firstOrCreate(['name' => 'DEMO ONLY - jacket measurement example'], ['unit' => 'cm', 'basis' => 'garment', 'note' => 'DEMO measurements only, not a real size recommendation. Lay a comparable jacket flat without stretching. Chest is measured straight across under the armholes; it is not a full circumference.', 'measurements' => [
            ['label' => 'Chest', 'method' => 'flat_width', 'values' => [['size' => 'S', 'value' => 50], ['size' => 'M', 'value' => 53], ['size' => 'L', 'value' => 56], ['size' => 'XL', 'value' => 59]]],
            ['label' => 'Back length', 'method' => 'length', 'values' => [['size' => 'S', 'value' => 62], ['size' => 'M', 'value' => 64], ['size' => 'L', 'value' => 66], ['size' => 'XL', 'value' => 68]]],
        ]]);
        $category = Category::firstOrCreate(['slug' => 'demo-jackets'], ['name' => 'Demo jackets', 'is_active' => true]);
        $launch = Product::firstOrCreate(['slug' => 'demo-launch-jacket'], ['name' => 'DEMO Launch Jacket', 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 1290000, 'usd_price_minor' => 4900, 'size_guide_id' => $guide->id, 'description' => 'Isolated shopping demonstration. Photos are copied for local testing; measurements and stock are test fixtures, not real product specifications.', 'fit' => 'DEMO ONLY: compare the example garment measurements with a jacket you own.']);
        foreach (['Black', 'Brown'] as $position => $name) {
            $color = $launch->colors()->firstOrCreate(['name' => $name], ['sort_order' => $position, 'swatch_hex' => $name === 'Black' ? '#181818' : '#75543a']);
            foreach (['S', 'M', 'L', 'XL'] as $size) {
                $variant = $color->variants()->firstOrCreate(['size' => $size], ['sku' => 'LAUNCH-'.strtoupper($name).'-'.$size, 'is_active' => true]);
                if ($variant->on_hand === null) {
                    app(Inventory::class)->adjust($variant->id, $size === 'XL' ? 0 : 8, 'Isolated launch demo opening fixture', null);
                }
            }
            $this->copyCatalogPhoto($color, $name);
        }
        $batch = Product::firstOrCreate(['slug' => 'demo-size-setup-jacket'], ['name' => 'DEMO Size Setup Jacket', 'category_id' => $category->id, 'is_active' => false, 'size_guide_id' => $guide->id]);
        $black = $batch->colors()->firstOrCreate(['name' => 'Black']);
        foreach (['M', 'L'] as $size) {
            $black->variants()->firstOrCreate(['size' => $size], ['sku' => 'DSSJ-BLK-'.$size, 'is_active' => true]);
        }
        $batch->colors()->firstOrCreate(['name' => 'Brown']);
        if (! $black->hasMedia('gallery')) {
            $photo = $launch->colors()->where('name', 'Black')->first()->getFirstMedia('gallery');
            if ($photo) {
                $photo->copy($black, 'gallery', 'demo');
            }
        }
        $collection = Collection::firstOrCreate(['slug' => 'demo-launch-jackets'], ['title' => 'Jackets to explore', 'is_published' => true, 'introduction' => 'An isolated shopping demo. Explore color, measurements and Pakistan checkout.']);
        if ($collection->wasRecentlyCreated) {
            $collection->items()->create(['product_id' => $launch->id, 'featured_color_id' => $launch->colors()->where('name', 'Black')->value('id')]);
            foreach (Product::whereIn('slug', ['demo-campaign-vest', 'demo-campaign-coat'])->get() as $i => $product) {
                $collection->items()->create(['product_id' => $product->id, 'sort_order' => $i + 1]);
            }
        }
        if (! DB::table('store_settings')->where('key', 'launch_demo_prepared')->exists()) {
            CommerceSettings::save(array_replace(CommerceSettings::get(), ['shipping_minor' => 25000, 'tax_basis_points' => 0, 'cod_enabled' => true, 'contact_email' => 'demo-care@example.test', 'delivery_information' => 'DEMO ONLY: example delivery information for reviewing the customer journey. No real dispatch or delivery commitment is made.', 'returns_information' => 'DEMO ONLY: describe your approved item condition and return process here before a real launch. Contact the store before sending an item.', 'returns_enabled' => true, 'return_days' => 14, 'returns_policy_approved' => true]));
            $home = Homepage::firstOrCreate(['key' => 'main']);
            $home->update(['draft' => ['headline' => 'Find your next favourite jacket', 'supporting_text' => 'Explore the details. Choose your color. Find your fit. This is a local demo; no real payments or deliveries.', 'primary_target' => 'collection', 'primary_collection_id' => $collection->id, 'featured' => [['collection_id' => $collection->id]], 'hero_alt' => 'Black jacket - isolated catalog demonstration']]);
            $photo = $launch->colors()->where('name', 'Black')->first()->getFirstMedia('gallery');
            if ($photo) {
                $photo->copy($home, 'draft_hero', 'demo');
            }
            $home->publishDraft();
            DB::table('store_settings')->insert(['key' => 'launch_demo_prepared', 'value' => '1']);
        }
        $this->command?->line('Shopping: '.route('products.show', $launch->slug));
        $this->command?->line('Batch manual check: '.route('filament.admin.resources.products.edit', $batch));
        $this->command?->line('Collection: '.route('collections.show', $collection->slug));
        $this->command?->info('Demo only. Existing fixture edits and orders retained; run the media queue.');
    }

    private function copyCatalogPhoto(ProductColor $destination, string $color): void
    {
        if ($destination->hasMedia('gallery')) {
            return;
        }
        $settings = config('database.connections.mysql');
        $settings['database'] = 'leather_store';
        config(['database.connections.catalog_read' => $settings]);
        try {
            $source = DB::connection('catalog_read')->table('media as m')->join('product_colors as c', 'c.id', '=', 'm.model_id')->join('products as p', 'p.id', '=', 'c.product_id')->where('m.model_type', ProductColor::class)->where('m.collection_name', 'gallery')->where('p.slug', 'classic-biker-jacket')->where('c.name', $color)->orderBy('m.order_column')->select('m.id', 'm.file_name', 'm.custom_properties')->first();
            if ($source) {
                $path = storage_path('app/public/'.$source->id.'/'.basename($source->file_name));
                if (is_file($path)) {
                    $destination->addMedia($path)->preservingOriginal()->withCustomProperties(['alt' => json_decode($source->custom_properties, true)['alt'] ?? $color.' jacket (demo copy)'])->toMediaCollection('gallery', 'demo');

                    return;
                }
            }
        } catch (QueryException $exception) {
            $this->command?->warn('Catalog photo source unavailable; retaining demo-only fallback.');
        }
        if ($color === 'Black') {
            $fallback = Media::where('disk', 'demo')->where('collection_name', 'gallery')->where('name','Demo Black front')->first();
            if ($fallback) {
                $fallback->copy($destination,'gallery','demo');
            }
        }
    }
}
