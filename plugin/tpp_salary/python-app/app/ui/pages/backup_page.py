# -*- coding: utf-8 -*-
"""پشتیبان‌گیری و بازگردانی — نسخه 1.8.0.

- پشتیبان JSON کامل و بسته ZIP (full.json + employees.json + records.json)
  — قالب هم‌سان با بکاپ افزونه؛ بازگردانی از هر دو (خودی یا افزونه) کار می‌کند.
- پشتیبان اکسل آنی (1.7.5) سر جایش است؛ فهرست هر دو پوشه اینجا دیده می‌شود.
- بازگردانی: جایگزینی داده محلی با محتوای فایل بکاپ؛ کارمندان با
  کد ملی/شناسه/نام تطبیق یا ساخته می‌شوند و رکوردها به واقعیت نگاشت می‌شوند.
"""

import os

from PySide6.QtCore import Qt
from PySide6.QtWidgets import (
    QFileDialog, QFrame, QHBoxLayout, QHeaderView, QLabel, QMessageBox,
    QPushButton, QTableWidget, QTableWidgetItem, QVBoxLayout,
)

from ... import jalali as J


class BackupPage(QFrame):

    def __init__(self, win):
        super().__init__()
        self.win = win

        v = QVBoxLayout(self)
        v.setContentsMargins(22, 20, 22, 20)
        v.setSpacing(12)

        title = QLabel("پشتیبان‌گیری و بازگردانی")
        title.setObjectName("PageTitle")
        v.addWidget(title)

        hint = QLabel(
            "پشتیبان JSON/ZIP شامل همه کارمندان، مراکز، بانک‌ها، فیلدها و رکوردهای حقوق است و با بکاپ افزونه سازگار است. "
            "علاوه بر این، پشتیبان اکسل آنی با هر ذخیره در پوشه «excel» ساخته می‌شود (نسخه 1.7.5). "
            "بازگردانی، داده‌های محلی را با محتوای فایل بکاپ جایگزین می‌کند؛ پیش از ادامه از وضعیت فعلی پشتیبان بگیرید.")
        hint.setObjectName("PageHint")
        hint.setWordWrap(True)
        v.addWidget(hint)

        # --- ساخت پشتیبان ---
        row1 = QHBoxLayout()
        btn_json = QPushButton("ایجاد پشتیبان JSON (کامل)")
        btn_json.setObjectName("Primary")
        btn_json.setCursor(Qt.PointingHandCursor)
        btn_json.clicked.connect(self.make_json)
        btn_zip = QPushButton("ایجاد بسته ZIP (کامل)")
        btn_zip.setObjectName("Primary")
        btn_zip.setCursor(Qt.PointingHandCursor)
        btn_zip.clicked.connect(self.make_zip)
        btn_open = QPushButton("باز کردن پوشه پشتیبان‌ها")
        btn_open.setCursor(Qt.PointingHandCursor)
        btn_open.clicked.connect(self.open_folder)
        row1.addWidget(btn_json)
        row1.addWidget(btn_zip)
        row1.addStretch(1)
        row1.addWidget(btn_open)
        v.addLayout(row1)

        # --- بازگردانی ---
        row2 = QHBoxLayout()
        btn_restore = QPushButton("بازگردانی از فایل پشتیبان (JSON / ZIP)")
        btn_restore.setObjectName("Danger")
        btn_restore.setCursor(Qt.PointingHandCursor)
        btn_restore.clicked.connect(self.restore_from_file)
        row2.addWidget(btn_restore)
        row2.addStretch(1)
        v.addLayout(row2)

        self.info = QLabel("")
        self.info.setObjectName("PageHint")
        self.info.setWordWrap(True)
        v.addWidget(self.info)

        # --- فهرست پشتیبان‌ها ---
        lbl = QLabel("آخرین فایل‌های پشتیبان (پوشه backups و excel کنار برنامه)")
        lbl.setObjectName("PageHint")
        v.addWidget(lbl)

        self.table = QTableWidget(0, 4)
        self.table.setHorizontalHeaderLabels(["فایل", "نوع", "حجم", "زمان"])
        self.table.verticalHeader().hide()
        self.table.setEditTriggers(QTableWidget.NoEditTriggers)
        self.table.setAlternatingRowColors(True)
        h = self.table.horizontalHeader()
        h.setSectionResizeMode(QHeaderView.ResizeMode.Stretch)
        v.addWidget(self.table, 1)

        btn_reload = QPushButton("به‌روزرسانی فهرست")
        btn_reload.setCursor(Qt.PointingHandCursor)
        btn_reload.clicked.connect(self.reload_list)
        v.addWidget(btn_reload, 0, Qt.AlignRight)

    # ---------------- ساخت ----------------

    def _save_path(self, title, default, filt):
        path, _ = QFileDialog.getSaveFileName(self, title, default, filt)
        return path

    def make_json(self):
        path = self._save_path("ذخیره پشتیبان JSON", "tpp-backup.json", "JSON (*.json)")
        if not path:
            return
        try:
            out = self.win.backup_core.create_json(path)
            QMessageBox.information(self, "پشتیبان‌گیری", "پشتیبان ساخته شد:\n%s" % out)
        except Exception as exc:  # noqa: BLE001
            QMessageBox.warning(self, "پشتیبان‌گیری", "ساخت پشتیبان ناموفق: %s" % str(exc)[:160])
        self.reload_list()

    def make_zip(self):
        path = self._save_path("ذخیره بسته ZIP", "tpp-backup.zip", "ZIP (*.zip)")
        if not path:
            return
        try:
            out = self.win.backup_core.create_zip(path)
            QMessageBox.information(self, "پشتیبان‌گیری", "بسته ZIP ساخته شد:\n%s" % out)
        except Exception as exc:  # noqa: BLE001
            QMessageBox.warning(self, "پشتیبان‌گیری", "ساخت بسته ناموفق: %s" % str(exc)[:160])
        self.reload_list()

    # ---------------- بازگردانی ----------------

    def restore_from_file(self):
        path, _ = QFileDialog.getOpenFileName(
            self, "انتخاب فایل پشتیبان", "",
            "Backup (*.json *.zip);;JSON (*.json);;ZIP (*.zip)")
        if not path:
            return
        try:
            data = self.win.backup_core.load_backup(path)
            desc = self.win.backup_core.describe(data)
        except Exception as exc:  # noqa: BLE001
            QMessageBox.warning(self, "بازگردانی", "خواندن فایل ناموفق:\n%s" % str(exc)[:160])
            return
        msg = (
            "بازگردانی، داده‌های فعلی برنامه را با محتوای فایل پشتیبان جایگزین می‌کند.\n\n"
            "نسخه بکاپ: %s\nنوع: %s\nزمان بکاپ: %s\n"
            "کارمندان: %s — رکورد حقوق: %s — مرکز: %s — بانک: %s — فیلد: %s\n\n"
            "تغییرات ارسال‌نشده در صف همگام‌سازی حذف می‌شوند. پیش از ادامه، از وضعیت فعلی پشتیبان بگیرید.\n"
            "ادامه می‌دهید؟" % (
                desc["version"], desc["kind"], desc["stamp"] or "—",
                J.fa_digits(desc["profiles"]), J.fa_digits(desc["records"]),
                J.fa_digits(desc["centers"]), J.fa_digits(desc["banks"]), J.fa_digits(desc["fields"]))
        )
        if QMessageBox.question(self, "بازگردانی پشتیبان", msg,
                                QMessageBox.Yes | QMessageBox.No) != QMessageBox.Yes:
            return
        try:
            summary = self.win.backup_core.restore(data)
        except Exception as exc:  # noqa: BLE001
            QMessageBox.warning(self, "بازگردانی", "بازگردانی ناموفق: %s" % str(exc)[:200])
            return
        parts = []
        if summary["records"]:
            parts.append("%s رکورد حقوق" % J.fa_digits(summary["records"]))
        if summary["users_created"]:
            parts.append("%s کارمند جدید" % J.fa_digits(summary["users_created"]))
        if summary["users_matched"]:
            parts.append("%s کارمند تطبیق‌یافته" % J.fa_digits(summary["users_matched"]))
        if summary["centers"]:
            parts.append("%s مرکز" % J.fa_digits(summary["centers"]))
        if summary["banks"]:
            parts.append("%s بانک" % J.fa_digits(summary["banks"]))
        if summary["fields"]:
            parts.append("%s فیلد" % J.fa_digits(summary["fields"]))
        self.info.setText("بازگردانی انجام شد: " + ("؛ ".join(parts) or "تغییری اعمال نشد") + ".")
        QMessageBox.information(self, "بازگردانی", "بازگردانی انجام شد.\n%s" % ("؛ ".join(parts) or ""))
        self.win.update_pending()
        self.win.bus.data_changed.emit()
        self.reload_list()

    # ---------------- فهرست ----------------

    def reload_list(self):
        items = []
        bc = self.win.backup_core
        for p in bc.backup_files():
            items.append((p, "JSON/ZIP"))
        excel_dir = os.path.join(self.win.app_dir, "excel")
        try:
            names = sorted(n for n in os.listdir(excel_dir) if n.endswith(".xlsx"))
        except Exception:
            names = []
        for n in names[-30:]:
            items.append((os.path.join(excel_dir, n), "اکسل آنی"))
        items = items[-80:]
        items.reverse()
        self.table.setRowCount(len(items))
        for r, (p, kind) in enumerate(items):
            st = os.stat(p)
            self.table.setItem(r, 0, QTableWidgetItem(os.path.basename(p)))
            self.table.setItem(r, 1, QTableWidgetItem(kind))
            it = QTableWidgetItem(self._size(st.st_size))
            it.setTextAlignment(Qt.AlignCenter)
            self.table.setItem(r, 2, it)
            import datetime
            mt = datetime.datetime.fromtimestamp(st.st_mtime).strftime("%Y-%m-%d %H:%M:%S")
            it2 = QTableWidgetItem(mt)
            it2.setTextAlignment(Qt.AlignCenter)
            self.table.setItem(r, 3, it2)

    @staticmethod
    def _size(n):
        for unit in ("B", "KB", "MB", "GB"):
            if n < 1024:
                return ("%d %s" % (n, unit)) if unit == "B" else ("%.1f %s" % (n, unit))
            n /= 1024.0
        return "%.1f GB" % n

    def open_folder(self):
        folder = self.win.backup_core.folder
        try:
            os.makedirs(folder, exist_ok=True)
        except Exception:
            pass
        try:
            if os.name == "nt":
                os.startfile(folder)  # noqa: S606
            elif hasattr(os, "uname") and os.uname().sysname == "Darwin":
                import subprocess
                subprocess.Popen(["open", folder])
            else:
                import subprocess
                subprocess.Popen(["xdg-open", folder])
        except Exception as exc:  # noqa: BLE001
            QMessageBox.warning(self, "پوشه", "باز کردن پوشه ناموفق: %s\n%s" % (str(exc)[:80], folder))
