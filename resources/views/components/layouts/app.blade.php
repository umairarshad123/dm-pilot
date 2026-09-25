{{--
    App shell for every admin page.

    <x-layouts.app title="Contacts">
        <x-slot:actions>
            <x-ui.button variant="primary" icon="plus">New</x-ui.button>
        </x-slot:actions>

        ...page content...
    </x-layouts.app>

    Props:
      title     page title (top bar + <title>)
      width     default (max-w-7xl) | narrow (max-w-3xl) | wide (max-w-[1600px]) | full (edge-to-edge, fills the
                viewport under the top bar with no padding; for split panes like Live Chat)
      legacy    internal: set by layouts/admin.blade.php for pages not yet rebuilt
    Slots:
      actions   buttons at the right of the top bar
      subnav    optional row under the top bar (link tabs, filters) that stays sticky with it
    Shared by ViewServiceProvider: $currentPage (App\Support\CurrentPage), $connectedPages, $navCounts, $brand.
    Page scripts: @push('scripts') ... @endpush (rendered before </body>, after Alpine is loaded as a module).
--}}
@props(['title' => null, 'width' => 'default', 'legacy' => false])

@php
    $brand ??= \App\Providers\ViewServiceProvider::BRAND;
    $user = auth()->user();
    $navCounts ??= [];

    $nav = [
        ['items' => [
            ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'admin.dashboard', 'active' => ['admin.dashboard']],
        ]],
        ['label' => 'Engage', 'items' => [
            ['label' => 'Live Chat', 'icon' => 'message', 'route' => 'admin.conversations.index', 'active' => ['admin.conversations.*', 'admin.inbox.*', 'admin.live-chat.*'], 'count' => $navCounts['live_chat'] ?? 0],
            ['label' => 'Contacts', 'icon' => 'users', 'route' => 'admin.contacts.index', 'active' => ['admin.contacts.*']],
        ]],
        ['label' => 'Build', 'items' => [
            ['label' => 'Bot Studio', 'icon' => 'bot', 'route' => 'admin.bot-settings.edit', 'active' => ['admin.bot-settings.*', 'admin.bot.*', 'admin.knowledge.*']],
            ['label' => 'Automations', 'icon' => 'zap', 'route' => 'admin.automations.index', 'active' => ['admin.automations.*']],
        ]],
        ['label' => 'Manage', 'items' => [
            ['label' => 'Pages & Channels', 'icon' => 'layers', 'route' => 'admin.meta-accounts.index', 'active' => ['admin.meta-accounts.*', 'admin.pages.*']],
            ['label' => 'Settings', 'icon' => 'settings', 'route' => 'admin.settings.index', 'active' => ['admin.settings.*', 'admin.webhook-events.*', 'admin.ui']],
        ]],
    ];

    $activeLabel = null;
    foreach ($nav as $group) {
        foreach ($group['items'] as $item) {
            if (request()->routeIs(...$item['active'])) {
                $activeLabel = $item['label'];
            }
        }
    }

    // Legacy pages render their own <h1>; the top bar shows the section instead.
    $heading = $legacy ? ($activeLabel ?? $title) : $title;

    $containers = [
        'default' => 'mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8',
        'narrow' => 'mx-auto w-full max-w-3xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8',
        'wide' => 'mx-auto w-full max-w-[1600px] px-4 py-6 sm:px-6 lg:px-8 lg:py-8',
        'full' => 'w-full',
    ];
    $container = $containers[$width] ?? $containers['default'];

    $flashes = collect([
        ['type' => 'success', 'message' => session('success')],
        ['type' => 'success', 'message' => session('status')],
        ['type' => 'error', 'message' => session('error')],
        ['type' => 'warning', 'message' => session('warning')],
        ['type' => 'info', 'message' => session('info')],
    ])->filter(fn ($f) => is_string($f['message']) && $f['message'] !== '')->values();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#ffffff">
    <title>{{ $title ? $title.' · ' : '' }}{{ $brand }}</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Cdefs%3E%3ClinearGradient id='g' x1='0' y1='0' x2='1' y2='1'%3E%3Cstop offset='0' stop-color='%233375ff'/%3E%3Cstop offset='1' stop-color='%234f46e5'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='32' height='32' rx='8' fill='url(%23g)'/%3E%3Cpath d='M19.4 23.5a.6.6 0 0 0 1.1 0l4.4-13a.6.6 0 0 0-.8-.8l-13 4.4a.6.6 0 0 0 0 1.1l5.4 2.2a1.4 1.4 0 0 1 .8.8z' fill='white'/%3E%3C/svg%3E">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="h-full" x-data="{ nav: false }" x-on:keydown.escape.window="nav = false" x-on:open-page-switcher.window="nav = true">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[100] focus:rounded-lg focus:bg-white focus:px-3 focus:py-2 focus:shadow-pop">Skip to content</a>

