<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Notifications\NewUserSignupAlertNotification;
use Illuminate\Support\Facades\Notification;

class SendSignupOpsAlert
{
    public function handle(UserRegistered $event): void
    {
        Notification::route('mail', (string) config('legal.support_email'))
            ->notify(new NewUserSignupAlertNotification($event->user, $event->source));
    }
}
