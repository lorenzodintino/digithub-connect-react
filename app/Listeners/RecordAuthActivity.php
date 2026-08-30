<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\Activity;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class RecordAuthActivity
{
    /**
     * Write down who came in, who left, and who tried and failed.
     *
     * A failed attempt keeps the email that was typed: without it the line
     * would say that somebody, somewhere, got a password wrong — which is not
     * something anybody can act on. It is the one place where the register
     * holds an address on purpose.
     */
    public function handle(Login|Logout|Failed $event): void
    {
        match (true) {
            $event instanceof Login => $this->record('auth.login', $event->user),
            $event instanceof Logout => $this->record('auth.logout', $event->user),
            $event instanceof Failed => Activity::log('auth.failed', null, [
                'email' => $event->credentials['email'] ?? null,
            ]),
        };
    }

    /**
     * Record an event about somebody who is really there.
     */
    private function record(string $action, mixed $user): void
    {
        if (! $user instanceof User) {
            return;
        }

        Activity::log($action, $user, ['role' => $user->role->value], $user->tenant_id, $user->name);
    }
}
