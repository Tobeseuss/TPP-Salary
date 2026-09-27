<?php
/**
 * تست رگرسیون 1.4.1 — پوشش ده گزارش کاربر:
 *  ۱) بانک‌ها: درج ستون ناموجود created_at حذف شد (علت «بانک اضافه نمی‌شود»)
 *  ۲) ورود گروهی کارمندان: تشخیص عنوان ستون با نرمال‌سازی تهاجمی + پیام تشخیصی
 *  ۳) تطبیق کارمند با کد ملی «یا» نام و نام خانوادگی (بدون کاربر تکراری)
 *  ۴) ورود گروهی حقوق: ساخت خودکار کارمند + تطبیق با نام/کد ملی/full_name
 *  ۵) فیلد «نام و نام خانوادگی» افزونه‌ای در پروفایل + انتقال عنوان شغلی/خودرو به پروفایل (in_record)
 *  ۶) حذف افزونه: پیش‌فرض حفظ داده‌ها (tpp_salary_delete_data)
 *  ۷) فیش بانکی: یک فرم واحد (سال/ماه/مرکز/بانک + یک دکمه جستجو)
 *  ۸) حذف مفهوم «ذاتاً منفی» + نمایش قرمز اعداد منفی (CSS/JS/PHP/PDF/اکسل)
 *  ۹) «محاسبه با فرمول» مشمول بیمه: اجبار محاسبه در سمت سرور
 *  ۱۰) دارک‌مود: حذف پس‌زمینه سفید inline در فرم کارمند جدید/ویرایشگر فیلد
 */

define( 'SIM_SQLITE_WPDB', 1 );
define( 'SIM_SQLITE_USERS', 1 );
error_reporting( E_ALL );

/*
 * سناریوی ارتقا واقعی: جدول‌ها با ساختار 1.4.0 (بدون in_record) ساخته می‌شوند؛
 * dbDelta شبیه‌ساز ستون in_record را هنگام فعال‌سازی اضافه می‌کند (مثل وردپرس واقعی).
 */
$GLOBALS['sim_pre_activate_cb'] = function () {
        global $wpdb;
        $pdo = $wpdb->pdo;
        $ddl = array(
                'CREATE TABLE wp_tpp_salary_centers ( id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, created_at TEXT NULL )',
                'CREATE TABLE wp_tpp_salary_banks ( id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0 )',
                'CREATE TABLE wp_tpp_salary_fields ( id INTEGER PRIMARY KEY AUTOINCREMENT, field_key TEXT NOT NULL, label TEXT NOT NULL, field_type TEXT NOT NULL DEFAULT "number", default_value TEXT NULL, formula TEXT NULL, options TEXT NULL, is_profile INTEGER NOT NULL DEFAULT 0, is_calculated INTEGER NOT NULL DEFAULT 0, is_negative INTEGER NOT NULL DEFAULT 0, allow_manual INTEGER NOT NULL DEFAULT 1, show_in_payslip INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0, is_system INTEGER NOT NULL DEFAULT 0, is_active INTEGER NOT NULL DEFAULT 1 )',
                'CREATE TABLE wp_tpp_salary_records ( id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, center_id INTEGER NOT NULL, jyear INTEGER NOT NULL, jmonth INTEGER NOT NULL, payload TEXT NULL, gross INTEGER NOT NULL DEFAULT 0, insurable INTEGER NOT NULL DEFAULT 0, insurance_deduct INTEGER NOT NULL DEFAULT 0, other_deductions INTEGER NOT NULL DEFAULT 0, net INTEGER NOT NULL DEFAULT 0, created_by INTEGER NULL, created_at TEXT NULL, updated_at TEXT NULL )',
                'CREATE TABLE wp_tpp_salary_backups ( id INTEGER PRIMARY KEY AUTOINCREMENT, backup_type TEXT NOT NULL, origin TEXT NOT NULL DEFAULT "manual", file_path TEXT NULL, file_size INTEGER NOT NULL DEFAULT 0, created_at TEXT NULL )',
        );
        foreach ( $ddl as $q ) { $pdo->exec( $q ); }
};

