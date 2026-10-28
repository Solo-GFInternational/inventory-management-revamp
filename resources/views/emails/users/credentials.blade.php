{{--
    Built on the shared mail layout (resources/views/vendor/mail), ported from
    tender-management: Goodman Fielder logo inside the card, rounded card,
    rounded teal button. See App\Mail\UserAccountCredentials.
--}}
<x-mail::message>
# Hi {{ $name }},

@if ($reactivated)
Your account on the Goodman Fielder {{ $systemName }} has been reactivated with a new temporary password.
@else
An account has been created for you on the Goodman Fielder {{ $systemName }}.
@endif

**Sign-in email:** {{ $email }}

**Temporary password:** `{{ $password }}`

**Role:** {{ $role }}

<x-mail::button :url="$loginUrl">
Sign in
</x-mail::button>

If the button doesn't work, open {{ $loginUrl }} in your browser.

After signing in, please change this password under **Your profile** (open your name at the top right).

{{-- Italic, set further down than a normal line: inline style because
     email clients drop most stylesheet rules. --}}
<p style="margin-top: 28px; font-style: italic;">Keep this password private and don't forward this email.</p>

Regards,<br>
{{ $systemName }}
</x-mail::message>
