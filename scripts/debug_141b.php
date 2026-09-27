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

// بازتاب mapping داخلی process_records با همان ورودی تست
$fields = tpp_salary_get_fields();
$fbn_label = array();
foreach ( $fields as $f ) {
        if ( isset( $f->in_record ) && ! (int) $f->in_record ) { continue; }
        $fbn_label[ TppSalary_Import::normalize_key( $f->label ) ] = $f->field_key;
}
foreach ( array( 'دستمزد روزانه', 'کارکرد (تعداد روز)', 'حقوق ناخالص', 'کسر درصد بیمه' ) as $h ) {
        $n = TppSalary_Import::normalize_key( $h );
        echo $h . ' => norm=' . $n . ' => ' . ( isset( $fbn_label[ $n ] ) ? $fbn_label[ $n ] : '(بدون تطبیق)' ) . "\n";
}
echo "--- daily_wage rows ---\n";
foreach ( $wpdb->get_results( "SELECT id, field_key, label, in_record, is_active, sort_order FROM {$wpdb->prefix}tpp_salary_fields WHERE field_key='daily_wage'" ) as $r ) {
        var_dump( $r );
}
