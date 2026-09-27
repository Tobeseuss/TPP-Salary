# -*- coding: utf-8 -*-
"""
تست منطق نرم‌افزار آفلاین پایتون (بدون GUI):
- تقویم جلالی و ارقام
- موتور فرمول (آینه PHP)
- Store آفلاین: رکورد/کارمند/مرکز + صف outbox
- SyncEngine: push (نگاشت شناسه منفی→سرور) / pull (هم‌سانی) / تداخل / آفلاین

اجرا: python3 scripts/test_pyapp_sync.py
"""

import json
import os
import shutil
import sys
import tempfile

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(BASE, "build", "tpp_salary", "python-app"))

fail = 0


def check(cond, msg):
    global fail
    print(("  PASS: " if cond else "  FAIL: ") + msg)
    if not cond:
        fail = 1


print("== 1) تقویم جلالی ==")
from app import jalali as J  # noqa: E402

check(J.gregorian_to_jalali(2025, 8, 22) == (1404, 5, 31), "تبدیل 2025-08-22 → 1404/05/31 (مرداد ۳۱): %s" % (J.gregorian_to_jalali(2025, 8, 22),))
check(J.gregorian_to_jalali(2026, 9, 5) == (1405, 6, 14), "تبدیل 2026-09-05 → 1405/06/14: %s" % (J.gregorian_to_jalali(2026, 9, 5),))
check(J.gregorian_to_jalali(2025, 3, 21) == (1404, 1, 1), "نوروز 1404")
check(J.fa_digits("1404/05") == "1404/05" and J.fa_digits("۱۴۰۴") == "1404" and J.fa_digits("٥") == "5", "fa_digits همیشه انگلیسی (نسخه 1.7.3)")
check(J.en_digits("۱۴۰۴") == "1404" and J.en_digits("٥") == "5", "ارقام فارسی/عربی → لاتین")
check(J.month_name(5) == "مرداد", "نام ماه")
check("مرداد 1404" == J.period_label(1404, 5), "برچسب دوره (ارقام انگلیسی)")
check(J.format_money(1234567) == "1,234,567", "قالب مبلغ: %s" % J.format_money(1234567))

print("== 2) موتور فرمول ==")
from app import formulas as F  # noqa: E402

check(F.evaluate("{base_salary}+{housing}", {"base_salary": 100, "housing": 50}) == 150, "جمع توکن‌ها")
check(F.evaluate("{daily_wage}*{work_days}", {"daily_wage": 6000000, "work_days": 30}) == 180000000, "ضرب")
check(F.evaluate("{a}-{b}", {"a": 10, "b": 3}) == 7, "تفریق")
check(F.evaluate("( {a}+{b} )*2", {"a": 3, "b": 4}) == 14, "پرانتز")
check(F.evaluate("-{a}+5", {"a": 3}) == 2, "منفی یگانی")
try:
    F.evaluate("{a}/0", {"a": 1})
    check(False, "تقسیم بر صفر باید خطا بدهد")
except F.FormulaError:
    check(True, "تقسیم بر صفر → FormulaError")
try:
    F.evaluate("__import__", {})
    check(False, "کاراکتر غیرمجاز باید خطا بدهد")
except F.FormulaError:
    check(True, "کاراکتر غیرمجاز → FormulaError")
check(F.evaluate("۷۰۰٬۰۰۰+{a}", {"a": 1}) == 700001, "ارقام فارسی و جداکننده در فرمول")
check(F.php_round(2.5) == 3 and F.php_round(-2.5) == -3, "گرد کردن هم‌سان PHP")
fields = [
    {"key": "gross", "label": "ناخالص", "formula": "", "calculated": 1},
    {"key": "insurable", "label": "مشمول", "formula": "{base_salary}", "calculated": 1},
]
g = {"gross": "{base_salary}+{housing}"}
# هم‌سان با PHP: پستِ ۱۲۰ با حاصل فرمول {base_salary}=۱۰۰ تفاوت دارد → دستی و حفظ ۱۲۰
vals, manual = F.compute_values({"base_salary": 100, "housing": 20, "gross": 0, "insurable": 120}, [], fields, [], g)
check(vals["insurable"] == 120 and "insurable" in manual, "اختلاف پست با فرمول → دستی و حفظ مقدار کاربر (هم‌سان PHP)")
# هم‌خوانی پست با فرمول → مقدار فرمول اعمال و دستی نمی‌شود
vals, manual = F.compute_values({"base_salary": 100, "housing": 20, "gross": 0, "insurable": 100}, [], fields, [], g)
check(vals["insurable"] == 100 and "insurable" not in manual, "هم‌خوانی پست با فرمول → مقدار فرمول اعمال شد")
# force مانند TPP.recalc: اجبار محاسبه بدون توجه به تشخیص دستی
vals, manual = F.compute_values({"base_salary": 100, "housing": 20, "gross": 0, "insurable": 120}, [], fields, ["gross", "insurable"], g)
check(vals["gross"] == 120 and vals["insurable"] == 100, "force → اجبار محاسبه (هم‌سان TPP.recalc)")

