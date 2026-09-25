{{--
    Under an inheritable field: validation error, or a preview of the inherited value while not overridden.
    Vars: $name (error key, dot notation), $isPage (preview only shown for page overrides), optional $preview (bool), optional $unit.
--}}
@error($name)
    <p class="ui-error flex items-center gap-1"><x-ui.icon name="alert-circle" class="size-3.5" />{{ $message }}</p>
@else
    @if (($preview ?? true) && $isPage)
        <p x-show="!custom" x-cloak class="flex min-w-0 items-start gap-1.5 text-xs text-ink-muted">
            <x-ui.icon name="{{ $isPage ? 'layers' : 'settings' }}" class="mt-0.5 size-3.5 shrink-0 text-slate-400" />
            <span class="min-w-0">
                <span class="font-medium text-slate-600">{{ $isPage ? 'Inherited from global:' : 'Built-in default:' }}</span>
                <span x-show="hasInherited" class="line-clamp-2 break-words whitespace-pre-line" x-text="String(inherited) + @js(isset($unit) ? ' '.$unit : '')"></span>
                <span x-show="!hasInherited" class="italic">not set</span>
            </span>
        </p>
    @endif
@enderror
