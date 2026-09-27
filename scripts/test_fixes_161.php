<?php
/**
 * تست‌های نسخه 1.6.1 — درخواست‌های کاربر:
 *  ۱) PDF گزارش لیست حقوق: همه ردیف‌ها در یک صفحه (بدون انتقال سطر به صفحه بعد)،
 *     فقط ستون‌های اضافی به صفحات بعد؛ متا (سال/ماه/مرکز/واحد) یک‌بار در سربرگ.
 *  ۲) اکسل ستونی: نام ستون = نام کارمند، سطرها = فقط عناوین حقوق.
 *  ۳) صفحه‌بندی نتایج جستجو (کمکی مشترک + فهرست کارمندان).
 *
 * اجرا: ./tools/php scripts/test_fixes_161.php
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

echo "== 0) آماده‌سازی ==\n";
$now = current_time( 'mysql' );
$wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => 'مرکز ۱۶۱', 'created_at' => $now ), array( '%s', '%s' ) );
$center_id = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}tpp_salary_centers LIMIT 1" );
check( $center_id > 0, 'مرکز ساخته شد' );

$names = array( 'علی محمدی', 'زهرا کریمی', 'رضا احمدی', 'مریم نادری', 'حسین صادقی', 'سارا رضایی', 'امیر تهرانی' );
$users_ids = array();
foreach ( $names as $i => $name ) {
        $nid = '16100000' . ( $i + 10 );
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
check( 7 === count( $recs ), '۷ رکورد دوره: ' . count( $recs ) );

// ===== ۱) PDF: همه ردیف‌ها در یک صفحه =====
echo "== 1) PDF: همه ردیف‌ها در یک صفحه ==\n";

$fit = TppSalary_Reports::report_pdf_fit( $recs, 12 );
check( isset( $fit['rh'], $fit['fs_body'], $fit['fs_head'], $fit['total_rows'], $fit['usable_h'] ), 'کلیدهای فیت موجود: rh=' . round( $fit['rh'], 2 ) . 'mm fs_body=' . $fit['fs_body'] . ' total_rows=' . $fit['total_rows'] );
check( 10.0 === (float) $fit['fs_body'] && 10.0 === (float) $fit['fs_head'], 'نسخه 1.7.3: فونت ثابت 10pt (fs_body=fs_head=10) — دیگر فونت ریز تولید نمی‌شود' );
check( $fit['rh'] >= 4.6, 'نسخه 1.7.3: ارتفاع سطر حداقل 4.6mm برای فونت Bold 10pt (کف جدید): ' . round( $fit['rh'], 2 ) );
check( $fit['per'] === $fit['max_fit'], 'نسخه 1.7.3: ستون‌ها تا گنجایش کامل صفحه پر می‌شوند (per=' . $fit['per'] . ' = max_fit=' . $fit['max_fit'] . ')' );

/* نسخه 1.7.3: total_rows = ۱ + فیلدهای چاپی (بدون فقط‌محاسباتی‌ها و همه‌صفرها) */
$ref_pf = new ReflectionMethod( 'TppSalary_Reports', 'printable_fields' );
$ref_pf->setAccessible( true );
$printable_count = count( $ref_pf->invoke( null, $recs ) );
check( $fit['total_rows'] === 1 + $printable_count, 'total_rows = سطر عناوین + فیلدهای چاپی (نسخه 1.7.3): ' . $fit['total_rows'] . ' = 1+' . $printable_count );

/* نسخه 1.7.3: حداقل فونت 10pt Bold اولویت دارد — اگر همه ردیف‌ها با ارتفاع سطر ≥4.6mm
 * جا شوند یک‌صفحه است؛ وگرنه سطرهای مازاد به صفحه بعد می‌روند (هدر تکرار می‌شود). */
