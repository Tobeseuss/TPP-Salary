#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""بسته‌بندی نسخه 1.7.2 — رفع خرابی‌های نرم‌افزار پایتون (records._bulk_delete / employees.setFlags)
- پلاگین: build/tpp_salary به‌جز کش فونت و __pycache__ (شامل python-app)
- کامل: پلاگین + CHANGELOG.md + README.md + caddy در ریشه
- جدید: بسته مستقل python-app برای جایگزینی سریع پوشه دسکتاپ کاربر
- حذف بسته‌های نسخه قبلی + به‌روزرسانی صفحه دانلود + راستی‌آزمایی
"""
import os, re, zipfile

ROOT = '/home/z/my-project'
BUILD = os.path.join(ROOT, 'build', 'tpp_salary')
DL = os.path.join(ROOT, 'download')
VER = '1.7.2'

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
assert len(plugin_files) == 94, 'plugin file count drift: %d' % len(plugin_files)
assert any(rel.endswith('python-app/tpp_salary_app.py') for _p, rel in plugin_files), 'python-app missing!'
assert any(rel.endswith('class-tppsalary-api.php') for _p, rel in plugin_files), 'api class missing!'
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
assert len(full_files) == 99, 'full file count drift: %d' % len(full_files)

# --- بسته مستقل python-app (جایگزینی سریع پوشه دسکتاپ) ---
py_files = [(p, rel) for p, rel in plugin_files if rel.startswith('tpp_salary/python-app/')]
py_zip = zipify(py_files, os.path.join(DL, 'tpp-salary-python-app-%s.zip' % VER), strip=2)
print('python-app files:', len(py_files))
assert len(py_files) == 26, 'python-app file count drift: %d' % len(py_files)

# --- حذف بسته‌های نسخه قبلی ---
removed = []
for old in ['tpp_salary-1.7.1-plugin.zip', 'tpp-salary-v1.7.1-full.zip',
            'tpp_salary-1.7.0-plugin.zip', 'tpp-salary-v1.7.0-full.zip',
            'tpp_salary-1.6.3-plugin.zip', 'tpp-salary-v1.6.3-full.zip',
            'tpp_salary-1.6.2-plugin.zip', 'tpp-salary-v1.6.2-full.zip']:
    p = os.path.join(DL, old)
    if os.path.exists(p):
        os.remove(p)
        removed.append(old)
print('removed old packages:', removed)

# --- صفحه دانلود ---
page = os.path.join(DL, 'index.html')
html = open(page, encoding='utf-8').read()
html = html.replace('tpp_Salary v1.7.1', 'tpp_Salary v1.7.2')
html = html.replace('نسخه ۱.۷.۱ — فونت‌های PDF خوانا (Vazirmatn حداقل 10pt Bold) + همه اعداد انگلیسی در سایت و نرم‌افزار آفلاین',
                    'نسخه ۱.۷.۲ — رفع خرابی‌های نرم‌افزار آفلاین پایتون (لیست حقوق + پنجره افزودن/ویرایش کارمند)')
html = html.replace('tpp-salary-v1.7.1-full.zip', 'tpp-salary-v1.7.2-full.zip')
html = html.replace('tpp_salary-1.7.1-plugin.zip', 'tpp_salary-1.7.2-plugin.zip')
html = html.replace('tpp-salary-v1.7.1-full.zip', 'tpp-salary-v1.7.2-full.zip')
py_card = '''
                <div class="dl">
                        <div>
                                <div class="name">tpp-salary-python-app-1.7.2.zip</div>
                                <div class="meta">فقط نرم‌افزار آفلاین — برای به‌روزرسانی سریع نسخه دسکتاپ، محتوای آن را روی پوشه python-app قبلی استخراج کنید</div>
                        </div>
                        <a class="btn" href="tpp-salary-python-app-1.7.2.zip" download>دانلود نرم‌افزار</a>
                </div>'''
pat = re.compile(r'(<a class="btn" href="tpp_salary-1\.7\.2-plugin\.zip" download>دانلود پلاگین</a>\s*</div>)', re.S)
assert pat.search(html), 'plugin card anchor not found'
html = pat.sub(lambda m: m.group(1) + py_card, html, count=1)

old_hl = html[html.index('<div class="hl">'):html.index('</div>', html.index('<div class="hl">')) + 6]
new_hl = '''<div class="hl">
                <b>🔧 جدید در نسخه ۱.۷.۲ — رفع خرابی نرم‌افزار پایتون:</b> در صفحه «لیست حقوق» دکمه حذف گروهی به متدی وجودنداشت وصل بود و برنامه هنگام اجرا متوقف می‌شد (رفع شد)؛ پنجره «افزودن/ویرایش کارمند» باز نمی‌شد (رفع شد)؛ دکمه «حذف رکورد انتخابی» از حذف گروهی جدا و اصلاح شد. همه صفحات و دکمه‌ها اکنون با تست خودکار بدون خطا اجرا می‌شوند. نسخه‌های قبل: ۱.۷.۱ فونت PDF (Vazirmatn حداقل 10pt Bold) + ارقام انگلیسی؛ ۱.۷.۰ نرم‌افزار آفلاین پایتون + REST API؛ ۱.۶.۳ پر کردن فیلدها از حقوق گذشته؛ ۱.۶.۲ جستجو و حذف تکی/گروهی؛ ۱.۶.۱ PDF تک‌صفحه‌ای و اکسل ستونی.
        </div>'''
html = html.replace(old_hl, new_hl)
html = html.replace('سپس ۱.۷.۱ را نصب و فعال کنید', 'سپس ۱.۷.۲ را نصب و فعال کنید')
html = html.replace('فایل <code>tpp_salary-1.7.2-plugin.zip</code> را استخراج کنید ← پوشه <code>python-app</code> ← دوبار کلیک روی <code>TPP Salary.bat</code>',
                    'فایل <code>tpp_salary-1.7.2-plugin.zip</code> را استخراج کنید ← پوشه <code>python-app</code> ← دوبار کلیک روی <code>TPP Salary.bat</code> (یا بسته مستقل <code>tpp-salary-python-app-1.7.2.zip</code> را روی پوشه قبلی استخراج کنید)')
open(page, 'w', encoding='utf-8').write(html)
print('download page updated')

# --- راستی‌آزمایی ---
with zipfile.ZipFile(main_zip) as z:
    names = z.namelist()
    assert 'tpp_salary/tpp-salary.php' in names
    assert 'tpp_salary/python-app/TPP Salary.bat' in names
    assert 'tpp_salary/python-app/app/main.py' in names
    main = z.read('tpp_salary/tpp-salary.php').decode('utf-8')
    assert "Version:     1.7.2" in main, 'main version stamp'
    readme = z.read('tpp_salary/readme.txt').decode('utf-8')
    assert 'Stable tag: 1.7.2' in readme, 'readme stable tag'
    recs = z.read('tpp_salary/python-app/app/ui/pages/records.py').decode('utf-8')
    assert '_delete_one_selected' in recs and '_bulk_delete' not in recs, 'records fix inside package'
with zipfile.ZipFile(py_zip) as z:
    pnames = z.namelist()
    assert 'tpp_salary_app.py' in pnames, 'py zip root entry'
    assert any(n == 'app/ui/pages/records.py' for n in pnames), 'py zip app files'
    emp = z.read('app/ui/pages/employees.py').decode('utf-8')
    assert 'item.setFlags(item.flags() | Qt.ItemIsUserCheckable)' in emp, 'employees fix inside py zip'

for f in (main_zip, full_zip, py_zip):
    print('%s => %.1f KB' % (os.path.basename(f), os.path.getsize(f) / 1024))
print('PACKAGE OK main=%d full=%d py=%d' % (len(plugin_files), len(full_files), len(py_files)))
