#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""انتشار Release نسخه 1.7.7 با سه فایل نصبی (plugin / full / python-app)"""
import json, urllib.request

TOKEN = 'os.environ["GITHUB_PAT"]'
REPO = 'Tobeseuss/TPP-Salary'
DL = '/home/z/my-project/download/'

BODY = """## v1.7.7 — بازطراحی پنل کارمند | Employee Panel Redesign

<div dir="rtl">

### فارسی
شورت‌کد صفحه دریافت فیش حقوقی `[tpp_salary_panel]` کامل بازطراحی شد:

- 📊 کارت‌های آماری: تعداد فیش‌ها، آخرین دوره، جمع کل خالص پرداختی با واحد پول
- 📅 گروه‌بندی فیش‌ها بر اساس سال شمسی (سال جاری باز، سال‌های قبل با یک کلیک)
- 🏷️ نشان دوره، نشان «جدید» برای آخرین دوره و نمایش مرکز به‌صورت نشان
- 🆕 فیش‌ها پیش از فرم اطلاعات بانکی + حالت خالی گویا + راهنمای شبا
- 🔗 دکمه‌های «مشاهده» و «دانلود PDF» در تب جدید باز می‌شوند
- 📱 جدول‌های ریسپانسیو با اسکرول افقی در موبایل + دکمه سبز ذخیره

**نصب:** `tpp_salary-1.7.7-plugin.zip` را از بخش Assets دانلود و از «افزونه‌ها ← افزودن ← بارگذاری» نصب کنید. برای ارتقا فقط پوشه افزونه را جایگزین کنید — شورت‌کد صفحه تغییر نمی‌کند.

### فایل‌های بسته
- `tpp_salary-1.7.7-plugin.zip` — فقط افزونه (شامل نرم‌افزار دسکتاپ)
- `tpp-salary-v1.7.7-full.zip` — افزونه + مستندات + پیکربندی Caddy
- `tpp-salary-python-app-1.7.7.zip` — فقط نرم‌افزار دسکتاپ (جایگزینی سریع)

</div>

### English
The payslip page shortcode `[tpp_salary_panel]` was fully redesigned:

- 📊 Stat cards: payslip count, latest period, total net pay with currency
- 📅 Year-based grouping with collapsible sections (current year open)
- 🏷️ Period badge, "new" badge on the latest period, center as a badge
- 🆕 Payslips now come before the bank form + friendly empty state + Sheba hint
- 🔗 "View" and "Download PDF" buttons open in a new tab (`rel=noopener`)
- 📱 Responsive tables with horizontal scroll on mobile + green save button

**Install:** download `tpp_salary-1.7.7-plugin.zip` from Assets and upload via Plugins ← Add New ← Upload. To upgrade, just replace the plugin folder — the page shortcode is unchanged.

> Full details: [CHANGELOG.md](../blob/main/CHANGELOG.md) — سابقه کامل همه نسخه‌ها در همین ریپو.
"""

req = urllib.request.Request(
    'https://api.github.com/repos/%s/releases' % REPO,
    data=json.dumps({
        'tag_name': 'v1.7.7',
        'target_commitish': 'main',
        'name': 'v1.7.7 — پنل کارمند بازطراحی شد | Employee Panel Redesign',
        'body': BODY,
        'draft': False,
        'prerelease': False,
    }).encode('utf-8'),
    headers={'Authorization': 'Bearer %s' % TOKEN, 'Accept': 'application/vnd.github+json',
             'Content-Type': 'application/json'},
    method='POST')
with urllib.request.urlopen(req) as r:
    rel = json.loads(r.read())
print('release id:', rel['id'], '| url:', rel['html_url'])

for fname in ['tpp_salary-1.7.7-plugin.zip', 'tpp-salary-v1.7.7-full.zip', 'tpp-salary-python-app-1.7.7.zip']:
    data = open(DL + fname, 'rb').read()
    req = urllib.request.Request(
        'https://uploads.github.com/repos/%s/releases/%d/assets?name=%s' % (REPO, rel['id'], fname),
        data=data,
        headers={'Authorization': 'Bearer %s' % TOKEN, 'Accept': 'application/vnd.github+json',
                 'Content-Type': 'application/zip'},
        method='POST')
    with urllib.request.urlopen(req) as r:
        a = json.loads(r.read())
    print('asset:', a['name'], a['size'], 'bytes | state:', a['state'])
print('RELEASE DONE')