$rows_fit = ( $fit['total_rows'] * $fit['rh'] <= $fit['usable_h'] + 0.01 );
if ( $rows_fit ) {
        check( true, 'همه ردیف‌ها در ارتفاع مفید جا می‌شوند: ' . $fit['total_rows'] . '×' . round( $fit['rh'], 2 ) . '=' . round( $fit['total_rows'] * $fit['rh'], 1 ) . ' ≤ ' . round( $fit['usable_h'], 1 ) . 'mm' );
} else {
        check( 4.6 === (float) $fit['rh'], 'ردیف‌ها با حداقل فونت جا نمی‌شوند → صفحه‌بندی سطرها (rh ثابت = ' . $fit['rh'] . 'mm، ' . $fit['total_rows'] . '×4.6=' . ( $fit['total_rows'] * 4.6 ) . ' > ' . round( $fit['usable_h'], 1 ) . 'mm)' );
}

/* نسخه 1.7.3: همه فونت‌ها Bold 10pt — فونت جاریِ موتور پس از رندر باید Vazirmatn-Bold با 10pt باشد
 * (tFPDF برای هر فونت ثبت‌شده حتی استفاده‌نشده شیء BaseFont می‌سازد، پس بازرسی بایت‌ای به‌تنهایی کافی نیست) */
$pdf_bold_probe = TppSalary_Reports::build_report_pdf( $recs, 1405, 5, 0, 12 );
$bold_probe_raw = $pdf_bold_probe->Output( 'S' );
check( false !== strpos( $bold_probe_raw, 'Vazirmatn-Bold' ), 'فونت Bold در PDF جاسازی شده است' );
$ref_cur = new ReflectionProperty( 'tFPDF', 'CurrentFont' );
$ref_cur->setAccessible( true );
$cur_font = $ref_cur->getValue( $pdf_bold_probe );
check( is_array( $cur_font ) && 'Vazirmatn-Bold' === $cur_font['name'], 'فونت جاری انتهای رندر گزارش = Vazirmatn-Bold (هیچ متنی با Regular نمی‌ماند)' );
$ref_size = new ReflectionProperty( 'tFPDF', 'FontSizePt' );
$ref_size->setAccessible( true );
check( 10.0 === (float) $ref_size->getValue( $pdf_bold_probe ), 'اندازه فونت جاری موتور = 10pt: ' . $ref_size->getValue( $pdf_bold_probe ) );

/* ساخت PDF واقعی — تعداد صفحات باید دقیقاً برابر بلوک‌های ستونی باشد (بدون صفحه اضافه سطرها) */
$pdf = TppSalary_Reports::build_report_pdf( $recs, 1405, 5, 0, 12 );
check( $pdf instanceof TppSalary_PDF, 'PDF ساخته شد' );
$w = $pdf->GetPageWidth(); $h = $pdf->GetPageHeight();
check( $w > $h && abs( $w - 297 ) < 1.5 && abs( $h - 210 ) < 1.5, 'A4 افقی (297×210mm)' );

/* تعداد صفحات = بلوک‌های ستونی × صفحات سطری (نسخه 1.7.3: اگر سطرها جا نشوند،
 * به صفحه بعد می‌روند و هدر ستونی در هر صفحه تکرار می‌شود) */
$row_pages = (int) max( 1, ceil( ( $fit['total_rows'] * $fit['rh'] ) / $fit['usable_h'] ) );
$pages_expected = (int) ceil( 7 / $fit['per'] ) * $row_pages;
$out_file = sys_get_temp_dir() . '/tpp161-report.pdf';
file_put_contents( $out_file, $pdf->Output( 'S' ) );
check( filesize( $out_file ) > 5000 && '%PDF-' === file_get_contents( $out_file, false, null, 0, 5 ), 'خروجی PDF سالم و غیرخالی' );
$raw = file_get_contents( $out_file );
$page_count = preg_match_all( '/\/Type\s*\/Page[^s]/', $raw );
check( $page_count === $pages_expected, 'صفحات = بلوک ستونی × صفحات سطری (' . $fit['per'] . ' ستون × ' . $row_pages . ' → ' . $pages_expected . ' صفحه): ' . $page_count );

/* نسخه 1.7.3: تنظیم ۴ در PDF نادیده گرفته می‌شود — ستون‌ها تا گنجایش پر می‌شوند */
$fit4 = TppSalary_Reports::report_pdf_fit( $recs, 4 );
check( $fit4['per'] === $fit4['max_fit'], 'نسخه 1.7.3: تنظیم ۴ → per=max_fit (پر شدن صفحه)' );

