<?php
/**
 * تست‌های نسخه 1.7.3 — درخواست کاربر:
 *  ۱) خروجی PDF گزارش و فیش: فیلدهایی که مقدارشان صفر است نمایش داده نمی‌شوند؛
 *     فیلدهای فقط‌محاسباتی (ساعات اضافه‌کاری/روز تعطیل کاری/روز غیبت) حذف می‌شوند.
 *  ۲) همه ردیف‌ها در یک صفحه جا می‌شوند (کف ارتفاع سطر 4.6mm) و ستون‌ها همیشه
 *     تا گنجایش کامل صفحه پر می‌شوند (per = max_fit).
 *  ۳) محاسبه زنده: فیلد دستی با تغییر «منبع» فرمولش دوباره خودکار محاسبه می‌شود
 *     (رفع ثابت‌ماندن خالص پرداختی).
 *  ۴) ذخیره تنظیمات بخشی است — ذخیره بکاپ خودکار دیگر تنظیمات سایر تب‌ها را پاک نمی‌کند.
 *
 * اجرا: ./tools/php scripts/test_fixes_173.php
 */

define( 'SIM_SQLITE_WPDB', 1 );
define( 'SIM_SQLITE_USERS', 1 );
error_reporting( E_ALL );
require __DIR__ . '/wp_sim_bootstrap.php';

if ( ! function_exists( 'check_admin_referer' ) ) {
        function check_admin_referer( $a = '', $b = '' ) { return true; }
}
if ( ! function_exists( 'add_settings_error' ) ) {
        function add_settings_error( $a = '', $b = '', $c = '' ) { $GLOBALS['sim_settings_errors'][] = $c; }
}

$fail = 0;
function check( $cond, $msg ) {
        global $fail;
        echo ( $cond ? '  PASS: ' : '  FAIL: ' ) . $msg . "\n";
        if ( ! $cond ) { $fail = 1; }
}

global $wpdb;
/* آیا اکشن fields_changed در شبیه‌ساز موجود است؟ اگر نیست، نسخه را مستقیم بالا می‌بریم */
if ( ! function_exists( 'do_action' ) ) {
        function do_action( $tag, ...$a ) {
                if ( 'tpp_salary_fields_changed' === $tag && function_exists( 'tpp_salary_bump_fields_version' ) ) {
                        tpp_salary_bump_fields_version();
                }
        }
}

echo "== 0) آماده‌سازی ==\n";
$now = current_time( 'mysql' );
$wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => 'مرکز ۱۷۳', 'created_at' => $now ), array( '%s', '%s' ) );
$center_id = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}tpp_salary_centers LIMIT 1" );
check( $center_id > 0, 'مرکز ساخته شد' );

$payload_173 = array(
        'work_days' => 31, 'base_salary' => 250000000, 'housing' => 12000000, 'food' => 8000000,
        'seniority' => 15000000, 'marriage' => 7000000, 'children_count' => 2, 'child_allowance' => 33251100,
        'commute' => 5000000, 'overtime_pay' => 15000000, 'holiday_pay' => 999000,
        'absence_penalty' => 0, 'work_deduction' => 0, 'other' => 10683840,
        'gross' => 381381596, 'insurable' => 270697756, 'insurance_deduct' => 18948842,
        'other_deductions' => 500000, 'net' => 362432754,
);

/*
 * فیلدهای آزمون باید «پیش از نخستین فراخوانی tpp_salary_get_fields» درج شوند
 * (کش استاتیک درون‌درخواستی — از جمله از مسیر save_profile) وگرنه مقادیرشان
 * هنگام upsert در payload ذخیره نمی‌شود.
 */
$calc_only = array( 'overtime_hours', 'holiday_days', 'absence_days' );

/* شمارش فیلدهای چاپی مطابق قواعد 1.7.3 — با کوئری مستقیم (بدون کش get_fields) */
function printable_count_for_payload( $payload, $calc_only ) {
        global $wpdb;
        $rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}tpp_salary_fields WHERE is_active = 1" ); // phpcs:ignore
        $n = 0;
        foreach ( array_filter( (array) $rows, 'tpp_salary_field_in_record' ) as $f ) {
                if ( in_array( (string) $f->field_key, $calc_only, true ) ) { continue; }
                if ( 'number' === $f->field_type ) {
                        $val = isset( $payload[ $f->field_key ] ) ? (float) $payload[ $f->field_key ] : 0.0;
                        if ( abs( $val ) <= 0.0001 ) { continue; }
                }
                $n++;
        }
        return $n;
}

