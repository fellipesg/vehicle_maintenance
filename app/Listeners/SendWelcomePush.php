<?php

namespace App\Listeners;

use App\Enums\RegistrationSource;
use App\Events\UserRegistered;
use App\Services\WelcomePushNotifier;

class SendWelcomePush
{
    public function __construct(private WelcomePushNotifier $welcomePush) {}

    public function handle(UserRegistered $event): void
    {
        if (! in_array($event->source, [RegistrationSource::Api, RegistrationSource::Oauth], true)) {
            return;
        }

        $this->welcomePush->send($event->user);
    }
}
