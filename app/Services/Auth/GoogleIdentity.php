<?php

namespace App\Services\Auth;

final readonly class GoogleIdentity
{
    public function __construct(
        public string $subject,
        public string $email,
        public ?string $name,
        public ?string $avatar,
    ) {}
}
