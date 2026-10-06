<?php
/**
 * تست‌های نسخه 1.8.1 — درخواست کاربر:
 * «از وقتی این پلاگین را فعال کرده ام وبسایتم بسیار کند هست و بالا نمی آید»
 *
 * پوشش تست (سمت افزونه):
 *  ۱) نسخه 1.8.1 + INSTALL_BUILD همگام
 *  ۲) Revision — هش ارزان، پایدار، و حساس به هر تغییر داده (رکورد/پروفایل/مرکز/بانک/فیلد/تنظیمات)
 *  ۳) bundle_for_revision — پاسخ سبک not_modified وقتی rev برابر است؛ بسته کامل وقتی داده عوض شده
 *  ۴) قفل ضد طوفان ارتقا — maybe_upgrade با قفل فعال اجرا نمی‌شود
 *  ۵) سخت‌سازی کرون بکاپ — قفل ۱۵ دقیقه‌ای + ZIP خودکار بدون پوشه افزونه + ZIP دستی با پوشه افزونه
 *  ۶) بهینه‌سازی N+1 — get_employees و پنل کارمند همچنان نتایج درست می‌دهند
 *  ۷) بخش «کارایی و منابع» در تب وضعیت سیستم رندر می‌شود
 *
 * اجرا: ./tools/php scripts/test_perf_181.php
 */

define( 'SIM_SQLITE_WPDB', 1 );
define( 'SIM_SQLITE_USERS', 1 );
error_reporting( E_ALL );
require __DIR__ . '/wp_sim_bootstrap.php';

$fail = 0;
function check( $cond, $msg ) {
        global $fail;
        echo ( $cond ? '  PASS: ' : '  FAIL: ' ) . $msg . "\n";
        if ( ! $cond ) { $fail = 1; }
}

function tpp_rev_reset() {
        unset( $GLOBALS['tpp_salary_api_rev'] );
}

if ( ! function_exists( 'get_permalink' ) ) {
        function get_permalink( $post = 0 ) { return 'http://example.test/panel/'; }
}
if ( ! function_exists( 'wp_is_writable' ) ) {
        function wp_is_writable( $path ) { return is_dir( $path ) ? is_writable( $path ) : false; }
}
if ( ! function_exists( 'plugins_url' ) ) {
        function plugins_url( $path = '', $plugin = '' ) { return 'http://example.test/wp-content/plugins/' . ltrim( $path, '/' ); }
}
if ( ! function_exists( 'submit_button' ) ) {
        function submit_button( $text = null ) { echo '<button type="submit">' . esc_html( $text ? $text : 'ذخیره' ) . '</button>'; }
}

global $wpdb;
$now = current_time( 'mysql' );

echo "== 0) نسخه ==\n";
check( defined( 'TPP_SALARY_VERSION' ) && '1.8.1' === TPP_SALARY_VERSION, 'نسخه افزونه 1.8.1 است' );
check( defined( 'TPP_SALARY_INSTALL_BUILD' ) && TPP_SALARY_INSTALL_BUILD === TPP_SALARY_VERSION, 'INSTALL_BUILD همگام با نسخه است' );
check( method_exists( 'TppSalary_Api', 'revision' ), 'متد revision در API موجود است' );
check( method_exists( 'TppSalary_Api', 'bundle_for_revision' ), 'متد bundle_for_revision در API موجود است' );

// ===== ۱) داده‌های آزمون =====
echo "== 1) داده‌های آزمون ==\n";
$wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => 'مرکز کارایی', 'created_at' => $now ), array( '%s', '%s' ) );
$center = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}tpp_salary_centers WHERE name = 'مرکز کارایی'" );
check( $center > 0, 'مرکز آزمون ساخته شد' );

$u1 = (int) wp_insert_user( array( 'user_login' => '181emp0001', 'user_pass' => 'pass1234', 'display_name' => 'کارمند یک', 'role' => 'tpp_salary_employee' ) );
$u2 = (int) wp_insert_user( array( 'user_login' => '181emp0002', 'user_pass' => 'pass1234', 'display_name' => 'کارمند دو', 'role' => 'tpp_salary_employee' ) );
check( $u1 > 0 && $u2 > 0, 'دو کارمند آزمون ساخته شدند' );
tpp_salary_save_profile( $u1, array( 'full_name' => 'کارمند یک', 'daily_wage' => 1000000, 'centers' => array( $center ) ) );
tpp_salary_save_profile( $u2, array( 'full_name' => 'کارمند دو', 'daily_wage' => 1200000, 'centers' => array( $center ) ) );

// ===== ۲) Revision =====
echo "== 2) Revision ==\n";
tpp_rev_reset();
$rev0 = TppSalary_Api::revision();
check( is_string( $rev0 ) && 1 === preg_match( '/^[0-9a-f]{32}$/', $rev0 ), 'revision هش md5 معتبر است' );
tpp_rev_reset();
check( TppSalary_Api::revision() === $rev0, 'revision بدون تغییر داده ثابت می‌ماند (پایداری)' );

