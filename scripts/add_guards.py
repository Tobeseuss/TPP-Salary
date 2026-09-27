#!/usr/bin/env python3
"""
add_guards.py — 防双重加载守卫注入（修复 "Cannot declare class ... already in use"）

三层防御：
1. tpp-salary.php        → 早退守卫（检测到另一副本已加载 → 管理员提示 + return）
2. includes/class-*.php  → 每个类声明包上 if ( ! class_exists() ) 守卫
3. includes/helpers.php  → 每个顶层函数包上 if ( ! function_exists() ) 守卫

插入位置：紧邻前置 docblock 之前（保持文档块与类/函数的关联）；
闭括号追加到文件末尾（PHP 中 if 块内嵌套类/函数声明完全合法）。
"""
import re
import sys
from pathlib import Path

BASE = Path('/home/z/my-project/build/tpp_salary')
INC = BASE / 'includes'

CLASS_RE = re.compile(r'^(final\s+|abstract\s+)?class\s+(TPP_\w+)')
FUNC_RE = re.compile(r'^function\s+(tpp_\w+)\s*\(')


def find_docblock_start(lines, decl_idx):
    """从声明行向上查找紧邻的 docblock 起始行（/**），返回应插入守卫的行号。"""
    i = decl_idx - 1
    while i >= 0:
        stripped = lines[i].strip()
        if stripped == '' or stripped.startswith('*') or stripped.startswith('*/'):
            if stripped == '/**':
                return i
            i -= 1
            continue
        break
    return decl_idx  # 无 docblock，直接插在声明前


def guard_class_file(path):
    text = path.read_text(encoding='utf-8')
    lines = text.split('\n')
    changed = []

    # 逐行扫描（仅顶层，缩进为 0 的 class 声明）
    for idx, line in enumerate(lines):
        m = CLASS_RE.match(line)
        if not m:
            continue
        cname = m.group(2)
        # 是否已有守卫（检查声明前 6 行）
        window = '\n'.join(lines[max(0, idx - 6):idx])
        if 'class_exists' in window:
            changed.append(f'  SKIP (already guarded): {cname}')
            continue
        insert_at = find_docblock_start(lines, idx)
        guard_open = [f"if ( ! class_exists( '{cname}' ) ) {{",
                      "\t// TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)"]
        lines[insert_at:insert_at] = guard_open
        changed.append(f'  GUARD class {cname} @ line {insert_at + 1}')
        # 只处理第一个未守卫的类（本插件每文件一类）
        break

    if len(changed) > 0 and any('GUARD' in c for c in changed):
        # 追加闭括号（保持文件以换行结尾）
        while lines and lines[-1] == '':
            lines.pop()
        lines.append('}')
        lines.append('// TPP_SALARY GUARD END')
        lines.append('')
        path.write_text('\n'.join(lines), encoding='utf-8')
    return changed


def guard_helpers(path):
    text = path.read_text(encoding='utf-8')
    lines = text.split('\n')
    changed = []
    i = 0
    while i < len(lines):
        line = lines[i]
        m = FUNC_RE.match(line)
        if not m:
            i += 1
            continue
        fname = m.group(1)
        window = '\n'.join(lines[max(0, i - 4):i])
        if 'function_exists' in window:
            changed.append(f'  SKIP (already guarded): {fname}')
            i += 1
            continue
        insert_at = find_docblock_start(lines, i)
        # 找函数结束行：花括号深度回到 0
        depth = 0
        opened = False
        j = i
        while j < len(lines):
            for ch in lines[j]:
                if ch == '{':
                    depth += 1
                    opened = True
                elif ch == '}':
                    depth -= 1
            if opened and depth <= 0:
                break
            j += 1
        if not opened or j >= len(lines):
            changed.append(f'  ERROR: cannot find end of {fname}')
            break
        guard_open = [f"if ( ! function_exists( '{fname}' ) ) {{",
                      "\t// TPP_SALARY GUARD: جلوگیری از Cannot redeclare function"]
        lines[insert_at:insert_at] = guard_open
        # 行号偏移：j 之后 +2
        lines[j + 2:j + 2] = ['}', f'// TPP_SALARY GUARD END ({fname})']
        changed.append(f'  GUARD function {fname} (lines {insert_at + 1}–{j + 3})')
        i = j + 4
    if any('GUARD' in c for c in changed):
        path.write_text('\n'.join(lines), encoding='utf-8')
    return changed


def main():
    print('=== 1) class files ===')
    total = 0
    for f in sorted(INC.glob('class-tppsalary-*.php')):
        results = guard_class_file(f)
        if results:
            print(f'{f.name}:')
            for r in results:
                print(r)
            total += sum(1 for r in results if 'GUARD' in r)
    print(f'class guards added: {total}')

    print('=== 2) helpers.php ===')
    for r in guard_helpers(INC / 'helpers.php'):
        print(r)

    print('DONE')


if __name__ == '__main__':
    main()
