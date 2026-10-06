#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""انتشار Release v1.8.1 در گیت‌هاب — توکن فقط از متغیر محیطی GITHUB_PAT خوانده می‌شود."""
import json
import os
import subprocess
import time

PAT = os.environ["GITHUB_PAT"]
REPO = "Tobeseuss/TPP-Salary"
TAG = "v1.8.1"
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
        if code_i in (0, 6, 7, 28, 35, 56):
            time.sleep(2 * (attempt + 1))
            continue
        try:
            parsed = json.loads(body) if body.strip() else None
        except ValueError:
            parsed = body
        return code_i, parsed
    raise RuntimeError("network failed after retries: %s %s" % (method, path))

NOTES = """## نسخه ۱.۸.۱ — رفع کندی سایت

گزارش کاربر: «از وقتی این پلاگین را فعال کرده ام وبسایتم بسیار کند هست و بالا نمی آید» — ممیزی کامل کارایی انجام شد و چهار بهینه‌سازی ریشه‌ای اعمال شد (سمت افزونه + برنامه دسکتاپ؛ سازگاری کامل با نسخه‌های قبلی در هر دو جهت).

### ۱) همگام‌سازی سبک Revision (مهم‌ترین بهینه‌سازی)
- مشکل: برنامه دسکتاپ به‌طور پیش‌فرض هر ۶۰ ثانیه یک بار بسته کامل داده (تا ۵۰٬۰۰۰ رکورد) را از سایت می‌گرفت — بازسازی چند-مگابایتی JSON در هر دقیقه = بار دائمی روی هاست
- اکنون هر بسته یک «revision» دارد (هش ارزان از COUNT/MAX/SUM(LENGTH) همه جدول‌ها + متاهای پروفایل/کد ملی/موبایل + کاربران + تنظیمات)
- اگر داده تغییر نکرده باشد، سرور فقط پاسخ چند صد بایتی «بدون تغییر» می‌دهد — بار هر همگام‌سازی به چند میلی‌ثانیه رسید
- سازگاری کامل: نسخه‌های قدیمی افزونه و برنامه هم بدون مشکل کار می‌کنند

### ۲) قفل ضد طوفان ارتقا
- اگر ذخیره نسخه دیتابیس ناموفق بماند (مثلاً پر بودن دیسک)، مسیر سنگین نصب/ارتقا دیگر در «هر درخواست» اجرا نمی‌شود — حداقل ۱۰ دقیقه فاصله اجباری

### ۳) سخت‌سازی بکاپ خودکار کرون
- قفل ۱۵ دقیقه‌ای ضد اجرای موازی/تکراری + بالابردن حافظه/زمان اجرا + محافظت کامل در برابر خطا
- بکاپ خودکار سبک شد (بایگانی پوشه افزونه فقط در بکاپ دستی) — فایل‌های افزونه همیشه از همین صفحه Releases قابل دریافت‌اند

### ۴) حذف کوئری‌های N+1
- لیست کارمندان (اثر روی همه صفحات مدیریت، همگام‌سازی و بکاپ)، اکسل بکاپ رکوردها و پنل کارمند بهینه شدند

### ۵) بخش جدید «کارایی و منابع» در پیشخوان
- تنظیمات ← وضعیت سیستم: حافظه/زمان PHP، وضعیت WP-Cron، وضعیت بکاپ خودکار، مقیاس داده و فضای دیسک — اگر سایت کند است اول این بخش را ببینید

### کیفیت
- تست‌های جدید: test_perf_181.php (۳۵+ ادعا) و test_pyapp_181.py (۲۰+ ادعا)
- رگرسیون کامل سبز: ۳۱ تست PHP + ۶ مجموعه تست پایتون + attr-check + pyflakes صفر + اسموک ۱۲ صفحه/۴۵ دکمه
- بسته‌ها: افزونه ۱۰۴ فایل / کامل ۱۰۹ فایل / نرم‌افزار ۳۶ فایل

**نصب:** افزونه = `tpp_salary-1.8.1-plugin.zip`؛ بسته کامل = `tpp-salary-v1.8.1-full.zip`؛ به‌روزرسانی نرم‌افزار دسکتاپ = `tpp-salary-python-app-1.8.1.zip` روی پوشه قبلی استخراج شود (برای گرفتن بهینه‌سازی همگام‌سازی، به‌روزرسانی نرم‌افزار ضروری است).

---

## v1.8.1 — Site slowness fix

User report: "the website became very slow and would not come up after activating the plugin" — a full performance audit was performed and four root-cause optimizations were applied (plugin + desktop app; fully backward compatible in both directions).

### 1) Lightweight revision-based sync (the biggest win)
- Problem: the desktop app fetched the FULL data bundle (up to 50,000 records) every 60 seconds by default — rebuilding a multi-MB JSON every minute = continuous server load
- Every bundle now carries a cheap "revision" hash (COUNT/MAX/SUM(LENGTH) over all tables + employee/identity usermeta + users + settings)
- When nothing changed the server answers with a tiny "not_modified" payload — per-sync server cost dropped to a few milliseconds
- Fully compatible: old plugin + new app and new plugin + old app both keep working

### 2) Upgrade anti-storm lock
- If the DB version write ever fails (e.g. full disk), the heavy upgrade path no longer runs on EVERY request — a 10-minute transient lock enforces spacing

### 3) Cron backup hardening
- 15-minute lock against parallel/repeated runs + raised memory/time limits + full try/catch protection
- Automatic (cron) backups are now light — the plugin-folder archive is only included in manual backups (plugin files are always downloadable from Releases)

### 4) N+1 query elimination
- Employee lists (affects every admin page, sync bundle and backups), the backup Excel and the employee panel

### 5) New "Performance & Resources" block (System Status tab)
- PHP memory/execution limits, WP-Cron state, cron backup state, data scale and backup-dir free disk — check this block first if the site is slow

### Quality
- New tests: test_perf_181.php (35+ assertions) and test_pyapp_181.py (20+ assertions)
- Full regression green: 31 PHP suites + 6 Python suites + attr-check + zero pyflakes + 12-page/45-button smoke
- Packages: plugin 104 files / full 109 files / desktop 36 files

**Install:** plugin = `tpp_salary-1.8.1-plugin.zip`; full bundle = `tpp-salary-v1.8.1-full.zip`; desktop update = extract `tpp-salary-python-app-1.8.1.zip` over the previous folder (required to benefit from the sync optimization).
"""

