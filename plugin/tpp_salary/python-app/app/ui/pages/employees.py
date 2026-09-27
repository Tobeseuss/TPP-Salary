# -*- coding: utf-8 -*-
"""کارمندان — جستجو، افزودن/ویرایش (پروفایل + مراکز)، قطع همکاری، حذف تکی/گروهی."""

from PySide6.QtCore import Qt
from PySide6.QtWidgets import (
    QCheckBox, QDialog, QDialogButtonBox, QFormLayout, QFrame, QHBoxLayout,
    QHeaderView, QLabel, QLineEdit, QListWidget, QListWidgetItem, QMessageBox,
    QPushButton, QTableWidget, QTableWidgetItem, QVBoxLayout,
)

from ... import jalali as J
from ...api_client import load_json


class EmployeeDialog(QDialog):
    """افزودن/ویرایش کارمند — شامل فیلدهای پروفایل و مراکز."""

    def __init__(self, win, emp=None):
        super().__init__(win)
        self.win = win
        self.emp = emp or {}
        self.setWindowTitle("ویرایش کارمند" if emp else "افزودن کارمند")
        self.setModal(True)
        self.setMinimumWidth(520)

        v = QVBoxLayout(self)
        v.setContentsMargins(18, 16, 18, 14)
        v.setSpacing(10)

        form = QFormLayout()
        form.setSpacing(10)
        self.inp_name = QLineEdit(str(self.emp.get("name", "")))
        self.inp_national = QLineEdit(str(self.emp.get("national", "")))
        self.inp_mobile = QLineEdit(str(self.emp.get("mobile", "")))
        form.addRow("نام و نام خانوادگی *:", self.inp_name)
        form.addRow("کد ملی:", self.inp_national)
        form.addRow("موبایل:", self.inp_mobile)

        # مراکز
        self.center_list = QListWidget()
        self.center_list.setMinimumHeight(90)
        sel_centers = [int(c) for c in (self.emp.get("centers") or [])]
        for c in win.store.centers():
            item = QListWidgetItem(c["name"])
            item.setData(Qt.UserRole, int(c["id"]))
            item.setFlags(item.flags() | Qt.ItemIsUserCheckable)
            self.center_list.addItem(item)
            item.setCheckState(Qt.Checked if int(c["id"]) in sel_centers else Qt.Unchecked)
        form.addRow("مراکز:", self.center_list)
        v.addLayout(form)

        # فیلدهای پروفایل (از سایت — آینده‌پذیر)
        profile_fields = load_json(win.db.kv_get("profile_fields", "[]"), []) or []
        profile = self.emp.get("profile", {}) or {}
        if profile_fields:
            cap = QLabel("فیلدهای پروفایل")
            cap.setStyleSheet("font-weight:700; color:#334155; background:transparent;")
            v.addWidget(cap)
            pf = QFormLayout()
            self.profile_inputs = {}
            for f in profile_fields:
                e = QLineEdit(str(profile.get(f["key"], f.get("default", "")) or ""))
                self.profile_inputs[f["key"]] = e
                pf.addRow("%s:" % f["label"], e)
            v.addLayout(pf)

        # وضعیت
        self.chk_terminated = QCheckBox("قطع همکاری (از فهرست‌های حقوق حذف می‌شود)")
        self.chk_terminated.setChecked(bool(self.emp.get("terminated")))
        v.addWidget(self.chk_terminated)

        bb = QDialogButtonBox(QDialogButtonBox.Ok | QDialogButtonBox.Cancel)
        bb.accepted.connect(self._accept)
        bb.rejected.connect(self.reject)
        bb.button(QDialogButtonBox.Ok).setText("ذخیره")
        bb.button(QDialogButtonBox.Cancel).setText("انصراف")
        v.addWidget(bb)

    def _accept(self):
        if not self.inp_name.text().strip():
            QMessageBox.warning(self, "کارمند", "نام و نام خانوادگی الزامی است.")
            return
        self.accept()

    def data(self):
        centers = []
        for i in range(self.center_list.count()):
            it = self.center_list.item(i)
            if it.checkState() == Qt.Checked:
                centers.append(int(it.data(Qt.UserRole)))
        d = {
            "id": int(self.emp.get("id") or 0),
            "name": self.inp_name.text().strip(),
            "national": self.inp_national.text().strip(),
            "mobile": self.inp_mobile.text().strip(),
            "terminated": 1 if self.chk_terminated.isChecked() else 0,
            "centers": centers,
            "profile": dict(self.emp.get("profile") or {}),
        }
        for key, e in getattr(self, "profile_inputs", {}).items():
            d["profile"][key] = e.text().strip()
        return d


