#!/usr/bin/env python3
"""End-to-end test for ProofOfPen against a running instance (base URL arg).
Exercises: create -> verify(id) -> verify(pack) -> tamper pack -> pack page
-> download -> offline pack json round-trip. Prints PASS/FAIL summary.
"""
import json, sys, urllib.request, urllib.error, re, base64

BASE = sys.argv[1].rstrip('/')
fails = []
def check(name, cond, extra=""):
    print(("ok  " if cond else "FAIL ") + name + ((" | " + extra) if extra and not cond else ""))
    if not cond: fails.append(name)

def post(path, payload, expect=200):
    req = urllib.request.Request(BASE + path, data=json.dumps(payload).encode(),
                                 headers={'Content-Type': 'application/json'}, method='POST')
    try:
        with urllib.request.urlopen(req) as r:
            return r.status, json.loads(r.read().decode())
    except urllib.error.HTTPError as e:
        return e.code, json.loads(e.read().decode())

def get(path):
    try:
        with urllib.request.urlopen(BASE + path) as r:
            return r.status, r.read().decode()
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode()

BASEMS = 1728500000000
events = [{"t": BASEMS, "type": "snapshot", "text": ""},
          {"t": BASEMS+1200, "type": "burst", "n": 4, "d": 900},
          {"t": BASEMS+2600, "type": "burst", "n": 5, "d": 1100},
          {"t": BASEMS+9800, "type": "pause", "d": 6000},
          {"t": BASEMS+10400, "type": "paste", "n": 40},
          {"t": BASEMS+12000, "type": "burst", "n": 30, "d": 4000},
          {"t": BASEMS+13000, "type": "snapshot", "text": "final-ish"}]

# 1. create
st, res = post('/api/create', {"events": events, "final_text": "Hello ProofOfPen world.", "title": "E2E", "author": "Bot"})
check("create returns 200 ok", st == 200 and res.get('ok'), str(res))
pack = res.get('pack', {})
pid = pack.get('id', '')
check("create returns id/url/download", len(pid) == 12 and '/p/' in pack.get('url', '') and pack.get('download', ''))

# 2. verify by id
st, res = post('/api/verify', {"id": pid})
check("verify by id: VALID", st == 200 and res['verdict']['valid'] is True, str(res.get('verdict')))
v = res['verdict']
check("verify stats present", v['stats']['typed_chars'] == 39 and v['stats']['pastes'] == 1, str(v.get('stats')))
check("verify warnings mention paste", any('paste' in w for w in v.get('warnings', [])))

# 3. verify by pack payload
st, res = post('/api/verify', {"pack": res['pack']})
check("verify by pack payload: VALID", st == 200 and res['verdict']['valid'] is True)

# 4. tamper: change one event
bad = json.loads(json.dumps(res['pack']))
bad['events'][2]['n'] = 6
st, res = post('/api/verify', {"pack": bad})
check("tampered event detected + first_bad_index=2", not res['verdict']['valid'] and res['verdict'].get('first_bad_index') == 2, str(res.get('verdict')))

# 5. tamper: edit final_text only
bad2 = json.loads(json.dumps(bad)); bad2['events'][2]['n'] = 5  # restore
bad2['final_text'] = 'Rewritten.'
st, res = post('/api/verify', {"pack": bad2})
check("tampered final_text detected", not res['verdict']['valid'])

# 6. bad input rejected
st, res = post('/api/create', {"events": [], "final_text": "x"})
check("empty events rejected 422", st == 422)
st, res = post('/api/create', {"events": [{"t": 5, "type": "burst", "n": 2}], "final_text": "x"})
check("non-ms timestamp rejected 422", st == 422)

# 7. pack page
st, html = get('/p.php/' + pid)
check("pack page renders 200", st == 200 and pid in html and 'VALID' in html, str(st))
st, html = get('/p.php/NOTAPACK!')
check("pack page bad id -> 404", st == 404)
st, html = get('/p.php/ZZZZZZZZ0000')
check("unknown pack -> 404", st == 404)

# 8. download self-contained html
st, html = get('/download.php?id=' + pid)
check("download returns html attachment", st == 200 and 'packB64' in html and 'popVerify' in html and 'chain head' in html.lower())
m = re.search(r'id="packB64">([A-Za-z0-9+/=]+)</script>', html)
check("pack b64 block extractable", bool(m))
if m:
    pack_dl = json.loads(base64.b64decode(m.group(1)))
    check("downloaded pack matches created pack", pack_dl['id'] == pid and pack_dl['chain_head'] == pack.get('chain_head'))
    st, res = post('/api/verify', {"pack": pack_dl})
    check("downloaded pack re-verifies VALID", res['verdict']['valid'] is True)

# 9. GET on POST-only endpoints
st, _ = get('/api/create')
check("GET /api/create -> 405", st == 405)

print()
print("ALL PASSED" if not fails else f"FAILURES: {fails}")
sys.exit(1 if fails else 0)
