{{--
    Tabs (Alpine) or link-tabs (server-side navigation).

    Client tabs:
      <x-ui.tabs :tabs="['general' => 'General', 'faq' => ['label' => 'FAQ', 'icon' => 'book', 'count' => 12]]" default="general" hash>
          <x-ui.tab-panel name="general">...</x-ui.tab-panel>
          <x-ui.tab-panel name="faq">...</x-ui.tab-panel>
      </x-ui.tabs>
      Inside, `tab` is the active key (Alpine), e.g. x-show="tab === 'faq'". Arrow keys move between tabs.

    Link tabs (each item has href; active decided by you):
      <x-ui.tabs :tabs="[
          ['label' => 'Global', 'href' => route('admin.bot-settings.edit'), 'active' => request()->routeIs('admin.bot-settings.edit')],
          ['label' => 'Per page', 'href' => '#', 'active' => false],
      ]" />

    Props:
      tabs     array  key => label | key => [label, icon, count]  (or list of [label, href, active, icon, count])
      default  active key on load (defaults to the first key)
      hash     bool: sync the active tab with location.hash (#tab-faq) so reloads/links keep it
      variant  underline (default) | pills
--}}
@props(['tabs' => [], 'default' => null, 'hash' => false, 'variant' => 'underline'])

@php
    $items = [];
    foreach ($tabs as $key => $tab) {
        $tab = is_array($tab) ? $tab : ['label' => $tab];
        $items[] = $tab + ['key' => (string) $key, 'icon' => null, 'count' => null, 'href' => null, 'active' => false];
    }
    $isLinks = collect($items)->contains(fn ($t) => $t['href'] !== null);
    $default ??= $items[0]['key'] ?? '';

    $bar = $variant === 'pills'
        ? 'inline-flex items-center gap-1 rounded-xl bg-slate-100 p-1'
        : 'flex items-center gap-1 overflow-x-auto border-b border-line ui-scroll';
    $base = $variant === 'pills'
        ? 'inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-[13px] font-medium whitespace-nowrap transition'
        : 'relative -mb-px inline-flex items-center gap-2 border-b-2 px-3 pt-2 pb-2.5 text-sm font-medium whitespace-nowrap transition';
    $on = $variant === 'pills' ? 'bg-white text-ink shadow-xs' : 'border-brand-600 text-brand-700';
    $off = $variant === 'pills' ? 'text-slate-600 hover:text-ink' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-ink';
@endphp

@if ($isLinks)
    <div {{ $attributes->class(['min-w-0']) }}>
        <nav class="{{ $bar }}" aria-label="Tabs">
            @foreach ($items as $t)
                <a href="{{ $t['href'] }}" class="{{ $base }} {{ $t['active'] ? $on : $off }}" @if ($t['active']) aria-current="page" @endif>
                    @if ($t['icon'])<x-ui.icon :name="$t['icon']" class="size-4" />@endif
                    {{ $t['label'] }}
                    @if ($t['count'] !== null)<span class="rounded-full bg-slate-100 px-1.5 text-2xs font-semibold text-slate-600">{{ $t['count'] }}</span>@endif
                </a>
            @endforeach
        </nav>
        @if ($slot->isNotEmpty())<div class="pt-6">{{ $slot }}</div>@endif
    </div>
@else
    <div {{ $attributes->class(['min-w-0']) }}
        x-data="{
            tab: @js($default),
            keys: @js(array_column($items, 'key')),
            hash: @js((bool) $hash),
            init() {
                if (this.hash) {
                    const h = location.hash.replace('#tab-', '');
                    if (this.keys.includes(h)) this.tab = h;
                    this.$watch('tab', v => history.replaceState(null, '', '#tab-' + v));
                }
            },
            move(dir) {
                const i = (this.keys.indexOf(this.tab) + dir + this.keys.length) % this.keys.length;
                this.tab = this.keys[i];
                this.$nextTick(() => this.$root.querySelector(`[data-tab='${this.tab}']`)?.focus());
            },
        }">
        <div class="{{ $bar }}" role="tablist" x-on:keydown.arrow-right.prevent="move(1)" x-on:keydown.arrow-left.prevent="move(-1)">
            @foreach ($items as $t)
                <button type="button" role="tab" data-tab="{{ $t['key'] }}" id="tab-btn-{{ $t['key'] }}"
                    x-on:click="tab = @js($t['key'])"
                    x-bind:aria-selected="tab === @js($t['key'])"
                    x-bind:tabindex="tab === @js($t['key']) ? 0 : -1"
                    x-bind:class="{ [@js($on)]: tab === @js($t['key']), [@js($off)]: tab !== @js($t['key']) }"
                    class="{{ $base }} {{ $t['key'] === $default ? $on : $off }}">
                    @if ($t['icon'])<x-ui.icon :name="$t['icon']" class="size-4" />@endif
                    {{ $t['label'] }}
                    @if ($t['count'] !== null)<span class="rounded-full bg-slate-100 px-1.5 text-2xs font-semibold text-slate-600">{{ $t['count'] }}</span>@endif
                </button>
            @endforeach
        </div>
        @if ($slot->isNotEmpty())<div class="pt-6">{{ $slot }}</div>@endif
    </div>
@endif
