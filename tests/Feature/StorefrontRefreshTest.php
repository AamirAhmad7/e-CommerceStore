<?php

namespace Tests\Feature;

use App\Filament\Pages\Homepage as HomepageEditor;
use App\Filament\Pages\StoreSettings as SettingsEditor;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Homepage;
use App\Models\Product;
use App\Models\User;
use App\Support\StoreSettings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class StorefrontRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_store_name_and_private_hero_are_independent_and_name_reads_are_reused(): void
    {
        config(['app.env' => 'local']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        Homepage::create(['key' => 'main', 'published' => ['headline' => 'Winter Leather Collection', 'primary_target' => 'shop', 'featured' => []], 'draft' => ['headline' => 'Private spring launch', 'primary_target' => 'shop', 'featured' => []]]);
        Livewire::test(SettingsEditor::class)->set('brandName', 'Sialkot Atelier')->call('save')->assertHasNoErrors();
        $page = $this->get('/')->assertOk()->assertSee('Winter Leather Collection')->assertDontSee('Private spring launch')->assertSee('Sialkot Atelier')->assertSee('content="Sialkot Atelier"', false);
        $this->assertGreaterThanOrEqual(3, substr_count($page->getContent(), 'Sialkot Atelier'));
        $editor = Livewire::test(HomepageEditor::class)->assertSee('Hero headline')->assertSee('Change store name')->set('data.headline', 'Quiet autumn edit')->call('saveDraft')->assertHasNoErrors();
        $this->get('/')->assertSee('Winter Leather Collection')->assertDontSee('Quiet autumn edit');
        $this->get('/homepage/preview')->assertOk()->assertSee('Quiet autumn edit')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $editor->call('publish')->assertHasNoErrors();
        $this->get('/')->assertSee('Quiet autumn edit')->assertSee('Sialkot Atelier');
        request()->attributes->remove('store_public_settings');
        DB::enableQueryLog();
        DB::flushQueryLog();
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame('Sialkot Atelier', StoreSettings::brandName());
        }$this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
        StoreSettings::setBrandName('Renamed Atelier');
        $this->assertSame('Renamed Atelier', StoreSettings::brandName());
        $this->get('/')->assertSee('Renamed Atelier')->assertSee('Quiet autumn edit');
    }

    public function test_empty_store_and_bounded_public_navigation_work_without_promotions(): void
    {
        foreach (['/', '/shop', '/cart', '/checkout', '/find-order'] as $path) {
            $this->get($path)->assertOk();
        }
        $category = Category::create(['name' => 'Jackets', 'slug' => 'jackets', 'is_active' => true]);
        $product = Product::create(['name' => 'Long winter jacket', 'slug' => 'long-winter-jacket', 'category_id' => $category->id, 'is_active' => true, 'pkr_price_minor' => 500000]);
        $product->colors()->create(['name' => 'Brown'])->variants()->create(['sku' => 'REFRESH-BROWN-M', 'size' => 'M', 'is_active' => true]);
        $a = Collection::create(['title' => 'First edit', 'slug' => 'first-edit', 'is_published' => true]);
        $b = Collection::create(['title' => 'Second edit', 'slug' => 'second-edit', 'is_published' => true]);
        $private = Collection::create(['title' => 'Private edit', 'slug' => 'private-edit', 'is_published' => false]);
        foreach ([$a, $b, $private] as $c) {
            $c->items()->create(['product_id' => $product->id]);
        }
        Homepage::create(['key' => 'main', 'published' => ['headline' => 'Winter', 'featured' => [['collection_id' => $b->id], ['collection_id' => $private->id], ['collection_id' => $a->id]]], 'draft' => ['headline' => 'Draft', 'featured' => [['collection_id' => $private->id]]]]);
        $response = $this->get('/')->assertOk()->assertSeeInOrder(['Second edit', 'First edit'])->assertDontSee('Private edit')->assertSee('aria-controls="store-menu"', false);
        $this->get('/collections/second-edit?currency=PKR')->assertOk()->assertSessionHas('continue_shopping', '/collections/second-edit?currency=PKR');
        $this->get('/cart')->assertSee('/collections/second-edit?currency=PKR', false);
    }
}
