<?php
/**
 * تست‌های نسخه 1.7.9 — درخواست کاربر:
 * «در صفحه ثبت حقوق میخواهم هم در پلاگین و هم در نرم افزار پایتون به صورت
 *  پیشفرض، در صورتی که برای ماه قبل کارمند حقوقی ثبت شده باشد، حقوق ماه جدید
 *  نیز در فرم نمایش داده شده مطابق حقوق ماه قبل تکمیل شده باشد … همچنین
 *  مطابق دستور قبلی چنانچه تغییری در حقوق کارمندی داده می‌شود، اطلاعات به
 *  صورت پیشفرض در پروفایل کاربر هم بروزرسانی شود»
 *
 * پوشش تست:
 *  ۱) نسخه + CSS اعلان + همگامی هسته
 *  ۲) پیش‌فرض جدید فرم مرحله ۳: فیش دورهٔ قبل → تکمیل خودکار فیلدها + اعلان
 *  ۳) نبود فیش دورهٔ قبل → رفتار قبلی (پروفایل/پیش‌فرض) و بدون اعلان
 *  ۴) فروردین → اسفند سال قبل
 *  ۵) فلگ‌های دستی دورهٔ مبدأ → data-manual="1"
 *  ۶) ویرایش رکورد موجود → بدون اعلان تکمیل خودکار
 *  ۷) همگام‌سازی پروفایل پس از upsert (رگرسیون 1.7.8) — هستهٔ مشترک فرم/REST
 *
 * اجرا: ./tools/php scripts/test_fixes_179.php
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
$now = current_time( 'mysql' );
$plugin_dir = dirname( __DIR__ ) . '/build/tpp_salary';

echo "== 0) نسخه و اجزای ثبت‌شده ==\n";
check( defined( 'TPP_SALARY_VERSION' ) && '1.7.9' === TPP_SALARY_VERSION, 'نسخه افزونه 1.7.9 است' );
check( defined( 'TPP_SALARY_INSTALL_BUILD' ) && TPP_SALARY_INSTALL_BUILD === TPP_SALARY_VERSION, 'INSTALL_BUILD همگام با نسخه است' );
check( method_exists( 'TppSalary_Salary_Pages', 'past_salary_payload' ), 'هسته past_salary_payload موجود است' );

$css = file_get_contents( $plugin_dir . '/admin/css/tpp-salary-admin.css' );
check( false !== strpos( $css, '.tpp-note-auto' ), 'استایل اعلان تکمیل خودکار در CSS موجود است' );

// ===== ۱) داده‌های آزمون =====
echo "== 1) داده‌های آزمون: مرکز + کارمند + فیش دورهٔ قبل ==\n";

$wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => 'مرکز آزمون ۱۷۹', 'created_at' => $now ), array( '%s', '%s' ) );
$center = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}tpp_salary_centers WHERE name = 'مرکز آزمون ۱۷۹'" );
check( $center > 0, 'مرکز آزمون ساخته شد' );

$uid = (int) wp_insert_user( array( 'user_login' => '179emp0001', 'user_pass' => 'pass1234', 'display_name' => 'کارمند آزمون ۱۷۹', 'role' => 'tpp_salary_employee' ) );
check( $uid > 0, 'کارمند آزمون ساخته شد' );
tpp_salary_save_profile( $uid, array( 'full_name' => 'کارمند آزمون ۱۷۹', 'daily_wage' => 1234567, 'centers' => array( $center ) ) );

/* فیش آذر ۱۴۰۴ (دورهٔ قبل) — یک فیلد دستی برای بررسی انتقال فلگ */
$res_prev = TppSalary_Salary_Pages::upsert_record( $uid, $center, 1404, 9, array(
        'base_salary' => 250000000, 'housing' => 12000000, 'food' => 8000000,
        'seniority' => 15000000, 'marriage' => 7000000, 'child_allowance' => 33251100,
        'overtime_pay' => 15000000, 'holiday_pay' => 999000, 'commute' => 5000000,
        'absence_penalty' => 0, 'work_deduction' => 0, 'other' => 10683840,
        'gross' => 381381596, 'insurable' => 270697756, 'insurance_deduct' => 18948842,
        'other_deductions' => 500000, 'net' => 362432754,
), false, array() );
check( is_array( $res_prev ) && 'created' === $res_prev['status'], 'فیش آذر ۱۴۰۴ (دورهٔ قبل) ثبت شد' );

