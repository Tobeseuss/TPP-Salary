<?php
/**
 * تست‌های نسخه 1.7.3 — درخواست کاربر:
 *  ۱) همه فونت‌های PDF (گزارش لیست حقوق، فیش بانکی، فیش حقوقی) حداقل Vazirmatn
 *     با اندازه 10pt و Bold — دیگر فونت ریز تولید نمی‌شود.
 *  ۲) همه اعداد در تمام سایت و نرم‌افزار پایتون با ارقام انگلیسی.
 *
 * اجرا: ./tools/php scripts/test_fixes_171.php
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
/* حذف ارقام فارسی/عربی برای بررسی «همه اعداد انگلیسی» */
function fa_digit_count( $s ) {
        return preg_match_all( '/[\x{06F0}-\x{06F9}\x{0660}-\x{0669}]/u', (string) $s );
}
function font_probe( $pdf ) {
        $rc = new ReflectionProperty( 'tFPDF', 'CurrentFont' );
        $rc->setAccessible( true );
        $rs = new ReflectionProperty( 'tFPDF', 'FontSizePt' );
        $rs->setAccessible( true );
        $cur = $rc->getValue( $pdf );
        return array( 'name' => is_array( $cur ) ? $cur['name'] : '', 'size' => (float) $rs->getValue( $pdf ) );
}

global $wpdb;

echo "== 0) آماده‌سازی ==\n";
$now = current_time( 'mysql' );
$wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => 'مرکز ۱۷۱', 'created_at' => $now ), array( '%s', '%s' ) );
$center_id = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}tpp_salary_centers LIMIT 1" );
check( $center_id > 0, 'مرکز ساخته شد' );

$users_ids = array();
foreach ( array( 'علی 171 محمدی', 'زهرا 171 کریمی', 'رضا 171 احمدی' ) as $i => $name ) {
        $nid = '17100000' . ( $i + 10 );
        $uid = (int) wp_insert_user( array( 'user_login' => $nid, 'user_pass' => 'pass1234', 'display_name' => $name, 'role' => 'tpp_salary_employee' ) );
        check( $uid > 0, 'کارمند ساخته شد: ' . $name );
        update_user_meta( $uid, 'tpp_salary_national_id', $nid );
        tpp_salary_save_profile( $uid, array( 'full_name' => $name, 'daily_wage' => 5593757, 'work_days' => 31, 'insurance_rate' => 7, 'centers' => array( $center_id ) ) );
        $users_ids[] = $uid;
}
$payload_tpl = array(
        'base_salary' => 250000000, 'housing' => 12000000, 'food' => 8000000,
        'seniority' => 15000000, 'marriage' => 7000000, 'child_allowance' => 33251100,
        'overtime_pay' => 15000000, 'holiday_pay' => 999000, 'commute' => 5000000,
        'absence_penalty' => 0, 'work_deduction' => 0, 'other' => 10683840,
        'gross' => 381381596, 'insurable' => 270697756, 'insurance_deduct' => 18948842,
        'other_deductions' => 500000, 'net' => 362432754,
);
foreach ( $users_ids as $uid ) {
        $r = TppSalary_Salary_Pages::upsert_record( $uid, $center_id, 1405, 5, $payload_tpl, false, array() );
        check( ! is_wp_error( $r ), 'ثبت رکورد حقوق برای #' . $uid );
}
$recs = tpp_salary_get_period_records( 1405, 5, 0 );
check( 3 === count( $recs ), '۳ رکورد دوره: ' . count( $recs ) );

// ===== ۱) سیاست ارقام انگلیسی =====
echo "== 1) همه اعداد انگلیسی ==\n";
check( '123' === TppSalary_Jalali::digits_fa( '۱۲۳' ), 'digits_fa ارقام فارسی را لاتین می‌کند: ' . TppSalary_Jalali::digits_fa( '۱۲۳' ) );
check( '45' === TppSalary_Jalali::digits_fa( '٤٥' ), 'digits_fa ارقام عربی را لاتین می‌کند' );
check( '456' === TppSalary_Jalali::digits_fa( '456' ), 'digits_fa روی لاتین دست‌نخورده' );
check( 'مرداد 1404' === TppSalary_Jalali::period_label( 1404, 5 ), 'period_label با ارقام انگلیسی: ' . TppSalary_Jalali::period_label( 1404, 5 ) );
check( '1,234,567' === tpp_salary_format_number( 1234567.0 ) && '1,234,567' === tpp_salary_format_number( 1234567.0, true ), 'tpp_salary_format_number همیشه لاتین (با $fa=true هم)' );
check( '1405' === TppSalary_PDF::fa_digits( '۱۴۰۵' ), 'PDF::fa_digits همیشه لاتین' );

