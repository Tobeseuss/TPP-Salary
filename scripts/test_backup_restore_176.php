<?php
/**
 * تست‌های نسخه 1.7.6 — بازنویسی کامل «پشتیبان‌گیری و بازگردانی»
 *
 * درخواست کاربر: «بکاپ تهیه‌شده وقتی در یک سرور و دامنه دیگر تلاش کردم تا
 * بازگردانی کنم اصلا کار نکرد».
 *
 * سه ریشه که این تست‌ها پوشش می‌دهند:
 *  ۱) بکاپ‌های خودکار ZIP بودند ولی بازگردانی فقط JSON می‌پذیرفت
 *     → حالا ZIP مستقیم بازگردانی می‌شود (json/full.json از داخل بسته).
 *  ۲) کارمندان کاربر وردپرس‌اند و در نصب مقصد هرگز ساخته نمی‌شدند
 *     → حالا تطبیق (login/کد ملی/ایمیل/شناسه+نقش) یا ساخت حساب + نگاشت user_id رکوردها.
 *  ۳) تنظیمات کامل جایگزین می‌شد → حالا ادغام.
 *  به‌علاوه: بازگردانی بخشی (kind)، رد رکورد یتیم با هشدار، پیام‌های خطای گویا.
 *
 * اجرا: ./tools/php scripts/test_backup_restore_176.php
 */

define( 'SIM_SQLITE_WPDB', 1 );
define( 'SIM_SQLITE_USERS', 1 );
error_reporting( E_ALL );
require __DIR__ . '/wp_sim_bootstrap.php';

if ( ! function_exists( 'check_admin_referer' ) ) {
        function check_admin_referer( $a = '', $b = '' ) { return true; }
}

$fail = 0;
function check( $cond, $msg ) {
        global $fail;
        echo ( $cond ? '  PASS: ' : '  FAIL: ' ) . $msg . "\n";
        if ( ! $cond ) { $fail = 1; }
}

global $wpdb;
$now = current_time( 'mysql' );

echo "== 0) آماده‌سازی — سایت «مبدأ» با داده واقعی ==\n";
/* فیلدهای پایه باید موجود باشند (فعال‌سازی افزونه در شبیه‌ساز) */
$fields_now = tpp_salary_get_fields();
check( count( $fields_now ) > 0, 'فیلدهای افزونه آماده است (' . count( $fields_now ) . ' فیلد)' );

$wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => 'کارگاه شماره یک', 'created_at' => $now ), array( '%s', '%s' ) );
$center_id = (int) $wpdb->insert_id;
$wpdb->insert( $wpdb->prefix . 'tpp_salary_banks', array( 'name' => 'بانک ملت', 'sort_order' => 1 ), array( '%s', '%d' ) );
$bank_id = (int) $wpdb->insert_id;
check( $center_id > 0 && $bank_id > 0, 'مرکز و بانک ساخته شدند' );

/* سه کارمند با پروفایل کامل */
$mk_user = function ( $login, $name, $national, $mobile, $profile ) use ( $center_id, $bank_id ) {
        $uid = wp_insert_user( array( 'user_login' => $login, 'user_pass' => 'secret123', 'display_name' => $name, 'user_email' => $login . '@example.com', 'role' => 'tpp_salary_employee' ) );
        if ( is_wp_error( $uid ) || ! $uid ) { return 0; }
        update_user_meta( $uid, 'tpp_salary_national_id', $national );
        update_user_meta( $uid, 'tpp_salary_mobile', $mobile );
        $profile['centers'] = array( $center_id );
        $profile['full_name'] = $name;
        $profile['bank_accounts'] = array( $bank_id => array( 'account' => '123', 'sheba' => 'IR', 'card' => '456' ) );
        tpp_salary_save_profile( $uid, $profile );
        return (int) $uid;
};
$uid1 = $mk_user( '0012345678', 'اسماعیل کمال آبادی', '0012345678', '09121234567', array( 'daily_wage' => 700000, 'work_days' => 31, 'housing' => 100000, 'food' => 50000, 'children_count' => 2 ) );
$uid2 = $mk_user( '0098765432', 'محمد غلامی', '0098765432', '09351112233', array( 'daily_wage' => 550000, 'work_days' => 30, 'housing' => 90000, 'food' => 45000, 'children_count' => 0 ) );
/* کارمند سوم: login غیرعددی (وگرنه تطبیق ۱ همیشه کار می‌کند) */
$uid3 = $mk_user( 'ali.rezaei', 'علی رضایی', '0044556677', '09130000000', array( 'daily_wage' => 620000, 'work_days' => 31, 'housing' => 80000, 'food' => 40000 ) );
check( $uid1 > 0 && $uid2 > 0 && $uid3 > 0, "سه کارمند ساخته شد ($uid1/$uid2/$uid3)" );

