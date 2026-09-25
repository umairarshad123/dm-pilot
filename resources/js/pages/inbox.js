/*
 | Live Chat (admin.conversations.index / show): 3-pane inbox.
 |
 | Boots from <script type="application/json" id="live-chat-boot"> (see ConversationController::page) and then
 | talks to the JSON API in routes/admin/inbox.php:
 |   - list      polled every 5s in delta mode (?since=) -> rows are patched in place (scroll + selection kept)
 |   - thread    polled every 3s (?after_id=&since=) -> new messages appended, status changes applied
 |   - actions   reply (optimistic), read, bot / takeover / clear-pause / status, contact PATCH, refresh profile
 |
 | All customer text is rendered with x-text / attribute bindings (never innerHTML).
 | Loaded after app.js has started Alpine, so the root carries x-ignore and is initialised here.
 */

const LIST_POLL_MS = 5000;
const THREAD_POLL_MS = 3000;
const MAX_REPLY_CHARS = 4000;
const PANEL_KEY = 'dmpilot.livechat.contactPanel';
const GROUP_GAP_MS = 5 * 60 * 1000;

const AVATAR_COLORS = [
    'bg-sky-100 text-sky-700', 'bg-violet-100 text-violet-700', 'bg-emerald-100 text-emerald-700',
    'bg-amber-100 text-amber-800', 'bg-rose-100 text-rose-700', 'bg-indigo-100 text-indigo-700',
    'bg-teal-100 text-teal-700', 'bg-fuchsia-100 text-fuchsia-700', 'bg-orange-100 text-orange-700',
    'bg-blue-100 text-blue-700', 'bg-lime-100 text-lime-800', 'bg-cyan-100 text-cyan-800',
];

/* ------------------------------------------------------------------ */
/* Small pure helpers                                                  */
/* ------------------------------------------------------------------ */

const fill = (template, id) => template.replace('__ID__', encodeURIComponent(id));
const ts = (iso) => (iso ? Date.parse(iso) : 0);
const encoder = new TextEncoder();
const byteLength = (s) => encoder.encode(s).length;
const charLength = (s) => [...s].length;
const isRealId = (id) => typeof id === 'number';

function hash(str) {
    let h = 0;
    for (let i = 0; i < str.length; i++) h = (Math.imul(31, h) + str.charCodeAt(i)) | 0;
    return Math.abs(h);
}

function dayKey(d) {
    return `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;
}

function dayLabel(iso) {
    const d = new Date(iso);
    const today = new Date();
    const yesterday = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 1);
    if (dayKey(d) === dayKey(today)) return 'Today';
    if (dayKey(d) === dayKey(yesterday)) return 'Yesterday';
    const diffDays = (today - d) / 86400000;
    if (diffDays < 6) return d.toLocaleDateString(undefined, { weekday: 'long' });
    return d.toLocaleDateString(undefined, {
        weekday: 'short', month: 'short', day: 'numeric',
        ...(d.getFullYear() !== today.getFullYear() ? { year: 'numeric' } : {}),
    });
}

function clock(iso) {
    return iso ? new Date(iso).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' }) : '';
}

function fullDate(iso) {
    return iso ? new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '';
}

/** Compact list time: now, 5m, 3h, Yesterday, Mon, Sep 12, 12/03/24 */
function shortAgo(iso, now) {
    if (!iso) return '';
    const t = ts(iso);
    const diff = Math.max(0, now - t);
    const d = new Date(t);
    const today = new Date(now);
    if (diff < 60000) return 'now';
    if (diff < 3600000) return `${Math.floor(diff / 60000)}m`;
    if (dayKey(d) === dayKey(today)) return `${Math.floor(diff / 3600000)}h`;
    const yesterday = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 1);
    if (dayKey(d) === dayKey(yesterday)) return 'Yesterday';
    if (diff < 6 * 86400000) return d.toLocaleDateString(undefined, { weekday: 'short' });
    if (d.getFullYear() === today.getFullYear()) return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    return d.toLocaleDateString(undefined, { year: '2-digit', month: 'numeric', day: 'numeric' });
}

const rtf = typeof Intl !== 'undefined' && Intl.RelativeTimeFormat ? new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' }) : null;

function longAgo(iso, now) {
    if (!iso) return '—';
    const diff = (ts(iso) - now) / 1000;
    const abs = Math.abs(diff);
    if (!rtf) return fullDate(iso);
    if (abs < 60) return rtf.format(Math.round(diff), 'second');
    if (abs < 3600) return rtf.format(Math.round(diff / 60), 'minute');
    if (abs < 86400) return rtf.format(Math.round(diff / 3600), 'hour');
    if (abs < 86400 * 30) return rtf.format(Math.round(diff / 86400), 'day');
    return fullDate(iso);
}

/** 42m · 1h 5m · 30s */
function duration(ms) {
    const s = Math.max(0, Math.ceil(ms / 1000));
    if (s < 60) return `${s}s`;
    const m = Math.ceil(s / 60);
    if (m < 60) return `${m}m`;
    const h = Math.floor(m / 60);
    const rest = m % 60;
    if (h < 48) return rest ? `${h}h ${rest}m` : `${h}h`;
    return `${Math.round(h / 24)}d`;
}

const URL_RE = /((?:https?:\/\/|www\.)[^\s<>"']+)/gi;

/** Split text into [{ text, href? }] parts; only http(s) links become hrefs. */
function linkify(text) {
    const parts = [];
    const str = String(text ?? '');
    let last = 0;
    for (const match of str.matchAll(URL_RE)) {
        let url = match[0];
        const trailing = url.match(/[.,!?;:)\]]+$/);
        if (trailing) url = url.slice(0, -trailing[0].length);
        const start = match.index;
        if (start > last) parts.push({ text: str.slice(last, start) });
        const href = /^https?:\/\//i.test(url) ? url : `https://${url}`;
        parts.push({ text: url, href });
        last = start + url.length;
    }
    if (last < str.length) parts.push({ text: str.slice(last) });
    return parts;
}

