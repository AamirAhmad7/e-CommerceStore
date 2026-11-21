<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductSkuSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $this->assertContains(config("database.connections.$connection.database"), [':memory:', 'leather_store_variant_regression_test']);
    }

    public function test_sku_search_returns_parent_once_and_preserves_name_search_and_trashed_filter(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $category = Category::create(['name' => 'Jackets', 'slug' => 'jackets']);
        $product = Product::create(['name' => 'Classic Biker Jacket', 'slug' => 'classic-biker-jacket', 'category_id' => $category->id]);
        $color = $product->colors()->create(['name' => 'Black']);
        foreach (['M', 'L'] as $size) {
            $color->variants()->create(['size' => $size, 'sku' => 'CBJ-BLK-'.$size]);
        }
        $other = Product::create(['name' => 'Other jacket', 'slug' => 'other', 'category_id' => $category->id]);
        $deleted = Product::create(['name' => 'Deleted jacket', 'slug' => 'deleted', 'category_id' => $category->id]);
        $deleted->colors()->create(['name' => 'Black'])->variants()->create(['size' => 'S', 'sku' => 'CBJ-BLK-S']);
        $deleted->delete();

        $page = Livewire::test(ListProducts::class)->searchTable('CBJ-BLK')
            ->assertCanSeeTableRecords([$product])->assertCanNotSeeTableRecords([$other, $deleted])->assertCountTableRecords(1);
        $records = $page->instance()->getTableRecords();
        $this->assertSame(1, $records->total());
        $this->assertSame([$product->id], $records->getCollection()->modelKeys());
        $this->assertFalse($records->first()->relationLoaded('variants'));
        $page->searchTable('CBJ-BLK-M')->assertCanSeeTableRecords([$product])->assertCountTableRecords(1)
            ->searchTable('Classic Biker')->assertCanSeeTableRecords([$product])->assertCountTableRecords(1)
            ->searchTable('CBJ-BLK')->filterTable('trashed', false)->assertCanSeeTableRecords([$deleted])->assertCanNotSeeTableRecords([$product])->assertCountTableRecords(1);
    }
}
