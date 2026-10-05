#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""انتشار Release v1.7.8 با یادداشت دوزبانه و سه asset نصبی"""
import json, urllib.request

TOKEN = 'os.environ["GITHUB_PAT"]'
REPO = 'Tobeseuss/TPP-Salary'
DL = '/home/z/my-project/download/'

BODY = '''<div dir="rtl">

## فارسی

**همگام‌سازی خودکار پروفایل کارمند با آخرین فیش صادرشده** (درخواست کاربر) + رفع باگ قدیمی موتور محاسبه.

پس از صدور هر فیش — از فرم ویزارد، ورود گروهی اکسل، همگام‌سازی نرم‌افزار دسکتاپ یا REST API — این ۱۴ فیلد پروفایل کارمند به‌طور خودکار مطابق همان فیش به‌روزرسانی می‌شوند:

دستمزد روزانه مرجع • پایه سنوات • مبلغ هر ساعت اضافه کاری • مبلغ تعطیل کاری • گروه اصلی بیمه • نرخ درصد بیمه • حقوق مشمول بیمه • حق اولاد هر فرزند • حق مسکن • حق بن • حق تأهل • جریمه غیبت روزانه • تعداد فرزند • کمک هزینه ایاب و ذهاب

**نکات کلیدی:**
- نرخ‌هایی که مستقیم در فیش نیستند (اضافه‌کاری، تعطیل‌کاری، حق اولاد هر فرزند، جریمه غیبت، نرخ بیمه) از تقسیم مبلغ فیش بر تعداد استنتاج می‌شوند — فقط وقتی مخرج بزرگ‌تر از صفر باشد
- ملاک همیشه **آخرین دوره** کارمند است؛ ثبت پس‌گیرانه دوره‌های قدیمی‌تر پروفایل را رگرس نمی‌دهد
- پس از حذف فیش (تکی یا گروهی)، پروفایل به آخرین فیش باقی‌مانده بازمی‌گردد
- در پروفایل کاربری پیشخوان، دوره آخرین فیش همگام‌شده نمایش داده می‌شود
- 🐛 رفع باگ: مقدار متنی «گروه اصلی بیمه» به‌دلیل تبدیل float در موتور محاسبه، در پیلود هر فیش صفر ذخیره می‌شد — اکنون مقادیر متنی دست‌نخورده ذخیره می‌شوند
- برای توسعه‌دهندگان: فیلتر `tpp_salary_disable_profile_sync` و اکشن `tpp_salary_profile_synced`

**تست:** تست جدید ۶۲ ادعایی (ALL PASS) + رگرسیون کامل ۸ مجموعه تست سبز.

**نصب:** فایل `tpp_salary-1.7.8-plugin.zip` را در وردپرس نصب کنید یا پوشه افزونه را جایگزین کنید (فقط ۱.۷.۸ کامل است؛ ۱.۷.۷ و قدیمی‌تر منسوخ).

</div>

---

## English

**Automatic employee-profile sync from the latest issued payslip** (user request) + a legacy compute-engine bug fix.

After every payslip is issued — via the wizard form, bulk Excel import, the desktop app sync or the REST API — these 14 profile fields are updated automatically from that payslip:

reference daily wage • seniority base • overtime hourly rate • holiday-work rate • insurance group • insurance rate % • insurable salary • child allowance per child • housing allowance • food allowance • marriage allowance • daily absence penalty • number of children • commuting allowance

**Key points:**
- Rates not present in the payslip (overtime, holiday, per-child allowance, absence penalty, insurance %) are derived as amount ÷ count — only when the divisor is greater than zero
- The employee's **latest period** always wins; backfilling an older period never regresses the profile
- After deleting a payslip (single or bulk), the profile falls back to the newest remaining payslip
- The dashboard profile screen shows the latest synced payslip period
- 🐛 Bug fix: the textual "insurance group" used to be stored as 0 in every payslip payload (float casting in the compute engine) — text values now pass through untouched
- For developers: `tpp_salary_disable_profile_sync` filter and `tpp_salary_profile_synced` action

**Testing:** new 62-assertion test suite (ALL PASS) + full green regression across all 8 suites.

**Install:** upload `tpp_salary-1.7.8-plugin.zip` in WordPress or replace the plugin folder (only 1.7.8 is current; 1.7.7 and older are superseded).

---

**Assets / فایل‌ها**
- `tpp_salary-1.7.8-plugin.zip` — پلاگین (96 فایل) | plugin package
- `tpp-salary-v1.7.8-full.zip` — پلاگین + CHANGELOG + README + پیکربندی Caddy | full bundle
- `tpp-salary-python-app-1.7.8.zip` — نرم‌افزار دسکتاپ (بدون تغییر نسبت به 1.7.7) | desktop app (unchanged since 1.7.7)
'''

def api(method, url, data=None, ctype='application/json', raw=False):
    req = urllib.request.Request(url, method=method)
    req.add_header('Authorization', 'Bearer ' + TOKEN)
    req.add_header('Accept', 'application/vnd.github+json')
    body = None
    if data is not None:
        body = data if raw else json.dumps(data).encode()
        req.add_header('Content-Type', ctype)
    req.data = body
    with urllib.request.urlopen(req) as r:
        return json.loads(r.read().decode())

rel = api('POST', 'https://api.github.com/repos/%s/releases' % REPO, {
    'tag_name': 'v1.7.8',
    'target_commitish': 'main',
    'name': 'v1.7.8 — همگام‌سازی خودکار پروفایل با آخرین فیش | Auto-sync profile from latest payslip',
    'body': BODY,
})
print('release id:', rel['id'], rel['html_url'])

for fname, ctype in [
    ('tpp_salary-1.7.8-plugin.zip', 'application/zip'),
    ('tpp-salary-v1.7.8-full.zip', 'application/zip'),
    ('tpp-salary-python-app-1.7.8.zip', 'application/zip'),
]:
    up = api('POST', 'https://uploads.github.com/repos/%s/releases/%d/assets?name=%s' % (REPO, rel['id'], fname),
             data=open(DL + fname, 'rb').read(), ctype=ctype, raw=True)
    print('asset:', up['name'], up['size'], 'bytes ->', up['browser_download_url'])

print('RELEASE OK')
