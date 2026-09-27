#!/usr/bin/env php
<?php
/**
 * بازتولید باگ «ستون نام و نام خانوادگی یافت نشد» با فایل نمونه خود پلاگین
 */
define( 'SIM_SQLITE_WPDB', 1 );
define( 'SIM_SQLITE_USERS', 1 );
error_reporting( E_ALL );
require __DIR__ . '/wp_sim_bootstrap.php';

$sample = dirname( __DIR__ ) . '/build/tpp_salary/samples/employees-sample.xlsx';
echo "sample: $sample\n";

$rows = TppSalary_Xlsx_Reader::read( $sample );
if ( is_wp_error( $rows ) ) { echo "READ ERROR: " . $rows->get_error_message() . "\n"; exit(1); }
echo "rows: " . count( $rows ) . "\n";

$headers = $rows[0];
echo "header row (" . count( $headers ) . " cells):\n";
foreach ( $headers as $i => $t ) {
    $norm = TppSalary_Import::normalize_key( $t );
    printf( "  [%d] raw=%s\n      norm=%s\n      hex=%s\n", $i, var_export( $t, true ), var_export( $norm, true ), bin2hex( $norm ) );
}

// مقایسه با کلیدهای header_map
$rm = new ReflectionMethod( 'TppSalary_Import', 'header_map' );
$rm->setAccessible( true );
$map = $rm->invoke( null );
echo "\nheader_map keys for display_name:\n";
foreach ( $map as $k => $v ) {
    if ( 'display_name' === $v ) {
        printf( "  key=%s hex=%s\n", var_export( $k, true ), bin2hex( $k ) );
    }
}

// اجرای دقیق منطق handle
$col_map = array();
foreach ( $headers as $i => $title ) {
    $norm = TppSalary_Import::normalize_key( $title );
    $key = isset( $map[ $norm ] ) ? $map[ $norm ] : null;
    if ( $key ) { $col_map[ $i ] = $key; }
}
echo "\ncol_map: " . json_encode( $col_map, JSON_UNESCAPED_UNICODE ) . "\n";
echo empty( array_flip( $col_map )['display_name'] ?? null ) ? ">>> display_name NOT mapped\n" : ">>> mapping OK (display_name found)\n";