require __DIR__ . '/wp_sim_bootstrap.php';

$fail = 0;
function check( $cond, $msg ) {
        global $fail;
        echo ( $cond ? '  PASS: ' : '  FAIL: ' ) . $msg . "\n";
        if ( ! $cond ) { $fail = 1; }
}

global $wpdb;
$plugin_dir = dirname( __DIR__ ) . '/build/tpp_salary';

echo "== 1) بانک‌ها — علت «بانک اضافه نمی‌شود» ==\n";
$banks_src = file_get_contents( $plugin_dir . '/includes/class-tppsalary-banks.php' );
check( strpos( $banks_src, "'created_at'" ) === false, 'add() بانک: دیگر ستون ناموجود created_at درج نمی‌شود' );
check( strpos( $banks_src, 'bankerr' ) !== false, 'خطای درج (نام تکراری) با اعلان مشخص گزارش می‌شود' );
// درج واقعی با همان امضای کد اصلاح‌شده:
$ins = $wpdb->insert( $wpdb->prefix . 'tpp_salary_banks', array( 'name' => 'بانک ملت' ), array( '%s' ) );
check( 1 === (int) $ins && 'بانک ملت' === (string) $wpdb->get_var( "SELECT name FROM {$wpdb->prefix}tpp_salary_banks LIMIT 1" ), 'درج بانک با ساختار اصلاح‌شده واقعاً کار می‌کند' );

echo "== 2) نرمال‌سازی تهاجمی عناوین ستون‌ها ==\n";
$n  = array( 'TppSalary_Import', 'normalize_key' );
$base = call_user_func( $n, 'نام و نام خانوادگی' );
check( '' !== $base, 'عنوان استاندارد نرمال شد: ' . $base );
check( $base === call_user_func( $n, 'نام‌و‌نام‌خانوادگی' ), 'نیم‌فاصله (ZWNJ) خنثی می‌شود' );
check( $base === call_user_func( $n, '«نام و نام خانوادگی»' ), 'گیومه فارسی خنثی می‌شود' );
check( $base === call_user_func( $n, "نام و نام خانوادگی\u{200F}" ), 'نشان جهت RLM خنثی می‌شود' );
check( $base === call_user_func( $n, 'نام و نام خانوادگی' ), 'نویسه ناسازگار/فاصله ویژه خنثی می‌شود' );
check( $base === call_user_func( $n, 'نام و نام خانوادگي' ), 'ي عربی به ی تبدیل می‌شود' );
$kd = call_user_func( $n, 'كد ملي' );
check( $kd === call_user_func( $n, 'کد ملی' ), 'ك/ي عربی در «کد ملی» خنثی می‌شود' );
check( call_user_func( $n, 'کد ۱۲' ) === 'کد12', 'ارقام فارسی به لاتین تبدیل می‌شود' );
check( call_user_func( $n, 'نام-کارمند.' ) === 'نامکارمند', 'علائم نگارشی حذف می‌شوند' );