print("== 3) Store آفلاین ==")
tmp = tempfile.mkdtemp(prefix="tpp_py_test_")
from app.config import Config  # noqa: E402
from app.database import Database  # noqa: E402
from app.store import Store  # noqa: E402
from app.sync_engine import SyncEngine  # noqa: E402

db = Database(tmp)
store = Store(db)
cfg = Config(tmp)
check(store.use_fa_digits() is False, "use_fa_digits همیشه False (نسخه 1.7.3 — همه اعداد انگلیسی)")

# تنظیم فیلدها مثل بسته سرور
for f in [
    {"key": "daily_wage", "label": "دستمزد روزانه", "type": "number", "default": "", "formula": "", "calculated": 0, "allow_manual": 1, "sort": 1},
    {"key": "work_days", "label": "روز کارکرد", "type": "number", "default": "30", "formula": "", "calculated": 0, "allow_manual": 1, "sort": 2},
    {"key": "insurance_rate", "label": "نرخ بیمه", "type": "number", "default": "7", "formula": "", "calculated": 0, "allow_manual": 1, "sort": 3},
    {"key": "gross", "label": "جمع ناخالص", "type": "number", "default": "", "formula": "", "calculated": 1, "allow_manual": 1, "sort": 20},
    {"key": "insurable", "label": "مشمول بیمه", "type": "number", "default": "", "formula": "{daily_wage}*{work_days}", "calculated": 1, "allow_manual": 1, "sort": 21},
    {"key": "insurance_deduct", "label": "بیمه", "type": "number", "default": "", "formula": "{insurable}*{insurance_rate}/100", "calculated": 1, "allow_manual": 1, "sort": 22},
    {"key": "net", "label": "خالص", "type": "number", "default": "", "formula": "{gross}-{insurance_deduct}", "calculated": 1, "allow_manual": 1, "sort": 23},
]:
    db.ex(
        "INSERT INTO fields(field_key, label, type, default_value, formula, calculated, allow_manual, sort) VALUES(?,?,?,?,?,?,?,?)",
        (f["key"], f["label"], f["type"], f["default"], f["formula"], f["calculated"], f["allow_manual"], f["sort"]),
    )
db.kv_set("formulas", json.dumps({"gross": "{daily_wage}*{work_days}", "net": "{gross}-{insurance_deduct}"}))

cid = store.save_center(0, "کارگاه مرکزی")
check(cid < 0, "مرکز محلی با شناسه منفی: %s" % cid)
eid = store.save_employee({"id": 0, "name": "علی رضایی", "national": "9000000001", "mobile": "09120000000", "centers": [cid], "profile": {"daily_wage": 6000000}})
check(eid < 0, "کارمند محلی با شناسه منفی: %s" % eid)
emp = store.employee(eid)
check(emp and str(emp["profile"].get("daily_wage")) == "6000000", "پروفایل ذخیره شد")

rec_id = store.upsert_record(eid, cid, 1404, 5, {"daily_wage": "۶٬۰۰۰٬۰۰۰", "work_days": 30, "insurance_rate": 7}, False)
rec = store.record(rec_id)
check(rec["gross"] == 180000000, "ناخالص محاسبه شد: %s" % rec["gross"])
check(rec["insurable"] == 0, "مشمول در حالت پروفایلی بدون ورودی = ۰ و دستی (هم‌سان PHP): %s" % rec["insurable"])
check(rec["insurance_deduct"] == 0, "بیمه از مشمول صفر: %s" % rec["insurance_deduct"])
check(rec["net"] == 180000000, "خالص = ناخالص − بیمه: %s" % rec["net"])
payload = json.loads(rec["payload"])
check(payload["insurable_mode"] == "profile" and "insurable" in payload["manual"], "حالت غیرفرمولی → مشمول دستی")
check(store.pending_count() == 3, "سه op در صف (مرکز/کارمند/رکورد): %s" % store.pending_count())

