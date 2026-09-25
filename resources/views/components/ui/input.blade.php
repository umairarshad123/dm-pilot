{{--
    Text input. Value defaults to old($name, $value). Marks itself invalid when $errors has the field.
    <x-ui.input name="email" type="email" :value="$user->email" />
    <x-ui.input name="q" icon="search" placeholder="Search contacts" />
    <x-ui.input name="page_name" label="Page name" hint="Shown in the inbox" required />   (auto-wraps in ui.field)
    Props:
      name, type (text), value, id (defaults to name), icon (leading), label, hint, optional
      size   sm | md (default)
      Any other attribute (placeholder, x-model, required, autofocus...) is forwarded to <input>.
    Slot "trailing" renders inside the right edge (e.g. a unit or a small button).
--}}
@props([
    'name' => null, 'type' => 'text', 'value' => null, 'id' => null, 'icon' => null,
    'label' => null, 'hint' => null, 'optional' => false, 'size' => 'md',
])

@php
    $id ??= $name ? trim(str_replace(['[]', '[', ']', '.'], ['', '_', '', '_'], $name), '_') : null;
    $errorKey = $name ? trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.') : null;
    $invalid = $errorKey && isset($errors) && $errors->has($errorKey);
    $current = $name && $type !== 'password' ? old($errorKey, $value) : $value;
    $sizeClass = $size === 'sm' ? 'h-8 text-[13px]' : 'h-9';
@endphp

<x-ui.field :bare="! $label" :label="$label" :for="$id" :name="$name" :hint="$hint" :required="$attributes->has('required')" :optional="$optional">
    <div class="relative">
        @if ($icon)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                <x-ui.icon :name="$icon" class="size-4" />
            </span>
        @endif
        <input type="{{ $type }}"
            @if ($name) name="{{ $name }}" @endif
            @if ($id) id="{{ $id }}" @endif
            @if ($current !== null) value="{{ $current }}" @endif
            @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->class(['ui-input', $sizeClass, 'pl-9' => $icon, 'pr-10' => isset($trailing)]) }}>
        @isset($trailing)
            <span class="absolute inset-y-0 right-0 flex items-center pr-2 text-sm text-slate-400">{{ $trailing }}</span>
        @endisset
    </div>
</x-ui.field>
