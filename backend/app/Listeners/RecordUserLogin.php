<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class RecordUserLogin
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        // Login activity recording will be implemented here.
    }
}