/* تغییر رکورد → revision عوض می‌شود */
$rec = TppSalary_Salary_Pages::upsert_record( $u1, $center, 1404, 7, array(
        'base_salary' => 31000000, 'net' => 31000000,
), false, array() );
check( is_array( $rec ), 'رکورد آزمون ثبت شد' );
tpp_rev_reset();
$rev1 = TppSalary_Api::revision();
check( $rev1 !== $rev0, 'ثبت رکورد جدید revision را عوض می‌کند' );

/* ویرایش پروفایل (بدون تغییر umeta_id!) → باز هم revision عوض می‌شود */
tpp_rev_reset();
$rev_before_profile = TppSalary_Api::revision();
$p = tpp_salary_get_profile( $u1 );
$p['daily_wage'] = 1500000;
tpp_salary_save_profile( $u1, $p );
tpp_rev_reset();
check( TppSalary_Api::revision() !== $rev_before_profile, 'ویرایش متن پروفایل (طول عمداً یکسان نبود) revision را عوض می‌کند' );

/* تغییر نام مرکز → revision عوض می‌شود */
tpp_rev_reset();
$rev_before_center = TppSalary_Api::revision();
$wpdb->update( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => 'مرکز کارایی ویرایش' ), array( 'id' => $center ), array( '%s' ), array( '%d' ) );
tpp_rev_reset();
check( TppSalary_Api::revision() !== $rev_before_center, 'تغییر نام مرکز revision را عوض می‌کند' );

/* تغییر تنظیمات → revision عوض می‌شود */
tpp_rev_reset();
$rev_before_set = TppSalary_Api::revision();
$s = tpp_salary_get_settings();
$s['company_name'] = 'شرکت آزمون ۱۸۱';
update_option( 'tpp_salary_settings', $s );
tpp_rev_reset();
check( TppSalary_Api::revision() !== $rev_before_set, 'تغییر تنظیمات revision را عوض می‌کند' );
tpp_rev_reset();

// ===== ۳) bundle_for_revision =====
echo "== 3) bundle_for_revision ==\n";
$cur = TppSalary_Api::revision();
$full = TppSalary_Api::bundle_for_revision( '' );
check( isset( $full['employees'] ) && isset( $full['records'] ) && ! empty( $full['revision'] ), 'بدون rev → بسته کامل با کلید revision' );
check( $full['revision'] === $cur, 'revision داخل بسته با revision فعلی برابر است' );

$light = TppSalary_Api::bundle_for_revision( $cur );
check( ! empty( $light['not_modified'] ) && true === $light['not_modified'], 'با rev برابر → پاسخ سبک not_modified' );
check( isset( $light['schema'] ) && ! isset( $light['records'] ), 'پاسخ سبک فاقد رکوردها است (سبک واقعی)' );

/* داده عوض شد → همان rev قدیمی دیگر سبک نمی‌شود */
tpp_salary_save_profile( $u2, array( 'full_name' => 'کارمند دو', 'daily_wage' => 9900000, 'centers' => array( $center ) ) );
tpp_rev_reset();
check( TppSalary_Api::revision() !== $cur, 'داده عوض شد و revision عوض شد' );
$stale = TppSalary_Api::bundle_for_revision( $cur );
check( empty( $stale['not_modified'] ) && isset( $stale['records'] ), 'با rev کهنه → دوباره بسته کامل (بدون stale)' );

// ===== ۴) قفل ضد طوفان ارتقا =====
echo "== 4) قفل ضد طوفان ارتقا ==\n";
delete_option( 'tpp_salary_db_version' );
set_transient( 'tpp_salary_upgrade_lock', 1, 600 );
TppSalary_Install::maybe_upgrade();
check( false === get_option( 'tpp_salary_db_version' ), 'با قفل فعال، upgrade در maybe_upgrade اجرا نمی‌شود' );
delete_transient( 'tpp_salary_upgrade_lock' );
TppSalary_Install::maybe_upgrade();
check( get_option( 'tpp_salary_db_version' ) === TPP_SALARY_DB_VERSION, 'با حذف قفل، upgrade یک‌بار اجرا و نسخه ذخیره می‌شود' );

// ===== ۵) سخت‌سازی کرون بکاپ =====
echo "== 5) سخت‌سازی کرون بکاپ ==\n";
$s = tpp_salary_get_settings();
$s['backup']['daily'] = 1;
update_option( 'tpp_salary_settings', $s );
tpp_rev_reset();

$before_rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tpp_salary_backups" );
delete_transient( 'tpp_salary_backup_cron_lock' );
TppSalary_Backup::run_daily();
$after_rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tpp_salary_backups" );
check( $after_rows === $before_rows + 1, 'بکاپ خودکار روزانه یک بار اجرا شد' );
check( (bool) get_transient( 'tpp_salary_backup_cron_lock' ), 'قفل ۱۵ دقیقه‌ای کرون پس از اجرا برقرار است' );

