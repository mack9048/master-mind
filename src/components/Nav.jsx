import { useEffect, useState } from 'react';
import { EVENT, telLink, waLink } from '../config.js';

const LINKS = [
  ['#learn', 'Workshop'],
  ['#speaker', 'Speaker'],
  ['#details', 'Details'],
  ['#register', 'Book a seat'],
  ['#faq', 'FAQ'],
];

export default function Nav() {
  const [scrolled, setScrolled] = useState(false);
  const [open, setOpen] = useState(false);

  useEffect(() => {
    const on = () => setScrolled(window.scrollY > 10);
    on();
    window.addEventListener('scroll', on, { passive: true });
    return () => window.removeEventListener('scroll', on);
  }, []);

  useEffect(() => {
    document.body.classList.toggle('menu-open', open);
    const onKey = (e) => e.key === 'Escape' && setOpen(false);
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [open]);

  const close = () => setOpen(false);

  return (
    <>
      <header className={`nav${scrolled ? ' is-scrolled' : ''}${open ? ' is-menu' : ''}`} id="top">
        <div className="wrap nav__in">
          <a href="#top" className="nav__brand" aria-label="Master Mind – Meet the Expert" onClick={close}>
            <img src="/img/mastermind-logo.png" alt="Master Mind – Meet the Expert" width="132" height="50" />
          </a>
          <div className="nav__actions">
            <a href="#register" className="btn btn--sm btn--primary" onClick={close}>Book your seat</a>
            <button
              type="button"
              className={`burger${open ? ' is-open' : ''}`}
              aria-label={open ? 'Close menu' : 'Open menu'}
              aria-expanded={open}
              aria-controls="site-menu"
              onClick={() => setOpen((o) => !o)}
            >
              <span /><span /><span />
            </button>
          </div>
        </div>
      </header>

      <nav id="site-menu" className={`menu${open ? ' is-open' : ''}`} aria-hidden={!open} aria-label="Site">
        <div className="menu__in">
          {LINKS.map(([href, label], i) => (
            <a key={href} href={href} className="menu__link" onClick={close} tabIndex={open ? 0 : -1}>
              <small>0{i + 1}</small>{label}
            </a>
          ))}
          <div className="menu__foot">
            <span>{EVENT.dateLabel} · {EVENT.timeLabel}</span>
            <a href={telLink} tabIndex={open ? 0 : -1}>{EVENT.phone}</a>
            <a href={waLink('Hi, I have a question about Master Mind')} target="_blank" rel="noopener noreferrer" tabIndex={open ? 0 : -1}>WhatsApp</a>
          </div>
        </div>
      </nav>
    </>
  );
}
