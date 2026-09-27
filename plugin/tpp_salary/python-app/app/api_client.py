# -*- coding: utf-8 -*-
"""
کلاینت REST API سایت — آدرس و کلید از کانفیگ خوانده می‌شود (بدون هاردکد).

پشتیبانی از هر دو حالت لینک REST:
- پیوندهای یکتا فعال:  {site}/wp-json/tpp_salary/v1/...
- پیوند یکتای خاموش:   {site}/?rest_route=/tpp_salary/v1/... (fallback خودکار)

نسخه 1.7.4: عبور خودکار از محافظت ضدربات هاست (صفحه /aes.js + کوکی __test) با
Session دائمی (ماندگاری کوکی بین درخواست‌ها) و User-Agent مرورگر — رفع خطای
«پاسخ نامعتبر از سرور (JSON)» روی هاست‌های دارای محافظت ضدربات.
"""

import json
from urllib.parse import urlparse

import requests

from .antibot import CHALLENGE_COOKIE, looks_like_challenge, solve_cookie

NS = "tpp_salary/v1"
TIMEOUT = 30

# برخی هاست‌ها درخواست با UA غیرمرورگری را می‌بندند؛ UA مرورگری پیش‌فرض است.
DEFAULT_UA = ("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
              "(KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36")

MAX_ATTEMPTS = 5  # چالش ×۳ + جابه‌جایی مسیر (404→rest_route) + پاسخ نهایی


class ApiError(Exception):
    def __init__(self, message, status=0, code=""):
        super(ApiError, self).__init__(message)
        self.status = status
        self.code = code


class ApiClient(object):
    def __init__(self, config):
        self.config = config
        self._plain_mode = None  # None=نامشخص، True=rest_route، False=wp-json
        self._session = requests.Session()

    # ---------- ابزار ----------

    def _base(self):
        url = str(self.config.get("api_url", "") or "").strip()
        if not url:
            raise ApiError("آدرس API تنظیم نشده است (منوی تنظیمات)")
        url = url.rstrip("/")
        # اگر کاربر آدرس کامل REST یا wp-json را وارد کرده باشد نرمال می‌شود
        for marker in ("/wp-json/%s" % NS, "/?rest_route=/%s" % NS.replace("/", "\\/")):
            if url.endswith(marker):
                url = url[: -len(marker)]
        if "/wp-json" in url:
            url = url.split("/wp-json")[0]
        return url

    def _key(self):
        key = str(self.config.get("api_key", "") or "").strip()
        if not key:
            raise ApiError("کلید API تنظیم نشده است (منوی تنظیمات)")
        return key

    def _url(self, path):
        base = self._base()
        if self._plain_mode:
            return "%s/?rest_route=/%s%s" % (base, NS, path)
        return "%s/wp-json/%s%s" % (base, NS, path)

    def _headers(self):
        ua = str(self.config.get("user_agent", "") or "").strip() or DEFAULT_UA
        return {
            "X-TPP-Key": self._key(),
            "Accept": "application/json",
            "User-Agent": ua,
        }

    def _set_challenge_cookie(self, url, value):
        host = urlparse(url).hostname or ""
        if host:
            self._session.cookies.set(CHALLENGE_COOKIE, value, domain=host, path="/")

    def _request(self, method, path, json_body=None):
        for attempt in range(MAX_ATTEMPTS):
            url = self._url(path)
            try:
                resp = self._session.request(
                    method, url, headers=self._headers(),
                    json=json_body, timeout=TIMEOUT,
                )
            except requests.exceptions.Timeout:
                raise ApiError("مهلت اتصال به سرور تمام شد")
            except requests.exceptions.ConnectionError as exc:
                raise ApiError("اتصال به سرور برقرار نشد: %s" % str(exc)[:120])
            except Exception as exc:  # noqa: BLE001
                raise ApiError("خطای ارتباط: %s" % str(exc)[:160])

            # صفحه حفاظتی ضدربات هاست → حل چالش و تلاش دوباره با کوکی
            body = resp.text or ""
            if looks_like_challenge(body) and attempt < MAX_ATTEMPTS - 1:
                cookie = solve_cookie(body)
                if cookie:
                    self._set_challenge_cookie(url, cookie)
                    continue
                raise ApiError(
                    "صفحه حفاظتی سرور قابل عبور نیست؛ کمی بعد دوباره تلاش کنید")

            if resp.status_code == 404 and self._plain_mode is None:
                # پیوند یکتا خاموش است → حالت rest_route و تلاش دوباره
                self._plain_mode = True
                continue
            if self._plain_mode is None:
                self._plain_mode = False

            if resp.status_code >= 400:
                try:
                    data = resp.json()
                    msg = data.get("message") or body[:200]
                    code = data.get("code", "")
                except (ValueError, AttributeError):
                    msg, code = body[:200], ""
                if msg.strip().lower().startswith("error code:"):
                    msg = "سرور میزبان موقتاً در دسترس نیست (%s)" % msg.strip()
                raise ApiError(msg, status=resp.status_code, code=code)
            try:
                return resp.json()
            except ValueError:
                if looks_like_challenge(body):
                    raise ApiError(
                        "صفحه حفاظتی سرور قابل عبور نیست؛ کمی بعد دوباره تلاش کنید")
                raise ApiError("پاسخ نامعتبر از سرور (JSON)")

        raise ApiError("مسیر API یافت نشد (404)")

    # ---------- مسیرها ----------

    def ping(self):
        return self._request("GET", "/ping")

    def bundle(self):
        return self._request("GET", "/bundle")

    def push(self, ops):
        return self._request("POST", "/sync", json_body={"ops": ops})

    # ---------- تشخیص ----------

    def status_text(self):
        try:
            data = self.ping()
            return True, "متصل — نسخه پلاگین %s" % data.get("version", "?"), data
        except ApiError as exc:
            return False, str(exc), None
        except Exception as exc:  # noqa: BLE001
            return False, str(exc)[:160], None


def dump_json(obj):
    return json.dumps(obj, ensure_ascii=False)


def load_json(text, fallback=None):
    try:
        return json.loads(text)
    except (ValueError, TypeError):
        return fallback
