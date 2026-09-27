<?php
/**
 * تست رگرسیون 1.4.0 — پوشش شش گزارش کاربر:
 *  ۱) موتور فرمول JS: آبجکت موکال‌شده TPP (نه TPPSALARY) + فرمول‌های سراسری تنظیمات
 *  ۲) دیالوگ سایر: enqueue شدن jquery-ui-dialog و wp-jquery-ui-dialog
 *  ۳) دارک‌مود: color-scheme و پوشش select option / ui-dialog در CSS
 *  ۴) styles.xml: ترتیب عناصر <font> + صفت val — علت «Removed Part /xl/styles.xml»
 *  ۵) بازگردانی بکاپ: tmp_name بدون wp_unslash + apply_restore قابل‌تست
 *  ۶) ورود گروهی کارمندان: tmp_name خام + ورود گروهی «حقوق» (قابلیت جدید)
 */

define( 'SIM_SQLITE_WPDB', 1 );
define( 'SIM_SQLITE_USERS', 1 );
error_reporting( E_ALL );

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

echo "== 1) فرمول‌ها: آبجکت JS و فرمول‌های سراسری ==\n";
$js = file_get_contents( $plugin_dir . '/admin/js/tpp-salary-admin.js' );
check( strpos( $js, 'typeof TPPSALARY' ) === false && strpos( $js, 'TPPSALARY.parseNum' ) === false && strpos( $js, 'TPPSALARY.fmt' ) === false, 'admin JS دیگر به TPPSALARY (تعریف‌نشده) ارجاع عملیاتی ندارد' );
check( strpos( $js, "typeof TPP !== 'undefined'" ) !== false, 'admin JS از آبجکت موکال‌شده TPP استفاده می‌کند' );
$sp = file_get_contents( $plugin_dir . '/includes/class-tppsalary-salary-pages.php' );
check( preg_match( "#wp_localize_script\(\s*'tpp-salary-admin',\s*'TPP'#", $sp ) === 1, 'نام آبجکت موکال‌شده TPP است (مطابق JS)' );
check( strpos( $sp, 'wp_enqueue_script( \'jquery-ui-dialog\' )' ) !== false && strpos( $sp, 'wp_enqueue_style( \'wp-jquery-ui-dialog\' )' ) !== false, 'دیالوگ سایر: jquery-ui-dialog + استایل وردپرس enqueue می‌شوند' );
check( strpos( $sp, "dialogClass: 'tpp-dialog wp-dialog'" ) !== false, 'دیالوگ سایر: dialogClass تیره تنظیم شده' );

// فرمول سراسری settings باید در compute_values و fields_js اعمال شود.
$check_settings = tpp_salary_get_settings();
check( ! empty( $check_settings['formulas']['gross'] ) && ! empty( $check_settings['formulas']['net'] ) && ! empty( $check_settings['formulas']['insurable'] ), 'فرمول‌های سراسری gross/insurable/net در تنظیمات موجودند' );
$fields_db = tpp_salary_get_fields();
$gross_field = null;
foreach ( $fields_db as $f ) { if ( 'gross' === $f->field_key ) { $gross_field = $f; break; } }
check( $gross_field && '' === (string) $gross_field->formula, 'فیلد gross در دیتابیس فرمول ذخیره‌شده ندارد (پس fallback تنظیمات ضروری است)' );
$fields_js = TppSalary_Salary_Pages::fields_js();
$gross_js = null;
foreach ( $fields_js as $f ) { if ( 'gross' === $f['key'] ) { $gross_js = $f; break; } }
check( $gross_js && '' !== $gross_js['formula'], 'fields_js فرمول سراسری را به gross تزریق می‌کند (JS هم می‌تواند محاسبه کند)' );

