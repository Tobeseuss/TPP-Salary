#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""به‌روزرسانی مستندات دوزبانه ریپو برای نسخه 1.8.1 + همگام‌سازی صفحه دانلود و worklog"""
import os, shutil

REPO = '/home/z/my-project/TPP-Salary'
ROOT = '/home/z/my-project'

# ---------- 1) README.md ----------
p = os.path.join(REPO, 'README.md')
h = open(p, encoding='utf-8').read()
h = h.replace('version-1.8.0-blue', 'version-1.8.1-blue')
fa_old = '- **گزارش سالانه مراکز** — گزارش لیست حقوق «یک مرکز» در ماه‌های مختلف «یک سال» با خروجی اکسل چندشیتی (شیت «جمع سال» + یک شیت برای هر ماه) — در هر دو نسخه (نسخه 1.8.0)'
assert fa_old in h
fa_new = fa_old + '\n- **رفع کندی سایت** — همگام‌سازی سبک «Revision» (همگام‌سازی دوره‌ای برنامه دسکتاپ در نبود تغییر، فقط پاسخ چند بایتی «بدون تغییر» می‌گیرد)، قفل ضد طوفان ارتقا و کرون بکاپ خودکار، حذف کوئری‌های N+1 و بخش جدید «کارایی و منابع» در تب وضعیت سیستم (نسخه 1.8.1)'
h = h.replace(fa_old, fa_new)
en_old = "- **Annual centers report** — one center's salary list across the months of a year exported as a multi-sheet Excel (a year-summary sheet + one sheet per month), in **both** the plugin and the desktop app (v1.8.0)"
assert en_old in h
en_new = en_old + '\n- **Site slowness fix** — lightweight revision-based sync (periodic desktop sync now costs a few lightweight queries when nothing changed), anti-storm locks for runtime upgrades and cron backups, N+1 query elimination, and a new "Performance & Resources" block in the System Status tab (v1.8.1)'
h = h.replace(en_old, en_new)
h = h.replace('سابقه کامل نسخه‌های قبل از انتشار عمومی (1.0.0 تا 1.8.0)', 'سابقه کامل نسخه‌های قبل از انتشار عمومی (1.0.0 تا 1.8.1)')
open(p, 'w', encoding='utf-8').write(h)
print('README.md updated')

