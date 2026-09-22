<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Mail\WelcomeUserMail;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmail
{
    public function handle(UserRegistered $event): void
    {
        Mail::to($event->user)->send(new WelcomeUserMail($event->user, $event->source));
    }
}