echo "== 3) فیلدها: in_record و full_name (ارتقای 1.4.0→1.4.1) ==\n";
$fields = tpp_salary_get_fields();
$by_key = array();
foreach ( $fields as $f ) { $by_key[ $f->field_key ] = $f; }
check( isset( $by_key['full_name'] ), 'فیلد «نام و نام خانوادگی» (full_name) بعد از ارتقا seed شده است' );
$in_record_ok = true;
foreach ( array( 'full_name', 'job_title', 'vehicle_type', 'vehicle_plate' ) as $k ) {
        if ( ! isset( $by_key[ $k ] ) || ( isset( $by_key[ $k ]->in_record ) && (int) $by_key[ $k ]->in_record ) ) { $in_record_ok = false; }
}
check( $in_record_ok, 'عنوان شغلی/نوع خودرو/پلاک خودرو/full_name فقط پروفایلی‌اند (in_record=0)' );
$rec_ok = true;
foreach ( array( 'base_salary', 'gross', 'net', 'insurance_group', 'daily_wage' ) as $k ) {
        if ( ! isset( $by_key[ $k ] ) || (int) $by_key[ $k ]->in_record !== 1 ) { $rec_ok = false; }
}
check( $rec_ok, 'فیلدهای فرم ثبت حقوق in_record=1 دارند' );
$neg_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tpp_salary_fields WHERE is_negative = 1" );
check( 0 === $neg_count, 'مفهوم «ذاتاً منفی» حذف شد — هیچ فیلدی is_negative=1 ندارد' );
$prof_keys = array_map( function ( $f ) { return $f->field_key; }, tpp_salary_get_profile_fields() );
check( in_array( 'full_name', $prof_keys, true ), 'full_name در فیلدهای پروفایل (صفحه کاربر) حاضر است' );
$js_fields = TppSalary_Salary_Pages::fields_js();
$js_keys = array_map( function ( $f ) { return $f['key']; }, $js_fields );
check( ! in_array( 'job_title', $js_keys, true ) && ! in_array( 'vehicle_type', $js_keys, true ) && ! in_array( 'full_name', $js_keys, true ), 'fields_js فیلدهای فقط‌پروفایلی را برنمی‌گرداند' );
$rec_headers = TppSalary_Samples::records_headers();
check( ! in_array( 'عنوان شغلی', $rec_headers, true ) && ! in_array( 'نوع خودرو', $rec_headers, true ), 'نمونه رکوردها: ستون عنوان شغلی/خودرو ندارد' );
$emp_headers = TppSalary_Samples::employees_headers();
check( count( array_unique( array_map( 'trim', $emp_headers ) ) ) === count( $emp_headers ), 'نمونه کارمندان: هیچ سرستون تکراری‌ای ندارد' );
check( in_array( 'نام و نام خانوادگی', $emp_headers, true ), 'نمونه کارمندان: ستون نام و نام خانوادگی دارد' );

echo "== 4) ورود گروهی حقوق — ساخت خودکار کارمند + تطبیق نام/کد ملی ==\n";
$now = current_time( 'mysql' );
$wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => 'بومهن', 'created_at' => $now ), array( '%s', '%s' ) );
$c1 = (int) $wpdb->insert_id;
$wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => 'رودهن', 'created_at' => $now ), array( '%s', '%s' ) );
$c2 = (int) $wpdb->insert_id;
// کارمند موجود: علی محمدی
$uid1 = wp_insert_user( array( 'user_login' => '0012345678', 'user_pass' => 'pass1234', 'display_name' => 'علی محمدی', 'role' => 'tpp_salary_employee' ) );
check( ! is_wp_error( $uid1 ) && $uid1 > 0, 'کارمند پایه (علی محمدی) ساخته شد' );
update_user_meta( $uid1, 'tpp_salary_national_id', '0012345678' );

