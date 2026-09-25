{{--
    Switch. A real checkbox (role="switch"), so it works in plain forms, with x-model, and with the keyboard.
    <x-ui.toggle name="active" :checked="$account->active" label="Bot enabled" description="Reply automatically" />
    <x-ui.toggle x-model="enabled" label="Live" />                       (Alpine only, no name)
    <x-ui.toggle name="enabled" :checked="$c->bot_enabled" onchange="this.form.requestSubmit()" />   (auto-submit)
    Props:
      name           field name (a hidden input posts $uncheckedValue when off; set :unchecked-value="null" to omit)
      checked        bool (defaults to old($name, $checked))
      value          posted when on (default "1")     uncheckedValue  posted when off (default "0")
      label, description   text beside the switch      size  sm | md (default)
      disabled       bool      id  defaults to name
    Other attributes go to the checkbox.
--}}
@props([
    'name' => null, 'checked' => false, 'value' => '1', 'uncheckedValue' => '0', 'label' => null,
    'description' => null, 'size' => 'md', 'disabled' => false, 'id' => null,
])

@php
    $id ??= ($name ? trim(str_replace(['[]', '[', ']', '.'], ['', '_', '', '_'], $name), '_') : 'toggle').'_'.substr(md5(uniqid('', true)), 0, 6);
    $errorKey = $name ? trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.') : null;
    $isOn = (bool) ($name && old($errorKey) !== null ? filter_var(old($errorKey), FILTER_VALIDATE_BOOLEAN) : $checked);
    $track = $size === 'sm' ? 'h-5 w-9' : 'h-6 w-11';
    $knob = $size === 'sm' ? 'size-4 peer-checked:translate-x-4' : 'size-5 peer-checked:translate-x-5';
@endphp

<label for="{{ $id }}" @class(['group inline-flex items-start gap-3 select-none', 'cursor-pointer' => ! $disabled, 'cursor-not-allowed opacity-60' => $disabled])>
    @if ($name && $uncheckedValue !== null)
        <input type="hidden" name="{{ $name }}" value="{{ $uncheckedValue }}" @disabled($disabled)>
    @endif
    <span class="relative inline-flex shrink-0 {{ $track }}">
        <input type="checkbox" role="switch" id="{{ $id }}" value="{{ $value }}"
            @if ($name) name="{{ $name }}" @endif
            @checked($isOn) @disabled($disabled)
            {{ $attributes->class(['peer absolute inset-0 z-10 h-full w-full cursor-[inherit] appearance-none rounded-full opacity-0']) }}>
        <span class="{{ $track }} pointer-events-none rounded-full bg-slate-200 ring-1 ring-inset ring-slate-900/5 transition-colors duration-200 group-hover:bg-slate-300 peer-checked:bg-brand-600 peer-checked:group-hover:bg-brand-700 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand-500"></span>
        <span class="{{ $knob }} pointer-events-none absolute top-0.5 left-0.5 rounded-full bg-white shadow-sm ring-1 ring-slate-900/5 transition-transform duration-200 ease-out"></span>
    </span>
    @if ($label || $description)
        <span class="min-w-0 {{ $size === 'sm' ? 'pt-px' : 'pt-0.5' }}">
            @if ($label)<span class="block text-sm font-medium text-ink">{{ $label }}</span>@endif
            @if ($description)<span class="mt-0.5 block text-[13px] text-ink-muted">{{ $description }}</span>@endif
        </span>
    @endif
    {{ $slot }}
</label>
