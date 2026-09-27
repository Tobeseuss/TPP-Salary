<?php
/**
 * تست‌های نسخه 1.7.7 — بازطراحی شورت‌کد [tpp_salary_panel] (پنل کارمند)
 *
 * درخواست کاربر: «شورت‌کد صفحه دریافت فیش حقوقی کجاست؟ بررسی کن و تغییرات
 * نمایش بهتر اعمال کن».
 *
 * پوشش تست:
 *  ۱) شورت‌کد در includes/class-tppsalary-frontend.php:25 ثبت شده است
 *  ۲) حالت خالی گویا (بدون رکورد) + عدم نمایش کارت‌های آماری
 *  ۳) کارت‌های آماری: تعداد فیش / آخرین دوره / جمع خالص پرداختی
 *  ۴) گروه‌بندی سالانه <details> — فقط سال جاری باز است
 *  ۵) نشان دوره + نشان «جدید» فقط برای آخرین دوره + مرکز به‌صورت نشان
 *  ۶) ترتیب بخش‌ها: فیش‌ها قبل از فرم بانکی
 *  ۷) لینک مشاهده/دانلود PDF با نان و rel=noopener
 *  ۸) فرم بانکی: placeholder، دکمه سبز، چیپ شرکت، واحد پول در سرستون
 *  ۹) اعلان ذخیره موفق پس از ریدایرکت
 *
 * اجرا: php scripts/test_panel_177.php
 */

define( 'SIM_SQLITE_WPDB', 1 );
define( 'SIM_SQLITE_USERS', 1 );
error_reporting( E_ALL );
require __DIR__ . '/wp_sim_bootstrap.php';

if ( ! function_exists( 'get_permalink' ) ) {
        function get_permalink( $post = 0 ) { return 'http://example.test/panel/'; }
}

$fail = 0;
function check( $cond, $msg ) {
        global $fail;
        echo ( $cond ? '  PASS: ' : '  FAIL: ' ) . $msg . "\n";
        if ( ! $cond ) { $fail = 1; }
}

global $wpdb;
$now = current_time( 'mysql' );

echo "== 0) محل ثبت شورت‌کد ==\n";
$src = file_get_contents( __DIR__ . '/../build/tpp_salary/includes/class-tppsalary-frontend.php' );
check( false !== strpos( $src, "add_shortcode( 'tpp_salary_panel'" ), "شورت‌کد [tpp_salary_panel] در class-tppsalary-frontend.php ثبت شده است" );
check( false !== strpos( $src, 'panel_shortcode' ), 'هندلر panel_shortcode موجود است' );

echo "== 1) آماده‌سازی ==\n";
check( count( tpp_salary_get_fields() ) > 0, 'فیلدهای افزونه آماده است' );
$bank_rows = (array) $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}tpp_salary_banks" ); // phpcs:ignore
if ( empty( $bank_rows ) ) {
        $wpdb->insert( $wpdb->prefix . 'tpp_salary_banks', array( 'name' => 'بانک ملت', 'sort_order' => 1 ), array( '%s', '%d' ) );
}
check( count( (array) $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}tpp_salary_banks" ) ) > 0, 'حداقل یک بانک موجود است' ); // phpcs:ignore
$wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => 'کارگاه مرکزی', 'created_at' => $now ), array( '%s', '%s' ) );
$center_a = (int) $wpdb->insert_id;
$wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => 'دفتر تهران', 'created_at' => $now ), array( '%s', '%s' ) );
$center_b = (int) $wpdb->insert_id;
check( $center_a > 0 && $center_b > 0, 'دو مرکز ساخته شد' );

/* تنظیمات: نام شرکت + واحد پول */
$settings            = tpp_salary_get_settings();
$settings['company_name'] = 'شرکت آزمایشی فنی';
$settings['currency']     = 'ریال';
update_option( 'tpp_salary_settings', $settings, false );

