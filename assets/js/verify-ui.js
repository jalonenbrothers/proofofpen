/* verify-ui.js — verify page tabs + result rendering (vanilla JS). */

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

  function showResult(res) {
    var v = res.verdict, pack = res.pack;
    var result = document.getElementById('result');
    result.hidden = false;
    var banner = document.getElementById('result-banner');
    banner.className = 'banner ' + (v.valid ? 'valid' : 'bad');
    banner.textContent = v.valid
      ? 'VALID — every event re-hashes to the recorded chain head'
      : 'TAMPERED — ' + (v.reason || 'chain broken')
        + (v.first_bad_index !== null && v.first_bad_index !== undefined ? ' (first mismatch at event #' + v.first_bad_index + ')' : '');
    document.getElementById('final-text').textContent = pack.final_text || '';
    if (v.warnings && v.warnings.length) {
      banner.textContent += '. Notes: ' + v.warnings.join('; ');
    }
    popReplay(pack, {
      text: document.getElementById('replay-text'),
      play: document.getElementById('play'),
      speed: document.getElementById('speed'),
      scrub: document.getElementById('scrub'),
      note: document.getElementById('replay-note')
    });
    result.scrollIntoView({ behavior: 'smooth' });
  }

  function post(payload) {
    return fetch('/api/verify', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (r) { return r.json(); }).then(function (res) {
      if (!res.ok) { throw new Error(res.error || 'verification failed'); }
      showResult(res);
    }).catch(function (err) { alert('Verify failed: ' + err.message); });
  }

  document.getElementById('verify-id').addEventListener('click', function () {
    var id = document.getElementById('pack-id').value.trim().toUpperCase();
    if (!/^[0-9A-Z]{6,16}$/.test(id)) { alert('Pack id looks wrong'); return; }
    post({ id: id });
  });

  document.getElementById('verify-json').addEventListener('click', function () {
    var pack = JSON.parse(document.getElementById('pack-json').value);
    post({ pack: pack });
  });

  document.getElementById('verify-file').addEventListener('change', function () {
    var f = this.files[0];
    if (!f) { return; }
    var fr = new FileReader();
    fr.onload = function () {
      var html = String(fr.result);
      // Extract embedded pack from the b64 json block.
      var m = html.match(/id="packB64">([A-Za-z0-9+\/=]+)<\/script>/);
      if (m) {
        var bin = atob(m[1]), bytes = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        post({ pack: JSON.parse(new TextDecoder().decode(bytes)) });
        return;
      }
      post({ pack: JSON.parse(html) }); // raw JSON pack file
    };
    fr.readAsText(f);
  });
})();
