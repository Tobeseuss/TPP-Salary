<?php
/**
 * شبیه‌ساز محیط وردپرس برای یافتن خطای مرگبار فعال‌سازی
 * همه توابع/کلاس‌های موردنیاز پلاگین را stub می‌کند و سپس فایل اصلی را لود می‌کند.
 */
error_reporting(E_ALL);
define('ABSPATH', '/tmp/wp-sim/');
// فایل upgrade.php وردپرس (خواسته create_tables) — اگر غایب باشد stub ساخته می‌شود.
if ( ! is_dir( ABSPATH . 'wp-admin/includes' ) ) { @mkdir( ABSPATH . 'wp-admin/includes', 0755, true ); }
if ( ! file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) { @file_put_contents( ABSPATH . 'wp-admin/includes/upgrade.php', "<?php\n// sim stub\n" ); }
define('ARRAY_A', 'ARRAY_A');
define('ARRAY_N', 'ARRAY_N');
define('OBJECT', 'OBJECT');
/* نسخه 1.8.1 — ثابت‌های زمانی هسته وردپرس (در وردپرس واقعی همیشه تعریف می‌شوند) */
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) { define( 'MINUTE_IN_SECONDS', 60 ); }
if ( ! defined( 'HOUR_IN_SECONDS' ) )   { define( 'HOUR_IN_SECONDS', 3600 ); }
if ( ! defined( 'DAY_IN_SECONDS' ) )    { define( 'DAY_IN_SECONDS', 86400 ); }

// ---------- GLOBAL ERROR CAPTURE ----------
$GLOBALS['caught'] = [];
set_error_handler(function($no,$str,$file,$line){
    throw new ErrorException($str, 0, $no, $file, $line);
});

