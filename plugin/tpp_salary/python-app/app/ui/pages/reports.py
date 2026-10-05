# -*- coding: utf-8 -*-
"""گزارش لیست حقوق — سطر: عناوین حقوقی، ستون: کارمندان (هم‌سان با گزارش سایت).

خروجی اکسل ستونی (هم‌سان با نسخه 1.6.1 افزونه):
سطر ۱ عنوان، سطر ۲ واحد، سطر ۴ هدر ستونی (عناوین + نام کارمندان) و سطرهای بعدی فقط عناوین.
خروجی PDF — نسخه 1.8.0: موتور برداری جدید (app/pdf_engine.py).
"""

from PySide6.QtCore import Qt
from PySide6.QtWidgets import (
    QComboBox, QFileDialog, QFrame, QHBoxLayout, QHeaderView, QLabel,
    QMessageBox, QPushButton, QTableWidget, QTableWidgetItem, QVBoxLayout,
)

from ... import jalali as J
from ... import pdf_engine as PE

YEARS = list(range(1399, 1407))
SUMMARY_KEYS = ["gross", "insurable", "insurance_deduct", "other_deductions", "net"]
SUMMARY_LABELS = {
    "gross": "جمع ناخالص",
    "insurable": "جمع مشمول بیمه",
    "insurance_deduct": "جمع بیمه سهم کارمند",
    "other_deductions": "جمع سایر کسورات",
    "net": "جمع خالص پرداختی",
}


