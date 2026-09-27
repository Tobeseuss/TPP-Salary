#!/usr/bin/env python3
"""
test_ddl_words.py — اسکنر کلمات رزرو MySQL/MariaDB در DDL پلاگین

این باگ واقعی رخ داد: جدول field_archives در یک نسخه میانی، ستون `values` داشت
(کلمه رزرو) و فعال‌سازی روی MariaDB با خطای SQL شکست می‌خورد.
این اسکنر همه CREATE TABLE های پلاگین را می‌خواند و نام جدول/ستون/ایندکس‌ها را
با فهرست کلمات رزرو مقایسه می‌کند تا این دست خطا هرگز تکرار نشود.
"""
import re
import sys
from pathlib import Path

PLUGIN = Path('/home/z/my-project/build/tpp_salary')

# زیرمجموعه عملیاتی کلمات رزرو MySQL 5.7/8.0 + MariaDB 10.x (پرکاربردترین‌ها)
RESERVED = {
    'accessible', 'add', 'all', 'alter', 'analyze', 'and', 'as', 'asc', 'before',
    'between', 'bigint', 'binary', 'blob', 'both', 'by', 'call', 'cascade', 'case',
    'change', 'char', 'character', 'check', 'collate', 'column', 'condition',
    'constraint', 'continue', 'convert', 'create', 'cross', 'current_date',
    'current_time', 'current_timestamp', 'current_user', 'cursor', 'database',
    'databases', 'day_hour', 'day_minute', 'day_second', 'dec', 'decimal',
    'declare', 'default', 'delayed', 'delete', 'desc', 'describe', 'deterministic',
    'distinct', 'distinctrow', 'div', 'double', 'drop', 'dual', 'each', 'else',
    'elseif', 'empty', 'enclosed', 'escaped', 'except', 'exists', 'exit', 'explain',
    'false', 'fetch', 'float', 'for', 'force', 'foreign', 'from', 'fulltext',
    'function', 'generated', 'get', 'grant', 'group', 'grouping', 'groups',
    'high_priority', 'hour_minute', 'hour_second', 'if', 'ignore', 'in', 'index',
    'infile', 'inner', 'inout', 'insensitive', 'insert', 'int', 'integer',
    'interval', 'into', 'is', 'iterate', 'join', 'key', 'keys', 'kill', 'leading',
    'leave', 'left', 'like', 'limit', 'linear', 'lines', 'load', 'localtime',
    'localtimestamp', 'lock', 'long', 'longblob', 'longtext', 'loop',
    'low_priority', 'match', 'maxvalue', 'mediumblob', 'mediumint', 'mediumtext',
    'middleint', 'minute_second', 'mod', 'modifies', 'natural', 'not',
    'no_write_to_binlog', 'null', 'numeric', 'of', 'on', 'optimize', 'option',
    'optionally', 'or', 'order', 'out', 'outer', 'outfile', 'over', 'partition',
    'precision', 'primary', 'procedure', 'purge', 'range', 'rank', 'read', 'reads',
    'read_write', 'real', 'recursive', 'references', 'regexp', 'release', 'rename',
    'repeat', 'replace', 'require', 'restrict', 'return', 'revoke', 'right',
    'rlike', 'row', 'rows', 'schema', 'schemas', 'second_microsecond', 'select',
    'sensitive', 'separator', 'set', 'show', 'signal', 'smallint', 'spatial',
    'specific', 'sql', 'sqlexception', 'sqlstate', 'sqlwarning', 'ssl',
    'starting', 'stored', 'straight_join', 'system', 'table', 'terminated', 'then',
    'tinyblob', 'tinyint', 'tinytext', 'to', 'trailing', 'trigger', 'true', 'undo',
    'union', 'unique', 'unlock', 'unsigned', 'update', 'usage', 'use', 'using',
    'utc_date', 'utc_time', 'utc_timestamp', 'values', 'varbinary', 'varchar',
    'varying', 'virtual', 'when', 'where', 'while', 'window', 'with', 'write',
    'xor', 'year_month', 'zerofill',
    # رزرو در MySQL 8.0 / MariaDB 10.2+ (توابع پنجره‌ای و ...)
    'rows', 'row_number', 'nth_value', 'ntile', 'dense_rank', 'percent_rank',
    'cume_dist', 'lead', 'lag', 'first_value', 'last_value', 'json_table',
    'lateral', 'master_bind', 'io_after_gtids', 'io_before_gtids',
}

# انواع ستون که بعد از نام ستون می‌آیند
TYPES = r'(?:bigint|int|smallint|tinyint|mediumint|varchar|char|text|longtext|mediumtext|tinytext|datetime|date|timestamp|time|decimal|double|float|blob|longblob|json|enum|year|boolean|bool)'

ddl_re = re.compile(r'CREATE\s+TABLE\s+\{?\$?(\w+)\}?\s*\((.*?)\)\s*\{?\$charset\}?;', re.S | re.I)
col_re = re.compile(rf'^\s*(\w+)\s+{TYPES}\b', re.I | re.M)
key_re = re.compile(r'^\s*(?:UNIQUE\s+KEY|KEY|INDEX)\s+(\w+)', re.I | re.M)
pk_re = re.compile(r'^\s*PRIMARY\s+KEY', re.I | re.M)

errors = []
checked = 0

for f in sorted(PLUGIN.rglob('*.php')):
    text = f.read_text(encoding='utf-8', errors='ignore')
    for m in ddl_re.finditer(text):
        checked += 1
        line_no = text[:m.start()].count('\n') + 1
        body = m.group(2)
        # نام ستون‌ها
        for cm in col_re.finditer(body):
            col = cm.group(1).lower()
            if col in RESERVED:
                errors.append(f'{f.relative_to(PLUGIN)}:{line_no} — COLUMN "{col}" reserved!')
        # نام ایندکس‌ها
        for km in key_re.finditer(body):
            idx = km.group(1).lower()
            if idx in RESERVED:
                errors.append(f'{f.relative_to(PLUGIN)}:{line_no} — INDEX "{idx}" reserved!')

print(f'DDL blocks checked: {checked}')
if errors:
    print('FAIL — reserved word usage found:')
    for e in errors:
        print('  ' + e)
    sys.exit(1)
print('RESULT: PASS — no reserved words in any DDL')
