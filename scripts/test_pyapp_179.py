# -*- coding: utf-8 -*-
"""
تست‌های نسخه 1.7.9 نرم‌افزار آفلاین پایتون (درخواست کاربر):
  ۱) رکورد دقیق دوره (record_period) — آینه افزونه
  ۲) فرم ثبت حقوق: بارگذاری رکورد ثبت‌شدهٔ همان دوره (هم‌سان با فرم ویرایش افزونه)
  ۳) فرم ثبت حقوق: تکمیل خودکار پیش‌فرض از فیش ماه قبل
  ۴) محاسبهٔ زندهٔ خودکار — آینه TPP.recalc افزونه (تغییر تعداد فرزند ← حق اولاد)
  ۵) فیلد دستی تا تغییر منابع دستی می‌ماند؛ با تغییر منبع آزاد می‌شود
  ۶) اکسل بکاپ: قالب ستونی هم‌سان با گزارش افزونه (نام کارمند = ستون، عناوین حقوق = سطر)
     — حذف شیت سطری قدیمی، فیلدهای فقط‌محاسباتی/همه‌صفر، قالب عددی با منفی قرمز

اجرا (بدون نمایش پنجره): QT_QPA_PLATFORM=offscreen python3 scripts/test_pyapp_179.py
"""

import json
import os
import shutil
import sys
import tempfile

os.environ["QT_QPA_PLATFORM"] = "offscreen"
os.environ.setdefault("QT_LOGGING_RULES", "*.debug=false")

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(BASE, "build", "tpp_salary", "python-app"))

fail = 0


def check(cond, msg):
    global fail
    print(("  PASS: " if cond else "  FAIL: ") + msg)
    if not cond:
        fail = 1


from PySide6.QtWidgets import QApplication  # noqa: E402

app = QApplication.instance() or QApplication(sys.argv)

from types import SimpleNamespace  # noqa: E402

from app import jalali as J  # noqa: E402
from app.config import Config  # noqa: E402
from app.database import Database  # noqa: E402
from app.excel_backup import ExcelBackupManager  # noqa: E402
from app.store import Store  # noqa: E402

tmp = tempfile.mkdtemp(prefix="tpp_179_")
db = Database(tmp)
store = Store(db)
win = SimpleNamespace(store=store)  # EmployeeForm فقط store را لازم دارد

print("== 1) داده‌های آزمون ==")
for f in [
    {"key": "daily_wage", "label": "دستمزد روزانه", "type": "number", "default": "", "formula": "", "calculated": 0, "allow_manual": 1, "sort": 1},
    {"key": "work_days", "label": "روز کارکرد", "type": "number", "default": "30", "formula": "", "calculated": 0, "allow_manual": 1, "sort": 2},
    {"key": "children", "label": "تعداد فرزند", "type": "number", "default": "", "formula": "", "calculated": 0, "allow_manual": 1, "sort": 3},
    {"key": "child_rate", "label": "حق اولاد هر فرزند", "type": "number", "default": "", "formula": "", "calculated": 0, "allow_manual": 1, "sort": 4},
    {"key": "child_allowance", "label": "حق اولاد", "type": "number", "default": "", "formula": "{children}*{child_rate}", "calculated": 1, "allow_manual": 1, "sort": 5},
    {"key": "overtime_hours", "label": "ساعات اضافه کاری", "type": "number", "default": "", "formula": "", "calculated": 0, "allow_manual": 1, "sort": 6},
    {"key": "food", "label": "حق بن", "type": "number", "default": "", "formula": "", "calculated": 0, "allow_manual": 1, "sort": 7},
    {"key": "gross", "label": "جمع ناخالص", "type": "number", "default": "", "formula": "{daily_wage}*{work_days}", "calculated": 1, "allow_manual": 1, "sort": 20},
]:
    db.ex(
        "INSERT INTO fields(field_key, label, type, default_value, formula, calculated, allow_manual, sort) VALUES(?,?,?,?,?,?,?,?)",
        (f["key"], f["label"], f["type"], f["default"], f["formula"], f["calculated"], f["allow_manual"], f["sort"]),
    )

cid = store.save_center(0, "کارگاه مرکزی")
eid = store.save_employee({
    "id": 0, "name": "علی رضایی", "national": "9000000001", "mobile": "0912",
    "centers": [cid],
    "profile": {"daily_wage": 6000000, "work_days": 30, "children": 2, "child_rate": 500000},
})
emp = store.employee(eid)
check(emp is not None, "کارمند آزمون ساخته شد")

# فیش ماه قبل (تیر ۱۴۰۴) — مبنا برای تکمیل خودکار مرداد
rec_prev = store.upsert_record(eid, cid, 1404, 4, {
    "daily_wage": 6000000, "work_days": 30, "children": 2, "child_rate": 500000,
    "overtime_hours": 8, "food": 2200000, "gross": 180000000,
}, False)
check(rec_prev is not None, "فیش ماه قبل ثبت شد")

