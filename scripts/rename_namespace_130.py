# -*- coding: utf-8 -*-
"""
تغییر نام کامل namespace پلاگین برای رفع تداخل با افزونه‌های دیگرِ tpp_
- کلاس‌ها: TPP_X  -> TppSalary_X
- توابع/جدول‌ها/آپشن‌ها/هوک‌ها/نانس‌ها/متا: tpp_salary_x -> tpp_salary_x (به‌جز مواردی که از قبل tpp_salary* هستند)
- اسلاگ‌ها/هندل‌های dash: tpp-xxx -> tpp-salary-xxx
- فایل‌ها: class-tppsalary-*.php -> class-tppsalary-*.php + فایل‌های CSS/JS
پردازش: build/tpp_salary (به‌جز lib/) + scripts/*.php + scripts/*.py

⚠️ one-shot: پس از اجرای موفق دیگر اجرا نکنید (sentinel پایین فایل).
"""
import os, re, sys

if os.path.exists('/home/z/my-project/.rename_130_done'):
    sys.exit('rename_130 already applied — one-shot script, do not re-run.')

BASE = '/home/z/my-project'
PLUGIN = os.path.join(BASE, 'build', 'tpp_salary')
SCRIPTS = os.path.join(BASE, 'scripts')

# ---------- قوانین ترتیب‌دار (literal) ----------
LITERAL_RULES = [
    # محافظت از نام پوشه بکاپ (تغییر نمی‌کند)
    ('tpp-backups', 'tpp-backups'),
    # نقش‌ها (حروف بزرگ - قبل از قوانین دیگر)
    ('tpp_salary_employee', 'tpp_salary_employee'),
    ('tpp_salary_accountant', 'tpp_salary_accountant'),
    # دسترسی‌ها
    ('tpp_salary_view', 'tpp_salary_view'),
    ('tpp_salary_manage', 'tpp_salary_manage'),
    ('tpp_salary_import', 'tpp_salary_import'),
    # کلاس‌ها (طولانی‌تر اول)
    ('TppSalary_Salary_Pages', 'TppSalary_Salary_Pages'),
    ('TppSalary_Xlsx_Writer', 'TppSalary_Xlsx_Writer'),
    ('TppSalary_Xlsx_Reader', 'TppSalary_Xlsx_Reader'),
    ('TppSalary_Settings', 'TppSalary_Settings'),
    ('TppSalary_Samples', 'TppSalary_Samples'),
    ('TppSalary_Reports', 'TppSalary_Reports'),
    ('TppSalary_Offline', 'TppSalary_Offline'),
    ('TppSalary_Install', 'TppSalary_Install'),
    ('TppSalary_Import', 'TppSalary_Import'),
    ('TppSalary_Frontend', 'TppSalary_Frontend'),
    ('TppSalary_Employees', 'TppSalary_Employees'),
    ('TppSalary_Centers', 'TppSalary_Centers'),
    ('TppSalary_Banks', 'TppSalary_Banks'),
    ('TppSalary_Backup', 'TppSalary_Backup'),
    ('TppSalary_Ajax', 'TppSalary_Ajax'),
    ('TppSalary_Jalali', 'TppSalary_Jalali'),
    ('TppSalary_Formula', 'TppSalary_Formula'),
    ('TppSalary_PDF', 'TppSalary_PDF'),
    # ثابت نشان فایل نصب
    ('TPP_SALARY_INSTALL_BUILD', 'TPP_SALARY_INSTALL_BUILD'),
    # آبجکت‌های سراسری JS
    ('TPPSALARY_OFFLINE', 'TPPSALARY_OFFLINE'),
    ('TPPSALARY_SW_CACHE', 'TPPSALARY_SW_CACHE'),
    ('TPPSALARY_ASSETS', 'TPPSALARY_ASSETS'),
    # اسلاگ‌های dash منو
    ('tpp-salary-bank-report', 'tpp-salary-bank-report'),
    ('tpp-salary-report', 'tpp-salary-report'),
    ('tpp-salary-payslips', 'tpp-salary-payslips'),
    ('tpp-salary-backup', 'tpp-salary-backup'),
    ('tpp-salary-offline', 'tpp-salary-offline'),
    ('tpp-salary-settings', 'tpp-salary-settings'),
    ('tpp-salary-import', 'tpp-salary-import'),
    ('tpp-salary-employees', 'tpp-salary-employees'),
    ('tpp-salary-centers', 'tpp-salary-centers'),
    ('tpp-salary-banks', 'tpp-salary-banks'),
    ('tpp-salary-admin', 'tpp-salary-admin'),
    ('tpp-salary-frontend', 'tpp-salary-frontend'),
    # نام فایل‌های کلاس
    ('class-tppsalary-', 'class-tppsalary-'),
    # داک‌بلاک
    ('@package TppSalary', '@package TppSalary'),
]

