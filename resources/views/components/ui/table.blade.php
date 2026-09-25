{{--
    Data table inside a card, horizontally scrollable on small screens.
    <x-ui.table hoverable>
        <x-slot:toolbar> ...filters / bulk actions (optional)... </x-slot:toolbar>
        <x-slot:head>
            <tr><th>Customer</th><th>Channel</th><th class="text-right">Last activity</th></tr>
        </x-slot:head>
        @forelse ($rows as $row)
            <tr> <td>...</td> </tr>
        @empty
            <tr><td colspan="3"><x-ui.empty-state compact icon="inbox" title="Nothing here" /></td></tr>
        @endforelse
        <x-slot:footer>{{ $rows->links('components.ui.pagination') }}</x-slot:footer>
    </x-ui.table>
    Props:
      hoverable  bool: row hover highlight (use when rows are clickable)
      card       bool (default true): false = bare table without the card chrome
    th / td are styled automatically (.ui-table). Use class="text-right" / "w-0" / "whitespace-nowrap" per cell as needed.
--}}
@props(['hoverable' => false, 'card' => true])

<div {{ $attributes->class(['min-w-0 overflow-hidden', 'ui-card' => $card]) }}>
    @isset($toolbar)
        <div class="flex flex-wrap items-center gap-2 border-b border-line px-5 py-3">{{ $toolbar }}</div>
    @endisset
    <div class="ui-scroll overflow-x-auto">
        <table @class(['ui-table', 'is-hoverable' => $hoverable])>
            @isset($head)<thead>{{ $head }}</thead>@endisset
            <tbody>{{ $slot }}</tbody>
        </table>
    </div>
    @isset($footer)
        @if (trim((string) $footer) !== '')
            <div class="border-t border-line px-5 py-3">{{ $footer }}</div>
        @endif
    @endisset
</div>
