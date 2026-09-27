# -*- coding: utf-8 -*-
"""عبور خودکار از محافظت ضدرباتِ هاست (صفحه جاوااسکریپتی /aes.js).

برخی هاست‌ها جلوی درخواست‌های بدون کوکی را با یک صفحه جاوااسکریپتی می‌گیرند:
مرورگر باید ``slowAES.decrypt(c, 2, a, b)`` را اجرا کند، نتیجه را در کوکی
``__test`` بگذارد و صفحه را دوباره بخواند — در غیر این صورت به‌جای پاسخ واقعی
همین صفحه HTML برگردانده می‌شود و کلاینت‌های ساده (از جمله نرم‌افزار دسکتاپ)
خطای «پاسخ نامعتبر از سرور (JSON)» می‌گیرند. این ماژول همان کار مرورگر را بدون
مرورگر و بدون هیچ وابستگی خارجی انجام می‌دهد (پیاده‌سازی خالص AES-128).

نکته مهم: نسخه slowaes رایج روی این هاست‌ها جدول حالت‌های
``modeOfOperation = {OFB:0, CFB:1, CBC:2}`` دارد؛ یعنی در فراخوانی
``decrypt(c, 2, a, b)`` عدد ۲ یعنی **CBC** (نه CTR). برای یک بلوک ۱۶ بایتی:

    cookie = AES128_DECRYPT(key=a, data=c) XOR b

(تابع unpad در طول ۱۶ بایت هیچ بایتی حذف نمی‌کند — مطابق unpadBytesOut خود
slowaes که فقط برای طول بیش از ۱۶ فعال است.)
"""

import re

CHALLENGE_COOKIE = "__test"

_RCON = [0x01, 0x02, 0x04, 0x08, 0x10, 0x20, 0x40, 0x80, 0x1B, 0x36]


def _gmul(a, b):
    """ضرب در میدان GF(2^8) با چندجمله‌ای 0x11B."""
    p = 0
    for _ in range(8):
        if b & 1:
            p ^= a
        hi = a & 0x80
        a = (a << 1) & 0xFF
        if hi:
            a ^= 0x1B
        b >>= 1
    return p


def _build_sbox():
    """جدول SBOX استاندارد AES از وارون ضربی + تبدیل افین (بدون خطای تایپی)."""
    def rotl(x, n):
        return ((x << n) | (x >> (8 - n))) & 0xFF

    inv = [0] * 256
    for x in range(1, 256):
        for y in range(1, 256):
            if _gmul(x, y) == 1:
                inv[x] = y
                break
    sbox = [0] * 256
    for x in range(256):
        sbox[x] = inv[x] ^ rotl(inv[x], 1) ^ rotl(inv[x], 2) ^ rotl(inv[x], 3) ^ rotl(inv[x], 4) ^ 0x63
    return sbox


_SBOX = _build_sbox()
_INV_SBOX = [_SBOX.index(i) for i in range(256)]


def _expand_key128(key):
    """گسترش کلید AES-128 → ۱۱ کلید راند ۱۶ بایتی."""
    w = [list(key[i * 4:i * 4 + 4]) for i in range(4)]
    for i in range(4, 44):
        t = list(w[i - 1])
        if i % 4 == 0:
            t = t[1:] + t[:1]                    # RotWord
            t = [_SBOX[b] for b in t]            # SubWord
            t[0] ^= _RCON[i // 4 - 1]
        w.append([w[i - 4][j] ^ t[j] for j in range(4)])
    return [sum((w[r * 4 + c] for c in range(4)), []) for r in range(11)]


def aes128_decrypt_block(key, block):
    """رمزگشایی AES-128 یک بلوک ۱۶ بایتی (FIPS-197 InvCipher — بردار آزمون پایین)."""
    assert len(key) == 16 and len(block) == 16
    rks = _expand_key128(key)
    s = [[block[r::4][c] for c in range(4)] for r in range(4)]  # s[r][c] = block[4c+r]

    def add_round_key(rnd):
        rk = rks[rnd]
        for c in range(4):
            for r in range(4):
                s[r][c] ^= rk[c * 4 + r]

    def inv_shift_sub():
        for r in range(4):
            if r:
                s[r] = s[r][-r:] + s[r][:-r]     # InvShiftRows
            s[r] = [_INV_SBOX[b] for b in s[r]]  # InvSubBytes

    def inv_mix_cols():
        for c in range(4):
            a = [s[r][c] for r in range(4)]
            for r in range(4):
                s[r][c] = (_gmul(a[r], 14) ^ _gmul(a[(r + 1) % 4], 11)
                           ^ _gmul(a[(r + 2) % 4], 13) ^ _gmul(a[(r + 3) % 4], 9))

    add_round_key(10)
    for rnd in range(9, 0, -1):
        inv_shift_sub()
        add_round_key(rnd)
        inv_mix_cols()
    inv_shift_sub()
    add_round_key(0)
    return bytes(s[r][c] for c in range(4) for r in range(4))


def looks_like_challenge(text):
    """آیا این متن صفحه حفاظتی /aes.js است؟ (بدون وابستگی به کد وضعیت)"""
    if not text:
        return False
    return "toNumbers(" in text and ("slowaes" in text.lower() or "/aes.js" in text)


def solve_cookie(html):
    """محاسبه مقدار کوکی ``__test`` از صفحه حفاظتی — خروجی رشته hex یا None."""
    vals = re.findall(r'toNumbers\("([0-9a-fA-F]{32})"\)', html or "")
    if len(vals) < 3:
        return None
    a, b, c = (bytes.fromhex(v) for v in vals[:3])
    try:
        p = aes128_decrypt_block(a, c)  # slowAES CBC، بلوک اول: D(c) XOR iv
    except (AssertionError, ValueError):
        return None
    return bytes(x ^ y for x, y in zip(p, b)).hex()
