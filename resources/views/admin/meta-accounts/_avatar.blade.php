{{--
    Channel avatar: Page / IG picture with a fallback to initials when there is none or it fails to load.
    Vars: $account (MetaAccount), $size ('lg' | 'md'), $picture (?string)
--}}
@php
    $size ??= 'lg';
    $isIg = $account->platform === \App\Enums\Platform::Instagram;
    $src = $picture ?? null;
    if (! $src && ! $isIg && $account->page_id && ctype_digit((string) $account->page_id)) {
        // Public Page profile picture (redirect), no token needed.
        $src = 'https://graph.facebook.com/'.$account->page_id.'/picture?type=large';
    }
    $label = trim((string) ($account->page_name ?: $account->ownExternalId() ?: '?'));
    $clean = trim((string) preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $label));
    $words = $clean !== '' ? preg_split('/\s+/u', $clean) : [$label];
    $initials = mb_strtoupper(count($words) > 1 ? mb_substr($words[0], 0, 1).mb_substr(end($words), 0, 1) : mb_substr($words[0], 0, 2));
    $palette = [
        'from-sky-400 to-blue-600', 'from-violet-400 to-indigo-600', 'from-emerald-400 to-teal-600',
        'from-amber-400 to-orange-600', 'from-rose-400 to-pink-600', 'from-fuchsia-400 to-purple-600',
    ];
    $gradient = $isIg ? 'from-amber-400 via-pink-500 to-purple-600' : $palette[crc32(mb_strtolower($label)) % count($palette)];
    $box = $size === 'lg' ? 'size-12 rounded-2xl text-[15px]' : 'size-9 rounded-xl text-xs';
@endphp
<span class="relative inline-flex shrink-0" x-data="{ broken: {{ $src ? 'false' : 'true' }} }">
    @if ($src)
        <img src="{{ $src }}" alt="" loading="lazy" referrerpolicy="no-referrer" x-show="! broken" x-on:error="broken = true"
            class="{{ $box }} bg-slate-100 object-cover ring-1 ring-black/5">
    @endif
    <span x-show="broken" @if ($src) x-cloak @endif aria-hidden="true"
        class="{{ $box }} {{ $gradient }} inline-flex items-center justify-center bg-gradient-to-br font-semibold tracking-tight text-white shadow-xs select-none">{{ $initials }}</span>
    <x-ui.channel-badge :platform="$account->platform" variant="icon" :size="$size === 'lg' ? 'xs' : '2xs'" class="absolute -right-1 -bottom-1" />
</span>
