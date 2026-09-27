<?php
/**
 * تست‌های نسخه 1.7.8 — همگام‌سازی خودکار پروفایل کارمند با آخرین فیش صادرشده
 *
 * درخواست کاربر: «میخواهم فیلد های دستمزد روزانه مرجع، پایه سنوات، مبلغ هر ساعت
 * اضافه کاری، مبلغ تعطیل کاری، گروه اصلی بیمه، نرخ درصد بیمه، حقوق مشمول بیمه،
 * حق اولاد هر فرزند، حق مسکن، حق بن، حق تأهل، جریمه غیبت روزانه، تعداد فرزند،
 * کمک هزینه ایاب و ذهاب که در پروفایل کاربری هر کارمند وجود دارد به طور خودکار
 * مطابق آخرین فیش حقوقی صادر شده برای کارمند آپدیت شوند».
 *
 * پوشش تست:
 *  ۱) توابع کمکی جدید + همگامی نسخه‌ها
 *  ۲) همگام‌سازی مستقیم + نرخ‌های استنتاجی از آخرین فیش
 *  ۳) حفاظت مخرج صفر / گروه بیمه خالی
 *  ۴) ثبت پس‌گیرانه دوره قدیمی — پروفایل رگرس نمی‌کند (آخرین دوره ملاک است)
 *  ۵) یکپارچگی upsert_record (فرم ویزارد → محاسبه → دیتابیس → پروفایل)
 *  ۶) حذف گروهی — بازگشت پروفایل به فیش جدیدتر باقی‌مانده
 *  ۷) کاربر بدون فیش
 *  ۸) یادداشت همگام‌سازی در صفحه پروفایل کاربری (پیشخوان)
 *
 * اجرا: php scripts/test_profile_sync_178.php
 */

define( 'SIM_SQLITE_WPDB', 1 );
define( 'SIM_SQLITE_USERS', 1 );
error_reporting( E_ALL );
require __DIR__ . '/wp_sim_bootstrap.php';

$fail = 0;
$pass = 0;
function check( $cond, $msg ) {
        global $fail, $pass;
        echo ( $cond ? '  PASS: ' : '  FAIL: ' ) . $msg . "\n";
        if ( $cond ) { $pass++; } else { $fail = 1; }
}

global $wpdb;
$now = current_time( 'mysql' );

echo "== 1) توابع کمکی و نسخه ==\n";
check( defined( 'TPP_SALARY_VERSION' ) && '1.7.8' === TPP_SALARY_VERSION, 'نسخه افزونه 1.7.8 است' );
check( defined( 'TPP_SALARY_INSTALL_BUILD' ) && TPP_SALARY_INSTALL_BUILD === TPP_SALARY_VERSION, 'INSTALL_BUILD همگام با نسخه است' );
check( function_exists( 'tpp_salary_sync_profile_from_latest_record' ), 'تابع همگام‌سازی تعریف شده است' );
check( function_exists( 'tpp_salary_latest_record_period' ), 'تابع آخرین دوره فیش تعریف شده است' );
check( function_exists( 'tpp_salary_normalize_rate' ), 'تابع پاکسازی نرخ تعریف شده است' );
check( 7.0 === tpp_salary_normalize_rate( 735000 / 10500000 * 100 ), 'پاکسازی خطای ممیز شناور (۷٪ دقیق)' );
check( 45000.5 === tpp_salary_normalize_rate( 45000.500000001 ), 'گردکردن تا چهار رقم اعشار' );

/* کاربران آزمون — درج مستقیم (wp_insert_user با ID فقط UPDATE می‌کند) */
$sim_pdo = $GLOBALS['sim_users_pdo'];
$st_user = $sim_pdo->prepare( 'INSERT OR IGNORE INTO wp_users (ID, user_login, user_pass, user_email, display_name) VALUES (?, ?, ?, ?, ?)' );
foreach ( array( 21, 22, 23, 24, 25, 32, 999 ) as $uid ) {
        $st_user->execute( array( $uid, 'emp' . $uid, 'x', 'emp' . $uid . '@example.test', 'کارمند ' . $uid ) );
}

$mk_slip = function ( $user_id, $y, $m, $payload ) use ( $wpdb, $now ) {
        $wpdb->insert( $wpdb->prefix . 'tpp_salary_records', array(
                'user_id' => $user_id, 'center_id' => 1, 'jyear' => $y, 'jmonth' => $m,
                'payload' => wp_json_encode( $payload ),
                'gross' => 0, 'insurable' => 0, 'insurance_deduct' => 0,
                'other_deductions' => 0, 'net' => 0,
                'created_by' => 1, 'created_at' => $now, 'updated_at' => $now,
        ), array( '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s' ) );
        return (int) $wpdb->insert_id;
};

