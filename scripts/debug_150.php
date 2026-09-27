<?php
define( 'SIM_SQLITE_WPDB', 1 );
define( 'SIM_SQLITE_USERS', 1 );
error_reporting( E_ALL );
require __DIR__ . '/wp_sim_bootstrap.php';

global $wpdb;
$fields = tpp_salary_get_fields();
echo "fields:\n";
foreach ( $fields as $f ) {
        echo '  ', $f->field_key, ' calc=', (int) $f->is_calculated, ' in_record=', isset( $f->in_record ) ? (int) $f->in_record : 'NULL', ' formula=', (string) $f->formula, "\n";
}
$guard = function_exists( 'tpp_salary_field_in_record' );
echo "guard:", $guard ? "Y" : "N", "\n";
$filtered = array_values( array_filter( $fields, 'tpp_salary_field_in_record' ) );
echo "filtered keys: ", implode( ',', wp_list_pluck( $filtered, 'field_key' ) ), "\n";