# ویرایش همان دوره → همان رکورد
rec_id2 = store.upsert_record(eid, cid, 1404, 5, {"daily_wage": 7000000, "work_days": 30, "insurance_rate": 7}, True)
check(rec_id2 == rec_id, "ثبت مجدد همان دوره → همان رکورد به‌روز شد")
rec = store.record(rec_id)
check(rec["insurable"] == 210000000 and rec["net"] == 195300000, "محاسبه با حالت فرمولی: %s / %s" % (rec["insurable"], rec["net"]))

# پر کردن بر اساس گذشته — مبدأ = مرداد ۱۴۰۴ (همان دوره‌ای که رکورد دارد)
past, same = store.past_salary(eid, cid, 1404, 5)
check(past is not None and past["jmonth"] == 5 and same is True, "past_salary → رکورد همان مرکز پیدا شد")
rows, total, page, pages = store.records(search="علی")
check(total == 1 and rows[0]["employee_name"] == "علی رضایی", "جستجوی رکورد با نام کارمند")

# حذف رکورد منفی → صف آن هم پاک می‌شود؛ کارمند موقت هم حذف می‌شود (op آن هم)
tmp_eid = store.save_employee({"id": 0, "name": "موقت", "centers": []})
rec_tmp = store.upsert_record(tmp_eid, cid, 1404, 6, {"daily_wage": 1000, "work_days": 1, "insurance_rate": 0}, False)
n_before = store.pending_count()
store.delete_record(rec_tmp)
check(store.pending_count() == n_before - 1, "حذف رکورد محلی → op رکورد هم از صف پاک شد")
store.delete_employee(tmp_eid)  # کارمند موقت + op آن حذف می‌شود

print("== 4) همگام‌سازی — push و نگاشت شناسه ==")


