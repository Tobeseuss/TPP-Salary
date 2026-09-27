<?php
/**
 * تست‌های نسخه 1.6.2 — درخواست کاربر:
 * «امکان جستجو و حذف تکی و گروهی حقوق های ثبت شده ، کارمندان و مراکز را هم فراهم کن»
 *  ۱) حقوق‌های ثبت‌شده: چک‌باکس + حذف گروهی + دکمه حذف تکی در فهرست + اعلان تعداد
 *  ۲) کارمندان: چک‌باکس + حذف گروهی نقش کارمندی (حساب حفظ می‌شود) + اعلان تعداد
 *  ۳) مراکز: جستجو + صفحه‌بندی نتایج جستجو + حذف گروهی + اعلان تعداد
 *  ۴) دفاع دولایه: JS مشترک + هندلرهای نانس‌دار با گارد دسترسی
 *
 * اجرا: ./tools/php scripts/test_fixes_162.php
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

global $wpdb;
$plugin_dir = dirname( __DIR__ ) . '/build/tpp_salary';

echo "== 0) نسخه و کمک‌تابع‌های مشترک ==\n";
check( defined( 'TPP_SALARY_VERSION' ) && '1.7.7' === TPP_SALARY_VERSION, 'نسخه افزونه (همگام با آخرین نسخه) است' );
check( function_exists( 'tpp_salary_ids_from_request' ), 'تابع پاک‌سازی شناسه‌ها موجود است' );
check( function_exists( 'tpp_salary_bulk_table_script' ), 'تابع اسکریپت جدول گروهی موجود است' );

$out = tpp_salary_ids_from_request( array( '5', '3', 'abc', -2, 0, '5', 12.7 ) );
check( array( 5, 3, 12 ) === $out, 'پاک‌سازی: رشته عددی/عدد مثبت می‌ماند، منفی/غیرعددی/صفر دور ریخته و تکراری حذف می‌شود: ' . json_encode( $out ) );
check( array() === tpp_salary_ids_from_request( 'x' ) && array() === tpp_salary_ids_from_request( array() ) && array() === tpp_salary_ids_from_request( null ), 'ورودی غیرآرایه/خالی → آرایه خالی (خطا نمی‌دهد)' );

// ===== ۱) حذف گروهی حقوق‌های ثبت‌شده =====
echo "== 1) حذف گروهی رکوردهای حقوق ==\n";

$now       = current_time( 'mysql' );
$wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => 'مرکز رندر ۱۶۲', 'created_at' => $now ), array( '%s', '%s' ) );
$render_center = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}tpp_salary_centers WHERE name = 'مرکز رندر ۱۶۲'" );
check( $render_center > 0, 'مرکز اختصاصی رندر ساخته شد' );

$mk_emp = function ( $name, $nid, $center_id ) {
        $uid = (int) wp_insert_user( array( 'user_login' => $nid, 'user_pass' => 'pass1234', 'display_name' => $name, 'role' => 'tpp_salary_employee' ) );
        update_user_meta( $uid, 'tpp_salary_national_id', $nid );
        tpp_salary_save_profile( $uid, array( 'full_name' => $name, 'daily_wage' => 5593757, 'work_days' => 31, 'insurance_rate' => 7, 'centers' => array( $center_id ) ) );
        return $uid;
};

$payload = array(
        'base_salary' => 250000000, 'housing' => 12000000, 'food' => 8000000,
        'seniority' => 15000000, 'marriage' => 7000000, 'child_allowance' => 33251100,
        'overtime_pay' => 15000000, 'holiday_pay' => 999000, 'commute' => 5000000,
        'absence_penalty' => 0, 'work_deduction' => 0, 'other' => 10683840,
        'gross' => 381381596, 'insurable' => 270697756, 'insurance_deduct' => 18948842,
        'other_deductions' => 500000, 'net' => 362432754,
);

/* ۵ رکورد در دوره یکتای 1404/12 (تداخلی با داده سایر تست‌ها ندارد). */
$rec_users = array();
for ( $i = 1; $i <= 5; $i++ ) {
        $uid = $mk_emp( 'کارمند رکورد ' . $i, '162000000' . $i, $render_center );
        $r   = TppSalary_Salary_Pages::upsert_record( $uid, $render_center, 1404, 12, $payload, false, array() );
        check( ! is_wp_error( $r ), 'رکورد دوره 1404/12 برای ' . 'کارمند رکورد ' . $i );
        $rec_users[] = $uid;
}
$rec_ids = array_map( 'intval', $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}tpp_salary_records WHERE jyear = 1404 AND jmonth = 12 ORDER BY id" ) );
check( 5 === count( $rec_ids ), '۵ رکورد ثبت شد' );

