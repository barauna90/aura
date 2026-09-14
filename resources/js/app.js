import './exam-runner.js';
import './essay-editor.js';
import './subscription.js';

// Menu mobile e utilidades pequenas (sem framework).
document.addEventListener('click', (e) => {
    const toggle = e.target.closest('[data-toggle]');
    if (toggle) {
        const target = document.getElementById(toggle.dataset.toggle);
        if (target) {
            const open = target.toggleAttribute('hidden');
            toggle.setAttribute('aria-expanded', String(!open));
        }
    }
    const copy = e.target.closest('[data-copy]');
    if (copy && navigator.clipboard) {
        navigator.clipboard.writeText(copy.dataset.copy).then(() => {
            const original = copy.textContent;
            copy.textContent = 'Copiado!';
            setTimeout(() => (copy.textContent = original), 1500);
        });
    }
});

// Confirmação para formulários destrutivos: <form data-confirm="Texto">
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
        e.preventDefault();
    }
});
