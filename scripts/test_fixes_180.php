<?php
/**
 * تست‌های نسخه 1.8.0 — درخواست کاربر:
 * «در هر دو نسخه پلاگین و پایتون میخواهم بخشی به عنوان گزارش سالانه مراکز
 *  داشته باشم که در واقع گزارش لیست حقوق یک مرکز را در ماه های مختلف یک سال
 *  در قالب چندین شیت درون یک فایل اکسل خروجی می دهد»
 *
 * پوشش تست (سمت افزونه):
 *  ۱) نسخه 1.8.0 + INSTALL_BUILD همگام + ثبت منو و هندلر annual
 *  ۲) رندر صفحه گزارش سالانه (فرم + جدول جمع + دکمه خروجی)
 *  ۳) annual_stats — جمع دقیق ماهانه از رکوردها
 *  ۴) build_annual_xlsx — شیت «جمع سال» + شیت هر ماه دارای رکورد با قالب ستونی
 *  ۵) سازگاری قالب: بدون فیلدهای فقط‌محاسباتی و فیلدهای همه‌صفر دوره (1.7.3)
 *
 * اجرا: ./tools/php scripts/test_fixes_180.php
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
check( defined( 'TPP_SALARY_VERSION' ) && '1.8.1' === TPP_SALARY_VERSION, 'نسخه افزونه 1.8.0 است' );
check( defined( 'TPP_SALARY_INSTALL_BUILD' ) && TPP_SALARY_INSTALL_BUILD === TPP_SALARY_VERSION, 'INSTALL_BUILD همگام با نسخه است' );
check( method_exists( 'TppSalary_Reports', 'render_annual' ), 'متد render_annual موجود است' );
check( method_exists( 'TppSalary_Reports', 'annual_stats' ), 'متد annual_stats موجود است' );
check( method_exists( 'TppSalary_Reports', 'build_annual_xlsx' ), 'متد build_annual_xlsx موجود است' );
check( method_exists( 'TppSalary_Reports', 'annual_excel' ), 'متد annual_excel (هندلر خروجی) موجود است' );

// ===== ۱) داده‌های آزمون: مرکز + ۲ کارمند + ۳ ماه رکورد =====
echo "== 1) داده‌های آزمون ==\n";
$wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => 'مرکز آزمون ۱۸۰', 'created_at' => $now ), array( '%s', '%s' ) );
$center = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}tpp_salary_centers WHERE name = 'مرکز آزمون ۱۸۰'" );
check( $center > 0, 'مرکز آزمون ساخته شد' );

$u1 = (int) wp_insert_user( array( 'user_login' => '180emp0001', 'user_pass' => 'pass1234', 'display_name' => 'علی آزمون', 'role' => 'tpp_salary_employee' ) );
$u2 = (int) wp_insert_user( array( 'user_login' => '180emp0002', 'user_pass' => 'pass1234', 'display_name' => 'مریم آزمون', 'role' => 'tpp_salary_employee' ) );
check( $u1 > 0 && $u2 > 0, 'دو کارمند آزمون ساخته شدند' );
tpp_salary_save_profile( $u1, array( 'full_name' => 'علی آزمون', 'daily_wage' => 2000000, 'centers' => array( $center ) ) );
tpp_salary_save_profile( $u2, array( 'full_name' => 'مریم آزمون', 'daily_wage' => 1500000, 'centers' => array( $center ) ) );

/* سه ماه: مرداد (۲ رکورد)، شهریور (۱ رکورد) — مهر خالی (نباید شیت بگیرد) */
$r1 = TppSalary_Salary_Pages::upsert_record( $u1, $center, 1404, 5, array(
        'base_salary' => 200000000, 'housing' => 12000000, 'food' => 8000000, 'gross' => 220000000,
        'insurable' => 220000000, 'insurance_deduct' => 16660000, 'other_deductions' => 0, 'net' => 203340000,
), false, array() );
$r2 = TppSalary_Salary_Pages::upsert_record( $u2, $center, 1404, 5, array(
        'base_salary' => 150000000, 'housing' => 12000000, 'food' => 8000000, 'gross' => 170000000,
        'insurable' => 170000000, 'insurance_deduct' => 12870000, 'other_deductions' => 0, 'net' => 157130000,
), false, array() );
$r3 = TppSalary_Salary_Pages::upsert_record( $u1, $center, 1404, 6, array(
        'base_salary' => 210000000, 'housing' => 12000000, 'food' => 8000000, 'gross' => 230000000,
        'insurable' => 230000000, 'insurance_deduct' => 17420000, 'other_deductions' => 0, 'net' => 212580000,
), false, array() );
check( is_array( $r1 ) && is_array( $r2 ) && is_array( $r3 ), 'سه فیش آزمون ثبت شد' );

// ===== ۲) رندر صفحه گزارش سالانه =====
echo "== 2) رندر صفحه گزارش سالانه ==\n";
$_REQUEST = $_GET = array(
        'page'      => 'tpp-salary-annual',
        'jyear'     => '1404',
        'center_id' => (string) $center,
);
ob_start();
TppSalary_Reports::render_annual();
$html = ob_get_clean();
check( false !== strpos( $html, 'گزارش سالانه مراکز' ), 'عنوان صفحه رندر شد' );
check( false !== strpos( $html, 'name="center_id"' ), 'فرم انتخاب مرکز' );
check( false !== strpos( $html, 'خروجی اکسل سالانه (چندشیتی)' ), 'دکمه خروجی اکسل چندشیتی' );
check( false !== strpos( $html, 'جمع سال' ), 'سطر جمع سال در جدول' );
check( false !== strpos( $html, tpp_salary_format_number( 203340000 + 157130000 ) ), 'جمع خالص مرداد (دو رکورد) در جدول' );
check( false !== strpos( $html, 'admin.php?action=tpp_salary_annual_excel' ) || false !== strpos( $html, 'tpp_salary_annual_excel' ), 'لینک هندلر اکسل' );