$n = TppSalary_Salary_Pages::bulk_delete_records( array_slice( $rec_ids, 0, 3 ) );
check( 3 === $n, 'حذف گروهی ۳ رکورد → تعداد گزارش‌شده ۳: ' . $n );
$left = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tpp_salary_records WHERE jyear = 1404 AND jmonth = 12" );
check( 2 === $left, 'فقط ۲ رکورد باقی می‌ماند: ' . $left );

check( 0 === TppSalary_Salary_Pages::bulk_delete_records( array( $rec_ids[0] ) ), 'حذف مجدد رکورد حذف‌شده → صفر (id ناموجود بی‌خطر است)' );
check( 0 === TppSalary_Salary_Pages::bulk_delete_records( array() ), 'آرایه خالی → صفر حذف' );
check( 0 === TppSalary_Salary_Pages::bulk_delete_records( array( 'abc', -5, 0, null ) ), 'شناسه‌های آلوده (غیرعددی/منفی/صفر) → صفر حذف' );
$left2 = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tpp_salary_records WHERE jyear = 1404 AND jmonth = 12" );
check( 2 === $left2, 'حملات شناسه آلوده هیچ رکورد سالمی را حذف نمی‌کند: ' . $left2 );
$left_ids = array_map( 'intval', $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}tpp_salary_records WHERE jyear = 1404 AND jmonth = 12 ORDER BY id" ) );
check( $left_ids === array_slice( $rec_ids, 3 ), 'رکوردهای باقی‌مانده دقیقاً همان‌هایی‌اند که انتخاب نشدند' );

/* حذف تکی (هسته همان مسیر $wpdb->delete در delete_record) */
$d1 = (int) $wpdb->delete( $wpdb->prefix . 'tpp_salary_records', array( 'id' => $left_ids[0] ), array( '%d' ) );
check( 1 === $d1, 'حذف تکی رکورد کار می‌کند: ' . $d1 );

// ===== ۲) حذف گروهی نقش کارمندی =====
echo "== 2) حذف گروهی نقش کارمندی ==\n";

$emp_a = $mk_emp( 'حذف گروهی الف', '1621000001', $render_center );
$emp_b = $mk_emp( 'حذف گروهی ب', '1621000002', $render_center );
$plain = (int) wp_insert_user( array( 'user_login' => '1621000003', 'user_pass' => 'pass1234', 'display_name' => 'کاربر عادی', 'role' => 'subscriber' ) );
check( $emp_a > 0 && $emp_b > 0 && $plain > 0, 'دو کارمند و یک کاربر عادی ساخته شدند' );
check( 0 === TppSalary_Employees::bulk_delete_employees( array() ), 'آرایه خالی → صفر' );

$n = TppSalary_Employees::bulk_delete_employees( array( $emp_a, $emp_b, $plain ) );
check( 2 === $n, 'حذف گروهی → فقط ۲ نقش کارمندی حذف شد (کاربر عادی نادیده گرفته شد): ' . $n );
check( false !== get_user_by( 'id', $emp_a ) && false !== get_user_by( 'id', $emp_b ), 'حساب کاربری هر دو کارمند حفظ شده است (فقط نقش حذف شد)' );
$ua = get_user_by( 'id', $emp_a );
check( ! in_array( 'tpp_salary_employee', (array) $ua->roles, true ), 'نقش tpp_salary_employee از کاربر برداشته شد' );
$emp_ids_now = array_map( function ( $u ) { return (int) $u->ID; }, tpp_salary_get_employees() );
check( ! in_array( $emp_a, $emp_ids_now, true ) && ! in_array( $emp_b, $emp_ids_now, true ), 'کارمندان حذف‌شده از لیست کارمندان افزونه کنار رفتند' );
check( false !== get_user_by( 'id', $plain ) && ! in_array( 'tpp_salary_employee', (array) get_user_by( 'id', $plain )->roles, true ), 'کاربر عادی دست‌نخورده ماند (بدون نقش کارمندی)' );
/* بازگردانی نقش برای ادامه تست‌های رندر */
$ua->add_role( 'tpp_salary_employee' );
get_user_by( 'id', $emp_b )->add_role( 'tpp_salary_employee' );

