<?php
/**
 * بازتولید فایل‌های نمونه با موتور اصلاح‌شده اکسل (۱.۳.۲)
 *  - ورودی: /tmp/old_samples.json (داده استخراج‌شده از نمونه‌های قبلی)
 *  - خروجی: samples/ افزونه + فایل‌های تست اضافه (multi-sheet، بکاپ‌مانند)
 */
error_reporting( E_ALL & ~E_DEPRECATED );
define( 'ABSPATH', '/tmp/wp-sim/' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'OBJECT', 'OBJECT' );
define( 'SIM_FIXTURES', 1 );
require '/home/z/my-project/scripts/wp_sim_bootstrap.php';

$data = json_decode( file_get_contents( '/tmp/old_samples.json' ), true );
if ( ! is_array( $data ) ) { fwrite( STDERR, "bad json\n" ); exit( 1 ); }

/**
 * ساخت فایل از داده استخراج‌شده با نویسنده اصلاح‌شده
 */
function build_from( $sheets_spec, $out_path ) {
        $x = new TppSalary_Xlsx_Writer();
        foreach ( $sheets_spec as $spec ) {
                $x->add_sheet( $spec['title'], true );
                $rows = $spec['rows'];
                foreach ( $rows as $r => $vals ) {
                        foreach ( $vals as $ci => $v ) {
                                $style = ( 0 === $r ) ? 'header' : ( ( is_int( $v ) || is_float( $v ) ) ? 'num' : 'text' );
                                if ( 0 === $r ) {
                                        $x->set( $r + 1, $ci + 1, (string) $v, 'header' );
                                } else {
                                        $x->set( $r + 1, $ci + 1, $v, $style );
                                }
                        }
                }
                if ( ! empty( $spec['freeze'] ) && 'A1' !== $spec['freeze'] ) {
                        $x->freeze( $spec['freeze'] );
                } elseif ( 'A1' === $spec['freeze'] ) {
                        $x->freeze( 'A2' );
                }
                foreach ( (array) ( isset( $spec['widths'] ) ? $spec['widths'] : array() ) as $letter => $w ) {
                        $x->set_width( $letter, $w );
                }
        }
        $data = $x->to_string();
        if ( is_wp_error( $data ) ) {
                fwrite( STDERR, 'writer error: ' . $data->get_error_message() . "\n" );
                exit( 1 );
        }
        file_put_contents( $out_path, $data );
        echo "WROTE $out_path (" . strlen( $data ) . " bytes)\n";
}

build_from( $data['employees'], '/home/z/my-project/build/tpp_salary/samples/employees-sample.xlsx' );
build_from( $data['records'],  '/home/z/my-project/build/tpp_salary/samples/salary-records-sample.xlsx' );

// ---------- فایل‌های تست اضافه ----------
// ۱) سه‌شیتی با نام‌های فارسی، ادغام، اعداد منفی و رشته با صفر ابتدایی
$spec = array( array(
        'title' => 'کارمندان',
        'freeze' => 'A2',
        'widths' => array( 'A' => 24, 'B' => 16, 'C' => 15 ),
        'rows' => array(
                array( 'نام و نام خانوادگی', 'کد ملی', 'حق پایه' ),
                array( 'علی محمدی', '0012345678', 950000 ),
                array( 'زهرا حسینی', '0098765432', 1025000.5 ),
                array( 'حسین رضایی', '0078451229', -12500 ),
        ),
) );
build_from( $spec, '/tmp/xlsxfixtures/multisheet-persian.xlsx' );

// ۲) بازتولید نمونه از سازنده داینامیک (مسیر واقعی TppSalary_Samples::refresh)
$ok = TppSalary_Samples::refresh();
echo 'Samples::refresh => ' . var_export( $ok, true ) . "\n";

// ۳) بکاپ اکسل واقعی از مسیر TppSalary_Backup::make — تأیید پسوند و محتوا
$dirp = '/tmp/xlsxfixtures';
if ( ! is_dir( $dirp ) ) { mkdir( $dirp, 0777, true ); }
$path = TppSalary_Backup::make( 'xlsx', 'manual' );
if ( is_wp_error( $path ) ) {
        fwrite( STDERR, 'backup make error: ' . $path->get_error_message() . "\n" );
        exit( 1 );
}
echo "BACKUP PATH: $path\n";
if ( ! preg_match( '/\.xlsx$/', $path ) ) {
        fwrite( STDERR, "FAIL: backup extension is not .xlsx\n" );
        exit( 1 );
}
// کپی برای اعتبارسنجی پایتون (پوشه بکاپ شبیه‌ساز)
copy( $path, '/tmp/xlsxfixtures/backup-real.xlsx' );
echo "fixture copied\n";

// ۴) بکاپ excel قدیمی → باید xlsx بسازد (سازگاری قدیمی)
$path2 = TppSalary_Backup::make( 'excel', 'manual' );
if ( is_wp_error( $path2 ) ) { fwrite( STDERR, 'backup(excel) error: ' . $path2->get_error_message() . "\n" ); exit( 1 ); }
if ( ! preg_match( '/\.xlsx$/', $path2 ) ) {
        fwrite( STDERR, "FAIL: legacy 'excel' type did not produce .xlsx: $path2\n" );
        exit( 1 );
}
echo "LEGACY 'excel' → xlsx OK\n";
echo "ALL FIXTURES DONE\n";
