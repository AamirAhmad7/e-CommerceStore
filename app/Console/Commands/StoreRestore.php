<?php

namespace App\Console\Commands;

use App\Services\LocalRecovery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class StoreRestore extends Command
{
    protected $signature = 'store:restore {backup : Absolute path to a local backup} {database : New leather_store_restore_ database} {--mysql-bin=C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin}';

    protected $description = 'Restore into a brand-new isolated database and non-public directory; never overwrites an existing destination';

    public function handle(LocalRecovery $recovery): int
    {
        $connection = $recovery->connection();
        $name = $this->argument('database');
        LocalRecovery::restoreName($name);
        $source = realpath($this->argument('backup'));
        $backupRoot = realpath(storage_path('app/backups'));
        if (! $source || ! $backupRoot || ! str_starts_with(str_replace('\\', '/', $source), str_replace('\\', '/', $backupRoot).'/')) {
            throw new \RuntimeException('Restore only accepts a backup under storage/app/backups.');
        }
        $manifest = json_decode(File::get($source.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        if (($manifest['version'] ?? null) !== 1 || $recovery->hashes($source) !== ($manifest['files'] ?? null)) {
            throw new \RuntimeException('Backup integrity verification failed.');
        }
        if (DB::select('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$name])) {
            throw new \RuntimeException('The restore database already exists; choose a new name.');
        }
        $destination = storage_path('app/restores/'.$name);
        if (file_exists($destination)) {
            throw new \RuntimeException('The restore directory already exists; choose a new name.');
        }
        $binary = $recovery->binary($this->option('mysql-bin'), 'mysql');
        DB::statement('CREATE DATABASE '.$name.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $stream = fopen($source.'/database.sql', 'rb');
        try {
            $process = $recovery->process($binary, [$name], $connection);
            $process->setInput($stream);
            $process->mustRun();
        } finally {
            fclose($stream);
        }
        File::ensureDirectoryExists($destination);
        foreach (['media', 'private'] as $folder) {
            if (! File::copyDirectory($source.'/'.$folder, $destination.'/'.$folder)) {
                throw new \RuntimeException('Restored file copy failed. The isolated destination is incomplete.');
            }
        }
        $expected = array_filter($manifest['files'], fn ($key) => str_starts_with($key, 'media/') || str_starts_with($key, 'private/'), ARRAY_FILTER_USE_KEY);
        if ($recovery->hashes($destination) !== $expected) {
            throw new \RuntimeException('Restored media checksum verification failed.');
        }
        $this->info('Restored database: '.$name);
        $this->info('Verified media and private files: '.count($expected).' files at '.$destination);
        $this->line('No app environment, public link, scheduler, worker or running store was changed. Restore destinations must stay offline until mail, queues and private storage are isolated.');

        return self::SUCCESS;
    }
}