TppSalary_Backup::run_daily();
$after_rows2 = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tpp_salary_backups" );
check( $after_rows2 === $after_rows, 'اجرای فوری دوم به‌خاطر قفل رد می‌شود (ضد بار انباشتی)' );

/* ZIP خودکار: بدون پوشه افزونه */
$auto_row = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}tpp_salary_backups ORDER BY id DESC LIMIT 1" );
check( $auto_row && 'zip' === $auto_row->backup_type && 'daily' === $auto_row->origin, 'بکاپ خودکار از نوع zip با origin=daily است' );
$auto_zip = new ZipArchive();
$auto_ok  = $auto_zip->open( $auto_row->file_path );
$has_plugin = false;
if ( $auto_ok ) {
        for ( $i = 0; $i < $auto_zip->numFiles; $i++ ) {
                if ( false !== strpos( $auto_zip->getNameIndex( $i ), 'plugin/' ) ) { $has_plugin = true; break; }
        }
        $auto_zip->close();
}
check( $auto_ok && ! $has_plugin, 'ZIP بکاپ خودکار پوشه افزونه را بایگانی نمی‌کند (سبک)' );

/* ZIP دستی: با پوشه افزونه */
$_POST = array( 'backup_type' => 'zip' );
$_REQUEST = $_POST;
ob_start();
// create_manual ریدایرکت می‌کند؛ به‌جای آن هسته make را مستقیم صدا می‌زنیم
$manual_path = TppSalary_Backup::make( 'zip', 'manual', true );
ob_end_clean();
check( is_string( $manual_path ) && file_exists( $manual_path ), 'بکاپ دستی ZIP ساخته شد' );
$man_zip = new ZipArchive();
$man_ok  = $man_zip->open( $manual_path );
$has_plugin_m = false;
if ( $man_ok ) {
        for ( $i = 0; $i < $man_zip->numFiles; $i++ ) {
                if ( false !== strpos( $man_zip->getNameIndex( $i ), 'plugin/' ) ) { $has_plugin_m = true; break; }
        }
        $man_zip->close();
}
check( $man_ok && $has_plugin_m, 'ZIP بکاپ دستی پوشه افزونه را بایگانی می‌کند (رفتار 1.5.0 حفظ شده)' );

// ===== ۶) بهینه‌سازی N+1 — نتایج همچنان درست =====
echo "== 6) صحت نتایج پس از بهینه‌سازی N+1 ==\n";
$emps = tpp_salary_get_employees( $center );
check( 2 === count( $emps ), 'get_employees با مرکز: هر دو کارمند برمی‌گردند (کش دسته‌ای سالم)' );
check( false === tpp_salary_is_terminated( $u1 ), 'is_terminated پس از پیش‌بارگذاری کش سالم است' );
$p1 = tpp_salary_get_profile( $u1 );
check( isset( $p1['daily_wage'] ) && 1500000.0 === (float) $p1['daily_wage'], 'پروفایل پس از کش دسته‌ای درست خوانده می‌شود' );

/* پنل کارمند — رندر با نقشه مراکز (به‌جای کوئری ردیفی) */
$user_id_backup = get_current_user_id();
wp_set_current_user( $u1 );
$html = TppSalary_Frontend::panel_shortcode( array() );
check( is_string( $html ) && false !== strpos( $html, 'مرکز کارایی ویرایش' ), 'پنل کارمند نام مرکز را از نقشه پیش‌خوانی رندر می‌کند' );
check( false !== strpos( $html, 'فیش‌های حقوقی من' ), 'پنل کارمند ساختار کامل دارد' );
wp_set_current_user( $user_id_backup );

// ===== ۷) بخش کارایی و منابع در تب وضعیت سیستم =====
echo "== 7) تب وضعیت سیستم — کارایی و منابع ==\n";
$_GET = array( 'page' => 'tpp-salary-settings', 'tab' => 'system' );
ob_start();
TppSalary_Settings::render();
$sys = ob_get_clean();
check( false !== strpos( $sys, 'کارایی و منابع' ), 'عنوان بخش کارایی و منابع رندر شد' );
check( false !== strpos( $sys, 'WP-Cron' ), 'وضعیت WP-Cron نمایش داده می‌شود' );
check( false !== strpos( $sys, 'بکاپ خودکار (کرون)' ), 'وضعیت بکاپ خودکار کرون نمایش داده می‌شود' );
check( false !== strpos( $sys, 'Revision' ), 'یادداشت بهینه‌سازی Revision نمایش داده می‌شود' );

echo "\n";
if ( $fail ) {
        echo "RESULT: FAIL\n";
        exit( 1 );
}
echo "RESULT: ALL PASS\n";
exit( 0 );
