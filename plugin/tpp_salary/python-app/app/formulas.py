# -*- coding: utf-8 -*-
"""
موتور فرمول امن — آینه TppSalary_Formula در سمت PHP.

توکن‌های {field_key}، اعداد (با ارقام فارسی/جداکننده) و عملگرهای + - * / ( )
با الگوریتم Shunting-yard ارزیابی می‌شوند؛ هیچ تابع/eval پویایی اجرا نمی‌شود.
"""

import math

from . import jalali as J


class FormulaError(Exception):
    pass


def _tokenize(formula):
    tokens = []
    src = J.en_digits(str(formula))
    for ch in "،٬":  # جداکننده‌های هزارگان
        src = src.replace(ch, "")
    i, n = 0, len(src)
    while i < n:
        ch = src[i]
        if ch in " \t\n":
            i += 1
            continue
        if ch.isdigit() or ch == ".":
            j = i
            while j < n and (src[j].isdigit() or src[j] == "."):
                j += 1
            try:
                tokens.append(("num", float(src[i:j])))
            except ValueError:
                raise FormulaError("عدد نامعتبر در فرمول")
            i = j
            continue
        if ch == "{":
            j = src.find("}", i + 1)
            if j < 0:
                raise FormulaError("آکولاد بسته‌نشده در فرمول")
            key = src[i + 1:j].strip()
            if not key:
                raise FormulaError("توکن خالی در فرمول")
            tokens.append(("var", key))
            i = j + 1
            continue
        if ch in "+-*/()":
            tokens.append(("op", ch))
            i += 1
            continue
        raise FormulaError("کاراکتر غیرمجاز در فرمول: %s" % ch)
    return tokens


def _to_rpn(tokens):
    """تبدیل توکن‌ها به RPN — یگانی منفی با درج ۰ مجازی پشتیبانی می‌شود."""
    out, stack = [], []
    prev = None
    for typ, val in tokens:
        if typ == "num":
            out.append(("num", val))
        elif typ == "var":
            out.append(("var", val))
        elif typ == "op":
            if val == "(":
                stack.append(val)
            elif val == ")":
                while stack and stack[-1] != "(":
                    out.append(("op", stack.pop()))
                if not stack:
                    raise FormulaError("پرانتز ناهماهنگ")
                stack.pop()
            else:
                # منفی یگانی: ابتدای عبارت یا بعد از عملگر یا پرانتز باز
                if val == "-" and (prev is None or (prev[0] == "op" and prev[1] in "+-*/(")):
                    out.append(("num", 0.0))
                while stack and stack[-1] != "(" and _prec(stack[-1]) >= _prec(val):
                    out.append(("op", stack.pop()))
                stack.append(val)
        prev = (typ, val)
    while stack:
        top = stack.pop()
        if top == "(":
            raise FormulaError("پرانتز ناهماهنگ")
        out.append(("op", top))
    return out


def _prec(op):
    return 2 if op in "*/" else 1


def evaluate(formula, values):
    """ارزیابی فرمول با مقادیر فیلدها؛ خطا → FormulaError."""
    tokens = _tokenize(formula)
    if not tokens:
        raise FormulaError("فرمول خالی است")
    rpn = _to_rpn(tokens)
    stack = []
    for typ, val in rpn:
        if typ == "num":
            stack.append(float(val))
        elif typ == "var":
            try:
                stack.append(float(J.en_digits(values.get(val, 0)) or 0))
            except (TypeError, ValueError):
                stack.append(0.0)
        else:
            if len(stack) < 2:
                raise FormulaError("عبارت نامعتبر")
            b, a = stack.pop(), stack.pop()
            if val == "+":
                stack.append(a + b)
            elif val == "-":
                stack.append(a - b)
            elif val == "*":
                stack.append(a * b)
            elif val == "/":
                if abs(b) < 1e-12:
                    raise FormulaError("تقسیم بر صفر")
                stack.append(a / b)
    if len(stack) != 1:
        raise FormulaError("عبارت نامعتبر")
    return stack[0]


def php_round(x):
    """گرد کردن هم‌سان با PHP round(): نیم به سمت دور از صفر."""
    if x < 0:
        return float(math.ceil(x - 0.5))
    return float(math.floor(x + 0.5))


def php_ceil_toward_zero(x):
    """کسورات منفی به سمت صفر (هم‌سان با PHP ceil برای اعداد منفی)."""
    return float(math.ceil(x))


def compute_values(values, manual=None, fields=None, force=None, global_formulas=None):
    """
    آینه tpp_salary_compute_values:
    - فیلدهای محاسباتی از فرمول فیلد یا فرمول سراسری تنظیمات محاسبه می‌شوند
    - اگر مقدار کاربر با حاصل فرمول تفاوت داشت → دستی تلقی می‌شود
    - خروجی: (values نهایی، manual به‌روزشده)
    """
    values = {k: float(J.en_digits(v) or 0) for k, v in (values or {}).items()}
    manual = [str(m) for m in (manual or [])]
    force = [str(f) for f in (force or [])]
    global_formulas = global_formulas or {}

    for f in fields or []:
        calculated = int(f.get("calculated", f.get("is_calculated", 0)) or 0)
        if not calculated:
            continue
        key = f.get("key") or f.get("field_key")
        formula = (f.get("formula") or "").strip() or str(global_formulas.get(key, "") or "")
        if not formula.strip():
            continue
        posted = float(values.get(key, 0.0) or 0.0)
        if key in manual:
            continue
        try:
            val = evaluate(formula, values)
        except FormulaError:
            continue
        val = php_ceil_toward_zero(val) if val < 0 else php_round(val)
        if key in force:
            values[key] = val
            continue
        if abs(val - posted) > 0.5:
            manual.append(key)
            continue
        values[key] = val

    for k in list(values.keys()):
        v = float(values[k] or 0)
        values[k] = php_ceil_toward_zero(v) if v < 0 else php_round(v)
    return values, manual