$pdf1 = new TppSalary_PDF( 'P', 'mm', 'A4' );
$pdf1->AddPage();
$pdf1->SetFont( 'vazir', 'B', 10 );
$shaped = $pdf1->fa( 'حق مسکن مرداد ۱۴۰۵ — مبلغ 1234567' );
check( 0 === fa_digit_count( $shaped ) && false !== strpos( $shaped, '1405' ) && false !== strpos( $shaped, '1234567' ), 'موتور PDF: خروجی fa هیچ رقم فارسی ندارد و اعداد لاتین‌اند' );

/* صفحه‌بندی نتایج جستجو با ارقام انگلیسی */
$_GET = array( 'page' => 'tpp-salary-records', 's' => 'علی', 'jmonth' => '5' );
ob_start();
tpp_salary_pagination( 45, 20 );
$nav = ob_get_clean();
check( 0 === fa_digit_count( $nav ), 'ناوبری صفحه‌بندی بدون هیچ رقم فارسی' );
check( false !== strpos( $nav, 'صفحه 1 از 3' ), 'برچسب «صفحه 1 از 3» انگلیسی است' );

/* گزینه ارقام فارسی از صفحه تنظیمات حذف شده */
$settings_src = file_get_contents( __DIR__ . '/../build/tpp_salary/includes/class-tppsalary-settings.php' );
check( false === strpos( (string) $settings_src, 'name="digits_fa"' ), 'چک‌باکس ارقام فارسی از UI تنظیمات حذف شد' );

// ===== ۲) فونت PDF گزارش لیست حقوق: Bold 10 =====
echo "== 2) فونت PDF گزارش: Vazirmatn ≥10 Bold ==\n";
$fit = TppSalary_Reports::report_pdf_fit( $recs, 12 );
check( 10.0 === (float) $fit['fs_body'] && 10.0 === (float) $fit['fs_head'], 'fs_body = fs_head = 10pt ثابت: ' . $fit['fs_body'] . '/' . $fit['fs_head'] );
check( $fit['rh'] >= 4.6, 'نسخه 1.7.3: ارتفاع سطر حداقل 4.6mm برای فونت 10pt (کف جدید): ' . round( $fit['rh'], 2 ) );
$pdf_report = TppSalary_Reports::build_report_pdf( $recs, 1405, 5, 0, 12 );
$probe = font_probe( $pdf_report );
check( 'Vazirmatn-Bold' === $probe['name'] && 10.0 === $probe['size'], 'فونت جاری گزارش = Vazirmatn-Bold 10pt: ' . $probe['name'] . ' ' . $probe['size'] );
$report_bytes = $pdf_report->Output( 'S' );
check( '%PDF-' === substr( $report_bytes, 0, 5 ), 'PDF گزارش سالم تولید شد' );

/* سطرهای مازاد (فیلدهای زیاد) به صفحه بعد می‌روند — حداقل فونت اولویت دارد.
 * نسخه 1.7.3: مقادیر فیلدهای آزمون باید ناصفر باشند وگرنه در خروجی چاپی حذف می‌شوند. */
for ( $i = 1; $i <= 35; $i++ ) {
        $wpdb->insert(
                $wpdb->prefix . 'tpp_salary_fields',
                array( 'field_key' => 'test171_f' . $i, 'label' => 'فیلد آزمون ' . $i, 'field_type' => 'number', 'in_record' => 1, 'show_in_payslip' => 1, 'sort_order' => 100 + $i ),
                array( '%s', '%s', '%s', '%d', '%d', '%d' )
        );
}
tpp_salary_bump_fields_version(); // نسخه 1.7.3: باطل‌سازی کش فیلدها
/* رکوردها با مقادیر ناصفر فیلدهای آزمون دوباره ثبت می‌شوند تا چاپی بمانند */
foreach ( $users_ids as $idx => $uid ) {
        $p = $payload_tpl;
        for ( $i = 1; $i <= 35; $i++ ) { $p[ 'test171_f' . $i ] = 5000 * $i; }
        $p['work_deduction'] = ( 0 === $idx ) ? 300000 : 0;
        TppSalary_Salary_Pages::upsert_record( $uid, $center_id, 1405, 5, $p, false, array() );
}
$recs = tpp_salary_get_period_records( 1405, 5, 0 );
$ref_pf171 = new ReflectionMethod( 'TppSalary_Reports', 'printable_fields' );
$ref_pf171->setAccessible( true );
$printable_before_35 = count( $ref_pf171->invoke( null, $recs ) ) - 35;
$fit_many = TppSalary_Reports::report_pdf_fit( $recs, 12 );
check( 4.6 === (float) $fit_many['rh'], 'با فیلدهای زیاد، ارتفاع سطر به کف 4.6mm می‌رسد: ' . $fit_many['rh'] );
check( ( 1 + $printable_before_35 + 35 ) * $fit_many['rh'] > $fit_many['usable_h'], 'فیت یک‌صفحه‌ای با این تعداد فیلد ممکن نیست → سطرهای مازاد صفحه‌بندی می‌شوند (' . ( 1 + $printable_before_35 + 35 ) . ' سطر × 4.6mm)' );
check( $fit_many['total_rows'] === 1 + $printable_before_35 + 35, 'فیلدهای چاپی = فیلدهای چاپی پایه + ۳۵ آزمونی: ' . $fit_many['total_rows'] );
$pdf_many = TppSalary_Reports::build_report_pdf( $recs, 1405, 5, 0, 12 );
$raw_many = $pdf_many->Output( 'S' );
$pages_many = preg_match_all( '/\/Type\s*\/Page[^s]/', $raw_many );
check( $pages_many > 1, 'سطرهای مازاد به صفحه بعد منتقل شدند (صفحات: ' . $pages_many . ' — با per=' . $fit_many['per'] . ' ستون → حداقل ۲ صفحه سطری)' );
$probe_many = font_probe( $pdf_many );
check( 'Vazirmatn-Bold' === $probe_many['name'] && 10.0 === $probe_many['size'], 'حتی با صفحه‌بندی سطرها، فونت جاری Bold 10pt است' );

