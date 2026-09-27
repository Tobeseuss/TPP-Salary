<?php
/* دیباگ دو FAIL تست 141 — مرکز پروفایل کارمند خودکار + ثبت رکورد */
define( 'SIM_SQLITE_WPDB', 1 );
define( 'SIM_SQLITE_USERS', 1 );
error_reporting( E_ALL );
$GLOBALS['sim_pre_activate_cb'] = function () {
        global $wpdb; $pdo = $wpdb->pdo;
        foreach ( array(
                'CREATE TABLE wp_tpp_salary_centers ( id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, created_at TEXT NULL )',
                'CREATE TABLE wp_tpp_salary_banks ( id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0 )',
                'CREATE TABLE wp_tpp_salary_fields ( id INTEGER PRIMARY KEY AUTOINCREMENT, field_key TEXT NOT NULL, label TEXT NOT NULL, field_type TEXT NOT NULL DEFAULT "number", default_value TEXT NULL, formula TEXT NULL, options TEXT NULL, is_profile INTEGER NOT NULL DEFAULT 0, in_record INTEGER NOT NULL DEFAULT 1, is_calculated INTEGER NOT NULL DEFAULT 0, is_negative INTEGER NOT NULL DEFAULT 0, allow_manual INTEGER NOT NULL DEFAULT 1, show_in_payslip INTEGER NOT NULL DEFAULT 1, sort_order INTEGER NOT NULL DEFAULT 0, is_system INTEGER NOT NULL DEFAULT 0, is_active INTEGER NOT NULL DEFAULT 1 )',
                'CREATE TABLE wp_tpp_salary_records ( id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, center_id INTEGER NOT NULL, jyear INTEGER NOT NULL, jmonth INTEGER NOT NULL, payload TEXT NULL, gross INTEGER NOT NULL DEFAULT 0, insurable INTEGER NOT NULL DEFAULT 0, insurance_deduct INTEGER NOT NULL DEFAULT 0, other_deductions INTEGER NOT NULL DEFAULT 0, net INTEGER NOT NULL DEFAULT 0, created_by INTEGER NULL, created_at TEXT NULL, updated_at TEXT NULL )',
                'CREATE TABLE wp_tpp_salary_backups ( id INTEGER PRIMARY KEY AUTOINCREMENT, backup_type TEXT NOT NULL, origin TEXT NOT NULL DEFAULT "manual", file_path TEXT NULL, file_size INTEGER NOT NULL DEFAULT 0, created_at TEXT NULL )',
        ) as $q ) { $pdo->exec( $q ); }
};
require __DIR__ . '/wp_sim_bootstrap.php';
global $wpdb;
$now = current_time( 'mysql' );
$wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => 'بومهن', 'created_at' => $now ), array( '%s', '%s' ) );
$c1 = (int) $wpdb->insert_id;
$uid1 = wp_insert_user( array( 'user_login' => '0012345678', 'user_pass' => 'x', 'display_name' => 'علی محمدی', 'role' => 'tpp_salary_employee' ) );

$rows = array(
        array( 'سال', 'ماه', 'مرکز', '«نام و نام‌خانوادگی»', 'کد ملی', 'دستمزد روزانه', 'کارکرد (تعداد روز)', 'حقوق ناخالص', 'حقوق مشمول بیمه', 'کسر درصد بیمه', 'حقوق خالص پرداختی' ),
        array( '1405', 'مرداد', 'بومهن', 'علی محمدی', '0012345678', '700000', '31', '—', '—', '—', '—' ),
);
$res = TppSalary_Import::process_records( $rows, false, true );
echo "res: "; var_dump( $res );
echo "last_error after process: [" . $wpdb->last_error . "]\n";
$cnt = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tpp_salary_records" );
echo "records count: $cnt\n";
$rec = tpp_salary_get_record_period( $uid1, $c1, 1405, 5 );
echo "rec period: "; var_dump( $rec );
// نمایش جدول‌های واقعی
echo "tables: "; var_dump( $wpdb->get_col( "SHOW TABLES LIKE 'wp_tpp_salary%'" ) );
