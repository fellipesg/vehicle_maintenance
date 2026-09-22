<?php

namespace App\Events;

use App\Enums\RegistrationSource;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public RegistrationSource $source,
    ) {}
}
