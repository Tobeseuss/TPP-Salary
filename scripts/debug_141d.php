<?php
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
$wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => 'رودهن', 'created_at' => $now ), array( '%s', '%s' ) );
$c2 = (int) $wpdb->insert_id;
echo "c1=$c1 c2=$c2\n";
$uid1 = wp_insert_user( array( 'user_login' => '0012345678', 'user_pass' => 'pass1234', 'display_name' => 'علی محمدی', 'role' => 'tpp_salary_employee' ) );
update_user_meta( $uid1, 'tpp_salary_national_id', '0012345678' );

$headers = array( 'سال', 'ماه', 'مرکز', '«نام و نام‌خانوادگی»', 'کد ملی', 'دستمزد روزانه', 'کارکرد (تعداد روز)', 'حقوق ناخالص', 'حقوق مشمول بیمه', 'کسر درصد بیمه', 'حقوق خالص پرداختی' );
$rows = array( $headers );
$rows[] = array( '1405', 'مرداد', 'بومهن', 'علی محمدی', '0012345678', '700000', '31', '—', '—', '—', '—' );
$rows[] = array( '1405', 'مرداد', 'رودهن', 'رضا کریمی', '0091111111', '650000', '30', '—', '—', '—', '—' );
$res = TppSalary_Import::process_records( $rows, false, true );
foreach ( $res['log'] as $l ) { echo "row {$l['row']}: [{$l['status']}] {$l['message']}\n"; }
echo "ok={$res['ok']} fail={$res['fail']}\n";
$rec1 = tpp_salary_get_record_period( $uid1, $c1, 1405, 5 );
echo "rec1: "; var_dump( $rec1 );
$found = get_users( array( 'meta_key' => 'tpp_salary_national_id', 'meta_value' => '0091111111', 'number' => 1, 'fields' => 'all' ) );
$p = tpp_salary_get_profile( $found[0]->ID );
echo "centers: "; var_dump( $p['centers'] ); echo " (expect c2=$c2)\n";
