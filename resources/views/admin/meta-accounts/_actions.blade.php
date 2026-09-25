{{--
    Quick-actions menu + disconnect confirmation for one channel. Vars: $account (MetaAccount)
--}}
@php($name = $account->page_name ?: 'Account #'.$account->id)
<x-ui.dropdown align="right" width="w-60">
    <x-slot:trigger>
        <x-ui.button variant="ghost" size="sm" icon="dots" aria-label="Actions for {{ $name }}" />
    </x-slot:trigger>
    <x-slot:header>
        <p class="truncate text-[13px] font-semibold text-ink">{{ $name }}</p>
        <p class="flex items-center gap-1.5 text-xs text-ink-muted"><x-ui.icon name="key" class="size-3" /> <span class="font-mono">{{ $account->maskedToken() }}</span></p>
    </x-slot:header>
    <form method="POST" action="{{ route('admin.meta-accounts.test', $account) }}">
        @csrf
        <x-ui.dropdown-item type="submit" icon="activity" description="Check token, picture, webhooks">Test connection</x-ui.dropdown-item>
    </form>
    <form method="POST" action="{{ route('admin.meta-accounts.subscribe', $account) }}">
        @csrf
        <x-ui.dropdown-item type="submit" icon="webhook" description="messages, postbacks, echoes">Re-subscribe webhooks</x-ui.dropdown-item>
    </form>
    <form method="POST" action="{{ route('admin.meta-accounts.toggle', $account) }}">
        @csrf
        <x-ui.dropdown-item type="submit" :icon="$account->active ? 'pause' : 'play'" :description="$account->active ? 'Stop replying on this channel' : 'Start replying again'">
            {{ $account->active ? 'Pause channel' : 'Activate channel' }}
        </x-ui.dropdown-item>
    </form>
    <x-ui.dropdown-divider />
    <form method="POST" action="{{ route('admin.meta-accounts.open', $account) }}">
        @csrf <input type="hidden" name="to" value="chat">
        <x-ui.dropdown-item type="submit" icon="message">Open Live Chat</x-ui.dropdown-item>
    </form>
    <form method="POST" action="{{ route('admin.meta-accounts.open', $account) }}">
        @csrf <input type="hidden" name="to" value="bot">
        <x-ui.dropdown-item type="submit" icon="bot">Open Bot Studio</x-ui.dropdown-item>
    </form>
    <x-ui.dropdown-item :href="route('admin.meta-accounts.edit', $account)" icon="edit">Edit details</x-ui.dropdown-item>
    <x-ui.dropdown-divider />
    <x-ui.dropdown-item icon="trash" danger x-on:click="close(); $dispatch('open-modal', 'disconnect-{{ $account->id }}')">Disconnect</x-ui.dropdown-item>
</x-ui.dropdown>

<x-ui.modal name="disconnect-{{ $account->id }}" title="Disconnect {{ $name }}?" icon="trash" tone="danger" size="sm"
    description="The bot stops replying here and all conversations and messages of this channel are deleted. You can connect it again later.">
    <x-slot:footer>
        <x-ui.button x-on:click="close()">Cancel</x-ui.button>
        <form method="POST" action="{{ route('admin.meta-accounts.destroy', $account) }}">
            @csrf @method('DELETE')
            <x-ui.button type="submit" variant="danger" icon="trash">Disconnect</x-ui.button>
        </form>
    </x-slot:footer>
</x-ui.modal>
