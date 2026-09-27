# -*- coding: utf-8 -*-
"""تقویم جلالی — تبدیل میلادی به شمسی و ابزارهای نمایش (بدون وابستگی خارجی)."""

import datetime

JMONTH_NAMES = [
    "", "فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور",
    "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند",
]

_FA_DIGITS = str.maketrans("۰۱۲۳۴۵۶۷۸۹", "0123456789")  # نسخه 1.7.1: فارسی → لاتین


def gregorian_to_jalali(gy, gm, gd):
    """تبدیل تاریخ میلادی به جلالی (الگوریتم استاندارد)."""
    g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334]
    gy2 = gy + 1 if gm > 2 else gy
    days = (
        355666 + (365 * gy) + ((gy2 + 3) // 4) - ((gy2 + 99) // 100)
        + ((gy2 + 399) // 400) + gd + g_d_m[gm - 1]
    )
    jy = -1595 + (33 * (days // 12053))
    days %= 12053
    jy += 4 * (days // 1461)
    days %= 1461
    if days > 365:
        jy += (days - 1) // 365
        days = (days - 1) % 365
    if days < 186:
        jm = 1 + (days // 31)
        jd = 1 + (days % 31)
    else:
        jm = 7 + ((days - 186) // 30)
        jd = 1 + ((days - 186) % 30)
    return jy, jm, jd


def today_jalali():
    """امروز به تاریخ جلالی: (سال، ماه، روز)."""
    now = datetime.date.today()
    return gregorian_to_jalali(now.year, now.month, now.day)


def month_name(m):
    try:
        return JMONTH_NAMES[int(m)] if 1 <= int(m) <= 12 else str(m)
    except (TypeError, ValueError):
        return str(m)


def fa_digits(value):
    """نسخه 1.7.1 — سیاست ارقام انگلیسی: همه اعداد در کل نرم‌افزار لاتین‌اند.
    نام تابع برای سازگاری با نسخه‌های قبل و کدهای موجود حفظ شده، اما اکنون
    هر رقم فارسی/عربی ورودی را به لاتین تبدیل می‌کند (خروجی همیشه انگلیسی).
    """
    return en_digits(value)


def en_digits(value):
    """تبدیل ارقام فارسی/عربی به لاتین (برای پارس ورودی کاربر)."""
    s = str(value)
    table = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")
    return s.translate(table)


def parse_number(value):
    """پارس عددی هم‌سان با tpp_salary_parse_number افزونه (ارقام فارسی + جداکننده‌ها)."""
    s = en_digits(str(value))
    for ch in ("،", "٬", ",", " "):
        s = s.replace(ch, "")
    s = s.strip()
    if s in ("", "-", "."):
        return 0.0
    try:
        return float(s)
    except ValueError:
        return 0.0


def period_label(jyear, jmonth):
    """برچسب فارسی دوره با ارقام انگلیسی: «مرداد 1404» (نسخه 1.7.1)."""
    return "%s %s" % (month_name(jmonth), fa_digits(jyear))


def format_money(value, fa=True, suffix=""):
    """قالب‌بندی مبلغ با جداکننده هزارگان (نسخه 1.7.1: همیشه ارقام انگلیسی؛
    پارامتر fa فقط برای سازگاری حفظ شده و بی‌اثر است)."""
    try:
        n = float(value)
    except (TypeError, ValueError):
        n = 0.0
    if abs(n - int(n)) < 0.5:
        n = int(n)
    return "{:,}".format(n) + ("" if not suffix else " " + suffix)
