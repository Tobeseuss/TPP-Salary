<?php
/**
 * تست موتور PDF فارسی
 */
define( 'ABSPATH', '/tmp/' );
define( 'TPP_SALARY_DIR', '/home/z/my-project/build/tpp_salary/' );
function tpp_salary_get_setting( $k, $d = '' ) { return $d; }
error_reporting( E_ALL );

require TPP_SALARY_DIR . 'includes/class-tppsalary-pdf.php';

$pdf = new TppSalary_PDF( 'P', 'mm', 'A4' );
$pdf->AddPage();

// تست ۱: متن فارسی ساده.
$pdf->SetFont( 'vazir', 'B', 16 );
$pdf->faCell( 190, 12, 'فیش حقوقی و دستمزد', 1, 1, 'C' );

// تست ۲: ترکیب عدد و حروف.
$pdf->SetFont( 'vazir', '', 12 );
$pdf->faCell( 190, 10, 'دستمزد روزانه مرجع: 5,593,757 ریال', 1, 1, 'C' );
$pdf->faCell( 190, 10, 'حق مسکن و حق بن (مرداد ۱۴۰۵)', 1, 1, 'C' );

// تست ۳: لگاتور لا.
$pdf->faCell( 190, 10, 'کالا پلاک اعداد 12345 و بانک ملی', 1, 1, 'C' );

// تست ۴: نیم‌فاصله.
$pdf->faCell( 190, 10, 'می‌خواهم ساختارهای مالی‌ای که دادیم', 1, 1, 'C' );

// تست ۵: جدول دو ستونه.
$pdf->SetFont( 'vazir', '', 10 );
$pdf->SetFillColor( 220, 226, 243 );
$pdf->faCell( 95, 9, 'عنوان', 1, 0, 'C', true );
$pdf->faCell( 95, 9, 'مقدار', 1, 1, 'C', true );
$pdf->faCell( 95, 9, 'حقوق پایه', 1, 0, 'R' );
$pdf->faCell( 95, 9, '173,406,467', 1, 1, 'C' );
$pdf->faCell( 95, 9, 'جریمه غیبت', 1, 0, 'R' );
$pdf->faCell( 95, 9, '-10,683,840', 1, 1, 'C' );

// تست ۶: عرض رشته.
$pdf->SetFont( 'vazir', 'B', 12 );
$w = $pdf->faGetStringWidth( 'اسماعیل کمال آبادی' );
$pdf->faCell( 190, 10, 'عرض رشته "اسماعیل کمال آبادی" = ' . round( $w, 1 ) . 'mm', 1, 1, 'C' );

$pdf->Output( 'F', '/home/z/my-project/build/test_persian.pdf' );
echo "PDF OK\n";
echo 'SHAPE TEST: ' . bin2hex( mb_convert_encoding( TppSalary_PDF::shape( 'حقوق' ), 'UTF-16BE', 'UTF-8' ) ) . "\n";