print("== 2) store.record_period ==")
check(store.record_period(eid, cid, 1404, 4) is not None, "رکورد دورهٔ دقیق پیدا می‌شود")
check(store.record_period(eid, cid, 1404, 5) is None, "دورهٔ بدون رکورد → None")
check(store.record_period(eid, 999, 1404, 4) is None, "مرکز اشتباه → None")

print("== 3) فرم: تکمیل خودکار از ماه قبل ==")
from app.ui.pages.register import EmployeeForm  # noqa: E402

form = EmployeeForm(win, emp, cid, 1404, 5)
check(form.inputs["daily_wage"].text() == "6000000", "دستمزد روزانه از فیش ماه قبل: %s" % form.inputs["daily_wage"].text())
check(form.inputs["work_days"].text() == "30", "روز کارکرد از فیش ماه قبل")
check(form.inputs["children"].text() == "2", "تعداد فرزند از فیش ماه قبل")
check(form.inputs["overtime_hours"].text() == "8", "ساعات اضافه کاری از فیش ماه قبل")
check(form.inputs["food"].text() == "2200000", "حق بن از فیش ماه قبل")
check(form.inputs["child_allowance"].text() == "1000000", "حق اولاد محاسبهٔ زندهٔ اولیه: %s" % form.inputs["child_allowance"].text())
check(form.inputs["gross"].text() == "180000000", "ناخالص محاسبهٔ زندهٔ اولیه: %s" % form.inputs["gross"].text())
check("تکمیل خودکار" in form.src_label.text(), "برچسب منبع: %s" % form.src_label.text())
check("تیر 1404" in form.src_label.text(), "دورهٔ مبدأ در برچسب: %s" % form.src_label.text())

print("== 4) محاسبهٔ زنده: تغییر فرزند ← حق اولاد (آینه TPP.recalc) ==")
form.inputs["children"].setText("3")
check(form.inputs["child_allowance"].text() == "1500000", "حق اولاد خودکار ← ۳×۵۰۰,۰۰۰: %s" % form.inputs["child_allowance"].text())
check("children" in form.manual_keys, "فیلد ویرایش‌شده دستی علامت خورد")

print("== 5) فیلد دستی و آزادسازی با تغییر منبع ==")
form.inputs["child_allowance"].setText("999")
check(form.inputs["child_allowance"].text() == "999", "مقدار دستی حفظ می‌شود (منبع تغییر نکرده)")
form.inputs["work_days"].setText("31")
check(form.inputs["child_allowance"].text() == "999", "تغییر منبعِ بی‌ربط، فیلد دستی را نمی‌شکند")
check(form.inputs["gross"].text() == "186000000", "ناخالص با تغییر روز کارکرد خودکار به‌روز شد: %s" % form.inputs["gross"].text())
form.inputs["children"].setText("4")
check(form.inputs["child_allowance"].text() == "2000000", "با تغییر منبع (فرزند) فیلد دستی آزاد و بازمحاسبه شد: %s" % form.inputs["child_allowance"].text())

print("== 6) دکمهٔ محاسبه (اجباری) ==")
form.inputs["gross"].setText("555")
form.force_recalc()
check(form.inputs["gross"].text() == "186000000", "بازمحاسبهٔ اجباری همهٔ فیلدهای محاسباتی: %s" % form.inputs["gross"].text())
check(form.manual_keys == [], "فلگ‌های دستی پس از محاسبهٔ اجباری پاک شد")

print("== 7) فرم: ویرایش رکورد ثبت‌شدهٔ همان دوره ==")
rec_cur = store.upsert_record(eid, cid, 1404, 5, {
    "daily_wage": 7000000, "work_days": 30, "children": 3, "child_rate": 500000,
    "gross": 210000000, "child_allowance": 1500000, "overtime_hours": 0, "food": 0,
}, False)
check(store.record_period(eid, cid, 1404, 5) is not None, "رکورد مرداد برای حالت ویرایش ثبت شد")
form2 = EmployeeForm(win, emp, cid, 1404, 5)
check(form2.inputs["daily_wage"].text() == "7000000", "مقدار رکورد موجود بارگذاری شد (نه پروفایل/ماه قبل): %s" % form2.inputs["daily_wage"].text())
check("ویرایش رکورد" in form2.src_label.text(), "برچسب حالت ویرایش: %s" % form2.src_label.text())
check(form2.inputs["child_allowance"].text() == "1500000", "حق اولاد رکورد موجود (دستی ذخیره‌شده) حفظ شد: %s" % form2.inputs["child_allowance"].text())

print("== 8) ذخیرهٔ فرم و پیوستگی محاسبه ==")
# مانند کاربر واقعی: تغییر در فرم ← محاسبهٔ زنده ← ذخیره
form2.inputs["children"].setText("5")
check(form2.inputs["child_allowance"].text() == "2500000", "محاسبهٔ زنده پیش از ذخیره: %s" % form2.inputs["child_allowance"].text())
vals = form2.collect_values()
store.upsert_record(eid, cid, 1404, 5, vals, form2.chk_formula.isChecked())
rec = store.record(rec_cur)
payload = json.loads(rec["payload"])
check(payload["child_allowance"] == 2500000.0, "ذخیرهٔ مجدد ← حق اولاد ۵×۵۰۰,۰۰۰: %s" % payload["child_allowance"])

