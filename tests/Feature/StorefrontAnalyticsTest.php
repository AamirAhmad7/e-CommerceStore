<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\StorefrontAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorefrontAnalyticsTest extends TestCase
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
        Cache::flush();
        config(['store.analytics_enabled' => true]);
    }

    public function test_page_requests_are_aggregated_without_private_identifiers(): void
    {
        $headers = ['User-Agent' => 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537 Chrome/120.0 Mobile Safari/537', 'Referer' => 'https://www.google.com/search?q=private-email@example.test'];
        $this->get('/shop?q=private+search', $headers)->assertOk();
        $this->get('/shop?q=another+search', $headers)->assertOk();
        $this->assertDatabaseCount('storefront_page_stats', 1);
        $row = DB::table('storefront_page_stats')->sole();
        $this->assertSame(2, (int) $row->views);
        $this->assertSame('/shop', $row->page);
        $this->assertSame('www.google.com', $row->source);
        $this->assertSame('Android', $row->platform);
        $this->assertSame('Chrome', $row->browser);
        $this->assertSame('Phone', $row->device);
        $this->assertStringNotContainsString('private', json_encode($row));
        $summary = StorefrontAnalytics::summary();
        $this->assertSame(2, array_sum(array_column($summary['daily'], 'views')));
        $this->assertCount(14, $summary['daily']);
    }

    public function test_private_pages_staff_bots_and_privacy_signals_are_not_counted(): void
    {
        $this->get('/shop', ['DNT' => '1'])->assertOk();
        $this->get('/shop', ['Sec-GPC' => '1'])->assertOk();
        $this->get('/shop', ['User-Agent' => 'Googlebot'])->assertOk();
        $this->get('/find-order')->assertOk();
        $this->get('/cart')->assertOk();
        $this->actingAs(User::factory()->create())->get('/shop')->assertOk();
        $this->assertDatabaseCount('storefront_page_stats', 0);
    }

    public function test_retention_removes_only_expired_aggregates(): void
    {
        $this->get('/shop')->assertOk();
        DB::table('storefront_page_stats')->update(['day' => now()->subDays(91)->toDateString()]);
        $this->artisan('analytics:prune')->assertSuccessful();
        $this->assertDatabaseCount('storefront_page_stats', 0);
    }
}
