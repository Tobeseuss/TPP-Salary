<?php
/**
 * تست نهایی: لود کامل پلاگین + موتور PDF در محیط شبیه‌ساز وردپرس
 * (بخش stub ها از wp_sim_bootstrap.php تا قبل LOAD PLUGIN)
 */
$src = file_get_contents('/home/z/my-project/scripts/wp_sim_bootstrap.php');
$cut = strpos($src, "// ---------- LOAD PLUGIN ----------");
$stub = substr($src, 0, $cut);
$stub = preg_replace('/^\s*<\?php/', '', $stub); // eval تگ باز نمی‌خواهد
eval($stub);

try {
    require '/home/z/my-project/build/tpp_salary/tpp-salary.php';
    echo "PDF class loaded: "; var_dump(class_exists('TppSalary_PDF'));
    echo "tFPDF loaded: "; var_dump(class_exists('tFPDF'));
    echo "TTFontFile loaded: "; var_dump(class_exists('TTFontFile'));

    $pdf = new TppSalary_PDF('P', 'mm', 'A4');
    $ref = new ReflectionProperty('tFPDF', 'fonts');
    $ref->setAccessible(true);
    echo "TppSalary_PDF instantiated OK, fonts: ".count($ref->getValue($pdf))."\n";

    $shaped = TppSalary_PDF::shape('سلام دنیا');
    echo "shape() OK (".strlen($shaped)." bytes)\n";

    // رندر واقعی PDF کوچک
    $pdf->AddPage();
    $pdf->SetFont('vazir', '', 12);
    $pdf->faCell(0, 10, 'تست فیش حقوقی — رفع خطای فعال‌سازی', 0, 1, 'C');
    $out = $pdf->Output('S');
    echo "PDF render OK: ".strlen($out)." bytes\n";

    // پس از رندر، کش بازتولید شده و باید مسیر صحیح (محلی) داشته باشد
    $mtx = '/home/z/my-project/build/tpp_salary/lib/tfpdf/font/unifont/vazirmatn-regular.mtx.php';
    if (file_exists($mtx)) {
        $c = file_get_contents($mtx);
        $ok = strpos($c, '/home/z/my-project/build') !== false;
        echo "regenerated cache path is local: ".($ok ? "YES (correct)" : "NO (stale!)")."\n";
    }
    echo "PDF ENGINE GREEN\n";
} catch (Throwable $e) {
    echo "FATAL: ".get_class($e).": ".$e->getMessage()." @ ".$e->getFile().":".$e->getLine()."\n";
    exit(1);
}
