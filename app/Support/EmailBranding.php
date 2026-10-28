<?php

namespace App\Support;

/**
 * Ported from the tender-management project. The Goodman Fielder logo shown
 * inside every email's card (resources/views/vendor/mail/html/logo.blade.php),
 * embedded as an inline image under this Content-ID by
 * App\Listeners\EmbedGfLogoInEmails, and the system name the emails use.
 */
final class EmailBranding
{
    public const LOGO_CID = 'gf-logo@inventory-management';

    public const SYSTEM_NAME = 'Inventory Management System';

    public static function logoPath(): string
    {
        return public_path('images/goodman-fielder-logo.png');
    }
}
