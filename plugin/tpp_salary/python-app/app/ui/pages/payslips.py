# -*- coding: utf-8 -*-
"""فیش‌های حقوقی — مشاهده/چاپ فیش تکی و دریافت ZIP عمده (نسخه 1.8.0).

هم‌سان با صفحه «فیش‌های حقوقی» افزونه:
- فیلتر سال/ماه/مرکز → فهرست فیش‌های دوره
- «مشاهده / چاپ» هر فیش → PDF در نمایشگر پیش‌فرض سیستم (قابل چاپ)
- «دریافت ZIP فیش‌های همه کارکنان» → هر فیش یک PDF مجزا در یک ZIP
قالب فیش: A5 و آینه build_payslip_pdf افزونه (اطلاعات کارمند + جدول
جزئیات بدون فیلدهای فقط‌محاسباتی و مقادیر صفر + امضاها + تاریخ چاپ).
"""

import os
import tempfile
import zipfile

from PySide6.QtCore import Qt
from PySide6.QtWidgets import (
    QComboBox, QFileDialog, QFrame, QHBoxLayout, QHeaderView, QLabel,
    QMessageBox, QPushButton, QTableWidget, QTableWidgetItem, QVBoxLayout,
)

from ... import jalali as J
from ...api_client import load_json
from ... import pdf_engine as PE

CALC_ONLY = ("overtime_hours", "holiday_days", "absence_days")


def payslip_fields(store, payload):
    """ردیف‌های چاپی فیش — آینه payslip_rows افزونه: بدون CALC_ONLY و
    بدون فیلدهای عددی صفر برای همین رکورد."""
    out = []
    for f in store.fields():
        key = str(f.get("key", ""))
        if key in CALC_ONLY:
            continue
        raw = payload.get(key, 0)
        if str(f.get("type", "")) == "number":
            try:
                val = float(raw or 0)
            except (TypeError, ValueError):
                val = 0.0
            if abs(val) <= 0.0001:
                continue
            out.append((str(f.get("label") or key), J.format_money(val), True, val))
        else:
            s = str(raw if raw is not None else "")
            if s:
                out.append((str(f.get("label") or key), s, False, None))
    return out


