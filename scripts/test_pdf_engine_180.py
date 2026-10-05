# -*- coding: utf-8 -*-
"""تست سریع موتور PDF 1.8.0 — ساخت سه قالب و بررسی اعتبار فایل."""
import os, sys

os.environ.setdefault("QT_QPA_PLATFORM", "offscreen")
sys.path.insert(0, "/home/z/my-project/build/tpp_salary/python-app")

from PySide6.QtWidgets import QApplication
app = QApplication([])

from app import pdf_engine as PE

OUT = "/home/z/my-project/scripts/out180"
os.makedirs(OUT, exist_ok=True)

# 1) گزارش لیست حقوق — 6 کارمند (افقی) + خلاصه
emps = [(1, "علی رضایی"), (2, "مریم احمدی"), (3, "حسین کریمی"), (4, "زهرا موسوی"), (5, "رضا محمدی"), (6, "سارا نجفی")]
rows = [
    ("حقوق پایه", {1: 250000000, 2: 180000000, 3: 210000000, 4: 195000000, 5: 230000000, 6: 175000000}, False),
    ("حق مسکن", {1: 12000000, 2: 12000000, 3: 12000000, 4: 12000000, 5: 12000000, 6: 12000000}, False),
    ("جمع ناخالص", {1: 262000000, 2: 192000000, 3: 222000000, 4: 207000000, 5: 242000000, 6: 187000000}, True),
    ("جمع خالص پرداختی", {1: 210000000, 2: 154000000, 3: 178000000, 4: 166000000, 5: 194000000, 6: 150000000}, True),
]
p1 = os.path.join(OUT, "report.pdf")
pages = PE.write_report_pdf(p1, emps, rows, "شرکت آزمون", ["دوره: مرداد 1404 — مرکز: مرکز اصلی — واحد: ریال"])
assert os.path.getsize(p1) > 3000, "report pdf too small"
print("report OK pages=%d size=%d" % (pages, os.path.getsize(p1)))

# 2) فیش بانکی
bank_rows = [("علی رضایی", "1234567890", "IR820540102680020817909002", 210000000),
             ("مریم احمدی", "9876543210", "IR520540102680020817909001", 154000000)]
p2 = os.path.join(OUT, "bank.pdf")
PE.write_bank_pdf(p2, bank_rows, "شرکت آزمون — فیش بانکی بانک ملت — مرداد 1404 — مرکز اصلی", ["کارکنان دارای حساب: 2 نفر"])
assert os.path.getsize(p2) > 3000
print("bank OK size=%d" % os.path.getsize(p2))

# 3) فیش حقوقی A5
fields = [("حقوق پایه", "250,000,000", True, 250000000.0),
          ("حق مسکن", "12,000,000", True, 12000000.0),
          ("کسر بیمه", "-18,948,842", True, -18948842.0),
          ("خالص پرداختی", "210,000,000", True, 210000000.0)]
p3 = os.path.join(OUT, "payslip.pdf")
PE.write_payslip_pdf(p3, {"name": "علی رضایی", "national": "0012345678", "job_title": "کارشناس",
                          "personnel": 12, "period_label": "مرداد 1404", "center_name": "مرکز اصلی"},
                     fields, "شرکت آزمون", "ریال", "1404/05/12")
assert os.path.getsize(p3) > 3000
print("payslip OK size=%d" % os.path.getsize(p3))

# 4) تعداد سطر زیاد → چند صفحه
many_rows = [("سطر %d" % i, {e[0]: 1000000 * i for e in emps}, i % 5 == 0) for i in range(1, 61)]
p4 = os.path.join(OUT, "report_multi.pdf")
pages = PE.write_report_pdf(p4, emps, many_rows, "شرکت آزمون", ["دوره: مرداد 1404"])
assert pages > 1, "multi-page expected"
assert os.path.getsize(p4) > 10000
print("multi OK pages=%d size=%d" % (pages, os.path.getsize(p4)))
print("ALL PDF SMOKE PASS")