// ===== ۳) مراکز: حذف گروهی + جستجو + صفحه‌بندی =====
echo "== 3) مراکز: حذف گروهی + جستجو + صفحه‌بندی ==\n";

$center_names = array( 'پروژه الف ۱۶۲', 'پروژه ب ۱۶۲', 'پروژه پ ۱۶۲', 'کارگاه جنوبی', 'انبار شمالی' );
$center_ids   = array();
foreach ( $center_names as $cn ) {
        $wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => $cn, 'created_at' => $now ), array( '%s', '%s' ) );
        $center_ids[] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}tpp_salary_centers WHERE name = %s", $cn ) );
}
check( 5 === count( array_filter( $center_ids ) ), '۵ مرکز آزمون ساخته شد' );

$total_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tpp_salary_centers" );
$n = TppSalary_Centers::bulk_delete_centers( array( $center_ids[3], $center_ids[4], 'x', -1, 0 ) );
check( 2 === $n, 'حذف گروهی ۲ مرکز (شناسه‌های آلوده نادیده گرفته شدند): ' . $n );
$total_after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tpp_salary_centers" );
check( $total_before - 2 === $total_after, 'تعداد مراکز دقیقاً ۲ واحد کم شد: ' . $total_before . ' → ' . $total_after );
check( null === $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}tpp_salary_centers WHERE id = %d", $center_ids[3] ) ), 'مرکز حذف‌شده دیگر در جدول نیست' );

/* جستجوی نام مرکز */
$_GET = array( 'page' => 'tpp-salary-centers', 's' => 'پروژه' );
ob_start();
TppSalary_Centers::render();
$chtml = ob_get_clean();
check( false !== strpos( $chtml, 'پروژه الف ۱۶۲' ) && false !== strpos( $chtml, 'پروژه ب ۱۶۲' ) && false !== strpos( $chtml, 'پروژه پ ۱۶۲' ), 'جستجوی «پروژه» هر سه مرکز پروژه را می‌آورد' );
check( false === strpos( $chtml, 'کارگاه جنوبی' ) && false === strpos( $chtml, 'انبار شمالی' ), 'مراکز غیرمطابق از نتایج جستجو حذف شدند' );
check( false !== strpos( $chtml, 'name="s"' ), 'فرم جستجوی مراکز موجود است' );

/* جستجو با شناسه عددی */
$_GET = array( 'page' => 'tpp-salary-centers', 's' => (string) $center_ids[0] );
ob_start();
TppSalary_Centers::render();
$chtml2 = ob_get_clean();
check( false !== strpos( $chtml2, 'پروژه الف ۱۶۲' ) && false === strpos( $chtml2, 'پروژه ب ۱۶۲' ), 'جستجوی شناسه عددی مرکز دقیق کار می‌کند' );

/* جستجوی بی‌نتیجه */
$_GET = array( 'page' => 'tpp-salary-centers', 's' => 'موجود-نیست' );
ob_start();
TppSalary_Centers::render();
$chtml3 = ob_get_clean();
check( false !== strpos( $chtml3, 'مرکزی با این جستجو یافت نشد' ), 'پیام «یافت نشد» برای جستجوی بی‌نتیجه' );

/* صفحه‌بندی نتایج جستجو: ۳ نتیجه «پروژه» / ۲ در هر صفحه → ۲ صفحه + حفظ s */
$s = tpp_salary_get_settings();
$s['per_page_list'] = 2;
update_option( 'tpp_salary_settings', $s );
$_GET = array( 'page' => 'tpp-salary-centers', 's' => 'پروژه' );
ob_start();
TppSalary_Centers::render();
$chtml4 = ob_get_clean();
$cb_count = substr_count( $chtml4, 'name="ids[]"' );
check( 2 === $cb_count, 'صفحه ۱ جستجو فقط ۲ ردیف دارد: ' . $cb_count );
check( false !== strpos( $chtml4, 'صفحه 1 از 2' ), 'برچسب «صفحه 1 از 2» در جستجوی مراکز (ارقام انگلیسی)' );
check( false !== strpos( $chtml4, 'paged=2' ), 'لینک صفحه ۲ در نتایج جستجوی مراکز' );
check( false !== strpos( $chtml4, 's=' ) && false !== strpos( $chtml4, 'پروژه' ), 'پارامتر جستجو s در لینک صفحات مراکز حفظ می‌شود' );
$_GET = array( 'page' => 'tpp-salary-centers', 's' => 'پروژه', 'paged' => '2' );
ob_start();
TppSalary_Centers::render();
$chtml5 = ob_get_clean();
check( 1 === substr_count( $chtml5, 'name="ids[]"' ), 'صفحه ۲ جستجو ۱ ردیف دارد: ' . substr_count( $chtml5, 'name="ids[]"' ) );

