# -*- coding: utf-8 -*-
"""اصلاح مسیرهای require که زیر قوانین dash دوبار پیشوند خوردند
class-tppsalary-XXX.php -> class-tppsalary-XXX.php (به‌جز pages)
"""
import os, re

TARGETS = ['/home/z/my-project/build/tpp_salary', '/home/z/my-project/scripts']
rx = re.compile(r'class-tppsalary-(?!pages)')
n = 0
for base in TARGETS:
    for root, dirs, files in os.walk(base):
        if os.path.relpath(root, base) == 'lib' or os.path.relpath(root, base).startswith('lib' + os.sep):
            continue
        for f in files:
            if not f.endswith(('.php', '.js', '.css', '.py')):
                continue
            p = os.path.join(root, f)
            with open(p, 'r', encoding='utf-8', errors='replace') as fh:
                src = fh.read()
            out = rx.sub('class-tppsalary-', src)
            if out != src:
                with open(p, 'w', encoding='utf-8') as fh:
                    fh.write(out)
                n += 1
                print('FIX', os.path.relpath(p, '/home/z/my-project'))
print('DONE fixed=%d' % n)