/* دو رکورد حقوق */
$payload = array( 'work_days' => 31, 'base_salary' => 21700000, 'housing' => 100000, 'food' => 50000, 'gross' => 21850000, 'insurable' => 21850000, 'insurance_deduct' => 1529500, 'other_deductions' => 0, 'net' => 20320500 );
$wpdb->insert( $wpdb->prefix . 'tpp_salary_records', array(
        'user_id' => $uid1, 'center_id' => $center_id, 'jyear' => 1403, 'jmonth' => 5,
        'payload' => wp_json_encode( $payload ), 'gross' => 21850000, 'insurable' => 21850000,
        'insurance_deduct' => 1529500, 'other_deductions' => 0, 'net' => 20320500,
        'created_by' => 1, 'created_at' => $now, 'updated_at' => $now,
), array( '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s' ) );
$rec1 = (int) $wpdb->insert_id;
$wpdb->insert( $wpdb->prefix . 'tpp_salary_records', array(
        'user_id' => $uid2, 'center_id' => $center_id, 'jyear' => 1403, 'jmonth' => 5,
        'payload' => wp_json_encode( $payload ), 'gross' => 21850000, 'insurable' => 21850000,
        'insurance_deduct' => 1529500, 'other_deductions' => 0, 'net' => 20320500,
        'created_by' => 1, 'created_at' => $now, 'updated_at' => $now,
), array( '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s' ) );
$rec2 = (int) $wpdb->insert_id;
check( $rec1 > 0 && $rec2 > 0, 'دو رکورد حقوق ساخته شد' );

/* تنظیمات مبدأ — بعداً باید در مقصد ادغام شود */
$orig_settings = tpp_salary_get_settings();
$orig_settings['company_name'] = 'شرکت نمونه مبدأ';
$orig_settings['backup']['weekly'] = 1;
update_option( 'tpp_salary_settings', $orig_settings, false );

echo "== 1) بکاپ ZIP (شبیه بکاپ خودکار) ==\n";
$zip_path = TppSalary_Backup::make( 'zip', 'monthly' );
check( is_string( $zip_path ) && file_exists( $zip_path ), 'بکاپ ZIP ماهانه ساخته شد' );

/* خواندن full.json داخل بسته — برای مقایسه بعدی */
$za = new ZipArchive();
check( true === $za->open( $zip_path ), 'ZIP باز می‌شود' );
$full_idx = $za->locateName( 'full.json', ZipArchive::FL_NODIR );
check( false !== $full_idx, 'json/full.json داخل بسته پیدا می‌شود' );
$full_raw = $za->getFromIndex( $full_idx );
$za->close();
$full = json_decode( (string) $full_raw, true );
check( is_array( $full ) && isset( $full['version'] ), 'full.json ساختار معتبر دارد' );
check( isset( $full['site']['url'] ), 'اطلاعات سایت مبدأ در بکاپ ثبت می‌شود (1.7.6)' );
$has_email = false;
foreach ( (array) ( isset( $full['profiles'] ) ? $full['profiles'] : array() ) as $p ) {
        if ( ! empty( $p['user_email'] ) ) { $has_email = true; }
}
check( $has_email, 'پروفایل‌ها اکنون user_email دارند (1.7.6)' );