print("== 9) اکسل بکاپ: قالب ستونی هم‌سان با گزارش افزونه ==")
# دوره/مرکز دوم برای آزمون گروه‌بندی شیت‌ها
cid2 = store.save_center(0, "کارگاه شمالی")
eid2 = store.save_employee({"id": 0, "name": "مریم احمدی", "national": "9000000002", "mobile": "0913", "centers": [cid2], "profile": {}})
store.upsert_record(eid2, cid2, 1404, 4, {"daily_wage": 9000000, "work_days": 30, "children": 1, "child_rate": 500000, "gross": 270000000, "child_allowance": 500000, "overtime_hours": 2, "food": 1100000}, False)

mgr = ExcelBackupManager(db, store, tmp)
path = mgr.backup_now(reason="manual", force=True)
check(path is not None and os.path.exists(path), "فایل بکاپ ساخته شد: %s" % os.path.basename(path or ""))

from openpyxl import load_workbook  # noqa: E402

wb = load_workbook(path)
names = wb.sheetnames
check(not any(n == "حقوق و دستمزد" for n in names), "شیت سطری قدیمی «حقوق و دستمزد» حذف شد")
sal_names = [n for n in names if n.startswith("لیست ")]
check(len(sal_names) >= 2, "یک شیت ستونی به‌ازای هر دوره ساخته شد: %s" % sal_names)
check(any("1404-04" in n for n in sal_names), "نام شیت شامل دورهٔ 1404-04: %s" % [n for n in sal_names if "1404-04" in n])
check(any("1404-05" in n for n in sal_names), "نام شیت شامل دورهٔ 1404-05")

ws = wb[[n for n in sal_names if "1404-05" in n][0]]
check(ws.sheet_view.rightToLeft is True, "شیت راست‌به‌چپ")
check("لیست حقوق" in str(ws.cell(row=1, column=1).value), "سطر عنوان: %s" % ws.cell(row=1, column=1).value)
check("مرداد 1404" in str(ws.cell(row=1, column=1).value), "دوره در عنوان سطر ۱")
check(str(ws.cell(row=2, column=1).value).startswith("واحد:"), "سطر واحد پول: %s" % ws.cell(row=2, column=1).value)
check(ws.cell(row=4, column=1).value == "عناوین", "هدر «عناوین» در سطر ۴")
check(ws.cell(row=4, column=2).value == "علی رضایی", "نام کارمند = عنوان ستون: %s" % ws.cell(row=4, column=2).value)

rows = {}
for r in range(5, ws.max_row + 1):
    lab = ws.cell(row=r, column=1).value
    if lab:
        rows[str(lab)] = r
check("دستمزد روزانه" in rows and ws.cell(row=rows["دستمزد روزانه"], column=2).value == 7000000, "سطر دستمزد روزانه با مقدار فیش مرداد")
check("حق اولاد" in rows and ws.cell(row=rows["حق اولاد"], column=2).value == 2500000, "سطر حق اولاد = ۲,۵۰۰,۰۰۰")
check("ساعات اضافه کاری" not in rows, "فیلد فقط‌محاسباتی (ساعات اضافه کاری) حذف شد — آینه افزونه")
check("حق بن" not in rows, "فیلد عددی همه‌صفر دوره (حق بن) حذف شد — آینه افزونه")
neg_row = rows.get("دستمزد روزانه")
check(ws.cell(row=neg_row, column=2).number_format == "#,##0;[Red]-#,##0", "قالب عددی با جداکننده و منفی قرمز: %s" % ws.cell(row=neg_row, column=2).number_format)

ws_apr = None
ws_apr_maryam = None
for n in [n for n in sal_names if "1404-04" in n]:
    w = wb[n]
    hdr = [w.cell(row=4, column=c).value for c in range(2, w.max_column + 1)]
    if "علی رضایی" in hdr:
        ws_apr = w
    if "مریم احمدی" in hdr:
        ws_apr_maryam = w
        check("کارگاه شمالی" in str(w.cell(row=1, column=1).value), "عنوان شیت مرکز دوم شامل نام مرکز")
check(ws_apr is not None and ws_apr_maryam is not None, "شیت‌های جدا برای دو مرکز دورهٔ 1404-04")
apr_names = [ws_apr.cell(row=4, column=c).value for c in range(2, ws_apr.max_column + 1)] if ws_apr else []
check("علی رضایی" in apr_names and "مریم احمدی" not in apr_names, "هر شیت فقط کارمندان همان دوره/مرکز: %s" % apr_names)

shutil.rmtree(tmp, ignore_errors=True)
print("\n" + ("SOME TESTS FAILED" if fail else "ALL PASS"))
sys.exit(1 if fail else 0)
