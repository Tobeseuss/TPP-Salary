# -*- coding: utf-8 -*-
"""تنظیمات — آدرس API و کلید (بدون هاردکد) + همگام‌سازی خودکار + تم."""

import os
import subprocess
import sys

from PySide6.QtCore import Qt, Signal
from PySide6.QtWidgets import (
    QCheckBox, QComboBox, QFormLayout, QFrame, QHBoxLayout, QLabel,
    QLineEdit, QPushButton, QSpinBox, QVBoxLayout,
)


class SettingsPage(QFrame):

    test_done = Signal(bool, str)

    def __init__(self, win):
        super().__init__()
        self.win = win
        v = QVBoxLayout(self)
        v.setContentsMargins(22, 20, 22, 20)
        v.setSpacing(12)

        title = QLabel("تنظیمات برنامه")
        title.setObjectName("PageTitle")
        v.addWidget(title)

        hint = QLabel(
            "آدرس API و کلید را از پیشخوان سایت بگیرید: تنظیمات ← تب «برنامه آفلاین (API)» ← ساخت کلید API.\n"
            "آدرس پیشنهادی: آدرس سایت (مثل http://localhost/wp) — برنامه مسیر REST را خودکار تشخیص می‌دهد."
        )
        hint.setObjectName("PageHint")
        hint.setWordWrap(True)
        v.addWidget(hint)

        panel = QFrame()
        panel.setObjectName("Panel")
        form = QFormLayout(panel)
        form.setContentsMargins(18, 16, 18, 16)
        form.setSpacing(12)

        self.inp_url = QLineEdit()
        self.inp_url.setPlaceholderText("http://localhost/wp")
        self.inp_url.setMinimumWidth(380)
        form.addRow("آدرس API سایت:", self.inp_url)

        key_row = QHBoxLayout()
        self.inp_key = QLineEdit()
        self.inp_key.setPlaceholderText("tppk_...")
        self.inp_key.setEchoMode(QLineEdit.Password)
        self.inp_key.setMinimumWidth(380)
        btn_show = QPushButton("نمایش")
        btn_show.setCheckable(True)
        btn_show.toggled.connect(
            lambda on: self.inp_key.setEchoMode(QLineEdit.Normal if on else QLineEdit.Password))
        key_row.addWidget(self.inp_key, 1)
        key_row.addWidget(btn_show)
        form.addRow("کلید API:", key_row)

        self.spin_interval = QSpinBox()
        self.spin_interval.setRange(30, 3600)
        self.spin_interval.setSuffix(" ثانیه")
        form.addRow("فاصله همگام‌سازی خودکار:", self.spin_interval)

        self.chk_auto = QCheckBox("همگام‌سازی خودکار در پس‌زمینه (در اولین فرصت سایت و برنامه همگام می‌شوند)")
        form.addRow("", self.chk_auto)

        # نسخه 1.7.1: همه اعداد انگلیسی — گزینه ارقام فارسی حذف شد.
        info_fa = QLabel("همه اعداد با ارقام انگلیسی نمایش داده می‌شوند (نسخه 1.7.1)")
        info_fa.setObjectName("PageHint")
        form.addRow("", info_fa)

        self.cmb_theme = QComboBox()
        self.cmb_theme.addItems(["روشن", "تیره"])
        form.addRow("حالت نمایش:", self.cmb_theme)
        v.addWidget(panel)

        btns = QHBoxLayout()
        btn_save = QPushButton("ذخیره تنظیمات")
        btn_save.setObjectName("Primary")
        btn_save.setCursor(Qt.PointingHandCursor)
        btn_save.clicked.connect(self.save)
        btn_test = QPushButton("تست اتصال")
        btn_test.setCursor(Qt.PointingHandCursor)
        btn_test.clicked.connect(self.test_connection)
        btns.addWidget(btn_save)
        btns.addWidget(btn_test)
        btns.addStretch(1)
        v.addLayout(btns)

        # نسخه 1.7.5: اطلاع‌رسانی پشتیبان‌گیری اکسل آنی + دسترسی سریع به پوشه بکاپ‌ها
        backup_hint = QLabel(
            "پشتیبان‌گیری خودکار اکسل: با هر ذخیره و هر اجرای برنامه، یک نسخه کامل از کارمندان "
            "و حقوق‌ها در پوشه «excel» کنار برنامه ذخیره می‌شود (۲۰۰ نسخه آخر نگه داشته می‌شود)."
        )
        backup_hint.setObjectName("PageHint")
        backup_hint.setWordWrap(True)
        v.addWidget(backup_hint)

        btns2 = QHBoxLayout()
        btn_folder = QPushButton("پوشه پشتیبان‌های اکسل")
        btn_folder.setCursor(Qt.PointingHandCursor)
        btn_folder.clicked.connect(self.open_backup_folder)
        btns2.addWidget(btn_folder)
        btns2.addStretch(1)
        v.addLayout(btns2)

        self.result_label = QLabel("")
        self.result_label.setObjectName("PageHint")
        self.result_label.setWordWrap(True)
        v.addWidget(self.result_label)
        v.addStretch(1)

        self.test_done.connect(self._on_test_done)

    def refresh(self):
        cfg = self.win.config
        self.inp_url.setText(str(cfg.get("api_url", "") or ""))
        self.inp_key.setText(str(cfg.get("api_key", "") or ""))
        self.spin_interval.setValue(max(30, int(cfg.get("sync_interval", 60) or 60)))
        self.chk_auto.setChecked(bool(cfg.get("auto_sync", True)))
        self.cmb_theme.setCurrentIndex(1 if cfg.get("theme", "light") == "dark" else 0)

    def save(self):
        cfg = self.win.config
        cfg.set("api_url", self.inp_url.text().strip())
        cfg.set("api_key", self.inp_key.text().strip())
        cfg.set("sync_interval", int(self.spin_interval.value()))
        cfg.set("auto_sync", bool(self.chk_auto.isChecked()))
        old_theme = str(cfg.get("theme", "light"))
        new_theme = "dark" if self.cmb_theme.currentIndex() == 1 else "light"
        cfg.set("theme", new_theme)
        self.win._restart_timer()
        if old_theme != new_theme:
            from PySide6.QtWidgets import QApplication
            from ..theme import apply_theme
            app = QApplication.instance()
            if app:
                apply_theme(app, new_theme)
        self.result_label.setText("✓ تنظیمات ذخیره شد.")
        self.win.update_pending()

    def test_connection(self):
        # ابتدا مقادیر فعلی فرم را (بدون ذخیره) برای تست اعمال کن
        self.win.config.set("api_url", self.inp_url.text().strip(), save=False)
        self.win.config.set("api_key", self.inp_key.text().strip(), save=False)
        self.result_label.setText("در حال بررسی اتصال…")
        import threading

        def worker():
            ok, msg, _data = self.win.api.status_text()
            self.test_done.emit(ok, msg)

        threading.Thread(target=worker, daemon=True).start()

    def _on_test_done(self, ok, msg):
        if ok:
            self.result_label.setText("✓ اتصال موفق: %s" % msg)
        else:
            self.result_label.setText("✗ اتصال ناموفق: %s" % msg)

    def open_backup_folder(self):
        """بازکردن پوشه excel کنار برنامه (پشتیبان‌های خودکار)."""
        from PySide6.QtWidgets import QMessageBox

        mgr = getattr(self.win, "backup_mgr", None)
        folder = str(mgr.folder) if mgr else os.path.join(self.win.app_dir, "excel")
        if mgr:
            mgr.ensure_folder()
        try:
            if os.name == "nt":
                os.startfile(folder)  # noqa: S606 — ویندوز
            elif sys.platform == "darwin":
                subprocess.Popen(["open", folder])
            else:
                subprocess.Popen(["xdg-open", folder])
        except Exception:
            QMessageBox.information(self, "پشتیبان‌های اکسل", "مسیر پوشه پشتیبان‌ها:\n%s" % folder)