// ---------- WP stub functions ----------
function wpdb_stub_connect_error(){ return ''; }
class wpdb {
    public $prefix = 'wp_';
    public $charset_collate = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';
    public $insert_id = 1;
    public $usermeta = 'wp_usermeta';
    public $options = 'wp_options';
    public $users = 'wp_users';
    public function __call($name, $args){ return null; }
    public function prepare($q, ...$a){ return $q; }
    public function query($q){ return 1; }
    public function get_var($q){ return null; }
    public function get_results($q, $o = null){ return []; }
    public function get_row($q){ return null; }
    public function get_col($q){ return []; }
    public function insert($t,$d,$f=null){ return 1; }
    public function update($t,$d,$w,$f=null,$wf=null){ return 1; }
    public function delete($t,$w,$f=null){ return 1; }
    public function replace($t,$d,$f=null){ return 1; }
}
if ( defined( 'SIM_SQLITE_WPDB' ) ) {
    /**
     * wpdb پشتیبان SQLite (در حافظه) — برای تست‌های مهاجرت داده با SQL واقعی.
     * ترجمه‌ی حداقلیِ MySQL→SQLite: SHOW TABLES LIKE / SHOW COLUMNS / prepare / insert / update
     */
    class wpdb_sqlite extends wpdb {
        public $pdo;
        public $last_error = '';
        public function __construct() {
            $this->pdo = new PDO( 'sqlite::memory:' );
            $this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT );
            $this->pdo->exec( 'CREATE TABLE IF NOT EXISTS wp_usermeta (umeta_id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, meta_key TEXT, meta_value TEXT)' );
            $this->pdo->exec( 'CREATE TABLE IF NOT EXISTS wp_options (option_id INTEGER PRIMARY KEY AUTOINCREMENT, option_name TEXT, option_value TEXT, autoload TEXT)' );
            if ( defined( 'SIM_SQLITE_USERS' ) ) {
                // لایه کاربر واقعی روی SQLite — فقط برای تست‌هایی که به کاربران واقعی نیاز دارند.
                $this->pdo->exec( 'CREATE TABLE IF NOT EXISTS wp_users (ID INTEGER PRIMARY KEY AUTOINCREMENT, user_login TEXT, user_pass TEXT, user_email TEXT, display_name TEXT)' );
                $GLOBALS['sim_users_pdo'] = $this->pdo;
            }
        }
        private function lit( $v, $fmt ) {
            if ( '%d' === $fmt ) { return (string) intval( $v ); }
            if ( '%f' === $fmt ) { return (string) floatval( $v ); }
            return "'" . str_replace( "'", "''", (string) $v ) . "'";
        }
        public function prepare( $q, ...$a ) {
            if ( empty( $a ) ) { return $q; }
            /* هم‌سان با وردپرس واقعی: اگر تنها آرگومان یک آرایه بود، باز می‌شود (prepare($q, $array)). */
            if ( 1 === count( $a ) && is_array( $a[0] ) ) { $a = $a[0]; }
            $i = 0;
            return preg_replace_callback( '/%[sdf]/', function ( $m ) use ( &$i, $a ) {
                $v = isset( $a[ $i ] ) ? $a[ $i ] : null;
                $i++;
                return $this->lit( $v, $m[0] );
            }, $q );
        }
        private function execq( $sql ) {
            $st = $this->pdo->query( $sql );
            $e  = $this->pdo->errorInfo();
            $this->last_error = ( '00000' !== $e[0] ) ? implode( ' | ', $e ) . ' @ ' . $sql : '';
            return $st;
        }
        public function query( $q ) {
            $t = strtoupper( trim( $q ) );
            if ( 0 === strpos( $t, 'START TRANSACTION' ) ) { $this->pdo->beginTransaction(); return 1; }
            if ( 0 === strpos( $t, 'COMMIT' ) ) { if ( $this->pdo->inTransaction() ) { $this->pdo->commit(); } return 1; }
            if ( 0 === strpos( $t, 'ROLLBACK' ) ) { if ( $this->pdo->inTransaction() ) { $this->pdo->rollBack(); } return 1; }
            $st = $this->execq( $q );
            return $st ? $st->rowCount() : 0;
        }
        public function get_var( $q, $x = 0, $y = 0 ) {
            if ( preg_match( '/^SHOW\s+TABLES\s+LIKE\s+(.+)$/is', $q, $m ) ) {
                $name = trim( $m[1], " '\"`" );
                $st   = $this->pdo->prepare( "SELECT name FROM sqlite_master WHERE type='table' AND name = ?" );
                $st->execute( array( $name ) );
                $r = $st->fetchAll( PDO::FETCH_NUM );
                return $r ? $r[0][0] : null;
            }
            $st = $this->execq( $q );
            if ( ! $st ) { return null; }
            $row = $st->fetch( PDO::FETCH_NUM );
            return $row ? $row[0] : null;
        }
        public function get_col( $q, $x = 0 ) {
            if ( preg_match( '/^SHOW\s+COLUMNS\s+FROM\s+([^\s;]+)/i', $q, $m ) ) {
                $table = trim( $m[1], "`'" );
                $st    = $this->pdo->query( 'PRAGMA table_info(' . $table . ')' );
                $out   = array();
                while ( $r = $st->fetch( PDO::FETCH_ASSOC ) ) { $out[] = $r['name']; }
                return $out;
            }
            $st = $this->execq( $q );
            if ( ! $st ) { return array(); }
            $out = array();
            while ( $r = $st->fetch( PDO::FETCH_NUM ) ) { $out[] = $r[0]; }
            return $out;
        }
        public function get_row( $q, $o = null ) { $st = $this->execq( $q ); if ( ! $st ) { return ( ARRAY_A === $o ) ? array() : null; } return ( ARRAY_A === $o ) ? $st->fetch( PDO::FETCH_ASSOC ) : $st->fetch( PDO::FETCH_OBJ ); }
        public function get_results( $q, $o = null ) {
            $st = $this->execq( $q );
            if ( ! $st ) { return array(); }
            if ( ARRAY_A === $o ) { return $st->fetchAll( PDO::FETCH_ASSOC ); }
            if ( ARRAY_N === $o ) { return $st->fetchAll( PDO::FETCH_NUM ); }
            return $st->fetchAll( PDO::FETCH_OBJ ); // پیش‌فرض واقعی وردپرس: آبجکت.
        }
        public function insert( $t, $d, $f = null ) {
            $cols = array(); $vals = array(); $i = 0;
            foreach ( $d as $k => $v ) {
                $cols[] = '`' . $k . '`';
                $fmt    = ( is_array( $f ) && isset( $f[ $i ] ) ) ? $f[ $i ] : '%s';
                $vals[] = $this->lit( $v, $fmt );
                $i++;
            }
            $ok = $this->query( 'INSERT INTO ' . $t . ' (' . implode( ', ', $cols ) . ') VALUES (' . implode( ', ', $vals ) . ')' );
            if ( $ok ) { $this->insert_id = (int) $this->pdo->lastInsertId(); }
            return $ok ? 1 : false;
        }
        public function update( $t, $d, $w, $f = null, $wf = null ) {
            $set = array(); $i = 0;
            foreach ( $d as $k => $v ) { $fmt = ( is_array( $f ) && isset( $f[ $i ] ) ) ? $f[ $i ] : '%s'; $set[] = '`' . $k . '` = ' . $this->lit( $v, $fmt ); $i++; }
            $whr = array(); $i = 0;
            foreach ( $w as $k => $v ) { $fmt = ( is_array( $wf ) && isset( $wf[ $i ] ) ) ? $wf[ $i ] : '%s'; $whr[] = '`' . $k . '` = ' . $this->lit( $v, $fmt ); $i++; }
            return $this->query( 'UPDATE ' . $t . ' SET ' . implode( ', ', $set ) . ' WHERE ' . implode( ' AND ', $whr ) );
        }
        public function delete( $t, $w, $f = null ) {
            $whr = array(); $i = 0;
            foreach ( (array) $w as $k => $v ) { $fmt = ( is_array( $f ) && isset( $f[ $i ] ) ) ? $f[ $i ] : '%s'; $whr[] = '`' . $k . '` = ' . $this->lit( $v, $fmt ); $i++; }
            return $this->query( 'DELETE FROM ' . $t . ' WHERE ' . implode( ' AND ', $whr ) );
        }
    }
    $GLOBALS['wpdb'] = new wpdb_sqlite();
} else {
    $GLOBALS['wpdb'] = new wpdb();
}