class FakeApi:
    """سرور شبیه‌سازی‌شده — با نگاشت دسته مثل TppSalary_Api واقعی."""

    def __init__(self):
        self.online = True
        self.server = {
            "centers": [{"id": 50, "name": "کارگاه جنوبی", "created_at": "2026-01-01 00:00:00"}],
            "banks": [],
            "employees": [{"id": 70, "name": "کارمند سرور", "login": "7000000001", "email": "", "national": "", "mobile": "", "terminated": 0, "centers": [50], "profile": {}}],
            "records": [{"id": 900, "user_id": 70, "center_id": 50, "jyear": 1404, "jmonth": 4,
                          "payload": json.dumps({"daily_wage": 1, "gross": 1}), "gross": 1, "insurable": 1,
                          "insurance_deduct": 0, "other_deductions": 0, "net": 1, "updated_at": "2026-08-01 00:00:00", "created_at": "2026-08-01 00:00:00"}],
            "fields": [],
            "profile_fields": [],
            "company_name": "شرکت تست",
            "currency": "ریال",
            "digits_fa": 1,
            "formulas": {},
            "defaults": {},
            "period": {"jyear": 1404, "jmonth": 5},
            "schema": 1,
            "version": "1.7.0",
        }
        self.next_emp = 71
        self.next_center = 51
        self.next_record = 901

    def _bundle(self):
        b = dict(self.server)
        b["records"] = list(b["records"])
        return b

    def ping(self):
        if not self.online:
            raise RuntimeError("offline")
        return {"ok": True, "version": "1.7.0"}

    def bundle(self):
        return self._bundle()

    def push(self, ops):
        results = []
        id_map = {"employee": {}, "center": {}, "bank": {}}
        for op in ops:
            act = op["action"]
            if act == "employee.upsert":
                e = dict(op["employee"])
                e["centers"] = [id_map["center"].get(c, c) if c < 0 else c for c in e.get("centers", [])]
                if e.get("id", 0) > 0:
                    results.append({"ref": op["ref"], "status": "applied", "action": "updated", "server_id": e["id"]})
                else:
                    sid = self.next_emp
                    self.next_emp += 1
                    if e.get("id", 0) < 0:
                        id_map["employee"][e["id"]] = sid
                    self.server["employees"].append({"id": sid, "name": e["name"], "login": str(sid), "email": "", "national": e.get("national", ""), "mobile": e.get("mobile", ""), "terminated": e.get("terminated", 0), "centers": e.get("centers", []), "profile": e.get("profile", {})})
                    results.append({"ref": op["ref"], "status": "applied", "action": "created", "server_id": sid})
            elif act == "center.upsert":
                c = op["center"]
                if c.get("id", 0) > 0:
                    for row in self.server["centers"]:
                        if row["id"] == c["id"]:
                            row["name"] = c["name"]
                    results.append({"ref": op["ref"], "status": "applied", "action": "updated", "server_id": c["id"]})
                else:
                    sid = self.next_center
                    self.next_center += 1
                    if c.get("id", 0) < 0:
                        id_map["center"][c["id"]] = sid
                    self.server["centers"].append({"id": sid, "name": c["name"], "created_at": "2026-09-01 00:00:00"})
                    results.append({"ref": op["ref"], "status": "applied", "action": "created", "server_id": sid})
            elif act == "record.upsert":
                p = dict(op["payload"])
                if p["user_id"] < 0:
                    p["user_id"] = id_map["employee"].get(p["user_id"], p["user_id"])
                if p["center_id"] < 0:
                    p["center_id"] = id_map["center"].get(p["center_id"], p["center_id"])
                if p["user_id"] < 0 or p["center_id"] < 0:
                    results.append({"ref": op["ref"], "status": "error", "message": "مرجع همگام نشده"})
                    continue
                key = (p["user_id"], p["center_id"], p["jyear"], p["jmonth"])
                existing = [r for r in self.server["records"]
                            if (r["user_id"], r["center_id"], r["jyear"], r["jmonth"]) == key]
                if existing and op.get("client_updated_at") and str(existing[0]["updated_at"]) > str(op["client_updated_at"]):
                    results.append({"ref": op["ref"], "status": "conflict", "message": "نسخه سرور جدیدتر است",
                                    "server": {"id": existing[0]["id"], "updated_at": existing[0]["updated_at"]}})
                    continue
                values = p["values"]
                if existing:
                    rid = existing[0]["id"]
                    existing[0].update({
                        "payload": json.dumps(values, ensure_ascii=False),
                        "gross": values.get("gross", 0), "insurable": values.get("insurable", 0),
                        "insurance_deduct": values.get("insurance_deduct", 0),
                        "other_deductions": values.get("other_deductions", 0), "net": values.get("net", 0),
                        "updated_at": "2026-09-01 00:00:00",
                    })
                    results.append({"ref": op["ref"], "status": "applied", "action": "updated", "record_id": rid})
                else:
                    rid = self.next_record
                    self.next_record += 1
                    self.server["records"].append({
                        "id": rid, "user_id": p["user_id"], "center_id": p["center_id"],
                        "jyear": p["jyear"], "jmonth": p["jmonth"],
                        "payload": json.dumps(values, ensure_ascii=False),
                        "gross": values.get("gross", 0), "insurable": values.get("insurable", 0),
                        "insurance_deduct": values.get("insurance_deduct", 0),
                        "other_deductions": values.get("other_deductions", 0), "net": values.get("net", 0),
                        "updated_at": "2026-09-01 00:00:00", "created_at": "2026-09-01 00:00:00",
                    })
                    results.append({"ref": op["ref"], "status": "applied", "action": "created", "record_id": rid})
            elif act == "record.delete":
                rid = op["record_id"]
                self.server["records"] = [r for r in self.server["records"] if r["id"] != rid]
                results.append({"ref": op["ref"], "status": "applied", "action": "delete"})
            elif act == "center.delete":
                cid2 = op["center_id"]
                self.server["centers"] = [c for c in self.server["centers"] if c["id"] != cid2]
                results.append({"ref": op["ref"], "status": "applied", "action": "delete"})
            elif act == "employee.delete":
                eid2 = op["employee_id"]
                self.server["employees"] = [e for e in self.server["employees"] if e["id"] != eid2]
                results.append({"ref": op["ref"], "status": "applied", "action": "delete"})
            else:
                results.append({"ref": op["ref"], "status": "error", "message": "نامشخص"})
        return {"results": results, "bundle": self._bundle()}


