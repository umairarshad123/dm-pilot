{{--
    Alpine-bound avatar: profile picture (falls back to initials when missing or expired) + channel badge.
    @include('admin.inbox.partials.avatar', ['obj' => 'c', 'size' => 'size-11 text-sm', 'badge' => 'xs'])
    'badge' => false hides the channel badge. $obj is the Alpine expression of a row / conversation object (name, initials, avatar, platform).
--}}
@php($badge ??= 'xs')
<span class="relative inline-flex shrink-0">
    <template x-if="imageOk({{ $obj }}?.avatar)">
        <img x-bind:src="{{ $obj }}.avatar" x-bind:alt="{{ $obj }}.name" x-on:error="imageFailed({{ $obj }}.avatar)"
            referrerpolicy="no-referrer" loading="lazy" class="{{ $size }} rounded-full bg-slate-100 object-cover ring-1 ring-black/5">
    </template>
    <template x-if="! imageOk({{ $obj }}?.avatar)">
        <span class="{{ $size }} inline-flex items-center justify-center rounded-full font-semibold tracking-tight select-none"
            x-bind:class="{ [avatarColor({{ $obj }}?.name)]: true }" x-text="{{ $obj }}?.initials ?? '?'" aria-hidden="true"></span>
    </template>
    @if ($badge)
        <span class="absolute -right-1 -bottom-1" x-show="{{ $obj }}?.platform === 'instagram'"><x-ui.channel-badge platform="instagram" variant="icon" :size="$badge" /></span>
        <span class="absolute -right-1 -bottom-1" x-show="{{ $obj }}?.platform === 'facebook'"><x-ui.channel-badge platform="facebook" variant="icon" :size="$badge" /></span>
    @endif
</span>
