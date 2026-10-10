/* capture.js — ProofOfPen editor capture (vanilla JS, no frameworks).
   Records keystroke bursts, pastes, pauses, away gaps and snapshots per the
   pack event contract (src/Pack.php), then POSTs the session to /api/create.

   Event contract (t = ms epoch, non-decreasing):
     {t, type:'burst', n, d}      — n chars typed over d ms
     {t, type:'paste', n}         — n chars arrived via paste
     {t, type:'snapshot', text}   — full-text snapshot (first + periodic)
     {t, type:'pause', d}         — d ms of inactivity (>2s)
     {t, type:'away', d}          — tab hidden / blur for d ms

   UX-plan niceties: pulsing status dot + live stats, Pause capture,
   Discard with inline confirm (no modal lib), sessionStorage autosave,
   beforeunload guard, clipboard copy with fallback + "copied ✓" flip. */

(function () {
  'use strict';

  var editor   = document.getElementById('editor');
  var finish    = document.getElementById('finish');
  var statsEl   = document.getElementById('stats');
  var statusEl  = document.getElementById('status');
  var statusDot = document.querySelector('.status-strip .dot');
  var pauseBtn  = document.getElementById('pause-capture');
  var discardBtn   = document.getElementById('discard-session');
  var discardRow   = document.getElementById('discard-row');
  var discardYes   = document.getElementById('discard-yes');
  var discardNo    = document.getElementById('discard-no');
  var pausedBanner = document.getElementById('paused-banner');

  var events = [];
  var t0 = Date.now();
  var lastKey = 0;
  var burstN = 0, burstStart = 0, burstLast = 0;
  var snapLen = 0;
  var awayAt = 0;
  var paused = false, pausedAt = 0;
  var submitted = false;
  var wordsEl = document.getElementById('stat-words');
  var elapsedEl = document.getElementById('stat-elapsed');

  function now() { return Date.now(); }

  function flushBurst() {
    if (burstN > 0) {
      events.push({ t: burstStart, type: 'burst', n: burstN, d: Math.max(1, burstLast - burstStart) });
      burstN = 0;
    }
  }

  function snapshot(force) {
    var len = editor.value.length;
    if (force || len - snapLen >= 1024) {
      events.push({ t: now(), type: 'snapshot', text: editor.value });
      snapLen = len;
      saveDraft();
    }
  }

  /* ---------- capture ---------- */
  editor.addEventListener('keydown', function () {
    if (submitted || paused) return;
    var t = now();
    if (lastKey > 0 && t - lastKey > 2000) {
      // inactivity gap > 2s: record it as a pause before this keystroke
      events.push({ t: lastKey, type: 'pause', d: t - lastKey });
    }
    if (burstN === 0) { burstStart = t; }
    burstN++;
    burstLast = t;
    lastKey = t;
    if (burstLast - burstStart > 800) { flushBurst(); burstStart = t; }
    snapshot(false);
  });

  editor.addEventListener('paste', function (ev) {
    if (submitted) return;
    if (paused) { ev.preventDefault(); return; }
    flushBurst();
    var text = (ev.clipboardData || window.clipboardData).getData('text');
    events.push({ t: now(), type: 'paste', n: text.length });
    setTimeout(function () { snapshot(true); }, 0);
  });

  document.addEventListener('visibilitychange', function () {
    if (submitted) return;
    flushBurst();
    if (document.hidden) { awayAt = now(); }
    else if (awayAt) { events.push({ t: awayAt, type: 'away', d: now() - awayAt }); awayAt = 0; saveDraft(); }
  });

  editor.addEventListener('keyup', flushBurst);
  setInterval(function () { if (!submitted && !paused && editor.value.length) { flushBurst(); snapshot(true); } }, 30000);

  /* ---------- pause capture (frontend-only toggle; resume records a pause event) ---------- */
  if (pauseBtn) pauseBtn.addEventListener('click', function () {
    paused = !paused;
    if (paused) {
      flushBurst();
      pausedAt = now();
      pauseBtn.textContent = 'Resume capture';
      statusEl.textContent = 'paused';
      if (statusDot) statusDot.classList.add('off');
      if (pausedBanner) pausedBanner.hidden = false;
    } else {
      if (pausedAt) events.push({ t: pausedAt, type: 'pause', d: now() - pausedAt });
      pausedAt = 0;
      pauseBtn.textContent = 'Pause capture';
      statusEl.textContent = 'recording';
      if (statusDot) statusDot.classList.remove('off');
      if (pausedBanner) pausedBanner.hidden = true;
      saveDraft();
    }
  });

  /* ---------- discard session (inline confirm, no modal) ---------- */
  if (discardBtn) discardBtn.addEventListener('click', function () {
    discardRow.hidden = !discardRow.hidden;
  });
  if (discardNo) discardNo.addEventListener('click', function () { discardRow.hidden = true; });
  if (discardYes) discardYes.addEventListener('click', function () {
    editor.value = '';
    events = []; t0 = now(); lastKey = 0; snapLen = 0; awayAt = 0;
    try { sessionStorage.removeItem('pop-draft'); sessionStorage.removeItem('pop-events'); } catch (e) {}
    discardRow.hidden = true;
    refreshStats();
    saveDraft();
  });

  /* ---------- live status strip ---------- */
  function fmtElapsed(ms) {
    var s = Math.max(0, Math.floor(ms / 1000));
    return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
  }
  function wordCount(s) { return (s.trim().match(/\S+/g) || []).length; }
  function refreshStats() {
    if (statsEl) statsEl.textContent = events.length + ' events · ' + editor.value.length + ' chars';
    if (wordsEl) wordsEl.textContent = wordCount(editor.value) + ' words';
    if (elapsedEl) elapsedEl.textContent = fmtElapsed(now() - t0);
    if (finish) finish.disabled = submitted || paused || editor.value.trim().length < 10 || events.length === 0;
  }
  editor.addEventListener('input', function () {
    flushBurst(); // keep burst boundaries honest with edits
    refreshStats();
    saveDraftSoon();
  });
  setInterval(function () { if (!paused) refreshStats(); }, 1000);

  /* ---------- autosave (sessionStorage) ---------- */
  var saveTimer = null;
  function saveDraft() {
    try {
      sessionStorage.setItem('pop-draft', editor.value);
      sessionStorage.setItem('pop-events', JSON.stringify(events));
    } catch (e) { /* private mode: fine */ }
  }
  function saveDraftSoon() {
    if (saveTimer) return;
    saveTimer = setTimeout(function () { saveTimer = null; saveDraft(); }, 2000);
  }
  (function restore() {
    try {
      var d = sessionStorage.getItem('pop-draft');
      var ev = sessionStorage.getItem('pop-events');
      if (d !== null) editor.value = d;
      if (ev) { var parsed = JSON.parse(ev); if (parsed && parsed.length) { events = parsed; snapLen = editor.value.length; } }
    } catch (e) {}
    refreshStats();
  })();

  /* ---------- beforeunload guard ---------- */
  window.addEventListener('beforeunload', function (ev) {
    try { if (sessionStorage.getItem('pop-submitted') === '1') return; } catch (e) {}
    if (events.length > 5 && editor.value.trim()) {
      saveDraft();
      ev.preventDefault();
      ev.returnValue = '';
    }
  });

  /* ---------- toast + clipboard with fallback ---------- */
  var toastEl = document.getElementById('toast');
  var toastTimer = null;
  function toast(msg) {
    if (!toastEl) return;
    toastEl.textContent = msg;
    toastEl.classList.add('show');
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.classList.remove('show'); }, 1800);
  }
  function copyText(text, btn, okMsg) {
    function flip() {
      toast(okMsg);
      if (btn) {
        var old = btn.textContent;
        btn.textContent = 'copied ✓';
        setTimeout(function () { btn.textContent = old; }, 1500);
      }
    }
    function fallback() {
      var tmp = document.createElement('textarea');
      tmp.value = text;
      tmp.setAttribute('readonly', '');
      tmp.style.position = 'fixed'; tmp.style.opacity = '0';
      document.body.appendChild(tmp);
      tmp.select();
      try {
        if (document.execCommand('copy')) flip(); else toast('copy failed — select it manually');
      } catch (e) { toast('copy failed — select it manually'); }
      tmp.remove();
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(flip, fallback);
    } else fallback();
  }
  var copyUrlBtn = document.getElementById('copy-url');
  if (copyUrlBtn) copyUrlBtn.addEventListener('click', function () {
    var href = copyUrlBtn.dataset.url || '';
    if (href) copyText(href, copyUrlBtn, 'verify URL copied');
  });
  var copyIdBtn = document.getElementById('copy-id');
  if (copyIdBtn) copyIdBtn.addEventListener('click', function () {
    var id = copyIdBtn.dataset.id || '';
    if (id) copyText(id, copyIdBtn, 'pack id copied');
  });

  /* ---------- finish & prove ---------- */
  finish.addEventListener('click', function () {
    finish.disabled = true;
    finish.textContent = 'Sealing…';
    flushBurst();
    snapshot(true);
    statusEl.textContent = 'sealing…';
    fetch('/api/create', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        events: events,
        final_text: editor.value,
        title: (document.getElementById('title') || {}).value || '',
        author: (document.getElementById('author') || {}).value || ''
      })
    }).then(function (r) { return r.json(); }).then(function (res) {
      if (!res.ok) { throw new Error(res.error || 'failed'); }
      submitted = true;
      try { sessionStorage.setItem('pop-submitted', '1'); } catch (e) {}
      statusEl.textContent = 'sealed ✓';
      if (statusDot) statusDot.classList.add('off');
      var p = res.pack;
      var card = document.getElementById('finish-card');
      document.getElementById('pack-summary').textContent =
        'pack ' + p.id + ' · chain ' + p.chain_head.slice(0, 16) + '…';
      var link = document.getElementById('pack-link');
      link.href = p.url; link.textContent = 'Open pack page (/p/' + p.id + ')';
      var dl = document.getElementById('pack-dl');
      dl.href = p.download;
      if (copyUrlBtn) copyUrlBtn.dataset.url = p.url;
      if (copyIdBtn) copyIdBtn.dataset.id = p.id;
      var warns = document.getElementById('pack-warnings');
      warns.textContent = (p.warnings && p.warnings.length)
        ? 'Receipt notes: ' + p.warnings.join(' · ')
        : 'No pasted segments or unusually fast bursts flagged.';
      document.getElementById('pack-storage').textContent =
        'Pack stored on this server for 90 days. The downloaded .html file works offline, forever.';
      card.hidden = false;
      card.scrollIntoView({ behavior: 'smooth' });
    }).catch(function (err) {
      finish.disabled = false;
      finish.textContent = 'Finish & prove';
      statusEl.textContent = 'Error: ' + err.message;
      toast('could not seal — ' + err.message);
    });
  });

  refreshStats();
})();