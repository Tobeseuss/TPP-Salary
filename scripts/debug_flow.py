# -*- coding: utf-8 -*-
"""Debug flow: challenge -> solve -> retry, inspecting actual Cookie header sent."""
import os
import sys

import requests

sys.path.insert(0, os.path.join(os.path.dirname(__file__)))
from test_live_api import solve_challenge, UA  # noqa: E402

URL = "https://tpptc.ir/wp-json/salary/v1/ping"  # deliberately wrong path to see behavior? NO — use real
URL = "https://tpptc.ir/wp-json/tpp_salary/v1/ping"


def describe(r):
    body = r.text
    kind = "CHALLENGE" if "toNumbers" in body else ("JSON" if body.strip().startswith("{") else "OTHER")
    setc = r.headers.get("Set-Cookie", "-")
    return "%s status=%s type=%s set-cookie=%s len=%d" % (kind, r.status_code, r.headers.get("content-type"), setc, len(body))


s = requests.Session()
s.headers.update({"User-Agent": UA, "Accept": "application/json"})

for hop in range(1, 6):
    r = s.get(URL if hop > 1 else URL, timeout=30)
    print("hop%d GET %s\n   -> %s" % (hop, os.path.basename(URL), describe(r)))
    print("   sent Cookie header: %r" % r.request.headers.get("Cookie"))
    print("   jar: %s" % dict(s.cookies))
    if "toNumbers" in r.text:
        val = solve_challenge(r.text)
        print("   solved __test=%s" % val)
        s.cookies.set("__test", val, domain="tpptc.ir", path="/")
        # also try setting via raw header on next request
    elif hop >= 3:
        break
