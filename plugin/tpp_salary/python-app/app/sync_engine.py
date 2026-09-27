# -*- coding: utf-8 -*-
"""
موتور همگام‌سازی دوطرفه (سایت ↔ برنامه آفلاین):

۱) push  → اعمال صف outbox روی سرور (record/employee/center/bank upsert-delete)
۲) pull  → دریافت بسته تازه و هم‌سانی دیتابیس محلی (مرجع: سرور برای داده‌های بدون تغییر محلی)

- تداخل رکورد با updated_at تشخیص داده می‌شود (نسخه جدیدتر ملاک؛ موارد گزارش می‌شوند)
- شناسه‌های منفیِ محلی پس از تأیید سرور به شناسه واقعی نگاشت می‌شوند
- در نبود اینترنت همه‌چیز آفلاین کار می‌کند و صف دست‌نخورده باقی می‌ماند
"""

from . import jalali as J
from .api_client import ApiError, dump_json, load_json


class SyncResult(object):
    def __init__(self):
        self.online = False
        self.pushed = 0
        self.pulled = 0
        self.conflicts = []
        self.errors = []
        self.message = ""
        self.server_version = ""

    def ok(self):
        return self.online and not self.errors

    def summary(self):
        if not self.online:
            return "آفلاین — تغییرات در صف باقی ماند (%s)" % self.message if self.message else "آفلاین"
        parts = ["ارسال: %s" % J.fa_digits(self.pushed), "دریافت: %s" % J.fa_digits(self.pulled)]
        if self.conflicts:
            parts.append("تداخل: %s" % J.fa_digits(len(self.conflicts)))
        if self.errors:
            parts.append("خطا: %s" % J.fa_digits(len(self.errors)))
        return "، ".join(parts)


