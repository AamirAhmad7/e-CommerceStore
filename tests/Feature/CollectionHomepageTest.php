<?php

namespace Tests\Feature;

use App\Filament\Pages\Homepage as HomepageEditor;
use App\Filament\Resources\Collections\Pages\CreateCollection;
use App\Filament\Resources\Collections\Pages\EditCollection;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Homepage;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Tests\TestCase;

class CollectionHomepageTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function beforeRefreshingDatabase(): void
    {
        $c = config('database.default');
        $this->assertContains(config("database.connections.$c.database"), [':memory:', 'leather_store_variant_regression_test']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake();
        Storage::fake(config('store.media_disk'));
        config(['app.url' => 'https://store.example.test']);
        $this->category = Category::create(['name' => 'Jackets', 'slug' => 'jackets', 'is_active' => true]);
    }

    private function owner(): void
    {
        config(['app.env' => 'local']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
    }

    private function product(string $name = 'Jacket'): Product
    {
        $p = Product::create(['name' => $name, 'slug' => Str::slug($name), 'category_id' => $this->category->id, 'is_active' => true, 'pkr_price_minor' => 10000, 'usd_price_minor' => 2500]);
        foreach (['Black', 'Brown'] as $color) {
            $c = $p->colors()->create(['name' => $color]);
            $v = $c->variants()->create(['size' => 'M', 'sku' => strtoupper(Str::slug($name)).'-'.strtoupper($color), 'is_active' => true]);
            app(Inventory::class)->adjust($v->id, 3, 'Test fixture', null);
        }

        return $p;
    }

    private function collection(string $title = 'Biker Jackets', bool $published = true): Collection
    {
        return Collection::create(['title' => $title, 'slug' => Str::slug($title), 'is_published' => $published, 'introduction' => 'Hand-picked jackets.']);
    }

    private function photo($owner, string $collection, string $name = 'cover')
    {
        return $owner->addMedia(UploadedFile::fake()->image($name.'.jpg', 600, 800))->withCustomProperties(['alt' => $name])->toMediaCollection($collection);
    }

    public function test_collection_preview_authorization_publication_and_clean_metadata(): void
    {
        $p = $this->product();
        $c = $this->collection(published: false);
        $c->items()->create(['product_id' => $p->id]);
        $photo = $this->photo($p->colors->first(), 'gallery', 'Public black photo');
        $this->get(route('collections.show', $c->slug))->assertNotFound();
        $this->get(route('collections.preview', $c->slug))->assertForbidden();
        $this->owner();
        $response = $this->get(route('collections.preview', $c->slug))->assertOk()->assertSee('Staff preview')->assertDontSee('data-share-url', false)->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertFalse($c->fresh()->is_published);
        $c->update(['is_published' => true]);
        $response = $this->get(route('collections.show', $c->slug).'?utm_source=whatsapp&currency=USD')->assertOk()->assertSee('USD 25.00');
        $this->assertSame('https://store.example.test/collections/biker-jackets', $response->viewData('canonical'));
        $response->assertSee('data-share-url="https://store.example.test/collections/biker-jackets"', false)->assertSee('property="og:title" content="Biker Jackets"', false)->assertSee('Hand-picked jackets.');
        $this->assertSame($photo->id, $response->viewData('cover')['id']);
        config(['app.env' => 'production']);
        $this->get(route('collections.preview', $c->slug))->assertForbidden();
    }

    public function test_featured_color_photo_link_and_ineligible_fallback_agree(): void
    {
        $p = $this->product();
        $black = $p->colors->firstWhere('name', 'Black');
        $brown = $p->colors->firstWhere('name', 'Brown');
        $this->photo($black, 'gallery', 'Black photo');
        $brownPhoto = $this->photo($brown, 'gallery', 'Brown photo');
        $c = $this->collection();
        $c->items()->create(['product_id' => $p->id, 'featured_color_id' => $brown->id]);
        $r = $this->get(route('collections.show', $c->slug))->assertOk();
        $card = $r->viewData('cards')->first();
        $this->assertSame($brownPhoto->id, $card['photo']['id']);
        $this->assertStringContainsString('color='.$brown->id, $card['url']);
        $this->get($card['url'])->assertOk()->assertViewHas('selected', fn ($s) => $s['id'] === (string) $brown->id);
        $brown->variants()->update(['is_active' => false]);
        $card = $this->get(route('collections.show', $c->slug))->viewData('cards')->first();
        $this->assertSame($black->id, $card['color']->id);
        $this->assertStringContainsString('color='.$black->id, $card['url']);
        $brown->variants()->update(['is_active' => true]);
        $brownPhoto->delete();
        $card = $this->get(route('collections.show', $c->slug))->viewData('cards')->first();
        $this->assertSame($brown->id, $card['color']->id);
        $this->assertNull($card['photo']);
    }

    public function test_hidden_products_are_removed_before_pagination_and_query_count_is_bounded(): void
    {
        $c = $this->collection();
        for ($i = 0; $i < 15; $i++) {
            $p = $this->product('Jacket '.$i);
            $this->photo($p->colors->first(), 'gallery', 'Photo '.$i);
            $c->items()->create(['product_id' => $p->id, 'sort_order' => $i]);
            if ($i < 2) {
                $p->update(['is_active' => false]);
            }
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $r = $this->get(route('collections.show', $c->slug))->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame(13, $r->viewData('items')->total());
        $this->assertCount(12, $r->viewData('cards'));
        $this->assertSame('Jacket 2', $r->viewData('cards')->first()['product']->name);
        $this->assertLessThanOrEqual(16, count($queries), implode("\n", array_column($queries, 'query')));
        $this->assertCount(1, $this->get(route('collections.show', $c->slug).'?page=2')->viewData('cards'));
        $this->category->update(['is_active' => false]);
        $r = $this->get(route('collections.show', $c->slug))->assertOk()->assertSee('No products are currently available');
        $this->assertSame(0, $r->viewData('items')->total());
    }

    public function test_filament_create_search_duplicate_validation_publish_and_stable_title_link(): void
    {
        $this->owner();
        $p = $this->product();
        $second = $this->product('Second jacket');
        $page = Livewire::test(CreateCollection::class)->set('data.title', 'Biker Jackets')->assertSet('data.slug', 'biker-jackets');
        $page->fillForm(['items' => [['product_id' => null]]]);
        $select = collect($page->instance()->form->getFlatComponents())->first(fn ($component) => $component instanceof Select && $component->getName() === 'product_id');
        $this->assertSame([$second->id => $second->name], $select->getSearchResults($second->variants()->first()->sku));
        $page->fillForm(['title' => 'Biker Jackets', 'slug' => 'biker-jackets', 'is_published' => false, 'items' => [['product_id' => $p->id], ['product_id' => $p->id]]])->call('create')->assertHasFormErrors();
        $page->fillForm(['items' => [['product_id' => $p->id, 'featured_color_id' => $p->colors->first()->id], ['product_id' => $second->id, 'featured_color_id' => null]]])->call('create')->assertHasNoFormErrors()->assertNotified();
        $c = Collection::sole();
        $this->assertSame([$p->id, $second->id], $c->items()->pluck('product_id')->all());
        $edit = Livewire::test(EditCollection::class, ['record' => $c->id]);
        $edit->callAction('publish')->assertHasNoFormErrors()->assertNotified('Collection published');
        $edit->set('data.title', 'Renamed campaign')->call('save')->assertHasNoFormErrors()->assertNotified('Collection saved');
        $this->assertTrue($c->fresh()->is_published);
        $this->assertSame('biker-jackets', $c->fresh()->slug);
        $state = $edit->get('data')['items'];
        $edit->set('data.items', array_reverse($state, true))->call('save')->assertHasNoFormErrors();
        $this->assertSame([$second->id, $p->id], $c->items()->pluck('product_id')->all());
        $edit->mountAction('copyPublicLink')->assertActionDataSet(['url' => 'https://store.example.test/collections/biker-jackets']);
    }

    public function test_foreign_featured_color_is_rejected_and_collection_duplicates_have_database_constraint(): void
    {
        $p = $this->product();
        $other = $this->product('Other');
        $c = $this->collection();
        try {
            $c->items()->create(['product_id' => $p->id, 'featured_color_id' => $other->colors->first()->id]);
            $this->fail('Expected ownership validation');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
        $c->items()->create(['product_id' => $p->id]);
        $this->expectException(QueryException::class);
        $c->items()->create(['product_id' => $p->id]);
    }

    public function test_homepage_draft_publish_and_media_snapshot_are_independent(): void
    {
        $this->get('/')->assertOk()->assertSee('Browse all products');
        $this->get(route('home.preview'))->assertForbidden();
        $this->owner();
        $p = $this->product();
        $c = $this->collection();
        $c->items()->create(['product_id' => $p->id]);
        $page = Livewire::test(HomepageEditor::class)->fillForm(['headline' => 'First homepage', 'supporting_text' => 'First public copy', 'primary_target' => 'collection', 'primary_collection_id' => $c->id, 'featured' => [['collection_id' => $c->id]]])->call('saveDraft')->assertHasNoFormErrors()->assertNotified('Homepage draft saved');
        $this->get('/')->assertDontSee('First homepage');
        $this->get(route('home.preview'))->assertSee('First homepage')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $home = Homepage::sole();
        $hero = $this->photo($home, 'draft_hero', 'First hero');
        $home->publishDraft();
        $snapshotId = $home->published['hero_media_id'];
        $publishedPhoto = $home->getMedia('published_heroes')->firstWhere('id', $snapshotId);
        $this->assertNotNull($publishedPhoto);
        $this->get('/')->assertSee('First homepage')->assertSee('Biker Jackets');
        $this->photo($home, 'draft_hero', 'Replacement draft');
        $home->update(['draft' => array_replace($home->draft, ['headline' => 'Second draft', 'featured' => []])]);
        $this->get('/')->assertSee('First homepage')->assertDontSee('Second draft');
        Storage::disk($publishedPhoto->disk)->assertExists($publishedPhoto->getPathRelativeToRoot());
        $this->get(route('home.preview'))->assertSee('Second draft');
        $c->update(['is_published' => false]);
        $this->get('/')->assertDontSee('Shop Biker Jackets')->assertDontSee('Shop this collection')->assertSee('Browse all products');
        $home->publishDraft();
        $this->get('/')->assertSee('Second draft')->assertDontSee('First homepage');
        $this->assertNotSame($snapshotId, $home->published['hero_media_id']);
    }

    public function test_cover_responsive_images_empty_collections_and_sitemap_exclude_drafts(): void
    {
        $p = $this->product();
        $c = $this->collection();
        $c->items()->create(['product_id' => $p->id]);
        $c->update(['cover_alt' => 'Biker collection cover']);
        $cover = $this->photo($c, 'cover');
        app()->call([Queue::pushed(PerformConversionsJob::class)->last(), 'handle']);
        $r = $this->get(route('collections.show', $c->slug))->assertOk()->assertSee('Biker collection cover');
        $this->assertStringEndsWith('.webp', $r->viewData('cover')['src']);
        $this->assertNotEmpty($r->viewData('cover')['srcset']);
        $draft = $this->collection('Private campaign', false);
        $draft->items()->create(['product_id' => $p->id]);
        $empty = $this->collection('Empty campaign');
        $this->get(route('collections.show', $empty->slug))->assertOk()->assertSee('0 products');
        $this->get('/sitemap.xml')->assertOk()->assertSee('https://store.example.test/collections/biker-jackets')->assertDontSee('private-campaign')->assertDontSee('empty-campaign')->assertDontSee('/preview')->assertDontSee('utm_');
        $p->delete();
        $this->get('/sitemap.xml')->assertDontSee('/collections/biker-jackets');
    }

    public function test_homepage_editor_upload_publish_and_second_save_keep_live_hero(): void
    {
        $this->owner();
        $page = Livewire::test(HomepageEditor::class)->fillForm(['headline' => 'Uploaded campaign', 'primary_target' => 'shop', 'hero_alt' => 'Demo hero', 'hero' => [UploadedFile::fake()->image('hero.jpg', 600, 800)]])
            ->call('publish')->assertHasNoFormErrors()->assertNotified('Homepage published');
        $home = Homepage::sole();
        $this->assertNotNull($home->published['hero_media_id']);
        $page->set('data.headline','Changed draft')->call('saveDraft')->assertHasNoFormErrors();
        $this->get('/')->assertSee('Uploaded campaign')->assertDontSee('Changed draft')->assertSee('Demo hero');
        $this->get(route('home.preview'))->assertSee('Changed draft');
    }
}