echo "== 2) سرور/دامنه دیگر — نصب تازه و خالی ==\n";
/* همه داده‌ها و همه کاربران حذف می‌شوند — مثل نصب وردپرس جدید با شناسه‌های متفاوت */
$wpdb->query( "DELETE FROM {$wpdb->prefix}tpp_salary_records" );
$wpdb->query( "DELETE FROM {$wpdb->prefix}tpp_salary_centers" );
$wpdb->query( "DELETE FROM {$wpdb->prefix}tpp_salary_banks" );
$wpdb->query( "DELETE FROM {$wpdb->prefix}tpp_salary_fields" );
$wpdb->query( "DELETE FROM {$wpdb->prefix}tpp_salary_backups" );
$pdo = $GLOBALS['sim_users_pdo'];
$pdo->exec( 'DELETE FROM wp_users' );
$pdo->exec( 'DELETE FROM wp_usermeta' );
/* کاربر نامربوط در مقصد (مثل مدیر سایت) — شناسه ۱ */
$pdo->exec( "INSERT INTO wp_users (ID, user_login, user_pass, user_email, display_name) VALUES (1, 'admin', 'x', 'admin@dest.test', 'مدیر سایت')" );
$pdo->exec( "INSERT INTO wp_usermeta (user_id, meta_key, meta_value) VALUES (1, 'tpp_sim_role', 'administrator')" );
check( 1 === (int) $pdo->query( 'SELECT COUNT(*) FROM wp_users' )->fetchColumn(), 'نصب مقصد فقط مدیر دارد (کارمندان حذف شدند)' );

/* تنظیمات متفاوت در مقصد — برای آزمون ادغام */
$dest_settings = array(
        'company_name' => 'شرکت مقصد فعلی',
        'defaults'     => array( 'base_salary' => 999 ),
        'backup'       => array( 'daily' => 1, 'weekly' => 0, 'monthly' => 1, 'yearly' => 0, 'retention' => 10 ),
);
update_option( 'tpp_salary_settings', $dest_settings, false );

echo "== 3) بازگردانی مستقیم فایل ZIP روی نصب مقصد ==\n";
$loaded = TppSalary_Backup::load_backup_file( $zip_path );
check( is_array( $loaded ) && ! is_wp_error( $loaded ), 'load_backup_file فایل ZIP را می‌پذیرد' );
if ( is_array( $loaded ) ) {
        check( 0 === strpos( (string) $loaded['source'], 'zip:json/full.json' ), 'منشأ: zip:json/full.json — (' . $loaded['source'] . ')' );
}
$applied = TppSalary_Backup::apply_restore( is_array( $loaded ) ? $loaded['data'] : array() );
check( true === $applied, 'بازگردانی ZIP روی نصب خالی موفق شد' );

/* گارد جدید: فایل بدون هیچ داده‌ای نباید جدول‌ها را خالی کند */
$empty_guard = TppSalary_Backup::apply_restore( array( 'version' => '9.9', 'stamp' => 'x' ) );
check( is_wp_error( $empty_guard ), 'بکاپ بدون هیچ داده‌ای رد می‌شود (گارد پاک‌سازی کامل)');

echo "== 4) راستی‌آزمایی انتقال کامل (قلب 1.7.6) ==\n";
$sum = TppSalary_Backup::$last_summary;
check( is_array( $sum ) && 2 === (int) $sum['records'], 'دو رکورد حقوق بازگردانی شد' );
check( (int) $sum['centers'] === 1 && (int) $sum['banks'] === 1, 'مرکز و بانک بازگردانی شدند' );
check( (int) $sum['users_created'] >= 3, 'سه کارمند در نصب مقصد «ساخته» شدند' );
check( (int) $sum['records_skipped'] === 0 && empty( $sum['warnings'] ), 'هیچ رکوردی رد نشد' );

/* کارمندان با همان login و کد ملی برمی‌گردند */
$q = $pdo->query( "SELECT ID FROM wp_users WHERE user_login = '0012345678'" );
$login1 = $q ? (int) $q->fetchColumn() : 0;
$q = $pdo->query( "SELECT ID FROM wp_users WHERE user_login = '0098765432'" );
$login2 = $q ? (int) $q->fetchColumn() : 0;
$q = $pdo->query( "SELECT ID FROM wp_users WHERE user_login = 'ali.rezaei'" );
$login3 = $q ? (int) $q->fetchColumn() : 0;
check( $login1 > 0 && $login2 > 0 && $login3 > 0, 'هر سه کارمند با login اصلی ساخته شدند' );
$q = $pdo->query( "SELECT meta_value FROM wp_usermeta WHERE user_id = {$login1} AND meta_key = 'tpp_salary_national_id'" );
$nat1 = $q ? (string) $q->fetchColumn() : '';
check( '0012345678' === (string) $nat1, 'کد ملی کارمند اول منتقل شد' );
$prof1 = get_user_meta( (int) $login1, 'tpp_salary_employee_profile', true );
check( is_array( $prof1 ) && 700000.0 === (float) ( isset( $prof1['daily_wage'] ) ? $prof1['daily_wage'] : 0 ), 'پروفایل (دستمزد روزانه) منتقل شد' );
check( is_array( $prof1 ) && ! empty( $prof1['centers'] ), 'مراکز کارمند در پروفایل منتقل شد' );
$role1_q = $pdo->query( "SELECT meta_value FROM wp_usermeta WHERE user_id = {$login1} AND meta_key = 'tpp_sim_role'" );
$role1 = $role1_q ? (string) $role1_q->fetchColumn() : '';
check( 'tpp_salary_employee' === (string) $role1, 'نقش کارمندی به حساب ساخته‌شده داده شد' );

