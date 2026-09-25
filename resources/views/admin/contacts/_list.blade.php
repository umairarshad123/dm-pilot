{{-- Contacts table (re-fetched in place by reloadResults(), so keep the data-results wrapper). --}}
<div data-results data-ids='@json($contacts->getCollection()->pluck('id')->values())' data-total="{{ $contacts->total() }}">
    <x-ui.table hoverable>
        <x-slot:head>
            <tr>
                <th class="w-0 !pr-0">
                    <input type="checkbox" class="size-4 rounded border-line-strong accent-brand-600" aria-label="Select all contacts on this page"
                        x-bind:checked="allOnPage" x-effect="$el.indeterminate = someOnPage" x-on:change="toggleAll($event.target.checked)"
                        @disabled($contacts->isEmpty())>
                </th>
                <th>Contact</th>
                @if ($showPageColumn)<th class="hidden 2xl:table-cell">Page</th>@endif
                <th>Stage</th>
                <th>Tags</th>
                <th>Email / phone</th>
                <th class="text-right">Messages</th>
                <th>Last active</th>
                <th>Bot</th>
                <th class="w-0"><span class="sr-only">Actions</span></th>
            </tr>
        </x-slot:head>

        @forelse ($contacts as $contact)
            @php
                $stage = $stageStyles[$contact->leadStage()->value];
                $bot = $botStyles[\App\Http\Controllers\Admin\ContactController::botState($contact)];
                $tagList = $contact->tagList();
                $unread = (int) $contact->unread_count;
            @endphp
            <tr data-contact-id="{{ $contact->id }}" class="cursor-pointer"
                x-bind:class="{ '[&>td]:!bg-brand-50/50': selected.includes({{ $contact->id }}) || allMatching }"
                x-on:click="open({{ $contact->id }})">
                <td class="w-0 !pr-0" x-on:click.stop>
                    <input type="checkbox" class="size-4 rounded border-line-strong accent-brand-600" aria-label="Select {{ $contact->displayName() }}"
                        x-bind:checked="allMatching || selected.includes({{ $contact->id }})" x-on:change="toggle({{ $contact->id }})">
                </td>
                <td class="min-w-60">
                    <div class="flex items-center gap-3">
                        @include('admin.contacts._avatar', ['contact' => $contact, 'size' => 'md'])
                        <div class="min-w-0">
                            <a href="{{ route('admin.contacts.index', ['contact' => $contact->id] + request()->except(['contact'])) }}"
                                x-on:click.prevent.stop="open({{ $contact->id }})"
                                @class(['block max-w-56 truncate text-ink hover:text-brand-700', 'font-semibold' => $unread > 0, 'font-medium' => $unread === 0])>{{ $contact->displayName() }}</a>
                            <p class="flex items-center gap-1.5 truncate text-xs text-ink-muted">
                                @if ($unread > 0)
                                    <span class="inline-flex items-center gap-1 font-semibold text-brand-600"><span class="size-1.5 rounded-full bg-brand-500"></span>{{ $unread }} unread</span>
                                    <span class="text-slate-300">·</span>
                                @endif
                                @if ($contact->username)
                                    <span class="truncate">{{ '@'.$contact->username }}</span>
                                @else
                                    <span>{{ $contact->platform?->value === 'instagram' ? 'Instagram' : 'Messenger' }}</span>
                                @endif
                                @if ($showPageColumn && $contact->metaAccount?->page_name)
                                    <span class="text-slate-300 2xl:hidden">·</span>
                                    <span class="max-w-32 truncate 2xl:hidden">{{ $contact->metaAccount->page_name }}</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </td>
                @if ($showPageColumn)
                    <td class="hidden max-w-40 truncate text-[13px] text-slate-600 2xl:table-cell">{{ $contact->metaAccount?->page_name ?: '—' }}</td>
                @endif
                <td>
                    <span class="{{ $stage['badge'] }} inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset">
                        <span class="{{ $stage['dot'] }} size-1.5 rounded-full" aria-hidden="true"></span>{{ $stage['label'] }}
                    </span>
                </td>
                <td>
                    @if ($tagList)
                        <div class="flex max-w-56 items-center gap-1" title="{{ implode(', ', $tagList) }}">
                            @foreach (array_slice($tagList, 0, 2) as $tag)
                                <span class="inline-flex max-w-28 items-center gap-1 truncate rounded-md bg-slate-100 px-1.5 py-0.5 text-2xs font-medium text-slate-700">
                                    <x-ui.icon name="tag" class="size-2.5 shrink-0 text-slate-400" /><span class="truncate">{{ $tag }}</span>
                                </span>
                            @endforeach
                            @if (count($tagList) > 2)
                                <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-2xs font-semibold text-slate-500">+{{ count($tagList) - 2 }}</span>
                            @endif
                        </div>
                    @else
                        <span class="text-slate-300">—</span>
                    @endif
                </td>
                <td class="text-[13px]">
                    @if ($contact->email || $contact->phone)
                        <div class="max-w-56 space-y-0.5">
                            @if ($contact->email)
                                <p class="flex items-center gap-1.5 truncate text-slate-700"><x-ui.icon name="mail" class="size-3.5 shrink-0 text-slate-400" /><span class="truncate">{{ $contact->email }}</span></p>
                            @endif
                            @if ($contact->phone)
                                <p class="flex items-center gap-1.5 truncate text-slate-700 tabular-nums"><x-ui.icon name="phone" class="size-3.5 shrink-0 text-slate-400" /><span class="truncate">{{ $contact->phone }}</span></p>
                            @endif
                        </div>
                    @else
                        <span class="text-slate-300">—</span>
                    @endif
                </td>
                <td class="text-right text-[13px] font-medium text-slate-700 tabular-nums">{{ number_format($contact->messages_count) }}</td>
                <td class="text-[13px] whitespace-nowrap text-ink-muted" title="{{ $contact->last_message_at?->format('M j, Y · H:i') }}">
                    {{ $contact->last_message_at?->diffForHumans(['parts' => 1]) ?? '—' }}
                </td>
                <td>
                    <span class="{{ $bot['class'] }} inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-2xs font-semibold whitespace-nowrap ring-1 ring-inset">
                        <span class="{{ $bot['dot'] }} size-1.5 rounded-full" aria-hidden="true"></span>{{ $bot['label'] }}
                    </span>
                </td>
                <td class="w-0" x-on:click.stop>
                    <a href="{{ route('admin.conversations.show', $contact) }}" class="inline-flex size-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-brand-50 hover:text-brand-600"
                        aria-label="Open {{ $contact->displayName() }} in Live Chat" title="Open in Live Chat">
                        <x-ui.icon name="message" class="size-4" />
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="{{ $showPageColumn ? 10 : 9 }}">
                    <x-ui.empty-state compact icon="search" tone="neutral" title="No contacts match these filters"
                        description="Try a different search, or clear the filters to see everyone.">
                        <x-ui.button :href="route('admin.contacts.index')" icon="x" size="sm">Clear filters</x-ui.button>
                    </x-ui.empty-state>
                </td>
            </tr>
        @endforelse

        <x-slot:footer>{{ $contacts->links('components.ui.pagination') }}</x-slot:footer>
    </x-ui.table>
</div>
