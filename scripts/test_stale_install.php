<?php
/**
 * test_stale_install.php — سناریوی خطای SQL کاربر:
 * class-tppsalary-install.php قدیمی (نسخه میانی با جدول field_archives و ستون رزرو `values`)
 * + بوت‌استرپ جدید 1.1.3
 *
 * نتیجه مورد انتظار: فعال‌سازی DDL قدیمی را اجرا نمی‌کند (خطای SQL رخ نمی‌دهد)
 * و اعلان راهنما ثبت می‌شود.
 */

error_reporting( E_ALL );

$src = '/home/z/my-project/build/tpp_salary';
$dst = '/tmp/tpp_salary_stale_install';

function rrmdir2( $d ) {
	if ( is_dir( $d ) ) {
		foreach ( scandir( $d ) as $i ) {
			if ( '.' === $i || '..' === $i ) continue;
			$p = "$d/$i";
			is_dir( $p ) ? rrmdir2( $p ) : unlink( $p );
		}
		rmdir( $d );
	}
}
rrmdir2( $dst );
mkdir( $dst, 0777, true );
$it = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $src, RecursiveDirectoryIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::SELF_FIRST
);
foreach ( $it as $item ) {
	$t = $dst . '/' . $it->getSubPathName();
	if ( $item->isDir() ) { mkdir( $t ); } else { copy( $item, $t ); }
}

// جایگزینی install با نسخه قدیمی‌سبک — همان DDL خراب کاربر (ستون `values` رزرو)
$stale_install = <<<'PHP'
<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// توجه: بدون ثابت TPP_SALARY_INSTALL_BUILD (نسخه میانی قدیمی)
if ( ! class_exists( 'TppSalary_Install' ) ) {
	class TppSalary_Install {
		public static function activate() {
			self::create_tables();
		}
		public static function create_tables() {
			global $wpdb;
			$GLOBALS['stale_ddl_ran'] = true; // نشان اجرا شدن DDL خراب
			dbDelta( "CREATE TABLE {$wpdb->prefix}tpp_salary_field_archives (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				field_def LONGTEXT NULL,
				values LONGTEXT NULL,
				archived_at DATETIME NULL,
				PRIMARY KEY  (id)
			) {$wpdb->get_charset_collate()};" );
		}
	}
}
PHP;
file_put_contents( "$dst/includes/class-tppsalary-install.php", $stale_install );
echo "== stale install ready (old field_archives with reserved `values`) ==\n";

// ---------- بوت‌استرپ WP بدون لود پلاگین ----------
require '/tmp/wp_sim_noload.php';

// ---------- لود بوت‌استرپ جدید ----------
try {
	require $dst . '/tpp-salary.php';
	echo 'LOAD OK version=' . TPP_SALARY_VERSION . "\n";
} catch ( Throwable $e ) {
	die( 'LOAD FATAL: ' . $e->getMessage() . "\n" );
}

// ---------- اجرای فعال‌سازی (جایی که کاربر خطای SQL می‌گرفت) ----------
try {
	tpp_salary_activate();
	echo "ACTIVATE: NO SQL ERROR (stale DDL skipped)\n";
} catch ( Throwable $e ) {
	die( 'ACTIVATE FATAL: ' . get_class( $e ) . ': ' . $e->getMessage() . "\n" );
}

$t1 = isset( $GLOBALS['stale_ddl_ran'] ) ? 'YES (BAD!)' : 'NO (good — skipped)';
$t2 = function_exists( 'tpp_salary_install_stale_notice' ) ? 'YES' : 'NO';
$t3 = defined( 'TPP_SALARY_INSTALL_BUILD' ) ? 'YES (' . TPP_SALARY_INSTALL_BUILD . ')' : 'NO (stale file detected)';

echo "stale DDL executed: $t1\n";
echo "stale-install notice registered: $t2\n";
echo "TPP_SALARY_INSTALL_BUILD defined: $t3\n";

$pass = ( 0 === strpos( $t1, 'NO' ) && 'YES' === $t2 );
echo "\n" . ( $pass ? 'RESULT: PASS — stale install file can no longer cause SQL fatal' : 'RESULT: FAIL' ) . "\n";
exit( $pass ? 0 : 1 );