$base_printable = printable_count_for_payload( $payload_173, $calc_only );
check( $base_printable > 10, 'فیلدهای چاپی پایه (بر اساس seed سیستم): ' . $base_printable );

/*
 * مرز تک‌صفحه‌ای: در فاز مرزی، work_deduction هم ناصفر است (کارمند اول)؛
 * پس ۳۷ = base_printable + 1 (کسر از کار) + f_count → f_count = 36 − base.
 */
$f_count = 36 - $base_printable;
for ( $i = 1; $i <= $f_count; $i++ ) {
        $wpdb->insert( $wpdb->prefix . 'tpp_salary_fields',
                array( 'field_key' => 'test173_f' . $i, 'label' => 'فیلد آزمون ' . $i, 'field_type' => 'number', 'in_record' => 1, 'show_in_payslip' => 1, 'sort_order' => 300 + $i ),
                array( '%s', '%s', '%s', '%d', '%d', '%d' ) );
}
$g_count = 5; // فیلدهای سرریز (بعداً مقدار می‌گیرند)
for ( $i = 1; $i <= $g_count; $i++ ) {
        $wpdb->insert( $wpdb->prefix . 'tpp_salary_fields',
                array( 'field_key' => 'test173_g' . $i, 'label' => 'فیلد سرریز ' . $i, 'field_type' => 'number', 'in_record' => 1, 'show_in_payslip' => 1, 'sort_order' => 400 + $i ),
                array( '%s', '%s', '%s', '%d', '%d', '%d' ) );
}
check( ( $f_count + $g_count ) > 0, 'فیلدهای آزمون درج شدند (مرزی: ' . $f_count . '، سرریز: ' . $g_count . ')' );
/* نسخه 1.7.3: باطل‌سازی کش فیلدها در همان درخواست (شبیه‌ساز do_action را اجرا نمی‌کند) */
tpp_salary_bump_fields_version();

$users_ids = array();
foreach ( array( 'علی 173 محمدی', 'زهرا 173 کریمی', 'رضا 173 احمدی' ) as $i => $name ) {
        $nid = '17300000' . ( $i + 10 );
        $uid = (int) wp_insert_user( array( 'user_login' => $nid, 'user_pass' => 'pass1234', 'display_name' => $name, 'role' => 'tpp_salary_employee' ) );
        check( $uid > 0, 'کارمند ساخته شد: ' . $name );
        update_user_meta( $uid, 'tpp_salary_national_id', $nid );
        tpp_salary_save_profile( $uid, array( 'full_name' => $name, 'daily_wage' => 5593757, 'work_days' => 31, 'insurance_rate' => 7, 'children_count' => ( 0 === $i ? 2 : 0 ), 'centers' => array( $center_id ) ) );
        $users_ids[] = $uid;
}

// ===== ۱) فیلدهای چاپی: حذف فقط‌محاسباتی‌ها و ردیف‌های همه‌صفر =====
echo "== 1) فیلدهای چاپی PDF/اکسل ==\n";
foreach ( $users_ids as $uid ) {
        $r = TppSalary_Salary_Pages::upsert_record( $uid, $center_id, 1405, 5, $payload_173, false, array() );
        check( ! is_wp_error( $r ), 'ثبت رکورد حقوق برای #' . $uid );
}
$recs = tpp_salary_get_period_records( 1405, 5, 0 );
check( 3 === count( $recs ), '۳ رکورد دوره: ' . count( $recs ) );

$ref_pf = new ReflectionMethod( 'TppSalary_Reports', 'printable_fields' );
$ref_pf->setAccessible( true );
$printable_keys = array_map( function ( $f ) { return (string) $f->field_key; }, $ref_pf->invoke( null, $recs ) );

check( count( $printable_keys ) === $base_printable, 'تعداد فیلدهای چاپی مطابق قاعده: ' . count( $printable_keys ) . ' = ' . $base_printable );
check( 0 === count( array_intersect( $calc_only, $printable_keys ) ), 'ساعات اضافه‌کاری/روز تعطیل کاری/روز غیبت هرگز چاپ نمی‌شوند' );
check( ! in_array( 'work_deduction', $printable_keys, true ) && ! in_array( 'absence_penalty', $printable_keys, true ), 'فیلدهای عددی همه‌صفر نمایش داده نمی‌شوند' );
check( in_array( 'housing', $printable_keys, true ) && in_array( 'children_count', $printable_keys, true ), 'فیلدهای ناصفر حفظ می‌شوند' );
check( in_array( 'net', $printable_keys, true ) && in_array( 'gross', $printable_keys, true ), 'سطرهای خلاصه (ناخالص/خالص) چاپ می‌شوند' );