# idempotent: اگر Release از تلاش قبلی ساخته شده، همان را پیدا کن
release = None
try:
    st, rel = api("GET", "/repos/%s/releases/tags/%s" % (REPO, TAG))
    if st == 200 and isinstance(rel, dict) and rel.get("id"):
        release = rel
        print("release exists:", release.get("html_url"))
except Exception:
    release = None
if release is None:
    status, release = api("POST", "/repos/%s/releases" % REPO, {
        "tag_name": TAG,
        "name": "tpp_Salary v1.8.1 — Site slowness fix (light revision sync + locks + N+1 fixes)",
        "body": NOTES,
        "draft": False,
        "prerelease": False,
    })
    print("release created:", release.get("html_url") if status in (200, 201) else status)
    assert status == 201, release

# نام و یادداشت را همیشه به‌روز کن
api("PATCH", "/repos/%s/releases/%s" % (REPO, release["id"]), {
    "name": "tpp_Salary v1.8.1 — Site slowness fix (light revision sync + locks + N+1 fixes)",
    "body": NOTES,
})

upload = release["upload_url"].split("{")[0] + "?name=%s"
existing = set()
st, assets = api("GET", "/repos/%s/releases/%s/assets" % (REPO, release["id"]))
if st == 200 and isinstance(assets, list):
    existing = {a["name"] for a in assets}
    for a in assets:
        print("asset already present:", a["name"])
for fname in ("tpp_salary-1.8.1-plugin.zip", "tpp-salary-v1.8.1-full.zip", "tpp-salary-python-app-1.8.1.zip"):
    if fname in existing:
        continue
    path = os.path.join(DL, fname)
    assert os.path.exists(path), path
    st, out = api("POST", upload % fname, path, is_json=False)
    print("upload %s => %s" % (fname, st))
    assert st in (200, 201), out

print("RELEASE OK:", release.get("html_url"))
