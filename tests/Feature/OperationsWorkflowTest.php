<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Jobs\OperationsHeartbeat;
use App\Models\Order;
use App\Models\User;
use App\Services\CommerceAccess;
use App\Support\OperationsHealth;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OperationsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertSame(':memory:', config('database.connections.'.config('database.default').'.database'));
    }

    private function order(): Order
    {
        return Order::create(['number' => 'TEST-'.Str::random(8), 'guest_hash' => hash('sha256', Str::random()), 'access_token' => Str::random(64), 'payment_method' => 'cod', 'payment_status' => 'unpaid', 'currency' => 'PKR', 'customer' => ['name' => '=HYPERLINK("bad")', 'phone' => '03000000000'], 'subtotal_minor' => 10000, 'discount_minor' => 0, 'tax_minor' => 0, 'shipping_minor' => 0, 'total_minor' => 10000, 'tax_basis_points' => 0, 'shipping_name' => 'Test']);
    }

    public function test_exports_require_permission_escape_formulas_and_exclude_private_tokens(): void
    {
        $this->get(route('store.export', ['dataset' => 'orders']))->assertForbidden();
        config(['app.env' => 'local']);
        $this->actingAs(User::factory()->create());
        $order = $this->order();
        $response = $this->get(route('store.export', ['dataset' => 'orders']))->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString($order->number, $csv);
        $this->assertStringNotContainsString($order->access_token, $csv);
        $this->assertStringNotContainsString($order->guest_hash, $csv);
        $this->get(route('store.export', ['dataset' => 'secrets']))->assertNotFound();
    }

    public function test_failed_message_card_opens_only_affected_orders(): void
    {
        $this->withoutVite();
        config(['app.env' => 'local']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $order = $this->order();
        $other = $this->order();
        $order->events()->create(['event_key' => 'fixture', 'subject' => 'Fixture', 'message' => 'Test', 'body' => 'Test', 'recipient' => 'owner@example.test', 'last_error' => 'Isolated failure']);
        $card = collect(app(Dashboard::class)->attention())->firstWhere('label', 'Failed customer emails');
        $this->assertSame(1, $card['count']);
        parse_str(parse_url($card['url'], PHP_URL_QUERY), $query);
        Livewire::withQueryParams($query)->test(ListOrders::class)->assertCanSeeTableRecords([$order])->assertCanNotSeeTableRecords([$other]);
    }

    public function test_worker_heartbeat_is_database_scoped(): void
    {
        $key = OperationsHealth::key('worker');
        try {
            (new OperationsHeartbeat)->handle();
            $this->assertSame(now()->timestamp, Cache::store('file')->get($key));
            config(['database.connections.sqlite.database' => 'another-isolated-database']);
            $this->assertNotSame($key, OperationsHealth::key('worker'));
        } finally {
            Cache::store('file')->forget($key);
        }
    }

    public function test_owner_manager_permissions_and_encrypted_authenticator_storage(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        $stranger = User::factory()->create();
        config(['app.env' => 'production', 'store.admin_owner_ids' => [$owner->id], 'store.admin_manager_ids' => [$manager->id]]);
        $this->assertTrue(CommerceAccess::owner($owner));
        $this->assertTrue(CommerceAccess::allowed($manager));
        $this->assertFalse(CommerceAccess::owner($manager));
        $this->assertFalse(CommerceAccess::allowed($stranger));
        $this->assertFalse(Gate::forUser($manager)->allows('verify-payments'));
        $owner->saveAppAuthenticationSecret('ISOLATED-TEST-SECRET');
        $owner->saveAppAuthenticationRecoveryCodes(['ISOLATED-RECOVERY']);
        $this->assertSame('ISOLATED-TEST-SECRET', $owner->fresh()->getAppAuthenticationSecret());
        $this->assertStringNotContainsString('ISOLATED-TEST-SECRET', $owner->fresh()->getRawOriginal('app_authentication_secret'));
        $this->assertArrayNotHasKey('app_authentication_secret', $owner->fresh()->toArray());
        $this->assertArrayNotHasKey('app_authentication_recovery_codes', $owner->fresh()->toArray());
    }

    public function test_authenticator_profile_is_available_after_migration(): void
    {
        $this->withoutVite();
        config(['app.env' => 'local', 'store.admin_mfa_enabled' => true]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create());
        $this->get('/admin/profile')->assertOk()->assertSee('authentication');
    }
}
