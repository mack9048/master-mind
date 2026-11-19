import { useEffect, useRef, useState } from 'react';
import { EVENT, waLink } from '../config.js';
import { reduceMotion } from '../hooks.js';
import PixelTitle from './PixelTitle.jsx';
import Countdown from './Countdown.jsx';

const Icon = {
  cal: <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" /></svg>,
  clock: <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>,
  pin: <svg viewBox="0 0 24 24"><path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12z" /><circle cx="12" cy="10" r="2.5" /></svg>,
};

export default function Hero({ status }) {
  const titleRef = useRef(null);
  const pinkRef = useRef(null);
  const [cursor, setCursor] = useState({ x: 0, y: 0, moving: false, click: false });
  const [on, setOn] = useState(reduceMotion);

  // Animated cursor glides to "Branding" and "clicks" — the highlight boxes sweep in
  useEffect(() => {
    if (reduceMotion) return;
    const place = (offset) => {
      const tr = titleRef.current.getBoundingClientRect();
      const pr = pinkRef.current.getBoundingClientRect();
      return { x: pr.right - tr.left - 6 + offset.x, y: pr.bottom - tr.top - pr.height * 0.45 + offset.y };
    };
    const start = () => {
      setCursor({ ...place({ x: 220, y: 160 }), moving: false, click: false });
      requestAnimationFrame(() => requestAnimationFrame(() => setCursor({ ...place({ x: 0, y: 0 }), moving: true, click: false })));
      t2 = setTimeout(() => { setCursor((c) => ({ ...c, click: true })); setOn(true); }, 1150);
    };
    let t2;
    const t1 = setTimeout(start, 300);
    const onResize = () => setCursor((c) => (c.moving ? { ...place({ x: 0, y: 0 }), moving: true, click: c.click, instant: true } : c));
    window.addEventListener('resize', onResize);
    return () => { clearTimeout(t1); clearTimeout(t2); window.removeEventListener('resize', onResize); };
  }, []);

  return (
    <section className="hero">

      <div className="wrap hero__grid">
        <div className="hero__copy">
          <p className="eyebrow"><span className="dot" />{EVENT.series} <i>·</i> {EVENT.tagline} <i>·</i> One Day Workshop</p>

          <h1 className="hero__title" ref={titleRef}>
            <span className={`hl hl--blue${on ? ' is-on' : ''}`}><span>Explore</span></span>
            <span className={`hl hl--pink${on ? ' is-on' : ''}`} ref={pinkRef}><span>Branding</span></span>
            <svg
              className={`hero__cursor${cursor.moving ? ' is-moving' : ''}${cursor.click ? ' is-click' : ''}`}
              style={{ transform: `translate(${cursor.x}px, ${cursor.y}px)`, transition: cursor.instant ? 'none' : undefined }}
              viewBox="0 0 24 24" aria-hidden="true"
            >
              <path d="M4 2l15 11.2-6.6.9 3.9 7.6-3 1.5-3.9-7.7L4 20z" fill="#fff" stroke="#111" strokeWidth="1.4" strokeLinejoin="round" />
            </svg>
          </h1>
          <p className="hero__with">with <strong>{EVENT.speaker}</strong></p>

          <ul className="hero__meta">
            <li>{Icon.cal}{EVENT.dateLabel}</li>
            <li>{Icon.clock}{EVENT.timeLabel}</li>
            <li>{Icon.pin}{EVENT.venue}</li>
          </ul>

          <div className="hero__cta">
            <a href="#register" className="btn btn--primary btn--lg">Book your seat <span className="btn__price">₹{EVENT.fee}</span></a>
            <a href={waLink(`Hi, I want to know more about Master Mind – ${EVENT.title}`)} className="btn btn--ghost" target="_blank" rel="noopener noreferrer">Ask on WhatsApp</a>
          </div>
        </div>

        <div className="hero__visual">
          <div className="hero__glow" aria-hidden="true" />
          <img className="hero__photo" src={EVENT.speakerPhoto} alt={EVENT.speaker} width="1000" height="1333" fetchpriority="high" />
          <div className="hero__badge">
            <span className="hero__badge-name">{EVENT.speaker}</span>
            <span className="hero__badge-role">{EVENT.speakerRole}</span>
          </div>
        </div>
      </div>

      <PixelTitle />
      <Countdown status={status} />
    </section>
  );
}
