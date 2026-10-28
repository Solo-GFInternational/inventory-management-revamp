<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\AuditTrail;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Signing in and out, on the audit trail (Settings > Audit trail): each
 * sign-in and sign-out, and each failed attempt with the email that was
 * tried (never the password). Discovered by Laravel from the type-hints.
 */
class RecordSignIns
{
    public function handleLogin(Login $event): void
    {
        if ($event->user instanceof User) {
            AuditTrail::signedIn($event->user);
        }
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            AuditTrail::signedOut($event->user);
        }
    }

    public function handleFailed(Failed $event): void
    {
        AuditTrail::failedSignIn($event->user instanceof User ? $event->user : null, $event->credentials['email'] ?? null);
    }
}
