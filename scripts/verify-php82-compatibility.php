<?php

$root = dirname(__DIR__);
$paths = ['app', 'bootstrap', 'config', 'database', 'routes', 'tests', 'scripts'];
$patterns = [
    '/\\bjson_validate\\s*\\(/' => 'json_validate() requires PHP 8.3',
    '/\\bmb_str_pad\\s*\\(/' => 'mb_str_pad() requires PHP 8.3',
    '/\\bstr_increment\\s*\\(/' => 'str_increment() requires PHP 8.3',
    '/\\bstr_decrement\\s*\\(/' => 'str_decrement() requires PHP 8.3',
    '/#\\[\\\\?Override(?:\\([^]]*\\))?\\]/' => '#[Override] requires PHP 8.3',
    '/\\bconst\\s+(?:string|int|float|bool|array|object|mixed|iterable|callable)\\s+[A-Z_][A-Z0-9_]*\\s*=/' => 'Typed class constants require PHP 8.3',
    '/::\\s*\\{/' => 'Dynamic class constant fetch requires PHP 8.3',
    '/\\b(?:public|protected|private)\\s*\\(set\\)/' => 'Asymmetric property visibility requires PHP 8.4',
    '/#\\[\\\\?Deprecated(?:\\([^]]*\\))?\\]/' => '#[Deprecated] requires PHP 8.4',
];

$errors = [];
foreach ($paths as $relative) {
    $directory = $root.'/'.$relative;
    if (! is_dir($directory)) continue;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') continue;
        if (realpath($file->getPathname()) === realpath(__FILE__)) continue;
        $content = file_get_contents($file->getPathname()) ?: '';
        foreach ($patterns as $pattern => $message) {
            if (preg_match($pattern, $content, $m, PREG_OFFSET_CAPTURE)) {
                $before = substr($content, 0, $m[0][1]);
                $line = substr_count($before, "\n") + 1;
                $errors[] = str_replace($root.'/', '', $file->getPathname()).":{$line}: {$message}";
            }
        }
    }
}

$composer = json_decode(file_get_contents($root.'/composer.json') ?: '{}', true) ?: [];
if (($composer['require']['php'] ?? null) !== '^8.2') $errors[] = 'composer.json must require php ^8.2';
if (($composer['require']['laravel/framework'] ?? null) !== '^12.0') $errors[] = 'composer.json must require laravel/framework ^12.0';
if (($composer['require-dev']['phpunit/phpunit'] ?? null) !== '^11.5.50') $errors[] = 'PHPUnit must remain on an 8.2-compatible 11.x release';
if (($composer['config']['platform']['php'] ?? null) !== '8.2.0') $errors[] = 'Composer platform PHP must be pinned to 8.2.0 for dependency resolution';
if (($composer['require']['maennchen/zipstream-php'] ?? null) !== '3.1.2') $errors[] = 'ZipStream must be pinned to 3.1.2 for PHP 8.2 compatibility';

foreach ($errors as $error) fwrite(STDERR, "ERROR: {$error}\n");
if ($errors !== []) exit(1);

echo "PHP 8.2 compatibility static checks passed.\n";