echo "== 2) همگام‌سازی مستقیم + نرخ‌های استنتاجی (کاربر ۲۱) ==\n";
/* پروفایل قبلی متفاوت — باید کاملاً از فیش بازنویسی شود */
tpp_salary_save_profile( 21, array(
        'daily_wage' => 111, 'seniority' => 222, 'housing' => 333, 'food' => 444,
        'marriage' => 555, 'children_count' => 9, 'commute' => 666,
        'insurance_group' => 'قدیمی', 'insurable_default' => 777,
        'overtime_rate' => 888, 'holiday_rate' => 999, 'child_allowance_rate' => 1111,
        'absence_rate' => 500000, /* غیبت صفر در فیش → نباید دست بخورد */
        'centers' => array(), 'bank_accounts' => array(),
) );
$slip21 = $mk_slip( 21, 1404, 9, array(
        'insurance_group' => 'الف', 'daily_wage' => 1500000, 'work_days' => 30,
        'base_salary' => 45000000, 'housing' => 900000, 'food' => 2200000,
        'seniority' => 3500000, 'marriage' => 0, 'children_count' => 2,
        'child_allowance' => 1200000, 'commute' => 1800000,
        'overtime_hours' => 8, 'overtime_pay' => 720000,
        'holiday_days' => 2, 'holiday_pay' => 1500000,
        'absence_days' => 0, 'absence_penalty' => 0,
        'work_deduction' => 0, 'other' => 0, 'gross' => 50000000,
        'insurable' => 10500000, 'insurance_deduct' => -735000,
        'other_deductions' => 0, 'net' => 48000000,
        'manual' => array(), 'insurable_mode' => 'profile',
) );
check( $slip21 > 0, 'فیش مهر ۱۴۰۴ برای کاربر ۲۱ درج شد' );
$res21 = tpp_salary_sync_profile_from_latest_record( 21 );
check( is_array( $res21 ) && true === $res21['updated'], 'همگام‌سازی انجام شد' );
check( 1404 === $res21['jyear'] && 9 === $res21['jmonth'], 'دوره مبدأ درست گزارش شد (مهر ۱۴۰۴)' );
$p21 = tpp_salary_get_profile( 21 );
check( 1500000.0 === (float) $p21['daily_wage'], 'دستمزد روزانه مرجع ← از فیش' );
check( 3500000.0 === (float) $p21['seniority'], 'پایه سنوات ← از فیش' );
check( 900000.0 === (float) $p21['housing'], 'حق مسکن ← از فیش' );
check( 2200000.0 === (float) $p21['food'], 'حق بن ← از فیش' );
check( 0.0 === (float) $p21['marriage'], 'حق تأهل ← از فیش (صفر فیش)' );
check( 2.0 === (float) $p21['children_count'], 'تعداد فرزند ← از فیش' );
check( 1800000.0 === (float) $p21['commute'], 'کمک هزینه ایاب و ذهاب ← از فیش' );
check( 'الف' === $p21['insurance_group'], 'گروه اصلی بیمه ← از فیش' );
check( 10500000.0 === (float) $p21['insurable_default'], 'حقوق مشمول بیمه ← insurable فیش' );
check( 90000.0 === (float) $p21['overtime_rate'], 'مبلغ هر ساعت اضافه کاری ← 720000÷8' );
check( 750000.0 === (float) $p21['holiday_rate'], 'مبلغ تعطیل کاری ← 1500000÷2' );
check( 600000.0 === (float) $p21['child_allowance_rate'], 'حق اولاد هر فرزند ← 1200000÷2' );
check( 7.0 === (float) $p21['insurance_rate'], 'نرخ درصد بیمه ← 735000÷10500000×100' );
check( 500000.0 === (float) $p21['absence_rate'], 'جریمه غیبت روزانه با غیبت صفر دست‌نخورده ماند' );
check( isset( $p21['centers'] ) && array() === $p21['centers'], 'ساختار پروفایل (مراکز/بانک‌ها) حفظ شد' );

