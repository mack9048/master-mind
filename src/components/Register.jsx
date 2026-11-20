import { useEffect, useRef, useState } from 'react';
import qrcode from 'qrcode-generator';
import { EVENT, PROFESSIONS, telLink, waLink } from '../config.js';
import { postForm } from '../hooks.js';
import { payWithRazorpay, loadRazorpay } from '../razorpay.js';
import { Reveal } from './Sections.jsx';

const EMPTY = { name: '', phone: '', email: '', profession: '', website: '' };

function validate(f) {
  const e = {};
  if (f.name.trim().length < 2) e.name = 'Please enter your full name.';
  const ph = f.phone.replace(/\D/g, '').replace(/^(91|0)(?=\d{10}$)/, '');
  if (!/^[6-9]\d{9}$/.test(ph)) e.phone = 'Enter a valid 10-digit mobile number.';
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email.trim())) e.email = 'Enter a valid email.';
  if (!f.profession) e.profession = 'Choose your current status.';
  return e;
}

function Field({ id, label, optional, required, error, children, as: Tag = 'div' }) {
  return (
    <Tag className={`field${error ? ' has-err' : ''}`}>
      {Tag === 'fieldset'
        ? <legend>{label}{required && <i className="req">*</i>}</legend>
        : <label htmlFor={id}>{label}{required && <i className="req">*</i>} {optional && <span className="opt">optional</span>}</label>}
      {children}
      {error && <small className="field__err">{error}</small>}
    </Tag>
  );
}

function Modal({ onClose, children }) {
  useEffect(() => {
    const onKey = (e) => e.key === 'Escape' && onClose();
    document.addEventListener('keydown', onKey);
    document.body.style.overflow = 'hidden';
    return () => { document.removeEventListener('keydown', onKey); document.body.style.overflow = ''; };
  }, [onClose]);
  return (
    <div className="modal" onClick={(e) => e.target === e.currentTarget && onClose()}>
      <div className="modal__box" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <button className="modal__x" type="button" onClick={onClose} aria-label="Close">×</button>
        <div className="modal__body">{children}</div>
      </div>
    </div>
  );
}

function UpiQr({ upi }) {
  if (upi.qr) return <img src={upi.qr} alt={`UPI QR code to pay ₹${upi.amount}`} />;
  if (!upi.link) return null;
  const q = qrcode(0, 'M');
  q.addData(upi.link);
  q.make();
  return <div dangerouslySetInnerHTML={{ __html: q.createSvgTag({ cellSize: 6, margin: 0, scalable: true }) }} />;
}

function PayStep({ reg, onPaid }) {
  const { upi } = reg;
  const [utr, setUtr] = useState('');
  const [err, setErr] = useState('');
  const [busy, setBusy] = useState(false);
  const isMobile = /Android|iPhone|iPad/i.test(navigator.userAgent);
  const hasQR = !!(upi.qr || upi.link);

  async function submit(e) {
    e.preventDefault();
    setErr(''); setBusy(true);
    const r = await postForm('/api/payment.php', { action: 'upi_ref', reg_code: reg.reg_code, token: reg.token, payment_ref: utr });
    setBusy(false);
    if (r.ok) onPaid(); else setErr(r.error || 'Could not save. Try again.');
  }

  return (
    <>
      <h3 id="modalTitle">Scan &amp; pay ₹{upi.amount}</h3>
      <p>Your seat is reserved. Scan this QR with GPay, PhonePe, Paytm or any UPI app, then enter the transaction ID below.</p>
      <span className="code">{reg.reg_code}</span>
      {hasQR ? (
        <div className="qr">
          <div className="qr__box"><UpiQr upi={upi} /></div>
          <p className="qr__cap">Pay <b>₹{upi.amount}</b> to <b>{upi.name}</b>{upi.id && <><br /><small>{upi.id}</small></>}</p>
        </div>
      ) : (
        <p className="qr__cap">Our team will send you the payment QR on WhatsApp shortly.</p>
      )}
      {isMobile && upi.link && <a className="btn btn--ghost btn--block" href={upi.link}>Open UPI app to pay ₹{upi.amount}</a>}
      <form className="form" onSubmit={submit} style={{ marginTop: 16 }}>
        <Field id="utr" label="UPI transaction / UTR number" error={err}>
          <input id="utr" value={utr} onChange={(e) => setUtr(e.target.value)} inputMode="numeric" autoComplete="off" required maxLength={40} placeholder="12-digit UTR" autoFocus />
        </Field>
        <button className={`btn btn--primary btn--block${busy ? ' is-loading' : ''}`} type="submit">
          <span className="btn__label">Confirm payment</span><span className="btn__spin" />
        </button>
      </form>
    </>
  );
}

