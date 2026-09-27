<?php
/**
 * test_mixed_files.php — شبیه‌سازی دقیق سناریوی کاربر:
 * بوت‌استرپ جدید (1.1.2) + class-tppsalary-settings.php قدیمی بدون متد static init()
 * نتیجه مورد انتظار: بدون خطای مهلک؛ بقیه ماژول‌ها بالا می‌آیند + اعلان راهنما ثبت می‌شود
 */

error_reporting( E_ALL );

// ---------- آماده‌سازی نسخه ترکیبی ----------
$src = '/home/z/my-project/build/tpp_salary';
$dst = '/tmp/tpp_salary_mixed';

function rrmdir( $d ) {
	if ( is_dir( $d ) ) {
		foreach ( scandir( $d ) as $i ) {
			if ( '.' === $i || '..' === $i ) continue;
			$p = "$d/$i";
			is_dir( $p ) ? rrmdir( $p ) : unlink( $p );
		}
		rmdir( $d );
	}
}
rrmdir( $dst );
mkdir( $dst, 0777, true );
foreach ( $iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $src, RecursiveDirectoryIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::SELF_FIRST
) as $item ) {
	$t = $dst . '/' . $iterator->getSubPathName();
	if ( $item->isDir() ) {
		mkdir( $t );
	} else {
		copy( $item, $t );
	}
}

// جایگزینی Settings با نسخه قدیمی‌سبک (بدون init — مثل 1.0.x)
$old_style = <<<'PHP'
<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'TppSalary_Settings' ) ) {
	class TppSalary_Settings {
		public static function get( $key, $default = '' ) { return $default; }
		public static function all() { return array(); }
		public static function menu_hook() { }
	}
}
PHP;
file_put_contents( "$dst/includes/class-tppsalary-settings.php", $old_style );
echo "== mixed copy ready: new bootstrap + OLD-style TppSalary_Settings (no init) ==\n";

// ---------- بوت‌استرپ WP بدون لود پلاگین ----------
$sim = file_get_contents( '/home/z/my-project/scripts/wp_sim_bootstrap.php' );
$cut = strpos( $sim, '// ---------- LOAD PLUGIN ----------' );
if ( false === $cut ) {
	die( "FATAL: cannot find LOAD PLUGIN marker in simulator\n" );
}
file_put_contents( '/tmp/wp_sim_noload.php', substr( $sim, 0, $cut ) . "\necho 'STUBS READY\\n';\n" );
require '/tmp/wp_sim_noload.php';

// ---------- لود نسخه ترکیبی ----------
try {
	require $dst . '/tpp-salary.php';
	echo 'LOAD OK version=' . TPP_SALARY_VERSION . "\n";
} catch ( Throwable $e ) {
	die( 'LOAD FATAL: ' . $e->getMessage() . "\n" );
}

// ---------- اجرای init (جایی که کاربر fatal می‌گرفت) ----------
try {
	tpp_salary_init();
	echo "INIT: NO FATAL (defensive) \n";
} catch ( Throwable $e ) {
	die( 'INIT FATAL: ' . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n" );
}

// بررسی‌ها
$t1 = function_exists( 'tpp_salary_mismatch_notice' ) ? 'YES' : 'NO';
$t2 = class_exists( 'TppSalary_Settings' ) && ! is_callable( array( 'TppSalary_Settings', 'init' ) ) ? 'YES (old-style detected)' : 'NO';
$t3 = class_exists( 'TppSalary_Reports' ) ? 'YES' : 'NO';

echo "mismatch notice registered: $t1\n";
echo "old-style Settings detected: $t2\n";
echo "other modules still loaded (Reports): $t3\n";

$pass = ( 'YES' === $t1 && 'NO' !== $t2 && 'YES' === $t3 );
echo "\n" . ( $pass ? 'RESULT: PASS — mixed files degrade gracefully' : 'RESULT: FAIL' ) . "\n";
exit( $pass ? 0 : 1 );
