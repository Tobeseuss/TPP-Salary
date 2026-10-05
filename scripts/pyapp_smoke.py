# -*- coding: utf-8 -*-
"""Headless smoke test for tpp_salary python-app.

Instantiates the full MainWindow offscreen, visits every page, clicks every
QPushButton with all modals stubbed — catches AttributeError/crash-class bugs
like the reported RecordsPage._bulk_delete.

Run: python3 scripts/pyapp_smoke.py
Exit 0 = clean; exit 1 = failures (printed).
"""
import os
import sys
import tempfile
import traceback

os.environ["QT_QPA_PLATFORM"] = "offscreen"
os.environ.setdefault("QT_LOGGING_RULES", "*.debug=false")

APP = os.environ.get("TPP_SMOKE_APP", "/home/z/my-project/build/tpp_salary/python-app")
sys.path.insert(0, APP)

from PySide6.QtWidgets import (QApplication, QDialog, QFileDialog,
                               QInputDialog, QMessageBox)

# ---- stub all blocking modals ----
QMessageBox.information = staticmethod(lambda *a, **k: QMessageBox.StandardButton.Ok)
QMessageBox.warning = staticmethod(lambda *a, **k: QMessageBox.StandardButton.Ok)
QMessageBox.critical = staticmethod(lambda *a, **k: QMessageBox.StandardButton.Ok)
QMessageBox.question = staticmethod(lambda *a, **k: QMessageBox.StandardButton.No)
QMessageBox.about = staticmethod(lambda *a, **k: None)
QDialog.exec = lambda self, *a, **k: QDialog.DialogCode.Rejected
QFileDialog.getSaveFileName = staticmethod(lambda *a, **k: ("", ""))
QFileDialog.getOpenFileName = staticmethod(lambda *a, **k: ("", ""))
QFileDialog.getExistingDirectory = staticmethod(lambda *a, **k: "")
QInputDialog.getText = staticmethod(lambda *a, **k: ("", False))
QInputDialog.getItem = staticmethod(lambda *a, **k: ("", False))
QInputDialog.getInt = staticmethod(lambda *a, **k: (0, False))
QInputDialog.getDouble = staticmethod(lambda *a, **k: (0.0, False))

# PySide6 reports slot exceptions via sys.excepthook — capture them
slot_errors = []
def _hook(t, v, tb):
    slot_errors.append("".join(traceback.format_exception(t, v, tb)))
sys.excepthook = _hook


