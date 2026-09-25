@php
    $editing = $account->exists;
    $isIg = $account->platform === \App\Enums\Platform::Instagram;
    $name = $account->page_name ?: ($editing ? 'Account #'.$account->id : 'New account');
    $viaOauth = in_array($account->setting('connected_via'), ['facebook_login', 'token'], true);
@endphp
<x-layouts.app :title="$editing ? 'Edit '.$name : 'Add a channel manually'" width="narrow">
    <x-ui.page-header :title="$editing ? $name : 'Add a channel manually'"
        :description="$editing ? 'Channel details and access token.' : 'Advanced: paste IDs and a Page token yourself. Continue with Facebook is easier.'"
        :back="route('admin.meta-accounts.index')" back-label="Pages & Channels">
        @if ($editing)
            <x-slot:leading>
                @include('admin.meta-accounts._avatar', ['account' => $account, 'size' => 'lg', 'picture' => $account->setting('picture_url')])
            </x-slot:leading>
            <x-slot:meta>
                <x-ui.channel-badge :platform="$account->platform" size="sm" />
                <x-ui.badge :tone="$account->active ? 'success' : 'neutral'" dot>{{ $account->active ? 'Active' : 'Paused' }}</x-ui.badge>
                @if ($account->setting('connected_at'))
                    <span class="text-xs text-ink-muted">Connected {{ \Illuminate\Support\Carbon::parse($account->setting('connected_at'))->diffForHumans() }}</span>
                @endif
            </x-slot:meta>
        @endif
    </x-ui.page-header>

    @unless ($editing)
        <x-ui.alert tone="brand" title="Tip: let us do this for you" class="mb-6">
            Continue with Facebook finds your Pages, their Instagram accounts and never-expiring tokens automatically.
            <x-slot:actions>
                <x-ui.button size="sm" variant="primary" icon="facebook" :href="route('admin.meta-accounts.connect')">Continue with Facebook</x-ui.button>
            </x-slot:actions>
        </x-ui.alert>
    @endunless

    <form method="POST" action="{{ $editing ? route('admin.meta-accounts.update', $account) : route('admin.meta-accounts.store') }}" autocomplete="off" data-warn-unsaved class="space-y-6"
        x-data="{ platform: @js(old('platform', $account->platform?->value ?? 'facebook')), unlock: {{ $editing ? 'false' : 'true' }}, replace: {{ $editing ? ($errors->has('access_token') ? 'true' : 'false') : 'true' }} }">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-ui.card title="Details" icon="layers">
            <div class="space-y-5">
                <x-ui.input name="page_name" label="Display name" :value="old('page_name', $account->page_name)" maxlength="255" hint="Shown in the sidebar, Live Chat and reports." />

                <x-ui.field inline label="Active" hint="Paused channels ignore incoming messages.">
                    <x-ui.toggle name="active" :checked="(bool) old('active', $account->active ?? true)" label="Bot answers on this channel" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Meta IDs" icon="key" :description="$editing ? 'Set when the channel was connected. Change only if you know what you are doing.' : 'Find them in Meta Business Suite → Settings.'">
            @if ($editing)
                <x-slot:actions>
                    <x-ui.button size="xs" variant="ghost" icon="edit" x-show="! unlock" x-on:click="unlock = true">Edit IDs</x-ui.button>
                </x-slot:actions>
            @endif
            <div class="space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.field label="Channel" for="platform" name="platform">
                        <x-ui.select name="platform" id="platform" x-model="platform" x-bind:disabled="! unlock"
                            :options="['facebook' => 'Messenger (Facebook Page)', 'instagram' => 'Instagram']" :value="old('platform', $account->platform?->value)" />
                    </x-ui.field>
                    <x-ui.field label="Login type" for="auth_type" name="auth_type">
                        <x-ui.select name="auth_type" id="auth_type" x-bind:disabled="! unlock"
                            :options="[\App\Models\MetaAccount::AUTH_FACEBOOK_LOGIN => 'Facebook Login (Page token)', \App\Models\MetaAccount::AUTH_INSTAGRAM_LOGIN => 'Instagram Login (IG user token)']"
                            :value="old('auth_type', $account->auth_type)" />
                    </x-ui.field>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.field label="Page ID" for="page_id" name="page_id" hint="Required for Messenger; the linked Page for Instagram via Facebook Login.">
                        <x-ui.input name="page_id" id="page_id" :value="old('page_id', $account->page_id)" inputmode="numeric" class="font-mono" x-bind:readonly="! unlock" />
                    </x-ui.field>
                    <x-ui.field label="Instagram account ID" for="instagram_account_id" name="instagram_account_id" hint="Required for Instagram." x-bind:class="{ 'opacity-60': platform !== 'instagram' }">
                        <x-ui.input name="instagram_account_id" id="instagram_account_id" :value="old('instagram_account_id', $account->instagram_account_id)" inputmode="numeric" class="font-mono" x-bind:readonly="! unlock" />
                    </x-ui.field>
                </div>
                {{-- Disabled selects are not submitted: mirror their values while locked. --}}
                <template x-if="! unlock">
                    <div>
                        <input type="hidden" name="platform" value="{{ $account->platform?->value }}">
                        <input type="hidden" name="auth_type" value="{{ $account->auth_type }}">
                    </div>
                </template>
            </div>
        </x-ui.card>

        <x-ui.card title="Access token" icon="lock" description="Stored encrypted. It is never shown again, not even to admins.">
            <div class="space-y-5">
                @if ($editing)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 px-4 py-3 ring-1 ring-line ring-inset">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex size-8 items-center justify-center rounded-lg bg-white text-slate-500 ring-1 ring-line"><x-ui.icon name="key" class="size-4" /></span>
                            <div>
                                <p class="font-mono text-[13px] text-ink">{{ $account->maskedToken() }}</p>
                                <p class="text-xs text-ink-muted">
                                    Expires {{ $account->token_expires_at?->toDayDateTimeString() ?? 'never' }}
                                    · checked {{ $account->token_checked_at?->diffForHumans() ?? 'never' }}
                                    @if ($viaOauth) · via Facebook @endif
                                </p>
                            </div>
                        </div>
                        <x-ui.button size="sm" icon="refresh" x-show="! replace" x-on:click="replace = true; $nextTick(() => $refs.token.focus())">Replace token</x-ui.button>
                    </div>
                @endif

                <div x-show="replace" @if ($editing && ! $errors->has('access_token')) x-cloak @endif class="space-y-5">
                    <x-ui.field :label="$editing ? 'New access token' : 'Access token'" for="access_token" name="access_token"
                        :hint="$editing ? 'Leave blank to keep the current token.' : 'A Page access token (or an Instagram user token for Instagram Login).'" :required="! $editing">
                        <x-ui.input type="password" name="access_token" id="access_token" value="" autocomplete="new-password" spellcheck="false" icon="lock" x-ref="token" placeholder="EAAG…" />
                    </x-ui.field>
                    <x-ui.field label="Token expires at" for="token_expires_at" name="token_expires_at" optional hint="Blank = never expires / unknown.">
                        <x-ui.input type="datetime-local" name="token_expires_at" id="token_expires_at" :value="old('token_expires_at', $account->token_expires_at?->format('Y-m-d\TH:i'))" />
                    </x-ui.field>
                </div>
            </div>
        </x-ui.card>

        <div class="flex flex-wrap items-center justify-between gap-3">
            @if ($editing)
                <x-ui.button variant="danger-soft" icon="trash" x-on:click="$dispatch('open-modal', 'disconnect-{{ $account->id }}')">Disconnect</x-ui.button>
            @else
                <span></span>
            @endif
            <div class="flex items-center gap-2">
                <x-ui.button :href="route('admin.meta-accounts.index')" variant="ghost">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="check">{{ $editing ? 'Save changes' : 'Add channel' }}</x-ui.button>
            </div>
        </div>
    </form>

    @if ($editing)
        <x-ui.modal name="disconnect-{{ $account->id }}" title="Disconnect {{ $name }}?" icon="trash" tone="danger" size="sm"
            description="The bot stops replying here and all conversations and messages of this channel are deleted.">
            <x-slot:footer>
                <x-ui.button x-on:click="close()">Cancel</x-ui.button>
                <form method="POST" action="{{ route('admin.meta-accounts.destroy', $account) }}">
                    @csrf @method('DELETE')
                    <x-ui.button type="submit" variant="danger" icon="trash">Disconnect</x-ui.button>
                </form>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</x-layouts.app>