// محاسبه سمت سرور با فرمول سراسری — همان ساختاری که upsert_record می‌سازد:
// همه کلیدها صفر + فهرست force (مثل سلول‌های «—» در ورود گروهی).
$values = array( 'daily_wage' => 700000, 'work_days' => 31, 'housing' => 100000, 'food' => 50000, 'insurance_rate' => 7, 'insurable' => 0, 'insurance_deduct' => 0, 'net' => 0, 'base_salary' => 0, 'children_count' => 0, 'child_allowance' => 0, 'child_allowance_rate' => 0, 'commute' => 0, 'overtime_hours' => 0, 'overtime_pay' => 0, 'overtime_rate' => 0, 'holiday_days' => 0, 'holiday_pay' => 0, 'holiday_rate' => 0, 'absence_days' => 0, 'absence_penalty' => 0, 'absence_rate' => 0, 'seniority' => 0, 'marriage' => 0, 'work_deduction' => 0, 'other_deductions' => 0, 'other' => 0, 'gross' => 0 );
$force_all = array( 'base_salary', 'child_allowance', 'overtime_pay', 'holiday_pay', 'absence_penalty', 'gross', 'insurable', 'insurance_deduct', 'net' );
$res = tpp_salary_compute_values( $values, array(), null, $force_all );
check( 21700000.0 === (float) $res['values']['base_salary'] && 21850000.0 === (float) $res['values']['gross'], 'base_salary و gross از فرمول محاسبه شدند (۲۱٫۷۰۰٫۰۰۰ و ۲۱٫۸۵۰٫۰۰۰)' );
check( 21850000.0 === (float) $res['values']['insurable'], 'insurable از فرمول سراسری محاسبه شد' );
check( -1529500.0 === (float) $res['values']['insurance_deduct'], 'insurance_deduct = ۷٪ مشمول بیمه (منفی)' );
check( 20320500.0 === (float) $res['values']['net'], 'net از فرمول سراسری محاسبه شد' );

echo "== 2) دارک‌مود CSS ==\n";
$css = file_get_contents( $plugin_dir . '/admin/css/tpp-salary-admin.css' );
check( strpos( $css, 'color-scheme: dark' ) !== false, 'color-scheme: dark تنظیم شده (پاپ‌آپ selectها تیره می‌شوند)' );
check( strpos( $css, 'select option' ) !== false, 'استایل صریح select option (ضد سفید-روی-سفید)' );
check( strpos( $css, 'input[type="file"]' ) !== false, 'استایل ورودی file' );
check( strpos( $css, '.ui-dialog.wp-dialog' ) !== false, 'پوشش دیالوگ jQuery UI در پوسته تیره' );

echo "== 3) styles.xml — ترتیب <font> و صفت val ==\n";
$x = new TppSalary_Xlsx_Writer();
$x->add_sheet( 'تست' );
$x->set( 1, 1, 'عنوان', 'header' );
$x->set( 2, 1, 'عدد', 'num' );
$data = $x->to_string();
check( ! is_wp_error( $data ), 'تولید فایل تست بدون خطا' );
$tmpzip = sys_get_temp_dir() . '/tpp-test-styles-' . wp_generate_password( 8, false ) . '.xlsx';
file_put_contents( $tmpzip, $data );
$zip = new ZipArchive();
$zip->open( $tmpzip );
$styles_xml = $zip->getFromName( 'xl/styles.xml' );
$zip->close();
check( $styles_xml && false !== strpos( $styles_xml, '<name val="Vazirmatn"/>' ), '<name> دارای صفت الزامی val است' );
check( preg_match( '#<font>(<b/>)?<i?/><sz val="[0-9.]+"/><color rgb="FF[0-9A-F]{6}"/><name val="Vazirmatn"/></font>#u', $styles_xml ) === 1 || preg_match( '#<font><b/><sz val="[0-9.]+"/>#u', $styles_xml ) === 1, 'ترتیب عناصر <font>: b/i قبل از sz/color/name' );
preg_match_all( '#<font>(.*?)</font>#u', $styles_xml, $fm );
$order_ok = true;
foreach ( $fm[1] as $font_inner ) {
        $pos_b    = strpos( $font_inner, '<b/>' );
        $pos_sz   = strpos( $font_inner, '<sz ' );
        $pos_name = strpos( $font_inner, '<name ' );
        if ( false !== $pos_b && false !== $pos_sz && $pos_b > $pos_sz ) { $order_ok = false; }
        if ( false !== $pos_name && ( false !== $pos_sz && $pos_name < $pos_sz ) ) { $order_ok = false; }
}
check( $order_ok, 'همه <font>ها ترتیب اسکیمایی دارند' );
unlink( $tmpzip );

