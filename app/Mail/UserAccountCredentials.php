<?php

namespace App\Mail;

use App\Models\User;
use App\Support\EmailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when an admin creates an account on Settings > Users, or reuses a
 * deactivated account's email (which reactivates it): the person's sign-in
 * email and the temporary password App\Support\TemporaryPassword generated.
 * Ported from the tender-management project.
 *
 * ShouldBeEncrypted: the queued job carries the plain password while it waits
 * in a queue (and in failed_jobs if sending fails), so its payload is
 * encrypted with APP_KEY. With QUEUE_CONNECTION=sync it is sent during the
 * request, which is why the create form shows a "Processing…" alert.
 */
class UserAccountCredentials extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $password,
        public readonly bool $reactivated = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->reactivated
                ? 'Your '.EmailBranding::SYSTEM_NAME.' account has been reactivated'
                : 'Your '.EmailBranding::SYSTEM_NAME.' account',
        );
    }

    public function content(): Content
    {
        // markdown (not view): renders through the shared mail layout in
        // resources/views/vendor/mail (logo in the card, teal button).
        return new Content(
            markdown: 'emails.users.credentials',
            with: [
                'name' => $this->user->name,
                'email' => $this->user->email,
                'password' => $this->password,
                'role' => $this->user->isAdmin() ? 'Administrator' : 'User',
                'reactivated' => $this->reactivated,
                'systemName' => EmailBranding::SYSTEM_NAME,
                'loginUrl' => route('login'),
            ],
        );
    }
}
