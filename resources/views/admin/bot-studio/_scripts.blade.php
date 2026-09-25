{{--
    Bot Studio Alpine factories. Classic inline script: it runs while the page is parsed, i.e. BEFORE the
    app.js module starts Alpine, so x-data="botStudio(...)" can reference these functions.
--}}
<script>
@verbatim
(() => {
    const clone = (v) => JSON.parse(JSON.stringify(v));
    let uid = 0;
    const nextUid = () => 'faq' + (++uid);

    /** Parse "Q: … / A: …" text (same rules as BotSettingRequest::parseFaqs). */
    const parseFaqText = (text) => {
        const items = [];
        let current = null, field = null;
        for (const line of String(text || '').split(/\r\n|\r|\n/)) {
            let m;
            if ((m = line.match(/^\s*Q\s*[:.]\s*(.*)$/i))) {
                if (current) items.push(current);
                current = { question: m[1], answer: '' };
                field = 'question';
            } else if (current && (m = line.match(/^\s*A\s*[:.]\s*(.*)$/i))) {
                current.answer = m[1];
                field = 'answer';
            } else if (current && field) {
                current[field] += '\n' + line;
            }
        }
        if (current) items.push(current);
        return items
            .map((i) => ({ question: i.question.trim(), answer: i.answer.trim() }))
            .filter((i) => i.question !== '' && i.answer !== '');
    };

    /** The whole settings form. */
    window.botStudio = (cfg) => ({
        tab: cfg.tab,
        tabKeys: cfg.tabKeys,
        errorTabs: cfg.errorTabs,
        isPage: cfg.isPage,
        enabled: cfg.enabled,
        faqs: [],
        inheritedFaqs: cfg.inheritedFaqs,
        faqErrors: cfg.faqErrors,
        provider: cfg.provider ?? '',
        inheritedProvider: cfg.inheritedProvider,
        providers: cfg.providers,
        modelChoice: '',
        customModel: '',
        fallbackOn: cfg.fallbackOn,
        templates: cfg.templates,
        importText: '',
        dragIndex: null,
        silent: false,
        snapshot: null,

        init() {
            this.faqs = (cfg.faqs || []).map((f, i) => ({ ...f, uid: nextUid(), open: !!this.faqErrors[i] }));
            this.setModel(cfg.model ?? '');

            const hash = location.hash.replace('#tab-', '');
            if (!this.errorTabs.length && this.tabKeys.includes(hash)) this.tab = hash;
            this.$watch('tab', (v) => history.replaceState(null, '', '#tab-' + v));
            this.$watch('effectiveProvider', () => {
                if (this.modelChoice !== '' && this.modelChoice !== '__custom' && !this.providerInfo.models.includes(this.modelChoice)) {
                    this.modelChoice = '';
                }
            });

            this.snapshot = this.state();
            this.$root.addEventListener('reset', () => setTimeout(() => this.restore(), 0));
        },

        state() {
            return clone({ enabled: this.enabled, faqs: this.faqs, provider: this.provider, modelChoice: this.modelChoice, customModel: this.customModel, fallbackOn: this.fallbackOn });
        },
        restore() {
            this.silent = true;
            Object.assign(this, clone(this.snapshot));
            setTimeout(() => (this.silent = false), 50);
        },
        dirty() {
            if (!this.silent) this.$root.dispatchEvent(new Event('input', { bubbles: true }));
        },

        hasError(tab) {
            return this.errorTabs.includes(tab);
        },

        /* ---- Persona -------------------------------------------------------- */
        field(id) {
            return document.getElementById(id);
        },
        setField(id, value) {
            const el = this.field(id);
            if (!el) return;
            el.value = value;
            el.dispatchEvent(new Event('input', { bubbles: true }));
        },
        applyTemplate(key) {
            const t = this.templates[key];
            if (!t) return;
            this.setField('system_prompt', t.prompt);
            toast(`"${t.label}" template applied. Tweak it, then save.`, 'info');
        },
        appendTone(line) {
            const el = this.field('system_prompt');
            if (!el) return;
            const base = el.value.trim() !== '' ? el.value.trim() : String(el.dataset.inherited || '').trim();
            const cleaned = base.replace(/\n?Tone: .*$/m, '').trim();
            this.setField('system_prompt', (cleaned ? cleaned + '\n' : '') + line);
        },

        /* ---- FAQs ------------------------------------------------------------ */
        addFaq(question = '', answer = '') {
            this.faqs.push({ question, answer, uid: nextUid(), open: true });
            this.dirty();
            this.$nextTick(() => {
                const inputs = this.$root.querySelectorAll('[data-faq-question]');
                inputs[inputs.length - 1]?.focus();
            });
        },
        removeFaq(i) {
            this.faqs.splice(i, 1);
            this.dirty();
        },
        moveFaq(i, dir) {
            const j = i + dir;
            if (j < 0 || j >= this.faqs.length) return;
            const [item] = this.faqs.splice(i, 1);
            this.faqs.splice(j, 0, item);
            this.dirty();
        },
        dropFaq(i) {
            if (this.dragIndex === null || this.dragIndex === i) return (this.dragIndex = null);
            const [item] = this.faqs.splice(this.dragIndex, 1);
            this.faqs.splice(i, 0, item);
            this.dragIndex = null;
            this.dirty();
        },
        toggleAllFaqs(open) {
            this.faqs.forEach((f) => (f.open = open));
        },
        copyInheritedFaqs() {
            this.faqs = this.inheritedFaqs.map((f) => ({ ...f, uid: nextUid(), open: false }));
            this.dirty();
        },
        importFaqs() {
            const items = parseFaqText(this.importText);
            if (!items.length) {
                toast('No Q:/A: pairs found. Start questions with "Q:" and answers with "A:".', 'warning');
                return false;
            }
            items.forEach((f) => this.faqs.push({ ...f, uid: nextUid(), open: false }));
            this.importText = '';
            this.dirty();
            toast(`Imported ${items.length} FAQ${items.length === 1 ? '' : 's'}.`);
            return true;
        },
        faqError(i, key) {
            return (this.faqErrors[i] || {})[key] || '';
        },

        /* ---- AI model -------------------------------------------------------- */
        get effectiveProvider() {
            return this.provider || this.inheritedProvider;
        },
        get providerInfo() {
            return this.providers.find((p) => p.key === this.effectiveProvider) || this.providers[0];
        },
        providerLabel(key) {
            return (this.providers.find((p) => p.key === key) || {}).label || key;
        },
        setModel(model) {
            const info = this.providerInfo;
            if (!model) {
                this.modelChoice = '';
            } else if (info.models.includes(model)) {
                this.modelChoice = model;
            } else {
                this.modelChoice = '__custom';
                this.customModel = model;
            }
        },
        get modelValue() {
            return this.modelChoice === '__custom' ? this.customModel.trim() : this.modelChoice;
        },
        get activeModel() {
            return this.modelValue || this.providerInfo.inherited_model;
        },
        hint(model) {
            return this.providerInfo.hints[model] || null;
        },
    });

    /**
     * One inheritable field: tracks whether the input inside ([data-inherit-input]) has its own value.
     * Blank = inherit (page → global, global → built-in default).
     */
    window.bsInherit = (inherited) => ({
        inherited,
        value: '',
        input: null,
        init() {
            this.input = this.$root.querySelector('[data-inherit-input]');
            if (!this.input) return;
            const sync = () => (this.value = this.input.value);
            sync();
            this.input.addEventListener('input', sync);
            this.input.form?.addEventListener('reset', () => setTimeout(sync, 0));
        },
        get custom() {
            return String(this.value ?? '').trim() !== '';
        },
        get hasInherited() {
            return this.inherited !== null && this.inherited !== undefined && String(this.inherited).trim() !== '';
        },
        override() {
            this.set(this.hasInherited ? String(this.inherited) : '');
            this.$nextTick(() => this.input.focus());
        },
        clear() {
            this.set('');
        },
        set(v) {
            this.input.value = v;
            this.input.dispatchEvent(new Event('input', { bubbles: true }));
        },
    });

    /** "Test your bot" phone preview. Talks to BotStudioPlaygroundController (saved settings). */
    window.bsPlayground = (cfg) => ({
        url: cfg.url,
        accountId: cfg.accountId,
        platform: cfg.platform,
        pageName: cfg.pageName,
        name: 'Sara Khan',
        messages: [],
        text: '',
        sending: false,
        unsaved: false,
        botEnabled: cfg.botEnabled,
        seq: 0,

        init() {
            const form = document.getElementById('bot-studio-form');
            form?.addEventListener('input', () => (this.unsaved = true));
            form?.addEventListener('reset', () => setTimeout(() => (this.unsaved = false), 60));
        },
        history() {
            return this.messages.filter((m) => !m.error && !m.pending).map((m) => ({ role: m.role, content: m.content }));
        },
        scroll() {
            this.$nextTick(() => {
                const el = this.$refs.thread;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },
        async send(content = null, payload = null) {
            const body = String(content ?? this.text).trim();
            if (!body || this.sending) return;
            this.text = '';
            this.messages.push({ id: ++this.seq, role: 'user', content: body, payload });
            const pending = { id: ++this.seq, role: 'assistant', content: '', pending: true };
            this.messages.push(pending);
            this.sending = true;
            this.scroll();
            try {
                const res = await api(this.url, {
                    method: 'POST',
                    body: {
                        meta_account_id: this.accountId,
                        platform: this.platform,
                        messages: this.history(),
                        customer_name: this.name || null,
                        payload,
                    },
                });
                Object.assign(pending, { pending: false, content: res.text, meta: res });
            } catch (e) {
                Object.assign(pending, { pending: false, error: true, content: e.message || 'Something went wrong.' });
            } finally {
                this.sending = false;
                this.scroll();
                this.$nextTick(() => this.$refs.input?.focus());
            }
        },
        getStarted() {
            this.send('Get Started', 'GET_STARTED');
        },
        reset() {
            this.messages = [];
            this.text = '';
            this.$refs.input?.focus();
        },
        retry() {
            const lastUser = [...this.messages].reverse().find((m) => m.role === 'user');
            if (!lastUser) return;
            this.messages = this.messages.filter((m) => !m.error);
            const idx = this.messages.lastIndexOf(lastUser);
            this.messages.splice(idx, 1);
            this.send(lastUser.content, lastUser.payload ?? null);
        },
    });
})();
@endverbatim
</script>