/* نگاشت شناسه: رکوردها باید به شناسه‌های «جدید» وصل باشند */
$new_uid1 = (int) $login1;
$row1 = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}tpp_salary_records WHERE jyear = 1403 AND jmonth = 5 AND user_id = {$new_uid1}" );
check( $row1 && 20320500 === (int) $row1->net, 'رکورد کارمند اول به شناسه جدید متصل است (net درست)' );
$row2 = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}tpp_salary_records WHERE user_id = " . (int) $login2 );
check( $row2 && (int) $row2->center_id === $center_id, 'رکورد کارمند دوم با مرکز بازگردانی‌شده هم‌خوان است' );

/* ادغام تنظیمات: company_name از بکاپ، defaults مقصد حفظ */
$merged = tpp_salary_get_settings();
check( 'شرکت نمونه مبدأ' === (string) $merged['company_name'], 'تنظیمات بکاپ اعمال شد (company_name)' );
check( isset( $merged['defaults']['base_salary'] ) && 999 === (int) $merged['defaults']['base_salary'], 'کلیدهای غایب در بکاپ (defaults مقصد) حفظ شدند — ادغام' );
check( 1 === (int) $merged['backup']['weekly'], 'زمان‌بندی بکاپ از تنظیمات بکاپ آمد' );

echo "== 5) بازگردانی دوباره روی همان سایت — تطبیق بدون ساخت تکراری ==\n";
$users_before = (int) $pdo->query( 'SELECT COUNT(*) FROM wp_users' )->fetchColumn();
$loaded2 = TppSalary_Backup::load_backup_file( $zip_path );
$applied2 = TppSalary_Backup::apply_restore( is_array( $loaded2 ) ? $loaded2['data'] : array() );
check( true === $applied2, 'بازگردانی دوم موفق' );
$users_after = (int) $pdo->query( 'SELECT COUNT(*) FROM wp_users' )->fetchColumn();
check( $users_before === $users_after, 'هیچ حساب تکراری ساخته نشد' );
$sum2 = TppSalary_Backup::$last_summary;
check( (int) $sum2['users_created'] === 0 && (int) $sum2['users_matched'] >= 3, 'همه کارمندان این بار «تطبیق» یافتند' );
check( 2 === (int) $sum2['records'], 'رکوردها همچنان سالم بازگردانی شدند' );

echo "== 6) تطبیق با کد ملی وقتی login مقصد متفاوت است ==\n";
/* بکاپ دوم در حالی که کارمندان/رکوردها هنوز در «مبدأ» هستند — پیش از پاک‌سازی */
$zip_path2 = TppSalary_Backup::make( 'zip', 'manual' );
check( is_string( $zip_path2 ) && file_exists( $zip_path2 ), 'بکاپ دوم ساخته شد' );
/* نصب مقصد: کاربری با login متفاوت ولی همان کد ملی کارمند اول + نقش کارمندی */
$wpdb->query( "DELETE FROM {$wpdb->prefix}tpp_salary_records" );
$pdo->exec( "DELETE FROM wp_usermeta WHERE meta_key LIKE 'tpp_salary_%'" );
$pdo->exec( "DELETE FROM wp_users WHERE user_login != 'admin'" );
$pdo->exec( "INSERT INTO wp_users (user_login, user_pass, user_email, display_name) VALUES ('7700000000', 'x', 'old@dest.test', 'نام قدیمی مقصد')" );
$q = $pdo->query( "SELECT ID FROM wp_users WHERE user_login = '7700000000'" );
$alt_uid = $q ? (int) $q->fetchColumn() : 0;
$pdo->exec( "INSERT INTO wp_usermeta (user_id, meta_key, meta_value) VALUES ({$alt_uid}, 'tpp_sim_role', 'tpp_salary_employee')" );
$pdo->exec( "INSERT INTO wp_usermeta (user_id, meta_key, meta_value) VALUES ({$alt_uid}, 'tpp_salary_national_id', '0012345678')" );
check( $alt_uid > 0, "کاربر مقصد با login متفاوت و کد ملی یکسان ساخته شد ($alt_uid)" );

