<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Inventory') — {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('icons/favicon-32.png') }}" sizes="32x32">
    {{--
        Alerts: SweetAlert everywhere, except pages trialling TallStackUI
        (@section('alerts', 'tallstackui') — the Inventory pages). Either way
        app.js shows them through window.gfSwal; resources/js/tallstackui-alerts.js
        swaps in the TallStackUI version. TallStackUI needs Livewire's script,
        which is where Alpine comes from; all of it is served from this app.
    --}}
    @php
        $tallStackUi = trim($__env->yieldContent('alerts')) === 'tallstackui';
    @endphp
    @if ($tallStackUi)
        <tallstackui:script />
        @livewireStyles
    @else
        {{-- Served from this app (public/vendor/sweetalert2), not a CDN — as on the site-visit and tender projects. --}}
        <script src="{{ asset('vendor/sweetalert2/sweetalert2.all.min.js') }}"></script>
    @endif
    {{-- Flashed status messages and validation errors, shown as SweetAlert dialogs by resources/js/app.js. --}}
    @php
        $gfFlash = array_filter([
            'status' => session('status'),
            'title' => session('status_title'),
            'error' => session('error'),
            'errors' => $errors->any() ? $errors->all() : null,
        ]);
    @endphp
    <script>window.gfFlash = {{ \Illuminate\Support\Js::from((object) $gfFlash) }};</script>
    @fonts
    {{-- The TallStackUI adapter goes first, so app.js finds it when it sets up window.gfSwal. --}}
    @vite($tallStackUi
        ? ['resources/css/app.css', 'resources/js/tallstackui-alerts.js', 'resources/js/app.js']
        : ['resources/css/app.css', 'resources/js/app.js'])
    {{-- Page-only scripts (e.g. the scanner's @vite('resources/js/scanner.js')); they run after app.js. --}}
    @stack('scripts')
</head>
<body class="h-full bg-gray-50 text-gray-950 antialiased">
<div class="min-h-full">
    {{--
        The site-visit / tender top bar: navy, the logo at the left, the
        system's name large in the middle, the user menu at the right, and
        the navigation — each item with its icon — across a second row.
    --}}
    <header class="bg-brand-navy text-white">
        <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-x-4 gap-y-1.5 px-4 pt-4 pb-3 md:px-6 lg:px-8">
            <a href="{{ route('dashboard') }}" class="justify-self-start" title="Dashboard">
                <img src="{{ asset('images/goodman-fielder-logo.png') }}" alt="Goodman Fielder"
                     class="h-[4.5rem] w-auto object-contain">
            </a>

            <p class="flex flex-col items-center justify-center px-2 text-center text-[clamp(1.125rem,1rem+1vw,1.75rem)] leading-tight font-bold sm:flex-row sm:flex-wrap sm:items-baseline sm:gap-x-[0.35em]">
                <span class="whitespace-nowrap">Inventory</span>
                <span class="whitespace-nowrap">Management System</span>
            </p>

            {{-- User menu: a <details> dropdown, no JS needed. --}}
            <details class="group relative justify-self-end">
                <summary class="flex cursor-pointer list-none items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-white hover:bg-white/10 [&::-webkit-details-marker]:hidden">
                    <span class="flex size-9 items-center justify-center rounded-full bg-white/15 text-sm font-semibold">
                        {{ \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('') }}
                    </span>
                    <span class="hidden font-medium md:inline">{{ auth()->user()->name }}</span>
                    <x-icon name="chevron-down" class="hidden size-4 text-white/70 transition group-open:rotate-180 md:block" />
                </summary>

                <div class="absolute right-0 z-20 mt-2 w-60 overflow-hidden rounded-lg bg-white text-gray-950 shadow-lg ring-1 ring-gray-950/5">
                    <div class="border-b border-gray-200 px-4 py-3">
                        <p class="truncate text-sm font-medium">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-gray-500">{{ auth()->user()->isAdmin() ? 'Administrator' : 'User' }}</p>
                    </div>
                    <div class="p-1">
                        <a href="{{ route('profile.edit') }}"
                           @class([
                               'flex items-center gap-2 rounded-md px-3 py-2 text-sm hover:bg-gray-50',
                               'text-primary-600' => request()->routeIs('profile.*'),
                           ])>
                            <x-icon name="user-circle" class="size-5 text-gray-400" />
                            Your profile
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm hover:bg-gray-50">
                                <x-icon name="arrow-left-start-on-rectangle" class="size-5 text-gray-400" />
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </details>

            <nav class="col-span-3 mt-1 flex w-full justify-evenly border-t border-white/12 pt-2">
                <x-nav-link :href="route('dashboard')" icon="home" :active="request()->routeIs('dashboard')">Dashboard</x-nav-link>
                {{-- One page, two tabs (x-inventory-tabs); an item's own page counts as part of it. --}}
                <x-nav-link :href="route('inventory.index')" icon="clipboard-document-check"
                            :active="request()->routeIs('inventory.index') || request()->routeIs('inventory.show') || request()->routeIs('stock.*')">
                    Inventory and Stock
                </x-nav-link>
                <x-nav-link :href="route('scanner.index')" icon="viewfinder-circle" :active="request()->routeIs('scanner.*')">Scanner</x-nav-link>
                @if (auth()->user()->isAdmin())
                    <x-nav-link :href="route('inventory.pending')" icon="clock" :active="request()->routeIs('inventory.pending')">
                        Approvals
                        @if ($pendingBadge = \App\Models\Inventory::where('status', 'pending')->where('active_yn', true)->count())
                            <x-slot:badge>
                                <span class="rounded-md bg-amber-400 px-1.5 text-xs font-semibold text-amber-950">{{ $pendingBadge }}</span>
                            </x-slot:badge>
                        @endif
                    </x-nav-link>
                    <x-nav-link :href="route('users.index')" icon="cog-6-tooth"
                                :active="request()->routeIs('settings') || request()->routeIs('users.*') || request()->routeIs('audit.*') || request()->routeIs('types.*')">
                        Settings
                    </x-nav-link>
                @endif
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8 md:px-6 lg:px-8">
        @yield('content')
    </main>
</div>
@if ($tallStackUi)
    <x-ts-dialog />
    <x-ts-toast />
    @livewireScripts
@endif
</body>
</html>
