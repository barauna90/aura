/** Escolha de plano com resumo ao vivo, verificação de cupom e polling do pagamento. */
const brl = (c) => `R$ ${(c / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`;
const checkout = document.getElementById('checkout-form');
if (checkout) {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const referral = Number(checkout.dataset.referralDiscount || 0);
    let coupon = { code: null, discount_cents: 0 };
    const q = (k) => checkout.querySelector(`[data-summary="${k}"]`);
    const row = (k, show) => (checkout.querySelector(`[data-summary-row="${k}"]`).hidden = !show);

    // Recalcula o resumo sempre que o plano (ou o cupom) muda.
    const render = () => {
        const sel = checkout.querySelector('[name=plan]:checked');
        if (!sel) return;
        const price = Number(sel.dataset.price);
        const ref = Math.min(price, referral);
        const cup = Math.min(Math.max(0, price - ref), coupon.discount_cents);
        q('name').textContent = sel.dataset.name;
        q('price').textContent = brl(price);
        q('referral').textContent = `− ${brl(ref)}`;
        q('coupon').textContent = `− ${brl(cup)}`;
        row('referral', ref > 0);
        row('coupon', cup > 0);
        q('total').textContent = brl(Math.max(0, price - ref - cup));
        q('recurring').textContent = brl(price);
        q('button').textContent = `${sel.dataset.name} por ${brl(Math.max(0, price - ref - cup))}`;
    };

    const couponBtn = document.getElementById('coupon-check');
    const out = document.getElementById('coupon-result');
    const checkCoupon = async () => {
        const code = checkout.querySelector('[name=coupon]').value.trim();
        const plan = checkout.querySelector('[name=plan]:checked')?.value;
        if (!code || !plan) {
            coupon = { code: null, discount_cents: 0 };
            out.hidden = true;
            render();
            return;
        }
        const r = await fetch(couponBtn.dataset.url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
            body: JSON.stringify({ coupon: code, plan }),
        }).then((x) => x.json());
        coupon = r.valid ? { code, discount_cents: r.discount_cents } : { code: null, discount_cents: 0 };
        out.className = r.valid ? 'notice-success' : 'notice-danger';
        out.textContent = r.valid
            ? `Cupom válido: desconto de ${brl(r.discount_cents)}${r.trial_days ? ` · ${r.trial_days} dias grátis` : ''}`
            : r.reason;
        out.hidden = false;
        render();
    };
    couponBtn?.addEventListener('click', checkCoupon);
    checkout.querySelectorAll('[name=plan]').forEach((el) =>
        el.addEventListener('change', () => (coupon.code ? checkCoupon() : render())),
    );
    render();
}

// Página de pagamento: recarrega quando o webhook confirmar.
const waiting = document.getElementById('payment-waiting');
if (waiting) {
    setInterval(() => window.location.reload(), 15000);
}
