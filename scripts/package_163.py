#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""بسته‌بندی نسخه 1.6.3 — مطابق الگوی نسخه‌های قبلی:
- پلاگین: build/tpp_salary به‌جز ۶ فایل کش فونت (cw.dat/cw127.php/mtx.php — هنگام نصب ساخته می‌شوند)
- کامل: پلاگین + build/CHANGELOG.md + build/README.md + build/caddy/* در ریشه
- حذف بسته‌های نسخه قبلی + راستی‌آزمایی محتوای بسته
"""
import os, zipfile, glob, sys

ROOT = '/home/z/my-project'
BUILD = os.path.join(ROOT, 'build', 'tpp_salary')
DL = os.path.join(ROOT, 'download')
VER = '1.6.3'

CACHE_EXT = ('.cw.dat', '.cw127.php', '.mtx.php')
cache_names = {'tpp_salary/lib/tfpdf/font/unifont/vazirmatn-bold.cw.dat',
               'tpp_salary/lib/tfpdf/font/unifont/vazirmatn-bold.cw127.php',
               'tpp_salary/lib/tfpdf/font/unifont/vazirmatn-bold.mtx.php',
               'tpp_salary/lib/tfpdf/font/unifont/vazirmatn-regular.cw.dat',
               'tpp_salary/lib/tfpdf/font/unifont/vazirmatn-regular.cw127.php',
               'tpp_salary/lib/tfpdf/font/unifont/vazirmatn-regular.mtx.php'}

def collect_plugin():
    files = []
    for root, dirs, fnames in os.walk(BUILD):
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
assert len(plugin_files) == 67, 'plugin file count drift: %d' % len(plugin_files)

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
assert len(full_files) == 72, 'full file count drift: %d' % len(full_files)

# --- حذف بسته‌های نسخه قبلی ---
removed = []
for z in glob.glob(os.path.join(DL, 'tpp_salary-1.6.2-plugin.zip')) + glob.glob(os.path.join(DL, 'tpp-salary-v1.6.2-full.zip')) + glob.glob(os.path.join(DL, 'tpp_salary-1.6.1-plugin.zip')) + glob.glob(os.path.join(DL, 'tpp-salary-v1.6.1-full.zip')):
    os.remove(z)
    removed.append(os.path.basename(z))
print('removed:', removed)

# --- راستی‌آزمایی ---
with zipfile.ZipFile(main_zip) as z:
    bad = z.testzip()
    assert bad is None, 'corrupt member: %s' % bad
    n1 = z.namelist()
    assert 'tpp_salary/tpp-salary.php' in n1
    ver = z.read('tpp_salary/tpp-salary.php').decode('utf-8')
    assert "Version:     %s" % VER in ver and "TPP_SALARY_VERSION', '%s'" % VER in ver
    js = z.read('tpp_salary/admin/js/tpp-salary-admin.js').decode('utf-8')
    assert 'TPP.recalc = recalc' in js
    sp = z.read('tpp_salary/includes/class-tppsalary-salary-pages.php').decode('utf-8')
    assert 'پر کردن فیلدها بر اساس حقوق گذشته' in sp and 'past_salary_payload' in sp
    ax = z.read('tpp_salary/includes/class-tppsalary-ajax.php').decode('utf-8')
    assert 'tpp_salary_past_salary' in ax
    assert not any(n.endswith(('.cw.dat', '.cw127.php', '.mtx.php')) for n in n1), 'cache leaked into package'

with zipfile.ZipFile(full_zip) as z:
    bad = z.testzip()
    assert bad is None
    n2 = z.namelist()
    for want in ['CHANGELOG.md', 'README.md', 'caddy/Caddyfile', 'caddy/Caddyfile.wordpress', 'caddy/docker-compose.yml']:
        assert want in n2, 'missing in full zip: %s' % want

for p in (main_zip, full_zip):
    print('%s  %.1fKB  %d files' % (os.path.basename(p), os.path.getsize(p) / 1024.0, len(zipfile.ZipFile(p).namelist())))
print('PACKAGE OK')
