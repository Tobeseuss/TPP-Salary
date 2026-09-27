# -*- coding: utf-8 -*-
"""تم و استایل حرفه‌ای برنامه — QSS روشن/تیره + قلم فارسی."""

import os

from PySide6.QtGui import QFont, QFontDatabase

FONTS_CANDIDATES = [
    "Vazirmatn", "IRANSans", "B Nazanin", "Segoe UI", "Tahoma",
]


def app_font():
    """قلم فارسی مناسب — در صورت وجود فایل Vazirmatn داخل پوشه برنامه از آن استفاده می‌شود."""
    font_file = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "assets", "Vazirmatn-Regular.ttf")
    family = None
    if os.path.exists(font_file):
        fid = QFontDatabase.addApplicationFont(font_file)
        if fid >= 0:
            fams = QFontDatabase.applicationFontFamilies(fid)
            if fams:
                family = fams[0]
    if not family:
        for cand in FONTS_CANDIDATES:
            if cand in QFontDatabase.families():
                family = cand
                break
    font = QFont(family or "Tahoma")
    font.setPointSize(10)
    return font


LIGHT_QSS = """
* { font-family: '%(font)s'; outline: none; }
QWidget { background: #f4f6fa; color: #1c2b3a; font-size: 13px; }
QMainWindow, QDialog { background: #f4f6fa; }

/* ---- سایدبار ---- */
#Sidebar { background: qlineargradient(x1:0,y1:0,x2:0,y2:1, stop:0 #10263f, stop:1 #0b1c30); }
#Sidebar QLabel { color: #9fb6cc; background: transparent; }
#AppTitle { color: #ffffff; font-size: 17px; font-weight: 700; background: transparent; }
#AppSub { color: #56d4c5; font-size: 11px; background: transparent; }
QPushButton#NavBtn {
    color: #cfe0ee; background: transparent; border: none; border-radius: 9px;
    padding: 11px 14px; text-align: right; font-size: 14px; font-weight: 600;
}
QPushButton#NavBtn:hover { background: rgba(255,255,255,0.08); color: #ffffff; }
QPushButton#NavBtn:checked { background: #14b8a6; color: #ffffff; }
#SidebarHR { color: #1d3a57; background: #1d3a57; max-height: 1px; border: none; }

/* ---- کارت و پنل ---- */
QFrame#Card, QFrame#Panel { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; }
QFrame#StatCard { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; }
QFrame#StatCard QLabel[statTitle="1"] { color: #64748b; font-size: 12px; background: transparent; }
QFrame#StatCard QLabel[statValue="1"] { color: #0f172a; font-size: 24px; font-weight: 800; background: transparent; }
QLabel#PageTitle { font-size: 19px; font-weight: 800; color: #0f172a; background: transparent; }
QLabel#PageHint { color: #64748b; font-size: 12px; background: transparent; }

/* ---- ورودی‌ها ---- */
QLineEdit, QComboBox, QSpinBox, QDoubleSpinBox, QDateEdit {
    background: #ffffff; border: 1.4px solid #d7dee8; border-radius: 9px; padding: 7px 11px;
    selection-background-color: #14b8a6;
}
QLineEdit:focus, QComboBox:focus, QSpinBox:focus { border-color: #14b8a6; }
QComboBox::drop-down { border: none; width: 26px; }
QComboBox QAbstractItemView { background: #ffffff; border: 1px solid #d7dee8; selection-background-color: #d9f5f1; selection-color: #0f172a; }
QCheckBox { spacing: 7px; background: transparent; }
QCheckBox::indicator { width: 17px; height: 17px; border: 1.5px solid #b8c4d4; border-radius: 5px; background: #ffffff; }
QCheckBox::indicator:checked { background: #14b8a6; border-color: #14b8a6; }

/* ---- دکمه‌ها ---- */
QPushButton {
    background: #eef2f7; color: #1c2b3a; border: 1px solid #d7dee8; border-radius: 9px;
    padding: 8px 16px; font-weight: 600;
}
QPushButton:hover { background: #e3eaf2; }
QPushButton:disabled { color: #94a3b8; background: #f1f5f9; }
QPushButton#Primary { background: #0d9488; color: #ffffff; border: none; }
QPushButton#Primary:hover { background: #0f766e; }
QPushButton#Danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
QPushButton#Danger:hover { background: #fecaca; }
QPushButton#Ghost { background: transparent; border: 1px dashed #b8c4d4; color: #475569; }

/* ---- جدول ---- */
QTableWidget, QTableView {
    background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px;
    gridline-color: #eef2f7; alternate-background-color: #f8fafc;
}
QHeaderView::section {
    background: #f1f5f9; color: #334155; border: none; border-bottom: 1px solid #e2e8f0;
    padding: 9px 8px; font-weight: 700;
}
QTableCornerButton::section { background: #f1f5f9; border: none; }
QTableWidget::item { padding: 5px 8px; }
QTableWidget::item:selected { background: #ccfbf1; color: #0f172a; }

/* ---- تب ---- */
QTabWidget::pane { border: 1px solid #e2e8f0; border-radius: 10px; background: #ffffff; top: -1px; }
QTabBar::tab {
    background: #eef2f7; color: #475569; padding: 9px 18px; margin-inline-start: 3px;
    border-top-right-radius: 9px; border-top-left-radius: 9px; font-weight: 600;
}
QTabBar::tab:selected { background: #ffffff; color: #0d9488; border: 1px solid #e2e8f0; border-bottom: 2px solid #14b8a6; }

/* ---- نوار وضعیت ---- */
#StatusBar { background: #ffffff; border-top: 1px solid #e2e8f0; }
#StatusBar QLabel { background: transparent; color: #475569; font-size: 12px; }
QLabel#DotOnline { background: #22c55e; border-radius: 5px; max-width: 10px; max-height: 10px; }
QLabel#DotOffline { background: #ef4444; border-radius: 5px; max-width: 10px; max-height: 10px; }
QLabel#DotBusy { background: #f59e0b; border-radius: 5px; max-width: 10px; max-height: 10px; }
QLabel#PendingBadge { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; border-radius: 9px; padding: 2px 10px; font-weight: 700; }

QProgressBar { background: #e2e8f0; border: none; border-radius: 6px; max-height: 8px; }
QProgressBar::chunk { background: #14b8a6; border-radius: 6px; }
QListWidget { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; }
QListWidget::item { padding: 8px; }
QListWidget::item:selected { background: #ccfbf1; color: #0f172a; }
QScrollBar:vertical { background: transparent; width: 10px; margin: 2px; }
QScrollBar::handle:vertical { background: #cbd5e1; border-radius: 5px; min-height: 30px; }
QScrollBar::handle:vertical:hover { background: #94a3b8; }
QScrollBar:horizontal { background: transparent; height: 10px; margin: 2px; }
QScrollBar::handle:horizontal { background: #cbd5e1; border-radius: 5px; min-width: 30px; }
QToolTip { background: #0f172a; color: #ffffff; border: none; padding: 6px; }
"""

