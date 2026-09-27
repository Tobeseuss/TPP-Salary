<?php
/**
 * تست رگرسیون 1.6.0 — پوشش پنج گزارش کاربر:
 *  ۱) رفع ریشه‌ای «ستون نام و نام خانوادگی یافت نشد» (empty→isset + process_employees)
 *  ۲) دکمه نمونه در صفحه ورود گروهی کارمندان
 *  ۳) قطع/ادامه همکاری + حذف از لیست‌ها/فرم‌های حقوق
 *  ۴) PDF گزارش لیست حقوق: A4 افقی + جادادن ستون‌ها بدون شکستگی
 *  (E2E با فایل‌های واقعی کاربر: test_e2e_user_files.php)
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

echo "== 0) نسخه ==\n";
check( defined( 'TPP_SALARY_VERSION' ) && '1.7.8' === TPP_SALARY_VERSION, 'نسخه افزونه 1.6.1 است' );

// ===== ۱) ریشه باگ ستون نام =====
echo "== 1) ریشه باگ «ستون نام و نام خانوادگی یافت نشد» ==\n";
$src = file_get_contents( $plugin_dir . '/includes/class-tppsalary-import.php' );
check( false === strpos( $src, 'empty( $col_map' ) && false === strpos( $src, "empty( \$col_fields['display_name']" ), 'بررسی معیوب empty حذف شده' );
check( false !== strpos( $src, "isset( \$col_fields['display_name'] )" ), 'بررسی صحیح isset روی مقادیر نگاشت' );
check( false !== strpos( $src, 'function process_employees' ), 'هسته ورود گروهی کارمندان قابل‌تست شد (process_employees)' );

// سناریوی واقعی: فایل نمونه خود افزونه
$rows = TppSalary_Xlsx_Reader::read( $plugin_dir . '/samples/employees-sample.xlsx' );
$res  = TppSalary_Import::process_employees( $rows, true ); // dry-run
check( ! is_wp_error( $res ), 'فایل نمونه خود پلاگین بدون خطا پردازش می‌شود' );
check( is_array( $res ) && 3 === (int) $res['ok'], 'هر ۳ سطر نمونه معتبر' );
check( 0 === count( tpp_salary_get_employees() ) && 3 === (int) $res['created'], 'اجرای آزمایشی هیچ کاربر واقعی نمی‌سازد (باگ ساخت در dry-run رفع شد)' );

// نرخ بیمه کسری
check( '7' === TppSalary_Import::parse_rate_percent( '7.0000000000000007E-2' ), 'parse_rate_percent: 7E-2 → ۷' );
check( '7' === TppSalary_Import::parse_rate_percent( '0.07' ), 'parse_rate_percent: 0.07 → ۷' );
check( '9' === TppSalary_Import::parse_rate_percent( '9' ), 'parse_rate_percent: ۹ دست‌نخورده' );

// ===== ۲) دکمه نمونه در صفحه ورود گروهی کارمندان =====
echo "== 2) دکمه نمونه در صفحه ورود گروهی کارمندان ==\n";
check( false !== strpos( $src, 'tpp_salary_sample_employees' ), 'لینک دانلود نمونه کارمندان در صفحه ورود گروهی حاضر است' );
$ref = new ReflectionMethod( 'TppSalary_Import', 'render' );
ob_start();
try { $ref->invoke( null ); $out = ob_get_clean(); } catch ( Throwable $e ) { ob_end_clean(); $out = ''; echo '  EXC: ' . $e->getMessage() . "\n"; }
check( false !== strpos( (string) $out, 'دانلود فایل نمونه کارمندان' ), 'رندر واقعی صفحه دکمه نمونه دارد' );

// ===== ۳) قطع/ادامه همکاری =====
echo "== 3) قطع همکاری / ادامه همکاری ==\n";
$now = current_time( 'mysql' );
foreach ( array( 'بومهن', 'رودهن' ) as $cn ) {
        $wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => $cn, 'created_at' => $now ), array( '%s', '%s' ) );
}
$center_id = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}tpp_salary_centers LIMIT 1" );

$mk_emp = function ( $name, $nid ) use ( $center_id ) {
        $uid = wp_insert_user( array( 'user_login' => $nid, 'user_pass' => 'pass1234', 'display_name' => $name, 'role' => 'tpp_salary_employee' ) );
        update_user_meta( $uid, 'tpp_salary_national_id', $nid );
        tpp_salary_save_profile( $uid, array( 'full_name' => $name, 'daily_wage' => 5593757, 'work_days' => 31, 'insurance_rate' => 7, 'insurable_default' => 270697756, 'centers' => array( $center_id ) ) );
        return (int) $uid;
};
$uid1 = $mk_emp( 'علی محمدی', '0012345678' );
$uid2 = $mk_emp( 'زهرا حسینی', '0098765432' );
check( $uid1 > 0 && $uid2 > 0, 'دو کارمند ساخته شد' );

check( 2 === count( tpp_salary_get_employees() ), 'هر دو کارمند فعال‌اند' );
update_user_meta( $uid1, 'tpp_salary_terminated', '1' );
check( tpp_salary_is_terminated( $uid1 ), 'وضعیت قطع همکاری ثبت شد' );
check( 1 === count( tpp_salary_get_employees() ), 'کارمند قطع‌همکاری از لیست پیش‌فرض حذف شد' );
check( 2 === count( tpp_salary_get_employees( null, true ) ), 'با include_terminated هر دو برمی‌گردند' );
check( null !== TppSalary_Import::find_employee_by_name( 'علی محمدی' ), 'تطبیق ورود گروهی کارمند قطع‌همکاری را هم می‌یابد (بدون کاربر تکراری)' );
delete_user_meta( $uid1, 'tpp_salary_terminated' );
check( 2 === count( tpp_salary_get_employees() ), 'ادامه همکاری → بازگشت به لیست' );

// فرم/ایجکس ثبت حقوق: کارمند قطع‌شده نمی‌آید
update_user_meta( $uid1, 'tpp_salary_terminated', '1' );
$ids = array_map( function ( $u ) { return (int) $u->ID; }, tpp_salary_get_employees() );
check( ! in_array( $uid1, $ids, true ), 'فرم/ایجکس ثبت حقوق کارمند قطع‌شده را نمی‌آورد' );
update_user_meta( $uid1, 'tpp_salary_terminated', '0' ); // صفر هم یعنی فعال
check( 2 === count( tpp_salary_get_employees() ), 'متای صفر = فعال (فقط «۱» قطع است)' );

$emp_src = file_get_contents( $plugin_dir . '/includes/class-tppsalary-employees.php' );
check( false !== strpos( $emp_src, 'قطع همکاری' ) && false !== strpos( $emp_src, 'ادامه همکاری' ), 'برچسب دکمه‌ها در کلاس کارمندان' );
check( false !== strpos( $emp_src, 'tpp_salary_toggle_termination' ), 'هندلر قطع/ادامه همکاری ثبت شده' );

// ===== ۴) PDF گزارش لیست حقوق — A4 افقی =====
echo "== 4) PDF گزارش لیست حقوق (A4 افقی + فیت ستون‌ها) ==\n";
$users_ids = array();
$i = 0;
foreach ( array( 'اسماعیل کمال آبادی', 'محمد غلامی', 'محمدرضا ریاحی', 'حسن احمدی', 'مهدی هاشمی', 'رضا کریمی', 'سعید مرادی' ) as $nm ) {
        $uid = $mk_emp( $nm, (string) ( 9000000000 + $i++ ) );
        $users_ids[] = $uid;
}
$payload_tpl = array(
        'daily_wage' => 5593757, 'seniority' => 40291289, 'overtime_rate' => 1068385, 'work_days' => 31,
        'holiday_rate' => 1068385, 'base_salary' => 173406467, 'insurance_group' => '6', 'insurance_rate' => 7,
        'insurable_default' => 270697756, 'child_allowance_rate' => 16625550, 'housing' => 30000000,
        'food' => 22000000, 'child_allowance' => 33251100, 'marriage' => 5000000, 'absence_rate' => 0,
        'overtime_hours' => 0, 'overtime_pay' => 0, 'holiday_days' => 0, 'holiday_pay' => 0,
        'children_count' => 2, 'absence_days' => 0, 'commute' => 0, 'absence_penalty' => 0,
        'work_deduction' => 0, 'other' => 10683840, 'gross' => 414632696, 'insurable' => 270697756,
        'insurance_deduct' => 18948842, 'other_deductions' => 0, 'net' => 395683854,
);
foreach ( $users_ids as $uid ) {
        $r = TppSalary_Salary_Pages::upsert_record( $uid, $center_id, 1405, 5, $payload_tpl, false, array() );
        check( ! is_wp_error( $r ), 'ثبت رکورد کارمند #' . $uid );
}
$recs = tpp_salary_get_period_records( 1405, 5, 0 );
check( 7 === count( $recs ), '۷ رکورد دوره ساخته شد: ' . count( $recs ) );

// تعداد ستون بالا (۱۲) → خودکار محدود به ظرفیت صفحه
$fit = TppSalary_Reports::report_pdf_fit( $recs, 12 );
check( isset( $fit['per'], $fit['max_fit'], $fit['col_w'], $fit['need_col'] ), 'محاسبه فیت: per=' . $fit['per'] . ' max_fit=' . $fit['max_fit'] . ' col_w=' . round( $fit['col_w'], 1 ) . 'mm need=' . round( $fit['need_col'], 1 ) . 'mm' );
check( $fit['per'] <= $fit['max_fit'], 'تعداد ستون‌ها هرگز از ظرفیت صفحه A4 افقی بیشتر نیست' );
check( $fit['per'] === $fit['max_fit'], 'نسخه 1.7.3: ستون‌ها همیشه تا گنجایش کامل صفحه پر می‌شوند (per=max_fit) — ستون جاگیر به صفحه بعد نمی‌رود' );
check( $fit['col_w'] >= $fit['need_col'] - 0.01, 'عرض ستون ≥ پهن‌ترین متن + لایی (بدون شکستگی)' );

// ساخت PDF واقعی — ابعاد و بدون خطا
$pdf = TppSalary_Reports::build_report_pdf( $recs, 1405, 5, 0, 12 );
check( $pdf instanceof TppSalary_PDF, 'PDF ساخته شد' );
$w = $pdf->GetPageWidth(); $h = $pdf->GetPageHeight();
check( $w > $h, 'صفحه افقی است (' . round( $w, 1 ) . '×' . round( $h, 1 ) . 'mm)' );
check( abs( $w - 297 ) < 1.5 && abs( $h - 210 ) < 1.5, 'ابعاد دقیق A4 افقی (297×210mm)' );

// خروجی و اعتبارسنجی PDF
$out_file = sys_get_temp_dir() . '/tpp160-report.pdf';
file_put_contents( $out_file, $pdf->Output( 'S' ) );
check( filesize( $out_file ) > 5000, 'خروجی PDF غیرخالی' );
$hdr = file_get_contents( $out_file, false, null, 0, 5 );
check( '%PDF-' === $hdr, 'امضای PDF سالم' );
// تعداد صفحات با تنظیم ۱۲ (۷ رکورد → یک بلوک + صفحات ادامه سطرها)
$page_count = preg_match_all( '/\/Type\s*\/Page[^s]/', file_get_contents( $out_file ) );
check( $page_count >= 1, 'حداقل یک صفحه: ' . $page_count );

// تنظیم ۴ — نسخه 1.7.3: در PDF ستون‌ها همیشه تا گنجایش صفحه پر می‌شوند
$fit4 = TppSalary_Reports::report_pdf_fit( $recs, 4 );
check( $fit4['per'] === $fit4['max_fit'], 'نسخه 1.7.3: تنظیم ۴ در PDF نادیده گرفته می‌شود و صفحه تا گنجایش پر می‌شود' );

echo $fail ? "\n>>> FAIL\n" : "\n>>> ALL PASS\n";
exit( $fail );
