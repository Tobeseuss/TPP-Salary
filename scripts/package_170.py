#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""بسته‌بندی نسخه 1.7.0 — مطابق الگوی نسخه‌های قبلی:
- پلاگین: build/tpp_salary به‌جز ۶ فایل کش فونت و __pycache__ (شامل python-app)
- کامل: پلاگین + build/CHANGELOG.md + build/README.md + build/caddy/* در ریشه
- حذف بسته‌های نسخه قبلی + راستی‌آزمایی محتوای بسته
"""
import os, zipfile, sys

ROOT = '/home/z/my-project'
BUILD = os.path.join(ROOT, 'build', 'tpp_salary')
DL = os.path.join(ROOT, 'download')
VER = '1.7.0'

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


def zipify(files, arcbase):
    out = os.path.join(DL, arcbase)
    with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
        for p, rel in files:
            z.write(p, rel)
    return out


plugin_files = collect_plugin()
print('plugin files:', len(plugin_files))
assert len(plugin_files) == 94, 'plugin file count drift: %d' % len(plugin_files)
assert any(rel.endswith('python-app/tpp_salary_app.py') for _p, rel in plugin_files), 'python-app missing!'
assert any(rel.endswith('class-tppsalary-api.php') for _p, rel in plugin_files), 'api class missing!'
assert not any('__pycache__' in rel for _p, rel in plugin_files), 'pycache leaked into zip!'

main_zip = zipify(plugin_files, 'tpp_salary-%s-plugin.zip' % VER)

full_files = list(plugin_files)
for extra in ['CHANGELOG.md', 'README.md']:
    full_files.append((os.path.join(ROOT, 'build', extra), extra))
for root, dirs, fnames in os.walk(os.path.join(ROOT, 'build', 'caddy')):
    for f in sorted(fnames):
        p = os.path.join(root, f)
        rel = 'caddy/' + os.path.relpath(p, os.path.join(ROOT, 'build', 'caddy')).replace(os.sep, '/')
        full_files.append((p, rel))

full_zip = zipify(full_files, 'tpp-salary-v%s-full.zip' % VER)
print('full files:', len(full_files))
assert len(full_files) == 99, 'full file count drift: %d' % len(full_files)

# --- حذف بسته‌های نسخه قبلی ---
removed = []
for old in ['tpp_salary-1.6.3-plugin.zip', 'tpp-salary-v1.6.3-full.zip',
            'tpp_salary-1.6.2-plugin.zip', 'tpp-salary-v1.6.2-full.zip',
            'tpp_salary-1.6.1-plugin.zip', 'tpp-salary-v1.6.1-full.zip',
            'tpp_salary-1.6.0-plugin.zip', 'tpp-salary-v1.6.0-full.zip']:
    p = os.path.join(DL, old)
    if os.path.exists(p):
        os.remove(p)
        removed.append(old)
print('removed old packages:', removed)

# --- راستی‌آزمایی ---
with zipfile.ZipFile(main_zip) as z:
    names = z.namelist()
    assert 'tpp_salary/tpp-salary.php' in names
    assert 'tpp_salary/includes/class-tppsalary-api.php' in names
    assert 'tpp_salary/python-app/TPP Salary.bat' in names
    assert 'tpp_salary/python-app/app/main.py' in names
    assert 'tpp_salary/python-app/data/index.php' in names
    main = z.read('tpp_salary/tpp-salary.php').decode('utf-8')
    assert "Version:     1.7.0" in main
with zipfile.ZipFile(full_zip) as z:
    names = z.namelist()
    assert 'CHANGELOG.md' in names and 'caddy/' in ' '.join(names)

for f in (main_zip, full_zip):
    print('%s => %.1f KB' % (os.path.basename(f), os.path.getsize(f) / 1024))
print('PACKAGE OK main=%d full=%d' % (len(plugin_files), len(full_files)))