echo "== 3) حفاظت مخرج صفر و گروه بیمه خالی (کاربر ۲۲) ==\n";
tpp_salary_save_profile( 22, array(
        'overtime_rate' => 123456, 'insurance_group' => 'ب', 'insurance_rate' => 7.5,
        'child_allowance_rate' => 300000, 'absence_rate' => 100000,
        'centers' => array(), 'bank_accounts' => array(),
) );
$slip22 = $mk_slip( 22, 1404, 9, array(
        'insurance_group' => '', 'daily_wage' => 2000000, 'work_days' => 31,
        'housing' => 0, 'food' => 0, 'seniority' => 0, 'marriage' => 0,
        'children_count' => 0, 'child_allowance' => 0, 'commute' => 0,
        'overtime_hours' => 0, 'overtime_pay' => 0,
        'holiday_days' => 0, 'holiday_pay' => 0,
        'absence_days' => 3, 'absence_penalty' => -2400000,
        'work_deduction' => 0, 'other' => 0, 'gross' => 1000000,
        'insurable' => 0, 'insurance_deduct' => 0,
        'other_deductions' => 0, 'net' => 1000000,
        'manual' => array(), 'insurable_mode' => 'profile',
) );
$res22 = tpp_salary_sync_profile_from_latest_record( 22 );
check( is_array( $res22 ) && true === $res22['updated'], 'همگام‌سازی کاربر ۲۲ انجام شد' );
$p22 = tpp_salary_get_profile( 22 );
check( 123456.0 === (float) $p22['overtime_rate'], 'ساعت اضافه‌کاری صفر → نرخ پروفایل دست‌نخورده' );
check( 'ب' === $p22['insurance_group'], 'گروه بیمه خالی در فیش → پروفایل حفظ شد' );
check( 7.5 === (float) $p22['insurance_rate'], 'مشمول بیمه صفر → نرخ بیمه دست‌نخورده' );
check( 300000.0 === (float) $p22['child_allowance_rate'], 'فرزند صفر → حق اولاد هر فرزند دست‌نخورده' );
check( 800000.0 === (float) $p22['absence_rate'], 'جریمه غیبت روزانه ← |−2400000|÷3' );
check( 2000000.0 === (float) $p22['daily_wage'], 'دستمزد روزانه از فیش جدید' );

echo "== 4) ثبت پس‌گیرانه دوره قدیمی — رگرسیون ممنوع (کاربر ۲۳) ==\n";
$mk_slip( 23, 1404, 9, array(
        'daily_wage' => 1500000, 'housing' => 900000, 'seniority' => 3500000,
        'insurable' => 10500000, 'insurance_deduct' => -735000,
        'insurance_group' => 'الف', 'food' => 1, 'marriage' => 1,
        'children_count' => 1, 'child_allowance' => 1, 'commute' => 1,
        'work_days' => 30, 'base_salary' => 1, 'overtime_hours' => 1, 'overtime_pay' => 1,
        'holiday_days' => 1, 'holiday_pay' => 1, 'absence_days' => 0, 'absence_penalty' => 0,
        'work_deduction' => 0, 'other' => 0, 'gross' => 1, 'other_deductions' => 0, 'net' => 1,
        'manual' => array(), 'insurable_mode' => 'profile',
) );
$mk_slip( 23, 1404, 8, array( /* دوره قدیمی‌تر — بعداً ثبت پس‌گیرانه می‌شود */
        'daily_wage' => 999, 'housing' => 111111, 'seniority' => 222222,
        'insurable' => 1, 'insurance_deduct' => 0, 'insurance_group' => 'قدیمی',
        'food' => 1, 'marriage' => 1, 'children_count' => 1, 'child_allowance' => 1,
        'commute' => 1, 'work_days' => 30, 'base_salary' => 1,
        'overtime_hours' => 1, 'overtime_pay' => 1, 'holiday_days' => 1, 'holiday_pay' => 1,
        'absence_days' => 0, 'absence_penalty' => 0, 'work_deduction' => 0,
        'other' => 0, 'gross' => 1, 'other_deductions' => 0, 'net' => 1,
        'manual' => array(), 'insurable_mode' => 'profile',
) );
$res23 = tpp_salary_sync_profile_from_latest_record( 23 );
$p23 = tpp_salary_get_profile( 23 );
check( is_array( $res23 ) && 900000.0 === (float) $p23['housing'], 'ملاک، آخرین دوره (مهر) است نه جدیدترین ثبت' );
check( 1500000.0 === (float) $p23['daily_wage'], 'دستمزد از فیش مهر ۱۴۰۴ (نه آبان)' );
/* به‌روزرسانی خود فیش آخر → پروفایل دنبال می‌کند */
$rec23 = tpp_salary_latest_record_period( 23 );
global $wpdb;
$row23 = $wpdb->get_row( $wpdb->prepare( "SELECT id, payload FROM {$wpdb->prefix}tpp_salary_records WHERE id = %d", $rec23['id'] ) );
$pay23 = json_decode( $row23->payload, true );
$pay23['housing'] = 7770000;
$wpdb->update( $wpdb->prefix . 'tpp_salary_records', array( 'payload' => wp_json_encode( $pay23 ) ), array( 'id' => $rec23['id'] ), array( '%s' ), array( '%d' ) );
tpp_salary_sync_profile_from_latest_record( 23 );
$p23b = tpp_salary_get_profile( 23 );
check( 7770000.0 === (float) $p23b['housing'], 'به‌روزرسانی فیش آخر → پروفایل همگام شد' );

