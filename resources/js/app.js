/*
 | DM Pilot admin — JS entry.
 | Alpine.js (+ focus, collapse) powers every interactive Blade component in
 | resources/views/components/ui. Keep page-specific behaviour in x-data blocks
 | inside the page's Blade view (see docs/UI_GUIDE.md "JSON polling pattern").
 */
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import collapse from '@alpinejs/collapse';

Alpine.plugin(focus);
Alpine.plugin(collapse);

/* ------------------------------------------------------------------ */
/* Helpers available globally for page scripts                         */
/* ------------------------------------------------------------------ */

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

/**
 * fetch() wrapper for the admin JSON API: same-origin session auth, CSRF header,
 * JSON in/out. Throws an Error (with .status and .data) on non-2xx responses.
 *
 *   const { data } = await api('/admin/api/conversations');
 *   await api(url, { method: 'POST', body: { enabled: true } });
 */
async function api(url, { method = 'GET', body, headers = {}, signal } = {}) {
    const res = await fetch(url, {
        method,
        signal,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(method !== 'GET' ? { 'X-CSRF-TOKEN': csrfToken() } : {}),
            ...(body !== undefined && !(body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
            ...headers,
        },
        body: body === undefined ? undefined : body instanceof FormData ? body : JSON.stringify(body),
    });

    const isJson = res.headers.get('content-type')?.includes('application/json');
    const data = isJson ? await res.json() : null;

    if (!res.ok) {
        const error = new Error(data?.message ?? `Request failed (${res.status})`);
        error.status = res.status;
        error.data = data;
        throw error;
    }

    return data;
}

/** Show a toast from anywhere: toast('Saved', 'success'). Types: success | error | info | warning. */
function toast(message, type = 'success', timeout = 4500) {
    window.dispatchEvent(new CustomEvent('toast', { detail: { message, type, timeout } }));
}

/** Copy text to the clipboard with a fallback for non-secure contexts. */
async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch {
        const el = document.createElement('textarea');
        el.value = text;
        el.setAttribute('readonly', '');
        el.style.position = 'fixed';
        el.style.opacity = '0';
        document.body.appendChild(el);
        el.select();
        const ok = document.execCommand('copy');
        el.remove();
        return ok;
    }
}

window.api = api;
window.toast = toast;
window.copyText = copyText;
window.Alpine = Alpine;

/* ------------------------------------------------------------------ */
/* Reusable Alpine data                                                */
/* ------------------------------------------------------------------ */

/** Toast stack rendered once by the app layout. */
Alpine.data('toaster', (initial = []) => ({
    toasts: [],
    nextId: 1,
    init() {
        initial.forEach((t) => this.push(t));
    },
    push({ message, type = 'success', timeout = 4500 }) {
        if (!message) return;
        const id = this.nextId++;
        this.toasts.push({ id, message, type, visible: true });
        if (timeout > 0) setTimeout(() => this.dismiss(id), timeout);
    },
    dismiss(id) {
        const t = this.toasts.find((t) => t.id === id);
        if (t) t.visible = false;
        setTimeout(() => (this.toasts = this.toasts.filter((t) => t.id !== id)), 200);
    },
}));

/**
 * Poll a JSON endpoint on an interval; pauses while the tab is hidden.
 *   x-data="poller('/admin/api/conversations', 10000)"  ->  exposes data, error, loading, refresh()
 */
Alpine.data('poller', (url, interval = 10000) => ({
    data: null,
    error: null,
    loading: false,
    timer: null,
    init() {
        this.refresh();
        this.timer = setInterval(() => document.visibilityState === 'visible' && this.refresh(), interval);
    },
    destroy() {
        clearInterval(this.timer);
    },
    async refresh() {
        this.loading = true;
        try {
            this.data = await api(url);
            this.error = null;
        } catch (e) {
            this.error = e.message;
        } finally {
            this.loading = false;
        }
    },
}));

/** Tracks whether a form has unsaved changes (used by <x-ui.save-bar>). */
Alpine.data('dirtyForm', () => ({
    dirty: false,
    init() {
        const form = this.$root.closest('form') ?? this.$root;
        const mark = () => (this.dirty = true);
        form.addEventListener('input', mark);
        form.addEventListener('change', mark);
        form.addEventListener('submit', () => (this.dirty = false));
        window.addEventListener('beforeunload', (e) => {
            if (this.dirty && form.hasAttribute('data-warn-unsaved')) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    },
    reset() {
        const form = this.$root.closest('form') ?? this.$root;
        form.reset();
        this.dirty = false;
    },
}));

/* ------------------------------------------------------------------ */
/* Submit buttons show a spinner + prevent double submits              */
/* ------------------------------------------------------------------ */

document.addEventListener('submit', (event) => {
    if (event.defaultPrevented) return;
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-loading')) return;
    const button = event.submitter?.classList.contains('ui-btn') ? event.submitter : null;
    // Defer so the submitter's name/value is still included in the submission.
    const buttons = [...form.querySelectorAll('button[type="submit"]:not(:disabled)')];
    setTimeout(() => {
        if (button) button.setAttribute('aria-busy', 'true');
        buttons.forEach((b) => (b.disabled = true));
    }, 0);
    // Safety net for submissions that never navigate away (downloads, target=_blank).
    setTimeout(() => {
        button?.removeAttribute('aria-busy');
        buttons.forEach((b) => (b.disabled = false));
    }, 10000);
});

// Restore buttons when the page comes back from the bfcache (browser back button).
window.addEventListener('pageshow', (event) => {
    if (!event.persisted) return;
    document.querySelectorAll('.ui-btn[aria-busy="true"]').forEach((b) => b.removeAttribute('aria-busy'));
    document.querySelectorAll('form button[type="submit"]:disabled:not([data-disabled])').forEach((b) => (b.disabled = false));
});

Alpine.start();
