{{--
    Code snippet block. The slot is shown as literal text (HTML-escaped), so wrap Blade examples in @verbatim.
    <x-ui.code>@verbatim<x-ui.button>Save</x-ui.button>@endverbatim</x-ui.code>
    Props: copy (bool, default true): show a copy button
--}}
@props(['copy' => true])

@php($code = trim((string) $slot, "\r\n"))

<div {{ $attributes->class(['group relative min-w-0']) }}>
    <pre class="ui-pre ui-scroll">{{ $code }}</pre>
    @if ($copy)
        <x-ui.copy-button :value="$code" variant="ghost" size="xs" icon-only label="Copy code"
            class="absolute top-2 right-2 !text-slate-400 opacity-0 transition-opacity group-hover:opacity-100 focus:opacity-100 hover:!bg-white/10 hover:!text-white" />
    @endif
</div>
