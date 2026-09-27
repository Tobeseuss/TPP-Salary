<?php
/* رندر بصری PDF گزارش با per=4 → دو صفحه ستونی — همه سطرها در هر صفحه */
define( 'SIM_SQLITE_WPDB', 1 );
define( 'SIM_SQLITE_USERS', 1 );
require __DIR__ . '/wp_sim_bootstrap.php';

global $wpdb;
$now = current_time( 'mysql' );
$wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => 'مرکز ویژوال', 'created_at' => $now ), array( '%s', '%s' ) );
$center_id = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}tpp_salary_centers LIMIT 1" );

$names = array( 'علی محمدی', 'زهرا کریمی', 'رضا احمدی', 'مریم نادری', 'حسین صادقی', 'سارا رضایی', 'امیر تهرانی' );
$payload = array(
        'base_salary' => 250000000, 'housing' => 12000000, 'food' => 8000000,
        'seniority' => 15000000, 'marriage' => 7000000, 'child_allowance' => 33251100,
        'overtime_pay' => 15000000, 'holiday_pay' => 999000, 'commute' => 5000000,
        'absence_penalty' => 0, 'work_deduction' => 0, 'other' => 10683840,
        'gross' => 381381596, 'insurable' => 270697756, 'insurance_deduct' => 18948842,
        'other_deductions' => 500000, 'net' => 362432754,
);
foreach ( $names as $i => $name ) {
        $nid = '16600000' . ( $i + 10 );
        $uid = (int) wp_insert_user( array( 'user_login' => $nid, 'user_pass' => 'p1', 'display_name' => $name, 'role' => 'tpp_salary_employee' ) );
        tpp_salary_save_profile( $uid, array( 'full_name' => $name, 'centers' => array( $center_id ) ) );
        TppSalary_Salary_Pages::upsert_record( $uid, $center_id, 1405, 5, $payload, false, array() );
}
$recs = tpp_salary_get_period_records( 1405, 5, 0 );
$pdf  = TppSalary_Reports::build_report_pdf( $recs, 1405, 5, 0, 4 );
file_put_contents( sys_get_temp_dir() . '/tpp161-visual2.pdf', $pdf->Output( 'S' ) );
echo 'pages: ' . preg_match_all( '/\/Type\s*\/Page[^s]/', file_get_contents( sys_get_temp_dir() . '/tpp161-visual2.pdf' ) ) . "\n";
echo "OK\n";