/* کارمند اول جریمه کسر از کار دارد → سطر باید برگردد؛ برای بقیه در فیش چاپ نمی‌شود */
$payload_r1 = $payload_173;
$payload_r1['work_deduction'] = 500000;
TppSalary_Salary_Pages::upsert_record( $users_ids[0], $center_id, 1405, 5, $payload_r1, false, array() );
$recs = tpp_salary_get_period_records( 1405, 5, 0 );
$printable2 = array_map( function ( $f ) { return (string) $f->field_key; }, $ref_pf->invoke( null, $recs ) );
check( in_array( 'work_deduction', $printable2, true ), 'با ناصفرشدن یک رکورد، سطر همه‌صفر قبلی به گزارش برمی‌گردد' );
check( count( $printable2 ) === $base_printable + 1, 'تعداد فیلدهای چاپی یک واحد زیاد شد: ' . count( $printable2 ) );

// ===== ۲) فیش حقوقی: مقادیر صفرِ همان رکورد چاپ نمی‌شوند =====
echo "== 2) فیش حقوقی: حذف مقادیر صفر ==\n";
$ref_pr = new ReflectionMethod( 'TppSalary_Reports', 'payslip_rows' );
$ref_pr->setAccessible( true );
$rows_r1 = array_map( function ( $f ) { return (string) $f->field_key; }, $ref_pr->invoke( null, $recs[0] ) );
$rows_r2 = array_map( function ( $f ) { return (string) $f->field_key; }, $ref_pr->invoke( null, $recs[1] ) );
check( in_array( 'work_deduction', $rows_r1, true ), 'فیش کارمند اول: جریمه کسر از کار (۵۰۰,۰۰۰) چاپ می‌شود' );
check( ! in_array( 'work_deduction', $rows_r2, true ), 'فیش کارمند دوم: جریمه کسر از کار صفر → چاپ نمی‌شود (همان سطر در گزارش هست)' );
check( 0 === count( array_intersect( $calc_only, $rows_r1 ) ), 'فیش: فیلدهای فقط‌محاسباتی حذف شده‌اند' );
check( ! in_array( 'absence_penalty', $rows_r1, true ), 'فیش: جریمه غیبت صفر → چاپ نمی‌شود' );
check( ! in_array( 'test173_f1', $rows_r1, true ) && ! in_array( 'test173_g1', $rows_r1, true ), 'فیش: فیلدهای آزمون صفر چاپ نمی‌شوند' );
$bytes = TppSalary_Reports::build_payslip_pdf( $recs[0] );
check( is_string( $bytes ) && '%PDF-' === substr( $bytes, 0, 5 ), 'فیش حقوقی سالم با ردیف‌های فیلترشده تولید شد' );

// ===== ۳) فیت PDF: همه ردیف‌ها یک صفحه + ستون‌ها تا گنجایش کامل =====
echo "== 3) فیت: ردیف‌ها یک صفحه + پر شدن ستون‌ها ==\n";
$fit = TppSalary_Reports::report_pdf_fit( $recs, 2 );
check( $fit['per'] === $fit['max_fit'], 'ستون‌ها همیشه تا گنجایش کامل صفحه پر می‌شوند (per=' . $fit['per'] . ' = max_fit) — تنظیم ۲ نادیده گرفته می‌شود' );
check( $fit['rh'] >= 4.6, 'ارتفاع سطر حداقل 4.6mm برای فونت Bold 10pt: ' . round( $fit['rh'], 2 ) );
check( $fit['col_w'] >= 14.0, 'عرض ستون حداقل 14mm (قبلاً 20mm): ' . round( $fit['col_w'], 1 ) );
check( ( 1 + $base_printable + 1 ) === $fit['total_rows'], 'total_rows = سطر عناوین + فیلدهای چاپی: ' . $fit['total_rows'] );
check( $fit['total_rows'] * $fit['rh'] <= $fit['usable_h'] + 0.01, 'همه ردیف‌ها (' . $fit['total_rows'] . ') در ارتفاع مفید جا می‌شوند: ' . round( $fit['total_rows'] * $fit['rh'], 1 ) . ' ≤ ' . round( $fit['usable_h'], 1 ) . 'mm' );

$pdf1 = TppSalary_Reports::build_report_pdf( $recs, 1405, 5, 0, 2 );
$pages1 = preg_match_all( '/\/Type\s*\/Page[^s]/', $pdf1->Output( 'S' ) );
check( 1 === $pages1, 'گزارش معمولی: ' . $fit['total_rows'] . ' ردیف × ۳ کارمند → دقیقاً ۱ صفحه: ' . $pages1 );

