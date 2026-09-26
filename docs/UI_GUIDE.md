# Apex Chat Bot admin UI guide

The contract for building admin pages. Live examples of every component: **GET /admin/_ui** (`admin.ui`, source `resources/views/admin/_ui.blade.php`). Copy from there.

Stack: Laravel Blade anonymous components + Tailwind CSS v4 + Alpine.js 3 (with `focus` and `collapse` plugins), all bundled by Vite. No CDNs; the Inter font is downloaded at build time and self-hosted.

> After adding or changing Tailwind classes in any view, run `npm run build`. Classes that are not in the last build do not exist in production (the live site serves `public/build`). Never build class names dynamically (`"bg-{$tone}-50"`); use a PHP map of full class strings like the components do.

---

## 1. Page skeleton

Every admin page uses the `<x-layouts.app>` component (file: `resources/views/components/layouts/app.blade.php`).

```blade
<x-layouts.app title="Contacts">
    <x-slot:actions>
        <x-ui.button variant="primary" icon="plus" :href="route('admin.contacts.create')">Add contact</x-ui.button>
    </x-slot:actions>

    {{-- optional: description / back link / avatar --}}
    <x-ui.page-header title="Contacts" description="Everyone who messaged your pages." />

    <div class="space-y-6">
        ...
    </div>
</x-layouts.app>
```

| Prop / slot | Meaning |
|---|---|
| `title` | Top-bar `<h1>` and `<title>` (suffix "· Apex Chat Bot"). |
| `width` | `default` (max-w-7xl), `narrow` (max-w-3xl, forms), `wide` (max-w-[1600px]), `full` (edge to edge, fills the viewport below the top bar, no padding: use for split panes like Live Chat; make your own children scroll with `overflow-y-auto`). |
| `actions` slot | Primary buttons in the top bar (they move to a second row on phones). |
| `subnav` slot | Row under the top bar that stays sticky with it (link tabs, a filter strip). |
| `@push('scripts')` | Page scripts, rendered before `</body>`. `@push('head')` for extra head tags. |

The shell renders for you: sidebar + page switcher, top bar (title, actions, page-context chip, user menu), flash toasts, and a validation-error summary (hidden on `width="full"`).

Brand name: `App\Providers\ViewServiceProvider::BRAND` (shared as `$brand`). Change it only there.

Legacy: old pages still use `@extends('layouts.admin')` + `@section('content')`. That file now delegates to the new shell and wraps content in `.legacy` (scoped old class names such as `.card`, `.btn`, `.badge`). When you rebuild a page, switch it to `<x-layouts.app>` and stop using legacy classes.

## 2. Page context ($currentPage)

The sidebar switcher stores the selected Page or IG account in the session. `null` means "All pages".

- Service: `App\Support\CurrentPage` (scoped singleton). Inject it in controllers, or use `$currentPage`, which is shared with every `admin.*` view and the layout.
- `$connectedPages` (Collection of MetaAccount: id, page_name, platform, active; active first, then by name) is shared too.

```php
public function index(Request $request, CurrentPage $currentPage)
{
    $conversations = $currentPage->scope(Conversation::query())   // where meta_account_id = selected (no-op for All pages)
        ->latest('last_message_at')->paginate(25);

    return view('admin.contacts.index', compact('conversations'));
}
```

| Method | Returns |
|---|---|
| `id()` | `?int`, the selected MetaAccount id (null = all) |
| `account()` | `?MetaAccount` (full model) |
| `isAll()` | `bool` |
| `label()` | `"All pages"` or the page name |
| `set(?int $id)` | select a page (the `admin.page-switch` POST route does this) |
| `scope(Builder $q, string $column = 'meta_account_id')` | constrained query |
| `connectedPages()` | the Collection above |

Switching: `POST /admin/page-switch` (`admin.page-switch`) with `meta_account_id` (empty = all). Redirects back and flashes a toast. To open the switcher from anywhere: `$dispatch('open-page-switcher')`.

