<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Tests\TestCase;

class ProductPhotosTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $connection = config('database.default');
        $this->assertContains(config("database.connections.{$connection}.database"), [':memory:', 'leather_store_variant_regression_test']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('store.media_disk'));
        Queue::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
    }

    private function product(): Product
    {
        $category = Category::create(['name' => 'Jackets', 'slug' => 'jackets']);

        return Product::create(['category_id' => $category->id, 'name' => 'Classic Biker Jacket', 'slug' => 'classic-biker-jacket']);
    }

    public function test_alt_text_stays_with_each_photo_after_reordering_and_saving(): void
    {
        $product = $this->product();
        $color = $product->colors()->create(['name' => 'Black']);
        $shared = $product->addMedia(UploadedFile::fake()->image('diagram.jpg'))->withCustomProperties(['credit' => 'Studio'])->toMediaCollection('shared');
        $front = $color->addMedia(UploadedFile::fake()->image('front.jpg'))->toMediaCollection('gallery');
        $back = $color->addMedia(UploadedFile::fake()->image('back.jpg'))->toMediaCollection('gallery');
        $page = Livewire::test(EditProduct::class, ['record' => $product->id]);
        $page->callFormComponentAction('shared_photos', 'editPhotoAltText', ['alt' => [$shared->uuid => 'Jacket measurement diagram']])
            ->assertHasNoFormErrors()->assertNotified('Photo descriptions saved');
        $page->callFormComponentAction("colors.record-{$color->id}.color_photos", 'editPhotoAltText', [
            'alt' => [$front->uuid => 'Black jacket, front view', $back->uuid => 'Black jacket, rear view', $shared->uuid => 'Must not overwrite another collection'],
        ])->assertHasNoFormErrors();
        $this->assertSame('Jacket measurement diagram', $shared->fresh()->getCustomProperty('alt'));
        $this->assertSame('Studio', $shared->fresh()->getCustomProperty('credit'));
        $page->set("data.colors.record-{$color->id}.color_photos", [$back->uuid => $back->uuid, $front->uuid => $front->uuid])
            ->call('save')->assertHasNoFormErrors()->assertNotified('Product saved')
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('Black jacket, front view', $front->fresh()->getCustomProperty('alt'));
        $this->assertSame('Black jacket, rear view', $back->fresh()->getCustomProperty('alt'));
        $this->assertSame([$back->id, $front->id], $color->fresh()->getMedia('gallery')->modelKeys());
        foreach ([$shared, $front, $back] as $photo) {
            Storage::disk(config('store.media_disk'))->assertExists($photo->getPathRelativeToRoot());
        }
        $page->mountFormComponentAction('shared_photos', 'editPhotoAltText')
            ->assertFormComponentActionDataSet(['alt' => [$shared->uuid => 'Jacket measurement diagram']])
            ->unmountFormComponentAction();
        $page->callFormComponentAction('shared_photos', 'editPhotoAltText', ['alt' => [$shared->uuid => '']])
            ->assertHasNoFormErrors();
        $this->assertSame('', $shared->fresh()->getCustomProperty('alt'));
        $page->callFormComponentAction('shared_photos', 'editPhotoAltText', ['alt' => [$shared->uuid => str_repeat('a', 501)]])
            ->assertHasFormErrors(["alt.{$shared->uuid}" => 'max']);
    }

    public function test_queued_webp_and_responsive_files_preserve_originals(): void
    {
        $product = $this->product();
        $color = $product->colors()->create(['name' => 'Black']);
        foreach ([[$product, 'shared'], [$color, 'gallery']] as [$owner, $collection]) {
            $file = UploadedFile::fake()->image("{$collection}.jpg", 1800, 1200);
            $image = imagecreatefromjpeg($file->getRealPath());
            for ($i = 0; $i < 8000; $i++) {
                $shade = imagecolorallocate($image, ($i * 31) % 256, ($i * 73) % 256, ($i * 137) % 256);
                imagefilledellipse($image, ($i * 137) % 1800, ($i * 193) % 1200, 23, 19, $shade);
            }
            imagejpeg($image, $file->getRealPath(), 90);
            $hash = hash_file('sha256', $file->getRealPath());
            $photo = $owner->addMedia($file)->withCustomProperties(['alt' => 'Test jacket photograph'])->toMediaCollection($collection);
            $originalOrder = $photo->order_column;
            $this->assertFalse($photo->hasGeneratedConversion('storefront'));
            Queue::assertPushed(PerformConversionsJob::class);
            Queue::fake();
            $this->artisan('media-library:regenerate', [
                'modelType' => $owner::class,
                '--ids' => [$photo->id],
                '--only' => ['storefront'],
                '--queue-all' => true,
            ])->assertSuccessful();
            Queue::assertPushed(PerformConversionsJob::class, 1);
            $job = Queue::pushed(PerformConversionsJob::class)->last();
            $this->assertSame('database', $job->connection);
            $this->assertSame('media', $job->queue);
            $this->assertTrue($job->afterCommit);
            app()->call([$job, 'handle']);
            $photo->refresh();
            $this->assertSame('Test jacket photograph', $photo->getCustomProperty('alt'));
            $this->assertSame($originalOrder, $photo->order_column);
            $this->assertTrue($photo->hasGeneratedConversion('storefront'));
            $this->assertSame($hash, hash_file('sha256', $photo->getPath()));
            $size = getimagesize($photo->getPath('storefront'));
            $this->assertSame('image/webp', $size['mime']);
            $this->assertLessThanOrEqual(1600, max($size[0], $size[1]));
            $files = $photo->responsive_images['storefront']['urls'];
            $this->assertGreaterThan(1, count($files));
            foreach ($files as $fileName) {
                $path = Storage::disk(config('store.media_disk'))->path("{$photo->id}/responsive-images/{$fileName}");
                $this->assertFileExists($path);
                $this->assertSame('image/webp', getimagesize($path)['mime']);
            }
            $this->assertStringContainsString('.webp', $photo->getSrcset('storefront'));
        }
    }
}
