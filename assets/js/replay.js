/* replay.js — DOM keystroke replay + offline chain verify.
   Shared by the site's verify page and inlined into exported packs
   (identical behaviour offline and online, per the UX plan).
   No canvas, no frameworks. Depends on popCrypto (sha256.js). */

/* Verify a pack's chain in the browser. Mirrors PHP Pack::verify
   (events chain + final_text seal). Returns a verdict-like object. */
function popVerify(pack) {
  var prev = 'GENESIS', firstBad = null;
  pack.events.forEach(function (ev, i) {
    var body = Object.assign({}, ev); delete body.h;
    var h = popCrypto.sha256Hex(prev + popCrypto.canon(body));
    if (ev.h !== undefined && ev.h !== h && firstBad === null) firstBad = i;
    prev = h;
  });
  var head = popCrypto.sha256Hex(prev + popCrypto.canon({ final_text: pack.final_text }));
  var valid = head === pack.chain_head && firstBad === null;
  return { valid: valid, first_bad_index: firstBad, chain_head: head };
}

/* Attach replay UI. opts: {text, play, speed, scrub, note}. */
function popReplay(pack, opts) {
  var evs = pack.events;
  var t0 = evs.length ? evs[0].t : 0;
  var tEnd = evs.length ? evs[evs.length - 1].t : 0;
  var dur = Math.max(1, tEnd - t0);
  var speeds = [1, 4, 8], spIdx = 0;
  var cursor = 0, playing = false, raf = null, lastFrame = 0;

  opts.scrub.min = 0; opts.scrub.max = dur; opts.scrub.value = 0;

  // Build a char timeline: each burst contributes n placeholder chars; pastes are separate.
  var marks = []; // {ms, kind, n}
  evs.forEach(function (ev) {
    if (ev.type === 'burst') marks.push({ ms: ev.t - t0, kind: 'typed', n: ev.n });
    else if (ev.type === 'paste') marks.push({ ms: ev.t - t0, kind: 'pasted', n: ev.n });
  });
  var fastMarks = 0;
  evs.forEach(function (ev) {
    if (ev.type === 'burst' && ev.d > 0 && (ev.n / (ev.d / 1000)) > 12) fastMarks++;
  });

  function render(ms) {
    var typedBudget = 0, pastedBudget = 0, i;
    for (i = 0; i < marks.length && marks[i].ms <= ms; i++) {
      if (marks[i].kind === 'typed') typedBudget += marks[i].n;
      else pastedBudget += marks[i].n;
    }
    // Typed chars consume the final text from the front, pasted appended at their position.
    var text = pack.final_text.slice(0, Math.min(pack.final_text.length, typedBudget));
    opts.text.textContent = text + '▮';
    opts.note.textContent =
      Math.round(ms / 1000) + 's / ' + Math.round(dur / 1000) + 's · ' +
      typedBudget + ' typed chars shown' +
      (pastedBudget ? ' · ' + pastedBudget + ' chars arrived by paste' : '') +
      (fastMarks ? ' · ' + fastMarks + ' unusually fast burst(s) flagged' : '');
  }

  function frame(ts) {
    if (!playing) return;
    var dt = lastFrame ? ts - lastFrame : 0;
    lastFrame = ts;
    cursor += dt * speeds[spIdx];
    if (cursor >= dur) { cursor = dur; stop(); }
    opts.scrub.value = cursor;
    render(cursor);
    raf = requestAnimationFrame(frame);
  }
  function play() { playing = true; lastFrame = 0; raf = requestAnimationFrame(frame); opts.play.textContent = 'Pause'; }
  function stop() { playing = false; cancelAnimationFrame(raf); opts.play.textContent = 'Play'; }

  opts.play.addEventListener('click', function () {
    if (playing) stop();
    else { if (cursor >= dur) cursor = 0; play(); }
  });
  opts.speed.addEventListener('click', function () {
    spIdx = (spIdx + 1) % speeds.length;
    opts.speed.textContent = 'Speed ' + speeds[spIdx] + '×';
  });
  opts.scrub.addEventListener('input', function () {
    stop(); cursor = +opts.scrub.value; render(cursor);
  });

  render(0);
}
