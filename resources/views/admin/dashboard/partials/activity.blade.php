{{-- Recent activity feed. $recent = InsightsService::recentActivity(). --}}
@php
    $conversationRoute = \Illuminate\Support\Facades\Route::has('admin.conversations.show');
    $badge = function (array $r): array {
        if ($r['status'] === 'failed') {
            return ['danger', 'Failed', 'alert-circle'];
        }
        if ($r['direction'] === 'incoming') {
            return ['neutral', 'Customer', null];
        }
        if ($r['sender_type'] === 'human') {
            return ['purple', 'Human', 'hand'];
        }

        return match ($r['source']) {
            'automation' => ['info', 'Automation', 'zap'],
            'fallback' => ['warning', 'Fallback', null],
            default => ['brand', 'Bot', 'sparkles'],
        };
    };
@endphp

<x-ui.card :padded="false" class="h-full">
    <x-slot:header>
        <div class="flex items-center justify-between gap-3">
            <div>
                <h3 class="text-[15px] font-semibold text-ink">Recent activity</h3>
                <p class="mt-0.5 text-[13px] text-ink-muted">Latest messages {{ $selectedPageId ? 'on this page' : 'across your pages' }}</p>
            </div>
            <x-ui.button size="sm" variant="ghost" icon-right="arrow-right" :href="$links['live_chat']">Live Chat</x-ui.button>
        </div>
    </x-slot:header>

    @if (empty($recent))
        <x-ui.empty-state compact icon="messages" title="No messages yet"
            description="Conversations will stream in here the moment someone messages your page.">
            <x-ui.button size="sm" variant="soft" icon="bot" :href="$links['bot']">Test your bot</x-ui.button>
        </x-ui.empty-state>
    @else
        <ul class="ui-scroll max-h-[26rem] divide-y divide-line overflow-y-auto">
            @foreach ($recent as $r)
                @php([$tone, $label, $icon] = $badge($r))
                <li>
                    <a href="{{ $conversationRoute ? route('admin.conversations.show', $r['conversation_id']) : $links['live_chat'] }}"
                        class="group flex items-start gap-3 px-5 py-3 transition-colors hover:bg-slate-50 focus-visible:bg-slate-50">
                        <x-ui.avatar :name="$r['contact_name']" :src="$r['profile_pic_url']" :channel="$r['platform']" size="sm" class="mt-0.5" />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="min-w-0 truncate text-[13px] font-semibold text-ink group-hover:text-brand-700">{{ $r['contact_name'] }}</p>
                                <x-ui.badge :tone="$tone" size="sm" :icon="$icon">{{ $label }}</x-ui.badge>
                                <time class="ml-auto shrink-0 text-2xs text-slate-400" datetime="{{ $r['at']?->toIso8601String() }}" title="{{ $r['at']?->toDayDateTimeString() }}">{{ $r['at']?->shortRelativeDiffForHumans() }}</time>
                            </div>
                            <p class="mt-0.5 line-clamp-2 text-[13px] text-slate-600">
                                @if ($r['direction'] === 'outgoing')<span class="text-slate-400">You: </span>@endif{{ $r['excerpt'] !== '' ? $r['excerpt'] : '(no text)' }}
                            </p>
                            @if (! $selectedPageId && $r['page_name'])
                                <p class="mt-1 truncate text-2xs text-slate-400">{{ $r['page_name'] }}</p>
                            @endif
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-ui.card>
