# -*- coding: utf-8 -*-
"""موتور PDF نسخه 1.8.0 — بازنویسی کامل خروجی PDF برنامه آفلاین.

نسخه‌های قبلی PDF را با QTextDocument/HTML می‌ساختند؛ جدول‌ها ناقص
شکسته می‌شدند، ستون‌ها بی‌نظم بودند و سربرگ در صفحه‌های بعد تکرار
نمی‌شد. اکنون PDF مستقیماً و برداری با QPainter/QPdfWriter ترسیم
می‌شود:

- شکل‌دهی حروف فارسی توسط موتور متن Qt (HarfBuzz) — کامل و درست
- فونت همراه برنامه Vazirmatn (پوشه assets/fonts) — هم‌سان با افزونه
- همه‌چیز Bold و حداقل 10pt (سیاست نسخه 1.7.1 افزونه)
- ارقام انگلیسی با جداکننده هزارگان؛ مبالغ منفی قرمز
- جدول چندصفحه‌ای با تکرار سربرگ در هر صفحه + شماره صفحه
- اندازه ستون‌ها بر اساس محتوای واقعی محاسبه و در عرض صفحه «فیت» می‌شود
- قالب‌ها آینه PDF افزونه: گزارش لیست حقوق (A4)، فیش حقوقی (A5)، فیش بانکی (A4)
"""

import os
import subprocess

from PySide6.QtCore import Qt, QRect, QMarginsF, QSizeF
from PySide6.QtGui import QColor, QFont, QFontDatabase, QFontMetrics, QPainter, QPdfWriter
from PySide6.QtGui import QPageSize, QPageLayout

# ---------------- فونت همراه برنامه ----------------

_ASSETS = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "assets", "fonts")
_FONT_FILES = (
    os.path.join(_ASSETS, "Vazirmatn-Regular.ttf"),
    os.path.join(_ASSETS, "Vazirmatn-Bold.ttf"),
)
_fonts_loaded = False
_family = "Vazirmatn"


def ensure_fonts():
    """بارگذاری یک‌باره فونت‌های همراه؛ اگر فایل در دسترس نبود خانواده
    پیش‌فرض سیستم (با پشتیبانی عربی/فارسی در ویندوز) به‌کار می‌رود."""
    global _fonts_loaded, _family
    if _fonts_loaded:
        return _family
    _fonts_loaded = True
    loaded_ok = False
    try:
        for p in _FONT_FILES:
            if os.path.exists(p):
                if QFontDatabase.addApplicationFont(p) >= 0:
                    loaded_ok = True
        if loaded_ok and _family not in QFontDatabase.families():
            loaded_ok = False
    except Exception:
        loaded_ok = False
    if not loaded_ok:
        # جایگزین‌های رایج با پشتیبانی فارسی — Qt خودش fallback می‌کند.
        for cand in ("Vazirmatn", "Tahoma", "Segoe UI", "DejaVu Sans"):
            if cand in QFontDatabase.families():
                _family = cand
                break
    return _family


def app_font(size_pt, bold=True):
    f = QFont(ensure_fonts())
    f.setPointSizeF(float(size_pt))
    f.setBold(bool(bold))
    return f


def open_pdf(path):
    """باز کردن PDF با نمایشگر پیش‌فرض سیستم (مشاهده/چاپ)."""
    try:
        if os.name == "nt":
            os.startfile(path)  # noqa: S606 — ویندوز: نمایشگر پیش‌فرض
        elif os.uname().sysname == "Darwin":
            subprocess.Popen(["open", path])
        else:
            subprocess.Popen(["xdg-open", path])
        return True
    except Exception:
        return False


# ---------------- ابزار مشترک ----------------

HEADER_BG = QColor(217, 226, 243)   # سربرگ جدول — هم‌سان افزونه
LABEL_BG = QColor(240, 244, 251)    # برچسب‌های اطلاعات فیش
ZEBRA_BG = QColor(246, 247, 250)    # زبرا
TOTAL_BG = QColor(242, 242, 242)    # سطر جمع کل
BORDER = QColor(120, 128, 140)
TEXT = QColor(17, 24, 39)
MUTED = QColor(100, 116, 139)
NEG = QColor(185, 28, 28)           # مبالغ منفی — هم‌سان افزونه