echo "== 4) بازگردانی بکاپ — tmp_name خام و apply_restore ==\n";
$imp_src = file_get_contents( $plugin_dir . '/includes/class-tppsalary-import.php' );
$bak_src = file_get_contents( $plugin_dir . '/includes/class-tppsalary-backup.php' );
check( strpos( $imp_src, "wp_unslash( \$_FILES['import_file']['tmp_name']" ) === false, 'ورود گروهی کارمندان: tmp_name از wp_unslash عبور نمی‌کند (باگ ویندوز)' );
check( strpos( $bak_src, "wp_unslash( \$_FILES['restore_file']['tmp_name']" ) === false, 'بازگردانی بکاپ: tmp_name از wp_unslash عبور نمی‌کند (باگ ویندوز)' );
check( strpos( $imp_src, 'is_uploaded_file' ) !== false && strpos( $bak_src, 'is_uploaded_file' ) !== false, 'اعتبارسنجی is_uploaded_file در هر دو مسیر آپلود' );

// داده پایه: دو مرکز و دو کارمند.
$now = current_time( 'mysql' );
$wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => 'بومهن', 'created_at' => $now ), array( '%s', '%s' ) );
$center_id = (int) $wpdb->insert_id;
$wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => 'رودهن', 'created_at' => $now ), array( '%s', '%s' ) );
$center_id2 = (int) $wpdb->insert_id;
check( $center_id > 0 && $center_id2 > 0, 'ایجاد دو مرکز' );
$uid1 = wp_insert_user( array( 'user_login' => '0012345678', 'user_pass' => 'pass1234', 'display_name' => 'اسماعیل کمال آبادی', 'role' => 'tpp_salary_employee' ) );
$uid2 = wp_insert_user( array( 'user_login' => '0098765432', 'user_pass' => 'pass1234', 'display_name' => 'محمد غلامی', 'role' => 'tpp_salary_employee' ) );
check( (int) $uid1 > 0 && (int) $uid2 > 0, 'ایجاد دو کارمند' );
tpp_salary_save_profile( $uid1, array( 'daily_wage' => 700000, 'work_days' => 31, 'housing' => 100000, 'food' => 50000, 'insurance_rate' => 7, 'insurable_default' => 0, 'centers' => array( $center_id ) ) );

$bak_path = TppSalary_Backup::make( 'json', 'manual' );
check( is_string( $bak_path ) && file_exists( $bak_path ), 'ساخت بکاپ JSON' );
$bak_data = json_decode( (string) file_get_contents( $bak_path ), true );
check( is_array( $bak_data ) && ! empty( $bak_data['version'] ), 'بکاپ JSON ساختار معتبر دارد' );
$recs_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tpp_salary_records" );
$wpdb->query( "DELETE FROM {$wpdb->prefix}tpp_salary_records" );
$applied = TppSalary_Backup::apply_restore( $bak_data );
check( true === $applied, 'apply_restore موفق' );
$recs_after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tpp_salary_records" );
check( $recs_after === $recs_before, "رکوردها بازگردانی شدند ({$recs_before} → {$recs_after})" );
// سطر نامعتبر → WP_Error و داده دست‌نخورده.
$bak_data['centers'][] = array( 'id' => 999, 'name' => 'x', 'evil_col' => 'boom' );
$cnt_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tpp_salary_centers" );
$bad = TppSalary_Backup::apply_restore( $bak_data );
check( is_wp_error( $bad ), 'ستون ناشناخته → بازگردانی رد می‌شود' );
$cnt_after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tpp_salary_centers" );
check( $cnt_after === $cnt_before, 'داده فعلی پس از رد بازگردانی دست‌نخورده ماند' );