function __($s, $d = null){ return $s; }
function esc_html__($s, $d = null){ return $s; }
function esc_attr__($s, $d = null){ return $s; }
function esc_html($s){ return $s; }
function esc_attr($s){ return $s; }
function esc_textarea($s){ return $s; }
function esc_url($s){ return $s; }
function esc_url_raw($s){ return $s; }
function esc_js($s){ return $s; }
function wp_kses_post($s){ return $s; }
function plugin_dir_path($f){ return rtrim(dirname($f),'/').'/'; }
function plugin_dir_url($f){ return 'http://example.test/wp-content/plugins/'.basename(dirname($f)).'/'; }
function plugin_basename($f){
    $f = wp_normalize_path($f);
    // اسلاگ‌های خالی از مسیر (منوی مدیریت) باید دست‌نخورده بمانند — مطابق وردپرس واقعی
    if ( false === strpos($f, '/') ) { return $f; }
    return basename(dirname($f)).'/'.basename($f);
}
function register_activation_hook($f, $cb){ $GLOBALS['activation_hook'] = $cb; }
function register_deactivation_hook($f, $cb){ $GLOBALS['deactivation_hook'] = $cb; }
function add_action($h, $cb = null, $p = 10, $a = 1){
    if(!isset($GLOBALS['sim_hooks'][$h])) $GLOBALS['sim_hooks'][$h] = [];
    $GLOBALS['sim_hooks'][$h][] = ['cb'=>$cb,'p'=>$p];
    return true;
}
function add_filter($h, $cb = null, $p = 10, $a = 1){
    if(!isset($GLOBALS['sim_hooks'][$h])) $GLOBALS['sim_hooks'][$h] = [];
    $GLOBALS['sim_hooks'][$h][] = ['cb'=>$cb,'p'=>$p];
    return true;
}
function sim_do_action($h, ...$args){
    if(empty($GLOBALS['sim_hooks'][$h])) return;
    $list = $GLOBALS['sim_hooks'][$h];
    usort($list, function($x,$y){ return $x['p'] <=> $y['p']; });
    foreach($list as $entry){ if(is_callable($entry['cb'])) call_user_func_array($entry['cb'], $args); }
}
function sim_has_handler($h){
    return !empty($GLOBALS['sim_hooks'][$h]);
}
if ( ! function_exists( 'has_action' ) ) {
    function has_action($h, $cb = false){
        if ( empty( $GLOBALS['sim_hooks'][$h] ) ) { return false; }
        if ( false === $cb ) { return true; }
        foreach ( $GLOBALS['sim_hooks'][$h] as $e ) { if ( $e['cb'] === $cb ) { return true; } }
        return false;
    }
}
$GLOBALS['sim_hooks'] = [];
$GLOBALS['sim_menu'] = [];      // slug => ['parent','title','cap','cb','is_top']
if ( ! defined( 'SIM_REAL_MENU' ) ) {
function add_menu_page($pt, $mt, $cap, $slug, $cb = null, $icon = '', $pos = null){
    $GLOBALS['sim_menu'][$slug] = ['parent'=>'', 'title'=>$mt, 'cap'=>$cap, 'cb'=>$cb, 'is_top'=>true];
    return 'toplevel_page_' . $slug;
}
function add_submenu_page($parent, $pt, $mt, $cap, $slug, $cb = null){
    $GLOBALS['sim_menu'][$slug] = ['parent'=>$parent, 'title'=>$mt, 'cap'=>$cap, 'cb'=>$cb, 'is_top'=>false];
    return get_plugin_page_hookname_stub($slug, $parent);
}
function get_plugin_page_hookname_stub($slug, $parent){
    $p = $parent ? preg_replace('/[^A-Za-z0-9-]+/','-', $parent) : 'admin';
    return $p . '_page_' . $slug;
}
}
function do_action($h, ...$a){ return null; }
function apply_filters($h, $v, ...$a){ return $v; }
function has_filter($h, $cb = false){ return false; }
function add_shortcode($tag, $cb){}
function shortcode_atts($d, $a, $s = ''){ return array_merge($d, (array)$a); }
function wp_enqueue_style($h, $src = '', $d = [], $v = false, $m = 'all'){}
function wp_enqueue_script($h, $src = '', $d = [], $v = false, $f = false){}
function wp_localize_script($h, $o, $d){}
function wp_register_style($h, $src = '', $d = [], $v = false, $m = 'all'){}
function wp_register_script($h, $src = '', $d = [], $v = false, $f = false){}
function wp_enqueue_media(){}
function wp_add_inline_style($h, $d){}
function wp_add_inline_script($h, $d, $p = 'after'){}
function wp_nonce_field($a = -1, $n = '_wpnonce', $r = true, $e = true){ return ''; }
function wp_nonce_url($u, $a = -1){ return $u; }
function wp_verify_nonce($n, $a = -1){ return 1; }
function check_ajax_referer($a = -1, $q = false, $d = true){ return 1; }
function current_user_can($c, ...$a){ return true; }
function wp_get_current_user(){ return new WP_User(); }
function get_current_user_id(){ return 1; }
function is_user_logged_in(){ return true; }
function add_management_page(...$a){ return ''; }
function add_options_page(...$a){ return ''; }
function add_meta_boxes(...$a){}
function admin_url($p = ''){ return 'http://example.test/wp-admin/'.$p; }
function home_url($p = ''){ return 'http://example.test/'.$p; }
function site_url($p = ''){ return 'http://example.test/'.$p; }
function wp_upload_dir($t = null){ return ['basedir'=>'/tmp/wp-sim/uploads','baseurl'=>'http://example.test/wp-content/uploads','path'=>'/tmp/wp-sim/uploads','url'=>'http://example.test/wp-content/uploads']; }
function wp_mkdir_p($d){ return is_dir($d) ? true : @mkdir($d, 0755, true); }
function trailingslashit($s){ return rtrim($s,'/').'/'; }
function untrailingslashit($s){ return rtrim($s,'/'); }
function get_option($n, $d = false){ $opts = $GLOBALS['sim_options'] ?? []; return $opts[$n] ?? $d; }
function update_option($n, $v, $a = null){ $GLOBALS['sim_options'][$n] = $v; return true; }
function add_option($n, $v, $d = '', $a = null){ $GLOBALS['sim_options'][$n] = $v; return true; }
function delete_option($n){ unset($GLOBALS['sim_options'][$n]); return true; }
function get_user_meta($u, $k = '', $s = false){
        $pdo = $GLOBALS['sim_users_pdo'] ?? null;
        if ( ! $pdo ) { return []; }
        if ( '' === $k ) {
                $st = $pdo->prepare( 'SELECT meta_key, meta_value FROM wp_usermeta WHERE user_id = ?' );
                $st->execute( [ (int) $u ] );
                $out = [];
                foreach ( $st->fetchAll( PDO::FETCH_ASSOC ) as $r ) { $out[ $r['meta_key'] ] = sim_maybe_unserialize( $r['meta_value'] ); }
                return $out;
        }
        $st = $pdo->prepare( 'SELECT meta_value FROM wp_usermeta WHERE user_id = ? AND meta_key = ?' );
        $st->execute( [ (int) $u, (string) $k ] );
        $rows = $st->fetchAll( PDO::FETCH_COLUMN );
        if ( $s ) { return $rows ? sim_maybe_unserialize( $rows[0] ) : ''; }
        return array_map( 'sim_maybe_unserialize', $rows );
}
function sim_maybe_unserialize( $v ) {
        if ( is_string( $v ) && ( 'a:' === substr( $v, 0, 2 ) || 'O:' === substr( $v, 0, 2 ) ) ) {
                $u = @unserialize( $v );
                if ( false !== $u || 'b:0;' === $v ) { return $u; }
        }
        return $v;
}
function update_user_meta($u, $k, $v, $p = ''){
        $pdo = $GLOBALS['sim_users_pdo'] ?? null;
        if ( ! $pdo ) { return true; }
        $sv = is_array( $v ) || is_object( $v ) ? serialize( $v ) : (string) $v;
        $st = $pdo->prepare( 'DELETE FROM wp_usermeta WHERE user_id = ? AND meta_key = ?' );
        $st->execute( [ (int) $u, (string) $k ] );
        $st = $pdo->prepare( 'INSERT INTO wp_usermeta (user_id, meta_key, meta_value) VALUES (?, ?, ?)' );
        $st->execute( [ (int) $u, (string) $k, $sv ] );
        return true;
}
function delete_user_meta($u, $k, $p = ''){
        $pdo = $GLOBALS['sim_users_pdo'] ?? null;
        if ( ! $pdo ) { return true; }
        $st = $pdo->prepare( 'DELETE FROM wp_usermeta WHERE user_id = ? AND meta_key = ?' );
        $st->execute( [ (int) $u, (string) $k ] );
        return true;
}
function add_user_meta($u, $k, $v, $uq = false){
        $pdo = $GLOBALS['sim_users_pdo'] ?? null;
        if ( ! $pdo ) { return true; }
        $sv = is_array( $v ) || is_object( $v ) ? serialize( $v ) : (string) $v;
        $st = $pdo->prepare( 'INSERT INTO wp_usermeta (user_id, meta_key, meta_value) VALUES (?, ?, ?)' );
        $st->execute( [ (int) $u, (string) $k, $sv ] );
        return true;
}
function sim_user_obj( $row ) {
        if ( ! $row ) { return false; }
        $u = new WP_User();
        $u->ID           = (int) $row['ID'];
        $u->user_login   = (string) $row['user_login'];
        $u->user_pass    = (string) $row['user_pass'];
        $u->user_email   = (string) $row['user_email'];
        $u->display_name = (string) $row['display_name'];
        $st = $GLOBALS['sim_users_pdo']->prepare( "SELECT meta_value FROM wp_usermeta WHERE user_id = ? AND meta_key = 'tpp_sim_role'" );
        $st->execute( [ $u->ID ] );
        $roles = $st->fetchAll( PDO::FETCH_COLUMN );
        $u->roles = $roles ? array_map( 'strval', $roles ) : [];
        return $u;
}
function get_user_by($f, $v){
        $pdo = $GLOBALS['sim_users_pdo'] ?? null;
        if ( ! $pdo ) { return false; }
        $col = [ 'id' => 'ID', 'login' => 'user_login', 'email' => 'user_email', 'slug' => 'user_login', 'nicename' => 'user_login' ][ strtolower( (string) $f ) ] ?? null;
        if ( ! $col ) { return false; }
        $st = $pdo->prepare( "SELECT * FROM wp_users WHERE {$col} = ? LIMIT 1" );
        $st->execute( [ (string) $v ] );
        return sim_user_obj( $st->fetch( PDO::FETCH_ASSOC ) );
}
function wp_insert_user($d){
        $pdo = $GLOBALS['sim_users_pdo'] ?? null;
        if ( ! $pdo ) { return 1; }
        if ( ! empty( $d['ID'] ) ) {
                $st = $pdo->prepare( 'UPDATE wp_users SET user_login = ?, user_pass = ?, user_email = ?, display_name = ? WHERE ID = ?' );
                $st->execute( [ (string) ($d['user_login'] ?? ''), (string) ($d['user_pass'] ?? ''), (string) ($d['user_email'] ?? ''), (string) ($d['display_name'] ?? ''), (int) $d['ID'] ] );
                return (int) $d['ID'];
        }
        $st = $pdo->prepare( 'INSERT INTO wp_users (user_login, user_pass, user_email, display_name) VALUES (?, ?, ?, ?)' );
        $st->execute( [ (string) ($d['user_login'] ?? ''), (string) ($d['user_pass'] ?? ''), (string) ($d['user_email'] ?? ''), (string) ($d['display_name'] ?? '') ] );
        $id = (int) $pdo->lastInsertId();
        if ( ! empty( $d['role'] ) ) {
                $st = $pdo->prepare( "INSERT INTO wp_usermeta (user_id, meta_key, meta_value) VALUES (?, 'tpp_sim_role', ?)" );
                $st->execute( [ $id, (string) $d['role'] ] );
        }
        return $id;
}
function wp_update_user($d){
        $pdo = $GLOBALS['sim_users_pdo'] ?? null;
        if ( ! $pdo ) { return 1; }
        return wp_insert_user( $d );
}
function wp_set_password($p, $u){
        $pdo = $GLOBALS['sim_users_pdo'] ?? null;
        if ( ! $pdo ) { return; }
        $st = $pdo->prepare( 'UPDATE wp_users SET user_pass = ? WHERE ID = ?' );
        $st->execute( [ (string) $p, (int) $u ] );
}
function wp_generate_password($l = 12, $ss = true, $ee = false){ return substr(str_shuffle('abcdefgh1234567890'), 0, $l); }
if ( ! function_exists( 'wp_rand' ) ) {
    function wp_rand( $min = 0, $max = 0 ) {
        if ( $min === $max ) { return (int) $min; }
        if ( $max === 0 ) { $max = (int) $min; $min = 0; }
        return mt_rand( (int) $min, (int) $max );
    }
}
function wp_create_user($u, $p, $e = ''){ return wp_insert_user( [ 'user_login' => $u, 'user_pass' => $p, 'user_email' => $e ] ); }
function username_exists($u){
        $pdo = $GLOBALS['sim_users_pdo'] ?? null;
        if ( ! $pdo ) { return false; }
        $st = $pdo->prepare( 'SELECT ID FROM wp_users WHERE user_login = ? LIMIT 1' );
        $st->execute( [ (string) $u ] );
        $r = $st->fetch( PDO::FETCH_ASSOC );
        return $r ? (int) $r['ID'] : false;
}
function email_exists($e){ return false; }
/* نسخه 1.7.6 — تست‌های بازگردانی بکاپ به این دو نیاز دارند */
if ( ! function_exists( 'is_email' ) ) {
    function is_email($e){ return (bool) filter_var((string)$e, FILTER_VALIDATE_EMAIL) ? (string)$e : false; }
}
if ( ! function_exists( 'sanitize_user' ) ) {
    function sanitize_user($u, $strict = false){ $u = preg_replace('/[^a-zA-Z0-9._\-@]/', '', (string)$u); return $strict ? preg_replace('/[^a-z0-9._\-@]/', '', strtolower($u)) : $u; }
}
function add_role($r, $n, $c = []){ return null; }
function remove_role($r){}
function get_role($r){ return null; }
function wp_roles(){ return null; }
function current_time($t, $g = 0){ return $t === 'timestamp' ? time() : date('Y-m-d H:i:s'); }
function get_date_from_gmt($s, $f = 'Y-m-d H:i:s'){ return $s; }
function get_gmt_from_date($s, $f = 'Y-m-d H:i:s'){ return $s; }
function date_i18n($f, $t = null){ return date($f, $t ?? time()); }
function wp_next_scheduled($h, $a = []){ return false; }
function wp_schedule_event($t, $r, $h, $a = []){ return true; }
function wp_clear_scheduled_hook($h, $a = []){}
function wp_unschedule_event($t, $h, $a = []){ return true; }
function wp_schedule_single_event($t, $h, $a = []){ return true; }
function add_editor_style(){}
function add_theme_support(){}
function wp_send_json_success($d = null){ echo json_encode(['success'=>true,'data'=>$d]); exit; }
function wp_send_json_error($d = null){ echo json_encode(['success'=>false,'data'=>$d]); exit; }
function wp_check_filetype($f, $m = null){ return ['ext'=>'xlsx','type'=>'application/vnd.openxml']; }
function wp_handle_upload($f, $o = []){ return ['file'=>'/tmp/up.xlsx','url'=>'http://x/up.xlsx','type'=>'application/zip']; }
function is_wp_error($t){ return $t instanceof WP_Error; }
function wp_die($m = '', $t = '', $a = []){ fwrite(STDERR, "wp_die: ".(is_string($m)?$m:json_encode($m))."\n"); exit(1); }
function sanitize_text_field($s){ return trim(strip_tags((string)$s)); }
function sanitize_textarea_field($s){ return trim(strip_tags((string)$s)); }
function sanitize_email($s){ return filter_var($s, FILTER_SANITIZE_EMAIL); }
function sanitize_title($t){
    if ( defined( 'SIM_REAL_MENU' ) && function_exists( 'sim_default_sanitize_title_filter' ) ) {
        // رفتار واقعی وردپرس: فیلتر پیش‌فرض sanitize_title_with_dashes
        $raw = $t;
        $t = remove_accents( $t );
        $t = sim_default_sanitize_title_filter( $t, $raw, 'save' );
        if ( '' === $t || false !== strpos( $t, '%25' ) ) {
            $t = utf8_uri_encode( $raw, 200 );
            $t = strtolower( $t );
            $t = preg_replace( '/[^a-z0-9\-_]/', '', $t );
            $t = preg_replace( '/[\r\n\t ]+/', '-', $t );
            $t = trim( $t, '-' );
        }
        return $t;
    }
    return preg_replace('/[^a-z0-9]+/','-', strtolower($t));
}
function sanitize_key($k){ return strtolower(preg_replace('/[^a-z0-9_\-]/','', $k)); }
function sanitize_file_name($n){ return $n; }
function wp_unslash($v){ return $v; }
function absint($n){ return abs((int)$n); }
function wp_is_numeric_array($a){ return is_array($a) && (array_keys($a) === range(0, count($a)-1)); }
function stripslashes_deep($v){ return $v; }
function shortcode_empty(){}
function load_plugin_textdomain($d, $dd = false, $p = false){ return true; }
function is_admin(){ return true; }
function admin_post_url(){}
function get_search_query(){ return ''; }
function wp_reset_postdata(){}
function get_post($p = null){ return null; }
function get_post_meta($p, $k = '', $s = false){ return []; }
function update_post_meta($p, $k, $v){ return true; }
function add_meta_box(...$a){}
function add_settings_section(...$a){}
function add_settings_field(...$a){}
function register_setting(...$a){}
function settings_fields($g){}
function do_settings_sections($p){}
function submit_button(){}
/* نسخه 1.6.3: هم‌سان با وردپرس واقعی — مثل هسته WP هم echo می‌کند هم برمی‌گرداند
 * (رفتار قبلی فقط return بود و در رندر شبیه‌ساز «selected» چاپ نمی‌شد). */
