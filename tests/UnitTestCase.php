<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case for pure unit tests that don't need Laravel.
 *
 * ⚠️ RÈGLE : Les tests qui héritent de cette classe :
 * - NE PEUVENT PAS utiliser la base de données
 * - NE PEUVENT PAS utiliser les facades Laravel
 * - DOIVENT mocker toutes leurs dépendances
 * - Time is frozen for deterministic tests
 */
abstract class UnitTestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Freeze time for deterministic test results
        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
        \Mockery::close();
    }
}