echo "== 5) یکپارچگی upsert_record — فرم ویزارد تا پروفایل (کاربر ۲۴) ==\n";
tpp_salary_save_profile( 24, array(
        'daily_wage' => 100, 'seniority' => 200, 'housing' => 300,
        'centers' => array(), 'bank_accounts' => array(),
) );
$raw24 = array(
        'insurance_group' => 'ج', 'daily_wage' => '1800000', 'work_days' => '30',
        'base_salary' => '54000000', 'housing' => '850000', 'food' => '2100000',
        'seniority' => '3300000', 'marriage' => '500000', 'children_count' => '1',
        'child_allowance' => '600000', 'commute' => '1600000',
        'overtime_hours' => '10', 'overtime_pay' => '900000',
        'holiday_days' => '1', 'holiday_pay' => '800000',
        'absence_days' => '0', 'absence_penalty' => '0',
        'work_deduction' => '0', 'other' => '0',
        'insurable' => '12000000', 'insurance_deduct' => '-840000',
        'other_deductions' => '0',
);
$res24 = TppSalary_Salary_Pages::upsert_record( 24, 1, 1404, 9, $raw24, false );
check( is_array( $res24 ) && 'created' === $res24['status'], 'رکورد با موفقیت ایجاد شد' );
$p24 = tpp_salary_get_profile( 24 );
check( 1800000.0 === (float) $p24['daily_wage'], 'پروفایل پس از صدور فیش: دستمزد روزانه مرجع' );
check( 3300000.0 === (float) $p24['seniority'], 'پروفایل پس از صدور فیش: پایه سنوات' );
check( 850000.0 === (float) $p24['housing'], 'پروفایل پس از صدور فیش: حق مسکن' );
check( 2100000.0 === (float) $p24['food'], 'پروفایل پس از صدور فیش: حق بن' );
check( 500000.0 === (float) $p24['marriage'], 'پروفایل پس از صدور فیش: حق تأهل' );
check( 1.0 === (float) $p24['children_count'], 'پروفایل پس از صدور فیش: تعداد فرزند' );
check( 1600000.0 === (float) $p24['commute'], 'پروفایل پس از صدور فیش: ایاب و ذهاب' );
check( 'ج' === $p24['insurance_group'], 'پروفایل پس از صدور فیش: گروه اصلی بیمه' );
check( 90000.0 === (float) $p24['overtime_rate'], 'پروفایل پس از صدور فیش: نرخ اضافه‌کاری (900000÷10)' );
check( 800000.0 === (float) $p24['holiday_rate'], 'پروفایل پس از صدور فیش: نرخ تعطیل کاری' );
check( 600000.0 === (float) $p24['child_allowance_rate'], 'پروفایل پس از صدور فیش: حق اولاد هر فرزند' );
check( abs( (float) $p24['insurance_rate'] - 7.0 ) < 0.001, 'پروفایل پس از صدور فیش: نرخ بیمه ≈ ۷٪' );
check( (float) $p24['insurable_default'] > 0, 'پروفایل پس از صدور فیش: حقوق مشمول بیمه' );

