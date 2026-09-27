# -*- coding: utf-8 -*-
"""پنجره اصلی — سایدبار ناوبری + صفحات + نوار وضعیت اتصال/همگام‌سازی."""

import threading

from PySide6.QtCore import QObject, Qt, QTimer, Signal
from PySide6.QtWidgets import (
    QButtonGroup, QFrame, QHBoxLayout, QLabel, QMainWindow, QPushButton,
    QStackedWidget, QVBoxLayout, QWidget,
)

from ..api_client import ApiClient
from ..database import Database
from ..store import Store
from ..sync_engine import SyncEngine
from ..excel_backup import ExcelBackupManager
from .. import jalali as J
from .pages.dashboard import DashboardPage
from .pages.register import RegisterPage
from .pages.records import RecordsPage
from .pages.employees import EmployeesPage
from .pages.centers import CentersPage
from .pages.reports import ReportsPage
from .pages.sync_page import SyncPage
from .pages.settings_page import SettingsPage


class Bus(QObject):
    """مغزی ارتباطی صفحات — تغییر داده/وضعیت همگام‌سازی."""

    data_changed = Signal()
    sync_state = Signal(str, str)  # (dot_class, text)


class MainWindow(QMainWindow):

    NAV = [
        ("dashboard", "داشبورد"),
        ("register", "ثبت حقوق"),
        ("records", "لیست حقوق"),
        ("employees", "کارمندان"),
        ("centers", "مراکز و بانک‌ها"),
        ("reports", "گزارش‌ها"),
        ("sync", "همگام‌سازی"),
        ("settings", "تنظیمات"),
    ]

    def __init__(self, app_dir):
        super().__init__()
        self.app_dir = app_dir
        self.db = Database(app_dir)
        self.store = Store(self.db)
        self.config_api = None  # set by main.run
        self.bus = Bus()
        self.sync_busy = False

        from ..config import Config
        self.config = Config(app_dir)
        self.api = ApiClient(self.config)
        self.engine = SyncEngine(self.db, self.store, self.api, self.config)

        # نسخه 1.7.5: پشتیبان‌گیری اکسل آنی — با هر ذخیره و هر اجرا یک نسخه کامل
        # کارمندان و حقوق‌ها در پوشه «excel» کنار برنامه ذخیره می‌شود.
        self.backup_mgr = ExcelBackupManager(self.db, self.store, app_dir)
        self.db.add_write_listener(self.backup_mgr.on_data_write)
        self.backup_mgr.start()

        self.setWindowTitle("حقوق و دستمزد — نسخه آفلاین")
        self.resize(1240, 760)
        self.setMinimumSize(1020, 640)

        central = QWidget()
        v = QVBoxLayout(central)
        v.setContentsMargins(0, 0, 0, 0)
        v.setSpacing(0)

        body = QWidget()
        root = QHBoxLayout(body)
        root.setContentsMargins(0, 0, 0, 0)
        root.setSpacing(0)
        root.addWidget(self._build_sidebar(), 0)
        root.addWidget(self._build_content(), 1)
        v.addWidget(body, 1)
        v.addWidget(self._build_statusbar(), 0)

        self.setCentralWidget(central)

        self.pages["dashboard"].refresh()
        self._status_offline_init()

        # همگام‌سازی خودکار
        self.sync_timer = QTimer(self)
        self.sync_timer.timeout.connect(self.start_sync)
        self._restart_timer()

        # تلاش اول برای همگام‌سازی (اگر تنظیمات پر باشد)
        QTimer.singleShot(700, self._first_sync)

    # ---------------- UI سازنده ----------------

    def _build_sidebar(self):
        side = QFrame()
        side.setObjectName("Sidebar")
        side.setFixedWidth(232)
        v = QVBoxLayout(side)
        v.setContentsMargins(14, 18, 14, 14)
        v.setSpacing(4)

        title = QLabel("حقوق و دستمزد")
        title.setObjectName("AppTitle")
        sub = QLabel("نسخه آفلاین (دسکتاپ)")
        sub.setObjectName("AppSub")
        v.addWidget(title)
        v.addWidget(sub)
        v.addSpacing(10)

        hr = QFrame()
        hr.setObjectName("SidebarHR")
        hr.setFrameShape(QFrame.HLine)
        v.addWidget(hr)
        v.addSpacing(6)

        self.nav_group = QButtonGroup(self)
        self.nav_group.setExclusive(True)
        self.nav_buttons = {}
        self.pages = {}
        for key, label in self.NAV:
            btn = QPushButton(label)
            btn.setObjectName("NavBtn")
            btn.setCheckable(True)
            btn.setCursor(Qt.PointingHandCursor)
            btn.clicked.connect(lambda _=False, k=key: self.show_page(k))
            self.nav_group.addButton(btn)
            self.nav_buttons[key] = btn
            v.addWidget(btn)

        v.addStretch(1)
        hint = QLabel("با قطع اینترنت هم کار می‌کند\nتغییرات پس از اتصال همگام می‌شود")
        hint.setWordWrap(True)
        v.addWidget(hint)
        return side

    def _build_content(self):
        self.stack = QStackedWidget()
        pages = {
            "dashboard": DashboardPage(self),
            "register": RegisterPage(self),
            "records": RecordsPage(self),
            "employees": EmployeesPage(self),
            "centers": CentersPage(self),
            "reports": ReportsPage(self),
            "sync": SyncPage(self),
            "settings": SettingsPage(self),
        }
        for key, label in self.NAV:
            page = pages[key]
            self.pages[key] = page
            self.stack.addWidget(page)
        self.nav_buttons["dashboard"].setChecked(True)
        return self.stack

    def _build_statusbar(self):
        bar = QFrame()
        bar.setObjectName("StatusBar")
        bar.setFixedHeight(42)
        h = QHBoxLayout(bar)
        h.setContentsMargins(14, 4, 14, 4)
        h.setSpacing(10)

        self.dot = QLabel()
        self.dot.setObjectName("DotOffline")
        self.dot.setFixedSize(10, 10)
        self.status_label = QLabel("در حال آماده‌سازی…")
        h.addWidget(self.dot)
        h.addWidget(self.status_label)

        self.pending_badge = QLabel("")
        self.pending_badge.setObjectName("PendingBadge")
        self.pending_badge.hide()
        h.addWidget(self.pending_badge)

        self.last_sync_label = QLabel("")
        h.addWidget(self.last_sync_label)
        h.addStretch(1)

        btn_sync = QPushButton("همگام‌سازی الآن")
        btn_sync.setObjectName("Primary")
        btn_sync.setCursor(Qt.PointingHandCursor)
        btn_sync.clicked.connect(self.start_sync)
        h.addWidget(btn_sync)
        return bar

    # ---------------- ناوبری ----------------

    def show_page(self, key):
        page = self.pages.get(key)
        if not page:
            return
        if hasattr(page, "refresh"):
            try:
                page.refresh()
            except Exception:
                pass
        self.stack.setCurrentWidget(page)
        self.nav_buttons[key].setChecked(True)

    # ---------------- وضعیت ----------------

    def _status_offline_init(self):
        self.set_status("DotOffline", "آفلاین — برای اتصال از تنظیمات استفاده کنید")
        self.update_pending()

    def set_status(self, dot_object_name, text):
        self.dot.setObjectName(dot_object_name)
        self.dot.style().unpolish(self.dot)
        self.dot.style().polish(self.dot)
        self.status_label.setText(text)
        self.bus.sync_state.emit(dot_object_name, text)

    def update_pending(self):
        n = self.store.pending_count()
        if n > 0:
            self.pending_badge.setText("در صف: %s" % J.fa_digits(n))
            self.pending_badge.show()
        else:
            self.pending_badge.hide()
        last = self.db.kv_get("last_sync", "")
        if last:
            self.last_sync_label.setText("آخرین همگام‌سازی: %s" % J.fa_digits(last))
        else:
            self.last_sync_label.setText("")

    # ---------------- همگام‌سازی ----------------

    def _restart_timer(self):
        self.sync_timer.stop()
        if bool(self.config.get("auto_sync", True)) and self.config.is_configured():
            interval = max(30, int(self.config.get("sync_interval", 60) or 60)) * 1000
            self.sync_timer.start(interval)

    def _first_sync(self):
        if self.config.is_configured():
            self.start_sync()
        else:
            self.set_status("DotOffline", "ابتدا از منوی «تنظیمات» آدرس سایت و کلید API را وارد کنید")
            self.show_page("settings")

    def start_sync(self):
        if self.sync_busy:
            return
        if not self.config.is_configured():
            self.set_status("DotOffline", "آدرس API یا کلید تنظیم نشده است (تنظیمات)")
            return
        self.sync_busy = True
        self.set_status("DotBusy", "در حال همگام‌سازی…")

        def worker():
            result = self.engine.sync_now()
            self._on_sync_done(result)

        t = threading.Thread(target=worker, daemon=True)
        t.start()

    def _on_sync_done(self, result):
        self.sync_busy = False
        if result.online:
            if result.errors:
                self.set_status("DotBusy", "همگام‌سازی با خطا: %s" % "؛ ".join(result.errors[:2]))
            elif result.conflicts:
                self.set_status("DotOffline", "تداخل: %s مورد — جزئیات در صفحه همگام‌سازی" % J.fa_digits(len(result.conflicts)))
            else:
                self.set_status("DotOnline", "متصل به سایت — %s" % result.summary())
        else:
            self.set_status("DotOffline", "آفلاین — %s" % (result.message or "تغییرات در صف باقی می‌ماند"))
        self.update_pending()
        self.bus.data_changed.emit()
        page = self.pages.get("sync")
        if hasattr(page, "on_sync_result"):
            page.on_sync_result(result)

    # ---------------- کمکی ----------------

    def toast(self, message, error=False):
        self.status_label.setText(message)

    def closeEvent(self, event):
        try:
            self.sync_timer.stop()
            self.backup_mgr.shutdown()  # بکاپ نهایی اگر تغییری ذخیره‌نشده مانده باشد
            self.db.close()
        except Exception:
            pass
        super().closeEvent(event)
