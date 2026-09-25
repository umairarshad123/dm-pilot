{{--
    Checkbox (or radio) with label + description.
    <x-ui.checkbox name="remember" label="Remember me" />
    <x-ui.checkbox name="pages[]" :value="$page['id']" :checked="true" :label="$page['name']" description="Messenger" />
    <x-ui.checkbox type="radio" name="mode" value="ai" label="AI replies" />
    Props: name, value ("1"), checked (bool), label, description, type (checkbox|radio), id, disabled
--}}
@props(['name' => null, 'value' => '1', 'checked' => false, 'label' => null, 'description' => null, 'type' => 'checkbox', 'id' => null, 'disabled' => false])

@php
    $id ??= ($name ? trim(str_replace(['[]', '[', ']', '.'], ['', '_', '', '_'], $name), '_') : 'cb').'_'.substr(md5($value.uniqid('', true)), 0, 6);
@endphp

<label for="{{ $id }}" @class(['flex items-start gap-2.5 select-none', 'cursor-pointer' => ! $disabled, 'cursor-not-allowed opacity-60' => $disabled])>
    <input type="{{ $type }}" id="{{ $id }}" value="{{ $value }}" @if ($name) name="{{ $name }}" @endif @checked($checked) @disabled($disabled)
        {{ $attributes->class([
            'mt-0.5 size-4 shrink-0 border-line-strong text-brand-600 accent-brand-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500',
            'rounded' => $type === 'checkbox',
            'rounded-full' => $type === 'radio',
        ]) }}>
    @if ($label || $description)
        <span class="min-w-0">
            @if ($label)<span class="block text-sm font-medium text-ink">{{ $label }}</span>@endif
            @if ($description)<span class="block text-[13px] text-ink-muted">{{ $description }}</span>@endif
        </span>
    @endif
    {{ $slot }}
</label>
