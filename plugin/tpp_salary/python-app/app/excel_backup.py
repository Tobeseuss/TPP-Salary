# -*- coding: utf-8 -*-
"""
پشتیبان‌گیری اکسل آنی (نسخه 1.7.5) — با هر ذخیره و با هر اجرا یک نسخه کامل xlsx
در پوشه «excel» کنار برنامه ساخته می‌شود؛ علاوه بر دیتابیس محلی (SQLite) و
همگام‌سازی سایت، همیشه یک تصویر قابل‌مطالعه از کارمندان و حقوق‌ها روی دیسک است.

 رفتار:
- اجرای برنامه      → بلافاصله یک فایل بکاپ (اجباری — حتی بدون تغییر)
- هر ذخیره‌سازی     → بکاپ با تأخیر کوتاه (۲ ثانیه) تا تغییرات پشت‌سرهم یک فایل شوند
- تغییر از سرور     → پس از همگام‌سازی اگر داده واقعاً عوض شده باشد بکاپ می‌گیرد
- بدون تغییر واقعی  → فایل تکراری ساخته نمی‌شود (اثر انگشت داده با هش مقایسه می‌شود)
- نگهداری           → ۲۰۰ نسخه آخر؛ قدیمی‌ترها خودکار حذف می‌شوند
- خرابی             → بی‌سوت در sync_log ثبت می‌شود؛ هیچ‌وقت برنامه را نمی‌بندد

محتوات هر فایل: شیت «اطلاعات بکاپ» + «کارمندان» + شیت‌های «ستونی لیست حقوق»
به‌ازای هر دوره (نسخه 1.7.9 — هم‌سان با خروجی اکسل گزارش افزونه: «عنوان ستون»
= نام کارمند و «سطرها» = عناوین حقوق، با سطر عنوان شرکت/دوره/مرکز و سطر واحد)
+ «مراکز» + «بانک‌ها» — راست‌به‌چپ.
"""

import datetime
import hashlib
import json
import os
import threading

from . import jalali as J
from .database import DATA_TABLES

KEEP_LAST_DEFAULT = 200
DEBOUNCE_DEFAULT = 2.0          # ثانیه — ادغام ذخیره‌های پشت‌سرهم در یک فایل
STARTUP_DELAY = 1.0             # ثانیه — بکاپ اجرا کمی پس از بالا آمدن UI

REASON_LABELS = {
    "run": "اجرای برنامه",
    "save": "ذخیره تغییرات",
    "close": "بستن برنامه",
    "manual": "دستی",
}

_BACKUP_PREFIX = "backup_"

# نسخه 1.7.9 — قالب ستونی هم‌سان با گزارش افزونه:
# فیلدهای «فقط محاسباتی» (آینه CALC_ONLY_KEYS در class-tppsalary-reports.php)
CALC_ONLY_KEYS = ("overtime_hours", "holiday_days", "absence_days")
# بیشینه تعداد ستون (کارمند) در هر شیت — شیت‌های بعدی با پسوند (۲)، (۳)…
MAX_COLS_PER_SHEET = 10


