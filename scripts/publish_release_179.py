#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""انتشار Release v1.7.9 در گیت‌هاب — توکن فقط از متغیر محیطی GITHUB_PAT خوانده می‌شود."""
import json
import os
import sys
import urllib.request

PAT = os.environ.get("GITHUB_PAT")
assert PAT, "GITHUB_PAT is required"
REPO = "Tobeseuss/TPP-Salary"
TAG = "v1.7.9"
DL = "/home/z/my-project/download"

NOTES = """## نسخه ۱.۷.۹ — هم‌سانی کامل آفلاین/آنلاین

درخواست کاربر: تکمیل خودکار فرم حقوق ماه جدید از ماه قبل (در هر دو نسخه)، محاسبات خودکار در نرم‌افزار پایتون، رفع مشکلات نمایش لیست‌ها، اکسل خروجی ستونی و تضمین سینک بی‌مشکل.

### تکمیل خودکار از فیش ماه قبل (پلاگین + پایتون)
- در صفحه ثبت حقوق، اگر برای ماه قبل کارمند فیش ثبت شده باشد، فیلدهای ماه جدید به‌صورت پیش‌فرض مطابق همان تکمیل می‌شود
- پلاگین: اعلان سبز دورهٔ مبدأ (اولویت رکورد همان مرکز، سپس مرکز دیگر)؛ فروردین ← اسفند سال قبل
- پایتون: برچسب «تکمیل خودکار بر اساس فیش …»؛ دکمهٔ «پر کردن از گذشته» برای مبدأ دلخواه حفظ شد
- رفع باگ: مقادیر قالب‌بندی‌شده با تبدیل float خراب می‌شدند (250,000,000 ← 250) — اکنون مقادیر خام به فرم می‌روند

### محاسبهٔ زندهٔ خودکار در نرم‌افزار پایتون
- آینه کامل TPP.recalc افزونه: با تغییر هر ورودی (مثلاً تعداد فرزند) فیلدهای فرمولی مانند حق اولاد بلافاصله به‌روز می‌شوند
- فیلد دستی فقط تا تغییر نیامدن منابع فرمولش دستی می‌ماند؛ دکمهٔ «محاسبه» = بازمحاسبهٔ اجباری
- فرم در حالت ویرایش مقادیر رکورد ثبت‌شدهٔ همان دوره را بارگذاری می‌کند (قبلاً پروفایل نمایش داده می‌شد)

### رابط کاربری
- پنجرهٔ افزودن/ویرایش کارمند کاملاً قابل اسکرول و محدود به اندازهٔ صفحه (با ۱۴+ فیلد پروفایل از صفحه بیرون نمی‌زند)
- لیست کارمندان مرحلهٔ ۲ ثبت حقوق تمام ارتفاع صفحه را می‌گیرد (قبلاً بسیار کوچک بود)

### اکسل بکاپ پایتون — قالب ستونی هم‌سان با گزارش افزونه
- به‌ازای هر «دوره + مرکز» یک شیت ستونی: عنوان ستون = نام کارمند، سطرها = عناوین حقوق
- سطر عنوان شرکت/دوره/مرکز + سطر واحد پول؛ اعداد با جداکننده هزارگان و منفی قرمز
- حذف فیلدهای فقط‌محاسباتی و فیلدهای همه‌صفر هر دوره — دقیقاً مانند گزارش پلاگین

### پروفایل و سینک
- همگام‌سازی خودکار ۱۴ فیلد پروفایل با آخرین فیش (نسخه ۱.۷.۸) برای فیش‌های ثبت‌شده در حالت آفلاین هم فعال است — راستی‌آزمایی مجدد مسیر REST → هستهٔ مشترک افزونه
- رگرسیون کامل سبز: ۲۹ تست PHP + تست‌های همگام‌سازی/ضدربات/دسترسی + اسموک (۸ صفحه/۳۳ دکمه)

**نصب:** افزونه = `tpp_salary-1.7.9-plugin.zip`؛ بسته کامل = `tpp-salary-v1.7.9-full.zip`؛ به‌روزرسانی نرم‌افزار دسکتاپ = `tpp-salary-python-app-1.7.9.zip` روی پوشه قبلی استخراج شود.

---

## v1.7.9 — Full offline/online parity

User request: auto-prefill the new month's salary form from the previous month (in both plugin and the Python app), working auto-calculations in the desktop app, list display fixes, columnar Excel output, and guaranteed clean sync.

- **Prev-month auto-prefill (plugin + Python):** if the employee has a payslip for the previous month, the new form is pre-filled from it by default (green notice, same-center priority, Farvardin → Esfand of the prior year); fixed the float-cast corruption of formatted values (250,000,000 → 250)
- **Live auto-calculation in Python:** mirrors the plugin's TPP.recalc — formula fields (e.g. child allowance) update instantly as inputs change; manual overrides are released only when their formula sources change; edit mode now loads the saved period record
- **UI fixes:** employee add/edit dialog fully scrollable and screen-bounded; the wizard's employee selection list now fills the page
- **Columnar Excel backup:** one sheet per period+center — employees as columns, salary items as rows, company/period/center title, currency row, thousand separators with red negatives, CALC_ONLY/all-zero fields dropped (same as the plugin report)
- **Profile & sync:** the v1.7.8 automatic profile sync also applies to offline-entered payslips (REST → shared upsert core re-verified); full regression green (29 PHP suites + sync/antibot/attr/smoke)

**Install:** plugin = `tpp_salary-1.7.9-plugin.zip`; full bundle = `tpp-salary-v1.7.9-full.zip`; desktop update = extract `tpp-salary-python-app-1.7.9.zip` over the previous folder.
"""


def api(method, path, data=None, is_json=True):
    req = urllib.request.Request(
        "https://api.github.com" + path,
        method=method,
        data=json.dumps(data).encode() if (data is not None and is_json) else data,
        headers={
            "Authorization": "Bearer " + PAT,
            "Accept": "application/vnd.github+json",
            "User-Agent": "release-script",
        },
    )
    if data is not None and not is_json:
        req.add_header("Content-Type", "application/octet-stream")
    with urllib.request.urlopen(req) as r:
        body = r.read()
        return r.status, (json.loads(body) if body else None)


status, release = api("POST", "/repos/%s/releases" % REPO, {
    "tag_name": TAG,
    "name": "tpp_Salary v1.7.9 — Full offline/online parity",
    "body": NOTES,
    "draft": False,
    "prerelease": False,
})
print("release created:", release.get("html_url") if status in (200, 201) else status)
assert status == 201, release

upload = release["upload_url"].split("{")[0] + "?name=%s"
for fname in ("tpp_salary-1.7.9-plugin.zip", "tpp-salary-v1.7.9-full.zip", "tpp-salary-python-app-1.7.9.zip"):
    path = os.path.join(DL, fname)
    st, asset = api("POST", upload % fname, open(path, "rb").read(), is_json=False)
    assert st == 201, (fname, st, asset)
    print("asset uploaded:", fname, "%.1f KB" % (os.path.getsize(path) / 1024))
print("RELEASE OK:", release["html_url"])
