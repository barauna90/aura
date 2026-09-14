/**
 * Folha de redação: autosave do rascunho no servidor (5 s) e no navegador,
 * contagem de linhas, sem corretor/IA/sugestões.
 */
const editor = document.getElementById('essay-editor');
if (editor) {
    const cfg = editor.dataset;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const sheet = document.getElementById('essay-sheet');
    const draft = document.getElementById('essay-draft');
    const linesEl = document.getElementById('essay-lines');
    const saveEl = document.getElementById('essay-save-state');
    const submitBtn = document.getElementById('essay-submit');
    const maxLines = Number(cfg.maxLines || 30);
    const localKey = `aura.essay.draft.${cfg.essayId}`;
    let lastSaved = sheet.value;

    try {
        if (draft && !draft.value) draft.value = localStorage.getItem(localKey) || '';
    } catch {}

    const lines = () => sheet.value.split('\n').filter((l) => l.trim()).length;
    const render = () => {
        const n = lines();
        linesEl.textContent = `${n} / ${maxLines} linhas`;
        linesEl.classList.toggle('text-danger', n > maxLines);
        if (submitBtn) submitBtn.disabled = n === 0 || n > maxLines;
    };

    const save = async () => {
        if (sheet.value === lastSaved) return;
        saveEl.textContent = 'Salvando…';
        try {
            const r = await fetch(cfg.draftUrl, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                body: JSON.stringify({ text: sheet.value }),
            });
            if (!r.ok) throw new Error((await r.json()).message || 'Erro ao salvar');
            lastSaved = sheet.value;
            saveEl.textContent = 'Salvo automaticamente';
        } catch (e) {
            saveEl.textContent = e.message;
        }
    };

    sheet.addEventListener('input', () => {
        saveEl.textContent = 'Alterações pendentes';
        render();
    });
    draft?.addEventListener('input', () => {
        try {
            localStorage.setItem(localKey, draft.value);
        } catch {}
    });
    setInterval(save, 5000);
    window.addEventListener('beforeunload', save);
    document.getElementById('essay-save-now')?.addEventListener('click', save);

    document.querySelectorAll('[data-tab]').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('[data-tab]').forEach((b) => b.setAttribute('aria-selected', String(b === btn)));
            document.querySelectorAll('[data-panel]').forEach((p) => (p.hidden = p.dataset.panel !== btn.dataset.tab));
        });
    });
    render();
}
