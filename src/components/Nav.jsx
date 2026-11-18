import { useEffect, useState } from 'react';
import { EVENT } from '../config.js';

export default function Nav() {
  const [scrolled, setScrolled] = useState(false);
  useEffect(() => {
    const on = () => setScrolled(window.scrollY > 10);
    on();
    window.addEventListener('scroll', on, { passive: true });
    return () => window.removeEventListener('scroll', on);
  }, []);
  return (
    <header className={`nav${scrolled ? ' is-scrolled' : ''}`} id="top">
      <div className="wrap nav__in">
        <a href="#top" className="nav__brand" aria-label="Master Mind – Meet the Expert">
          <img src="/img/mastermind-logo.png" alt="Master Mind – Meet the Expert" width="132" height="50" />
        </a>
        <nav className="nav__links" aria-label="Sections">
          <a href="#learn">Workshop</a>
          <a href="#speaker">Speaker</a>
          <a href="#details">Details</a>
          <a href="#faq">FAQ</a>
        </nav>
        <a href="#register" className="btn btn--sm btn--pink">Book · ₹{EVENT.fee}</a>
      </div>
    </header>
  );
}