function checked($a, $b = true, $e = true){ $r = ((string)$a === (string)$b) ? "checked='checked'" : ''; if ($e) { echo $r; } return $r; }
function selected($a, $b = true, $e = true){ $r = ((string)$a === (string)$b) ? "selected='selected'" : ''; if ($e) { echo $r; } return $r; }
function disabled($a, $b = true, $e = true){ return $a == $b ? ($e?' disabled="disabled"':' disabled') : ''; }
function wp_get_upload_dir($t = null){ return wp_upload_dir($t); }
function wp_list_pluck($l, $f, $i = null){ return []; }
function wp_set_current_user($i){}
function wp_insert_post($a, $e = false){ return 1; }
function wp_delete_post($i, $f = false){ return null; }
function get_posts($a = []){ return []; }
function get_the_title($p = 0){ return ''; }
function has_post_thumbnail($p = null){ return false; }
function get_the_post_thumbnail_url($p = null, $s = 'thumbnail'){ return false; }
function wp_get_attachment_image_url($p, $s = 'thumbnail'){ return false; }
function wp_get_attachment_url($p){ return false; }
function get_attached_file($p){ return false; }
function media_handle_upload($f, $p, $m = [], $o = []){ return 0; }
function admin_action(){}
function add_screen_option(){}
function set_screen_option(){}
function get_screen_option(){ return 20; }
function _e($s, $d = null){ echo $s; }
function _x($s, $c, $d = null){ return $s; }
function _n($s, $p, $n, $d = null){ return $n == 1 ? $s : $p; }
function number_format_i18n($n, $d = 0){ return number_format((float)$n, $d); }
function date_i18n_jalali(){}
function wp_timezone(){ return new DateTimeZone('UTC'); }
function get_locale(){ return 'fa_IR'; }
function load_textdomain(){}
function is_rtl(){ return true; }
function wp_style_is(){}
function wp_script_is(){}
function plugins_url($p = '', $f = ''){ return 'http://example.test/wp-content/plugins/tpp_salary/'.$p; }
function includes_url($p = ''){ return 'http://example.test/wp-includes/'.$p; }
function content_url($p = ''){ return 'http://example.test/wp-content/'.$p; }
function wp_create_nonce($a = -1){ return 'nonce123'; }
function rest_url($p = ''){ return 'http://example.test/wp-json/'.$p; }
function get_rest_url(){ return 'http://example.test/wp-json/'; }
function wp_is_mobile(){ return false; }
function wp_redirect($l, $s = 302){ return false; }
function wp_safe_redirect($l, $s = 302){ return false; }
/* نسخه 1.6.2: هندلرهای حذف گروهی برای بازگشت به صفحه مبدا از wp_get_referer استفاده می‌کنند. */
if ( ! function_exists( 'wp_get_referer' ) ) {
function wp_get_referer(){ return ''; }
}
function status_header($c){}
function nocache_headers(){}
function is_wp_version_compatible(){ return true; }
function current_filter(){ return ''; }
function remove_action(...$a){}
function remove_filter(...$a){}
function remove_all_actions(...$a){}
function remove_all_filters(...$a){}
function doing_action($h){ return false; }
function doing_filter($h){ return false; }
function did_action($h){ return 1; }
/**
 * نسخه 1.6.1: پیاده‌سازی واقعی add_query_arg (قبلاً ثابت رشته‌ای بود و
 * صفحه‌بندی/لینک‌های کوئری در تست‌ها قابل‌بررسی نبودند).
 * پشتیبانی از دو امضا: add_query_arg(args, url) و add_query_arg(key, val, url)
 */
