<?php
/**
 * تست E2E با فایل‌های واقعی پیوست کاربر (نسخه 1.6.0)
 *  1) ورود گروهی کارمندان از employees-sample-completed-checked.xlsx (۷۶ کارمند)
 *  2) ورود گروهی حقوق از salary-records-sample-completed-checked.xlsx (۷۶ رکورد)
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

// مراکز واقعی فایل کاربر
global $wpdb;
$now = current_time( 'mysql' );
foreach ( array( 'بومهن', 'رودهن', 'ال اسحاق', 'شهدای گمنام', 'آزادگان' ) as $cn ) {
        $wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => $cn, 'created_at' => $now ), array( '%s', '%s' ) );
}
check( count( tpp_salary_get_centers() ) === 5, '۵ مرکز ساخته شد' );

// ===== 1) ورود گروهی کارمندان =====
echo "== 1) ورود گروهی کارمندان (فایل واقعی کاربر) ==\n";
$rows = TppSalary_Xlsx_Reader::read( '/home/z/my-project/upload/employees-sample-completed-checked.xlsx' );
check( ! is_wp_error( $rows ) && count( $rows ) === 77, 'خواندن فایل کارمندان: ۷۶ سطر داده + عنوان' );

$res = TppSalary_Import::process_employees( $rows, false );
check( ! is_wp_error( $res ), 'process_employees بدون خطا (باگ empty(display_name) رفع شد)' );
if ( is_wp_error( $res ) ) { echo "  ERROR: " . $res->get_error_message() . "\n"; exit(1); }
check( $res['ok'] === 76, "۷۶ سطر موفق (دریافتی: {$res['ok']})" );
check( $res['fail'] === 0, "۰ سطر خطا (دریافتی: {$res['fail']})" );
check( $res['created'] === 76, "۷۶ کاربر جدید (دریافتی: {$res['created']})" );
foreach ( $res['log'] as $lg ) {
        if ( 'خطا' === $lg['status'] ) { echo "  سطر {$lg['row']}: {$lg['message']}\n"; }
}
$emps = tpp_salary_get_employees();
check( count( $emps ) === 76, '۷۶ کارمند در سیستم' );

// بررسی پروفایل کارمند اول
$u1 = TppSalary_Import::find_employee_by_name( 'اسماعیل کمال آبادی' );
check( $u1, 'کارمند اول با نام یافت شد' );
if ( $u1 ) {
        $p = tpp_salary_get_profile( $u1->ID );
        check( '5593757' === (string) $p['daily_wage'], 'دستمزد روزانه = 5593757' );
        check( abs( (float) $p['insurance_rate'] - 7.0 ) < 0.001, 'نرخ بیمه کسری اکسل (7E-2) به ۷ درصد تبدیل شد: ' . $p['insurance_rate'] );
        check( 'نیروی خدمات' === $p['job_title'], 'عنوان شغلی ذخیره شد' );
        check( 'پراید' === $p['vehicle_type'], 'نوع خودرو ذخیره شد' );
        check( ! empty( $p['centers'] ), 'مرکز ذخیره شد' );
}

// ===== 2) ورود گروهی حقوق =====
echo "== 2) ورود گروهی حقوق (فایل واقعی کاربر) ==\n";
$rrows = TppSalary_Xlsx_Reader::read( '/home/z/my-project/upload/salary-records-sample-completed-checked.xlsx' );
check( ! is_wp_error( $rrows ) && count( $rrows ) === 77, 'خواندن فایل حقوق: ۷۶ سطر داده + عنوان' );

// نگاشت ستون‌ها را جداگانه بررسی کن
$hdr = $rrows[0];
$rec_res = TppSalary_Import::process_records( $rrows, true, false ); // dry-run اول
check( $rec_res['fail'] === 0, "dry-run: ۰ خطا (دریافتی: {$rec_res['fail']})" );
check( $rec_res['ok'] === 76, "dry-run: ۷۶ سطر آماده ثبت (دریافتی: {$rec_res['ok']})" );
foreach ( $rec_res['log'] as $lg ) {
        if ( 'خطا' === $lg['status'] ) { echo "  سطر {$lg['row']}: {$lg['message']}\n"; }
}

// ثبت واقعی
$rec_res2 = TppSalary_Import::process_records( $rrows, false, false );
check( $rec_res2['fail'] === 0, "ثبت واقعی: ۰ خطا (دریافتی: {$rec_res2['fail']})" );
check( $rec_res2['ok'] === 76, "ثبت واقعی: ۷۶ موفق (دریافتی: {$rec_res2['ok']})" );
foreach ( $rec_res2['log'] as $lg ) {
        if ( 'خطا' === $lg['status'] ) { echo "  سطر {$lg['row']}: {$lg['message']}\n"; }
}

// بدون تکرار — اجرای دوباره باید همه «به‌روزرسانی» باشد
$rec_res3 = TppSalary_Import::process_records( $rrows, false, false );
check( $rec_res3['fail'] === 0 && $rec_res3['ok'] === 76, 'اجرای دوباره: upsert بدون تکرار' );
$dup = $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) c FROM {$GLOBALS['wpdb']->prefix}tpp_salary_records" );
check( (int) $dup === 76, "تعداد رکورد در جدول = ۷۶ (دریافتی: {$dup})" );

// صحت مقادیر رکورد اول (اسماعیل کمال آبادی — مرداد ۱۴۰۵ بومهن)
$u1 = TppSalary_Import::find_employee_by_name( 'اسماعیل کمال آبادی' );
$rec = $GLOBALS['wpdb']->get_row( $GLOBALS['wpdb']->prepare(
        "SELECT * FROM {$GLOBALS['wpdb']->prefix}tpp_salary_records WHERE user_id = %d", $u1->ID ), ARRAY_A );
check( ! empty( $rec ), 'رکورد حقوق کارمند اول ثبت شده' );
if ( $rec ) {
        $vals = json_decode( $rec['payload'], true );
        check( abs( (float) $vals['insurable_default'] - 270697756 ) < 1, 'حقوق مشمول بیمه (پیش‌فرض) = 270697756: ' . ( isset( $vals['insurable_default'] ) ? $vals['insurable_default'] : '-' ) );
        check( abs( (float) $vals['insurable'] - 270697756 ) < 1, 'حقوق مشمول بیمه (رکورد) = 270697756: ' . ( isset( $vals['insurable'] ) ? $vals['insurable'] : '-' ) );
        check( abs( (float) $vals['holiday_rate'] - 1068385 ) < 1, 'مبلغ تعطیل کاری (نرخ) = 1068385: ' . ( isset( $vals['holiday_rate'] ) ? $vals['holiday_rate'] : '-' ) );
        check( abs( (float) $vals['gross'] - 381381596 ) < 1, 'حقوق ناخالص = 381381596: ' . ( isset( $vals['gross'] ) ? $vals['gross'] : '-' ) );
        check( abs( (float) $vals['net'] - 362432754 ) < 1, 'حقوق خالص = 362432754: ' . ( isset( $vals['net'] ) ? $vals['net'] : '-' ) );
        check( (int) $vals['work_days'] === 31, 'کارکرد = ۳۱ روز' );
        check( abs( (float) $vals['insurance_deduct'] - 18948842 ) < 1, 'کسر درصد بیمه = 18948842: ' . ( isset( $vals['insurance_deduct'] ) ? $vals['insurance_deduct'] : '-' ) );
}
// صحت مقادیر رکورد دوم (محمد غلامی — دارد حق اولاد و فرزند)
$u2 = TppSalary_Import::find_employee_by_name( 'محمد غلامی' );
$rec2 = $GLOBALS['wpdb']->get_row( $GLOBALS['wpdb']->prepare(
        "SELECT * FROM {$GLOBALS['wpdb']->prefix}tpp_salary_records WHERE user_id = %d", $u2->ID ), ARRAY_A );
check( ! empty( $rec2 ), 'رکورد حقوق کارمند دوم ثبت شده' );
if ( $rec2 ) {
        $v2 = json_decode( $rec2['payload'], true );
        check( abs( (float) $v2['child_allowance_rate'] - 16625550 ) < 1, 'حق اولاد هر فرزند = 16625550: ' . ( isset( $v2['child_allowance_rate'] ) ? $v2['child_allowance_rate'] : '-' ) );
        check( abs( (float) $v2['child_allowance'] - 33251100 ) < 1, 'حق اولاد (کل) = 33251100: ' . ( isset( $v2['child_allowance'] ) ? $v2['child_allowance'] : '-' ) );
        check( (int) $v2['children_count'] === 2, 'تعداد فرزند = ۲: ' . ( isset( $v2['children_count'] ) ? $v2['children_count'] : '-' ) );
        check( abs( (float) $v2['holiday_pay'] - 0 ) < 1 && isset( $v2['holiday_days'] ) && (int) $v2['holiday_days'] === 0, 'ستون دوم «مبلغ تعطیل کاری» → holiday_pay (نه تکرار نرخ)' );
}

// ساخت خودکار کارمند در ورود حقوق — رکورد کارمند غایب
echo "== 3) ساخت خودکار کارمند در ورود حقوق ==\n";
$rows_auto = array(
        array( 'سال', 'ماه', 'مرکز', 'نام و نام خانوادگی', 'دستمزد روزانه مرجع', 'کارکرد (تعداد روز)', 'حقوق پایه' ),
        array( '1405', 'مرداد', 'بومهن', 'کارمند تستی جدید', '1000000', '30', '—' ),
);
$auto_res = TppSalary_Import::process_records( $rows_auto, false, true );
check( $auto_res['ok'] === 1 && $auto_res['fail'] === 0, 'کارمند غایب خودکار ساخته و رکورد ثبت شد' );
foreach ( $auto_res['log'] as $lg ) { echo "  [{$lg['status']}] {$lg['message']}\n"; }

// خطای مرکز ناشناخته
$rows_bad = array(
        array( 'سال', 'ماه', 'مرکز', 'نام و نام خانوادگی' ),
        array( '1405', 'مرداد', 'مرکز ناشناخته', 'اسماعیل کمال آبادی' ),
);
$bad_res = TppSalary_Import::process_records( $rows_bad, true, true );
check( $bad_res['fail'] === 1, 'مرکز ناشناخته → خطای واضح' );

echo $fail ? "\n>>> FAIL\n" : "\n>>> ALL PASS\n";
exit( $fail );