/** history.pushState / replaceState that never throws (sandboxed frames, odd origins). */
function navigate(url, replace = false) {
    try {
        history[replace ? 'replaceState' : 'pushState']({ lc: true }, '', url);
    } catch {
        /* URL sync is a nicety */
    }
}

const normalizeTag = (t) => String(t ?? '').trim().replace(/\s+/g, ' ').toLowerCase().slice(0, 50);

/* ------------------------------------------------------------------ */
/* The component                                                        */
/* ------------------------------------------------------------------ */

function liveChat(boot) {
    const wideQuery = window.matchMedia('(min-width: 1380px)');
    let storedPanel = true;
    try {
        storedPanel = localStorage.getItem(PANEL_KEY) !== '0';
    } catch {
        /* storage unavailable */
    }

    return {
        routes: boot.routes,
        me: boot.me,
        hasPages: boot.has_pages,
        pageLabel: boot.page_label,
        maxChars: MAX_REPLY_CHARS,

        // List
        items: boot.list.data,
        counts: boot.list.counts ?? {},
        listPage: boot.list.meta.current_page,
        listLastPage: boot.list.meta.last_page,
        listSince: boot.list.server_time,
        view: boot.filters.view || 'all',
        platform: boot.filters.platform || '',
        q: boot.filters.q || '',
        extra: boot.filters.extra || {},
        listLoading: false,
        loadingMore: false,
        cursor: -1,
        listToken: 0,

        // Thread
        selectedId: boot.selected?.id ?? null,
        conv: boot.selected,
        messages: boot.messages ?? [],
        hasOlder: !!boot.has_older,
        loadingOlder: false,
        threadLoading: false,
        threadError: null,
        msgSince: boot.list.server_time,
        openToken: 0,
        atBottom: true,
        unseen: 0,
        tempSeq: 0,
        busy: 0, // in-flight actions / contact saves: polled state must not overwrite optimistic edits
        stateRev: 0, // bumped on every local edit; a poll that started before it is stale

        // Composer
        draft: '',
        drafts: {},

        // Layout
        pane: boot.selected ? 'thread' : 'list',
        wide: wideQuery.matches,
        contactOpen: storedPanel,
        sheetOpen: false,
        lightbox: null,

        // Clocks
        now: Date.now(),
        minuteNow: Date.now(),

        // Connection
        offline: false,
        sessionExpired: false,
        failures: 0,

        // Contact panel
        allTags: boot.tags ?? [],
        form: { customer_name: '', email: '', phone: '', notes: '' },
        editingName: false,
        errors: {},
        saving: {},
        saved: {},
        tagInput: '',
        tagOpen: false,
        tagIndex: 0,
        brokenImages: {},
        baseTitle: document.title,

        init() {
            this.syncForm();
            if (this.conv) {
                this.markReadLocal(this.conv.id);
                this.cursor = this.items.findIndex((c) => c.id === this.selectedId);
                this.setTitle();
                this.$nextTick(() => this.scrollToBottom(true));
            }

            wideQuery.addEventListener?.('change', (e) => (this.wide = e.matches));

            setInterval(() => (this.now = Date.now()), 1000);
            setInterval(() => (this.minuteNow = Date.now()), 30000);
            setInterval(() => this.pollList(), LIST_POLL_MS);
            setInterval(() => this.pollThread(), THREAD_POLL_MS);

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    this.now = this.minuteNow = Date.now();
                    this.pollList();
                    this.pollThread();
                }
            });

            window.addEventListener('popstate', () => this.syncFromLocation());
            navigate(location.href, true);
        },

        /* ---------------- URLs / navigation ---------------- */

        listParams(extra = {}) {
            const p = new URLSearchParams();
            if (this.view && this.view !== 'all') p.set('view', this.view);
            if (this.platform) p.set('platform', this.platform);
            if (this.q.trim()) p.set('q', this.q.trim());
            Object.entries(this.extra).forEach(([k, v]) => p.set(k, v));
            Object.entries(extra).forEach(([k, v]) => p.set(k, v));
            return p;
        },

        pageUrl(id = null) {
            const qs = this.listParams().toString();
            return (id ? fill(this.routes.show, id) : this.routes.index) + (qs ? `?${qs}` : '');
        },

        syncFromLocation() {
            const pattern = new URL(this.routes.show, location.origin).pathname.replace('__ID__', '(\\d+)');
            const match = location.pathname.match(new RegExp(`^${pattern}$`));
            if (match) {
                this.open(Number(match[1]), { push: false });
            } else {
                this.pane = 'list';
            }
        },

        setTitle() {
            document.title = this.conv ? `${this.conv.name} · ${this.baseTitle}` : this.baseTitle;
        },

        /* ---------------- Formatting (used by the view) ---------------- */

        shortAgo(iso) {
            return shortAgo(iso, this.minuteNow);
        },
        longAgo(iso) {
            return longAgo(iso, this.minuteNow);
        },
        clock,
        fullDate,
        linkify,
        avatarColor(name) {
            return AVATAR_COLORS[hash(String(name ?? '?').toLowerCase()) % AVATAR_COLORS.length];
        },
        imageOk(url) {
            return !!url && !this.brokenImages[url];
        },
        imageFailed(url) {
            if (url) this.brokenImages[url] = true;
        },
        number(n) {
            return Number(n ?? 0).toLocaleString();
        },

        /* ---------------- List ---------------- */

        setView(view) {
            if (this.view === view) return;
            this.view = view;
            this.reloadList();
        },

        setPlatform(platform) {
            this.platform = platform;
            this.reloadList();
        },

        async reloadList() {
            const token = ++this.listToken;
            this.listLoading = true;
            navigate(this.pageUrl(this.pane === 'thread' ? this.selectedId : null), true);
            try {
                const res = await window.api(`${this.routes.list}?${this.listParams()}`);
                if (token !== this.listToken) return;
                this.items = res.data;
                this.counts = res.counts ?? this.counts;
                this.listPage = res.meta.current_page;
                this.listLastPage = res.meta.last_page;
                this.listSince = res.server_time;
                this.cursor = this.items.findIndex((c) => c.id === this.selectedId);
                this.$refs.list?.scrollTo({ top: 0 });
                this.connectionOk();
            } catch (e) {
                this.connectionFailed(e);
            } finally {
                if (token === this.listToken) this.listLoading = false;
            }
        },

        async loadMore() {
            if (this.loadingMore || this.listLoading || this.listPage >= this.listLastPage) return;
            this.loadingMore = true;
            const token = this.listToken;
            try {
                const res = await window.api(`${this.routes.list}?${this.listParams({ page: this.listPage + 1 })}`);
                if (token !== this.listToken) return;
                const known = new Set(this.items.map((c) => c.id));
                this.items.push(...res.data.filter((c) => !known.has(c.id)));
                this.listPage = res.meta.current_page;
                this.listLastPage = res.meta.last_page;
            } catch (e) {
                this.connectionFailed(e);
            } finally {
                this.loadingMore = false;
            }
        },

        onListScroll(el) {
            if (el.scrollHeight - el.scrollTop - el.clientHeight < 240) this.loadMore();
        },

        async pollList() {
            if (document.visibilityState !== 'visible' || this.listLoading || this.sessionExpired) return;
            const token = this.listToken;
            try {
                const res = await window.api(`${this.routes.list}?${this.listParams({ since: this.listSince })}`);
                if (token !== this.listToken) return;
                this.listSince = res.server_time;
                this.counts = res.counts ?? this.counts;
                const oldest = this.items.length ? ts(this.items[this.items.length - 1].last_message_at) : 0;
                const complete = this.listPage >= this.listLastPage;

                for (const row of res.data) {
                    if (row.id === this.selectedId && row.unread_count > 0 && this.pane === 'thread' && this.threadVisible()) {
                        row.unread_count = 0; // we are looking at it; the thread poll marks it read
                    }
                    if (row.matches || row.id === this.selectedId) {
                        const exists = this.items.some((c) => c.id === row.id);
                        if (exists || complete || ts(row.last_message_at) >= oldest) this.upsertRow(row, true);
                    } else {
                        this.items = this.items.filter((c) => c.id !== row.id);
                    }
                }
                if (res.data.length) this.sortItems();
                this.connectionOk();
            } catch (e) {
                this.connectionFailed(e);
            }
        },

        upsertRow(row, insert = false) {
            const existing = this.items.find((c) => c.id === row.id);
            const { matches, ...data } = row;
            if (existing) Object.assign(existing, data);
            else if (insert) this.items.push(data);
        },

        sortItems() {
            const selected = this.items[this.cursor]?.id;
            this.items.sort((a, b) => ts(b.last_message_at) - ts(a.last_message_at) || b.id - a.id);
            if (selected !== undefined) this.cursor = this.items.findIndex((c) => c.id === selected);
        },

        markReadLocal(id) {
            const row = this.items.find((c) => c.id === id);
            if (row && row.unread_count > 0) {
                row.unread_count = 0;
                if (this.counts.unread > 0) this.counts.unread--;
            }
            if (this.conv?.id === id) this.conv.unread_count = 0;
        },

        async markRead(id = this.selectedId) {
            if (!id) return;
            this.markReadLocal(id);
            try {
                await window.api(fill(this.routes.read, id), { method: 'POST' });
            } catch {
                /* not critical */
            }
        },

        emptyTitle() {
            if (this.q.trim()) return 'No matches';
            return {
                unread: 'All caught up',
                human: 'Nobody is waiting for a human',
                bot: 'No conversations handled by the bot',
                closed: 'No closed conversations',
            }[this.view] ?? 'No conversations yet';
        },

        emptyText() {
            if (this.q.trim()) return `Nothing matches “${this.q.trim()}”. Try a name, @username, email or phone number.`;
            return {
                unread: 'Every message has been read.',
                human: 'Conversations appear here when you take over, the bot is paused, or a reply fails.',
                bot: 'Conversations where the bot is on and replying show up here.',
                closed: 'Closed conversations show up here.',
            }[this.view] ?? 'Send a DM to your Page to see it here.';
        },

        /* ---------------- Keyboard ---------------- */

        onKey(e) {
            if (e.key === 'Escape') {
                if (this.lightbox) return (this.lightbox = null);
                if (this.sheetOpen) return (this.sheetOpen = false);
            }
            const t = e.target;
            if (t.closest?.('input, textarea, select, [contenteditable="true"], [role="menu"], [role="dialog"]')) return;
            if (e.metaKey || e.ctrlKey || e.altKey) return;

            if (e.key === '/') {
                e.preventDefault();
                this.$refs.search?.focus();
                return;
            }

            const inList = t === document.body || t.closest?.('[data-lc-list]');
            if (!inList || !['ArrowDown', 'ArrowUp', 'Enter'].includes(e.key) || !this.items.length) return;

            if (e.key === 'Enter') {
                if (t.closest?.('[data-lc-row]')) return; // the focused row button handles Enter itself
                if (this.cursor >= 0) this.open(this.items[this.cursor].id);
                return;
            }

            e.preventDefault();
            const dir = e.key === 'ArrowDown' ? 1 : -1;
            const current = this.cursor >= 0 ? this.cursor : this.items.findIndex((c) => c.id === this.selectedId);
            this.cursor = Math.min(this.items.length - 1, Math.max(0, current + dir));
            const el = document.getElementById(`lc-row-${this.items[this.cursor].id}`);
            el?.focus({ preventScroll: true });
            el?.scrollIntoView({ block: 'nearest' });
            if (this.cursor >= this.items.length - 3) this.loadMore();
        },

        /* ---------------- Thread ---------------- */

        async open(id, { push = true } = {}) {
            this.cursor = this.items.findIndex((c) => c.id === id);
            this.pane = 'thread';
            this.sheetOpen = false;
            if (push) navigate(this.pageUrl(id));

            if (id === this.selectedId && this.conv && !this.threadError) {
                this.focusComposer();
                return;
            }

            if (this.selectedId) this.drafts[this.selectedId] = this.draft;
            const row = this.items.find((c) => c.id === id);
            const token = ++this.openToken;

            this.selectedId = id;
            this.conv = row ? { ...row, _partial: true } : null;
            this.messages = [];
            this.hasOlder = false;
            this.threadError = null;
            this.threadLoading = true;
            this.unseen = 0;
            this.atBottom = true;
            this.editingName = false;
            this.draft = this.drafts[id] ?? '';
            this.syncForm();
            this.setTitle();
            this.$nextTick(() => this.autosize());

            try {
                const res = await window.api(`${fill(this.routes.messages, id)}?limit=60`);
                if (token !== this.openToken) return;
                this.conv = res.conversation;
                this.messages = res.data;
                this.hasOlder = res.has_more;
                this.msgSince = res.server_time;
                this.syncForm();
                this.setTitle();
                this.upsertRow(res.conversation);
                this.scrollToBottom(true);
                this.markRead(id);
                this.focusComposer();
            } catch (e) {
                if (token !== this.openToken) return;
                this.threadError = e.status === 404 ? 'This conversation no longer exists.' : e.message;
            } finally {
                if (token === this.openToken) this.threadLoading = false;
            }
        },

        retryThread() {
            const id = this.selectedId;
            this.selectedId = null;
            this.open(id, { push: false });
        },

        backToList() {
            this.pane = 'list';
            navigate(this.pageUrl());
            this.$nextTick(() => document.getElementById(`lc-row-${this.selectedId}`)?.focus({ preventScroll: false }));
        },

        focusComposer() {
            if (window.matchMedia('(pointer: fine)').matches) this.$nextTick(() => this.$refs.composer?.focus({ preventScroll: true }));
        },

        threadVisible() {
            return document.visibilityState === 'visible' && (this.pane === 'thread' || window.matchMedia('(min-width: 768px)').matches);
        },

        lastRealId() {
            for (let i = this.messages.length - 1; i >= 0; i--) {
                if (isRealId(this.messages[i].id)) return this.messages[i].id;
            }
            return 0;
        },

        async pollThread() {
            const id = this.selectedId;
            if (!id || this.threadLoading || this.threadError || document.visibilityState !== 'visible' || this.sessionExpired) return;
            const rev = this.stateRev;
            try {
                const res = await window.api(`${fill(this.routes.messages, id)}?after_id=${this.lastRealId()}&since=${encodeURIComponent(this.msgSince)}`);
                if (id !== this.selectedId || this.threadLoading) return;
                this.msgSince = res.server_time;
                if (!this.busy && rev === this.stateRev) this.conv = { ...this.conv, ...res.conversation };
                const { added, incoming } = this.mergeMessages(res.data);
                if (incoming && this.threadVisible()) this.markRead(id);
                if (added) {
                    if (this.atBottom) this.scrollToBottom();
                    else this.unseen += added;
                }
                this.connectionOk();
            } catch (e) {
                if (e.status === 404) this.threadError = 'This conversation no longer exists.';
                else this.connectionFailed(e);
            }
        },

        /** Upsert server messages by id; returns how many were new and whether any came from the customer. */
        mergeMessages(list) {
            let added = 0;
            let incoming = false;
            for (const m of list) {
                const existing = this.messages.find((x) => x.id === m.id);
                if (existing) {
                    Object.assign(existing, m);
                    continue;
                }
                // Our own reply is still in flight: its server row is merged when the send returns.
                const inFlight = m.sender_type === 'human' && this.messages.some((x) => x.temp && x.status === 'sending' && x.body === m.body);
                if (inFlight) continue;
                this.messages.push(m);
                added++;
                if (m.direction === 'incoming') incoming = true;
            }
            if (added) this.sortMessages();
            return { added, incoming };
        },

        sortMessages() {
            const real = this.messages.filter((m) => isRealId(m.id)).sort((a, b) => a.id - b.id);
            const temp = this.messages.filter((m) => !isRealId(m.id));
            this.messages = [...real, ...temp];
        },

        async loadOlder() {
            if (this.loadingOlder || !this.hasOlder || !this.selectedId) return;
            const first = this.messages.find((m) => isRealId(m.id));
            if (!first) return;
            const id = this.selectedId;
            const el = this.$refs.thread;
            this.loadingOlder = true;
            try {
                const res = await window.api(`${fill(this.routes.messages, id)}?before_id=${first.id}&limit=60`);
                if (id !== this.selectedId) return;
                const prevHeight = el.scrollHeight;
                const prevTop = el.scrollTop;
                const known = new Set(this.messages.map((m) => m.id));
                this.messages = [...res.data.filter((m) => !known.has(m.id)), ...this.messages];
                this.hasOlder = res.has_more;
                this.$nextTick(() => (el.scrollTop = prevTop + (el.scrollHeight - prevHeight)));
            } catch (e) {
                window.toast(e.message, 'error');
            } finally {
                this.loadingOlder = false;
            }
        },

        onThreadScroll() {
            const el = this.$refs.thread;
            if (!el) return;
            this.atBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 80;
            if (this.atBottom) this.unseen = 0;
        },

        scrollToBottom(instant = false) {
            this.$nextTick(() => {
                const el = this.$refs.thread;
                if (!el) return;
                el.scrollTo({ top: el.scrollHeight, behavior: instant ? 'auto' : 'smooth' });
                this.atBottom = true;
                this.unseen = 0;
            });
        },

        onMediaLoad() {
            if (this.atBottom) this.scrollToBottom(true);
        },

        /** Messages + day separators, with grouping flags for bubble shapes. */
        get threadRows() {
            const rows = [];
            let prev = null;
            let prevDay = null;
            const list = this.messages;
            for (let i = 0; i < list.length; i++) {
                const m = list[i];
                const at = m.at ?? m.created_at;
                const key = at ? dayKey(new Date(at)) : prevDay;
                if (key !== prevDay) {
                    rows.push({ kind: 'day', key: `day-${key}-${m.id}`, label: at ? dayLabel(at) : '' });
                    prevDay = key;
                    prev = null;
                }
                const next = list[i + 1];
                const same = (a, b) => a && b && a.sender_type === b.sender_type && a.source === b.source
                    && Math.abs(ts(b.at) - ts(a.at)) < GROUP_GAP_MS
                    && dayKey(new Date(a.at ?? 0)) === dayKey(new Date(b.at ?? 0));
                const first = !same(prev, m);
                const last = !same(m, next) || m.status === 'failed';
                rows.push({ kind: 'msg', key: `m-${m.id}`, m, first, last, out: m.direction === 'outgoing' });
                prev = m.status === 'failed' ? null : m;
            }
            return rows;
        },

        /* ---------------- Composer ---------------- */

        get limit() {
            return this.conv?.char_limit || 2000;
        },
        get unit() {
            return this.conv?.char_unit === 'bytes' ? 'bytes' : 'chars';
        },
        get used() {
            return this.unit === 'bytes' ? byteLength(this.draft) : charLength(this.draft);
        },
        get parts() {
            return Math.max(1, Math.ceil(this.used / this.limit));
        },
        get tooLong() {
            return this.draft.length > MAX_REPLY_CHARS;
        },
        get canSend() {
            return !!this.conv && this.draft.trim() !== '' && !this.tooLong && !this.threadLoading;
        },
        get windowOpen() {
            const last = ts(this.conv?.last_customer_message_at);
            return !!last && this.now - last < 24 * 3600 * 1000;
        },
        get windowLeftMs() {
            return ts(this.conv?.last_customer_message_at) + 24 * 3600 * 1000 - this.now;
        },
        get pausedMs() {
            return this.conv?.bot_paused_until ? ts(this.conv.bot_paused_until) - this.now : 0;
        },
        get paused() {
            return this.pausedMs > 0;
        },
        get botState() {
            const c = this.conv;
            if (!c) return 'none';
            if (c.status === 'closed') return 'closed';
            if (c.human_takeover) return 'takeover';
            if (!c.bot_enabled) return 'off';
            if (this.paused) return 'paused';
            return 'active';
        },
        duration,

        autosize() {
            const el = this.$refs.composer;
            if (!el) return;
            el.style.height = 'auto';
            el.style.height = `${Math.min(el.scrollHeight, 208)}px`;
        },

        onComposerKey(e) {
            if (e.key !== 'Enter' || e.shiftKey || e.isComposing || e.keyCode === 229) return;
            e.preventDefault();
            this.send();
        },

        send() {
            if (!this.canSend) {
                if (this.tooLong) window.toast(`Messages can be at most ${MAX_REPLY_CHARS.toLocaleString()} characters.`, 'error');
                return;
            }
            const text = this.draft.trim();
            this.draft = '';
            this.drafts[this.selectedId] = '';
            this.$nextTick(() => this.autosize());
            this.deliver(text);
        },

        async deliver(text) {
            const convId = this.selectedId;
            const tempId = `tmp-${++this.tempSeq}`;
            const at = new Date().toISOString();
            this.messages.push({
                id: tempId, temp: true, direction: 'outgoing', sender_type: 'human', sender_label: 'You', source: 'admin',
                body: text, attachments: [], status: 'sending', error: null, error_hint: null, at,
            });
            this.scrollToBottom();

            const row = this.items.find((c) => c.id === convId);
            if (row) {
                Object.assign(row, { excerpt: text.replace(/\s+/g, ' ').slice(0, 90), last_sender: 'human', last_message_at: at, unread_count: 0 });
                this.sortItems();
            }

            const settle = (real, fallback) => {
                if (convId !== this.selectedId) return;
                const idx = this.messages.findIndex((m) => m.id === tempId);
                if (idx === -1) return;
                if (real && this.messages.some((m) => m.id === real.id)) {
                    this.messages.splice(idx, 1);
                    Object.assign(this.messages.find((m) => m.id === real.id), real);
                } else if (real) {
                    this.messages.splice(idx, 1, real);
                } else {
                    Object.assign(this.messages[idx], fallback);
                }
                this.sortMessages();
            };

            try {
                const res = await window.api(fill(this.routes.reply, convId), { method: 'POST', body: { text } });
                settle(res.data, { status: 'sent' });
                this.applyConversation(res.conversation);
            } catch (e) {
                const message = e.data?.errors?.text?.[0] ?? e.data?.message ?? e.message;
                settle(e.data?.data ?? null, { status: 'failed', error: message, error_hint: message, retryText: text });
                if (e.data?.conversation) this.applyConversation(e.data.conversation);
                if (e.status === 419) this.sessionExpired = true;
                window.toast(`Not delivered: ${message}`, 'error', 7000);
            }
        },

        retry(m) {
            const text = m.body ?? m.retryText;
            if (!text) return;
            if (!isRealId(m.id)) this.messages = this.messages.filter((x) => x.id !== m.id);
            else m.retried = true;
            this.deliver(text);
        },

        /* ---------------- Conversation actions ---------------- */

        applyConversation(data) {
            if (!data) return;
            if (data.id === this.selectedId) this.conv = { ...this.conv, ...data };
            this.upsertRow(data);
        },

        async action(route, body, optimistic, success) {
            const id = this.selectedId;
            const previous = { ...this.conv };
            Object.assign(this.conv, optimistic);
            this.busy++;
            this.stateRev++;
            try {
                const res = await window.api(fill(route, id), { method: 'POST', body });
                this.applyConversation(res.data);
                if (success) window.toast(success);
            } catch (e) {
                if (id === this.selectedId) this.conv = previous;
                window.toast(e.data?.message ?? e.message, 'error');
            } finally {
                this.busy--;
            }
        },

        setBot(enabled) {
            return this.action(this.routes.bot, { enabled }, { bot_enabled: enabled },
                enabled ? 'Bot is on for this conversation' : 'Bot is off for this conversation');
        },

        takeOver() {
            return this.action(this.routes.takeover, { enabled: true }, { human_takeover: true },
                'You are handling this conversation. The bot stays silent.');
        },

        handBack() {
            return this.action(this.routes.takeover, { enabled: false }, { human_takeover: false, bot_paused_until: null },
                'Handed back to the bot');
        },

        clearPause() {
            return this.action(this.routes.clear_pause, undefined, { bot_paused_until: null }, 'Pause cleared. The bot can reply again.');
        },

        setStatus(status) {
            return this.action(this.routes.status, { status }, { status },
                status === 'closed' ? 'Conversation closed' : 'Conversation reopened');
        },

        async copyId() {
            if (!this.conv) return;
            const ok = await window.copyText(this.conv.external_user_id);
            window.toast(ok ? 'Customer ID copied' : 'Could not copy', ok ? 'success' : 'error');
        },

        /* ---------------- Contact panel ---------------- */

        toggleContact() {
            if (this.wide) {
                this.contactOpen = !this.contactOpen;
                try {
                    localStorage.setItem(PANEL_KEY, this.contactOpen ? '1' : '0');
                } catch {
                    /* ignore */
                }
            } else {
                this.sheetOpen = !this.sheetOpen;
            }
        },

        get panelVisible() {
            return !!this.conv && (this.wide ? this.contactOpen : this.sheetOpen);
        },

        syncForm() {
            const c = this.conv ?? {};
            this.form = { customer_name: c.customer_name ?? '', email: c.email ?? '', phone: c.phone ?? '', notes: c.notes ?? '' };
            this.errors = {};
            this.saving = {};
            this.saved = {};
        },

        startEditName() {
            this.form.customer_name = this.conv?.customer_name ?? '';
            this.editingName = true;
            this.$nextTick(() => this.$refs.nameInput?.select());
        },

        async saveName() {
            if (!this.editingName) return;
            this.editingName = false;
            if ((this.form.customer_name ?? '').trim() === (this.conv?.customer_name ?? '')) return;
            await this.saveField('customer_name', this.form.customer_name.trim());
        },

        saveIfChanged(field) {
            const value = (this.form[field] ?? '').trim();
            if (value === (this.conv?.[field] ?? '')) return;
            this.saveField(field, value);
        },

        async saveField(field, value) {
            const id = this.selectedId;
            if (!id) return;
            this.saving[field] = true;
            this.errors[field] = null;
            this.busy++;
            this.stateRev++;
            try {
                const res = await window.api(fill(this.routes.contact, id), { method: 'PATCH', body: { [field]: value } });
                if (id !== this.selectedId) return;
                this.conv = { ...this.conv, ...res.data };
                this.upsertRow(res.data);
                this.allTags = res.tags ?? this.allTags;
                if (field in this.form && field !== 'notes') this.form[field] = res.data[field] ?? '';
                this.saved[field] = true;
                setTimeout(() => (this.saved[field] = false), 2000);
            } catch (e) {
                if (id !== this.selectedId) return;
                const errors = e.data?.errors ?? {};
                const key = Object.keys(errors).find((k) => k === field || k.startsWith(`${field}.`));
                this.errors[field] = key ? errors[key][0] : (e.data?.message ?? e.message);
                if (!key) window.toast(this.errors[field], 'error');
            } finally {
                this.saving[field] = false;
                this.busy--;
            }
        },

        notesTimer: null,
        queueNotes() {
            clearTimeout(this.notesTimer);
            this.notesTimer = setTimeout(() => this.saveIfChanged('notes'), 1500);
        },
        flushNotes() {
            clearTimeout(this.notesTimer);
            this.saveIfChanged('notes');
        },

        setStage(value) {
            if (!this.conv || this.conv.lead_stage === value) return;
            this.conv.lead_stage = value;
            this.saveField('lead_stage', value);
        },

        get tagSuggestions() {
            const q = normalizeTag(this.tagInput);
            const current = this.conv?.tags ?? [];
            return this.allTags.filter((t) => !current.includes(t) && (!q || t.includes(q))).slice(0, 8);
        },

        addTag(raw = null) {
            const tag = normalizeTag(raw ?? (this.tagOpen && this.tagSuggestions[this.tagIndex] && this.tagInput.trim() !== '' && this.tagSuggestions[this.tagIndex].startsWith(normalizeTag(this.tagInput)) ? this.tagSuggestions[this.tagIndex] : this.tagInput));
            this.tagInput = '';
            this.tagIndex = 0;
            if (!tag || !this.conv) return;
            const tags = this.conv.tags ?? [];
            if (tags.includes(tag)) return;
            this.conv.tags = [...tags, tag];
            this.saveField('tags', this.conv.tags);
        },

        removeTag(tag) {
            if (!this.conv) return;
            this.conv.tags = (this.conv.tags ?? []).filter((t) => t !== tag);
            this.saveField('tags', this.conv.tags);
        },

        onTagKey(e) {
            const list = this.tagSuggestions;
            if (e.key === 'Enter' || e.key === ',' || e.key === 'Tab') {
                if (e.key === 'Tab' && this.tagInput.trim() === '') return;
                e.preventDefault();
                this.addTag();
            } else if (e.key === 'Backspace' && this.tagInput === '' && this.conv?.tags?.length) {
                this.removeTag(this.conv.tags[this.conv.tags.length - 1]);
            } else if (e.key === 'ArrowDown' && list.length) {
                e.preventDefault();
                this.tagOpen = true;
                this.tagIndex = (this.tagIndex + 1) % list.length;
            } else if (e.key === 'ArrowUp' && list.length) {
                e.preventDefault();
                this.tagIndex = (this.tagIndex - 1 + list.length) % list.length;
            } else if (e.key === 'Escape') {
                e.stopPropagation();
                this.tagOpen = false;
            }
        },

        async refreshProfile() {
            const id = this.selectedId;
            if (!id) return;
            try {
                const res = await window.api(fill(this.routes.refresh_profile, id), { method: 'POST' });
                window.toast(res.message ?? 'Profile refresh requested', 'info');
                setTimeout(async () => {
                    if (id !== this.selectedId) return;
                    try {
                        const detail = await window.api(fill(this.routes.detail, id));
                        this.applyConversation(detail.data);
                    } catch {
                        /* next poll catches up */
                    }
                }, 6000);
            } catch (e) {
                window.toast(e.data?.message ?? e.message, 'error');
            }
        },

        contactsUrl() {
            return this.conv ? fill(this.routes.contacts, this.conv.id) : this.routes.contacts;
        },

        /* ---------------- Connection state ---------------- */

        connectionOk() {
            this.failures = 0;
            this.offline = false;
        },

        connectionFailed(e) {
            if (e?.status === 401 || e?.status === 419) {
                this.sessionExpired = true;
                return;
            }
            this.failures++;
            if (this.failures >= 2) this.offline = true;
        },
    };
}

/* ------------------------------------------------------------------ */
/* Boot                                                                 */
/* ------------------------------------------------------------------ */

function register() {
    const bootEl = document.getElementById('live-chat-boot');
    const template = document.getElementById('live-chat-template');
    const mount = document.querySelector('[data-live-chat-mount]');
    if (!bootEl || !template || !mount || !window.Alpine) return;

    const boot = JSON.parse(bootEl.textContent);
    window.Alpine.data('liveChat', () => liveChat(boot));

    // Swap the skeleton for the real markup. Alpine (already started by app.js) initialises
    // added nodes through its mutation observer; initTree() covers the not-yet-started case.
    const root = template.content.firstElementChild.cloneNode(true);
    mount.replaceWith(root);
    if (document.body._x_dataStack && !root._x_dataStack) queueMicrotask(() => root._x_dataStack || window.Alpine.initTree(root));
}

register();
