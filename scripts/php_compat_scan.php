<?php
/**
 * اسکن سینتکس پلاگین برای یافتن ویژگی‌های نیازمند PHP جدید
 * هدف: مطابقت با PHP 7.2 (حداقل رایج روی هاست‌های اشتراکی ایران)
 */
$dir = '/home/z/my-project/build/tpp_salary';
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$findings = [];
foreach ($rii as $f) {
    if (!$f->isFile() || substr($f->getFilename(), -4) !== '.php') continue;
    if (strpos($f->getPathname(), '/lib/') !== false) continue; // tFPDF کتابخانه شناخته‌شده
    $src = file_get_contents($f->getPathname());
    $rel = str_replace($dir.'/', '', $f->getPathname());

    // PHP 7.4: arrow functions
    if (preg_match('/\bfn\s*\(/', $src)) $findings[] = "$rel: arrow function fn() (PHP 7.4+)";
    // PHP 7.4: ??=
    if (preg_match('/\?\?=/', $src)) $findings[] = "$rel: ??= operator (PHP 7.4+)";
    // PHP 7.4: typed properties
    if (preg_match('/^\s*(public|private|protected)\s+(int|string|bool|float|array|\?[a-zA-Z_])/m', $src)) $findings[] = "$rel: typed property (PHP 7.4+)";
    // PHP 8.0: match
    if (preg_match('/\bmatch\s*\(/', $src)) $findings[] = "$rel: match() (PHP 8.0+)";
    // PHP 8.0: named arguments
    // PHP 8.0: constructor promotion
    if (preg_match('/function\s+__construct\s*\(\s*(public|private|protected)\s+/', $src)) $findings[] = "$rel: constructor promotion (PHP 8.0+)";
    // PHP 8.1: readonly / enum
    if (preg_match('/\breadonly\s+\$/', $src)) $findings[] = "$rel: readonly property (PHP 8.1+)";
    if (preg_match('/^\s*enum\s+\w+/m', $src)) $findings[] = "$rel: enum (PHP 8.1+)";
    // PHP 8.0-only functions
    foreach (['str_contains','str_starts_with','str_ends_with','array_is_list'] as $fn) {
        if (preg_match('/\b'.$fn.'\s*\(/', $src)) $findings[] = "$rel: $fn() (PHP 8.0+)";
    }
    // PHP 7.3-only functions
    foreach (['array_key_first','array_key_last','is_countable'] as $fn) {
        if (preg_match('/\b'.$fn.'\s*\(/', $src)) $findings[] = "$rel: $fn() (PHP 7.3+) — نیاز به بررسی در PHP 7.2";
    }
    // PHP 7.4-only functions
    foreach (['mb_str_split'] as $fn) {
        if (preg_match('/\b'.$fn.'\s*\(/', $src)) $findings[] = "$rel: $fn() (PHP 7.4+)";
    }
    // PHP 7.1: multi-catch
    if (preg_match('/catch\s*\(\s*\w+\s*\|\s*\w+/', $src)) $findings[] = "$rel: multi-catch (PHP 7.1+)";
    // تابع‌های فقط PHP 8.2
    foreach (['curl_upload_to'] as $fn) {}
}
echo $findings ? implode("\n", array_unique($findings))."\n" : "NO MODERN SYNTAX FOUND — compatible with PHP 7.2+\n";

// بررسی فراخوانی توابعی که ممکن است روی هاست نباشند (extension-based)
echo "\n=== mb_* usage (needs mbstring ext) ===\n";
foreach ($findings as $x) {}
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$mb = [];
$zip = [];
$gd = [];
foreach ($rii as $f) {
    if (!$f->isFile() || substr($f->getFilename(), -4) !== '.php') continue;
    if (strpos($f->getPathname(), '/font/') !== false) continue;
    $src = file_get_contents($f->getPathname());
    $rel = str_replace($dir.'/', '', $f->getPathname());
    if (preg_match_all('/\bmb_[a-z_]+\s*\(/', $src, $m)) $mb[$rel] = array_unique($m[0]);
    if (preg_match('/new\s+ZipArchive/', $src)) $zip[] = $rel;
    if (preg_match('/\b(imagecreate|imagesx|imagettftext)\b/', $src, $m)) $gd[$rel] = array_unique($m[0]);
}
foreach ($mb as $f2 => $fns) echo "$f2: ".implode(', ', $fns)."\n";
echo "=== ZipArchive usage ===\n".(count($zip) ? implode("\n", $zip) : "(none)")."\n";
echo "=== GD usage ===\n".(count($gd) ? print_r($gd, true) : "(none)")."\n";