// ===== ۴) رندر واقعی: فرم گروهی + چک‌باکس + حذف تکی + اعلان در هر سه فهرست =====
echo "== 4) رندر واقعی سه فهرست ==\n";

/* ۴-الف) حقوق‌های ثبت‌شده */
$s['per_page_list'] = 20;
update_option( 'tpp_salary_settings', $s );
$_GET = array( 'page' => 'tpp-salary-records', 'center_id' => (string) $render_center, 'deleted' => '2' );
ob_start();
TppSalary_Salary_Pages::render_records();
$rhtml = ob_get_clean();
check( false !== strpos( $rhtml, 'data-tpp-bulk' ) && false !== strpos( $rhtml, 'tpp_salary_del_salary_bulk' ), 'فرم حذف گروهی در فهرست حقوق‌ها (اکشن + data-tpp-bulk)' );
check( false !== strpos( $rhtml, 'tpp-cb-all' ) && false !== strpos( $rhtml, 'name="ids[]"' ), 'چک‌باکس انتخاب همه + چک‌باکس ردیف‌ها' );
check( false !== strpos( $rhtml, 'action=tpp_salary_del_salary' ), 'دکمه حذف تکی در فهرست حقوق‌ها (قبلاً نبود)' );
check( false !== strpos( $rhtml, 'اقدام گروهی' ) && false !== strpos( $rhtml, 'value="delete"' ), 'انتخاب‌گر «اقدام گروهی: حذف»' );
check( false !== strpos( $rhtml, '2 رکورد حقوق حذف شد' ), 'اعلان تعداد حذف (ارقام انگلیسی)' );
check( 1 === substr_count( $rhtml, 'name="ids[]"' ), 'پس از حذف‌های آزمون یک ردیف باقی مانده → ۱ چک‌باکس: ' . substr_count( $rhtml, 'name="ids[]"' ) );
check( false !== strpos( $rhtml, 'form[data-tpp-bulk]' ) && false !== strpos( $rhtml, 'window.confirm' ), 'اسکریپت مشترک انتخاب همه + تأیید درج شد' );
check( false !== strpos( $rhtml, 'همه حقوق‌های انتخاب‌شده برای همیشه حذف می‌شوند' ), 'متن تأیید اختصاصی حقوق‌ها' );

/* ۴-ب) کارمندان */
$_GET = array( 'page' => 'tpp-salary-employees', 'deleted' => '2' );
ob_start();
TppSalary_Employees::render_list();
$ehtml = ob_get_clean();
check( false !== strpos( $ehtml, 'data-tpp-bulk' ) && false !== strpos( $ehtml, 'tpp_salary_del_employee_bulk' ), 'فرم حذف گروهی در فهرست کارمندان' );
check( false !== strpos( $ehtml, 'value="delete_role"' ) && false !== strpos( $ehtml, 'حذف نقش کارمندی' ), 'اقدام گروهی «حذف نقش کارمندی»' );
check( false !== strpos( $ehtml, 'colspan="8"' ) || false !== strpos( $ehtml, 'tpp-cb-all' ), 'ستون چک‌باکس به جدول کارمندان اضافه شد' );
check( false !== strpos( $ehtml, 'نقش کارمندی 2 کارمند حذف شد' ), 'اعلان تعداد حذف نقش کارمندی (ارقام انگلیسی)' );
check( false !== strpos( $ehtml, 'حساب کاربری حفظ شد' ), 'اعلان صراحتاً می‌گوید حساب حفظ می‌شود' );
check( false !== strpos( $ehtml, 'نقش کارمندی از همه کارمندان انتخاب‌شده حذف می‌شود' ), 'متن تأیید اختصاصی کارمندان' );