function sim_build_url( $url, array $qs_new ) {
        $qs = [];
        $parts = parse_url( (string) $url );
        if ( ! empty( $parts['query'] ) ) {
                parse_str( $parts['query'], $qs );
        }
        foreach ( $qs_new as $k => $v ) {
                $qs[ $k ] = $v;
        }
        $base = '';
        if ( isset( $parts['scheme'] ) ) {
                $base = $parts['scheme'] . '://' . ( $parts['host'] ?? '' ) . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) . ( $parts['path'] ?? '' );
        } else {
                $base = $parts['path'] ?? (string) $url;
        }
        $q = http_build_query( $qs );
        return $q ? ( $base . '?' . $q ) : $base;
}
function add_query_arg( ...$a ) {
        if ( is_array( $a[0] ?? null ) ) {
                $args = $a[0];
                $url  = $a[1] ?? ( $_SERVER['REQUEST_URI'] ?? '' );
        } else {
                $args = [ $a[0] => $a[1] ];
                $url  = $a[2] ?? ( $_SERVER['REQUEST_URI'] ?? '' );
        }
        return sim_build_url( $url, $args );
}
function remove_query_arg( $k, $q = false ) {
        $url  = $q ? $q : ( $_SERVER['REQUEST_URI'] ?? '' );
        $keys = (array) $k;
        $parts = parse_url( (string) $url );
        $qs = [];
        if ( ! empty( $parts['query'] ) ) {
                parse_str( $parts['query'], $qs );
        }
        foreach ( $keys as $k2 ) {
                unset( $qs[ $k2 ] );
        }
        $base = isset( $parts['scheme'] ) ? $parts['scheme'] . '://' . ( $parts['host'] ?? '' ) . ( $parts['path'] ?? '' ) : ( $parts['path'] ?? (string) $url );
        $q2 = http_build_query( $qs );
        return $q2 ? ( $base . '?' . $q2 ) : $base;
}
function get_bloginfo($k = ''){ return 'Test'; }
function bloginfo($k = ''){}
function wp_basename($p){ return basename($p); }
function wp_normalize_path($p){ return str_replace('\\','/', $p); }
function get_temp_dir(){ return '/tmp/'; }
function download_url($u, $t = 300){ return '/tmp/f'; }
function unzip_file($f, $d){ return true; }
function wp_delete_file($f){ @unlink($f); }
function wp_handle_sideload($f, $o = []){ return ['file'=>'/tmp/f']; }
function list_files($f, $l = 100, $e = []){ return []; }
function recursive_unset(){}
function wpautop($s){ return $s; }
function do_shortcode($s){ return $s; }
function get_userdata($i){ return get_user_by( 'id', $i ); }
function get_edit_user_link($id){ return admin_url( 'user-edit.php?user_id=' . (int) $id ); }
function urlencode_deep($a){ return array_map('rawurlencode', (array)$a); }
function get_the_author_meta($k, $u = 0){ return ''; }
function count_users(){ return ['total_users'=>1]; }
function wp_new_user_notification(...$a){ return true; }

