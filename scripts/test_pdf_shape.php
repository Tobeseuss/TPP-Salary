<?php
/**
 * تست موتور شکل‌دهی فارسی PDF — نسخه 1.5.0
 * اجرا: /home/z/my-project/tools/php scripts/test_pdf_shape.php
 *
 * چیدمان گلیف‌ها: خروجی «ترتیب دیداری چپ‌به‌راست» است.
 */
error_reporting( E_ALL );
define( 'ABSPATH', '/tmp/' );
define( 'TPP_SALARY_DIR', '/home/z/my-project/build/tpp_salary/' );
require TPP_SALARY_DIR . 'includes/class-tppsalary-pdf.php';

$fail = 0;
function check( $label, $cond ) {
        global $fail;
        echo ( $cond ? "PASS" : "FAIL" ) . ' — ' . $label . "\n";
        if ( ! $cond ) { $fail++; }
}
function cps( $s ) {
        $out = array();
        $n = mb_strlen( $s, 'UTF-8' );
        for ( $i = 0; $i < $n; $i++ ) {
                $out[] = dechex( unpack( 'N', mb_convert_encoding( mb_substr( $s, $i, 1, 'UTF-8' ), 'UTF-32BE', 'UTF-8' ) )[1] );
        }
        return implode( ' ', $out );
}
function C( $cp ) { return mb_convert_encoding( pack( 'N', $cp ), 'UTF-8', 'UTF-32BE' ); }

// 1) حقوق = ح ق و ق → دیداری: ق(جداشده) و(پایانی) ق(میانی) ح(آغازین)
$s = TppSalary_PDF::shape( 'حقوق' );
check( 'حقوق joined: ' . cps( $s ), $s === C(0xFED5) . C(0xFEEE) . C(0xFED8) . C(0xFEA3) );

// 2) محمد = م ح م د → دیداری: د(پایانی) م(میانی) ح(میانی) م(آغازین)
$s = TppSalary_PDF::shape( 'محمد' );
check( 'محمد joined: ' . cps( $s ), $s === C(0xFEAA) . C(0xFEE4) . C(0xFEA4) . C(0xFEE3) );

// 3) دستمزد = د س ت م ز د → دیداری: د(جدا) ز(پایانی) م(میانی) ت(میانی) س(آغازین) د(جدا)
$s = TppSalary_PDF::shape( 'دستمزد' );
check( 'دستمزد joined: ' . cps( $s ), $s === C(0xFEA9) . C(0xFEB0) . C(0xFEE4) . C(0xFE98) . C(0xFEB3) . C(0xFEA9) );

// 4) رضا = ر ض ا → دیداری: ا(پایانی) ض(آغازین) ر(جداشده)
$s = TppSalary_PDF::shape( 'رضا' );
check( 'رضا joined: ' . cps( $s ), $s === C(0xFE8E) . C(0xFEBF) . C(0xFEAD) );

// 5) لگاتور لا
$s = TppSalary_PDF::shape( 'لا' );
check( 'لا → لگاتور FEFB: ' . cps( $s ), $s === C(0xFEFB) );
$s = TppSalary_PDF::shape( 'جلا' );
check( 'جلا → لگاتور FEFC: ' . cps( $s ), $s === C(0xFEFC) . C(0xFE9F) );

// 6) نیم‌فاصله اتصال را قطع می‌کند: «می‌شود»
//    دیداری: د(جداشده، چون و به بعد وصل نمی‌شود) و(پایانی) ش(آغازین) ZWNJ ی(پایانی) م(آغازین)
$s = TppSalary_PDF::shape( 'می‌شود' );
check( 'ZWNJ قطع اتصال: ' . cps( $s ), $s === C(0xFEA9) . C(0xFEEE) . C(0xFEB7) . C(0x200C) . C(0xFBFD) . C(0xFEE3) );

// 7) عدد با جداکننده فارسی: «۱٬۰۰۰ تومان» — ارقام یک‌پارچه و ترتیب درست (عدد راستِ واژه)
$s = TppSalary_PDF::shape( '۱٬۰۰۰ تومان' );
$needle_word = TppSalary_PDF::shape( 'تومان' );
check( 'عدد ۱٬۰۰۰ سالم: ' . $s, false !== mb_strpos( $s, '۱٬۰۰۰', 0, 'UTF-8' ) && false !== mb_strpos( $s, $needle_word ) && mb_strpos( $s, '۱٬۰۰۰' ) > mb_strpos( $s, $needle_word ) );

// 8) «مرداد ۱۴۰۵» — عدد سمت چپِ واژه (درستِ RTL)
$s = TppSalary_PDF::shape( 'مرداد ۱۴۰۵' );
$needle_m = TppSalary_PDF::shape( 'مرداد' );
check( 'مرداد ۱۴۰۵: ' . $s, false !== mb_strpos( $s, '۱۴۰۵' ) && false !== mb_strpos( $s, $needle_m ) && mb_strpos( $s, '۱۴۰۵' ) < mb_strpos( $s, $needle_m ) );

// 9) متن با اعراب (شفاف): «عَلی»
$s = TppSalary_PDF::shape( 'عَلی' );
check( 'اعراب شفاف: ' . cps( $s ), $s === C(0xFBFD) . C(0xFEE0) . C(0x064E) . C(0xFECB) );

// 10) سالار = س ا ل ا ر → دیداری: ر(جدا) لا(لگاتور جداشده FEFB — ل به اِ قبل وصل نیست) ا(پایانیِ سـا) س(آغازین)
$s = TppSalary_PDF::shape( 'سالار' );
check( 'سالار (لا وسط کلمه): ' . cps( $s ), $s === C(0xFEAD) . C(0xFEFB) . C(0xFE8E) . C(0xFEB3) );

// 11) fa_digits — نسخه 1.7.3: همیشه ارقام انگلیسی (نام قدیمی برای سازگاری)
check( 'fa_digits → انگلیسی', TppSalary_PDF::fa_digits( '1405/05' ) === '1405/05' && TppSalary_PDF::fa_digits( '۱۴۰۵' ) === '1405' && TppSalary_PDF::fa_digits( '٤٥' ) === '45' );

// 12) سلام = س ل ا م → دیداری: م(جدا — بعد از ا) لا(لگاتور میانی FEFC) س(آغازین)
$s = TppSalary_PDF::shape( 'سلام' );
check( 'سلام: ' . cps( $s ), $s === C(0xFEE1) . C(0xFEFC) . C(0xFEB3) );

echo $fail ? "\n{$fail} FAILED\n" : "\nALL PASS\n";
exit( $fail ? 1 : 0 );
