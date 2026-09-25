{{--
    Automations Alpine factories. Classic inline script: runs while the page is parsed, before the app.js
    module starts Alpine, so x-data="automations(...)" can use it.
--}}
<script>
@verbatim
(() => {
    const debounce = (fn, ms = 300) => {
        let t;
        return (...args) => {
            clearTimeout(t);
            t = setTimeout(() => fn(...args), ms);
        };
    };

    const timeAgo = (iso) => {
        if (!iso) return null;
        const s = Math.max(0, (Date.now() - new Date(iso).getTime()) / 1000);
        if (s < 60) return 'just now';
        const units = [[60, 'min'], [24, 'h'], [7, 'd'], [4.35, 'w'], [12, 'mo'], [Infinity, 'y']];
        let v = s / 60;
        for (const [n, label] of units) {
            if (v < n) return Math.floor(v) + (label === 'min' ? ' min' : label) + ' ago';
            v /= n;
        }
        return null;
    };

    const insertAtCursor = (el, text) => {
        if (!el) return null;
        const start = el.selectionStart ?? el.value.length;
        const end = el.selectionEnd ?? el.value.length;
        const value = el.value.slice(0, start) + text + el.value.slice(end);
        el.value = value;
        el.focus();
        el.setSelectionRange(start + text.length, start + text.length);
        el.dispatchEvent(new Event('input', { bubbles: true }));
        return value;
    };

    const blankRule = (scope) => ({
        id: null, name: '', match_type: 'contains', keywords: [], reply_text: '', priority: 0, active: true, meta_account_id: scope,
    });

    window.automations = (cfg) => ({
        routes: cfg.routes,
        accountId: cfg.accountId,
        pageName: cfg.pageName,
        rules: cfg.rules,
        starters: cfg.starters,
        matchTypes: cfg.matchTypes,

        // editor
        form: blankRule(cfg.accountId),
        keywordDraft: '',
        errors: {},
        regexErrors: {},
        saving: false,
        preview: '',
        previewLoading: false,
        showAdvanced: false,

        // delete
        toDelete: null,

        // drag
        drag: null,

        // test a message
        test: { text: '', firstContact: false, loading: false, result: null },

        init() {
            this.refreshPreview = debounce(this.refreshPreview.bind(this), 250);
            this.checkRegex = debounce(this.checkRegex.bind(this), 350);
            this.$watch('form.reply_text', () => this.refreshPreview());
            this.$watch('form.keywords', () => this.checkRegex());
            this.$watch('form.match_type', () => this.checkRegex());
            setInterval(() => (this.rules = [...this.rules]), 60000); // refresh "x min ago"
        },

        /* ---- list ------------------------------------------------------------ */
        get pageRules() {
            return this.rules.filter((r) => r.meta_account_id !== null);
        },
        get globalRules() {
            return this.rules.filter((r) => r.meta_account_id === null);
        },
        group(scope) {
            return scope === 'page' ? this.pageRules : this.globalRules;
        },
        timeAgo,
        matchLabel(type) {
            return (this.matchTypes[type] || {}).label || type;
        },

        async toggle(rule) {
            const next = !rule.active;
            rule.active = next;
            try {
                await api(this.routes.toggle.replace('__ID__', rule.id), { method: 'PATCH', body: { active: next } });
                toast(next ? `"${rule.name}" is on.` : `"${rule.name}" is off.`, next ? 'success' : 'info');
            } catch (e) {
                rule.active = !next;
                toast(e.message, 'error');
            }
        },

        async move(rule, dir) {
            const list = this.group(rule.meta_account_id === null ? 'global' : 'page');
            const i = list.indexOf(rule);
            const j = i + dir;
            if (j < 0 || j >= list.length) return;
            const ids = list.map((r) => r.id);
            [ids[i], ids[j]] = [ids[j], ids[i]];
            await this.saveOrder(ids);
        },
        async dropOn(target) {
            const dragged = this.drag;
            this.drag = null;
            if (!dragged || dragged === target || dragged.meta_account_id !== target.meta_account_id) return;
            const list = this.group(target.meta_account_id === null ? 'global' : 'page');
            const ids = list.map((r) => r.id).filter((id) => id !== dragged.id);
            ids.splice(ids.indexOf(target.id), 0, dragged.id);
            await this.saveOrder(ids);
        },
        async saveOrder(ids) {
            const before = [...this.rules];
            // optimistic: reorder locally inside the group
            const byId = Object.fromEntries(this.rules.map((r) => [r.id, r]));
            const moved = ids.map((id) => byId[id]);
            const positions = this.rules.map((r, idx) => (ids.includes(r.id) ? idx : null)).filter((x) => x !== null);
            const next = [...this.rules];
            positions.forEach((pos, k) => (next[pos] = moved[k]));
            this.rules = next;
            try {
                const res = await api(this.routes.reorder, { method: 'POST', body: { ids } });
                res.rules.forEach((r) => Object.assign(byId[r.id], r));
            } catch (e) {
                this.rules = before;
                toast(e.message, 'error');
            }
        },

        async duplicate(rule) {
            try {
                const res = await api(this.routes.duplicate.replace('__ID__', rule.id), { method: 'POST' });
                const idx = this.rules.indexOf(rule);
                this.rules.splice(idx + 1, 0, res.rule);
                toast(res.message);
            } catch (e) {
                toast(e.message, 'error');
            }
        },

        confirmDelete(rule) {
            this.toDelete = rule;
            this.$dispatch('open-modal', 'delete-rule');
        },
        async destroy() {
            const rule = this.toDelete;
            if (!rule) return;
            try {
                const res = await api(this.routes.destroy.replace('__ID__', rule.id), { method: 'DELETE' });
                this.rules = this.rules.filter((r) => r.id !== rule.id);
                toast(res.message);
                this.$dispatch('close-modal', 'delete-rule');
            } catch (e) {
                toast(e.message, 'error');
            } finally {
                this.toDelete = null;
            }
        },

        /* ---- editor ------------------------------------------------------------ */
        openCreate(preset = null) {
            this.form = { ...blankRule(this.accountId), ...(preset ? JSON.parse(JSON.stringify(preset)) : {}) };
            this.openEditor();
        },
        openEdit(rule) {
            this.form = JSON.parse(JSON.stringify(rule));
            this.openEditor();
        },
        openEditor() {
            this.errors = {};
            this.regexErrors = {};
            this.keywordDraft = '';
            this.showAdvanced = this.form.priority !== 0;
            this.preview = '';
            this.refreshPreview();
            this.checkRegex();
            this.$dispatch('open-modal', 'rule-editor');
            this.$nextTick(() => setTimeout(() => document.getElementById('rule-name')?.focus(), 60));
        },
        addKeywords(raw) {
            const parts = String(raw ?? this.keywordDraft).split(this.form.match_type === 'regex' ? /\n/ : /[,\n]/).map((k) => k.trim()).filter(Boolean);
            parts.forEach((k) => {
                if (!this.form.keywords.some((x) => x.toLowerCase() === k.toLowerCase())) this.form.keywords.push(k);
            });
            this.keywordDraft = '';
        },
        onKeywordKey(e) {
            if (e.key === 'Enter' || (e.key === ',' && this.form.match_type !== 'regex')) {
                e.preventDefault();
                this.addKeywords();
            } else if (e.key === 'Backspace' && this.keywordDraft === '' && this.form.keywords.length) {
                this.form.keywords.pop();
            }
        },
        onKeywordPaste(e) {
            const text = e.clipboardData?.getData('text') ?? '';
            if (/[,\n]/.test(text)) {
                e.preventDefault();
                this.addKeywords(text);
            }
        },
        removeKeyword(i) {
            this.form.keywords.splice(i, 1);
        },
        insertVar(id, variable) {
            // The editor lives in a teleported modal (its own Alpine scope), so look the textarea up by id.
            const value = insertAtCursor(document.getElementById(id), variable);
            if (value !== null) this.form.reply_text = value;
        },
        async refreshPreview() {
            const text = this.form.reply_text;
            if (!text || !text.trim()) return (this.preview = '');
            this.previewLoading = true;
            try {
                const res = await api(this.routes.preview, { method: 'POST', body: { text, meta_account_id: this.form.meta_account_id ?? this.accountId } });
                this.preview = res.text;
            } catch {
                this.preview = text;
            } finally {
                this.previewLoading = false;
            }
        },
        async checkRegex() {
            if (this.form.match_type !== 'regex' || !this.form.keywords.length) return (this.regexErrors = {});
            try {
                const res = await api(this.routes.regex, { method: 'POST', body: { patterns: this.form.keywords } });
                this.regexErrors = res.errors;
            } catch {
                /* validated again on save */
            }
        },
        get hasRegexErrors() {
            return Object.values(this.regexErrors).some(Boolean);
        },
        err(key) {
            const found = Object.keys(this.errors).find((k) => k === key || k.startsWith(key + '.'));
            return found ? this.errors[found][0] : '';
        },
        async saveRule() {
            if (this.keywordDraft.trim()) this.addKeywords();
            this.saving = true;
            this.errors = {};
            const body = {
                name: this.form.name,
                trigger: 'keyword',
                match_type: this.form.match_type,
                keywords: this.form.keywords,
                reply_text: this.form.reply_text,
                priority: Number(this.form.priority || 0),
                active: !!this.form.active,
                meta_account_id: this.form.meta_account_id || null,
            };
            try {
                const editing = !!this.form.id;
                const res = await api(editing ? this.routes.update.replace('__ID__', this.form.id) : this.routes.store, { method: editing ? 'PUT' : 'POST', body });
                const inScope = res.rule.meta_account_id === null || res.rule.meta_account_id === this.accountId;
                if (editing) {
                    const idx = this.rules.findIndex((r) => r.id === res.rule.id);
                    if (!inScope) this.rules.splice(idx, 1);
                    else if (idx >= 0) this.rules.splice(idx, 1, res.rule);
                } else if (inScope) {
                    this.rules.push(res.rule);
                }
                this.sortRules();
                toast(inScope ? res.message : res.message + ' It belongs to another page, so it is not listed here.');
                this.$dispatch('close-modal', 'rule-editor');
            } catch (e) {
                this.errors = e.data?.errors ?? {};
                toast(e.status === 422 ? 'Please fix the highlighted fields.' : e.message, 'error');
            } finally {
                this.saving = false;
            }
        },
        sortRules() {
            // Same order as the matcher: page-specific first, then priority desc, then oldest.
            this.rules = [...this.rules].sort((a, b) =>
                (a.meta_account_id === null) - (b.meta_account_id === null) || b.priority - a.priority || a.id - b.id);
        },

        /* ---- test a message ---------------------------------------------------- */
        async runTest() {
            if (!this.test.text.trim()) return;
            this.test.loading = true;
            try {
                this.test.result = await api(this.routes.test, {
                    method: 'POST',
                    body: { text: this.test.text, first_contact: this.test.firstContact, meta_account_id: this.accountId },
                });
            } catch (e) {
                toast(e.message, 'error');
            } finally {
                this.test.loading = false;
            }
        },
    });

    /** Welcome message card. */
    window.automationWelcome = (cfg) => ({
        routes: cfg.routes,
        accountId: cfg.accountId,
        rule: cfg.rule,
        inherited: cfg.inherited,
        editing: !!cfg.rule || !cfg.accountId,
        active: cfg.rule ? cfg.rule.active : true,
        text: cfg.rule ? cfg.rule.reply_text : '',
        saved: null,
        preview: '',
        saving: false,
        error: '',
        init() {
            this.saved = JSON.stringify([this.active, this.text]);
            this.refresh = debounce(this.refresh.bind(this), 250);
            this.$watch('text', () => this.refresh());
            this.refresh();
        },
        get dirty() {
            return JSON.stringify([this.active, this.text]) !== this.saved;
        },
        insertVar(variable) {
            const value = insertAtCursor(this.$refs.welcomeText, variable);
            if (value !== null) this.text = value;
        },
        startOverride() {
            this.editing = true;
            this.text = this.inherited ? this.inherited.reply_text : '';
            this.$nextTick(() => this.$refs.welcomeText?.focus());
        },
        async refresh() {
            if (!this.text.trim()) return (this.preview = '');
            try {
                const res = await api(this.routes.preview, { method: 'POST', body: { text: this.text, meta_account_id: this.accountId } });
                this.preview = res.text;
            } catch {
                this.preview = this.text;
            }
        },
        async save() {
            this.saving = true;
            this.error = '';
            try {
                const res = await api(this.routes.welcome, { method: 'PUT', body: { meta_account_id: this.accountId, active: this.active, reply_text: this.text } });
                this.rule = res.rule;
                this.saved = JSON.stringify([this.active, this.text]);
                toast(res.message);
            } catch (e) {
                this.error = e.data?.errors?.reply_text?.[0] ?? e.message;
                toast(this.error, 'error');
            } finally {
                this.saving = false;
            }
        },
        async removeOverride() {
            if (!this.rule) return;
            try {
                await api(this.routes.destroy.replace('__ID__', this.rule.id), { method: 'DELETE' });
                this.rule = null;
                this.editing = false;
                this.text = '';
                this.active = true;
                this.saved = JSON.stringify([this.active, this.text]);
                toast('This page now uses the All pages welcome message.');
            } catch (e) {
                toast(e.message, 'error');
            }
        },
    });

    /** Welcome screen & ice breakers (Meta messenger profile) for one page. */
    window.messengerProfile = (cfg) => ({
        routes: cfg.routes,
        instagram: cfg.instagram,
        limits: cfg.limits,
        greeting: cfg.profile.greeting ?? '',
        getStarted: !!cfg.profile.get_started,
        iceBreakers: (cfg.profile.ice_breakers ?? []).map((i) => ({ question: i.question, payload: i.payload ?? '' })),
        updatedAt: cfg.profile.updated_at ?? null,
        loading: false,
        saving: false,
        warnings: [],
        error: '',
        loadedFromMeta: false,
        timeAgo,
        get conflict() {
            return !this.instagram && this.getStarted && this.iceBreakers.some((i) => i.question.trim());
        },
        addIceBreaker() {
            if (this.iceBreakers.length >= this.limits.ice_breakers) return;
            this.iceBreakers.push({ question: '', payload: '' });
            this.$nextTick(() => {
                const inputs = this.$root.querySelectorAll('[data-ice-question]');
                inputs[inputs.length - 1]?.focus();
            });
        },
        removeIceBreaker(i) {
            this.iceBreakers.splice(i, 1);
        },
        moveIceBreaker(i, dir) {
            const j = i + dir;
            if (j < 0 || j >= this.iceBreakers.length) return;
            const [item] = this.iceBreakers.splice(i, 1);
            this.iceBreakers.splice(j, 0, item);
        },
        payloadFor(item, i) {
            return item.payload && item.payload.trim() ? item.payload.trim() : 'ICE_BREAKER_' + (i + 1);
        },
        async load() {
            this.loading = true;
            this.error = '';
            try {
                const res = await api(this.routes.show);
                this.greeting = res.profile.greeting ?? '';
                this.getStarted = !!res.profile.get_started;
                this.iceBreakers = (res.profile.ice_breakers ?? []).map((i) => ({ question: i.question, payload: i.payload ?? '' }));
                this.loadedFromMeta = true;
                toast(res.message, 'info');
            } catch (e) {
                this.error = e.message;
                toast(e.message, 'error');
            } finally {
                this.loading = false;
            }
        },
        async save() {
            this.saving = true;
            this.error = '';
            this.warnings = [];
            try {
                const res = await api(this.routes.update, {
                    method: 'PUT',
                    body: {
                        greeting: this.greeting,
                        get_started: this.getStarted,
                        ice_breakers: this.iceBreakers.filter((i) => i.question.trim()).map((i) => ({ question: i.question, payload: i.payload || null })),
                    },
                });
                this.updatedAt = res.profile.updated_at ?? new Date().toISOString();
                this.warnings = res.warnings ?? [];
                toast(res.message);
                this.warnings.forEach((w) => toast(w, 'warning', 8000));
            } catch (e) {
                const errors = e.data?.errors ? Object.values(e.data.errors).flat() : [];
                this.error = errors[0] ?? e.message;
                toast(this.error, 'error');
            } finally {
                this.saving = false;
            }
        },
    });
})();
@endverbatim
</script>
