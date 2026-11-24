<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class OperationsHealth
{
    public static function key(string $name): string
    {
        return 'operations:'.hash('sha256', (string) config('database.connections.'.config('database.default').'.database')).':'.$name;
    }

    /** @return array<int, array{title: string, detail: string, healthy: bool}> */
    public static function checks(): array
    {
        $rows = [];
        foreach (['scheduler' => 'Scheduled tasks', 'worker' => 'Background worker'] as $key => $title) {
            $seen = Cache::store('file')->get(self::key($key));
            $healthy = is_numeric($seen) && (int) $seen > now()->subMinutes(10)->timestamp;
            $rows[] = ['title' => $title, 'healthy' => $healthy, 'detail' => $healthy ? 'Heartbeat received within the last 10 minutes.' : 'No recent heartbeat. Check the scheduler and database queue worker.'];
        }
        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $rows[] = ['title' => 'Failed background jobs', 'healthy' => $failed === 0, 'detail' => $failed.' failed jobs. Review the failure before retrying; never blindly repeat financial operations.'];
        $mailer = config('mail.default');
        $rows[] = ['title' => 'Customer email transport', 'healthy' => ! in_array($mailer, ['log', 'array'], true), 'detail' => in_array($mailer, ['log', 'array'], true) ? 'Messages are captured locally, not delivered to customers. Configure a mail provider before launch.' : 'A delivery transport is configured. Send an owner-approved test to verify actual inbox delivery.'];
        $backup = Cache::store('file')->get(self::key('backup'));
        $rows[] = ['title' => 'Last local backup', 'healthy' => is_numeric($backup) && $backup > now()->subDays(2)->timestamp, 'detail' => $backup ? 'Last successful backup: '.date('Y-m-d H:i', $backup).'. A separate restore drill and off-site copy are still required.' : 'No successful backup recorded. Existing store:backup and store:restore commands support isolated local recovery.'];

        $rows[] = ['title' => 'Admin sign-in protection', 'healthy' => (bool) config('store.admin_mfa_enabled'), 'detail' => config('store.admin_mfa_enabled') ? 'Authenticator enrollment is available under your admin profile. Each user must enroll and save recovery codes.' : 'Authenticator sign-in is not enabled. Apply the approved users-table migration, then enable STORE_ADMIN_MFA_ENABLED and enroll from your profile.'];
        $rows[] = ['title' => 'Staff access', 'healthy' => count(config('store.admin_owner_ids', [])) > 0, 'detail' => count(config('store.admin_owner_ids', [])) > 0 ? 'Owner and manager account IDs are explicitly configured; managers cannot verify payments or record refunds.' : 'Local development access is retained. Configure STORE_ADMIN_OWNER_IDS and optional STORE_ADMIN_MANAGER_IDS before hosting.'];
        $rows[] = ['title' => 'WhatsApp customer updates', 'healthy' => false, 'detail' => 'Automatic sending is not connected. Choose a WhatsApp Business provider, approved templates, and consent flow. Existing customer support chat links still work.'];
        $rows[] = ['title' => 'Courier tracking integration', 'healthy' => false, 'detail' => 'Tracking links and recorded courier events work manually. Choose a courier before connecting automatic status updates.'];

        return $rows;
    }
}
