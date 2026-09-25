{{--
    Panel for <x-ui.tabs>. Hidden until its tab is active (server renders the default one visible, no flash).
    <x-ui.tab-panel name="faq">...</x-ui.tab-panel>
    Props: name (tab key), default (bool, render visible before Alpine boots; set on the default tab's panel)
--}}
@props(['name', 'default' => false])

<div role="tabpanel" aria-labelledby="tab-btn-{{ $name }}" tabindex="0"
    x-show="tab === @js($name)" @unless ($default) x-cloak @endunless
    {{ $attributes->class(['focus:outline-none']) }}>
    {{ $slot }}
</div>
