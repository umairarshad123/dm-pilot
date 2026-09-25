<x-layouts.guest title="Log in">
    <h1 class="text-2xl font-semibold tracking-tight text-ink">Welcome back</h1>
    <p class="mt-1.5 text-sm text-ink-muted">Log in to manage your conversations and bot.</p>

    @if (session('status'))
        <x-ui.alert tone="success" class="mt-6">{{ session('status') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">
        @csrf

        <x-ui.field label="Email" for="email" name="email">
            <x-ui.input name="email" id="email" type="email" required autofocus autocomplete="username" placeholder="you@company.com" />
        </x-ui.field>

        <x-ui.field label="Password" for="password" name="password">
            <div class="relative" x-data="{ show: false }">
                <x-ui.input name="password" id="password" type="password" required autocomplete="current-password" class="pr-10" x-bind:type="show ? 'text' : 'password'" />
                <button type="button" x-on:click="show = ! show" class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600" x-bind:aria-label="show ? 'Hide password' : 'Show password'" aria-label="Show password">
                    <span x-show="! show"><x-ui.icon name="eye" class="size-4" /></span>
                    <span x-show="show" x-cloak><x-ui.icon name="eye-off" class="size-4" /></span>
                </button>
            </div>
        </x-ui.field>

        <x-ui.checkbox name="remember" label="Remember me" />

        <x-ui.button type="submit" variant="primary" size="lg" block>Log in</x-ui.button>
    </form>

    <p class="mt-10 flex items-center gap-2 text-xs text-slate-400">
        <x-ui.icon name="lock" class="size-3.5" /> Admin access only.
    </p>
</x-layouts.guest>