/* نسخه 1.7.3: حداقل فونت 10pt Bold اولویت دارد — با ۴۵ فیلد ارتفاع سطر زیر 4.6mm نمی‌رود
 * و سطرهای مازاد به صفحه بعد منتقل می‌شوند (هدر ستونی تکرار می‌شود). */
$rh_needed = $fit['usable_h'] / ( 1 + 45 );
check( $rh_needed < 4.6, 'با ۴۵ فیلد فیت یک‌صفحه‌ای زیر حد فونت است (rh لازم = ' . round( $rh_needed, 2 ) . 'mm < 4.6mm) — سطرهای مازاد صفحه‌بندی می‌شوند' );

// ===== ۲) متا یک‌بار در سربرگ + اکسل ستونی =====
echo "== 2) اکسل ستونی + متا فقط در سربرگ ==\n";

$xlsx = TppSalary_Reports::build_report_xlsx( $recs, 1405, 5, 0, 4 );
check( $xlsx instanceof TppSalary_Xlsx_Writer, 'سازنده اکسل ستونی قابل‌تست (build_report_xlsx)' );

$xfile = sys_get_temp_dir() . '/tpp161-report.xlsx';
file_put_contents( $xfile, $xlsx->to_string() );
check( filesize( $xfile ) > 2000, 'فایل xlsx غیرخالی' );

/* اعتبارسنجی سخت ساختار XLSX */
$z = new ZipArchive();
check( true === $z->open( $xfile ), 'xlsx باز می‌شود (ZIP سالم)' );
$sheet1 = $z->getFromName( 'xl/worksheets/sheet1.xml' );
$z->close();
check( false !== $sheet1 && false !== strpos( $sheet1, 'inlineStr' ), 'شیت اول موجود (رشته‌های inline)' );

/* سلول‌های متنی ستون A (inline string) */
preg_match_all( '/<c r="A(\d+)"[^>]*t="inlineStr"[^>]*><is><t[^>]*>(.*?)<\/t><\/is><\/c>/s', $sheet1, $a_cells, PREG_SET_ORDER );
$labels_col = array();
foreach ( $a_cells as $m ) {
        $labels_col[ (int) $m[1] ] = html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' );
}
check( ! in_array( 'سال', $labels_col, true ) && ! in_array( 'ماه', $labels_col, true ) && ! in_array( 'مرکز', $labels_col, true ) && ! in_array( 'واحد', $labels_col, true ), 'هیچ ردیف متایی (سال/ماه/مرکز/واحد) در ستون عناوین نیست' );
check( ( $labels_col[4] ?? '' ) === 'عناوین', 'سطر ۴ = هدر «عناوین» (بعد از عنوان + واحد + سطر خالی)' );
check( count( $labels_col ) === 3 + $printable_count, 'سطرهای جدول فقط عناوین حقوق چاپی هستند (نسخه 1.7.3): ' . count( $labels_col ) . ' = ۲ سربرگ + هدر + ' . $printable_count . ' فیلد' );

/* هدر ستون‌ها = نام کارمندان (قالب ستونی) — شیت ۱ چهار ستون، شیت ۲ بقیه */
$z = new ZipArchive();
$z->open( $xfile );
$sheet2 = $z->getFromName( 'xl/worksheets/sheet2.xml' );
$z->close();
check( false !== $sheet2, 'شیت دوم برای ستون‌های ادامه ساخته شد (صفحه‌بندی ستونی)' );
$emp_found = 0;
foreach ( $names as $n ) {
        if ( false !== strpos( $sheet1, $n ) ) {
                $emp_found++;
        }
}
check( 4 === $emp_found, 'شیت ۱ دقیقاً ۴ ستون کارمند دارد (per=4): ' . $emp_found );
$emp2 = 0;
foreach ( $names as $n ) {
        if ( false !== strpos( (string) $sheet2, $n ) ) {
                $emp2++;
        }
}
check( 7 === $emp_found + $emp2, 'همه ۷ کارمند بین شیت‌ها پخش شده‌اند: ' . ( $emp_found + $emp2 ) );
check( false !== strpos( $sheet1, 'واحد: ' ) && false !== strpos( $sheet1, 'لیست حقوق' ), 'واحد/دوره/مرکز یک‌بار در سربرگ بالای شیت' );

