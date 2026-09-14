/** Verificação de cupom (sem recarregar) e polling do status do pagamento. */
const couponBtn = document.getElementById('coupon-check');
if (couponBtn) {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    couponBtn.addEventListener('click', async () => {
        const form = couponBtn.closest('form');
        const code = form.querySelector('[name=coupon]').value.trim();
        const plan = form.querySelector('[name=plan]:checked')?.value;
        const out = document.getElementById('coupon-result');
        if (!code || !plan) {
            out.textContent = 'Escolha um plano e informe o cupom.';
            return;
        }
        const r = await fetch(couponBtn.dataset.url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
            body: JSON.stringify({ coupon: code, plan }),
        }).then((x) => x.json());
        out.className = r.valid ? 'notice-success' : 'notice-danger';
        out.textContent = r.valid
            ? `Cupom válido: desconto de R$ ${(r.discount_cents / 100).toFixed(2).replace('.', ',')}${r.trial_days ? ` · ${r.trial_days} dias grátis` : ''}`
            : r.reason;
        out.hidden = false;
    });
}

// Página de pagamento: recarrega quando o webhook confirmar.
const waiting = document.getElementById('payment-waiting');
if (waiting) {
    setInterval(() => window.location.reload(), 15000);
}
