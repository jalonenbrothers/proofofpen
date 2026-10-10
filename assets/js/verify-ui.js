/* verify-ui.js — verify page tabs + verdict rendering (vanilla JS).
   UX plan: underline tabs, result banner naming first mismatch, DOM replay
   with 1/4/8x + scrubber, burst list with chars/s bars (>12 chars/s flagged
   "unusually fast input" — never AI language), no alert() — inline errors.
   Server verdict via POST /api/verify + independent local recompute
   (popVerify from replay.js, popCrypto from sha256.js) — receipts checked
   twice, nothing trusted blindly. */

(function () {
  'use strict';
  var tabs = document.querySelectorAll('.tab');
  var panes = { id: document.getElementById('tab-id'), paste: document.getElementById('tab-paste'), upload: document.getElementById('tab-upload') };

  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      tabs.forEach(function (x) { x.classList.toggle('active', x === t); });
      Object.keys(panes).forEach(function (k) { panes[k].hidden = k !== t.dataset.tab; });
    });
  });

  var errEl = document.getElementById('verify-error');

  function showError(msg) {
    if (errEl) { errEl.hidden = false; errEl.textContent = msg; }
  }

  function esc(s) {
    return String(s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }

  function fmtDur(ms) {
    if (ms >= 1000) return (ms / 1000).toFixed(1) + ' s';
    return ms + ' ms';
  }
  function fmtClock(ms) {
    var s = Math.floor(ms / 1000);
    return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
  }

  /* Burst list: speed bars, fast bursts flagged (never AI language). */
  function renderBursts(events, local) {
    var host = document.getElementById('burst-list');
    if (!host) return;
    var rows = [];
    var marks = [];
    events.forEach(function (ev) {
      if (ev.type === 'burst') marks.push({ t: ev.t, typed: ev.n, dur: ev.d || 1, fast: (ev.n / Math.max(1, ev.d) * 1000) > 12 });
      else if (ev.type === 'paste') marks.push({ t: ev.t, pasted: ev.n, dur: 0 });
    });
    var t0 = events.length ? events[0].t : 0;
    var maxCps = 12;
    marks.forEach(function (m) { if (m.typed) { m.cps = m.typed / (m.dur / 1000); if (m.cps > maxCps) maxCps = m.cps; } });
    marks.forEach(function (m) {
      var pct = m.typed ? Math.round(100 * m.cps / maxCps) : 100;
      var label = m.pasted != null
        ? '<span class="paste-note">' + m.pasted + ' chars pasted (kept separate from typed text)</span>'
        : esc(m.cps.toFixed(1)) + ' chars/s' + (m.fast ? ' <span class="warn">unusually fast input</span>' : '');
      rows.push('<tr><td class="mono">' + esc(fmtClock(m.t - t0)) + '</td>'
        + '<td class="mono">' + fmtDur(m.dur) + '</td>'
        + '<td class="mono">' + (m.typed || m.pasted) + '</td>'
        + '<td><div class="speedbar-wrap"><div class="speedbar' + (m.fast || m.pasted != null ? ' fast' : '') + '" style="width:' + pct + '%"></div></div></td>'
        + '<td class="small">' + label + '</td></tr>');
    });
    host.innerHTML = '<table class="burst-table"><thead><tr><th>start</th><th>duration</th><th>chars</th><th colspan="2">speed</th></tr></thead><tbody>'
      + (rows.join('') || '<tr><td colspan="5" class="small">No bursts recorded.</td></tr>')
      + '</tbody></table>';
    void local;
  }

  function showResult(res, sourceLabel) {
    var v = res.verdict, pack = res.pack;
    var result = document.getElementById('result');
    result.hidden = false;
    var banner = document.getElementById('result-banner');
    banner.className = 'banner ' + (v.valid ? 'valid' : 'bad');

    /* Independent local recompute (same chain rules as PHP Pack::verify). */
    var local = null;
    try { local = popVerify(pack); } catch (e) { local = null; }
    var localLine = local
      ? (local.valid === v.valid
        ? 'Local recompute agrees: ' + (local.valid ? 'VALID' : 'TAMPERED') + ', head ' + String(local.chain_head).slice(0, 8) + '….'
        : 'Local recompute DISAGREES with the server — treat this pack as suspect.')
      : 'Local recompute unavailable.';

    banner.innerHTML = v.valid
      ? '&#10003; VALID — every event re-hashes to the recorded chain head (' + esc(String(v.chain_head).slice(0, 12)) + '…) <span class="small">· ' + esc(sourceLabel) + '</span>'
      : '&#10007; TAMPERED — ' + esc(v.reason || 'chain broken')
        + (v.first_bad_index !== null && v.first_bad_index !== undefined ? ' (first mismatch at event #' + v.first_bad_index + ')' : '');
    document.getElementById('verdict-local').textContent = localLine;

    var notes = [];
    if (v.warnings && v.warnings.length) notes = v.warnings;
    document.getElementById('verdict-notes').innerHTML = notes.length
      ? notes.map(function (w) { return '<li class="warn">' + esc(w) + '</li>'; }).join('')
      : '';
    document.getElementById('verdict-notes').hidden = !notes.length;

    document.getElementById('final-text').textContent = pack.final_text || '';
    renderBursts(pack.events || [], local);
    popReplay(pack, {
      text: document.getElementById('replay-text'),
      play: document.getElementById('play'),
      speed: document.getElementById('speed'),
      scrub: document.getElementById('scrub'),
      note: document.getElementById('replay-note')
    });
    result.scrollIntoView({ behavior: 'smooth' });
  }

  function post(payload, sourceLabel) {
    if (errEl) { errEl.hidden = true; }
    return fetch('/api/verify', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (r) { return r.json(); }).then(function (res) {
      if (!res.ok) { throw new Error(res.error || 'verification failed'); }
      showResult(res, sourceLabel);
    }).catch(function (err) {
      showError('Verify failed: ' + err.message);
    });
  }

  document.getElementById('verify-id').addEventListener('click', function () {
    var id = document.getElementById('pack-id').value.trim().toUpperCase();
    if (!/^[0-9A-Z]{6,16}$/.test(id)) { showError('Pack id looks wrong — 6–16 letters/digits.'); return; }
    post({ id: id }, 'stored pack ' + id);
  });

  document.getElementById('verify-json').addEventListener('click', function () {
    var pack;
    try { pack = JSON.parse(document.getElementById('pack-json').value); }
    catch (e) { showError('That is not valid JSON.'); return; }
    post({ pack: pack }, 'pasted JSON');
  });

  document.getElementById('verify-file').addEventListener('change', function () {
    var f = this.files[0];
    if (!f) { return; }
    var fr = new FileReader();
    fr.onload = function () {
      var html = String(fr.result);
      var m = html.match(/id="packB64">([A-Za-z0-9+\/=]+)<\/script>/);
      if (m) {
        var bin = atob(m[1]), bytes = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        post({ pack: JSON.parse(new TextDecoder().decode(bytes)) }, 'uploaded ' + f.name);
        return;
      }
      try { post({ pack: JSON.parse(html) }, 'uploaded ' + f.name); }
      catch (e) { showError('No embedded proof pack found in that file.'); }
    };
    fr.readAsText(f);
  });
  document.getElementById('verify-file-btn').addEventListener('click', function () {
    var inp = document.getElementById('pack-file');
    var f = inp.files && inp.files[0];
    if (!f) { showError('Choose the .html pack file first.'); return; }
    inp.dispatchEvent(new Event('change'));
  });
})();