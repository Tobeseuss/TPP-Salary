# -*- coding: utf-8 -*-
"""
TPP Salary — نسخه آفلاین (دسکتاپ)
نقطه ورود برنامه: بررسی و نصب خودکار وابستگی‌ها و سپس اجرای رابط کاربری.

با یک کلیک (TPP Salary.bat یا اجرای همین فایل) همه وابستگی‌های لازم به‌صورت
خودکار بررسی و در صورت نبود نصب می‌شوند و سپس برنامه بالا می‌آید.

آدرس API و کلید API هاردکد نمی‌شوند — پس از اولین اجرا از منوی «تنظیمات»
آدرس سایت و کلید API (از پیشخوان ← تنظیمات ← تب «برنامه آفلاین») را وارد کنید.
"""

import os
import sys
import subprocess

APP_DIR = os.path.dirname(os.path.abspath(__file__))
DATA_DIR = os.path.join(APP_DIR, "data")
PY_VER = "%d.%d" % (sys.version_info[0], sys.version_info[1])

# وابستگی‌های لازم: نام ماژول import → نام بسته pip
REQUIRED = [
    ("requests", "requests>=2.28"),
    ("PySide6", "PySide6>=6.5"),
    ("openpyxl", "openpyxl>=3.1"),
]


def ensure_dirs():
    try:
        os.makedirs(DATA_DIR, exist_ok=True)
    except Exception:
        pass


def missing_packages():
    missing = []
    for module_name, pip_name in REQUIRED:
        try:
            __import__(module_name)
        except Exception:
            missing.append((module_name, pip_name))
    return missing


def install_packages(packages):
    """نصب خودکار بسته‌های جاافتاده با pip (اول global و در صورت نیاز --user)."""
    results = []
    for _module_name, pip_name in packages:
        cmds = [
            [sys.executable, "-m", "pip", "install", "--disable-pip-version-check", pip_name],
            [sys.executable, "-m", "pip", "install", "--disable-pip-version-check", "--user", pip_name],
        ]
        ok = False
        last_err = ""
        for cmd in cmds:
            try:
                proc = subprocess.run(
                    cmd, stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                    text=True, timeout=900,
                )
                if proc.returncode == 0:
                    ok = True
                    break
                last_err = (proc.stdout or "")[-500:]
            except Exception as exc:  # noqa: BLE001
                last_err = str(exc)
        results.append((pip_name, ok, last_err))
    return results


def fail(message):
    print("\n" + "=" * 62)
    print(message)
    print("=" * 62)
    try:
        input("\nبرای بستن Enter را فشار دهید…")
    except EOFError:
        pass
    sys.exit(1)


def main():
    ensure_dirs()

    if sys.version_info[:2] < (3, 8):
        fail("پایتون ۳.۸ یا جدیدتر لازم است (نسخه فعلی: %s)" % PY_VER)

    missing = missing_packages()
    if missing:
        names = ", ".join(p for _, p in missing)
        print("[TPP Salary] نصب خودکار وابستگی‌ها: %s" % names)
        print("[TPP Salary] این کار فقط بار اول انجام می‌شود و ممکن است چند دقیقه طول بکشد…")
        results = install_packages(missing)
        failed = [r for r in results if not r[1]]
        if failed:
            for pip_name, _ok, err in failed:
                print("[TPP Salary] نصب %s ناموفق بود: %s" % (pip_name, err[-200:]))
            fail(
                "نصب وابستگی‌ها ناموفق بود.\n"
                "۱) اتصال اینترنت را بررسی کنید.\n"
                "۲) به‌صورت دستی اجرا کنید:  python -m pip install -r requirements.txt\n"
                "۳) سپس دوباره برنامه را اجرا کنید."
            )
        # پس از نصب، ماژول‌های تازه باید از کش import خارج شوند.
        try:
            import importlib  # noqa: F401
            importlib.invalidate_caches()
        except Exception:
            pass
        still = missing_packages()
        if still:
            fail("بسته‌های %s پس از نصب در دسترس نیستند؛ پایتون را ری‌استارت و دوباره اجرا کنید." % ", ".join(m for m, _ in still))

    print("[TPP Salary] اجرای برنامه…")
    sys.path.insert(0, APP_DIR)
    try:
        from app.main import run  # noqa: E402
    except ImportError as exc:
        fail("بارگذاری برنامه ناموفق بود: %s" % exc)
    run(APP_DIR)


if __name__ == "__main__":
    # خطای‌های غیرمنتظره را هم نمایش بده تا پنجره ناگهان بسته نشود.
    try:
        main()
    except SystemExit:
        raise
    except Exception:  # noqa: BLE001
        import traceback

        traceback.print_exc()
        try:
            input("\nخطایی رخ داد؛ برای بستن Enter را فشار دهید…")
        except EOFError:
            pass
        raise