JS_RULES = [
    (re.compile(r'\bTPP\.'), 'TPPSALARY.'),
    (re.compile(r'\btypeof TPP\b(?!S)'), 'typeof TPPSALARY'),
]

# قانون خودکار: tpp_ + حرف کوچک، اما نه وقتی از قبل salary است
AUTO = re.compile(r'tpp_(?!salary)(?=[a-z0-9_])')

PHP_EXT = ('.php',)
def iter_plugin_files():
    for root, dirs, files in os.walk(PLUGIN):
        if os.path.join('lib') + '' in root.replace(PLUGIN, ''):
            pass
        # حذف lib از پیمایش
        rel = os.path.relpath(root, PLUGIN)
        if rel == 'lib' or rel.startswith('lib' + os.sep):
            continue
        for f in files:
            yield os.path.join(root, f)

def iter_script_files():
    for f in sorted(os.listdir(SCRIPTS)):
        if f.endswith(('.php', '.py')):
            yield os.path.join(SCRIPTS, f)

def transform(content, is_js=False):
    for old, new in LITERAL_RULES:
        content = content.replace(old, new)
    if is_js:
        for rx, new in JS_RULES:
            content = rx.sub(new, content)
    content = AUTO.sub('tpp_salary_', content)
    content = content.replace('tpp-backups', 'tpp-backups')
    return content

changed = 0
for path in list(iter_plugin_files()) + list(iter_script_files()):
    ext = os.path.splitext(path)[1].lower()
    if ext not in ('.php', '.js', '.css', '.py'):
        continue
    with open(path, 'r', encoding='utf-8', errors='replace') as fh:
        src = fh.read()
    out = transform(src, is_js=(ext == '.js'))
    if out != src:
        with open(path, 'w', encoding='utf-8') as fh:
            fh.write(out)
        changed += 1
        print('CHG', os.path.relpath(path, BASE))

# ---------- تغییر نام فایل‌ها ----------
RENAMES = []
inc = os.path.join(PLUGIN, 'includes')
for f in sorted(os.listdir(inc)):
    if f.startswith('class-tppsalary-'):
        RENAMES.append((os.path.join(inc, f), os.path.join(inc, 'class-tppsalary-' + f[len('class-tppsalary-'):])))
for rel in ('admin/css/tpp-salary-admin.css', 'admin/js/tpp-salary-admin.js', 'admin/js/tpp-salary-offline.js', 'assets/tpp-salary-frontend.css'):
    src = os.path.join(PLUGIN, rel)
    dst = os.path.join(PLUGIN, rel.replace('tpp-', 'tpp-salary-'))
    RENAMES.append((src, dst))

for src, dst in RENAMES:
    if os.path.exists(src):
        os.rename(src, dst)
        print('REN', os.path.relpath(src, BASE), '->', os.path.basename(dst))

print('DONE changed=%d renamed=%d' % (changed, len(RENAMES)))