class ExcelBackupManager(object):
    """مغزی پشتیبان اکسل — با شنونده نوشتن Database فعال می‌شود (بدون وابستگی به Qt)."""

    def __init__(self, db, store, app_dir, debounce=DEBOUNCE_DEFAULT, keep_last=KEEP_LAST_DEFAULT):
        self.db = db
        self.store = store
        self.app_dir = app_dir
        self.folder = os.path.join(app_dir, "excel")
        self.debounce = max(0.5, float(debounce))
        self.keep_last = max(10, int(keep_last))

        self._timer = None            # threading.Timer برای ادغام ذخیره‌های پیاپی
        self._timer_lock = threading.Lock()
        self._run_lock = threading.Lock()   # هم‌زمان بیش از یک فایل نوشته نشود
        self._stopped = False

        self.last_error = None        # آخرین خطا (برای تست و صفحه تنظیمات)
        self.last_file = ""           # آخرین فایل نوشته‌شده
        self.writes_done = 0          # شمار فایل‌های ساخته‌شده در این نشست

    # ---------------- چرخه حیات ----------------

    def start(self):
        """ایجاد پوشه + نخ بکاپ اجرای برنامه (هر بار اجرا = یک فایل، طبق درخواست کاربر)."""
        self.ensure_folder()
        t = threading.Thread(target=self._startup, daemon=True)
        t.start()

    def _startup(self):
        try:
            self._sleep(STARTUP_DELAY)
        except Exception:
            return
        if self._stopped:
            return
        self.backup_now(reason="run", force=True)

    def shutdown(self):
        """هنگام بستن برنامه: لغو تایمر + اگر تغییری ذخیره‌نشده مانده، فوراً بکاپ نهایی."""
        self._stopped = True
        with self._timer_lock:
            if self._timer is not None:
                self._timer.cancel()
                self._timer = None
        try:
            self.backup_now(reason="close")
        except Exception:
            pass

    # ---------------- فعال‌سازی با هر ذخیره ----------------

    def on_data_write(self, tables):
        """شنونده Database — پس از هر تغییر واقعی جدول‌های داده صدا زده می‌شود."""
        if self._stopped:
            return
        if not (set(tables or ()) & DATA_TABLES):
            return
        with self._timer_lock:
            if self._stopped:
                return
            if self._timer is not None:
                self._timer.cancel()
            self._timer = threading.Timer(self.debounce, self._debounce_fire)
            self._timer.daemon = True
            self._timer.start()

    def _debounce_fire(self):
        if self._stopped:
            return
        self.backup_now(reason="save")

    # ---------------- ساخت بکاپ ----------------

    def backup_now(self, reason="manual", force=False):
        """ساخت فایل بکاپ — force=False فقط وقتی داده نسبت به آخرین بکاپ عوض شده می‌نویسد.
        مسیر فایل نوشته‌شده برمی‌گردد؛ بدون تغییر واقعی None."""
        if self._stopped:
            return None
        with self._run_lock:
            if self._stopped:
                return None
            try:
                snap = self._snapshot()
                if not force and snap["hash"] == self._stored_hash():
                    return None  # هیچ تغییر واقعی‌ای نسبت به آخرین بکاپ نیست
                path = self._write_workbook(snap, reason)
                self.db.kv_set("excel_backup_last_hash", snap["hash"])
                self.db.kv_set("excel_backup_last_file", os.path.basename(path))
                self.db.kv_set(
                    "excel_backup_last_time",
                    datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                )
                self.last_file = path
                self.last_error = None
                self.writes_done += 1
                self._cleanup_old()
                return path
            except Exception as exc:  # noqa: BLE001 — بکاپ هرگز نباید برنامه را متوقف کند
                self.last_error = str(exc)[:300]
                try:
                    self.db.log("پشتیبان اکسل ناموفق: %s" % self.last_error, "error")
                except Exception:
                    pass
                return None

    def _snapshot(self):
        """خواندن هماهنگ همه جدول‌های داده + محاسبه اثر انگشت (هش) — یکجا و سازگار."""
        with self.db.locked():
            centers = self.db.q("SELECT * FROM centers WHERE local_deleted = 0 ORDER BY id")
            banks = self.db.q("SELECT * FROM banks WHERE local_deleted = 0 ORDER BY sort_order, name COLLATE NOCASE")
            employees = self.db.q("SELECT * FROM employees WHERE local_deleted = 0 ORDER BY name COLLATE NOCASE")
            records = self.db.q(
                "SELECT * FROM records WHERE local_deleted = 0"
                " ORDER BY jyear DESC, jmonth DESC, user_id ASC, center_id ASC"
            )
            fields = self.db.q("SELECT * FROM fields ORDER BY sort ASC, field_key ASC")
            company = self.db.kv_get("company_name", "")
            currency = self.db.kv_get("currency", "ریال")
        digest = hashlib.sha256()
        for rows in (centers, banks, employees, records, fields):
            digest.update(
                json.dumps(rows, ensure_ascii=False, default=str, separators=(",", ":")).encode("utf-8")
            )
            digest.update(b"\x00")
        return {
            "hash": digest.hexdigest(),
            "centers": centers,
            "banks": banks,
            "employees": employees,
            "records": records,
            "fields": fields,
            "company": company,
            "currency": currency,
        }

    def _stored_hash(self):
        return self.db.kv_get("excel_backup_last_hash", "")

    def _write_workbook(self, snap, reason):
        from openpyxl import Workbook
        from openpyxl.styles import Font, PatternFill
        from openpyxl.utils import get_column_letter

        wb = Workbook()
        head_font = Font(bold=True)
        head_fill = PatternFill("solid", fgColor="E8EDF5")

        def style_header(ws, ncols, height_row=1):
            ws.sheet_view.rightToLeft = True
            for c in range(1, ncols + 1):
                cell = ws.cell(row=height_row, column=c)
                cell.font = head_font
                cell.fill = head_fill
            ws.freeze_panes = "A2"

        now = datetime.datetime.now()
        jy, jm, jd = J.today_jalali()

        # --- شیت اطلاعات بکاپ ---
        ws_info = wb.active
        ws_info.title = "اطلاعات بکاپ"
        ws_info.sheet_view.rightToLeft = True
        info_rows = [
            ("عنوان", "نسخه پشتیبان خودکار — حقوق و دستمزد"),
            ("شرکت", snap["company"] or "—"),
            ("زمان پشتیبان‌گیری (شمسی)", "%04d/%02d/%02d - %s" % (jy, jm, jd, now.strftime("%H:%M:%S"))),
            ("زمان پشتیبان‌گیری (میلادی)", now.strftime("%Y-%m-%d %H:%M:%S")),
            ("علت پشتیبان‌گیری", REASON_LABELS.get(reason, "دستی")),
            ("تعداد کارمندان", len(snap["employees"])),
            ("تعداد رکوردهای حقوق", len(snap["records"])),
            ("تعداد مراکز", len(snap["centers"])),
            ("تعداد بانک‌ها", len(snap["banks"])),
            ("اثر انگشت داده (هش)", snap["hash"]),
            ("توضیح", "این فایل خودکار و آنی با هر ذخیره و هر اجرای برنامه ساخته می‌شود."),
        ]
        ws_info.cell(row=1, column=1, value="شرح")
        ws_info.cell(row=1, column=2, value="مقدار")
        for r, (k, v) in enumerate(info_rows, start=2):
            ws_info.cell(row=r, column=1, value=k)
            ws_info.cell(row=r, column=2, value=v)
        style_header(ws_info, 2)
        ws_info.column_dimensions["A"].width = 30
        ws_info.column_dimensions["B"].width = 60

        # --- شیت کارمندان ---
        center_names = {int(c["id"]): str(c["name"] or "") for c in snap["centers"]}
        ws_emp = wb.create_sheet("کارمندان")
        emp_headers = [
            "شناسه", "نام و نام خانوادگی", "کد ملی", "موبایل", "حساب کاربری", "ایمیل",
            "وضعیت", "مراکز", "سمت", "سایر مشخصات",
        ]
        for c, h in enumerate(emp_headers, start=1):
            ws_emp.cell(row=1, column=c, value=h)
        for r, e in enumerate(snap["employees"], start=2):
            profile = {}
            try:
                profile = json.loads(e.get("profile") or "{}")
                if not isinstance(profile, dict):
                    profile = {}
            except Exception:
                profile = {}
            center_ids = []
            try:
                center_ids = [int(x) for x in json.loads(e.get("centers") or "[]")]
            except Exception:
                center_ids = []
            job_title = str(profile.get("job_title") or "")
            rest = {k: v for k, v in profile.items() if k != "job_title"}
            ws_emp.cell(row=r, column=1, value=int(e["id"]))
            ws_emp.cell(row=r, column=2, value=str(e.get("name") or ""))
            ws_emp.cell(row=r, column=3, value=str(e.get("national") or ""))
            ws_emp.cell(row=r, column=4, value=str(e.get("mobile") or ""))
            ws_emp.cell(row=r, column=5, value=str(e.get("login") or ""))
            ws_emp.cell(row=r, column=6, value=str(e.get("email") or ""))
            ws_emp.cell(row=r, column=7, value="قطع همکاری" if int(e.get("terminated") or 0) else "فعال")
            ws_emp.cell(row=r, column=8, value="، ".join(
                center_names.get(cid, "مرکز #%s" % cid) for cid in center_ids))
            ws_emp.cell(row=r, column=9, value=job_title)
            ws_emp.cell(row=r, column=10, value=json.dumps(rest, ensure_ascii=False) if rest else "")
        style_header(ws_emp, len(emp_headers))
        for c, w in enumerate((8, 24, 14, 14, 14, 22, 12, 26, 16, 28), start=1):
            ws_emp.column_dimensions[get_column_letter(c)].width = w

        # --- شیت‌های ستونی لیست حقوق — نسخه 1.7.9 (هم‌سان با گزارش اکسل افزونه) ---
        emp_by_id = {int(e["id"]): e for e in snap["employees"]}
        self._write_salary_sheets(wb, snap, emp_by_id, center_names)

        # --- شیت مراکز ---
        ws_cen = wb.create_sheet("مراکز")
        for c, h in enumerate(("شناسه", "نام مرکز", "تاریخ ایجاد"), start=1):
            ws_cen.cell(row=1, column=c, value=h)
        for r, cn in enumerate(snap["centers"], start=2):
            ws_cen.cell(row=r, column=1, value=int(cn["id"]))
            ws_cen.cell(row=r, column=2, value=str(cn.get("name") or ""))
            ws_cen.cell(row=r, column=3, value=str(cn.get("created_at") or ""))
        style_header(ws_cen, 3)
        for c, w in enumerate((8, 30, 20), start=1):
            ws_cen.column_dimensions[get_column_letter(c)].width = w

        # --- شیت بانک‌ها ---
        ws_bank = wb.create_sheet("بانک‌ها")
        for c, h in enumerate(("شناسه", "نام بانک", "ترتیب"), start=1):
            ws_bank.cell(row=1, column=c, value=h)
        for r, b in enumerate(snap["banks"], start=2):
            ws_bank.cell(row=r, column=1, value=int(b["id"]))
            ws_bank.cell(row=r, column=2, value=str(b.get("name") or ""))
            ws_bank.cell(row=r, column=3, value=int(b.get("sort_order") or 0))
        style_header(ws_bank, 3)
        for c, w in enumerate((8, 30, 8), start=1):
            ws_bank.column_dimensions[get_column_letter(c)].width = w

        path = self._new_path(now)
        self.ensure_folder()  # اگر پوشه حذف شده باشد دوباره ساخته می‌شود
        tmp = path + ".part"
        try:
            wb.save(tmp)          # نوشتن اتمی: فایل نهایی فقط کامل ظاهر می‌شود
            os.replace(tmp, path)
        finally:
            try:
                if os.path.exists(tmp):
                    os.remove(tmp)
            except Exception:
                pass
        return path

    # ---------------- شیت‌های ستونی لیست حقوق (نسخه 1.7.9) ----------------
    #
    # آینه build_report_xlsx افزونه (class-tppsalary-reports.php — قالب 1.6.1):
    #  — «نام ستون» = نام کارمند و «سطرها» = عناوین حقوق
    #  — سطر ۱ عنوان (شرکت — لیست حقوق دوره — مرکز)، سطر ۲ واحد پول، سطر ۳ خالی
    #  — فیلدهای فقط‌محاسباتی و فیلدهای عددیِ همه‌صفر دوره حذف می‌شوند (1.7.3 افزونه)
    #  — اعداد با جداکننده هزارگان؛ منفی قرمز

    @staticmethod
    def _to_float(v):
        try:
            return float(v or 0)
        except (TypeError, ValueError):
            return 0.0

    @classmethod
    def _parse_payload(cls, rec):
        try:
            payload = json.loads(rec.get("payload") or "{}")
            return payload if isinstance(payload, dict) else {}
        except Exception:
            return {}

    @classmethod
    def _salary_printable_fields(cls, snap_fields, payloads):
        """آینه printable_fields افزونه — بدون CALC_ONLY و فیلدهای عددی همه‌صفر دوره."""
        out = []
        for f in snap_fields:
            key = str(f["field_key"])
            if key in CALC_ONLY_KEYS:
                continue
            ftype = str(f["type"] or "")
            if ftype == "number":
                if not any(abs(cls._to_float(p.get(key))) > 1e-4 for p in payloads):
                    continue
            out.append((key, str(f["label"] or key), ftype))
        return out

    @staticmethod
    def _unique_sheet_name(used, base):
        name, n = base, 2
        while name in used or len(name) > 31:
            name = "%s (%d)" % (base, n)
            n += 1
        used.add(name)
        return name

    def _write_salary_sheets(self, wb, snap, emp_by_id, center_names):
        from openpyxl.styles import Alignment, Border, Font, PatternFill, Side
        from openpyxl.utils import get_column_letter

        groups = {}
        for rec in snap["records"]:
            key = (int(rec["jyear"] or 0), int(rec["jmonth"] or 0), int(rec["center_id"] or 0))
            groups.setdefault(key, []).append(rec)
        if not groups:
            return

        used = set(wb.sheetnames)
        company = str(snap.get("company") or "")
        currency = str(snap.get("currency") or "ریال")

        title_font = Font(bold=True, size=12)
        head_font = Font(bold=True, size=10)
        head_fill = PatternFill("solid", fgColor="E8EDF5")
        label_fill = PatternFill("solid", fgColor="F7F7F7")
        thin = Side(style="thin", color="B8BFCB")
        border = Border(left=thin, right=thin, top=thin, bottom=thin)
        center = Alignment(horizontal="center", vertical="center")
        right = Alignment(horizontal="right", vertical="center")
        numfmt = "#,##0;[Red]-#,##0"

        for (jy, jm, cid), recs in groups.items():
            payloads = [self._parse_payload(r) for r in recs]
            fields = self._salary_printable_fields(snap["fields"], payloads)
            center_name = center_names.get(cid, "مرکز #%d" % cid)
            pages = [recs[i:i + MAX_COLS_PER_SHEET] for i in range(0, len(recs), MAX_COLS_PER_SHEET)]
            for ci, chunk in enumerate(pages):
                base = "لیست %04d-%02d" % (jy, jm)
                if len(groups) > 1 or ci > 0 or len(chunk) > 1:
                    base += " " + center_name.strip()[:10]
                if ci > 0:
                    base += " (%d)" % (ci + 1)
                ws = wb.create_sheet(self._unique_sheet_name(used, base))
                ws.sheet_view.rightToLeft = True
                ncols = 1 + len(chunk)

                # سطر ۱: عنوان — شرکت + دوره + مرکز
                title = ("%s — " % company if company else "") + \
                    "لیست حقوق %s — %s" % (J.period_label(jy, jm), center_name)
                ws.cell(row=1, column=1, value=title)
                ws.merge_cells(start_row=1, start_column=1, end_row=1, end_column=ncols)
                ws.cell(row=1, column=1).font = title_font

                # سطر ۲: واحد پول
                ws.cell(row=2, column=1, value="واحد: %s" % currency)
                ws.merge_cells(start_row=2, start_column=1, end_row=2, end_column=ncols)
                ws.cell(row=2, column=1).font = head_font

                # سطر ۳ خالی — هدر در سطر ۴: نام کارمندان (نام ستون = نام کارمند)
                hdr = 4
                for cc in range(1, ncols + 1):
                    cell = ws.cell(row=hdr, column=cc)
                    cell.font = head_font
                    cell.fill = head_fill
                    cell.border = border
                    cell.alignment = center
                ws.cell(row=hdr, column=1, value="عناوین")
                for k, rec in enumerate(chunk):
                    emp = emp_by_id.get(int(rec.get("user_id") or 0))
                    name = str(emp["name"]) if emp else "کارمند #%s" % rec.get("user_id")
                    ws.cell(row=hdr, column=2 + k, value=name)

                # سطرها: فقط عناوین حقوق
                r = hdr + 1
                for key, label, ftype in fields:
                    lab = ws.cell(row=r, column=1, value=label)
                    lab.font = head_font
                    lab.fill = label_fill
                    lab.border = border
                    lab.alignment = right
                    for k, rec in enumerate(chunk):
                        payload = self._parse_payload(rec)
                        cell = ws.cell(row=r, column=2 + k)
                        cell.border = border
                        cell.alignment = center
                        if ftype == "number":
                            val = self._to_float(payload.get(key))
                            cell.value = val
                            cell.number_format = numfmt
                        else:
                            cell.value = str(payload.get(key, "") or "")
                    r += 1

                ws.column_dimensions["A"].width = 26
                for cc in range(2, ncols + 1):
                    ws.column_dimensions[get_column_letter(cc)].width = 22
                ws.freeze_panes = ws.cell(row=hdr + 1, column=2).coordinate

    # ---------------- فایل‌ها و نگهداری ----------------

    def ensure_folder(self):
        try:
            os.makedirs(self.folder, exist_ok=True)
        except Exception:
            pass

    def _new_path(self, now):
        base = _BACKUP_PREFIX + now.strftime("%Y-%m-%d_%H%M%S")
        path = os.path.join(self.folder, base + ".xlsx")
        n = 2
        while os.path.exists(path):
            path = os.path.join(self.folder, "%s-%d.xlsx" % (base, n))
            n += 1
        return path

    def backup_files(self):
        """فهرست فایل‌های بکاپ مرتب بر اساس زمان (قدیمی → جدید)."""
        try:
            names = [n for n in os.listdir(self.folder) if n.startswith(_BACKUP_PREFIX) and n.endswith(".xlsx")]
        except Exception:
            return []
        return [os.path.join(self.folder, n) for n in sorted(names)]

    def _cleanup_old(self):
        files = self.backup_files()
        extra = len(files) - self.keep_last
        if extra <= 0:
            return
        for path in files[:extra]:
            try:
                os.remove(path)
            except Exception:
                pass

    def _sleep(self, seconds):
        # توقف قابل قطع — در تست‌ها و بستن برنامه معطل نمی‌مانیم
        deadline = threading.Event()
        deadline.wait(seconds)
