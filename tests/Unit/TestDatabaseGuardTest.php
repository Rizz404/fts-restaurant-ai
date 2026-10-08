<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabaseGuard;

class TestDatabaseGuardTest extends TestCase
{
    /**
     * @return array<string, array{?string, ?string}>
     */
    public static function safe(): array
    {
        return [
            'pgsql test' => ['pgsql', 'restaurant_ai_test'],
            'sqlite memory' => ['sqlite', ':memory:'],
            'sqlite test file' => ['sqlite', '/tmp/restaurant_ai_test.sqlite'],
        ];
    }

    /**
     * @return array<string, array{?string, ?string}>
     */
    public static function unsafe(): array
    {
        return [
            'pgsql development' => ['pgsql', 'restaurant_ai'],
            'pgsql test in middle' => ['pgsql', 'restaurant_test_ai'],
            'sqlite development file' => ['sqlite', 'database/database.sqlite'],
            'mysql' => ['mysql', 'restaurant_ai_test'],
            'missing' => [null, null],
        ];
    }

    #[DataProvider('safe')]
    public function test_accepts_test_databases(?string $driver, ?string $database): void
    {
        TestDatabaseGuard::assertSafe($driver, $database);

        $this->addToAssertionCount(1);
    }

    #[DataProvider('unsafe')]
    public function test_rejects_other_databases(?string $driver, ?string $database): void
    {
        $this->expectException(RuntimeException::class);

        TestDatabaseGuard::assertSafe($driver, $database);
    }
}
