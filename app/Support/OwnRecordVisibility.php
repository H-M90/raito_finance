<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OwnRecordVisibility
{
    public const PERMISSION = 'transactions.view-own';

    public static function restricts(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user instanceof User
            && $user->is_active
            && $user->role?->code !== 'admin'
            && $user->hasPermission(self::PERMISSION);
    }

    public static function apply($query, ?User $user = null)
    {
        $user ??= auth()->user();

        if (self::restricts($user)) {
            $query->where($query->qualifyColumn('created_by'), $user->id);
        }

        return $query;
    }

    public static function applyColumn($query, string $qualifiedColumn, ?User $user = null)
    {
        $user ??= auth()->user();
        if (self::restricts($user)) {
            $query->where($qualifiedColumn, $user->id);
        }
        return $query;
    }

    public static function allows(Model $record, ?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! self::restricts($user) || ! array_key_exists('created_by', $record->getAttributes())) {
            return true;
        }

        return (int) $record->getAttribute('created_by') === (int) $user->id;
    }
}