class EmployeesPage(QFrame):

    def __init__(self, win):
        super().__init__()
        self.win = win
        v = QVBoxLayout(self)
        v.setContentsMargins(22, 20, 22, 20)
        v.setSpacing(12)

        title = QLabel("کارمندان")
        title.setObjectName("PageTitle")
        v.addWidget(title)

        bar = QHBoxLayout()
        self.inp_search = QLineEdit()
        self.inp_search.setPlaceholderText("جستجو: نام، نام کاربری، کد ملی، موبایل…")
        self.inp_search.returnPressed.connect(self.refresh)
        self.chk_terminated = QCheckBox("نمایش قطع‌همکاری‌ها")
        self.chk_terminated.stateChanged.connect(lambda *_: self.refresh())
        btn_add = QPushButton("+ افزودن کارمند")
        btn_add.setObjectName("Primary")
        btn_add.setCursor(Qt.PointingHandCursor)
        btn_add.clicked.connect(self._add)
        bar.addWidget(self.inp_search, 2)
        bar.addWidget(self.chk_terminated)
        bar.addWidget(btn_add)
        v.addLayout(bar)

        self.table = QTableWidget(0, 6)
        self.table.setHorizontalHeaderLabels(["نام و نام خانوادگی", "نام کاربری", "کد ملی", "موبایل", "مراکز", "وضعیت"])
        self.table.verticalHeader().hide()
        self.table.setEditTriggers(QTableWidget.NoEditTriggers)
        self.table.setAlternatingRowColors(True)
        self.table.setSelectionBehavior(QTableWidget.SelectRows)
        self.table.horizontalHeader().setSectionResizeMode(QHeaderView.ResizeMode.Stretch)
        self.table.doubleClicked.connect(lambda *_: self._edit())
        v.addWidget(self.table, 1)

        foot = QHBoxLayout()
        btn_edit = QPushButton("ویرایش")
        btn_edit.setCursor(Qt.PointingHandCursor)
        btn_edit.clicked.connect(self._edit)
        btn_term = QPushButton("قطع همکاری")
        btn_term.setObjectName("Danger")
        btn_term.setCursor(Qt.PointingHandCursor)
        btn_term.clicked.connect(lambda: self._toggle(True))
        btn_cont = QPushButton("ادامه همکاری")
        btn_cont.setCursor(Qt.PointingHandCursor)
        btn_cont.clicked.connect(lambda: self._toggle(False))
        btn_del = QPushButton("حذف انتخاب‌شده‌ها (فقط نقش کارمندی)")
        btn_del.setObjectName("Danger")
        btn_del.setCursor(Qt.PointingHandCursor)
        btn_del.clicked.connect(self._delete)
        foot.addWidget(btn_edit)
        foot.addWidget(btn_term)
        foot.addWidget(btn_cont)
        foot.addStretch(1)
        foot.addWidget(btn_del)
        v.addLayout(foot)

    def refresh(self):
        rows = self.win.store.employees(
            search=self.inp_search.text(),
            include_terminated=self.chk_terminated.isChecked())
        self.table.setRowCount(len(rows))
        fa = self.win.store.use_fa_digits()
        for r, e in enumerate(rows):
            center_names = "، ".join(
                (self.win.store.center(c)["name"] if self.win.store.center(c) else "—")
                for c in e["centers"]) or "—"
            vals = [
                e["name"], e.get("login") or "—",
                J.fa_digits(e.get("national") or "—") if fa else (e.get("national") or "—"),
                J.fa_digits(e.get("mobile") or "—") if fa else (e.get("mobile") or "—"),
                center_names,
                "قطع همکاری" if e["terminated"] else "فعال",
            ]
            for c, val in enumerate(vals):
                item = QTableWidgetItem(str(val))
                item.setData(Qt.UserRole, e["id"])
                if c == 5:
                    item.setForeground(Qt.red if e["terminated"] else Qt.darkGreen)
                    item.setTextAlignment(Qt.AlignCenter)
                self.table.setItem(r, c, item)

    def _current(self):
        row = self.table.currentRow()
        if row < 0:
            return None
        return self.table.item(row, 0).data(Qt.UserRole)

    def _add(self):
        dlg = EmployeeDialog(self.win)
        if dlg.exec() == QDialog.Accepted:
            try:
                self.win.store.save_employee(dlg.data())
                self.win.update_pending()
                self.refresh()
            except Exception as exc:  # noqa: BLE001
                QMessageBox.warning(self, "کارمند", str(exc))

    def _edit(self):
        eid = self._current()
        if eid is None:
            QMessageBox.information(self, "ویرایش", "ابتدا یک کارمند را انتخاب کنید.")
            return
        emp = self.win.store.employee(eid)
        if not emp:
            return
        dlg = EmployeeDialog(self.win, emp)
        if dlg.exec() == QDialog.Accepted:
            try:
                self.win.store.save_employee(dlg.data())
                self.win.update_pending()
                self.refresh()
            except Exception as exc:  # noqa: BLE001
                QMessageBox.warning(self, "کارمند", str(exc))

    def _toggle(self, terminate):
        eid = self._current()
        if eid is None:
            QMessageBox.information(self, "وضعیت", "ابتدا یک کارمند را انتخاب کنید.")
            return
        emp = self.win.store.employee(eid)
        if not emp or int(emp.get("id", 0)) < 0:
            QMessageBox.warning(self, "وضعیت", "این کارمند هنوز به سایت همگام نشده است؛ پس از همگام‌سازی قابل تغییر وضعیت است.")
            return
        self.win.store.set_terminated(eid, terminate)
        self.win.update_pending()
        self.refresh()

    def _delete(self):
        row = self.table.currentRow()
        if row < 0:
            QMessageBox.information(self, "حذف", "ابتدا یک کارمند را انتخاب کنید.")
            return
        eid = self.table.item(row, 0).data(Qt.UserRole)
        if QMessageBox.question(
                self, "حذف کارمند",
                "نقش کارمندی این شخص حذف شود؟ (حساب کاربری وردپرس و رکوردهای ثبت‌شده حفظ می‌شوند — هم‌سان با سایت)",
                QMessageBox.Yes | QMessageBox.No) != QMessageBox.Yes:
            return
        self.win.store.delete_employee(eid)
        self.win.update_pending()
        self.refresh()
