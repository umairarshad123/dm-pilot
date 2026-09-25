{{--
    Label row of an inheritable field: label + Inherited/Overridden badge + Override / Reset action.
    Must sit inside x-data="bsInherit(...)". Vars: $label, $for, $isPage (from the parent view), optional $hint.
--}}
<div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
    <div class="flex min-w-0 items-center gap-2">
        <label for="{{ $for }}" class="ui-label">{{ $label }}</label>
        <span x-show="custom" x-cloak class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 text-2xs font-semibold text-brand-700 ring-1 ring-brand-100 ring-inset">
            <span class="size-1.5 rounded-full bg-brand-500"></span>{{ $isPage ? 'Overridden' : 'Custom' }}
        </span>
        <span x-show="!custom" x-cloak class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-2xs font-semibold text-slate-600">
            <x-ui.icon name="link" class="size-3" />{{ $isPage ? 'Inherited' : 'Default' }}
        </span>
    </div>
    <div class="flex items-center gap-1">
        <button type="button" x-show="!custom && hasInherited" x-cloak x-on:click="override()"
            class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium text-brand-700 hover:bg-brand-50">
            <x-ui.icon name="edit" class="size-3.5" />{{ $isPage ? 'Override' : 'Customize' }}
        </button>
        <button type="button" x-show="custom" x-cloak x-on:click="clear()"
            class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-medium text-slate-500 hover:bg-slate-100 hover:text-ink">
            <x-ui.icon name="refresh" class="size-3.5" />{{ $isPage ? 'Reset to inherited' : 'Reset to default' }}
        </button>
    </div>
</div>
@if (! empty($hint))
    <p class="ui-hint -mt-1">{{ $hint }}</p>
@endif