class PayslipsPage(QFrame):

    def __init__(self, win):
        super().__init__()
        self.win = win

        v = QVBoxLayout(self)
        v.setContentsMargins(22, 20, 22, 20)
        v.setSpacing(12)

        title = QLabel("فیش‌های حقوقی — تکی و عمده (ZIP)")
        title.setObjectName("PageTitle")
        v.addWidget(title)

        hint = QLabel("با مشخص کردن سال، ماه و مرکز می‌توانید فیش حقوقی همه کارکنان ثبت‌شده را به صورت فایل‌های PDF مجزا دریافت یا در قالب یک فایل ZIP دانلود کنید. «مشاهده / چاپ» فیش را در نمایشگر PDF سیستم باز می‌کند.")
        hint.setObjectName("PageHint")
        hint.setWordWrap(True)
        v.addWidget(hint)

        bar = QHBoxLayout()
        jy, jm, _ = J.today_jalali()
        self.cmb_year = QComboBox()
        for y in range(1399, 1407):
            self.cmb_year.addItem(J.fa_digits(y), y)
        self.cmb_year.setCurrentIndex(max(0, list(range(1399, 1407)).index(jy) if jy in range(1399, 1407) else 6))
        self.cmb_month = QComboBox()
        for m in range(1, 13):
            self.cmb_month.addItem(J.month_name(m), m)
        self.cmb_month.setCurrentIndex(jm - 1)
        self.cmb_center = QComboBox()
        btn_show = QPushButton("نمایش فیش‌ها")
        btn_show.setObjectName("Primary")
        btn_show.setCursor(Qt.PointingHandCursor)
        btn_show.clicked.connect(self.load_rows)
        bar.addWidget(self.cmb_year)
        bar.addWidget(self.cmb_month)
        bar.addWidget(self.cmb_center, 1)
        bar.addWidget(btn_show)
        v.addLayout(bar)

        self.meta_label = QLabel("")
        self.meta_label.setObjectName("PageHint")
        v.addWidget(self.meta_label)

        self.table = QTableWidget(0, 5)
        self.table.setHorizontalHeaderLabels(["#", "کارمند", "مرکز", "خالص پرداختی", "فیش PDF"])
        self.table.verticalHeader().hide()
        self.table.setEditTriggers(QTableWidget.NoEditTriggers)
        self.table.setAlternatingRowColors(True)
        self.table.setSelectionBehavior(QTableWidget.SelectRows)
        h = self.table.horizontalHeader()
        h.setSectionResizeMode(QHeaderView.ResizeMode.Stretch)
        h.setSectionResizeMode(0, QHeaderView.ResizeMode.Fixed)
        self.table.setColumnWidth(0, 40)
        v.addWidget(self.table, 1)

        foot = QHBoxLayout()
        self.btn_zip = QPushButton("دریافت ZIP فیش‌های همه کارکنان")
        self.btn_zip.setObjectName("Primary")
        self.btn_zip.setCursor(Qt.PointingHandCursor)
        self.btn_zip.clicked.connect(self.export_zip)
        foot.addStretch(1)
        foot.addWidget(self.btn_zip)
        v.addLayout(foot)

        self._rows = []  # (record_id, name, center_name, net)

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
        self.load_rows()

    # ---------------- داده ----------------

    def _period(self):
        return (
            self.cmb_year.currentData() or 0,
            self.cmb_month.currentIndex() + 1,
            self.cmb_center.currentData() or 0,
        )

    def load_rows(self):
        jyear, jmonth, center_id = self._period()
        records = self.win.store.period_records(jyear, jmonth, center_id)
        self._rows = []
        for rec in records:
            emp = self.win.store.employee(rec["user_id"])
            name = emp["name"] if emp else "کارمند #%s" % J.fa_digits(rec["user_id"])
            cen = self.win.store.center(rec["center_id"])
            self._rows.append((rec["id"], name, (cen["name"] if cen else "—"), float(rec.get("net") or 0)))
        self._rows.sort(key=lambda t: t[1])

        self.table.setRowCount(len(self._rows))
        for r, (rec_id, name, cname, net) in enumerate(self._rows):
            it = QTableWidgetItem(J.fa_digits(r + 1))
            it.setTextAlignment(Qt.AlignCenter)
            self.table.setItem(r, 0, it)
            self.table.setItem(r, 1, QTableWidgetItem(name))
            it2 = QTableWidgetItem(cname)
            it2.setTextAlignment(Qt.AlignCenter)
            self.table.setItem(r, 2, it2)
            it3 = QTableWidgetItem(J.format_money(net))
            it3.setTextAlignment(Qt.AlignCenter)
            self.table.setItem(r, 3, it3)
            btn = QPushButton("مشاهده / چاپ")
            btn.setCursor(Qt.PointingHandCursor)
            btn.clicked.connect(lambda _=False, rid=rec_id: self.view_one(rid))
            self.table.setCellWidget(r, 4, btn)
        self.meta_label.setText("دوره: %s — %s فیش" % (
            J.period_label(jyear, jmonth), J.fa_digits(len(self._rows))))

    # ---------------- ساخت فیش ----------------

    def _make_pdf(self, rec_id, out_path):
        """ساخت PDF فیش یک رکورد — خروجی: (ok, پیام خطا)."""
        rec = self.win.store.record(rec_id)
        if not rec:
            return False, "رکورد یافت نشد."
        emp = self.win.store.employee(rec["user_id"]) or {}
        payload = load_json(rec.get("payload"), {}) or {}
        cen = self.win.store.center(rec["center_id"])
        fields = payslip_fields(self.win.store, payload)
        jy, jm, _jd = J.today_jalali()
        try:
            PE.write_payslip_pdf(
                out_path,
                {
                    "name": emp.get("name") or "?",
                    "national": emp.get("national") or "",
                    "job_title": (emp.get("profile") or {}).get("job_title", ""),
                    "personnel": rec["user_id"],
                    "period_label": J.period_label(int(rec["jyear"]), int(rec["jmonth"])),
                    "center_name": cen["name"] if cen else "",
                },
                fields,
                self.win.store.kv_company() or "فیش حقوقی و دستمزد",
                self.win.store.kv_currency() or "ریال",
                "%04d/%02d/%02d" % (jy, jm, _jd),
            )
        except Exception as exc:  # noqa: BLE001
            return False, str(exc)[:160]
        return True, ""

    def view_one(self, rec_id):
        fd, tmp = tempfile.mkstemp(prefix="payslip-", suffix=".pdf")
        os.close(fd)
        ok, err = self._make_pdf(rec_id, tmp)
        if not ok:
            QMessageBox.warning(self, "فیش حقوقی", "ساخت PDF ناموفق: %s" % err)
            return
        if not PE.open_pdf(tmp):
            # اگر نمایشگر باز نشد، مسیر ذخیره دستی پیشنهاد می‌شود
            path, _ = QFileDialog.getSaveFileName(self, "ذخیره فیش", "payslip.pdf", "PDF (*.pdf)")
            if path:
                with open(tmp, "rb") as src, open(path, "wb") as dst:
                    dst.write(src.read())
                QMessageBox.information(self, "فیش حقوقی", "فایل ذخیره شد:\n%s" % path)

    def export_zip(self):
        if not self._rows:
            QMessageBox.information(self, "فیش‌های حقوقی", "ابتدا فیش‌های دوره را نمایش دهید.")
            return
        jyear, jmonth, center_id = self._period()
        default = "payslips-%s-%02d%s.zip" % (jyear, jmonth, ("-center%s" % center_id) if center_id else "")
        path, _ = QFileDialog.getSaveFileName(self, "ذخیره ZIP فیش‌ها", default, "ZIP (*.zip)")
        if not path:
            return
        tmpdir = tempfile.mkdtemp(prefix="tpp-payslips-")
        used = set()
        try:
            with zipfile.ZipFile(path, "w", zipfile.ZIP_DEFLATED) as z:
                for rec_id, name, _cn, _net in self._rows:
                    safe = "".join(ch if ch.isalnum() or ch in " -_" else " " for ch in name).strip() or ("user-%s" % rec_id)
                    base = safe
                    n = 2
                    while base in used:
                        base = "%s (%d)" % (safe, n)
                        n += 1
                    used.add(base)
                    pdf_path = os.path.join(tmpdir, base + ".pdf")
                    ok, err = self._make_pdf(rec_id, pdf_path)
                    if ok:
                        z.write(pdf_path, base + ".pdf")
            QMessageBox.information(self, "فیش‌های حقوقی", "فایل ZIP ساخته شد:\n%s" % path)
        except Exception as exc:  # noqa: BLE001
            QMessageBox.warning(self, "فیش‌های حقوقی", "ساخت ZIP ناموفق: %s" % str(exc)[:160])
        finally:
            try:
                for f in os.listdir(tmpdir):
                    os.remove(os.path.join(tmpdir, f))
                os.rmdir(tmpdir)
            except Exception:
                pass
