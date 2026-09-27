# -*- coding: utf-8 -*-
"""لیست حقوق — فیلتر، جستجو، صفحه‌بندی، حذف تکی/گروهی، ویرایش."""

from PySide6.QtCore import Qt
from PySide6.QtWidgets import (
    QCheckBox, QComboBox, QFrame, QHBoxLayout, QHeaderView, QLabel,
    QLineEdit, QMessageBox, QPushButton, QTableWidget, QTableWidgetItem,
    QVBoxLayout, QWidget,
)

from ... import jalali as J

PER_PAGE = 15


class RecordsPage(QFrame):

    def __init__(self, win):
        super().__init__()
        self.win = win
        self.page = 1
        self.pages = 1
        self.total = 0
        self._updating = False

        v = QVBoxLayout(self)
        v.setContentsMargins(22, 20, 22, 20)
        v.setSpacing(12)

        title = QLabel("لیست حقوق‌های ثبت‌شده")
        title.setObjectName("PageTitle")
        v.addWidget(title)

        # فیلترها
        bar = QHBoxLayout()
        self.inp_search = QLineEdit()
        self.inp_search.setPlaceholderText("جستجو: نام کارمند یا کد ملی…")
        self.inp_search.returnPressed.connect(self._reset_and_load)
        self.cmb_year = QComboBox()
        self.cmb_month = QComboBox()
        self.cmb_center = QComboBox()
        self._fill_filters()
        btn_filter = QPushButton("اعمال")
        btn_filter.setCursor(Qt.PointingHandCursor)
        btn_filter.clicked.connect(self._reset_and_load)
        bar.addWidget(self.inp_search, 2)
        bar.addWidget(self.cmb_year, 1)
        bar.addWidget(self.cmb_month, 1)
        bar.addWidget(self.cmb_center, 1)
        bar.addWidget(btn_filter)
        v.addLayout(bar)

        # اقدام گروهی
        bulk = QHBoxLayout()
        self.chk_all = QCheckBox("انتخاب همه ردیف‌های این صفحه")
        self.chk_all.stateChanged.connect(lambda *_: self._select_all())
        self.btn_del_bulk = QPushButton("حذف موارد انتخاب‌شده")
        self.btn_del_bulk.setObjectName("Danger")
        self.btn_del_bulk.setCursor(Qt.PointingHandCursor)
        self.btn_del_bulk.clicked.connect(self._delete_selected)
        bulk.addWidget(self.chk_all)
        bulk.addStretch(1)
        bulk.addWidget(self.btn_del_bulk)
        v.addLayout(bulk)

        # جدول
        self.table = QTableWidget(0, 8)
        self.table.setHorizontalHeaderLabels(
            ["✓", "کارمند", "مرکز", "دوره", "ناخالص", "مشمول بیمه", "کسورات", "خالص پرداختی"])
        self.table.verticalHeader().hide()
        self.table.setEditTriggers(QTableWidget.NoEditTriggers)
        self.table.setAlternatingRowColors(True)
        self.table.setSelectionBehavior(QTableWidget.SelectRows)
        h = self.table.horizontalHeader()
        h.setSectionResizeMode(QHeaderView.ResizeMode.Stretch)
        h.setSectionResizeMode(0, QHeaderView.ResizeMode.Fixed)
        self.table.setColumnWidth(0, 34)
        v.addWidget(self.table, 1)

        # ردیف اقدام + صفحه‌بندی
        foot = QHBoxLayout()
        self.btn_edit = QPushButton("ویرایش رکورد انتخابی")
        self.btn_edit.setCursor(Qt.PointingHandCursor)
        self.btn_edit.clicked.connect(self._edit_selected)
        self.btn_del_one = QPushButton("حذف رکورد انتخابی")
        self.btn_del_one.setObjectName("Danger")
        self.btn_del_one.setCursor(Qt.PointingHandCursor)
        self.btn_del_one.clicked.connect(self._delete_one_selected)
        foot.addWidget(self.btn_edit)
        foot.addWidget(self.btn_del_one)
        foot.addStretch(1)
        self.lbl_pages = QLabel("")
        self.lbl_pages.setObjectName("PageHint")
        btn_prev = QPushButton("صفحه قبل")
        btn_prev.clicked.connect(lambda: self._go(-1))
        btn_next = QPushButton("صفحه بعد")
        btn_next.clicked.connect(lambda: self._go(1))
        foot.addWidget(self.lbl_pages)
        foot.addWidget(btn_prev)
        foot.addWidget(btn_next)
        v.addLayout(foot)

        self.table.cellDoubleClicked.connect(lambda *_: self._edit_selected())

    # ---------------- فیلتر ----------------

    def _fill_filters(self):
        self.cmb_year.addItem("همه سال‌ها", 0)
        for y in range(1399, 1407):
            self.cmb_year.addItem(J.fa_digits(y), y)
        self.cmb_month.addItem("همه ماه‌ها", 0)
        for m in range(1, 13):
            self.cmb_month.addItem(J.month_name(m), m)
        self.cmb_center.addItem("همه مراکز", 0)

    def refresh(self):
        self._fill_centers()
        self._load()

    def _fill_centers(self):
        cur = self.cmb_center.currentData() or 0
        self.cmb_center.blockSignals(True)
        self.cmb_center.clear()
        self.cmb_center.addItem("همه مراکز", 0)
        for c in self.win.store.centers():
            self.cmb_center.addItem(c["name"], c["id"])
        idx = self.cmb_center.findData(cur)
        self.cmb_center.setCurrentIndex(max(0, idx))
        self.cmb_center.blockSignals(False)

    def _reset_and_load(self):
        self.page = 1
        self._load()

    def _go(self, delta):
        newp = self.page + delta
        if 1 <= newp <= self.pages:
            self.page = newp
            self._load()

    # ---------------- داده ----------------

    def _load(self):
        self._updating = True
        rows, self.total, self.page, self.pages = self.win.store.records(
            search=self.inp_search.text(),
            jyear=self.cmb_year.currentData() or 0,
            jmonth=self.cmb_month.currentData() or 0,
            center_id=self.cmb_center.currentData() or 0,
            page=self.page, per_page=PER_PAGE,
        )
        fa = self.win.store.use_fa_digits()
        fd = J.fa_digits if fa else (lambda x: str(x))
        self.table.setRowCount(len(rows))
        for r, rec in enumerate(rows):
            chk = QCheckBox()
            chk.setProperty("rec_id", rec["id"])
            cell_w = QWidget()
            cl = QHBoxLayout(cell_w)
            cl.setContentsMargins(0, 0, 0, 0)
            cl.setAlignment(Qt.AlignCenter)
            cl.addWidget(chk)
            self.table.setCellWidget(r, 0, cell_w)
            deduct = (rec["insurance_deduct"] or 0) + (rec["other_deductions"] or 0)
            vals = [
                rec.get("employee_name") or self.win.store.employee_name(rec["user_id"]),
                self._center_name(rec["center_id"]),
                J.period_label(rec["jyear"], rec["jmonth"]),
                J.format_money(rec["gross"], fa),
                J.format_money(rec["insurable"], fa),
                J.format_money(deduct, fa),
                J.format_money(rec["net"], fa),
            ]
            for c, val in enumerate(vals):
                item = QTableWidgetItem(str(val))
                if c >= 1:
                    item.setTextAlignment(Qt.AlignCenter)
                self.table.setItem(r, c + 1, item)
            self.table.item(r, 1).setData(Qt.UserRole, rec["id"])
        self.lbl_pages.setText("صفحه %s از %s — مجموع: %s رکورد" % (
            fd(self.page), fd(self.pages), fd(self.total)))
        self.chk_all.setChecked(False)
        self._updating = False

    def _center_name(self, cid):
        c = self.win.store.center(cid)
        return c["name"] if c else "—"

    def _selected_ids(self):
        ids = []
        for r in range(self.table.rowCount()):
            w = self.table.cellWidget(r, 0)
            if not w:
                continue
            chk = w.findChild(QCheckBox)
            if chk and chk.isChecked():
                ids.append(int(chk.property("rec_id")))
        return ids

    def _select_all(self):
        checked = self.chk_all.isChecked()
        for r in range(self.table.rowCount()):
            w = self.table.cellWidget(r, 0)
            if not w:
                continue
            chk = w.findChild(QCheckBox)
            if chk:
                chk.setChecked(checked)

    # ---------------- اقدامات ----------------

    def _edit_selected(self):
        row = self.table.currentRow()
        if row < 0:
            QMessageBox.information(self, "ویرایش", "ابتدا یک رکورد را انتخاب کنید (کلیک روی ردیف یا دوبار کلیک روی آن).")
            return
        rec_id = self.table.item(row, 1).data(Qt.UserRole)
        rec = self.win.store.record(rec_id)
        if not rec:
            return
        self.win.pages["register"].open_edit(rec["user_id"], rec["center_id"], rec["jyear"], rec["jmonth"])

    def _delete_selected(self):
        ids = self._selected_ids()
        if not ids:
            QMessageBox.information(self, "حذف", "ابتدا ردیف‌های موردنظر را تیک بزنید.")
            return
        if QMessageBox.question(
                self, "حذف رکوردها",
                "%s رکورد حذف شود؟ (تغییر پس از همگام‌سازی روی سایت هم اعمال می‌شود)" % J.fa_digits(len(ids)),
                QMessageBox.Yes | QMessageBox.No) != QMessageBox.Yes:
            return
        for rid in ids:
            self.win.store.delete_record(rid)
        self.win.update_pending()
        self._load()

    def _delete_one_selected(self):
        row = self.table.currentRow()
        if row < 0:
            QMessageBox.information(self, "حذف", "ابتدا یک رکورد را انتخاب کنید (کلیک روی ردیف).")
            return
        rec_id = self.table.item(row, 1).data(Qt.UserRole)
        if rec_id is None:
            return
        self._delete_one(int(rec_id))

    def _delete_one(self, rec_id):
        if QMessageBox.question(self, "حذف رکورد", "این رکورد حذف شود؟", QMessageBox.Yes | QMessageBox.No) != QMessageBox.Yes:
            return
        self.win.store.delete_record(rec_id)
        self.win.update_pending()
        self._load()
