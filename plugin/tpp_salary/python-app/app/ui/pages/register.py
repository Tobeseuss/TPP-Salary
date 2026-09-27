# -*- coding: utf-8 -*-
"""ثبت حقوق — ویزارد ۳ مرحله‌ای (دوره/مرکز → کارمندان → فیلدها).

شامل دکمه «پر کردن فیلدها بر اساس حقوق گذشته» برای هر کارمند:
کاربر سال/ماه مبدأ را انتخاب می‌کند و مقادیر رکورد گذشته همان کارمند
(اولویت: همان مرکز، سپس آخرین رکورد همان دوره از مرکز دیگر) بارگذاری می‌شود.
"""

from PySide6.QtCore import Qt
from PySide6.QtWidgets import (
    QCheckBox, QComboBox, QDialog, QDialogButtonBox, QFormLayout, QFrame,
    QGridLayout, QHBoxLayout, QLabel, QLineEdit, QMessageBox, QPushButton,
    QScrollArea, QTabWidget, QVBoxLayout, QWidget,
)

from ... import jalali as J

MONTHS = list(range(1, 13))
YEARS = list(range(1399, 1407))


def _num_input(text=""):
    e = QLineEdit(text)
    e.setAlignment(Qt.AlignCenter)
    return e


class PastPeriodDialog(QDialog):
    """انتخاب سال/ماه مبدأ برای «پر کردن بر اساس حقوق گذشته»."""

    def __init__(self, default_year, default_month, parent=None):
        super().__init__(parent)
        self.setWindowTitle("پر کردن فیلدها بر اساس حقوق گذشته")
        self.setModal(True)
        self.setMinimumWidth(360)
        v = QVBoxLayout(self)
        v.setContentsMargins(18, 16, 18, 14)
        v.setSpacing(10)
        note = QLabel("سال و ماهی را انتخاب کنید که فیلدهای ماه جدید بر اساس آن تکمیل شود:")
        note.setWordWrap(True)
        v.addWidget(note)
        form = QFormLayout()
        self.cmb_year = QComboBox()
        self.cmb_year.addItems([J.fa_digits(y) for y in YEARS])
        self.cmb_year.setCurrentIndex(max(0, YEARS.index(default_year) if default_year in YEARS else len(YEARS) - 1))
        self.cmb_month = QComboBox()
        self.cmb_month.addItems([J.month_name(m) for m in MONTHS])
        self.cmb_month.setCurrentIndex(default_month - 1 if 1 <= default_month <= 12 else 0)
        form.addRow("سال:", self.cmb_year)
        form.addRow("ماه:", self.cmb_month)
        v.addLayout(form)
        bb = QDialogButtonBox(QDialogButtonBox.Ok | QDialogButtonBox.Cancel)
        bb.accepted.connect(self.accept)
        bb.rejected.connect(self.reject)
        bb.button(QDialogButtonBox.Ok).setText("اعمال")
        bb.button(QDialogButtonBox.Cancel).setText("انصراف")
        v.addWidget(bb)

    def values(self):
        return YEARS[self.cmb_year.currentIndex()], MONTHS[self.cmb_month.currentIndex()]


