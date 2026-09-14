/**
 * Runner da prova: cronômetro exibido a partir do servidor, autosave do
 * cartão-resposta em lote, reconciliação periódica, resiliência offline.
 * Elemento raiz: <div id="exam-runner" data-...>
 */
const root = document.getElementById('exam-runner');
if (root) {
    const cfg = root.dataset;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const timerEl = document.getElementById('timer');
    const saveEl = document.getElementById('save-state');
    const answeredEl = document.getElementById('answered-count');
    const blankEl = document.getElementById('blank-count');
    const sheet = document.getElementById('answer-sheet');
    const storageKey = `aura.sheet.${cfg.sessionId}`;

    let status = cfg.status;
    let remaining = cfg.remaining === '' ? null : Number(cfg.remaining);
    let dirty = new Map();
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

    const render = () => {
        if (timerEl) {
            timerEl.textContent = remaining === null ? '--:--:--' : hms(remaining);
            timerEl.classList.toggle('text-danger', remaining !== null && remaining < 600);
            timerEl.classList.toggle('text-warning', remaining !== null && remaining >= 600 && remaining < 1800);
        }
        const bubbles = sheet.querySelectorAll('.bubble[aria-pressed="true"]');
        const total = sheet.querySelectorAll('[data-question]').length;
        if (answeredEl) answeredEl.textContent = bubbles.length;
        if (blankEl) blankEl.textContent = total - bubbles.length;
    };

    const persistLocal = () => {
        try {
            const marks = {};
            sheet.querySelectorAll('[data-question]').forEach((row) => {
                const on = row.querySelector('.bubble[aria-pressed="true"]');
                marks[row.dataset.question] = on ? on.dataset.option : null;
            });
            localStorage.setItem(storageKey, JSON.stringify(marks));
        } catch {}
    };

    const flush = async () => {
        if (flushing || dirty.size === 0 || status !== 'IN_PROGRESS') return;
        flushing = true;
        const batch = [...dirty.entries()].map(([question_id, option]) => ({ question_id: Number(question_id), option }));
        dirty.clear();
        saveEl.textContent = 'Salvando…';
        try {
            const r = await api(cfg.answersUrl, { answers: batch });
            remaining = r.remaining_seconds;
            saveEl.textContent = 'Cartão salvo';
        } catch (e) {
            for (const b of batch) if (!dirty.has(String(b.question_id))) dirty.set(String(b.question_id), b.option);
            saveEl.textContent = e.status === 422 || e.status === 403 ? e.message : 'Sem conexão — salvo localmente';
            if (e.status === 422 && /encerrad|esgotad/i.test(e.message)) window.location.href = cfg.resultUrl;
        } finally {
            flushing = false;
        }
    };

    sheet.addEventListener('click', (e) => {
        const b = e.target.closest('.bubble');
        if (!b || status !== 'IN_PROGRESS') return;
        const row = b.closest('[data-question]');
        const wasOn = b.getAttribute('aria-pressed') === 'true';
        row.querySelectorAll('.bubble').forEach((x) => x.setAttribute('aria-pressed', 'false'));
        if (!wasOn) b.setAttribute('aria-pressed', 'true');
        dirty.set(row.dataset.question, wasOn ? null : b.dataset.option);
        saveEl.textContent = 'Alterações pendentes';
        persistLocal();
        render();
    });

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
            const msg = `Encerrar a prova agora?\n\n${blank} questão(ões) em branco.\n${cfg.hasEssay === '1' ? 'Após encerrar você poderá enviar a redação para avaliação.\n' : ''}Esta ação não pode ser desfeita.`;
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
        p = Math.min(pageCount, Math.max(1, p));
        pageInput.value = p;
        frame.src = `${cfg.pdfUrl}#page=${p}&toolbar=0&navpanes=0&view=FitH`;
    };
    document.getElementById('pdf-prev')?.addEventListener('click', () => goto(Number(pageInput.value) - 1));
    document.getElementById('pdf-next')?.addEventListener('click', () => goto(Number(pageInput.value) + 1));
    pageInput?.addEventListener('change', () => goto(Number(pageInput.value) || 1));
    document.querySelectorAll('[data-goto-page]').forEach((el) => el.addEventListener('click', () => goto(Number(el.dataset.gotoPage))));

    render();
}
