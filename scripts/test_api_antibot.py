# -*- coding: utf-8 -*-
"""
تست عبور از محافظت ضدربات هاست + کلاینت API (آفلاین — بدون شبکه):

۱) بردارهای استاندارد FIPS-197 برای AES-128 خالصِ پایتون (رمز/رمزگشایی)
۲) حل چالش با اوراکل مستقل pycryptodome (CBC: AES_ECB_decrypt(a,c) XOR b)
۳) سناریوی واقعی tpptc.ir — چالش واقعی ثبت‌شده + پاسخ JSON واقعی ping
۴) جریان ApiClient با موک: چالش → حل → کوکی → JSON ؛ 404 → rest_route ؛
   خطاهای 5xx کلادفلر، پاسخ HTML بدون JSON، پایداری کوکی در Session

اجرا: python3 scripts/test_api_antibot.py
"""

import os
import sys
import unittest.mock as mock

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(BASE, "build", "tpp_salary", "python-app"))

fail = 0


def check(cond, msg):
    global fail
    print(("  PASS: " if cond else "  FAIL: ") + msg)
    if not cond:
        fail = 1


# ---------------------------------------------------------------------------
print("== 1) AES-128 خالص پایتون — بردارهای FIPS-197 ==")
from app.antibot import aes128_decrypt_block, looks_like_challenge, solve_cookie  # noqa: E402

KEY = bytes(range(16))
PT = bytes.fromhex("00112233445566778899aabbccddeeff")
CT = bytes.fromhex("69c4e0d86a7b0430d8cdb78070b4c55a")
check(aes128_decrypt_block(KEY, CT) == PT, "رمزگشایی بردار FIPS-197")

# بردارهای تصادفی با اوراکل مستقل pycryptodome (اوراکل نهایی اعتبار AES)
from Crypto.Cipher import AES as _REF_AES  # noqa: E402
import random as _rnd
for i in range(5):
    k = bytes(_rnd.randrange(256) for _ in range(16))
    c = bytes(_rnd.randrange(256) for _ in range(16))
    out = aes128_decrypt_block(k, c)
    ref = _REF_AES.new(k, _REF_AES.MODE_ECB).decrypt(c)
    check(out == ref, "رمزگشایی تصادفی #%d درست با اوراکل pycryptodome" % (i + 1))

# ---------------------------------------------------------------------------
print("== 2) حل چالش — اوراکل مستقل pycryptodome ==")
from Crypto.Cipher import AES  # noqa: E402

import random
for i in range(5):
    a = bytes(random.randrange(256) for _ in range(16))
    b = bytes(random.randrange(256) for _ in range(16))
    c = bytes(random.randrange(256) for _ in range(16))
    html = ('<html><body><script type="text/javascript" src="/aes.js" ></script>'
            '<script>var a=toNumbers("%s"),b=toNumbers("%s"),c=toNumbers("%s");'
            'document.cookie="__test="+toHex(slowAES.decrypt(c,2,a,b))+"; path=/";'
            '</script></body></html>') % (a.hex(), b.hex(), c.hex())
    expect = bytes(x ^ y for x, y in zip(AES.new(a, AES.MODE_ECB).decrypt(c), b)).hex()
    check(solve_cookie(html) == expect, "چالش تصادفی #%d → %s" % (i + 1, expect))

check(solve_cookie("no challenge here") is None, "متن بدون چالش → None")
check(solve_cookie('<script>toNumbers("aabb")</script>') is None, "چالش ناقص → None")
check(looks_like_challenge('<html>slowAES.decrypt(c,2,a,b) toNumbers("aa")</html>'), "شناسایی چالش")
check(not looks_like_challenge('{"ok":true,"employees":[]}'), "JSON عادی چالش نیست")

# ---------------------------------------------------------------------------
print("== 3) سناریوی واقعی tpptc.ir (چالش ثبت‌شده + پاسخ واقعی) ==")
REAL_CHALLENGE = (
    '<html><body><script type="text/javascript" src="/aes.js" ></script>'
    '<script>function toNumbers(d){var e=[];d.replace(/(..)/g,function(d){e.push('
    'parseInt(d,16))});return e}function toHex(){for(var d=[],d=1==arguments.length'
    '&&arguments[0].constructor==Array?arguments[0]:arguments,e="",f=0;f<d.length;'
    'f++)e+=(16>d[f]?"0":"")+d[f].toString(16);return e.toLowerCase()}'
    'var a=toNumbers("f655ba9d09a112d4968c63579db590b4"),'
    'b=toNumbers("98344c2eee86c3994890592585b49f80"),'
    'c=toNumbers("548be347a645d77827f2b0bf7792257b");'
    'document.cookie="__test="+toHex(slowAES.decrypt(c,2,a,b))+'
    '"; max-age=21600; expires=Thu, 31-Dec-37 23:55:55 GMT; path=/"; '
    'location.href="https://tpptc.ir/wp-json/tpp_salary/v1/ping?i=1";</script>'
    '<noscript>This site requires Javascript</noscript></body></html>'
)
A = bytes.fromhex("f655ba9d09a112d4968c63579db590b4")
B = bytes.fromhex("98344c2eee86c3994890592585b49f80")
C = bytes.fromhex("548be347a645d77827f2b0bf7792257b")
expected_real = bytes(x ^ y for x, y in zip(AES.new(A, AES.MODE_ECB).decrypt(C), B)).hex()
got = solve_cookie(REAL_CHALLENGE)
check(got == expected_real, "چالش واقعی tpptc.ir → __test=%s" % got)