def main():
    app = QApplication.instance() or QApplication(sys.argv)
    tmp = tempfile.mkdtemp(prefix="tpp_smoke_")

    from app.ui.app_window import MainWindow
    win = MainWindow(tmp)
    win.show()

    errors = []
    clicked = 0

    # 1) visit every page
    for key, page in list(win.pages.items()):
        try:
            win.show_page(key)
            if hasattr(page, "refresh"):
                page.refresh()
        except Exception:
            errors.append("show_page(%s): %s" % (key, traceback.format_exc(limit=4)))

    # 2) click every push button on every page
    for key, page in list(win.pages.items()):
        win.show_page(key)
        from PySide6.QtWidgets import QPushButton
        for btn in page.findChildren(QPushButton):
            n0 = len(slot_errors)
            try:
                btn.click()
                clicked += 1
            except Exception:
                errors.append("click %s#%s: %s" % (
                    key, btn.text()[:25], traceback.format_exc(limit=4)))
            if len(slot_errors) > n0:
                for e in slot_errors[n0:]:
                    errors.append("slot-error %s#%s: %s" % (
                        key, btn.text()[:25], e.strip().splitlines()[-3:]))

    # 3) records page regression: bulk + single delete handlers must exist & run
    try:
        rec = win.pages["records"]
        assert hasattr(rec, "_delete_selected"), "records: _delete_selected missing"
        assert hasattr(rec, "_delete_one_selected"), "records: _delete_one_selected missing"
        assert not hasattr(rec, "_bulk_delete") or callable(getattr(rec, "_bulk_delete", None)), "records: _bulk_delete must not be referenced"
        rec.table.setCurrentCell(0, 1)
        rec._delete_one_selected()   # empty-table path → info box (stubbed)
        rec._delete_selected()       # no selection path → info box (stubbed)
        rec._go(1)
        rec._go(-1)
    except Exception:
        errors.append("records handlers: %s" % traceback.format_exc(limit=4))

    # 3b) functional: real data → bulk delete (No) → single delete (Yes)
    try:
        from PySide6.QtWidgets import QCheckBox
        rec = win.pages["records"]
        emp_id = win.store.save_employee({"id": 0, "name": "کارمند تست", "national": "1234567890"})
        cen_id = win.store.save_center(0, "مرکز تست")
        rec_id = win.store.upsert_record(emp_id, cen_id, 1404, 5, {"gross": "10,000,000", "base": "10,000,000"})
        rec.refresh()
        assert rec.total == 1, "expected 1 record, got %s" % rec.total

        # bulk path with question=No → nothing deleted
        w = rec.table.cellWidget(0, 0)
        w.findChild(QCheckBox).setChecked(True)
        rec.btn_del_bulk.click()
        assert rec.total == 1, "bulk delete with No must not delete"

        # single path with question=Yes → record deleted
        QMessageBox.question = staticmethod(lambda *a, **k: QMessageBox.StandardButton.Yes)
        rec.table.setCurrentCell(0, 1)
        rec._delete_one_selected()
        assert rec.total == 0, "single delete with Yes must delete (got total=%s)" % rec.total
    except Exception:
        errors.append("functional delete: %s" % traceback.format_exc(limit=6))

    # 4) status / pending without sync
    try:
        win.update_pending()
        win.set_status("DotOffline", "smoke")
    except Exception:
        errors.append("status: %s" % traceback.format_exc(limit=4))

    # 5) excel auto-backup (1.7.5): startup file + dedup + forced + debounced save + content
    try:
        import time as _time
        from openpyxl import load_workbook

        mgr = getattr(win, "backup_mgr", None)
        assert mgr is not None, "backup manager missing"

        # a) startup (run) and/or debounced save backup must exist — wait ≤15s
        deadline = _time.time() + 15
        while _time.time() < deadline and not mgr.backup_files():
            _time.sleep(0.25)
        assert mgr.backup_files(), "no excel backup created (last_error=%r)" % mgr.last_error
        # let any pending debounce from section 3b settle before dedup assertions
        _time.sleep(mgr.debounce + 0.7)
        base = len(mgr.backup_files())

        # b) dedup: unchanged data → no new file
        mgr.backup_now(reason="smoke-dedup")
        assert len(mgr.backup_files()) == base, "dedup failed: file written without data change"

        # c) forced immediate backup → new file
        p = mgr.backup_now(reason="smoke-forced", force=True)
        assert p and os.path.exists(p), "forced backup failed (last_error=%r)" % mgr.last_error
        assert len(mgr.backup_files()) == base + 1, "forced backup file not listed"

        # d) any save → debounced backup appears within ~5s
        win.store.save_center(0, "مرکز پشتیبان")
        deadline = _time.time() + 12
        while _time.time() < deadline and len(mgr.backup_files()) == base + 1:
            _time.sleep(0.25)
        assert len(mgr.backup_files()) > base + 1, \
            "save-triggered backup missing (last_error=%r)" % mgr.last_error

        # e) content: all sheets present + row counts match store
        #    (نسخه 1.7.9: شیت سطری «حقوق و دستمزد» با شیت‌های ستونی «لیست …» جایگزین شد)
        wb = load_workbook(mgr.backup_files()[-1])
        for sheet in ("اطلاعات بکاپ", "کارمندان", "مراکز", "بانک‌ها"):
            assert sheet in wb.sheetnames, "missing sheet %s (got %s)" % (sheet, wb.sheetnames)
        assert "حقوق و دستمزد" not in wb.sheetnames, \
            "legacy row sheet still present (got %s)" % wb.sheetnames
        n_emp = len(win.store.employees())
        rows_emp = max(0, wb["کارمندان"].max_row - 1)
        assert rows_emp == n_emp, "employees sheet rows %s != store %s" % (rows_emp, n_emp)
        n_rec = int(win.db.one("SELECT COUNT(*) AS c FROM records WHERE local_deleted = 0")["c"])
        sal_sheets = [n for n in wb.sheetnames if n.startswith("لیست ")]
        if n_rec:
            assert sal_sheets, "columnar salary sheets missing while records exist"
            recs_per_sheet = {}
            for sn in sal_sheets:
                hdrs = [wb[sn].cell(row=4, column=c).value for c in range(2, wb[sn].max_column + 1)]
                assert wb[sn].cell(row=4, column=1).value == "عناوین", \
                    "columnar header missing in %s" % sn
                for h in hdrs:
                    recs_per_sheet[h] = recs_per_sheet.get(h, 0) + 1
            assert len(recs_per_sheet) <= n_rec, \
                "more employee columns than records (%s > %s)" % (len(recs_per_sheet), n_rec)
        else:
            assert not sal_sheets, "salary sheets written without records (got %s)" % sal_sheets
        assert mgr.last_error is None, "backup manager error: %r" % mgr.last_error
    except Exception:
        errors.append("excel backup: %s" % traceback.format_exc(limit=6))

    # 6) closeEvent → final flush path must not crash
    try:
        win.close()
    except Exception:
        errors.append("close: %s" % traceback.format_exc(limit=4))

    print("pages visited: %d | buttons clicked: %d" % (len(win.pages), clicked))
    if slot_errors:
        errors.extend("late-slot-error: %s" % e.strip().splitlines()[-3:] for e in slot_errors)
    if errors:
        print("SMOKE FAILURES (%d):" % len(errors))
        for e in errors:
            print("  " + e.replace("\n", "\n  "))
        sys.exit(1)
    print("SMOKE: ALL CLEAN")


if __name__ == "__main__":
    main()
