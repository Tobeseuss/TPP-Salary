# -*- coding: utf-8 -*-
"""همگام‌سازی — وضعیت اتصال، صف تغییرات آفلاین، لاگ رویدادها."""

from PySide6.QtCore import Qt
from PySide6.QtWidgets import (
    QFrame, QHBoxLayout, QHeaderView, QLabel, QPushButton, QTableWidget,
    QTableWidgetItem, QTabWidget, QTextBrowser, QVBoxLayout, QWidget,
)

from ... import jalali as J

STATUS_LABELS = {
    "pending": "در انتظار ارسال",
    "conflict": "تداخل با سرور",
    "error": "خطا",
}


class SyncPage(QFrame):

    def __init__(self, win):
        super().__init__()
        self.win = win
        v = QVBoxLayout(self)
        v.setContentsMargins(22, 20, 22, 20)
        v.setSpacing(12)

        title = QLabel("همگام‌سازی با سایت")
        title.setObjectName("PageTitle")
        v.addWidget(title)

        bar = QHBoxLayout()
        btn_sync = QPushButton("همگام‌سازی الآن")
        btn_sync.setObjectName("Primary")
        btn_sync.setCursor(Qt.PointingHandCursor)
        btn_sync.clicked.connect(self.win.start_sync)
        btn_test = QPushButton("بررسی اتصال")
        btn_test.setCursor(Qt.PointingHandCursor)
        btn_test.clicked.connect(self._test)
        self.status_label = QLabel("")
        self.status_label.setObjectName("PageHint")
        bar.addWidget(btn_sync)
        bar.addWidget(btn_test)
        bar.addWidget(self.status_label, 1)
        v.addLayout(bar)

        self.tabs = QTabWidget()
        v.addWidget(self.tabs, 1)

        # تب صف
        page_queue = QWidget()
        qv = QVBoxLayout(page_queue)
        qv.setContentsMargins(8, 8, 8, 8)
        self.queue_table = QTableWidget(0, 6)
        self.queue_table.setHorizontalHeaderLabels(
            ["موجودیت", "عملیات", "وضعیت", "تلاش", "زمان", "پیام خطا"])
        self.queue_table.verticalHeader().hide()
        self.queue_table.setEditTriggers(QTableWidget.NoEditTriggers)
        self.queue_table.horizontalHeader().setSectionResizeMode(QHeaderView.ResizeMode.Stretch)
        qv.addWidget(self.queue_table)
        note = QLabel("تغییرات آفلاین اینجا نگه داشته می‌شوند و به محض اتصال به سایت به‌ترتیب اعمال می‌شوند؛ "
                      "موارد «تداخل» یعنی روی سایت نسخه جدیدتری ثبت شده است.")
        note.setWordWrap(True)
        note.setObjectName("PageHint")
        qv.addWidget(note)
        self.tabs.addTab(page_queue, "صف تغییرات")

        # تب لاگ
        page_log = QWidget()
        lv = QVBoxLayout(page_log)
        lv.setContentsMargins(8, 8, 8, 8)
        self.log_view = QTextBrowser()
        lv.addWidget(self.log_view)
        self.tabs.addTab(page_log, "گزارش رویدادها")

    def refresh(self):
        self._load_queue()
        self._load_log()
        # وضعیت فعلی را از نوار اصلی می‌خوانیم (بدون تماس شبکه)
        self.status_label.setText(self.win.status_label.text())

    def _test(self):
        import threading
        self.status_label.setText("در حال بررسی اتصال…")

        def worker():
            ok, msg, _data = self.win.api.status_text()

            def apply():
                self.status_label.setText(("✓ %s" % msg) if ok else ("✗ %s" % msg))
            from PySide6.QtCore import QTimer
            QTimer.singleShot(0, apply)

        threading.Thread(target=worker, daemon=True).start()

    def _load_queue(self):
        rows = self.win.db.q("SELECT * FROM outbox WHERE status IN ('pending','conflict','error') ORDER BY id ASC LIMIT 400")
        self.queue_table.setRowCount(len(rows))
        for r, row in enumerate(rows):
            vals = [
                {"record": "رکورد حقوق", "employee": "کارمند", "center": "مرکز", "bank": "بانک"}.get(row["entity"], row["entity"]),
                row["action"],
                STATUS_LABELS.get(row["status"], row["status"]),
                J.fa_digits(row["attempts"]),
                J.fa_digits(row["created_at"]),
                row["error"] or "",
            ]
            for c, val in enumerate(vals):
                item = QTableWidgetItem(str(val))
                if c == 2 and row["status"] != "pending":
                    item.setForeground(Qt.red)
                self.queue_table.setItem(r, c, item)

    def _load_log(self):
        rows = self.win.db.q("SELECT * FROM sync_log ORDER BY id DESC LIMIT 200")
        lines = []
        for r in rows:
            icon = {"info": "•", "warn": "⚠", "error": "✗"}.get(r["level"], "•")
            lines.append("%s — %s %s" % (J.fa_digits(r["ts"]), icon, r["message"]))
        self.log_view.setPlainText("\n".join(lines) or "هنوز رویدادی ثبت نشده است.")

    def on_sync_result(self, result):
        """پس از پایان همگام‌سازی (از پنجره اصلی فراخوانی می‌شود)."""
        self._load_queue()
        self._load_log()
        if result.conflicts:
            from PySide6.QtWidgets import QMessageBox
            QMessageBox.warning(
                self, "تداخل در همگام‌سازی",
                "%s مورد تداخل دارد: روی سایت نسخه جدیدتری از رکورد ثبت شده بود.\n"
                "مورد را در سایت بررسی و در صورت نیاز اینجا دوباره ویرایش کنید." % J.fa_digits(len(result.conflicts)))
