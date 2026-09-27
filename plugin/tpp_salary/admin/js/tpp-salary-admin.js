/**
 * پلاگین حقوق و دستمزد — موتور محاسبه زنده و ابزارها
 */
(function ($) {
        'use strict';

        /* ---------- پوسته: دارک‌مود پیش‌فرض + کلید تغییر ---------- */
        try {
                var theme = localStorage.getItem('tpp_salary_theme') || 'dark';
                document.documentElement.classList.add('tpp-page');
                document.documentElement.setAttribute('data-tpp-theme', theme);
                if (theme === 'light') { document.documentElement.classList.add('tpp-theme-light'); }
        } catch (e) {
                document.documentElement.classList.add('tpp-page');
        }
        $(function () {
                // اعمال کلاس روی body (CSS بر اساس body.tpp-page است).
                $('body').addClass('tpp-page');
                if (document.documentElement.getAttribute('data-tpp-theme') === 'light') {
                        $('body').addClass('tpp-theme-light');
                }
                var $btn = $('<button/>', {
                        type: 'button',
                        id: 'tpp-theme-toggle',
                        title: 'تغییر پوسته روشن/تاریک',
                        html: function () { return $('body').hasClass('tpp-theme-light') ? '🌙' : '☀'; }
                });
                $btn.on('click', function () {
                        var light = !$('body').hasClass('tpp-theme-light');
                        $('body').toggleClass('tpp-theme-light', light);
                        try { localStorage.setItem('tpp_salary_theme', light ? 'light' : 'dark'); } catch (e) {}
                        $(this).html(light ? '🌙' : '☀');
                });
                $('body').append($btn);
        });

        /*
         * نکته بحرانی: آبجکت موکال‌شده با wp_localize_script نامش TPP است
         * (در salary-pages::assets). پیش‌تر اینجا TPPSALARY خوانده می‌شد که
         * تعریف نمی‌شد → فیلدها/فرمول‌ها همیشه خالی می‌ماندند و محاسبات زنده
         * کار نمی‌کرد؛ ضمناً انتساب‌های بعدی ReferenceError می‌دادند و همهٔ
         * بایندهای رویداد از کار می‌افتادند.
         */
        var state = {
                formulas: (typeof TPP !== 'undefined') ? (TPP.formulas || {}) : {},
                fields: (typeof TPP !== 'undefined') ? (TPP.fields || []) : [],
                manual: {}
        };

        /* ابزارها بلافاصله (بدون انتظار برای ready) در دسترس اسکریپت درون‌صفحه باشد
         * — دیالوگ «سایر» در بدنه صفحه از TPP.parseNum استفاده می‌کند. */
        if (typeof TPP !== 'undefined') {
                TPP.parseNum = parseNum;
                TPP.fmt = fmt;
                TPP.fmtFa = fmtFa;
                /* نسخه 1.6.3: بازمحاسبه برای دکمه «پر کردن فیلدها بر اساس حقوق گذشته» */
                TPP.recalc = recalc;
        }

        /** تبدیل ارقام فارسی/عربی به لاتین و پارس عدد */
        function parseNum(v) {
                if (typeof v !== 'string') { v = String(v); }
                var fa = '۰۱۲۳۴۵۶۷۸۹', ar = '٠١٢٣٤٥٦٧٨٩';
                v = v.replace(/[۰-۹]/g, function (d) { return String(fa.indexOf(d)); });
                v = v.replace(/[٠-٩]/g, function (d) { return String(ar.indexOf(d)); });
                v = v.replace(/[،٬,\s]/g, '');
                var n = parseFloat(v);
                return isNaN(n) ? 0 : n;
        }

        /** قالب‌بندی عدد با جداکننده */
        function fmt(n) {
                n = Math.round(n);
                var s = Math.abs(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                return (n < 0 ? '-' : '') + s;
        }

        function fmtFa(n) {
                var fa = '۰۱۲۳۴۵۶۷۸۹';
                return fmt(n).replace(/\d/g, function (d) { return fa[+d]; });
        }

        /** موتور فرمول امن — توکن فیلد {key} و عملگرها + - * / ( ) */
        function evalFormula(formula, values) {
                if (!formula) { return 0; }
                // توکن‌ها را با مقدار جایگزین می‌کنیم سپس با موتور RPN امن محاسبه می‌کنیم.
                var tokens = tokenize(formula, values);
                if (tokens === null) { return 0; }
                var rpn = shuntingYard(tokens);
                if (!rpn) { return 0; }
                return evalRPN(rpn);
        }

        function tokenize(formula, values) {
                var tokens = [], i = 0, len = formula.length;
                while (i < len) {
                        var ch = formula[i];
                        if (ch === ' ' || ch === '\t' || ch === '\n') { i++; continue; }
                        if (ch === '{') {
                                var end = formula.indexOf('}', i);
                                if (end === -1) { return null; }
                                var key = formula.slice(i + 1, end).trim();
                                tokens.push({ t: 'n', v: parseNum(values[key] !== undefined ? values[key] : 0) });
                                i = end + 1;
                                continue;
                        }
                        if (/[0-9.،٬,]/.test(ch)) {
                                var num = '';
                                while (i < len && /[0-9.،٬,]/.test(formula[i])) {
                                        var c = formula[i];
                                        num += (c === '،' || c === '٬' || c === ',') ? '' : c;
                                        i++;
                                }
                                var n = parseFloat(num);
                                if (isNaN(n)) { return null; }
                                tokens.push({ t: 'n', v: n });
                                continue;
                        }
                        if ('+-*/×÷()'.indexOf(ch) !== -1) {
                                var op = ch === '×' ? '*' : (ch === '÷' ? '/' : ch);
                                // یونری منفی: تبدیل به (0 - x)
                                var prev = tokens[tokens.length - 1];
                                if (op === '-' && (!prev || (prev.t === 'o' && prev.v !== ')'))) {
                                        tokens.push({ t: 'n', v: 0 });
                                }
                                tokens.push({ t: 'o', v: op });
                                i++;
                                continue;
                        }
                        return null;
                }
                return tokens;
        }

        function shuntingYard(tokens) {
                var out = [], stack = [], prec = { '+': 1, '-': 1, '*': 2, '/': 2 };
                for (var i = 0; i < tokens.length; i++) {
                        var tk = tokens[i];
                        if (tk.t === 'n') { out.push(tk); continue; }
                        var op = tk.v;
                        if (op === '(') { stack.push(op); continue; }
                        if (op === ')') {
                                while (stack.length && stack[stack.length - 1] !== '(') { out.push({ t: 'o', v: stack.pop() }); }
                                if (!stack.length) { return null; }
                                stack.pop();
                                continue;
                        }
                        while (stack.length && stack[stack.length - 1] !== '(' && prec[stack[stack.length - 1]] >= prec[op]) {
                                out.push({ t: 'o', v: stack.pop() });
                        }
                        stack.push(op);
                }
                while (stack.length) {
                        var op2 = stack.pop();
                        if (op2 === '(') { return null; }
                        out.push({ t: 'o', v: op2 });
                }
                return out;
        }

        function evalRPN(rpn) {
                var stack = [];
                for (var i = 0; i < rpn.length; i++) {
                        var tk = rpn[i];
                        if (tk.t === 'n') { stack.push(tk.v); continue; }
                        if (stack.length < 2) { return 0; }
                        var b = stack.pop(), a = stack.pop();
                        switch (tk.v) {
                                case '+': stack.push(a + b); break;
                                case '-': stack.push(a - b); break;
                                case '*': stack.push(a * b); break;
                                case '/': stack.push(b === 0 ? 0 : a / b); break;
                                default: return 0;
                        }
                }
                return stack.length === 1 ? stack[0] : 0;
        }

        /** جمع‌آوری مقادیر فعلی فرم */
        function collect() {
                var values = {};
                $('.tpp-num').each(function () {
                        values[$(this).data('key')] = $(this).val();
                });
                return values;
        }

        /* نسخه 1.7.3 — کلیدهای منبع فرمول (توکن‌های {key}) */
        function formulaSources(formula) {
                var keys = [];
                String(formula || '').replace(/\{([a-zA-Z0-9_]+)\}/g, function (_, k) { keys.push(k); });
                return keys;
        }

        /*
         * بازمحاسبه فیلدهای فرمولی — نسخه 1.7.3 (رفع باگ درخواست کاربر)
         *
         * رفتار قبلی: فیلدی که یک‌بار دستی پر می‌شد برای همیشه از محاسبه خارج
         * می‌شد؛ در نتیجه مثلاً «خالص پرداختی» با تغییر «سایر» و بقیه فیلدها
         * ثابت می‌ماند.
         *
         * رفتار جدید: فیلد دستی فقط تا وقتی معتبر است که «منبع‌های فرمولش»
         * تغییر نکرده باشند؛ به‌محض تغییر هر منبع (مستقیم یا زنجیره‌ای)،
         * فیلد دوباره خودکار محاسبه می‌شود. بقیه فیلدهای دستی دست‌نخورده می‌مانند.
         */
        function recalc() {
                var collected = collect();           // وضعیت فرم پیش از تغییرات این دور
                var values = collected;
                var changed = {};                    // منبع‌های کثیف
                if (state.last) {
                        for (var k in values) {
                                if (Object.prototype.hasOwnProperty.call(values, k) && state.last[k] !== undefined && state.last[k] !== values[k]) {
                                        changed[k] = true;
                                }
                        }
                }
                /* دو گذر: انتشار زنجیره‌ای وابستگی‌ها (مثلاً کارکرد ← ناخالص ← خالص) */
                for (var pass = 0; pass < 2; pass++) {
                        state.fields.forEach(function (f) {
                                if (!f.calculated || !f.formula) { return; }
                                var $inp = $('#fld_' + f.key);
                                if (!$inp.length) { return; }
                                // فیلد مشمول بیمه فقط در حالت فرمولی محاسبه می‌شود.
                                if (f.key === 'insurable' && !$('input[name="insurable_formula"]').prop('checked')) { return; }
                                var depChanged = formulaSources(f.formula).some(function (src) { return changed[src]; });
                                if ($inp.attr('data-manual') === '1' && !depChanged) { return; } // دستی معتبر — دست نمی‌زنیم
                                var v = evalFormula(f.formula, values);
                                v = v < 0 ? Math.ceil(v) : Math.round(v); // کسورات به سمت صفر
                                if (parseNum(collected[f.key] === undefined ? '' : collected[f.key]) !== v) {
                                        changed[f.key] = true; // برای انتشار به وابسته‌های دستی در گذر بعد
                                }
                                $inp.val(fmt(v));
                                /* نسخه 1.4.1: عدد منفی قرمز نمایش داده می‌شود. */
                                $inp.toggleClass('tpp-neg', v < 0);
                                values[f.key] = String(v);
                        });
                }
                state.last = values;
                var gross = parseNum($('#fld_gross').val() || '0');
                var net = parseNum($('#fld_net').val() || '0');
                $('#tpp-sum-gross').text(fmtFa(gross)).toggleClass('tpp-neg', gross < 0);
                $('#tpp-sum-net').text(fmtFa(net)).toggleClass('tpp-neg', net < 0);
        }

        $(function () {
                if (!$('#tpp-salary-form').length) { return; }

                if (typeof TPP === 'undefined') { window.TPP = {}; }
                TPP.parseNum = parseNum;
                TPP.fmt = fmt;
                TPP.fmtFa = fmtFa;
                TPP.recalc = recalc; // نسخه 1.6.3

                // ویرایش دستی فیلد → توقف محاسبه خودکار همان فیلد (مطابق رفتار سمت سرور).
                $(document).on('input change', '.tpp-num', function () {
                        $(this).attr('data-manual', '1');
                        /* نسخه 1.4.1: علامت منفیِ مقدار دستی هم قرمز نمایش داده می‌شود. */
                        $(this).toggleClass('tpp-neg', parseNum($(this).val()) < 0);
                        recalc();
                });

                // فیلد فرمولی: با تغییر منابع، اگر دستی نباشد بازمحاسبه می‌شود.
                $(document).on('change', 'input[name="insurable_formula"]', function () {
                        $('#fld_insurable').attr('data-manual', this.checked ? '0' : '1');
                        recalc();
                });

                recalc();
        });
})(jQuery);
