{{--
    Empty state for lists, tables and not-yet-built pages.
    <x-ui.empty-state icon="users" title="No contacts yet" description="Contacts appear when someone messages your Page.">
        <x-ui.button variant="primary" :href="route('admin.meta-accounts.connect')" icon="plus">Connect a page</x-ui.button>
    </x-ui.empty-state>
    Props:
      icon, title, description
      compact  bool: less padding (inside cards / table bodies)
      tone     brand (default) | neutral | success | warning | danger (icon tile colour)
    Default slot = actions (rendered below the text).
--}}
@props(['icon' => 'inbox', 'title', 'description' => null, 'compact' => false, 'tone' => 'brand'])

@php
    $tones = [
        'brand' => 'bg-brand-50 text-brand-600 ring-brand-100', 'neutral' => 'bg-slate-100 text-slate-500 ring-slate-200',
        'success' => 'bg-success-50 text-success-600 ring-success-100', 'warning' => 'bg-warning-50 text-warning-600 ring-warning-100',
        'danger' => 'bg-danger-50 text-danger-600 ring-danger-100',
    ];
@endphp

<div {{ $attributes->class(['flex flex-col items-center text-center', $compact ? 'px-6 py-10' : 'px-6 py-16 sm:py-20']) }}>
    <div class="relative">
        <div class="absolute inset-0 -m-3 rounded-full bg-gradient-to-b from-slate-100/80 to-transparent" aria-hidden="true"></div>
        <span class="{{ $tones[$tone] ?? $tones['brand'] }} relative inline-flex size-12 items-center justify-center rounded-2xl ring-8 ring-inset">
            <x-ui.icon :name="$icon" class="size-6" :stroke="1.75" />
        </span>
    </div>
    <h3 class="mt-5 text-base font-semibold text-ink">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1.5 max-w-sm text-sm text-ink-muted">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-6 flex flex-wrap items-center justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