## 3. Conventions

**Spacing (4px grid).** Page sections: `space-y-6` (or `gap-6`). Card padding is `p-5` (built into `x-ui.card`). Form fields: `space-y-5`/`space-y-6`; two-column forms: `grid gap-5 sm:grid-cols-2`. KPI row: `grid gap-4 sm:grid-cols-2 xl:grid-cols-4`. Inline control groups: `gap-2`.

**Type.** Body `text-sm` (14px, the default). Secondary text `text-[13px] text-ink-muted`. Section title `text-base font-semibold` (`x-ui.section-header`). Card title `text-[15px] font-semibold`. Overline `text-2xs font-semibold uppercase tracking-wider text-slate-400`. Numbers: `tabular-nums`.

**Colour tokens** (from `@theme` in `resources/css/app.css`): `brand-50…950` (accent), `ink` / `ink-muted` (text), `canvas` (app bg), `surface` (cards), `line` / `line-strong` (borders), `success-*`, `warning-*`, `danger-*`, `messenger`, `instagram`, `bg-instagram-gradient`. Neutrals are Tailwind `slate`. Shadows: `shadow-xs`, `shadow-card`, `shadow-card-hover`, `shadow-pop` (menus/modals).

**CSS helpers** (for markup that is not a component): `ui-card`, `ui-input`, `ui-label`, `ui-hint`, `ui-error`, `ui-table`, `ui-link`, `ui-code`, `ui-pre`, `ui-skeleton`, `ui-scroll` (thin scrollbar).

**States.** Everything interactive has hover, `focus-visible` (brand outline, provided globally), and disabled styles. Hide Alpine-only elements until boot with `x-cloak`. Icon-only buttons need `aria-label`.

**Channels.** Platform `facebook` is shown as "Messenger". Always use `x-ui.channel-badge` or `x-ui.avatar :channel` for channel markers.

## 4. Components (`resources/views/components/ui/*`)

All are anonymous components; extra HTML attributes (`class`, `x-*`, `wire:*`, `data-*`, `aria-*`) are forwarded to the main element. Every file starts with a usage comment.