class SyncEngine(object):

    def __init__(self, db, store, api, config):
        self.db = db
        self.store = store
        self.api = api
        self.config = config

    # --------------------------------------------------

    def sync_now(self):
        res = SyncResult()
        # --- اتصال ---
        try:
            ping = self.api.ping()
            res.online = True
            res.server_version = str(ping.get("version", ""))
        except ApiError as exc:
            res.message = str(exc)
            self.db.log("اتصال ناموفق: %s" % exc, "warn")
            return res
        except Exception as exc:  # noqa: BLE001
            res.message = str(exc)[:160]
            self.db.log("اتصال ناموفق: %s" % res.message, "warn")
            return res

        # --- push ---
        try:
            self._push(res)
        except ApiError as exc:
            res.errors.append("ارسال تغییرات ناموفق: %s" % exc)
            self.db.log("push ناموفق: %s" % exc, "error")
        # --- pull ---
        if res.online and not res.errors:
            try:
                self._pull(res)
            except ApiError as exc:
                res.errors.append("دریافت داده ناموفق: %s" % exc)
                self.db.log("pull ناموفق: %s" % exc, "error")
            except Exception as exc:  # noqa: BLE001
                res.errors.append("هم‌سانی ناموفق: %s" % str(exc)[:120])
                self.db.log("pull ناموفق: %s" % res.errors[-1], "error")

        if res.online:
            self.db.kv_set("last_sync", J.today_jalali().__str__() + " " + _now_hm())
            self.db.kv_set("server_version", res.server_version)
            self.db.log("همگام‌سازی انجام شد: %s" % res.summary(), "info")
            res.message = res.summary()
        return res

    # --------------------------------------------------
    # ارسال صف
    # --------------------------------------------------

    def _push(self, res):
        id_map = {"employee": {}, "center": {}, "bank": {}}
        while True:
            ops = self.store.pending_ops(limit=300)
            if not ops:
                return
            payload_ops = []
            for op in ops:
                item = {"ref": op["ref"], "action": op["action"], "client_updated_at": op["client_updated_at"]}
                if op["action"] == "record.upsert":
                    item["payload"] = op["payload"].get("payload", {})
                elif op["action"] == "record.delete":
                    item["record_id"] = op["payload"].get("record_id")
                    item["client_updated_at"] = op["payload"].get("client_updated_at") or op["client_updated_at"]
                elif op["action"] == "employee.upsert":
                    item["employee"] = op["payload"].get("employee", {})
                elif op["action"] == "employee.delete":
                    item["employee_id"] = op["payload"].get("employee_id")
                elif op["action"] == "center.upsert":
                    item["center"] = op["payload"].get("center", {})
                elif op["action"] == "center.delete":
                    item["center_id"] = op["payload"].get("center_id")
                elif op["action"] == "bank.upsert":
                    item["bank"] = op["payload"].get("bank", {})
                elif op["action"] == "bank.delete":
                    item["bank_id"] = op["payload"].get("bank_id")
                payload_ops.append(item)

            resp = self.api.push(payload_ops)
            results = resp.get("results", []) if isinstance(resp, dict) else []
            by_ref = {r.get("ref"): r for r in results if isinstance(r, dict)}

            for op in payload_ops:
                row = self.db.one("SELECT * FROM outbox WHERE ref = ?", (op["ref"],))
                if not row:
                    continue
                r = by_ref.get(op["ref"], {})
                status = r.get("status", "error")
                if status == "applied":
                    self.db.ex("DELETE FROM outbox WHERE id = ?", (row["id"],))
                    res.pushed += 1
                    self._apply_mapping(op, r)
                    self._collect_map(op, r, id_map)
                elif status == "conflict":
                    self.db.ex("UPDATE outbox SET status='conflict', error=? WHERE id = ?",
                               (r.get("message", "تداخل با سرور"), row["id"]))
                    res.conflicts.append(op["ref"])
                else:
                    attempts = int(row["attempts"]) + 1
                    err = r.get("message", "خطای نامشخص")
                    new_status = "error" if attempts >= 5 else "pending"
                    self.db.ex("UPDATE outbox SET attempts=?, status=?, error=? WHERE id = ?",
                               (attempts, new_status, err, row["id"]))
                    res.errors.append("op %s: %s" % (op["action"], err))

            # بازنویسی ارجاع‌های منفیِ صف باقی‌مانده با شناسه‌های تازه سرور
            if id_map:
                self._rewrite_pending_refs(id_map)

            if len(ops) < 300:
                return  # صف تخلیه شد

    def _collect_map(self, op, result, id_map):
        """جمع نگاشت محلی→سرور از نتایج دسته برای بازنویسی opهای باقی‌مانده."""
        server_id = int(result.get("server_id", 0) or 0)
        if server_id <= 0:
            return
        if op["action"] == "employee.upsert":
            local = int(op.get("employee", {}).get("id", 0) or 0)
            if local < 0:
                id_map["employee"][local] = server_id
        elif op["action"] == "center.upsert":
            local = int(op.get("center", {}).get("id", 0) or 0)
            if local < 0:
                id_map["center"][local] = server_id
        elif op["action"] == "bank.upsert":
            local = int(op.get("bank", {}).get("id", 0) or 0)
            if local < 0:
                id_map["bank"][local] = server_id

    def _rewrite_pending_refs(self, id_map):
        """opهای صف باقی‌مانده که به شناسه منفی نگاشت‌شده ارجاع دارند، به‌روز می‌شوند
        تا در تلاش بعدی سرور شناسه واقعی ببیند (بدون اتکا به نگاشت موقت دسته)."""
        if not (id_map["employee"] or id_map["center"] or id_map["bank"]):
            return
        rows = self.db.q("SELECT id, data FROM outbox WHERE status IN ('pending','conflict')")
        for row in rows:
            data = load_json(row["data"], {})
            changed = False
            emp = data.get("employee")
            if isinstance(emp, dict):
                eid = int(emp.get("id", 0) or 0)
                if eid < 0 and eid in id_map["employee"]:
                    emp["id"] = id_map["employee"][eid]
                    changed = True
                centers = emp.get("centers")
                if isinstance(centers, list):
                    new_centers = [id_map["center"].get(int(c), int(c)) if int(c) < 0 else int(c) for c in centers]
                    if new_centers != centers:
                        emp["centers"] = new_centers
                        changed = True
            cen = data.get("center")
            if isinstance(cen, dict):
                cid = int(cen.get("id", 0) or 0)
                if cid < 0 and cid in id_map["center"]:
                    cen["id"] = id_map["center"][cid]
                    changed = True
            bnk = data.get("bank")
            if isinstance(bnk, dict):
                bid = int(bnk.get("id", 0) or 0)
                if bid < 0 and bid in id_map["bank"]:
                    bnk["id"] = id_map["bank"][bid]
                    changed = True
            rec = data.get("payload")
            if isinstance(rec, dict):
                uid = int(rec.get("user_id", 0) or 0)
                if uid < 0 and uid in id_map["employee"]:
                    rec["user_id"] = id_map["employee"][uid]
                    changed = True
                cid2 = int(rec.get("center_id", 0) or 0)
                if cid2 < 0 and cid2 in id_map["center"]:
                    rec["center_id"] = id_map["center"][cid2]
                    changed = True
            if changed:
                self.db.ex("UPDATE outbox SET data = ? WHERE id = ?", (dump_json(data), row["id"]))

    def _apply_mapping(self, op, result):
        """نگاشت شناسه منفی محلی → شناسه واقعی سرور."""
        server_id = int(result.get("server_id", 0) or 0)
        if not server_id or server_id <= 0:
            return
        if op["action"] == "employee.upsert":
            self.store.apply_id_map(
                "employees", _extract_local_id(op.get("employee", {}).get("id")), server_id,
                fk_updates=[("records", "user_id")],
            )
        elif op["action"] == "center.upsert":
            self.store.apply_id_map(
                "centers", _extract_local_id(op.get("center", {}).get("id")), server_id,
                fk_updates=[("records", "center_id")],
            )
        elif op["action"] == "bank.upsert":
            self.store.apply_id_map("banks", _extract_local_id(op.get("bank", {}).get("id")), server_id)

    # --------------------------------------------------
    # دریافت و هم‌سانی
    # --------------------------------------------------

    def _pull(self, res):
        bundle = self.api.bundle()
        if not isinstance(bundle, dict):
            raise ApiError("بسته دریافتی نامعتبر است")

        # متادیتا
        self.db.kv_set("company_name", bundle.get("company_name", ""))
        self.db.kv_set("currency", bundle.get("currency", "ریال"))
        self.db.kv_set("digits_fa", "1" if int(bundle.get("digits_fa", 1) or 0) else "0")
        self.db.kv_set("formulas", dump_json(bundle.get("formulas", {})))
        self.db.kv_set("defaults", dump_json(bundle.get("defaults", {})))
        self.db.kv_set("period", dump_json(bundle.get("period", {})))
        self.db.kv_set("schema", str(bundle.get("schema", 1)))

        # فیلدها (بازنویسی کامل — مرجع سرور)
        self.db.ex("DELETE FROM fields")
        for f in bundle.get("fields", []):
            self.db.ex(
                "INSERT INTO fields(field_key, label, type, default_value, formula, calculated, allow_manual, sort)"
                " VALUES(?,?,?,?,?,?,?,?)",
                (f.get("key"), f.get("label"), f.get("type", "number"), f.get("default", ""),
                 f.get("formula", ""), int(f.get("calculated", 0) or 0), int(f.get("allow_manual", 1) or 0),
                 int(f.get("sort", 0) or 0)),
            )
        self.db.kv_set("profile_fields", dump_json(bundle.get("profile_fields", [])))

        # مراکز / بانک‌ها / کارمندان — حفظ موارد دارای تغییر محلی (pending)
        self._sync_reference("centers", bundle.get("centers", []), self._upsert_center, "center_id")
        self._sync_reference("banks", bundle.get("banks", []), self._upsert_bank, "bank_id")
        self._sync_reference("employees", bundle.get("employees", []), self._upsert_employee, "employee_id")

        # رکوردها — upsert کامل؛ حذف مواردی که سرور ندارند و تغییر محلی هم ندارند
        pending_refs = self._pending_record_keys()
        server_ids = set()
        server_keys = {}
        for r in bundle.get("records", []):
            rid = int(r.get("id", 0) or 0)
            if rid <= 0:
                continue
            server_ids.add(rid)
            self._upsert_record_row(r)
            key = (int(r.get("user_id", 0) or 0), int(r.get("center_id", 0) or 0),
                   int(r.get("jyear", 0) or 0), int(r.get("jmonth", 0) or 0))
            server_keys[key] = rid
            res.pulled += 1
        for row in self.db.q("SELECT id FROM records WHERE id > 0"):
            rid = int(row["id"])
            if rid not in server_ids and rid not in pending_refs:
                self.db.ex("DELETE FROM records WHERE id = ?", (rid,))

        # نگاشت رکوردهای محلی منفی: اگر رکورد همان (کارمند/مرکز/دوره) از سرور آمد،
        # نسخه محلی منفی پس از اعمال op جایش داده شده و حذف می‌شود.
        for row in self.db.q("SELECT id, user_id, center_id, jyear, jmonth FROM records WHERE id < 0"):
            key = (int(row["user_id"]), int(row["center_id"]), int(row["jyear"]), int(row["jmonth"]))
            if key in server_keys:
                self.db.ex("DELETE FROM records WHERE id = ?", (row["id"],))

    def _pending_record_keys(self):
        """شناسه رکوردهایی که صف محلی برایشان در جریان است (pending/conflict)."""
        keys = set()
        for row in self.db.q("SELECT * FROM outbox WHERE entity='record' AND status IN ('pending','conflict')"):
            data = load_json(row["data"], {})
            if row["action"] == "record.delete":
                rid = int(data.get("record_id", 0) or 0)
                if rid:
                    keys.add(rid)
            elif row["action"] == "record.upsert":
                # رکورد مربوطه ممکن است با شناسه منفی/مثبت محلی ذخیره شده باشد؛
                # با کلید دوره پیدا می‌کنیم تا هنگام pull پاک نشود.
                p = data.get("payload", {})
                found = self.db.one(
                    "SELECT id FROM records WHERE user_id=? AND center_id=? AND jyear=? AND jmonth=?",
                    (int(p.get("user_id", 0) or 0), int(p.get("center_id", 0) or 0),
                     int(p.get("jyear", 0) or 0), int(p.get("jmonth", 0) or 0)),
                )
                if found:
                    keys.add(int(found["id"]))
        return keys

    def _sync_reference(self, table, items, upsert_fn, _fk_unused=None):
        server_ids = set()
        for item in items:
            try:
                rid = int(item.get("id", 0) or 0)
            except (TypeError, ValueError):
                continue
            if rid <= 0:
                continue
            server_ids.add(rid)
            upsert_fn(item)
        # حذف مواردی که سرور ندارند و تغییر محلی هم ندارند
        for row in self.db.q("SELECT id FROM %s WHERE id > 0" % table):
            rid = int(row["id"])
            if rid not in server_ids:
                pending = self.db.one("SELECT id FROM outbox WHERE entity=? AND status IN ('pending','conflict') AND data LIKE ?",
                                      (table[:-1] if table.endswith("s") else table, "%" + str(rid) + "%"))
                if not pending:
                    self.db.ex("DELETE FROM %s WHERE id = ?" % table, (rid,))

    def _upsert_center(self, c):
        self.db.ex(
            "INSERT INTO centers(id, name, created_at, pending, local_deleted)"
            " VALUES(?,?,?,?,0)"
            " ON CONFLICT(id) DO UPDATE SET name=excluded.name, local_deleted=0, pending=0",
            (int(c.get("id", 0)), str(c.get("name", "")), str(c.get("created_at", "")), 0),
        )

    def _upsert_bank(self, b):
        self.db.ex(
            "INSERT INTO banks(id, name, sort_order, pending, local_deleted)"
            " VALUES(?,?,?,?,0)"
            " ON CONFLICT(id) DO UPDATE SET name=excluded.name, sort_order=excluded.sort_order, local_deleted=0, pending=0",
            (int(b.get("id", 0)), str(b.get("name", "")), int(b.get("sort_order", 0) or 0), 0),
        )

    def _upsert_employee(self, e):
        self.db.ex(
            "INSERT INTO employees(id, name, login, email, national, mobile, terminated, centers, profile, pending, local_deleted)"
            " VALUES(?,?,?,?,?,?,?,?,?,0,0)"
            " ON CONFLICT(id) DO UPDATE SET name=excluded.name, login=excluded.login, email=excluded.email,"
            " national=excluded.national, mobile=excluded.mobile, terminated=excluded.terminated,"
            " centers=excluded.centers, profile=excluded.profile, local_deleted=0, pending=0",
            (
                int(e.get("id", 0)), str(e.get("name", "")), str(e.get("login", "")), str(e.get("email", "")),
                str(e.get("national", "")), str(e.get("mobile", "")), int(e.get("terminated", 0) or 0),
                dump_json(e.get("centers", [])), dump_json(e.get("profile", {})),
            ),
        )

    def _upsert_record_row(self, r):
        self.db.ex(
            "INSERT INTO records(id, user_id, center_id, jyear, jmonth, payload, gross, insurable,"
            " insurance_deduct, other_deductions, net, updated_at, created_at, pending, local_deleted)"
            " VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,0,0)"
            " ON CONFLICT(id) DO UPDATE SET user_id=excluded.user_id, center_id=excluded.center_id,"
            " jyear=excluded.jyear, jmonth=excluded.jmonth, payload=excluded.payload, gross=excluded.gross,"
            " insurable=excluded.insurable, insurance_deduct=excluded.insurance_deduct,"
            " other_deductions=excluded.other_deductions, net=excluded.net, updated_at=excluded.updated_at,"
            " local_deleted=0, pending=0",
            (
                int(r.get("id", 0)), int(r.get("user_id", 0) or 0), int(r.get("center_id", 0) or 0),
                int(r.get("jyear", 0) or 0), int(r.get("jmonth", 0) or 0), str(r.get("payload", "{}")),
                float(r.get("gross", 0) or 0), float(r.get("insurable", 0) or 0),
                float(r.get("insurance_deduct", 0) or 0), float(r.get("other_deductions", 0) or 0),
                float(r.get("net", 0) or 0), str(r.get("updated_at", "")), str(r.get("created_at", "")),
            ),
        )


def _extract_local_id(value):
    try:
        v = int(value)
        return v if v < 0 else 0
    except (TypeError, ValueError):
        return 0


def _now_hm():
    import datetime
    return datetime.datetime.now().strftime("%H:%M")
