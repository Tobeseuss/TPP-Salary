/**
 * پلاگین حقوق و دستمزد — حالت آفلاین
 * کش داده در IndexedDB + صف عملیات + همگام‌سازی خودکار + Service Worker + فایل snapshot
 */
(function ($) {
        'use strict';

        if (typeof TPPSALARY_OFFLINE === 'undefined') { return; }

        var CFG = TPPSALARY_OFFLINE;
        var DB_NAME = 'tpp_salary_offline_db';
        var DB_VER = 1;
        var db = null;
        var syncing = false;

        /* ---------- IndexedDB (promise-wrapped) ---------- */
        function idbOpen() {
                if (db) { return Promise.resolve(db); }
                return new Promise(function (resolve, reject) {
                        var rq = indexedDB.open(DB_NAME, DB_VER);
                        rq.onupgradeneeded = function (e) {
                                var d = e.target.result;
                                if (!d.objectStoreNames.contains('kv')) { d.createObjectStore('kv'); }
                                if (!d.objectStoreNames.contains('queue')) { d.createObjectStore('queue', { autoIncrement: true }); }
                        };
                        rq.onsuccess = function () { db = rq.result; resolve(db); };
                        rq.onerror = function () { reject(rq.error); };
                });
        }
        function idbPut(store, key, val) {
                return idbOpen().then(function (d) {
                        return new Promise(function (res, rej) {
                                var tx = d.transaction(store, 'readwrite');
                                tx.objectStore(store).put(val, key);
                                tx.oncomplete = res; tx.onerror = function () { rej(tx.error); };
                        });
                });
        }
        function idbGet(store, key) {
                return idbOpen().then(function (d) {
                        return new Promise(function (res) {
                                var rq = d.transaction(store, 'readonly').objectStore(store).get(key);
                                rq.onsuccess = function () { res(rq.result); };
                                rq.onerror = function () { res(null); };
                        });
                });
        }
        function idbAll(store) {
                return idbOpen().then(function (d) {
                        return new Promise(function (res) {
                                var rq = d.transaction(store, 'readonly').objectStore(store).getAll();
                                rq.onsuccess = function () { res(rq.result || []); };
                                rq.onerror = function () { res([]); };
                        });
                });
        }
        function idbKeys(store) {
                return idbOpen().then(function (d) {
                        return new Promise(function (res) {
                                var rq = d.transaction(store, 'readonly').objectStore(store).getAllKeys();
                                rq.onsuccess = function () { res(rq.result || []); };
                                rq.onerror = function () { res([]); };
                        });
                });
        }
        function idbDelete(store, key) {
                return idbOpen().then(function (d) {
                        return new Promise(function (res) {
                                var tx = d.transaction(store, 'readwrite');
                                tx.objectStore(store).delete(key);
                                tx.oncomplete = res; tx.onerror = res;
                        });
                });
        }
        function idbClear(store) {
                return idbOpen().then(function (d) {
                        return new Promise(function (res) {
                                var tx = d.transaction(store, 'readwrite');
                                tx.objectStore(store).clear();
                                tx.oncomplete = res; tx.onerror = res;
                        });
                });
        }

        /* ---------- وضعیت و رابط ---------- */
        function isOnline() { return navigator.onLine; }

        function setUI() {
                var on = isOnline();
                $('#tpp-salary-offline-dot').attr('class', 'tpp-salary-offline-dot ' + (on ? 'on' : 'off'));
                $('#tpp-salary-offline-label').text(on ? 'آنلاین' : 'آفلاین — تغییرات محلی ذخیره و پس از اتصال همگام می‌شود');
                idbKeys('queue').then(function (keys) {
                        var n = keys.length;
                        if (n > 0) {
                                $('#tpp-pending').text('در انتظار همگام‌سازی: ' + n).show();
                        } else {
                                $('#tpp-pending').hide();
                        }
                });
        }

        function msg(t) { $('#tpp-sync-msg').text(t || ''); }

        /* ---------- دریافت بسته داده ---------- */
        function pullBundle() {
                return $.post(CFG.ajaxUrl, { action: 'tpp_salary_offline_pull', nonce: CFG.nonce })
                        .then(function (r) {
                                if (r && r.success) {
                                        r.data.type = 'tpp_salary_offline_bundle';
                                        return idbPut('kv', 'bundle', r.data).then(function () { return r.data; });
                                }
                                return null;
                        });
        }

        /* ---------- همگام‌سازی صف ---------- */
        function sync() {
                if (syncing || !isOnline()) { return Promise.resolve(); }
                syncing = true;
                return idbAll('queue').then(function (ops) {
                        if (!ops.length) { syncing = false; setUI(); return; }
                        msg('در حال همگام‌سازی ' + ops.length + ' عملیات…');
                        return $.post(CFG.ajaxUrl, {
                                action: 'tpp_salary_offline_sync',
                                nonce: CFG.nonce,
                                ops: JSON.stringify(ops)
                        }).then(function (r) {
                                syncing = false;
                                if (!(r && r.success)) { msg('خطا در همگام‌سازی'); return; }
                                var results = r.data.results || [];
                                var applied = 0, conflict = 0, error = 0;
                                var doneRefs = {};
                                results.forEach(function (x) {
                                        if (x.status === 'applied') { applied++; doneRefs[x.ref] = 1; }
                                        else if (x.status === 'conflict') { conflict++; }
                                        else { error++; }
                                });
                                // عملیات اعمال‌شده از صف حذف می‌شوند (به ترتیب کلیدها).
                                return idbKeys('queue').then(function (keys) {
                                        var chain = Promise.resolve();
                                        keys.forEach(function (k, i) {
                                                if (doneRefs[ops[i] ? ops[i].ref : null]) {
                                                        chain = chain.then(function () { return idbDelete('queue', k); });
                                                }
                                        });
                                        return chain.then(function () {
                                                // کش داده با نسخه جدید سرور به‌روزرسانی می‌شود.
                                                if (r.data.bundle) {
                                                        r.data.bundle.type = 'tpp_salary_offline_bundle';
                                                        return idbPut('kv', 'bundle', r.data.bundle).then(function () {
                                                                msg('همگام شد: ' + applied + ' اعمال' + (conflict ? '، ' + conflict + ' تداخل' : '') + (error ? '، ' + error + ' خطا' : ''));
                                                                setUI();
                                                        });
                                                }
                                                msg('همگام شد: ' + applied);
                                                setUI();
                                        });
                                });
                        }).fail(function () {
                                syncing = false;
                                msg('اتصال برقرار نشد — بعداً دوباره تلاش می‌شود');
                        });
                });
        }

        /* ---------- افزودن به صف ---------- */
        function enqueue(op) {
                return idbPut('queue', 'op-' + op.ref, op).then(function () {
                        setUI();
                        return sync();
                });
        }

        function refId() {
                return 'r' + Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
        }

        /* ---------- رهگیری فرم ثبت حقوق در حالت آفلاین ---------- */
        function bindForms() {
                $(document).on('submit', 'form[action*="tpp_salary_save_salary"]', function (e) {
                        if (isOnline()) { return; }
                        e.preventDefault();
                        var $f = $(this);
                        var values = {};
                        $f.find('input[name^="field["], select[name^="field["], textarea[name^="field["]').each(function () {
                                var m = this.name.match(/^field\[(.+?)\]$/);
                                if (m) { values[m[1]] = $(this).val(); }
                        });
                        var op = {
                                ref: refId(),
                                action: 'upsert',
                                client_updated_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
                                payload: {
                                        user_id: $f.find('input[name="user_id"]').val(),
                                        center_id: $f.find('input[name="center_id"]').val(),
                                        jyear: $f.find('input[name="jyear"]').val(),
                                        jmonth: $f.find('input[name="jmonth"]').val(),
                                        insurable_formula: $f.find('input[name="insurable_formula"]').is(':checked') ? 1 : 0,
                                        values: values
                                }
                        };
                        enqueue(op).then(function () {
                                var $note = $('<div class="tpp-note" style="margin-top:14px"><strong>ذخیره شد (حالت آفلاین).</strong> رکورد در صف همگام‌سازی قرار گرفت و پس از وصل شدن اینترنت به سرور اعمال می‌شود.</div>');
                                $f.hide().after($note);
                                if ($('.tpp-salary-offline-local-list').length) { renderLocalList(); }
                        });
                });

                // حذف رکورد در حالت آفلاین
                $(document).on('click', 'a[href*="tpp_salary_del_salary"]', function (e) {
                        if (isOnline()) { return; }
                        e.preventDefault();
                        var href = this.href;
                        var mId = href.match(/id=(\d+)/);
                        var mNonce = href.match(/_wpnonce=([a-f0-9]+)/);
                        if (!mId) { return; }
                        var op = {
                                ref: refId(),
                                action: 'delete',
                                record_id: mId[1],
                                server_nonce: mNonce ? mNonce[1] : '',
                                client_updated_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
                        };
                        enqueue(op).then(function () {
                                window.alert('حذف در حالت آفلاین ثبت شد و پس از اتصال اعمال می‌شود.');
                        });
                });
        }

        /* ---------- فهرست آفلاین رکوردها (مشاهده + جستجو) ---------- */
        function renderLocalList() {
                var $box = $('#tpp-salary-offline-local-view');
                if (!$box.length) { return; }
                idbGet('kv', 'bundle').then(function (b) {
                        if (!b) {
                                $box.html('<div class="tpp-note">هنوز داده‌ای برای حالت آفلاین کش نشده است — یک بار آنلاین این صفحه را باز کنید.</div>');
                                return;
                        }
                        var recs = b.records || [];
                        var emps = {};
                        (b.employees || []).forEach(function (e) { emps[e.id] = e.name; });
                        var cents = {};
                        (b.centers || []).forEach(function (c) { cents[c.id] = c.name; });
                        var $q = $box.find('.tpp-local-search');
                        var term = ($q.val() || '').toString().trim();
                        var rows = recs.filter(function (r) {
                                if (!term) { return true; }
                                var name = emps[r.user_id] || String(r.user_id);
                                var cent = cents[r.center_id] || String(r.center_id);
                                return (name + ' ' + cent).indexOf(term) !== -1;
                        }).slice(0, 300);
                        var html = '<table class="widefat striped"><thead><tr><th>کارمند</th><th>مرکز</th><th>سال</th><th>ماه</th><th>ناخالص</th><th>خالص</th><th>وضعیت</th></tr></thead><tbody>';
                        if (!rows.length) {
                                html += '<tr><td colspan="7">رکوردی یافت نشد</td></tr>';
                        }
                        rows.forEach(function (r) {
                                html += '<tr><td>' + (emps[r.user_id] || r.user_id) + '</td><td>' + (cents[r.center_id] || r.center_id) +
                                        '</td><td>' + r.jyear + '</td><td>' + r.jmonth +
                                        '</td><td>' + Number(r.gross || 0).toLocaleString('en-US') +
                                        '</td><td>' + Number(r.net || 0).toLocaleString('en-US') +
                                        '</td><td>' + (r._local ? 'محلی' : 'کش‌شده') + '</td></tr>';
                        });
                        html += '</tbody></table>';
                        $box.find('.tpp-local-rows').html(html);
                });
        }

        function mountLocalView() {
                if ($('#tpp-salary-offline-local-view').length) { return; }
                var $v = $('<div id="tpp-salary-offline-local-view" style="margin-top:12px"><h2 style="font-size:15px">داده‌های موجود آفلاین</h2><p><input type="search" class="regular-text tpp-local-search" placeholder="جستجو در داده‌های آفلاین (نام کارمند / مرکز)…"></p><div class="tpp-local-rows"></div></div>');
                $('.tpp-wrap').first().append($v);
                $v.find('.tpp-local-search').on('input', renderLocalList);
                renderLocalList();
        }

        /* ---------- فایل snapshot محلی (خروجی/ورودی فایل روی سیستم کاربر) ---------- */
        function bindSnapshotButtons() {
                var $exp = $('#tpp-snapshot-export');
                if ($exp.length) {
                        $exp.on('click', function () {
                                idbGet('kv', 'bundle').then(function (b) {
                                        return idbAll('queue').then(function (ops) {
                                                var snap = b || { type: 'tpp_salary_offline_snapshot', records: [] };
                                                snap.type = 'tpp_salary_offline_snapshot';
                                                snap.queued_ops = ops;
                                                snap.exported_at = new Date().toISOString();
                                                var blob = new Blob([JSON.stringify(snap)], { type: 'application/json' });
                                                var a = document.createElement('a');
                                                a.href = URL.createObjectURL(blob);
                                                a.download = 'tpp-salary-offline-snapshot-' + new Date().toISOString().slice(0, 10) + '.json';
                                                document.body.appendChild(a); a.click(); a.remove();
                                        });
                                });
                        });
                }
                var $imp = $('#tpp-snapshot-import-file');
                if ($imp.length) {
                        $imp.on('change', function () {
                                var f = this.files[0];
                                if (!f) { return; }
                                var rd = new FileReader();
                                rd.onload = function () {
                                        var snap;
                                        try { snap = JSON.parse(rd.result); } catch (e) { window.alert('فایل JSON نامعتبر است'); return; }
                                        if (!snap || snap.type !== 'tpp_salary_offline_snapshot') { window.alert('این فایل snapshot پلاگین نیست'); return; }
                                        // صف داخل فایل به صف محلی منتقل می‌شود.
                                        var ops = snap.queued_ops || [];
                                        var chain = Promise.resolve();
                                        ops.forEach(function (op) {
                                                chain = chain.then(function () { return idbPut('queue', 'op-' + (op.ref || refId()), op); });
                                        });
                                        chain.then(function () {
                                                window.alert('فایل snapshot بارگذاری شد' + (ops.length ? ' — ' + ops.length + ' عملیات در صف همگام‌سازی قرار گرفت' : ''));
                                                setUI();
                                                if (isOnline()) { sync(); }
                                        });
                                };
                                rd.readAsText(f);
                        });
                }
        }

        /* ---------- Service Worker ---------- */
        function registerSW() {
                if (!('serviceWorker' in navigator)) {
                        $('#tpp-sw-status').text('مرورگر پشتیبانی نمی‌کند');
                        return;
                }
                navigator.serviceWorker.register(CFG.swUrl, { scope: '/' + CFG.scope })
                        .then(function () { $('#tpp-sw-status').text('ثبت شد ✓ — صفحات پلاگین به‌صورت خودکار برای آفلاین ذخیره می‌شوند'); })
                        .catch(function () { $('#tpp-sw-status').text('ثبت نشد (در برخی هاست‌ها محدود است — بقیه قابلیت‌ها مستقل کار می‌کنند)'); });
        }

        /* ---------- راه‌اندازی ---------- */
        $(function () {
                // نوار وضعیت روی همه صفحات پلاگین (صفحه اختصاصی، نوار کامل دارد).
                if ($('.tpp-wrap').length && !$('#tpp-salary-offline-bar').length) {
                        var $bar = $('<div class="tpp-salary-offline-bar" id="tpp-salary-offline-bar">' +
                                '<span class="tpp-salary-offline-status"><span class="tpp-salary-offline-dot" id="tpp-salary-offline-dot"></span><span id="tpp-salary-offline-label">…</span></span>' +
                                '<span class="tpp-pending-badge" id="tpp-pending" style="display:none"></span>' +
                                '<button type="button" class="button button-small" id="tpp-sync-now">همگام‌سازی</button>' +
                                '<a class="button button-small" href="' + CFG.adminUrl + '?page=tpp-salary-offline">مدیریت حالت آفلاین</a>' +
                                '<span class="tpp-sync-msg" id="tpp-sync-msg"></span></div>');
                        $('.tpp-wrap').first().prepend($bar);
                }
                setUI();
                bindForms();
                bindSnapshotButtons();

                if (isOnline()) {
                        pullBundle().then(function () {
                                if ($('#tpp-salary-offline-local-view').length) { renderLocalList(); }
                        });
                        registerSW();
                        if ($('#tpp-pending').length) { sync(); }
                } else {
                        mountLocalView();
                }

                $(window).on('online', function () {
                        setUI();
                        pullBundle();
                        sync();
                });
                $(window).on('offline', function () {
                        setUI();
                        mountLocalView();
                });

                $('#tpp-sync-now').on('click', function () {
                        msg('');
                        sync().then(function () { pullBundle(); });
                });

                // در صفحه آفلاینِ اختصاصی، خروجی فایل محلی هم هست.
                if ($('#tpp-salary-offline-bar').length && !$('#tpp-snapshot-export').length) {
                        $('<button type="button" class="button" id="tpp-snapshot-export" style="margin-right:8px">خروجی فایل محلی (با صف)</button>')
                                .insertAfter('#tpp-sync-now');
                        bindSnapshotButtons();
                }
        });
})(jQuery);
