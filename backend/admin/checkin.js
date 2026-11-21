/* Master Mind – camera / scanner check-in (dashboard) */
(() => {
  'use strict';
  const root = document.getElementById('scan');
  if (!root) return;
  const csrf = root.dataset.csrf;
  const $ = (id) => document.getElementById(id);
  const video = $('scanVideo'), idle = $('scanIdle'), result = $('scanResult');
  const btnStart = $('scanStart'), btnStop = $('scanStop'), btnFlip = $('scanFlip');
  const form = $('manualForm'), input = $('manualCode'), log = $('scanLog');
  const canvas = document.createElement('canvas');
  const ctx = canvas.getContext('2d', { willReadFrequently: true });

  let stream = null, detector = null, facing = 'environment', busy = false, raf = 0;
  let last = { text: '', at: 0 };

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  // ---------- feedback
  let audio;
  function beep(ok) {
    try {
      audio = audio || new (window.AudioContext || window.webkitAudioContext)();
      const o = audio.createOscillator(), g = audio.createGain();
      o.frequency.value = ok ? 1046 : 220; o.type = ok ? 'sine' : 'square';
      g.gain.setValueAtTime(0.18, audio.currentTime); g.gain.exponentialRampToValueAtTime(0.001, audio.currentTime + (ok ? 0.18 : 0.45));
      o.connect(g).connect(audio.destination); o.start(); o.stop(audio.currentTime + (ok ? 0.2 : 0.5));
    } catch (e) { /* no audio */ }
    if (navigator.vibrate) navigator.vibrate(ok ? 80 : [120, 80, 120]);
  }

  const ICON = { ok: '✓', already: '!', unpaid: '₹', cancelled: '✕', notfound: '?', multiple: '⋯', error: '!' };
  function show(res) {
    const st = res.status || 'error';
    result.className = 'scan__result is-' + st;
    result.innerHTML = `
      <div class="scan__icon">${ICON[st] || '!'}</div>
      <div class="scan__msg">${esc(res.message || 'Error')}</div>
      ${res.list ? `<div class="scan__meta">${res.list.map(esc).join('<br>')}</div>` : ''}
      ${res.name ? `<div class="scan__name">${esc(res.name)}</div><div class="scan__meta">${esc(res.reg_code)} · ${esc(res.phone)}${res.profession ? ' · ' + esc(res.profession) : ''}</div>` : (res.list ? '' : `<div class="scan__meta">${esc(res.input)}</div>`)}
      ${res.id && st !== 'ok' ? `<a class="b-ghost" href="?view=booking&id=${res.id}">Open booking</a>` : ''}`;
    result.hidden = false;
    beep(st === 'ok');
    if (typeof res.checked_in === 'number') {
      $('cntIn').textContent = res.checked_in;
      $('cntWait').textContent = Math.max(0, res.paid - res.checked_in);
    }
    if (res.name) {
      const li = document.createElement('li');
      li.className = st === 'ok' ? 'ok' : 'warn';
      li.innerHTML = `<b>${esc(res.name)}</b><code>${esc(res.reg_code)}</code><small>${st === 'ok' ? new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : esc(res.message)}</small>`;
      log.querySelector('.empty-li')?.remove();
      log.prepend(li);
      while (log.children.length > 12) log.lastElementChild.remove();
    }
  }

  async function checkin(text) {
    busy = true;
    try {
      const body = new URLSearchParams({ csrf, code: text });
      const r = await fetch('?api=checkin', { method: 'POST', body, credentials: 'same-origin' });
      show(await r.json());
    } catch (e) {
      show({ status: 'error', message: 'Network error – try again', input: text });
    }
    // keep the result visible, then resume scanning
    setTimeout(() => { busy = false; if (stream) result.hidden = true; }, 2200);
  }

  // ---------- camera scanning
  async function start() {
    if (!navigator.mediaDevices?.getUserMedia) {
      show({ status: 'error', message: 'Camera not available. Open this page over HTTPS in Chrome or Safari.' });
      return;
    }
    try {
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: facing }, width: { ideal: 1280 } }, audio: false });
    } catch (e) {
      show({ status: 'error', message: 'Camera permission denied – allow camera access in the browser settings.' });
      return;
    }
    video.srcObject = stream;
    await video.play();
    if (!detector && 'BarcodeDetector' in window) {
      try {
        const formats = await window.BarcodeDetector.getSupportedFormats();
        if (formats.includes('qr_code')) detector = new window.BarcodeDetector({ formats: ['qr_code', 'code_128'].filter((f) => formats.includes(f)) });
      } catch (e) { detector = null; }
    }
    idle.hidden = true; result.hidden = true;
    btnStop.hidden = false; btnFlip.hidden = false;
    root.classList.add('is-live');
    loop();
  }

  function stop() {
    cancelAnimationFrame(raf);
    stream?.getTracks().forEach((t) => t.stop());
    stream = null;
    video.srcObject = null;
    idle.hidden = false; btnStop.hidden = true; btnFlip.hidden = true;
    root.classList.remove('is-live');
  }

  async function detect() {
    if (detector) {
      const codes = await detector.detect(video);
      return codes[0]?.rawValue || null;
    }
    if (!window.jsQR || !video.videoWidth) return null;
    const scale = Math.min(1, 640 / video.videoWidth);
    canvas.width = Math.round(video.videoWidth * scale);
    canvas.height = Math.round(video.videoHeight * scale);
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
    return window.jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' })?.data || null;
  }

  let lastScan = 0;
  async function loop() {
    if (!stream) return;
    const now = performance.now();
    if (!busy && now - lastScan > 120 && video.readyState >= 2) {
      lastScan = now;
      try {
        const text = await detect();
        if (text && !(text === last.text && Date.now() - last.at < 4000)) {
          last = { text, at: Date.now() };
          checkin(text);
        }
      } catch (e) { /* keep scanning */ }
    }
    raf = requestAnimationFrame(loop);
  }

  btnStart.addEventListener('click', start);
  btnStop.addEventListener('click', stop);
  btnFlip.addEventListener('click', () => { facing = facing === 'environment' ? 'user' : 'environment'; stop(); start(); });
  window.addEventListener('pagehide', stop);

  // ---------- typed codes and USB/Bluetooth barcode scanners (they "type" + Enter)
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return;
    input.value = '';
    checkin(text).then(() => input.focus());
  });
  input.focus();
})();
