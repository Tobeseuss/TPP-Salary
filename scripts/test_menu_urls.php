<?php
/**
 * تست واقع‌گرایانه منوی مدیریت — بازتولید دقیق رفتار وردپرس واقعی
 *
 * از توابع واقعی وردپرس 6.6 (استخراج‌شده از سورس رسمی) برای بازسازی دقیق
 * مکانیزم hookname/URL استفاده می‌کند؛ باگی را که کاربر گزارش کرد
 * (404 منو + «دسترسی ندارید») بازتولید و رفع آن را تأیید می‌کند.
 *
 * منطق URL مستقیماً از wp-admin/menu-header.php نسخه 6.6 بازسازی شده است:
 *   - اگر has_action(get_plugin_page_hookname(slug,parent)) برقرار نباشد
 *     لینک آیتم به slug خام تبدیل می‌شود (wp-admin/<slug>) => 404
 *   - user_can_access_admin_page اگر hookname در $_registered_pages نباشد => «دسترسی ندارید»
 */
error_reporting( E_ALL );

$GLOBALS['TEST_ASSERTS'] = array( 'pass' => 0, 'fail' => 0 );
function t( $label, $cond, $detail = '' ) {
    if ( $cond ) {
        $GLOBALS['TEST_ASSERTS']['pass']++;
        echo "  [OK]   $label\n";
    } else {
        $GLOBALS['TEST_ASSERTS']['fail']++;
        echo "  [FAIL] $label" . ( $detail ? " — $detail" : '' ) . "\n";
    }
}

define( 'SIM_REAL_MENU', 1 );
require __DIR__ . '/wp_sim_bootstrap.php';

// اکنون توابع واقعی وردپرس را روی شبیه‌ساز سوار می‌کنیم (قبل از شلیک admin_menu)
require __DIR__ . '/wp_core_ref/real_menu_lib.php';

echo "== plugin loaded; firing REAL admin_menu pipeline ==\n";

// globals واقعی وردپرس برای لایه منو
$GLOBALS['menu']                = array();
$GLOBALS['submenu']             = array();
$GLOBALS['admin_page_hooks']    = array();
$GLOBALS['_registered_pages']   = array();
$GLOBALS['_parent_pages']       = array();
$GLOBALS['_wp_real_parent_file'] = array();
$GLOBALS['_wp_menu_nopriv']     = array();
$GLOBALS['_wp_submenu_nopriv']  = array();
$GLOBALS['parent_file']         = '';
$GLOBALS['typenow']             = '';
$GLOBALS['pagenow']             = 'index.php'; // شبیه بازشدن پیشخوان
unset( $GLOBALS['plugin_page'] );

sim_do_action( 'admin_menu' );

$expected = array(
    'tpp-salary'              => 'top',
    'tpp-salary-register'     => 'sub',
    'tpp-salary-records'      => 'sub',
    'tpp-salary-centers'      => 'sub',
    'tpp-salary-banks'        => 'sub',
    'tpp-salary-employees'    => 'sub',
    'tpp-salary-import'       => 'sub',
    'tpp-salary-import-records' => 'sub',
    'tpp-salary-settings'     => 'sub',
    'tpp-salary-report'       => 'sub',
    'tpp-salary-bank-report'  => 'sub',
    'tpp-salary-payslips'     => 'sub',
    'tpp-salary-backup'       => 'sub',
    'tpp-salary-offline'      => 'sub',
);

echo "\n-- 1) همه صفحات موردانتظار ثبت شده‌اند؟\n";
$sub_slugs = array();
foreach ( (array) $GLOBALS['submenu'] as $parent => $items ) {
    foreach ( $items as $it ) { $sub_slugs[] = $it[2]; }
}
$top_slugs = array();
foreach ( (array) $GLOBALS['menu'] as $it ) { $top_slugs[] = $it[2]; }
foreach ( $expected as $slug => $kind ) {
    if ( 'top' === $kind ) {
        t( "ثبت منوی سطح‌بالا: $slug", in_array( $slug, $top_slugs, true ) );
    } else {
        t( "ثبت زیرمنو: $slug", in_array( $slug, $sub_slugs, true ) );
    }
}

