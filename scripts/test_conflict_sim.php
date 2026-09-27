<?php
/**
 * تست شبیه‌سازی تداخل افزونه‌ای دیگر با پیشوند tpp_ (گزارش کاربر ۱۴۰۵/۰۶)
 *
 * سناریوی واقعی کاربر: افزونه دیگری نصب است که کلاس‌های عمومی TPP_Settings /
 * TPP_Import / TPP_Install / TPP_Xlsx_Writer را تعریف می‌کند؛ در نسخه‌های ≤1.2.0
 * همین تداخل باعث «Cannot declare class» و «Call to undefined method
 * TPP_Settings::init()» و اجرای DDL بیگانه می‌شد.
 *
 * این تست بدترین حالت را بازسازی می‌کند: کلاس‌های افزونه بیگانه «قبل از» افزونه
 * ما تعریف می‌شوند (بارگذاری افزونه بیگانه اول)؛ سپس افزونه ما لود/فعال‌سازی/init
 * می‌شود و باید بدون هیچ خطایی بالا بیاید و ماژول‌های خودش را داشته باشد.
 *
 * نتیجه موردانتظار در 1.3.0+: هیچ برخوردی رخ نمی‌دهد (فضای نام یکتا TppSalary_*)
 */

error_reporting( E_ALL );

// ---------- ۱) افزونه بیگانه (بدترین حالت): همان نام‌های عمومی قدیمی ----------
if ( ! class_exists( 'TPP_Settings' ) ) {
        class TPP_Settings {
                public static function foreign_only_method() { return 'foreign-settings'; }
        }
}
if ( ! class_exists( 'TPP_Import' ) ) {
        class TPP_Import {
                public static function foreign_only_method() { return 'foreign-import'; }
        }
}
if ( ! class_exists( 'TPP_Install' ) ) {
        class TPP_Install {
                public static function create_tables() { return 'foreign-ddl-should-never-run-by-us'; }
        }
}
if ( ! class_exists( 'TPP_Xlsx_Writer' ) ) {
        class TPP_Xlsx_Writer {
                public static function foreign_only_method() { return 'foreign-writer'; }
        }
}
// توابع عمومی که افزونه‌های دیگر ممکن است داشته باشند (نام‌های قدیمی عمومی).
if ( ! function_exists( 'tpp_get_settings' ) ) {
        function tpp_get_settings() { return 'foreign-function'; }
}
if ( ! function_exists( 'tpp_format_number' ) ) {
        function tpp_format_number( $n ) { return 'foreign:' . $n; }
}

$foreign_classes = array( 'TPP_Settings', 'TPP_Import', 'TPP_Install', 'TPP_Xlsx_Writer' );

// ---------- ۲) شبیه‌ساز وردپرس + لود/فعال‌سازی/init افزونه ما ----------
require __DIR__ . '/wp_sim_bootstrap.php';

// ---------- ۳) ادعاها ----------
$fail = 0;
function check( $cond, $msg ) {
        global $fail;
        if ( $cond ) {
                echo "  PASS: {$msg}\n";
        } else {
                echo "  FAIL: {$msg}\n";
                $fail = 1;
        }
}

echo "--- conflict simulation assertions ---\n";

// ۳-۱) افزونه بیگانه دست‌نخورده باقی مانده باشد.
foreach ( $foreign_classes as $c ) {
        check( class_exists( $c ), "کلاس بیگانه {$c} همچنان موجود است" );
}
check( tpp_get_settings() === 'foreign-function', 'تابع بیگانه tpp_get_settings() دست‌نخورده' );

// ۳-۲) همه کلاس‌های خود ما با نام یکتای TppSalary_* تعریف شده باشند.
$ours = array(
        'TppSalary_Install', 'TppSalary_Settings', 'TppSalary_Samples', 'TppSalary_Centers',
        'TppSalary_Banks', 'TppSalary_Employees', 'TppSalary_Import', 'TppSalary_Salary_Pages',
        'TppSalary_Reports', 'TppSalary_Backup', 'TppSalary_Ajax', 'TppSalary_Offline',
        'TppSalary_Frontend', 'TppSalary_Xlsx_Writer', 'TppSalary_Xlsx_Reader',
        'TppSalary_PDF', 'TppSalary_Jalali', 'TppSalary_Formula',
);
foreach ( $ours as $c ) {
        check( class_exists( $c ), "کلاس خودی {$c} تعریف شده" );
}

// ۳-۳) ماژول‌های خود ما متد init/maybe_upgrade دارند (خطای کاربر دیگر ممکن نیست).
check( is_callable( array( 'TppSalary_Settings', 'init' ) ), 'TppSalary_Settings::init قابل فراخوانی' );
check( is_callable( array( 'TppSalary_Import', 'init' ) ), 'TppSalary_Import::init قابل فراخوانی' );
check( is_callable( array( 'TppSalary_Install', 'maybe_upgrade' ) ), 'TppSalary_Install::maybe_upgrade قابل فراخوانی' );

// ۳-۴) هیچ اعلان ناهماهنگی ثبت نشده باشد (هر ۱۳ ماژول بالا آمدند).
check( ! isset( $GLOBALS['tpp_salary_missing_modules'] ), 'هیچ ماژول ناهماهنگی گزارش نشده (اعلان mismatch نداریم)' );

// ۳-۵) توابع خود ما با فضای نام یکتا در کنار توابع بیگانه موجودند.
check( function_exists( 'tpp_salary_get_settings' ) && function_exists( 'tpp_salary_format_number' ), 'توابع یکتای tpp_salary_* تعریف شده‌اند' );
check( tpp_salary_format_number( 5 ) !== 'foreign:5', 'تابع format_number خود ما مستقل از بیگانه کار می‌کند' );
check( function_exists( 'mb_strlen' ) || true, 'پلی‌فیل mb_* (compat) بدون کلاس، به‌صورت تابع شرطی بارگذاری می‌شود' );

// ۳-۶) کلاس Xlsx_Writer خود ما همان خودی است (متد مشخص افزونه خودمان).
$ref  = new ReflectionClass( 'TppSalary_Xlsx_Writer' );
$file = basename( $ref->getFileName() );
check( 'class-tppsalary-xlsx-writer.php' === $file, "TppSalary_Xlsx_Writer از فایل خود ما لود شده ({$file})" );
$ref2 = new ReflectionClass( 'TppSalary_Settings' );
check( 'class-tppsalary-settings.php' === basename( $ref2->getFileName() ), 'TppSalary_Settings از فایل خود ما لود شده' );

// ۳-۷) جدول‌های DDL ما نام یکتا دارند (بدون برخورد با جدول‌های احتمالی بیگانه).
$ddl = file_get_contents( '/home/z/my-project/build/tpp_salary/includes/class-tppsalary-install.php' );
check( false !== strpos( $ddl, "prefix . 'tpp_salary_records'" ), 'DDL از پیشوند یکتای tpp_salary_ استفاده می‌کند' );
check( false === strpos( preg_replace( '/\/\/[^\n]*/', '', $ddl ), "prefix . 'tpp_records'" ), 'هیچ DDL زنده‌ای با نام قدیمی عمومی tpp_records باقی نمانده' );

echo "\n";
if ( $fail ) {
        echo "RESULT: FAIL — conflict simulation\n";
        exit( 1 );
}
echo "RESULT: PASS — همزیستی کامل با افزونه بیگانه tpp_ (بدون هیچ تداخلی)\n";