api = FakeApi()
engine = SyncEngine(db, store, api, cfg)
res = engine.sync_now()
check(res.online and res.ok(), "همگام‌سازی اول موفق: %s" % res.summary())
check(res.pushed == 4, "چهار op ارسال شد (مرکز + کارمند + دو بار ذخیره رکورد): %s" % res.pushed)
check(store.pending_count() == 0, "صف خالی شد: %s" % store.pending_count())
emp = store.employee(eid)
check((emp is None) and store.employee(71) is not None, "شناسه کارمند به ۷۱ نگاشت شد")
emp = store.employee(71)
check(emp["name"] == "علی رضایی", "نام کارمند بعد از نگاشت سالم است")
rec = store.record(rec_id)
check(rec is None and store.record(901) is not None, "رکورد به شناسه سرور (۹۰۱) نگاشت شد")
rec = store.record(901)
check(rec["user_id"] == 71, "رکورد به کارمند نگاشت‌شده وصله: user_id=%s" % rec["user_id"])
check(store.center(cid) is None and store.center(51) is not None, "مرکز به ۵۱ نگاشت شد")
check(store.center(51)["name"] == "کارگاه مرکزی", "نام مرکز بعد از نگاشت سالم است")
check(rec["center_id"] == 51, "مرکز رکورد هم نگاشت شد: %s" % rec["center_id"])
check(rec["gross"] == 210000000, "مقدار نهایی رکورد (آخرین ذخیره) سالم است: %s" % rec["gross"])
check(store.kv_company() == "شرکت تست", "نام شرکت از سرور ذخیره شد")
check(store.kv_get("server_version") == "1.7.0", "نسخه سرور ذخیره شد")

print("== 5) همگام‌سازی — pull (حذف سمت سرور) ==")
api.server["records"] = [r for r in api.server["records"] if r["id"] != 901]
res = engine.sync_now()
check(res.ok(), "همگام‌سازی دوم موفق")
check(store.record(901) is None, "رکوردی که سرور حذف کرده بود، محلی هم حذف شد")

print("== 6) تداخل ==")
# رکورد سرور جدیدتر از تغییر محلی — ویرایش همان رکورد ۹۰۰ (کارمند ۷۰ / مرکز ۵۰ / ۱۴۰۴-۴)
local_rid = store.upsert_record(70, 50, 1404, 4, {"daily_wage": 5, "work_days": 1, "insurance_rate": 0}, False)
check(local_rid == 900, "ویرایش محلی همان رکورد سرور ۹۰۰: %s" % local_rid)
# تاریخ سرور را جدیدتر کنیم
for r in api.server["records"]:
    if r["id"] == 900:
        r["updated_at"] = "2027-01-01 00:00:00"
res = engine.sync_now()
check(res.conflicts or not res.ok(), "تداخل گزارش شد: %s" % (res.summary(),))
row = db.one("SELECT status FROM outbox WHERE status='conflict'")
check(row is not None, "op متداخل در صف با وضعیت conflict ماند")
db.ex("DELETE FROM outbox")
store2_pending = store.pending_count()
check(store2_pending == 0, "صف برای ادامه تست پاک شد")

print("== 7) آفلاین ماندن و صف ==")
api.online = False
store.save_center(0, "مرکز آفلاین")
res = engine.sync_now()
check(res.online is False, "با سرور قطع → نتیجه آفلاین")
check(store.pending_count() == 1, "تغییر محلی در صف ماند: %s" % store.pending_count())
check(len(store.centers()) == 3, "مرکز آفلاین در فهرست محلی هست (۳ مرکز): %s" % len(store.centers()))

print("== 8) اتصال دوباره → ارسال صف ==")
api.online = True
res = engine.sync_now()
check(res.ok() and res.pushed == 1 and store.pending_count() == 0, "پس از اتصال، صف ارسال و خالی شد: %s" % res.summary())

shutil.rmtree(tmp, ignore_errors=True)
print("\n" + ("SOME TESTS FAILED" if fail else "ALL PASS"))
sys.exit(1 if fail else 0)