class EmployeeForm(QWidget):
    """فرم فیلدهای یک کارمند در مرحله ۳."""

    def __init__(self, win, emp, center_id, jyear, jmonth, on_changed=None):
        super().__init__()
        self.win = win
        self.emp = emp
        self.center_id = center_id
        self.jyear = jyear
        self.jmonth = jmonth
        self.on_changed = on_changed
        self.inputs = {}
        self.chk_formula = None
        self.src_label = None

        v = QVBoxLayout(self)
        v.setContentsMargins(14, 12, 14, 12)
        v.setSpacing(10)

        top = QHBoxLayout()
        cap = QLabel("کارمند: %s" % emp["name"])
        cap.setStyleSheet("font-weight:800; font-size:14px; background:transparent;")
        top.addWidget(cap)
        top.addStretch(1)
        btn_past = QPushButton("پر کردن فیلدها بر اساس حقوق گذشته")
        btn_past.setCursor(Qt.PointingHandCursor)
        btn_past.clicked.connect(self.fill_from_past)
        top.addWidget(btn_past)
        v.addLayout(top)

        fields = win.store.fields()
        grid = QGridLayout()
        grid.setHorizontalSpacing(18)
        grid.setVerticalSpacing(8)
        col = 0
        row = 0
        for f in fields:
            lab = QLabel(J.fa_digits(f["label"]) if win.store.use_fa_digits() else f["label"])
            lab.setStyleSheet("background:transparent; color:#475569;")
            e = _num_input()
            e.setMinimumWidth(130)
            # مقدار اولیه: پروفایل کارمند یا پیش‌فرض فیلد
            profile = emp.get("profile", {}) or {}
            val = profile.get(f["key"], f.get("default", "") or "")
            if str(val) not in ("", None):
                e.setText(str(val))
            if f["calculated"]:
                e.setStyleSheet("color:#0d9488; font-weight:700;")
                e.setToolTip("فیلد محاسباتی — با دکمه «محاسبه» یا ذخیره، از فرمول به‌روز می‌شود؛ ویرایش دستی مجاز است")
            self.inputs[f["key"]] = e
            grid.addWidget(lab, row, col * 2)
            grid.addWidget(e, row, col * 2 + 1)
            col += 1
            if col == 3:
                col = 0
                row += 1
        v.addLayout(grid)

        bottom = QHBoxLayout()
        self.chk_formula = QCheckBox("محاسبه با فرمول (حقوق مشمول بیمه)")
        bottom.addWidget(self.chk_formula)
        btn_calc = QPushButton("محاسبه")
        btn_calc.setCursor(Qt.PointingHandCursor)
        btn_calc.clicked.connect(self.recalc)
        bottom.addWidget(btn_calc)
        bottom.addStretch(1)
        self.src_label = QLabel("")
        self.src_label.setStyleSheet("color:#0d9488; background:transparent; font-weight:600;")
        bottom.addWidget(self.src_label)
        v.addLayout(bottom)

    # ------- پر کردن از گذشته -------

    def fill_from_past(self):
        jy, jm, _ = J.today_jalali()
        # پیش‌فرض هوشمند: ماه قبل از دوره جاری فرم
        src_year, src_month = self.jyear, self.jmonth
        src_month -= 1
        if src_month < 1:
            src_month = 12
            src_year -= 1
        if not (src_year == self.jyear and src_month == self.jmonth):
            src_year, src_month = src_year, src_month
        dlg = PastPeriodDialog(src_year, src_month, self)
        if dlg.exec() != QDialog.Accepted:
            return
        want_year, want_month = dlg.values()

        rec, same_center = self.win.store.past_salary(self.emp["id"], self.center_id, want_year, want_month)
        if not rec:
            self.src_label.setText("✗ رکوردی برای %s یافت نشد" % J.period_label(want_year, want_month))
            return
        from ...api_client import load_json
        payload = load_json(rec.get("payload"), {}) or {}
        manual = payload.get("manual", [])
        for key, e in self.inputs.items():
            if key in ("manual", "insurable_mode"):
                continue
            val = payload.get(key, 0)
            e.setText(str(int(val)) if isinstance(val, float) and val == int(val) else str(val))
        mode = payload.get("insurable_mode", "profile")
        self.chk_formula.setChecked(mode == "formula")
        src_center = self.win.store.center(rec["center_id"])
        center_txt = src_center["name"] if src_center else "—"
        self.src_label.setText("✓ از %s — مرکز: %s%s" % (
            J.period_label(rec["jyear"], rec["jmonth"]), center_txt,
            "" if same_center else " (مرکز فعلی رکورد نداشت)"))
        # مانند TPP.recalc افزونه: بازمحاسبه اجباری فیلدهای محاسباتی با حفظ فلگ‌های دستی مبدأ
        self.recalc(force=True, manual_keys=manual)

    # ------- محاسبه زنده -------

    def collect_values(self):
        vals = {}
        for key, e in self.inputs.items():
            vals[key] = e.text()
        return vals

    def recalc(self, force=False, manual_keys=None):
        """محاسبه زنده — مانند دکمه محاسبه افزونه: فیلدهای محاسباتی از فرمول پر می‌شوند."""
        values, manual = self.win.store.compute_preview(
            self.collect_values(), self.chk_formula.isChecked(),
            manual_keys or [], force_calculated=force)
        for key, e in self.inputs.items():
            v = values.get(key, 0)
            e.setText(str(int(v)) if float(v) == int(v) else str(v))