| Component | Props (defaults) | Notes |
|---|---|---|
| `x-ui.button` | `variant` primary\|secondary\|soft\|ghost\|danger\|danger-soft\|link (secondary), `size` xs\|sm\|md\|lg (md), `href`, `type` (button), `icon`, `icon-right`, `loading`, `disabled`, `block` | Empty slot = square icon button. `type="submit"` buttons show a spinner automatically while their form submits (opt out with `data-no-loading` on the form). |
| `x-ui.icon` | `name`, `stroke` (2) | Size with a class (`size-4` default). Names: home, dashboard, message, messages, inbox, users, user, bot, zap, layers, settings, sliders, plus, minus, search, send, pause, play, check, check-circle, x, x-circle, chevron-*, chevrons-up-down, arrow-left/right/up-right/down-right, trending-up/down, external-link, link, copy, trash, edit, refresh, logout, sparkles, chart, activity, filter, clock, alert-triangle, alert-circle, info, eye, eye-off, tag, phone, mail, dots, dots-vertical, menu, palette, shield, webhook, globe, lock, key, calendar, image, paperclip, smile, book, hand, download, upload, facebook, messenger, instagram. |
| `x-ui.card` | `title`, `description`, `icon`, `padded` (true), `hover` | Slots: `header` (replaces header), `actions`, `footer`. |
| `x-ui.stat` | `label`, `value`, `delta`, `trend` up\|down\|flat (auto from delta sign), `invert`, `icon`, `tone` brand\|success\|warning\|danger\|neutral\|purple, `hint`, `href` | KPI tile. `invert` = rising is bad. |
| `x-ui.badge` | `tone` neutral\|brand\|info\|success\|warning\|danger\|purple\|dark, `size` sm\|md, `dot`, `icon` | |
| `x-ui.channel-badge` | `platform` (string or `Platform` enum), `variant` pill\|icon\|plain, `size` xs\|sm\|md (+2xs for icon), `label` | |
| `x-ui.avatar` | `name`, `src`, `size` xs\|sm\|md\|lg\|xl, `channel`, `status` online\|away\|offline, `square` | Deterministic colour from name. |
| `x-ui.field` | `label`, `for`, `name` (error lookup), `error`, `hint`, `required`, `optional`, `inline`, `bare` | Wrapper; slot `aside` next to the label. `inline` = label left / control right (settings rows). |
| `x-ui.input` | `name`, `type`, `value`, `id` (=name), `icon`, `label`, `hint`, `optional`, `size` sm\|md | Uses `old()`; sets `aria-invalid` from `$errors`. With `label` it wraps itself in a field. Slot `trailing` (unit/button). |
| `x-ui.textarea` | `name`, `value`, `id`, `rows` (4), `label`, `hint`, `optional`, `autosize`, `counter` (int), `mono` | Pair `counter` with `maxlength`. |
| `x-ui.select` | `name`, `id`, `value`, `options` (value=>label, nested = optgroup), `placeholder`, `label`, `hint`, `optional`, `size` | Or pass `<option>`s in the slot. Enum values are handled. |
| `x-ui.toggle` | `name`, `checked`, `value` ("1"), `unchecked-value` ("0"; `null` = no hidden input), `label`, `description`, `size` sm\|md, `disabled`, `id` | Real checkbox with `role="switch"`: works in plain forms, with `x-model`, keyboard. Auto-submit: `onchange="this.form.requestSubmit()"`. |
| `x-ui.checkbox` | `name`, `value`, `checked`, `label`, `description`, `type` checkbox\|radio, `id`, `disabled` | |
| `x-ui.tabs` | `tabs`, `default`, `hash`, `variant` underline\|pills | Client tabs with `x-ui.tab-panel name="…"` children (mark the default panel with `default` to avoid a flash), or link tabs when items have `href` + `active`. Alpine var `tab`. |
| `x-ui.tab-panel` | `name`, `default` | |
| `x-ui.modal` | `name` (required), `title`, `description`, `icon`, `tone`, `size` sm\|md\|lg\|xl, `show` | Open: `$dispatch('open-modal', 'name')`; close: `$dispatch('close-modal', 'name')` or `close()` inside. Focus trap, Esc, backdrop click, bottom sheet on phones. Slot `footer`. |
| `x-ui.dropdown` | `align` left\|right\|top-left\|top-right, `width` (w-56) | Slots `trigger`, `header`, default items. Arrow-key navigation. |
| `x-ui.dropdown-item` | `href`, `icon`, `danger`, `active`, `type`, `description` | Put `type="submit"` items inside a `<form>`. |
| `x-ui.dropdown-divider` | `label` | Line, or a group label when `label` is set. |
| `x-ui.empty-state` | `icon`, `title`, `description`, `compact`, `tone` | Slot = actions. |
| `x-ui.table` | `hoverable`, `card` (true) | Slots `toolbar`, `head`, `footer`; body rows in the default slot. `th`/`td` styled automatically. |
| `components.ui.pagination` | (paginator view) | `{{ $items->links('components.ui.pagination') }}` |
| `x-ui.filter-bar` | `action`, `search` ('q', null = none), `placeholder`, `reset` | GET form; selects auto-submit; Reset appears when filters are active. Slot `right`. |
| `x-ui.alert` | `tone` info\|brand\|success\|warning\|danger\|neutral, `title`, `icon` (false = none), `dismissible` | Slot `actions`. |
| `x-ui.skeleton` | `lines`, `avatar` | Or size a single block with classes. |
| `x-ui.spinner` | `size` xs\|sm\|md\|lg | currentColor. |
| `x-ui.section-header` | `title`, `description`, `count` | Slot = actions. |
| `x-ui.page-header` | `title`, `description`, `eyebrow`, `back`, `back-label` | Slots `leading`, `meta`, default = actions. |
| `x-ui.copy-button` | `value` or `target` (selector), `label`, `size`, `variant`, `icon-only` | |
| `x-ui.tooltip` | `text`, `position` top\|bottom\|left\|right | CSS only; shows on hover and focus. |
| `x-ui.save-bar` | `label`, `always` (true) | Last child of a settings `<form>`: floating sticky bar with dirty state + Discard. Add `data-warn-unsaved` to the form to warn on leave. |
| `x-ui.code` | `copy` (true) | Escaped code block; wrap Blade in `@verbatim`. |
| `x-ui.logo` | `name`, `wordmark`, `size`, `inverted` | |

