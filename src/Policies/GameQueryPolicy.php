<?php

namespace Finxnz\PlayerCounter\Policies;

use App\Models\User;

class GameQueryPolicy
{
    public function before(User $user): ?bool
    {
        // Root admins can do everything
        return ($user->root_admin ?? false) ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->root_admin ?? false;
    }

    public function update(User $user, $model): bool
    {
        return $user->root_admin ?? false;
    }

    public function delete(User $user, $model): bool
    {
        return $user->root_admin ?? false;
    }
}
