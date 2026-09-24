<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NumberGenerator
{
    public function unique(string $table, string $prefix): string
    {
        return $this->uniqueColumn($table, 'number', $prefix);
    }

    public function uniqueCode(string $table, string $prefix): string
    {
        return $this->uniqueColumn($table, 'code', $prefix);
    }

    public function uniqueColumn(string $table, string $column, string $prefix): string
    {
        do {
            $value = sprintf('%s-%s-%s', $prefix, now()->format('ymd'), Str::upper(Str::random(5)));
        } while (DB::table($table)->where($column, $value)->exists());

        return $value;
    }
}
