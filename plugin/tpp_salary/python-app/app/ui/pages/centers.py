# -*- coding: utf-8 -*-
"""مراکز و بانک‌ها — افزودن، جستجو، حذف تکی/گروهی."""

from PySide6.QtCore import Qt
from PySide6.QtWidgets import (
    QHBoxLayout, QLineEdit, QListWidget, QListWidgetItem, QMessageBox,
    QPushButton, QVBoxLayout, QWidget,
)


class _ListPane(QWidget):

    def __init__(self, win, kind, title):
        super().__init__()
        self.win = win
        self.kind = kind  # center | bank
        v = QVBoxLayout(self)
        v.setContentsMargins(0, 0, 0, 0)
        v.setSpacing(8)

        cap = QLabelFixed(title)
        v.addWidget(cap)

        self.inp_add = QLineEdit()
        self.inp_add.setPlaceholderText("نام جدید…")
        self.inp_add.returnPressed.connect(self._add)
        btn_add = QPushButton("+ افزودن")
        btn_add.setObjectName("Primary")
        btn_add.setCursor(Qt.PointingHandCursor)
        btn_add.clicked.connect(self._add)
        row = QHBoxLayout()
        row.addWidget(self.inp_add, 1)
        row.addWidget(btn_add)
        v.addLayout(row)

        self.inp_search = QLineEdit()
        self.inp_search.setPlaceholderText("جستجو…")
        self.inp_search.textChanged.connect(lambda *_: self.refresh())
        v.addWidget(self.inp_search)

        self.listw = QListWidget()
        self.listw.setSelectionMode(QListWidget.MultiSelection)
        v.addWidget(self.listw, 1)

        foot = QHBoxLayout()
        btn_del = QPushButton("حذف انتخاب‌شده‌ها")
        btn_del.setObjectName("Danger")
        btn_del.setCursor(Qt.PointingHandCursor)
        btn_del.clicked.connect(self._delete)
        foot.addStretch(1)
        foot.addWidget(btn_del)
        v.addLayout(foot)

    def _items(self):
        if self.kind == "center":
            return self.win.store.centers()
        return self.win.store.banks()

    def _save(self, item_id, name):
        if self.kind == "center":
            self.win.store.save_center(item_id, name)
        else:
            self.win.store.save_bank(item_id, name)

    def _delete_items(self, ids):
        for i in ids:
            if self.kind == "center":
                self.win.store.delete_center(i)
            else:
                self.win.store.delete_bank(i)

    def _add(self):
        name = self.inp_add.text().strip()
        if not name:
            return
        try:
            self._save(0, name)
        except Exception as exc:  # noqa: BLE001
            QMessageBox.warning(self, "خطا", str(exc))
            return
        self.inp_add.clear()
        self.win.update_pending()
        self.refresh()

    def _delete(self):
        ids = [int(it.data(Qt.UserRole)) for it in self.listw.selectedItems()]
        if not ids:
            QMessageBox.information(self, "حذف", "ابتدا موارد را انتخاب کنید.")
            return
        if QMessageBox.question(
                self, "حذف", "%s مورد حذف شود؟" % J_fa(len(ids)),
                QMessageBox.Yes | QMessageBox.No) != QMessageBox.Yes:
            return
        self._delete_items(ids)
        self.win.update_pending()
        self.refresh()

    def refresh(self):
        q = self.inp_search.text().strip().lower()
        self.listw.clear()
        for it in self._items():
            name = it["name"]
            if q and q not in name.lower():
                continue
            item = QListWidgetItem(name + ("  (تغییر محلی)" if it.get("pending") else ""))
            item.setData(Qt.UserRole, int(it["id"]))
            self.listw.addItem(item)


def QLabelFixed(text):
    from PySide6.QtWidgets import QLabel
    lab = QLabel(text)
    lab.setStyleSheet("font-weight:800; font-size:15px; background:transparent; color:#0f172a;")
    return lab


def J_fa(x):
    from ... import jalali as J
    return J.fa_digits(x)


class CentersPage(QWidget):

    def __init__(self, win):
        super().__init__()
        v = QVBoxLayout(self)
        v.setContentsMargins(22, 20, 22, 20)
        v.setSpacing(12)
        title = QLabelFixed("مراکز و بانک‌ها")
        title.setObjectName("PageTitle")
        v.addWidget(title)
        h = QHBoxLayout()
        h.setSpacing(16)
        self.pane_center = _ListPane(win, "center", "مراکز (پروژه/کارگاه)")
        self.pane_bank = _ListPane(win, "bank", "بانک‌ها")
        h.addWidget(self.pane_center, 1)
        h.addWidget(self.pane_bank, 1)
        v.addLayout(h)

    def refresh(self):
        self.pane_center.refresh()
        self.pane_bank.refresh()
