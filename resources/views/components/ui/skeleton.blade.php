{{--
    Loading placeholder with shimmer.
    <x-ui.skeleton class="h-4 w-32" />           single block (size it with classes)
    <x-ui.skeleton lines="3" />                   paragraph of 3 lines (last one shorter)
    <x-ui.skeleton avatar lines="2" />            avatar + 2 lines (list rows)
    Props: lines (int|null), avatar (bool)
--}}
@props(['lines' => null, 'avatar' => false])

@if ($lines || $avatar)
    <div {{ $attributes->class(['flex items-start gap-3']) }} aria-hidden="true">
        @if ($avatar)<div class="ui-skeleton size-9 shrink-0 rounded-full"></div>@endif
        <div class="flex-1 space-y-2 pt-0.5">
            @for ($i = 0; $i < max(1, (int) $lines); $i++)
                <div class="ui-skeleton h-3 {{ $i === max(1, (int) $lines) - 1 && (int) $lines > 1 ? 'w-3/5' : 'w-full' }}"></div>
            @endfor
        </div>
    </div>
@else
    <div {{ $attributes->class(['ui-skeleton', 'h-4 w-full' => ! str_contains((string) $attributes->get('class'), 'h-')]) }} aria-hidden="true"></div>
@endif