/* مرز تک‌صفحه‌ای: مقدار دادن به فیلدهای مرزی → ۳۸ سطر (هدر + ۳۷ فیلد) با کف 4.6mm */
$payload_edge = $payload_r1;
for ( $i = 1; $i <= $f_count; $i++ ) { $payload_edge[ 'test173_f' . $i ] = 1000 * $i; }
foreach ( $users_ids as $idx => $uid ) {
        $p = $payload_edge;
        $p['work_deduction'] = ( 0 === $idx ) ? 500000 : 0;
        TppSalary_Salary_Pages::upsert_record( $uid, $center_id, 1405, 5, $p, false, array() );
}
$recs = tpp_salary_get_period_records( 1405, 5, 0 );
$fit38 = TppSalary_Reports::report_pdf_fit( $recs, 12 );
check( 38 === $fit38['total_rows'], '۳۷ فیلد چاپی + سطر عناوین = ۳۸ سطر: ' . $fit38['total_rows'] );
check( $fit38['total_rows'] * $fit38['rh'] <= $fit38['usable_h'] + 0.01, '۳۸ سطر با فشرده‌سازی (rh=' . round( $fit38['rh'], 2 ) . 'mm) در یک صفحه جا می‌شوند' );
$pdf38 = TppSalary_Reports::build_report_pdf( $recs, 1405, 5, 0, 12 );
$pages38 = preg_match_all( '/\/Type\s*\/Page[^s]/', $pdf38->Output( 'S' ) );
check( 1 === $pages38, '۳۸ سطر → PDF تک‌صفحه‌ای: ' . $pages38 );

/* فراتر از گنجایش فیزیکی → سطرهای مازاد به صفحه بعد با هدر تکرار (فونت 10pt Bold حفظ) */
$payload_more = $payload_edge;
for ( $i = 1; $i <= $g_count; $i++ ) { $payload_more[ 'test173_g' . $i ] = 7000 * $i; }
foreach ( $users_ids as $uid ) {
        TppSalary_Salary_Pages::upsert_record( $uid, $center_id, 1405, 5, $payload_more, false, array() );
}
$recs = tpp_salary_get_period_records( 1405, 5, 0 );
$fit_over = TppSalary_Reports::report_pdf_fit( $recs, 12 );
check( ( 38 + $g_count ) === $fit_over['total_rows'] && 4.6 === (float) $fit_over['rh'], ( 38 + $g_count ) . ' سطر → کف ارتفاع سطر 4.6mm فعال (rh=' . $fit_over['rh'] . ')' );
check( $fit_over['total_rows'] * $fit_over['rh'] > $fit_over['usable_h'], 'فراتر از گنجایش فیزیکی صفحه → سطرهای مازاد صفحه‌بندی می‌شوند (فونت 10pt Bold اولویت دارد)' );
$pdf_over = TppSalary_Reports::build_report_pdf( $recs, 1405, 5, 0, 12 );
$pages_over = preg_match_all( '/\/Type\s*\/Page[^s]/', $pdf_over->Output( 'S' ) );
check( $pages_over >= 2, 'سرریز سطرها → حداقل ۲ صفحه با هدر تکرار: ' . $pages_over );

/* اکسل هم‌سان با PDF: از فیلدهای چاپی استفاده می‌کند */
$xlsx = TppSalary_Reports::build_report_xlsx( $recs, 1405, 5, 0, 4 );
check( $xlsx instanceof TppSalary_Xlsx_Writer, 'اکسل ستونی ساخته شد' );
$xlsx_src = file_get_contents( __DIR__ . '/../build/tpp_salary/includes/class-tppsalary-reports.php' );
check( false !== strpos( (string) $xlsx_src, "\$fields = self::printable_fields( \$records ); // نسخه 1.7.3" ), 'اکسل از فیلدهای چاپی (هم‌سان PDF) استفاده می‌کند' );

// ===== ۴) ذخیره بخشی تنظیمات — بکاپ دیگر بقیه را پاک نمی‌کند =====
echo "== 4) ذخیره بخشی تنظیمات ==\n";
$seed = tpp_salary_get_settings();
$seed['company_name'] = 'شرکت تست ۱۷۳';
$seed['currency'] = 'تومان';
$seed['logo_id'] = 5;
$seed['per_page_a4'] = 7;
$seed['formulas'] = array( 'gross' => '{daily_wage}*{work_days}', 'insurable' => '{base_salary}+{housing}', 'net' => '{gross}-{insurance_deduct}' );
$seed['backup'] = array( 'daily' => 0, 'weekly' => 0, 'monthly' => 0, 'yearly' => 0, 'retention' => 24 );
update_option( 'tpp_salary_settings', $seed );

