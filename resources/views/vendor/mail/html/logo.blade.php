{{--
    The GF logo, shown inside the email card above the greeting (rather than
    above the card). Part of the shared email look (as in the
    tender-management and site-visit projects): logo inside the card, rounded card, rounded
    dark-teal buttons.

    Embedded via cid: (App\Support\EmailBranding::LOGO_CID) rather than a URL,
    since a linked image only loads if the app is reachable from wherever the
    recipient opens the email. App\Listeners\EmbedGfLogoInEmails attaches the
    image to every email that shows it.

    The size is set three ways on purpose: width/height attributes (the only
    thing the Outlook desktop app honours — without them it shows the
    full-size PNG), inline style, and the .logo theme rule. Centred with a
    table cell, which works in every email client, unlike margin: auto.
--}}
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center" style="padding-bottom: 20px;">
<img src="cid:{{ \App\Support\EmailBranding::LOGO_CID }}" class="logo" alt="Goodman Fielder" width="78" height="64" style="display: block; width: 78px; height: 64px; max-width: 78px; border: 0;">
</td>
</tr>
</table>
