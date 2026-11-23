<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminProductEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame(':memory:', config('database.connections.'.config('database.default').'.database'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
    }

    public function test_slug_suggestion_preserves_manual_slug_and_existing_url(): void
    {
        Livewire::test(CreateProduct::class)->set('data.name', 'Classic Biker Jacket')->assertSet('data.slug', 'classic-biker-jacket')
            ->set('data.slug', 'my-jacket')->set('data.name', 'Renamed jacket')->assertSet('data.slug', 'my-jacket');
        $category = Category::create(['name' => 'Jackets', 'slug' => 'jackets', 'is_active' => true]);
        $product = Product::create(['name' => 'Classic Biker Jacket', 'slug' => 'classic-biker-jacket', 'category_id' => $category->id, 'usd_price_minor' => 14900, 'pkr_price_minor' => 1000000]);
        Livewire::test(EditProduct::class, ['record' => $product->id])->set('data.name', 'Renamed jacket')
            ->assertSet('data.slug', 'classic-biker-jacket')->set('data.pkr_price_minor', '12000')->call('save')->assertHasNoFormErrors();
        $this->assertSame('classic-biker-jacket', $product->fresh()->slug);
        $this->assertSame(14900, $product->fresh()->usd_price_minor);
        $this->assertSame(1200000, $product->fresh()->pkr_price_minor);
    }

    public function test_editor_groups_readiness_and_retains_pending_values_on_validation_failure(): void
    {
        $category = Category::create(['name' => 'Jackets', 'slug' => 'jackets', 'is_active' => true]);
        $product = Product::create(['name' => 'Classic Biker Jacket', 'slug' => 'classic-biker-jacket', 'category_id' => $category->id, 'is_active' => true]);
        $color = $product->colors()->create(['name' => 'Black']);
        $color->variants()->create(['size' => 'M', 'sku' => 'CBJ-BLK-M', 'is_active' => true]);
        $page = Livewire::test(EditProduct::class, ['record' => $product->id]);
        foreach (['General', 'Pricing', 'Colors &amp; sizes', 'Photos', 'SEO &amp; sharing', 'Size guide', 'Stock has never been entered', 'sizes need a PKR price'] as $label) {
            $this->assertStringContainsString($label, $page->html());
        }
        $document = new \DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        $document->loadHTML($page->html());
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        $xpath = new \DOMXPath($document);
        $counts = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " sf-color-counts ")]/span/b');
        $this->assertCount(3, $counts);
        $this->assertSame('1', $counts->item(0)->textContent);
        $this->assertSame('0', $counts->item(1)->textContent);
        $this->assertSame('0', $counts->item(2)->textContent);
        $tabs = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " fi-sc-tabs ")]');
        $this->assertCount(1, $tabs);
        $this->assertStringContainsString('querySelector("[data-validation-error]")', $tabs->item(0)->getAttribute('x-on:form-validation-error.window'));
        $this->assertCount(6, $xpath->query('.//*[@role="tabpanel"]', $tabs->item(0)));
        $page->set('data.description', 'Pending description')->set('data.slug', 'invalid slug')->call('save')
            ->assertHasFormErrors(['slug' => 'regex'])->assertSet('data.description', 'Pending description');
        $this->assertNull($product->fresh()->description);
        $this->assertTrue($page->instance()->areFormActionsSticky());
    }
}