// ===== ۳) صفحه‌بندی نتایج جستجو =====
echo "== 3) صفحه‌بندی نتایج جستجو ==\n";

check( function_exists( 'tpp_salary_pagination' ) && function_exists( 'tpp_salary_list_per_page' ) && function_exists( 'tpp_salary_current_paged' ), 'توابع مشترک صفحه‌بندی موجود' );
check( 20 === tpp_salary_list_per_page(), 'per_page_list پیش‌فرض ۲۰' );

/* ۴۵ ردیف / ۲۰ در صفحه → ۳ صفحه + حفظ جستجو و فیلتر ماه */
$_GET = array( 'page' => 'tpp-salary-records', 's' => 'علی', 'jmonth' => '5' );
ob_start();
tpp_salary_pagination( 45, 20 );
$nav = ob_get_clean();
check( false !== strpos( $nav, 'paged=2' ) && false !== strpos( $nav, 'paged=3' ), 'لینک صفحات ۲ و ۳ ساخته شد' );
check( false === strpos( $nav, 'paged=4' ), 'صفحه ۴ وجود ندارد (۴۵/۲۰ → ۳ صفحه)' );
check( false !== strpos( $nav, 's=%D8%B9%D9%84%DB%8C' ), 'پارامتر جستجو s در لینک صفحات حفظ شده' );
check( false !== strpos( $nav, 'jmonth=5' ), 'پارامتر فیلتر ماه حفظ شده' );
check( false !== strpos( $nav, 'صفحه 1 از 3' ), 'برچسب «صفحه 1 از 3» با ارقام انگلیسی نمایش داده می‌شود' );

/* کمتر از یک صفحه → هیچ ناوبری‌ای چاپ نمی‌شود */
ob_start();
tpp_salary_pagination( 7, 20 );
$nav2 = ob_get_clean();
check( '' === trim( $nav2 ), 'با یک صفحه، ناوبری چاپ نمی‌شود' );

/* per_page_list از تنظیمات خوانده می‌شود */
$s = tpp_salary_get_settings();
$s['per_page_list'] = 3;
update_option( 'tpp_salary_settings', $s );
check( 3 === tpp_salary_list_per_page(), 'per_page_list از تنظیمات خوانده می‌شود (۳)' );

/* رندر واقعی فهرست کارمندان با صفحه‌بندی: ۷ کارمند / ۳ در صفحه */
$_GET = array( 'page' => 'tpp-salary-employees' );
ob_start();
TppSalary_Employees::render_list();
$html = ob_get_clean();
check( false !== strpos( $html, 'paged=2' ), 'فهرست کارمندان صفحه‌بندی دارد (۷ کارمند / ۳ در صفحه)' );
$row_count = substr_count( $html, 'ویرایش پروفایل' );
check( 3 === $row_count, 'فقط ۳ ردیف در صفحه اول رندر شد: ' . $row_count );
check( false !== strpos( $html, 'صفحه 1 از 3' ), 'نمایش «صفحه 1 از 3» در فهرست کارمندان (ارقام انگلیسی)' );

/* صفحه ۲ → ردیف‌های بعدی */
$_GET = array( 'page' => 'tpp-salary-employees', 'paged' => '2' );
ob_start();
TppSalary_Employees::render_list();
$html2 = ob_get_clean();
$row_count2 = substr_count( $html2, 'ویرایش پروفایل' );
check( 3 === $row_count2, 'صفحه ۲ هم ۳ ردیف دارد: ' . $row_count2 );
check( false !== strpos( $html2, 'صفحه 2 از 3' ), 'برچسب صفحه ۲ درست است (ارقام انگلیسی)' );

$s['per_page_list'] = 20;
update_option( 'tpp_salary_settings', $s );

echo $fail ? "\n>>> FAIL ($fail)\n" : "\n>>> ALL PASS\n";
exit( $fail );
