<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Homepage;
use App\Models\Product;
use App\Services\Inventory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

class CollectionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $connection = config('database.default');
        if (config("database.connections.$connection.database") !== 'leather_store_demo' || config('store.media_disk') !== 'demo') {
            throw new \RuntimeException('Collection fixtures require the isolated demo database and disk.');
        }
        $products = [];
        foreach (['Jacket', 'Vest', 'Coat'] as $index => $name) {
            $category = Category::firstOrCreate(['slug' => $name === 'Vest' ? 'demo-vests' : 'demo-jackets'], ['name' => $name === 'Vest' ? 'Demo vests' : 'Demo jackets', 'is_active' => true]);
            $product = Product::firstOrCreate(['slug' => 'demo-campaign-'.strtolower($name)], ['name' => 'Demo Campaign '.$name, 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 1290000 + $index * 100000, 'usd_price_minor' => 4900 + $index * 500, 'description' => 'Synthetic collection demo. Not real stock or photography.']);
            foreach (['Black', 'Brown'] as $position => $colorName) {
                $color = $product->colors()->firstOrCreate(['name' => $colorName], ['sort_order' => $position, 'swatch_hex' => $position ? '#70432b' : '#151515']);
                $variant = $color->variants()->firstOrCreate(['size' => 'M'], ['sku' => 'CAMPAIGN-'.strtoupper($name).'-'.strtoupper($colorName).'-M', 'is_active' => true]);
                if ($variant->on_hand === null) {
                    app(Inventory::class)->adjust($variant->id, 5, 'DEMO collection fixture', null);
                }
                $this->photo($color, 'gallery', 'Demo '.$colorName.' '.$name, $position ? [112, 67, 43] : [25, 25, 30]);
            }
            $products[] = $product;
        }
        foreach (['biker-jackets' => ['Biker Jackets', true, 'Black'], 'earth-tones' => ['Earth Tones', true, 'Brown'], 'draft-campaign' => ['Draft Campaign', false, 'Black']] as $slug => [$title, $published, $colorName]) {
            $collection = Collection::firstOrCreate(['slug' => $slug], ['title' => $title, 'introduction' => 'Explore our hand-picked demo pieces. Images are labeled test fixtures.', 'is_published' => $published, 'cover_alt' => 'Synthetic '.$title.' campaign cover']);
            if ($collection->wasRecentlyCreated) {
                foreach ($products as $position => $product) {
                    $collection->items()->create(['product_id' => $product->id, 'featured_color_id' => $product->colors()->where('name', $colorName)->value('id'), 'sort_order' => $position]);
                }
                if ($slug === 'biker-jackets') {
                    $this->photo($collection, 'cover', 'Demo Biker Jackets cover', [50, 60, 75]);
                }
            }
            $this->command?->line(route('collections.show', $slug));
        }
        $home = Homepage::firstOrCreate(['key' => 'main']);
        if ($home->draft === null && $home->published === null) {
            $biker = Collection::where('slug', 'biker-jackets')->firstOrFail();
            $earth = Collection::where('slug', 'earth-tones')->firstOrFail();
            $home->update(['draft' => ['headline' => 'Find your next signature jacket', 'supporting_text' => 'Discover Biker Jackets and Earth Tones. This isolated demo uses synthetic test images.', 'hero_alt' => 'Synthetic Sialkot Fashion campaign banner', 'primary_target' => 'collection', 'primary_collection_id' => $biker->id, 'featured' => [['collection_id' => $biker->id], ['collection_id' => $earth->id]]]]);
            $this->photo($home, 'draft_hero', 'Demo Homepage hero', [70, 85, 95]);
            $home->publishDraft();
        }
        $this->command?->info('Demo collections prepared. Existing fixture edits are retained. Run the media queue worker.');
    }

    private function photo(HasMedia $owner, string $collection, string $name, array $rgb): void
    {
        if ($owner->getMedia($collection)->isNotEmpty()) {
            return;
        }
        $image = imagecreatetruecolor(1200, 1500);
        imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
        $paper = imagecolorallocate($image, 245, 245, 235);
        $ink = imagecolorallocate($image, 20, 20, 20);
        imagefilledrectangle($image, 30, 40, 1170, 160, $paper);
        imagestring($image, 5, 60, 70, $name, $ink);
        imagestring($image, 5, 60, 100, 'TEST IMAGE - not a product photograph', $ink);
        for ($i = 0; $i < 1000; $i++) {
            imagefilledrectangle($image, ($i * 137) % 1100, 200 + ($i * 193) % 1200, ($i * 137) % 1100 + 10, 200 + ($i * 193) % 1200 + 10, $paper);
        }
        ob_start();
        imagejpeg($image, null, 90);
        $bytes = ob_get_clean();
        imagedestroy($image);
        $owner->addMediaFromString($bytes)->usingName($name)->usingFileName(Str::slug($name).'.jpg')->withCustomProperties(['alt' => $name.' (synthetic test image)'])->toMediaCollection($collection);
    }
}
