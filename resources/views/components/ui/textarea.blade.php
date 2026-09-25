{{--
    Textarea with optional auto-grow and character counter.
    <x-ui.textarea name="system_prompt" rows="8" autosize :counter="4000" maxlength="4000" :value="$settings->system_prompt" />
    <x-ui.textarea name="text" label="Reply" hint="Sent as a human" />
    Props:
      name, value (defaults to old($name, $value)), id, rows (4), label, hint, optional
      autosize  bool: grows with content (up to max-h-[60vh])
      counter   int|null: show "n / counter" (turns red when exceeded); pair with maxlength
      mono      bool: monospace (prompts, JSON)
    Other attributes (placeholder, required, maxlength, x-model...) go to <textarea>.
--}}
@props([
    'name' => null, 'value' => null, 'id' => null, 'rows' => 4, 'label' => null, 'hint' => null,
    'optional' => false, 'autosize' => false, 'counter' => null, 'mono' => false,
])

@php
    $id ??= $name ? trim(str_replace(['[]', '[', ']', '.'], ['', '_', '', '_'], $name), '_') : null;
    $errorKey = $name ? trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.') : null;
    $invalid = $errorKey && isset($errors) && $errors->has($errorKey);
    $current = $name ? old($errorKey, $value) : $value;
    $alpine = $autosize || $counter;
@endphp

<x-ui.field :bare="! $label" :label="$label" :for="$id" :name="$name" :hint="$hint" :required="$attributes->has('required')" :optional="$optional">
    <div class="relative"
        @if ($alpine)
            x-data="{ count: 0, max: {{ (int) $counter }}, sync() { const el = this.$refs.ta; this.count = el.value.length; @if ($autosize) el.style.height = 'auto'; el.style.height = (el.scrollHeight + 2) + 'px'; @endif } }"
            x-init="$nextTick(() => sync())"
        @endif
    >
        <textarea
            @if ($name) name="{{ $name }}" @endif
            @if ($id) id="{{ $id }}" @endif
            rows="{{ $rows }}"
            @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            @if ($alpine) x-ref="ta" x-on:input="sync()" @endif
            {{ $attributes->class([
                'ui-input min-h-20 leading-relaxed',
                'resize-none overflow-hidden max-h-[60vh]' => $autosize,
                'resize-y' => ! $autosize,
                'font-mono text-[13px]' => $mono,
                'pb-7' => $counter,
            ]) }}>{{ $current }}</textarea>
        @if ($counter)
            <span class="pointer-events-none absolute right-3 bottom-2 text-2xs font-medium tabular-nums"
                x-bind:class="count > max ? 'text-danger-600' : 'text-slate-400'"
                x-text="count.toLocaleString() + ' / ' + max.toLocaleString()"></span>
        @endif
    </div>
</x-ui.field>
