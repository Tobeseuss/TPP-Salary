<?php
/**
 * تست‌های نسخه 1.6.3 — درخواست کاربر:
 * «یک دکمه در قسمت ثبت حقوق هر کارمند قرار بده در مرحله 3 به نام پر کردن فیلدهای بر اساس حقوق گذشته
 *  که کاربر بتواند با کلیک بر روی آن ماه و سالی را که میخواهد فیلد های حقوق ماه جدید بر اساس آن
 *  تکمیل شود را انتخاب کند و اعمال کند»
 *  ۱) دکمه + دیالوگ انتخاب سال/ماه در فرم مرحله ۳ (فقط همان‌جا)
 *  ۲) هسته سرور: خواندن رکورد گذشتهٔ همان کارمند (اولویت همان مرکز، جایگزین: هر مرکز دیگر)
 *  ۳) مقادیر قالب‌بندی‌شده هم‌سان با فرم + فهرست فیلدهای دستی + حالت مشمول بیمه
 *  ۴) اندپوینت AJAX ثبت‌شده + موتور JS بازمحاسبه (TPP.recalc) در دسترس
 *
 * اجرا: ./tools/php scripts/test_fixes_163.php
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
$now        = current_time( 'mysql' );
$plugin_dir = dirname( __DIR__ ) . '/build/tpp_salary';

echo "== 0) نسخه و اجزای ثبت‌شده ==\n";
check( defined( 'TPP_SALARY_VERSION' ) && '1.7.8' === TPP_SALARY_VERSION, 'نسخه افزونه 1.6.3 است' );
check( defined( 'TPP_SALARY_INSTALL_BUILD' ) && TPP_SALARY_INSTALL_BUILD === TPP_SALARY_VERSION, 'TPP_SALARY_INSTALL_BUILD هم‌سانی دارد' );
check( method_exists( 'TppSalary_Salary_Pages', 'past_salary_payload' ), 'هسته past_salary_payload موجود است' );
check( method_exists( 'TppSalary_Ajax', 'past_salary' ), 'هندلر AJAX موجود است' );
check( sim_has_handler( 'wp_ajax_tpp_salary_past_salary' ), 'اکشن wp_ajax_tpp_salary_past_salary ثبت شده' );

/* موتور JS باید recalc را در دسترس اسکریپت درون‌صفحه بگذارد. */
$js = file_get_contents( $plugin_dir . '/admin/js/tpp-salary-admin.js' );
check( false !== strpos( $js, 'TPP.recalc = recalc' ), 'TPP.recalc در موتور JS منتشر شده است' );

/* استایل‌های بخش جدید */
$css = file_get_contents( $plugin_dir . '/admin/css/tpp-salary-admin.css' );
check( false !== strpos( $css, '#tpp-past-fill-note' ) && false !== strpos( $css, '.tpp-past-toolbar' ), 'استایل نوار دکمه و اعلان در CSS موجود است' );

// ===== ۱) داده‌های آزمون =====
echo "== 1) داده‌های آزمون: دو مرکز + کارمند + رکورد گذشته ==\n";

$wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => 'مرکز الف ۱۶۳', 'created_at' => $now ), array( '%s', '%s' ) );
$wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => 'مرکز ب ۱۶۳', 'created_at' => $now ), array( '%s', '%s' ) );
$center_a = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}tpp_salary_centers WHERE name = 'مرکز الف ۱۶۳'" );
$center_b = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}tpp_salary_centers WHERE name = 'مرکز ب ۱۶۳'" );
check( $center_a > 0 && $center_b > 0, 'دو مرکز آزمون ساخته شد' );

$uid = (int) wp_insert_user( array( 'user_login' => '163emp0001', 'user_pass' => 'pass1234', 'display_name' => 'کارمند آزمون ۱۶۳', 'role' => 'tpp_salary_employee' ) );
check( $uid > 0, 'کارمند آزمون ساخته شد' );
tpp_salary_save_profile( $uid, array( 'full_name' => 'کارمند آزمون ۱۶۳', 'daily_wage' => 5593757, 'work_days' => 31, 'insurance_rate' => 7, 'centers' => array( $center_a, $center_b ) ) );

/* رکورد گذشته: مرداد ۱۴۰۴ در مرکز الف — حالت غیر فرمولی (مشمول بیمه دستی). */
$payload_src = array(
        'base_salary' => 250000000, 'housing' => 12000000, 'food' => 8000000,
        'seniority' => 15000000, 'marriage' => 7000000, 'child_allowance' => 33251100,
        'overtime_pay' => 15000000, 'holiday_pay' => 999000, 'commute' => 5000000,
        'absence_penalty' => 0, 'work_deduction' => 0, 'other' => 10683840,
        'gross' => 381381596, 'insurable' => 270697756, 'insurance_deduct' => 18948842,
        'other_deductions' => 500000, 'net' => 362432754,
);
$res_src = TppSalary_Salary_Pages::upsert_record( $uid, $center_a, 1404, 5, $payload_src, false, array() );
check( is_array( $res_src ) && 'created' === $res_src['status'], 'رکورد مبدأ 1404/05 در مرکز الف ثبت شد' );

