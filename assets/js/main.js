/* Master Mind · Meet the Expert — front-end */
(() => {
  'use strict';
  const MM = window.MM || {};
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => [...el.querySelectorAll(s)];
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  /* ---------- Nav border + sticky CTA ---------- */
  const nav = $('.nav');
  const sticky = $('.sticky-cta');
  const reg = $('#register');
  const onScroll = () => {
    nav && nav.classList.toggle('is-scrolled', scrollY > 10);
    if (sticky && reg) {
      const r = reg.getBoundingClientRect();
      const inReg = r.top < innerHeight && r.bottom > 0;
      sticky.classList.toggle('is-shown', scrollY > innerHeight * 0.7 && !inReg);
    }
  };
  addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ---------- Scroll reveal ---------- */
  const io = new IntersectionObserver((entries) => {
    entries.forEach((en) => { if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); } });
  }, { threshold: 0.15 });
  $$('.reveal').forEach((el) => io.observe(el));

  /* ---------- Hero: highlight boxes + cursor ---------- */
  const title = $('.hero__title');
  const cursor = $('.hero__cursor');
  const hls = $$('[data-reveal-hl]');
  function heroIntro() {
    if (!title) return;
    if (reduceMotion || !cursor) { hls.forEach((h) => h.classList.add('is-on')); return; }
    const pink = $('.hl--pink', title);
    const tr = title.getBoundingClientRect();
    const pr = pink.getBoundingClientRect();
    const tx = pr.right - tr.left - 6;
    const ty = pr.bottom - tr.top - pr.height * 0.45;
    cursor.style.transform = `translate(${tx + 220}px, ${ty + 160}px)`;
    requestAnimationFrame(() => {
      cursor.classList.add('is-moving');
      cursor.style.transform = `translate(${tx}px, ${ty}px)`;
    });
    setTimeout(() => {
      cursor.classList.add('is-click');
      hls.forEach((h) => h.classList.add('is-on'));
    }, 1150);
  }
  // Re-place the cursor when the layout changes size
  addEventListener('resize', () => {
    if (!cursor || !cursor.classList.contains('is-moving')) return;
    const pink = $('.hl--pink', title);
    const tr = title.getBoundingClientRect();
    const pr = pink.getBoundingClientRect();
    cursor.style.transition = 'none';
    cursor.style.transform = `translate(${pr.right - tr.left - 6}px, ${pr.bottom - tr.top - pr.height * 0.45}px)`;
  });

  /* ---------- Pixel title (particles assemble into "FAVOUR / BRAND") ---------- */
  function pixelTitle() {
    const canvas = $('#pixelTitle');
    if (!canvas) return;
    const wrap = canvas.parentElement;
    const ctx = canvas.getContext('2d');
    const W = 1100, H = 300;
    let particles = [], dpr = 1, raf = 0, mouse = { x: -999, y: -999, active: false };

    const stops = [[61, 139, 255], [244, 118, 157], [255, 201, 92]]; // blue → pink → yellow
    const lerp = (a, b, t) => a + (b - a) * t;
    function colorAt(t) {
      const seg = t < 0.35 ? 0 : 1;
      const lt = seg === 0 ? t / 0.35 : (t - 0.35) / 0.65;
      const a = stops[seg], b = stops[seg + 1];
      return `rgb(${lerp(a[0], b[0], lt) | 0},${lerp(a[1], b[1], lt) | 0},${lerp(a[2], b[2], lt) | 0})`;
    }

    function sample() {
      const off = document.createElement('canvas');
      off.width = W; off.height = H;
      const o = off.getContext('2d');
      o.fillStyle = '#fff';
      o.textBaseline = 'alphabetic';
      const fit = (text, targetW, maxSize) => {
        let size = 100;
        o.font = `700 ${size}px Silkscreen, monospace`;
        size = Math.min(maxSize, size * targetW / o.measureText(text).width);
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
      const step = 8;
      const pts = [];
      for (let y = 0; y < H; y += step) {
        for (let x = 0; x < W; x += step) {
          if (data[(y * W + x) * 4 + 3] > 128) pts.push([x, y]);
        }
      }
      return { pts, step };
    }

    function build() {
      const { pts, step } = sample();
      particles = pts.map(([x, y]) => {
        const t = x / W;
        return {
          tx: x, ty: y,
          x: reduceMotion ? x : Math.random() * W,
          y: reduceMotion ? y : (Math.random() < 0.5 ? -40 - Math.random() * 200 : H + 40 + Math.random() * 200),
          vx: 0, vy: 0,
          delay: reduceMotion ? 0 : (x / W) * 40 + Math.random() * 30,
          size: step + 0.6,
          c: colorAt(Math.min(1, t / 0.94)),
        };
      });
    }

    function resize() {
      dpr = Math.min(window.devicePixelRatio || 1, 2);
      const cssW = wrap.clientWidth;
      canvas.width = Math.round(cssW * dpr);
      canvas.height = Math.round(cssW * (H / W) * dpr);
      ctx.setTransform(canvas.width / W, 0, 0, canvas.height / H, 0, 0);
      kick();
    }

    let frame = 0;
    function tick() {
      frame++;
      ctx.clearRect(0, 0, W, H);
      let moving = false;
      for (const p of particles) {
        if (frame > p.delay) {
          let fx = (p.tx - p.x) * 0.06, fy = (p.ty - p.y) * 0.06;
          if (mouse.active) {
            const dx = p.x - mouse.x, dy = p.y - mouse.y, d2 = dx * dx + dy * dy;
            if (d2 < 4900) { const f = (4900 - d2) / 4900 * 6; const d = Math.sqrt(d2) || 1; fx += dx / d * f; fy += dy / d * f; }
          }
          p.vx = (p.vx + fx) * 0.78;
          p.vy = (p.vy + fy) * 0.78;
          p.x += p.vx; p.y += p.vy;
        }
        if (Math.abs(p.tx - p.x) > 0.3 || Math.abs(p.ty - p.y) > 0.3 || frame <= p.delay) moving = true;
      }
      // 3D pixel shadow (like a print offset)
      ctx.fillStyle = '#c3123f';
      for (const p of particles) ctx.fillRect(p.x + 3, p.y + 3, p.size, p.size);
      for (const p of particles) { ctx.fillStyle = p.c; ctx.fillRect(p.x, p.y, p.size, p.size); }

      if (!moving && !wrap.classList.contains('is-done')) wrap.classList.add('is-done');
      raf = (moving || mouse.active) ? requestAnimationFrame(tick) : 0;
    }
    function kick() { if (!raf) raf = requestAnimationFrame(tick); }

    canvas.addEventListener('pointermove', (ev) => {
      const r = canvas.getBoundingClientRect();
      mouse.x = (ev.clientX - r.left) * (W / r.width);
      mouse.y = (ev.clientY - r.top) * (H / r.height);
      mouse.active = true; kick();
    });
    canvas.addEventListener('pointerleave', () => { mouse.active = false; kick(); });

    let rT;
    addEventListener('resize', () => { clearTimeout(rT); rT = setTimeout(resize, 120); });

    const start = () => { build(); resize(); };
    // Start when visible, after the pixel font has loaded
    const fontReady = document.fonts && document.fonts.load ? document.fonts.load('700 100px Silkscreen').catch(() => {}) : Promise.resolve();
    const vis = new IntersectionObserver((en) => {
      if (en[0].isIntersecting) { vis.disconnect(); fontReady.then(() => setTimeout(start, reduceMotion ? 0 : 500)); }
    }, { threshold: 0.1 });
    vis.observe(canvas);
  }

  /* ---------- Countdown ---------- */
  function countdown() {
    const el = $('#countdown');
    if (!el) return;
    const target = new Date(el.dataset.target).getTime();
    const units = { d: $('[data-unit="d"]', el), h: $('[data-unit="h"]', el), m: $('[data-unit="m"]', el), s: $('[data-unit="s"]', el) };
    const pad = (n) => String(n).padStart(2, '0');
    let timer;
    function set(u, v) {
      const n = units[u];
      if (n.textContent !== v) {
        n.textContent = v;
        if (!reduceMotion) { n.classList.remove('tick'); void n.offsetWidth; n.classList.add('tick'); }
      }
    }
    function update() {
      let diff = Math.max(0, target - Date.now());
      if (diff === 0) {
        clearInterval(timer);
        el.classList.add('is-live');
        $('.countdown__kicker', el).textContent = 'The workshop is live';
        $('.countdown__units', el).textContent = 'See you at the venue!';
        return;
      }
      const d = Math.floor(diff / 864e5); diff -= d * 864e5;
      const h = Math.floor(diff / 36e5); diff -= h * 36e5;
      const m = Math.floor(diff / 6e4); diff -= m * 6e4;
      const s = Math.floor(diff / 1e3);
      set('d', pad(d)); set('h', pad(h)); set('m', pad(m)); set('s', pad(s));
    }
    update();
    timer = setInterval(update, 1000);
  }

  /* ---------- Modal ---------- */
  const modal = $('#modal');
  const modalBody = $('#modalBody');
  function openModal(html) {
    modalBody.innerHTML = html;
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    const f = $('input, button:not(.modal__x), a', modalBody);
    f && f.focus();
  }
  function closeModal() { modal.hidden = true; document.body.style.overflow = ''; }
  modal && modal.addEventListener('click', (e) => { if (e.target === modal || e.target.closest('[data-close]')) closeModal(); });
  addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal && !modal.hidden) closeModal(); });

  const waLink = (text) => `https://wa.me/${MM.whatsapp}?text=${encodeURIComponent(text)}`;

  function successHTML(code, pending) {
    return `
      <div class="ok-icon">✓</div>
      <h3 id="modalTitle">${pending ? 'Registration received!' : 'You’re in!'}</h3>
      <p>${pending
        ? 'We’ll verify your payment and confirm your seat on WhatsApp shortly.'
        : 'Your payment is successful and your seat is confirmed.'}</p>
      <span class="code">${esc(code)}</span>
      <p>Save this registration ID and show it at the venue.<br>${esc(MM.dateLabel || '')}</p>
      <div class="stack">
        <a class="btn btn--ghost" href="${waLink('Hi, I registered for Master Mind. My registration ID is ' + code)}" target="_blank" rel="noopener">Share ID on WhatsApp</a>
        <button class="btn btn--pink" data-close type="button">Done</button>
      </div>`;
  }

  /* ---------- Registration ---------- */
  const form = $('#regForm');
  async function post(url, data) {
    const res = await fetch(url, { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json' } });
    let json;
    try { json = await res.json(); } catch { json = { ok: false, error: 'Server error. Please try again.' }; }
    return json;
  }

  function clearErrors() {
    $$('.field', form).forEach((f) => { f.classList.remove('has-err'); const s = $('.field__err', f); if (s) s.textContent = ''; });
    $('.form__msg', form).textContent = '';
  }
  function showErrors(fields) {
    let first;
    Object.entries(fields || {}).forEach(([name, msg]) => {
      const input = form.elements[name];
      const field = input && input.closest('.field');
      if (field) { field.classList.add('has-err'); $('.field__err', field).textContent = msg; first = first || input; }
    });
    first && first.focus();
  }
  function validate() {
    const f = form.elements, errs = {};
    if (f.name.value.trim().length < 2) errs.name = 'Please enter your full name.';
    const ph = f.phone.value.replace(/\D/g, '').replace(/^(91|0)(?=\d{10}$)/, '');
    if (!/^[6-9]\d{9}$/.test(ph)) errs.phone = 'Enter a valid 10-digit mobile number.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email.value.trim())) errs.email = 'Enter a valid email.';
    if (f.city.value.trim().length < 2) errs.city = 'Enter your city / place.';
    if (!f.profession.value) errs.profession = 'Choose one.';
    return errs;
  }

  function startRazorpay(res, btn) {
    if (typeof window.Razorpay !== 'function') {
      $('.form__msg', form).textContent = 'Payment window could not load. Check your connection and try again.';
      return;
    }
    const rp = res.razorpay;
    const rzp = new window.Razorpay({
      key: rp.key, order_id: rp.order_id, amount: rp.amount, currency: rp.currency,
      name: rp.name, description: rp.description, prefill: rp.prefill,
      theme: { color: '#f4769d' },
      handler: async (r) => {
        btn.classList.add('is-loading');
        const fd = new FormData();
        fd.append('csrf', MM.csrf); fd.append('action', 'razorpay_verify'); fd.append('reg_code', res.reg_code);
        fd.append('razorpay_order_id', r.razorpay_order_id);
        fd.append('razorpay_payment_id', r.razorpay_payment_id);
        fd.append('razorpay_signature', r.razorpay_signature);
        const v = await post('api/payment.php', fd);
        btn.classList.remove('is-loading');
        if (v.ok) { form.reset(); openModal(successHTML(res.reg_code, false)); }
        else openModal(`<h3 id="modalTitle">Payment check failed</h3><p>${esc(v.error)}</p><div class="stack"><a class="btn btn--ghost" href="tel:${esc(MM.phone)}">Call ${esc(MM.phone)}</a></div>`);
      },
      modal: { ondismiss: () => { $('.form__msg', form).textContent = 'Payment cancelled. Your details are saved — press the button again to pay.'; } },
    });
    rzp.on('payment.failed', () => {
      const fd = new FormData();
      fd.append('csrf', MM.csrf); fd.append('action', 'razorpay_failed'); fd.append('reg_code', res.reg_code);
      post('api/payment.php', fd);
    });
    rzp.open();
  }

  function showUPI(res) {
    const u = res.upi;
    const isMobile = /Android|iPhone|iPad/i.test(navigator.userAgent);
    const hasQR = !!(u.qr || u.link);
    openModal(`
      <h3 id="modalTitle">Scan &amp; pay ₹${u.amount}</h3>
      <p>Your seat is reserved. Scan this QR with GPay, PhonePe, Paytm or any UPI app, then enter the transaction ID below.</p>
      <span class="code">${esc(res.reg_code)}</span>
      ${hasQR ? `<div class="qr"><div class="qr__box" id="qrBox">${u.qr ? `<img src="${esc(u.qr)}" alt="UPI QR code to pay ₹${u.amount}">` : ''}</div>
        <p class="qr__cap">Pay <b>₹${u.amount}</b> to <b>${esc(u.name)}</b>${u.id ? `<br><small>${esc(u.id)}</small>` : ''}</p></div>`
        : `<p class="qr__cap">Our team will send you the payment QR on WhatsApp shortly.</p>`}
      ${isMobile && u.link ? `<a class="btn btn--ghost btn--block" href="${esc(u.link)}">Open UPI app to pay ₹${u.amount}</a>` : ''}
      <form class="form" id="utrForm" style="margin-top:16px">
        <div class="field">
          <label for="utr">UPI transaction / UTR number</label>
          <input id="utr" name="payment_ref" inputmode="numeric" autocomplete="off" required maxlength="40" placeholder="12-digit UTR">
          <small class="field__err"></small>
        </div>
        <button class="btn btn--pink btn--block" type="submit"><span class="btn__label">Confirm payment</span><span class="btn__spin"></span></button>
      </form>`);

    if (!u.qr && u.link && typeof window.qrcode === 'function') {
      const q = window.qrcode(0, 'M');
      q.addData(u.link);
      q.make();
      $('#qrBox', modalBody).innerHTML = q.createSvgTag({ cellSize: 6, margin: 0, scalable: true });
    }
    $('#utrForm', modalBody).addEventListener('submit', async (e) => {
      e.preventDefault();
      const f = e.currentTarget, b = $('button', f), err = $('.field__err', f);
      b.classList.add('is-loading'); err.textContent = '';
      const fd = new FormData(f);
      fd.append('csrf', MM.csrf); fd.append('action', 'upi_ref'); fd.append('reg_code', res.reg_code);
      const r = await post('api/payment.php', fd);
      b.classList.remove('is-loading');
      if (r.ok) { form.reset(); openModal(successHTML(res.reg_code, true)); }
      else err.textContent = r.error || 'Could not save. Try again.';
    });
  }

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      clearErrors();
      const errs = validate();
      if (Object.keys(errs).length) { showErrors(errs); return; }
      const btn = $('#regBtn');
      btn.classList.add('is-loading');
      try {
        const res = await post('api/register.php', new FormData(form));
        if (!res.ok) {
          showErrors(res.fields);
          $('.form__msg', form).textContent = res.error || 'Something went wrong.';
          return;
        }
        if (res.mode === 'razorpay') startRazorpay(res, btn);
        else showUPI(res);
      } catch {
        $('.form__msg', form).textContent = 'Network error. Please check your connection and try again.';
      } finally {
        btn.classList.remove('is-loading');
      }
    });
    // Clear a field's error as the user fixes it
    form.addEventListener('input', (e) => {
      const f = e.target.closest('.field');
      if (f && f.classList.contains('has-err')) { f.classList.remove('has-err'); $('.field__err', f).textContent = ''; }
    });
  }

  /* ---------- Boot ---------- */
  countdown();
  const boot = () => { heroIntro(); pixelTitle(); };
  if (document.readyState === 'complete') boot(); else addEventListener('load', boot);
})();