MM = 1.0
PAD_X = 2.2         # حاشیه داخلی سلول (mm)
MIN_CELL_MM = 8.0   # کمینه عرض ستون
MAX_FONT_SHRINK = 7.5  # کمینه اندازه فونت در فیت‌کردن (نقطه)


class PdfCanvas(object):
    """کاغذ PDF با مختصات میلی‌متری + فونت برنامه."""

    def __init__(self, path, page_w, page_h, margin=10.0, title="Document"):
        self.writer = QPdfWriter(path)
        self.writer.setResolution(300)
        self.writer.setPageSize(QPageSize(QSizeF(page_w, page_h), QPageSize.Millimeter))
        self.writer.setPageMargins(QMarginsF(margin, margin, margin, margin), QPageLayout.Millimeter)
        self.writer.setTitle(title)
        self.writer.setCreator("TPP Salary Desktop")
        self.painter = QPainter()
        if not self.painter.begin(self.writer):
            raise RuntimeError("امکان ساخت فایل PDF وجود ندارد (فایل باز است؟)")
        self.page_w = float(page_w)
        self.page_h = float(page_h)
        self.margin = float(margin)
        self.px_per_mm = float(self.writer.resolution()) / 25.4
        self._page_no = 1

    # ---- تبدیل مختصات ----

    def px(self, mm_value):
        return int(round(float(mm_value) * self.px_per_mm))

    def rect(self, x, y, w, h):
        return QRect(self.px(x), self.px(y), self.px(w), self.px(h))

    # ---- ترسیم ----

    def fill(self, x, y, w, h, color):
        self.painter.fillRect(self.rect(x, y, w, h), color)

    def line(self, x1, y1, x2, y2, color=BORDER, width_mm=0.25):
        pen = self.painter.pen()
        from PySide6.QtGui import QPen
        p = QPen(color)
        p.setWidthF(max(0.7, self.px(width_mm)))
        self.painter.setPen(p)
        self.painter.drawLine(self.px(x1), self.px(y1), self.px(x2), self.px(y2))
        self.painter.setPen(pen)

    def box(self, x, y, w, h, fill=None):
        if fill is not None:
            self.fill(x, y, w, h, fill)
        self.line(x, y, x + w, y)
        self.line(x, y + h, x + w, y + h)
        self.line(x, y, x, y + h)
        self.line(x + w, y, x + w, y + h)

    def text(self, x, y, w, h, txt, font, align=Qt.AlignCenter, color=TEXT, elide=True):
        """متن در ناحیه داده‌شده — خط تکی با بریدگی ایمن."""
        fm = QFontMetrics(font)
        s = str(txt if txt is not None else "")
        if elide and fm.horizontalAdvance(s) > self.px(w) - self.px(2 * PAD_X):
            s = fm.elidedText(s, Qt.ElideRight, self.px(w) - self.px(2 * PAD_X))
        self.painter.setFont(font)
        self.painter.setPen(color)
        self.painter.drawText(self.rect(x, y, w, h), align | Qt.AlignVCenter | Qt.TextSingleLine, s)

    def text_width_mm(self, txt, font):
        return QFontMetrics(font).horizontalAdvance(str(txt)) / self.px_per_mm

    def new_page(self):
        self.writer.newPage()
        self._page_no += 1

    def footer(self, font, extra=""):
        """شماره صفحه + یادداشت اختیاری در پایین صفحه جاری."""
        h = 5.2
        y = self.page_h - self.margin - h + 0.6
        label = "صفحه %d" % self._page_no
        if extra:
            label = "%s — %s" % (label, extra)
        self.text(self.margin, y, self.page_w - 2 * self.margin, h, label, font,
                  align=Qt.AlignHCenter, color=MUTED)

    def close(self):
        self.painter.end()


# ---------------- ستون‌ها و اندازه‌گذاری ----------------

