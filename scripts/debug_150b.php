<?php
define( 'SIM_SQLITE_WPDB', 1 );
define( 'SIM_SQLITE_USERS', 1 );
error_reporting( E_ALL );
require '/home/z/my-project/scripts/wp_sim_bootstrap.php';

$fields = tpp_salary_get_fields();
echo "count=", count( $fields ), "\n";
$first = $fields[0];
echo "first is: ", get_class( $first ), " key=", var_export( $first->field_key, true ), " in_record=", var_export( $first->in_record, true ), "\n";
echo "guard(first) = ";
var_dump( tpp_salary_field_in_record( $first ) );
$daily = null;
foreach ( $fields as $f ) { if ( 'daily_wage' === $f->field_key ) { $daily = $f; break; } }
echo "guard(daily_wage) = ";
var_dump( tpp_salary_field_in_record( $daily ) );
$filtered = array_values( array_filter( $fields, 'tpp_salary_field_in_record' ) );
echo "filtered count=", count( $filtered ), "\n";
$filtered2 = array();
foreach ( $fields as $f ) { if ( tpp_salary_field_in_record( $f ) ) { $filtered2[] = $f->field_key; } }
echo "manual loop count=", count( $filtered2 ), ": ", implode( ',', $filtered2 ), "\n";