// ===== ۲) هسته past_salary_payload =====
echo "== 2) هسته past_salary_payload ==\n";

$p = TppSalary_Salary_Pages::past_salary_payload( $uid, $center_a, 1404, 5 );
check( is_array( $p ), 'پیلود برمی‌گردد' );
check( true === $p['same_center'] && $center_a === $p['center_id'], 'اولویت رکورد همان مرکز رعایت شده' );
check( 'مرکز الف ۱۶۳' === $p['center_name'], 'نام مرکز مبدأ در پاسخ هست' );
check( 'مرداد 1404' === $p['period_label'], 'برچسب دورهٔ مبدأ درست است (ارقام انگلیسی): ' . $p['period_label'] );
check( '250,000,000' === $p['values']['base_salary'], 'مقدار عددی قالب‌بندی‌شده هم‌سان با فرم: ' . $p['values']['base_salary'] );
check( '362,432,754' === $p['values']['net'], 'خالص پرداختی قالب‌بندی‌شده: ' . $p['values']['net'] );
check( '10,683,840' === $p['values']['other'], 'فیلد سایر هم قالب‌بندی شده' );
/* ارقام لاتین (بدون ۰-۹ فارسی) تا TPP.parseNum مستقیم بخواند */
check( 1 === preg_match( '/^[0-9,]+$/', $p['values']['gross'] ), 'ارقام لاتین با جداکننده هزار (بدون رقم فارسی)' );
/* فیلدهای فقط‌پروفایلی نباید در پیلود باشند */
check( ! isset( $p['values']['job_title'] ) && ! isset( $p['values']['full_name'] ), 'فیلدهای پروفایلی از پیلود حذف هستند' );
/* حالت غیر فرمولی → مشمول بیمه دستی است */
check( in_array( 'insurable', $p['manual'], true ), 'مشمول بیمه در فهرست فیلدهای دستی است' );
check( 'profile' === $p['insurable_mode'], 'حالت مشمول بیمه = profile' );

/* نبود رکورد */
$p_none = TppSalary_Salary_Pages::past_salary_payload( $uid, $center_a, 1399, 1 );
check( false === $p_none, 'دورهٔ بدون رکورد → false (پیام «یافت نشد»)' );

/* پارامتر ناقص */
check( is_wp_error( TppSalary_Salary_Pages::past_salary_payload( 0, $center_a, 1404, 5 ) ), 'user_id صفر → WP_Error' );
check( is_wp_error( TppSalary_Salary_Pages::past_salary_payload( $uid, $center_a, 1404, 0 ) ), 'ماه صفر → WP_Error' );

// ===== ۳) جایگزین: فقط رکورد مرکز دیگر موجود است =====
echo "== 3) جایگزینی مرکز (جابه‌جایی کارمند) ==\n";

$res_b = TppSalary_Salary_Pages::upsert_record( $uid, $center_b, 1404, 6, $payload_src, false, array() );
check( is_array( $res_b ) && 'created' === $res_b['status'], 'رکورد 1404/06 فقط در مرکز ب ثبت شد' );

$p_b = TppSalary_Salary_Pages::past_salary_payload( $uid, $center_a, 1404, 6 );
check( is_array( $p_b ), 'در نبود رکورد مرکز جاری، رکورد مرکز دیگر برمی‌گردد' );
check( false === $p_b['same_center'] && $center_b === $p_b['center_id'], 'علامت‌گذاری same_center=false با center_id مرکز ب' );
check( 'مرکز ب ۱۶۳' === $p_b['center_name'], 'نام مرکز مبدأ (ب) در پاسخ هست' );

/* اولویت: در یک دوره هر دو مرکز رکورد دارند → رکورد مرکز فرم جاری برنده است حتی اگر جدیدتر نباشد */
TppSalary_Salary_Pages::upsert_record( $uid, $center_a, 1404, 7, $payload_src, false, array() );
TppSalary_Salary_Pages::upsert_record( $uid, $center_b, 1404, 7, $payload_src, false, array() );
$p_both = TppSalary_Salary_Pages::past_salary_payload( $uid, $center_a, 1404, 7 );
check( $center_a === $p_both['center_id'] && true === $p_both['same_center'], 'در دورهٔ چندمرکزی، رکورد همان مرکز اولویت دارد' );
$p_both_b = TppSalary_Salary_Pages::past_salary_payload( $uid, $center_b, 1404, 7 );
check( $center_b === $p_both_b['center_id'], 'قرینه: فرم مرکز ب → رکورد مرکز ب' );

// ===== ۴) حالت فرمولی مشمول بیمه =====
echo "== 4) پیلود حالت فرمولی مشمول بیمه ==\n";

