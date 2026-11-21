<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

final class LocalRecovery
{
    public function connection(): array
    {
        $connection = DB::connection()->getConfig();
        if (! app()->environment(['local', 'demo']) || ($connection['driver'] ?? '') !== 'mysql' || ! in_array($connection['host'], ['localhost', '127.0.0.1'], true)) {
            throw new \RuntimeException('Local recovery only supports local MySQL in APP_ENV=local.');
        }

        return $connection;
    }

    public static function restoreName(string $name): void
    {
        if (! preg_match('/^leather_store_restore_[a-z0-9_]{1,32}$/D', $name)) {
            throw new \InvalidArgumentException('Use a new leather_store_restore_ name with lowercase letters, digits and underscores.');
        }
    }

    public function binary(string $directory, string $name): string
    {
        $path = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$name.(PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
        if (! is_file($path)) {
            throw new \RuntimeException('MySQL tool not found: '.$path);
        }

        return $path;
    }

    public function process(string $binary, array $arguments, array $connection): Process
    {
        return new Process(array_merge([$binary, '--host='.$connection['host'], '--port='.$connection['port'], '--user='.$connection['username']], $arguments), base_path(), ['MYSQL_PWD' => $connection['password']], timeout: 300);
    }

    /** @return array<string,string> */
    public function hashes(string $directory): array
    {
        $hashes = [];
        foreach (File::allFiles($directory) as $file) {
            if ($file->isLink()) {
                throw new \RuntimeException('Backup symlinks are not supported.');
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
            if ($relative !== 'manifest.json') {
                $hashes[$relative] = hash_file('sha256', $file->getPathname());
            }
        }
        ksort($hashes);

        return $hashes;
    }
}