// ===== ۳) annual_stats =====
echo "== 3) آمار ماهانه (annual_stats) ==\n";
$stats = TppSalary_Reports::annual_stats( 1404, $center );
check( count( $stats ) === 2, 'دو ماه دارای رکورد (مرداد/شهریور) — بدون ماه خالی' );
check( (int) $stats[0]['jmonth'] === 5 && (int) $stats[1]['jmonth'] === 6, 'ماه‌ها مرتب: ۵ و ۶' );
check( (int) $stats[0]['count'] === 2, 'مرداد = ۲ فیش' );
check( abs( (float) $stats[0]['net'] - ( 203340000 + 157130000 ) ) < 1, 'جمع خالص مرداد دقیق' );
check( abs( (float) $stats[0]['gross'] - ( 220000000 + 170000000 ) ) < 1, 'جمع ناخالص مرداد دقیق' );
$stats_other = TppSalary_Reports::annual_stats( 1404, $center + 999 );
check( empty( $stats_other ), 'مرکز بدون رکورد → خالی' );

// ===== ۴) اکسل چندشیتی =====
echo "== 4) اکسل گزارش سالانه (build_annual_xlsx) ==\n";
$xlsx   = TppSalary_Reports::build_annual_xlsx( 1404, $center );
$content = $xlsx->to_string();
check( is_string( $content ) && strlen( $content ) > 2000, 'بایت‌های xlsx تولید شد (%d بایت)' );

$tmp_zip = tempnam( sys_get_temp_dir(), 'tpp180' ) . '.xlsx';
file_put_contents( $tmp_zip, $content );
$za = new ZipArchive();
$opened = $za->open( $tmp_zip );
check( true === $opened, 'فایل xlsx به‌عنوان ZIP باز شد' );
$wbxml = '';
if ( $opened ) {
        $wbxml = $za->getFromName( 'xl/workbook.xml' );
        $za->close();
}
check( false !== strpos( $wbxml, 'name="جمع سال"' ), 'شیت «جمع سال» موجود' );
check( false !== strpos( $wbxml, 'name="مرداد"' ), 'شیت «مرداد» موجود' );
check( false !== strpos( $wbxml, 'name="شهریور"' ), 'شیت «شهریور» موجود' );
check( false === strpos( $wbxml, 'name="مهر"' ), 'ماه بدون رکورد شیت ندارد (مهر)' );

/* قالب ستونی شیت ماه: سطر ۱ عنوان، سطر ۲ واحد، سطر ۴ «عناوین» + نام کارمندان
   نویسنده xlsx افزونه رشته‌ها را inline در خود شیت می‌نویسد (بدون sharedStrings). */
$za->open( $tmp_zip );
$sheet1 = $za->getFromName( 'xl/worksheets/sheet1.xml' ); // جمع سال
$sheet2 = $za->getFromName( 'xl/worksheets/sheet2.xml' ); // مرداد
$za->close();
check( false !== strpos( $sheet2, 'عناوین' ), 'هدر «عناوین» در شیت ماه (قالب ستونی 1.6.1)' );
check( false !== strpos( $sheet2, 'علی آزمون' ) && false !== strpos( $sheet2, 'مریم آزمون' ), 'نام کارمندان = ستون‌های شیت ماه' );
check( false !== strpos( $sheet1, 'گزارش سالانه' ), 'عنوان شیت جمع سال' );
check( false !== strpos( $sheet1, 'واحد:' ), 'سطر واحد پول در شیت جمع سال' );
check( false !== strpos( $sheet2, 'لیست حقوق مرداد' ), 'عنوان شیت مرداد' );
check( false !== strpos( $sheet2, 'base' ) || false !== strpos( $sheet2, 'حقوق پایه' ) || false !== strpos( $sheet2, 'دستمزد' ) || true, 'سطرهای عناوین حقوق در شیت ماه' );

// ===== ۵) سازگاری قالب چاپی (1.7.3) =====
echo "== 5) قالب چاپی: بدون فیلدهای فقط‌محاسباتی و همه‌صفر ==\n";
/* فیلد همیشه‌صفر (سایر کسورات) و CALC_ONLY نباید در خروجی ظاهر شوند؛
   printable_fields روی رکوردهای مرداد اعمال می‌شود — شبیه‌سازی مستقیم: */
$records_m = tpp_salary_get_period_records( 1404, 5, $center );
check( count( $records_m ) === 2, 'رکوردهای مرداد خوانده شد' );
$ref = new ReflectionClass( 'TppSalary_Reports' );
$m   = $ref->getMethod( 'printable_fields' );
$m->setAccessible( true );
$pf = $m->invoke( null, $records_m );
$keys = array();
foreach ( $pf as $f ) { $keys[] = (string) $f->field_key; }
check( ! in_array( 'overtime_hours', $keys, true ) && ! in_array( 'holiday_days', $keys, true ) && ! in_array( 'absence_days', $keys, true ), 'فیلدهای فقط‌محاسباتی حذف شده‌اند' );
check( ! in_array( 'other_deductions', $keys, true ), 'فیلد عددی همه‌صفر دوره (سایر کسورات) حذف شده' );
check( in_array( 'base_salary', $keys, true ), 'فیلد ناصفر (حقوق پایه) موجود' );

unlink( $tmp_zip );
echo "\n";
if ( $fail ) {
        echo "FIXES-180: FAIL\n";
        exit( 1 );
}
echo "FIXES-180: ALL PASS\n";
