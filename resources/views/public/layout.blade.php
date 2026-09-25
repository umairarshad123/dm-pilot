@php
    $operator = config('legal.operator_name');
    $product = config('legal.product_name');
    $contactEmail = config('legal.contact_email');
    $links = [
        'public.about' => 'About',
        'public.privacy' => 'Privacy Policy',
        'public.terms' => 'Terms of Service',
        'public.data-deletion' => 'Data Deletion',
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ $product }}</title>
    <meta name="description" content="@yield('description', $product.' by '.$operator.': automated and human replies to Facebook Messenger and Instagram direct messages.')">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-3 px-4 py-4">
            <a href="{{ route('public.about') }}" class="flex items-center gap-2 font-semibold text-slate-900 no-underline">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-blue-600 text-sm font-bold text-white">{{ mb_substr($product, 0, 1) }}</span>
                <span>{{ $product }}</span>
            </a>
            <nav class="flex flex-wrap gap-x-4 gap-y-1 text-sm" aria-label="Legal">
                @foreach ($links as $name => $label)
                    <a href="{{ route($name) }}"
                       class="{{ request()->routeIs($name) ? 'font-semibold text-blue-700' : 'text-slate-600 hover:text-slate-900' }}">{{ $label }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-4xl px-4 py-8 sm:py-12">
        <article class="rounded-2xl border border-slate-200 bg-white px-5 py-8 shadow-sm sm:px-10 sm:py-10
            [&_h1]:text-3xl [&_h1]:font-bold [&_h1]:tracking-tight [&_h1]:text-slate-900
            [&_h2]:mt-10 [&_h2]:mb-3 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-slate-900
            [&_h3]:mt-6 [&_h3]:mb-2 [&_h3]:text-base [&_h3]:font-semibold [&_h3]:text-slate-900
            [&_p]:my-3 [&_p]:leading-7
            [&_ul]:my-3 [&_ul]:list-disc [&_ul]:space-y-1.5 [&_ul]:pl-6 [&_ul]:leading-7
            [&_ol]:my-3 [&_ol]:list-decimal [&_ol]:space-y-1.5 [&_ol]:pl-6 [&_ol]:leading-7
            [&_a]:text-blue-700 [&_a]:underline [&_a]:underline-offset-2
            [&_table]:my-4 [&_table]:w-full [&_table]:text-sm [&_th]:border-b [&_th]:border-slate-200 [&_th]:py-2 [&_th]:pr-3 [&_th]:text-left [&_th]:font-semibold
            [&_td]:border-b [&_td]:border-slate-100 [&_td]:py-2 [&_td]:pr-3 [&_td]:align-top
            [&_code]:rounded [&_code]:bg-slate-100 [&_code]:px-1 [&_code]:py-0.5 [&_code]:text-sm">
            @yield('content')
        </article>
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto flex max-w-4xl flex-col gap-3 px-4 py-6 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ now()->year }} {{ $operator }}. Contact: <a class="text-blue-700 underline" href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a></p>
            <nav class="flex flex-wrap gap-x-4 gap-y-1" aria-label="Footer">
                @foreach ($links as $name => $label)
                    <a href="{{ route($name) }}" class="hover:text-slate-800">{{ $label }}</a>
                @endforeach
            </nav>
        </div>
    </footer>
</body>
</html>
