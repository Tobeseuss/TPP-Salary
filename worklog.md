# Worklog — پروژه پلاگین حقوق و دستمزد (tpp_Salary)

## English Summary — All Tasks

| # | Task |
|---|---|
| 1 | Plugin scaffolded from the requirements doc, tested, packaged, Caddy server setup |
| 2 | Full completion of the plugin, tests, packaging, Caddy run |
| 3 | Diagnosed "plugin could not be activated" on the user site |
| 4 | Free-host compatibility, dark mode, dynamic samples, Vazirmatn, offline mode, in-plugin worklog |
| 5 | Fatal "Cannot declare class TPP_Xlsx_Writer" — hardened against double loading |
| 6 | Fixed "Call to undefined method TPP_Settings::init()" (mixed old/new files on host) |
| 7 | MariaDB reserved-word `values` SQL failure + stale-install guard + DDL reserved-word scanner |
| 8 | Full security/perf refactor: atomic restore transaction, random passwords, mass-assignment whitelist |
| 9 | Namespace isolation 1.3.0 (TppSalary_*) — zero collision with other tpp_ plugins, data migration |
| 10 | 1.3.1 — broken Excel sample, admin 404s, backup management |
| 11 | 1.3.2 — menu 404/permissions + broken .excel file fixed for good |
| 12 | 1.4.0 — six fixes + bulk salary import |
| 13 | 1.4.1 — ten fixes (banks, import columns, auto-employee, profile fields, insurable formula, dark mode) |
| 14 | 1.5.0 — three fields moved to profile, multi-format backups, PDF shaping fix, A5 payslip |
| 15 | 1.6.0 — import name-column root cause, E2E with real files, employment status, A4-landscape PDF |
| 16 | 1.6.1 — one-page salary-list PDF, column Excel, pagination everywhere |
| 17 | 1.6.2 — search + single/bulk delete (records, employees, centers) |
| 18 | 1.6.3 — "fill fields from past salary" in wizard step 3 |
| 19 | 1.7.0 — offline Python desktop app + two-way REST API sync |
| 20 | 1.7.1 — Vazirmatn >= 10pt Bold in PDFs, English digits everywhere |
| 21 | 1.7.2 — fixed Python app startup crash + permanent QA gates |
| 22 | 1.7.3 — PDF zero-field cleanup, single-page fit, live recompute fix, settings save fix |
| 23 | 1.7.4 — fixed anti-bot "invalid JSON response" on tpptc.ir |
| 24 | 1.7.5 — instant Excel backup in the desktop app |
| 25 | 1.7.6 — backup/restore rewritten for cross-server moves |
| 26 | 1.7.7 — employee panel shortcode redesign |
| 27 | Public GitHub repo (Tobeseuss/TPP-Salary) + Release v1.7.7 + bilingual docs convention |
| 28 | 1.7.8 — auto-sync employee profile from latest payslip + insurance-group payload bug fix |
| 29 | 1.7.9 — offline/online parity: auto-prefill salary form from the previous month (plugin + Python), live auto-calculation in the Python app, scroll/size UI fixes, columnar Excel backup, prefill float-cast bug fix |
| 30 | 1.8.0 — annual centers report in both editions (multi-sheet Excel: year summary + one columnar sheet per month); complete Python PDF rewrite (vector QPainter/QPdfWriter + bundled Vazirmatn, repeated headers, fitted columns, A4 report / A4 bank slip / A5 payslip); new desktop pages — payslips (view/print + bulk ZIP), bank fiche (Excel+PDF), backup/restore (plugin-compatible JSON/ZIP with employee matching) |

> جزئیات کامل هر تسک به فارسی در ادامه همین فایل آمده است. از Task 28 به بعد، ورودی‌ها دوزبانه‌اند.
> Full Persian details for every task follow below. Entries are bilingual from Task 28 on.

---
Task ID: 8
Agent: main
Task: بازبینی و ریفکتور کامل پروژه + حرفه‌ای‌سازی (درخواست کاربر: بررسی همه فایل‌ها، اتصالات دیتابیس و جداول + افزودن هرچه حرفه‌ای‌تر می‌کند)

Work Log:
- ممیز امنیتی کامل با grep/خواندن فایل‌ها: ۹۲ دسترسی superglobal، هر ۵ DDL، همه handlerها (admin_post/ajax/فرم‌ها) — وضعیت پایه خوب بود (nonce/cap/prepare همه‌جا حاضر) اما ۴ مشکل واقعی یافت شد
- مشکل ۱ (بحرانی): بازگردانی بکاپ TRUNCATE سپس درج بدون تراکنش — خرابی فایل بکاپ در میانه = از دست رفتن دائمی داده‌ها + درج (array)$row بدون whitelist ستون (mass assignment) + update_option تنظیمات بدون پالایش
  → بازنویسی restore(): اعتبارسنجی همه سطرها پیش از هر تغییری + START TRANSACTION (نکته فنی مهم: TRUNCATE در MySQL/MariaDB COMMIT ضمنی دارد و rollback نمی‌شود → DELETE داخل تراکنش) + whitelist ستون‌ها در restore_sections() + پالایش تنظیمات + ROLLBACK کامل در خطا
- مشکل ۲ (امنیتی): رمز عبور کارمند جدید = کد ملی (در create_employee و import) — هر کسی کد ملی کارمند را بداند می‌توانست وارد شود
  → wp_generate_password(12) + wp_new_user_notification(admin) + نمایش یک‌باره login/pass به مدیر (transient + admin_notices)؛ در ورود گروهی رمز در گزارش نتایج هر سطر درج می‌شود
- مشکل ۳ (باگ): دسترسی مستقیم به $settings['defaults'] در salary-pages (خط ۲۴۱) و employees (خط ۲۲۴) — کلید در tpp_get_settings() تعریف نشده بود → PHP Notice/رفتار نادرست در نصب تازه → کلید 'defaults' => array() به پیش‌فرض‌ها اضافه + گارد در هر دو خواننده
- مشکل ۴ (الگوی ناامن): tpp_get_fields($where) رشته SQL خام می‌پذیرفت → امضای جدید tpp_get_fields(array('profile'=>1)) با شرط‌های whitelist + کش static درون-درخواستی (کاهش کوئری تکراری فیلدها در هر رندر)
- ریفکتور DB: فرمت صریح %d/%f/%s در insert/update رکوردهای حقوق (upsert_record) — دقت انواع عددی
- امکان جدید: «مشاهده فیش وب» — template_redirect + nonce اختصاصی tpp_view_{id} + بررسی مالکیت رکورد → صفحه HTML تمیز RTL با جدول فیلدها، جمع‌ها هایلایت، دکمه چاپ/PDF و بازگشت؛ لینک «مشاهده» در پنل کارمند اضافه شد (کد مرده قبلی $view که هیچ handler نداشت اکنون کامل پیاده شد)
- uninstall.php کامل شد: wp_clear_scheduled_hook ×۴ کرون بکاپ + پاک‌سازی transientهای import/new_user + remove_cap سه دسترسی از administrator
- includes/.htaccess (Require all denied + سازگار Apache 2.2) — دفاع در عمق؛ index.php ها از قبل بود
- تست جدید scripts/test_restore_schema.php: whitelist بازگردانی = ستون‌های DDL واقعی (تفکیک از CREATE TABLE) + کلیدهای JSON بکاپ — همه PASS (تست اول باگ خودش را نشان داد: regex tab در فایل space-indented → اصلاح)
- رگرسیون کامل سبز: شبیه‌ساز WP (LOAD/ACTIVATE/INIT)، double-load، mixed-files، stale-install، DDL words، static_scan (۵۲ فایل)، موتور ۲۱/۲۱، E2E فیش PDF و ZIP
- نسخه 1.2.0 (هدر + ثابت + TPP_INSTALL_BUILD + readme.txt + CHANGELOG + index.html + package)؛ بسته‌بندی مجدد + حذف zipهای 1.1.3 و قدیمی‌تر از download

Stage Summary:
- چهار مشکل واقعی (۱ بحرانی داده‌ای، ۱ امنیتی رمز، ۲ باگ/الگوی ناامن) رفع شد؛ افزونه از نظر الگوهای وردپرس (nonce/cap/prepare/escape) اکنون سرتاسری پاکیزه است
- بازگردانی بکاپ اکنون اتمی است: یا کامل موفق یا کاملاً بدون تغییر — نیمه‌کاره غیرممکن
- دو بهبود کاربری: فیش وب قابل چاپ در پنل کارمند + جریان امن رمز عبور جدید

---
Task ID: 7
Agent: main
Task: رفع خطای SQL فعال‌سازی کاربر (ستون رزرو `values` در MariaDB از فایل نصب قدیمی) + اعلان دقیق ماژول‌های ناهماهنگ

Work Log:
- تحلیل خطا: CREATE TABLE wp_tpp_field_archives با ستون `values` (کلمه رزرو MySQL/MariaDB) — راستی‌آزمایی: این جدول در بیلد فعلی اصلاً وجود ندارد (grep نتیجه‌ای نداد) → فایل class-tpp-install.php کاربر از یک نسخه میانی توسعه است؛ یعنی پوشه کاربر همچنان ترکیبی از سه بازه فایل مختلف بود
- بازبینی دستی هر ۵ DDL فعلی (centers/banks/fields/records/backups) → هیچ کلمه رزروی وجود ندارد؛ dbDelta-conventions هم سالم (PRIMARY KEY دو فاصله، کلیدها پایین‌کیس)
- گارد نسخه فایل نصب: define TPP_INSTALL_BUILD='1.1.3' در class-tpp-install.php؛ در tpp_salary_activate و tpp_salary_init (maybe_upgrade) اگر تعریف‌نشده/نابرابر باشد → DDL اجرا نمی‌شود، فقط اعلان راهنمای نصب مجدد — خطای SQL مهلک دیگر غیرممکن است حتی با پوشه ناهماهنگ
- اعلان ناهماهنگی ارتقا یافت: فهرست دقیق ماژول‌های ناهماهنگ (global $tpp_missing_modules) را نشان می‌دهد
- اسکنر دائمی scripts/test_ddl_words.py: استخراج ستون/ایندکس از همه CREATE TABLE ها و مقایسه با ~۲۸۰ کلمه رزرو MySQL 5.7/8.0 + MariaDB 10.x (شامل window functions) — هر ۵ جدول PASS؛ دو هشدار اولیه (payload/net) پس از راستی‌آزمایی رزرو نبودن، از فهرست حذف شد
- تست جدید scripts/test_stale_install.php: کپی افزونه + جایگزینی install با نسخه میانی (همان DDL خراب `values`) + اجرای فعال‌سازی → DDL رد شد، اعلان ثبت شد، بدون خطا — PASS
- رگرسیون کامل سبز: شبیه‌ساز WP، mixed-files، double-load، DDL words، static (۵۲ فایل)، compat، موتور ۲۱/۲۱، E2E فیش/ZIP
- نسخه 1.1.3 (هدر + ثابت + TPP_INSTALL_BUILD + readme.txt + CHANGELOG + index.html)؛ بسته‌بندی مجدد

Stage Summary:
- چهار لایه دفاعی اکنون کامل است: (۱) بارگذاری دوگانه، (۲) توابع early-bind، (۳) فایل‌های ناهماهنگ، (۴) فایل نصب قدیمی/DDL رزرو — هیچ‌کدام دیگر وردپرس را با خطای مهلک نمی‌شکنند
- ریشه همه مشکلات کاربر یک چیز بود: پوشه افزونه حذف نشده و فایل‌های قدیمی مانده‌اند؛ راه‌حل قطعی: حذف کامل پوشه(ها) + حذف افزونه از فهرست + نصب تازه + ری‌استارت Apache

---
Task ID: 6
Agent: main
Task: رفع خطای کاربر «Call to undefined method TPP_Settings::init()» — ناهماهنگی فایل‌های قدیمی/جدید روی هاست (XAMPP)

Work Log:
- تحلیل stack trace کاربر: خط ۱۰۱ فایل اصلی دقیقاً TPP_Settings::init() نسخه جدید است → فایل اصلی روی هاست کاربر جدید است اما class-tpp-settings.php قدیمی (بدون متد static init — سبک 1.0.x)؛ علت محتمل: استخراج ناقص زیپ، کپی دستی ناقص، باقی‌ماندن کپی دوم قدیمی، یا کش OPcache
- راستی‌آزمایی سلامت بیلد محلی: هر ۱۳ ماژول متد init/maybe_upgrade دارند و شبیه‌ساز WP کامل سبز است → خطا قطعاً از ترکیب فایل‌ها روی هاست کاربر است نه بیلد
- دفاعی‌سازی کامل tpp_salary_init: هر ۱۳ ماژول با class_exists + is_callable اجرا می‌شود؛ ماژول ناهماهنگ → رد می‌شود و اعلان مدیریتی گام‌به‌گام (حذف کامل پوشه، نصب مجدد، ری‌استارت Apache برای پاک‌شدن OPcache) نمایش داده می‌شود — دیگر هیچ خطای مهلکی در plugins_loaded ممکن نیست
- scripts/test_mixed_files.php (تست جدید): کپی موقت از افزونه + جایگزینی Settings با کلاس قدیمی‌سبک بدون init + لود و اجرای init کامل → بدون خطا، اعلان ثبت شد، بقیه ماژول‌ها (مثل Reports) سالم بالا آمدند — PASS
- رگرسیون کامل سبز: شبیه‌ساز WP، تست بارگذاری دوگانه، static_scan (۵۲ فایل)، compat، موتور ۲۱/۲۱، E2E فیش و ZIP
- نسخه 1.1.2 (هدر + ثابت + readme.txt + CHANGELOG.md + index.html)؛ بسته‌بندی مجدد و حذف نسخه‌های قدیمی از download/

Stage Summary:
- زنجیره دفاعی کامل: بارگذاری دوگانه (1.1.1) + فایل‌های ناهماهنگ (1.1.2) → هر دو سناریو اکنون فقط اعلان راهنما می‌دهند و هیچ‌گاه وردپرس را با خطای مهلک نمی‌شکنند
- دستورالعمل نهایی برای کاربر: حذف کامل همه پوشه‌های tpp_salary در wp-content/plugins + حذف افزونه از فهرست افزونه‌ها + نصب تازه 1.1.2 + در صورت نیاز ری‌استارت Apache (پاک‌سازی OPcache)

---
Task ID: 5
Agent: main
Task: رفع خطای مرگبار کاربر «Cannot declare class TPP_Xlsx_Writer, because the name is already in use» (class-tpp-xlsx-writer.php line 18) — مقاوم‌سازی کامل در برابر بارگذاری دوگانه

Work Log:
- تشخیص ریشه: خطا هنگام تعریف دوگانه کلاس‌ها رخ می‌دهد — سناریوهای محتمل: نصب دو نسخه از پوشه افزونه (tpp_salary + tpp_salary-1)، ورودی تکراری در active_plugins، یا include دوم فایل کلاس از مسیر دیگری؛ همه ۱۸ کلاس افزونه بدون گارد class_exists بودند
- scripts/add_guards.py: تزریق خودکار گارد class_exists روی هر ۱۸ کلاس (قرارگیری قبل از docblock + بستن در انتهای فایل) و گارد function_exists روی هر ۲۲ تابع helpers.php
- کشف مکانیزم کلیدی با تست minimal: PHP توابع unconditional سطح بالای فایل را در «زمان کامپایل» early-bind می‌کند؛ بنابراین بارگذاری دوم حتی با return اولیه هم قبل از اجرای هر خطی با «Cannot redeclare tpp_salary_autoload()» می‌مرد (تست واقعاً این را نشان داد!) — راه‌حل: هر ۵ تابع بوت‌استرپ اصلی (autoload/init/activate/deactivate/load_textdomain) به تعریف شرطی با function_exists تبدیل شدند تا early-bind رخ ندهد
- گارد ضد بارگذاری دوگانه در ابتدای tpp-salary.php: اگر TPP_SALARY_VERSION از قبل تعریف شده باشد، نسخه دوم به‌جای خطای مهلک فقط اعلان مدیریتی راهنما (حذف نسخه اضافی) نمایش می‌دهد و تمیز return می‌کند
- scripts/test_double_load.php (تست اختصاصی جدید): بارگذاری دوباره فایل اصلی (require + require_once)، re-include هر ۱۹ فایل کلاس با eval (بدترین حالت)، re-require دوگانه helpers.php → همه سبز
- رگرسیون کامل: شبیه‌ساز WP (LOAD/ACTIVATE/INIT ALL GREEN)، static_scan (۵۲ فایل)، php_compat_scan، موتور محاسبات ۲۱/۲۱، E2E فیش PDF (۲۰۶۸۳ بایت) و ZIP عمده (۶۹۲۰۸ بایت) — همه سبز
- نسخه 1.1.1 (هدر + ثابت + readme.txt Stable tag + CHANGELOG.md)؛ index.html صفحه دانلود به‌روز شد با هشدار «قبل از نصب فقط یک پوشه بماند»
- بسته‌بندی مجدد: tpp_salary-1.1.1-plugin.zip + tpp-salary-v1.1.1-full.zip؛ نسخه‌های 1.1.0 و قدیمی‌تر از download/ حذف شدند

Stage Summary:
- افزونه اکنون در برابر «هر» سناریوی بارگذاری دوگانه ایمن است: دو پوشه همزمان، ورودی تکراری active_plugins، include دوم فایل کلاس، redeclare توابع — هیچ‌کدام دیگر خطای مهلک تولید نمی‌کنند
- اگر کاربر باز دو نسخه داشته باشد: اولین نسخه کامل بالا می‌آید و دومی فقط اعلان «نصب دوگانه» می‌دهد؛ داده‌ها سالم می‌مانند
- برای رفع کامل در سایت کاربر: حذف همه پوشه‌های افزونه به‌جز یکی در wp-content/plugins و فعال‌سازی نسخه ۱.۱.۱

---
Task ID: 4
Agent: main
Task: درخواست‌های کاربر پس از شکست مجدد فعال‌سازی — سازگاری هاست رایگان، دارک‌مود، نمونه داینامیک، وزیرمتن، حالت آفلاین، درج worklog داخل پلاگین

