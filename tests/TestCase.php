<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // The SQLite schema is prepared once by tests/bootstrap.php.
    // Feature tests continue to use Laravel's RefreshDatabase transactions.
}
