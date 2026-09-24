<?php

namespace App\Support;

final class Money
{
    public static function minor(int|float|string|null $value, int $scale = 2): int
    {
        $value = trim((string) ($value ?? '0'));
        if ($value === '') return 0;
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = preg_replace('/\D/', '', $whole) ?: '0';
        $fraction = preg_replace('/\D/', '', $fraction);
        $fraction = substr(str_pad($fraction, $scale + 1, '0'), 0, $scale + 1);
        $base = ((int) $whole * (10 ** $scale)) + (int) substr($fraction, 0, $scale);
        if ((int) ($fraction[$scale] ?? '0') >= 5) $base++;
        return $negative ? -$base : $base;
    }

    public static function decimal(int $minor, int $scale = 2): string
    {
        $negative = $minor < 0;
        $minor = abs($minor);
        $factor = 10 ** $scale;
        $value = intdiv($minor, $factor).'.'.str_pad((string) ($minor % $factor), $scale, '0', STR_PAD_LEFT);
        return $negative ? '-'.$value : $value;
    }
}
