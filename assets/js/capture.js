/* capture.js — ProofOfPen editor capture (vanilla JS, no frameworks).
   Records keystroke bursts, pastes, pauses and snapshots, then POSTs
   the session to /api/create. sessionStorage autosave + beforeunload guard. */

(function () {
  'use strict';
  var editor = document.getElementById('editor');
  var finish = document.getElementById('finish');
  var statsEl = document.getElementById('stats');
  var statusEl = document.getElementById('status');

  var events = [];
  var t0 = Date.now();
  var lastKey = 0;
  var burstN = 0, burstStart = 0, burstLast = 0;
  var snapLen = 0;
  var awayAt = 0;

  function now() { return Date.now(); }

  function flushBurst() {
    if (burstN > 0) {
      events.push({ t: burstStart, type: 'burst', n: burstN, d: burstLast - burstStart });
      burstN = 0;
    }
  }

  function snapshot(force) {
    var len = editor.value.length;
    if (force || len - snapLen >= 1024) {
      events.push({ t: now(), type: 'snapshot', text: editor.value });
      snapLen = len;
    }
  }

  editor.addEventListener('keydown', function () {
    var t = now();
    if (t - lastKey > 2000 && lastKey > 0) {
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
    flushBurst();
    var text = (ev.clipboardData || window.clipboardData).getData('text');
    events.push({ t: now(), type: 'paste', n: text.length });
    setTimeout(function () { snapshot(true); }, 0);
  });

  document.addEventListener('visibilitychange', function () {
    flushBurst();
    if (document.hidden) { awayAt = now(); }
    else if (awayAt) { events.push({ t: awayAt, type: 'away', d: now() - awayAt }); awayAt = 0; }
  });

  window.addEventListener('beforeunload', function (ev) {
    if (events.length > 5 && !sessionStorage.getItem('pop-submitted')) {
      ev.preventDefault();
      ev.returnValue = '';
    }
  });

  setInterval(function () {
    flushBurst();
    if (editor.value.length) { snapshot(true); }
    try { sessionStorage.setItem('pop-draft', editor.value); } catch (e) {}
  }, 30000);

  try { editor.value = sessionStorage.getItem('pop-draft') || ''; } catch (e) {}

  editor.addEventListener('input', function () {
    finish.disabled = editor.value.trim().length < 10 || events.length === 0;
    statsEl.textContent = events.length + ' events · ' + editor.value.length + ' chars';
  });
  editor.addEventListener('keyup', flushBurst);
  setInterval(flushBurst, 1000);

  finish.addEventListener('click', function () {
    finish.disabled = true;
    finish.textContent = 'Sealing…';
    flushBurst();
    snapshot(true);
    fetch('/api/create', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        events: events,
        final_text: editor.value,
        title: document.getElementById('title').value,
        author: document.getElementById('author').value
      })
    }).then(function (r) { return r.json(); }).then(function (res) {
      if (!res.ok) { throw new Error(res.error || 'failed'); }
      try { sessionStorage.setItem('pop-submitted', '1'); } catch (e) {}
      var p = res.pack;
      var card = document.getElementById('finish-card');
      card.hidden = false;
      document.getElementById('pack-summary').textContent =
        'pack ' + p.id + ' · chain ' + p.chain_head.slice(0, 16) + '…';
      document.getElementById('pack-link').href = p.url;
      document.getElementById('pack-dl').href = p.download;
      document.getElementById('pack-warnings').textContent = (p.warnings || []).join(' · ');
      card.scrollIntoView({ behavior: 'smooth' });
    }).catch(function (err) {
      finish.disabled = false;
      finish.textContent = 'Finish & prove';
      statusEl.textContent = 'Error: ' + err.message;
    });
  });
})();
