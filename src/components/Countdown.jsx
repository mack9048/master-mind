import { useEffect, useState } from 'react';
import { EVENT } from '../config.js';

const pad = (n) => String(n).padStart(2, '0');

function parts(target) {
  let diff = Math.max(0, target - Date.now());
  const d = Math.floor(diff / 864e5); diff -= d * 864e5;
  const h = Math.floor(diff / 36e5); diff -= h * 36e5;
  const m = Math.floor(diff / 6e4); diff -= m * 6e4;
  return { live: target <= Date.now(), d: pad(d), h: pad(h), m: pad(m), s: pad(Math.floor(diff / 1e3)) };
}

function Unit({ value, label }) {
  // key={value} remounts the number so the tick animation replays on change
  return (
    <div className="cd">
      <span className="cd__n tick" key={value}>{value}</span>
      <span className="cd__l">{label}</span>
    </div>
  );
}

export default function Countdown({ status }) {
  const target = new Date(EVENT.startsAt).getTime();
  const [t, setT] = useState(() => parts(target));
  useEffect(() => {
    const id = setInterval(() => setT(parts(target)), 1000);
    return () => clearInterval(id);
  }, [target]);

  return (
    <div className="wrap">
      <div className={`countdown${t.live ? ' is-live' : ''}`}>
        <div className="countdown__label">
          <span className="countdown__kicker">{t.live ? 'The workshop is live' : 'Workshop starts in'}</span>
          <span className="countdown__date">{EVENT.dateLabel} · {EVENT.timeLabel}</span>
        </div>
        {t.live ? (
          <div className="countdown__units">See you at the venue!</div>
        ) : (
          <div className="countdown__units" role="timer">
            <Unit value={t.d} label="Days" />
            <Unit value={t.h} label="Hours" />
            <Unit value={t.m} label="Minutes" />
            <Unit value={t.s} label="Seconds" />
          </div>
        )}
        {status?.seats > 0 && status.seats_left != null && (
          <div className="countdown__seats"><strong>{status.seats_left}</strong> of {status.seats} seats left</div>
        )}
      </div>
    </div>
  );
}