echo "== 5) ورود گروهی حقوق — قابلیت جدید ==\n";
check( strpos( $imp_src, 'admin_post_tpp_salary_import_records' ) !== false, 'هوک handler ورود گروهی حقوق ثبت می‌شود' );
check( strpos( $imp_src, 'tpp-salary-import-records' ) !== false, 'صفحه منوی «ورود گروهی حقوق» وجود دارد' );
check( TppSalary_Import::parse_month( 'مرداد' ) === 5 && TppSalary_Import::parse_month( '۵' ) === 5 && TppSalary_Import::parse_month( '12' ) === 12 && TppSalary_Import::parse_month( 'فروردین ماه' ) === 1 && TppSalary_Import::parse_month( 'نامعتبر' ) === 0 && TppSalary_Import::parse_month( '' ) === 0, 'parse_month: نام ماه، عدد، ارقام فارسی، پسوند «ماه» و نامعتبر' );

// ساخت فایل نمونه رکوردها و خواندن آن با خواننده داخلی.
$sx = TppSalary_Samples::build_records();
$sdata = $sx->to_string();
check( ! is_wp_error( $sdata ) && strlen( $sdata ) > 500, 'فایل نمونه رکوردها ساخته شد' );
$spath = sys_get_temp_dir() . '/tpp-records-sample-' . wp_generate_password( 6, false ) . '.xlsx';
file_put_contents( $spath, $sdata );
$rows = TppSalary_Xlsx_Reader::read( $spath );
check( ! is_wp_error( $rows ) && count( $rows ) >= 3, 'خواننده داخلی فایل نمونه را می‌خواند (' . ( is_wp_error( $rows ) ? '' : count( $rows ) ) . ' سطر)' );

// مقادیر صریح برای سطر اول (علی): حق پایه قابل محاسبه از دستمزد و روزها.
$headers_rows = $rows[0];
$col_of = array();
foreach ( $headers_rows as $ci => $h ) { $col_of[ TppSalary_Import::normalize_key( $h ) ] = $ci; }
$set_cell = function ( &$row, $label, $val ) use ( $col_of ) {
        $nk = TppSalary_Import::normalize_key( $label );
        if ( isset( $col_of[ $nk ] ) ) { $row[ $col_of[ $nk ] ] = $val; }
};
$row1 = $rows[1];
$set_cell( $row1, 'دستمزد روزانه مرجع', '۷۰۰٬۰۰۰' );
$set_cell( $row1, 'کارکرد (تعداد روز)', '31' );
$set_cell( $row1, 'حق مسکن', '100000' );
$set_cell( $row1, 'حق بن', '۵۰۰۰۰' );
$set_cell( $row1, 'نرخ درصد بیمه', '7' );
$rows[1] = $row1;

// اجرای آزمایشی — نباید رکوردی بنویسد.
$dry = TppSalary_Import::process_records( $rows, true );
check( is_array( $dry ) && 2 === $dry['ok'] && 0 === $dry['fail'], 'اجرای آزمایشی: ۲ سطر معتبر، ۰ خطا' );
$cnt_dry = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tpp_salary_records" );
check( 0 === $cnt_dry, 'اجرای آزمایشی هیچ رکوردی ننوشت' );