/* ---------- REST API stubs (نسخه 1.7.0 — تست TppSalary_Api) ---------- */
$GLOBALS['tpp_rest_routes'] = array();
if ( ! function_exists( 'register_rest_route' ) ) {
function register_rest_route($ns, $route, $args = array()){
    $GLOBALS['tpp_rest_routes'][] = array('ns' => $ns, 'route' => $route, 'args' => $args);
    return true;
}
}
if ( ! function_exists( 'rest_ensure_response' ) ) {
function rest_ensure_response($data){ return $data; }
}
if ( ! function_exists( 'rest_authorization_required_code' ) ) {
function rest_authorization_required_code(){ return 401; }
}
function get_users($a = []){
        $pdo = $GLOBALS['sim_users_pdo'] ?? null;
        if ( ! $pdo ) { return []; }
        $sql = 'SELECT * FROM wp_users WHERE 1=1';
        $params = [];
        if ( ! empty( $a['role'] ) ) {
                $sql .= " AND ID IN (SELECT user_id FROM wp_usermeta WHERE meta_key = 'tpp_sim_role' AND meta_value = ?)";
                $params[] = (string) $a['role'];
        }
        if ( ! empty( $a['meta_key'] ) ) {
                $sql .= ' AND ID IN (SELECT user_id FROM wp_usermeta WHERE meta_key = ?' . ( isset( $a['meta_value'] ) ? ' AND meta_value = ?' : '' ) . ')';
                $params[] = (string) $a['meta_key'];
                if ( isset( $a['meta_value'] ) ) { $params[] = (string) $a['meta_value']; }
        }
        $sql .= ' ORDER BY display_name ASC';
        $st = $pdo->prepare( $sql );
        $st->execute( $params );
        $rows = $st->fetchAll( PDO::FETCH_ASSOC );
        if ( isset( $a['fields'] ) && 'ID' === $a['fields'] ) {
                return array_map( 'intval', array_column( $rows, 'ID' ) );
        }
        return array_map( 'sim_user_obj', $rows );
}
function wp_mail(...$a){ return true; }
function wp_json_encode($d, $f = 0){ return json_encode($d, $f); }
function _doing_it_wrong($f, $m, $v){}
function _deprecated_function($f, $v, $r = ''){}
function is_multisite(){ return false; }
function get_current_blog_id(){ return 1; }
function ms_is_switched(){ return false; }
function get_site_option($n, $d = false){ return get_option($n, $d); }
function update_site_option($n, $v){ return update_option($n, $v); }
function get_network_option(){ return false; }
function is_main_site(){ return true; }
function is_main_network(){ return true; }
function get_site_transient($t){ return false; }
function set_site_transient($t, $v){ return true; }
/* نسخه 1.7.6 — transient های شبیه‌ساز واقعاً ذخیره می‌شوند (تست گزارش بازگردانی) */
function get_transient($t){ $s = $GLOBALS['sim_transients'] ?? []; return $s[$t] ?? false; }
function set_transient($t, $v, $e = 0){ $GLOBALS['sim_transients'][$t] = $v; return true; }
function delete_transient($t){ unset( $GLOBALS['sim_transients'][$t] ); return true; }
function wp_cache_get($k, $g = ''){ return false; }
function wp_cache_set($k, $v, $g = '', $e = 0){ return true; }
function wp_cache_delete($k, $g = ''){ return true; }
function wp_cache_flush(){}
function wp_reset_vars(){}

