<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;

$databaseDirectory = realpath(__DIR__.'/../database');
if ($databaseDirectory === false) {
    throw new RuntimeException('The database directory does not exist.');
}

$databasePath = $databaseDirectory.DIRECTORY_SEPARATOR.'testing.sqlite';
if (is_file($databasePath) && ! @unlink($databasePath)) {
    throw new RuntimeException('Unable to reset SQLite test database: '.$databasePath);
}
if (@touch($databasePath) === false) {
    throw new RuntimeException('Unable to create SQLite test database: '.$databasePath);
}

putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE='.$databasePath);
putenv('DB_FOREIGN_KEYS=true');
$_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'testing';
$_ENV['DB_CONNECTION'] = $_SERVER['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = $databasePath;
$_ENV['DB_FOREIGN_KEYS'] = $_SERVER['DB_FOREIGN_KEYS'] = 'true';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$exitCode = Artisan::call('migrate:fresh', ['--force' => true]);
if ($exitCode !== 0) {
    throw new RuntimeException("Unable to prepare SQLite test database.\n".Artisan::output());
}

// RefreshDatabase must not run migrate:fresh again after the bootstrap has
// already prepared the exact schema for this PHPUnit process.
RefreshDatabaseState::$migrated = true;

// Laravel's bootstrap installs its exception handlers before PHPUnit starts
// each test. Leave handler ownership with PHPUnit until a test boots the app.
restore_error_handler();
restore_exception_handler();
