{{--
    GET filter bar: search box + your selects + Reset. Selects auto-submit on change.
    <x-ui.filter-bar :action="route('admin.conversations.index')" search="q" placeholder="Search name or ID">
        <x-ui.select name="platform" size="sm" :options="['facebook' => 'Messenger', 'instagram' => 'Instagram']" placeholder="All channels" :value="request('platform')" class="w-auto" />
    </x-ui.filter-bar>
    Props:
      action       form action (default: current url)
      search       name of the search input (null = no search box)     placeholder
      reset        url for Reset (default: action); Reset shows only when any query param is present
    Slot "right" renders at the far right (e.g. view toggles / export).
--}}
@props(['action' => null, 'search' => 'q', 'placeholder' => 'Search…', 'reset' => null])

@php
    $action ??= url()->current();
    $hasFilters = collect(request()->query())->except('page')->filter(fn ($v) => filled($v))->isNotEmpty();
@endphp

<form method="GET" action="{{ $action }}" data-no-loading
    x-data x-on:change="if ($event.target.matches('select, input[type=checkbox], input[type=radio]')) $el.requestSubmit()"
    {{ $attributes->class(['flex flex-wrap items-center gap-2']) }}>
    @if ($search)
        <div class="w-full sm:w-72">
            <x-ui.input :name="$search" :value="request($search)" icon="search" size="sm" :placeholder="$placeholder" type="search" autocomplete="off" />
        </div>
    @endif
    {{ $slot }}
    @if ($hasFilters)
        <x-ui.button :href="$reset ?? $action" variant="ghost" size="sm" icon="x">Reset</x-ui.button>
    @endif
    @isset($right)<div class="ml-auto flex items-center gap-2">{{ $right }}</div>@endisset
    <button type="submit" class="sr-only">Apply filters</button>
</form>
