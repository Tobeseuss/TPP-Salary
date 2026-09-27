/**
 * پلاگین حقوق و دستمزد — موتور محاسبه زنده و ابزارها
 */
(function ($) {
        'use strict';

        /* ---------- پوسته: دارک‌مود پیش‌فرض + کلید تغییر ---------- */
        try {
                var theme = localStorage.getItem('tpp_theme') || 'dark';
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
                        try { localStorage.setItem('tpp_theme', light ? 'light' : 'dark'); } catch (e) {}
                        $(this).html(light ? '🌙' : '☀');
                });
                $('body').append($btn);
        });

        var state = {
                formulas: (typeof TPP !== 'undefined') ? TPP.formulas : {},
                fields: (typeof TPP !== 'undefined') ? TPP.fields : [],
                manual: {}
        };

        /** تبدیل ارقام فارسی/عربی به لاتین و پارس عدد */
        function parseNum(v) {
                if (typeof v !== 'string') { v = String(v); }
                var fa = '۰۱۲۳۴۵۶۷۸۹', ar = '٠١٢٣٤٥٦٧٨٩';
                v = v.replace(/[۰-۹]/g, function (d) { return String(fa.indexOf(d)); });
                v = v.replace(/[٠-٩]/g, function (d) { return String(ar.indexOf(d)); });
                v = v.replace(/[،,\s]/g, '');
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
                        if (/[0-9.،,]/.test(ch)) {
                                var num = '';
                                while (i < len && /[0-9.،,]/.test(formula[i])) {
                                        var c = formula[i];
                                        num += (c === '،' || c === ',') ? '' : c;
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

        /** بازمحاسبه فیلدهای فرمولی غیر دستی */
        function recalc() {
                var values = collect();
                state.fields.forEach(function (f) {
                        if (!f.calculated || !f.formula) { return; }
                        var $inp = $('#fld_' + f.key);
                        if (!$inp.length) { return; }
                        if ($inp.attr('data-manual') === '1') { return; }
                        // فیلد مشمول بیمه فقط در حالت فرمولی محاسبه می‌شود.
                        if (f.key === 'insurable' && !$('input[name="insurable_formula"]').prop('checked')) { return; }
                        var v = evalFormula(f.formula, values);
                        v = v < 0 ? Math.ceil(v) : Math.round(v); // کسورات به سمت صفر
                        $inp.val(fmt(v));
                        values[f.key] = String(v);
                });
                var gross = parseNum($('#fld_gross').val() || '0');
                var net = parseNum($('#fld_net').val() || '0');
                $('#tpp-sum-gross').text(fmtFa(gross));
                $('#tpp-sum-net').text(fmtFa(net));
        }

        $(function () {
                if (!$('#tpp-salary-form').length) { return; }

                TPP.parseNum = parseNum;
                TPP.fmt = fmt;
                TPP.fmtFa = fmtFa;

                // ویرایش دستی فیلد → توقف محاسبه خودکار همان فیلد (مطابق رفتار سمت سرور).
                $(document).on('input change', '.tpp-num', function () {
                        $(this).attr('data-manual', '1');
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
