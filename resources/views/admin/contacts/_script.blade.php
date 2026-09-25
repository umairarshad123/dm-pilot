{{--
    Alpine component for the Contacts page (list + board + drawer + bulk actions).
    Classic inline script: it runs during parsing, so the alpine:init listener is registered before app.js
    (a deferred module) starts Alpine. Uses window.api / window.toast from resources/js/app.js.
--}}
<script>
@verbatim
document.addEventListener('alpine:init', () => {
    const FORM_KEYS = ['customer_name', 'first_name', 'last_name', 'email', 'phone', 'lead_stage', 'tags', 'notes'];
    const normTag = (raw) => String(raw ?? '').trim().replace(/\s+/g, ' ').toLowerCase().slice(0, 50);
    const firstError = (e) => {
        const errors = e?.data?.errors;
        return errors ? Object.values(errors)[0]?.[0] : e?.message;
    };

    window.Alpine.data('contactsPage', (config) => ({
        view: config.view,
        stages: config.stages,
        allTags: config.tags ?? [],
        urls: config.urls,
        filters: config.filters ?? {},

        // Selection (list view)
        selected: [],
        allMatching: false,
        pageIds: config.pageIds ?? [],
        total: config.total ?? 0,

        // Drawer
        drawer: false,
        loading: false,
        contact: null,
        form: {},
        original: '{}',
        errors: {},
        saving: false,
        refreshing: false,
        changed: false,
        tagDraft: '',
        tagOpen: false,
        deleteConfirm: '',
        deleting: false,
        seq: 0,

        // Bulk bar
        bulkMenu: null,
        bulkTag: '',
        bulkBusy: false,
        bulkConfirm: '',

        // Board
        columns: config.board ?? [],
        drag: null,
        over: null,

        init() {
            if (config.initial) this.show(config.initial);

            window.addEventListener('popstate', () => {
                const id = Number(new URL(location.href).searchParams.get('contact'));
                id ? this.open(id, false) : this.close(false, true);
            });
        },

        stage(value) {
            return this.stages[value] ?? this.stages.new;
        },

        /* ---------------- Selection ---------------- */

        get allOnPage() {
            return this.pageIds.length > 0 && this.pageIds.every((id) => this.selected.includes(id));
        },
        get someOnPage() {
            return !this.allOnPage && this.pageIds.some((id) => this.selected.includes(id));
        },
        get selectionCount() {
            return this.allMatching ? this.total : this.selected.length;
        },
        toggleAll(on) {
            this.allMatching = false;
            this.selected = on
                ? [...new Set([...this.selected, ...this.pageIds])]
                : this.selected.filter((id) => !this.pageIds.includes(id));
        },
        toggle(id) {
            this.allMatching = false;
            const i = this.selected.indexOf(id);
            i === -1 ? this.selected.push(id) : this.selected.splice(i, 1);
        },
        clearSelection() {
            this.selected = [];
            this.allMatching = false;
            this.bulkMenu = null;
        },

        /* ---------------- Drawer ---------------- */

        showUrl(id) {
            return this.urls.show.replace('__ID__', String(id));
        },
        setUrl(id) {
            const url = new URL(location.href);
            id ? url.searchParams.set('contact', id) : url.searchParams.delete('contact');
            if (url.href !== location.href) history.pushState({ contact: id }, '', url);
        },
        async open(id, push = true) {
            if (this.drawer && this.contact?.id === id) return;
            if (this.drawer && this.dirty && !confirm('Discard unsaved changes to this contact?')) return;
            if (push) this.setUrl(id);

            this.drawer = true;
            this.loading = true;
            this.contact = null;
            this.errors = {};
            const seq = ++this.seq;

            try {
                const { data } = await api(this.showUrl(id));
                if (seq === this.seq) this.show(data);
            } catch (e) {
                if (seq !== this.seq) return;
                toast(e.status === 404 ? 'That contact no longer exists.' : e.message, 'error');
                this.close(true, true);
            } finally {
                if (seq === this.seq) this.loading = false;
            }
        },
        show(data) {
            this.contact = data;
            this.drawer = true;
            this.loading = false;
            this.errors = {};
            this.form = Object.fromEntries(FORM_KEYS.map((k) => [k, k === 'tags' ? [...(data.tags ?? [])] : (data[k] ?? '')]));
            this.original = JSON.stringify(this.form);
            this.tagDraft = '';
            this.deleteConfirm = '';
        },
        get dirty() {
            return this.contact !== null && JSON.stringify(this.form) !== this.original;
        },
        discard() {
            if (this.contact) this.show(this.contact);
        },
        close(push = true, force = false) {
            if (!this.drawer) return;
            if (!force && this.dirty && !confirm('Discard unsaved changes to this contact?')) {
                if (!push) this.setUrl(this.contact?.id);
                return;
            }

            this.drawer = false;
            this.seq++;
            if (push) this.setUrl(null);
            if (this.changed) {
                this.changed = false;
                this.reloadResults();
            }
            setTimeout(() => {
                if (!this.drawer) this.contact = null;
            }, 250);
        },
        escape() {
            // A confirmation modal handles its own Esc.
            if (document.querySelector('[role=dialog][aria-modal=true]:not([data-drawer])')?.offsetParent) return;
            this.close();
        },
        err(field) {
            const hit = this.errors[field] ?? Object.entries(this.errors).find(([k]) => k.startsWith(field + '.'))?.[1];
            return hit ? hit[0] : '';
        },
        payload() {
            const original = JSON.parse(this.original);
            const out = {};
            FORM_KEYS.forEach((k) => {
                if (JSON.stringify(this.form[k]) !== JSON.stringify(original[k])) {
                    out[k] = this.form[k] === '' ? null : this.form[k];
                }
            });
            return out;
        },
        async save() {
            if (!this.contact || this.saving || !this.dirty) return;
            this.saving = true;
            this.errors = {};

            try {
                const res = await api(this.contact.urls.update, { method: 'PATCH', body: this.payload() });
                (res.data.tags ?? []).forEach((t) => this.allTags.includes(t) || this.allTags.push(t));
                this.allTags.sort();
                this.show(res.data);
                this.syncCard(res.data);
                this.changed = true;
                toast(res.message ?? 'Contact saved.');
            } catch (e) {
                if (e.status === 422) {
                    this.errors = e.data?.errors ?? {};
                    toast('Please fix the highlighted fields.', 'error');
                } else {
                    toast(e.message, 'error');
                }
            } finally {
                this.saving = false;
            }
        },

        // Tags editor
        get tagSuggestions() {
            const q = normTag(this.tagDraft);
            return this.allTags.filter((t) => !(this.form.tags ?? []).includes(t) && (!q || t.includes(q))).slice(0, 8);
        },
        addTag(raw = null) {
            const tag = normTag(raw ?? this.tagDraft);
            this.tagDraft = '';
            if (tag && !this.form.tags.includes(tag)) this.form.tags.push(tag);
        },
        removeTag(tag) {
            this.form.tags = this.form.tags.filter((t) => t !== tag);
        },
        tagKeydown(e) {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                this.addTag();
            } else if (e.key === 'Backspace' && this.tagDraft === '' && this.form.tags.length) {
                this.form.tags.pop();
            }
        },

        async refreshProfile() {
            if (!this.contact || this.refreshing) return;
            const id = this.contact.id;
            this.refreshing = true;

            try {
                const res = await api(this.contact.urls.refresh, { method: 'POST' });
                toast(res.message, 'info');
                setTimeout(async () => {
                    try {
                        if (this.contact?.id === id && !this.dirty) {
                            const { data } = await api(this.showUrl(id));
                            if (this.contact?.id === id && !this.dirty) {
                                this.show(data);
                                this.syncCard(data);
                                this.changed = true;
                            }
                        }
                    } catch {
                        // keep the current data
                    } finally {
                        this.refreshing = false;
                    }
                }, 3500);
            } catch (e) {
                toast(e.message, 'error');
                this.refreshing = false;
            }
        },

        async destroyContact() {
            if (!this.contact || this.deleteConfirm !== 'DELETE' || this.deleting) return;
            this.deleting = true;
            const id = this.contact.id;

            try {
                const res = await api(this.contact.urls.destroy, { method: 'DELETE', body: { confirm: 'DELETE' } });
                this.$dispatch('close-modal', 'contact-delete');
                this.removeCard(id);
                this.selected = this.selected.filter((x) => x !== id);
                this.changed = true;
                this.close(true, true);
                toast(res.message);
            } catch (e) {
                toast(firstError(e), 'error');
            } finally {
                this.deleting = false;
            }
        },

        /* ---------------- List refresh (keeps the drawer / Alpine state) ---------------- */

        async reloadResults() {
            if (this.view === 'board') return;

            try {
                const url = new URL(location.href);
                url.searchParams.delete('contact');
                const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
                if (!res.ok) throw new Error(String(res.status));
                const doc = new DOMParser().parseFromString(await res.text(), 'text/html');

                ['contacts-results', 'contacts-segments', 'contacts-stats'].forEach((id) => {
                    const fresh = doc.getElementById(id);
                    const current = document.getElementById(id);
                    if (fresh && current) current.innerHTML = fresh.innerHTML;
                });

                const meta = document.querySelector('#contacts-results [data-results]');
                this.pageIds = JSON.parse(meta?.dataset.ids ?? '[]');
                this.total = Number(meta?.dataset.total ?? 0);
            } catch {
                location.reload();
            }
        },

        /* ---------------- Bulk actions ---------------- */

        async bulk(action, extra = {}) {
            if (this.bulkBusy || this.selectionCount === 0) return;
            this.bulkBusy = true;

            const body = { action, filters: this.filters, ...extra };
            this.allMatching ? (body.all = 1) : (body.ids = this.selected);

            try {
                const res = await api(this.urls.bulk, { method: 'POST', body });
                if (action === 'delete') this.$dispatch('close-modal', 'contacts-bulk-delete');
                if (action === 'add_tag') {
                    const tag = normTag(extra.tag);
                    if (!this.allTags.includes(tag)) this.allTags = [...this.allTags, tag].sort();
                }
                toast(res.message);
                this.clearSelection();
                this.bulkTag = '';
                this.bulkConfirm = '';
                await this.reloadResults();
            } catch (e) {
                toast(firstError(e), 'error');
            } finally {
                this.bulkBusy = false;
            }
        },
        get bulkTagSuggestions() {
            const q = normTag(this.bulkTag);
            return this.allTags.filter((t) => !q || t.includes(q)).slice(0, 6);
        },
        exportSelected() {
            this.bulkMenu = null;
            if (this.allMatching) {
                window.location.href = this.urls.exportAll;
                return;
            }
            this.$nextTick(() => this.$refs.exportForm.submit());
            toast('Preparing your CSV…', 'info');
        },

        /* ---------------- Board ---------------- */

        column(stage) {
            return this.columns.find((c) => c.stage === stage);
        },
        dragStart(event, card, stage) {
            this.drag = { id: card.id, from: stage };
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(card.id));
        },
        dragEnd() {
            this.drag = null;
            this.over = null;
        },
        async drop(stage) {
            const drag = this.drag;
            this.dragEnd();
            if (drag) await this.move(drag.id, drag.from, stage);
        },
        async move(id, from, to) {
            if (!to || from === to) return;
            const src = this.column(from);
            const dst = this.column(to);
            const index = src?.cards.findIndex((c) => c.id === id) ?? -1;
            if (index === -1 || !dst) return;

            const [card] = src.cards.splice(index, 1);
            card.lead_stage = to;
            dst.cards.unshift(card);
            src.count--;
            dst.count++;

            try {
                const res = await api(this.showUrl(id), { method: 'PATCH', body: { lead_stage: to } });
                toast(res.message);
                if (this.contact?.id === id) {
                    this.contact = res.data;
                    const original = JSON.parse(this.original);
                    original.lead_stage = to;
                    this.original = JSON.stringify(original);
                    this.form.lead_stage = to;
                }
            } catch (e) {
                const i = dst.cards.findIndex((c) => c.id === id);
                if (i !== -1) dst.cards.splice(i, 1);
                card.lead_stage = from;
                src.cards.splice(index, 0, card);
                src.count++;
                dst.count--;
                toast(firstError(e), 'error');
            }
        },
        syncCard(data) {
            for (const col of this.columns) {
                const i = col.cards.findIndex((c) => c.id === data.id);
                if (i === -1) continue;
                const card = { ...col.cards[i], ...Object.fromEntries(Object.keys(col.cards[i]).map((k) => [k, data[k]])) };
                if (col.stage === data.lead_stage) {
                    col.cards.splice(i, 1, card);
                } else {
                    col.cards.splice(i, 1);
                    col.count--;
                    const dst = this.column(data.lead_stage);
                    if (dst) {
                        dst.cards.unshift(card);
                        dst.count++;
                    }
                }
                return;
            }
        },
        removeCard(id) {
            for (const col of this.columns) {
                const i = col.cards.findIndex((c) => c.id === id);
                if (i !== -1) {
                    col.cards.splice(i, 1);
                    col.count--;
                    return;
                }
            }
        },
    }));
});
@endverbatim
</script>
