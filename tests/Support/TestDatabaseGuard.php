<?php

namespace Tests\Support;

use RuntimeException;

/**
 * Refuses to let database-resetting tests touch a non-test database.
 *
 * PostgreSQL (the target, used by CI) needs a name ending in "_test".
 * SQLite is accepted for local runs only as :memory: or a *_test.sqlite file.
 */
final class TestDatabaseGuard
{
    public const SUFFIX = '_test';

    public static function assertSafe(?string $driver, ?string $database): void
    {
        $database ??= '';

        $safe = match ($driver) {
            'pgsql' => str_ends_with($database, self::SUFFIX),
            'sqlite' => $database === ':memory:' || str_ends_with(basename($database), self::SUFFIX.'.sqlite'),
            default => false,
        };

        if (! $safe) {
            throw new RuntimeException(sprintf(
                'Refusing to reset database "%s" (%s): use PostgreSQL "*%s", or SQLite ":memory:" / "*%s.sqlite".',
                $database,
                $driver ?? 'no driver',
                self::SUFFIX,
                self::SUFFIX,
            ));
        }
    }
}