// ===== ۳) فیش بانکی: Bold 10 =====
echo "== 3) فیش بانکی: Vazirmatn ≥10 Bold ==\n";
$bank = (object) array( 'id' => 1, 'name' => 'بانک تست' );
$rows = array(
        array( 'name' => 'علی 171 محمدی', 'account' => '1234567890', 'sheba' => 'IR120570028110012345678901', 'net' => 362432754.0 ),
        array( 'name' => 'زهرا 171 کریمی', 'account' => '2345678901', 'sheba' => 'IR220570028110023456789012', 'net' => 21000000.0 ),
);
$pdf_bank = TppSalary_Reports::build_bank_pdf( $rows, $bank, 1405, 5, 'مرکز ۱۷۱', 'شرکت تست', 'ریال' );
$probe_bank = font_probe( $pdf_bank );
check( 'Vazirmatn-Bold' === $probe_bank['name'] && 10.0 === $probe_bank['size'], 'فونت جاری فیش بانکی = Vazirmatn-Bold 10pt: ' . $probe_bank['name'] . ' ' . $probe_bank['size'] );
$bank_bytes = $pdf_bank->Output( 'S' );
check( '%PDF-' === substr( $bank_bytes, 0, 5 ), 'PDF فیش بانکی سالم' );

/* فیش بانکی اکنون قابل‌تست است (build_bank_pdf) و bank_pdf فقط خروجی می‌دهد */
$ref = new ReflectionMethod( 'TppSalary_Reports', 'build_bank_pdf' );
check( $ref->isStatic() && $ref->isPublic(), 'build_bank_pdf استاتیک/عمومی برای تست و استفاده مجدد' );

// ===== ۴) فیش حقوقی: Bold 10 =====
echo "== 4) فیش حقوقی: Vazirmatn ≥10 Bold ==\n";
$record = $recs[0];
$payslip_bytes = TppSalary_Reports::build_payslip_pdf( $record );
check( is_string( $payslip_bytes ) && '%PDF-' === substr( $payslip_bytes, 0, 5 ), 'فیش حقوقی سالم تولید شد: ' . ( is_string( $payslip_bytes ) ? strlen( $payslip_bytes ) . ' بایت' : 'خطا' ) );
check( false !== strpos( $payslip_bytes, 'Vazirmatn-Bold' ), 'فونت Bold در فیش حقوقی جاسازی شده' );
/* فونت‌های زیر 10pt در کد فیش/گزارش/بانک باقی نمانده باشد */
$src = file_get_contents( __DIR__ . '/../build/tpp_salary/includes/class-tppsalary-reports.php' );
$small = array();
if ( preg_match_all( "/SetFont\(\s*'vazir'\s*,\s*'(?:'|\w+)'\s*,\s*([0-9.]+)\s*\)/u", (string) $src, $m, PREG_SET_ORDER ) ) {
        foreach ( $m as $row ) {
                if ( (float) $row[1] < 10.0 ) { $small[] = $row[0]; }
        }
}
check( 0 === count( $small ), 'هیچ SetFont کوچک‌تر از 10pt در reports.php باقی نمانده: ' . implode( ' | ', array_slice( $small, 0, 3 ) ) );

// ===== ۵) فیش HTML کارمند (front) با ارقام انگلیسی =====
echo "== 5) تنظیمات و سازگاری ==\n";
$helpers_src = file_get_contents( __DIR__ . '/../build/tpp_salary/includes/helpers.php' );
check( false === strpos( (string) $helpers_src, 'TppSalary_Jalali::digits_fa( $out )' ) && false !== strpos( (string) $helpers_src, "return number_format( (float) \$n, \$dec, '.', ',' );" ), 'format_number بدون تبدیل فارسی' );
$api_src = file_get_contents( __DIR__ . '/../build/tpp_salary/includes/class-tppsalary-api.php' );
check( false !== strpos( (string) $api_src, "'digits_fa'     => 0," ), 'bundle همیشه digits_fa=0 برای نرم‌افزار پایتون می‌فرستد' );

echo $fail ? "\n>>> FAIL ($fail)\n" : "\n>>> ALL PASS\n";
exit( $fail );