{{-- Mobile backdrop --}}
<div x-show="nav" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-[2px] lg:hidden" x-on:click="nav = false" aria-hidden="true"></div>

{{-- Sidebar --}}
<aside id="sidebar"
    class="fixed inset-y-0 left-0 z-50 flex w-[17rem] -translate-x-full flex-col border-r border-line bg-surface transition-transform duration-200 ease-out lg:w-64 lg:translate-x-0"
    x-bind:class="{ 'translate-x-0 shadow-pop lg:shadow-none': nav, '-translate-x-full': ! nav }"
    x-trap="nav && window.innerWidth < 1024"
    aria-label="Main navigation">
    <div class="flex h-16 shrink-0 items-center justify-between px-5">
        <a href="{{ route('admin.dashboard') }}" class="rounded-lg focus-visible:outline-offset-4"><x-ui.logo :name="$brand" /></a>
        <button type="button" class="-mr-2 rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 lg:hidden" x-on:click="nav = false" aria-label="Close menu">
            <x-ui.icon name="x" class="size-5" />
        </button>
    </div>

    @isset($currentPage)
        <div class="px-3 pb-3">
            <x-layouts.page-switcher :current-page="$currentPage" :pages="$connectedPages ?? collect()" />
        </div>
    @endisset

    <nav class="ui-scroll flex-1 overflow-y-auto px-3 pb-6">
        @foreach ($nav as $group)
            <div @class(['mt-5' => ! $loop->first])>
                @isset($group['label'])
                    <p class="px-3 pb-1.5 text-2xs font-semibold tracking-wider text-slate-400 uppercase">{{ $group['label'] }}</p>
                @endisset
                <ul class="space-y-0.5">
                    @foreach ($group['items'] as $item)
                        @continue(! \Illuminate\Support\Facades\Route::has($item['route']))
                        @php($isActive = request()->routeIs(...$item['active']))
                        <li>
                            <a href="{{ route($item['route']) }}" @if ($isActive) aria-current="page" @endif
                                @class([
                                    'group relative flex items-center gap-3 rounded-lg px-3 py-2 text-[13.5px] font-medium transition-colors duration-150',
                                    'bg-brand-50 text-brand-700' => $isActive,
                                    'text-slate-600 hover:bg-slate-100 hover:text-ink' => ! $isActive,
                                ])>
                                @if ($isActive)<span class="absolute inset-y-2 -left-3 w-1 rounded-r-full bg-brand-600" aria-hidden="true"></span>@endif
                                <x-ui.icon :name="$item['icon']" @class(['size-[18px]', 'text-brand-600' => $isActive, 'text-slate-400 group-hover:text-slate-600' => ! $isActive]) :stroke="1.9" />
                                <span class="flex-1 truncate">{{ $item['label'] }}</span>
                                @if (($item['count'] ?? 0) > 0)
                                    <span class="rounded-full bg-danger-500 px-1.5 py-px text-2xs font-semibold text-white tabular-nums" title="Waiting for a human">{{ $item['count'] > 99 ? '99+' : $item['count'] }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    <div class="shrink-0 border-t border-line p-3">
        <a href="{{ route('admin.meta-accounts.connect') }}" class="group flex items-center gap-3 rounded-xl bg-gradient-to-br from-brand-50 to-indigo-50 px-3 py-2.5 ring-1 ring-brand-100 ring-inset transition hover:from-brand-100/70 hover:to-indigo-100/70">
            <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-white text-brand-600 shadow-xs"><x-ui.icon name="plus" class="size-4" /></span>
            <span class="min-w-0">
                <span class="block text-[13px] font-semibold text-ink">Connect a channel</span>
                <span class="block truncate text-2xs text-ink-muted">Messenger or Instagram</span>
            </span>
        </a>
    </div>
</aside>

<div class="flex min-h-full flex-col lg:pl-64">
    {{-- Top bar --}}
    <header class="sticky top-0 z-30 border-b border-line bg-white/80 backdrop-blur-md supports-[backdrop-filter]:bg-white/70">
        <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
            <button type="button" class="-ml-1.5 rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-ink lg:hidden" x-on:click="nav = true" aria-label="Open menu" aria-controls="sidebar" x-bind:aria-expanded="nav">
                <x-ui.icon name="menu" class="size-5" />
            </button>

            <div class="min-w-0 flex-1">
                @if ($heading)
                    <h1 class="truncate text-[17px] font-semibold tracking-tight text-ink">{{ $heading }}</h1>
                @endif
            </div>

            <div class="flex shrink-0 items-center gap-2">
                @isset($actions)
                    <div class="hidden items-center gap-2 sm:flex">{{ $actions }}</div>
                @endisset

                @isset($currentPage)
                    @php($ctx = $currentPage->account())
                    <button type="button" x-on:click="$dispatch('open-page-switcher')"
                        class="hidden max-w-52 items-center gap-2 rounded-full border border-line bg-white py-1 pr-3 pl-1 text-[13px] font-medium text-slate-700 shadow-xs transition hover:border-line-strong hover:bg-slate-50 md:inline-flex"
                        title="Switch page">
                        @if ($ctx)
                            <x-ui.channel-badge :platform="$ctx->platform" variant="icon" size="sm" class="!ring-0" />
                        @else
                            <span class="inline-flex size-5 items-center justify-center rounded-full bg-slate-900 text-white"><x-ui.icon name="layers" class="size-3" /></span>
                        @endif
                        <span class="truncate">{{ $currentPage->label() }}</span>
                    </button>
                @endisset

                @if ($user)
                    <x-ui.dropdown align="right" width="w-64">
                        <x-slot:trigger>
                            <button type="button" class="flex items-center rounded-full ring-offset-2 transition hover:ring-2 hover:ring-slate-200" aria-label="Account menu">
                                <x-ui.avatar :name="$user->name ?: $user->email" size="sm" />
                            </button>
                        </x-slot:trigger>
                        <x-slot:header>
                            <p class="truncate text-[13px] font-semibold text-ink">{{ $user->name ?: 'Admin' }}</p>
                            <p class="truncate text-xs text-ink-muted">{{ $user->email }}</p>
                        </x-slot:header>
                        <x-ui.dropdown-item :href="route('admin.settings.index')" icon="settings">Settings</x-ui.dropdown-item>
                        <x-ui.dropdown-item :href="route('admin.webhook-events.index')" icon="webhook">Webhook events</x-ui.dropdown-item>
                        <x-ui.dropdown-item :href="route('admin.ui')" icon="palette">UI style guide</x-ui.dropdown-item>
                        <x-ui.dropdown-divider />
                        <form method="POST" action="{{ route('logout') }}" data-no-loading>
                            @csrf
                            <x-ui.dropdown-item type="submit" icon="logout">Log out</x-ui.dropdown-item>
                        </form>
                    </x-ui.dropdown>
                @endif
            </div>
        </div>
        @isset($actions)
            <div class="flex items-center gap-2 overflow-x-auto border-t border-line px-4 py-2 sm:hidden">{{ $actions }}</div>
        @endisset
        @isset($subnav)
            <div class="px-4 sm:px-6 lg:px-8">{{ $subnav }}</div>
        @endisset
    </header>

    <main id="main" @class(['flex-1 min-w-0', 'flex flex-col h-[calc(100dvh-4rem)] overflow-hidden' => $width === 'full'])>
        <div @class([$container, 'flex-1 min-h-0 flex flex-col' => $width === 'full'])>
            @if (isset($errors) && $errors->any() && $width !== 'full')
                <x-ui.alert tone="danger" :title="$errors->count() === 1 ? 'Please fix the highlighted field' : 'Please fix '.$errors->count().' problems'" class="mb-6">
                    <ul class="list-disc space-y-0.5 pl-4">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </x-ui.alert>
            @endif

            @if ($legacy)
                <div class="legacy">{{ $slot }}</div>
            @else
                {{ $slot }}
            @endif
        </div>
    </main>
</div>

{{-- Toasts: session flashes (server-rendered) + window.toast() / $dispatch('toast', {...}) --}}
<div class="pointer-events-none fixed inset-x-0 bottom-4 z-[80] flex flex-col-reverse items-center gap-2 px-4 sm:top-20 sm:bottom-auto sm:right-6 sm:left-auto sm:flex-col sm:items-end sm:px-0"
    x-data="toaster()" x-on:toast.window="push($event.detail)" aria-live="polite">
    @foreach ($flashes as $flash)
        @php($isError = $flash['type'] === 'error')
        <div x-data="{ show: true }" x-init="{{ $isError ? '' : 'setTimeout(() => show = false, 5000)' }}" x-show="show"
            x-transition:leave="transition duration-200 ease-in" x-transition:leave-end="opacity-0 translate-x-2"
            role="{{ $isError ? 'alert' : 'status' }}"
            class="pointer-events-auto flex w-full max-w-sm animate-slide-up items-start gap-3 rounded-xl border border-line bg-white p-3.5 pr-2.5 shadow-pop">
            <span @class([
                'mt-px inline-flex size-6 shrink-0 items-center justify-center rounded-full',
                'bg-success-50 text-success-600' => $flash['type'] === 'success',
                'bg-danger-50 text-danger-600' => $isError,
                'bg-warning-50 text-warning-600' => $flash['type'] === 'warning',
                'bg-sky-50 text-sky-600' => $flash['type'] === 'info',
            ])>
                <x-ui.icon :name="match ($flash['type']) { 'success' => 'check', 'error' => 'alert-circle', 'warning' => 'alert-triangle', default => 'info' }" class="size-3.5" :stroke="2.5" />
            </span>
            <p class="min-w-0 flex-1 pt-0.5 text-[13px] leading-5 font-medium break-words text-ink">{{ $flash['message'] }}</p>
            <button type="button" x-on:click="show = false" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Dismiss"><x-ui.icon name="x" class="size-3.5" /></button>
        </div>
    @endforeach
    <template x-for="t in toasts" x-bind:key="t.id">
        <div x-show="t.visible" x-transition:leave="transition duration-200 ease-in" x-transition:leave-end="opacity-0 translate-x-2"
            class="pointer-events-auto flex w-full max-w-sm animate-slide-up items-start gap-3 rounded-xl border border-line bg-white p-3.5 pr-2.5 shadow-pop" role="status">
            <span class="mt-px inline-flex size-6 shrink-0 items-center justify-center rounded-full"
                x-bind:class="{ 'bg-success-50 text-success-600': t.type === 'success', 'bg-danger-50 text-danger-600': t.type === 'error', 'bg-warning-50 text-warning-600': t.type === 'warning', 'bg-sky-50 text-sky-600': t.type === 'info' }">
                <span x-show="t.type === 'success'"><x-ui.icon name="check" class="size-3.5" :stroke="2.5" /></span>
                <span x-show="t.type === 'error'"><x-ui.icon name="alert-circle" class="size-3.5" :stroke="2.5" /></span>
                <span x-show="t.type === 'warning'"><x-ui.icon name="alert-triangle" class="size-3.5" :stroke="2.5" /></span>
                <span x-show="t.type === 'info'"><x-ui.icon name="info" class="size-3.5" :stroke="2.5" /></span>
            </span>
            <p class="min-w-0 flex-1 pt-0.5 text-[13px] leading-5 font-medium break-words text-ink" x-text="t.message"></p>
            <button type="button" x-on:click="dismiss(t.id)" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Dismiss"><x-ui.icon name="x" class="size-3.5" /></button>
        </div>
    </template>
</div>

@stack('scripts')
</body>
</html>
