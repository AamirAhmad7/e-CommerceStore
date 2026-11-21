<?php

namespace Tests\Feature;

use App\Services\LocalRecovery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocalRecoveryTest extends TestCase
{
    public static function unsafeNames(): array
    {
        return [['leather_store'], ['leather_store_demo'], ['mysql'], ['leather_store_restore_x; DROP DATABASE leather_store'], ['../leather_store_restore_x']];
    }

    #[DataProvider('unsafeNames')]
    public function test_restore_cannot_target_working_databases_or_escape_its_namespace(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);
        LocalRecovery::restoreName($name);
    }

    public function test_restore_accepts_a_separate_named_destination(): void
    {
        LocalRecovery::restoreName('leather_store_restore_demo_20261122');
        $this->assertTrue(true);
    }
}
