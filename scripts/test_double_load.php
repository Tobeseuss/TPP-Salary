<?php
/**
 * test_double_load.php — شبیه‌سازی نصب دوگانه افزونه
 *
 * سناریوی کاربر: دو نسخه از پوشه افزونه (tpp_salary + tpp_salary-1) هر دو فعال باشند.
 * نتیجه مورد انتظار: نسخه دوم نباید خطای "Cannot declare class" بدهد؛
 * باید اعلان نصب دوگانه ثبت شود و اجرای آن به‌صورت تمیز متوقف شود.
 *
 * اجرا: php test_double_load.php
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

$ROOT = '/home/z/my-project/scripts';
require $ROOT . '/wp_sim_bootstrap.php'; // محیط شبیه‌سازی WP

define( 'ABSPATH_DOUBLE_TEST', true );

echo "== Pass 1: load copy #1 (tpp_salary) ==\n";
require '/home/z/my-project/build/tpp_salary/tpp-salary.php';
echo '   version=' . TPP_SALARY_VERSION . "\n";
echo '   class TppSalary_Xlsx_Writer: ' . ( class_exists( 'TppSalary_Xlsx_Writer' ) ? 'declared' : 'MISSING' ) . "\n";
echo '   class TppSalary_Settings: ' . ( class_exists( 'TppSalary_Settings' ) ? 'declared' : 'MISSING' ) . "\n";
echo '   function tpp_salary_get_settings: ' . ( function_exists( 'tpp_salary_get_settings' ) ? 'declared' : 'MISSING' ) . "\n";

echo "== Pass 2: load copy #2 (same file again — like tpp_salary-1) ==\n";
$fatal = null;
set_error_handler( function ( $no, $str, $file, $line ) {
	throw new ErrorException( $str, 0, $no, $file, $line );
} );
try {
	require '/home/z/my-project/build/tpp_salary/tpp-salary.php';
	echo "   second load: NO FATAL — returned cleanly\n";
} catch ( Throwable $e ) {
	$fatal = $e;
	echo '   second load FATAL: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n";
}
restore_error_handler();

echo "== Pass 3: re-include every class file directly (worst case) ==\n";
$inc = '/home/z/my-project/build/tpp_salary/includes/';
$files = glob( $inc . 'class-tppsalary-*.php' );
$ok = 0; $bad = 0;
foreach ( $files as $f ) {
	try {
		require_once $f; // require_once نمی‌تواند دوباره اجرا شود؛ با require سخت‌گیرانه‌تر:
	} catch ( Throwable $e ) {
		$bad++;
		echo '   ' . basename( $f ) . ' => ' . $e->getMessage() . "\n";
	}
}
// نکته: require_once در همین فایل قبلاً استفاده شده؛ برای تست واقعی «دو بار تعریف»
// از eval با محتوای فایل استفاده می‌کنیم (مثل اینکه فایل با مسیر دیگری include شود)
foreach ( $files as $f ) {
	$code = file_get_contents( $f );
	try {
		eval( '?>' . $code );
		$ok++;
	} catch ( Throwable $e ) {
		$bad++;
		echo '   EVAL ' . basename( $f ) . ' => ' . $e->getMessage() . "\n";
	}
}
echo "   direct re-include: ok={$ok} bad={$bad}\n";

echo "== Pass 4: helpers.php re-include (plain require) ==\n";
try {
	require $inc . 'helpers.php';
	require $inc . 'helpers.php'; // دوباره، مثل دو کپی
	echo "   helpers re-require: NO FATAL\n";
} catch ( Throwable $e ) {
	echo '   helpers FATAL: ' . $e->getMessage() . "\n";
}

echo "\n" . ( ( null === $fatal && 0 === $bad ) ? 'RESULT: PASS — double load is safe' : 'RESULT: FAIL' ) . "\n";
exit( ( null === $fatal && 0 === $bad ) ? 0 : 1 );
