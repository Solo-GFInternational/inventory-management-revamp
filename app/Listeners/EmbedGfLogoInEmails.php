<?php

namespace App\Listeners;

use App\Support\EmailBranding;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

/**
 * Attaches the Goodman Fielder logo to every outgoing email that shows it.
 *
 * The shared mail layout puts the logo inside every email's card as an
 * embedded image (cid:EmailBranding::LOGO_CID), which only displays if the
 * image is attached to that email. Doing it here, once, covers every email
 * — including Laravel's own "Forgot password?" email — so new emails don't
 * need to attach it themselves. Never adds a second copy.
 */
class EmbedGfLogoInEmails
{
    public function handle(MessageSending $event): void
    {
        $email = $event->message;

        if (! $email instanceof Email) {
            return;
        }

        $html = $email->getHtmlBody();

        if (! is_string($html) || ! str_contains($html, 'cid:'.EmailBranding::LOGO_CID)) {
            return;
        }

        foreach ($email->getAttachments() as $part) {
            if ($part instanceof DataPart && $part->hasContentId() && $part->getContentId() === EmailBranding::LOGO_CID) {
                return;
            }
        }

        $email->addPart(
            (new DataPart(new File(EmailBranding::logoPath()), 'gf-logo', 'image/png'))
                ->asInline()
                ->setContentId(EmailBranding::LOGO_CID)
        );
    }
}