## 5. Patterns

### Filters bar + table + pagination

```blade
<div class="space-y-4">
    <x-ui.filter-bar :action="route('admin.contacts.index')" search="q" placeholder="Search name or ID">
        <x-ui.select name="platform" size="sm" class="w-auto" placeholder="All channels"
            :options="['facebook' => 'Messenger', 'instagram' => 'Instagram']" :value="request('platform')" />
    </x-ui.filter-bar>

    <x-ui.table hoverable>
        <x-slot:head>
            <tr><th>Customer</th><th>Channel</th><th class="text-right">Last activity</th></tr>
        </x-slot:head>
        @forelse ($contacts as $contact)
            <tr class="cursor-pointer" onclick="location.href='{{ route('admin.contacts.show', $contact) }}'">
                <td><div class="flex items-center gap-3"><x-ui.avatar :name="$contact->customer_name" :channel="$contact->platform" size="sm" /> <span class="font-medium text-ink">{{ $contact->customer_name }}</span></div></td>
                <td><x-ui.channel-badge :platform="$contact->platform" size="sm" /></td>
                <td class="text-right whitespace-nowrap text-ink-muted">{{ $contact->last_message_at?->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td colspan="3"><x-ui.empty-state compact icon="users" title="No contacts yet" description="People appear here after they message a page." /></td></tr>
        @endforelse
        <x-slot:footer>{{ $contacts->withQueryString()->links('components.ui.pagination') }}</x-slot:footer>
    </x-ui.table>
</div>
```

### Settings form with sticky save bar

```blade
<x-layouts.app title="Bot Studio" width="narrow">
    <form method="POST" action="{{ route('admin.bot-settings.update') }}" data-warn-unsaved class="space-y-6">
        @csrf @method('PUT')
        <x-ui.card title="Behaviour" description="How the assistant talks to customers.">
            <div class="space-y-6">
                <x-ui.input name="bot_name" label="Bot name" :value="$settings->bot_name" required />
                <x-ui.textarea name="system_prompt" label="System prompt" rows="8" autosize :counter="4000" maxlength="4000" :value="$settings->system_prompt" />
                <x-ui.field inline label="Enabled" hint="Reply automatically to new messages">
                    <x-ui.toggle name="enabled" :checked="$settings->enabled" label="Bot replies automatically" />
                </x-ui.field>
            </div>
        </x-ui.card>
        <x-ui.save-bar />
    </form>
</x-layouts.app>
```

Validation errors show inline under each field (by `name`, including `a[b]` to `a.b`) and as a summary alert at the top of the page. Flash `->with('success', '…')` (or `status`, `error`, `warning`, `info`) to show a toast after redirect.

### Destructive action with confirmation

```blade
<x-ui.button variant="danger-soft" icon="trash" x-data x-on:click="$dispatch('open-modal', 'delete-{{ $page->id }}')">Delete</x-ui.button>
<x-ui.modal name="delete-{{ $page->id }}" title="Delete {{ $page->page_name }}?" description="Its conversations are removed too." icon="trash" tone="danger" size="sm">
    <x-slot:footer>
        <x-ui.button x-on:click="close()">Cancel</x-ui.button>
        <form method="POST" action="{{ route('admin.meta-accounts.destroy', $page) }}">@csrf @method('DELETE')
            <x-ui.button type="submit" variant="danger">Delete</x-ui.button>
        </form>
    </x-slot:footer>
</x-ui.modal>
```

