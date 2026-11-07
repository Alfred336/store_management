<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

/** Record the timestamp of a successful login on the user model. */
class RecordLastLogin
{
    public function handle(Login $event): void
    {
        $event->user->forceFill(['last_login_at' => now()])->save();
    }
}
