#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""بسته‌بندی نسخه 1.7.7 — بازطراحی شورت‌کد پنل کارمند [tpp_salary_panel]
- پلاگین: build/tpp_salary به‌جز کش فونت و __pycache__ (شامل python-app)
- کامل: پلاگین + CHANGELOG.md + README.md + caddy در ریشه
- بسته مستقل python-app برای جایگزینی سریع پوشه دسکتاپ کاربر
- حذف بسته‌های نسخه قبلی + به‌روزرسانی صفحه دانلود + راستی‌آزمایی
"""
import os, re, zipfile

ROOT = '/home/z/my-project'
BUILD = os.path.join(ROOT, 'build', 'tpp_salary')
DL = os.path.join(ROOT, 'download')
VER = '1.7.7'
PREV = '1.7.6'

cache_names = {'tpp_salary/lib/tfpdf/font/unifont/vazirmatn-bold.cw.dat',
               'tpp_salary/lib/tfpdf/font/unifont/vazirmatn-bold.cw127.php',
               'tpp_salary/lib/tfpdf/font/unifont/vazirmatn-bold.mtx.php',
               'tpp_salary/lib/tfpdf/font/unifont/vazirmatn-regular.cw.dat',
               'tpp_salary/lib/tfpdf/font/unifont/vazirmatn-regular.cw127.php',
               'tpp_salary/lib/tfpdf/font/unifont/vazirmatn-regular.mtx.php'}


def collect_plugin():
    files = []
    for root, dirs, fnames in os.walk(BUILD):
        dirs[:] = [d for d in dirs if d != '__pycache__']
        for f in sorted(fnames):
            p = os.path.join(root, f)
            rel = 'tpp_salary/' + os.path.relpath(p, BUILD).replace(os.sep, '/')
            if rel in cache_names:
                continue
            files.append((p, rel))
    return files


def zipify(files, out_path, strip=0):
    with zipfile.ZipFile(out_path, 'w', zipfile.ZIP_DEFLATED) as z:
        for p, rel in files:
            if strip:
                rel = '/'.join(rel.split('/')[strip:])
            z.write(p, rel)
    return out_path


plugin_files = collect_plugin()
print('plugin files:', len(plugin_files))
assert len(plugin_files) == 96, 'plugin file count drift: %d' % len(plugin_files)
assert any(rel.endswith('python-app/tpp_salary_app.py') for _p, rel in plugin_files), 'python-app missing!'
assert any(rel.endswith('python-app/app/antibot.py') for _p, rel in plugin_files), 'antibot.py missing!'
assert any(rel.endswith('python-app/app/excel_backup.py') for _p, rel in plugin_files), 'excel_backup.py missing!'
assert not any('__pycache__' in rel for _p, rel in plugin_files), 'pycache leaked into zip!'

main_zip = zipify(plugin_files, os.path.join(DL, 'tpp_salary-%s-plugin.zip' % VER))

full_files = list(plugin_files)
for extra in ['CHANGELOG.md', 'README.md']:
    full_files.append((os.path.join(ROOT, 'build', extra), extra))
for root, dirs, fnames in os.walk(os.path.join(ROOT, 'build', 'caddy')):
    for f in sorted(fnames):
        p = os.path.join(root, f)
        rel = 'caddy/' + os.path.relpath(p, os.path.join(ROOT, 'build', 'caddy')).replace(os.sep, '/')
        full_files.append((p, rel))

full_zip = zipify(full_files, os.path.join(DL, 'tpp-salary-v%s-full.zip' % VER))
print('full files:', len(full_files))
assert len(full_files) == 101, 'full file count drift: %d' % len(full_files)

# --- بسته مستقل python-app ---
py_files = [(p, rel) for p, rel in plugin_files if rel.startswith('tpp_salary/python-app/')]
py_zip = zipify(py_files, os.path.join(DL, 'tpp-salary-python-app-%s.zip' % VER), strip=2)
print('python-app files:', len(py_files))
assert len(py_files) == 28, 'python-app file count drift: %d' % len(py_files)

# --- حذف بسته‌های نسخه قبلی ---
removed = []
for old in ['tpp_salary-%s-plugin.zip' % PREV, 'tpp-salary-v%s-full.zip' % PREV,
            'tpp-salary-python-app-%s.zip' % PREV]:
    p = os.path.join(DL, old)
    if os.path.exists(p):
        os.remove(p)
        removed.append(old)
print('removed old packages:', removed)

# --- صفحه دانلود ---
page = os.path.join(DL, 'index.html')
html = open(page, encoding='utf-8').read()
html = html.replace('tpp_Salary v%s' % PREV, 'tpp_Salary v%s' % VER)
html = html.replace('نسخه ۱.۷.۶ — بازنویسی پشتیبان‌گیری/بازگردانی: انتقال بکاپ به سرور و دامنه دیگر اکنون کار می‌کند',
                    'نسخه ۱.۷.۷ — بازطراحی پنل کارمند [tpp_salary_panel]: کارت آماری، گروه‌بندی سالانه فیش‌ها و نمایش بهتر')
html = html.replace('tpp-salary-v%s-full.zip' % PREV, 'tpp-salary-v%s-full.zip' % VER)
html = html.replace('tpp_salary-%s-plugin.zip' % PREV, 'tpp_salary-%s-plugin.zip' % VER)
html = html.replace('tpp-salary-python-app-%s.zip' % PREV, 'tpp-salary-python-app-%s.zip' % VER)

py_card_pat = re.compile(r'(<a class="btn" href="tpp-salary-python-app-1\.7\.7\.zip" download>دانلود نرم‌افزار</a>\s*</div>)', re.S)
assert py_card_pat.search(html), 'python-app card anchor not found'

old_hl = html[html.index('<div class="hl">'):html.index('</div>', html.index('<div class="hl">')) + 6]
new_hl = '''<div class="hl">
                <b>💾 جدید در نسخه ۱.۷.۷ — پنل کارمند بازطراحی شد:</b> شورت‌کد [tpp_salary_panel] حالا کارت‌های آماری (تعداد فیش/آخرین دوره/جمع خالص)، گروه‌بندی فیش‌ها بر اساس سال شمسی (سال جاری باز، سال‌های قبل بسته)، نشان «جدید» برای آخرین دوره و مرکز به‌صورت نشان دارد؛ فیش‌ها قبل از فرم بانکی آمده‌اند، «مشاهده» در تب جدید باز می‌شود، حالت خالی و راهنمای شبا اضافه شده و جدول‌ها در موبایل هم مرتب‌اند. نسخه‌های قبل: ۱.۷.۶ بازنویسی پشتیبان‌گیری/بازگردانی (انتقال بکاپ به سرور و دامنه دیگر)؛ ۱.۷.۵ پشتیبان اکسل آنی در نرم‌افزار دسکتاپ؛ ۱.۷.۴ رفع اتصال روی هاست‌های دارای محافظت ضدربات؛ ۱.۷.۳ خروجی چاپی تمیز + رفع باگ محاسبه زنده و ذخیره تنظیمات؛ ۱.۷.۲ رفع خرابی نرم‌افزار پایتون؛ ۱.۷.۱ فونت PDF و ارقام انگلیسی؛ ۱.۷.۰ نرم‌افزار آفلاین + REST API.
        </div>'''
html = html.replace(old_hl, new_hl)
html = html.replace('سپس ۱.۷.۶ را نصب و فعال کنید', 'سپس ۱.۷.۷ را نصب و فعال کنید')
open(page, 'w', encoding='utf-8').write(html)
print('download page updated')

# --- راستی‌آزمایی ---
with zipfile.ZipFile(main_zip) as z:
    main = z.read('tpp_salary/tpp-salary.php').decode('utf-8')
    assert "Version:     1.7.7" in main, 'main version stamp'
    readme = z.read('tpp_salary/readme.txt').decode('utf-8')
    assert 'Stable tag: 1.7.7' in readme, 'readme stable tag'
    fe = z.read('tpp_salary/includes/class-tppsalary-frontend.php').decode('utf-8')
    assert "add_shortcode( 'tpp_salary_panel'" in fe, 'shortcode registration inside package'
    assert 'tpp-stats' in fe and 'tpp-badge-new' in fe and 'tpp-year' in fe, 'redesigned panel inside package'
    assert 'rel="noopener"' in fe and 'tpp-btn-green' in fe, 'noopener + green button inside package'
    css = z.read('tpp_salary/assets/tpp-salary-frontend.css').decode('utf-8')
    assert '.tpp-stats' in css and '.tpp-year' in css and 'max-width: 640px' in css, 'new css inside package'
    bak = z.read('tpp_salary/includes/class-tppsalary-backup.php').decode('utf-8')
    assert 'load_backup_file' in bak and 'restore_resolve_user' in bak and 'restore_map_user' in bak, 'restore pipeline still inside package'
    assert 'json/full.json' in bak and 'FL_NODIR' in bak, 'zip restore still inside package'
    anti = z.read('tpp_salary/python-app/app/antibot.py').decode('utf-8')
    assert 'aes128_decrypt_block' in anti and 'solve_cookie' in anti, 'antibot still inside package'
    ebk = z.read('tpp_salary/python-app/app/excel_backup.py').decode('utf-8')
    assert 'ExcelBackupManager' in ebk, 'excel backup still inside package'
    inst = z.read('tpp_salary/includes/class-tppsalary-install.php').decode('utf-8')
    assert "TPP_SALARY_INSTALL_BUILD', '1.7.7'" in inst, 'INSTALL_BUILD inside package'
with zipfile.ZipFile(py_zip) as z:
    rep = z.read('app/excel_backup.py').decode('utf-8')
    assert 'ExcelBackupManager' in rep and 'REASON_LABELS' in rep, 'backup module inside py zip'
    rep_db = z.read('app/database.py').decode('utf-8')
    assert 'locked' in rep_db and 'add_write_listener' in rep_db, 'db hook inside py zip'

for f in (main_zip, full_zip, py_zip):
    print('%s => %.1f KB' % (os.path.basename(f), os.path.getsize(f) / 1024))
print('PACKAGE OK main=%d full=%d py=%d' % (len(plugin_files), len(full_files), len(py_files)))
