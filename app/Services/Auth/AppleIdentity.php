<?php

namespace App\Services\Auth;

final readonly class AppleIdentity
{
    public function __construct(
        public string $subject,
        public ?string $email,
        public bool $emailVerified,
    ) {}
}
