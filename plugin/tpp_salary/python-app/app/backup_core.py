# -*- coding: utf-8 -*-
"""پشتیبان‌گیری و بازگردانی کامل — نسخه 1.8.0.

قالب فایل پشتیبان، همان ساختار JSON بکاپ افزونه است (class-tppsalary-backup.php):
    {version, stamp, site, settings, centers, banks, fields, records, profiles}
بنابراین بکاپ برنامه آفلاین و بکاپ افزونه در نگاه داده‌ای با هم سازگارند
(بخش‌های مشترک هر دو قابل بازگردانی‌اند).

- پشتیبان JSON کامل   : همه بخش‌ها
- پشتیبان ZIP بسته کامل: full.json + employees.json + records.json (کیندهای افزونه)
- بازگردانی            : کارمندان با کد ملی/شناسه/نام تطبیق یا ساخته می‌شوند و
  رکوردها به کارمند/مرکز واقعی نگاشت می‌شوند (آینه restore افزونه).
"""

import datetime
import json
import os
import zipfile

from . import jalali as J
from .api_client import load_json, dump_json

APP_VERSION = "1.8.0"


def _f(v, default=0.0):
    try:
        return float(v if v is not None else default)
    except (TypeError, ValueError):
        return default


def _i(v, default=0):
    try:
        return int(v if v is not None else default)
    except (TypeError, ValueError):
        return default


