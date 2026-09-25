{{--
    Native select (styled). Options from an array or the slot.
    <x-ui.select name="platform" :options="['facebook' => 'Messenger', 'instagram' => 'Instagram']" placeholder="All channels" :value="request('platform')" />
    <x-ui.select name="model" label="Model"> <option value="a">A</option> </x-ui.select>
    Props:
      name, id, value (selected; defaults to old($name, $value)), options (value => label; nested array = optgroup)
      placeholder  first empty option label       label, hint, optional   (auto-wraps in ui.field)
      size         sm | md (default)
--}}
@props([
    'name' => null, 'id' => null, 'value' => null, 'options' => [], 'placeholder' => null,
    'label' => null, 'hint' => null, 'optional' => false, 'size' => 'md',
])

@php
    $id ??= $name ? trim(str_replace(['[]', '[', ']', '.'], ['', '_', '', '_'], $name), '_') : null;
    $errorKey = $name ? trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.') : null;
    $invalid = $errorKey && isset($errors) && $errors->has($errorKey);
    $selected = $name ? old($errorKey, $value) : $value;
    $selected = $selected instanceof \BackedEnum ? $selected->value : $selected;
    $isSelected = fn ($v) => $selected !== null && (string) $selected === (string) $v;
@endphp

<x-ui.field :bare="! $label" :label="$label" :for="$id" :name="$name" :hint="$hint" :required="$attributes->has('required')" :optional="$optional">
    <select
        @if ($name) name="{{ $name }}" @endif
        @if ($id) id="{{ $id }}" @endif
        @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->class(['ui-input', $size === 'sm' ? 'h-8 py-0 text-[13px]' : 'h-9 py-0']) }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optValue => $optLabel)
            @if (is_array($optLabel))
                <optgroup label="{{ $optValue }}">
                    @foreach ($optLabel as $v => $l)
                        <option value="{{ $v }}" @selected($isSelected($v))>{{ $l }}</option>
                    @endforeach
                </optgroup>
            @else
                <option value="{{ $optValue }}" @selected($isSelected($optValue))>{{ $optLabel }}</option>
            @endif
        @endforeach
        {{ $slot }}
    </select>
</x-ui.field>