/* ۴-پ) مراکز: فرم گروهی */
$_GET = array( 'page' => 'tpp-salary-centers' );
ob_start();
TppSalary_Centers::render();
$chtml6 = ob_get_clean();
check( false !== strpos( $chtml6, 'data-tpp-bulk' ) && false !== strpos( $chtml6, 'tpp_salary_del_center_bulk' ), 'فرم حذف گروهی در فهرست مراکز' );
check( false !== strpos( $chtml6, 'همه مراکز انتخاب‌شده حذف می‌شوند' ), 'متن تأیید اختصاصی مراکز' );

// ===== ۵) دفاع سمت سرور (سورس) =====
echo "== 5) هندلرها: نانس + گارد دسترسی + فراخوانی یک‌باره هسته ==\n";

$rec_src = file_get_contents( $plugin_dir . '/includes/class-tppsalary-salary-pages.php' );
check( false !== strpos( $rec_src, "add_action( 'admin_post_tpp_salary_del_salary_bulk'" ), 'هندلر حذف گروهی حقوق‌ها ثبت شده' );
check( false !== strpos( $rec_src, "check_admin_referer( 'tpp_salary_del_salary_bulk' )" ), 'نانس اختصاصی حذف گروهی حقوق‌ها' );
check( 1 === substr_count( $rec_src, 'self::bulk_delete_records(' ), 'هسته حذف گروهی حقوق‌ها دقیقاً یک‌بار در هندلر فراخوانی می‌شود' );
check( false !== strpos( $rec_src, 'function bulk_delete_records' ) && false !== strpos( $rec_src, 'function delete_records_bulk' ), 'هسته قابل‌تست از هندلر جدا شده (حقوق‌ها)' );

$emp_src = file_get_contents( $plugin_dir . '/includes/class-tppsalary-employees.php' );
check( false !== strpos( $emp_src, "add_action( 'admin_post_tpp_salary_del_employee_bulk'" ), 'هندلر حذف گروهی کارمندان ثبت شده' );
check( false !== strpos( $emp_src, "check_admin_referer( 'tpp_salary_del_employee_bulk' )" ), 'نانس اختصاصی حذف گروهی کارمندان' );
check( 1 === substr_count( $emp_src, 'self::bulk_delete_employees(' ), 'هسته حذف گروهی کارمندان دقیقاً یک‌بار فراخوانی می‌شود (بدون حذف دوباره)' );
check( false !== strpos( $emp_src, "in_array( 'tpp_salary_employee', (array) \$usr->roles, true )" ), 'هسته فقط کاربر با نقش کارمندی را حذف می‌کند' );

$ctr_src = file_get_contents( $plugin_dir . '/includes/class-tppsalary-centers.php' );
check( false !== strpos( $ctr_src, "add_action( 'admin_post_tpp_salary_del_center_bulk'" ), 'هندلر حذف گروهی مراکز ثبت شده' );
check( false !== strpos( $ctr_src, "check_admin_referer( 'tpp_salary_del_center_bulk' )" ), 'نانس اختصاصی حذف گروهی مراکز' );
check( 1 === substr_count( $ctr_src, 'self::bulk_delete_centers(' ), 'هسته حذف گروهی مراکز دقیقاً یک‌بار فراخوانی می‌شود' );
check( false === strpos( $ctr_src, 'function tpp_salary' ) && false !== strpos( $ctr_src, 'tpp_salary_ids_from_request' ), 'پاک‌سازی شناسه‌ها در مراکز' );

foreach ( array( $rec_src, $emp_src, $ctr_src ) as $src_i ) {
        check( false !== strpos( $src_i, 'tpp_salary_can_manage' ) && false !== strpos( $src_i, 'wp_die' ), 'گارد دسترسی + wp_die در هندلرها' );
}
$help_src = file_get_contents( $plugin_dir . '/includes/helpers.php' );
check( false !== strpos( $help_src, 'function tpp_salary_ids_from_request' ) && false !== strpos( $help_src, 'function tpp_salary_bulk_table_script' ), 'کمک‌تابع‌های مشترک در helpers.php' );
check( false !== strpos( $help_src, 'tpp-cb-all' ) && false !== strpos( $help_src, 'data-tpp-bulk-action' ), 'اسکریپت مشترک: انتخاب همه + اقدام گروهی' );

echo $fail ? "\n>>> FAIL ($fail)\n" : "\n>>> ALL PASS\n";
exit( $fail );