class BackupCore(object):
    """مغزی پشتیبان/بازگردانی — مستقل از Qt (قابل تست هدلس)."""

    def __init__(self, db, store, app_dir):
        self.db = db
        self.store = store
        self.app_dir = app_dir
        self.folder = os.path.join(app_dir, "backups")
        try:
            os.makedirs(self.folder, exist_ok=True)
        except Exception:
            pass

    # ==================================================
    # جمع‌آوری داده (آینه collect_json افزونه)
    # ==================================================

    def snapshot_full(self):
        with self.db.locked():
            centers = self.db.q("SELECT * FROM centers WHERE local_deleted = 0 ORDER BY id")
            banks = self.db.q("SELECT * FROM banks WHERE local_deleted = 0 ORDER BY id")
            fields = self.db.q("SELECT * FROM fields ORDER BY sort ASC, field_key ASC")
            records = self.db.q(
                "SELECT * FROM records WHERE local_deleted = 0"
                " ORDER BY jyear, jmonth, user_id, center_id"
            )
            employees = self.db.q("SELECT * FROM employees WHERE local_deleted = 0 ORDER BY id")
            settings = {
                "company_name": self.db.kv_get("company_name", ""),
                "currency": self.db.kv_get("currency", "ریال"),
                "digits_fa": self.db.kv_get("digits_fa", "0"),
                "formulas": load_json(self.db.kv_get("formulas", "{}"), {}),
                "defaults": load_json(self.db.kv_get("defaults", "{}"), {}),
            }
        now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        jy, jm, jd = J.today_jalali()
        profiles = []
        for e in employees:
            profiles.append({
                "user_id": _i(e["id"]),
                "user_login": str(e.get("login") or ""),
                "display_name": str(e.get("name") or ""),
                "user_email": str(e.get("email") or ""),
                "national_id": str(e.get("national") or ""),
                "mobile": str(e.get("mobile") or ""),
                "terminated": _i(e.get("terminated")),
                "centers": load_json(e.get("centers"), []),
                "profile": load_json(e.get("profile"), {}),
            })
        return {
            "version": APP_VERSION,
            "app": "tpp-salary-python",
            "stamp": now,
            "site": {"url": self.db.kv_get("site_url", "") or "", "stamp": now,
                     "jalali": "%04d/%02d/%02d" % (jy, jm, jd)},
            "settings": settings,
            "centers": centers,
            "banks": banks,
            "fields": fields,
            "records": records,
            "profiles": profiles,
        }

    def _section(self, full, name):
        return full.get(name) if isinstance(full.get(name), list) else []

    def _collect_employees_only(self, full):
        return {
            "version": full.get("version", APP_VERSION),
            "stamp": full.get("stamp", ""),
            "kind": "employees",
            "centers": self._section(full, "centers"),
            "profiles": self._section(full, "profiles"),
        }

    def _collect_records_only(self, full):
        return {
            "version": full.get("version", APP_VERSION),
            "stamp": full.get("stamp", ""),
            "kind": "records",
            "centers": self._section(full, "centers"),
            "fields": self._section(full, "fields"),
            "records": self._section(full, "records"),
            "profiles": self._section(full, "profiles"),
        }

    # ==================================================
    # ساخت فایل پشتیبان
    # ==================================================

    def _new_path(self, ext):
        now = datetime.datetime.now()
        base = "tpp-backup-" + now.strftime("%Y-%m-%d_%H%M%S")
        path = os.path.join(self.folder, base + ext)
        n = 2
        while os.path.exists(path):
            path = os.path.join(self.folder, "%s-%d%s" % (base, n, ext))
            n += 1
        return path

    def create_json(self, target_path=None):
        full = self.snapshot_full()
        path = target_path or self._new_path(".json")
        tmp = path + ".part"
        with open(tmp, "w", encoding="utf-8") as fh:
            json.dump(full, fh, ensure_ascii=False, default=str)
        os.replace(tmp, path)
        return path

    def create_zip(self, target_path=None):
        full = self.snapshot_full()
        path = target_path or self._new_path(".zip")
        tmp = path + ".part"
        with zipfile.ZipFile(tmp, "w", zipfile.ZIP_DEFLATED) as z:
            z.writestr("full.json", json.dumps(full, ensure_ascii=False, default=str))
            z.writestr("employees.json", json.dumps(
                self._collect_employees_only(full), ensure_ascii=False, default=str))
            z.writestr("records.json", json.dumps(
                self._collect_records_only(full), ensure_ascii=False, default=str))
        os.replace(tmp, path)
        return path

    def backup_files(self):
        """فهرست فایل‌های پشتیبان دستی (json/zip) قدیمی → جدید."""
        try:
            names = [n for n in os.listdir(self.folder)
                     if n.startswith("tpp-backup-") and (n.endswith(".json") or n.endswith(".zip"))]
        except Exception:
            return []
        return [os.path.join(self.folder, n) for n in sorted(names)]

    # ==================================================
    # خواندن فایل پشتیبان (json / zip — خودی یا افزونه)
    # ==================================================

    def load_backup(self, path):
        """dict داده بکاپ — پشتیبانی از JSON کامل، ZIP (full.json/employees.json/
        records.json) و بکاپ افزونه. خروجی نامعتبر → ValueError با پیام فارسی."""
        ext = os.path.splitext(path)[1].lower()
        if ext == ".zip":
            with zipfile.ZipFile(path, "r") as z:
                names = z.namelist()
                pick = None
                for cand in ("full.json", "employees.json", "records.json"):
                    for n in names:
                        if n.endswith(cand):
                            pick = n
                            break
                    if pick:
                        break
                if not pick:
                    # بسته افزونه: هر json دارای version/kind
                    for n in names:
                        if n.endswith(".json"):
                            try:
                                data = json.loads(z.read(n).decode("utf-8"))
                            except Exception:
                                continue
                            if isinstance(data, dict) and (data.get("version") or data.get("kind")):
                                pick = n
                                break
                if not pick:
                    raise ValueError("فایل ZIP بکاپ معتبر نیست (فایل JSON یافت نشد)")
                data = json.loads(z.read(pick).decode("utf-8"))
        else:
            with open(path, "r", encoding="utf-8") as fh:
                data = json.load(fh)
        if not isinstance(data, dict) or not (data.get("version") or data.get("kind")):
            raise ValueError("ساختار فایل بکاپ شناخته نشد (کلید version/kind موجود نیست)")
        return data

    def describe(self, data):
        """خلاصه شمارش بخش‌ها برای نمایش پیش از بازگردانی."""
        profiles = self._section(data, "profiles")
        return {
            "version": str(data.get("version", "?")),
            "kind": str(data.get("kind", "full")),
            "stamp": str(data.get("stamp", "")),
            "profiles": len(profiles),
            "records": len(self._section(data, "records")),
            "centers": len(self._section(data, "centers")),
            "banks": len(self._section(data, "banks")),
            "fields": len(self._section(data, "fields")),
        }

    # ==================================================
    # بازگردانی (آینه apply_restore افزونه)
    # ==================================================

    def restore(self, data):
        """بازگردانی در دیتابیس محلی — در یک تراکنش؛ خروجی: خلاصه شمارش‌ها.

        نکته: بازگردانی «جایگزین داده محلی» است (هم‌سان افزونه)؛ صف ارسال
        (outbox) پاک می‌شود تا تغییرات قدیمی روی داده تازه اعمال نشود.
        """
        summary = {
            "records": 0, "users_created": 0, "users_matched": 0,
            "centers": 0, "banks": 0, "fields": 0,
        }

        profiles = self._section(data, "profiles")
        employees = self._section(data, "employees")
        # سازگاری: برخی بکاپ‌ها کارمندان را در profiles و برخی در employees دارند
        if not profiles and employees:
            profiles = employees

        centers = self._section(data, "centers")
        banks = self._section(data, "banks")
        fields = self._section(data, "fields")
        records = self._section(data, "records")

        stmts = []          # [(sql, params)]
        user_map = {}       # old user_id → local id
        center_map = {}

        # --- کارمندان: تطبیق با کد ملی → شناسه → نام کاربری → نام ---
        local_emps = {int(r["id"]): r for r in self.db.q("SELECT * FROM employees")}
        by_national = {J.en_digits(str(r.get("national") or "")).strip(): int(r["id"])
                       for r in local_emps.values()}
        by_login = {str(r.get("login") or "").strip().lower(): int(r["id"])
                    for r in local_emps.values()}
        by_name = {str(r.get("name") or "").strip(): int(r["id"])
                   for r in local_emps.values()}

        def next_id(rows_seen):
            m = min(rows_seen) if rows_seen else 0
            return -1 if m >= 0 else m - 1

        used_ids = set(local_emps.keys())
        for p in profiles:
            if not isinstance(p, dict):
                continue
            old_uid = _i(p.get("user_id"))
            national = J.en_digits(str(p.get("national_id") or p.get("national") or "")).strip()
            login = str(p.get("user_login") or p.get("login") or "").strip()
            name = str(p.get("display_name") or p.get("name") or "").strip()
            profile = p.get("profile") if isinstance(p.get("profile"), dict) else load_json(p.get("profile"), {})
            centers_ids = p.get("centers")
            if centers_ids is None:
                centers_ids = profile.get("centers", [])
            centers_ids = [_i(c) for c in (centers_ids or [])]
            terminated = _i(p.get("terminated"), 0)
            mobile = str(p.get("mobile") or "")
            email = str(p.get("user_email") or p.get("email") or "")

            target = None
            if national and national in by_national:
                target = by_national[national]
                summary["users_matched"] += 1
            elif old_uid and old_uid in local_emps and not national and not login:
                target = old_uid
                summary["users_matched"] += 1
            elif login and login.lower() in by_login:
                target = by_login[login.lower()]
                summary["users_matched"] += 1
            elif name and name in by_name:
                target = by_name[name]
                summary["users_matched"] += 1
            else:
                if old_uid and old_uid not in used_ids and old_uid > 0:
                    target = old_uid
                else:
                    target = next_id(used_ids)
                used_ids.add(target)
                summary["users_created"] += 1
            if name and name not in by_name:
                by_name[name] = target
            user_map[old_uid] = target
            profile["full_name"] = name
            stmts.append((
                "INSERT INTO employees(id, name, login, email, national, mobile, terminated, centers, profile, pending, local_deleted)"
                " VALUES(?,?,?,?,?,?,?,?,?,0,0)"
                " ON CONFLICT(id) DO UPDATE SET name=excluded.name, login=excluded.login,"
                " email=excluded.email, national=excluded.national, mobile=excluded.mobile,"
                " terminated=excluded.terminated, centers=excluded.centers, profile=excluded.profile,"
                " pending=0, local_deleted=0",
                (target, name, login, email, national, mobile, terminated,
                 dump_json(centers_ids), dump_json(profile)),
            ))

        # --- مراکز (شناسه اصلی حفظ می‌شود) ---
        for c in centers:
            if not isinstance(c, dict):
                continue
            cid = _i(c.get("id"))
            if not cid:
                continue
            center_map[cid] = cid
            stmts.append((
                "INSERT INTO centers(id, name, created_at, pending, local_deleted)"
                " VALUES(?,?,?,0,0)"
                " ON CONFLICT(id) DO UPDATE SET name=excluded.name, pending=0, local_deleted=0",
                (cid, str(c.get("name") or ""), str(c.get("created_at") or "")),
            ))
            summary["centers"] += 1

        # --- بانک‌ها ---
        for b in banks:
            if not isinstance(b, dict):
                continue
            bid = _i(b.get("id"))
            if not bid:
                continue
            stmts.append((
                "INSERT INTO banks(id, name, sort_order, pending, local_deleted)"
                " VALUES(?,?,?,0,0) ON CONFLICT(id) DO UPDATE SET name=excluded.name,"
                " sort_order=excluded.sort_order, pending=0, local_deleted=0",
                (bid, str(b.get("name") or ""), _i(b.get("sort_order"), 0)),
            ))
            summary["banks"] += 1

        # --- فیلدها (بازنویسی کامل — هم‌سان افزونه؛ قالب افزونه یا برنامه) ---
        if fields:
            stmts.append(("DELETE FROM fields", ()))
            for f in fields:
                if not isinstance(f, dict):
                    continue
                key = str(f.get("field_key") or f.get("key") or "").strip()
                if not key:
                    continue
                ftype = str(f.get("field_type") or f.get("type") or "number")
                stmts.append((
                    "INSERT OR REPLACE INTO fields(field_key, label, type, default_value, formula, calculated, allow_manual, sort)"
                    " VALUES(?,?,?,?,?,?,?,?)",
                    (key, str(f.get("label") or key), ftype,
                     str(f.get("default_value") or f.get("default") or ""),
                     str(f.get("formula") or ""),
                     _i(f.get("calculated"), 0), _i(f.get("allow_manual"), 1), _i(f.get("sort"), 0)),
                ))
                summary["fields"] += 1

        # --- رکوردهای حقوق (بازنویسی کامل + نگاشت کارمند/مرکز) ---
        if records:
            stmts.append(("DELETE FROM records", ()))
            next_rid = -1
            for r in records:
                if not isinstance(r, dict):
                    continue
                payload = r.get("payload")
                if isinstance(payload, str):
                    payload = load_json(payload, {})
                if not isinstance(payload, dict):
                    payload = {}
                old_uid = _i(r.get("user_id"))
                uid = user_map.get(old_uid, old_uid)
                cid = _i(r.get("center_id"))
                cid = center_map.get(cid, cid)
                jyear = _i(r.get("jyear"))
                jmonth = _i(r.get("jmonth"))
                if not uid or not jyear or not jmonth:
                    continue
                rid = _i(r.get("id"), 0)
                if rid <= 0:
                    rid = next_rid
                    next_rid -= 1
                stmts.append((
                    "INSERT OR REPLACE INTO records(id, user_id, center_id, jyear, jmonth, payload,"
                    " gross, insurable, insurance_deduct, other_deductions, net, updated_at, created_at, pending, local_deleted)"
                    " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,0,0)",
                    (rid, uid, cid, jyear, jmonth, dump_json(payload),
                     _f(r.get("gross")), _f(r.get("insurable")), _f(r.get("insurance_deduct")),
                     _f(r.get("other_deductions")), _f(r.get("net")),
                     str(r.get("updated_at") or ""), str(r.get("created_at") or "")),
                ))
                summary["records"] += 1

        # --- تنظیمات (پالایش‌شده — آینه sanitize_restored_settings افزونه) ---
        settings = data.get("settings") if isinstance(data.get("settings"), dict) else {}
        if settings.get("company_name"):
            stmts.append(("INSERT INTO kv(k, v) VALUES('company_name', ?)"
                          " ON CONFLICT(k) DO UPDATE SET v=excluded.v",
                          (str(settings.get("company_name")),)))
        if settings.get("currency"):
            stmts.append(("INSERT INTO kv(k, v) VALUES('currency', ?)"
                          " ON CONFLICT(k) DO UPDATE SET v=excluded.v",
                          (str(settings.get("currency")),)))
        if "formulas" in settings and isinstance(settings.get("formulas"), dict):
            stmts.append(("INSERT INTO kv(k, v) VALUES('formulas', ?)"
                          " ON CONFLICT(k) DO UPDATE SET v=excluded.v",
                          (dump_json(settings.get("formulas") or {}),)))
        if "defaults" in settings and isinstance(settings.get("defaults"), dict):
            stmts.append(("INSERT INTO kv(k, v) VALUES('defaults', ?)"
                          " ON CONFLICT(k) DO UPDATE SET v=excluded.v",
                          (dump_json(settings.get("defaults") or {}),)))

        # --- پاک‌سازی صف ارسال و فلگ‌ها (جایگزینی کامل وضعیت محلی) ---
        stmts.append(("DELETE FROM outbox", ()))
        stmts.append(("UPDATE records SET pending = 0 WHERE pending = 1", ()))

        try:
            self.db.exmany(stmts)
        except Exception:
            raise
        self.db.log("بازگردانی پشتیبان: %s" % json.dumps(summary, ensure_ascii=False))
        return summary
