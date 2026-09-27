<?php
/**
 * ساخت فایل‌های نمونه اکسل از داده‌های سند نیازمندی‌ها
 */

define( 'ABSPATH', '/tmp/' );
define( 'TPP_SALARY_DIR', '/home/z/my-project/build/tpp_salary/' );
require TPP_SALARY_DIR . 'includes/class-tppsalary-xlsx-writer.php';

$emps = json_decode( file_get_contents( '/home/z/my-project/scripts/employees.json' ), true );
$recs = json_decode( file_get_contents( '/home/z/my-project/scripts/salary_records.json' ), true );
echo 'emps=' . count( $emps ) . ' recs=' . count( $recs ) . "\n";

function esc_stub( $s ) { return $s; }

// ===== نمونه ۱: کارمندان (قالب ورود گروهی) =====
$xlsx = new TppSalary_Xlsx_Writer();
$xlsx->add_sheet( 'کارمندان' );
$headers = array( 'نام و نام خانوادگی', 'کد ملی', 'شماره همراه', 'تعداد فرزند', 'گروه اصلی', 'دستمزد روزانه مرجع' );
foreach ( $headers as $i => $h ) {
	$xlsx->set( 1, 1 + $i, $h, 'header' );
}
$row = 2;
foreach ( $emps as $e ) {
	$xlsx->set( $row, 1, $e['name'], 'text' );
	$xlsx->set( $row, 2, $e['nid'], 'text' );
	$xlsx->set( $row, 3, $e['mobile'], 'text' );
	$xlsx->set( $row, 4, (float) $e['children'], 'num' );
	$xlsx->set( $row, 5, $e['group'], 'text' );
	$xlsx->set( $row, 6, (float) $e['wage'], 'num' );
	$row++;
}
$xlsx->freeze( 'A2' );
$xlsx->set_width( 'A', 30 );
$data = $xlsx->to_string();
file_put_contents( '/home/z/my-project/build/tpp_salary/samples/employees-sample.xlsx', $data );
echo 'employees-sample.xlsx: ' . strlen( $data ) . " bytes\n";

// ===== نمونه ۲: رکوردهای حقوق (نمونه گزارش) =====
$xlsx2 = new TppSalary_Xlsx_Writer();
$xlsx2->add_sheet( 'حقوق مرداد ۱۴۰۵' );
$sheaders = array( 'سال', 'ماه', 'پروژه', 'نام کارمند', 'گروه اصلی', 'دستمزد روزانه', 'کارکرد', 'حقوق پایه', 'حق مسکن', 'حق بن', 'پایه سنوات', 'حق تأهل', 'تعداد فرزند', 'حق اولاد', 'ایاب و ذهاب', 'اضافه کاری', 'تعطیل کاری', 'سایر', 'کسر از کار', 'حقوق ناخالص', 'حقوق مشمول بیمه', 'بیمه ۷٪', 'کسورات دیگر', 'حقوق خالص' );
foreach ( $sheaders as $i => $h ) {
	$xlsx2->set( 1, 1 + $i, $h, 'header' );
}
$row = 2;
foreach ( $recs as $r ) {
	foreach ( $r as $i => $v ) {
		$num = str_replace( array( ',', '،' ), '', $v );
		if ( '' !== $num && ( is_numeric( $num ) ) ) {
			$xlsx2->set( $row, 1 + $i, (float) $num, 'num' );
		} else {
			$xlsx2->set( $row, 1 + $i, $v, 'text' );
		}
	}
	$row++;
}
$xlsx2->freeze( 'A2' );
$xlsx2->set_width( 'A', 8 );
$xlsx2->set_width( 'B', 10 );
$xlsx2->set_width( 'C', 12 );
$xlsx2->set_width( 'D', 30 );
for ( $c = 5; $c <= 24; $c++ ) {
	$xlsx2->set_width( TppSalary_Xlsx_Writer::col_letter( $c ), 17 );
}
$data2 = $xlsx2->to_string();
file_put_contents( '/home/z/my-project/build/tpp_salary/samples/salary-records-sample.xlsx', $data2 );
echo 'salary-records-sample.xlsx: ' . strlen( $data2 ) . " bytes\n";
echo "OK\n";