function RazorpayStep({ reg, onPaid }) {
  const [state, setState] = useState({ busy: true, msg: '' });

  async function pay() {
    setState({ busy: true, msg: '' });
    const r = await payWithRazorpay(reg);
    if (r.status === 'paid') { onPaid(); return; }
    setState({
      busy: false,
      msg: r.status === 'dismissed' ? 'Payment cancelled. Your seat is held — tap Pay to try again.' : r.message,
    });
  }

  // Open Razorpay straight after registering (ref guard: StrictMode runs effects twice in dev)
  const started = useRef(false);
  useEffect(() => {
    if (started.current) return;
    started.current = true;
    pay();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <>
      <h3 id="modalTitle">Complete your payment</h3>
      <p>Your seat is reserved. Pay ₹{reg.amount ?? EVENT.fee} securely with UPI, card, net banking or wallet.</p>
      <span className="code">{reg.reg_code}</span>
      {state.msg && <p className="form__msg" role="alert">{state.msg}</p>}
      <button className={`btn btn--primary btn--block${state.busy ? ' is-loading' : ''}`} type="button" onClick={pay} disabled={state.busy}>
        <span className="btn__label">{state.busy ? 'Opening payment…' : `Pay ₹${reg.amount ?? EVENT.fee}`}</span><span className="btn__spin" />
      </button>
      <p className="form__note" style={{ textAlign: 'center', marginTop: 12 }}>Secured by Razorpay · Questions? <a className="link" href={telLink}>{EVENT.phone}</a></p>
    </>
  );
}

function Done({ code, onClose, confirmed }) {
  return (
    <>
      <div className="ok-icon">✓</div>
      <h3 id="modalTitle">{confirmed ? 'You’re in! Seat confirmed.' : 'Registration received!'}</h3>
      <p>{confirmed ? 'Your payment was successful.' : 'We’ll verify your payment and confirm your seat on WhatsApp shortly.'}</p>
      <span className="code">{code}</span>
      <p>Save this registration ID and show it at the venue.<br />{EVENT.dateLabel} · {EVENT.timeLabel}</p>
      <div className="stack">
        <a className="btn btn--ghost" href={waLink(`Hi, I registered for Master Mind. My registration ID is ${code}`)} target="_blank" rel="noopener noreferrer">Share ID on WhatsApp</a>
        <button className="btn btn--primary" type="button" onClick={onClose}>Done</button>
      </div>
    </>
  );
}

export default function Register({ status }) {
  const [f, setF] = useState(EMPTY);
  const [errors, setErrors] = useState({});
  const [msg, setMsg] = useState('');
  const [busy, setBusy] = useState(false);
  const [reg, setReg] = useState(null);       // { reg_code, token, upi }
  const [paid, setPaid] = useState(false);
  const formRef = useRef(null);

  const seatsLeft = status?.seats > 0 ? status.seats_left : null;
  const closed = status && !status.open;
  const soldOut = seatsLeft !== null && seatsLeft !== undefined && seatsLeft <= 0;

  // Warm up Razorpay Checkout while the visitor fills the form
  useEffect(() => {
    const el = formRef.current;
    if (!el) return;
    const warm = () => { loadRazorpay(); el.removeEventListener('focusin', warm); };
    el.addEventListener('focusin', warm);
    return () => el.removeEventListener('focusin', warm);
  }, []);

  const set = (k) => (e) => {
    setF((s) => ({ ...s, [k]: e.target.value }));
    if (errors[k]) setErrors((x) => ({ ...x, [k]: undefined }));
  };

  function focusFirst(errs) {
    const k = Object.keys(errs)[0];
    k && formRef.current?.elements[k]?.focus();
  }

  async function submit(e) {
    e.preventDefault();
    setMsg('');
    const errs = validate(f);
    setErrors(errs);
    if (Object.keys(errs).length) { focusFirst(errs); return; }
    setBusy(true);
    const res = await postForm('/api/register.php', f);
    setBusy(false);
    if (!res.ok) {
      setErrors(res.fields || {});
      setMsg(res.error || 'Something went wrong.');
      focusFirst(res.fields || {});
      return;
    }
    setReg(res);
  }

  const closeModal = () => {
    if (paid) { setF(EMPTY); setReg(null); setPaid(false); } else setReg(null);
  };

  const seatsNote = seatsLeft != null && !soldOut ? ` — ${seatsLeft} seats left` : ' — Limited seats';

  return (
    <section className="section section--register" id="register">
      <div className="wrap book">
        <Reveal className="book__head">
          <p className="book__badge"><span className="dot dot--y" />₹{EVENT.fee} ticket{seatsNote}</p>
          <h2 className="book__title">Secure your<br /><em>Brand</em> seat today.</h2>
          <p className="book__sub">{EVENT.dateLabel} · {EVENT.timeLabel} · {EVENT.venue}</p>
        </Reveal>

        <Reveal className="book__card">
          {closed ? (
            <div className="reg__closed"><h3>Registrations closed</h3><p>This edition has started. Follow MAC Media School for the next Master Mind.</p></div>
          ) : soldOut ? (
            <div className="reg__closed"><h3>Houseful!</h3><p>All seats are booked. Message us on <a className="link" href={waLink('Hi, please add me to the Master Mind waitlist')} target="_blank" rel="noopener noreferrer">WhatsApp</a> to join the waitlist.</p></div>
          ) : (
            <form ref={formRef} className="book__form" onSubmit={submit} noValidate>
              <div className="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabIndex={-1} autoComplete="off" value={f.website} onChange={set('website')} /></label></div>

              <div className="book__row">
                <Field id="f-name" label="Full name" required error={errors.name}>
                  <input id="f-name" name="name" type="text" autoComplete="name" maxLength={120} placeholder="Enter your name" value={f.name} onChange={set('name')} />
                </Field>
                <Field id="f-phone" label="Phone number" required error={errors.phone}>
                  <div className="phone"><span>+91</span><input id="f-phone" name="phone" type="tel" inputMode="numeric" autoComplete="tel-national" maxLength={14} placeholder="00000 00000" value={f.phone} onChange={set('phone')} /></div>
                </Field>
              </div>
              <div className="book__row">
                <Field id="f-email" label="Email address" required error={errors.email}>
                  <input id="f-email" name="email" type="email" autoComplete="email" maxLength={160} placeholder="name@example.com" value={f.email} onChange={set('email')} />
                </Field>
              </div>

              <Field as="fieldset" label="Current status" required error={errors.profession}>
                <div className="chips" role="radiogroup">
                  {PROFESSIONS.map((p) => (
                    <label key={p} className={`chip${f.profession === p ? ' is-on' : ''}`}>
                      <input type="radio" name="profession" value={p} checked={f.profession === p} onChange={set('profession')} />
                      {p}
                    </label>
                  ))}
                </div>
              </Field>

              <button type="submit" className={`btn btn--white btn--block book__submit${busy ? ' is-loading' : ''}`}>
                <span className="btn__label">Continue to payment · ₹{EVENT.fee}</span><span className="btn__spin" aria-hidden="true" />
              </button>
              {msg && <p className="form__msg" role="alert">{msg}</p>}
              <p className="book__note">Secure online payment on the next step · Questions? <a className="link" href={telLink}>{EVENT.phone}</a></p>
            </form>
          )}
        </Reveal>
      </div>

      {reg && (
        <Modal onClose={closeModal}>
          {paid
            ? <Done code={reg.reg_code} onClose={closeModal} confirmed={reg.mode === 'razorpay'} />
            : reg.mode === 'razorpay'
              ? <RazorpayStep reg={reg} onPaid={() => setPaid(true)} />
              : <PayStep reg={reg} onPaid={() => setPaid(true)} />}
        </Modal>
      )}
    </section>
  );
}
