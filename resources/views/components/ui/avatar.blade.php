{{--
    Avatar: image, or initials with a deterministic colour (same name -> same colour).
    <x-ui.avatar name="Alice Smith" />
    <x-ui.avatar :name="$c->customer_name" :src="$c->profile_pic" size="lg" :channel="$c->platform" status="online" />
    Props:
      name     string (initials + colour seed)        src  image url (optional)
      size     xs(20) | sm(28) | md(36, default) | lg(44) | xl(56)
      channel  'facebook' | 'instagram' | Platform enum: small brand badge bottom-right
      status   null | online | away | offline: dot top-right
      square   bool: rounded square instead of a circle (Pages / brands)
--}}
@props(['name' => '?', 'src' => null, 'size' => 'md', 'channel' => null, 'status' => null, 'square' => false])

@php
    $label = trim((string) $name) !== '' ? trim((string) $name) : '?';
    $clean = trim((string) preg_replace('/[^\p{L}\p{N}\s]/u', '', $label));
    $words = $clean !== '' ? preg_split('/\s+/u', $clean) : [$label];
    $initials = mb_strtoupper(
        count($words) > 1 ? mb_substr($words[0], 0, 1).mb_substr(end($words), 0, 1) : mb_substr($words[0], 0, 2)
    );

    $palette = [
        'bg-sky-100 text-sky-700', 'bg-violet-100 text-violet-700', 'bg-emerald-100 text-emerald-700',
        'bg-amber-100 text-amber-800', 'bg-rose-100 text-rose-700', 'bg-indigo-100 text-indigo-700',
        'bg-teal-100 text-teal-700', 'bg-fuchsia-100 text-fuchsia-700', 'bg-orange-100 text-orange-700',
        'bg-blue-100 text-blue-700', 'bg-lime-100 text-lime-800', 'bg-cyan-100 text-cyan-800',
    ];
    $color = $palette[crc32(mb_strtolower($label)) % count($palette)];

    $sizes = [
        'xs' => 'size-5 text-[9px]', 'sm' => 'size-7 text-[11px]', 'md' => 'size-9 text-xs',
        'lg' => 'size-11 text-sm', 'xl' => 'size-14 text-base',
    ];
    $sizeClass = $sizes[$size] ?? $sizes['md'];
    $radius = $square ? (in_array($size, ['lg', 'xl'], true) ? 'rounded-xl' : 'rounded-lg') : 'rounded-full';
    $statusColors = ['online' => 'bg-success-500', 'away' => 'bg-warning-500', 'offline' => 'bg-slate-300'];
@endphp

<span {{ $attributes->class(['relative inline-flex shrink-0']) }}>
    @if ($src)
        <img src="{{ $src }}" alt="{{ $label }}" loading="lazy" referrerpolicy="no-referrer" class="{{ $sizeClass }} {{ $radius }} object-cover ring-1 ring-black/5">
    @else
        <span class="{{ $sizeClass }} {{ $radius }} {{ $color }} inline-flex items-center justify-center font-semibold tracking-tight select-none" role="img" aria-label="{{ $label }}">{{ $initials }}</span>
    @endif
    @if ($channel)
        <x-ui.channel-badge :platform="$channel" variant="icon" :size="['xs' => '2xs', 'sm' => '2xs', 'md' => '2xs', 'lg' => 'xs', 'xl' => 'sm'][$size] ?? 'xs'" class="absolute -right-1 -bottom-1" />
    @endif
    @if ($status && isset($statusColors[$status]))
        <span class="{{ $statusColors[$status] }} absolute -top-0.5 -right-0.5 size-2.5 rounded-full ring-2 ring-white"></span>
    @endif
</span>
