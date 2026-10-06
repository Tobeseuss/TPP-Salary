# -*- coding: utf-8 -*-
"""تست‌های نسخه 1.8.1 — درخواست کاربر:

«از وقتی این پلاگین را فعال کرده ام وبسایتم بسیار کند هست و بالا نمی آید»

پوشش تست (برنامه پایتون — سمت کلاینت بهینه‌سازی همگام‌سازی):
  ۱) ApiClient.bundle(rev) — ارسال rev به‌صورت query param (سازگار با هر دو حالت لینک REST)
  ۲) ApiClient.push(ops, rev) — rev در بدنه POST /sync
  ۳) SyncEngine — پاسخ سبک not_modified مصرف می‌شود: بدون بازسازی دیتابیس محلی،
     پیام «بدون تغییر»، و فلگ not_modified
  ۴) پس از pull کامل، revision بسته در kv ذخیره می‌شود و pull بعدی همان را می‌فرستد
  ۵) سازگاری با سرور قدیمی: bundle بدون rev → بدون پارامتر
Run: QT_QPA_PLATFORM=offscreen python3 scripts/test_pyapp_181.py
"""
import os
import sys
import tempfile

os.environ["QT_QPA_PLATFORM"] = "offscreen"
APP = os.environ.get("TPP_SMOKE_APP", "/home/z/my-project/build/tpp_salary/python-app")
sys.path.insert(0, APP)

fail = 0
def check(cond, msg):
    global fail
    print(("  PASS: " if cond else "  FAIL: ") + msg)
    if not cond:
        fail = 1

from PySide6.QtWidgets import QApplication
app = QApplication.instance() or QApplication([])
tmp = tempfile.mkdtemp(prefix="tpp181_")

from app.ui.app_window import MainWindow  # noqa: E402
from app.sync_engine import SyncEngine, SyncResult  # noqa: E402
from app import jalali as J  # noqa: E402

win = MainWindow(tmp)
db = win.db

print("== 0) نسخه و اجزا ==")
ver_txt = open(os.path.join(APP, "README.md"), encoding="utf-8").read()
check("1.8.1" in ver_txt, "README برنامه به 1.8.1 اشاره می‌کند")

print("== 1) ApiClient.bundle(rev) — پارامتر rev ==")
from app.api_client import ApiClient
captured = []

class FakeSession(object):
    def request(self, method, url, headers=None, json=None, params=None, timeout=0):
        captured.append({"method": method, "url": url, "json": json, "params": params})
        class R(object):
            status_code = 200
            text = "{}"
            def json(self):
                return {}
        return R()

client = ApiClient(win.config)
client._session = FakeSession()
client._plain_mode = False
client.config.data["api_url"] = "https://example.test"
client.config.data["api_key"] = "tppk_test"

client.bundle()
check(captured[-1]["params"] is None and captured[-1]["url"].endswith("/bundle"),
      "بدون rev → بدون پارامتر (سازگاری سرور قدیمی)")
client.bundle("abc123")
check(captured[-1]["params"] == {"rev": "abc123"},
      "با rev → پارامتر rev ارسال می‌شود")

# حالت rest_route (پیوند یکتای خاموش) — params استاندارد requests مسیر را خراب نمی‌کند
client._plain_mode = True
client.bundle("xyz789")
check("rest_route=" in captured[-1]["url"] and captured[-1]["params"] == {"rev": "xyz789"},
      "در حالت rest_route هم rev به‌درستی ارسال می‌شود")
client._plain_mode = False

print("== 2) ApiClient.push(ops, rev) — rev در بدنه ==")
client.push([{"ref": "op1", "action": "record.upsert"}], "rev42")
body = captured[-1]["json"]
check(body.get("rev") == "rev42" and isinstance(body.get("ops"), list),
      "بدنه POST /sync شامل ops و rev است")
client.push([{"ref": "op2", "action": "record.delete"}])
check("rev" not in captured[-1]["json"], "بدون rev → بدنه مثل قبل (سازگاری)")

