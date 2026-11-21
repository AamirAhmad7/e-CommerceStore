<?php

namespace Tests\Feature;

use App\Filament\Pages\RestockRequests;
use App\Jobs\ConfirmRestockEmail;
use App\Jobs\SendRestockAlerts;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RestockSubscription;
use App\Models\User;
use App\Services\Inventory;
use App\Services\RestockAlerts;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class RestockAlertsTest extends TestCase
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
        Queue::fake();
    }

    /** @return array{0: ProductVariant, 1: ProductVariant} */
    private function variants(): array
    {
        $category = Category::create(['name' => 'Jackets', 'slug' => 'jackets', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Restock Jacket', 'slug' => 'restock-jacket', 'is_active' => true, 'pkr_price_minor' => 1000000, 'usd_price_minor' => 10000]);
        $variants = [];
        foreach (['Black' => 0, 'Brown' => 5] as $name => $quantity) {
            $variant = $product->colors()->create(['name' => $name])->variants()->create(['size' => 'M', 'sku' => 'ALERT-'.$name.'-M', 'is_active' => true]);
            $variant->forceFill(['on_hand' => $quantity, 'reserved' => 0])->save();
            $variants[] = $variant;
        }

        return $variants;
    }

    public function test_confirmation_is_private_post_only_and_duplicate_requests_are_one_subscription(): void
    {
        [$black,$brown] = $this->variants();
        $url = RestockAlerts::buyUrl($black);
        $this->get($url)->assertOk()->assertSee('Notify me when available')->assertSee('disabled aria-label="M - Sold out"', false);
        $this->from($url)->post('/restock-alerts', ['variant_id' => $black->id, 'email' => 'Buyer@EXAMPLE.test'])->assertRedirect($url);
        $this->from($url)->post('/restock-alerts', ['variant_id' => $black->id, 'email' => 'buyer@example.test'])->assertRedirect($url);
        $this->assertSame(1, RestockSubscription::count());
        Queue::assertPushed(ConfirmRestockEmail::class, 1);
        $row = RestockSubscription::first();
        $this->assertNull($row->confirmed_at);
        $this->assertNotSame('buyer@example.test', $row->getRawOriginal('email'));
        $manage = RestockAlerts::manageUrl($row);
        $this->get($manage)->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private')->assertDontSee('buyer@example.test');
        $this->assertNull($row->fresh()->confirmed_at);
        (new ConfirmRestockEmail($row->id, $row->token_hash))->handle();
        (new ConfirmRestockEmail($row->id, $row->token_hash))->handle();
        $this->assertCount(1, Mail::mailer('array')->getSymfonyTransport()->messages());
        $this->post($manage, ['action' => 'confirm'])->assertRedirect($manage);
        $this->assertNotNull($row->fresh()->confirmed_at);
        $this->get($manage.'?action=cancel')->assertOk();
        $this->assertNull($row->fresh()->cancelled_at);
        $this->post($manage, ['action' => 'cancel'])->assertRedirect();
        $this->assertNotNull($row->fresh()->cancelled_at);
        $this->post($manage, ['action' => 'confirm']);
        $this->assertSame(0, RestockSubscription::waiting()->count());
        $this->post('/restock-alerts', ['variant_id' => $brown->id, 'email' => 'new@example.test'])->assertRedirect(RestockAlerts::buyUrl($brown));
        $this->assertSame(1, RestockSubscription::count());
    }

    public function test_exact_variant_restock_retry_and_stock_disappearing(): void
    {
        [$black,$brown] = $this->variants();
        $row = RestockSubscription::factory()->confirmed()->create(['product_variant_id' => $black->id]);
        (new SendRestockAlerts($brown->id))->handle();
        (new SendRestockAlerts($black->id))->handle();
        $this->assertNull($row->fresh()->completed_at);
        app(Inventory::class)->adjust($black->id, 2, 'Receipt', null);
        Queue::assertPushed(SendRestockAlerts::class, fn ($job) => $job->variantId === $black->id);
        app(Inventory::class)->adjust($black->id, -2, 'Sold before queue ran', null);
        (new SendRestockAlerts($black->id))->handle();
        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
        $this->assertNull($row->fresh()->completed_at);
        app(Inventory::class)->adjust($black->id, 1, 'Next receipt', null);
        (new SendRestockAlerts($black->id))->handle();
        (new SendRestockAlerts($black->id))->handle();
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $body = $messages->first()->getOriginalMessage()->getTextBody();
        $this->assertStringContainsString('Black / M', $body);
        $this->assertStringContainsString('color='.$black->product_color_id, $body);
        $this->assertStringContainsString('size=M', $body);
        $this->assertStringContainsString('does not reserve stock', $body);
        $this->assertNotNull($row->fresh()->completed_at);
        $this->assertSame(0, RestockSubscription::waiting()->count());
    }

    public function test_reservation_release_is_after_commit_and_rollback_never_dispatches(): void
    {
        [$black] = $this->variants();
        DB::beginTransaction();
        app(Inventory::class)->adjust($black->id, 2, 'Uncommitted', null);
        Queue::assertNothingPushed();
        DB::rollBack();
        Queue::assertNothingPushed();
        $black->forceFill(['on_hand' => 2, 'reserved' => 2])->save();
        DB::transaction(function () use ($black): void {
            $locked = ProductVariant::lockForUpdate()->findOrFail($black->id);
            app(Inventory::class)->change($locked, 0, -1, 'Reservation expired');
            Queue::assertNothingPushed();
        });
        Queue::assertPushed(SendRestockAlerts::class, 1);
    }

    public function test_missing_prices_inactive_variants_and_expired_links_do_not_enroll_or_send(): void
    {
        [$black] = $this->variants();
        $row = RestockSubscription::factory()->create(['product_variant_id' => $black->id, 'confirmation_expires_at' => now()->subDay()]);
        $this->post(RestockAlerts::manageUrl($row), ['action' => 'confirm'])->assertRedirect();
        $this->assertNull($row->fresh()->confirmed_at);
        $black->update(['pkr_price_minor' => 0]);
        $this->post('/restock-alerts', ['variant_id' => $black->id, 'email' => 'x@example.test'])->assertNotFound();
        $this->get(RestockAlerts::buyUrl($black))->assertDontSee('Notify me when available');
        $black->update(['pkr_price_minor' => 1000000, 'is_active' => false]);
        $this->post('/restock-alerts', ['variant_id' => $black->id, 'email' => 'x@example.test'])->assertNotFound();
        $row->update(['confirmed_at' => now()]);
        $black->forceFill(['on_hand' => 5])->save();
        (new SendRestockAlerts($black->id))->handle();
        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_owner_demand_is_aggregated_without_addresses_and_public_page_never_queries_it(): void
    {
        [$black,$brown] = $this->variants();
        RestockSubscription::factory()->confirmed()->count(2)->create(['product_variant_id' => $black->id]);
        RestockSubscription::factory()->create(['product_variant_id' => $black->id]);
        RestockSubscription::factory()->confirmed()->create(['product_variant_id' => $brown->id, 'cancelled_at' => now()]);
        DB::enableQueryLog();
        $this->get(RestockAlerts::buyUrl($black))->assertOk();
        $this->assertFalse(collect(DB::getQueryLog())->contains(fn ($query) => str_contains($query['query'], 'restock_subscriptions')));
        DB::disableQueryLog();
        $this->get('/admin/restock-requests')->assertRedirect('/admin/login');
        config(['app.env' => 'local']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        Livewire::test(RestockRequests::class)->assertCanSeeTableRecords([$black])->assertCanNotSeeTableRecords([$brown])->assertTableColumnStateSet('waiting_count', 2, $black)->assertDontSee(RestockSubscription::first()->email);
        config(['app.env' => 'production']);
        $this->assertFalse(RestockRequests::canAccess());
    }

    public function test_failed_mail_handoff_stays_waiting_for_retry(): void
    {
        [$black] = $this->variants();
        $black->forceFill(['on_hand' => 1])->save();
        $row = RestockSubscription::factory()->confirmed()->create(['product_variant_id' => $black->id]);
        Event::listen(MessageSending::class, fn () => false);
        try {
            (new SendRestockAlerts($black->id))->handle();
            $this->fail('A suppressed message must not complete the request.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('not handed', $error->getMessage());
        }
        $this->assertNull($row->fresh()->completed_at);
        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
        Event::forget(MessageSending::class);
        (new SendRestockAlerts($black->id))->handle();
        $this->assertNotNull($row->fresh()->completed_at);
        $this->assertCount(1,Mail::mailer('array')->getSymfonyTransport()->messages());
    }
}
