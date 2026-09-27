# -*- coding: utf-8 -*-
"""
Live-API test (sandbox only — NOT packaged):
1) pure-python AES-128 encrypt/decrypt verified against FIPS-197 vectors
2) fetches the anti-bot JS challenge page from the real site
3) solves __test cookie — server's slowAES uses modeOfOperation {OFB:0,CFB:1,CBC:2},
   the page calls slowAES.decrypt(c, 2, a, b) => CBC decrypt, single block:
   cookie = AES128_DECRYPT(key=a, data=c) XOR b   (no unpadding at 16 bytes)
4) retries ping (expects JSON), then bundle with the user-provided key

Key is read from env TPP_TEST_KEY (never hardcoded in the repo).
"""
import os
import re
import sys

import requests

URL_PING = "https://tpptc.ir/wp-json/tpp_salary/v1/ping"
URL_BUNDLE = "https://tpptc.ir/wp-json/tpp_salary/v1/bundle"
KEY = os.environ.get("TPP_TEST_KEY", "")
UA = ("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
      "(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36")

# ---------------------------------------------------------------------------
# pure-python AES-128 (encrypt + decrypt one block)
# ---------------------------------------------------------------------------
_RCON = [0x01, 0x02, 0x04, 0x08, 0x10, 0x20, 0x40, 0x80, 0x1B, 0x36]


def _gmul(a, b):
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


def _xtime(a):
    a <<= 1
    if a & 0x100:
        a = (a ^ 0x1B) & 0xFF
    return a


def _expand_key128(key):
    w = [list(key[i * 4:i * 4 + 4]) for i in range(4)]
    for i in range(4, 44):
        t = list(w[i - 1])
        if i % 4 == 0:
            t = t[1:] + t[:1]
            t = [_SBOX[b] for b in t]
            t[0] ^= _RCON[i // 4 - 1]
        w.append([w[i - 4][j] ^ t[j] for j in range(4)])
    return [sum((w[r * 4 + c] for c in range(4)), []) for r in range(11)]


def aes128_encrypt_block(key, block):
    """AES-128 encrypt a single 16-byte block (FIPS-197)."""
    assert len(key) == 16 and len(block) == 16
    rks = _expand_key128(key)
    s = [[block[r::4][c] for c in range(4)] for r in range(4)]  # s[r][c] = block[4c+r]

    def add_round_key(rnd):
        rk = rks[rnd]
        for c in range(4):
            for r in range(4):
                s[r][c] ^= rk[c * 4 + r]

    def sub_shift():
        for r in range(4):
            s[r] = [_SBOX[b] for b in s[r]]
            s[r] = s[r][r:] + s[r][:r]

    def mix_cols():
        for c in range(4):
            a = [s[r][c] for r in range(4)]
            t = a[0] ^ a[1] ^ a[2] ^ a[3]
            for r in range(4):
                s[r][c] = a[r] ^ t ^ _xtime(a[r] ^ a[(r + 1) % 4])

    add_round_key(0)
    for rnd in range(1, 10):
        sub_shift()
        mix_cols()
        add_round_key(rnd)
    sub_shift()
    add_round_key(10)
    return bytes(s[r][c] for c in range(4) for r in range(4))


def aes128_decrypt_block(key, block):
    """AES-128 decrypt a single 16-byte block (FIPS-197 InvCipher)."""
    assert len(key) == 16 and len(block) == 16
    rks = _expand_key128(key)
    s = [[block[r::4][c] for c in range(4)] for r in range(4)]

    def add_round_key(rnd):
        rk = rks[rnd]
        for c in range(4):
            for r in range(4):
                s[r][c] ^= rk[c * 4 + r]

    def inv_shift_sub():
        for r in range(4):
            if r:
                s[r] = s[r][-r:] + s[r][:-r]
            s[r] = [_INV_SBOX[b] for b in s[r]]

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


def solve_challenge(html):
    """Return the __test cookie value from the JS challenge page (CBC variant)."""
    vals = re.findall(r'toNumbers\("([0-9a-fA-F]{32})"\)', html)
    if len(vals) < 3:
        return None
    a, b, c = (bytes.fromhex(v) for v in vals[:3])
    p = aes128_decrypt_block(a, c)  # slowAES CBC decrypt, first block: D(c) XOR iv
    return bytes(x ^ y for x, y in zip(p, b)).hex()


# ---------------------------------------------------------------------------
# live flow
# ---------------------------------------------------------------------------
def main():
    ok = True

    # 1) FIPS-197 vectors (both directions)
    ct = aes128_encrypt_block(bytes(range(16)), bytes.fromhex("00112233445566778899aabbccddeeff"))
    exp = "69c4e0d86a7b0430d8cdb78070b4c55a"
    e_ok = ct.hex() == exp
    pt = aes128_decrypt_block(bytes(range(16)), bytes.fromhex(exp))
    d_ok = pt == bytes.fromhex("00112233445566778899aabbccddeeff")
    print("AES FIPS-197 encrypt vector:", "PASS" if e_ok else "FAIL " + ct.hex())
    print("AES FIPS-197 decrypt vector:", "PASS" if d_ok else "FAIL " + pt.hex())
    ok = ok and e_ok and d_ok

    s = requests.Session()
    s.headers.update({"User-Agent": UA, "Accept": "application/json"})

    # 2) first ping — expect challenge HTML
    r1 = s.get(URL_PING, timeout=30)
    print("ping#1 status=%s type=%s len=%d" % (r1.status_code, r1.headers.get("content-type"), len(r1.text)))
    challenge = solve_challenge(r1.text)
    if challenge:
        print("challenge solved (CBC) → __test=" + challenge)
        s.cookies.set("__test", challenge, domain="tpptc.ir", path="/")
    else:
        print("no challenge pattern found; body head:", r1.text[:120].replace("\n", " "))

    # 3) ping again — expect JSON
    r2 = s.get(URL_PING, timeout=30)
    print("ping#2 status=%s type=%s sent-cookie=%s" % (r2.status_code, r2.headers.get("content-type"), r2.request.headers.get("Cookie")))
    try:
        data = r2.json()
        print("ping#2 JSON OK:", {k: data.get(k) for k in ("success", "ok", "version", "plugin")})
    except ValueError:
        print("ping#2 NOT JSON, head:", r2.text[:200].replace("\n", " "))
        ok = False

    # 4) bundle with key (read-only)
    if KEY:
        r3 = s.get(URL_BUNDLE, timeout=60, headers={"X-TPP-Key": KEY})
        print("bundle status=%s type=%s len=%d" % (r3.status_code, r3.headers.get("content-type"), len(r3.text)))
        try:
            b = r3.json()
            employees = b.get("employees") or []
            centers = b.get("centers") or []
            records = b.get("records") or []
            print("bundle JSON OK: employees=%d centers=%d records=%d version=%s"
                  % (len(employees), len(centers), len(records), b.get("version")))
        except ValueError:
            print("bundle NOT JSON, head:", r3.text[:200].replace("\n", " "))
            ok = False
    else:
        print("bundle skipped (TPP_TEST_KEY not set)")

    print("RESULT:", "ALL PASS" if ok else "FAIL")
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
