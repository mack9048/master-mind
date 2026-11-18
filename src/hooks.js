import { useEffect, useRef, useState } from 'react';
import { API_URL } from './config.js';

export const reduceMotion = typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/** Adds .is-in when the element scrolls into view. */
export function useReveal() {
  const ref = useRef(null);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const io = new IntersectionObserver(([en]) => {
      if (en.isIntersecting) { el.classList.add('is-in'); io.disconnect(); }
    }, { threshold: 0.15 });
    io.observe(el);
    return () => io.disconnect();
  }, []);
  return ref;
}

/** Live seat count / registration status from the PHP backend. */
export function useEventStatus() {
  const [status, setStatus] = useState(null);
  useEffect(() => {
    let alive = true;
    fetch(`${API_URL}/api/event.php`)
      .then((r) => r.json())
      .then((d) => { if (alive && d.ok) setStatus(d); })
      .catch(() => {});
    return () => { alive = false; };
  }, []);
  return status;
}

export async function postForm(path, data) {
  const body = new FormData();
  Object.entries(data).forEach(([k, v]) => body.append(k, v ?? ''));
  try {
    const res = await fetch(`${API_URL}${path}`, { method: 'POST', body, headers: { Accept: 'application/json' } });
    return await res.json();
  } catch {
    return { ok: false, error: 'Could not reach the server. Please check your connection and try again.' };
  }
}
