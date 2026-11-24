<?php

namespace App\Console\Commands;

use App\Services\LocalRecovery;
use App\Support\OperationsHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class StoreBackup extends Command
{
    protected $signature = 'store:backup {--mysql-bin=C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin}';

    protected $description = 'Create a private local MySQL and media backup, without changing the source store';

    public function handle(LocalRecovery $recovery): int
    {
        $connection = $recovery->connection();
        $database = $connection['database'];
        $directory = storage_path('app/backups/'.date('Ymd-His').'-'.bin2hex(random_bytes(3)));
        File::ensureDirectoryExists($directory);
        $process = $recovery->process($recovery->binary($this->option('mysql-bin'), 'mysqldump'), ['--single-transaction', '--quick', '--skip-lock-tables', '--no-tablespaces', '--set-gtid-purged=OFF', '--hex-blob', '--result-file='.$directory.'/database.sql', $database], $connection);
        $process->mustRun();
        $demo = $database === 'leather_store_demo';
        $disk = $demo ? 'demo' : 'public';
        $public = config('filesystems.disks.'.$disk.'.root');
        $private = storage_path('app/private'.($demo ? '/demo-bank-proofs' : ''));
        foreach (['media' => $public, 'private' => $private] as $name => $source) {
            File::ensureDirectoryExists($directory.'/'.$name);
            if (is_dir($source) && ! File::copyDirectory($source, $directory.'/'.$name)) {
                throw new \RuntimeException('Media copy failed. Incomplete backup must not be restored.');
            }
        }
        File::put($directory.'/manifest.json', json_encode(['version' => 1, 'source_database' => $database, 'media_disk' => $disk, 'private_scope' => $demo ? 'demo-bank-proofs' : 'private', 'created_at' => now()->toIso8601String(), 'app_key_fingerprint' => hash('sha256', config('app.key')), 'files' => $recovery->hashes($directory)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        Cache::store('file')->put(OperationsHealth::key('backup'), now()->timestamp, now()->addDays(30));
        $this->info('Backup complete: '.$directory);
        $this->line('Source: '.$database.'. Preserve the original APP_KEY separately. Pause uploads and workers for coordinated production backups.');

        return self::SUCCESS;
    }
}
