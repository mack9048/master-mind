import { postForm } from './hooks.js';

const CHECKOUT_SRC = 'https://checkout.razorpay.com/v1/checkout.js';
let loading = null;

/** Load Razorpay Checkout once (same as the <script> tag in the docs, but only when needed). */
export function loadRazorpay() {
  if (window.Razorpay) return Promise.resolve(true);
  if (!loading) {
    loading = new Promise((resolve) => {
      const s = document.createElement('script');
      s.src = CHECKOUT_SRC;
      s.async = true;
      s.onload = () => resolve(!!window.Razorpay);
      s.onerror = () => { loading = null; resolve(false); };
      document.body.appendChild(s);
    });
  }
  return loading;
}

/**
 * 1. ask the PHP backend to create an order   (POST /api/create-order.php)
 * 2. open the Razorpay modal with that order_id
 * 3. send the three razorpay_* values to       (POST /api/verify-payment.php)
 * Resolves to { status: 'paid' | 'dismissed' | 'failed' | 'error', message? }
 */
export async function payWithRazorpay(reg) {
  const ready = await loadRazorpay();
  if (!ready) return { status: 'error', message: 'Could not load the payment window. Check your internet connection and try again.' };

  const order = await postForm('/api/create-order.php', { reg_code: reg.reg_code, token: reg.token });
  if (!order.ok) {
    if (order.already_paid) return { status: 'paid' };
    return { status: 'error', message: order.error || 'Could not start the payment.' };
  }

  return new Promise((resolve) => {
    let settled = false;
    const done = (r) => { if (!settled) { settled = true; resolve(r); } };
    let lastFailure = '';

    const rzp = new window.Razorpay({
      key: order.key_id,
      order_id: order.order_id,
      amount: order.amount,
      currency: order.currency,
      name: order.name,
      description: order.description,
      image: `${window.location.origin}/img/favicon.png`,
      prefill: order.prefill,
      notes: { reg_code: reg.reg_code },
      theme: { color: '#f9c816' },
      handler: async (resp) => {
        const v = await postForm('/api/verify-payment.php', {
          reg_code: reg.reg_code,
          token: reg.token,
          razorpay_order_id: resp.razorpay_order_id,
          razorpay_payment_id: resp.razorpay_payment_id,
          razorpay_signature: resp.razorpay_signature,
        });
        done(v.ok ? { status: 'paid', paymentId: resp.razorpay_payment_id } : { status: 'error', message: v.error || 'Payment could not be verified.' });
      },
      modal: {
        ondismiss: () => done(lastFailure ? { status: 'failed', message: lastFailure } : { status: 'dismissed' }),
      },
    });

    rzp.on('payment.failed', (resp) => {
      // Razorpay keeps its modal open so the user can retry; remember the reason in case they close it
      lastFailure = resp?.error?.description || 'Payment failed. Please try again.';
    });

    rzp.open();
  });
}
