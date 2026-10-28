<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('icons/favicon-32.png') }}" sizes="32x32">
    {{-- Only for the "Processing…" alert on forms that send an email (forgot password). --}}
    <script src="{{ asset('vendor/sweetalert2/sweetalert2.all.min.js') }}"></script>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{--
    The signed-out pages (sign in, forgot / reset password, confirm password,
    verify email), drawn as the site-visit / tender login: a navy page, a
    24rem white card, the logo stacked over the heading, a full-width button.
    Status and error messages are banners inside the card.

    Pages fill: title, form-action, heading, subheading, and content (the
    fields, after @csrf has been emitted here); optionally form-attributes,
    extra attributes for the <form> tag (e.g. data-processing), and after-form
    (anything below the card, e.g. a second form such as "Sign out").
--}}
<body class="flex min-h-full items-center justify-center bg-brand-navy px-4 py-8 antialiased">
    <main class="w-full max-w-sm space-y-4">
        <form method="POST" action="@yield('form-action')" @yield('form-attributes')
              class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            @csrf

            <div class="mb-2 flex flex-col items-center text-center">
                <img src="{{ asset('images/goodman-fielder-logo.png') }}" alt="Goodman Fielder"
                     class="mb-3 h-24 w-auto object-contain">
                <h1 class="text-lg leading-7 font-semibold text-slate-900">@yield('heading')</h1>
                <p class="mt-1 text-sm text-slate-500">@yield('subheading')</p>
            </div>

            @if (session('status'))
                <div class="flex items-start gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800 ring-1 ring-emerald-600/20" role="status">
                    <x-icon name="check-circle" class="mt-px size-5 text-emerald-600" />
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="flex items-start gap-2 rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700 ring-1 ring-danger-600/20" role="alert">
                    <x-icon name="exclamation-circle" class="mt-px size-5 text-danger-600" />
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            @yield('content')
        </form>

        @yield('after-form')
    </main>
</body>
</html>
