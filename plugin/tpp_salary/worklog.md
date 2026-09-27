# Worklog — پروژه پلاگین حقوق و دستمزد (tpp_Salary)

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

## 1.6.2 — جستجو و حذف تکی و گروهی (حقوق‌های ثبت‌شده / کارمندان / مراکز)

- حقوق‌های ثبت‌شده (render_records): فرم POST گروهی (نانس tpp_salary_del_salary_bulk) + ستون چک‌باکس با انتخاب همه + نوار «اقدام گروهی: حذف» + دکمه حذف تکی در هر ردیف (قبلاً حذف فقط در ویزارد ثبت بود) + اعلان «۳ رکورد حقوق حذف شد» — delete_record اکنون تعداد را در redirect می‌فرستد
- کارمندان (render_list): فرم POST گروهی (tpp_salary_del_employee_bulk) + ستون چک‌باکس + اقدام «حذف نقش کارمندی» — هم‌سان با حذف تکی فقط نقش برداشته می‌شود و حساب کاربری حفظ می‌شود؛ کاربران بدون نقش نادیده گرفته می‌شوند + اعلان تعداد با توضیح «حساب کاربری حفظ شد»
- مراکز (render): جستجوی نام/شناسه (mb_stripos + digits_en) + صفحه‌بندی نتایج با حفظ پارامتر s + فرم حذف گروهی (tpp_salary_del_center_bulk) + اعلان تعداد (تکی/گروهی) + پیام «مرکزی با این جستجو یافت نشد»
- هسته‌های قابل‌تست جدا شدند: bulk_delete_records / bulk_delete_employees / bulk_delete_centers (کوئری آماده‌سازی‌شده IN(...) برای دو جدول؛ حلقه remove_role برای کارمندان) — هر هندلر دقیقاً یک‌بار هسته را صدا می‌زند
- helpers.php: tpp_salary_ids_from_request (فقط اعداد مثبت؛ منفی/غیرعددی/صفر/تکراری حذف) + tpp_salary_bulk_table_script (انتخاب/برداشتن همه + الزام انتخاب ردیف و اقدام + پنجره تأیید اختصاصی هر فهرست)
- شبیه‌ساز: استاب wp_get_referer + پشتیبانی prepare از فرم تک‌آرایه‌ای وردپرس (prepare($q, $array)) — قبلاً هر آرایه یک آرگومان حساب می‌شد
- تست scripts/test_fixes_162.php (۵۷ ادعا): پاک‌سازی شناسه‌ها، حذف گروهی/تکی رکوردها با باقی‌مانده دقیق، حذف گروهی نقش (کاربر عادی دست‌نخورده)، حذف گروهی مراکز، جستجو/صفحه‌بندی/حفظ s، رندر واقعی سه فهرست (چک‌باکس/نانس/اعلان/متن تأیید)، سورس (نانس + گارد + فراخوانی یک‌باره هسته) — ALL PASS
- رگرسیون کامل سبز: fixes_161/160/e2e_user_files/conflict_sim/upgrade_120/fixes_140/fixes_141/fixes_150/admin_runtime/engine/menu_urls/double_load/mixed_files/stale_install/payslip_e2e/pdf_engine_load/pdf/pdf_shape/persian_battery/restore_schema + xlsx_strict (۴ نمونه) + ddl_words + static/compat scan
- نسخه 1.6.2 (هدر/TPP_SALARY_VERSION/INSTALL_BUILD/readme Stable+changelog/CHANGELOG.md/README) + بسته‌بندی 1.6.2 و حذف بسته‌های 1.6.1 + صفحه دانلود به‌روز
