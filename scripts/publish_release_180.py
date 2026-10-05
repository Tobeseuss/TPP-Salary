#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""انتشار Release v1.8.0 در گیت‌هاب — توکن فقط از متغیر محیطی GITHUB_PAT خوانده می‌شود."""
import json
import os
import subprocess
import sys
import time

PAT = os.environ.get("GITHUB_PAT")
assert PAT, "GITHUB_PAT is required"
REPO = "Tobeseuss/TPP-Salary"
TAG = "v1.8.0"
DL = "/home/z/my-project/download"


def api(method, path, data=None, is_json=True):
    """تماس GitHub API با curl (DNS با urllib ناپایدار است) + سه تلاش."""
    url = "https://api.github.com" + path
    for attempt in range(3):
        cmd = [
            "curl", "-sS", "-X", method, url,
            "-H", "Authorization: Bearer " + PAT,
            "-H", "Accept: application/vnd.github+json",
            "-H", "User-Agent: release-script",
            "-w", "\n%{http_code}", "--max-time", "120",
        ]
        if data is not None and is_json:
            cmd += ["-H", "Content-Type: application/json", "-d", json.dumps(data)]
        elif data is not None:
            cmd += ["-H", "Content-Type: application/octet-stream", "--data-binary", data]
        out = subprocess.run(cmd, capture_output=True, text=True).stdout
        if "\n" in out:
            body, code = out.rsplit("\n", 1)
        else:
            body, code = "", out.strip()
        try:
            code_i = int(code.strip())
        except ValueError:
            code_i = 0
        if code_i in (0, 6, 7, 28, 35, 56):  # خطای شبکه curl → تلاش مجدد
            time.sleep(2 * (attempt + 1))
            continue
        try:
            parsed = json.loads(body) if body.strip() else None
        except ValueError:
            parsed = body
        return code_i, parsed
    raise RuntimeError("network failed after retries: %s %s" % (method, path))