// سطرها با عنوان‌های آشفته: گیومه + نیم‌فاصله + Y عربی در نام
$headers = array( 'سال', 'ماه', 'مرکز', '«نام و نام‌خانوادگی»', 'کد ملی', 'دستمزد روزانه', 'کارکرد (تعداد روز)', 'حقوق ناخالص', 'حقوق مشمول بیمه', 'کسر درصد بیمه', 'حقوق خالص پرداختی' );
$rows = array( $headers );
$rows[] = array( '1405', 'مرداد', 'بومهن', 'علی محمدی', '0012345678', '700000', '31', '—', '—', '—', '—' );
$rows[] = array( '1405', 'مرداد', 'رودهن', 'رضا کریمی', '0091111111', '650000', '30', '—', '—', '—', '—' );
$res = TppSalary_Import::process_records( $rows, false, true );
check( 2 === (int) $res['ok'] && 0 === (int) $res['fail'], 'هر دو سطر با عنوان‌های آشفته پردازش شد (ساخت خودکار فعال)' );
$found = get_users( array( 'meta_key' => 'tpp_salary_national_id', 'meta_value' => '0091111111', 'number' => 1, 'fields' => 'all' ) );
check( ! empty( $found ) && in_array( 'tpp_salary_employee', (array) $found[0]->roles, true ), 'کارمند «رضا کریمی» به‌صورت خودکار ساخته شد (نقش کارمندی)' );
if ( ! empty( $found ) ) {
        $p = tpp_salary_get_profile( $found[0]->ID );
        check( 'رضا کریمی' === (string) ( isset( $p['full_name'] ) ? $p['full_name'] : '' ), 'فیلد full_name کارمند خودکار ذخیره شد' );
        check( in_array( $c2, array_map( 'intval', $p['centers'] ), true ), 'مرکز سطر در پروفایل کارمند خودکار ثبت شد' );
}
$rec1 = tpp_salary_get_record_period( $uid1, $c1, 1405, 5 );
check( $rec1 && (float) $rec1->gross > 0 && (float) $rec1->net > 0, 'رکورد حقوق کارمند موجود ثبت و فرمول‌ها محاسبه شدند' );
// تکرار همان فایل — نباید رکورد/کارمند تکراری بسازد
$res2 = TppSalary_Import::process_records( $rows, false, true );
$users_named = 0;
foreach ( tpp_salary_get_employees() as $u ) {
        if ( TppSalary_Import::normalize_key( $u->display_name ) === TppSalary_Import::normalize_key( 'علی محمدی' ) ) { $users_named++; }
}
check( 1 === $users_named, 'ورود دوباره فایل، کارمند تکراری نمی‌سازد (تطبیق نام)' );
// غیرفعال بودن ساخت خودکار → سطر ناشناس خطا می‌خورد
$rows3 = array( $headers );
$rows3[] = array( '1405', 'مرداد', 'بومهن', 'کارمند ناشناس', '', '700000', '31', '—', '—', '—', '—' );
$res3 = TppSalary_Import::process_records( $rows3, false, false );
check( 0 === (int) $res3['ok'] && 1 === (int) $res3['fail'], 'با خام بودن «ساخت خودکار»، سطر ناشناس رد می‌شود' );
// تطبیق با full_name پروفایل
$p1 = tpp_salary_get_profile( $uid1 );
$p1['full_name'] = 'علی محمدی';
tpp_salary_save_profile( $uid1, $p1 );
$fby = TppSalary_Import::find_employee_by_name( 'علی‌محمدی' );
check( $fby && (int) $fby->ID === (int) $uid1, 'تطبیق با نام و نام خانوادگی پروفایل (بدون فاصله/نیم‌فاصله) کار می‌کند' );

echo "== 5) «محاسبه با فرمول» مشمول بیمه — اجبار محاسبه سمت سرور ==\n";
$raw = array(
        'daily_wage' => '700000', 'work_days' => '31', 'housing' => '100000', 'food' => '50000',
        'base_salary' => '21700000', // فرم زنده JS این مقدار را از قبل محاسبه و پست می‌کند
        'insurable' => '9999999', // مقدار پیش‌پرشده‌ای که با حاصل فرمول فرق دارد — قبلاً باعث رد فرمول می‌شد
        'insurance_rate' => '7', 'other_deductions' => '0', 'other' => '0',
);
$up = TppSalary_Salary_Pages::upsert_record( $uid1, $c1, 1405, 6, $raw, true );
check( ! is_wp_error( $up ), 'upsert_record با حالت فرمولی بدون خطا' );
$rec6 = tpp_salary_get_record_period( $uid1, $c1, 1405, 6 );
$payload = tpp_salary_record_payload( $rec6 );
$expected_ins = 700000 * 31 + 100000 + 50000; // base + housing + food (فرمول سراسری)
check( (float) $payload['insurable'] === (float) $expected_ins, 'مشمول بیمه اجباراً از فرمول محاسبه شد (نه مقدار پیش‌پرشده ' . number_format( (float) $payload['insurable'] ) . ' در برابر ' . number_format( $expected_ins ) . ')' );