class WP_Error {
    public $errors = []; public $error_data = [];
    public function __construct($c = '', $m = '', $d = null){ if($c){ $this->errors[$c][] = $m; if($d) $this->error_data[$c] = $d; } }
    public function get_error_message($c = ''){ $m = reset($this->errors); return $m === false ? '' : (is_array($m) ? reset($m) : $m); }
    public function get_error_code(){ $k = array_key_first($this->errors); return $k ?? ''; }
    public function get_error_messages(){ return $this->errors; }
    public function has_errors(){ return !empty($this->errors); }
}
class WP_User {
    public $ID = 1; public $roles = ['administrator']; public $caps = []; public $user_login = 'admin';
    public $user_pass = ''; public $user_email = ''; public $display_name = '';
    public function __get($k){ return null; }
    public function has_cap($c, ...$a){ return true; }
    public function get($k){ return null; }
    public function add_role($r){
            if ( ! in_array( $r, $this->roles, true ) ) { $this->roles[] = $r; }
            $pdo = $GLOBALS['sim_users_pdo'] ?? null;
            if ( $pdo ) {
                    $st = $pdo->prepare( "INSERT INTO wp_usermeta (user_id, meta_key, meta_value) VALUES (?, 'tpp_sim_role', ?)" );
                    $st->execute( [ (int) $this->ID, (string) $r ] );
            }
    }
    public function remove_role($r){
            $this->roles = array_values( array_diff( $this->roles, [ $r ] ) );
            $pdo = $GLOBALS['sim_users_pdo'] ?? null;
            if ( $pdo ) {
                    $st = $pdo->prepare( "DELETE FROM wp_usermeta WHERE user_id = ? AND meta_key = 'tpp_sim_role' AND meta_value = ?" );
                    $st->execute( [ (int) $this->ID, (string) $r ] );
            }
    }
}

