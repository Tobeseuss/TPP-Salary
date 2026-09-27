# -*- coding: utf-8 -*-
"""اجرای برنامه — QApplication با چیدمان راست‌به‌چپ."""

import os
import sys


def run(app_dir):
    os.environ.setdefault("QT_AUTO_SCREEN_SCALE_FACTOR", "1")

    from PySide6.QtCore import Qt
    from PySide6.QtWidgets import QApplication

    from .ui.theme import apply_theme

    QApplication.setApplicationName("TPP Salary Desktop")
    app = QApplication(sys.argv)

    # چیدمان راست‌به‌چپ کامل (سایدبار سمت راست، جدول‌ها RTL)
    app.setLayoutDirection(Qt.RightToLeft)

    from .config import Config
    cfg = Config(app_dir)
    apply_theme(app, str(cfg.get("theme", "light")))

    from .ui.app_window import MainWindow
    win = MainWindow(app_dir)
    win.show()
    sys.exit(app.exec())
