# -*- coding: utf-8 -*-
"""داشبورد — کارت‌های آماری + آخرین رکوردها + میانبرها."""

from PySide6.QtCore import Qt
from PySide6.QtWidgets import (
    QFrame, QGridLayout, QHBoxLayout, QHeaderView, QLabel, QPushButton,
    QTableWidget, QTableWidgetItem, QVBoxLayout,
)

from ... import jalali as J


def _stat_card(title, value="۰"):
    card = QFrame()
    card.setObjectName("StatCard")
    v = QVBoxLayout(card)
    v.setContentsMargins(16, 14, 16, 14)
    t = QLabel(title)
    t.setProperty("statTitle", "1")
    val = QLabel(value)
    val.setProperty("statValue", "1")
    v.addWidget(t)
    v.addWidget(val)
    return card, val


class DashboardPage(QFrame):

    def __init__(self, win):
        super().__init__()
        self.win = win
        v = QVBoxLayout(self)
        v.setContentsMargins(22, 20, 22, 20)
        v.setSpacing(14)

        head = QHBoxLayout()
        title = QLabel("داشبورد")
        title.setObjectName("PageTitle")
        self.subtitle = QLabel("")
        self.subtitle.setObjectName("PageHint")
        head.addWidget(title)
        head.addSpacing(10)
        head.addWidget(self.subtitle)
        head.addStretch(1)

        btn_reg = QPushButton("+ ثبت حقوق جدید")
        btn_reg.setObjectName("Primary")
        btn_reg.setCursor(Qt.PointingHandCursor)
        btn_reg.clicked.connect(lambda: self.win.show_page("register"))
        btn_emp = QPushButton("کارمندان")
        btn_emp.setCursor(Qt.PointingHandCursor)
        btn_emp.clicked.connect(lambda: self.win.show_page("employees"))
        head.addWidget(btn_reg)
        head.addWidget(btn_emp)
        v.addLayout(head)

        grid = QGridLayout()
        grid.setSpacing(12)
        self.card_emp, self.val_emp = _stat_card("کارمندان فعال")
        self.card_center, self.val_center = _stat_card("مراکز")
        self.card_records, self.val_records = _stat_card("رکوردهای ثبت‌شده")
        self.card_period, self.val_period = _stat_card("رکوردهای دوره جاری")
        for i, card in enumerate([self.card_emp, self.card_center, self.card_records, self.card_period]):
            grid.addWidget(card, 0, i)
        v.addLayout(grid)

        self.pending_note = QLabel("")
        self.pending_note.setObjectName("PageHint")
        v.addWidget(self.pending_note)

        cap = QLabel("آخرین رکوردهای ثبت‌شده")
        cap.setStyleSheet("font-weight:700; color:#334155; background:transparent;")
        v.addWidget(cap)

        self.table = QTableWidget(0, 6)
        self.table.setHorizontalHeaderLabels(["کارمند", "مرکز", "دوره", "ناخالص", "کسورات", "خالص پرداختی"])
        self.table.horizontalHeader().setSectionResizeMode(QHeaderView.Stretch)
        self.table.horizontalHeader().setSectionResizeMode(0, QHeaderView.ResizeMode.Stretch)
        self.table.verticalHeader().hide()
        self.table.setEditTriggers(QTableWidget.NoEditTriggers)
        self.table.setAlternatingRowColors(True)
        self.table.setSelectionBehavior(QTableWidget.SelectRows)
        v.addWidget(self.table, 1)

    def refresh(self):
        st = self.win.store.stats()
        fa = self.win.store.use_fa_digits()
        fd = J.fa_digits if fa else (lambda x: str(x))

        self.val_emp.setText(fd(st["employees_active"]))
        self.val_center.setText(fd(st["centers"]))
        self.val_records.setText(fd(st["records"]))
        self.val_period.setText(fd(st["records_current"]))

        jy, jm = st["period"]
        self.subtitle.setText("شرکت: %s — دوره جاری: %s" % (
            self.win.store.kv_company() or "—", J.period_label(jy, jm)))
        n = st["pending"]
        self.pending_note.setText(
            ("%s تغییر ثبت‌شده در انتظار همگام‌سازی است — پس از اتصال به اینترنت خودکار ارسال می‌شود." % fd(n))
            if n else "همه تغییرات با سایت همگام هستند.")

        rows = self.win.store.latest_records(10)
        self.table.setRowCount(len(rows))
        for r, rec in enumerate(rows):
            period = J.period_label(rec["jyear"], rec["jmonth"])
            deduct = (rec["insurance_deduct"] or 0) + (rec["other_deductions"] or 0)
            vals = [
                rec.get("employee_name") or self.win.store.employee_name(rec["user_id"]),
                self._center_name(rec["center_id"]),
                J.fa_digits(period) if fa else period,
                J.format_money(rec["gross"], fa),
                J.format_money(deduct, fa),
                J.format_money(rec["net"], fa),
            ]
            for c, val in enumerate(vals):
                item = QTableWidgetItem(str(val))
                if c >= 2:
                    item.setTextAlignment(Qt.AlignCenter)
                self.table.setItem(r, c, item)

    def _center_name(self, cid):
        c = self.win.store.center(cid)
        return c["name"] if c else "—"
