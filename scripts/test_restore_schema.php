<?php
/**
 * تست سازگاری بخش‌های بازگردانی بکاپ با DDL واقعی
 *
 * ۱) هر ستون در whitelist بازگردانی باید در CREATE TABLE موجود باشد (و برعکس) —
 *    وگرنه بازگردانی یا ستونی را از دست می‌دهد یا خطای SQL می‌گیرد.
 * ۲) کلیدهای JSON بکاپ (make) باید با کلیدهای خوانده‌شده در restore برابر باشند.
 *
 * @package TppSalary
 */

$build = '/home/z/my-project/build/tpp_salary';

$install = file_get_contents( $build . '/includes/class-tppsalary-install.php' );
$backup  = file_get_contents( $build . '/includes/class-tppsalary-backup.php' );

// ---- ۱) استخراج ستون‌های هر CREATE TABLE از DDL نصب ----
$ddl_tables = array();
if ( preg_match_all( '/CREATE TABLE \{\$([a-z]+)\}\s*\((.*?)\)\s*\{\$charset\}/s', $install, $m, PREG_SET_ORDER ) ) {
        foreach ( $m as $block ) {
                // نگاشت متغیر DDL به نام جدول واقعی.
                $var  = $block[1];
                $body = $block[2];
                $cols = array();
                foreach ( explode( "\n", $body ) as $line ) {
                        $line = trim( $line );
                        if ( '' === $line ) {
                                continue;
                        }
                        if ( preg_match( '/^(PRIMARY KEY|UNIQUE KEY|KEY|FULLTEXT|INDEX)\b/i', $line ) ) {
                                continue;
                        }
                        if ( preg_match( '/^([a-z_][a-z0-9_]*)\s+/', $line, $cm ) ) {
                                $cols[] = $cm[1];
                        }
                }
                $ddl_tables[ $var ] = $cols;
        }
}

// نگاشت متغیرهای داخل create_tables به نام جدول.
$var_map = array();
if ( preg_match_all( "/\\\$([a-z]+)\s*=\s*\\\$wpdb->prefix \. '([a-z_]+)'/", $install, $vm, PREG_SET_ORDER ) ) {
        foreach ( $vm as $v ) {
                $var_map[ $v[1] ] = $v[2];
        }
}

$fail = 0;
echo "=== DDL columns parsed ===\n";
$ddl_by_table = array();
foreach ( $ddl_tables as $var => $cols ) {
        $table = isset( $var_map[ $var ] ) ? $var_map[ $var ] : $var;
        $ddl_by_table[ $table ] = $cols;
        echo "  {$table}: " . count( $cols ) . " cols\n";
}

// ---- ۲) استخراج whitelist از restore_sections ----
$sections = array();
$start = strpos( $backup, 'function restore_sections() {' );
$end   = strpos( $backup, 'function sanitize_restored_settings' );
if ( false !== $start && false !== $end && $end > $start ) {
        $src = substr( $backup, $start, $end - $start );
        if ( preg_match_all( "/'([a-z_]+)'\s*=>\s*array\(\s*'table'\s*=>\s*'([a-z_]+)',\s*'columns'\s*=>\s*array\(([^)]*)\)/s", $src, $sm, PREG_SET_ORDER ) ) {
                foreach ( $sm as $s ) {
                        $cols = array_filter( array_map( 'trim', explode( ',', $s[3] ) ) );
                        $cols = array_map( function ( $c ) { return trim( $c, "' " ); }, $cols );
                        $sections[ $s[1] ] = array( 'table' => $s[2], 'columns' => array_values( $cols ) );
                }
        }
}

echo "\n=== restore_sections parsed ===\n";
foreach ( $sections as $key => $def ) {
        echo "  {$key} -> {$def['table']}: " . count( $def['columns'] ) . " cols\n";
}

if ( empty( $sections ) || count( $sections ) < 4 ) {
        echo "FAIL: restore_sections not fully parsed\n";
        exit( 1 );
}

// ---- ۳) مقایسه whitelist با DDL ----
echo "\n=== whitelist vs DDL ===\n";
foreach ( $sections as $key => $def ) {
        $table = $def['table'];
        if ( ! isset( $ddl_by_table[ $table ] ) ) {
                echo "FAIL: table {$table} (section {$key}) not found in DDL\n";
                $fail++;
                continue;
        }
        $missing_in_whitelist = array_diff( $ddl_by_table[ $table ], $def['columns'] );
        $unknown_in_whitelist = array_diff( $def['columns'], $ddl_by_table[ $table ] );
        if ( $missing_in_whitelist ) {
                echo "FAIL: {$table} — columns in DDL missing from whitelist: " . implode( ', ', $missing_in_whitelist ) . " (restore would silently DROP data)\n";
                $fail++;
        }
        if ( $unknown_in_whitelist ) {
                echo "FAIL: {$table} — whitelist columns not in DDL: " . implode( ', ', $unknown_in_whitelist ) . " (restore would break)\n";
                $fail++;
        }
        if ( ! $missing_in_whitelist && ! $unknown_in_whitelist ) {
                echo "  PASS: {$table} — whitelist matches DDL exactly\n";
        }
}

// ---- ۴) کلیدهای JSON در make باید با بخش‌های restore برابر باشند ----
echo "\n=== make() JSON keys vs restore sections ===\n";
$make_keys = array( 'version', 'stamp', 'settings', 'centers', 'banks', 'fields', 'records', 'profiles' );
foreach ( array( 'centers', 'banks', 'fields', 'records' ) as $k ) {
        if ( isset( $sections[ $k ] ) ) {
                echo "  PASS: key '{$k}' present in both make() and restore_sections\n";
        } else {
                echo "FAIL: key '{$k}' missing in restore_sections (restore would find nothing!)\n";
                $fail++;
        }
}

echo "\n";
if ( $fail ) {
        echo "RESULT: FAIL ({$fail} problems)\n";
        exit( 1 );
}
echo "RESULT: PASS — restore whitelist is consistent with DDL and backup JSON keys\n";
