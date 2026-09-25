{{--
    Contact avatar: profile picture over an initials fallback (Meta CDN URLs expire; a broken image removes itself)
    plus the channel badge. @include('admin.contacts._avatar', ['contact' => $c, 'size' => 'md'])
--}}
@php
    $size ??= 'md';
    $sizeClass = ['sm' => 'size-8 text-[11px]', 'md' => 'size-9 text-xs', 'lg' => 'size-11 text-sm'][$size] ?? 'size-9 text-xs';
    $name = $contact->displayName();
@endphp
<span class="relative inline-flex shrink-0">
    <span class="{{ $sizeClass }} {{ \App\Http\Controllers\Admin\ContactController::avatarClass($name) }} inline-flex items-center justify-center rounded-full font-semibold tracking-tight select-none" aria-hidden="true">{{ $contact->initials() }}</span>
    @if ($contact->profile_pic_url)
        <img src="{{ $contact->profile_pic_url }}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()"
            class="{{ $sizeClass }} absolute inset-0 rounded-full bg-white object-cover ring-1 ring-black/5">
    @endif
    @if ($contact->platform)
        <x-ui.channel-badge :platform="$contact->platform" variant="icon" size="2xs" class="absolute -right-1 -bottom-1" />
    @endif
</span>