# ---------------------------------------------------------------------------
print("== 4) جریان ApiClient با موک (بدون شبکه) ==")
from app.api_client import ApiClient  # noqa: E402


class FakeResp(object):
    def __init__(self, status=200, text="", headers=None):
        self.status_code = status
        self.text = text
        self.headers = headers or {"Content-Type": "application/json"}

    def json(self):
        import json as _j
        return _j.loads(self.text)


class FakeFlow(object):
    """توالی پاسخ‌ها + ثبت هر درخواست (با side_effect — بدون self)."""

    def __init__(self, responses):
        self.responses = list(responses)
        self.calls = []

    def __call__(self, method, url, headers=None, json=None, timeout=None, params=None):
        self.calls.append({"method": method, "url": url,
                           "key": (headers or {}).get("X-TPP-Key")})
        return self.responses.pop(0)


cfg = {"api_url": "https://example.com", "api_key": "tppk_test"}
json_ok = FakeResp(200, '{"ok":true,"version":"1.7.4"}')

# 4-۱) چالش در اولین درخواست → حل → کوکی → JSON
flow = FakeFlow([
    FakeResp(200, REAL_CHALLENGE, {"Content-Type": "text/html"}),
    json_ok,
])
with mock.patch("requests.Session.request", side_effect=flow):
    client = ApiClient(cfg)
    data = client.ping()
check(data.get("ok") is True and data.get("version") == "1.7.4", "چالش → حل → ping JSON")
check(len(flow.calls) == 2, "دو درخواست ارسال شد")
check(dict(client._session.cookies).get("__test") == expected_real,
      "کوکی حل‌شده در Session ذخیره شد: %s" % dict(client._session.cookies))

# 4-۲) کوکی یک‌بار حل‌شده در درخواست‌های بعدی هم می‌ماند (بدون چالش دوباره)
flow2 = FakeFlow([FakeResp(200, '{"ok":true}')])
with mock.patch("requests.Session.request", side_effect=flow2):
    data = client.bundle()
check(data.get("ok") is True and len(flow2.calls) == 1, "درخواست بعدی مستقیم JSON (کوکی ماندگار)")

# 4-۳) 404 در حالت wp-json → rest_route
client2 = ApiClient(dict(cfg))
flow3 = FakeFlow([
    FakeResp(404, '{"code":"rest_no_route"}'),
    FakeResp(200, '{"ok":true,"via":"rest_route"}'),
])
with mock.patch("requests.Session.request", side_effect=flow3):
    data = client2.ping()
check(data.get("via") == "rest_route", "404 → fallback rest_route")
check("/?rest_route=" in flow3.calls[1]["url"], "URL دوم rest_route است")

# 4-۴) چالش + 404 با هم (بدترین حالت): چالش → 404 → rest_route → چالش → JSON
client3 = ApiClient(dict(cfg))
flow4 = FakeFlow([
    FakeResp(200, REAL_CHALLENGE, {"Content-Type": "text/html"}),
    FakeResp(404, '{"code":"rest_no_route"}'),
    FakeResp(200, REAL_CHALLENGE, {"Content-Type": "text/html"}),
    FakeResp(200, '{"ok":true}'),
])
with mock.patch("requests.Session.request", side_effect=flow4):
    data = client3.ping()
check(data.get("ok") is True, "چالش+404+چالش → موفقیت نهایی")
check(len(flow4.calls) == 4, "توالی صحیح تلاش‌ها")

# 4-۵) خطای 5xx کلادفلر → پیام فارسی قابل فهم
client4 = ApiClient(dict(cfg))
flow5 = FakeFlow([FakeResp(520, "error code: 520", {"Content-Type": "text/html"})])
with mock.patch("requests.Session.request", side_effect=flow5):
    try:
        client4.ping()
        check(False, "520 باید خطا بدهد")
    except Exception as exc:
        check("در دسترس نیست" in str(exc), "پیام ۵۲۰ فارسی: %s" % exc)

# 4-۶) پاسخ HTML بدون JSON → پیام مناسب
client5 = ApiClient(dict(cfg))
flow6 = FakeFlow([FakeResp(200, "<html>unknown html page</html>", {"Content-Type": "text/html"})])
with mock.patch("requests.Session.request", side_effect=flow6):
    try:
        client5.ping()
        check(False, "HTML ناشناخته باید خطا بدهد")
    except Exception as exc:
        check("پاسخ نامعتبر از سرور (JSON)" in str(exc), "پیام HTML ناشناخته: %s" % exc)

# 4-۷) status_text موفق (داخل patch — بدون شبکه واقعی)
flow7 = FakeFlow([FakeResp(200, '{"ok":true,"version":"1.7.4"}')])
with mock.patch("requests.Session.request", side_effect=flow7):
    ok, msg, data = client.status_text()
check(ok and "1.7.4" in msg, "status_text موفق: %s" % msg)

# 4-۸) UA پیش‌فرض مرورگر است
h = client._headers()
check("Mozilla/5.0" in h.get("User-Agent", ""), "UA پیش‌فرض مرورگری")
check(h.get("X-TPP-Key") == "tppk_test", "هدر کلید حفظ شده")

print("RESULT:", "FAIL" if fail else "ALL PASS")
sys.exit(fail)
