#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""بسته‌بندی نسخه 1.8.0 — گزارش سالانه مراکز + PDF جدید + بخش‌های تازهٔ پایتون
- پلاگین: build/tpp_salary به‌جز کش فونت tFPDF و __pycache__ (شامل python-app)
- کامل: پلاگین + CHANGELOG.md + README.md + caddy در ریشه
- بسته مستقل python-app برای جایگزینی سریع پوشه دسکتاپ کاربر
- همگام‌سازی build → ریپو (plugin/tpp_salary) + حذف بسته‌های نسخه قبلی
- راستی‌آزمایی صفحه دانلود و محتوای بسته‌ها
"""
import os, shutil, zipfile

ROOT = '/home/z/my-project'
BUILD = os.path.join(ROOT, 'build', 'tpp_salary')
DL = os.path.join(ROOT, 'download')
REPO = os.path.join(ROOT, 'TPP-Salary')
VER = '1.8.0'
PREV = '1.7.9'

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


# --- همگام‌سازی build → ریپو (پیش از بسته‌بندی؛ ریپو منبع کامیت است) ---
repo_plugin = os.path.join(REPO, 'plugin', 'tpp_salary')
assert os.path.isdir(repo_plugin), 'repo plugin dir missing'
if os.path.exists(os.path.join(BUILD, 'lib', 'tfpdf', 'font', 'unifont', 'vazirmatn-regular.cw.dat')):
    pass  # کش فونت محلی به ریپو نمی‌رود (gitignore) — فقط فایل‌های اصلی همگام می‌شوند
n_sync = 0
for root, dirs, fnames in os.walk(BUILD):
    dirs[:] = [d for d in dirs if d != '__pycache__']
    rel_dir = os.path.relpath(root, BUILD)
    dst_root = os.path.join(repo_plugin, rel_dir) if rel_dir != '.' else repo_plugin
    os.makedirs(dst_root, exist_ok=True)
    for f in sorted(fnames):
        src = os.path.join(root, f)
        rel = 'tpp_salary/' + os.path.relpath(src, BUILD).replace(os.sep, '/')
        if rel in cache_names:
            continue
        shutil.copy2(src, os.path.join(dst_root, f))
        n_sync += 1
# حذف فایل‌های اضافه‌شده در ریپو که در build نیستند (به‌جز کش فونت)
for root, dirs, fnames in os.walk(repo_plugin):
    for f in sorted(fnames):
        rp = os.path.join(root, f)
        rel = 'tpp_salary/' + os.path.relpath(rp, repo_plugin).replace(os.sep, '/')
        if rel in cache_names:
            continue
        bp = os.path.join(BUILD, os.path.relpath(rp, repo_plugin))
        if not os.path.exists(bp):
            os.remove(rp)
            print('repo extra removed:', rel)
print('repo synced files:', n_sync)

plugin_files = collect_plugin()
print('plugin files:', len(plugin_files))
assert len(plugin_files) == 104, 'plugin file count drift: %d' % len(plugin_files)
assert any(rel.endswith('python-app/tpp_salary_app.py') for _p, rel in plugin_files), 'python-app missing!'
assert any(rel.endswith('python-app/app/pdf_engine.py') for _p, rel in plugin_files), 'pdf_engine.py missing!'
assert any(rel.endswith('python-app/app/backup_core.py') for _p, rel in plugin_files), 'backup_core.py missing!'
for page in ('payslips.py', 'bankfiche.py', 'annual.py', 'backup_page.py'):
    assert any(rel.endswith('python-app/app/ui/pages/' + page) for _p, rel in plugin_files), page + ' missing!'
assert any(rel.endswith('python-app/assets/fonts/Vazirmatn-Regular.ttf') for _p, rel in plugin_files), 'font missing!'
assert any(rel.endswith('python-app/assets/fonts/Vazirmatn-Bold.ttf') for _p, rel in plugin_files), 'font bold missing!'
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
assert len(full_files) == 109, 'full file count drift: %d' % len(full_files)

# --- بسته مستقل python-app ---
py_files = [(p, rel) for p, rel in plugin_files if rel.startswith('tpp_salary/python-app/')]
py_zip = zipify(py_files, os.path.join(DL, 'tpp-salary-python-app-%s.zip' % VER), strip=2)
print('python-app files:', len(py_files))
assert len(py_files) == 36, 'python-app file count drift: %d' % len(py_files)

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
               'tpp-salary-python-app-%s.zip' % VER, '۱.۸.۰']:
    assert needle in html, 'download page missing %r' % needle
assert 'tpp-salary-v%s-full.zip' % PREV not in html, 'download page still references %s' % PREV
print('download page verified')

# --- راستی‌آزمایی محتوا ---
with zipfile.ZipFile(main_zip) as z:
    main = z.read('tpp_salary/tpp-salary.php').decode('utf-8')
    assert "Version:     1.8.0" in main, 'main version stamp'
    readme = z.read('tpp_salary/readme.txt').decode('utf-8')
    assert 'Stable tag: 1.8.0' in readme, 'readme stable tag'
    assert '= 1.8.0 =' in readme, 'readme changelog section'
    inst = z.read('tpp_salary/includes/class-tppsalary-install.php').decode('utf-8')
    assert "TPP_SALARY_INSTALL_BUILD', '1.8.0'" in inst, 'INSTALL_BUILD inside package'
    rep = z.read('tpp_salary/includes/class-tppsalary-reports.php').decode('utf-8')
    assert 'build_annual_xlsx' in rep and 'tpp_salary_annual_excel' in rep, 'annual report inside package'
    assert 'گزارش سالانه مراکز' in rep, 'annual report menu inside package'
    helpers = z.read('tpp_salary/includes/helpers.php').decode('utf-8')
    assert 'tpp_salary_sync_profile_from_latest_record' in helpers, 'profile sync still inside package'
    sp = z.read('tpp_salary/includes/class-tppsalary-salary-pages.php').decode('utf-8')
    assert 'values_raw' in sp and 'tpp-note-auto' in sp, 'prefill core still inside package'
    anti = z.read('tpp_salary/python-app/app/antibot.py').decode('utf-8')
    assert 'solve_cookie' in anti, 'antibot still inside package'
with zipfile.ZipFile(py_zip) as z:
    pdf = z.read('app/pdf_engine.py').decode('utf-8')
    assert 'QPdfWriter' in pdf and 'write_payslip_pdf' in pdf and 'write_bank_pdf' in pdf, 'pdf engine inside py zip'
    assert 'Vazirmatn' in pdf, 'vazirmatn font load inside py zip'
    bk = z.read('app/backup_core.py').decode('utf-8')
    assert 'snapshot_full' in bk and 'restore' in bk and '"kind": "records"' in bk, 'backup core inside py zip'
    aw = z.read('app/ui/app_window.py').decode('utf-8')
    assert 'PayslipsPage' in aw and 'BankFichePage' in aw and 'AnnualPage' in aw and 'BackupPage' in aw, 'new pages wired'
    ann = z.read('app/ui/pages/annual.py').decode('utf-8')
    assert 'جمع سال' in ann, 'annual sheet naming inside py zip'
    pay = z.read('app/ui/pages/payslips.py').decode('utf-8')
    assert 'payslip_fields' in pay and 'write_payslip_pdf' in pay, 'payslips page inside py zip'
    bak = z.read('app/ui/pages/backup_page.py').decode('utf-8')
    assert 'restore_from_file' in bak, 'backup page inside py zip'
    win = z.read('app/ui/pages/reports.py').decode('utf-8')
    assert 'write_report_pdf' in win, 'reports pdf engine inside py zip'
    fonts = [n for n in z.namelist() if 'assets/fonts/' in n]
    assert len(fonts) == 2, 'fonts in py zip: %s' % fonts

for f in (main_zip, full_zip, py_zip):
    print('%s => %.1f KB' % (os.path.basename(f), os.path.getsize(f) / 1024))
print('PACKAGE OK main=%d full=%d py=%d' % (len(plugin_files), len(full_files), len(py_files)))