def fit_widths(canvas, headers, rows, weights, body_font, min_total=None):
    """عرض ستون‌ها بر اساس محتوای واقعی — سپس فیت در عرض مفید صفحه.

    headers/rows به ترتیب منطقی‌اند (ستون ۰ = سمت راست). weights وزن نسبی
    پایه هر ستون است (0 = اندازه فقط از محتوا).
    """
    usable = canvas.page_w - 2 * canvas.margin
    n = len(headers)
    req = []
    for i in range(n):
        w = canvas.text_width_mm(headers[i], body_font) + 2 * PAD_X + 1.0
        for row in rows:
            cell = row["cells"][i] if i < len(row["cells"]) else ""
            w = max(w, canvas.text_width_mm(cell, body_font) + 2 * PAD_X + 1.0)
        base = (weights[i] if i < len(weights) and weights[i] else 1.0)
        req.append(max(w, MIN_CELL_MM, base * 14.0 if weights and weights[i] else w))
    total = sum(req)
    if total > usable:
        scale = usable / total
        req = [max(MIN_CELL_MM, r * scale) for r in req]
        # پس از کف‌گذاری ممکن است باز هم سرریز باشد → تناسب نهایی
        total = sum(req)
        if total > usable:
            k = usable / total
            req = [r * k for r in req]
    else:
        # ستون‌های label (وزن‌دار) فضای مازاد می‌گیرند
        extra = usable - total
        wsum = sum((weights[i] if i < len(weights) and weights[i] else 0.0) for i in range(n))
        if wsum > 0 and extra > 0:
            req = [r + extra * ((weights[i] if i < len(weights) and weights[i] else 0.0) / wsum)
                   for i, r in enumerate(req)]
        elif extra > 0:
            req = [r + extra / n for r in req]
    return req


# ---------------- جدول چندصفحه‌ای ----------------

