#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""وارد کردن بخش‌های changelog نسخه‌های 1.3.2 تا 1.7.7 از readme.txt افزونه به CHANGELOG.md ریشه ریپو"""
import re, io

src = open('/home/z/my-project/TPP-Salary/plugin/tpp_salary/readme.txt', encoding='utf-8').read()
# بخش چنجلاگ: از «== Changelog ==» تا پایان
idx = src.index('== Changelog ==')
body = src[idx + len('== Changelog =='):].strip()

# تبدیل = 1.7.7 = به ## 1.7.7
body = re.sub(r'^= ([0-9.]+) =$', r'## \1', body, flags=re.M)
# فقط تا نسخه 1.3.2 (شامل) — 1.3.1 و قبلتر در فایل موجود است
cut = body.index('## 1.3.1')
newer = body[:cut].rstrip() + '\n\n---\n\n'

path = '/home/z/my-project/TPP-Salary/CHANGELOG.md'
cur = open(path, encoding='utf-8').read()
# درج پیش از «## 1.3.1»
anchor = cur.index('## 1.3.1')
merged = cur[:anchor] + newer + cur[anchor:]
open(path, 'w', encoding='utf-8').write(merged)
print('merged; version sections now:', len(re.findall(r'^## [0-9]+\.[0-9]+\.[0-9]+', merged, flags=re.M)))