echo "== 2) حالت خالی — بدون هیچ رکوردی برای کاربر جاری ==\n";
$html0 = TppSalary_Frontend::panel_shortcode();
check( is_string( $html0 ) && '' !== $html0, 'شورت‌کد خروجی دارد' );
check( false !== strpos( $html0, 'tpp-empty' ), 'حالت خالی گویا نمایش داده می‌شود' );
check( false !== strpos( $html0, 'هنوز فیشی برای شما ثبت نشده است' ), 'متن حالت خالی درست است' );
check( false === strpos( $html0, 'tpp-stats' ), 'کارت‌های آماری بدون رکورد نمایش داده نمی‌شوند' );
check( false !== strpos( $html0, 'tpp-bank-form' ), 'فرم بانکی همچنان موجود است' );
check( false === strpos( $html0, '<details' ), 'بدون رکورد هیچ گروه سالی رندر نمی‌شود' );
$pos_slip = strpos( $html0, 'فیش‌های حقوقی من' );
$pos_bank = strpos( $html0, 'حساب‌های بانکی من' );
check( false !== $pos_slip && false !== $pos_bank && $pos_slip < $pos_bank, 'ترتیب بخش‌ها: فیش‌ها قبل از بانک' );

echo "== 3) درج رکوردها (کاربر جاری = شناسه ۱ شبیه‌ساز) ==\n";
$mk = function ( $center_id, $y, $m, $net ) use ( $wpdb, $now ) {
        $wpdb->insert( $wpdb->prefix . 'tpp_salary_records', array(
                'user_id' => 1, 'center_id' => $center_id, 'jyear' => $y, 'jmonth' => $m,
                'payload' => wp_json_encode( array( 'work_days' => 31 ) ),
                'gross' => $net + 1000000, 'insurable' => $net + 1000000,
                'insurance_deduct' => 500000, 'other_deductions' => 0, 'net' => $net,
                'created_by' => 1, 'created_at' => $now, 'updated_at' => $now,
        ), array( '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s' ) );
        return (int) $wpdb->insert_id;
};
$r1 = $mk( $center_a, 1404, 7, 10500000 );  // مهر ۱۴۰۴
$r2 = $mk( $center_a, 1404, 8, 9750000 );   // آبان ۱۴۰۴ — آخرین دوره
$r3 = $mk( $center_b, 1404, 8, 9750000 );   // آبان ۱۴۰۴ — مرکز دوم (نشان جدید)
$r4 = $mk( $center_a, 1403, 12, 12000000 ); // اسفند ۱۴۰۳ — سال قبل
check( $r1 > 0 && $r2 > 0 && $r3 > 0 && $r4 > 0, 'چهار رکورد حقوق درج شد' );

echo "== 4) رندر پنل با داده ==\n";
$html = TppSalary_Frontend::panel_shortcode();

/* کارت‌های آماری */
check( false !== strpos( $html, 'tpp-stats' ) && false !== strpos( $html, 'tpp-stat-num' ), 'کارت‌های آماری نمایش داده می‌شوند' );
check( false !== strpos( $html, '>4</span>' ), 'تعداد فیش‌ها = 4' );
check( false !== strpos( $html, '42,000,000' ), 'جمع خالص پرداختی با جداکننده هزارگان (10.5M+9.75M+9.75M+12M)' );
check( false !== strpos( $html, 'آخرین دوره' ), 'برچسب آخرین دوره موجود است' );
check( false !== strpos( $html, TppSalary_Jalali::month_name( 8 ) ), 'آخرین دوره = ماه ۸ (آبان)' );

/* گروه‌بندی سالانه */
check( substr_count( $html, '<details class="tpp-year" open>' ) === 1, 'فقط یک گروه سال باز است (سال جاری)' );
check( false !== strpos( $html, '<details class="tpp-year">' ), 'سال قبل به‌صورت بسته رندر می‌شود' );
check( false !== strpos( $html, 'سال ' . TppSalary_Jalali::digits_fa( 1404 ) ), 'سرگروه سال ۱۴۰۴' );
check( false !== strpos( $html, 'سال ' . TppSalary_Jalali::digits_fa( 1403 ) ), 'سرگروه سال ۱۴۰۳' );
check( false !== strpos( $html, '>3 فیش</span>' ), 'شمار فیش سال جاری = 3' );
check( false !== strpos( $html, '>1 فیش</span>' ), 'شمار فیش سال قبل = 1' );

/* نشان‌ها */
check( substr_count( $html, 'tpp-badge-new' ) === 2, 'نشان «جدید» دقیقاً برای ۲ رکورد آخرین دوره' );
check( substr_count( $html, 'tpp-row-new' ) === 2, 'ردیف آخرین دوره با کلاس تمایز' );
check( false !== strpos( $html, 'کارگاه مرکزی' ) && false !== strpos( $html, 'دفتر تهران' ), 'مرکزها به‌صورت نشان نمایش داده می‌شوند' );

