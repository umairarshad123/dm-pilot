{{-- Image viewer for message attachments (Esc / click to close). Alpine: liveChat.lightbox = { url, label } --}}
<div x-show="lightbox" x-cloak x-transition.opacity.duration.150ms x-on:click.self="lightbox = null"
    class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/85 p-4 backdrop-blur-sm sm:p-10"
    role="dialog" aria-modal="true" aria-label="Image preview" x-trap.noscroll="lightbox">
    <div class="absolute top-3 right-3 flex items-center gap-2">
        <a x-bind:href="lightbox?.url" target="_blank" rel="noopener noreferrer nofollow"
            class="inline-flex h-9 items-center gap-1.5 rounded-full bg-white/10 px-3.5 text-[13px] font-medium text-white ring-1 ring-white/15 transition hover:bg-white/20">
            <x-ui.icon name="external-link" class="size-4" /> Open original
        </a>
        <button type="button" x-on:click="lightbox = null" class="inline-flex size-9 items-center justify-center rounded-full bg-white/10 text-white ring-1 ring-white/15 transition hover:bg-white/20" aria-label="Close preview">
            <x-ui.icon name="x" class="size-5" />
        </button>
    </div>
    <template x-if="lightbox">
        <img x-bind:src="lightbox.url" x-bind:alt="lightbox.label" referrerpolicy="no-referrer"
            class="max-h-full max-w-full rounded-xl object-contain shadow-2xl" x-on:click.stop>
    </template>
</div>
