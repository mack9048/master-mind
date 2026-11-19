import { useEffect, useRef, useState } from 'react';
import qrcode from 'qrcode-generator';
import { EVENT, PROFESSIONS, telLink, waLink } from '../config.js';
import { postForm } from '../hooks.js';
import { Reveal } from './Sections.jsx';

const EMPTY = { name: '', phone: '', email: '', city: '', profession: '', organization: '', expectation: '', website: '' };

function validate(f) {
  const e = {};
  if (f.name.trim().length < 2) e.name = 'Please enter your full name.';
  const ph = f.phone.replace(/\D/g, '').replace(/^(91|0)(?=\d{10}$)/, '');
  if (!/^[6-9]\d{9}$/.test(ph)) e.phone = 'Enter a valid 10-digit mobile number.';
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email.trim())) e.email = 'Enter a valid email.';
  if (f.city.trim().length < 2) e.city = 'Enter your city / place.';
  if (!f.profession) e.profession = 'Choose one.';
  return e;
}

function Field({ id, label, optional, error, children }) {
  return (
    <div className={`field${error ? ' has-err' : ''}`}>
      <label htmlFor={id}>{label} {optional && <span className="opt">optional</span>}</label>
      {children}
      {error && <small className="field__err">{error}</small>}
    </div>
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

function Done({ code, onClose }) {
  return (
    <>
      <div className="ok-icon">✓</div>
      <h3 id="modalTitle">Registration received!</h3>
      <p>We’ll verify your payment and confirm your seat on WhatsApp shortly.</p>
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

  return (
    <section className="section section--register" id="register">
      <div className="wrap reg">
        <Reveal className="reg__info">
          <p className="kicker">Booking</p>
          <h2 className="section__title">Reserve your seat <span className="serif">for ₹{EVENT.fee}.</span></h2>
          <p className="section__lead">Fill in your details and pay by UPI. Your registration ID confirms your seat — keep it handy on the day.</p>
          <ul className="reg__points">
            <li>Seats are limited and confirmed on payment</li>
            <li>Pay instantly by scanning the UPI QR code</li>
            <li>Questions? Call <a className="link" href={telLink}>{EVENT.phone}</a></li>
          </ul>
          {seatsLeft != null && !soldOut && (
            <div className="seatbar" style={{ '--fill': `${Math.round(100 * (1 - seatsLeft / Math.max(1, status.seats)))}%` }}>
              <div className="seatbar__track"><span /></div>
              <p><strong>{seatsLeft}</strong> seats left</p>
            </div>
          )}
        </Reveal>

        <Reveal className="reg__card">
          {closed ? (
            <div className="reg__closed"><h3>Registrations closed</h3><p>This edition has started. Follow MAC Media School for the next Master Mind.</p></div>
          ) : soldOut ? (
            <div className="reg__closed"><h3>Houseful!</h3><p>All seats are booked. Message us on <a className="link" href={waLink('Hi, please add me to the Master Mind waitlist')} target="_blank" rel="noopener noreferrer">WhatsApp</a> to join the waitlist.</p></div>
          ) : (
            <form ref={formRef} className="form" onSubmit={submit} noValidate>
              <div className="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabIndex={-1} autoComplete="off" value={f.website} onChange={set('website')} /></label></div>

              <Field id="f-name" label="Full name" error={errors.name}>
                <input id="f-name" name="name" type="text" autoComplete="name" maxLength={120} value={f.name} onChange={set('name')} />
              </Field>
              <div className="row">
                <Field id="f-phone" label="Mobile (WhatsApp)" error={errors.phone}>
                  <div className="phone"><span>+91</span><input id="f-phone" name="phone" type="tel" inputMode="numeric" autoComplete="tel-national" maxLength={14} placeholder="98XXXXXXXX" value={f.phone} onChange={set('phone')} /></div>
                </Field>
                <Field id="f-email" label="Email" error={errors.email}>
                  <input id="f-email" name="email" type="email" autoComplete="email" maxLength={160} value={f.email} onChange={set('email')} />
                </Field>
              </div>
              <div className="row">
                <Field id="f-city" label="City / Place" error={errors.city}>
                  <input id="f-city" name="city" type="text" autoComplete="address-level2" maxLength={80} value={f.city} onChange={set('city')} />
                </Field>
                <Field id="f-prof" label="You are a" error={errors.profession}>
                  <select id="f-prof" name="profession" value={f.profession} onChange={set('profession')}>
                    <option value="">Choose one</option>
                    {PROFESSIONS.map((p) => <option key={p}>{p}</option>)}
                  </select>
                </Field>
              </div>
              <Field id="f-org" label="College / Company" optional error={errors.organization}>
                <input id="f-org" name="organization" type="text" maxLength={160} value={f.organization} onChange={set('organization')} />
              </Field>
              <Field id="f-exp" label="What do you want to learn?" optional error={errors.expectation}>
                <textarea id="f-exp" name="expectation" rows={3} maxLength={1000} value={f.expectation} onChange={set('expectation')} />
              </Field>

              <div className="form__foot">
                <div className="form__total"><span>Total</span><strong>₹{EVENT.fee}</strong></div>
                <button type="submit" className={`btn btn--primary btn--lg btn--block${busy ? ' is-loading' : ''}`}>
                  <span className="btn__label">Register Now</span><span className="btn__spin" aria-hidden="true" />
                </button>
                {msg && <p className="form__msg" role="alert">{msg}</p>}
                <p className="form__note">By registering you agree to be contacted about this workshop on WhatsApp / phone.</p>
              </div>
            </form>
          )}
        </Reveal>
      </div>

      {reg && (
        <Modal onClose={closeModal}>
          {paid ? <Done code={reg.reg_code} onClose={closeModal} /> : <PayStep reg={reg} onPaid={() => setPaid(true)} />}
        </Modal>
      )}
    </section>
  );
}