// ===== ۲) پیش‌فرض جدید: تکمیل خودکار از دورهٔ قبل =====
echo "== 2) فرم دی ۱۴۰۴ → تکمیل خودکار از فیش آذر ۱۴۰۴ ==\n";

$_REQUEST = $_GET = array(
        'page'      => 'tpp-salary-register',
        'user_id'   => (string) $uid,
        'center_id' => (string) $center,
        'jyear'     => '1404',
        'jmonth'    => '10',
);
ob_start();
TppSalary_Salary_Pages::render_register();
$html = ob_get_clean();

check( false !== strpos( $html, 'tpp-note-auto' ), 'اعلان تکمیل خودکار (tpp-note-auto) رندر شد' );
check( false !== strpos( $html, 'فیلدها به‌صورت خودکار بر اساس فیش دورهٔ قبل' ), 'متن اعلان تکمیل خودکار' );
check( false !== strpos( $html, 'آذر 1404' ), 'دورهٔ مبدأ در اعلان: آذر 1404' );
check( false === strpos( $html, 'مرکز مبدأ' ), 'رکورد همان مرکز است → نام مرکز مبدأ ذکر نمی‌شود' );
check( false !== strpos( $html, '250,000,000' ), 'مقدار فیلد از فیش دورهٔ قبل (base_salary قالب‌بندی‌شده — بدون باگ float)' );
check( false !== strpos( $html, '362,432,754' ), 'خالص پرداختی از فیش دورهٔ قبل' );
check( false !== strpos( $html, '381,381,596' ), 'ناخالص از فیش دورهٔ قبل' );
/* رفتار قبلی نباید جایگزین شود: پیش‌فرض پروفایل (1234567) نباید در فیلدها باشد */
check( false === strpos( $html, '1,234,567' ), 'پیش‌فرض پروفایل جایگزین فیش دورهٔ قبل نشده' );
/* پروفایل آذر ۱۴۰۴ = آخرین فیش → پس از ثبت فیش آذر، پروفایل sync شده؛ فلگ دستی مشمول */
check( false !== strpos( $html, 'data-manual="1"' ), 'فلگ فیلد دستی دورهٔ مبدأ منتقل شد (data-manual=1)' );

/* هسته: پیش‌فرض دیالوگ «حقوق گذشته» هنوز ماه قبل است (سازگاری 1.6.3) */
check( false !== strpos( $html, "value=\"9\" selected='selected'" ), 'پیش‌فرض دیالوگ حقوق گذشته = ماه قبل (آذر = ۹)' );

// ===== ۳) نبود فیش دورهٔ قبل → رفتار قبلی =====
echo "== 3) فرم دی برای کارمند بدون فیش دورهٔ قبل ==\n";

$uid2 = (int) wp_insert_user( array( 'user_login' => '179emp0002', 'user_pass' => 'pass1234', 'display_name' => 'کارمند آزمون ۱۷۹-۲', 'role' => 'tpp_salary_employee' ) );
tpp_salary_save_profile( $uid2, array( 'full_name' => 'کارمند آزمون ۱۷۹-۲', 'daily_wage' => 5555555, 'centers' => array( $center ) ) );

$_REQUEST = $_GET = array(
        'page'      => 'tpp-salary-register',
        'user_id'   => (string) $uid2,
        'center_id' => (string) $center,
        'jyear'     => '1404',
        'jmonth'    => '10',
);
ob_start();
TppSalary_Salary_Pages::render_register();
$html2 = ob_get_clean();

