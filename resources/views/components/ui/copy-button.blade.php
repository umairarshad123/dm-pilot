{{--
    Copy-to-clipboard button with "Copied" feedback.
    <x-ui.copy-button :value="$callbackUrl" />                        copies a literal value
    <x-ui.copy-button target="#verify-token" label="Copy token" />    copies an input's value / element's text
    Props: value, target (CSS selector), label ("Copy"), size (xs|sm|md), variant (secondary|ghost|soft), iconOnly (bool)
--}}
@props(['value' => null, 'target' => null, 'label' => 'Copy', 'size' => 'sm', 'variant' => 'secondary', 'iconOnly' => false])

@php
    $copyData = '{ copied: false, label: '.\Illuminate\Support\Js::from($label).', target: '.\Illuminate\Support\Js::from($target).', value: '.\Illuminate\Support\Js::from((string) $value).', async copy() {'
        .' const el = this.target ? document.querySelector(this.target) : null;'
        .' const text = el ? (el.value ?? el.textContent) : this.value;'
        .' if (await window.copyText(text)) { this.copied = true; setTimeout(() => this.copied = false, 1600); } } }';
@endphp

<x-ui.button :variant="$variant" :size="$size" :x-data="$copyData" x-on:click="copy()" :aria-label="$label" {{ $attributes }}>
    <span class="inline-flex items-center gap-1.5">
        <span x-show="! copied"><x-ui.icon name="copy" class="size-3.5" /></span>
        <span x-show="copied" x-cloak><x-ui.icon name="check" class="size-3.5 text-success-600" /></span>
        @unless ($iconOnly)<span x-text="copied ? 'Copied' : label">{{ $label }}</span>@endunless
    </span>
</x-ui.button>
