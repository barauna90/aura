/**
 * Runner da prova: cronômetro exibido a partir do servidor, autosave em lote
 * (marcações do CADERNO = rascunho; CARTÃO-RESPOSTA = única coisa corrigida),
 * reconciliação periódica e resiliência offline.
 * Elemento raiz: <div id="exam-runner" data-...>
 */
const root = document.getElementById('exam-runner');
if (root) {
    const cfg = root.dataset;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const timerEl = document.getElementById('timer');
    const saveEl = document.getElementById('save-state');
    const answeredEls = [document.getElementById('answered-count'), document.getElementById('answered-count-2')].filter(Boolean);
    const blankEl = document.getElementById('blank-count');
    const draftedEl = document.getElementById('drafted-count');
    const untransferredEl = document.getElementById('untransferred-count');
    const sheet = document.getElementById('answer-sheet');
    const booklet = document.getElementById('booklet-marks');
    const storageKey = `aura.sheet.${cfg.sessionId}`;

    let status = cfg.status;
    let remaining = cfg.remaining === '' ? null : Number(cfg.remaining);
    // question_id -> { option?: string|null, draft_option?: string|null }
    const dirty = new Map();
    let flushing = false;

    const hms = (s) => {
        s = Math.max(0, Math.floor(s));
        const p = (n) => String(n).padStart(2, '0');
        return `${p(Math.floor(s / 3600))}:${p(Math.floor((s % 3600) / 60))}:${p(s % 60)}`;
    };

    const api = async (path, body) => {
        const r = await fetch(path, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
            body: body ? JSON.stringify(body) : undefined,
        });
        if (!r.ok) {
            const err = await r.json().catch(() => ({}));
            throw Object.assign(new Error(err.message || r.statusText), { status: r.status });
        }
        return r.json();
    };

    const selected = (container, qid) => container?.querySelector(`[data-question="${qid}"] .bubble[aria-pressed="true"]`)?.dataset.option ?? null;
    const setPressed = (container, qid, option) => {
        const row = container?.querySelector(`[data-question="${qid}"]`);
        if (!row) return;
        row.querySelectorAll('.bubble').forEach((x) => x.setAttribute('aria-pressed', x.dataset.option === option ? 'true' : 'false'));
    };

    const render = () => {
        if (timerEl) {
            timerEl.textContent = remaining === null ? '--:--:--' : hms(remaining);
            timerEl.classList.toggle('text-danger', remaining !== null && remaining < 600);
            timerEl.classList.toggle('text-warning', remaining !== null && remaining >= 600 && remaining < 1800);
        }
        if (!sheet) return;
        const rows = [...sheet.querySelectorAll('[data-question]')];
        let answered = 0, drafted = 0, untransferred = 0;
        for (const row of rows) {
            const qid = row.dataset.question;
            const opt = selected(sheet, qid);
            const draft = selected(booklet, qid);
            if (opt) answered++;
            if (draft) drafted++;
            if (draft && draft !== opt) untransferred++;
            const hint = row.querySelector('[data-draft-hint]');
            if (hint) hint.textContent = draft ? `caderno: ${draft}` : '';
        }
        answeredEls.forEach((el) => (el.textContent = answered));
        if (blankEl) blankEl.textContent = rows.length - answered;
        if (draftedEl) draftedEl.textContent = drafted;
        if (untransferredEl) untransferredEl.textContent = untransferred;
    };

    const persistLocal = () => {
        try {
            const marks = {};
            sheet.querySelectorAll('[data-question]').forEach((row) => {
                const qid = row.dataset.question;
                marks[qid] = { option: selected(sheet, qid), draft_option: selected(booklet, qid) };
            });
            localStorage.setItem(storageKey, JSON.stringify(marks));
        } catch {}
    };

    const mark = (qid, patch) => {
        dirty.set(qid, { ...(dirty.get(qid) || {}), ...patch });
        saveEl.textContent = 'Alterações pendentes';
        persistLocal();
        render();
    };

    const flush = async () => {
        if (flushing || dirty.size === 0 || status !== 'IN_PROGRESS') return;
        flushing = true;
        const batch = [...dirty.entries()].map(([question_id, patch]) => ({ question_id: Number(question_id), ...patch }));
        dirty.clear();
        saveEl.textContent = 'Salvando…';
        try {
            const r = await api(cfg.answersUrl, { answers: batch });
            remaining = r.remaining_seconds;
            saveEl.textContent = 'Salvo';
        } catch (e) {
            for (const b of batch) {
                const { question_id, ...patch } = b;
                dirty.set(String(question_id), { ...patch, ...(dirty.get(String(question_id)) || {}) });
            }
            saveEl.textContent = e.status === 422 || e.status === 403 ? e.message : 'Sem conexão — salvo localmente';
            if (e.status === 422 && /encerrad|esgotad/i.test(e.message)) window.location.href = cfg.resultUrl;
        } finally {
            flushing = false;
        }
    };

    // Clique numa bolha: alterna a alternativa da linha (cartão ou caderno).
    const bindBubbles = (container, field) => {
        container?.addEventListener('click', (e) => {
            const b = e.target.closest('.bubble');
            if (!b || status !== 'IN_PROGRESS') return;
            const row = b.closest('[data-question]');
            const wasOn = b.getAttribute('aria-pressed') === 'true';
            setPressed(container, row.dataset.question, wasOn ? null : b.dataset.option);
            mark(row.dataset.question, { [field]: wasOn ? null : b.dataset.option });
        });
    };
    bindBubbles(sheet, 'option');
    bindBubbles(booklet, 'draft_option');

    // Transferir marcações do caderno para o cartão (só onde o cartão diverge).
    document.getElementById('btn-transfer')?.addEventListener('click', () => {
        if (status !== 'IN_PROGRESS') return;
        let moved = 0;
        sheet.querySelectorAll('[data-question]').forEach((row) => {
            const qid = row.dataset.question;
            const draft = selected(booklet, qid);
            if (draft && draft !== selected(sheet, qid)) {
                setPressed(sheet, qid, draft);
                mark(qid, { option: draft });
                moved++;
            }
        });
        if (moved === 0) {
            alert('Nenhuma marcação nova para transferir. Marque as alternativas no caderno primeiro.');
            return;
        }
        showTab('sheet');
        flush();
    });

    // Abas Caderno / Cartão-resposta
    const showTab = (name) => {
        document.querySelectorAll('[data-panel-tab]').forEach((t) => t.setAttribute('aria-selected', t.dataset.panelTab === name ? 'true' : 'false'));
        document.querySelectorAll('[data-panel-body]').forEach((p) => (p.hidden = p.dataset.panelBody !== name));
    };
    document.querySelectorAll('[data-panel-tab]').forEach((t) => t.addEventListener('click', () => showTab(t.dataset.panelTab)));

    // Tique local (exibição). A fonte da verdade é o servidor, reconciliada a cada 30 s.
    setInterval(() => {
        if (status === 'IN_PROGRESS' && remaining !== null) {
            remaining = Math.max(0, remaining - 1);
            render();
            if (remaining === 0) sync();
        }
    }, 1000);
    setInterval(flush, 4000);
    setInterval(() => status === 'IN_PROGRESS' && sync(), 30000);

    const sync = async () => {
        try {
            const r = await fetch(cfg.stateUrl, { headers: { Accept: 'application/json' } }).then((x) => x.json());
            status = r.status;
            remaining = r.remaining_seconds;
            if (r.finished) window.location.href = cfg.resultUrl;
            render();
        } catch {}
    };

    document.addEventListener('visibilitychange', () => document.visibilityState === 'hidden' && flush());
    window.addEventListener('beforeunload', flush);

    // Ações
    const bind = (id, url, after) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('click', async () => {
            el.disabled = true;
            try {
                await flush();
                const r = await api(url);
                after(r);
            } catch (e) {
                alert(e.message);
                el.disabled = false;
            }
        });
    };
    bind('btn-pause', cfg.pauseUrl, () => window.location.reload());
    bind('btn-resume', cfg.resumeUrl, () => window.location.reload());
    const finishBtn = document.getElementById('btn-finish');
    if (finishBtn) {
        finishBtn.addEventListener('click', async () => {
            const blank = Number(blankEl?.textContent || 0);
            const untransferred = Number(untransferredEl?.textContent || 0);
            let msg = `Encerrar a prova agora?\n\n${blank} questão(ões) em branco no cartão-resposta.\n`;
            if (untransferred > 0) msg += `ATENÇÃO: ${untransferred} marcação(ões) do caderno NÃO foram transferidas para o cartão e não serão corrigidas.\n`;
            if (cfg.hasEssay === '1') msg += 'Após encerrar você poderá enviar a redação para avaliação.\n';
            msg += 'Esta ação não pode ser desfeita.';
            if (!window.confirm(msg)) return;
            finishBtn.disabled = true;
            try {
                await flush();
                const r = await api(cfg.finishUrl);
                window.location.href = r.redirect || cfg.resultUrl;
            } catch (e) {
                alert(e.message);
                finishBtn.disabled = false;
            }
        });
    }

    // Navegação de páginas do PDF oficial
    const frame = document.getElementById('pdf-frame');
    const pageInput = document.getElementById('pdf-page');
    const pageCount = Number(cfg.pageCount || 1);
    const goto = (p) => {
        if (!frame || !pageInput) return;
        p = Math.min(pageCount, Math.max(1, p));
        pageInput.value = p;
        // O visualizador de PDF ignora mudanças só no fragmento (#page=): força uma
        // navegação real do iframe. O arquivo vem do cache HTTP (Cache-Control no servidor).
        const url = `${cfg.pdfUrl}#page=${p}&toolbar=0&navpanes=0&view=FitH`;
        frame.src = 'about:blank';
        requestAnimationFrame(() => (frame.src = url));
    };
    document.getElementById('pdf-prev')?.addEventListener('click', () => goto(Number(pageInput.value) - 1));
    document.getElementById('pdf-next')?.addEventListener('click', () => goto(Number(pageInput.value) + 1));
    pageInput?.addEventListener('change', () => goto(Number(pageInput.value) || 1));
    document.querySelectorAll('[data-goto-page]').forEach((el) => el.addEventListener('click', () => goto(Number(el.dataset.gotoPage))));

    render();
}
