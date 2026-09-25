{{--
    Dropdown menu (Alpine). Click-outside / Esc to close, arrow keys move focus between items.
    <x-ui.dropdown align="right" width="w-56">
        <x-slot:trigger>
            <x-ui.button variant="ghost" size="sm" icon="dots" aria-label="More" />
        </x-slot:trigger>
        <x-ui.dropdown-item :href="route('...')" icon="edit">Edit</x-ui.dropdown-item>
        <x-ui.dropdown-divider />
        <x-ui.dropdown-item icon="trash" danger x-on:click="$dispatch('open-modal', 'delete')">Delete</x-ui.dropdown-item>
    </x-ui.dropdown>
    Props:
      align     left | right (default) | top-left | top-right (opens upwards)
      width     tailwind width class for the panel (default w-56)
    Slots: trigger (any focusable element), default (items), header (optional block above items)
--}}
@props(['align' => 'right', 'width' => 'w-56'])

@php
    $position = match ($align) {
        'left' => 'left-0 top-full mt-2 origin-top-left',
        'top-left' => 'left-0 bottom-full mb-2 origin-bottom-left',
        'top-right' => 'right-0 bottom-full mb-2 origin-bottom-right',
        default => 'right-0 top-full mt-2 origin-top-right',
    };
@endphp

<div x-data="{
        open: false,
        toggle() { this.open = ! this.open },
        close(focus = false) { this.open = false; if (focus) this.$refs.trigger.querySelector('button,a,[tabindex]')?.focus() },
        items() { return [...this.$refs.panel.querySelectorAll('[role=menuitem]:not([disabled])')] },
        focusItem(dir) {
            const items = this.items(); if (! items.length) return;
            const i = items.indexOf(document.activeElement);
            items[(i + dir + items.length) % items.length].focus();
        },
    }"
    x-on:keydown.escape.prevent.stop="close(true)"
    x-on:click.outside="close()"
    {{ $attributes->class(['relative inline-block text-left']) }}>
    <div x-ref="trigger" x-on:click="toggle()" x-on:keydown.arrow-down.prevent="open = true; $nextTick(() => focusItem(1))" aria-haspopup="menu" x-bind:aria-expanded="open">
        {{ $trigger }}
    </div>
    <div x-ref="panel" x-show="open" x-cloak role="menu"
        x-on:keydown.arrow-down.prevent="focusItem(1)" x-on:keydown.arrow-up.prevent="focusItem(-1)" x-on:keydown.tab="close()"
        x-transition:enter="transition duration-100 ease-out" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition duration-75 ease-in" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
        class="{{ $position }} {{ $width }} absolute z-50 rounded-xl border border-line bg-white p-1.5 shadow-pop focus:outline-none">
        @isset($header)<div class="border-b border-line px-2.5 pt-1.5 pb-2.5 mb-1.5">{{ $header }}</div>@endisset
        {{ $slot }}
    </div>
</div>
