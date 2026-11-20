import { EVENT, LEARN, telLink, waLink } from '../config.js';
import { useReveal } from '../hooks.js';

function Reveal({ as: Tag = 'div', className = '', children, ...rest }) {
  const ref = useReveal();
  return <Tag ref={ref} className={`reveal ${className}`} {...rest}>{children}</Tag>;
}

export function Marquee() {
  const items = (
    <>
      <span>Master Mind</span><b>✦</b><em>Meet the Expert</em><b>✦</b><span>Favour Your Brand</span><b>✦</b>
      <em>Branding Masterclass</em><b>✦</b><span>26 Nov 2026</span><b>✦</b><em>₹{EVENT.fee} only</em><b>✦</b>
    </>
  );
  return (
    <div className="marquee" aria-hidden="true">
      <div className="marquee__track">{items}{items}</div>
    </div>
  );
}

const CARD_COLORS = [
  ['#ff5f8f', '#ffb347'], // pink → orange
  ['#3d8bff', '#22d3ee'], // blue → cyan
  ['#a855f7', '#f472b6'], // purple → pink
  ['#f9c816', '#ff7a18'], // yellow → orange
  ['#22c55e', '#2dd4bf'], // green → teal
  ['#6366f1', '#38bdf8'], // indigo → sky
];

const svg = (d) => (
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">{d}</svg>
);
const CARD_ICONS = [
  svg(<><circle cx="12" cy="12" r="9" /><circle cx="12" cy="12" r="5" /><circle cx="12" cy="12" r="1.5" /></>),            // target
  svg(<><circle cx="8" cy="15" r="4" /><path d="M10.8 12.2 20 3M16 7l3 3M18 5l2 2" /></>),                                  // key
  svg(<><path d="M12 3a9 9 0 1 0 0 18c1.1 0 1.5-.8 1.5-1.6 0-1.3-1-1.6-1-2.9 0-1 .8-1.5 1.8-1.5H17a4 4 0 0 0 4-4C21 6.6 17 3 12 3Z" /><circle cx="7.5" cy="11" r="1" /><circle cx="10" cy="7" r="1" /><circle cx="15" cy="7.5" r="1" /></>), // palette
  svg(<><path d="M4 5h16v11H8l-4 4V5Z" /><path d="M8 9h8M8 12h5" /></>),                                                     // story
  svg(<><path d="m12 3 9 5-9 5-9-5 9-5Z" /><path d="m3 13 9 5 9-5" /></>),                                                  // layers
  svg(<><rect x="9" y="3" width="6" height="11" rx="3" /><path d="M5 11a7 7 0 0 0 14 0M12 18v3" /></>),                     // mic
];

/** Moves the card's glow to follow the pointer */
function spotlight(e) {
  const r = e.currentTarget.getBoundingClientRect();
  e.currentTarget.style.setProperty('--mx', `${e.clientX - r.left}px`);
  e.currentTarget.style.setProperty('--my', `${e.clientY - r.top}px`);
}

export function Learn() {
  return (
    <section className="section" id="learn">
      <div className="wrap">
        <Reveal className="section__head">
          <p className="kicker">The workshop</p>
          <h2 className="section__title">One day to see brands <span className="serif">the way experts do.</span></h2>
          <p className="section__lead">A hands-on branding masterclass for students, designers, marketers and business owners — what makes people choose one brand over another, and how to build one that earns that choice.</p>
        </Reveal>
        <div className="cards">
          {LEARN.map(([title, text], i) => {
            const [c1, c2] = CARD_COLORS[i % CARD_COLORS.length];
            return (
              <Reveal
                as="article"
                className="card"
                key={title}
                style={{ '--c1': c1, '--c2': c2 }}
                onPointerMove={spotlight}
              >
                <span className="card__num" aria-hidden="true">{String(i + 1).padStart(2, '0')}</span>
                <span className="card__icon" aria-hidden="true">{CARD_ICONS[i % CARD_ICONS.length]}</span>
                <h3>{title}</h3>
                <p>{text}</p>
              </Reveal>
            );
          })}
        </div>
      </div>
    </section>
  );
}