# ---------- 2) CHANGELOG.md ----------
p = os.path.join(REPO, 'CHANGELOG.md')
c = open(p, encoding='utf-8').read()
fa_section = '''## 1.8.1 — 1405/07/15 — رفع کندی سایت

درخواست کاربر: «از وقتی این پلاگین را فعال کرده ام وبسایتم بسیار کند هست و بالا نمی آید» — ممیزی کامل کارایی انجام شد و چهار بهینه‌سازی ریشه‌ای اعمال شد (سمت افزونه + برنامه دسکتاپ؛ سازگاری کامل با نسخه‌های قبلی در هر دو جهت).

### همگام‌سازی سبک Revision (مهم‌ترین بهینه‌سازی)
- برنامه دسکتاپ به‌طور پیش‌فرض هر ۶۰ ثانیه یک بار `/bundle` کامل می‌گرفت؛ سرور برای هر درخواست همه رکوردها (تا ۵۰٬۰۰۰ سطر) را می‌خواند و JSON چند-مگابایتی می‌ساخت = بار دائمی روی هاست‌های ضعیف
- اکنون هر بسته یک `revision` دارد: هش ارزان از COUNT/MAX/SUM(LENGTH) رکوردها، مراکز، بانک‌ها، فیلدها، متاهای پروفایل/کد ملی/موبایل/قطع‌همکاری، COUNT/MAX کاربران + هش تنظیمات + نسخه افزونه — هیچ مسیر نوشتنی نیاز به به‌روزرسانی شمارنده ندارد و هر تغییر محتوایی هش را عوض می‌کند
- کلاینت revision خود را در `/bundle?rev=...` و بدنه `/sync` می‌فرستد؛ در نبود تغییر، سرور فقط `{"not_modified": true}` (چند صد بایت) برمی‌گرداند
- برنامه پایتون: پاسخ سبک مصرف می‌شود (بدون بازسازی دیتابیس محلی)، revision بعد از هر pull کامل ذخیره می‌شود و پیام «بدون تغییر» نمایش داده می‌شود
- سازگاری دوجهته: کلاینت قدیمی + سرور جدید ✓ و کلاینت جدید + سرور قدیمی ✓ (بدون rev = بسته کامل مثل قبل)

### قفل ضد طوفان ارتقا (maybe_upgrade)
- اگر ذخیره `tpp_salary_db_version` ناموفق بماند (پر بودن دیسک، خطای نوشتن options، قطع اجرا)، مسیر سنگین upgrade پیش‌تر در «هر درخواست» اجرا می‌شد؛ اکنون قفل ترنزینت ۱۰ دقیقه‌ای بین اجراها فاصله اجباری می‌گذارد

### سخت‌سازی بکاپ خودکار کرون
- قفل ترنزینت ۱۵ دقیقه‌ای ضد اجرای موازی/تکراری + بالابردن حافظه/زمان اجرا (تا ۶۰۰ ثانیه) + ignore_user_abort + try/catch تا خطای کرون هرگز صفحات را نشکند
- بکاپ خودکار ZIP سبک شد: بایگانی پوشه افزونه فقط در بکاپ دستی (`make(..., $include_plugin_files=false)`)

### حذف کوئری‌های N+1
- `tpp_salary_get_employees()`: پیش‌بارگذاری کش کاربران/متاها با یک کوئری (`cache_users`) — اثر روی همه صفحات مدیریت، بسته همگام‌سازی و بکاپ
- اکسل بکاپ رکوردها: نقشه نام کارمندان/مراکز یک‌بار ساخته می‌شود (قبلاً کوئری به‌ازای هر رکورد)
- پنل کارمند: نقشه مراکز یک‌بار پیش‌خوانی می‌شود (قبلاً کوئری به‌ازای هر فیش)

### بخش «کارایی و منابع» در تب وضعیت سیستم
- حافظه/زمان اجرا PHP، وضعیت WP-Cron و ALTERNATE_WP_CRON، وضعیت بازه‌های بکاپ خودکار، مقیاس داده، فضای دیسک پوشه بکاپ

### تست و بسته‌بندی 1.8.1
- تست‌های جدید: `scripts/test_perf_181.php` (۳۵+ ادعا — revision/قفل‌ها/ZIP سبک/نقشه‌ها/تب کارایی) و `scripts/test_pyapp_181.py` (۲۰+ ادعا — پارامتر rev در هر دو حالت لینک، چرخه سبک/کامل/سازگاری سرور قدیمی)
- رگرسیون کامل سبز: ۳۱ تست PHP + test_pyapp_sync + test_api_antibot + test_pyapp_179/180/181 + test_pdf_engine_180 + attr-check (۲۷ فایل) + pyflakes ۰ + smoke (۱۲ صفحه/۴۵ دکمه)
- بسته‌بندی `package_181.py` (۱۰۴/۱۰۹/۳۶ فایل) + کامیت + تگ v1.8.1 + Release دوزبانه با سه asset

'''
marker = '## 1.8.0 — 1405/07/14'
assert marker in c
c = c.replace(marker, fa_section + marker, 1)

en_row_marker = '| 1.8.0 | **Annual centers report'
assert en_row_marker in c
en_row = ('| 1.8.1 | **Site slowness fix** (user report: "the site became very slow after activating the plugin"). '
          '**Lightweight revision-based sync**: every bundle now carries a cheap revision hash (COUNT/MAX/SUM(LENGTH) over records, centers, banks, fields, employee/identity usermeta, users + settings hash); the desktop app sends its revision on /bundle?rev= and /sync, and the server answers with a tiny `not_modified` payload when nothing changed — periodic 60s syncs no longer rebuild/transfer a multi-MB bundle. **Upgrade anti-storm lock** (10-min transient) for maybe_upgrade. **Cron backup hardening**: 15-min lock, raised memory/time limits, try/catch, and auto (cron) ZIP no longer archives the plugin folder (manual backups keep it). **N+1 elimination**: employee list cache priming (cache_users), backup Excel name maps, employee panel center map. **New "Performance & Resources" block** in the System Status tab. Regression: 31 PHP suites + 6 Python suites + attr-check + pyflakes + smoke (12 pages / 45 buttons) all green |\n') + en_row_marker
c = c.replace(en_row_marker, en_row, 1)
open(p, 'w', encoding='utf-8').write(c)
print('CHANGELOG.md updated')

# ---------- 3) download/index.html ----------
shutil.copy2(os.path.join(ROOT, 'download', 'index.html'), os.path.join(REPO, 'download', 'index.html'))
print('download/index.html synced')
print('OK')