/* ذخیره فقط تب بکاپ */
TppSalary_Settings::apply_section_save( array( 'tpp_section' => 'backup', 'backup_daily' => '1', 'backup_monthly' => '1', 'backup_retention' => '7' ) );
$s = tpp_salary_get_settings();
check( 1 === (int) $s['backup']['daily'] && 1 === (int) $s['backup']['monthly'] && 7 === (int) $s['backup']['retention'], 'تنظیمات بکاپ ذخیره شد (روزانه/ماهانه/نگهداری=7)' );
check( 'شرکت تست ۱۷۳' === $s['company_name'] && 'تومان' === $s['currency'] && 5 === (int) $s['logo_id'] && 7 === (int) $s['per_page_a4'], 'نام شرکت/واحد/لوگو/per_page با ذخیره بکاپ دست‌نخورده ماندند' );
check( '{daily_wage}*{work_days}' === $s['formulas']['gross'], 'فرمول‌ها با ذخیره بکاپ پاک نشدند' );

/* ذخیره فقط تب فرمول‌ها */
TppSalary_Settings::apply_section_save( array( 'tpp_section' => 'formulas', 'formula_gross' => '{base_salary}+{housing}+{commute}', 'formula_insurable' => '{base_salary}', 'formula_net' => '{gross}-{insurance_deduct}-{other_deductions}' ) );
$s = tpp_salary_get_settings();
check( '{base_salary}+{housing}+{commute}' === $s['formulas']['gross'], 'فرمول ناخالص از تب فرمول‌ها ذخیره شد' );
check( 1 === (int) $s['backup']['daily'] && 'شرکت تست ۱۷۳' === $s['company_name'], 'بکاپ و تنظیمات عمومی با ذخیره فرمول‌ها دست‌نخورده ماندند' );

/* ذخیره فقط تب عمومی */
TppSalary_Settings::apply_section_save( array( 'tpp_section' => 'general', 'company_name' => 'شرکت جدید', 'currency' => 'ریال', 'per_page_a4' => 5, 'per_page_list' => 30, 'logo_id' => 9 ) );
$s = tpp_salary_get_settings();
check( 'شرکت جدید' === $s['company_name'] && 9 === (int) $s['logo_id'], 'تنظیمات عمومی ذخیره شد' );
check( 1 === (int) $s['backup']['daily'] && '{base_salary}' === $s['formulas']['insurable'], 'بکاپ/فرمول‌ها با ذخیره عمومی دست‌نخورده ماندند' );

/* هر سه فرم تگ بخش دارند */
$set_src = file_get_contents( __DIR__ . '/../build/tpp_salary/includes/class-tppsalary-settings.php' );
check( 3 === preg_match_all( '/name="tpp_section"/', (string) $set_src ), 'سه فرم (عمومی/فرمول‌ها/بکاپ) فیلد tpp_section دارند' );
check( false !== strpos( (string) $set_src, 'value="backup"' ) && false !== strpos( (string) $set_src, 'value="formulas"' ) && false !== strpos( (string) $set_src, 'value="general"' ), 'مقادیر بخش‌ها درست هستند' );

// ===== ۵) موتور محاسبه زنده: وابسته‌آگاه =====
echo "== 5) موتور محاسبه زنده (JS) ==\n";
$js_src = file_get_contents( __DIR__ . '/../build/tpp_salary/admin/js/tpp-salary-admin.js' );
check( false !== strpos( (string) $js_src, 'formulaSources' ), 'استخراج منبع‌های فرمول (formulaSources) اضافه شد' );
check( false !== strpos( (string) $js_src, 'depChanged' ), 'بازمحاسبه وابسته به تغییر منبع است (depChanged)' );
check( false === strpos( (string) $js_src, 'if ($inp.attr(\'data-manual\') === \'1\') { return; }' ), 'رفتار قدیمی «فریز همیشگی فیلد دستی» حذف شد' );
check( false !== strpos( (string) $js_src, "data-manual') === '1' && !depChanged" ), 'فیلد دستی فقط تا تغییر منبع معتبر است' );
check( false !== strpos( (string) $js_src, 'pass < 2' ), 'انتشار زنجیره‌ای وابستگی‌ها با دو گذر' );

echo $fail ? "\n>>> FAIL ($fail)\n" : "\n>>> ALL PASS\n";
exit( $fail );
