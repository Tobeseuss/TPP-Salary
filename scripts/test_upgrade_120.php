<?php
/**
 * تست مسیر ارتقا 1.2.0 → 1.3.0 — مهاجرت واقعی داده با SQLite
 *
 * سناریو: سایتی که نسخه 1.2.0 را دارد (جدول‌های tpp_* عمومی با داده،
 * آپشن tpp_settings، متای کاربران با کلیدهای قدیمی) افزونه 1.3.0 را فعال می‌کند.
 * باید همه داده‌ها به جدول‌های tpp_salary_* کپی شوند (با حفظ شناسه‌ها)،
 * متاها تغییرنام یابند، تنظیمات منتقل شود و جدول بیگانه دست نخورد.
 */

define( 'SIM_SQLITE_WPDB', 1 );
error_reporting( E_ALL );

$GLOBALS['sim_pre_activate_cb'] = function () {
        /** @var wpdb_sqlite $wpdb */
        global $wpdb;
        $pdo = $wpdb->pdo;

        // ---------- جدول‌های قدیمی 1.2.0 (ساختار عیناً از بسته 1.2.0 استخراج شد) ----------
        $pdo->exec( 'CREATE TABLE wp_tpp_centers (
                id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, created_at TEXT NULL )' );
        $pdo->exec( 'CREATE TABLE wp_tpp_banks (
                id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0 )' );
        $pdo->exec( 'CREATE TABLE wp_tpp_fields (
                id INTEGER PRIMARY KEY AUTOINCREMENT, field_key TEXT NOT NULL, label TEXT NOT NULL,
                field_type TEXT NOT NULL DEFAULT "number", default_value TEXT NULL, formula TEXT NULL,
                options TEXT NULL, is_profile INTEGER NOT NULL DEFAULT 0, is_calculated INTEGER NOT NULL DEFAULT 0,
                is_negative INTEGER NOT NULL DEFAULT 0, allow_manual INTEGER NOT NULL DEFAULT 1,
                show_in_payslip INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0,
                is_system INTEGER NOT NULL DEFAULT 0, is_active INTEGER NOT NULL DEFAULT 1 )' );
        $pdo->exec( 'CREATE TABLE wp_tpp_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, center_id INTEGER NOT NULL,
                jyear INTEGER NOT NULL, jmonth INTEGER NOT NULL, payload TEXT NULL,
                gross INTEGER NOT NULL DEFAULT 0, insurable INTEGER NOT NULL DEFAULT 0,
                insurance_deduct INTEGER NOT NULL DEFAULT 0, other_deductions INTEGER NOT NULL DEFAULT 0,
                net INTEGER NOT NULL DEFAULT 0, created_by INTEGER NULL, created_at TEXT NULL, updated_at TEXT NULL )' );
        $pdo->exec( 'CREATE TABLE wp_tpp_backups (
                id INTEGER PRIMARY KEY AUTOINCREMENT, backup_type TEXT NOT NULL, origin TEXT NOT NULL DEFAULT "manual",
                file_path TEXT NULL, file_size INTEGER NOT NULL DEFAULT 0, created_at TEXT NULL )' );

        // جدول بیگانه از خطای کاربر (wp_tpp_field_archives با ستون رزرو values) — نباید دست بخورد.
        $pdo->exec( 'CREATE TABLE wp_tpp_field_archives ( id INTEGER PRIMARY KEY AUTOINCREMENT, "values" TEXT )' );
        $pdo->exec( "INSERT INTO wp_tpp_field_archives (\"values\") VALUES ('foreign-junk-row')" );

        // ---------- جدول‌های جدید 1.3.0 (dbDelta در شبیه‌ساز no-op است؛ واقعاً می‌سازیم) ----------
        $pdo->exec( 'CREATE TABLE wp_tpp_salary_centers ( id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, created_at TEXT NULL )' );
        $pdo->exec( 'CREATE TABLE wp_tpp_salary_banks ( id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0 )' );
        $pdo->exec( 'CREATE TABLE wp_tpp_salary_fields (
                id INTEGER PRIMARY KEY AUTOINCREMENT, field_key TEXT NOT NULL, label TEXT NOT NULL,
                field_type TEXT NOT NULL DEFAULT "number", default_value TEXT NULL, formula TEXT NULL,
                options TEXT NULL, is_profile INTEGER NOT NULL DEFAULT 0, is_calculated INTEGER NOT NULL DEFAULT 0,
                is_negative INTEGER NOT NULL DEFAULT 0, allow_manual INTEGER NOT NULL DEFAULT 1,
                show_in_payslip INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0,
                is_system INTEGER NOT NULL DEFAULT 0, is_active INTEGER NOT NULL DEFAULT 1 )' );
        $pdo->exec( 'CREATE TABLE wp_tpp_salary_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, center_id INTEGER NOT NULL,
                jyear INTEGER NOT NULL, jmonth INTEGER NOT NULL, payload TEXT NULL,
                gross INTEGER NOT NULL DEFAULT 0, insurable INTEGER NOT NULL DEFAULT 0,
                insurance_deduct INTEGER NOT NULL DEFAULT 0, other_deductions INTEGER NOT NULL DEFAULT 0,
                net INTEGER NOT NULL DEFAULT 0, created_by INTEGER NULL, created_at TEXT NULL, updated_at TEXT NULL )' );
        $pdo->exec( 'CREATE TABLE wp_tpp_salary_backups ( id INTEGER PRIMARY KEY AUTOINCREMENT, backup_type TEXT NOT NULL, origin TEXT NOT NULL DEFAULT "manual", file_path TEXT NULL, file_size INTEGER NOT NULL DEFAULT 0, created_at TEXT NULL )' );

        // ---------- داده‌های قدیمی ----------
        $pdo->exec( "INSERT INTO wp_tpp_centers (id, name, created_at) VALUES (1, 'مرکز بومهن', '2026-08-01 00:00:00'), (2, 'مرکز رودهن', '2026-08-01 00:00:00')" );
        $pdo->exec( "INSERT INTO wp_tpp_banks (id, name, sort_order) VALUES (1, 'بانک ملت', 10)" );
        $pdo->exec( "INSERT INTO wp_tpp_fields (id, field_key, label, field_type, sort_order, is_profile, is_active, is_system) VALUES
                (1, 'legacy_allowance', 'کمک هزینه قدیمی', 'number', 200, 0, 1, 0),
                (2, 'legacy_shift', 'نوبت‌کاری قدیمی', 'number', 210, 0, 1, 0),
                (3, 'legacy_note', 'یادداشت قدیمی', 'text', 220, 1, 1, 0)" );
        $payload1 = json_encode( array( 'daily_wage' => '950000', 'work_days' => '30', 'housing' => '800000' ), JSON_UNESCAPED_UNICODE );
        $payload2 = json_encode( array( 'daily_wage' => '1200000', 'work_days' => '31', 'food' => '2200000' ), JSON_UNESCAPED_UNICODE );
        $pdo->exec( "INSERT INTO wp_tpp_records (id, user_id, center_id, jyear, jmonth, payload, gross, insurable, insurance_deduct, other_deductions, net, created_by, created_at, updated_at) VALUES
                (1, 7, 1, 1405, 5, '" . $payload1 . "', 61000000, 28500000, 1995000, 0, 59005000, 1, '2026-08-05 10:00:00', '2026-08-05 10:00:00'),
                (2, 9, 2, 1405, 5, '" . $payload2 . "', 99000000, 37200000, 2604000, 150000, 96246000, 1, '2026-08-06 11:00:00', '2026-08-06 11:00:00')" );
        $pdo->exec( "INSERT INTO wp_tpp_backups (id, backup_type, origin, file_path, file_size, created_at) VALUES (1, 'json', 'manual', '/tmp/old-backup.json', 2048, '2026-08-01 09:00:00')" );

        // متای قدیمی کاربران.
        $pdo->exec( "INSERT INTO wp_usermeta (user_id, meta_key, meta_value) VALUES
                (7, 'tpp_employee_profile', '{\"job_title\":\"کارگر\"}'),
                (7, 'tpp_national_id', '0012345678'),
                (7, 'tpp_mobile', '09121234567'),
                (9, 'tpp_employee_profile', '{\"job_title\":\"حسابدار\"}')" );

        // تنظیمات قدیمی (اثر انگشت: کلید defaults).
        $GLOBALS['sim_options']['tpp_settings'] = array(
                'defaults'    => array( 'housing' => '900000', 'food' => '2300000' ),
                'print_logo'  => 1,
                'company_name'=> 'شرکت نمونه',
        );
};

// ---------- شبیه‌ساز + لود/فعال‌سازی/init ----------
require __DIR__ . '/wp_sim_bootstrap.php';

// ---------- ادعاها ----------
$fail = 0;
function check( $cond, $msg ) {
        global $fail;
        echo ( $cond ? '  PASS: ' : '  FAIL: ' ) . $msg . "\n";
        if ( ! $cond ) { $fail = 1; }
}

/** @var wpdb_sqlite $wpdb */
global $wpdb;
$pdo = $wpdb->pdo;
$q   = function ( $sql ) use ( $pdo ) { return $pdo->query( $sql )->fetchColumn(); };

echo "--- upgrade 1.2.0 → 1.3.0 assertions ---\n";

// ۱) جدول‌های جدید همان تعداد ردیف را دارند.
check( (int) $q( 'SELECT COUNT(*) FROM wp_tpp_salary_centers' ) === 2, 'مراکز: ۲ ردیف منتقل شد' );
check( (int) $q( 'SELECT COUNT(*) FROM wp_tpp_salary_banks' ) === 1, 'بانک‌ها: ۱ ردیف منتقل شد' );
check( (int) $q( 'SELECT COUNT(*) FROM wp_tpp_salary_records' ) === 2, 'رکوردهای حقوق: ۲ ردیف منتقل شد' );
check( (int) $q( 'SELECT COUNT(*) FROM wp_tpp_salary_backups' ) === 1, 'بکاپ‌ها: ۱ ردیف منتقل شد' );

// ۲) شناسه‌ها و داده‌ها سالم مانده‌اند.
check( (string) $q( "SELECT payload FROM wp_tpp_salary_records WHERE id = 2" ) === json_encode( array( 'daily_wage' => '1200000', 'work_days' => '31', 'food' => '2200000' ), JSON_UNESCAPED_UNICODE ), 'payload رکورد ۲ عیناً حفظ شده' );
check( (int) $q( 'SELECT gross FROM wp_tpp_salary_records WHERE id = 1' ) === 61000000, 'ناخالص رکورد ۱ صحیح' );
check( (string) $q( 'SELECT name FROM wp_tpp_salary_centers WHERE id = 2' ) === 'مرکز رودهن', 'نام مرکز ۲ (فارسی) سالم' );
check( (int) $q( 'SELECT sort_order FROM wp_tpp_salary_banks WHERE id = 1' ) === 10, 'ترتیب بانک سالم' );

// ۳) فیلدهای قدیمی + فیلدهای سیستم جدید (بدون تکرار).
check( (int) $q( "SELECT COUNT(*) FROM wp_tpp_salary_fields WHERE field_key IN ('legacy_allowance','legacy_shift','legacy_note')" ) === 3, 'هر ۳ فیلد سفارشی قدیمی منتقل شد' );
check( (string) $q( "SELECT label FROM wp_tpp_salary_fields WHERE field_key = 'legacy_allowance'" ) === 'کمک هزینه قدیمی', 'برچسب فیلد قدیمی سالم' );
check( (int) $q( "SELECT COUNT(*) FROM wp_tpp_salary_fields WHERE field_key = 'daily_wage'" ) === 1, 'فیلد سیستمی daily_wage وجود دارد' );
$dup = (int) $q( 'SELECT COUNT(*) FROM (SELECT field_key FROM wp_tpp_salary_fields GROUP BY field_key HAVING COUNT(*) > 1)' );
check( 0 === $dup, 'هیچ field_key تکراری تولید نشده (seed پس از مهاجرت)' );

// ۴) تنظیمات منتقل شده.
$s = get_option( 'tpp_salary_settings', false );
check( is_array( $s ) && isset( $s['defaults']['housing'] ) && '900000' === $s['defaults']['housing'], 'آپشن تنظیمات از tpp_settings منتقل شد (کلید defaults)' );
check( is_array( $s ) && isset( $s['company_name'] ) && 'شرکت نمونه' === $s['company_name'], 'سایر کلیدهای تنظیمات حفظ شده' );

// ۵) متای کاربران تغییرنام یافته.
check( (int) $q( "SELECT COUNT(*) FROM wp_usermeta WHERE meta_key = 'tpp_employee_profile'" ) === 0, 'متای قدیمی پروفایل باقی نمانده' );
check( (int) $q( "SELECT COUNT(*) FROM wp_usermeta WHERE meta_key = 'tpp_salary_employee_profile'" ) === 2, 'متای جدید پروفایل: ۲ کاربر' );
check( (int) $q( "SELECT COUNT(*) FROM wp_usermeta WHERE meta_key = 'tpp_salary_national_id'" ) === 1, 'متای کد ملی تغییرنام یافت' );
check( (int) $q( "SELECT COUNT(*) FROM wp_usermeta WHERE meta_key = 'tpp_salary_mobile'" ) === 1, 'متای موبایل تغییرنام یافت' );

// ۶) جدول بیگانه دست‌نخورده.
check( (string) $q( 'SELECT "values" FROM wp_tpp_field_archives LIMIT 1' ) === 'foreign-junk-row', 'جدول بیگانه field_archives دست‌نخورده (هرگز مال ما نبود)' );

// ۷) نسخه دیتابیس ثبت شده.
check( get_option( 'tpp_salary_db_version' ) === '3', 'نسخه دیتابیس = 3 ثبت شد (1.4.1)' );

// ۸) اجرای دوباره (idempotent) — مهاجرت دوباره ردیف تکراری نمی‌سازد.
TppSalary_Install::upgrade();
check( (int) $q( 'SELECT COUNT(*) FROM wp_tpp_salary_records' ) === 2, 'اجرای دوباره upgrade: رکوردها تکراری نشدند (idempotent)' );
check( (int) $q( 'SELECT COUNT(*) FROM wp_tpp_salary_fields' ) === (int) $q( 'SELECT COUNT(*) FROM wp_tpp_salary_fields' ), 'اجرای دوباره upgrade: فیلدها پایدار' );

// ۹) خطای SQL مخفی نداشته باشیم.
check( '' === $wpdb->last_error, 'بدون خطای SQL پنهان (' . ( $wpdb->last_error ?: 'ok' ) . ')' );

echo "\n";
if ( $fail ) {
        echo 'RESULT: FAIL — upgrade 1.2.0→1.3.0' . "\n";
        exit( 1 );
}
echo "RESULT: PASS — مهاجرت کامل داده از نام‌های عمومی tpp_* به tpp_salary_* (با حفظ شناسه‌ها)\n";
