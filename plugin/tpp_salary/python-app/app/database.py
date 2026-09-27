# -*- coding: utf-8 -*-
"""
دیتابیس محلی برنامه آفلاین (SQLite) — آینه جدول‌های سایت + صف همگام‌سازی.

جدول‌ها:
- centers / banks / employees / records  → داده اصلی (pending = تغییر محلی هنوز ارسال‌نشده)
- fields                                  → ساختار فیلدهای فیش (از سایت)
- kv                                      → تنظیمات/متادیتا (شرکت، دوره جاری، آخرین همگام‌سازی…)
- outbox                                  → صف تغییرات آفلاین برای ارسال به سایت
- sync_log                                → گزارش رویدادهای همگام‌سازی
"""

import os
import re
import sqlite3
import threading

from contextlib import contextmanager

SCHEMA = """
CREATE TABLE IF NOT EXISTS centers (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL DEFAULT '',
    created_at TEXT DEFAULT '',
    pending INTEGER NOT NULL DEFAULT 0,
    local_deleted INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS banks (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL DEFAULT '',
    sort_order INTEGER NOT NULL DEFAULT 0,
    pending INTEGER NOT NULL DEFAULT 0,
    local_deleted INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS employees (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL DEFAULT '',
    login TEXT DEFAULT '',
    email TEXT DEFAULT '',
    national TEXT DEFAULT '',
    mobile TEXT DEFAULT '',
    terminated INTEGER NOT NULL DEFAULT 0,
    centers TEXT NOT NULL DEFAULT '[]',
    profile TEXT NOT NULL DEFAULT '{}',
    pending INTEGER NOT NULL DEFAULT 0,
    local_deleted INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS records (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL DEFAULT 0,
    center_id INTEGER NOT NULL DEFAULT 0,
    jyear INTEGER NOT NULL DEFAULT 0,
    jmonth INTEGER NOT NULL DEFAULT 0,
    payload TEXT NOT NULL DEFAULT '{}',
    gross REAL NOT NULL DEFAULT 0,
    insurable REAL NOT NULL DEFAULT 0,
    insurance_deduct REAL NOT NULL DEFAULT 0,
    other_deductions REAL NOT NULL DEFAULT 0,
    net REAL NOT NULL DEFAULT 0,
    updated_at TEXT DEFAULT '',
    created_at TEXT DEFAULT '',
    pending INTEGER NOT NULL DEFAULT 0,
    local_deleted INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_records_period ON records(jyear, jmonth, center_id);
CREATE INDEX IF NOT EXISTS idx_records_user ON records(user_id);
CREATE TABLE IF NOT EXISTS fields (
    field_key TEXT PRIMARY KEY,
    label TEXT DEFAULT '',
    type TEXT DEFAULT 'number',
    default_value TEXT DEFAULT '',
    formula TEXT DEFAULT '',
    calculated INTEGER NOT NULL DEFAULT 0,
    allow_manual INTEGER NOT NULL DEFAULT 1,
    sort INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS kv (
    k TEXT PRIMARY KEY,
    v TEXT DEFAULT ''
);
CREATE TABLE IF NOT EXISTS outbox (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ref TEXT NOT NULL,
    entity TEXT NOT NULL,
    action TEXT NOT NULL,
    data TEXT NOT NULL DEFAULT '{}',
    client_updated_at TEXT DEFAULT '',
    status TEXT NOT NULL DEFAULT 'pending',
    attempts INTEGER NOT NULL DEFAULT 0,
    error TEXT DEFAULT '',
    created_at TEXT DEFAULT ''
);
CREATE INDEX IF NOT EXISTS idx_outbox_status ON outbox(status);
CREATE TABLE IF NOT EXISTS sync_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ts TEXT DEFAULT '',
    level TEXT DEFAULT 'info',
    message TEXT DEFAULT ''
);
"""


# جدول‌های داده که نوشتن در آن‌ها باید پشتیبان اکسل آنی را فعال کند (نسخه 1.7.5) —
# kv/sync_log/outbox عمداً مستثنی‌اند تا لاگ‌ها و صف همگام‌سازی باعث بکاپ تکراری نشوند.
DATA_TABLES = frozenset({"centers", "banks", "employees", "records", "fields"})