echo "== 6) اعداد منفی — قرمز در همه‌جا، بدون مفهوم «ذاتاً منفی» ==\n";
$sp = file_get_contents( $plugin_dir . '/includes/class-tppsalary-salary-pages.php' );
check( strpos( $sp, '(منفی)' ) === false, 'فرم ثبت حقوق: برچسب «(منفی)» حذف شد' );
check( strpos( $sp, "'negative'" ) === false, 'fields_js دیگر کلید negative ندارد' );
check( substr_count( $sp, 'tpp-neg' ) >= 3, 'فرم/فهرست حقوق‌ها: کلاس قرمز tpp-neg اعمال می‌شود' );
$rp = file_get_contents( $plugin_dir . '/includes/class-tppsalary-reports.php' );
check( strpos( $rp, '$f->is_negative' ) === false, 'اکسل: قرمزی فقط بر اساس علامت خود مقدار است' );
check( strpos( $rp, 'SetTextColor( 185, 28, 28 )' ) !== false, 'PDF فیش: مبالغ منفی با رنگ قرمز رندر می‌شوند' );
check( substr_count( $rp, 'tpp-neg' ) >= 2, 'پیوت/فیش بانکی: کلاس قرمز منفی اعمال می‌شود' );
$css = file_get_contents( $plugin_dir . '/admin/css/tpp-salary-admin.css' );
check( strpos( $css, '.tpp-neg' ) !== false && strpos( $css, 'input.tpp-neg' ) !== false, 'CSS: قواعد tpp-neg (متن و کادر قرمز) تعریف شده' );
$js = file_get_contents( $plugin_dir . '/admin/js/tpp-salary-admin.js' );
check( substr_count( $js, "toggleClass('tpp-neg'" ) >= 2, 'JS: کلاس قرمز هم برای محاسبه و هم ورود دستی تنظیم می‌شود' );
$fe = file_get_contents( $plugin_dir . '/includes/class-tppsalary-frontend.php' );
check( strpos( $fe, 'tpp-neg' ) !== false, 'فیش وب پنل کارمند: منفی قرمز است' );
// نشانه منفی در محاسبه دستی — علامت منفی ملاک است:
$valsneg = array( 'other' => '-10000', 'daily_wage' => '700000', 'work_days' => '31', 'housing' => '0', 'food' => '0', 'seniority' => '0', 'marriage' => '0', 'children_count' => '0', 'child_allowance_rate' => '0', 'commute' => '0', 'overtime_hours' => '0', 'overtime_rate' => '0', 'holiday_days' => '0', 'holiday_rate' => '0', 'absence_days' => '0', 'absence_rate' => '0', 'work_deduction' => '0', 'insurable' => '0', 'insurance_rate' => '7', 'other_deductions' => '0', 'base_salary' => '0', 'child_allowance' => '0', 'overtime_pay' => '0', 'holiday_pay' => '0', 'absence_penalty' => '0', 'insurance_deduct' => '0', 'gross' => '0', 'net' => '0' );
$cn = tpp_salary_compute_values( $valsneg, array( 'other' ), null, array() );
check( -10000.0 === (float) $cn['values']['other'], 'ورود دستی -10000 منفی باقی می‌ماند (علامت ملاک است)' );
$valpos = $valsneg; $valpos['other'] = '10000';
$cp = tpp_salary_compute_values( $valpos, array( 'other' ), null, array() );
check( 10000.0 === (float) $cp['values']['other'], 'ورود دستی 10000 مثبت در نظر گرفته می‌شود' );

echo "== 7) دارک‌مود — پس‌زمینه سفید inline حذف شد ==\n";
$emp = file_get_contents( $plugin_dir . '/includes/class-tppsalary-employees.php' );
check( strpos( $emp, 'background:#fff' ) === false, 'فرم «افزودن کارمند جدید»: پس‌زمینه سفید inline حذف شد (tpp-panel)' );
$st = file_get_contents( $plugin_dir . '/includes/class-tppsalary-settings.php' );
check( strpos( $st, 'background:#fff' ) === false, 'ویرایشگر فیلد تنظیمات: پس‌زمینه سفید inline حذف شد (tpp-panel)' );
check( strpos( $sp, "'profile.php' === \$hook || 'user-edit.php' === \$hook" ) !== false, 'استایل افزونه در صفحه پروفایل/ویرایش کاربر هم بارگذاری می‌شود' );

