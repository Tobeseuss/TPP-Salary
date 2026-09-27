<?php
/**
 * استخراج توابع واقعی وردپرس از سورس دانلودشده — با شمارش آکولاد
 * خروجی: scripts/wp_core_ref/real_menu_lib.php
 */
error_reporting( E_ALL );

$src_plugin = __DIR__ . '/wp_core_ref/plugin-admin.php';
$src_format = __DIR__ . '/wp_core_ref/formatting.php';
$out_file   = __DIR__ . '/wp_core_ref/real_menu_lib.php';

function extract_func( $src, $name ) {
    $pos = strpos( $src, 'function ' . $name . '(' );
    if ( false === $pos ) {
        // fallback with spacing variants
        if ( ! preg_match( '/function\s+' . preg_quote( $name, '/' ) . '\s*\(/', $src, $m, PREG_OFFSET_CAPTURE ) ) {
            fwrite( STDERR, "NOT FOUND: $name\n" );
            exit( 1 );
        }
        $pos = $m[0][1];
    }
    $open = strpos( $src, '{', $pos );
    // احتمال docblock بین نام و آکولاد نیست؛ آکولاد اول بعد از پارامترهاست
    $depth = 0;
    for ( $i = $open, $l = strlen( $src ); $i < $l; $i++ ) {
        $ch = $src[ $i ];
        if ( '{' === $ch ) { $depth++; }
        elseif ( '}' === $ch ) {
            $depth--;
            if ( 0 === $depth ) {
                return substr( $src, $pos, $i - $pos + 1 );
            }
        }
    }
    fwrite( STDERR, "UNBALANCED: $name\n" );
    exit( 1 );
}

$src_p = file_get_contents( $src_plugin );
$src_f = file_get_contents( $src_format );

$funcs_p = array( 'add_menu_page', 'add_submenu_page', 'get_admin_page_parent', 'get_plugin_page_hook', 'get_plugin_page_hookname', 'user_can_access_admin_page' );
// sanitize_title از sim_bootstrap می‌آید (نسخه SIM_REAL_MENU دقیقاً همان رفتار را دارد)
$funcs_f = array( 'sanitize_title_with_dashes', 'utf8_uri_encode', 'seems_utf8', 'remove_accents' );

$php = "<?php\n/**\n * توابع واقعی وردپرس 6.6 — استخراج‌شده از سورس رسمی (wp-admin/includes/plugin.php و wp-includes/formatting.php)\n * فقط برای شبیه‌ساز تست منو استفاده می‌شود؛ بخشی از افزونه نیست.\n */\n\n";
$php .= "if ( ! defined( 'WP_PLUGIN_DIR' ) ) { define( 'WP_PLUGIN_DIR', '/tmp/wp-sim/plugins' ); }\n\n";
$php .= "// ---------------- plugin.php funcs ----------------\n";
foreach ( $funcs_p as $f ) {
    $php .= extract_func( $src_p, $f ) . "\n\n";
}
$php .= "// ---------------- formatting.php funcs ----------------\n";
foreach ( $funcs_f as $f ) {
    $php .= extract_func( $src_f, $f ) . "\n\n";
}
$php .= <<< 'EOT'
// ---------------- shims ----------------
// در وردپرس واقعی sanitize_title_with_dashes فیلتر پیش‌فرض sanitize_title است (default-filters.php).
// شبیه‌ساز apply_filters را خنثی اجرا می‌کند؛ پس فیلتر پیش‌فرض را دستی صدا می‌زنیم.
if ( ! function_exists( 'sim_default_sanitize_title_filter' ) ) {
        function sim_default_sanitize_title_filter( $title, $raw_title = '', $context = 'display' ) {
                return sanitize_title_with_dashes( $title, $raw_title, $context );
        }
}
if ( ! function_exists( 'set_url_scheme' ) ) {
        function set_url_scheme( $url, $scheme = null ) { return $url; }
}
if ( ! function_exists( 'plugin_basename' ) ) {
        // اسلاگ‌های افزونه بدون مسیر هستند؛ هویت کافی و دقیق است.
        function plugin_basename( $file ) { return $file; }
}
if ( ! function_exists( 'mbstring_binary_safe_encoding' ) ) {
        function mbstring_binary_safe_encoding( $reset = false ) {}
}
if ( ! function_exists( 'reset_mbstring_encoding' ) ) {
        function reset_mbstring_encoding() {}
}
if ( ! function_exists( 'get_locale' ) ) {
        function get_locale() { return 'fa_IR'; }
}
if ( ! function_exists( 'determine_locale' ) ) {
        function determine_locale() { return 'fa_IR'; }
}
// جایگزینی فراخوانی apply_filters داخل sanitize_title با فیلتر پیش‌فرض واقعی
$GLOBALS['real_menu_src_ok'] = true;
EOT;

// در کد استخراج‌شده sanitize_title از apply_filters استفاده می‌کند — جایگزین می‌کنیم
$php = str_replace(
    "apply_filters( 'sanitize_title', \$title, \$raw_title, \$context )",
    "sim_default_sanitize_title_filter( \$title, \$raw_title, \$context )",
    $php
);
if ( false === strpos( $php, 'sim_default_sanitize_title_filter( $title' ) ) {
    fwrite( STDERR, "WARN: apply_filters replacement did not land!\n" );
}

file_put_contents( $out_file, $php );
echo "WROTE $out_file (" . strlen( $php ) . " bytes)\n";