NOTES = """## نسخه ۱.۸.۰ — گزارش سالانه مراکز + PDF جدید + بخش‌های تازهٔ نرم‌افزار

درخواست کاربر: خروجی PDF نرم‌افزار پایتون بازنویسی کامل شود؛ بخش «دریافت فیش‌های حقوقی» و «فیش بانک» به نرم‌افزار اضافه شود؛ پشتیبان‌گیری/بازگردانی در نرم‌افزار تعریف شود؛ و در هر دو نسخه «گزارش سالانه مراکز» ساخته شود (لیست حقوق یک مرکز در ماه‌های مختلف یک سال، چند شیت در یک فایل اکسل).

### گزارش سالانه مراکز (در هر دو نسخه)
- افزونه: زیرمنوی جدید «گزارش سالانه مراکز» — جدول جمع ماهانه + سطر «جمع سال» + دکمه خروجی اکسل چندشیتی
- خروجی اکسل: شیت «جمع سال» + به‌ازای هر ماه دارای رکورد یک شیت جداگانه با همان قالب ستونی 1.6.1 (ستون = نام کارمند، سطر = عناوین حقوق)
- نرم‌افزار پایتون: صفحه جدید «گزارش سالانه مراکز» با همان قالب اکسل — کاملاً هم‌سان با افزونه

### بازنویسی کامل موتور PDF نرم‌افزار پایتون
- PDF قبلی (HTML/QTextDocument) ناقص و نادرست بود — اکنون PDF برداری مستقیم با QPainter/QPdfWriter
- فونت Vazirmatn همراه برنامه، تکرار سربرگ جدول در هر صفحه، شماره صفحه + تاریخ چاپ، اندازه‌گیری واقعی ستون‌ها، جهت کاغذ خودکار
- سه قالب آینه افزونه: گزارش لیست حقوق (A4)، فیش بانکی (A4)، فیش حقوقی تکی (A5 با امضاها)

### بخش‌های جدید نرم‌افزار آفلاین
- **فیش‌های حقوقی**: فهرست فیش‌های هر دوره، مشاهده/چاپ فیش PDF تکی، ZIP عمده فیش همه کارکنان
- **فیش بانکی**: فهرست واریز بانک بر اساس حساب/شبای پروفایل کارمندان (همگام‌شده از سایت) با خروجی اکسل و PDF
- **پشتیبان‌گیری و بازگردانی**: پشتیبان JSON کامل و بسته ZIP (full/employees/records) با قالب هم‌سان بکاپ افزونه؛ بازگردانی از فایل خودی یا افزونه با تطبیق خودکار کارمندان (کد ملی/شناسه/نام) و نگاشت رکوردها

### کیفیت
- رگرسیون کامل سبز: ۳۰ تست PHP + ۵ مجموعه تست پایتون + اسموک ۱۲ صفحه/۴۵ دکمه + pyflakes صفر
- بسته‌ها: افزونه ۱۰۴ فایل / کامل ۱۰۹ فایل / نرم‌افزار ۳۶ فایل (افزودن pdf_engine.py، backup_core.py، ۴ صفحه جدید و ۲ فونت)

**نصب:** افزونه = `tpp_salary-1.8.0-plugin.zip`؛ بسته کامل = `tpp-salary-v1.8.0-full.zip`؛ به‌روزرسانی نرم‌افزار دسکتاپ = `tpp-salary-python-app-1.8.0.zip` روی پوشه قبلی استخراج شود.

---

## v1.8.0 — Annual centers report + new PDF engine + new desktop pages

User request: rewrite the desktop PDF output completely; add the payslips and bank-fiche sections to the Python app; add backup/restore to the Python app; and build an annual centers report in BOTH editions (one center's salary list across the months of a year as a multi-sheet Excel).

### Annual centers report (plugin + Python)
- Plugin: new submenu with a per-month summary table + year total row + multi-sheet Excel export
- Excel layout: a "جمع سال" (year summary) sheet + one columnar sheet per month with records, mirroring the 1.6.1 report layout (employees as columns, pay items as rows)
- Python: a matching "گزارش سالانه مراکز" page producing the identical Excel

### Complete Python PDF rewrite
- The old QTextDocument/HTML PDF was broken — PDF is now drawn directly as vectors with QPainter/QPdfWriter
- Bundled Vazirmatn font, repeated table header on every page, page numbers + print date, real column sizing with page-fit, automatic orientation
- Three plugin-mirroring layouts: salary report (A4), bank slip (A4), single payslip (A5 with signatures)

### New desktop app sections
- **Payslips**: period filter, single PDF view/print, bulk ZIP of every employee's payslip
- **Bank fiche**: bank deposit list from synced employee profiles (account/IBAN) with Excel + PDF exports
- **Backup/restore**: full JSON backup and ZIP bundle (full/employees/records) in the plugin's backup format; restores from own or plugin backups with employee auto-matching (national ID → ID → login → name) and record remapping

### Quality
- Full regression green: 30 PHP suites + 5 Python suites + 12-page/45-button smoke + zero pyflakes
- Packages: plugin 104 files / full 109 files / desktop 36 files (adds pdf_engine.py, backup_core.py, 4 new pages, 2 fonts)

**Install:** plugin = `tpp_salary-1.8.0-plugin.zip`; full bundle = `tpp-salary-v1.8.0-full.zip`; desktop update = extract `tpp-salary-python-app-1.8.0.zip` over the previous folder.
"""


# idempotent: اگر Release از تلاش قبلی ساخته شده، همان را پیدا کن
status, release = 0, None
try:
    st, release = api("GET", "/repos/%s/releases/tags/%s" % (REPO, TAG))
    if st == 200:
        print("release exists:", release.get("html_url"))
except Exception:
    release = None
if release is None:
    status, release = api("POST", "/repos/%s/releases" % REPO, {
        "tag_name": TAG,
        "name": "tpp_Salary v1.8.0 — Annual centers report + new PDF engine + new desktop pages",
        "body": NOTES,
        "draft": False,
        "prerelease": False,
    })
    print("release created:", release.get("html_url") if status in (200, 201) else status)
    assert status == 201, release

# نام و یادداشت را همیشه به‌روز کن
api("PATCH", "/repos/%s/releases/%s" % (REPO, release["id"]), {
    "name": "tpp_Salary v1.8.0 — Annual centers report + new PDF engine + new desktop pages",
    "body": NOTES,
})

upload = release["upload_url"].split("{")[0] + "?name=%s"
existing = set()
st, assets = api("GET", "/repos/%s/releases/%s/assets" % (REPO, release["id"]))
if st == 200 and isinstance(assets, list):
    existing = {a["name"] for a in assets}
    for a in assets:
        print("asset already present:", a["name"])
for fname in ("tpp_salary-1.8.0-plugin.zip", "tpp-salary-v1.8.0-full.zip", "tpp-salary-python-app-1.8.0.zip"):
    if fname in existing:
        continue
    path = os.path.join(DL, fname)
    st, asset = api("POST", upload % fname, "@" + path, is_json=False)
    assert st == 201, (fname, st, asset)
    print("asset uploaded:", fname, "%.1f KB" % (os.path.getsize(path) / 1024))
print("RELEASE OK:", release["html_url"])
