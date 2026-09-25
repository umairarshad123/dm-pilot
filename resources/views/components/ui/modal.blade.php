{{--
    Modal dialog (Alpine + focus trap, Esc / backdrop to close, scroll lock).
    <x-ui.modal name="delete-account" title="Delete this page?" description="This cannot be undone." size="sm">
        <p>...</p>
        <x-slot:footer>
            <x-ui.button x-on:click="close()">Cancel</x-ui.button>
            <form method="POST" action="..."> @csrf @method('DELETE') <x-ui.button type="submit" variant="danger">Delete</x-ui.button></form>
        </x-slot:footer>
    </x-ui.modal>

    Open:  <x-ui.button x-data x-on:click="$dispatch('open-modal', 'delete-account')">Delete</x-ui.button>
    Close: $dispatch('close-modal', 'delete-account')  or  close() from inside.
    Props:
      name         unique id (required)        title, description, icon (+ tone: brand|danger|warning|success)
      size         sm | md (default) | lg | xl
      show         bool: open on page load (e.g. when $errors relate to the modal's form)
--}}
@props(['name', 'title' => null, 'description' => null, 'icon' => null, 'tone' => 'brand', 'size' => 'md', 'show' => false])

@php
    $sizes = ['sm' => 'sm:max-w-md', 'md' => 'sm:max-w-lg', 'lg' => 'sm:max-w-2xl', 'xl' => 'sm:max-w-4xl'];
    $tones = ['brand' => 'bg-brand-50 text-brand-600', 'danger' => 'bg-danger-50 text-danger-600', 'warning' => 'bg-warning-50 text-warning-600', 'success' => 'bg-success-50 text-success-600'];
@endphp

<div x-data="{
        open: @js((bool) $show),
        close() { this.open = false },
    }"
    x-on:open-modal.window="$event.detail === @js($name) && (open = true)"
    x-on:close-modal.window="$event.detail === @js($name) && close()"
    x-on:keydown.escape.window="open && close()"
    {{ $attributes->only('id') }}>
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center p-0 sm:items-center sm:p-6" role="dialog" aria-modal="true" @if ($title) aria-labelledby="modal-{{ $name }}-title" @endif>
            <div x-show="open" x-transition.opacity.duration.150ms class="fixed inset-0 bg-slate-900/40 backdrop-blur-[2px]" x-on:click="close()"></div>
            <div x-show="open" x-trap.noscroll.inert="open"
                x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                {{ $attributes->except('id')->class(['relative flex max-h-[92vh] w-full flex-col rounded-t-2xl bg-white shadow-pop sm:rounded-2xl', $sizes[$size] ?? $sizes['md']]) }}>
                @if ($title || $icon)
                    <div class="flex items-start gap-4 px-6 pt-6">
                        @if ($icon)
                            <span class="{{ $tones[$tone] ?? $tones['brand'] }} inline-flex size-10 shrink-0 items-center justify-center rounded-full">
                                <x-ui.icon :name="$icon" class="size-5" />
                            </span>
                        @endif
                        <div class="min-w-0 flex-1 pt-0.5">
                            @if ($title)<h2 id="modal-{{ $name }}-title" class="text-base font-semibold text-ink">{{ $title }}</h2>@endif
                            @if ($description)<p class="mt-1 text-sm text-ink-muted">{{ $description }}</p>@endif
                        </div>
                        <button type="button" x-on:click="close()" class="-mt-1 -mr-2 rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Close">
                            <x-ui.icon name="x" class="size-4" />
                        </button>
                    </div>
                @endif
                <div class="ui-scroll min-h-0 flex-1 overflow-y-auto px-6 py-5 text-sm text-slate-700">{{ $slot }}</div>
                @isset($footer)
                    <div class="flex flex-col-reverse gap-2 rounded-b-2xl border-t border-line bg-slate-50/70 px-6 py-4 sm:flex-row sm:justify-end [&>form]:contents">{{ $footer }}</div>
                @endisset
            </div>
        </div>
    </template>
</div>
