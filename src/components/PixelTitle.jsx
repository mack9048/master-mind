import { useEffect, useRef, useState } from 'react';
import { reduceMotion } from '../hooks.js';

/** Pixel blocks fly in and assemble "FAVOUR / BRAND"; they dodge the pointer. */
export default function PixelTitle() {
  const canvasRef = useRef(null);
  const [done, setDone] = useState(false);

  useEffect(() => {
    const canvas = canvasRef.current;
    const wrap = canvas.parentElement;
    const ctx = canvas.getContext('2d');
    const W = 1100, H = 300, STEP = 8;
    let particles = [], raf = 0, frame = 0, stopped = false, finished = false;
    const mouse = { x: -999, y: -999, active: false };

    const stops = [[61, 139, 255], [244, 118, 157], [255, 201, 92]]; // blue → pink → yellow
    const lerp = (a, b, t) => a + (b - a) * t;
    const colorAt = (t) => {
      const seg = t < 0.35 ? 0 : 1;
      const lt = seg === 0 ? t / 0.35 : (t - 0.35) / 0.65;
      const a = stops[seg], b = stops[seg + 1];
      return `rgb(${lerp(a[0], b[0], lt) | 0},${lerp(a[1], b[1], lt) | 0},${lerp(a[2], b[2], lt) | 0})`;
    };

    function sample() {
      const off = document.createElement('canvas');
      off.width = W; off.height = H;
      const o = off.getContext('2d');
      o.fillStyle = '#fff';
      const fit = (text, targetW, maxSize) => {
        o.font = '700 100px Silkscreen, monospace';
        const size = Math.min(maxSize, 100 * targetW / o.measureText(text).width);
        o.font = `700 ${size}px Silkscreen, monospace`;
        return o.measureText(text);
      };
      const m1 = fit('FAVOUR', W * 0.6, 120);
      const a1 = m1.actualBoundingBoxAscent || 90;
      o.fillText('FAVOUR', 4, a1 + 8);
      const line1Bottom = a1 + 8 + (m1.actualBoundingBoxDescent || 0);
      const m2 = fit('BRAND', W * 0.94, 170);
      const a2 = m2.actualBoundingBoxAscent || 130;
      o.fillText('BRAND', 4, Math.min(H - 6, line1Bottom + 18 + a2));
      const data = o.getImageData(0, 0, W, H).data;
      const pts = [];
      for (let y = 0; y < H; y += STEP) for (let x = 0; x < W; x += STEP) if (data[(y * W + x) * 4 + 3] > 128) pts.push([x, y]);
      return pts;
    }

    function build() {
      particles = sample().map(([x, y]) => ({
        tx: x, ty: y,
        x: reduceMotion ? x : Math.random() * W,
        y: reduceMotion ? y : (Math.random() < 0.5 ? -40 - Math.random() * 200 : H + 40 + Math.random() * 200),
        vx: 0, vy: 0,
        delay: reduceMotion ? 0 : (x / W) * 40 + Math.random() * 30,
        c: colorAt(Math.min(1, x / W / 0.94)),
      }));
    }

    function resize() {
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      const cssW = wrap.clientWidth;
      canvas.width = Math.round(cssW * dpr);
      canvas.height = Math.round(cssW * (H / W) * dpr);
      ctx.setTransform(canvas.width / W, 0, 0, canvas.height / H, 0, 0);
      kick();
    }

    function tick() {
      frame++;
      ctx.clearRect(0, 0, W, H);
      let moving = false;
      for (const p of particles) {
        if (frame > p.delay) {
          let fx = (p.tx - p.x) * 0.06, fy = (p.ty - p.y) * 0.06;
          if (mouse.active) {
            const dx = p.x - mouse.x, dy = p.y - mouse.y, d2 = dx * dx + dy * dy;
            if (d2 < 4900) { const f = ((4900 - d2) / 4900) * 6; const d = Math.sqrt(d2) || 1; fx += (dx / d) * f; fy += (dy / d) * f; }
          }
          p.vx = (p.vx + fx) * 0.78; p.vy = (p.vy + fy) * 0.78;
          p.x += p.vx; p.y += p.vy;
        }
        if (frame <= p.delay || Math.abs(p.tx - p.x) > 0.3 || Math.abs(p.ty - p.y) > 0.3) moving = true;
      }
      const size = STEP + 0.6;
      ctx.fillStyle = '#c3123f';
      for (const p of particles) ctx.fillRect(p.x + 3, p.y + 3, size, size);
      for (const p of particles) { ctx.fillStyle = p.c; ctx.fillRect(p.x, p.y, size, size); }
      if (!moving && !finished) { finished = true; setDone(true); }
      raf = !stopped && (moving || mouse.active) ? requestAnimationFrame(tick) : 0;
    }
    function kick() { if (!raf && !stopped) raf = requestAnimationFrame(tick); }

    const onMove = (ev) => {
      const r = canvas.getBoundingClientRect();
      mouse.x = (ev.clientX - r.left) * (W / r.width);
      mouse.y = (ev.clientY - r.top) * (H / r.height);
      mouse.active = true; kick();
    };
    const onLeave = () => { mouse.active = false; kick(); };
    let rT;
    const onResize = () => { clearTimeout(rT); rT = setTimeout(resize, 120); };
    canvas.addEventListener('pointermove', onMove);
    canvas.addEventListener('pointerleave', onLeave);
    window.addEventListener('resize', onResize);

    const fontReady = document.fonts?.load ? document.fonts.load('700 100px Silkscreen').catch(() => {}) : Promise.resolve();
    let startT;
    const vis = new IntersectionObserver(([en]) => {
      if (en.isIntersecting) {
        vis.disconnect();
        fontReady.then(() => { startT = setTimeout(() => { build(); resize(); }, reduceMotion ? 0 : 500); });
      }
    }, { threshold: 0.1 });
    vis.observe(canvas);

    return () => {
      stopped = true;
      cancelAnimationFrame(raf);
      clearTimeout(startT); clearTimeout(rT);
      vis.disconnect();
      canvas.removeEventListener('pointermove', onMove);
      canvas.removeEventListener('pointerleave', onLeave);
      window.removeEventListener('resize', onResize);
    };
  }, []);

  return (
    <div className={`wrap pixel${done ? ' is-done' : ''}`}>
      <canvas ref={canvasRef} className="pixel__canvas" aria-hidden="true" />
      <span className="pixel__your" aria-hidden="true">Your</span>
      <h2 className="sr-only">Favour Your Brand</h2>
    </div>
  );
}
