#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""استخراج داده نمونه‌های قدیمی (ساختاری برای اکسل خراب اما قابل‌خواندن با openpyxl)"""
import json, sys
import openpyxl

out = {}
for key, path in (
    ('employees', '/home/z/my-project/build/tpp_salary/samples/employees-sample.xlsx'),
    ('records',  '/home/z/my-project/build/tpp_salary/samples/salary-records-sample.xlsx'),
):
    wb = openpyxl.load_workbook(path, data_only=False)
    sheets = []
    for ws in wb.worksheets:
        widths = {}
        for letter, dim in ws.column_dimensions.items():
            if dim.width:
                widths[letter] = round(float(dim.width), 2)
        rows = []
        for row in ws.iter_rows():
            vals = []
            for c in row:
                v = c.value
                if v is None: v = ''
                vals.append(v)
            # حذف ستون‌های تهی انتهایی
            while vals and vals[-1] == '': vals.pop()
            rows.append(vals)
        while rows and not rows[-1]: rows.pop()
        sheets.append({'title': ws.title, 'freeze': ws.freeze_panes or None,
                       'widths': widths, 'rows': rows})
    out[key] = sheets

with open('/tmp/old_samples.json', 'w', encoding='utf-8') as f:
    json.dump(out, f, ensure_ascii=False, default=str)
print('extracted:', {k: [(s['title'], len(s['rows'])) for s in v] for k, v in out.items()})
