<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserFcmToken;

class UserFcmTokenPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, UserFcmToken $userFcmToken): bool
    {
        return $userFcmToken->user_id === $user->id;
    }

    public function delete(User $user, UserFcmToken $userFcmToken): bool
    {
        return $userFcmToken->user_id === $user->id;
    }
}