# استخراج نام جدول از ابتدای دستور SQL نوشته‌شده در همین پروژه (INSERT INTO x / UPDATE x / DELETE FROM x)
_SQL_TABLE_RE = re.compile(
    r"^\s*(?:INSERT\s+(?:OR\s+\w+\s+)?INTO|REPLACE\s+INTO|UPDATE|DELETE\s+FROM)\s+[\"'`\[]?(\w+)",
    re.IGNORECASE,
)


def table_of(sql):
    """نام جدولِ هدف دستور SQL نوشتن را برمی‌گرداند (ناشناخته = رشته خالی)."""
    m = _SQL_TABLE_RE.match(sql or "")
    return m.group(1).lower() if m else ""


class Database(object):
    """اتصال SQLite با قفل تردها (GUI + worker همگام‌سازی)."""

    def __init__(self, app_dir):
        os.makedirs(os.path.join(app_dir, "data"), exist_ok=True)
        self.path = os.path.join(app_dir, "data", "tpp_salary_local.db")
        self._lock = threading.RLock()
        self.conn = sqlite3.connect(self.path, check_same_thread=False)
        self.conn.row_factory = sqlite3.Row
        self.write_listeners = []  # نسخه 1.7.5: شنونده‌های نوشتن (پشتیبان اکسل آنی)
        with self._lock:
            self.conn.executescript(SCHEMA)
            self.conn.execute("PRAGMA journal_mode=WAL")
            self.conn.commit()

    @contextmanager
    def locked(self):
        """بلوک خواندن هماهنگ (snapshot) — RLock تردهای موازی را می‌بندد.
        مثال: with db.locked(): rows = db.q(...)"""
        with self._lock:
            yield

    # ---------------- شنونده‌های نوشتن (نسخه 1.7.5) ----------------

    def add_write_listener(self, fn):
        """fn(tables) پس از هر commit موفق روی جدول‌های داده صدا زده می‌شود
        (tables = مجموعه نام جدول‌های تغییرکرده). استثنای شنونده نادیده گرفته می‌شود."""
        self.write_listeners.append(fn)

    def _notify_writes(self, sqls):
        if not self.write_listeners:
            return
        tables = set()
        for sql in sqls:
            t = table_of(sql)
            if t in DATA_TABLES:
                tables.add(t)
        if not tables:
            return
        for fn in tuple(self.write_listeners):
            try:
                fn(frozenset(tables))
            except Exception:
                pass

    # ---------------- ابزار پایه ----------------

    def q(self, sql, params=()):
        with self._lock:
            cur = self.conn.execute(sql, params)
            return [dict(r) for r in cur.fetchall()]

    def one(self, sql, params=()):
        rows = self.q(sql, params)
        return rows[0] if rows else None

    def ex(self, sql, params=()):
        with self._lock:
            cur = self.conn.execute(sql, params)
            self.conn.commit()
            self._notify_writes((sql,))
            return cur.lastrowid

    def exmany(self, statements):
        """اجرای چند دستور در یک تراکنش — statements: [(sql, params), ...]"""
        with self._lock:
            try:
                for sql, params in statements:
                    self.conn.execute(sql, params)
                self.conn.commit()
                self._notify_writes([sql for sql, _p in statements])
            except Exception:
                self.conn.rollback()
                raise

    def close(self):
        try:
            self.conn.close()
        except Exception:
            pass

    # ---------------- kv ----------------

    def kv_get(self, key, default=""):
        row = self.one("SELECT v FROM kv WHERE k = ?", (key,))
        return row["v"] if row else default

    def kv_set(self, key, value):
        self.ex(
            "INSERT INTO kv(k, v) VALUES(?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v",
            (key, str(value)),
        )

    # ---------------- لاگ ----------------

    def log(self, message, level="info"):
        import datetime
        ts = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        self.ex(
            "INSERT INTO sync_log(ts, level, message) VALUES(?, ?, ?)",
            (ts, level, str(message)),
        )
