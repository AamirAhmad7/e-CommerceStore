<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use App\Services\CommerceAccess;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return CommerceAccess::allowed($user);
    }

    public function view(User $user, Order $order): bool
    {
        return CommerceAccess::allowed($user);
    }

    public function update(User $user, Order $order): bool
    {
        return CommerceAccess::allowed($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, Order $order): bool
    {
        return false;
    }
}
