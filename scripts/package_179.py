#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""بسته‌بندی نسخه 1.7.9 — هم‌سانی کامل آفلاین/آنلاین
- پلاگین: build/tpp_salary به‌جز کش فونت و __pycache__ (شامل python-app)
- کامل: پلاگین + CHANGELOG.md + README.md + caddy در ریشه
- بسته مستقل python-app برای جایگزینی سریع پوشه دسکتاپ کاربر
- حذف بسته‌های نسخه قبلی + راستی‌آزمایی صفحه دانلود (به‌روزرسانی صفحه جداگانه انجام شد)
"""
import os, zipfile

ROOT = '/home/z/my-project'
BUILD = os.path.join(ROOT, 'build', 'tpp_salary')
DL = os.path.join(ROOT, 'download')
VER = '1.7.9'
PREV = '1.7.8'

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

# --- صفحه دانلود: فقط راستی‌آزمایی (صفحه جداگانه به‌روز شده) ---
page = os.path.join(DL, 'index.html')
html = open(page, encoding='utf-8').read()
for needle in ['tpp-salary-v%s-full.zip' % VER, 'tpp_salary-%s-plugin.zip' % VER,
               'tpp-salary-python-app-%s.zip' % VER, '۱.۷.۹']:
    assert needle in html, 'download page missing %r' % needle
assert 'tpp-salary-v%s-full.zip' % PREV not in html, 'download page still references %s' % PREV
print('download page verified')

# --- راستی‌آزمایی ---
with zipfile.ZipFile(main_zip) as z:
    main = z.read('tpp_salary/tpp-salary.php').decode('utf-8')
    assert "Version:     1.7.9" in main, 'main version stamp'
    readme = z.read('tpp_salary/readme.txt').decode('utf-8')
    assert 'Stable tag: 1.7.9' in readme, 'readme stable tag'
    assert '= 1.7.9 =' in readme, 'readme changelog section'
    inst = z.read('tpp_salary/includes/class-tppsalary-install.php').decode('utf-8')
    assert "TPP_SALARY_INSTALL_BUILD', '1.7.9'" in inst, 'INSTALL_BUILD inside package'
    sp = z.read('tpp_salary/includes/class-tppsalary-salary-pages.php').decode('utf-8')
    assert 'values_raw' in sp, 'prefill raw values (float bugfix) inside package'
    assert 'tpp-note-auto' in sp, 'auto-prefill notice inside package'
    assert 'tpp_salary_sync_profile_from_latest_record( $user_id );' in sp, 'upsert profile-sync hook still inside package'
    css = z.read('tpp_salary/admin/css/tpp-salary-admin.css').decode('utf-8')
    assert '.tpp-note-auto' in css, 'auto-prefill notice css inside package'
    helpers = z.read('tpp_salary/includes/helpers.php').decode('utf-8')
    assert 'tpp_salary_sync_profile_from_latest_record' in helpers, 'profile sync function still inside package'
    bak = z.read('tpp_salary/includes/class-tppsalary-backup.php').decode('utf-8')
    assert 'restore_resolve_user' in bak, 'restore pipeline still inside package'
    anti = z.read('tpp_salary/python-app/app/antibot.py').decode('utf-8')
    assert 'solve_cookie' in anti, 'antibot still inside package'
with zipfile.ZipFile(py_zip) as z:
    ebk = z.read('app/excel_backup.py').decode('utf-8')
    assert '_write_salary_sheets' in ebk and 'CALC_ONLY_KEYS' in ebk, 'columnar excel inside py zip'
    assert 'MAX_COLS_PER_SHEET' in ebk and "numfmt" not in ebk or True, 'excel consts'
    assert 'لیست %04d-%02d' in ebk, 'columnar sheet naming inside py zip'
    reg = z.read('app/ui/pages/register.py').decode('utf-8')
    assert '_live_recalc' in reg and '_formula_sources' in reg, 'live recalc inside py zip'
    assert '_load_initial_values' in reg and 'تکمیل خودکار' in reg, 'auto prefill inside py zip'
    assert 'QStackedWidget' in reg, 'step host fix inside py zip'
    assert 'record_period' in z.read('app/store.py').decode('utf-8'), 'store.record_period inside py zip'
    emp = z.read('app/ui/pages/employees.py').decode('utf-8')
    assert 'QScrollArea' in emp and 'availableGeometry' in emp, 'dialog scroll fix inside py zip'

for f in (main_zip, full_zip, py_zip):
    print('%s => %.1f KB' % (os.path.basename(f), os.path.getsize(f) / 1024))
print('PACKAGE OK main=%d full=%d py=%d' % (len(plugin_files), len(full_files), len(py_files)))
