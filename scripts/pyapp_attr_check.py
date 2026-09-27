# -*- coding: utf-8 -*-
"""AST check: every self.<attr> Load in python-app classes must be resolvable.

Resolvable = method defined in class MRO, assigned somewhere via self.<attr>=...,
inherited from a real Qt base class (introspected live), or a module-level
import visible as global. Exit 1 on any finding.
"""
import ast
import os
import sys

APP = "/home/z/my-project/build/tpp_salary/python-app"

# Parse real PySide6 .pyi stubs to get base-class attribute names (no Qt import needed)
import glob

STUB_DIRS = glob.glob("/home/z/.venv/lib/python3*/site-packages/PySide6")

def _pyi_class_attrs(cls_name, _cache={}):
    """All attribute/method names of a Qt class incl. inherited, from .pyi stubs."""
    if cls_name in _cache:
        return _cache[cls_name]
    _cache[cls_name] = set()
    if not STUB_DIRS:
        return _cache[cls_name]
    for stub in ("QtCore.pyi", "QtWidgets.pyi", "QtGui.pyi"):
        p = os.path.join(STUB_DIRS[0], stub)
        if not os.path.exists(p):
            continue
        try:
            t = ast.parse(open(p, encoding="utf-8").read())
        except Exception:
            continue
        for n in ast.walk(t):
            if isinstance(n, ast.ClassDef) and n.name == cls_name:
                for item in ast.walk(n):
                    if isinstance(item, (ast.FunctionDef, ast.AnnAssign)):
                        if isinstance(item, ast.FunctionDef):
                            _cache[cls_name].add(item.name)
                            for d in item.decorator_list:
                                if isinstance(d, ast.Name) and d.id in ("property", "staticmethod"):
                                    pass
                        else:
                            if isinstance(item.target, ast.Name):
                                _cache[cls_name].add(item.target.id)
                # resolve parents recursively (bases may be Name or fully-qualified Attribute)
                for b in n.bases:
                    bn = b.id if isinstance(b, ast.Name) else (b.attr if isinstance(b, ast.Attribute) else None)
                    if bn:
                        _cache[cls_name] |= _pyi_class_attrs(bn)
                break
    return _cache[cls_name]

WANTED = ["QFrame", "QWidget", "QDialog", "QMainWindow", "QObject",
          "QLabel", "QLineEdit", "QPushButton", "QComboBox", "QCheckBox",
          "QTableWidget", "QMessageBox", "QApplication", "QTimer",
          "QComboBox", "QListWidget", "QPlainTextEdit", "QSpinBox",
          "QGroupBox", "QScrollArea", "QStackedWidget", "QToolBar"]

BASES = {w: _pyi_class_attrs(w) for w in WANTED}
qt_total = max((len(v) for v in BASES.values()), default=0)
print("qt stub coverage: max base attrs = %d" % qt_total)
if qt_total < 50:
    print("WARN: stub parsing may have failed")

# safety net: QWidget/QDialog methods the stub parser is known to miss
QT_SAFE = {"setWindowTitle", "resize", "setMinimumSize", "setMinimumWidth",
           "setMaximumSize", "setFixedHeight", "setFixedWidth", "setLayout",
           "setObjectName", "show", "hide", "close", "exec", "raise_"}
for k in BASES:
    BASES[k] |= QT_SAFE

failures = []
files = []
for root, _dirs, names in os.walk(APP):
    for n in names:
        if n.endswith(".py") and n != "__init__.py":
            files.append(os.path.join(root, n))

for path in sorted(files):
    src = open(path, encoding="utf-8").read()
    tree = ast.parse(src)
    rel = os.path.relpath(path, APP)
    # collect top-level imports for global names
    globals_known = set()
    for node in ast.walk(tree):
        if isinstance(node, ast.Import):
            for a in node.names:
                globals_known.add((a.asname or a.name).split(".")[0])
        elif isinstance(node, ast.ImportFrom):
            for a in node.names:
                globals_known.add(a.asname or a.name)
        elif isinstance(node, (ast.FunctionDef, ast.ClassDef)):
            globals_known.add(node.name)
        elif isinstance(node, ast.Assign):
            for t in node.targets:
                if isinstance(t, ast.Name):
                    globals_known.add(t.id)

    for cnode in [n for n in ast.walk(tree) if isinstance(n, ast.ClassDef)]:
        # gather method names + self-assigned attrs across the class body
        methods, assigned = set(), set()
        for item in ast.walk(cnode):
            if isinstance(item, ast.FunctionDef):
                methods.add(item.name)
            if isinstance(item, ast.Attribute) and isinstance(item.value, ast.Name) \
                    and item.value.id == "self" and isinstance(item.ctx, ast.Store):
                assigned.add(item.attr)

        base_names = set()
        for b in cnode.bases:
            if isinstance(b, ast.Name) and b.id in BASES:
                base_names |= BASES[b.id]

        # now scan Loads of self.<attr>
        for item in ast.walk(cnode):
            if isinstance(item, ast.Attribute) and isinstance(item.value, ast.Name) \
                    and item.value.id == "self" and isinstance(item.ctx, ast.Load):
                a = item.attr
                if a in methods or a in assigned or a in base_names or a in globals_known:
                    continue
                line = getattr(item, "lineno", 0)
                failures.append("%s:%s: class %s → self.%s NOT FOUND" % (rel, line, cnode.name, a))

if failures:
    print("MISSING ATTRS (%d):" % len(failures))
    for f in failures:
        print("  " + f)
    sys.exit(1)
print("attr-check: ALL self.* references resolvable in %d files" % len(files))
