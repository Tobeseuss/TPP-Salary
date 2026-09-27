<?php
/**
 * حالت آفلاین — کار بدون اینترنت با ذخیره محلی و همگام‌سازی پس از اتصال
 *
 * - داده‌ها (فیلدها، مراکز، بانک‌ها، کارمندان، رکوردها) در مرورگر (IndexedDB) کش می‌شوند
 * - ثبت/ویرایش در نبود اینترنت در صف محلی ذخیره و پس از اتصال به سرور اعمال می‌شود
 * - امکان دانلود «فایل snapshot» از کل داده‌ها روی سیستم کاربر و بازیابی آن
 * - Service Worker برای بازشدن صفحات پلاگین در حالت آفلاین (بارگذاری مجدد صفحه)
 *
 * @package TppSalary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class TppSalary_Offline
 */
if ( ! class_exists( 'TppSalary_Offline' ) ) {
        // TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TppSalary_Offline {

        /**
         * مقداردهی اولیه
         *
         * @return void
         */
        public static function init() {
                add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
                add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
                add_action( 'wp_ajax_tpp_salary_offline_pull', array( __CLASS__, 'ajax_pull' ) );
                add_action( 'wp_ajax_tpp_salary_offline_sync', array( __CLASS__, 'ajax_sync' ) );
                add_action( 'wp_ajax_tpp_salary_offline_sw', array( __CLASS__, 'sw_script' ) );
                add_action( 'admin_post_tpp_salary_offline_snapshot', array( __CLASS__, 'snapshot_download' ) );
                add_action( 'admin_post_tpp_salary_offline_restore', array( __CLASS__, 'snapshot_restore' ) );
        }

        /**
         * منو
         *
         * @return void
         */
        public static function menu() {
                add_submenu_page(
                        'tpp-salary',
                        'حالت آفلاین و همگام‌سازی',
                        'حالت آفلاین',
                        'tpp_salary_manage',
                        'tpp-salary-offline',
                        array( __CLASS__, 'render' )
                );
        }

        /**
         * بارگذاری اسکریپت آفلاین در همه صفحات پلاگین
         *
         * @param string $hook هوک صفحه.
         * @return void
         */
        public static function assets( $hook ) {
                if ( false === strpos( $hook, 'tpp-' ) ) {
                        return;
                }
                wp_enqueue_script(
                        'tpp-salary-offline',
                        TPP_SALARY_URL . 'admin/js/tpp-salary-offline.js',
                        array( 'jquery' ),
                        TPP_SALARY_VERSION,
                        true
                );
                wp_localize_script(
                        'tpp-salary-offline',
                        'TPPSALARY_OFFLINE',
                        array(
                                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                                'adminUrl'  => admin_url( 'admin.php' ),
                                'swUrl'     => admin_url( 'admin-ajax.php?action=tpp_salary_offline_sw' ),
                                'scope'     => 'wp-admin/',
                                'nonce'     => wp_create_nonce( 'tpp_salary_offline' ),
                                'page'      => isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '', // phpcs:ignore
                                'snapshotUrl' => admin_url( 'admin-post.php?action=tpp_salary_offline_snapshot' ),
                                'restoreUrl'  => admin_url( 'admin-post.php?action=tpp_salary_offline_restore' ),
                        )
                );
        }

        /**
         * بسته داده کامل برای کش آفلاین
         *
         * @return array
         */
        public static function bundle() {
                global $wpdb;
                $jtoday = TppSalary_Jalali::today();
                $jyear  = (int) $jtoday[0];
                $jmonth = (int) $jtoday[1];

                $employees = array();
                foreach ( tpp_salary_get_employees() as $u ) {
                        $profile = tpp_salary_get_profile( $u->ID );
                        $employees[] = array(
                                'id'       => (int) $u->ID,
                                'name'     => $u->display_name,
                                'login'    => $u->user_login,
                                'national' => get_user_meta( $u->ID, 'tpp_salary_national_id', true ),
                                'centers'  => array_map( 'intval', $profile['centers'] ),
                                'profile'  => $profile,
                        );
                }

                // رکوردهای ۱۳ ماه اخیر (دوره جاری + ۱۲ ماه قبل).
                $records = $wpdb->get_results( // phpcs:ignore
                        $wpdb->prepare(
                                "SELECT * FROM {$wpdb->prefix}tpp_salary_records WHERE (jyear = %d AND jmonth <= %d) OR (jyear = %d) OR (jyear = %d) ORDER BY id DESC LIMIT 5000", // phpcs:ignore
                                $jyear,
                                $jmonth,
                                $jyear - 1,
                                $jyear + 1
                        ),
                        ARRAY_A
                );

                $fields = array();
                foreach ( tpp_salary_get_fields() as $f ) {
                        /* نسخه 1.4.1: فقط فیلدهای فرم ثبت حقوق + حذف مفهوم «ذاتاً منفی». */
                        if ( ! tpp_salary_field_in_record( $f ) ) { // نسخه 1.5.0: سپر هاردکد فیلدهای فقط‌پروفایلی.
                                continue;
                        }
                        $fields[] = array(
                                'key'        => $f->field_key,
                                'label'      => $f->label,
                                'type'       => $f->field_type,
                                'formula'    => (string) $f->formula,
                                'calculated' => (int) $f->is_calculated,
                        );
                }

                $centers = array();
                foreach ( tpp_salary_get_centers() as $c ) {
                        $centers[] = array( 'id' => (int) $c->id, 'name' => $c->name );
                }

                $banks = array();
                foreach ( tpp_salary_get_banks() as $b ) {
                        $banks[] = array( 'id' => (int) $b->id, 'name' => $b->name );
                }

                return array(
                        'generated_at' => current_time( 'mysql' ),
                        'period'       => array( 'jyear' => $jyear, 'jmonth' => $jmonth ),
                        'fields'       => $fields,
                        'centers'      => $centers,
                        'banks'        => $banks,
                        'employees'    => $employees,
                        'records'      => is_array( $records ) ? array_values( $records ) : array(),
                );
        }

        /**
         * AJAX: دریافت بسته داده
         *
         * @return void
         */
        public static function ajax_pull() {
                check_ajax_referer( 'tpp_salary_offline', 'nonce' );
                if ( ! tpp_salary_can_manage() ) {
                        wp_send_json_error( 'دسترسی غیرمجاز' );
                }
                wp_send_json_success( self::bundle() );
        }

        /**
         * AJAX: اعمال صف آفلاین — عملیات upsert/delete رکوردها
         *
         * @return void
         */
        public static function ajax_sync() {
                check_ajax_referer( 'tpp_salary_offline', 'nonce' );
                if ( ! tpp_salary_can_manage() ) {
                        wp_send_json_error( 'دسترسی غیرمجاز' );
                }
                $raw = isset( $_POST['ops'] ) ? wp_unslash( $_POST['ops'] ) : '[]'; // phpcs:ignore
                $ops = json_decode( $raw, true );
                if ( ! is_array( $ops ) ) {
                        wp_send_json_error( 'ساختار عملیات نامعتبر است' );
                }
                if ( count( $ops ) > 500 ) {
                        $ops = array_slice( $ops, 0, 500 );
                }

                $results = array();
                foreach ( $ops as $op ) {
                        $ref    = isset( $op['ref'] ) ? sanitize_text_field( $op['ref'] ) : '';
                        $action = isset( $op['action'] ) ? sanitize_key( $op['action'] ) : '';
                        if ( '' === $ref ) {
                                continue;
                        }
                        if ( 'delete' === $action ) {
                                $results[] = self::apply_delete( $ref, $op );
                                continue;
                        }
                        $results[] = self::apply_upsert( $ref, $op );
                }
                wp_send_json_success( array( 'results' => $results, 'bundle' => self::bundle() ) );
        }

        /**
         * اعمال یک عملیات ذخیره رکورد آفلاین
         *
         * @param string $ref شناسه مرجع محلی.
         * @param array  $op  عملیات.
         * @return array
         */
        private static function apply_upsert( $ref, $op ) {
                $p = isset( $op['payload'] ) && is_array( $op['payload'] ) ? $op['payload'] : array();
                $user_id   = isset( $p['user_id'] ) ? (int) $p['user_id'] : 0;
                $center_id = isset( $p['center_id'] ) ? (int) $p['center_id'] : 0;
                $jyear     = isset( $p['jyear'] ) ? (int) $p['jyear'] : 0;
                $jmonth    = isset( $p['jmonth'] ) ? (int) $p['jmonth'] : 0;
                $raw       = isset( $p['values'] ) && is_array( $p['values'] ) ? $p['values'] : array();
                $insurable_formula = ! empty( $p['insurable_formula'] );

                $existing = tpp_salary_get_record_period( $user_id, $center_id, $jyear, $jmonth );
                $client_updated = isset( $op['client_updated_at'] ) ? (string) $op['client_updated_at'] : '';
                if ( $existing && $client_updated && ! empty( $existing->updated_at ) && (string) $existing->updated_at > $client_updated ) {
                        return array(
                                'ref'      => $ref,
                                'status'   => 'conflict',
                                'message'  => 'نسخه سرور جدیدتر است',
                                'server'   => array(
                                        'id'         => (int) $existing->id,
                                        'updated_at' => $existing->updated_at,
                                        'net'        => (float) $existing->net,
                                ),
                        );
                }
                $res = TppSalary_Salary_Pages::upsert_record( $user_id, $center_id, $jyear, $jmonth, $raw, $insurable_formula );
                if ( is_wp_error( $res ) ) {
                        return array( 'ref' => $ref, 'status' => 'error', 'message' => $res->get_error_message() );
                }
                return array( 'ref' => $ref, 'status' => 'applied', 'action' => $res['status'], 'record_id' => $res['record_id'] );
        }

        /**
         * اعمال یک عملیات حذف آفلاین
         *
         * @param string $ref شناسه مرجع محلی.
         * @param array  $op  عملیات.
         * @return array
         */
        private static function apply_delete( $ref, $op ) {
                global $wpdb;
                $id = isset( $op['record_id'] ) ? (int) $op['record_id'] : 0;
                if ( ! $id ) {
                        return array( 'ref' => $ref, 'status' => 'error', 'message' => 'شناسه رکورد نامعتبر' );
                }
                $row = $wpdb->get_row( $wpdb->prepare( "SELECT id, updated_at FROM {$wpdb->prefix}tpp_salary_records WHERE id = %d", $id ) ); // phpcs:ignore
                if ( ! $row ) {
                        return array( 'ref' => $ref, 'status' => 'applied', 'action' => 'delete' );
                }
                $client_deleted = isset( $op['client_updated_at'] ) ? (string) $op['client_updated_at'] : '';
                if ( $client_deleted && ! empty( $row->updated_at ) && (string) $row->updated_at > $client_deleted ) {
                        return array( 'ref' => $ref, 'status' => 'conflict', 'message' => 'رکورد روی سرور پس از حذف محلی تغییر کرده است' );
                }
                $wpdb->delete( $wpdb->prefix . 'tpp_salary_records', array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore
                return array( 'ref' => $ref, 'status' => 'applied', 'action' => 'delete' );
        }

        /**
         * صفحه مدیریت حالت آفلاین
         *
         * @return void
         */
        public static function render() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                $restored = isset( $_GET['restored'] ) ? (int) $_GET['restored'] : -1; // phpcs:ignore
                ?>
                <div class="wrap tpp-wrap" dir="rtl">
                        <h1>حالت آفلاین و همگام‌سازی</h1>
                        <div class="tpp-scope tpp-panel">
                                <p class="description">در این حالت، داده‌های پلاگین در مرورگر شما (IndexedDB) کش می‌شود؛ با قطع اینترنت همچنان می‌توانید رکوردها را ببینید، جستجو کنید، ویرایش و ثبت جدید انجام دهید. تغییرات در صف محلی ذخیره و به محض وصل شدن اینترنت به‌صورت خودکار با سرور همگام می‌شود. در صورت تداخل، نسخه جدیدتر ملاک است و موارد تداخل گزارش می‌شود.</p>
                                <p class="description"><strong>فایل پشتیبان محلی:</strong> با «دانلود فایل snapshot» کل داده‌ها در یک فایل JSON روی سیستم شما ذخیره می‌شود و هر زمان با «بازیابی از فایل» قابل اعمال به سرور است — حتی پس از تغییر سیستم.</p>
                                <?php if ( $restored >= 0 ) : ?>
                                        <div class="notice notice-success"><p>بازیابی فایل انجام شد — اعمال‌شده: <?php echo (int) $restored; ?> رکورد.</p></div>
                                <?php elseif ( 0 === $restored ) : ?>
                                        <div class="notice notice-error"><p>فایل snapshot نامعتبر بود.</p></div>
                                <?php endif; ?>

                                <div class="tpp-salary-offline-bar" id="tpp-salary-offline-bar">
                                        <span class="tpp-salary-offline-status"><span class="tpp-salary-offline-dot" id="tpp-salary-offline-dot"></span><span id="tpp-salary-offline-label">در حال بررسی اتصال…</span></span>
                                        <span class="tpp-pending-badge" id="tpp-pending" style="display:none"></span>
                                        <button type="button" class="button button-primary" id="tpp-sync-now">همگام‌سازی الآن</button>
                                        <span class="tpp-sync-msg" id="tpp-sync-msg"></span>
                                </div>

                                <hr>
                                <h2>فایل snapshot محلی</h2>
                                <p>
                                        <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tpp_salary_offline_snapshot' ), 'tpp_salary_offline_snapshot' ) ); ?>">دانلود فایل snapshot (JSON)</a>
                                </p>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="margin-top:10px">
                                        <?php wp_nonce_field( 'tpp_salary_offline_restore' ); ?>
                                        <input type="hidden" name="action" value="tpp_salary_offline_restore">
                                        <p><input type="file" name="snapshot_file" accept=".json" required></p>
                                        <p><label><input type="checkbox" name="dry_run" value="1"> اجرای آزمایشی (فقط گزارش، بدون تغییر)</label></p>
                                        <?php submit_button( 'بازیابی از فایل', 'secondary', 'submit', false ); ?>
                                </form>
                                <hr>
                                <h2>سرویس آفلاین صفحات (Service Worker)</h2>
                                <p class="description">برای آنکه صفحات پلاگین حتی پس از بارگذاری مجدد در حالت قطع اینترنت باز شوند، Service Worker ثبت می‌شود؛ وضعیت: <code id="tpp-sw-status">در حال بررسی…</code></p>
                        </div>
                </div>
                <?php
        }

        /**
         * دانلود فایل snapshot (فایل محلی کاربر)
         *
         * @return void
         */
        public static function snapshot_download() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_salary_offline_snapshot' );
                $bundle          = self::bundle();
                $bundle['type']  = 'tpp_salary_offline_snapshot';
                $bundle['site']  = home_url();
                tpp_salary_clean_output(); // حذف خروجی مزاحم — جلوگیری از فایل خراب.
                nocache_headers();
                header( 'Content-Type: application/json; charset=utf-8' );
                header( 'Content-Disposition: attachment; filename="tpp-salary-offline-snapshot-' . gmdate( 'Y-m-d' ) . '.json"' );
                echo wp_json_encode( $bundle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); // phpcs:ignore
                exit;
        }

        /**
         * بازیابی فایل snapshot به سرور
         *
         * @return void
         */
        public static function snapshot_restore() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_salary_offline_restore' );
                if ( empty( $_FILES['snapshot_file']['tmp_name'] ) ) {
                        wp_die( 'فایلی انتخاب نشده است' );
                }
                $file = sanitize_text_field( wp_unslash( $_FILES['snapshot_file']['tmp_name'] ) );
                $content = (string) file_get_contents( $file ); // phpcs:ignore
                $data = json_decode( $content, true );
                $applied = 0;
                if ( is_array( $data ) && isset( $data['type'] ) && 'tpp_salary_offline_snapshot' === $data['type'] && ! empty( $data['records'] ) && is_array( $data['records'] ) ) {
                        foreach ( $data['records'] as $r ) {
                                if ( empty( $r['payload'] ) ) {
                                        continue;
                                }
                                $payload = is_array( $r['payload'] ) ? $r['payload'] : json_decode( (string) $r['payload'], true );
                                if ( ! is_array( $payload ) ) {
                                        continue;
                                }
                                $values = array();
                                foreach ( $payload as $k => $v ) {
                                        if ( in_array( $k, array( 'manual', 'insurable_mode' ), true ) ) {
                                                continue;
                                        }
                                        $values[ $k ] = $v;
                                }
                                $res = TppSalary_Salary_Pages::upsert_record(
                                        (int) $r['user_id'],
                                        (int) $r['center_id'],
                                        (int) $r['jyear'],
                                        (int) $r['jmonth'],
                                        $values,
                                        isset( $payload['insurable_mode'] ) && 'formula' === $payload['insurable_mode']
                                );
                                if ( ! is_wp_error( $res ) ) {
                                        $applied++;
                                }
                        }
                }
                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-salary-offline', 'restored' => $applied ), admin_url( 'admin.php' ) ) );
                exit;
        }

        /**
         * سرویس‌ورکر — باز کردن صفحات پلاگین در حالت آفلاین
         *
         * @return void
         */
        public static function sw_script() {
                header( 'Content-Type: application/javascript; charset=utf-8' );
                header( 'Cache-Control: public, max-age=60' );
                $assets = array(
                        TPP_SALARY_URL . 'admin/css/tpp-salary-admin.css',
                        TPP_SALARY_URL . 'admin/js/tpp-salary-admin.js',
                        TPP_SALARY_URL . 'admin/js/tpp-salary-offline.js',
                        TPP_SALARY_URL . 'admin/fonts/Vazirmatn-Regular.ttf',
                        TPP_SALARY_URL . 'admin/fonts/Vazirmatn-Bold.ttf',
                );
                ?>
const TPPSALARY_SW_CACHE = 'tpp-salary-offline-v1';
const TPPSALARY_ASSETS = <?php echo wp_json_encode( $assets ); ?>;

self.addEventListener( 'install', function ( e ) {
        e.waitUntil( caches.open( TPPSALARY_SW_CACHE ).then( function ( c ) { return c.addAll( TPPSALARY_ASSETS ); } ).then( function () { return self.skipWaiting(); } ) );
} );

self.addEventListener( 'activate', function ( e ) {
        e.waitUntil(
                caches.keys().then( function ( keys ) {
                        return Promise.all( keys.map( function ( k ) { return ( k !== TPPSALARY_SW_CACHE ) ? caches.delete( k ) : null; } ) );
                } ).then( function () { return self.clients.claim(); } )
        );
} );

self.addEventListener( 'fetch', function ( e ) {
        var req = e.request;
        var url = new URL( req.url );
        if ( req.method !== 'GET' || url.origin !== location.origin ) { return; }

        // admin-ajax: فقط شبکه؛ در قطعی، پاسخ JSON قابل مدیریت.
        if ( url.pathname.indexOf( 'admin-ajax.php' ) !== -1 ) {
                e.respondWith(
                        fetch( req ).catch( function () {
                                return new Response( JSON.stringify( { success: false, data: 'offline', offline: true } ), { status: 503, headers: { 'Content-Type': 'application/json' } } );
                        } )
                );
                return;
        }

        // صفحات مدیریت پلاگین: شبکه-اول با بازگشت به کش.
        if ( url.pathname.indexOf( '/wp-admin/' ) !== -1 && url.search.indexOf( 'page=tpp-' ) !== -1 ) {
                e.respondWith(
                        fetch( req ).then( function ( res ) {
                                var copy = res.clone();
                                caches.open( TPPSALARY_SW_CACHE ).then( function ( c ) { c.put( req, copy ); } );
                                return res;
                        } ).catch( function () {
                                return caches.match( req ).then( function ( hit ) {
                                        return hit || new Response( '<!DOCTYPE html><meta charset="utf-8"><body style="background:#13171e;color:#e9edf3;font-family:tahoma;direction:rtl;text-align:center;padding:40px"><h1>حالت آفلاین</h1><p>این صفحه هنوز در حافظه آفلاین ذخیره نشده است. یک بار آنلاین باز کنید.</p></body>', { headers: { 'Content-Type': 'text/html; charset=utf-8' } } );
                                } );
                        } )
                );
                return;
        }

        // فایل‌های استاتیک پلاگین: کش-اول.
        if ( url.pathname.indexOf( 'plugins/tpp_salary' ) !== -1 ) {
                e.respondWith(
                        caches.match( req ).then( function ( hit ) {
                                var net = fetch( req ).then( function ( res ) {
                                        var copy = res.clone();
                                        caches.open( TPPSALARY_SW_CACHE ).then( function ( c ) { c.put( req, copy ); } );
                                        return res;
                                } ).catch( function () { return hit; } );
                                return hit || net;
                        } )
                );
        }
} );
                <?php
                exit;
        }
}
}
// TPP_SALARY GUARD END
