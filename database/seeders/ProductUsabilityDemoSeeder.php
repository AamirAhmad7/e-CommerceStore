<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Services\Inventory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProductUsabilityDemoSeeder extends Seeder
{
    public function run(): void
    {
        $connection = config('database.default');
        if (config("database.connections.$connection.database") !== 'leather_store_demo') {
            throw new \RuntimeException('Only leather_store_demo is allowed.');
        }
        $category = Category::firstOrCreate(['slug' => 'demo-jackets'], ['name' => 'Demo jackets', 'is_active' => true]);
        $product = Product::firstOrCreate(['slug' => 'demo-gallery-jacket'], ['category_id' => $category->id, 'name' => 'Demo Gallery Jacket', 'is_active' => true, 'pkr_price_minor' => 1290000, 'usd_price_minor' => 4900, 'description' => 'Synthetic demo images for gallery testing. No real jacket, stock or pricing is changed.']);
        foreach (['Brown' => [[], 0, 0], 'Grey' => [['M'], 0, 1], 'Black' => [['XL', 's', 'M', 'XS', 'xxl', '42', '38', 'Custom'], 3, 2], 'Green' => [['M'], 3, 3], 'Unpriced' => [['M'], 3, 4]] as $name => [$sizes,$stock,$position]) {
            $color = $product->colors()->firstOrCreate(['name' => $name], ['sort_order' => $position, 'swatch_hex' => match ($name) {
                'Brown' => '#70432b','Grey' => '#777777','Black' => '#151515','Green' => '#40654b',default => '#aabbcc'
            }]);
            foreach ($sizes as $size) {
                $v = $color->variants()->firstOrCreate(['size' => $size], ['sku' => 'GALLERY-'.strtoupper($name).'-'.strtoupper($size), 'is_active' => true, 'pkr_price_minor' => $name === 'Unpriced' ? 0 : null]);
                if ($v->on_hand === null) {
                    app(Inventory::class)->adjust($v->id, $stock, 'DEMO gallery fixture', null);
                }
            }
            if ($name === 'Black') {
                $this->photo($color, 'gallery', 'Demo Black front', [25, 25, 30]);
                $this->photo($color, 'gallery', 'Demo Black back', [45, 45, 50]);
            }
        }
        $this->photo($product, 'shared', 'Demo neutral construction detail', [130, 100, 70], 'detail');
        $this->photo($product, 'shared', 'Demo size chart', [240, 240, 230], 'size_guide');
        $this->photo($product, 'shared', 'Demo unclassified black close-up', [35, 35, 35]);
        $this->command?->line(route('products.show', $product));
        $this->command?->line(route('products.preview', $product));
        $this->command?->line(route('filament.admin.resources.products.edit', $product));
        foreach ($product->colors as $color) {
            $this->command?->line($color->name.': '.route('products.show', [$product, 'color' => $color->id]));
        }
        $this->command?->info('Only isolated demo fixtures were added. Existing fixture edits are preserved. Run the media worker to generate WebP thumbnails.');
    }

    private function photo($owner, string $collection, string $name, array $rgb, ?string $placement = null): void
    {
        // Search all owners by fixture name so a manually moved photo is never recreated.
        if (Media::where('name', $name)->exists()) {
            return;
        }
        $image = imagecreatetruecolor(1200, 1500);
        imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
        for ($i = 0; $i < 5000; $i++) {
            $shade = imagecolorallocate($image, ($i * 31) % 256, ($i * 73) % 256, ($i * 137) % 256);
            imagefilledrectangle($image, ($i * 137) % 1200, ($i * 193) % 1500, ($i * 137) % 1200 + 8, ($i * 193) % 1500 + 8, $shade);
        }
        $white = imagecolorallocate($image, 255, 255, 255);
        $ink = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 30, 40, 1170, 160, $white);
        imagestring($image, 5, 60, 70, $name, $ink);
        imagestring($image, 5, 60, 100, 'TEST IMAGE - not a product photograph', $ink);
        if ($placement === 'size_guide') {
            foreach (['Size   Chest (demo only)', 'S       90 cm', 'M      100 cm', 'L      110 cm'] as $i => $line) {
                imagefilledrectangle($image, 100, 300 + $i * 150, 1100, 400 + $i * 150, $white);
                imagestring($image, 5, 140, 340 + $i * 150, $line, $ink);
            }
        }
        ob_start();
        imagejpeg($image,null,90);
        $bytes = ob_get_clean();
        imagedestroy($image);
        $owner->addMediaFromString($bytes)->usingName($name)->usingFileName(Str::slug($name).'.jpg')->withCustomProperties(array_filter(['alt' => $name, 'placement' => $placement]))->toMediaCollection($collection);
    }
}
