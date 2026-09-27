# -*- coding: utf-8 -*-
"""
لایه داده برنامه — همه خواندن/نوشتن از دیتابیس محلی؛
هر تغییر محلی در صف outbox ثبت می‌شود تا پس از اتصال به سایت اعمال گردد.
(استفاده در حالت آفلاین کاملاً مستقل است — هیچ تماس شبکه‌ای اینجا انجام نمی‌شود.)
"""

import datetime
import uuid

from . import formulas as F
from . import jalali as J
from .api_client import load_json, dump_json


def now_mysql():
    return datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")


class Store(object):

    def __init__(self, db):
        self.db = db

    # ==================================================
    # صف outbox
    # ==================================================

    def enqueue(self, entity, action, data, client_updated_at=None):
        ref = uuid.uuid4().hex[:16]
        self.db.ex(
            "INSERT INTO outbox(ref, entity, action, data, client_updated_at, status, created_at)"
            " VALUES(?,?,?,?,?,'pending',?)",
            (ref, entity, action, dump_json(data), client_updated_at or now_mysql(), now_mysql()),
        )
        return ref

    def pending_count(self):
        row = self.db.one("SELECT COUNT(*) AS c FROM outbox WHERE status IN ('pending','conflict')")
        return int(row["c"]) if row else 0

    def pending_ops(self, limit=300):
        rows = self.db.q(
            "SELECT * FROM outbox WHERE status = 'pending' ORDER BY id ASC LIMIT ?", (limit,)
        )
        ops = []
        for r in rows:
            ops.append({
                "outbox_id": r["id"],
                "ref": r["ref"],
                "action": r["action"],
                "client_updated_at": r["client_updated_at"],
                "payload": load_json(r["data"], {}),
            })
        return ops

    # ==================================================
    # شناسه‌های محلی منفی برای موجودیت‌های جدید
    # ==================================================

    def _next_local_id(self, table):
        row = self.db.one("SELECT MIN(id) AS m FROM %s" % table)
        m = int(row["m"]) if row and row["m"] is not None else 0
        return -1 if m >= 0 else m - 1

    def apply_id_map(self, table, local_id, server_id, fk_updates=None):
        """پس از اعمال op روی سرور، شناسه منفی محلی به شناسه سرور نگاشت می‌شود."""
        stmts = [
            ("UPDATE %s SET id = ?, pending = 0 WHERE id = ?" % table, (server_id, local_id)),
        ]
        for tbl, col in (fk_updates or []):
            stmts.append(("UPDATE %s SET %s = ? WHERE %s = ?" % (tbl, col, col), (server_id, local_id)))
        self.db.exmany(stmts)

    # ==================================================
    # مراکز
    # ==================================================

    def centers(self, include_deleted=False):
        sql = "SELECT * FROM centers"
        if not include_deleted:
            sql += " WHERE local_deleted = 0"
        return self.db.q(sql + " ORDER BY name COLLATE NOCASE ASC")

    def center(self, center_id):
        return self.db.one("SELECT * FROM centers WHERE id = ?", (center_id,))

    def save_center(self, center_id, name):
        name = str(name or "").strip()
        if not name:
            raise ValueError("نام مرکز الزامی است")
        if center_id and int(center_id) < 0:
            row = self.db.one("SELECT id FROM centers WHERE id = ?", (center_id,))
            if row:
                self.db.ex("UPDATE centers SET name = ?, pending = 1 WHERE id = ?", (name, center_id))
                self.enqueue("center", "center.upsert", {"center": {"id": center_id, "name": name}})
                return center_id
        # جدید یا نگاشت‌نشده
        if center_id and self.center(center_id):
            self.db.ex("UPDATE centers SET name = ?, pending = 1 WHERE id = ?", (name, center_id))
            self.enqueue("center", "center.upsert", {"center": {"id": center_id, "name": name}})
            return center_id
        new_id = self._next_local_id("centers")
        self.db.ex(
            "INSERT INTO centers(id, name, created_at, pending) VALUES(?,?,?,1)",
            (new_id, name, now_mysql()),
        )
        self.enqueue("center", "center.upsert", {"center": {"id": new_id, "name": name}})
        return new_id

    def delete_center(self, center_id):
        if int(center_id) < 0:
            self._drop_local_outbox("center", lambda d: int(d.get("center", {}).get("id", 0) or 0) == int(center_id))
            self.db.ex("DELETE FROM centers WHERE id = ?", (center_id,))
        else:
            self.db.ex("UPDATE centers SET local_deleted = 1, pending = 1 WHERE id = ?", (center_id,))
            self.enqueue("center", "center.delete", {"center_id": int(center_id)})

    def _drop_local_outbox(self, entity, matcher):
        """حذف صف‌های عملیاتی موجودیت منفیِ محلی که هنوز به سرور ارسال نشده‌اند.
        matcher: تابع (data dict) → bool"""
        rows = self.db.q("SELECT id, data FROM outbox WHERE entity = ? AND status = 'pending'", (entity,))
        for r in rows:
            data = load_json(r["data"], {})
            try:
                if matcher(data):
                    self.db.ex("DELETE FROM outbox WHERE id = ?", (r["id"],))
            except Exception:
                continue

    # ==================================================
    # بانک‌ها
    # ==================================================

    def banks(self, include_deleted=False):
        sql = "SELECT * FROM banks"
        if not include_deleted:
            sql += " WHERE local_deleted = 0"
        return self.db.q(sql + " ORDER BY sort_order ASC, name COLLATE NOCASE ASC")

    def save_bank(self, bank_id, name):
        name = str(name or "").strip()
        if not name:
            raise ValueError("نام بانک الزامی است")
        if bank_id and self.db.one("SELECT id FROM banks WHERE id = ?", (bank_id,)):
            self.db.ex("UPDATE banks SET name = ?, pending = 1 WHERE id = ?", (name, bank_id))
            self.enqueue("bank", "bank.upsert", {"bank": {"id": bank_id, "name": name}})
            return bank_id
        new_id = self._next_local_id("banks")
        self.db.ex("INSERT INTO banks(id, name, pending) VALUES(?,?,1)", (new_id, name))
        self.enqueue("bank", "bank.upsert", {"bank": {"id": new_id, "name": name}})
        return new_id

    def delete_bank(self, bank_id):
        if int(bank_id) < 0:
            self.db.ex("DELETE FROM banks WHERE id = ?", (bank_id,))
        else:
            self.db.ex("UPDATE banks SET local_deleted = 1, pending = 1 WHERE id = ?", (bank_id,))
            self.enqueue("bank", "bank.delete", {"bank_id": int(bank_id)})

    # ==================================================
    # کارمندان
    # ==================================================

    def employees(self, search="", include_terminated=True, center_id=None):
        rows = self.db.q("SELECT * FROM employees WHERE local_deleted = 0 ORDER BY name COLLATE NOCASE ASC")
        out = []
        q = J.en_digits(str(search or "").strip().lower())
        for r in rows:
            r = dict(r)
            r["centers"] = load_json(r.get("centers"), [])
            r["profile"] = load_json(r.get("profile"), {})
            if not include_terminated and r["terminated"]:
                continue
            if center_id and int(center_id) not in [int(c) for c in r["centers"]]:
                continue
            if q:
                haystack = " ".join([
                    str(r.get("name") or ""), str(r.get("login") or ""),
                    str(r.get("national") or ""), str(r.get("mobile") or ""),
                    str(r.get("profile", {}).get("job_title") or ""),
                ]).lower()
                if q not in J.en_digits(haystack).lower():
                    continue
            out.append(r)
        return out

    def employee(self, employee_id):
        r = self.db.one("SELECT * FROM employees WHERE id = ?", (employee_id,))
        if not r:
            return None
        r = dict(r)
        r["centers"] = load_json(r.get("centers"), [])
        r["profile"] = load_json(r.get("profile"), {})
        return r

    def save_employee(self, data):
        """افزودن/ویرایش کارمند — همیشه از صف عبور می‌کند (سرور مرجع است)."""
        emp_id = int(data.get("id") or 0)
        name = str(data.get("name") or "").strip()
        if not name:
            raise ValueError("نام کارمند الزامی است")
        centers = [int(c) for c in (data.get("centers") or [])]
        profile = dict(data.get("profile") or {})
        profile["full_name"] = name
        payload = {
            "employee": {
                "id": emp_id,
                "name": name,
                "national": J.en_digits(str(data.get("national") or "")),
                "mobile": J.en_digits(str(data.get("mobile") or "")),
                "terminated": 1 if data.get("terminated") else 0,
                "centers": centers,
                "profile": profile,
            }
        }
        if emp_id and self.employee(emp_id):
            self.db.ex(
                "UPDATE employees SET name=?, national=?, mobile=?, terminated=?, centers=?, profile=?, pending=1 WHERE id=?",
                (name, payload["employee"]["national"], payload["employee"]["mobile"],
                 1 if data.get("terminated") else 0, dump_json(centers), dump_json(profile), emp_id),
            )
            self.enqueue("employee", "employee.upsert", payload)
            return emp_id
        new_id = self._next_local_id("employees")
        payload["employee"]["id"] = new_id  # شناسه منفی محلی — سرور (id<=0) آن را ایجاد حساب می‌بیند و server_id برمی‌گرداند
        self.db.ex(
            "INSERT INTO employees(id, name, national, mobile, terminated, centers, profile, pending)"
            " VALUES(?,?,?,?,?,?,?,1)",
            (new_id, name, payload["employee"]["national"], payload["employee"]["mobile"],
             1 if data.get("terminated") else 0, dump_json(centers), dump_json(profile)),
        )
        self.enqueue("employee", "employee.upsert", payload)
        return new_id

    def set_terminated(self, employee_id, terminated):
        self.db.ex("UPDATE employees SET terminated = ?, pending = 1 WHERE id = ?",
                   (1 if terminated else 0, employee_id))
        emp = self.employee(employee_id)
        if emp:
            self.enqueue("employee", "employee.upsert", {
                "employee": {
                    "id": employee_id, "name": emp["name"],
                    "national": emp["national"], "mobile": emp["mobile"],
                    "terminated": 1 if terminated else 0,
                    "centers": emp["centers"], "profile": emp["profile"],
                }
            })

    def delete_employee(self, employee_id):
        """هم‌سان با افزونه: فقط نقش کارمندی حذف می‌شود (حساب و رکوردها حفظ می‌شوند)."""
        if int(employee_id) < 0:
            self._drop_local_outbox("employee", lambda d: int(d.get("employee", {}).get("id", 0) or 0) == int(employee_id))
            self.db.ex("DELETE FROM employees WHERE id = ?", (employee_id,))
        else:
            self.db.ex("UPDATE employees SET local_deleted = 1, pending = 1 WHERE id = ?", (employee_id,))
            self.enqueue("employee", "employee.delete", {"employee_id": int(employee_id)})

    def employee_name(self, employee_id):
        row = self.db.one("SELECT name FROM employees WHERE id = ?", (employee_id,))
        return row["name"] if row else ("کارمند #%s" % employee_id)

    # ==================================================
    # فیلدها و تنظیمات
    # ==================================================

    def fields(self):
        """فیلدها در شکل بسته سرور (key/…) — هم‌سان با bundle."""
        rows = self.db.q("SELECT * FROM fields ORDER BY sort ASC, field_key ASC")
        return [
            {
                "key": r["field_key"],
                "label": r["label"],
                "type": r["type"],
                "default": r["default_value"],
                "formula": r["formula"],
                "calculated": int(r["calculated"] or 0),
                "allow_manual": int(r["allow_manual"] or 0),
                "sort": int(r["sort"] or 0),
            }
            for r in rows
        ]

    def kv_company(self):
        return self.db.kv_get("company_name", "")

    def kv_get(self, key, default=""):
        """دسترسی عمومی به kv (برای UI و تست)."""
        return self.db.kv_get(key, default)

    def kv_currency(self):
        return self.db.kv_get("currency", "ریال")

    def use_fa_digits(self):
        """نسخه 1.7.1: همه اعداد انگلیسی — همیشه False (برای سازگاری حفظ شد)."""
        return False

    # ==================================================
    # رکوردهای حقوق
    # ==================================================

    def upsert_record(self, user_id, center_id, jyear, jmonth, raw_values, insurable_formula=False, manual_keys=None):
        """
        ثبت/ویرایش آفلاین فیش — محاسبه با موتور فرمول (آینه سرور) + صف sync.
        سرور هنگام sync همان را با هسته خودش محاسبه می‌کند؛ سراسر سازگار است.
        """
        raw_values = dict(raw_values or {})
        parsed = {k: J.parse_number(v) for k, v in dict(raw_values or {}).items()}
        global_formulas = load_json(self.db.kv_get("formulas", "{}"), {})
        fields = self.fields()
        # هم‌سان با upsert_record افزونه: همه فیلدها در values هستند (غایب = صفر)
        values = {f["key"]: 0.0 for f in fields}
        values.update(parsed)
        # فیلد محاسباتیِ غایب در ورودی = محاسبه اجباری از فرمول (رفع باگ 1.4.1 افزونه)
        manual = [str(m) for m in (manual_keys or [])]
        if not insurable_formula:
            manual.append("insurable")
        force = ["insurable"] if insurable_formula else []
        for f in fields:
            if f["calculated"] and f["key"] not in parsed:
                force.append(f["key"])
        values, manual = F.compute_values(values, manual, fields, force, global_formulas)

        gross = float(values.get("gross", 0) or 0)
        insurable = float(values.get("insurable", 0) or 0)
        insurance_deduct = float(values.get("insurance_deduct", 0) or 0)
        other_deductions = float(values.get("other_deductions", 0) or 0)
        net = float(values.get("net", 0) or 0)

        payload = dict(values)
        payload["manual"] = manual
        payload["insurable_mode"] = "formula" if insurable_formula else "profile"

        existing = self.db.one(
            "SELECT id FROM records WHERE user_id=? AND center_id=? AND jyear=? AND jmonth=?",
            (user_id, center_id, jyear, jmonth),
        )
        now = now_mysql()
        if existing:
            rec_id = int(existing["id"])
            self.db.ex(
                "UPDATE records SET payload=?, gross=?, insurable=?, insurance_deduct=?, other_deductions=?, net=?, updated_at=?, pending=1, local_deleted=0 WHERE id=?",
                (dump_json(payload), gross, insurable, insurance_deduct, other_deductions, net, now, rec_id),
            )
        else:
            rec_id = self._next_local_id("records")
            self.db.ex(
                "INSERT INTO records(id, user_id, center_id, jyear, jmonth, payload, gross, insurable, insurance_deduct, other_deductions, net, updated_at, created_at, pending)"
                " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,1)",
                (rec_id, user_id, center_id, jyear, jmonth, dump_json(payload),
                 gross, insurable, insurance_deduct, other_deductions, net, now, now),
            )
        self.enqueue("record", "record.upsert", {
            "payload": {
                "user_id": user_id, "center_id": center_id,
                "jyear": jyear, "jmonth": jmonth,
                "values": values, "insurable_formula": bool(insurable_formula),
            }
        }, client_updated_at=now)
        return rec_id

    def delete_record(self, record_id):
        if int(record_id) < 0:
            rec = self.record(record_id)
            if rec:
                self._drop_local_outbox(
                    "record",
                    lambda d: (
                        int(d.get("payload", {}).get("user_id", 0) or 0) == int(rec["user_id"])
                        and int(d.get("payload", {}).get("center_id", 0) or 0) == int(rec["center_id"])
                        and int(d.get("payload", {}).get("jyear", 0) or 0) == int(rec["jyear"])
                        and int(d.get("payload", {}).get("jmonth", 0) or 0) == int(rec["jmonth"])
                    ),
                )
            self.db.ex("DELETE FROM records WHERE id = ?", (record_id,))
        else:
            row = self.db.one("SELECT updated_at FROM records WHERE id = ?", (record_id,))
            self.db.ex("UPDATE records SET local_deleted = 1, pending = 1 WHERE id = ?", (record_id,))
            self.enqueue("record", "record.delete", {"record_id": int(record_id)},
                         client_updated_at=row["updated_at"] if row else None)

    def records(self, search="", jyear=0, jmonth=0, center_id=0, page=1, per_page=20, include_deleted=False):
        rows = self.db.q(
            "SELECT * FROM records" + ("" if include_deleted else " WHERE local_deleted = 0") + " ORDER BY jyear DESC, jmonth DESC, user_id ASC, center_id ASC"
        )
        q = J.en_digits(str(search or "").strip().lower())
        emp_cache = {}

        def emp_name(uid):
            if uid not in emp_cache:
                r = self.db.one("SELECT name, national FROM employees WHERE id = ?", (uid,))
                emp_cache[uid] = (r["name"], r["national"]) if r else ("", "")
            return emp_cache[uid]

        filtered = []
        for r in rows:
            r = dict(r)
            if jyear and int(r["jyear"]) != int(jyear):
                continue
            if jmonth and int(r["jmonth"]) != int(jmonth):
                continue
            if center_id and int(r["center_id"]) != int(center_id):
                continue
            if q:
                name, national = emp_name(r["user_id"])
                hay = J.en_digits("%s %s" % (name, national)).lower()
                if q not in hay:
                    continue
            r["employee_name"] = emp_name(r["user_id"])[0]
            filtered.append(r)

        total = len(filtered)
        page = max(1, int(page or 1))
        pages = max(1, (total + per_page - 1) // per_page) if total else 1
        page = min(page, pages)
        chunk = filtered[(page - 1) * per_page: page * per_page]
        return chunk, total, page, pages

    def record(self, record_id):
        r = self.db.one("SELECT * FROM records WHERE id = ?", (record_id,))
        return dict(r) if r else None

    def period_records(self, jyear, jmonth, center_id=0):
        sql = "SELECT * FROM records WHERE jyear = ? AND jmonth = ? AND local_deleted = 0"
        params = [jyear, jmonth]
        if center_id:
            sql += " AND center_id = ?"
            params.append(center_id)
        return [dict(r) for r in self.db.q(sql + " ORDER BY user_id ASC", params)]

    def past_salary(self, user_id, center_id, src_year, src_month):
        """آینه past_salary افزونه: اولویت رکورد همان کارمند + همان مرکز؛ وگرنه آخرین رکورد همان دوره از مرکز دیگر."""
        rec = self.db.one(
            "SELECT * FROM records WHERE user_id=? AND center_id=? AND jyear=? AND jmonth=? AND local_deleted=0"
            " ORDER BY (center_id=?) DESC, id DESC LIMIT 1",
            (user_id, center_id, src_year, src_month, center_id),
        )
        if rec:
            return dict(rec), True
        rec = self.db.one(
            "SELECT * FROM records WHERE user_id=? AND jyear=? AND jmonth=? AND local_deleted=0 ORDER BY id DESC LIMIT 1",
            (user_id, src_year, src_month),
        )
        if rec:
            return dict(rec), False
        return None, False

    def compute_preview(self, values, insurable_formula=False, manual_keys=None, force_calculated=False):
        """محاسبه پیش‌نمایش — force_calculated مانند دکمه «محاسبه/TPP.recalc» افزونه عمل می‌کند."""
        values = {k: J.parse_number(v) for k, v in dict(values or {}).items()}
        global_formulas = load_json(self.db.kv_get("formulas", "{}"), {})
        manual = [str(m) for m in (manual_keys or [])]
        if not insurable_formula:
            manual.append("insurable")
        force = ["insurable"] if insurable_formula else []
        if force_calculated:
            force += [f["key"] for f in self.fields() if f["calculated"]]
        values, manual = F.compute_values(values, manual, self.fields(), force, global_formulas)
        return values, manual

    # ==================================================
    # آمار داشبورد
    # ==================================================

    def stats(self):
        def cnt(sql, params=()):
            row = self.db.one(sql, params)
            return int(row["c"]) if row else 0
        jy, jm, _ = J.today_jalali()
        return {
            "employees_active": cnt("SELECT COUNT(*) AS c FROM employees WHERE local_deleted=0 AND terminated=0"),
            "centers": cnt("SELECT COUNT(*) AS c FROM centers WHERE local_deleted=0"),
            "records": cnt("SELECT COUNT(*) AS c FROM records WHERE local_deleted=0"),
            "records_current": cnt("SELECT COUNT(*) AS c FROM records WHERE local_deleted=0 AND jyear=? AND jmonth=?", (jy, jm)),
            "pending": self.pending_count(),
            "period": (jy, jm),
        }

    def latest_records(self, limit=10):
        rows = self.db.q(
            "SELECT * FROM records WHERE local_deleted=0 ORDER BY updated_at DESC, id DESC LIMIT ?", (limit,)
        )
        out = []
        for r in rows:
            r = dict(r)
            r["employee_name"] = self.employee_name(r["user_id"])
            out.append(r)
        return out
