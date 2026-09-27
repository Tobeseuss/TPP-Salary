# tpp_Salary — حقوق و دستمزد | WordPress Payroll & Payslip System

![Version](https://img.shields.io/badge/version-1.7.7-blue) ![WordPress](https://img.shields.io/badge/WordPress-%E2%89%A55.8-21759B) ![PHP](https://img.shields.io/badge/PHP-%E2%89%A55.6-777BB4) ![Python](https://img.shields.io/badge/Python-3.10%2B-3776AB) ![License](https://img.shields.io/badge/license-GPLv2%2B-green)

سامانه جامع حقوق و دستمزد برای وردپرس + نرم‌افزار دسکتاپ آفلاین پایتون — ثبت فیش حقوقی، فیش PDF/ZIP، گزارش اکسل، پنل کارمند، همگام‌سازی دوطرفه و پشتیبان‌گیری خودکار.

> ریپازیتوری رسمی پروژه. با هر تغییر (کوچک یا بزرگ) یک commit با پیام نسخه‌دار ثبت می‌شود و هر نسخه به‌صورت GitHub Release با فایل‌های نصبی منتشر می‌گردد.

<div dir="rtl">

## فارسی

### معرفی

`tpp_Salary` (حقوق و دستمزد) یک افزونه کامل وردپرس برای مدیریت حقوق و دستمزد کارکنان است که همراه با یک نرم‌افزار دسکتاپ پایتون عرضه می‌شود. همه کلاس‌ها (`TppSalary_*`)، توابع (`tpp_salary_*`)، جداول (`wp_tpp_salary_*`) و کلیدهای تنظیمات با پیشوند یکتا ایزوله شده‌اند تا با هیچ افزونه دیگری — حتی افزونه‌های دیگر با پیشوند `tpp_` — تداخل نکند. تمام رابط کاربری فارسی و راست‌به‌چپ است و تقویم جلالی در همه بخش‌ها رعایت می‌شود.

### امکانات کلیدی

- **ثبت حقوق با ویزارد ۳ مرحله‌ای** — انتخاب دوره (سال/ماه جلالی) و مرکز، تکمیل داده‌های کارکنان با محاسبات زنده مطابق فرمول‌های قابل ویرایش، محاسبه خودکار فیلد «سایر» برای رسیدن به خالص پرداختی هدف
- **فیش حقوقی PDF** — تکی و عمده (یک PDF مجزا برای هر کارمند داخل ZIP بر حسب سال/ماه/مرکز) با لوگوی شرکت و فونت Vazirmatn (حداقل 10pt Bold)
- **گزارش لیست حقوق** — جدول ستونی (هر ستون یک کارمند، هر سطر یک عنوان حقوق) با خروجی اکسل و PDF در یک صفحه
- **فیش بانکی** — فهرست کارکنان دارای حقوق ثبت‌شده در یک بانک، با شماره حساب و خالص دریافتی (اکسل و PDF)
- **پنل کارمند** — شورت‌کد `[tpp_salary_panel]` برای مشاهده فیش‌ها، دانلود PDF و ثبت اطلاعات بانکی
- **نرم‌افزار دسکتاپ پایتون (آفلاین)** — همان توانایی‌های ثبت و گزارش به‌صورت محلی با SQLite + همگام‌سازی دوطرفه با سایت از طریق REST API
- **پشتیبان‌گیری خودکار** — روزانه/هفتگی/ماهانه/سالانه (ZIP + JSON + اکسل) با نگهداشت قابل تنظیم و بازگردانی یک‌پارچه حتی روی سرور/دامنه دیگر
- **ورود گروهی** — ایمپورت کارمندان و حقوق‌ها از اکسل با ساخت خودکار حساب کاربری (رمز تصادفی امن)
- **نقش‌ها** — مدیرکل، حسابدار (ثبت حقوق) و کارمند (مشاهده فیش خود)

### نیازمندی‌ها

| مورد | حداقل |
|---|---|
| وردپرس | 5.8 |
| PHP | 5.6 (توصیه‌شده 7.4+) |
| MySQL/MariaDB | هر نسخه سازگار با وردپرس |
| نرم‌افزار دسکتاپ | Python 3.10+ (ویندوز/لینوکس/مک) |

### نصب افزونه

**نصب تازه:**
1. آخرین `tpp_salary-{V}-plugin.zip` را از صفحه [Releases](../../releases) دانلود کنید.
2. پیشخوان وردپرس ← افزونه‌ها ← افزودن ← بارگذاری افزونه ← انتخاب فایل ZIP ← نصب و فعال‌سازی.
3. از منوی «حقوق و دستمزد» تنظیمات، مراکز، بانک‌ها و کارمندان را تکمیل کنید.

**ارتقا از نسخه‌های قبلی:** فقط پوشه افزونه را با نسخه جدید جایگزین کنید (افزونه را غیرفعال/حذف نکنید). جداول و داده‌ها حفظ می‌شوند و در فعال‌سازی بعدی به‌صورت خودکار ارتقا می‌یابند. حذف افزونه داده‌ها را پاک نمی‌کند (از نسخه 1.4.1).

### راه‌اندازی اولیه

1. **تنظیمات** — نام شرکت، لوگو، واحد پول، فرمول‌ها (ناخالص/مشمول بیمه/خالص) و دوره‌های پشتیبان‌گیری.
2. **مراکز** — پروژه/کارگاه‌ها را تعریف کنید.
3. **بانک‌ها** — بانک‌های پرداخت حقوق را تعریف کنید (با مدیریت حساب‌های کارمندان همگام می‌شود).
4. **کارمندان** — هر کارمند یک حساب وردپرس با نقش «کارمند» است؛ ثبت دستی یا ورود گروهی از اکسل.
5. **ثبت حقوق** — منوی «ثبت حقوق» ← ویزارد ۳ مرحله‌ای ← انتخاب دوره و مرکز ← تکمیل مقادیر ← ثبت.
6. **گزارش‌ها** — «فیش‌های حقوقی» برای صدور فیش تکی/عمده و «گزارش لیست حقوق» و «فیش بانکی» برای خروجی‌ها.

### پنل کارمند

شورت‌کد زیر را در یک برگه قرار دهید تا کارمندان پس از ورود، فیش‌های خود را ببینند:

```
[tpp_salary_panel]
```

نمایش شامل کارت‌های آماری (تعداد فیش/آخرین دوره/جمع خالص)، گروه‌بندی سالانه، نشان «جدید» برای آخرین دوره، مشاهده نسخه وب قابل چاپ و دانلود PDF هر دوره، به‌همراه فرم ثبت شماره حساب/شبا/کارت برای هر بانک است. (بازطراحی کامل در نسخه 1.7.7)

### نرم‌افزار دسکتاپ پایتون + REST API

1. فایل `tpp-salary-python-app-{V}.zip` را از Releases دانلود و کنار افزونه استخراج کنید (پوشه `python-app` داخل افزونه هم موجود است).
2. ویندوز: دوبار کلیک روی `TPP Salary.bat` (نیازمند Python 3.10+ از python.org).
3. در پیشخوان وردپرس کلید API بسازید (منوی تنظیمات ← تب «کلید API») و آدرس `https://example.com/wp-json/tpp_salary/v1` + کلید `tppk_...` را در تنظیمات برنامه وارد کنید.
4. همگام‌سازی دوطرفه: ارسال رکوردهای محلی به سایت و دریافت رکوردهای سایت؛ هر ذخیره و هر اجرا نیز یک نسخه پشتیبان اکسل در پوشه `excel` کنار برنامه می‌سازد (از نسخه 1.7.5).

### پشتیبان‌گیری و بازگردانی

- بکاپ دستی: JSON، اکسل و ZIP (شامل فایل‌های افزونه) + بکاپ خودکار زمان‌بندی‌شده.
- بازگردانی از نسخه 1.7.6: فایل ZIP یا JSON را مستقیم آپلود کنید؛ کارمندان در نصب مقصد تطبیق داده می‌شوند (نام کاربری ← کد ملی ← ایمیل ← شناسه+نقش) و در نبود مطابقت خودکار ساخته می‌شوند؛ شناسه همه رکوردها به حساب‌های واقعی نگاشت می‌شود و تنظیمات ادغام می‌شوند — انتقال بین سرورها و دامنه‌های مختلف پشتیبانی می‌شود.

### ساختار ریپازیتوری

```
├── plugin/tpp_salary/     افزونه کامل وردپرس (شامل python-app و کتابخانه‌ها)
├── scripts/               تست‌ها، دروازه‌های کیفیت و اسکریپت‌های بسته‌بندی
├── download/index.html    صفحه دانلود عمومی پروژه
├── caddy/                 پیکربندی Caddy (فایل‌سرور دانلود / هاست سایت)
├── docs/                  مستندات تکمیلی (README خود افزونه)
├── CHANGELOG.md           تاریخچه کامل نسخه‌ها (فارسی + خلاصه انگلیسی)
├── worklog.md             گزارش کار کامل همه اقدامات (فارسی + خلاصه انگلیسی)
└── README.md              همین فایل (دوزبانه)
```

### توسعه و تست

- تست‌های PHP (۲۷ اسکریپت) روی PHP 8.3 اجرا می‌شوند: `php scripts/test_fixes_XXX.php` — هر اسکریپت خودبسတانه است و در پایان `ALL PASS` چاپ می‌کند.
- دروازه‌های پایتون: `python scripts/pyapp_attr_check.py` + `python scripts/pyapp_smoke.py` (نیازمند PySide6) + `test_pyapp_sync.py` و `test_api_antibot.py`.
- بسته‌بندی هر نسخه: `python scripts/package_1XX.py` — سه ZIP می‌سازد، بسته‌های قبلی را حذف و صفحه دانلود را به‌روز می‌کند و محتوای بسته‌ها را راستی‌آزمایی می‌کند.
- پیش از هر انتشار، کل رگرسیون باید سبز باشد؛ همان ترکیبی که در `worklog.md` هر نسخه ثبت شده است.

### نسخه‌بندی و انتشار

- قالب نسخه: `MAJOR.MINOR.PATCH` — نسخه در ۳ نقطه همگام است: هدر `Version` و `TPP_SALARY_VERSION` در `tpp-salary.php`، ثابت `TPP_SALARY_INSTALL_BUILD` در `class-tppsalary-install.php` و «Stable tag» در `readme.txt`.
- با هر تغییر، پیام commit شماره نسخه/موضوع را دارد (مثل `1.7.7: redesign employee panel shortcode`).
- هر نسخه پس از سبز شدن کامل رگرسیون، تگ `vX.Y.Z` گرفته و به‌صورت GitHub Release با سه فایل (plugin / full / python-app) منتشر می‌شود.
- سابقه کامل نسخه‌های قبل از انتشار عمومی (1.0.0 تا 1.7.7) در [CHANGELOG.md](CHANGELOG.md) و [worklog.md](worklog.md) موجود است.

### مجوز

GPLv2 یا جدیدتر — هماهنگ با وردپرس. فونت Vazirmatn (OFL) و کتابخانه tFPDF (LGPL) با مجوزهای خودشان داخل بسته هستند.

</div>

---

## English

### About

`tpp_Salary` (حقوق و دستمزد — "Payroll") is a full-featured WordPress payroll plugin shipped together with an offline Python desktop application. Every class (`TppSalary_*`), function (`tpp_salary_*`), table (`wp_tpp_salary_*`) and option key is namespaced with a unique prefix, so it never conflicts with any other plugin — even other plugins using a `tpp_` prefix. The whole UI is Persian/RTL with a Jalali calendar; PDFs embed the Vazirmatn font.

### Key features

- **3-step salary wizard** — pick a period (Jalali year/month) and a center, fill employee rows with live calculations driven by editable formulas, and auto-compute the "other" field to reach a target net pay
- **Payslip PDFs** — single and bulk (one PDF per employee inside a ZIP, grouped by year/month/center) with the company logo and Vazirmatn font (min 10pt Bold)
- **Salary list report** — column layout (one column per employee, one row per pay item) exported to Excel and single-page PDF
- **Bank payslip** — employees paid through a selected bank with their account numbers and net pay (Excel and PDF)
- **Employee panel** — the `[tpp_salary_panel]` shortcode lets staff view their payslips, download PDFs and save their bank details
- **Offline Python desktop app** — the same recording/reporting capabilities locally on SQLite, plus two-way sync with the website over a REST API
- **Automated backups** — daily/weekly/monthly/yearly (ZIP + JSON + Excel) with configurable retention and a unified restore that works across servers and domains
- **Bulk import** — import employees and salary records from Excel with automatic user creation (secure random passwords)
- **Roles** — administrator, accountant (records salaries) and employee (views own payslips)

### Requirements

| Component | Minimum |
|---|---|
| WordPress | 5.8 |
| PHP | 5.6 (7.4+ recommended) |
| MySQL/MariaDB | any WordPress-compatible version |
| Desktop app | Python 3.10+ (Windows/Linux/macOS) |

### Installing the plugin

**Fresh install:**
1. Download the latest `tpp_salary-{V}-plugin.zip` from the [Releases](../../releases) page.
2. WordPress admin ← Plugins ← Add New ← Upload Plugin ← choose the ZIP ← Install and Activate.
3. Open the "حقوق و دستمزد" (Payroll) menu and configure settings, centers, banks and employees.

**Upgrading:** just replace the plugin folder with the new version (do not delete the plugin from the admin). Tables and data are preserved and upgraded automatically on the next activation. Uninstalling the plugin never deletes payroll data (since 1.4.1).

### Initial setup

1. **Settings** — company name, logo, currency, formulas (gross / insurable / net) and backup schedules.
2. **Centers** — define projects/workshops.
3. **Banks** — define payroll banks (kept in sync with employee bank accounts).
4. **Employees** — each employee is a WordPress user with the "employee" role; add manually or bulk-import from Excel.
5. **Record salaries** — "ثبت حقوق" menu ← 3-step wizard ← pick period + center ← fill values ← save.
6. **Reports** — "فیش‌های حقوقی" for single/bulk payslips, plus the salary list and bank payslip reports.

### Employee panel

Place this shortcode on a page; logged-in employees see their own payslips:

```
[tpp_salary_panel]
```

The panel shows summary cards (payslip count / latest period / total net), year-based grouping with collapsible sections, a "new" badge on the latest period, a printable web view and a per-period PDF download, plus a bank-details form. (Fully redesigned in 1.7.7.)

### Python desktop app + REST API

1. Download `tpp-salary-python-app-{V}.zip` from Releases and extract it (a `python-app` folder is also bundled inside the plugin).
2. Windows: double-click `TPP Salary.bat` (requires Python 3.10+ from python.org).
3. Create an API key in the WordPress admin (Settings ← "کلید API" tab) and enter the endpoint `https://example.com/wp-json/tpp_salary/v1` plus the `tppk_...` key in the app settings.
4. Two-way sync pushes local records to the site and pulls site records back; every save and every launch also writes an Excel snapshot into the app's `excel` folder (since 1.7.5).

### Backup & restore

- Manual backups in JSON, Excel and ZIP (including plugin files) plus scheduled automatic backups.
- Restore (since 1.7.6) accepts a ZIP or JSON directly; employees are matched in the target install (username ← national ID ← email ← ID+role) and auto-created when unmatched, every record is remapped to the real target account, and settings are merged — cross-server/cross-domain moves are supported.

### Repository layout

```
├── plugin/tpp_salary/     the complete WordPress plugin (incl. python-app + libraries)
├── scripts/               tests, quality gates and packaging scripts
├── download/index.html    the project's public download page
├── caddy/                 Caddy configs (download file server / site hosting)
├── docs/                  extra docs (the plugin's own README, Persian)
├── CHANGELOG.md           full release history (Persian + English summary)
├── worklog.md             complete engineering work log (Persian + English summary)
└── README.md              this file (bilingual)
```

### Development & testing

- PHP tests (27 self-contained scripts) run on PHP 8.3: `php scripts/test_fixes_XXX.php` — each prints `ALL PASS` on success.
- Python gates: `python scripts/pyapp_attr_check.py`, `python scripts/pyapp_smoke.py` (needs PySide6), plus `test_pyapp_sync.py` and `test_api_antibot.py`.
- Packaging a release: `python scripts/package_1XX.py` builds three ZIPs, removes older packages, updates the download page and verifies the package contents.
- Every release requires the full regression to be green — exactly the combination logged per version in `worklog.md`.

### Versioning & releases

- Format: `MAJOR.MINOR.PATCH`, kept in sync at three points: the `Version` header and `TPP_SALARY_VERSION` in `tpp-salary.php`, `TPP_SALARY_INSTALL_BUILD` in `class-tppsalary-install.php`, and the "Stable tag" in `readme.txt`.
- Every change (small or large) is committed with a version-tagged message (e.g. `1.7.7: redesign employee panel shortcode`).
- Once the full regression is green, each version is tagged `vX.Y.Z` and published as a GitHub Release with three artifacts (plugin / full / python-app).
- The pre-publication history (1.0.0 → 1.7.7) is documented in [CHANGELOG.md](CHANGELOG.md) and [worklog.md](worklog.md).

### License

GPLv2 or later — WordPress-compatible. The Vazirmatn font (OFL) and the tFPDF library (LGPL) ship under their own licenses.

<!-- English section ends -->