class TablePDF(object):
    """جدول RTL با تکرار سربرگ — آینه ظاهر PDF افزونه.

    rows: هر ردیف dict با کلیدها:
      cells: list[str] به ترتیب منطقی (۰ = ستون عنوان سمت راست)
      bold:  bool (اختیاری)
      fill:  QColor (اختیاری — زبرا/جمع)
      color: QColor (اختیاری — مثلاً قرمز برای منفی)
      align: list[int] اختیاری برای هر سلول (Qt.Align*)
    """

    def __init__(self, path, title="Report", page_size=(210, 297), landscape=False, margin=10.0):
        w, h = page_size
        if landscape:
            w, h = max(w, h), min(w, h)
        else:
            w, h = min(w, h), max(w, h)
        self.c = PdfCanvas(path, w, h, margin=margin, title=title)
        self.usable_w = self.c.page_w - 2 * self.c.margin
        self.f_title = app_font(13.5)
        self.f_meta = app_font(10)
        self.f_body = app_font(10)
        self.f_footer = app_font(8.5)

    # ---- سربرگ صفحه ----

    def draw_header(self, title, meta_lines, rule=True):
        c = self.c
        y = c.margin
        c.text(c.margin, y, self.usable_w, 8.0, title or "", self.f_title, align=Qt.AlignHCenter)
        y += 8.0
        for m in (meta_lines or []):
            c.text(c.margin, y, self.usable_w, 5.4, m, self.f_meta, align=Qt.AlignHCenter, color=MUTED)
            y += 5.4
        if rule:
            y += 0.6
            c.line(c.margin, y, c.page_w - c.margin, y)
            y += 1.4
        return y

    # ---- رندر کامل ----

    def render(self, headers, rows, title, meta_lines=None, weights=None,
               row_h=7.0, head_h=None, footer_extra="", footer_date=True):
        """رندر جدول با شکستن صفحه و تکرار سربرگ؛ تعداد صفحه‌ها قطعی است."""
        c = self.c
        widths = fit_widths(c, headers, rows, weights, self.f_body)
        head_h = float(head_h or max(row_h, 7.6))

        # صفحه‌بندی قطعی: ارتفاع سربرگ متن + ظرفیت سطرهای هر صفحه
        # (ظرفیت هر صفحه = (ارتفاع مفید − سربرگ جدول) ÷ ارتفاع سطر)
        top_first = self.draw_header(title, meta_lines) if (title or meta_lines) else c.margin
        FOOTER_H = 7.0
        avail_first = c.page_h - c.margin - FOOTER_H - top_first
        avail_next = c.page_h - c.margin - FOOTER_H - c.margin
        per_first = max(1, int((avail_first - head_h) // row_h))
        per_next = max(1, int((avail_next - head_h) // row_h))
        # صفحه‌بندی هم‌سان با ترسیم: ظرفیت صفحه اول سپس صفحات بعدی
        caps = []
        drawn = 0
        while rows and drawn < len(rows):
            caps.append(per_first if not caps else per_next)
            drawn += caps[-1]
        pages = max(1, len(caps))

        today_fa = ""
        try:
            from . import jalali as J
            jy, jm, jd = J.today_jalali()
            today_fa = "%04d/%02d/%02d" % (jy, jm, jd)
        except Exception:
            pass
        fextra = " — تاریخ چاپ: %s" % today_fa if (footer_date and today_fa) else ""
        fextra += (" — " + footer_extra) if footer_extra else ""

        # ترتیب ترسیم RTL: ستون ۰ سمت راست
        xs = []
        x_right = c.page_w - c.margin
        for w in widths:
            x_right -= w
            xs.append(x_right)

        idx = 0
        page = 0
        while (rows and idx < len(rows)) or page == 0:
            if page > 0:
                c.new_page()
                top = c.margin
            else:
                top = top_first
            y = top
            if rows:
                # سربرگ جدول
                for i, htxt in enumerate(headers):
                    c.box(xs[i], y, widths[i], head_h, fill=HEADER_BG)
                    c.text(xs[i], y, widths[i], head_h, htxt, self.f_body, align=Qt.AlignHCenter)
                y += head_h
                cap = caps[page] if page < len(caps) else per_next
                for _r in range(cap):
                    if idx >= len(rows):
                        break
                    row = rows[idx]
                    fill = row.get("fill")
                    color = row.get("color", TEXT)
                    aligns = row.get("align") or [Qt.AlignHCenter] * len(headers)
                    for i in range(len(headers)):
                        cell = row["cells"][i] if i < len(row["cells"]) else ""
                        c.box(xs[i], y, widths[i], row_h, fill=fill)
                        c.text(xs[i], y, widths[i], row_h, cell, self.f_body,
                               align=aligns[i], color=color)
                    y += row_h
                    idx += 1
            c.footer(self.f_footer, extra=fextra.strip(" —"))
            page += 1
            if page >= pages and not rows:
                break
        c.close()
        return pages


# ==================================================
# سازنده‌های آماده — آینه PDF افزونه
# ==================================================

def money(v):
    from . import jalali as J
    return J.format_money(v)


def write_report_pdf(path, emps, rows_data, title, meta_lines, landscape=None):
    """گزارش لیست حقوق — emps: [(eid, name)]، rows_data: [(label, {eid: value}, bold)].

    bold=True برای سطرهای خلاصه. جهت کاغذ خودکار: بیش از ۴ کارمند → افقی.
    """
    if landscape is None:
        landscape = len(emps) > 4
    headers = ["عنوان حقوقی"] + [name for _eid, name in emps]
    rows = []
    for label, values, bold in rows_data:
        cells = [label]
        for eid, _n in emps:
            v = values.get(eid)
            cells.append(money(v) if v is not None else "—")
        rows.append({
            "cells": cells,
            "bold": bold,
            "fill": TOTAL_BG if bold else None,
            "align": [Qt.AlignRight] + [Qt.AlignHCenter] * len(emps),
        })
    t = TablePDF(path, title="Salary Report", page_size=(210, 297), landscape=landscape)
    return t.render(headers, rows, title, meta_lines, weights=[1.5] + [1.0] * len(emps))


def write_bank_pdf(path, rows, title, meta_lines, currency="ریال"):
    """فیش بانکی — rows: [(name, account, sheba, net)] آینه build_bank_pdf افزونه."""
    headers = ["#", "نام کارمند", "شماره حساب", "شماره شبا", "حقوق خالص"]
    body = []
    total = 0.0
    for i, (name, account, sheba, net) in enumerate(rows, start=1):
        total += float(net or 0)
        body.append({
            "cells": [str(i), name, str(account or ""), str(sheba or ""), money(net)],
            "color": NEG if float(net or 0) < 0 else TEXT,
            "align": [Qt.AlignHCenter, Qt.AlignRight, Qt.AlignHCenter, Qt.AlignHCenter, Qt.AlignHCenter],
        })
    body.append({
        "cells": ["", "جمع کل (%s)" % currency, "", "", money(total)],
        "bold": True, "fill": TOTAL_BG,
        "align": [Qt.AlignHCenter, Qt.AlignHCenter, Qt.AlignHCenter, Qt.AlignHCenter, Qt.AlignHCenter],
    })
    t = TablePDF(path, title="Bank Slip", page_size=(210, 297), landscape=False)
    return t.render(headers, body, title, meta_lines, weights=[0.4, 2.0, 1.3, 1.6, 1.0], row_h=7.4)


def write_payslip_pdf(path, info, fields, company, currency="ریال", print_date=""):
    """فیش حقوقی تکی — A5، آینه build_payslip_pdf افزونه.

    info: dict(name, national, job_title, personnel, period_label, center_name)
    fields: [(label, value_str, is_number, raw_float)]
    """
    A5_W, A5_H = 148.0, 210.0
    margin = 8.0
    c = PdfCanvas(path, A5_W, A5_H, margin=margin, title="Payslip")
    pw = A5_W - 2 * margin

    f_title = app_font(12.5)
    f_body = app_font(10)

    n = len(fields)
    reserved = 24 + 18 + 15
    avail = A5_H - 2 * margin - reserved
    rh = (avail / (n + 4)) if n > 0 else 5.7
    rh = max(5.7, min(6.4, rh))
    lh = rh + 0.6

    # --- سربرگ ---
    y = margin
    c.text(margin, y, pw, 7.5, company or "فیش حقوقی و دستمزد", f_title, align=Qt.AlignHCenter)
    y += 7.5
    c.text(margin, y, pw, 6.0, "فیش حقوقی %s — مرکز %s" % (info.get("period_label", ""), info.get("center_name", "")),
           f_body, align=Qt.AlignHCenter)
    y += 6.5
    c.line(margin, y, A5_W - margin, y)
    y += 2.0

    # --- اطلاعات کارمند — چهار ستون ---
    lw = 32.0
    vw = (pw - 2 * lw) / 2.0

    def info_row(pairs):
        nonlocal y
        x = A5_W - margin
        for label, value in pairs:
            x -= lw
            c.box(x, y, lw, rh, fill=LABEL_BG)
            c.text(x, y, lw, rh, label, f_body, align=Qt.AlignHCenter)
            x -= vw
            c.box(x, y, vw, rh)
            c.text(x, y, vw, rh, value, f_body, align=Qt.AlignHCenter)
        y += rh

    info_row([("نام و نام خانوادگی", info.get("name", "")), ("کد ملی", info.get("national", ""))])
    info_row([("عنوان شغلی", info.get("job_title", "")), ("شماره پرسنلی", str(info.get("personnel", "")))])
    y += 1.5

    # --- جدول جزئیات ---
    col_w = pw / 2.0

    def table_header():
        nonlocal y
        x = A5_W - margin
        for htxt, fill in (("عنوان", HEADER_BG), ("مقدار (%s)" % currency, HEADER_BG)):
            x -= col_w
            c.box(x, y, col_w, lh, fill=fill)
            c.text(x, y, col_w, lh, htxt, f_body, align=Qt.AlignHCenter)
        y += lh

    table_header()
    i = 0
    for label, value_str, is_number, raw in fields:
        if y + rh > A5_H - margin - 20:
            c.new_page()
            y = margin
            table_header()
        fill = ZEBRA_BG if (i % 2 == 0) else None
        x = A5_W - margin - col_w
        c.box(x, y, col_w, rh, fill=fill)
        c.text(x, y, col_w, rh, label, f_body, align=Qt.AlignRight)
        x -= col_w
        c.box(x, y, col_w, rh, fill=fill)
        color = NEG if (is_number and raw is not None and float(raw) < 0) else TEXT
        c.text(x, y, col_w, rh, value_str, f_body, align=Qt.AlignHCenter, color=color)
        y += rh
        i += 1

    # --- امضاها ---
    y += 3
    half = col_w
    c.text(margin, y, half, 5.5, "امضای کارمند", f_body, align=Qt.AlignHCenter)
    c.text(margin + half, y, half, 5.5, "امضای کارفرما", f_body, align=Qt.AlignHCenter)
    y += 5.5
    c.line(margin + pw * 0.22, y, margin + pw * 0.40, y)
    c.line(margin + pw * 0.62, y, margin + pw * 0.80, y)
    y += 2.5
    c.text(margin, y, pw, 4.5, "تاریخ چاپ: %s" % (print_date or ""), f_body, align=Qt.AlignHCenter)

    c.close()
    return 1
