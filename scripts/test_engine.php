<?php
/**
 * تست یکپارچگی موتور پلاگین — بدون وردپرس (استاب حداقلی)
 */

define( 'ABSPATH', '/tmp/' );
define( 'TPP_SALARY_DIR', '/home/z/my-project/build/tpp_salary/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

// --- WP stubs ---
$GLOBALS['__options'] = array();
function get_option( $k, $d = false ) { return $GLOBALS['__options'][ $k ] ?? $d; }
function update_option( $k, $v ) { $GLOBALS['__options'][ $k ] = $v; return true; }
function add_option( $k, $v ) { $GLOBALS['__options'][ $k ] = $v; return true; }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, (array) $args ); }
function current_time( $type ) { return 'mysql' === $type ? date( 'Y-m-d H:i:s' ) : time(); }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function wp_rand( $a, $b ) { return rand( $a, $b ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $k ) ); }
function wp_die( $m = '' ) { throw new Exception( 'WP_DIE: ' . $m ); }
class WP_Error {
	private $msg;
	public function __construct( $c = '', $m = '' ) { $this->msg = $m; }
	public function get_error_message() { return $this->msg; }
}
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function trailingslashit( $s ) { return rtrim( $s, '/\\' ) . '/'; }
function size_format( $b ) { return round( $b / 1024, 1 ) . ' KB'; }
function sanitize_file_name( $s ) { return preg_replace( '/[^A-Za-z0-9_\-\.\x{0600}-\x{06FF}]/u', '', $s ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }

require TPP_SALARY_DIR . 'includes/class-tppsalary-jalali.php';
require TPP_SALARY_DIR . 'includes/class-tppsalary-formula.php';
require TPP_SALARY_DIR . 'includes/class-tppsalary-xlsx-writer.php';

$pass = 0;
$fail = 0;
function check( $name, $cond ) {
	global $pass, $fail;
	if ( $cond ) {
		$pass++;
		echo "  ✓ $name\n";
	} else {
		$fail++;
		echo "  ✗ $name\n";
	}
}

echo "=== 1) تقویم جلالی ===\n";
// جفت‌های مرجع: 2026-09-04 => 1405/06/13 ; 2026-03-21 => 1405/01/01 ; 2025-03-20 => 1404/01/01
$j = TppSalary_Jalali::to_jalali( 2026, 9, 4 );
check( '2026-09-04 → 1405/6/13', 1405 === $j[0] && 6 === $j[1] && 13 === $j[2] );
$j = TppSalary_Jalali::to_jalali( 2026, 3, 21 );
check( '2026-03-21 → 1405/1/1', 1405 === $j[0] && 1 === $j[1] && 1 === $j[2] );
$j = TppSalary_Jalali::to_jalali( 2024, 3, 20 );
check( '2024-03-20 → 1403/1/1', 1403 === $j[0] && 1 === $j[1] && 1 === $j[2] );
// رفت و برگشت.
for ( $i = 0; $i < 40; $i++ ) {
	$ts = strtotime( "2020-01-01 +{$i} days" );
	$g  = array( (int) date( 'Y', $ts ), (int) date( 'n', $ts ), (int) date( 'j', $ts ) );
	$jj = TppSalary_Jalali::to_jalali( $g[0], $g[1], $g[2] );
	$gg = TppSalary_Jalali::to_gregorian( $jj[0], $jj[1], $jj[2] );
	if ( $g !== $gg ) {
		check( "رفت‌وبرگشت {$g[0]}-{$g[1]}-{$g[2]}", false );
		$fail++;
		exit( 1 );
	}
}
check( 'رفت و برگشت ۴۰ روز تصادفی', true );
check( 'طول اسفند ۱۴۰۳ (کبیسه) = 30', 30 === TppSalary_Jalali::month_days( 1403, 12 ) );
check( 'طول اسفند ۱۴۰۴ = 29', 29 === TppSalary_Jalali::month_days( 1404, 12 ) );

echo "\n=== 2) موتور فرمول ===\n";
$v = array(
	'daily_wage' => 5593757, 'work_days' => 31, 'housing' => 30000000, 'food' => 22000000,
	'seniority' => 40291289, 'marriage' => 5000000, 'children_count' => 0, 'child_allowance_rate' => 16625550,
	'commute' => 100000000, 'overtime_hours' => 0, 'overtime_rate' => 0, 'holiday_days' => 0, 'holiday_rate' => 0,
	'absence_days' => 0, 'absence_rate' => 0, 'work_deduction' => 0, 'other' => 10683840,
	'insurable' => 270697756, 'insurance_rate' => 7, 'other_deductions' => 0,
);
$base = TppSalary_Formula::evaluate( '{daily_wage}*{work_days}', $v );
check( 'حقوق پایه = 173,406,467', round( $base ) === 173406467.0 );
$child = TppSalary_Formula::evaluate( '{children_count}*{child_allowance_rate}', $v );
$child2 = TppSalary_Formula::evaluate( '{children_count}*{child_allowance_rate}', array_merge( $v, array( 'children_count' => 2 ) ) );
check( 'حق اولاد = 33,251,100 (۲ فرزند)', round( $child2 ) === 33251100.0 );
$gross = TppSalary_Formula::evaluate( '{base_salary}+{housing}+{food}+{seniority}+{marriage}+{child_allowance}+{commute}+{overtime_pay}+{holiday_pay}+{absence_penalty}+{work_deduction}+{other}', array_merge( $v, array( 'base_salary' => $base, 'child_allowance' => $child ) ) );
check( 'حقوق ناخالص = 381,381,596 (سطر ۱ نمونه)', abs( $gross - 381381596.0 ) < 1 );
$ins_deduct = TppSalary_Formula::evaluate( '-({insurable}*{insurance_rate}/100)', $v );
check( 'کسر بیمه ≈ -18,948,842 (گرد به سمت صفر)', abs( ceil( $ins_deduct ) + 18948842.0 ) < 0.1 );
$net = TppSalary_Formula::evaluate( '{gross}+{insurance_deduct}+{other_deductions}', array( 'gross' => $gross, 'insurance_deduct' => $ins_deduct, 'other_deductions' => 0 ) );
check( 'خالص پرداختی = 362,432,754 (سطر ۱ نمونه)', abs( $net - 362432754.0 ) < 1 );
check( 'تقسیم بر صفر → 0', TppSalary_Formula::evaluate( '{a}/{b}', array( 'a' => 5, 'b' => 0 ) ) === 0.0 );
check( 'پرانتز و یونری: -(2+3)*4 = -20', TppSalary_Formula::evaluate( '-({x}+{y})*{z}', array( 'x' => 2, 'y' => 3, 'z' => 4 ) ) === -20.0 );
check( 'فرمول نامعتبر → خطا', is_wp_error( TppSalary_Formula::evaluate( '{a}+phpinfo()', array( 'a' => 1 ) ) ) );
check( 'فیلدهای استفاده‌شده', TppSalary_Formula::fields_used( '{a}+{b}*2' ) === array( 'a', 'b' ) );

echo "\n=== 3) تولید XLSX ===\n";
$xlsx = new TppSalary_Xlsx_Writer();
$xlsx->add_sheet( 'کارمندان' );
$xlsx->merge( 1, 1, 1, 3 );
$xlsx->set( 1, 1, 'لیست آزمایشی حقوق', 'title' );
$xlsx->set( 2, 1, 'نام', 'header' );
$xlsx->set( 2, 2, 'حقوق پایه', 'header' );
$xlsx->set( 3, 1, 'اسماعیل کمال آبادی', 'text' );
$xlsx->set( 3, 2, 173406467, 'num' );
$xlsx->set_width( 'A', 30 );
$data = $xlsx->to_string();
check( 'تولید XLSX بدون خطا', ! is_wp_error( $data ) && strlen( $data ) > 1000 );
check( 'امضای ZIP (PK)', 0 === strpos( $data, 'PK' ) );
file_put_contents( '/home/z/my-project/build/test_engine.xlsx', $data );

echo "\n=== 4) خواندن XLSX ===\n";
require TPP_SALARY_DIR . 'includes/class-tppsalary-xlsx-reader.php';
$rows = TppSalary_Xlsx_Reader::read( '/home/z/my-project/build/test_engine.xlsx' );
check( 'خواندن ۳ سطر', is_array( $rows ) && 3 === count( $rows ) );
check( 'متن سلول عنوان', 'لیست آزمایشی حقوق' === $rows[0][0] );
check( 'عدد سلول', '173406467' === $rows[2][1] );

echo "\n=== 5) PDF فیش حقوقی (شبیه‌سازی رکورد) ===\n";
require TPP_SALARY_DIR . 'includes/class-tppsalary-pdf.php';
require TPP_SALARY_DIR . 'includes/helpers.php';
$record = (object) array(
	'jyear' => 1405, 'jmonth' => 5, 'user_id' => 0, 'center_id' => 0,
	'payload' => wp_json_encode( array(
		'insurance_group' => '6', 'daily_wage' => 5593757, 'work_days' => 31,
		'base_salary' => 173406467, 'housing' => 30000000, 'food' => 22000000,
		'seniority' => 40291289, 'marriage' => 5000000, 'children_count' => 2,
		'child_allowance' => 33251100, 'commute' => 100000000,
		'overtime_hours' => 12, 'overtime_pay' => 1350000, 'holiday_days' => 0, 'holiday_pay' => 0,
		'absence_days' => 1, 'absence_penalty' => -10683840, 'work_deduction' => 0, 'other' => 10683840,
		'gross' => 382731396, 'insurable' => 270697756, 'insurance_deduct' => -18948842,
		'other_deductions' => 0, 'net' => 363782554,
	) ),
);
try {
	$payload = tpp_salary_record_payload( $record );
	check( 'پارس پیلود رکورد', 382731396.0 === (float) $payload['gross'] );
	echo "  (رندر فیش کامل در تست PDF سرور انجام می‌شود)\n";
} catch ( Exception $e ) {
	check( 'پارس پیلود', false );
}

echo "\nنتیجه: $pass موفق, $fail ناموفق\n";
exit( $fail ? 1 : 0 );