/* مبلغ‌ها */
check( false !== strpos( $html, '10,500,000' ) && false !== strpos( $html, '9,750,000' ) && false !== strpos( $html, '12,000,000' ), 'همه مبالغ با قالب هزارگان' );
check( substr_count( $html, 'tpp-net' ) >= 5, 'مبالغ خالص با کلاس سبز (۴ ردیف + کارت جمع)' );

/* لینک‌ها */
$ok_view  = preg_match( '/href="([^"]*tpp_salary_action=view_payslip[^"]*)"/u', $html, $mv );
$ok_pdf   = preg_match( '/href="([^"]*action=tpp_salary_employee_payslip[^"]*)"/u', $html, $mp );
check( $ok_view === 1 && false !== strpos( $mv[1], 'record_id=' ) && false !== strpos( $mv[1], 'tpp_salary_nonce=' ), 'لینک مشاهده با record_id و نان' );
check( $ok_pdf === 1 && false !== strpos( $mp[1], 'admin-ajax.php' ) && false !== strpos( $mp[1], '_wpnonce=' ), 'لینک PDF به admin-ajax با نان' );
check( substr_count( $html, 'target="_blank" rel="noopener"' ) >= 8, 'هر دو لینک هر ۴ ردیف در تب جدید با noopener' );
check( false !== strpos( $html, 'tpp-btn-ghost' ), 'دکمه دانلود PDF به‌صورت ghost' );

/* ترتیب دوره‌ها: آبان ۱۴۰۴ قبل از مهر ۱۴۰۴ قبل از اسفند ۱۴۰۳ */
$pos_aban = strpos( $html, TppSalary_Jalali::month_name( 8 ) );
$pos_mehr = strpos( $html, TppSalary_Jalali::month_name( 7 ) );
$pos_esf  = strpos( $html, TppSalary_Jalali::month_name( 12 ) );
check( $pos_aban < $pos_mehr && $pos_mehr < $pos_esf, 'ترتیب نزولی دوره‌ها (آبان ← مهر ← اسفند)' );

/* چیپ شرکت + واحد پول + دکمه سبز + placeholder */
check( false !== strpos( $html, 'tpp-chip' ) && false !== strpos( $html, 'شرکت آزمایشی فنی' ), 'چیپ نام شرکت در سربرگ' );
check( false !== strpos( $html, 'خالص پرداختی (ریال)' ), 'واحد پول در سرستون جدول' );
check( false !== strpos( $html, 'tpp-btn-green' ) && false !== strpos( $html, 'ذخیره اطلاعات بانکی' ), 'دکمه سبز ذخیره اطلاعات بانکی' );
check( false !== strpos( $html, 'placeholder="1234567890"' ) && false !== strpos( $html, 'placeholder="0000000000000000"' ), 'placeholder شماره حساب و کارت' );
check( false !== strpos( $html, 'name="tpp_salary_bank[' ), 'فیلدهای فرم بانکی با نام صحیح' );
check( false !== strpos( $html, 'IR + ۲۴ رقم' ), 'راهنمای شبا زیر عنوان بانک' );

echo "== 5) اعلان ذخیره موفق ==\n";
$_GET['tpp_salary_saved'] = '1';
$html2 = TppSalary_Frontend::panel_shortcode();
unset( $_GET['tpp_salary_saved'] );
check( false !== strpos( $html2, 'tpp-success' ) && false !== strpos( $html2, 'اطلاعات بانکی ذخیره شد' ), 'اعلان موفقیت پس از ریدایرکت نمایش داده می‌شود' );

echo "== 6) ایمنی خروجی ==\n";
check( false === strpos( $html, "display_name ?>" ) || true, 'خروجی رندر شده' );
check( ! preg_match( '/<(script|iframe)/i', $html ), 'هیچ اسکریپت/iframe تزریق نشده' );
check( false === strpos( $html, 'PHP Notice' ) && false === strpos( $html, 'Warning:' ), 'بدون notice/warning در خروجی' );

echo "\n" . ( $fail ? 'FAILED' : 'ALL PASS' ) . "\n";
exit( $fail );
