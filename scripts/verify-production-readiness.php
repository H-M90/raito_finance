<?php

$allowMissingRuntime = in_array('--allow-missing-runtime', $argv, true);
$errors = [];
$warnings = [];
$root = dirname(__DIR__);

if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    $errors[] = 'PHP 8.2.0 or newer is required.';
}
if (version_compare(PHP_VERSION, '8.3.0', '>=')) {
    $warnings[] = 'Project targets PHP 8.2 compatibility; newer PHP is allowed, but production should still be tested on PHP 8.2.';
}

$requiredFiles = [
    'artisan', 'composer.json', 'package.json', 'package-lock.json',
    'bootstrap/app.php', 'routes/web.php', '.env.example',
];
foreach ($requiredFiles as $file) {
    if (! is_file($root.'/'.$file)) {
        $errors[] = "Missing required file: {$file}";
    }
}

if (! is_file($root.'/composer.lock')) {
    if ($allowMissingRuntime) $warnings[] = 'composer.lock is missing. Generate it with composer update, review it, then commit it before production.';
    else $errors[] = 'composer.lock is missing. Generate it with composer update, review it, then commit it before production.';
}
if (! is_file($root.'/vendor/autoload.php')) {
    if ($allowMissingRuntime) $warnings[] = 'vendor dependencies are not installed.';
    else $errors[] = 'vendor dependencies are not installed.';
}

$envPath = $root.'/.env';
if (! is_file($envPath)) {
    if ($allowMissingRuntime) $warnings[] = '.env is missing.';
    else $errors[] = '.env is missing.';
} else {
    $env = file_get_contents($envPath) ?: '';
    $read = static function (string $name) use ($env): ?string {
        if (! preg_match('/^'.preg_quote($name, '/').'=(.*)$/m', $env, $matches)) return null;
        return trim($matches[1], " \t\n\r\0\x0B\"'");
    };
    if (! $read('APP_KEY')) $errors[] = 'APP_KEY is empty.';
    if (strtolower((string) $read('APP_DEBUG')) !== 'false') $errors[] = 'APP_DEBUG must be false in production.';
    if (in_array(strtolower((string) $read('QUEUE_CONNECTION')), ['', 'sync'], true)) $errors[] = 'QUEUE_CONNECTION must use a real queue driver.';
    if (strtolower((string) $read('APP_ENV')) === 'local') $warnings[] = 'APP_ENV is still local.';
}

$composer = json_decode(file_get_contents($root.'/composer.json') ?: '{}', true) ?: [];
foreach (($composer['require'] ?? []) as $package => $constraint) {
    if (! str_starts_with($package, 'ext-')) continue;
    $extension = substr($package, 4);
    if (! extension_loaded($extension)) {
        if ($allowMissingRuntime) $warnings[] = "Missing required PHP extension: {$extension}";
        else $errors[] = "Missing required PHP extension: {$extension}";
    }
}

foreach (['storage', 'bootstrap/cache'] as $directory) {
    if (! is_dir($root.'/'.$directory)) $errors[] = "Missing directory: {$directory}";
    elseif (! is_writable($root.'/'.$directory)) $warnings[] = "Directory is not writable: {$directory}";
}

foreach ($warnings as $warning) fwrite(STDERR, "WARNING: {$warning}\n");
foreach ($errors as $error) fwrite(STDERR, "ERROR: {$error}\n");

if ($errors !== []) exit(1);
echo "Production readiness checks passed.\n";