print("== 3) SyncEngine — مصرف پاسخ سبک not_modified ==")
last_calls = {"bundle": [], "push": []}

class FakeApi(object):
    """سرور شبیه‌سازی‌شده با revision — چرخه کامل."""
    def __init__(self):
        self.rev = "r1"
        self.full_calls = 0
    def ping(self):
        return {"ok": True, "version": "1.8.1", "schema": 1}
    def bundle(self, rev=None):
        last_calls["bundle"].append(rev)
        if rev and rev == self.rev:
            return {"not_modified": True, "revision": self.rev, "schema": 1}
        self.full_calls += 1
        return self._full()
    def _full(self):
        return {
            "schema": 1, "revision": self.rev,
            "company_name": "شرکت آزمون", "currency": "ریال", "digits_fa": 0,
            "formulas": {}, "defaults": {},
            "period": {"jyear": 1404, "jmonth": 7},
            "fields": [], "profile_fields": [],
            "centers": [], "banks": [], "employees": [], "records": [],
        }
    def push(self, ops, rev=None):
        last_calls["push"].append(rev)
        return {"results": [], "bundle": self._full()}

class FakeCfg(object):
    def get(self, k, d=None):
        return d

fake_api = FakeApi()
engine = SyncEngine(db, win.store, fake_api, FakeCfg())

# چرخه اول: pull کامل — revision ذخیره می‌شود
res1 = engine.sync_now()
check(res1.online and res1.errors == [], "چرخه اول sync موفق بود")
check(db.kv_get("bundle_revision", "") == "r1", "revision بسته پس از pull کامل در kv ذخیره شد")
check(fake_api.full_calls == 1, "اولین pull کامل بود (%d بار بازسازی)" % fake_api.full_calls)

# چرخه دوم: داده سرور تغییر نکرده → پاسخ سبک، بدون بازسازی
res2 = engine.sync_now()
check(res2.online and not res2.errors, "چرخه دوم sync موفق بود")
check(res2.not_modified is True, "فلگ not_modified ست شد")
check("بدون تغییر" in res2.summary(), "پیام خلاصه «بدون تغییر» است")
check(last_calls["bundle"][-1] == "r1", "pull دوم همان revision r1 را فرستاد")
check(fake_api.full_calls == 1, "بازسازی کامل انجام نشد (بار سرور = صفر)")

# تغییر داده سرور → rev جدید → pull کامل دوباره
fake_api.rev = "r2"
res3 = engine.sync_now()
check(res3.not_modified is False and res3.errors == [], "پس از تغییر سرور، pull کامل انجام شد")
check(db.kv_get("bundle_revision", "") == "r2", "revision جدید r2 ذخیره شد")
check(fake_api.full_calls == 2, "بازسازی کامل فقط یک بار برای داده جدید")

# چرخه چهارم: دوباره سبک
res4 = engine.sync_now()
check(res4.not_modified is True and last_calls["bundle"][-1] == "r2", "چرخه بعدی دوباره سبک شد (revision r2)")

print("== 4) سازگاری — سرور قدیمی بدون revision ==")
class OldServerApi(FakeApi):
    def ping(self):
        return {"ok": True, "version": "1.8.1", "schema": 1}
    def bundle(self, rev=None):
        last_calls["bundle"].append(rev)
        self.full_calls += 1
        out = self._full()
        out.pop("revision", None)  # سرور قدیمی: بدون کلید revision
        return out

db.kv_set("bundle_revision", "")
old_api = OldServerApi()
old_engine = SyncEngine(db, win.store, old_api, FakeCfg())
res5 = old_engine.sync_now()
check(res5.online and res5.errors == [] and res5.not_modified is False, "سرور قدیمی (بدون revision) مشکلی ندارد")
check(db.kv_get("bundle_revision", "") == "", "کلید revision خالی می‌ماند و هر بار pull کامل می‌شود")

print("")
if fail:
    print("RESULT: FAIL")
    sys.exit(1)
print("RESULT: ALL PASS")
sys.exit(0)