### Empty states

Every list needs one. Put `x-ui.empty-state compact` inside the table (`<td colspan>`), or a full `x-ui.empty-state` inside a `:padded="false"` card for whole-page emptiness. Always offer the next action (for example "Connect a page").

### JSON polling / actions with Alpine

`resources/js/app.js` exposes helpers on `window`:

- `api(url, { method, body })`: fetch with session cookies, `Accept: application/json`, `X-CSRF-TOKEN` from `<meta name="csrf-token">` for non-GET, JSON body. Throws `Error` with `.status` / `.data` on non-2xx.
- `toast(message, type = 'success')`: types success\|error\|warning\|info (or `$dispatch('toast', { message, type })`).
- `copyText(text)`.
- Alpine data `poller(url, intervalMs)`: `data`, `error`, `loading`, `refresh()`; pauses while the tab is hidden.

```blade
<div x-data="{
        items: [], lastId: {{ (int) ($messages->last()?->id ?? 0) }}, sending: false,
        async poll() {
            const { data } = await api(@js(route('admin.api.conversations.messages', $conversation)) + '?after_id=' + this.lastId);
            if (data.length) { this.items.push(...data); this.lastId = data[data.length - 1].id; }
        },
        async setBot(enabled) {
            try { await api(@js(route('admin.api.conversations.bot', $conversation)), { method: 'POST', body: { enabled } }); toast(enabled ? 'Bot on' : 'Bot paused'); }
            catch (e) { toast(e.message, 'error'); }
        },
    }"
    x-init="setInterval(() => document.visibilityState === 'visible' && poll(), 5000)">
    <template x-for="m in items" :key="m.id"><p x-text="m.body"></p></template>
</div>
```

Or declaratively: `<div x-data="poller('/admin/api/conversations', 10000)"> <template x-if="data"> … </template> </div>`.

Keep JSON endpoints in the area's route file under `Route::prefix('api')->name('api.')` (see `routes/admin/inbox.php`).

## 6. Routes

`routes/web.php` requires one file per area inside the admin group (`/admin`, names `admin.*`, `auth` + `EnsureUserIsAdmin`):

| File | Area | Route names |
|---|---|---|
| `routes/admin/dashboard.php` | Dashboard | `admin.dashboard` |
| `routes/admin/inbox.php` | Live Chat + JSON API | `admin.conversations.*`, `admin.api.conversations.*` |
| `routes/admin/contacts.php` | Contacts | `admin.contacts.index` (placeholder) |
| `routes/admin/bot.php` | Bot Studio | `admin.bot-settings.*` |
| `routes/admin/automations.php` | Automations | `admin.automations.index` (placeholder) |
| `routes/admin/pages.php` | Pages & Channels | `admin.meta-accounts.*` (incl. connect flow) |
| `routes/admin/system.php` | Settings, webhook log, page switch, style guide | `admin.settings.index`, `admin.webhook-events.*`, `admin.page-switch`, `admin.ui` |

Placeholders use `Route::view(…, 'layouts.coming-soon', [...])`; replace them with a controller when you build the page. Sidebar active states match on route-name patterns (for example Live Chat is active for `admin.conversations.*`, `admin.inbox.*`, `admin.live-chat.*`; Bot Studio for `admin.bot-settings.*`, `admin.bot.*`, `admin.knowledge.*`). Use those prefixes for new routes, or update `$nav` in the layout.

## 7. Checklist before you ship a page

- Uses `<x-layouts.app>` and components; no inline `<style>`; no legacy classes.
- Scoped to `$currentPage` where the data belongs to a page.
- Empty, loading and error states covered; destructive actions confirmed.
- Works at 375px (no horizontal scroll; tables scroll inside their card) and at 1440px.
- Keyboard: every control is reachable and has a visible focus ring; icon buttons have `aria-label`.
- `npm run build` done, `php artisan test` green.