export function Speaker() {
  return (
    <section className="section section--alt" id="speaker">
      <div className="wrap speaker">
        <Reveal className="speaker__photo">
          <img src={EVENT.speakerPhoto} alt={EVENT.speaker} loading="lazy" width="1000" height="1333" />
          <span className="speaker__tag">Meet the Expert</span>
        </Reveal>
        <Reveal className="speaker__copy">
          <p className="kicker">Your mentor</p>
          <h2 className="speaker__name">{EVENT.speaker}</h2>
          <p className="speaker__role">{EVENT.speakerRole}</p>
          <p className="section__lead">Favour Francis is a creative director and brand consultant who has spent years shaping how brands look, speak and connect with people. In this edition of Master Mind he opens up his process — the thinking, the craft and the stories behind the brands we all know.</p>
          <div className="speaker__series">
            <img src="/img/mastermind-logo.png" alt="Master Mind" loading="lazy" width="132" height="50" />
            <p><strong>Master Mind</strong> is a series by MAC Media School where industry experts spend a day with learners — sharing real experience, not textbook theory.</p>
          </div>
        </Reveal>
      </div>
    </section>
  );
}

export function Details() {
  return (
    <section className="section" id="details">
      <div className="wrap">
        <Reveal className="details">
          <div className="details__item"><span className="details__k">Date</span><span className="details__v">{EVENT.dayShort}</span><span className="details__s">{EVENT.yearLabel}</span></div>
          <div className="details__item"><span className="details__k">Time</span><span className="details__v">{EVENT.timeLabel}</span><span className="details__s">Full-day workshop</span></div>
          <div className="details__item"><span className="details__k">Venue</span><span className="details__v details__v--sm">{EVENT.venue}</span><a className="details__s link" href={EVENT.venueMap} target="_blank" rel="noopener noreferrer">Open in Maps →</a></div>
          <div className="details__item details__item--fee"><span className="details__k">Fee</span><span className="details__v">₹{EVENT.fee}</span><span className="details__s">Per participant</span></div>
        </Reveal>
      </div>
    </section>
  );
}

export function Faq() {
  const faqs = [
    ['Who can attend?', 'Anyone curious about branding — students, designers, marketers, entrepreneurs and business owners. No prior experience needed.'],
    [`How do I pay the ₹${EVENT.fee} fee?`, `Online, right after filling the form — UPI (GPay, PhonePe, Paytm), debit/credit card, net banking or wallet, secured by Razorpay. Your seat is confirmed instantly.`],
    ['How do I know my seat is confirmed?', 'You get a registration ID (like MM26-XXXXXX) as soon as you register. As soon as the payment succeeds your seat is confirmed. Show the ID at the venue.'],
    ['Where is the venue?', <>{EVENT.venue}. <a className="link" href={EVENT.venueMap} target="_blank" rel="noopener noreferrer">Open in Google Maps</a>.</>],
    ['Is the fee refundable?', `Please contact us on ${EVENT.phone} if you can’t make it — we’ll do our best to help.`],
  ];
  return (
    <section className="section" id="faq">
      <div className="wrap faq">
        <Reveal className="section__head">
          <p className="kicker">FAQ</p>
          <h2 className="section__title">Good to know</h2>
        </Reveal>
        <Reveal className="faq__list">
          {faqs.map(([q, a]) => (
            <details key={q}><summary>{q}</summary><p>{a}</p></details>
          ))}
        </Reveal>
      </div>
    </section>
  );
}

export function Footer() {
  return (
    <footer className="footer">
      <div className="wrap footer__in">
        <div className="footer__brand">
          <img src="/img/mastermind-logo.png" alt="Master Mind" width="132" height="50" loading="lazy" />
          <span className="footer__by">a series by</span>
          <a href={EVENT.website} target="_blank" rel="noopener noreferrer"><img className="footer__mac" src="/img/mac-media-school.png" alt="MAC Media School" width="118" height="51" loading="lazy" /></a>
        </div>
        <div className="footer__contact">
          <a href={telLink}>{EVENT.phone}</a>
          <a href={waLink('Hi, I have a question about Master Mind')} target="_blank" rel="noopener noreferrer">WhatsApp</a>
          <a href={EVENT.website} target="_blank" rel="noopener noreferrer">school.macadz.com</a>
        </div>
        <p className="footer__copy">© {new Date().getFullYear()} MAC Media School. All rights reserved.</p>
      </div>
    </footer>
  );
}

export { Reveal };
