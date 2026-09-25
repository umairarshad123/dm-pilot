{{--
    Form field wrapper: label + control (slot) + hint + validation error.
    <x-ui.field label="Page name" for="page_name" name="page_name" hint="Shown in the inbox" required>
        <x-ui.input name="page_name" id="page_name" />
    </x-ui.field>
    Props:
      label     string          for   id of the control (label[for])
      name      field name used to look up the error in $errors (supports "a[b]" -> "a.b")
      error     explicit error message (overrides $errors lookup)
      hint      helper text     required  bool: shows a red *     optional  bool: shows "Optional"
      inline    bool: label left / control right on >= sm (settings rows)
      bare      bool: render only the slot (used internally by input/select/textarea when no label is given)
    Slot "aside" renders at the right of the label (e.g. a char counter or link).
--}}
@props([
    'label' => null, 'for' => null, 'name' => null, 'error' => null, 'hint' => null,
    'required' => false, 'optional' => false, 'inline' => false, 'bare' => false,
])

@php
    $errorKey = $name ? trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.') : null;
    $message = $error ?? ($errorKey && isset($errors) ? $errors->first($errorKey) : null);
@endphp

@if ($bare)
{{ $slot }}
@else
<div {{ $attributes->class([
    'min-w-0',
    'grid content-start gap-1.5' => ! $inline,
    'grid content-start gap-1.5 sm:grid-cols-[minmax(0,2fr)_minmax(0,3fr)] sm:gap-8' => $inline,
]) }}>
    @if ($label || isset($aside))
        <div @class(['flex items-center justify-between gap-2', 'sm:block sm:pt-2' => $inline])>
            @if ($label)
                <label @if ($for) for="{{ $for }}" @endif class="ui-label">
                    {{ $label }}
                    @if ($required)<span class="text-danger-500" aria-hidden="true">*</span>@endif
                    @if ($optional)<span class="ml-1 text-xs font-normal text-slate-400">Optional</span>@endif
                </label>
            @endif
            @if ($inline && $hint)<p class="ui-hint mt-1 hidden sm:block">{{ $hint }}</p>@endif
            @isset($aside)<div class="text-xs text-ink-muted">{{ $aside }}</div>@endisset
        </div>
    @endif
    <div class="grid min-w-0 content-start gap-1.5">
        {{ $slot }}
        @if ($message)
            <p class="ui-error flex items-center gap-1" @if ($for) id="{{ $for }}-error" @endif>
                <x-ui.icon name="alert-circle" class="size-3.5" />{{ $message }}
            </p>
        @elseif ($hint)
            <p @class(['ui-hint', 'sm:hidden' => $inline])>{{ $hint }}</p>
        @endif
    </div>
</div>
@endif