TppSalary_Salary_Pages::upsert_record( $uid, $center_a, 1404, 8, $payload_src, true, array() );
$p_f = TppSalary_Salary_Pages::past_salary_payload( $uid, $center_a, 1404, 8 );
check( 'formula' === $p_f['insurable_mode'], 'insurable_mode=formula منتقل می‌شود' );
check( ! in_array( 'insurable', $p_f['manual'], true ), 'در حالت فرمولی، مشمول بیمه دستی نیست' );

// ===== ۵) رندر فرم مرحله ۳ =====
echo "== 5) رندر فرم مرحله ۳ (دکمه + دیالوگ) ==\n";

$_REQUEST = $_GET = array(
        'page'      => 'tpp-salary-register',
        'user_id'   => (string) $uid,
        'center_id' => (string) $center_a,
        'jyear'     => '1404',
        'jmonth'    => '9',
);
ob_start();
TppSalary_Salary_Pages::render_register();
$html = ob_get_clean();

check( false !== strpos( $html, 'پر کردن فیلدها بر اساس حقوق گذشته' ), 'دکمه با عنوان درخواستی کاربر رندر شد' );
check( false !== strpos( $html, 'id="tpp-past-fill"' ), 'شناسه دکمه tpp-past-fill' );
check( false !== strpos( $html, 'id="tpp-past-dialog"' ), 'دیالوگ انتخاب دوره موجود است' );
check( false !== strpos( $html, 'id="tpp-past-year"' ) && false !== strpos( $html, 'id="tpp-past-month"' ), 'انتخابگرهای سال و ماه در دیالوگ' );
check( false !== strpos( $html, 'id="tpp-past-apply"' ), 'دکمه اعمال در دیالوگ' );
check( false !== strpos( $html, 'id="tpp-past-result"' ) && false !== strpos( $html, 'id="tpp-past-fill-note"' ), 'ناحیه پیام نتیجه/اعلان' );
check( false !== strpos( $html, "action: 'tpp_salary_past_salary'" ), 'درخواست AJAX به اندپوینت جدید' );
check( false !== strpos( $html, 'type="button"' ), 'دکمه از نوع button است (فرم را ارسال نمی‌کند)' );
/* پیش‌فرض دیالوگ = ماه قبل از دوره فرم (آبان ۱۴۰۴ ← دوره فرم آذر ۱۴۰۴)
 * خروجی وردپرس واقعی: selected='selected' (کوتیشن تکی، مثل هسته WP) */
check( false !== strpos( $html, 'value="8" selected=\'selected\'' ), 'پیش‌فرض ماه دیالوگ = ماه قبلی (آبان)' );
check( false === strpos( $html, 'value="9" selected=\'selected\'' ), 'ماه خودِ دوره فرم به‌عنوان مبدأ پیش‌فرض نیست' );
/* سال مبدأ پیش‌فرض باید ۱۴۰۴ باشد (نه ۱۳۹۹) */
preg_match( '/<select id="tpp-past-year"[^>]*>(.*?)<\/select>/s', $html, $ym );
$year_sel_ok = isset( $ym[1] ) && false !== strpos( $ym[1], 'value="1404" selected=\'selected\'' );
check( $year_sel_ok, 'سال پیش‌فرض دیالوگ ۱۴۰۴ است' );
/* ارقام فارسی سال‌ها + نام ماه‌ها */
check( false !== strpos( $html, 'مهر' ) && false !== strpos( $html, 'آبان' ), 'نام ماه‌های شمسی در دیالوگ' );
/* اسکریپت اعمال: پرکردن فیلدها + بازمحاسبه */
check( false !== strpos( $html, 'TPP.recalc()' ), 'اسکریپت اعمال، بازمحاسبه موتور را فراخوانی می‌کند' );
check( false !== strpos( $html, 'insurable_mode' ), 'حالت مشمول بیمه در اسکریپت اعمال لحاظ شده' );

/* مرحله ۱ (بدون user_id) نباید دکمه را داشته باشد */
$_REQUEST = $_GET = array( 'page' => 'tpp-salary-register' );
ob_start();
TppSalary_Salary_Pages::render_register();
$html_step1 = ob_get_clean();
check( false === strpos( $html_step1, 'tpp-past-fill' ), 'دکمه فقط در مرحله ۳ است (مرحله ۱ فاقد دکمه)' );

/* ویرایش رکورد موجود هم دکمه را دارد (سازگاری) */
$_REQUEST = $_GET = array(
        'page'      => 'tpp-salary-register',
        'user_id'   => (string) $uid,
        'center_id' => (string) $center_a,
        'jyear'     => '1404',
        'jmonth'    => '5',
);
ob_start();
TppSalary_Salary_Pages::render_register();
$html_edit = ob_get_clean();
check( false !== strpos( $html_edit, 'tpp-past-fill' ), 'حالت ویرایش رکورد موجود هم دکمه را دارد' );

echo $fail ? "\n>>> FAIL ($fail)\n" : "\n>>> ALL PASS\n";
exit( $fail );
