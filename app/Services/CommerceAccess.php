<?php

namespace App\Services;

use App\Models\User;

final class CommerceAccess
{
    public static function owner(User $user): bool
    {
        $owners = config('store.admin_owner_ids', []);

        return in_array((int) $user->getKey(), $owners, true)
            || ($owners === [] && config('store.admin_manager_ids', []) === [] && config('app.env') === 'local');
    }

    public static function allowed(User $user): bool
    {
        return self::owner($user) || in_array((int) $user->getKey(), config('store.admin_manager_ids', []), true);
    }
}