check( false === strpos( $html2, 'tpp-note-auto' ), 'بدون فیش دورهٔ قبل → بدون اعلان تکمیل خودکار' );
check( false !== strpos( $html2, '5,555,555' ), 'رفتار قبلی: پیش‌فرض از پروفایل کارمند (دستمزد ۵,۵۵۵,۵۵۵)' );

// ===== ۴) فروردین → اسفند سال قبل =====
echo "== 4) فروردین ۱۴۰۵ → اسفند ۱۴۰۴ ==\n";

$res_esf = TppSalary_Salary_Pages::upsert_record( $uid2, $center, 1404, 12, array(
        'base_salary' => 111000000, 'housing' => 1000000, 'food' => 2000000,
        'seniority' => 3000000, 'marriage' => 0, 'child_allowance' => 0,
        'overtime_pay' => 0, 'holiday_pay' => 0, 'commute' => 4000000,
        'absence_penalty' => 0, 'work_deduction' => 0, 'other' => 0,
        'gross' => 121000000, 'insurable' => 111000000, 'insurance_deduct' => 7770000,
        'other_deductions' => 0, 'net' => 113230000,
), false, array() );
check( is_array( $res_esf ) && 'created' === $res_esf['status'], 'فیش اسفند ۱۴۰۴ ثبت شد' );

$_REQUEST = $_GET = array(
        'page'      => 'tpp-salary-register',
        'user_id'   => (string) $uid2,
        'center_id' => (string) $center,
        'jyear'     => '1405',
        'jmonth'    => '1',
);
ob_start();
TppSalary_Salary_Pages::render_register();
$html3 = ob_get_clean();

check( false !== strpos( $html3, 'tpp-note-auto' ), 'فروردین بدون فیش اسفند سال قبل → اعلان رندر شد' );
check( false !== strpos( $html3, 'اسفند 1404' ), 'دورهٔ مبدأ در اعلان: اسفند 1404 (سال قبل)' );
check( false !== strpos( $html3, '111,000,000' ), 'مقدار از فیش اسفند سال قبل' );

// ===== ۵) ویرایش رکورد موجود → بدون اعلان =====
echo "== 5) ویرایش رکورد موجود (مهر ۱۴۰۴) ==\n";

$_REQUEST = $_GET = array(
        'page'      => 'tpp-salary-register',
        'user_id'   => (string) $uid,
        'center_id' => (string) $center,
        'jyear'     => '1404',
        'jmonth'    => '9',
);
ob_start();
TppSalary_Salary_Pages::render_register();
$html4 = ob_get_clean();

check( false === strpos( $html4, 'tpp-note-auto' ), 'ویرایش رکورد موجود → بدون اعلان تکمیل خودکار' );
check( false !== strpos( $html4, 'این رکورد قبلاً ثبت شده است' ), 'اعلان حالت ویرایش حفظ شده' );
check( false !== strpos( $html4, '250,000,000' ), 'مقادیر رکورد موجود بارگذاری شد' );

// ===== ۶) اولویت رکورد همان مرکز + مبدأ مرکز دیگر =====
echo "== 6) اولویت رکورد همان مرکز و مبدأ مرکز دیگر ==\n";

$wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => 'مرکز دوم ۱۷۹', 'created_at' => $now ), array( '%s', '%s' ) );
$center_b = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}tpp_salary_centers WHERE name = 'مرکز دوم ۱۷۹'" );
$res_b = TppSalary_Salary_Pages::upsert_record( $uid2, $center_b, 1404, 12, array(
        'base_salary' => 222000000, 'housing' => 0, 'food' => 0, 'seniority' => 0,
        'marriage' => 0, 'child_allowance' => 0, 'overtime_pay' => 0, 'holiday_pay' => 0,
        'commute' => 0, 'absence_penalty' => 0, 'work_deduction' => 0, 'other' => 0,
        'gross' => 222000000, 'insurable' => 222000000, 'insurance_deduct' => 0,
        'other_deductions' => 0, 'net' => 222000000,
), false, array() );
check( is_array( $res_b ) && 'created' === $res_b['status'], 'فیش اسفند در مرکز دوم هم ثبت شد' );