// dbDelta — شبیه‌سازی واقعی: جدول غایب را می‌سازد و ستون‌های غایب را ALTER TABLE ADD COLUMN می‌کند
// (تا مسیر ارتقا — مثل افزودن ستون in_record در 1.4.1 — در شبیه‌ساز هم مثل وردپرس واقعی جواب دهد)
function get_charset_collate(){ return 'DEFAULT CHARACTER SET utf8mb4'; }
if ( ! function_exists( 'dbDelta' ) ) {
    function sim_sqlite_coltype( $def ) {
        // تبدیل تعریف ستون MySQL به معادل SQLite.
        $d = trim( $def );
        $d = preg_replace( '/\s+unsigned/i', '', $d );
        if ( preg_match( '/^(bigint|int|smallint|tinyint)\s*\(\d+\)?/i', $d ) ) {
            $d = preg_replace( '/^(bigint|int|smallint|tinyint)\s*\(\d+\)?/i', 'INTEGER', $d, 1 );
        } elseif ( preg_match( '/^(varchar)\s*\(\d+\)/i', $d ) ) {
            $d = preg_replace( '/^varchar\s*\(\d+\)/i', 'TEXT', $d, 1 );
        } elseif ( preg_match( '/^(longtext|mediumtext|text)/i', $d ) ) {
            $d = preg_replace( '/^(longtext|mediumtext|text)/i', 'TEXT', $d, 1 );
        } elseif ( preg_match( '/^datetime/i', $d ) ) {
            $d = preg_replace( '/^datetime/i', 'TEXT', $d, 1 );
        }
        $d = str_ireplace( 'AUTO_INCREMENT', '', $d );
        return $d;
    }
    function dbDelta( $ddl ) {
        global $wpdb;
        $out = array();
        /* فقط در حالت SQLite واقعی اجرا می‌شود؛ در wpdb سادهٔ stub، no-op (رفتار قدیم). */
        if ( ! isset( $wpdb->pdo ) || ! ( $wpdb->pdo instanceof PDO ) ) { return $out; }
        if ( ! preg_match( '/CREATE TABLE\s+([^\s(]+)\s*\((.*)\)[^)]*$/is', $ddl, $m ) ) { return $out; }
        $table = trim( $m[1], '`' );
        $body  = $m[2];
        $lines = array();
        foreach ( explode( "\n", $body ) as $ln ) {
            $ln = trim( rtrim( $ln, ',' ) );
            if ( '' === $ln ) { continue; }
            $lines[] = $ln;
        }
        $cols = array();
        $pk_col = null;
        foreach ( $lines as $ln ) {
            if ( preg_match( '/^(PRIMARY\s+KEY|UNIQUE\s+KEY|KEY|INDEX|CONSTRAINT)\b/i', $ln ) ) { continue; }
            if ( preg_match( '/^([A-Za-z0-9_]+)\s+(.+)$/', $ln, $cm ) ) {
                $cname = trim( $cm[1], '`' );
                $cols[ $cname ] = sim_sqlite_coltype( $cm[2] );
                if ( false !== stripos( $cm[2], 'AUTO_INCREMENT' ) ) { $pk_col = $cname; }
            }
        }
        $exists = $wpdb->get_var( 'SHOW TABLES LIKE ' . $wpdb->prepare( '%s', $table ) );
        if ( ! $exists ) {
            $defs = array();
            foreach ( $cols as $cname => $cdef ) {
                if ( $pk_col && $cname === $pk_col ) {
                    $defs[] = '"' . $cname . '" INTEGER PRIMARY KEY AUTOINCREMENT';
                } else {
                    $defs[] = '"' . $cname . '" ' . $cdef;
                }
            }
            $wpdb->pdo->exec( 'CREATE TABLE IF NOT EXISTS "' . $table . '" (' . implode( ', ', $defs ) . ')' );
            $out[] = 'Created table ' . $table;
            return $out;
        }
        // جدول موجود — ستون‌های غایب اضافه می‌شوند (رفتار واقعی dbDelta در ارتقا).
        $existing = $wpdb->get_col( 'SHOW COLUMNS FROM ' . $table, 0 );
        foreach ( $cols as $cname => $cdef ) {
            if ( in_array( $cname, (array) $existing, true ) ) { continue; }
            $wpdb->pdo->exec( 'ALTER TABLE "' . $table . '" ADD COLUMN "' . $cname . '" ' . $cdef );
            $out[] = 'Added column ' . $table . '.' . $cname;
        }
        return $out;
    }
}
function wp_parse_args($a, $d = []){ return array_merge($d, (array)$a); }

// ZipArchive / spl موجود هستند

// ---------- LOAD PLUGIN ----------
$plugin_file = '/home/z/my-project/build/tpp_salary/tpp-salary.php';
try {
    require $plugin_file;
    echo "LOAD OK\n";
} catch (Throwable $e) {
    echo "LOAD FATAL: ".get_class($e).": ".$e->getMessage()." @ ".$e->getFile().":".$e->getLine()."\n";
    exit(1);
}

// ---------- PRE-ACTIVATE CALLBACK (برای تست‌های مهاجرت) ----------
if ( isset( $GLOBALS['sim_pre_activate_cb'] ) && is_callable( $GLOBALS['sim_pre_activate_cb'] ) ) {
    call_user_func( $GLOBALS['sim_pre_activate_cb'] );
}

// ---------- RUN ACTIVATION HOOK ----------
try {
    if (isset($GLOBALS['activation_hook']) && is_callable($GLOBALS['activation_hook'])) {
        call_user_func($GLOBALS['activation_hook']);
        echo "ACTIVATE OK\n";
    } else {
        echo "NO ACTIVATION HOOK\n";
    }
} catch (Throwable $e) {
    echo "ACTIVATE FATAL: ".get_class($e).": ".$e->getMessage()." @ ".$e->getFile().":".$e->getLine()."\n";
    echo "TRACE:\n".$e->getTraceAsString()."\n";
    exit(1);
}

// ---------- RUN plugins_loaded INIT ----------
try {
    tpp_salary_init();
    echo "INIT OK\n";
} catch (Throwable $e) {
    echo "INIT FATAL: ".get_class($e).": ".$e->getMessage()." @ ".$e->getFile().":".$e->getLine()."\n";
    echo "TRACE:\n".$e->getTraceAsString()."\n";
    exit(1);
}

echo "ALL GREEN\n";