class ReportsPage(QFrame):

    def __init__(self, win):
        super().__init__()
        self.win = win
        self._emps = []   # (emp_id, name)
        self._rows = []   # (label, {emp_id: value})
        self._title_meta = ""

        v = QVBoxLayout(self)
        v.setContentsMargins(22, 20, 22, 20)
        v.setSpacing(12)

        title = QLabel("گزارش لیست حقوق")
        title.setObjectName("PageTitle")
        v.addWidget(title)

        bar = QHBoxLayout()
        jy, jm, _ = J.today_jalali()
        self.cmb_year = QComboBox()
        self.cmb_year.addItems([J.fa_digits(y) for y in YEARS])
        self.cmb_year.setCurrentIndex(max(0, YEARS.index(jy) if jy in YEARS else len(YEARS) - 1))
        self.cmb_month = QComboBox()
        self.cmb_month.addItems([J.month_name(m) for m in range(1, 13)])
        self.cmb_month.setCurrentIndex(jm - 1)
        self.cmb_center = QComboBox()
        btn_build = QPushButton("نمایش گزارش")
        btn_build.setObjectName("Primary")
        btn_build.setCursor(Qt.PointingHandCursor)
        btn_build.clicked.connect(self.build)
        bar.addWidget(self.cmb_year)
        bar.addWidget(self.cmb_month)
        bar.addWidget(self.cmb_center, 1)
        bar.addWidget(btn_build)
        v.addLayout(bar)

        self.meta_label = QLabel("")
        self.meta_label.setObjectName("PageHint")
        v.addWidget(self.meta_label)

        self.table = QTableWidget()
        self.table.verticalHeader().hide()
        self.table.setEditTriggers(QTableWidget.NoEditTriggers)
        self.table.setAlternatingRowColors(True)
        self.table.horizontalHeader().setSectionResizeMode(QHeaderView.ResizeMode.Interactive)
        v.addWidget(self.table, 1)

        foot = QHBoxLayout()
        btn_excel = QPushButton("خروجی اکسل (ستونی)")
        btn_excel.setCursor(Qt.PointingHandCursor)
        btn_excel.clicked.connect(self.export_excel)
        btn_pdf = QPushButton("خروجی PDF")
        btn_pdf.setObjectName("Primary")
        btn_pdf.setCursor(Qt.PointingHandCursor)
        btn_pdf.clicked.connect(self.export_pdf)
        foot.addStretch(1)
        foot.addWidget(btn_excel)
        foot.addWidget(btn_pdf)
        v.addLayout(foot)

    def refresh(self):
        cur = self.cmb_center.currentData() or 0
        self.cmb_center.blockSignals(True)
        self.cmb_center.clear()
        self.cmb_center.addItem("همه مراکز", 0)
        for c in self.win.store.centers():
            self.cmb_center.addItem(c["name"], c["id"])
        idx = self.cmb_center.findData(cur)
        self.cmb_center.setCurrentIndex(max(0, idx))
        self.cmb_center.blockSignals(False)

    # ---------------- ساخت گزارش ----------------

    def build(self):
        jyear = YEARS[self.cmb_year.currentIndex()]
        jmonth = self.cmb_month.currentIndex() + 1
        center_id = self.cmb_center.currentData() or 0

        records = self.win.store.period_records(jyear, jmonth, center_id)
        self._title_meta = "%s — %s — %s" % (
            self.win.store.kv_company() or "حقوق و دستمزد",
            J.period_label(jyear, jmonth),
            (self.win.store.center(center_id)["name"] if center_id else "همه مراکز"),
        )
        self.meta_label.setText("سال/ماه/مرکز/واحد یک‌بار در سربرگ نمایش داده می‌شود (هم‌سان با گزارش سایت). — %s" % self._title_meta)

        # نسخه 1.7.3 — هم‌سان با افزونه: فیلدهای «فقط محاسباتی» و ردیف‌های
        # همه‌صفر در گزارش چاپی نمایش داده نمی‌شوند (درخواست کاربر).
        calc_only = {"overtime_hours", "holiday_days", "absence_days"}
        fields = [f for f in self.win.store.fields() if str(f.get("key", "")) not in calc_only]
        self._emps = []
        self._rows = []
        emp_map = {}

        from ...api_client import load_json
        for rec in records:
            eid = int(rec["user_id"])
            if eid not in emp_map:
                emp = self.win.store.employee(eid)
                emp_map[eid] = emp["name"] if emp else "کارمند #%s" % J.fa_digits(eid)
            payload = load_json(rec.get("payload"), {}) or {}
            if eid not in [e[0] for e in self._emps]:
                self._emps.append((eid, emp_map[eid]))
        self._emps.sort(key=lambda t: t[1])

        # سطر عناوین حقوقی از payload کارمندان
        for f in fields:
            row = {"label": f["label"], "values": {}}
            for rec in records:
                payload = load_json(rec.get("payload"), {}) or {}
                if f["key"] in payload:
                    eid = int(rec["user_id"])
                    row["values"][eid] = payload.get(f["key"], 0)
            self._rows.append(row)

        # سطرهای خلاصه از ستون‌های جدول
        for key in SUMMARY_KEYS:
            row = {"label": SUMMARY_LABELS[key], "values": {}}
            for rec in records:
                eid = int(rec["user_id"])
                val = float(rec.get(key, 0) or 0)
                row["values"][eid] = val
            self._rows.append(row)

        # نسخه 1.7.3: حذف ردیف‌هایی که مقدار همه کارمندان صفر است (هم‌سان PDF افزونه)
        # سطرهای متنی (مقدار غیرعددی) همیشه نمایش داده می‌شوند.
        def _row_all_zero(row):
            vals = list(row["values"].values())
            if not vals:
                return True  # هیچ مقداری در هیچ payload نیست — مثل افزونه حذف می‌شود
            nums = []
            for v in vals:
                try:
                    nums.append(abs(float(v or 0)))
                except (TypeError, ValueError):
                    return False
            return all(n <= 0.0001 for n in nums)

        self._rows = [row for row in self._rows if not _row_all_zero(row)]

        # رندر جدول
        ncols = 1 + len(self._emps)
        self.table.clear()
        self.table.setColumnCount(ncols)
        headers = ["عنوان حقوقی"] + [name for _eid, name in self._emps]
        self.table.setHorizontalHeaderLabels(headers)
        self.table.setRowCount(len(self._rows))
        for r, row in enumerate(self._rows):
            item = QTableWidgetItem(row["label"])
            item.setForeground(Qt.darkBlue if row["label"] in SUMMARY_LABELS.values() else Qt.black)
            font = item.font()
            font.setBold(row["label"] in SUMMARY_LABELS.values())
            item.setFont(font)
            self.table.setItem(r, 0, item)
            for c, (eid, _name) in enumerate(self._emps):
                val = row["values"].get(eid)
                txt = self._fmt(val)
                it = QTableWidgetItem(txt)
                it.setTextAlignment(Qt.AlignCenter)
                self.table.setItem(r, c + 1, it)
        self.table.resizeColumnsToContents()
        if not self._emps:
            self.meta_label.setText("رکوردی برای این دوره یافت نشد.")

    @staticmethod
    def _fmt(val):
        """عدد → مبلغ قالب‌بندی‌شده؛ متن (مثل گروه بیمه) → خود رشته."""
        if val is None:
            return "—"
        if isinstance(val, str):
            try:
                return J.format_money(float(J.en_digits(val).replace(",", "")))
            except (TypeError, ValueError):
                return val
        return J.format_money(val)

    # ---------------- خروجی اکسل ستونی ----------------

    def export_excel(self):
        if not self._rows:
            QMessageBox.information(self, "خروجی", "ابتدا گزارش را بسازید (نمایش گزارش).")
            return
        try:
            from openpyxl import Workbook
        except ImportError:
            QMessageBox.warning(self, "خروجی", "کتابخانه openpyxl نصب نیست؛ برنامه را دوباره باز کنید تا خودکار نصب شود.")
            return
        path, _ = QFileDialog.getSaveFileName(self, "ذخیره اکسل", "salary-report.xlsx", "Excel (*.xlsx)")
        if not path:
            return
        wb = Workbook()
        ws = wb.active
        ws.title = "گزارش حقوق"
        ws.sheet_view.rightToLeft = True

        center_name = self.cmb_center.currentText()
        center_id = self.cmb_center.currentData() or 0
        center_label = center_name if center_id else "همه مراکز"
        ws.cell(row=1, column=1, value=self.win.store.kv_company() or "گزارش لیست حقوق")
        ws.cell(row=2, column=1, value="دوره: %s — مرکز: %s" % (
            J.period_label(YEARS[self.cmb_year.currentIndex()], self.cmb_month.currentIndex() + 1),
            center_label,
        ))
        ws.cell(row=4, column=1, value="عنوان حقوقی")
        for c, (_eid, name) in enumerate(self._emps, start=2):
            ws.cell(row=4, column=c, value=name)
        r = 5
        for row in self._rows:
            ws.cell(row=r, column=1, value=row["label"])
            for c, (eid, _name) in enumerate(self._emps, start=2):
                val = row["values"].get(eid)
                if val is not None:
                    ws.cell(row=r, column=c, value=float(val))
            r += 1
        ws.column_dimensions["A"].width = 26
        for c in range(2, 2 + len(self._emps)):
            ws.column_dimensions[ws.cell(row=4, column=c).column_letter].width = 18
        try:
            wb.save(path)
            QMessageBox.information(self, "خروجی اکسل", "فایل ذخیره شد:\n%s" % path)
        except Exception as exc:  # noqa: BLE001
            QMessageBox.warning(self, "خروجی اکسل", "ذخیره ناموفق: %s" % str(exc)[:120])

    # ---------------- خروجی PDF — موتور جدید 1.8.0 ----------------

    def export_pdf(self):
        if not self._rows:
            QMessageBox.information(self, "خروجی", "ابتدا گزارش را بسازید (نمایش گزارش).")
            return
        default = "salary-report-%s-%02d.pdf" % (
            YEARS[self.cmb_year.currentIndex()], self.cmb_month.currentIndex() + 1)
        path, _ = QFileDialog.getSaveFileName(self, "ذخیره PDF", default, "PDF (*.pdf)")
        if not path:
            return
        # نسخه 1.8.0: PDF برداری با موتور جدید (فونت Vazirmatn، سربرگ تکرارشو،
        # شکستن صفحه سالم، سطرهای خلاصه Bold) — آینه PDF افزونه.
        rows_data = [
            (row["label"], row["values"], row["label"] in SUMMARY_LABELS.values())
            for row in self._rows
        ]
        jyear = YEARS[self.cmb_year.currentIndex()]
        jmonth = self.cmb_month.currentIndex() + 1
        center_label = self.cmb_center.currentText() if (self.cmb_center.currentData() or 0) else "همه مراکز"
        title = self.win.store.kv_company() or "گزارش لیست حقوق"
        meta = ["دوره: %s — مرکز: %s — واحد: %s" % (
            J.period_label(jyear, jmonth), center_label, self.win.store.kv_currency() or "ریال")]
        try:
            PE.write_report_pdf(path, self._emps, rows_data, title, meta)
            QMessageBox.information(self, "خروجی PDF", "فایل ذخیره شد:\n%s" % path)
        except Exception as exc:  # noqa: BLE001
            QMessageBox.warning(self, "خروجی PDF", "ساخت PDF ناموفق: %s" % str(exc)[:160])
