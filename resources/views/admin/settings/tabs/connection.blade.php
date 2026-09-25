{{-- Settings → Connection --}}
<div class="space-y-6">
    <x-ui.section-header title="Meta connection" description="Values to paste in the Meta App Dashboard. They come from .env and can only be changed there." />

    <x-ui.card title="Webhook" icon="webhook" description="Messenger / Instagram → Configure webhooks → Callback URL and Verify token.">
        <x-slot:actions>
            <x-ui.badge :tone="$verifySignature ? 'success' : 'warning'" dot>{{ $verifySignature ? 'Signature check on' : 'Signature check off' }}</x-ui.badge>
        </x-slot:actions>
        <div class="space-y-5">
            <x-ui.field label="Callback URL">
                <div class="flex items-center gap-2">
                    <code class="ui-code min-w-0 flex-1 truncate py-2">{{ $callbackUrl }}</code>
                    <x-ui.copy-button :value="$callbackUrl" />
                </div>
            </x-ui.field>

            <x-ui.field label="Verify token" hint="META_VERIFY_TOKEN. Must match the dashboard exactly.">
                @if ($verifyToken !== '')
                    <div class="flex items-center gap-2" x-data="{ show: false }">
                        <code class="ui-code min-w-0 flex-1 truncate py-2 font-mono">
                            <span x-show="! show">{{ str_repeat('•', min(24, max(8, strlen($verifyToken)))) }}</span>
                            <span x-show="show" x-cloak>{{ $verifyToken }}</span>
                        </code>
                        <x-ui.button size="sm" variant="secondary" x-on:click="show = ! show" x-bind:aria-label="show ? 'Hide verify token' : 'Reveal verify token'">
                            <span x-show="! show" class="inline-flex items-center gap-1.5"><x-ui.icon name="eye" class="size-3.5" /> Reveal</span>
                            <span x-show="show" x-cloak class="inline-flex items-center gap-1.5"><x-ui.icon name="eye-off" class="size-3.5" /> Hide</span>
                        </x-ui.button>
                        <x-ui.copy-button :value="$verifyToken" icon-only label="Copy verify token" />
                    </div>
                @else
                    <x-ui.alert tone="danger" title="META_VERIFY_TOKEN is not set">Pick any random string, put it in .env and in the Meta dashboard.</x-ui.alert>
                @endif
            </x-ui.field>

            <div>
                <p class="ui-label">Fields subscribed per Page</p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach ((array) ($subscribedFields['facebook_login'] ?? []) as $field)
                        <code class="ui-code">{{ $field }}</code>
                    @endforeach
                </div>
            </div>
        </div>
    </x-ui.card>

    <x-ui.card title="Meta app" icon="key">
        <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2">
            <div>
                <dt class="ui-label">App ID</dt>
                <dd class="mt-1 flex items-center gap-2">
                    @if ($appId !== '')
                        <code class="ui-code">{{ $appId }}</code>
                        <x-ui.copy-button :value="$appId" size="xs" icon-only label="Copy app ID" />
                    @else
                        <x-ui.badge tone="danger" dot>Missing: META_APP_ID</x-ui.badge>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="ui-label">App secret</dt>
                <dd class="mt-1">
                    <x-ui.badge :tone="$appSecretSet ? 'success' : 'danger'" dot>{{ $appSecretSet ? 'Set (hidden)' : 'Missing: META_APP_SECRET' }}</x-ui.badge>
                </dd>
            </div>
            <div>
                <dt class="ui-label">Graph API version</dt>
                <dd class="mt-1"><code class="ui-code">{{ $graphVersion }}</code> <span class="text-xs text-ink-muted">META_GRAPH_VERSION</span></dd>
            </div>
            <div>
                <dt class="ui-label">Webhook signature check</dt>
                <dd class="mt-1 text-[13px] text-ink-muted">
                    <x-ui.badge :tone="$verifySignature ? 'success' : 'warning'" dot>{{ $verifySignature ? 'Enforced' : 'Disabled' }}</x-ui.badge>
                    <span class="ml-1">META_VERIFY_SIGNATURE{{ $verifySignature ? '' : ': turn on in production' }}</span>
                </dd>
            </div>
        </dl>
    </x-ui.card>

    <x-ui.card title="Continue with Facebook" icon="facebook" description="Facebook Login for Business → Settings.">
        <x-slot:actions>
            <x-ui.badge :tone="$loginConfigId !== '' ? 'brand' : 'neutral'">{{ $loginConfigId !== '' ? 'Configuration ID' : 'Scopes' }}</x-ui.badge>
        </x-slot:actions>
        <div class="space-y-5">
            <x-ui.field label="Valid OAuth Redirect URI" hint="Add exactly this URL. It must match byte for byte (https, no trailing slash).">
                <div class="flex items-center gap-2">
                    <code class="ui-code min-w-0 flex-1 truncate py-2">{{ $oauthRedirectUri }}</code>
                    <x-ui.copy-button :value="$oauthRedirectUri" />
                </div>
            </x-ui.field>
            <div>
                <p class="ui-label">{{ $loginConfigId !== '' ? 'Configuration (META_LOGIN_CONFIG_ID)' : 'Permissions requested' }}</p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @if ($loginConfigId !== '')
                        <code class="ui-code">{{ $loginConfigId }}</code>
                    @else
                        @foreach ($loginScopes as $scope)<code class="ui-code">{{ $scope }}</code>@endforeach
                    @endif
                </div>
                <p class="ui-hint mt-2">Optional: create a Facebook Login for Business configuration (User access token, the permissions above) and set its ID as META_LOGIN_CONFIG_ID.</p>
            </div>
        </div>
    </x-ui.card>
</div>