$loaded3 = TppSalary_Backup::load_backup_file( $zip_path2 );
check( is_array( $loaded3 ) && ! is_wp_error( $loaded3 ), 'بکاپ دوم خوانده شد' );
$applied3 = TppSalary_Backup::apply_restore( is_array( $loaded3 ) ? $loaded3['data'] : array() );
check( true === $applied3, 'بازگردانی سوم موفق' );
$sum3 = TppSalary_Backup::$last_summary;
check( (int) $sum3['users_matched'] >= 1, 'کارمند اول با «کد ملی» به حساب مقصد تطبیق یافت' );
check( (int) $sum3['users_created'] === 2, 'فقط دو کارمند دیگر ساخته شدند (بدون تکرار برای کارمند اول)' );
check( 0 === (int) $pdo->query( "SELECT COUNT(*) FROM wp_users WHERE user_login = '0012345678'" )->fetchColumn(), 'حساب تکراری با login مبدأ ساخته نشد' );
$prof_alt = get_user_meta( $alt_uid, 'tpp_salary_employee_profile', true );
check( is_array( $prof_alt ) && 700000.0 === (float) ( isset( $prof_alt['daily_wage'] ) ? $prof_alt['daily_wage'] : 0 ), 'پروفایل بکاپ روی حساب تطبیق‌یافته نوشته شد' );
check( 1 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tpp_salary_records WHERE user_id = {$alt_uid}" ), 'رکورد کارمند اول به حساب تطبیق‌یافته با کد ملی متصل شد' );
check( 2 === (int) $sum3['records'], 'هر دو رکورد بازگردانی شدند' );

echo "== 7) بازگردانی بخشی — employees.json رکوردها را پاک نمی‌کند ==\n";
$za = new ZipArchive();
$za->open( $zip_path2 );
$emp_idx = $za->locateName( 'employees.json', ZipArchive::FL_NODIR );
$emp_json = $za->getFromIndex( $emp_idx );
$rec_idx = $za->locateName( 'records.json', ZipArchive::FL_NODIR );
$rec_json = $za->getFromIndex( $rec_idx );
$za->close();
$emp_data = json_decode( (string) $emp_json, true );
$rec_data = json_decode( (string) $rec_json, true );
check( is_array( $emp_data ) && 'employees' === (string) $emp_data['kind'], 'employees.json kind دارد' );
check( is_array( $rec_data ) && 'records' === (string) $rec_data['kind'], 'records.json kind دارد' );

$recs_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tpp_salary_records" );
$centers_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tpp_salary_centers" );
$applied4 = TppSalary_Backup::apply_restore( $emp_data );
check( true === $applied4, 'بازگردانی employees.json موفق' );
$recs_after_emp = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tpp_salary_records" );
check( $recs_after_emp === $recs_before, 'بازگردانی بخش کارمندان، رکوردهای حقوق را دست نخورد' );
$sum4 = TppSalary_Backup::$last_summary;
check( 'employees' === (string) $sum4['kind'] && 0 === (int) $sum4['records'], 'گزارش بازگردانی: kind=employees و بدون رکورد' );

$applied5 = TppSalary_Backup::apply_restore( $rec_data );
check( true === $applied5, 'بازگردانی records.json موفق' );
$centers_after_rec = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tpp_salary_centers" );
check( $centers_after_rec === $centers_before, 'مراکز در فایل مجزا هستند و بدون تغییر باقی می‌مانند' );
$recs_final = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tpp_salary_records" );
check( $recs_final === $recs_before, 'رکوردها با فایل مجزا هم کامل بازگردانی شدند (نگاشت با پروفایل‌های داخل همان فایل)' );

echo "== 8) رکورد یتیم (کارمند غایب در بکاپ و مقصد) — رد با هشدار، نه شکست کل ==\n";
$orphan = $rec_data;
$orphan['records'][] = array(
        'id' => 99, 'user_id' => 424242, 'center_id' => $center_id, 'jyear' => 1403, 'jmonth' => 7,
        'payload' => '{}', 'gross' => 1, 'insurable' => 1, 'insurance_deduct' => 0,
        'other_deductions' => 0, 'net' => 1, 'created_by' => 1, 'created_at' => $now, 'updated_at' => $now,
);
$applied6 = TppSalary_Backup::apply_restore( $orphan );
check( true === $applied6, 'رکورد یتیم مانع کل بازگردانی نمی‌شود' );
$sum6 = TppSalary_Backup::$last_summary;
check( 1 === (int) $sum6['records_skipped'] && ! empty( $sum6['warnings'] ), 'رکورد یتیم رد شد و هشدار ثبت شد' );
check( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tpp_salary_records WHERE user_id = 424242" ) === 0, 'رکورد یتیم در دیتابیس درج نشد' );
check( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tpp_salary_records" ) === $recs_final, 'بقیه رکوردها سالم بازگردانی شدند' );

echo "== 9) پیام‌های خطای گویا (اکسل به‌جای ZIP، فایل خراب) ==\n";
$xlsx_path = TppSalary_Backup::make( 'xlsx', 'manual' );
check( is_string( $xlsx_path ) && file_exists( $xlsx_path ), 'بکاپ اکسل ساخته شد' );
$bad1 = TppSalary_Backup::load_backup_file( $xlsx_path );
check( is_wp_error( $bad1 ), 'فایل اکسل به‌عنوان منبع بازگردانی رد می‌شود' );
check( is_wp_error( $bad1 ) && false !== strpos( $bad1->get_error_message(), 'اکسل' ), 'پیام خطا به اکسل اشاره می‌کند (گویا)' );

$tmp_bad = tempnam( sys_get_temp_dir(), 'tppbad' );
file_put_contents( $tmp_bad, 'این یک فایل JSON نیست' );
$bad2 = TppSalary_Backup::load_backup_file( $tmp_bad );
check( is_wp_error( $bad2 ), 'فایل غیر JSON/ZIP رد می‌شود' );
unlink( $tmp_bad );

$tmp_bad2 = tempnam( sys_get_temp_dir(), 'tppbad' );
file_put_contents( $tmp_bad2, '{"broken": ' );
$bad3 = TppSalary_Backup::load_backup_file( $tmp_bad2 );
check( is_wp_error( $bad3 ), 'JSON خراب رد می‌شود' );
unlink( $tmp_bad2 );

/* ZIP بی‌روح (بدون json/full.json) */
$tmp_zip = tempnam( sys_get_temp_dir(), 'tppzip' );
$z2 = new ZipArchive();
$z2->open( $tmp_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE );
$z2->addFromString( 'random.txt', 'nothing here' );
$z2->close();
$bad4 = TppSalary_Backup::load_backup_file( $tmp_zip );
check( is_wp_error( $bad4 ), 'ZIP بدون json/full.json رد می‌شود' );
unlink( $tmp_zip );

echo "== 10) ستون ناشناخته همچنان بازگردانی را متوقف می‌کند (امنیت/سازگاری قدیمی) ==\n";
$evil = $loaded3['data'];
$evil['banks'][] = array( 'id' => 555, 'name' => 'x', 'evil_col' => 'boom' );
$cnt_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tpp_salary_banks" );
$bad5 = TppSalary_Backup::apply_restore( $evil );
check( is_wp_error( $bad5 ), 'ستون ناشناخته → بازگردانی رد می‌شود' );
$cnt_after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tpp_salary_banks" );
check( $cnt_after === $cnt_before, 'داده فعلی دست‌نخورده ماند (ROLLBACK)' );

echo "== 11) بکاپ JSON خام هم همچنان مستقیم بازگردانی می‌شود ==\n";
$json_path = TppSalary_Backup::make( 'json', 'manual' );
$loaded4 = TppSalary_Backup::load_backup_file( $json_path );
check( is_array( $loaded4 ) && 'json' === (string) $loaded4['source'], 'فایل JSON خام شناسایی شد' );
$applied7 = TppSalary_Backup::apply_restore( is_array( $loaded4 ) ? $loaded4['data'] : array() );
check( true === $applied7, 'بازگردانی JSON خام موفق' );

echo "== 12) گزارش transient برای صفحه پشتیبان‌گیری ==\n";
$tr = get_transient( 'tpp_salary_restore_summary_1' );
check( is_array( $tr ) && isset( $tr['records'] ), 'گزارش بازگردانی برای کاربر جاری ثبت شد' );

echo "\n" . ( $fail ? "=== FAILED ===" : "=== ALL PASS ===" ) . "\n";
exit( $fail );
