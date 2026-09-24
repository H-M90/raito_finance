<?php

namespace App\Support;

use App\Models\SalesLead;
use App\Models\User;

class SalesLeadVisibility
{
    public const ASSIGNED_ONLY_PERMISSION = 'sales-leads.view-assigned-only';

    public static function restrictsToAssigned(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user instanceof User
            && $user->is_active
            && $user->role?->code !== 'admin'
            && $user->hasPermission(self::ASSIGNED_ONLY_PERMISSION);
    }

    /**
     * Apply the effective sales-lead visibility rules to an Eloquent query.
     *
     * The lead-specific assigned-only rule takes precedence over the older,
     * generic transactions.view-own rule because it is intentionally stricter:
     * only the current owner can see the lead, even if another user created it.
     */
    public static function apply($query, ?User $user = null)
    {
        $user ??= auth()->user();

        if (self::restrictsToAssigned($user)) {
            $query->where($query->qualifyColumn('owner_id'), $user->id);
        } elseif (OwnRecordVisibility::restricts($user)) {
            $query->where(function ($q) use ($user) {
                $q->where($q->qualifyColumn('owner_id'), $user->id)
                    ->orWhere($q->qualifyColumn('created_by'), $user->id);
            });
        }

        return $query;
    }

    public static function allows(SalesLead $lead, ?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if (self::restrictsToAssigned($user)) {
            return (int) $lead->owner_id === (int) $user->id;
        }

        if (OwnRecordVisibility::restricts($user)) {
            return (int) $lead->owner_id === (int) $user->id
                || (int) $lead->created_by === (int) $user->id;
        }

        return true;
    }
}
