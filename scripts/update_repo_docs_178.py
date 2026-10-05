#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""بروزرسانی مستندات دوزبانه ریشه ریپو برای نسخه 1.7.8
- worklog.md: جدول خلاصه انگلیسی همه تسک‌ها در ابتدا + ورودی Task 28 (دوزبانه)
- CHANGELOG.md: سطر 1.7.8 در جدول انگلیسی + بخش فارسی کامل در ابتدا
- README.md: نشان نسخه + بولت امکانات (فا/en) + ارجاع‌های نسخه
"""
import re

REPO = '/home/z/my-project/TPP-Salary'

# ---------- 1) worklog.md ----------
wl_path = REPO + '/worklog.md'
wl = open(wl_path, encoding='utf-8').read()

task28 = '''---
Task ID: 28
Agent: main
Task: نسخه 1.7.8 — همگام‌سازی خودکار فیلدهای پروفایل کارمند با آخرین فیش صادرشده + رفع باگ «گروه اصلی بیمه» | v1.7.8 — auto-sync employee profile fields from the latest issued payslip + fix the "insurance group" payload bug

Work Log:
- تابع جدید `tpp_salary_sync_profile_from_latest_record()` در helpers.php: پس از هر صدور فیش، ۱۴ فیلد پروفایل کارمند خودکار به‌روزرسانی می‌شود — ۸ فیلد مستقیم (دستمزد روزانه مرجع، پایه سنوات، حق مسکن، حق بن، حق تأهل، تعداد فرزند، ایاب و ذهاب، گروه اصلی بیمه) + حقوق مشمول بیمه از `insurable` + ۵ نرخ استنتاجی از تقسیم مبلغ بر تعداد (نرخ اضافه‌کاری، تعطیل‌کاری، حق اولاد هر فرزند، جریمه غیبت روزانه، نرخ درصد بیمه) | New `tpp_salary_sync_profile_from_latest_record()` in helpers.php: after every payslip issuance the employee's 14 profile fields auto-update — 8 direct mappings + insurable → insurable_default + 5 derived rates (amount ÷ count)
- حفاظت‌ها: مخرج صفر (بدون اضافه‌کاری/غیبت/فرزند در فیش) نرخ قبلی حفظ می‌شود؛ گروه بیمه خالی پروفایل را خراب نمی‌کند؛ نوشتن فقط با تغییر واقعی؛ پاکسازی ممیز شناور (7.000000001 → 7) | Guards: zero divisor keeps the previous rate; empty insurance group never clobbers the profile; write-only-on-change; float-noise normalization
- ملاک «آخرین دوره» است نه «آخرین ثبت» — ORDER BY jyear DESC, jmonth DESC, id DESC؛ ثبت پس‌گیرانه دوره قدیمی پروفایل را رگرس نمی‌دهد | "Latest" = latest period (jyear/jmonth/id DESC) — backfilling an older period never regresses the profile
- نقاط اتصال: TppSalary_Salary_Pages::upsert_record() (هسته مشترک فرم ویزارد، ورود گروهی اکسل، همگام‌سازی آفلاین و REST API) + حذف تکی و گروهی (بازگشت پروفایل به آخرین فیش باقی‌مانده) | Hook points: the shared upsert_record() core (wizard form, bulk Excel import, offline sync, REST API) + single/bulk delete (profile falls back to the newest remaining payslip)
- رفع باگ قدیمی موتور محاسبه: array_map('floatval') و حلقه گردکردن نهایی مقدار متنی «گروه اصلی بیمه» را در پیلود هر فیش صفر می‌کرد — فیلدهای متنی اکنون دست‌نخورده از موتور عبور می‌کنند | Fixed legacy compute-engine bug: floatval + final rounding used to zero the textual insurance group in every payslip payload; text fields now pass through untouched
- UI پیشخوان: یادداشت راهنمای همگام‌سازی در پروفایل کاربری + نمایش دوره آخرین فیش | Dashboard UI: sync note in the user-profile screen showing the latest synced payslip period
- API توسعه: فیلتر `tpp_salary_disable_profile_sync` + اکشن `tpp_salary_profile_synced` + تابع کمکی `tpp_salary_latest_record_period()` | Dev API: disable filter + synced action + latest-period helper
- تست جدید scripts/test_profile_sync_178.php (۶۲ ادعا ALL PASS): نگاشت مستقیم/استنتاجی، حفاظت مخرج صفر، رگرسیون‌ناپذیری ثبت پس‌گیرانه، یکپارچگی upsert_record، حذف گروهی، یادداشت پیشخوان | New test (62 assertions ALL PASS): direct/derived mappings, zero-divisor guards, no-regression on backfill, upsert integration, bulk-delete fallback, dashboard note
- رگرسیون کامل سبز: fixes_150/160/162/163، api_170، panel_177، pyapp_sync، api_antibot | Full regression green across all suites
- نسخه 1.7.8 در چهار نقطه (هدر، TPP_SALARY_VERSION، INSTALL_BUILD، readme.txt) + CHANGELOG/README افزونه + صفحه دانلود | Version bumped in all four places + plugin docs + download page
- پاکسازی: ۲۳ فایل میراثی (class-tpp-*.php نام‌قدیم + assetهای قدیمی) که توسط اسنپ‌شات سندباکس به build احیا شده بودند و در بسته رسمی 1.7.7 هم نبودند، از build و ریپو حذف شدند (کد مرده بدون هیچ ارجاع) | Cleanup: 23 legacy files (old-name class-tpp-*.php + old assets) resurrected by the sandbox snapshot — absent from the official 1.7.7 package — removed from build and repo (dead code, zero references)
- بسته‌بندی 1.7.8 (۹۶/۱۰۱/۲۸ فایل — همان شمار رسمی 1.7.7) + کامیت + تگ v1.7.8 + GitHub Release با سه فایل نصبی | Packaging (96/101/28 files — same as official 1.7.7) + commit + v1.7.8 tag + GitHub Release with 3 assets

Stage Summary:
- پروفایل هر کارمند همیشه آینه آخرین فیش اوست؛ فرم ثبت حقوق همیشه با مقادیر جدید پیش‌پر می‌شود | Each employee profile now mirrors their latest payslip; the salary form always pre-fills fresh values
- باگ تاریخی صفرشدن گروه بیمه در پیلود فیش رفع شد | Historical insurance-group zeroing bug fixed
- تحویل: Release v1.7.8 با سه asset در GitHub | Delivered: v1.7.8 release with 3 assets on GitHub
'''

table_rows = [
    ('1', 'Plugin scaffolded from the requirements doc, tested, packaged, Caddy server setup'),
    ('2', 'Full completion of the plugin, tests, packaging, Caddy run'),
    ('3', 'Diagnosed "plugin could not be activated" on the user site'),
    ('4', 'Free-host compatibility, dark mode, dynamic samples, Vazirmatn, offline mode, in-plugin worklog'),
    ('5', 'Fatal "Cannot declare class TPP_Xlsx_Writer" — hardened against double loading'),
    ('6', 'Fixed "Call to undefined method TPP_Settings::init()" (mixed old/new files on host)'),
    ('7', 'MariaDB reserved-word `values` SQL failure + stale-install guard + DDL reserved-word scanner'),
    ('8', 'Full security/perf refactor: atomic restore transaction, random passwords, mass-assignment whitelist'),
    ('9', 'Namespace isolation 1.3.0 (TppSalary_*) — zero collision with other tpp_ plugins, data migration'),
    ('10', '1.3.1 — broken Excel sample, admin 404s, backup management'),
    ('11', '1.3.2 — menu 404/permissions + broken .excel file fixed for good'),
    ('12', '1.4.0 — six fixes + bulk salary import'),
    ('13', '1.4.1 — ten fixes (banks, import columns, auto-employee, profile fields, insurable formula, dark mode)'),
    ('14', '1.5.0 — three fields moved to profile, multi-format backups, PDF shaping fix, A5 payslip'),
    ('15', '1.6.0 — import name-column root cause, E2E with real files, employment status, A4-landscape PDF'),
    ('16', '1.6.1 — one-page salary-list PDF, column Excel, pagination everywhere'),
    ('17', '1.6.2 — search + single/bulk delete (records, employees, centers)'),
    ('18', '1.6.3 — "fill fields from past salary" in wizard step 3'),
    ('19', '1.7.0 — offline Python desktop app + two-way REST API sync'),
    ('20', '1.7.1 — Vazirmatn >= 10pt Bold in PDFs, English digits everywhere'),
    ('21', '1.7.2 — fixed Python app startup crash + permanent QA gates'),
    ('22', '1.7.3 — PDF zero-field cleanup, single-page fit, live recompute fix, settings save fix'),
    ('23', '1.7.4 — fixed anti-bot "invalid JSON response" on tpptc.ir'),
    ('24', '1.7.5 — instant Excel backup in the desktop app'),
    ('25', '1.7.6 — backup/restore rewritten for cross-server moves'),
    ('26', '1.7.7 — employee panel shortcode redesign'),
    ('27', 'Public GitHub repo (Tobeseuss/TPP-Salary) + Release v1.7.7 + bilingual docs convention'),
    ('28', '1.7.8 — auto-sync employee profile from latest payslip + insurance-group payload bug fix'),
]

en_table = '\n## English Summary — All Tasks\n\n| # | Task |\n|---|---|\n'
for tid, title in table_rows:
    en_table += '| %s | %s |\n' % (tid, title)
en_table += '\n> جزئیات کامل هر تسک به فارسی در ادامه همین فایل آمده است. از Task 28 به بعد، ورودی‌ها دوزبانه‌اند.\n> Full Persian details for every task follow below. Entries are bilingual from Task 28 on.\n'

marker = '# Worklog — پروژه پلاگین حقوق و دستمزد (tpp_Salary)\n'
assert wl.startswith(marker), 'worklog title moved!'
assert '## English Summary — All Tasks' not in wl, 'summary table already present'
wl = wl.replace(marker, marker + en_table, 1)
wl = wl.rstrip('\n') + '\n\n' + task28
open(wl_path, 'w', encoding='utf-8').write(wl)
print('worklog.md updated')

# ---------- 2) CHANGELOG.md ----------
cl_path = REPO + '/CHANGELOG.md'
cl = open(cl_path, encoding='utf-8').read()

row178 = ('| 1.7.8 | Auto-sync of 14 employee profile fields from the latest issued payslip '
          '(direct + renamed + derived-rate mappings, latest-period wins, re-sync after single/bulk delete, '
          'covers wizard/bulk-import/offline/REST paths, dashboard note, dev filter/action) '
          '+ fixed legacy bug that zeroed the textual "insurance group" in every payslip payload |')
m = re.search(r'(\| 1\.7\.7 \|)', cl)
assert m, '1.7.7 changelog row not found'
cl = cl[:m.start()] + row178 + '\n' + cl[m.start():]

fa178 = open('/home/z/my-project/build/tpp_salary/CHANGELOG.md', encoding='utf-8').read()
start = fa178.index('## 1.7.8 — 1405/07/05')
end = fa178.index('## 1.7.7 — 1405/06/24')
fa_section = fa178[start:end].rstrip('\n')
anchor = '## English Summary — All Releases'
assert anchor in cl, 'changelog english anchor missing'
cl = cl.replace(anchor, fa_section + '\n\n' + anchor, 1)
open(cl_path, 'w', encoding='utf-8').write(cl)
print('CHANGELOG.md updated')

# ---------- 3) README.md ----------
rd_path = REPO + '/README.md'
rd = open(rd_path, encoding='utf-8').read()
rd = rd.replace('version-1.7.7-blue', 'version-1.7.8-blue')

fa_bullet_anchor = '- **پنل کارمند** — شورت‌کد `[tpp_salary_panel]` برای مشاهده فیش‌ها، دانلود PDF و ثبت اطلاعات بانکی'
assert fa_bullet_anchor in rd
rd = rd.replace(fa_bullet_anchor,
    fa_bullet_anchor + '\n- **همگام‌سازی خودکار پروفایل** — پس از صدور هر فیش، ۱۴ فیلد پروفایل کارمند (دستمزد روزانه مرجع، پایه سنوات، نرخ‌ها، گروه بیمه، حق مسکن/بن/تأهل/اولاد، جریمه غیبت، تعداد فرزند، ایاب و ذهاب) مطابق همان فیش به‌روزرسانی می‌شود (نسخه 1.7.8)')

en_anchor = rd.index('## English')
en_features_anchor = rd.index('- **Employee panel**', en_anchor) if '- **Employee panel**' in rd[en_anchor:] else -1
assert en_features_anchor > 0, 'english employee panel bullet missing'
rd = rd[:en_features_anchor] + '- **Automatic profile sync** — after every payslip is issued, 14 profile fields (daily wage, seniority, rates, insurance group, housing/food/marriage/child allowance, absence penalty, children count, commute) are updated from that payslip (v1.7.8)\n' + rd[en_features_anchor:]

rd = rd.replace('سابقه کامل نسخه‌های قبل از انتشار عمومی (1.0.0 تا 1.7.7)', 'سابقه کامل نسخه‌های قبل از انتشار عمومی (1.0.0 تا 1.7.8)')
rd = rd.replace('The pre-publication history (1.0.0 → 1.7.7)', 'The pre-publication history (1.0.0 → 1.7.8)')
open(rd_path, 'w', encoding='utf-8').write(rd)
print('README.md updated')