echo "== 8) فیش بانکی — یک فرم واحد ==\n";
check( preg_match( '#period_form\( \$page, \$title, \$records = null, \$with_bank = false \)#u', $rp ) === 1, 'period_form پارامتر بانک دارد' );
$bank_fn = '';
$_rb = strpos( $rp, 'public static function render_bank' );
$_br = strpos( $rp, 'static function bank_rows' );
if ( false !== $_rb && false !== $_br && $_br > $_rb ) { $bank_fn = substr( $rp, $_rb, $_br - $_rb ); }
check( '' !== $bank_fn && strpos( $bank_fn, '<form' ) === false, 'render_bank: فرم جداگانه دوم حذف شد (فرم واحد در period_form)' );
check( strpos( $bank_fn, 'true );' ) !== false && strpos( $bank_fn, 'period_form(' ) !== false, 'render_bank از فرم واحد با انتخاب بانک استفاده می‌کند' );

echo "== 9) حذف افزونه — پیش‌فرض حفظ داده‌ها ==\n";
$un = file_get_contents( $plugin_dir . '/uninstall.php' );
check( strpos( $un, "get_option( 'tpp_salary_delete_data', '0' )" ) !== false, 'uninstall: کلید جدید delete_data با پیش‌فرض «حفظ»' );
check( preg_match( '#if \( \$delete_data \) \{#u', $un ) === 1, 'uninstall: حذف جدول‌ها/متا فقط داخل گارد delete_data است' );
check( strpos( $st, 'name="delete_data"' ) !== false, 'تنظیمات: چک‌باکس «حذف کامل اطلاعات هنگام حذف افزونه» موجود است' );
check( strpos( $st, 'name="keep_data"' ) === false, 'تنظیمات: چک‌باکس قدیمی keep_data حذف شده' );
$ins2 = file_get_contents( $plugin_dir . '/includes/class-tppsalary-install.php' );
check( strpos( $ins2, "delete_option( 'tpp_salary_keep_data' )" ) !== false, 'ارتقا: گزینه قدیمی keep_data حذف می‌شود' );
check( get_option( 'tpp_salary_delete_data', '0' ) === '0' || get_option( 'tpp_salary_delete_data', '0' ) === '1', 'گزینه delete_data مقدار معتبر دارد (پیش‌فرض حفظ)' );

echo "== 10) ورود گروهی کارمندان — پیام تشخیصی و تطبیق نام ==\n";
$imp = file_get_contents( $plugin_dir . '/includes/class-tppsalary-import.php' );
check( strpos( $imp, 'عناوین شناسایی‌شده' ) !== false, 'پیام خطای ستون یافت‌نشده، عناوین خوانده‌شده را نشان می‌دهد' );
check( strpos( $imp, 'find_employee_by_name' ) !== false, 'تطبیق با نام و نام خانوادگی در ورود گروهی کارمندان' );
check( strpos( $imp, 'create_employee_user' ) !== false, 'ساخت کاربر کارمند مشترک بین دو ورود گروهی' );
// فایل نمونه واقعی کارمندان باید با خواننده خود پلاگین سرستون‌های قابل‌تشخیص بدهد
$xlsx_ok = TppSalary_Samples::build_employees();
$data = $xlsx_ok->to_string();
$tmp = sys_get_temp_dir() . '/tpp-emp-sample-' . wp_generate_password( 6, false ) . '.xlsx';
file_put_contents( $tmp, $data );
$read = TppSalary_Xlsx_Reader::read( $tmp );
check( is_array( $read ) && count( $read ) >= 2, 'نمونه کارمندان با خواننده پلاگین باز می‌شود' );
if ( is_array( $read ) ) {
        $hdr = $read[0];
        $norm_hdr = array_map( array( 'TppSalary_Import', 'normalize_key' ), $hdr );
        check( in_array( TppSalary_Import::normalize_key( 'نام و نام خانوادگی' ), $norm_hdr, true ), 'سرستون نام در نمونه واقعی قابل تشخیص است' );
}
unlink( $tmp );

echo "\n" . ( $fail ? 'RESULT: FAIL' : 'RESULT: ALL PASS' ) . "\n";
exit( $fail );
