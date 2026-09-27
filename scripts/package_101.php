<?php
/**
 * بسته‌بندی نهایی پلاگین tpp_Salary v1.0.1
 * - فایل‌های کش موقتی سرور توسعه (mtx/cw) را حذف می‌کند
 * - ZIP پلاگین خالص + ZIP کامل (پلاگین + caddy + مستندات)
 */
$base = '/home/z/my-project';
$plugin_dir = "$base/build/tpp_salary";
$out_plugin = "$base/download/tpp_salary-1.6.2-plugin.zip";
$out_full   = "$base/download/tpp-salary-v1.6.2-full.zip";

// الگوهای حذفی (فایل‌های کش موقتی که tFPDF روی سرور مقصد بازتولید می‌کند)
$exclude_basenames = ['.', '..'];
function is_cache_file($name) {
    $base = basename($name);
    return preg_match('/\.mtx\.php$/', $base) || preg_match('/\.cw\.dat$/', $base) || preg_match('/\.cw127\.php$/', $base);
}

function collect_files($dir, $prefix = '') {
    $files = [];
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir === '' ? $item : "$dir/$item";
        if (is_dir($path)) {
            $files = array_merge($files, collect_files($path, "$prefix$item/"));
        } else {
            $files[] = [$path, "$prefix$item"];
        }
    }
    return $files;
}

function build_zip($out, array $entries) {
    if (file_exists($out)) unlink($out);
    $zip = new ZipArchive();
    if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fwrite(STDERR, "FAIL open $out\n");
        exit(1);
    }
    foreach ($entries as $e) {
        list($src, $zipname) = $e;
        $zip->addFile($src, $zipname);
    }
    $zip->close();
    $size = filesize($out);
    echo "OK  $out  (" . number_format($size / 1024, 1) . " KB, " . count($entries) . " فایل)\n";
}

// ---------- ZIP پلاگین خالص ----------
$plugin_files = [];
foreach (collect_files($plugin_dir) as $e) {
    list($src, $name) = $e;
    $rel = "$src";
    $zipname = 'tpp_salary/' . substr($src, strlen($plugin_dir) + 1);
    if (is_cache_file($zipname)) continue;
    $plugin_files[] = [$src, $zipname];
}
build_zip($out_plugin, $plugin_files);

// ---------- ZIP کامل ----------
$full_files = [];
// 1) پلاگین
foreach ($plugin_files as $e) $full_files[] = $e;
// 2) caddy + مستندات سطح بالا
foreach (collect_files("$base/build/caddy") as $e) {
    list($src, $name) = $e;
    $full_files[] = [$src, "caddy/" . $name];
}
foreach (['CHANGELOG.md', 'README.md'] as $doc) {
    if (file_exists("$base/build/$doc")) $full_files[] = ["$base/build/$doc", $doc];
}
build_zip($out_full, $full_files);

// ---------- حذف نسخه‌های قدیمی ----------
$old_versions = array( '1.6.1', '1.6.0', '1.5.0', '1.4.1', '1.4.0', '1.3.2', '1.3.0', '1.2.0', '1.1.3', '1.1.2', '1.1.1', '1.1.0', '1.0.1' );
foreach ( $old_versions as $v ) {
    foreach ( array( "$GLOBALS[base]/download/tpp_salary-{$v}-plugin.zip", "$GLOBALS[base]/download/tpp-salary-v{$v}-full.zip" ) as $old ) {
        if ( file_exists( $old ) ) { unlink( $old ); echo "DEL $old\n"; }
    }
}

// ---------- اعتبارسنجی ----------
foreach ([$out_plugin, $out_full] as $z) {
    $za = new ZipArchive();
    $za->open($z);
    $cache_found = 0; $main = false;
    for ($i = 0; $i < $za->numFiles; $i++) {
        $n = $za->statIndex($i)['name'];
        if (is_cache_file($n)) $cache_found++;
        if ($n === 'tpp_salary/tpp-salary.php') $main = true;
    }
    $za->close();
    echo basename($z) . ": main=" . ($main ? 'YES' : 'NO') . " cache_files={$cache_found} => " . ($main && $cache_found === 0 ? 'PASS' : 'FAIL') . "\n";
}
echo "DONE\n";
