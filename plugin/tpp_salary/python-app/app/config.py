# -*- coding: utf-8 -*-
"""تنظیمات برنامه — ذخیره در data/config.json (آدرس و کلید API هاردکد نمی‌شوند)."""

import json
import os
import threading

DEFAULTS = {
    "api_url": "",          # مثال: http://localhost/wp  یا  http://localhost/wp/wp-json/tpp_salary/v1
    "api_key": "",          # کلید تولیدشده در پیشخوان — فقط از کاربر دریافت می‌شود
    "sync_interval": 60,    # ثانیه
    "auto_sync": True,
    "digits_fa": False,     # نسخه 1.7.1: همه اعداد انگلیسی (بی‌اثر — سازگاری)
    "theme": "light",
    "user_agent": "",       # نسخه 1.7.4: خالی = UA مرورگر (عبور از محافظت ضدربات)
}


class Config(object):
    def __init__(self, app_dir):
        self.path = os.path.join(app_dir, "data", "config.json")
        self._lock = threading.Lock()
        self.data = dict(DEFAULTS)
        self.load()

    def load(self):
        try:
            with open(self.path, "r", encoding="utf-8") as fh:
                stored = json.load(fh)
            if isinstance(stored, dict):
                self.data.update({k: v for k, v in stored.items() if k in DEFAULTS})
        except Exception:
            pass

    def save(self):
        with self._lock:
            try:
                os.makedirs(os.path.dirname(self.path), exist_ok=True)
                with open(self.path, "w", encoding="utf-8") as fh:
                    json.dump(self.data, fh, ensure_ascii=False, indent=2)
            except Exception:
                pass

    def get(self, key, fallback=None):
        return self.data.get(key, DEFAULTS.get(key, fallback))

    def set(self, key, value, save=True):
        self.data[key] = value
        if save:
            self.save()

    def is_configured(self):
        return bool(str(self.get("api_url", "")).strip()) and bool(str(self.get("api_key", "")).strip())