class RegisterPage(QFrame):

    def __init__(self, win):
        super().__init__()
        self.win = win
        self.step = 1
        self.jyear = 0
        self.jmonth = 0
        self.center_id = 0
        self.selected_ids = []
        self.form = None

        v = QVBoxLayout(self)
        v.setContentsMargins(22, 20, 22, 20)
        v.setSpacing(12)

        title = QLabel("ثبت حقوق")
        title.setObjectName("PageTitle")
        v.addWidget(title)

        self.step_label = QLabel("")
        self.step_label.setObjectName("PageHint")
        v.addWidget(self.step_label)

        self.step1 = self._build_step1()
        self.step2 = self._build_step2()
        self.step3 = self._build_step3()
        v.addWidget(self.step1)
        v.addWidget(self.step2)
        v.addWidget(self.step3)
        v.addStretch(1)

        self.show_step(1)

    # ---------------- مرحله ۱: دوره و مرکز ----------------

    def _build_step1(self):
        w = QWidget()
        v = QVBoxLayout(w)
        v.setContentsMargins(0, 0, 0, 0)
        panel = QFrame()
        panel.setObjectName("Panel")
        form = QFormLayout(panel)
        form.setContentsMargins(18, 16, 18, 16)
        form.setSpacing(12)

        jy, jm, _ = J.today_jalali()
        self.cmb_year = QComboBox()
        self.cmb_year.addItems([J.fa_digits(y) for y in YEARS])
        self.cmb_year.setCurrentIndex(max(0, YEARS.index(jy) if jy in YEARS else len(YEARS) - 1))
        self.cmb_month = QComboBox()
        self.cmb_month.addItems([J.month_name(m) for m in MONTHS])
        self.cmb_month.setCurrentIndex(jm - 1)
        self.cmb_center = QComboBox()
        form.addRow("سال:", self.cmb_year)
        form.addRow("ماه:", self.cmb_month)
        form.addRow("مرکز:", self.cmb_center)
        v.addWidget(panel)

        btns = QHBoxLayout()
        btn_next = QPushButton("مرحله بعد ←")
        btn_next.setObjectName("Primary")
        btn_next.setCursor(Qt.PointingHandCursor)
        btn_next.clicked.connect(self._step1_next)
        btns.addStretch(1)
        btns.addWidget(btn_next)
        v.addLayout(btns)
        return w

    def _step1_next(self):
        if not self.cmb_center.currentData():
            QMessageBox.warning(self, "ثبت حقوق", "ابتدا از صفحه «مراکز و بانک‌ها» یک مرکز اضافه کنید.")
            return
        self.jyear = YEARS[self.cmb_year.currentIndex()]
        self.jmonth = MONTHS[self.cmb_month.currentIndex()]
        self.center_id = int(self.cmb_center.currentData())
        self.show_step(2)

    # ---------------- مرحله ۲: انتخاب کارمندان ----------------

    def _build_step2(self):
        w = QWidget()
        v = QVBoxLayout(w)
        v.setContentsMargins(0, 0, 0, 0)
        v.setSpacing(10)

        bar = QHBoxLayout()
        self.lbl_selected = QLabel("")
        btn_all = QPushButton("انتخاب همه")
        btn_all.setCursor(Qt.PointingHandCursor)
        btn_all.clicked.connect(lambda: self._check_all(True))
        btn_none = QPushButton("هیچ‌کدام")
        btn_none.setCursor(Qt.PointingHandCursor)
        btn_none.clicked.connect(lambda: self._check_all(False))
        bar.addWidget(self.lbl_selected)
        bar.addStretch(1)
        bar.addWidget(btn_all)
        bar.addWidget(btn_none)
        v.addLayout(bar)

        scroll = QScrollArea()
        scroll.setWidgetResizable(True)
        scroll.setFrameShape(QFrame.NoFrame)
        self.emp_list_host = QWidget()
        self.emp_list_v = QVBoxLayout(self.emp_list_host)
        self.emp_list_v.setContentsMargins(0, 0, 0, 0)
        self.emp_list_v.setSpacing(6)
        scroll.setWidget(self.emp_list_host)
        v.addWidget(scroll, 1)

        btns = QHBoxLayout()
        btn_back = QPushButton("→ مرحله قبل")
        btn_back.setCursor(Qt.PointingHandCursor)
        btn_back.clicked.connect(lambda: self.show_step(1))
        btn_next = QPushButton("مرحله بعد ←")
        btn_next.setObjectName("Primary")
        btn_next.setCursor(Qt.PointingHandCursor)
        btn_next.clicked.connect(self._step2_next)
        btns.addWidget(btn_back)
        btns.addStretch(1)
        btns.addWidget(btn_next)
        v.addLayout(btns)
        return w

    def _check_all(self, checked):
        for i in range(self.emp_list_v.count()):
            item = self.emp_list_v.itemAt(i).widget()
            if isinstance(item, QCheckBox):
                item.setChecked(checked)
        self._update_selected()

    def _update_selected(self):
        ids = []
        for i in range(self.emp_list_v.count()):
            item = self.emp_list_v.itemAt(i).widget()
            if isinstance(item, QCheckBox) and item.isChecked():
                ids.append(int(item.property("emp_id")))
        self.selected_ids = ids
        self.lbl_selected.setText("انتخاب‌شده: %s کارمند" % J.fa_digits(len(ids)))

    def _step2_next(self):
        self._update_selected()
        if not self.selected_ids:
            QMessageBox.warning(self, "ثبت حقوق", "حداقل یک کارمند انتخاب کنید.")
            return
        self.show_step(3)

    # ---------------- مرحله ۳: فیلدها ----------------

    def _build_step3(self):
        w = QWidget()
        v = QVBoxLayout(w)
        v.setContentsMargins(0, 0, 0, 0)
        v.setSpacing(10)
        self.tabs = QTabWidget()
        self.tabs.setDocumentMode(True)
        v.addWidget(self.tabs, 1)

        btns = QHBoxLayout()
        btn_back = QPushButton("→ مرحله قبل")
        btn_back.setCursor(Qt.PointingHandCursor)
        btn_back.clicked.connect(lambda: self.show_step(2))
        btn_save = QPushButton("ذخیره همه")
        btn_save.setObjectName("Primary")
        btn_save.setCursor(Qt.PointingHandCursor)
        btn_save.clicked.connect(self._save_all)
        btns.addWidget(btn_back)
        btns.addStretch(1)
        btns.addWidget(btn_save)
        v.addLayout(btns)
        return w

    def _save_all(self):
        saved = 0
        errors = []
        for idx in range(self.tabs.count()):
            ef = self.tabs.widget(idx)
            if not isinstance(ef, EmployeeForm):
                continue
            try:
                self.win.store.upsert_record(
                    ef.emp["id"], ef.center_id, ef.jyear, ef.jmonth,
                    ef.collect_values(), ef.chk_formula.isChecked())
                saved += 1
            except Exception as exc:  # noqa: BLE001
                errors.append("%s: %s" % (ef.emp["name"], str(exc)[:80]))
        msg = "%s فیش ذخیره شد (محلی) — پس از اتصال به سایت همگام می‌شود." % J.fa_digits(saved)
        if errors:
            QMessageBox.warning(self, "ثبت حقوق", msg + "\nخطاها:\n" + "\n".join(errors))
        else:
            QMessageBox.information(self, "ثبت حقوق", msg)
        self.win.update_pending()
        self.win.start_sync()
        self.show_step(1)

    # ---------------- ناوبری ----------------

    def show_step(self, step):
        self.step = step
        self.step1.setVisible(step == 1)
        self.step2.setVisible(step == 2)
        self.step3.setVisible(step == 3)
        if step == 1:
            self.step_label.setText("مرحله ۱ از ۳ — سال، ماه و مرکز را انتخاب کنید")
            self._load_centers()
        elif step == 2:
            self.step_label.setText("مرحله ۲ از ۳ — کارمندان %s (%s) را انتخاب کنید" % (
                self._center_name(), J.period_label(self.jyear, self.jmonth)))
            self._load_employees()
        else:
            self.step_label.setText("مرحله ۳ از ۳ — فیلدهای حقوق هر کارمند را تکمیل کنید")
            self._load_forms()

    def _center_name(self):
        c = self.win.store.center(self.center_id)
        return c["name"] if c else "—"

    def _load_centers(self):
        self.cmb_year.blockSignals(True)
        self.cmb_month.blockSignals(True)
        self.cmb_center.blockSignals(True)
        self.cmb_center.clear()
        for c in self.win.store.centers():
            self.cmb_center.addItem(c["name"], c["id"])
        self.cmb_center.blockSignals(False)
        self.cmb_month.blockSignals(False)
        self.cmb_year.blockSignals(False)

    def _load_employees(self):
        while self.emp_list_v.count():
            item = self.emp_list_v.takeAt(0)
            if item.widget():
                item.widget().deleteLater()
        emps = self.win.store.employees(center_id=self.center_id, include_terminated=False)
        if not emps:
            note = QLabel("کارمند فعالی برای این مرکز یافت نشد — از صفحه «کارمندان» اضافه کنید یا مرکز را در پروفایل کارمند علامت بزنید.")
            note.setWordWrap(True)
            self.emp_list_v.addWidget(note)
        for e in emps:
            cb = QCheckBox(e["name"])
            cb.setProperty("emp_id", e["id"])
            cb.stateChanged.connect(lambda *_: self._update_selected())
            self.emp_list_v.addWidget(cb)
        self.emp_list_v.addStretch(1)
        self._update_selected()

    def _load_forms(self):
        self.tabs.clear()
        for eid in self.selected_ids:
            emp = self.win.store.employee(eid)
            if not emp:
                continue
            ef = EmployeeForm(self.win, emp, self.center_id, self.jyear, self.jmonth)
            self.tabs.addTab(ef, emp["name"])

    def open_edit(self, user_id, center_id, jyear, jmonth):
        """باز کردن مرحله ۳ برای ویرایش یک رکورد موجود (از لیست حقوق)."""
        self.jyear = int(jyear)
        self.jmonth = int(jmonth)
        self.center_id = int(center_id)
        self.selected_ids = [int(user_id)]
        self.show_step(3)
        self.win.show_page("register")

    def refresh(self):
        if self.step == 1:
            self._load_centers()