echo "== 6) صدور پس‌گیرانه از مسیر upsert_record — رگرسیون ممنوع (کاربر ۲۵) ==\n";
$resA = TppSalary_Salary_Pages::upsert_record( 25, 1, 1404, 9, array(
        'housing' => '500000', 'daily_wage' => '1500000', 'seniority' => '100000',
        'food' => '10', 'marriage' => '10', 'children_count' => '0', 'commute' => '10',
        'insurance_group' => 'الف', 'work_days' => '30', 'base_salary' => '1',
        'child_allowance' => '0', 'overtime_hours' => '0', 'overtime_pay' => '0',
        'holiday_days' => '0', 'holiday_pay' => '0', 'absence_days' => '0', 'absence_penalty' => '0',
        'work_deduction' => '0', 'other' => '0', 'insurable' => '1000000', 'insurance_deduct' => '0',
        'other_deductions' => '0',
), false );
$p25a = tpp_salary_get_profile( 25 );
check( 500000.0 === (float) $p25a['housing'], 'فیش مهر صادر شد → پروفایل همگام' );
TppSalary_Salary_Pages::upsert_record( 25, 1, 1404, 8, array(
        'housing' => '444444', 'daily_wage' => '555555', 'seniority' => '10',
        'food' => '10', 'marriage' => '10', 'children_count' => '0', 'commute' => '10',
        'insurance_group' => 'الف', 'work_days' => '30', 'base_salary' => '1',
        'child_allowance' => '0', 'overtime_hours' => '0', 'overtime_pay' => '0',
        'holiday_days' => '0', 'holiday_pay' => '0', 'absence_days' => '0', 'absence_penalty' => '0',
        'work_deduction' => '0', 'other' => '0', 'insurable' => '1000000', 'insurance_deduct' => '0',
        'other_deductions' => '0',
), false );
$p25b = tpp_salary_get_profile( 25 );
check( 500000.0 === (float) $p25b['housing'] && 1500000.0 === (float) $p25b['daily_wage'], 'ثبت آبان (قدیمی) پروفایل را رگرس نکرد' );

echo "== 7) حذف گروهی — بازگشت به فیش جدیدتر باقی‌مانده ==\n";
$latest25 = tpp_salary_latest_record_period( 25 );
check( is_array( $latest25 ) && 9 === $latest25['jmonth'], 'آخرین فیش کاربر ۲۵ مهر است' );
$ids25 = array();
foreach ( (array) $wpdb->get_results( "SELECT id, jmonth FROM {$wpdb->prefix}tpp_salary_records WHERE user_id = 25" ) as $r ) { // phpcs:ignore
        $ids25[ (int) $r->jmonth ] = (int) $r->id;
}
$deleted = TppSalary_Salary_Pages::bulk_delete_records( array( $ids25[9] ) );
check( 1 === $deleted, 'فیش مهر حذف شد' );
$p25c = tpp_salary_get_profile( 25 );
check( 444444.0 === (float) $p25c['housing'] && 555555.0 === (float) $p25c['daily_wage'], 'پروفایل به فیش آبان (جدیدترین باقی‌مانده) بازگشت' );
TppSalary_Salary_Pages::bulk_delete_records( array( $ids25[8] ) );
$p25d = tpp_salary_get_profile( 25 );
check( 444444.0 === (float) $p25d['housing'], 'بدون هیچ فیشی → پروفایل دست‌نخورده (خراب نمی‌شود)' );

echo "== 8) کاربر بدون فیش ==\n";
check( false === tpp_salary_sync_profile_from_latest_record( 999 ), 'همگام‌سازی بدون فیش → false' );
check( null === tpp_salary_latest_record_period( 999 ), 'آخرین دوره بدون فیش → null' );
check( false === tpp_salary_sync_profile_from_latest_record( 0 ), 'شناسه صفر → false' );

echo "== 9) یادداشت همگام‌سازی در پروفایل پیشخوان ==\n";
$u24  = get_userdata( 24 );
$u999 = get_userdata( 999 );
check( $u24 instanceof WP_User && 24 === (int) $u24->ID, 'کاربر ۲۴ آماده است' );
ob_start();
TppSalary_Employees::profile_fields( $u24 );
$html24 = ob_get_clean();
check( false !== strpos( $html24, 'همگام' ) && false !== strpos( $html24, 'آخرین فیش' ), 'یادداشت همگام‌سازی برای کارمند دارای فیش' );
check( false !== strpos( $html24, TppSalary_Jalali::month_name( 9 ) ), 'دوره آخرین فیش (مهر) نمایش داده می‌شود' );
ob_start();
TppSalary_Employees::profile_fields( $u999 );
$html999 = ob_get_clean();
check( false !== strpos( $html999, 'نخستین فیش' ), 'یادداشت حالت بدون فیش' );

echo "\n========================================\n";
echo $fail ? "RESULT: FAILED ({$fail} assertions)\n" : "RESULT: ALL PASS ({$pass} assertions)\n";
exit( $fail ? 1 : 0 );
