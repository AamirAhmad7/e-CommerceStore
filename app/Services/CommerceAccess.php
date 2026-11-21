<?php

namespace App\Services;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;

final class CommerceAccess
{
    public static function allowed(User $user): bool
    {
        return $user instanceof FilamentUser ? $user->canAccessPanel(Filament::getPanel('admin')) : config('app.env') === 'local';
    }
}