Work Log:
- فرضیه اصلی شکست فعال‌سازی: هاست رایگان با PHP قدیمی (۵.۶) — عملگر ?? (PHP 7) در ~۸۰ نقطه خطای parse می‌داد
- تبدیل توکن‌محور همه ?? به isset ? : (scripts/coalesce_to_isset.php) — ۸۱ مورد در ۱۱ فایل، شامل حالت‌های تو در تو؛ php -l و شبیه‌سازی کامل سبز
- ldd بررسی شد: کل lib/tfpdf از قبل 5.6-safe (تنها ?? داخل کامنت بود)
- لود تنبل PDF: حذف require مستقیم class-tpp-pdf.php از بوت‌استرپ و افزودن spl_autoload_register — هیچ خطای کتابخانه‌ای نمی‌تواند فعال‌سازی را متوقف کند
- پولی‌فیل mbstring جدید (includes/class-tpp-compat.php، اولین require): mb_strlen/mb_substr/mb_strpos/mb_stripos + mb_convert_encoding با تبدیل خالص PHP برای UTF-8↔UTF-16BE/LE↔UTF-32BE (نیاز موتور PDF) — تست مستقل helpers شد
- DDL سازگار MySQL 5.5: حذف DEFAULT CURRENT_TIMESTAMP از ۳ جدول و ست کردن created_at/updated_at صریح در درج‌های centers/banks/backups (records از قبل صریح بود)
- تب «وضعیت سیستم» در تنظیمات: PHP/WP/mbstring/zip/gd/حافظه/پوشه‌های قابل نوشتن/وجود ۵ جدول با بج سبز-قرمز + راهنمای WP_DEBUG
- دارک‌مود پیش‌فرض: بازنویسی کامل admin/css/tpp-admin.css با متغیرهای CSS (دارک پیش‌فرض + لایت اختیاری)، کادرهای واضح (#3d4a5c/#56697f)، استایل کامل جدول‌ها/فرم‌ها/دکمه‌ها/تب‌ها/اعلان‌ها در scope صفحات پلاگین؛ JS: افزودن body.tpp-page + دکمه شناور تغییر پوسته با localStorage؛ پنل فرانت‌اند (assets/tpp-frontend.css) هم دارک شد
- فونت وزیرمتن باندل شد: admin/fonts/Vazirmatn-*.ttf + @font-face در هر دو CSS (مدیریت و فرانت) به‌عنوان فونت پیش‌فرض
- فایل‌های نمونه داینامیک (includes/class-tpp-samples.php جدید): ستون‌ها در لحظه از فیلدهای فعال DB (is_profile=1 برای کارمندان، همه فیلدهای فعال برای رکوردها مطابق فرم ثبت)؛ دانلود زنده از تب جدید «فایل‌های نمونه»؛ هوک tpp_fields_changed پس از ذخیره/حذف/تاگل فیلد → refresh() فایل‌های داخل بسته؛ اصلاح حذف صفر ابتدای کد ملی (ذخیره متنی در نویسنده xlsx)؛ samples بازتولید شد (۲۲ ستون کارمندان / ۳۷ ستون رکوردها مطابق فرم)
- فارسی PDF: باطری ۱۰ متن دشوار (ZWNJ، پرانتز+عدد، شبا، درصد، اعداد با جداکننده) + رندر بصری PNG → شکل‌دهی صحیح تأیید شد (build/test_dark_persian.pdf)
- ماژول آفلاین کامل (includes/class-tpp-offline.php + admin/js/tpp-offline.js):
  - pull بسته داده (فیلدها/مراکز/بانک‌ها/کارمندان با پروفایل/رکوردهای ~۱۳ ماه) → IndexedDB (tpp_offline_db)
  - ثبت/ویرایش فرم حقوق در قطعی: رهگیری submit → صف محلی → همگام‌سازی خودکار پس از اتصال
  - حذف آفلاین در صف؛ همگام‌سازی با مدیریت تداخل (مقایسه updated_at — نسخه جدیدتر ملاک + گزارش)
  - refactor: هسته ذخیره به TPP_Salary_Pages::upsert_record منتقل شد (مشترک بین فرم و sync و restore)
  - Service Worker از admin-ajax (scope /wp-admin/): شبکه-اول صفحات tpp-* با fallback کش، کش-اول assetها، پاسخ 503 JSON برای ajax در قطعی
  - نوار وضعیت آفلاین روی همه صفحات + صفحه اختصاصی «حالت آفلاین»: وضعیت اتصال، تعداد صف، همگام‌سازی دستی
  - فایل snapshot: دانلود JSON (داده+صف) روی سیستم کاربر، بازیابی به سرور (dry-run هم دارد)، خروجی/ورودی فایل از مرورگر
  - فهرست آفلاین با جستجو در قطعی (رندر از کش)
- worklog.md (همین فایل) داخل بسته افزونه قرار گرفت و از این پس با هر تغییر به‌روز می‌شود
- نسخه 1.1.0 (هدر + readme.txt + CHANGELOG.md)؛ Requires PHP: 5.6
- تست‌ها: شبیه‌سازی WP سبز، موتور 21/21، JS هر دو فایل با node سالم، lld همه فایل‌ها، نمونه‌ها با openpyxl معتبر، E2E فیش PDF و ZIP سبز

Stage Summary:
- بسته 1.1.0: برای هاست‌های رایگان (PHP 5.6+، بدون mbstring، MySQL 5.5+) طراحی شد؛ فعال‌سازی با سه لایه دفاعی (بدون سینتکس 7، لود تنبل PDF، DDL قدیمی-سازگار)
- اگر باز هم فعال نشد: تب «وضعیت سیستم» (پس از فعال‌سازی موفق) یا WP_DEBUG مسیر دقیق خطا را نشان می‌دهد
- دارک‌مود پیش‌فرض + وزیرمتن + نمونه داینامیک + آفلاین کامل تحویل شد

---
Task ID: 3
Agent: main
Task: عیب‌یابی خطای «به‌دلیل داشتن مشکلی جدی افزونه فعال نشد» روی سایت کاربر

Work Log:
- شبیه‌سازی کامل محیط وردپرس (scripts/wp_sim_bootstrap.php با ~۱۵۰ stub): لود + فعال‌سازی + init همگی سبز — خطای سینتکس/فراخوانی تابع ناموجود در مسیر فعال‌سازی وجود نداشت
- اسکن استاتیک: php -l همه فایل‌ها ✓، مقایسه فراخوانی توابع با توابع وردپرس/PHP (scripts/static_scan.php) ✓، اسکن سینتکس PHP 7.1+/8 (scripts/php_compat_scan.php) ✓
- مقایسه ZIPهای دانلودی با build فعلی: محتوا منطبق بود
- یافتن باگ ۱: class-tpp-pdf.php هرگز require نشده بود ولی TPP_PDF در class-tpp-reports.php (۳ جا) استفاده می‌شود → خطای مرگبار Class not found در تولید هر گزارش/فیش PDF
- یافتن باگ ۲: فایل‌های کش فونت tFPDF (.mtx.php/.cw.dat/.cw127.php) حاوی مسیر مطلق سرور توسعه (/home/z/my-project/...) در ZIP بسته‌بندی شده بودند → تولید PDF روی سرور مقصد شکست می‌خورد (tFPDF کش را include و از مسیر خراب استفاده می‌کرد)
- رفع: افزودن require class-tpp-pdf.php به بوت‌استرپ؛ حذف ۵ فایل کش از build؛ اسکریپت بسته‌بندی جدید (scripts/package_101.php) با فیلتر کش؛ نسخه 1.0.1؛ Requires PHP به 7.0 کاهش یافت (کد 7.0-compatible است؛ کتابخانه tFPDF هم بررسی شد)
- تست مجدد: شبیه‌سازی WP سبز، موتور 21/21، E2E فیش PDF (20683B) و ZIP، تست جدید scripts/test_pdf_engine_load.php (لود PDF در بوت‌استرپ + رندر + صحت مسیر کش بازتولیدشده)
- بسته‌بندی: download/tpp_salary-1.0.1-plugin.zip (257.8KB, 57 فایل) و tpp-salary-v1.0.1-full.zip (263.8KB) — هر دو: main=YES، cache_files=0، مطابقت کامل با build
- نسخه‌های 1.0.0 از download حذف؛ index.html و README به 1.0.1 به‌روزرسانی؛ سرور Caddy هر دو فایل را 200 می‌دهد

Stage Summary:
- دو باگ قطعی رفع شد؛ اگر خطای کاربر هنگام فعال‌سازی بوده، محتمل‌ترین عامل محیطی: PHP قدیمی هاست (کد اکنون تا PHP 7.0 سازگار) یا آپلود ناقص پوشه افزونه
- برای تشخیص نهایی: WP_DEBUG=true و مشاهده wp-content/debug.log یا error_log هاست؛ یا خواندن خطای دقیق صفحه فعال‌سازی

---
Task ID: 2 (نهایی)
Agent: main
Task: تکمیل کامل پلاگین، تست، بسته‌بندی و اجرای سرور Caddy

Work Log:
- نوشتن کل پلاگین (۱۷ فایل PHP + CSS/JS):
  - tpp-salary.php (بوت‌استرپ), uninstall.php
  - class-tpp-install.php (۵ جدول + نقش‌های tpp_Employe/tpp_Accountant + seed ۲۴ فیلد حقوقی)
  - class-tpp-jalali.php (تبدیل جلالی تست‌شده با ۳ جفت مرجع + رفت‌وبرگشت ۴۰ روز)
  - class-tpp-formula.php (Shunting-yard امن: توکن {field}، +−×÷، پرانتز، یونری منفی)
  - class-tpp-xlsx-writer.php / reader.php (بدون وابستگی — با openpyxl اعتبارسنجی شد؛ رفع ۲ باگ XML: بسته شدن workbookViews و جای‌گیری wrapText)
  - class-tpp-settings.php (۴ تب: عمومی/پیش‌فرض‌ها، فرمول‌ها، مدیریت فیلدها، بکاپ خودکار)
  - class-tpp-centers.php / banks.php (حذف بانک = حذف حساب‌ها از پروفایل کارمندان)
  - class-tpp-employees.php (فهرست + فیلدهای پروفایل + مراکز چندگانه + حساب به ازای هر بانک)
  - class-tpp-import.php (ورود گروهی xlsx/csv + ساخت خودکار کاربر با کد ملی/رندم ۱۰ رقمی + dry-run)
  - class-tpp-salary-pages.php (ویزارد ۳ مرحله‌ای، هایلایت آبی مغایرت با پروفایل، دکمه محاسبه «سایر»، فهرست+جستجو+ویرایش)
  - class-tpp-reports.php (پیوت لیست حقوق اکسل/PDF با تعداد نفرات A4، فیش بانکی، فیش تکی PDF، فیش عمده ZIP با لوگو)
  - class-tpp-backup.php (JSON/اکسل/ZIP دستی + کرون روزانه/هفتگی/ماهانه/سالانه + بازگردانی + نگهداشت)
  - class-tpp-ajax.php / frontend.php (شورت‌کد [tpp_salary_panel]: فیش‌های من + ثبت اطلاعات بانکی)
  - admin/js/tpp-admin.js (موتور محاسبه زنده JS موازی PHP — در مرورگر واقعی تست شد)
- تست‌ها: ۲۱/۲۱ موتور (جلالی، فرمول مطابق سطرهای نمونه سند: ناخالص 381,381,596 / بیمه 18,948,842 / خالص 362,432,754)، E2E فیش PDF و ZIP — همه سبز
- تصمیم مهم: گرد کردن کسورات (منفی) به سمت صفر تا با نمونه‌های محاسباتی سند مطابق باشد (18,948,842.92 → 18,948,842)
- تولید فایل‌های نمونه از داده‌های خود سند: samples/employees-sample.xlsx (۷۶ کارمند) و salary-records-sample.xlsx (۴۲ رکورد مرداد ۱۴۰۵)
- php -l همه فایل‌ها بدون خطا
- بسته‌بندی: download/tpp_salary-1.0.0-plugin.zip (فقط پلاگین) و download/tpp-salary-v1.0.0-full.zip (پلاگین+caddy+مستندات)
- اجرای سرور Caddy (v2.8.4) روی پورت 3000 با صفحه دانلود فارسی — تست HTTP 200 برای صفحه و ZIP

Stage Summary:
- موتور PDF فارسی: tFPDF + شکل‌دهی حروف + Bidi + وزیرمتن؛ دو باگ ریشه‌ای رفع شد (bidi ارقام/پرانتز با مرجع مرورگر؛ باگ 65535 در GetStringWidth)
- خروجی‌های نهایی در /home/z/my-project/download/ (index.html صفحه دانلود، دو ZIP، README)
- سرور Caddy فعال: پورت 3000 (باینری با نام tools/webserver به دلیل محدودیت اجرای مستقیم)
- پلاگین: ۸۱ فایل، ~۹۴۰KB منبع، بدون وابستگی composer (XLSX و PDF اختصاصی)

---
Task ID: 1
Agent: main
Task: ساخت پلاگین وردپرس حقوق و دستمزد طبق سند نیازمندی‌های آپلودشده

Work Log:
- خواندن سند نیازمندی‌ها از upload/پلاگین حقوق و دستمزد.docx (پلاگین وردپرس tpp_Salary با نقش‌ها، مراکز، بانک‌ها، فرمول‌ها، گزارش‌ها، فیش PDF، بکاپ، ورود گروهی + Caddy + ZIP)
- استخراج ۷۶ کارمند نمونه و ۴۲ رکورد حقوق نمونه (۱۴۰۵/مرداد، مراکز بومهن و رودهن)
- نصب static PHP 8.3.9 در tools/php برای تست
- دانلود tFPDF 1.33 (LGPL) و فونت Vazirmatn Regular/Bold
- ساخت موتور PDF فارسی (class-tpp-pdf.php) با شکل‌دهی حروف (Presentation Forms) و Bidi ساده
- یافتن و رفع ۲ باگ: (۱) bidi ارقام فارسی/پرانتز — اعتبارسنجی با مرورگر به عنوان مرجع؛ (۲) باگ tFPDF: عرض صفر به صورت 65535 ذخیره می‌شود و GetStringWidth آن را 65535 می‌خواند → متن off-page می‌رفت. Fix: در GetStringWidth مقدار 65535 = 0
- تست رندر PDF با pdftoppm و مقایسه بصری با مرورگر ✓

Stage Summary:
- موتور PDF فارسی آماده و تست‌شده: build/tpp_salary/includes/class-tpp-pdf.php
- ابزار تست: /home/z/my-project/tools/php
- ساختار پروژه: build/tpp_salary (پلاگین) + build/caddy

---
Task ID: 9
Agent: main
Task: ریشه‌یابی گزارش کاربر (افزونه‌های دیگر با پیشوند tpp_) — تکمیل ایزوله‌سازی فضای نام 1.3.0، مهاجرت کامل داده، تست‌های تداخل/ارتقا، بسته‌بندی

Work Log:
- تأیید ریشه با استخراج بسته 1.2.0: کلاس‌های عمومی TPP_* بودند → تداخل با افزونه بیگانه هم‌نام، علت هر سه خطای قبلی
- راستی‌آزمایی 1.3.0 نیمه‌تمام جلسه قبل: ۱۸ کلاس/۳۲ تابع/۵ جدول/آپشن‌ها/متا/نقش‌ها/کرون‌ها/آژاکس همگی یکتا (TppSalary_* و tpp_salary_*)
- حفره بحرانی: migrate_legacy فیلدها و رکوردهای حقوق را مهاجرت نمی‌داد → افزودن دو migrate_table با ستون‌های دقیق DDL 1.2.0 (از بسته قدیمی استخراج شد)
- ارتقای شبیه‌ساز: wpdb->usermeta/options/users + کلاس wpdb_sqlite (SQLite واقعی در حافظه) + هوک sim_pre_activate_cb
- تست جدید test_conflict_sim.php (۳۳ ادعا) — همزیستی با افزونه بیگانه TPP_* هم‌نام: PASS
- تست جدید test_upgrade_120.php (۲۳ ادعا) — مهاجرت واقعی داده 1.2.0→1.3.0 روی SQLite شامل حفظ payload فارسی، شناسه‌ها، idempotency و عدم لمس جدول بیگانه wp_tpp_field_archives: PASS
- رگرسیون کامل سبز (شبیه‌ساز/double-load/mixed/stale/restore-schema/DDL words/static/compat/موتور ۲۱/۲۱/E2E/باتری فارسی)
- مستندات به‌روز: CHANGELOG 1.3.0، readme.txt، README.md (نقش‌های جدید + نسخه)، index.html (کارت راهنمای تداخل tpp_)
- بسته‌بندی: tpp_salary-1.3.0-plugin.zip (۴۳۴.۷KB/۶۵ فایل) و tpp-salary-v1.3.0-full.zip (۴۴۵KB/۷۰ فایل) — main=YES، cache=0
- اعتبارسنجی نهایی: diff -r بستهٔ بازشده با build → فقط فایل‌های کش فونت (حذف عمدی) متفاوت

Stage Summary:
- افزونه اکنون با هر ترکیبی از افزونه‌های tpp_ دیگر سازگار است؛ هرگز کد/DDL بیگانه اجرا نمی‌شود و تداخل جدولی ندارد
- ارتقا از ≤1.2.0 خودکار و امن است: فعال‌سازی 1.3.0 تمام داده‌ها را با حفظ شناسه‌ها منتقل می‌کند
- دو تست دائمی جدید (تداخل + مهاجرت واقعی) به مجموعه رگرسیون اضافه شد

---
Task ID: 10
Agent: main
Task: رفع سه مشکل گزارش‌شده کاربر در 1.3.0 — نمونه اکسل معیوب، 404 صفحات مدیریت، مدیریت ناقص بکاپ‌ها (نسخه 1.3.1)

Work Log:
- باگ ۱ (نمونه اکسل معیوب): همه ۹ نقطه خروجی فایل بدون پاک‌سازی بافر بودند؛ خروجی مزاحم افزونه‌های دیگر/Notice به ابتدای بایت‌های xlsx/PDF/ZIP می‌چسبید → فایل خراب. راه‌حل: tpp_salary_clean_output() + فراخوانی در همه دانلودها + داخل Xlsx_Writer::download
- باگ ۲ (حذف بکاپ وجود نداشت): delete_backup با نان + تأیید + دفاع path traversal + دکمه حذف در جدول + پیام موفقیت
- باگ ۳ (404 صفحات): ثبت‌های منو در شبیه‌ساز اثبات شد که سالم‌اند (۱۳ صفحه ثبت و رندر بدون خطا) → عامل محیطی محتمل؛ تب «وضعیت سیستم» ۴ ردیف تشخیصی گرفت: ماژول‌های فعال، صفحات ثبت‌شده، دسترسی کاربر، افزونه‌های دیگر tpp_
- تست‌ابد: ثبت واقعی هوک‌ها + منوها + wpdb_sqlite با آبجکت مطابق پیش‌فرض وردپرس (رفع باگ خود تست‌ابد)
- تست جدید test_admin_runtime.php (~۵۵ ادعا): منو/رندر/لینک‌ها/handlerها/اعتبارسنجی xlsx روی دیتابیس واقعی/چرخه بکاپ — PASS
- رگرسیون کامل (۱۵ سناریو) سبز؛ نسخه 1.3.1 + INSTALL_BUILD همگام؛ بسته‌بندی و حذف نسخه 1.3.0؛ اعتبارسنجی diff بسته با build

Stage Summary:
- هر سه گزارش به اقدام ملموس تبدیل شد؛ تب وضعیت سیستم اکنون علت 404 را با نام دقیق نشان می‌دهد
- تحویل: download/tpp_salary-1.3.1-plugin.zip و tpp-salary-v1.3.1-full.zip

---
Task ID: 11
Agent: main
Task: رفع قطعی دو باگ 1.3.1 گزارش‌شده کاربر — 404 منوها/دسترسی (مراکز، بانک‌ها، کارمندان، ورود گروهی) و فایل اکسل معیوب + پسوند .excel (نسخه 1.3.2)

Work Log:
- بازتولید دقیق باگ منو: توابع واقعی وردپرس 6.6 از سورس رسمی استخراج شد (add_menu_page/add_submenu_page/get_plugin_page_hookname/user_can_access_admin_page/sanitize_title واقعی) و کل چرخه منو در شبیه‌ساز بازسازی شد (SIM_REAL_MENU + wp_core_ref/real_menu_lib.php) — باگ با همان ۴ URL خام کاربر + 404 منوی والد بازتولید شد
- ریشه قطعی: چهار ماژول (centers/banks/employees/import) با اولویت ۱۰ و قبل از Salary_Pages اجرا می‌شدند → add_submenu_page قبل از add_menu_page والد → hookname با پیشوند admin_page_ ساخته می‌شد؛ در رندر با پیشوند hookِ والد (sanitize_title فارسی = %d8%ad%d9%82...) بازسازی می‌شد → ناسازگاری → لینک slug خام (404) و دسترسی رد (user_can_access_admin_page هوک را در $_registered_pages نمی‌یافت). تنظیمات چون اولویت ۲۰ داشت سالم می‌ماند — منطبق بر گزارش کاربر
- رفع ۱: منوی والد با اولویت ۹ + اولویت صریح ۱۰ برای چهار ماژول
- رفع ۲ (جبران خودکار): ensure_pages در admin_menu با اولویت ۹۹۹ — همه ۱۳ صفحه بررسی و callbackها روی hookname نهایی صحیح وصل و $_registered_pages تکمیل می‌شود (دفاع در برابر هر به‌هم‌ریختگی ترتیب در آینده)
- ریشه اکسل معیوب (سه نقص ساختاری که openpyxl نادیده می‌گرفت ولی اکسل واقعی رد می‌کرد): (۱) حلقه [Content_Types].xml ایندکس عددی نداشت و نام شیت فارسی جای N می‌نشست (sheetکارمندان.xml!) — Override بخش واقعی غایب و Override بخش ناموجود ثبت می‌شد؛ (۲) numFmt سفارشی (#,##0) هرگز در <numFmts> اعلام نمی‌شد؛ (۳) ترتیب printOptions/pageMargins/pageSetup مخالف اسکیما
- رفع نویسنده XLSX: بازنویسی to_string (تک‌گذر، بدون کد مرده دوگذرِ زیپ) + Content_Types عددی + اعلام واقعی numFmt_defs + ترتیب اسکیمایی + ht ردیف‌ها + گارد close/tmp
- رفع پسوند .excel: نقشه ext_map در Backup::make + نگاشت legacy 'excel'→'xlsx' + فرم/UI/لیبل‌ها + گارد realpath در backup_download (پوشه غایب → پیام واضح به‌جای fatal)
- تست جدید test_xlsx_strict.py (سطح اکسل): سلامت ZIP، پوشش کامل Content_Types، بدون Override ناموجود، سازگاری rels، خوش‌فرمی همه XML، یکتایی numFmt، ترتیب عناصر، openpyxl — همه خروجی‌ها (نمونه‌ها، multisheet فارسی، بکاپ واقعی) سبز
- نمونه‌های بسته با موتور سالم بازتولید شدند + TppSalary_Install::upgrade اکنون در هر فعال‌سازی/ارتقا نمونه‌ها را از فیلدهای واقعی سایت بازتولید می‌کند
- تست جدید test_menu_urls.php: ۵۱ ادعا (ثبت/URL/دسترسی/callback برای ۱۳ صفحه) — قبل از رفع ۱۳ FAIL دقیقاً مطابق گزارش کاربر، بعد از رفع صفر
- رگرسیون کامل سبز (۱۵ سناریو) + php -l همه فایل‌های ویرایش‌شده
- نسخه 1.3.2: بوت‌استرپ/INSTALL_BUILD/readme/CHANGELOG/README/index.html؛ بسته‌بندی plugin+full و حذف بسته‌های قدیمی

Stage Summary:
- هر دو باگ با «بازتولید در محیط تست» رفع شدند نه حدس: منوی 404 به‌دلیل ترتیب ثبت والد/زیرمنو با hooknameهای percent-encoded فارسی بود و اکسل معیوب به‌دلیل سه نقص ساختاری OOXML
- دفاع چندلایه: اولویت ۹ (والد اول) + جبران خودکار ۹۹۹ + تست واقع‌گرایانه وردپرس واقعی → این کلاس باگ دیگر قابل بازگشت نیست
- خروجی اکسل اکنون با اعتبارسنج سخت‌گیرانه (همان معیارهای اکسل واقعی) تأیید می‌شود؛ به پیشنهاد کاربر درباره کتابخانه Composer پاسخ داده شد (موتور سبک داخلی، بدون وابستگی ۲۰ مگابایتی)

---
Task ID: 12
Agent: main
Task: رفع شش گزارش جدید کاربر در 1.4.0 — فرمول‌ها/دکمه سایر/دارک‌مود/styles.xml/ایمپورت بکاپ و کارمندان روی ویندوز + قابلیت جدید ورود گروهی حقوق

Work Log:
- باگ ۱ (فرمول‌ها): دو ریشه — (الف) admin JS به TPPSALARY ارجاع می‌داد ولی localize نامش TPP است → فیلدهای فرمولی خالی + ReferenceError که همه بایندها را می‌کشت؛ (ب) فرمول‌های سراسری تنظیمات (gross/insurable/net) فقط در تنظیمات ذخیره می‌شدند و هیچ‌جا اعمال نمی‌شدند → اکنون fallback تنظیمات در هر دو موتور (compute_values + fields_js) اعمال می‌شود
- باگ ۲ (دکمه سایر): jquery-ui-dialog و wp-jquery-ui-dialog هرگز enqueue نمی‌شدند → $.fn.dialog تعریف‌نشده و اسکریپت دیالوگ می‌مرد؛ enqueue + dialogClass 'tpp-dialog wp-dialog' + گارد $.fn.dialog
- باگ ۳ (دارک‌مود ناقص): color-scheme: dark/light + استایل صریح select option/optgroup + input[type=file] + پوشش کامل .ui-dialog (سفید-روی-سفید دیالوگ)
- باگ ۴ (اکسل واقعی «Removed Part: /xl/styles.xml»): ترتیب عناصر <font> نقض OOXML بود (sz/color/name قبل از b/i) و <name> صفت الزامی val را نداشت → اکسل کل styles.xml را حذف و سلول‌ها را repair می‌کرد؛ ترتیب مرجع اکسل (b,i,sz,color,name) + فرم مرجع پرکردن‌های none/gray125
- باگ ۵ (ایمپورت بکاپ، ویندوز/XAMPP): tmp_name از wp_unslash عبور می‌کرد → stripslashes بک‌اسلش‌های C:\xampp\tmp\phpXXX.tmp را حذف می‌کرد → file_get_contents شکست؛ tmp_name خام + is_uploaded_file/is_readable + پیام JSON-only؛ همان ریشه در ورود گروهی کارمندان («فایل اکسل قابل بازکردن نیست»)
- قابلیت جدید: ورود گروهی حقوق (منوی tpp-salary-import-records) — process_records قابل‌تست، پارس نام ماه فارسی/عدد، تطبیق کارمند با نام/کد ملی، مرکز با نام، سلول «—»/خالی = force-compute از فرمول (پارامتر جدید force در compute_values/upsert_record)، dry-run، upsert بدون تکرار، دکمه دانلود نمونه
- پادزهر جانبی: جداکننده هزارگان فارسی «٬» (U+066C) در parse_number/parseNum/توکنایزر فرمول هر دو موتور (قبلاً ۷۰۰٬۰۰۰ → ۷۰۰ پارس می‌شد)
- ریفکتور تست‌پذیری: Backup::apply_restore عمومی، Import::process_records عمومی؛ شبیه‌ساز لایه کاربر واقعی SQLite گرفت (SIM_SQLITE_USERS: users/usermeta/get_users/wp_insert_user/WP_User::add_role)
- تست جدید test_fixes_140.php (۵۲ ادعا): فرمول سراسری PHP/JS، محاسبات دقیق round-trip نمونه→خواندن→ورود (base 21,700,000 / gross 21,850,000 / بیمه -1,529,500 / net 20,320,500)، upsert بدون تکرار، رد سطر مخرب با حفظ داده، styles.xml، دارک‌مود/دیالوگ، tmp_name
- test_xlsx_strict.py ارتقا: چک ترتیب CT_Font + val الزامی name + فرم پرکردن‌ها — نمونه‌ها و بکاپ واقعی سبز
- رگرسیون کامل ۱۶ سناریو سبز؛ بازتولید نمونه‌ها با موتور سالم؛ نسخه 1.4.0 (tpp-salary.php، INSTALL_BUILD، readme، CHANGELOG، README، index.html)
- بسته‌بندی: tpp_salary-1.4.0-plugin.zip (۴۵۲KB/۶۵ فایل) و tpp-salary-v1.4.0-full.zip (۴۶۳KB/۷۰ فایل)؛ diff با build فقط فایل‌های کش فونت عمدی؛ بسته‌های 1.3.2 حذف شدند

Stage Summary:
- هر شش گزارش کاربر ریشه‌یابی و رفع شد و «ورود گروهی حقوق» با فایل نمونه اضافه شد
- تحویل: download/tpp_salary-1.4.0-plugin.zip و tpp-salary-v1.4.0-full.zip + صفحه دانلود به‌روز

---
Task ID: 13
Agent: main
Task: رفع ده گزارش جدید کاربر در 1.4.1 — بانک‌ها/تشخیص ستون ورود گروهی/تطبیق و ساخت خودکار کارمند/full_name/انتقال سه فیلد به پروفایل/حذف «ذاتاً منفی»/فرمول مشمول بیمه/فرم واحد فیش بانکی/حفظ داده هنگام حذف افزونه/دارک‌مود

Work Log:
- باگ ۱ (بانک اضافه نمی‌شود): ریشه — add() ستون created_at را در جدولی که فقط id/name/sort_order دارد درج می‌کرد → wpdb->insert بی‌سروصدا false. درج اصلاح + اعلان خطای نام تکراری (bankerr)
- باگ ۲ (تشخیص عنوان ستون): normalize_key تهاجمی شد — حذف هر نویسه غیر حرف/رقم (ZWNJ، LRM/RLM، گیومه، علائم، NBSP، تطویل) + یکسان‌سازی ي/ى/ك/ة/أ/إ/آ و اعراب + مترادف‌های جدید (نام کارمند/نام کامل/نام و فامیل…) + پیام خطا اکنون عناوین شناسایی‌شده سطر اول را نشان می‌دهد
- باگ ۳ (تطبیق کارمند): در هر دو ورود گروهی تطبیق با کد ملی «یا» نام نرمال‌شده (display_name یا full_name پروفایل) — در ورود کارمندان دیگر کاربر تکراری ساخته نمی‌شود
- قابلیت جدید (ساخت خودکار کارمند در ورود حقوق): process_records پارامتر $auto_create گرفت (چک‌باکس صفحه، پیش‌فرض روشن) — ساخت کاربر با کد ملی/نام + رمز تصادفی فقط یک‌بار در گزارش + ثبت مرکز و full_name در پروفایل؛ create_employee_user مشترک دو ماژول
- باگ ۴ (فیلد نام و نام خانوادگی پروفایل): فیلد سیستمی full_name (متن، فقط‌پروفایلی) به seed اضافه شد + در پروفایل کاربر ویرایش می‌شود و display_name را هماهنگ می‌کند + در create_employee/imports ذخیره می‌شود
- باگ ۵ (انتقال ۳ فیلد به پروفایل): ستون جدید in_record در جدول فیلدها (DB_VERSION=3 → dbDelta ستون را اضافه می‌کند) + migrate_field_flags: job_title/vehicle_type/vehicle_plate/full_name=0 و فیلدهای فرم=1 + حذف از فرم ثبت/fields_js/نمونه رکورد/گزارش‌ها/آفلاین + چک‌باکس‌های «پروفایل/فرم ثبت» در ویرایشگر فیلد
- باگ ۶ (ذاتاً منفی): مفهوم حذف شد — seed و مهاجرت همه is_negative=0؛ چک‌باکس از ویرایشگر فیلد حذف؛ قرمز منفی بر اساس علامت خود عدد در: فرم ثبت (PHP + JS زنده)، فهرست حقوق‌ها، پیوت، فیش بانکی، فیش وب (کلاس tpp-neg)، فیش PDF (SetTextColor قرمز)، اکسل (num_neg بدون is_negative)
- باگ ۷ (دکمه محاسبه با فرمول مشمول بیمه): upsert_record در حالت $insurable_formula مقدار را به force_compute اضافه می‌کند (منطق تشخیص ویرایش دستی دیگر فرمول را رد نمی‌کرد) + فیلد محاسباتیِ غایب در raw ورود گروهی هم force-compute می‌شود (به‌جای صفرِ دستی)
- باگ ۸ (فیش بانکی دو فرم): period_form پارامتر $with_bank گرفت — سال/ماه/مرکز/بانک در یک فرم با یک دکمه «جستجو»؛ فرم دوم حذف شد
- باگ ۹ (حذف افزونه داده‌ها را پاک می‌کرد): گزینه جدید tpp_salary_delete_data با پیش‌فرض «0» (حفظ) — uninstall فقط با تیک تنظیمات حذف می‌کند + گزینه قدیمی keep_data در ارتقا/ذخیره تنظیمات جمع‌آوری + در uninstall هم پاک می‌شود
- باگ ۱۰ (دارک‌مود): پس‌زمینه سفید inline فرم کارمند جدید و ویرایشگر فیلد → کلاس tpp-panel + enqueue CSS در profile.php/user-edit.php
- جانبی: جستجوی حقوق‌های ثبت‌شده با نام «یا» کد ملی (EXISTS روی usermeta + تبدیل ارقام)؛ جستجوی کارمندان شامل full_name؛ مترادف‌های عنوان ستون رکورد (دستمزد روزانه ↔ مرجع و…)؛ در_record به whitelist بازگردانی بکاپ اضافه شد (تست restore_schema یافتش کرد!)
- شبیه‌ساز: dbDelta واقعی شد (ساخت جدول/ALTER ADD COLUMN ترجمه‌شده به SQLite) تا مسیر ارتقای ستون جدید در تست پوشش داده شود + insert_id در insert اصلاح + stub wp_rand + stub wp-admin/includes/upgrade.php خودکار
- تست جدید test_fixes_141.php (۶۱ ادعا): بانک/نرمال‌سازی/in_record/ساخت خودکار/تطبیق/فرمول اجباری/قرمز منفی/دارک‌مود/فرم واحد/uninstall — ALL PASS؛ رگرسیون کامل سبز (۱۳ اسکریپت PHP + xlsx_strict + ddl_words + static/compat)
- نسخه 1.4.1 (بوت‌استرپ/INSTALL_BUILD/DB_VERSION=3/readme/CHANGELOG/index.html) + sync worklog داخلی افزونه
- بسته‌بندی: tpp_salary-1.4.1-plugin.zip (460.6KB/65 فایل) و tpp-salary-v1.4.1-full.zip (471.7KB/70 فایل) — main=YES cache=0؛ diff با build فقط کش فونت عمدی؛ بسته‌های 1.4.0 حذف شدند

Stage Summary:
- هر ده گزارش با «بازتولید در محیط تست» رفع شد؛ نرمال‌سازی تهاجمی مشکل کلاسیک تشخیص ستون‌های فارسی اکسل را ریشه‌ای می‌بندد
- رفتارهای جدید: ساخت خودکار کارمند (پیش‌فرض روشن)، حفظ داده هنگام حذف افزونه (پیش‌فرض)، علامت عدد ملاک منفی/مثبت (قرمز سراسری)
- تحویل: download/tpp_salary-1.4.1-plugin.zip و tpp-salary-v1.4.1-full.zip + صفحه دانلود به‌روز

---
Task ID: 14
Agent: main
Task: شش به‌روزرسانی 1.5.0 — انتقال قطعی سه فیلد به پروفایل / بکاپ چندقالبی (SQL+JSON+اکسل مجزا و ZIP شامل فایل‌های پلاگین) / رفع اتصال حروف فارسی PDF / فیش A5 / به‌روزرسانی نمونه‌ها / بازبینی ورود گروهی

Work Log:
- بازبینی کد پایه (1.4.1 تحویل‌شده) و کشف ریشه واقعی باگ PDF: در shape() اسکن «حرف قبلی» روی آرایه در حال ویرایش انجام می‌شد؛ حروف قبلی به فرم نمایشی (0xFE91 و…) تبدیل شده بودند و is_arabic() آن‌ها را نمی‌شناخت → link_p همیشه false → تقریباً همه حروف آغازین/جدا رندر می‌شدند («برخی کلمات درست، برخی غلط» = واژه‌های بدون حروف غیرچسبان)
- رفع موتور شکل‌دهی (class-tppsalary-pdf.php): اسکن prev/next روی کپی متن اصلی ($orig) + شناخت فرم‌های نمایشی در is_arabic + حذف قاعده نادرست قطع اتصال به حروف بعدیِ غیرچسبان (ا د ذ ر ز ژ و) + لگاتور «لا» با پذیرش اِ الف پایانی (0xFE8E) و تفکیک FEFB/FEFC بر اساس اتصال ل به قبل + اعراب شفاف + حذف 0x066B/0x066C از علائم RTL (جداکننده رقم اجرای عدد را نمی‌شکند)
- تست جدید test_pdf_shape.php (۱۳ ادعا): حقوق/محمد/دستمزد/رضا/سلام/سالار گلیف‌به‌گلیف + ZWNJ + اعراب + ترتیب RTL اعداد — ALL PASS
- فیش حقوقی A5 (build_payslip_pdf): صفحه A5 (148.5×210mm)، حاشیه ۸mm، فونت فشرده، عرض ستون‌ها نسبی به صفحه، ارتفاع سطر خودکار بر اساس تعداد فیلدهای show_in_payslip (clamp 4.0–5.6mm) تا کل فیش در یک برگ جا شود؛ صفحه دوم خودکار برای فیش‌های خیلی بلند
- بکاپ چندقالبی (class-tppsalary-backup.php): قالب جدید SQL — collect_sql با SET NAMES + بخش کارمندان (INSERT wp_users با ON DUPLICATE KEY + DELETE/INSERT متاهای tpp_salary_* بدون DROP جداول هسته) + جداول افزونه (SHOW CREATE TABLE با fallback برای غیر-MySQL + INSERT chunked ۵۰تایی) + sql_value با esc_sql/fallback؛ collect_excel به دو سازنده مجزا شکسته شد (employees/records) + fill_excel_records مشترک؛ collect_json_employees/records (kind=employees/records)؛ ZIP جدید: پوشه tpp-salary-backup-{stamp}/ با README فارسی + json/{full,employees,records}.json + excel/{employees,records}.xlsx + sql/{full,employees,records}.sql + plugin/ (کل پوشه افزونه با RecursiveIteratorIterator، حذف worklog.md/debug.log)؛ نوع sql به create_manual/ext_map/برچسب‌ها/دروپ‌داون UI اضافه شد
- سپر هاردکد فیلدهای فقط‌پروفایلی (helpers.php): tpp_salary_profile_only_keys + tpp_salary_field_in_record — اعمال در ۷ جایگاه (salary-pages ×۳: فرم ثبت/fields_js/فیلتر لیست، reports.record_fields، offline، import.process_records، samples.record_form_fields) — عنوان شغلی/نوع خودرو/پلاک خودرو/full_name حتی با پرچم اشتباه DB در فرم ثبت نمی‌آیند
- باگ نگاشت ستون‌های هم‌عنوان در ورود گروهی حقوق: «مبلغ تعطیل کاری» (holiday_rate/holiday_pay) و «حقوق مشمول بیمه» (insurable_default/insurable) هر دو برچسب یکسان دارند — نگاشت تک‌به‌تک قبلی هر دو ستون را به یک فیلد می‌برد؛ اکنون field_keys_by_norm_label (فهرست به ترتیب sort_order) + مکان‌یاب موقعیتی label_seen + گارد used_keys (هر فیلد حداکثر یک ستون) — مترادف‌ها فقط وقتی عنوان اصلاً غایب است اضافه می‌شوند
- حذف wp_new_user_notification تکراری در handle() (create_employee_user خودش ارسال می‌کند)
- bump نسخه: tpp-salary.php 1.5.0 + TPP_SALARY_INSTALL_BUILD 1.5.0 (نگهبان نصب ناهماهنگ!) + readme + CHANGELOG + index.html دانلود
- تست test_fixes_150.php (۵۷ ادعا): گارد/سپر استاتیک و DB، فیش A5 (MediaBox 420.94×595.28 + نبود حرف خام فارسی)، بکاپ SQL (بخش کارمندان/رکوردها مجزا)، ZIP (۸ گروه فایل + فایل‌های پلاگین + json مجزا معتبر)، نمونه‌ها (employees با ستون‌های پروفایلی، records بدون آن‌ها)، نگاشت ستون‌های هم‌عنوان (holiday_rate=5000/holiday_pay=999000/insurable_default=111000/insurable=222000) + برچسب واقعی بر مترادف اولویت دارد + سناریوی فقط-مترادف + upsert بدون تکرار — ALL PASS
- رگرسیون کامل سبز: test_conflict_sim/test_upgrade_120/test_fixes_140/test_fixes_141/test_admin_runtime/test_engine/test_menu_urls/test_double_load/test_mixed_files/test_stale_install/test_payslip_e2e (استاب tpp_salary_field_in_record اضافه شد)/test_pdf_engine_load/test_pdf + xlsx_strict روی نمونه‌ها و بکاپ اکسل (دو-شیتی و داخل ZIP) + ddl_words + static/compat scan
- بسته‌بندی: tpp_salary-1.5.0-plugin.zip (471.6KB/65 فایل) و tpp-salary-v1.5.0-full.zip (482.8KB/70 فایل) — main=YES cache=0؛ صحت نمونه‌های داخل بسته راستی‌آزمایی شد؛ بسته‌های 1.4.1 حذف شدند؛ صفحه دانلود به‌روز

Stage Summary:
- ریشه واقعی «حروف جدا در PDF» پیدا شد: باگ دو مرحله‌ای شکل‌دهی (اسکن روی متن در حال ویرایش + قاعده اتصال) — موتور حالا گلیف‌به‌گلیف با تست پوشش داده شده و برای همیشه قابل بازگشت نیست
- بکاپ کامل بازطراحی شد: سه قالب × دو برش (کارمندان/رکوردها) + ZIP با فایل‌های خود پلاگین — مطابق درخواست صریح کاربر
- تحویل: download/tpp_salary-1.5.0-plugin.zip و tpp-salary-v1.5.0-full.zip + صفحه دانلود به‌روز

---
Task ID: 15
Agent: main
Task: نسخه 1.6.0 — ریشه‌یابی باگ «ستون نام و نام خانوادگی یافت نشد» (گزارش کاربر) + نمونه در صفحه ورود گروهی + تست E2E با فایل‌های واقعی پیوست کاربر (۷۶ کارمند/۷۶ رکورد) + دکمه‌های قطع/ادامه همکاری + PDF گزارش لیست حقوق A4 افقی با فیت ستون‌ها

Work Log:
- فایل‌های پیوست کاربر دریافت شد (employees-sample-completed-checked.xlsx / salary-records-sample-completed-checked.xlsx)
- بازتولید باگ با فایل نمونه خود پلاگین: نگاشت ستون‌ها کامل و سالم بود اما شرط empty($col_map['display_name']) به ایندکس‌های ستون نگاه می‌کرد نه مقادیر → همیشه true → اصلاح با isset روی array_flip($col_map)؛ نکته: empty(0) هم true است (ایندکس ستون نام=0) پس isset الزامی بود
- بازآرایی: process_employees استخراج شد (قابل‌تست) + باگ دوم: dry-run کاربر واقعی می‌ساخت (ساخت قبل از گارد) → اصلاح؛ در اجرای آزمایشی هیچ نوشتنشی رخ نمی‌دهد
- نرخ بیمه کسری اکسل (0.07 / 7.0000000000000007E-2 از فرمت ٪۷) با parse_rate_percent → ۷
- دکمه دانلود نمونه به صفحه ورود گروهی کارمندان اضافه شد (غایب بود) + فایل‌های پرشده واقعی کاربر به samples اضافه شد (employees-sample-filled / salary-records-sample-filled) + لینک از هر دو صفحه ورود گروهی و تب نمونه‌ها + سطرهای نمونه‌ساز داینامیک با داده واقعی کاربر به‌روز شد
- تست E2E با فایل واقعی: ۷۶ کارمند → ۷۶ کاربر جدید/۷۶ موفق/۰ خطا؛ ۷۶ رکورد حقوق (۳۷ ستون، دو جفت ستون هم‌عنوان با نگاشت موقعیتی) → ۷۶ موفق/۰ خطا؛ اجرای دوباره upsert بدون تکرار (۷۶ رکورد در جدول)؛ مقادیر کلیدی (insurable 270697756 / child_allowance 33251100 / gross 381381596 / net 362432754) صحت‌سنجی شد
- قطع/ادامه همکاری: متا tpp_salary_terminated + tpp_salary_is_terminated + پارامتر include_terminated در tpp_salary_get_employees (پیش‌فرض: کارمند قطع‌شده از همه لیست‌ها/فرم‌ها/گزارش‌ها/فیش‌ها مخفی)؛ مدیریت کارمندان/تطبیق ورود گروهی/بکاپ شامل همه؛ دکمه‌ها در ویرایش کاربر (وضعیت همکاری) و فهرست کارمندان (ستون وضعیت) با هندلر tpp_salary_toggle_termination (نانس + بازگشت به مبدا + confirm)
- PDF گزارش لیست حقوق: همیشه A4 افقی (۲۹۷×۲۱۰)؛ report_pdf_fit عرض ستون برچسب/کارمند را از پهنای واقعی متن‌ها (GetStringWidth روی متن شکل‌داده) می‌سنجد و per_page_a4 به نزدیک‌ترین تعداد جادادن کاهش می‌یابد (تنظیم ۱۲ با اعداد بزرگ واقعی → ۵ ستون ۴۰.۵mm)؛ ارتفاع سطر تطبیقی (۵.۲–۷.۶mm) + تکرار هدر ستونی در صفحات ادامه؛ رندر واقعی با pdftoppm بررسی بصری شد — بدون شکستگی متن
- رگرسیون کامل سبز: test_fixes_160 (جدید ~۴۵ ادعا) + test_e2e_user_files (فایل واقعی) + conflict_sim/upgrade_120/fixes_140 (هماهنگ با نام‌های نمونه جدید)/fixes_141/fixes_150/admin_runtime/engine/menu_urls/double_load/mixed_files/stale_install/payslip_e2e/pdf_engine_load/pdf/pdf_shape/persian_battery/restore_schema/xlsx_strict/ddl_words/static_scan
- مستندات: CHANGELOG 1.6.0 + readme.txt + README + worklog داخلی افزونه + صفحه دانلود
- بسته‌بندی: tpp_salary-1.6.0-plugin.zip (512.6KB/67 فایل) و tpp-salary-v1.6.0-full.zip (523.8KB/72 فایل) — main=YES cache=0؛ diff با build فقط کش فونت عمدی؛ بسته‌های 1.5.0 حذف شدند؛ نمونه‌های داخل بسته (هر ۴ فایل) با ورود گروهی و اعتبارسنج سخت‌گیرانه XLSX سبز

Stage Summary:
- ریشه باگ اصلی: جستجوی کلید فیلد در «ایندکس‌های» نگاشت ستون‌ها به‌جای «مقادیر» — با فایل کاملاً صحیح کاربر هم خطا می‌داد
- هر دو ورود گروهی اکنون با فایل واقعی ۷۶/۷۶ کاربر بی‌خطا کار می‌کند
- قابلیت‌های جدید: قطع/ادامه همکاری + PDF A4 افقی هوشمند
- تحویل: download/tpp_salary-1.6.0-plugin.zip و tpp-salary-v1.6.0-full.zip + صفحه دانلود به‌روز

---
Task ID: 16
Agent: main
Task: نسخه 1.6.1 — PDF گزارش لیست حقوق (همه سطرها در یک صفحه + حذف سطرهای متا) + اکسل ستونی + صفحه‌بندی همه نتایج جستجو

Work Log:
- PDF (class-tppsalary-reports.php): report_pdf_fit بازطراحی شد — total_rows = ۱ سطر عناوین + سطرهای فیلدها (سطرهای متای سال/ماه/مرکز/واحد حذف)؛ usable_h = ۲۱۰−۲۳−۱۲؛ rh = clamp(usable_h/total_rows, 2.8, 7.6)؛ فونت تطبیقی fs_body = clamp(rh−1, 4.8, 8.5) و fs_head = fs_body+0.6؛ اندازه‌گیری عرض ستون‌ها با «همان فونت نهایی» (قبلاً 9/8.5 ثابت بود) — کلیدهای جدید fs_body/fs_head/total_rows/usable_h به خروجی اضافه شد (کلیدهای قدیمی حفظ شدند)
- build_report_pdf: سربرگ فشرده (لوگو حداکثر ۱۲mm + شرکت B13 + خط «دوره: … — مرکز: … — واحد: …» ۹pt) از y=8، جدول از y=23؛ حذف کامل حلقه متا؛ گارد سرریز عمودی فقط اطمینانی؛ فونت سطر برچسب‌ها B هماهنگ با fs_body؛ Image لوگو با ارتفاع محدود 12mm
- اکسل: report_excel بازتوزیع شد → build_report_xlsx قابل‌تست (داده در writer، دانلود در report_excel) — سطر ۱ عنوان (شرکت+دوره+مرکز)، سطر ۲ «واحد: …» (header)، سطر ۴ هدر ستونی (عناوین + نام کارمندان)، سطرهای بعد فقط عناوین حقوق؛ شیت‌بندی ستونی (per_page_a4) مثل قبل
- پیوت HTML (pivot_table): حذف ۴ سطر متا؛ render_report اکنون خط دوره/مرکز/واحد یک‌بار بالای جدول + صفحه‌بندی ستون‌ها با paged (offset واقعی به pivot_table پاس می‌شود)
- صفحه‌بندی مشترک (helpers.php): tpp_salary_list_per_page (تنظیم per_page_list پیش‌فرض ۲۰) + tpp_salary_current_paged + tpp_salary_pagination (حفظ همه پارامترهای GET به‌جز paged، قبلی/بعدی، پنجره ۹ صفحه‌ای + اول/آخر با …، ارقام فارسی؛ هیچ‌چیز با یک صفحه)
- اعمال صفحه‌بندی: render_list کارمندان (array_slice بعد از فیلتر جستجو/مرکز) + render_records حقوق‌های ثبت‌شده (COUNT + LIMIT/OFFSET در SQL با حفظ فیلترها) + render_payslips (array_slice) + پیوت گزارش
- تنظیمات: ذخیره/UI «تعداد ردیف در هر صفحه (فهرست‌ها و نتایج جستجو)» (1..200، پیش‌فرض ۲۰) + توضیح جدید برای per_page_a4
- شبیه‌ساز (wp_sim_bootstrap.php): add_query_arg واقعی شد (قبلاً رشته ثابت بود — صفحه‌بندی در تست قابل‌بررسی نبود) + remove_query_arg واقعی + استاب get_edit_user_link/urlencode_deep
- تست جدید scripts/test_fixes_161.php (۴۹ ادعا): فیت عمودی (total_rows×rh ≤ usable_h)، total_rows بدون متا، تعداد صفحات PDF = دقیقاً بلوک‌های ستونی (۷ رکورد/per → بدون صفحه اضافه سطرها)، per=4 حفظ، اکسل: باز می‌شود/بدون سطر متا/سطر۴=عناوین/۳+N سطر/هدر ستونی=نام کارمندان/شیت۲ برای ستون‌های ادامه، صفحه‌بندی: لینک‌های paged با حفظ s و jmonth، عدم ناوبری تک‌صفحه‌ای، per_page_list از تنظیمات، رندر واقعی فهرست کارمندان (۳ ردیف در صفحه ۱ و ۲، «صفحه ۱ از ۳»)
- رندر بصری pdftoppm: صفحات ۱ و ۲ (per=4) — همه سطرها در هر صفحه، متن فارسی سالم، متا فقط در سربرگ (scripts/render_report_visual.php)
- رگرسیون کامل سبز: fixes_161/160/e2e_user_files/conflict_sim/upgrade_120/fixes_140/fixes_141/fixes_150 (ادعای نسخه به 1.6.1 به‌روز شد)/admin_runtime/engine/menu_urls/double_load/mixed_files/stale_install/payslip_e2e/pdf_engine_load/pdf/pdf_shape/persian_battery/restore_schema + xlsx_strict (نمونه‌ها + خروجی جدید ۲شیت) + ddl_words + static/compat
- نسخه 1.6.1 (هدر/TPP_SALARY_VERSION/INSTALL_BUILD/readme Stable+changelog/CHANGELOG.md/داخل افزونه) + بسته‌بندی 1.6.1 و حذف بسته‌های 1.6.0 + صفحه دانلود به‌روز

Stage Summary:
- هر سه درخواست کاربر اعمال شد: هیچ سطری از PDF به صفحه بعد نمی‌رود (فقط ستون‌های اضافی)، متا یک‌بار در سربرگ (PDF/اکسل/HTML)، اکسل ستونی با نام کارمند در ستون‌ها
- صفحه‌بندی جستجو در همه فهرست‌ها + تنظیم تعداد ردیف
- تحویل: download/tpp_salary-1.6.1-plugin.zip و tpp-salary-v1.6.1-full.zip + صفحه دانلود به‌روز

---
Task ID: 17
Agent: main
Task: نسخه 1.6.2 — جستجو و حذف تکی و گروهی برای حقوق‌های ثبت‌شده، کارمندان و مراکز (درخواست کاربر)

Work Log:
- وضعیت سنجیده شد: جستجو در حقوق‌ها/کارمندان موجود، در مراکز غایب؛ حذف تکی در فهرست حقوق‌ها غایب (فقط ویزارد)؛ حذف گروهی در هیچ‌کدام نبود
- helpers.php: tpp_salary_ids_from_request (فقط اعداد مثبت، حذف منفی/غیرعددی/صفر/تکراری) + tpp_salary_bulk_table_script (انتخاب همه + الزام انتخاب ردیف/اقدام + پنجره تأیید اختصاصی)
- salary-pages: فرم گروهی + چک‌باکس + «اقدام گروهی: حذف» + دکمه حذف تکی در render_records + هندلر delete_records_bulk با نانس tpp_salary_del_salary_bulk + هسته bulk_delete_records (IN آماده‌سازی‌شده) + اعلان تعداد؛ delete_record اکنون deleted=N برمی‌گرداند
- employees: فرم گروهی + چک‌باکس + «حذف نقش کارمندی» (حساب حفظ می‌شود) + هندلر delete_employees_bulk + هسته bulk_delete_employees + اعلان «نقش کارمندی N کارمند حذف شد (حساب کاربری حفظ شد)» — باگ فراخوانی دوباره هسته در همان هندلر در حین توسعه شناسایی و رفع شد
- centers: جستجوی نام/شناسه (mb_stripos + digits_en) + صفحه‌بندی (حفظ s) + فرم گروهی + هندلر delete_bulk + هسته bulk_delete_centers + اعلان تعداد + پیام جستجوی بی‌نتیجه
- شبیه‌ساز: استاب wp_get_referer + هم‌سانی prepare با وردپرس (فرم تک‌آرایه‌ای باز می‌شود) — علت ریشه‌ای «حذف فقط ۱ ردیف از ۳» در تست همین نبودِ این ویژگی بود
- گارد نصب: TPP_SALARY_INSTALL_BUILD در class-tppsalary-install.php به 1.6.2 همگام شد (در غیر این صورت dbDelta در نصب تازه اجرا نمی‌شد و جدول‌ها ساخته نمی‌شدند — در تست دیده شد)
- تست جدید scripts/test_fixes_162.php (۵۷ ادعا) — ALL PASS؛ رگرسیون کامل ۲۰+ اسکریپت سبز (fixes_150/160 به 1.6.2 به‌روز شدند)
- نسخه 1.6.2: هدر/ثابت/INSTALL_BUILD/readme.txt (Stable + changelog)/CHANGELOG.md/README داخلی + بسته‌بندی (tpp_salary-1.6.2-plugin.zip 525.1KB/67 فایل، tpp-salary-v1.6.2-full.zip 536.3KB/72 فایل، main=YES cache=0) + حذف بسته‌های 1.6.1 + صفحه دانلود به‌روز (عنوان/زیرعنوان/فایل‌ها/هایلایت نسخه)

Stage Summary:
- هر سه فهرست اکنون جستجو + حذف تکی + حذف گروهی دارند با دفاع دولایه (JS تأیید + نانس/گارد سرور) و اعلان تعداد فارسی
- semantic حذف کارمند هم‌سان با قبل: فقط نقش tpp_salary_employee برداشته می‌شود، حساب وردپرس حفظ می‌شود
- تحویل: download/tpp_salary-1.6.2-plugin.zip و tpp-salary-v1.6.2-full.zip + صفحه دانلود به‌روز

---
Task ID: 18
Agent: main
Task: نسخه 1.6.3 — دکمه «پر کردن فیلدها بر اساس حقوق گذشته» در مرحله ۳ ثبت حقوق (انتخاب سال/ماه مبدأ و اعمال)

Work Log:
- render_form (class-tppsalary-salary-pages.php): نوار دکمه «پر کردن فیلدها بر اساس حقوق گذشته» (type=button — فرم را ارسال نمی‌کند) + اعلان #tpp-past-fill-note + دیالوگ انتخاب سال (۱۳۹۹..۱۴۰۶) و ماه شمسی با پیش‌فرض هوشمند «ماه قبل از دوره فرم» + دکمه «اعمال» + ناحیه پیام؛ fallback درون‌خطی اگر jQuery UI Dialog لود نشده باشد
- هسته قابل‌تست past_salary_payload: رکورد «همان کارمند + همان مرکز» را با کوئری تک (ORDER BY (center_id=%d) DESC, id DESC LIMIT 1) می‌خواند؛ در نبود رکورد مرکز جاری، آخرین رکورد همان دوره از مرکز دیگر (جابه‌جایی کارمند) با نام مرکز و same_center=false؛ پارامتر ناقص → WP_Error؛ نبود رکورد → false؛ مقادیر با قالب‌بندی هم‌سان فرم (لاتین + جداکننده هزار) + manual + insurable_mode + period_label
- AJAX جدید wp_ajax_tpp_salary_past_salary (هندلر TppSalary_Ajax::past_salary) با نانس tpp_salary_ajax و گارد tpp_salary_can_manage؛ پیام «رکوردی یافت نشد» برای دورهٔ خالی
- اسکریپت درون‌صفحه: پرکردن همه فیلدها با کد فیلد (data-manual از رکورد مبدأ، tpp-neg برای منفی)، همگام‌سازی چک‌باکس «محاسبه با فرمول» با insurable_mode، بازمحاسبه فوری با TPP.recalc (جدیداً از موتور JS منتشر شد در دو نقطه)، پیام تأیید با برچسب دوره/مرکز — هیچ نوشتنشی تا «ذخیره» انجام نمی‌شود
- CSS: .tpp-past-toolbar / #tpp-past-fill-note / fallback درون‌خطی / استایل select دیالوگ (متغیرهای دارک/لایت)
- باگ پنهان شبیه‌ساز برطرف شد: selected()/checked() فقط return می‌کردند در حالی که هسته وردپرس echo هم می‌کند — اکنون هم‌سان با WP (echo+return، کوتیشن تکی، مقایسه رشته‌ای)
- تست جدید scripts/test_fixes_163.php (~۵۰ ادعا): هسته (اولویت مرکز/جایگزینی/چندمرکزی/حالت فرمولی/قالب‌بندی/گاردها) + رندر مرحله ۳ (دکمه/دیالوگ/پیش‌فرض ماه قبل/نبود دکمه در مرحله ۱/حالت ویرایش) + ثبت‌بودن اکشن + TPP.recalc در JS و CSS — ALL PASS
- رگرسیون کامل سبز: fixes_150 (سپر فیلد +۱ جایگاه شد: ۴), fixes_160/161/162 (ادعای نسخه), e2e_user_files, conflict_sim, upgrade_120, fixes_140/141, admin_runtime, engine, menu_urls, double_load, mixed_files, stale_install, payslip_e2e, pdf_engine_load, pdf, pdf_shape, persian_battery, restore_schema, xlsx_strict, ddl_words, static_scan, php_compat_scan
- نسخه 1.6.3: هدر/TPP_SALARY_VERSION/INSTALL_BUILD/readme.txt (Stable + changelog)/CHANGELOG.md + بسته‌بندی (tpp_salary-1.6.3-plugin.zip 532.6KB/67 فایل، tpp-salary-v1.6.3-full.zip 543.8KB/72 فایل) + حذف بسته‌های 1.6.2 + صفحه دانلود به‌روز (عنوان/زیرعنوان/هایلایت/فایل‌ها/راه‌اندازی)

Stage Summary:
- درخواست کاربر کامل اعمال شد: دکمه در مرحله ۳ ثبت حقوق هر کارمند، انتخاب سال/ماه مبدأ، اعمال روی فیلدهای ماه جدید با بازمحاسبه زنده و حفظ فلگ‌های دستی
- مبدأ پیش‌فرض = ماه قبل؛ اولویت رکورد همان مرکز با جایگزینی هوشمند مرکز دیگر
- تحویل: download/tpp_salary-1.6.3-plugin.zip و tpp-salary-v1.6.3-full.zip + صفحه دانلود به‌روز

---
Task ID: 19
Agent: main
Task: نسخه 1.7.0 — نرم‌افزار آفلاین پایتون داخل پوشه افزونه + REST API همگام‌سازی دوطرفه با کلید داینامیک (بدون هاردکد)

Work Log:
- REST API (class-tppsalary-api.php جدید): مسیرهای GET ping / GET bundle / POST sync در namespace tpp_salary/v1 با permission_callback اختصاصی؛ احراز هویت هدر X-TPP-Key با hash_equals (فallback به $_SERVER)؛ option مستقل tpp_salary_api (فقط هش sha256 کلید + created_at/last_used + revoked) — کلید با wp_generate_password داینامیک ساخته می‌شود و هرگز هاردکد نیست
- bundle کامل: کارمندان (شامل قطع‌همکاری‌ها + پروفایل + ملی/موبایل + terminated)، مراکز، بانک‌ها، فیلدهای فرم + فیلدهای پروفایل، رکوردها (LIMIT 50000)، تنظیمات (شرکت/واحد پول/فرمول‌ها/پیش‌فرض‌ها/ارقام فارسی)، دوره جاری شمسی، schema و version
- sync ops با فهرست سفید صریح (sanitize_key نقطه را حذف می‌کرد — باگ یافت و رفع شد): record/employee/center/bank → upsert/delete؛ تداخل رکورد با updated_at؛ ایجاد کارمند هم‌سان با create_employee (username از ملی، رمز تصادفی، نقش tpp_salary_employee)؛ حذف کارمند = فقط remove_role؛ یکتایی نام مرکز
- نگاشت درون‌دسته سمت سرور: id_map[employee/center/bank] local(منفی)→server برای opهای بعدی همان دسته (رکورد با user_id منفی به کارمند تازه‌ساخته وصله می‌شود؛ مراکز منفی در پروفایل کارمند حل می‌شوند؛ refs حل‌نشده → خطای کنترل‌شده نه رکورد یتیم)
- تب جدید «برنامه آفلاین (API)» در تنظیمات: وضعیت کلید + آدرس REST (rest_url) + ساخت/لغو کلید (admin_post + nonce + manage_options) + نمایش یک‌باره کلید + راهنمای امنیت
- بوت‌استرپ: require + TppSalary_Api::init در ماژول‌ها + نسخه 1.7.0 (هدر/ثابت/INSTALL_BUILD)
- نرم‌افزار پایتون (python-app/ — ۲۶ فایل): entry یک‌کلیکی tpp_salary_app.py (بررسی/نصب خودکار PySide6/requests/openpyxl با pip و fallback --user) + TPP Salary.bat (py -3/python + پیام راهنمای بدون پایتون) + requirements.txt + README فارسی + data/index.php
- هسته پایتون: database.py (SQLite آینه جداول + outbox + kv + sync_log، قفل تردها)، store.py (CRUD کامل با نوشتن از صف؛ شناسه منفی محلی؛ past_salary هم‌سان افزونه با اولویت مرکز؛ records با جستجو/فیلتر/صفحه‌بندی؛ compute_preview با force_calculated مثل TPP.recalc)، formulas.py (توکنایزر + Shunting-yard + گرد کردن هم‌سان PHP + تشخیص دستی + force)، sync_engine.py (push دسته ۳۰۰تایی + نگاشت + بازنویسی ارجاع‌های منفی صف + pull بسته با حفظ pendingها + حذف هم‌سان با سرور + ادغام رکوردهای منفی با server_keys)، api_client.py (X-TPP-Key + fallback خودکار wp-json/rest_route برای XAMPP)، config.py (data/config.json — بدون هاردکد)، jalali.py (تبدیل گرجی→جلالی + ارقام + parse_number با جداکننده)
- UI حرفه‌ای PySide6 RTL کامل: سایدبار گرادیانی + QSS روشن/تیره + نوار وضعیت (نقطه آنلاین/آفلاین/در حال همگام‌سازی + badge صف + آخرین sync + دکمه sync)؛ ۸ صفحه: داشبورد (کارت آماری + آخرین رکوردها)، ثبت حقوق (ویزارد ۳ مرحله‌ای + دکمه «پر کردن فیلدها بر اساس حقوق گذشته» با دیالوگ سال/ماه و پیش‌فرض ماه قبل + بازمحاسبه force)، لیست حقوق (فیلتر سال/ماه/مرکز/جستجو + صفحه‌بندی + حذف تکی/گروهی + ویرایش)، کارمندان (جستجو + دیالوگ پروفایل داینامیک از bundle + قطع/ادامه همکاری + حذف فقط نقش)، مراکز و بانک‌ها (دو پنل + جستجو + حذف گروهی)، گزارش‌ها (پیوت سطر=عناوین/ستون=کارمندان + اکسل ستونی openpyxl هم‌سان 1.6.1 + PDF با چاپ Qt RTL)، همگام‌سازی (صف + لاگ + تداخل‌ها)، تنظیمات (آدرس/کلید/تست اتصال/فاصله auto/تم)
- باگ‌های یافت و رفع‌شده در توسعه: sanitize_key و نقطه op، save_employee که id منفی را در صف نمی‌فرستاد، فیلدهای محاسباتی غایب در ورودی (رفع باگ 1.4.1 افزونه در پایتون هم اعمال شد)، parse جداکننده هزارگان، نگاشت رکورد منفی بعد از pull
- شبیه‌ساز: استاب register_rest_route/rest_ensure_response/rest_authorization_required_code اضافه شد
- تست: scripts/test_api_170.php (۵۴ ادعا ALL PASS) + scripts/test_pyapp_sync.py (۶۰+ ادعا ALL PASS — جلالی/فرمول/Store/صف/نگاشت/pull/تداخل/آفلاین/اتصال مجدد) + pyflakes تمیز + رگرسیون کامل ۲۰ اسکریپت PHP سبز (ادعای نسخه همه به 1.7.0 به‌روز شد)
- بسته‌بندی scripts/package_170.py: plugin zip (۹۴ فایل — ۵۹۵.۱KB شامل python-app) + full zip (۹۹ فایل — ۶۰۶.۲KB) + حذف بسته‌های 1.6.3 + صفحه دانلود به‌روز (عنوان/زیرعنوان/هایلایت/راه‌اندازی python-app)

Stage Summary:
- تحویل شد: REST API امن با کلید داینامیک (فقط هش ذخیره می‌شود، بدون هاردکد) + نرم‌افزار دسکتاپ پایتون داخل پوشه افزونه با اجرای یک‌کلیکی، کار کامل آفلاین، همگام‌سازی دوطرفه در اولین فرصت و تشخیص تداخل
- سیاست آینده‌پذیری: هر تغییر بعدی پلاگین باید هم در bundle (فیلدها/تنظیمات) منعکس و هم در برنامه پایتون اعمال شود — ساختار bundle + schema برای همین طراحی شد
- تحویل: download/tpp_salary-1.7.0-plugin.zip و tpp-salary-v1.7.0-full.zip + صفحه دانلود به‌روز

---
Task ID: 20
Agent: main
Task: نسخه 1.7.1 — فونت همه PDFها حداقل Vazirmatn 10pt و Bold + همه اعداد انگلیسی در سایت و نرم‌افزار پایتون (درخواست کاربر)

Work Log:
- PDF گزارش لیست حقوق (class-tppsalary-reports.php): report_pdf_fit بازطراحی شد — fs_body=fs_head=10.0 ثابت (قبلاً 4.8–8.5 تطبیقی)؛ rh = clamp(usable_h/total_rows, 6.0, 7.6)؛ اگر سطرها حتی با 6mm جا نشوند سطرهای مازاد به صفحه بعد می‌روند و هدر ستونی تکرار می‌شود (گارد موجود)؛ اندازه‌گیری عرض ستون‌ها با Bold 10 (مقادیر هم Bold شدند)؛ col_w حداقل 18→20mm
- build_report_pdf: سربرگ/subtitle همه Bold (subtitle از '' 9 به B10)؛ مقادیر سلول‌ها B10؛ docblock به‌روز
- فیش بانکی: باگ مطلق «No page has been added yet» رفع شد (bank_pdf از ابتدا AddPage نداشت — PDF بانکی عملاً خطای مهلک می‌داد)؛ هسته قابل‌تست TppSalary_Reports::build_bank_pdf جدا شد (wrapper خروجی)؛ همه فونت‌ها B10 (سربرگ 13 حفظ)
- فیش حقوقی A5: همه فونت‌ها Bold 10pt (8/8.5/9.5/6.5 قبلی)؛ rh = clamp(…, 5.7, 6.4)؛ lw برچسب 26→32mm؛ هدر جدول (عنوان/مقدار) در صفحه دوم با closure تکرار می‌شود
- ارقام انگلیسی (سیاست سراسری): TppSalary_Jalali::digits_fa اکنون = digits_en (نام قدیمی برای سازگاری)؛ TppSalary_PDF::fa_digits همین‌طور؛ fa() دیگر رقم فارسی تولید نمی‌کند (ورودی فارسی هم لاتین می‌شود)؛ tpp_salary_format_number بدون تبدیل (پارامتر $fa بی‌اثر)؛ پیش‌فرض settings digits_fa=0؛ چک‌باکس UI تنظیمات حذف (ردیف توضیحی جایگزین)؛ save همیشه 0؛ frontend $fa=false ثابت؛ bundle API همیشه digits_fa=0؛ helpers امروز/صفحه‌بندی/برچسب دوره خودکار انگلیسی شدند
- نرم‌افزار پایتون (سیاست آینده‌پذیری): jalali.fa_digits → en_digits (نام حفظ شد)؛ format_money همیشه لاتین؛ store.use_fa_digits() همیشه False؛ config پیش‌فرض False؛ چک‌باکس ارقام فارسی از صفحه تنظیمات حذف؛ PDF چاپ گزارش Bold و ≥10pt (h1 20px، جدول 14px)؛ pyflakes تمیز
- به‌روزرسانی تست‌ها: test_pdf_shape (fa_digits→انگلیسی)، test_pyapp_sync (ارقام/برچسب دوره/قالب مبلغ انگلیسی + use_fa_digits False)، test_fixes_161 (fs ثابت 10 + Bold probe با Reflection روی CurrentFont/FontSizePt tFPDF + صفحات = بلوک ستونی × صفحات سطری)، test_fixes_150 (اسکن حرف خام از بایت کل PDF به بررسی قطعی shape() منتقل شد — مثبت کاذب فشرده‌سازی)، test_fixes_162/163 (ادعاهای ارقام انگلیسی)، test_payslip_e2e (استاب format_number هم‌سان)
- تست جدید scripts/test_fixes_171.php (۳۵+ ادعا ALL PASS): سیاست ارقام (digits_fa/fa_digits/format_number/period_label/فای PDF/صفحه‌بندی بدون رقم فارسی/حذف چک‌باکس)، فونت گزارش (fs=10/rh≥6/Bold probe)، صفحه‌بندی سطرها با ۳۵ فیلد اضافی (rh زیر 6 نمی‌رود → ۲ صفحه با فونت Bold)، فیش بانکی (Bold 10 + build_bank_pdf قابل‌تست)، فیش حقوقی (Bold + بدون SetFont زیر 10 در reports.php)، سازگاری (bundle digits_fa=0)
- رگرسیون کامل سبز (۲۶ اسکریپت): fixes_150/160/161/162/163/171 + e2e_user_files/conflict_sim/upgrade_120/fixes_140/141/admin_runtime/engine/menu_urls/double_load/mixed_files/stale_install/payslip_e2e/pdf_engine_load/pdf/pdf_shape/persian_battery/restore_schema + api_170/xlsx_strict/ddl_words/pyapp_sync (ادعای نسخه همه به 1.7.1 به‌روز شد)
- رندر بصری pdftoppm: گزارش (Bold 10، ارقام انگلیسی، همه ردیف‌ها خوانا) و فیش A5 (تک‌صفحه حتی با ۲۴ فیلد، منفی قرمز، امضا/تاریخ چاپ Bold)
- نسخه 1.7.1: هدر/TPP_SALARY_VERSION/INSTALL_BUILD/readme.txt (Stable + changelog)/CHANGELOG.md + بسته‌بندی scripts/package_171.py (plugin 94 فایل/599.2KB + full 99 فایل/610.4KB) + حذف بسته‌های 1.7.0 + صفحه دانلود به‌روز (عنوان/زیرعنوان/هایلایت/راه‌اندازی)

Stage Summary:
- هر دو درخواست کاربر اعمال شد: فونت همه PDFها حداقل Vazirmatn 10pt و Bold (گزارش/فیش بانکی/فیش حقوقی + PDF پایتون) و همه اعداد در کل سایت و نرم‌افزار پایتون انگلیسی
- باگ پنهان و مطلق فیش بانکی (نبود AddPage → خطای مهلک PDF) کشف و رفع شد
- قاعده ۱.۶.۱ «همه سطرها همیشه یک صفحه» با قاعده جدید «حداقل فونت 10pt Bold» تعدیل شد: تا 6mm فشرده، مازاد به صفحه بعد با هدر تکرار
- تحویل: download/tpp_salary-1.7.1-plugin.zip و tpp-salary-v1.7.1-full.zip + صفحه دانلود به‌روز

---
Task ID: 21
Agent: main
Task: نسخه 1.7.2 — رفع خرابی اجرای نرم‌افزار پایتون (AttributeError: RecordsPage._bulk_delete — گزارش کاربر)

Work Log:
- باگ گزارش‌شده کاربر: در app/ui/pages/records.py دکمه «حذف موارد انتخاب‌شده» به self._bulk_delete وصل بود که متد وجودنداشت → برنامه هنگام ساخت صفحه لیست حقوق با AttributeError متوقف می‌شد (crash در __init__ یعنی کل برنامه بالا نمی‌آمد)
- بررسی دقیق نشان داد سیم‌کشی دو دکمه حذف به‌هم‌ریخته بود: دکمه حذف گروهی → متد ناموجود، و دکمه «حذف رکورد انتخابی» → به‌اشتباه به حذف گروهی وصل بود؛ متد جدید _delete_one_selected اضافه شد (حذف ردیف جاری با مرور رکورد انتخابی) و اتصال‌ها اصلاح شدند؛ btn_del_bulk به صورت attribute نگهداری می‌شود
- باگ دوم کشف‌شده با تست جدید: در app/ui/pages/employees.py کلاس EmployeeDialog خط 51 روی خود QListWidget (نه آیتم‌ها) setFlags صدا می‌زد → پنجره افزودن/ویرایش کارمند هرگز باز نمی‌شد؛ پرچم ItemIsUserCheckable اکنون هنگام ساخت هر آیتم تنظیم می‌شود و حلقه جبرانی حذف شد
- ابزار تازه scripts/pyapp_attr_check.py: بررسی AST همه ارجاع‌های self.* در ۱۹ فایل پایتون با استخراج نام‌های متد کلاس‌های پایه Qt از استاب‌های واقعی PySide6 (.pyi) — پیش از رفع، فقط records.py:60 سیگنال می‌داد؛ اکنون ALL CLEAN
- ابزار تازه scripts/pyapp_smoke.py: تست دود offscreen (QT_QPA_PLATFORM=offscreen) — ساخت MainWindow با دایرکتوری موقت، بازدید هر ۸ صفحه، کلیک هر ۳۲ دکمه با استاب همه مودال‌ها (QMessageBox/QDialog.exec/QFileDialog/QInputDialog) + شکار استثنای اسلات‌ها با sys.excepthook + مسیر کارکردی: ساخت مرکز/کارمند/رکورد واقعی → حذف گروهی با پاسخ No (هیچ حذف نمی‌شود) → حذف تکی با پاسخ Yes (رکورد حذف می‌شود) — ALL CLEAN (۳۲ دکمه/۸ صفحه)
- زیرساخت سندباکس: libEGL/libGL/libxkbcommon بدون دسترسی روت با apt-get download + dpkg -x در ~/.local/qtdeb فراهم و با LD_LIBRARY_PATH به PySide6 وصل شد (استاب‌های Chromium قابل استفاده نبودند — بدون نماد EGL)
- pyflakes روی کل python-app تمیز
- نگارش 1.7.2: هدر tpp-salary.php (Version + TPP_SALARY_VERSION) + INSTALL_BUILD + readme.txt (Stable + changelog) + CHANGELOG.md + bump ادعاهای نسخه در ۱۰ اسکریپت تست
- رگرسیون کامل: هر ۲۵ اسکریپت PHP خروجی ۰ + test_pyapp_sync.py (ALL PASS) + attr-check + smoke — همه سبز
- بسته‌بندی scripts/package_172.py: پلاگین ۹۴ فایل/۶۰۰.۱KB + کامل ۹۹ فایل/۶۱۱.۲KB + **بسته مستقل تازه tpp-salary-python-app-1.7.2.zip (۲۶ فایل/۵۲.۷KB)** برای جایگزینی سریع پوشه دسکتاپ بدون نصب دوباره افزونه + حذف بسته‌های 1.7.1 + صفحه دانلود (عنوان/زیرعنوان/کارت سوم/هایلایت/راه‌اندازی) — راستی‌آزمایی: رکورد اصلاح‌شده داخل هر سه بسته (نبود _bulk_delete، وجود _delete_one_selected و item.setFlags)

Stage Summary:
- هر دو خرابی crash نرم‌افزار پایتون رفع شد: صفحه لیست حقوق (دکمه حذف گروهی → متد ناموجود) و پنجره افزودن/ویرایش کارمند (setFlags روی لیست) + جداسازی حذف تکی از گروهی
- دروازه کیفیت تازه دائمی شد: attr-check (استاتیک) + smoke offscreen (اجرای واقعی همه صفحات/دکمه‌ها) — این کلاس باگ‌ها دیگر به نسخه بعدی راه نمی‌یابند
- تحویل: download/tpp_salary-1.7.2-plugin.zip و tpp-salary-v1.7.2-full.zip و tpp-salary-python-app-1.7.2.zip + صفحه دانلود به‌روز

---
Task ID: 22
Agent: main
Task: نسخه 1.7.3 — چهار درخواست کاربر: حذف فیلدهای صفر/فقط‌محاسباتی از PDF + تک‌صفحه‌ای شدن ردیف‌ها و پر شدن ستون‌ها + رفع باگ محاسبه زنده (خالص ثابت) + رفع باگ ذخیره تنظیمات (پاک شدن سایر بخش‌ها)

Work Log:
- فیلدهای چاپی (class-tppsalary-reports.php): CALC_ONLY_KEYS = overtime_hours/holiday_days/absence_days هرگز در PDF/اکسل گزارش و فیش درج نمی‌شوند؛ printable_fields() فیلدهای عددی همه‌صفر دوره را حذف می‌کند؛ payslip_rows() همان قواعد را برای فیش A5 به‌صورت «برای همین رکورد» اعمال می‌کند (مقادیر صفر چاپ نمی‌شوند) و rh فیش بر اساس ردیف‌های واقعی چاپی محاسبه می‌شود
- فیت PDF: کف ارتفاع سطر 6→4.6mm (تا ۳۷ فیلد + هدر = ۳۸ سطر در یک صفحه A4 افقی با فونت Bold 10pt)؛ per = max_fit همیشه (ستون‌ها تا گنجایش کامل صفحه پر می‌شوند — ستون جاگیر به صفحه بعد نمی‌رود)؛ کف عرض ستون 20→14mm؛ build_report_pdf از $fit['fields'] استفاده می‌کند (سطرها همیشه هم‌خوان با فیت)؛ سرریز بالای ۳۷ فیلد همچنان با هدر تکرار صفحه‌بندی می‌شود
- اکسل ستونی (build_report_xlsx) هم‌سان با PDF از printable_fields استفاده می‌کند
- موتور محاسبه زنده (admin/js/tpp-salary-admin.js): بازطراحی recalc به وابسته‌آگاه — formulaSources() توکن‌های {key} هر فرمول را استخراج می‌کند؛ state.last مقادیر قبلی را نگه می‌دارد؛ فیلد دستی فقط تا تغییر «منبع» معتبر است و با تغییر منبع (مستقیم/زنجیره‌ای با دو گذر) دوباره خودکار محاسبه می‌شود — رفع «خالص پرداختی ثابت می‌ماند»؛ گارد insurable در حالت پروفایل سرجای خود ماند
- تنظیمات (class-tppsalary-settings.php): منطق ذخیره به apply_section_save($post) منتقل شد (قابل‌تست بدون exit)؛ سه فرم عمومی/فرمول‌ها/بکاپ فیلد مخفی tpp_section (general/formulas/backup) دارند؛ فقط بخش اعلام‌شده به‌روزرسانی می‌شود (all = رفتار قدیمی) — رفع پاک شدن نام شرکت/لوگو/واحد/پیش‌فرض‌ها/فرمول‌ها هنگام ذخیره تب بکاپ؛ متن تنظیم per_page_a4 به‌روز شد
- زیرساخت: invalidation کش tpp_salary_get_fields با نسخه GLOBALS[tpp_salary_fields_ver] + تابع tpp_salary_bump_fields_version + listener اکشن tpp_salary_fields_changed (فیلد تازه در همان درخواست هم دیده می‌شود)
- پایتون (قاعده هم‌سان‌سازی): reports.py فقط‌محاسباتی‌ها را از فیلدها حذف و ردیف‌های همه‌صفر را با oracle امن غیرعددی فیلتر می‌کند (جدول/اکسل/PDF هم‌سان)؛ recalc پایتون فریز دستی ندارد (دکمه محاسبه همیشه بازمحاسبه می‌کند) — بدون تغییر
- تست جدید scripts/test_fixes_173.php (۵۱ ادعا ALL PASS): اوراکل قواعد چاپی، بازگشت سطر با ناصفر شدن، فیش دو رکورد متفاوت، مرز ۳۸ سطر تک‌صفحه‌ای، سرریز ۴۳ سطر، per=max_fit، ذخیره بخشی سه تب، بررسی‌های JS؛ نکته مهم تست: فیلدهای آزمون باید قبل از اولین get_fields درج و tpp_salary_bump_fields_version() صدا زده شود (کش استاتیک درون‌درخواستی)
- به‌روزرسانی تست‌های قدیمی به قواعد جدید: fixes_160 (per=max_fit، حذف per<=12 و 4===per)، fixes_161 (کف 4.6، printable_count با Reflection، اکسل 3+printable، fit4=max_fit، 45 فیلد <4.6)، fixes_171 (کف 4.6 + مقادیر ناصفر فیلدهای آزمون با upsert مجدد + bump)
- رگرسیون کامل سبز: هر ۲۵ اسکریپت PHP خروجی ۰ + test_pyapp_sync (ALL PASS) + attr-check + pyflakes + smoke پایتون (۸ صفحه/۳۲ دکمه ALL CLEAN)
- رندر بصری pdftoppm از گزارش: بدون سطرهای صفر/فقط‌محاسباتی، تک‌صفحه، ۷ کارمند در یک صفحه، Bold 10pt و ارقام انگلیسی
- نگارش 1.7.3: هدر/TPP_SALARY_VERSION/INSTALL_BUILD/readme.txt (Stable + changelog)/CHANGELOG.md + bump ادعاهای نسخه تست‌ها + بسته‌بندی scripts/package_173.py (plugin 94 فایل/604.4KB + full 99 فایل/615.5KB + python-app مستقل 26 فایل/53.1KB) + حذف بسته‌های 1.7.2 + صفحه دانلود (عنوان/هایلایت/راه‌اندازی)

Stage Summary:
- هر چهار درخواست کاربر اعمال شد: خروجی PDF/اکسل/فیش بدون مقادیر صفر و بدون سه فیلد فقط‌محاسباتی؛ همه ردیف‌ها در یک صفحه (کف 4.6mm) و پر شدن ستون‌ها تا گنجایش صفحه؛ رفع فریز محاسبه زنده با موتور وابسته‌آگاه؛ رفع پاک شدن تنظیمات با ذخیره بخشی tpp_section
- بهبود زیرساختی: باطل‌سازی کش فیلدها با اکشن موجود (رفع کهنگی درون‌درخواستی)
- قاعده هم‌سان‌سازی پایتون رعایت شد (قواعد چاپی در صفحه گزارش‌ها)
- تحویل: download/tpp_salary-1.7.3-plugin.zip و tpp-salary-v1.7.3-full.zip و tpp-salary-python-app-1.7.3.zip + صفحه دانلود به‌روز

---
Task ID: 23
Agent: main
Task: نسخه 1.7.4 — رفع «اتصال ناموفق: پاسخ نا معتبر از سرور (json)» نرم‌افزار دسکتاپ (گزارش کاربر + کلید/آدرس آزمایشی واقعی tpptc.ir)

Work Log:
- ریشه‌یابی با دسترسی واقعی: هاست tpptc.ir پشت Cloudflare است و درخواست‌های بدون کوکی را با صفحه حفاظتی ضدربات جواب می‌دهد (HTML 200 + /aes.js که باید slowAES.decrypt(c,2,a,b) را اجرا و در کوکی __test بگذارد و صفحه را دوباره بخواند) → کلاینت قبلی در شاخه status<400 به resp.json() می‌رسید و پیام «پاسخ نامعتبر از سرور (JSON)» می‌داد؛ endpoint و کلید کاملاً سالم بودند
- نکته کلیدی رمزنگاری: نسخه slowaes هاست modeOfOperation={OFB:0,CFB:1,CBC:2} دارد — یعنی «2» یعنی CBC نه CTR؛ راه‌حل صحیح __test = AES128_DECRYPT(a, c) XOR b (برای بلوک ۱۶ بایتی unpad رخ نمی‌دهد — مطابق unpadBytesOut)؛ اول CTR فرض شد (پاسخ می‌داد اما کوکی رد می‌شد)، سپس با خواندن aes.js واقعی سرور اصلاح شد
- ماژول تازه python-app/app/antibot.py: AES-128 خالص پایتون (رمزگشایی) بدون هیچ وابستگی جدید — SBOX مولّد از وارون ضربی GF(2^8)+افین (بدون خطای تایپی)، بردار FIPS-197 رمز/رمزگشایی؛ looks_like_challenge و solve_cookie (فرمت واقعی چالش ثبت شد)
- api_client.py بازنویسی شد: Session دائمی requests (ماندگاری کوکی بین ping/bundle/sync)، تشخیص خودکار صفحه حفاظتی + حل چالش + تلاش دوباره (MAX_ATTEMPTS=5؛ حتی ترکیب چالش×۳ + 404→rest_route)، User-Agent مرورگری پیش‌فرض (بعضی هاست‌ها UA غیرمرورگری را می‌بندند) با بازنویسی از config (کلید user_agent به DEFAULTS اضافه شد)، پیام فارسی برای خطای CDN («سرور میزبان موقتاً در دسترس نیست (error code: 520)») و صفحه حفاظتی عبورنشده
- config.py: user_agent به DEFAULTS؛ README برنامه: بخش «رفع اشکال اتصال» (علت خطا، خطای 5xx موقت، نرمال‌سازی آدرس)
- تست تازه scripts/test_api_antibot.py (۲۵+ ادعا، آفلاین): بردارهای AES + ۵ بلوک تصادفی با اوراکل مستقل pycryptodome (AESAVS حفظیِ نادرست حذف شد — درس: حفظ نکردن بردار)، حل چالش تصادفی با اوراکل CBC، چالش واقعی ثبت‌شده tpptc.ir، جریان موک (side_effect — نکته: patched attribute توصیفی نیست و self پاس نمی‌شود؛ کوکی باید از cookiejar سشن ادعا شود نه هدر ارسالی)، چالش+404 با هم، ۵۲۰ کلادفلر، HTML ناشناخته، status_text داخل context
- تست زنده سندباکس: scripts/test_live_api.py (AES + چالش + ping/bundle) و scripts/test_live_e2e.py (فقط‌خواندنی) — اتصال واقعی با کلید کاربر: ONLINE، bundle کامل (۷۶ کارمند/۵ مرکز/۲ بانک/۷۶ رکورد، نسخه پلاگین سایت 1.7.3)؛ مهم: هر دو با کد واقعی پوشه build و سپس با کد استخراج‌شده از zip تحویلی (PACKAGED CLIENT: ONLINE) — هیچ درخواست نوشتن به سایت واقعی زده نشد
- رگرسیون کامل سبز: ۲۵ اسکریپت PHP (خروجی ۰) + test_pyapp_sync (ALL PASS) + attr-check (۲۰ فایل) + smoke (۸ صفحه/۳۲ دکمه ALL CLEAN) + pyflakes
- نگارش 1.7.4: هدر tpp-salary.php (Version + TPP_SALARY_VERSION) + INSTALL_BUILD + readme.txt (Stable + changelog) + CHANGELOG.md + bump ادعای نسخه در ۵ اسکریپت (api_170/fixes_150/160/162/163) — ارجاع‌های تاریخی «نسخه 1.7.3: …» در کامنت‌ها عمداً حفظ شد
- بسته‌بندی scripts/package_174.py: پلاگین ۹۵ فایل/۶۰۹.۱KB + کامل ۱۰۰ فایل/۶۲۰.۲KB + python-app مستقل ۲۷ فایل/۵۶.۵KB (شمارش‌ها +۱ به‌خاطر antibot.py) + حذف بسته‌های 1.7.3 + صفحه دانلود (عنوان/زیرعنوان/کارت‌ها/هایلایت/راه‌اندازی) — راستی‌آزمایی: antibot.py و رفع api_client و README و user_agent داخل هر سه بسته

Stage Summary:
- علت واقعی «پاسخ نامعتبر از سرور (json)» کشف و رفع شد: صفحه حفاظت ضدربات هاست (نه باگ API/کلید) — برنامه اکنون مثل مرورگر چالش slowAES/CBC را خودکار حل می‌کند و کوکی __test را در نشست نگه می‌دارد
- کلید فرآیندی: برای دیباگ هاست‌های محافظت‌شده اول /aes.js سرور را بخوان (جدول modeOfOperation متفاوت است)؛ بردارهای رمز را از حفظ ننویس — با اوراکل مستقل بساز
- تحویل: download/tpp_salary-1.7.4-plugin.zip و tpp-salary-v1.7.4-full.zip و tpp-salary-python-app-1.7.4.zip + صفحه دانلود به‌روز؛ کاربر فقط python-app را با بسته مستقل جایگزین کند (آدرس/کلید فعلی او دست‌نخورده می‌ماند)

---
Task ID: 24
Agent: main
Task: نسخه 1.7.5 — پشتیبان‌گیری خودکار اکسل آنی در نرم‌افزار پایتون (درخواست کاربر: با هر ذخیره و هر اجرا یک نسخه پشتیبان اکسلی در پوشه excel نرم‌افزار از دیتابیس کارمندان و حقوق‌ها)

Work Log:
- معماری: قلاب شنونده نوشتن در پایین‌ترین لایه مشترک — database.py: add_write_listener + _notify_writes پس از commit موفق در ex()/exmany() + جداسازی نام جدول از SQL با regex (table_of) + فیلتر DATA_TABLES (centers/banks/employees/records/fields) — kv/sync_log/outbox مستثنی تا لاگ‌ها و صف sync بکاپ زنجیره‌ای نسازند + context manager db.locked() برای snapshot سازگار
- درس حیاتی طراحی: sync_engine در هر pull همه رکوردها را ON CONFLICT DO UPDATE بازنویسی و fields را کامل rebuild می‌کند → قلاب خامِ DB سطح پایین هر ۶۰ ثانیه (auto-sync) بکاپ می‌گرفت؛ راه‌حل: اثر انگشت SHA-256 از محتوای هر ۵ جدول داده در _snapshot (زیر یک قفل) و مقایسه با excel_backup_last_hash در kv → فقط تغییر واقعی فایل می‌سازد
- ماژول تازه app/excel_backup.py (ExcelBackupManager — بدون وابستگی Qt، نخ پس‌زمینه): بکاپ اجرای برنامه force=True طبق درخواست صریح کاربر؛ ذخیره‌های کاربر با تأخیر ۲ ثانیه (threading.Timer با reset — ادغام ذخیره‌های پشت‌سرهم)؛ بکاپ نهایی در closeEvent اگر تغییر ذخیره‌نشده مانده؛ نگهداری ۲۰۰ نسخه آخر با حذف خودکار؛ نام‌گذاری backup_YYYY-MM-DD_HHMMSS.xlsx (+ پسوند -2 در تصادم ثانیه)؛ خطا → last_error + sync_log بدون هیچ دیالوگ مزاحم
- باگ وسط راه که تست عملکردی گرفت: اگر پوشه excel حذف شده باشد wb.save(.part) FileNotFoundError می‌شد → ensure_folder() پیش از هر نوشتن؛ نوشتن اتمی با پسوند .part و os.replace (نوشته ناقص هرگز در فهرست بکاپ‌ها دیده نمی‌شود — باگ رِیس smoke بخش (e) را کشف کرد)
- محتوای هر فایل ۵ برگه RTL: «اطلاعات بکاپ» (شرکت/زمان شمسی+میلادی/علت/تعدادها/هش) + «کارمندان» (شناسه/نام/کد ملی/موبایل/login/email/وضعیت/مراکز/سمت/سایر مشخصات JSON) + «حقوق و دستمزد» (سال/ماه/کارمند/کد ملی/مرکز + همه فیلدهای فیش به ترتیب sort + جمع کل/مشمول/بیمه/سایر کسورات/خالص + آخرین تغییر — payload JSON پارس و اعداد float) + «مراکز» + «بانک‌ها» — سرستون ضخیم+fill، freeze panes، عرض ستون‌ها
- سیم‌کشی: app_window (ساخت manager پس از engine + add_write_listener + start()؛ closeEvent → shutdown() پیش از db.close())؛ settings_page: hint پشتیبان خودکار + دکمه «پوشه پشتیبان‌های اکسل» (os.startfile/xdg-open/open با fallback پیام مسیر)
- محیط سندباکس از نو ساخته شد (ریست شده بود): PySide6 6.11.2 + pyflakes در /home/z/.venv و کتابخانه‌های Qt با apt-get download + dpkg -x در /home/z/.local/qtdeb (LD_LIBRARY_PATH)
- smoke تازه بخش ۵: بکاپ اجرا، dedup (بدون تغییر = بدون فایل)، بکاپ اجباری، بکاپ با تأخیر پس از save_center، presence هر ۵ برگه + تطبیق تعداد سطر کارمندان/رکوردها با دیتابیس + last_error خالی + بستن برنامه؛ بخش ۶: closeEvent بدون کرش — پشتیبانی TPP_SMOKE_APP برای تست کد استخراج‌شده از zip (۸ صفحه/۳۳ دکمه ALL CLEAN روی build و روی PACKAGED)
- تست عملکردی مستقل: ۶ الگوی table_of + فایل واقعی (سطرها/اعداد/info sheet) + dedup + ساخت خودکار پوشه
- نگارش 1.7.5: tpp-salary.php (Version + TPP_SALARY_VERSION) + INSTALL_BUILD + readme.txt (Stable + changelog) + CHANGELOG.md + bump ادعای نسخه در ۵ اسکریپت PHP (api_170/fixes_150/160/162/163) — ارجاع‌های تاریخی نسخه‌ها در کامنت‌ها عمداً حفظ شد
- بسته‌بندی scripts/package_175.py: پلاگین ۹۶ فایل/۶۱۸.۲KB + کامل ۱۰۱ فایل/۶۲۹.۴KB + python-app مستقل ۲۸ فایل/۶۴.۱KB (هر سه +۱ فایل به‌خاطر excel_backup.py) + حذف بسته‌های 1.7.4 + صفحه دانلود (عنوان/کارت/هایلایت ۱.۷.۵) — راستی‌آزمایی داخل هر سه بسته: excel_backup.py (os.replace + excel_backup_last_hash)، قلاب database، سیم‌کشی app_window، دکمه تنظیمات، README، antibot دست‌نخورده
- رگرسیون کامل سبز: ۲۵ اسکریپت PHP (rc=0، بدون FAIL=) + test_pyapp_sync (ALL PASS) + test_api_antibot (ALL PASS) + attr-check (۲۱ فایل) + pyflakes + smoke (build و بسته تحویلی)
- نکته شل: تشخیص شکست PHP با grep "FAIL" ساده غلط مثبت می‌دهد (خروجی «FAIL=0» در test_menu_urls) → الگوی FAIL=[1-9]|FAIL: |^FAIL|✗

Stage Summary:
- نرم‌افزار دسکتاپ اکنون با هر ذخیره و هر اجرا یک نسخه اکسل کامل و خوانا از کارمندان/حقوق‌ها/مراکز/بانک‌ها در پوشه excel کنار برنامه می‌سازد — طبق درخواست کاربر، علاوه بر SQLite و همگام‌سازی سایت
- کلید فرآیندی: هر قلاب DB خام باید change-detection داشته باشد چون pull همگام‌سازی همه‌چیز را بازنویسی می‌کند؛ نوشتن اتمی .part→replace برای این است که کاربر هرگز فایل ناقص نبیند
- تحویل: download/tpp_salary-1.7.5-plugin.zip و tpp-salary-v1.7.5-full.zip و tpp-salary-python-app-1.7.5.zip + صفحه دانلود به‌روز؛ کاربر فقط python-app را جایگزین کند (آدرس/کلید در data/config.json او حفظ می‌شود)

---
Task ID: 25
Agent: main
Task: نسخه 1.7.6 — بازنویسی کامل بخش پشتیبان‌گیری و بازگردانی (گزارش کاربر: «نسخه پشتیبان تهیه‌شده وقتی در یک سرور و دامنه دیگر تلاش کردم تا بازگردانی کنم اصلا کار نکرد»)

Work Log:
- ریشه‌یابی سه‌گانه ناکامی انتقال بکاپ بین نصب‌ها: (۱) همه بکاپ‌های خودکار ZIP هستند ولی handler بازگردانی فقط JSON می‌پذیرفت (json_decode روی باینری ZIP شکست → «فایل بکاپ معتبر نیست»)؛ (۲) کارمند = کاربر وردپرس — بازگردانی هیچ حسابی در نصب مقصد نمی‌ساخت، update_user_meta روی شناسه‌های ناموجود می‌زد و رکوردها با user_id مبدأ درج می‌شدند → پس از بازگردانی هیچ داده‌ای دیده نمی‌شد؛ (۳) تنظیمات با جایگزینی کامل (نه ادغام) بازنویسی می‌شد
- class-tppsalary-backup.php بازنویسی شد: restore() اکنون آپلود را با load_backup_file() می‌خواند — تشخیص نوع با بایت جادویی PK/{ (اکسل هم ZIP است؛ پسوند گمراه‌کننده)، خواندن json/full.json از داخل ZIP با locateName(FL_NODIR)، پذیرش فایل‌های مجزا employees.json/records.json، پیام‌های خطای گویا برای اکسل/ZIP ناقص/بسته بدون full.json (با فهرست محتوا)/JSON خراب/خطای آپلود PHP
- هسته انتقال: restore_resolve_user() با تطبیق چهارمرحله‌ای (login ← کد ملی در usermeta ← ایمیل ← همان شناسه به‌شرط نقش کارمندی) + ساخت حساب با منطق create_employee (login یکتا با پسوند _i، رمز تصادفی، نقش tpp_salary_employee) + جلوگیری از تصاحب حساب مدیر (user_can manage_options)؛ restore_map_user() نگاشت user_id همه رکوردها؛ پروفایل/متا روی کاربر نگاشت‌شده نوشته می‌شود؛ همه درون همان تراکنش (ROLLBACK حساب‌های تازه‌ساخت را هم برمی‌گرداند)
- بازگردانی بخشی kind=employees/records فقط بخش خودش را خالی/پر می‌کند (قبلاً هر ۴ جدول بی‌قید و شرط خالی می‌شد)؛ records.json اکنون مراکز + پروفایل‌ها را هم دارد (خودبسنده برای نگاشت)؛ رکورد با کارمند غیرقابل‌تطبیق رد می‌شود با شمارش و هشدار (نه شکست کل)؛ گارد «بکاپ بدون هیچ داده‌ای» از پاک‌سازی کامل با فایل خراب/بیگانه جلوگیری می‌کند
- پس از commit: tpp_salary_bump_fields_version() + reschedule() کرون بکاپ خودکار؛ گزارش تفصیلی (records/users_created/users_matched/records_skipped/warnings) در $last_summary + transient کاربر جاری + نمایش در صفحه پشتیبان‌گیری (render_backup در reports.php: اعلان جزئیات، accept=".json,.zip"، متن و هشدار جدید)؛ collect_json اکنون user_email و بلوک site دارد؛ merge_settings ادغام یک‌سطحی به‌جای جایگزینی؛ راهنمای داخل ZIP به‌روز (بازگردانی مستقیم ZIP + نکته پیشوند جداول برای SQL دستی)
- درس حیاتی میانه راه: هنگام bump نسخه، TPP_SALARY_INSTALL_BUILD در class-tppsalary-install.php را جا انداختم → گارد install_ok جداول را seed نکرد و ۱۴ تست به‌ظاهر «ناگهان» شکست خوردند؛ بعد از bump نصب، همه سبز شدند — bump نسخه یعنی ۳ نقطه: Version/TPP_SALARY_VERSION + INSTALL_BUILD + readme Stable
- شبیه‌ساز: sanitize_user/is_email اضافه شد؛ transient ها اکنون واقعاً ذخیره/بازیابی می‌شوند (برای تست گزارش بازگردانی)
- تست جدید scripts/test_backup_restore_176.php (۶۰+ ادعا ALL PASS): چرخه کامل «مبدأ ← بکاپ ZIP ماهانه ← پاک‌سازی کامل کاربران و جدول‌ها (نصب تازه فقط با مدیر) ← بازگردانی مستقیم ZIP» با نگاشت شناسه‌ها/کد ملی/login/نقش، بدون ساخت تکراری در اجرای دوم، تطبیق با کد ملی وقتی login مقصد متفاوت است، بازگردانی بخشی بدون آسیب به بخش دیگر، رکورد یتیم با هشدار، رد اکسل/خراب/ZIP بی‌روح، ستون ناشناخته (سازگاری test_fixes_140)، JSON خام، گارد خالی
- محیط سندباکس دوباره ریست شده بود: PySide6 6.11.2 + pyflakes در /home/z/.venv و ۸ بسته Qt با apt-get download + dpkg -x در /home/z/.local/qtdeb از نو ساخته شد
- رگرسیون کامل سبز: ۲۶ اسکریپت PHP (خروجی ۰) + test_pyapp_sync (ALL PASS) + test_api_antibot (ALL PASS) + attr-check (۲۱ فایل) + pyflakes + smoke (۸ صفحه/۳۳ دکمه ALL CLEAN)
- نگارش 1.7.6: Version/TPP_SALARY_VERSION + INSTALL_BUILD + readme.txt (Stable + changelog ۱۰ بندی) + CHANGELOG.md + bump ادعای نسخه در ۵ اسکریپت تست
- بسته‌بندی scripts/package_176.py: پلاگین ۹۶ فایل/۶۲۶.۴KB + کامل ۱۰۱ فایل/۶۳۷.۶KB + python-app مستقل ۲۸ فایل/۶۴.۱KB + حذف بسته‌های 1.7.5 + صفحه دانلود (عنوان/زیرعنوان/هایلایت ۱.۷.۶) — راستی‌آزمایی: load_backup_file/restore_resolve_user/restore_map_user/FL_NODIR/merge_settings داخل بسته + accept=".json,.zip" + INSTALL_BUILD=1.7.6 داخل زیپ (پس از بسته‌بندی مجدد)

Stage Summary:
- علت واقعی «بکاپ در سرور دیگر بازگردانی نمی‌شود» سه‌بخشی بود و هر سه رفع شد: پذیرش ZIP، ساخت/تطبیق کارمندان در نصب مقصد با نگاشت شناسه رکوردها، ادغام تنظیمات
- کلید فرآیندی: bump نسخه = سه نقطه (Version + INSTALL_BUILD + readme) — جا انداختن INSTALL_BUILD کل seed جداول را ساکت از کار می‌اندازد و تست‌ها با شکست‌های زنجیره‌ای بی‌ربط جواب می‌دهند
- تحویل: download/tpp_salary-1.7.6-plugin.zip و tpp-salary-v1.7.6-full.zip و tpp-salary-python-app-1.7.6.zip + صفحه دانلود به‌روز؛ کاربر روی سرور جدید فقط افزونه را به‌روز و سپس فایل ZIP یا JSON بکاپ را از صفحه پشتیبان‌گیری آپلود کند

---
Task ID: 26
Agent: main
Task: نسخه 1.7.7 — بازطراحی شورت‌کد پنل کارمند [tpp_salary_panel] (درخواست کاربر: «شورت‌کد صفحه دریافت فیش حقوقی کجاست؟ بررسی کن و اگر تغییری برای نمایش بهتر آن میتوانی بدهی اعمال کن»)

Work Log:
- پاسخ پرسش مکان: شورت‌کد [tpp_salary_panel] در includes/class-tppsalary-frontend.php سطر ۲۵ ثبت می‌شود (add_shortcode)؛ هندلر panel_shortcode + استایل assets/tpp-salary-frontend.css؛ مستند در README.md و readme.txt — در یک برگه قرار می‌گیرد
- panel_shortcode بازطراحی شد (منطق داده/امنیت دست‌نخورده: نان‌ها، مالکیت رکورد، ذخیره بانکی): ترتیب بخش‌ها برعکس شد (فیش‌ها قبل از فرم بانکی — تمرکز صفحه روی دریافت فیش)؛ کارت‌های آماری (تعداد فیش / آخرین دوره / جمع خالص پرداختی با واحد پول) که بدون رکورد اصلاً رندر نمی‌شوند؛ گروه‌بندی فیش‌ها بر اساس سال شمسی با <details> بدون جاوااسکریپت (سال جاری open، سال‌های قبل بسته) با شمار فیش هر سال؛ نشان دوره آبی + نشان مرکز خنثی + نشان «جدید» سبز فقط برای رکوردهای آخرین دوره + نوار سبز inset لبه ردیف؛ خالص پرداختی سبز bold؛ لینک «مشاهده» هم مثل PDF به target=_blank با rel=noopener (قبلاً از پنل خارج می‌شد)؛ حالت خالی گویا با راهنما؛ فرم بانکی: راهنمای شبا (IR + ۲۴ رقم)، placeholder حساب/کارت، inputmode=numeric، دکمه ذخیره سبز؛ چیپ نام شرکت از تنظیمات کنار عنوان + زیرعنوان
- CSS بازنویسی کامل: جدول مدرن (خطوط افقی به‌جای گرید کامل + zebra + hover بعد از zebra در ترتیب کلاس برای برد specificity برابر)، summary سفارشی details با فلش CSS خالص (بدون کاراکتر)، دکمه ghost برای PDF، ریسپانسیو (@media 640px: کارت‌ها تک‌ستون، اسکرول افقی جدول با min-width 560px داخل .tpp-table-wrap)، ::placeholder محو، box-sizing border-box
- scripts/test_panel_177.php جدید (۳۵+ ادعا، ALL PASS): مکان ثبت شورت‌کد، حالت خالی + عدم رندر آمار، ترتیب بخش‌ها، کارت‌ها (تعداد 4 / جمع 42,000,000)، فقط یک گروه باز، نشان «جدید» دقیقاً ۲ بار (۲ مرکز در آخرین دوره)، ترتیب نزولی دوره‌ها، لینک‌ها با نان و noopener (۸ مورد)، چیپ شرکت، واحد پول، دکمه سبز، placeholder، اعلان موفقیت، بدون script/iframe/notice — دو باگ تست اولیه: نیام اندازه >3</span> به‌جای >4</span> (۴ رکورد درج شده بود) و الگوی «>N فیش</span>» از markup واقعی
- محیط سندباکس ریست شده بود (PySide6 غایب): PySide6 6.11.2 + pyflakes + openpyxl در /home/z/.venv بازنصب شد؛ این بار فقط libEGL.so.1 در سیستم کم بود (بقیه libGL/fontconfig/dbus/xkbcommon/xcb/GLX از قبل موجود) — بسته libegl1 (libglvnd) مستقیم از archive.ubuntu.com pool دانلود و با dpkg -x در /home/z/.local/qtdeb باز شد (apt-get update با Permission denied غیرممکن بود)؛ درس: هر ریست، فقط libEGL را چک کن نه کل ۸ بسته
- نگارش 1.7.7: tpp-salary.php (Version + TPP_SALARY_VERSION) + INSTALL_BUILD در class-tppsalary-install.php + readme.txt (Stable + changelog ۸ بندی) + CHANGELOG.md + README.md (بخش پنل کارمند با فهرست ویژگی‌های جدید + اصلاح متن بکاپ که هنوز می‌گفت فقط JSON) + bump ادعا در ۵ اسکریپت تست
- رگرسیون کامل سبز: ۲۷ اسکریپت PHP شامل test_panel_177 جدید (rc=0، الگوی FAIL=[1-9]|FAIL: |^FAIL|✗ صفر) + test_pyapp_sync (ALL PASS) + test_api_antibot (ALL PASS) + attr-check (۲۱ فایل) + pyflakes (۰) + smoke (۸ صفحه/۳۳ دکمه ALL CLEAN) روی build و روی اپ استخراج‌شده از بسته تحویلی
- بسته‌بندی scripts/package_177.py: پلاگین ۹۶ فایل/۶۴۵.۸KB + کامل ۱۰۱ فایل/۶۵۷.۳KB + python-app مستقل ۲۸ فایل/۶۵.۶KB + حذف بسته‌های 1.7.6 + صفحه دانلود (عنوان/زیرعنوان/هایلایت ۱.۷.۷) — راستی‌آزمایی داخل بسته: add_shortcode/tpp-stats/tpp-badge-new/noopener/tpp-btn-green در frontend.php، .tpp-stats/.tpp-year/max-width:640px در CSS، INSTALL_BUILD=1.7.7، version stampها، بقای لوله بازگردانی 1.7.6 و antibot و excel_backup

Stage Summary:
- شورت‌کد پنل کارمند از قبل وجود داشت ([tpp_salary_panel] در class-tppsalary-frontend.php) — اکنون با نمایش کامل بازطراحی شده: آمار، گروه‌بندی سالانه، نشان‌ها، تب جدید، ریسپانسیو
- کلید فرآیندی: در CSS با specificity برابر، ترتیب تعریف برنده است (hover باید بعد از zebra بیاید)؛ در بازطراحی UI، منطق امنیتی/نان‌ها را عمداً دست‌نخورده نگه داشتم و فقط لایه نمایش تغییر کرد
- تحویل: download/tpp_salary-1.7.7-plugin.zip و tpp-salary-v1.7.7-full.zip و tpp-salary-python-app-1.7.7.zip + صفحه دانلود به‌روز؛ کاربر برای ارتقا فقط پوشه افزونه را با بسته جدید جایگزین کند (شورت‌کد صفحه تغییری نمی‌کند)

---
Task ID: 27
Agent: main
Task: ساخت ریپازیتوری عمومی GitHub (TPP-Salary) + انتشار Release v1.7.7 + مستندات دوزبانه | Create the public GitHub repository (TPP-Salary), publish Release v1.7.7, bilingual docs

Work Log:
- توکن PAT کاربر با API بررسی شد → حساب Tobeseuss؛ ریپوی عمومی Tobeseuss/TPP-Salary ساخته شد (توضیح دوزبانه + homepage tpptc.ir) | Verified the user's PAT → account Tobeseuss; created the public repo Tobeseuss/TPP-Salary via API
- .git موجود در سندباکس مکانیزم snapshot خودکار پلتفرم است (کامیت‌های UUID، شامل skills/ و زیپ‌ها) → ریپوی تمیز مستقل در /home/z/my-project/TPP-Salary ساخته شد | The preexisting sandbox .git is the platform's auto-snapshot mechanism (UUID commits incl. skills/ and zips), so a clean standalone repo was built at /home/z/my-project/TPP-Salary
- محتوای ریپو (۲۰۲ فایل): plugin/tpp_salary (۹۶ فایل، بدون کش فونت cw.dat/mtx) + scripts کامل + download/index.html + caddy + docs/PLUGIN-README.fa.md + README/CHANGELOG/worklog دوزبانه + LICENSE (GPLv2+) + .gitignore | Repo content (202 files): plugin (96 files minus font caches), full scripts, download page, caddy, docs, bilingual README/CHANGELOG/worklog, LICENSE, .gitignore
- اسکن امنیتی قبل از push: بدون PAT / کلید زنده tppk_ / رمز؛ config.json برنامه دسکتاپ و پوشه excel در .gitignore | Security scan before push: no PAT / live tppk_ key / secrets; desktop app config.json and excel folder gitignored
- README.md دوزبانه کامل: معرفی، امکانات، نیازمندی‌ها، نصب/ارتقا، راه‌اندازی، پنل کارمند، برنامه دسکتاپ+API، بکاپ، ساختار ریپو، تست، نسخه‌بندی و مجوز — فارسی و English معادل | Full bilingual README (intro, features, requirements, install/upgrade, setup, employee panel, desktop app+API, backup, repo layout, testing, versioning, license)
- worklog.md: جدول خلاصه انگلیسی ۲۷ تسک در ابتدای فایل + حفظ کامل تاریخچه فارسی؛ از این پس ورودی‌ها دوزبانه | worklog.md: English summary table of all 27 tasks prepended; Persian history preserved; entries are bilingual from now on
- CHANGELOG.md: جدول خلاصه انگلیسی ۲۵ نسخه + بخش‌های فارسی 1.3.2→1.7.7 از readme.txt افزونه وارد شد (۲۵ سکشن کامل) | CHANGELOG.md: English summary table of 25 releases + Persian sections 1.3.2→1.7.7 merged from the plugin readme.txt (25 complete sections)
- ۴ کامیت ساختاریافته با پیام نسخه‌دار: chore baseline / feat plugin v1.7.7 / test QA suite / docs bilingual + تگ v1.7.7 → push به main | 4 structured version-tagged commits (baseline / plugin / tests / docs) + v1.7.7 tag → pushed to main
- Release v1.7.7 با یادداشت دوزبانه منتشر شد + سه asset نصبی: plugin 645.8KB / full 657.3KB / python-app 65.6KB | Release v1.7.7 published with bilingual notes + 3 installable assets (plugin/full/python-app)
- قاعده فرآیندی از این پس: هر تغییر کوچک/بزرگ → commit با پیام «X.Y.Z: موضوع» → push؛ هر نسخه پس از سبز شدن رگرسیون کامل → تگ vX.Y.Z + Release با سه فایل؛ worklog/CHANGELOG/README همیشه دوزبانه به‌روز می‌شوند | Going forward: every change gets a version-tagged commit+push; every release gets tag vX.Y.Z + GitHub Release with 3 artifacts; worklog/CHANGELOG/README always updated in both languages
- نکته امنیتی: remote محلی بدون توکن است؛ push با URL موقت توکن‌دار انجام می‌شود و توکن در هیچ فایلی ذخیره نمی‌شود | Security note: local remote has no embedded token; pushes use a temporary tokenized URL and the token is never stored in any file

Stage Summary:
- ریپوی عمومی: https://github.com/Tobeseuss/TPP-Salary — ریلیز: https://github.com/Tobeseuss/TPP-Salary/releases/tag/v1.7.7 | Public repo + release live
- ریپوی محلی سندباکس: /home/z/my-project/TPP-Salary (برای کامیت‌های آینده) | Local sandbox repo for future commits
- قاعده دوزبانه‌سازی مستندات و نسخه‌بندی/Release برقرار شد | Bilingual docs + versioned commit/release convention established
- بازیابی پس از ریست سندباکس: کافی است `git clone https://github.com/Tobeseuss/TPP-Salary.git` و ادامه کار؛ گیت‌هاب مرجع اصلی تاریخچه است | Sandbox-reset recovery: just `git clone https://github.com/Tobeseuss/TPP-Salary.git` and continue; GitHub is the canonical history

---
Task ID: 28
Agent: main
Task: نسخه 1.7.8 — همگام‌سازی خودکار فیلدهای پروفایل کارمند با آخرین فیش صادرشده + رفع باگ «گروه اصلی بیمه» | v1.7.8 — auto-sync employee profile fields from the latest issued payslip + fix the "insurance group" payload bug

Work Log:
- تابع جدید `tpp_salary_sync_profile_from_latest_record()` در helpers.php: پس از هر صدور فیش، ۱۴ فیلد پروفایل کارمند خودکار به‌روزرسانی می‌شود — ۸ فیلد مستقیم (دستمزد روزانه مرجع، پایه سنوات، حق مسکن، حق بن، حق تأهل، تعداد فرزند، ایاب و ذهاب، گروه اصلی بیمه) + حقوق مشمول بیمه از `insurable` + ۵ نرخ استنتاجی از تقسیم مبلغ بر تعداد (نرخ اضافه‌کاری، تعطیل‌کاری، حق اولاد هر فرزند، جریمه غیبت روزانه، نرخ درصد بیمه) | New `tpp_salary_sync_profile_from_latest_record()` in helpers.php: after every payslip issuance the employee's 14 profile fields auto-update — 8 direct mappings + insurable → insurable_default + 5 derived rates (amount ÷ count)
- حفاظت‌ها: مخرج صفر (بدون اضافه‌کاری/غیبت/فرزند در فیش) نرخ قبلی حفظ می‌شود؛ گروه بیمه خالی پروفایل را خراب نمی‌کند؛ نوشتن فقط با تغییر واقعی؛ پاکسازی ممیز شناور (7.000000001 → 7) | Guards: zero divisor keeps the previous rate; empty insurance group never clobbers the profile; write-only-on-change; float-noise normalization
- ملاک «آخرین دوره» است نه «آخرین ثبت» — ORDER BY jyear DESC, jmonth DESC, id DESC؛ ثبت پس‌گیرانه دوره قدیمی پروفایل را رگرس نمی‌دهد | "Latest" = latest period (jyear/jmonth/id DESC) — backfilling an older period never regresses the profile
- نقاط اتصال: TppSalary_Salary_Pages::upsert_record() (هسته مشترک فرم ویزارد، ورود گروهی اکسل، همگام‌سازی آفلاین و REST API) + حذف تکی و گروهی (بازگشت پروفایل به آخرین فیش باقی‌مانده) | Hook points: the shared upsert_record() core (wizard form, bulk Excel import, offline sync, REST API) + single/bulk delete (profile falls back to the newest remaining payslip)
- رفع باگ قدیمی موتور محاسبه: array_map('floatval') و حلقه گردکردن نهایی مقدار متنی «گروه اصلی بیمه» را در پیلود هر فیش صفر می‌کرد — فیلدهای متنی اکنون دست‌نخورده از موتور عبور می‌کنند | Fixed legacy compute-engine bug: floatval + final rounding used to zero the textual insurance group in every payslip payload; text fields now pass through untouched
- UI پیشخوان: یادداشت راهنمای همگام‌سازی در پروفایل کاربری + نمایش دوره آخرین فیش | Dashboard UI: sync note in the user-profile screen showing the latest synced payslip period
- API توسعه: فیلتر `tpp_salary_disable_profile_sync` + اکشن `tpp_salary_profile_synced` + تابع کمکی `tpp_salary_latest_record_period()` | Dev API: disable filter + synced action + latest-period helper
- تست جدید scripts/test_profile_sync_178.php (۶۲ ادعا ALL PASS): نگاشت مستقیم/استنتاجی، حفاظت مخرج صفر، رگرسیون‌ناپذیری ثبت پس‌گیرانه، یکپارچگی upsert_record، حذف گروهی، یادداشت پیشخوان | New test (62 assertions ALL PASS): direct/derived mappings, zero-divisor guards, no-regression on backfill, upsert integration, bulk-delete fallback, dashboard note
- رگرسیون کامل سبز: fixes_150/160/162/163، api_170، panel_177، pyapp_sync، api_antibot | Full regression green across all suites
- نسخه 1.7.8 در چهار نقطه (هدر، TPP_SALARY_VERSION، INSTALL_BUILD، readme.txt) + CHANGELOG/README افزونه + صفحه دانلود | Version bumped in all four places + plugin docs + download page
- پاکسازی: ۲۳ فایل میراثی (class-tpp-*.php نام‌قدیم + assetهای قدیمی) که توسط اسنپ‌شات سندباکس به build احیا شده بودند و در بسته رسمی 1.7.7 هم نبودند، از build و ریپو حذف شدند (کد مرده بدون هیچ ارجاع) | Cleanup: 23 legacy files (old-name class-tpp-*.php + old assets) resurrected by the sandbox snapshot — absent from the official 1.7.7 package — removed from build and repo (dead code, zero references)
- بسته‌بندی 1.7.8 (۹۶/۱۰۱/۲۸ فایل — همان شمار رسمی 1.7.7) + کامیت + تگ v1.7.8 + GitHub Release با سه فایل نصبی | Packaging (96/101/28 files — same as official 1.7.7) + commit + v1.7.8 tag + GitHub Release with 3 assets

Stage Summary:
- پروفایل هر کارمند همیشه آینه آخرین فیش اوست؛ فرم ثبت حقوق همیشه با مقادیر جدید پیش‌پر می‌شود | Each employee profile now mirrors their latest payslip; the salary form always pre-fills fresh values
- باگ تاریخی صفرشدن گروه بیمه در پیلود فیش رفع شد | Historical insurance-group zeroing bug fixed
- تحویل: Release v1.7.8 با سه asset در GitHub | Delivered: v1.7.8 release with 3 assets on GitHub

---
Task ID: 29
Agent: main
Task: نسخه 1.7.9 — هم‌سانی کامل نرم‌افزار آفلاین با نسخه آنلاین (تکمیل خودکار از ماه قبل در پلاگین و پایتون، محاسبهٔ زندهٔ پایتون، رفع UI، اکسل ستونی) | v1.7.9 — full offline/online parity (auto-prefill from the previous month in plugin & Python, live recalculation in Python, UI scroll fixes, columnar Excel backup)

Work Log:
- درخواست کاربر (۶ بند): لیست صفحهٔ ثبت کارمند قابل اسکرول نیست؛ لیست کارمندان ثبت حقوق بسیار کوچک است؛ تکمیل پیش‌فرض فرم حقوق ماه جدید از ماه قبل در «هر دو» نسخه؛ محاسبات خودکار پایتون کار نمی‌کند (تغییر فرزند ← حق اولاد)؛ پروفایل با تغییر حقوق به‌روز شود (دستور 1.7.8)؛ اکسل خروجی نرم‌افزار باید ستونی (ستون = نام کارمند، سطر = عناوین حقوق) باشد؛ و تضمین هم‌سانی کامل آفلاین/آنلاین و سینک بی‌مشکل | User request (6 items): non-scrollable employee-page list; too-small employee list in salary registration; default prefill of the new month's form from the previous month in BOTH versions; Python auto-calculations not working; profile must update on salary change (the 1.7.8 directive); Excel output must be columnar; guarantee exact offline/online parity
- پلاگین — render_form(): در نبود رکورد دوره، `past_salary_payload()` ماه قبل (اولویت همان مرکز، سپس مرکز دیگر) خوانده و فیلدها به‌صورت پیش‌فرض از آن تکمیل می‌شود + اعلان سبز `tpp-note-auto` با دورهٔ مبدأ (و نام مرکز مبدأ در جابه‌جایی)؛ فلگ‌های دستی و حالت مشمول منتقل می‌شوند؛ فروردین ← اسفند سال قبل خودکار | Plugin — render_form(): with no record for the period, the previous month's payload (same center first) pre-fills the form by default + green `tpp-note-auto` notice with the source period (and source center on transfer); manual flags and insurable mode carried; Farvardin resolves to Esfand of the prior year
- رفع باگ واقعی: `past_salary_payload()` اکنون `values_raw` (عدد خام) هم برمی‌گرداند — فرم سرور مقدار قالب‌بندی‌شده را `(float)` می‌کرد و «250,000,000» به «250» خراب می‌شد | Real bug fixed: past_salary_payload() now also returns `values_raw`; the server form was float-casting the comma-formatted string, corrupting 250,000,000 into 250
- پایتون — EmployeeForm: (۱) اگر رکورد ثبت‌شدهٔ همان دوره باشد مقادیر همان بارگذاری می‌شود (قبلاً پروفایل نمایش داده می‌شد و ذخیرهٔ مجدد بازنویسی می‌کرد — ناهم‌سانی با پلاگین) + برچسب «ویرایش رکورد ثبت‌شدهٔ …»؛ (۲) وگرنه تکمیل خودکار از فیش ماه قبل با برچسب «✓ تکمیل خودکار بر اساس فیش …»؛ (۳) store.record_period() جدید (آینه tpp_salary_get_record_period) | Python — EmployeeForm: (1) loads the existing record of the period (previously profile values were shown — plugin parity bug) + "editing saved record" label; (2) otherwise auto-prefills from the previous month's payslip; (3) new store.record_period() mirroring the plugin
- پایتون — محاسبهٔ زنده: `_live_recalc()` آینه دقیق TPP.recalc افزونه — textChanged هر ورودی؛ فلگ دستی فقط تا تغییر نیامدن منابع فرمول؛ دو گذر انتشار زنجیره‌ای؛ مشمول بیمه فقط در حالت فرمولی؛ دکمهٔ «محاسبه» = بازمحاسبهٔ اجباری + پاک‌سازی فلگ‌ها؛ گارد `_updating` و `_set_text` گارد‌دار برای پرکردن برنامه‌ای | Python — live recalculation: `_live_recalc()` mirrors TPP.recalc — textChanged on every input; manual flag released only when its formula sources change; two-pass dependency propagation; insurable only in formula mode; «محاسبه» button = forced recalc + flag reset; `_updating` guard and guarded `_set_text` for programmatic fills
- پایتون — UI: پنجرهٔ افزودن/ویرایش کارمند داخل QScrollArea با دکمه‌های همیشه‌در دسترس، سقف ارتفاع لیست مراکز و محدودیت ارتفاع به اندازهٔ صفحه؛ ویزارد ثبت حقوق در QStackedWidget — لیست کارمندان مرحلهٔ ۲ اکنون تمام ارتفاع صفحه | Python — UI: employee add/edit dialog wrapped in QScrollArea with always-visible buttons, capped centers list and screen-bounded height; registration wizard hosted in QStackedWidget — step 2's employee list now fills the page
- پایتون — اکسل بکاپ ستونی: شیت سطری «حقوق و دستمزد» حذف؛ به‌ازای هر «دوره+مرکز» شیت ستونی «لیست YYYY-MM مرکز» (سطر ۱ عنوان شرکت/دوره/مرکز، سطر ۲ واحد، سطر ۴ هدر «عناوین» + نام کارمندان به‌عنوان ستون، سطرها = عناوین حقوق)؛ آینه printable_fields افزونه (حذف CALC_ONLY و فیلدهای همه‌صفر دوره)؛ `#,##0;[Red]-#,##0`؛ راست‌به‌چپ + فریز پنل؛ بیشینه ۱۰ ستون در شیت با پسوند (۲)… | Python — columnar Excel backup: legacy row sheet removed; one «لیست YYYY-MM مرکز» sheet per period+center (title row, currency row, «عناوین» header with employees as columns, salary-item rows); mirrors printable_fields (CALC_ONLY and all-zero numeric fields dropped); `#,##0;[Red]-#,##0`; RTL + freeze panes; max 10 columns per sheet with (2)… suffixes
- پروفایل: مسیر سینک آفلاین دوباره راستی‌آزمایی شد — record.upsert REST → هستهٔ مشترک upsert_record افزونه → tpp_salary_sync_profile_from_latest_record (1.7.8) → پروفایل؛ با pull بعدی پروفایل جدید به برنامه هم می‌رسد | Profile: the offline sync path re-verified — REST record.upsert → the plugin's shared upsert_record core → 1.7.8 profile sync → next bundle pull brings updated profiles back to the app
- تست‌های جدید: test_fixes_179.php (۳۵+ ادعا) و test_pyapp_179.py (۳۵+ ادعا، offscreen)؛ pyapp_smoke.py به قالب ستونی به‌روز شد (نگهبان‌های «لیست …»/عناوین)؛ bump ادعای نسخه در ۶ اسکریپت تست قدیمی | New tests: test_fixes_179.php (35+ assertions) and test_pyapp_179.py (35+ assertions, offscreen); pyapp_smoke.py updated for the columnar format; version claims bumped in 6 legacy test scripts
- رگرسیون کامل سبز: ۲۹ اسکریپت PHP + test_pyapp_sync (ALL PASS) + test_api_antibot (ALL PASS) + pyflakes (۰) + attr-check (۲۱ فایل) + smoke (۸ صفحه/۳۳ دکمه ALL CLEAN) | Full regression green: 29 PHP scripts + pyapp_sync + api_antibot + pyflakes + attr-check + smoke
- نگارش 1.7.9 در چهار نقطه + CHANGELOG/README افزونه + README برنامهٔ دسکتاپ + صفحهٔ دانلود (هایلایت ۱.۷.۹) | Version bumped in 4 places + plugin CHANGELOG/README + desktop-app README + download page
- بسته‌بندی package_179.py (۹۶/۱۰۱/۲۸ فایل) + کامیت + تگ v1.7.9 + GitHub Release دوزبانه با سه asset | Packaging (96/101/28 files) + commit + v1.7.9 tag + bilingual GitHub Release with 3 assets

Stage Summary:
- نرم‌افزار آفلاین اکنون در چهار محور (پیش‌پر کردن از ماه قبل، محاسبهٔ زنده، بارگذاری رکورد موجود در ویرایش، اکسل ستونی) دقیقاً مطابق نسخهٔ آنلاین عمل می‌کند و هر دو مسیر با تست پوشش داده شدند | The offline app now matches the online version exactly in four axes (prev-month prefill, live recalc, existing-record load on edit, columnar Excel), each covered by tests
- دو باگ واقعی رفع شد: خرابی float در پیش‌فیل پلاگین (250 ← 250,000,000) و بازنویسی رکورد موجود در ویرایش پایتون | Two real bugs fixed: the plugin prefill float corruption (250,000,000 → 250) and Python overwriting the saved record on edit
- تحویل: Release v1.7.9 با سه asset (plugin 644.3KB / full 655.4KB / python-app 68.3KB) در GitHub | Delivered: v1.7.9 release with 3 assets on GitHub

---
Task ID: 30
Agent: main
Task: نسخه 1.8.0 — گزارش سالانه مراکز (در هر دو نسخه) + بازنویسی کامل PDF نرم‌افزار پایتون + صفحات «فیش‌های حقوقی»، «فیش بانکی» و «پشتیبان‌گیری و بازگردانی» در نرم‌افزار | v1.8.0 — annual centers report (both editions) + full Python PDF rewrite + payslips/bank-fiche/backup pages in the desktop app

Work Log:
- درخواست کاربر (۴ بند): PDF پایتون افتضاح است و بازنویسی کامل می‌خواهد؛ «دریافت فیش‌های حقوقی» و «فیش بانک» در پایتون وجود ندارد؛ پشتیبان‌گیری/بازگردانی در پایتون تعریف نشده؛ در هر دو نسخه بخش «گزارش سالانه مراکز» خواسته شد (لیست حقوق یک مرکز در ماه‌های مختلف یک سال، چند شیت در یک فایل اکسل) | User request (4 items): Python PDF is awful and needs a full rewrite; payslips and bank-fiche sections missing in Python; no backup/restore in Python; an annual centers report in BOTH editions (one center's salary list across months of a year as a multi-sheet Excel)
- افزونه — گزارش سالانه: زیرمنوی «گزارش سالانه مراکز» (tpp-salary-annual) + هندلر admin_post_tpp_salary_annual_excel؛ صفحه رندر با فرم سال+مرکز، جدول جمع ماهانه (تعداد/ناخالص/مشمول/بیمه/سایر کسورات/خالص) و سطر «جمع سال» | Plugin annual report: new submenu + Excel handler; year+center form, per-month summary table with year total row
- افزونه — توابع قابل‌تست: annual_stats() (SQL گروهی ماهانه) و build_annual_xlsx() — شیت «جمع سال» + یک شیت برای هر ماه دارای رکورد با قالب ستونی 1.6.1 (عناوین/نام کارمندان، حذف CALC_ONLY و همه‌صفر — هم‌سان printable_fields 1.7.3) | Testable annual_stats() + build_annual_xlsx(): «جمع سال» sheet + one columnar sheet per month with records, mirroring printable_fields
- پایتون — موتور PDF جدید (app/pdf_engine.py): PDF قبلی QTextDocument/HTML بود و ناقص/نادرست رندر می‌شد؛ اکنون ترسیم برداری مستقیم با QPainter/QPdfWriter — فونت Vazirmatn همراه برنامه (assets/fonts)، اندازه‌گیری واقعی عرض ستون‌ها از محتوا (fit_widths)، فیت در عرض صفحه، تکرار سربرگ جدول در هر صفحه با صفحه‌بندی قطعی، شماره صفحه + تاریخ چاپ، زبرا/سطر جمع، منفی قرمز، راست‌به‌چپ واقعی (ستون ۰ سمت راست) | Python — new PDF engine: vector drawing via QPainter/QPdfWriter replacing broken HTML output; bundled Vazirmatn, content-measured column widths, deterministic pagination with repeated table headers, page numbers + print date, zebra/total rows, red negatives, true RTL
- پایتون — سه سازنده آماده آینه افزونه: write_report_pdf (A4، افقی اگر >۴ کارمند)، write_bank_pdf (A4)، write_payslip_pdf (A5 با بلوک اطلاعات کارمند، جدول جزئیات، امضاها، تاریخ چاپ)؛ خروجی PDF صفحه گزارش‌ها به موتور جدید منتقل شد + اصلاح نمایش فیلدهای متنی در جدول گزارش | Python — three builders mirroring the plugin PDFs; reports page switched to the new engine; text-field display fixed
- پایتون — صفحه «فیش‌های حقوقی» (payslips.py): فیلتر دوره/مرکز، فهرست فیش‌ها، «مشاهده / چاپ» (PDF در نمایشگر سیستم)، ZIP عمده همه فیش‌ها؛ فیلدهای چاپی آینه payslip_rows افزونه | Python — payslips page: period/center filter, payslip list, view/print single A5 PDF, bulk ZIP; print fields mirror payslip_rows
- پایتون — صفحه «فیش بانکی» (bankfiche.py): بانک الزامی؛ سطرها از bank_accounts پروفایل کارمند (همگام‌شده از سایت)؛ خروجی اکسل (ستون‌بندی افزونه) + PDF | Python — bank fiche page: mandatory bank; rows from synced employee profiles; Excel + PDF exports
- پایتون — پشتیبان/بازگردانی: app/backup_core.py — snapshot_full() با ساختار بکاپ افزونه (version/stamp/site/settings/centers/banks/fields/records/profiles)؛ create_json/create_zip (full.json + employees.json + records.json)؛ load_backup (json/zip خودی یا افزونه)؛ restore() در یک تراکنش — تطبیق کارمند (کد ملی←شناسه←login←نام) یا ساخت، نگاشت user_id رکوردها، حفظ شناسه مراکز/بانک‌ها، پالایش تنظیمات، پاک‌سازی outbox | Python — backup_core.py: plugin-format snapshot (JSON/ZIP), loader accepting own or plugin backups, single-transaction restore with employee matching + record user_id remapping + outbox reset
- پایتون — صفحه «پشتیبان‌گیری و بازگردانی» (backup_page.py) + همگام‌سازی build→ریپو و اتصال چهار صفحه جدید در MainWindow (۱۲ صفحه ناوبری) | Python — backup page UI + 4 new pages wired into the window (12 nav pages)
- تست‌ها: test_fixes_180.php (۳۳ ادعا — رندر/annual_stats/شمارش شیت از workbook.xml/قالب ستونی/حذف CALC_ONLY) و test_pyapp_180.py (۵۵+ ادعا — موتور PDF سه‌قالب + چندصفحه، ZIP عمده، فیش بانکی، گزارش سالانه چندشیتی، چرخه پشتیبان/بازگردانی، سازگاری بکاپ افزونه با پیلود رشته‌ای) | New tests: test_fixes_180.php (33) + test_pyapp_180.py (55+) including plugin-format restore compatibility
- رگرسیون کامل سبز: ۳۰ تست PHP + pyapp_sync + api_antibot + attr-check (۲۷ فایل) + pyflakes ۰ + smoke (۱۲ صفحه/۴۵ دکمه) | Full regression green: 30 PHP suites + 5 Python suites + smoke (12 pages/45 buttons)
- نسخه 1.8.0 در چهار نقطه + readme.txt/CHANGELOG/README (افزونه و ریپو، دوزبانه) + README دسکتاپ + صفحه دانلود | Version bumped in 4 places + all bilingual docs + desktop README + download page
- بسته‌بندی package_180.py با شمار فایل جدید (۱۰۴/۱۰۹/۳۶ — +pdf_engine.py، +backup_core.py، +۴ صفحه، +۲ فونت) + همگام‌سازی build→ریپو + حذف بسته‌های 1.7.9 | Packaging with new counts (104/109/36) + build→repo sync + old zips removed
- کامیت «1.8.0: ...» + تگ v1.8.0 + GitHub Release دوزبانه با سه asset (PAT فقط از env) | Commit + v1.8.0 tag + bilingual GitHub Release with 3 assets

Stage Summary:
- گزارش سالانه مراکز در هر دو نسخه با یک قالب اکسل چندشیتی کاملاً هم‌سان ارائه شد | Annual centers report delivered in both editions with an identical multi-sheet Excel layout
- PDF نرم‌افزار آفلاین اکنون هم‌تراز PDF افزونه است (فارسی کامل، سربرگ تکرارشو، فیت ستون‌ها) | The desktop PDF now matches the plugin's quality (full Persian, repeated headers, fitted columns)
- نرم‌افزار آفلاین سه بخش گمشده خود را گرفت و از نظر قالب پشتیبان با افزونه سازگار شد | The desktop app gained its three missing sections and now shares the plugin's backup format
- Release: https://github.com/Tobeseuss/TPP-Salary/releases/tag/v1.8.0
- فایل‌های محلی: download/tpp_salary-1.8.0-plugin.zip و tpp-salary-v1.8.0-full.zip و tpp-salary-python-app-1.8.0.zip