// ورود واقعی.
$real = TppSalary_Import::process_records( $rows, false );
check( 2 === $real['ok'] && 0 === $real['fail'], 'ورود واقعی: ۲ سطر موفق' );
$rec1 = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tpp_salary_records WHERE user_id = %d AND jyear = 1405 AND jmonth = 5", $uid1 ) );
check( $rec1 && (float) $rec1->gross > 0, 'رکورد کارمند سطر اول — مرداد ۱۴۰۵ — ثبت شد' );
$payload1 = $rec1 ? json_decode( $rec1->payload, true ) : array();
check( isset( $payload1['base_salary'] ) && 21700000.0 === (float) $payload1['base_salary'], 'حقوق پایه = ۷۰۰هزار × ۳۱ = ۲۱٫۷۰۰٫۰۰۰ (ارقام فارسی و جداکننده پارس شد)' );
check( 21850000.0 === (float) $payload1['gross'], 'gross = پایه + مسکن + بن = ۲۱٫۸۵۰٫۰۰۰ (فرمول سراسری اعمال شد)' );
check( 21850000.0 === (float) $payload1['insurable'] && 'formula' === ( $payload1['insurable_mode'] ?? '' ), 'insurable از فرمول و حالت مشمول بیمه = formula (سلول «—»)' );
check( -1529500.0 === (float) $payload1['insurance_deduct'], 'کسر بیمه = ‎-۷٪ مشمول = ‎-۱٫۵۲۹٫۵۰۰' );
check( 20320500.0 === (float) $payload1['net'], 'net = ۲۱٫۸۵۰٫۰۰۰ − ۱٫۵۲۹٫۵۰۰ = ۲۰٫۳۲۰٫۵۰۰ (فرمول سراسری اعمال شد)' );
check( isset( $payload1['manual'] ) && ! in_array( 'gross', (array) $payload1['manual'], true ) && ! in_array( 'net', (array) $payload1['manual'], true ), 'gross/net به‌عنوان محاسباتی ثبت شدند نه دستی' );
// سطر نامعتبر — با «ساخت خودکار» خاموش (رفتار 1.4.1: پیش‌فرض ساخت خودکار روشن است).
$bad_rows = array( $rows[0], array( '۱۴۰۵', 'مرداد', 'بومهن', 'کارمند ناموجود' ) );
$badres = TppSalary_Import::process_records( $bad_rows, true, false );
check( 0 === $badres['ok'] && 1 === $badres['fail'] && strpos( $badres['log'][0]['message'], 'کارمند یافت نشد' ) !== false, 'سطر کارمند ناشناس → خطای گویا' );
$hdr_bad = array( array( 'ستون ۱', 'ستون ۲' ), array( 'a', 'b' ) );
$hdrres = TppSalary_Import::process_records( $hdr_bad, true );
check( 0 === $hdrres['ok'] && 1 === count( $hdrres['log'] ) && strpos( $hdrres['log'][0]['message'], 'سال' ) !== false, 'سطر عنوان ناقص → خطای گویا' );
// به‌روزرسانی همان دوره — باید upsert شود نه رکورد جدید.
$again = TppSalary_Import::process_records( $rows, false );
check( 2 === $again['ok'], 'اجرای مجدد: هر ۲ سطر موفق' );
$cnt_final = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tpp_salary_records" );
check( 2 === $cnt_final, 'upsert: هنوز فقط ۲ رکورد (بدون تکرار)' );
unlink( $spath );

echo "== 6) اکسل نمونه — اعتبارسنجی سخت‌گیرانه پایتونی جداگانه اجرا می‌شود ==\n";
check( file_exists( $plugin_dir . '/samples/employees-sample.xlsx' ) && file_exists( $plugin_dir . '/samples/salary-records-sample.xlsx' ), 'فایل‌های نمونه داخل بسته موجودند' );

echo "\n" . ( $fail ? '❌ FAIL — تست 1.4.0 شکست خورد' : '✅ ALL PASS — تست 1.4.0 سبز' ) . "\n";
exit( $fail );
