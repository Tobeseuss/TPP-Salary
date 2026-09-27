<?php
/**
 * باطری تست شکل‌دهی فارسی PDF — متون واقعی فیش حقوقی
 */
define('ABSPATH', '/tmp/wp-sim/');
$src = file_get_contents('/home/z/my-project/scripts/wp_sim_bootstrap.php');
$cut = strpos($src, "// ---------- LOAD PLUGIN ----------");
eval(preg_replace("/^\s*<\?php/", "", substr($src, 0, $cut)));
$GLOBALS['sim_options']['tpp_salary_settings'] = array('digits_fa' => 1, 'currency' => 'ریال');
require '/home/z/my-project/build/tpp_salary/tpp-salary.php';

$cases = array(
    'فیش حقوقی کارکرد مرداد ۱۴۰۵',
    'حقوق ناخالص: 381,381,596 ریال',
    'کسر بیمه (۷٪) = 18,948,842',
    'می‌شود و خانواده‌ها (نیم‌فاصله)',
    'شرکت پیشرو پروژه بومهن',
    'شماره شبا: IR580540105180011273413001',
    'کسورات: جریمه غیبت -2,400,000',
    'گزارش لیست حقوق و دستمزد (۱۴۰۵/۰۵)',
    'حق اولاد هر فرزند: 1,234,567 - 3 فرزند',
    'ایاب و ذهاب + حق مسکن + حق بن',
);
foreach ($cases as $t) {
    $shaped = TppSalary_PDF::shape(TppSalary_PDF::fa_digits($t));
    echo str_pad(mb_strlen($t), 3, ' ', STR_PAD_LEFT)." chars | ".mb_strlen($shaped)." shaped | OK: {$t}\n";
}

// رندر صفحه تست و تبدیل به PNG برای بررسی بصری
$pdf = new TppSalary_PDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('vazir', '', 13);
$y = 20;
foreach ($cases as $t) {
    $pdf->SetY($y);
    $pdf->faCell(190, 10, $t, 1, 0, 'R');
    $y += 13;
}
$pdf->SetY($y + 5);
$pdf->SetFont('vazir', 'B', 15);
$pdf->faCell(190, 12, 'جدول جمع‌بندی — خالص پرداختی 362,432,754 ریال', 1, 0, 'C');
$out = $pdf->Output('S');
file_put_contents('/home/z/my-project/build/test_dark_persian.pdf', $out);
echo "PDF rendered: ".strlen($out)." bytes\n";
echo "DONE\n";
