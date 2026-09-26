{{--
    Guest shell (login and other signed-out pages): split screen with a brand panel on >= lg.
    <x-layouts.guest title="Log in"> ...form... </x-layouts.guest>
--}}
@props(['title' => null])

@php($brand ??= \App\Providers\ViewServiceProvider::BRAND)
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-white">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ? $title.' · ' : '' }}{{ $brand }}</title>
    @include('partials.favicon')
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-white">
<div class="flex min-h-full">
    {{-- Brand panel --}}
    <aside class="relative hidden w-[46%] max-w-[720px] overflow-hidden bg-brand-950 lg:flex lg:flex-col">
        <div class="absolute inset-0 bg-[radial-gradient(120%_80%_at_0%_0%,#2f5bff_0%,transparent_55%),radial-gradient(90%_70%_at_100%_100%,#7c3aed_0%,transparent_55%)] opacity-90" aria-hidden="true"></div>
        <div class="absolute inset-0 bg-[linear-gradient(to_right,rgb(255_255_255/0.06)_1px,transparent_1px),linear-gradient(to_bottom,rgb(255_255_255/0.06)_1px,transparent_1px)] bg-[size:40px_40px] [mask-image:radial-gradient(ellipse_at_center,black_30%,transparent_75%)]" aria-hidden="true"></div>

        <div class="relative flex flex-1 flex-col justify-between p-12 xl:p-16">
            <x-ui.logo :name="$brand" inverted />

            {{-- Conversation illustration --}}
            <div class="mx-auto w-full max-w-md space-y-3 py-12" aria-hidden="true">
                <div class="flex items-end gap-2.5">
                    <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-instagram-gradient text-white ring-2 ring-white/20"><x-ui.icon name="instagram" class="size-4" /></span>
                    <div class="rounded-2xl rounded-bl-md bg-white/95 px-4 py-2.5 text-[13px] text-slate-800 shadow-lg">Hi! Is the blue hoodie still in stock?</div>
                </div>
                <div class="flex items-end justify-end gap-2.5">
                    <div class="max-w-[80%] rounded-2xl rounded-br-md bg-brand-500 px-4 py-2.5 text-[13px] text-white shadow-lg shadow-brand-900/30">
                        Yes! We have it in S to XL, and orders ship within 2 days. Want me to reserve one for you?
                        <span class="mt-1.5 flex items-center gap-1 text-2xs text-white/70"><x-ui.icon name="sparkles" class="size-3" /> AI reply · 2s</span>
                    </div>
                </div>
                <div class="flex items-end gap-2.5">
                    <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-messenger text-white ring-2 ring-white/20"><x-ui.icon name="messenger" class="size-4" /></span>
                    <div class="rounded-2xl rounded-bl-md bg-white/95 px-4 py-2.5 text-[13px] text-slate-800 shadow-lg">Perfect, a medium please 🙌</div>
                </div>
                <div class="flex justify-end">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-2xs font-medium text-white/80 ring-1 ring-white/15 backdrop-blur">
                        <span class="size-1.5 animate-pulse rounded-full bg-emerald-400"></span> Bot is typing…
                    </span>
                </div>
            </div>

            <div>
                <p class="max-w-md text-2xl leading-snug font-semibold tracking-tight text-white">Every DM answered in seconds, on Messenger and Instagram.</p>
                <p class="mt-3 max-w-md text-sm text-white/65">Your AI assistant replies instantly, hands off to a human when it matters, and never misses a lead.</p>
            </div>
        </div>
    </aside>

    {{-- Form side --}}
    <main class="flex flex-1 flex-col justify-center px-6 py-12 sm:px-12">
        <div class="mx-auto w-full max-w-sm">
            <div class="mb-10 lg:hidden"><x-ui.logo :name="$brand" /></div>
            {{ $slot }}
        </div>
    </main>
</div>
</body>
</html>
