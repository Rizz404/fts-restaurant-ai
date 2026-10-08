<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\TestDatabaseGuard;

abstract class TestCase extends BaseTestCase
{
    private const RESETTING_TRAITS = [
        RefreshDatabase::class,
        LazilyRefreshDatabase::class,
        DatabaseMigrations::class,
        DatabaseTruncation::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Tests never depend on a running Vite dev server or a build.
        $this->withoutVite();
    }

    /**
     * Checks the database before any reset trait runs.
     *
     * @return array<class-string, class-string>
     */
    protected function setUpTraits()
    {
        if (array_intersect(self::RESETTING_TRAITS, class_uses_recursive(static::class)) !== []) {
            $connection = config('database.default');

            TestDatabaseGuard::assertSafe(
                config("database.connections.{$connection}.driver"),
                config("database.connections.{$connection}.database"),
            );
        }

        return parent::setUpTraits();
    }
}