DARK_QSS = """
* { font-family: '%(font)s'; outline: none; }
QWidget { background: #0f172a; color: #e2e8f0; font-size: 13px; }
QMainWindow, QDialog { background: #0f172a; }

#Sidebar { background: #0b1120; }
#Sidebar QLabel { color: #7c93ad; background: transparent; }
#AppTitle { color: #ffffff; font-size: 17px; font-weight: 700; background: transparent; }
#AppSub { color: #2dd4bf; font-size: 11px; background: transparent; }
QPushButton#NavBtn {
    color: #b6c8db; background: transparent; border: none; border-radius: 9px;
    padding: 11px 14px; text-align: right; font-size: 14px; font-weight: 600;
}
QPushButton#NavBtn:hover { background: rgba(255,255,255,0.06); color: #ffffff; }
QPushButton#NavBtn:checked { background: #0d9488; color: #ffffff; }
#SidebarHR { color: #1e293b; background: #1e293b; max-height: 1px; border: none; }

QFrame#Card, QFrame#Panel, QFrame#StatCard { background: #16223a; border: 1px solid #233047; border-radius: 12px; }
QFrame#StatCard QLabel[statTitle="1"] { color: #7c93ad; font-size: 12px; background: transparent; }
QFrame#StatCard QLabel[statValue="1"] { color: #f1f5f9; font-size: 24px; font-weight: 800; background: transparent; }
QLabel#PageTitle { font-size: 19px; font-weight: 800; color: #f8fafc; background: transparent; }
QLabel#PageHint { color: #7c93ad; font-size: 12px; background: transparent; }

QLineEdit, QComboBox, QSpinBox, QDoubleSpinBox {
    background: #16223a; border: 1.4px solid #2c3a55; border-radius: 9px; padding: 7px 11px; color: #e2e8f0;
}
QLineEdit:focus, QComboBox:focus, QSpinBox:focus { border-color: #14b8a6; }
QComboBox QAbstractItemView { background: #16223a; border: 1px solid #2c3a55; selection-background-color: #134e4a; selection-color: #ffffff; }
QCheckBox::indicator { width: 17px; height: 17px; border: 1.5px solid #3b4b68; border-radius: 5px; background: #16223a; }
QCheckBox::indicator:checked { background: #14b8a6; border-color: #14b8a6; }

QPushButton { background: #1d2a44; color: #dbe6f3; border: 1px solid #2c3a55; border-radius: 9px; padding: 8px 16px; font-weight: 600; }
QPushButton:hover { background: #243352; }
QPushButton:disabled { color: #55647f; }
QPushButton#Primary { background: #0d9488; color: #ffffff; border: none; }
QPushButton#Primary:hover { background: #0f766e; }
QPushButton#Danger { background: #3f1d24; color: #fda4af; border: 1px solid #7f1d1d; }
QPushButton#Ghost { background: transparent; border: 1px dashed #3b4b68; color: #94a3b8; }

QTableWidget, QTableView, QListWidget { background: #16223a; border: 1px solid #233047; border-radius: 10px; gridline-color: #1d2a44; alternate-background-color: #182640; }
QHeaderView::section { background: #1d2a44; color: #cbd5e1; border: none; border-bottom: 1px solid #2c3a55; padding: 9px 8px; font-weight: 700; }
QTableWidget::item:selected { background: #134e4a; color: #ffffff; }
QListWidget::item:selected { background: #134e4a; color: #ffffff; }

QTabWidget::pane { border: 1px solid #233047; border-radius: 10px; background: #16223a; top: -1px; }
QTabBar::tab { background: #1d2a44; color: #94a3b8; padding: 9px 18px; margin-inline-start: 3px; border-top-right-radius: 9px; border-top-left-radius: 9px; font-weight: 600; }
QTabBar::tab:selected { background: #16223a; color: #2dd4bf; border-bottom: 2px solid #14b8a6; }

#StatusBar { background: #0b1120; border-top: 1px solid #1e293b; }
#StatusBar QLabel { background: transparent; color: #94a3b8; font-size: 12px; }
QLabel#DotOnline { background: #22c55e; border-radius: 5px; max-width: 10px; max-height: 10px; }
QLabel#DotOffline { background: #ef4444; border-radius: 5px; max-width: 10px; max-height: 10px; }
QLabel#DotBusy { background: #f59e0b; border-radius: 5px; max-width: 10px; max-height: 10px; }
QLabel#PendingBadge { background: #3f2d0c; color: #fcd34d; border: 1px solid #92400e; border-radius: 9px; padding: 2px 10px; font-weight: 700; }

QScrollBar:vertical { background: transparent; width: 10px; }
QScrollBar::handle:vertical { background: #334155; border-radius: 5px; min-height: 30px; }
QScrollBar:horizontal { background: transparent; height: 10px; }
QScrollBar::handle:horizontal { background: #334155; border-radius: 5px; min-width: 30px; }
QToolTip { background: #e2e8f0; color: #0f172a; border: none; padding: 6px; }
"""


def apply_theme(app, theme="light"):
    qss = LIGHT_QSS if theme != "dark" else DARK_QSS
    try:
        font = app_font()
        app.setFont(font)
        qss = qss % {"font": font.family()}
    except Exception:
        qss = qss.replace("%(font)s", "Tahoma")
    app.setStyleSheet(qss)