echo "\n-- 2) URLهای منو (منطق واقعی menu-header.php) — نباید slug خام باشد\n";

/**
 * بازسازی دقیق شرط‌های لینک در wp-admin/menu-header.php (6.6):
 * if ( !empty($menu_hook) || (('index.php'!==$slug) && file_exists(WP_PLUGIN_DIR."/slug") && !file_exists(ABSPATH."/wp-admin/slug")) )
 *     URL = admin.php?page=slug
 * else URL = slug (خام!)
 */
function menu_item_url( $slug, $parent ) {
    $menu_hook = get_plugin_page_hook( $slug, $parent );
    $menu_file = $slug;
    $pos       = strpos( $menu_file, '?' );
    if ( false !== $pos ) { $menu_file = substr( $menu_file, 0, $pos ); }
    if ( ! empty( $menu_hook )
        || ( ( 'index.php' !== $slug )
            && file_exists( WP_PLUGIN_DIR . "/$menu_file" )
            && ! file_exists( ABSPATH . "/wp-admin/$menu_file" ) ) ) {
        return array( 'admin.php?page=' . $slug, true );
    }
    return array( $slug, false ); // ← همان 404 کاربر
}

$bad_urls = array();
// منوی سطح‌بالا: چون زیرمنو دارد، لینک آن بر مبنای اولین زیرمنو است (submenu_as_parent)
$first_sub = isset( $GLOBALS['submenu']['tpp-salary'] ) ? $GLOBALS['submenu']['tpp-salary'][0][2] : null;
if ( null !== $first_sub ) {
    list( $top_url, $top_ok ) = menu_item_url( $first_sub, 'tpp-salary' );
    t( "URL منوی سطح‌بالا (حقوق و دستمزد) => $top_url", $top_ok, 'همان 404 گزارش کاربر' );
    if ( ! $top_ok ) { $bad_urls['top-level'] = $top_url; }
}
foreach ( $expected as $slug => $kind ) {
    if ( 'sub' !== $kind || ! in_array( $slug, $sub_slugs, true ) ) { continue; }
    list( $u, $ok ) = menu_item_url( $slug, 'tpp-salary' );
    t( "URL زیرمنو $slug => $u", $ok );
    if ( ! $ok ) { $bad_urls[ $slug ] = $u; }
}

echo "\n-- 3) شبیه‌سازی بازکردن admin.php?page=<slug> (user_can_access_admin_page واقعی)\n";
foreach ( $expected as $slug => $kind ) {
    if ( 'top' === $kind ) { continue; }
    $GLOBALS['plugin_page'] = $slug;
    $GLOBALS['pagenow']     = 'admin.php';
    $ok = user_can_access_admin_page();
    unset( $GLOBALS['plugin_page'] );
    t( "دسترسی به $slug", $ok, 'نتیجه واقعی وردپرس: «متأسفانه شما مجاز به دسترسی به این صفحه نیستید»' );
}

echo "\n-- 4) صدا زدن واقعی callback هر صفحه (do_action روی hookname واقعی)\n";
foreach ( $expected as $slug => $kind ) {
    $hookname = get_plugin_page_hookname( $slug, 'top' === $kind ? '' : 'tpp-salary' );
    $has      = has_action( $hookname );
    t( "hookname صفحه $slug دارای callback است ($hookname)", (bool) $has );
}

echo "\n==== نتیجه: PASS=" . $GLOBALS['TEST_ASSERTS']['pass'] . " FAIL=" . $GLOBALS['TEST_ASSERTS']['fail'] . " ====\n";
if ( ! empty( $bad_urls ) ) {
    echo "URLهای خراب (دقیقاً 404 کاربر):\n";
    foreach ( $bad_urls as $slug => $u ) { echo "  - $slug => http://localhost/wp/wp-admin/$u\n"; }
}
exit( $GLOBALS['TEST_ASSERTS']['fail'] > 0 ? 1 : 0 );