$p_b = TppSalary_Salary_Pages::past_salary_payload( $uid2, $center_b, 1404, 12 );
check( is_array( $p_b ) && true === $p_b['same_center'], 'فرم مرکز دوم → رکورد مرکز دوم (اولویت همان مرکز)' );
check( '222,000,000' === $p_b['values']['base_salary'], 'مقدار مرکز دوم در پیلود' );
check( 222000000.0 === (float) $p_b['values_raw']['base_salary'], 'مقادیر خام (values_raw) برای مصرف سرور' );

/* مبدأ مرکز دیگر: فیش دی ۱۴۰۴ فقط در مرکز اول → فرم بهمن مرکز دوم */
$res_dey = TppSalary_Salary_Pages::upsert_record( $uid2, $center, 1404, 10, array(
        'base_salary' => 333000000, 'housing' => 0, 'food' => 0, 'seniority' => 0,
        'marriage' => 0, 'child_allowance' => 0, 'overtime_pay' => 0, 'holiday_pay' => 0,
        'commute' => 0, 'absence_penalty' => 0, 'work_deduction' => 0, 'other' => 0,
        'gross' => 333000000, 'insurable' => 333000000, 'insurance_deduct' => 0,
        'other_deductions' => 0, 'net' => 333000000,
), false, array() );
check( is_array( $res_dey ) && 'created' === $res_dey['status'], 'فیش دی ۱۴۰۴ فقط در مرکز اول ثبت شد' );

$_REQUEST = $_GET = array(
        'page'      => 'tpp-salary-register',
        'user_id'   => (string) $uid2,
        'center_id' => (string) $center_b,
        'jyear'     => '1404',
        'jmonth'    => '11',
);
ob_start();
TppSalary_Salary_Pages::render_register();
$html5 = ob_get_clean();
check( false !== strpos( $html5, 'مرکز مبدأ: مرکز آزمون ۱۷۹' ), 'مبدأ مرکز دیگر → نام مرکز مبدأ در اعلان' );
check( false !== strpos( $html5, 'دی 1404' ), 'دورهٔ مبدأ در اعلان: دی 1404' );
check( false !== strpos( $html5, '333,000,000' ), 'مقدار فیش مرکز دیگر در فرم به‌کار رفت' );

// ===== ۷) رگرسیون 1.7.8: همگام‌سازی پروفایل پس از ثبت =====
echo "== 7) رگرسیون: پروفایل پس از upsert به‌روز می‌شود ==\n";

/* آخرین دورهٔ uid2 تا این لحظه ۱۴۰۵/۰۱ نیست — فیش جدید باید «آخرین دوره» باشد */
tpp_salary_save_profile( $uid2, array( 'full_name' => 'کارمند آزمون ۱۷۹-۲', 'daily_wage' => 1, 'centers' => array( $center ) ) );
TppSalary_Salary_Pages::upsert_record( $uid2, $center, 1405, 2, array(
        'daily_wage' => 300000000,
        'base_salary' => 300000000, 'housing' => 900000, 'food' => 2200000,
        'seniority' => 3500000, 'marriage' => 0, 'child_allowance' => 0,
        'overtime_pay' => 0, 'holiday_pay' => 0, 'commute' => 1800000,
        'absence_penalty' => 0, 'work_deduction' => 0, 'other' => 0,
        'gross' => 305000000, 'insurable' => 10500000, 'insurance_deduct' => 735000,
        'other_deductions' => 0, 'net' => 300000000,
), false, array() );
$p2 = tpp_salary_get_profile( $uid2 );
check( 300000000.0 === (float) $p2['daily_wage'], 'پروفایل پس از ثبت فیش (۱۴۰۵/۰۲ = آخرین دوره) به‌روز شد — دستمزد ۳۰۰,۰۰۰,۰۰۰' );
check( 1800000.0 === (float) $p2['commute'], 'ایاب و ذهاب پروفایل از فیش' );

echo "\n" . ( $fail ? 'SOME TESTS FAILED' : 'ALL PASS' ) . "\n";
exit( $fail ? 1 : 0 );
