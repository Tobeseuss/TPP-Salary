<?php
/**
 * REST API نسخه آفلاین — اتصال نرم‌افزار دسکتاپ پایتون به سایت
 *
 * نسخه 1.7.0 — یک REST API امن با کلید داینامیک (بدون هاردکد) برای برنامه
 * آفلاین پایتون (پوشه python-app داخل همین افزونه) فراهم می‌کند:
 *
 *   GET  /wp-json/tpp_salary/v1/ping    → بررسی اتصال و نسخه
 *   GET  /wp-json/tpp_salary/v1/bundle  → بسته کامل داده (همگام‌سازی کامل)
 *   POST /wp-json/tpp_salary/v1/sync    → اعمال صف تغییرات آفلاین + بسته تازه
 *
 * نکات امنیتی:
 * - کلید API هرگز هاردکد نمی‌شود؛ در پیشخوان (تنظیمات ← تب «برنامه آفلاین») تولید و
 *   فقط یک‌بار نمایش داده می‌شود و فقط هش آن در دیتابیس ذخیره می‌شود.
 * - هر درخواست باید هدر X-TPP-Key را با کلید معتبر ارسال کند (hash_equals).
 * - همه عملیات نوشتن از همان هسته‌های افزونه (upsert_record و…) عبور می‌کنند.
 *
 * پروتکل همگام‌سازی (دوطرفه):
 * - برنامه پایتون تغییرات آفلاین را به‌صورت صف op ارسال می‌کند (record/employee/
 *   center/bank → upsert/delete) و در پاسخ، نتیجه تک‌تک ops و یک بسته تازه دریافت می‌کند.
 * - تداخل رکوردها با updated_at تشخیص داده می‌شود (نسخه جدیدتر ملاک است).
 *
 * @package TppSalary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class TppSalary_Api
 */
if ( ! class_exists( 'TppSalary_Api' ) ) {
        // TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TppSalary_Api {

        const REST_NS      = 'tpp_salary/v1';
        const OPT_API      = 'tpp_salary_api';
        const KEY_HEADER   = 'X-TPP-Key';
        const SCHEMA       = 1; // نسخه ساختار بسته — برنامه پایتون بر اساس آن سازگاری را بررسی می‌کند.
        const MAX_OPS      = 300;
        const MAX_RECORDS  = 50000;

        /**
         * مقداردهی اولیه
         *
         * @return void
         */
        public static function init() {
                add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
                add_action( 'admin_post_tpp_salary_api_generate', array( __CLASS__, 'admin_generate_key' ) );
                add_action( 'admin_post_tpp_salary_api_revoke', array( __CLASS__, 'admin_revoke_key' ) );
        }

        /* ------------------------------------------------------------------
         * مدیریت کلید API
         * ---------------------------------------------------------------- */

        /**
         * وضعیت ذخیره API — آرایه: key_hash, created_at, last_used
         *
         * @return array
         */
        public static function get_state() {
                $st = get_option( self::OPT_API, array() );
                if ( ! is_array( $st ) ) {
                        $st = array();
                }
                return $st;
        }

        /**
         * آیا کلید فعال وجود دارد؟
         *
         * @return bool
         */
        public static function has_key() {
                $st = self::get_state();
                return ! empty( $st['key_hash'] ) && empty( $st['revoked'] );
        }

        /**
         * تولید کلید جدید (هسته قابل‌تست)
         *
         * @return string کلید خام (فقط یک‌بار نمایش داده می‌شود).
         */
        public static function generate_key() {
                $raw = 'tppk_' . wp_generate_password( 48, false, false ) . wp_generate_password( 16, false, false );
                update_option(
                        self::OPT_API,
                        array(
                                'key_hash'   => hash( 'sha256', $raw ),
                                'created_at' => current_time( 'mysql' ),
                                'last_used'  => '',
                                'revoked'    => 0,
                        ),
                        false
                );
                return $raw;
        }

        /**
         * لغو کلید
         *
         * @return void
         */
        public static function revoke_key() {
                update_option( self::OPT_API, array( 'key_hash' => '', 'created_at' => '', 'last_used' => '', 'revoked' => 1 ), false );
        }

        /**
         * هندلر پیشخوان: تولید کلید جدید
         *
         * @return void
         */
        public static function admin_generate_key() {
                if ( ! current_user_can( 'manage_options' ) ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_salary_api_generate' );
                $raw = self::generate_key();
                wp_safe_redirect(
                        add_query_arg(
                                array(
                                        'page'    => 'tpp-salary-settings',
                                        'tab'     => 'offline',
                                        'api_key' => $raw, // فقط یک‌بار در URL نمایش داده می‌شود.
                                ),
                                admin_url( 'admin.php' )
                        )
                );
                exit;
        }

        /**
         * هندلر پیشخوان: لغو کلید
         *
         * @return void
         */
        public static function admin_revoke_key() {
                if ( ! current_user_can( 'manage_options' ) ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_salary_api_revoke' );
                self::revoke_key();
                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-salary-settings', 'tab' => 'offline', 'revoked' => '1' ), admin_url( 'admin.php' ) ) );
                exit;
        }

        /* ------------------------------------------------------------------
         * مسیرهای REST
         * ---------------------------------------------------------------- */

        /**
         * ثبت مسیرها
         *
         * @return void
         */
        public static function routes() {
                register_rest_route(
                        self::REST_NS,
                        '/ping',
                        array(
                                'methods'             => 'GET',
                                'callback'            => array( __CLASS__, 'handle_ping' ),
                                'permission_callback' => array( __CLASS__, 'check_auth' ),
                        )
                );
                register_rest_route(
                        self::REST_NS,
                        '/bundle',
                        array(
                                'methods'             => 'GET',
                                'callback'            => array( __CLASS__, 'handle_bundle' ),
                                'permission_callback' => array( __CLASS__, 'check_auth' ),
                        )
                );
                register_rest_route(
                        self::REST_NS,
                        '/sync',
                        array(
                                'methods'             => 'POST',
                                'callback'            => array( __CLASS__, 'handle_sync' ),
                                'permission_callback' => array( __CLASS__, 'check_auth' ),
                        )
                );
        }

        /**
         * بررسی احراز هویت با کلید API
         *
         * @param object $request درخواست REST.
         * @return bool|WP_Error
         */
        public static function check_auth( $request ) {
                $st  = self::get_state();
                $hdr = '';
                if ( $request && is_callable( array( $request, 'get_header' ) ) ) {
                        $hdr = (string) $request->get_header( self::KEY_HEADER );
                }
                if ( '' === $hdr && isset( $_SERVER['HTTP_X_TPP_KEY'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        $hdr = (string) $_SERVER['HTTP_X_TPP_KEY']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
                }
                if ( '' === trim( $hdr ) ) {
                        return new WP_Error( 'tpp_salary_api_no_key', 'کلید API ارسال نشده است (هدر X-TPP-Key)', array( 'status' => 401 ) );
                }
                if ( empty( $st['key_hash'] ) ) {
                        return new WP_Error( 'tpp_salary_api_not_configured', 'هنوز کلید API تولید نشده است؛ از پیشخوان ← تنظیمات ← تب «برنامه آفلاین» کلید بسازید.', array( 'status' => 403 ) );
                }
                if ( ! hash_equals( (string) $st['key_hash'], hash( 'sha256', trim( $hdr ) ) ) ) {
                        return new WP_Error( 'tpp_salary_api_bad_key', 'کلید API نامعتبر است', array( 'status' => 401 ) );
                }
                // به‌روزرسانی آخرین استفاده (حداکثر هر ۶۰ ثانیه برای کاهش نوشتن).
                $last = isset( $st['last_used'] ) ? (string) $st['last_used'] : '';
                if ( '' === $last || ( time() - strtotime( $last ) ) > 60 ) {
                        $st['last_used'] = current_time( 'mysql' );
                        update_option( self::OPT_API, $st, false );
                }
                return true;
        }

        /**
         * GET /ping
         *
         * @param object $request درخواست.
         * @return object
         */
        public static function handle_ping( $request ) {
                $jtoday = TppSalary_Jalali::today();
                return rest_ensure_response(
                        array(
                                'ok'          => true,
                                'plugin'      => 'tpp_salary',
                                'version'     => defined( 'TPP_SALARY_VERSION' ) ? TPP_SALARY_VERSION : '',
                                'schema'      => self::SCHEMA,
                                'site'        => home_url(),
                                'company'     => (string) tpp_salary_get_setting( 'company_name', '' ),
                                'server_time' => current_time( 'mysql' ),
                                'period'      => array( 'jyear' => (int) $jtoday[0], 'jmonth' => (int) $jtoday[1] ),
                        )
                );
        }

        /**
         * GET /bundle — بسته کامل داده برای همگام‌سازی
         *
         * @param object $request درخواست.
         * @return object
         */
        public static function handle_bundle( $request ) {
                return rest_ensure_response( self::build_bundle() );
        }

        /**
         * POST /sync — اعمال صف ops و بازگرداندن بسته تازه
         *
         * بدنه JSON: { "ops": [ {ref, action, ...}, ... ] }
         *
         * @param object $request درخواست.
         * @return object
         */
        public static function handle_sync( $request ) {
                $ops = array();
                if ( $request && is_callable( array( $request, 'get_json_params' ) ) ) {
                        $body = $request->get_json_params();
                        if ( is_array( $body ) && isset( $body['ops'] ) && is_array( $body['ops'] ) ) {
                                $ops = $body['ops'];
                        }
                }
                if ( count( $ops ) > self::MAX_OPS ) {
                        $ops = array_slice( $ops, 0, self::MAX_OPS );
                }
                $results = array();
                /*
                 * نگاشت شناسه‌های محلی (منفی) در همان دسته: اگر op کارمند/مرکز جدید در همین
                 * دسته ساخته شود و op رکورد بعدی به همان شناسه منفی ارجاع دهد، سرور آن را
                 * به شناسه واقعی تبدیل می‌کند تا رکورد یتیم ساخته نشود.
                 */
                $id_map = array( 'employee' => array(), 'center' => array(), 'bank' => array() );
                foreach ( $ops as $op ) {
                        if ( ! is_array( $op ) ) {
                                continue;
                        }
                        $results[] = self::apply_op( $op, $id_map );
                }
                return rest_ensure_response(
                        array(
                                'results' => $results,
                                'bundle'  => self::build_bundle(),
                        )
                );
        }

        /* ------------------------------------------------------------------
         * بسته داده
         * ---------------------------------------------------------------- */

        /**
         * ساخت بسته کامل (قابل‌تست)
         *
         * @return array
         */
        public static function build_bundle() {
                global $wpdb;
                $jtoday = TppSalary_Jalali::today();

                $employees = array();
                foreach ( tpp_salary_get_employees( null, true ) as $u ) {
                        $profile     = tpp_salary_get_profile( $u->ID );
                        $employees[] = array(
                                'id'         => (int) $u->ID,
                                'name'       => $u->display_name,
                                'login'      => $u->user_login,
                                'email'      => $u->user_email,
                                'national'   => get_user_meta( $u->ID, 'tpp_salary_national_id', true ),
                                'mobile'     => get_user_meta( $u->ID, 'tpp_salary_mobile', true ),
                                'terminated' => tpp_salary_is_terminated( $u->ID ) ? 1 : 0,
                                'centers'    => array_map( 'intval', $profile['centers'] ),
                                'profile'    => $profile,
                        );
                }

                $records = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                        $wpdb->prepare(
                                "SELECT * FROM {$wpdb->prefix}tpp_salary_records ORDER BY id ASC LIMIT %d", // phpcs:ignore
                                self::MAX_RECORDS
                        ),
                        ARRAY_A
                );

                $fields = array();
                foreach ( tpp_salary_get_fields() as $f ) {
                        if ( ! tpp_salary_field_in_record( $f ) ) {
                                continue;
                        }
                        $fields[] = array(
                                'key'         => $f->field_key,
                                'label'       => $f->label,
                                'type'        => $f->field_type,
                                'default'     => (string) $f->default_value,
                                'formula'     => (string) $f->formula,
                                'calculated'  => (int) $f->is_calculated,
                                'allow_manual' => (int) $f->allow_manual,
                                'sort'        => (int) $f->sort_order,
                        );
                }

                $centers = array();
                foreach ( tpp_salary_get_centers() as $c ) {
                        $centers[] = array( 'id' => (int) $c->id, 'name' => $c->name, 'created_at' => (string) $c->created_at );
                }

                $banks = array();
                foreach ( tpp_salary_get_banks() as $b ) {
                        $banks[] = array( 'id' => (int) $b->id, 'name' => $b->name, 'sort_order' => (int) $b->sort_order );
                }

                $profile_fields = array();
                foreach ( tpp_salary_get_profile_fields() as $f ) {
                        $profile_fields[] = array(
                                'key'     => $f->field_key,
                                'label'   => $f->label,
                                'type'    => $f->field_type,
                                'default' => (string) $f->default_value,
                                'sort'    => (int) $f->sort_order,
                        );
                }

                $settings = tpp_salary_get_settings();

                return array(
                        'schema'        => self::SCHEMA,
                        'generated_at'  => current_time( 'mysql' ),
                        'version'       => defined( 'TPP_SALARY_VERSION' ) ? TPP_SALARY_VERSION : '',
                        'period'        => array( 'jyear' => (int) $jtoday[0], 'jmonth' => (int) $jtoday[1] ),
                        'company_name'  => (string) $settings['company_name'],
                        'currency'      => (string) $settings['currency'],
                        /* نسخه 1.7.1: همه اعداد انگلیسی — همیشه صفر برای کلاینت‌های قدیمی هم. */
                        'digits_fa'     => 0,
                        'formulas'      => is_array( $settings['formulas'] ) ? $settings['formulas'] : array(),
                        'defaults'      => is_array( $settings['defaults'] ) ? $settings['defaults'] : array(),
                        'fields'        => $fields,
                        'profile_fields' => $profile_fields,
                        'centers'       => $centers,
                        'banks'         => $banks,
                        'employees'     => $employees,
                        'records'       => is_array( $records ) ? array_values( $records ) : array(),
                );
        }

        /* ------------------------------------------------------------------
         * اعمال عملیات صف (ops)
         * ---------------------------------------------------------------- */

        /**
         * اعمال یک عملیات (قابل‌تست)
         *
         * @param array $op     عملیات: ref, action, payload/record_id/employee/center/bank...
         * @param array $id_map نگاشت شناسه محلی→سرور که در طول دسته ساخته می‌شود (اختیاری).
         * @return array نتیجه: {ref, status: applied|error|conflict, ...}
         */
        public static function apply_op( $op, &$id_map = null ) {
                $map_active = is_array( $id_map );
                if ( ! $map_active ) {
                        $id_map = array( 'employee' => array(), 'center' => array(), 'bank' => array() );
                }
                $ref    = isset( $op['ref'] ) ? sanitize_text_field( (string) $op['ref'] ) : '';
                $action = isset( $op['action'] ) ? (string) $op['action'] : '';
                /*
                 * توجه: sanitize_key برای action استفاده نمی‌شود چون نقطه (record.upsert)
                 * را حذف می‌کند؛ به‌جای آن action با فهرست سفید صریح اعتبارسنجی می‌شود.
                 */
                if ( ! preg_match( '/^[a-z_]{1,20}\.[a-z_]{1,20}$/', $action ) ) {
                        $action = '';
                }
                if ( '' === $ref ) {
                        $ref = 'op_' . wp_rand( 100000, 999999 );
                }
                if ( '' === $action ) {
                        return array( 'ref' => $ref, 'status' => 'error', 'message' => 'نوع عملیات نامعتبر است' );
                }

                $allowed = array(
                        'record.upsert', 'record.delete',
                        'employee.upsert', 'employee.delete',
                        'center.upsert', 'center.delete',
                        'bank.upsert', 'bank.delete',
                );
                if ( ! in_array( $action, $allowed, true ) ) {
                        return array( 'ref' => $ref, 'status' => 'error', 'message' => 'عملیات ناشناخته: ' . $action );
                }

                switch ( $action ) {
                        case 'record.upsert':
                                return self::op_record_upsert( $ref, $op, $id_map );
                        case 'record.delete':
                                return self::op_record_delete( $ref, $op, $id_map );
                        case 'employee.upsert':
                                return self::op_employee_upsert( $ref, $op, $id_map );
                        case 'employee.delete':
                                return self::op_employee_delete( $ref, $op, $id_map );
                        case 'center.upsert':
                                return self::op_center_upsert( $ref, $op, $id_map );
                        case 'center.delete':
                                return self::op_center_delete( $ref, $op, $id_map );
                        case 'bank.upsert':
                                return self::op_bank_upsert( $ref, $op, $id_map );
                        case 'bank.delete':
                                return self::op_bank_delete( $ref, $op, $id_map );
                }
                return array( 'ref' => $ref, 'status' => 'error', 'message' => 'عملیات ناشناخته: ' . $action );
        }

        /**
         * حل شناسه منفی محلی با نگاشت دسته — در صورت حل‌نشدن false برمی‌گردد.
         *
         * @param array $id_map نگاشت.
         * @param string $type  employee|center|bank.
         * @param int   $id     شناسه.
         * @return int|false
         */
        private static function resolve_map( $id_map, $type, $id ) {
                $id = (int) $id;
                if ( $id >= 0 ) {
                        return $id;
                }
                if ( isset( $id_map[ $type ][ $id ] ) ) {
                        return (int) $id_map[ $type ][ $id ];
                }
                return false;
        }

        /**
         * ذخیره/به‌روزرسانی رکورد حقوق
         *
         * @param string $ref    مرجع.
         * @param array  $op     عملیات.
         * @param array  $id_map نگاشت دسته.
         * @return array
         */
        private static function op_record_upsert( $ref, $op, $id_map ) {
                $p = isset( $op['payload'] ) && is_array( $op['payload'] ) ? $op['payload'] : array();
                $user_id   = isset( $p['user_id'] ) ? (int) $p['user_id'] : 0;
                $center_id = isset( $p['center_id'] ) ? (int) $p['center_id'] : 0;
                $jyear     = isset( $p['jyear'] ) ? (int) $p['jyear'] : 0;
                $jmonth    = isset( $p['jmonth'] ) ? (int) $p['jmonth'] : 0;
                $raw       = isset( $p['values'] ) && is_array( $p['values'] ) ? $p['values'] : array();
                $insurable_formula = ! empty( $p['insurable_formula'] );

                if ( $user_id < 0 || $center_id < 0 ) {
                        $r_user   = self::resolve_map( $id_map, 'employee', $user_id );
                        $r_center = self::resolve_map( $id_map, 'center', $center_id );
                        if ( false === $r_user || false === $r_center ) {
                                return array( 'ref' => $ref, 'status' => 'error', 'message' => 'کارمند یا مرکز مرجع هنوز همگام نشده است؛ دوباره تلاش کنید' );
                        }
                        $user_id   = $r_user;
                        $center_id = $r_center;
                }

                $existing = tpp_salary_get_record_period( $user_id, $center_id, $jyear, $jmonth );
                $client_updated = isset( $op['client_updated_at'] ) ? (string) $op['client_updated_at'] : '';
                if ( $existing && $client_updated && ! empty( $existing->updated_at ) && (string) $existing->updated_at > $client_updated ) {
                        return array(
                                'ref'     => $ref,
                                'status'  => 'conflict',
                                'message' => 'نسخه سرور جدیدتر است',
                                'server'  => array(
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
         * حذف رکورد حقوق
         *
         * @param string $ref مرجع.
         * @param array  $op  عملیات.
         * @return array
         */
        private static function op_record_delete( $ref, $op, $id_map ) {
                global $wpdb;
                $id = isset( $op['record_id'] ) ? (int) $op['record_id'] : 0;
                if ( $id < 0 ) {
                        // رکورد محلی منفی هرگز روی سرور نبوده است.
                        return array( 'ref' => $ref, 'status' => 'applied', 'action' => 'delete' );
                }
                if ( ! $id ) {
                        return array( 'ref' => $ref, 'status' => 'error', 'message' => 'شناسه رکورد نامعتبر' );
                }
                $row = $wpdb->get_row( $wpdb->prepare( "SELECT id, updated_at FROM {$wpdb->prefix}tpp_salary_records WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                if ( ! $row ) {
                        return array( 'ref' => $ref, 'status' => 'applied', 'action' => 'delete' );
                }
                $client_deleted = isset( $op['client_updated_at'] ) ? (string) $op['client_updated_at'] : '';
                if ( $client_deleted && ! empty( $row->updated_at ) && (string) $row->updated_at > $client_deleted ) {
                        return array( 'ref' => $ref, 'status' => 'conflict', 'message' => 'رکورد روی سرور پس از حذف محلی تغییر کرده است' );
                }
                $wpdb->delete( $wpdb->prefix . 'tpp_salary_records', array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                return array( 'ref' => $ref, 'status' => 'applied', 'action' => 'delete' );
        }

        /**
         * افزودن/به‌روزرسانی کارمند (ایجاد حساب وردپرس با نقش کارمندی)
         *
         * @param string $ref مرجع.
         * @param array  $op  عملیات.
         * @return array
         */
        private static function op_employee_upsert( $ref, $op, $id_map ) {
                $e = isset( $op['employee'] ) && is_array( $op['employee'] ) ? $op['employee'] : array();
                $id         = isset( $e['id'] ) ? (int) $e['id'] : 0;
                $name       = isset( $e['name'] ) ? sanitize_text_field( (string) $e['name'] ) : '';
                $national   = isset( $e['national'] ) ? TppSalary_Jalali::digits_en( sanitize_text_field( (string) $e['national'] ) ) : '';
                $mobile     = isset( $e['mobile'] ) ? TppSalary_Jalali::digits_en( sanitize_text_field( (string) $e['mobile'] ) ) : '';
                $terminated = ! empty( $e['terminated'] ) ? '1' : '';
                $centers    = isset( $e['centers'] ) && is_array( $e['centers'] ) ? array_values( array_filter( array_map( 'intval', $e['centers'] ) ) ) : array();
                $profile_in = isset( $e['profile'] ) && is_array( $e['profile'] ) ? $e['profile'] : array();

                if ( '' === $name ) {
                        return array( 'ref' => $ref, 'status' => 'error', 'message' => 'نام کارمند الزامی است' );
                }

                /* حل مراکز منفی با نگاشت دسته — موارد حل‌نشده حذف می‌شوند. */
                $resolved_centers = array();
                foreach ( $centers as $cid ) {
                        $rid = self::resolve_map( $id_map, 'center', $cid );
                        if ( false !== $rid && $rid > 0 ) {
                                $resolved_centers[] = (int) $rid;
                        }
                }
                $centers = array_values( array_unique( $resolved_centers ) );

                $user = $id > 0 ? get_user_by( 'id', $id ) : null;
                if ( $user && ! in_array( 'tpp_salary_employee', (array) $user->roles, true ) ) {
                        $user->add_role( 'tpp_salary_employee' );
                }

                $created = false;
                if ( ! $user ) {
                        /* ایجاد حساب جدید — منطبق بر create_employee افزونه. */
                        $username   = preg_match( '/^\d{10}$/', $national ) ? $national : (string) wp_rand( 1000000000, 9999999999 );
                        $login      = $username;
                        $i          = 0;
                        while ( username_exists( $login ) ) {
                                $login = $username . '_' . ( ++$i );
                        }
                        $plain_pass = wp_generate_password( 12, false );
                        $user_id    = wp_insert_user(
                                array(
                                        'user_login'   => $login,
                                        'user_pass'    => $plain_pass,
                                        'display_name' => $name,
                                        'role'         => 'tpp_salary_employee',
                                )
                        );
                        if ( is_wp_error( $user_id ) ) {
                                return array( 'ref' => $ref, 'status' => 'error', 'message' => $user_id->get_error_message() );
                        }
                        $created = true;
                        $user    = get_user_by( 'id', $user_id );
                } else {
                        $user_id = (int) $user->ID;
                        if ( $name !== $user->display_name ) {
                                wp_update_user(
                                        array(
                                                'ID'           => $user_id,
                                                'display_name' => $name,
                                        )
                                );
                        }
                }

                if ( $national ) {
                        update_user_meta( $user_id, 'tpp_salary_national_id', $national );
                }
                if ( $mobile ) {
                        update_user_meta( $user_id, 'tpp_salary_mobile', $mobile );
                }
                if ( $terminated ) {
                        update_user_meta( $user_id, 'tpp_salary_terminated', '1' );
                } else {
                        delete_user_meta( $user_id, 'tpp_salary_terminated' );
                }

                $profile = tpp_salary_get_profile( $user_id );
                if ( ! empty( $profile_in ) && is_array( $profile_in ) ) {
                        foreach ( $profile_in as $k => $v ) {
                                if ( 'centers' === $k || 'bank_accounts' === $k ) {
                                        continue;
                                }
                                $profile[ $k ] = is_scalar( $v ) ? sanitize_text_field( (string) $v ) : $v;
                        }
                }
                $profile['centers']   = $centers;
                $profile['full_name'] = $name;
                tpp_salary_save_profile( $user_id, $profile );

                /* ثبت نگاشت محلی→سرور برای opهای بعدی همین دسته. */
                if ( $id < 0 ) {
                        $id_map['employee'][ $id ] = $user_id;
                }

                return array(
                        'ref'        => $ref,
                        'status'     => 'applied',
                        'action'     => $created ? 'created' : 'updated',
                        'server_id'  => $user_id,
                        'login'      => $user ? $user->user_login : '',
                );
        }

        /**
         * حذف کارمند — هم‌سان با افزونه: فقط نقش کارمندی برداشته می‌شود (حساب حفظ می‌شود)
         *
         * @param string $ref مرجع.
         * @param array  $op  عملیات.
         * @return array
         */
        private static function op_employee_delete( $ref, $op, $id_map ) {
                $id  = isset( $op['employee_id'] ) ? (int) $op['employee_id'] : 0;
                if ( $id < 0 ) {
                        $rid = self::resolve_map( $id_map, 'employee', $id );
                        if ( false === $rid || $rid <= 0 ) {
                                return array( 'ref' => $ref, 'status' => 'applied', 'action' => 'delete' );
                        }
                        $id = $rid;
                }
                $usr = $id > 0 ? get_user_by( 'id', $id ) : null;
                if ( ! $usr ) {
                        return array( 'ref' => $ref, 'status' => 'applied', 'action' => 'delete' );
                }
                if ( in_array( 'tpp_salary_employee', (array) $usr->roles, true ) ) {
                        $usr->remove_role( 'tpp_salary_employee' );
                }
                return array( 'ref' => $ref, 'status' => 'applied', 'action' => 'delete' );
        }

        /**
         * افزودن/به‌روزرسانی مرکز
         *
         * @param string $ref مرجع.
         * @param array  $op  عملیات.
         * @return array
         */
        private static function op_center_upsert( $ref, $op, $id_map ) {
                global $wpdb;
                $c    = isset( $op['center'] ) && is_array( $op['center'] ) ? $op['center'] : array();
                $id   = isset( $c['id'] ) ? (int) $c['id'] : 0;
                $name = isset( $c['name'] ) ? sanitize_text_field( (string) $c['name'] ) : '';
                if ( '' === $name ) {
                        return array( 'ref' => $ref, 'status' => 'error', 'message' => 'نام مرکز الزامی است' );
                }
                $table = $wpdb->prefix . 'tpp_salary_centers';
                $dup   = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE name = %s AND id != %d LIMIT 1", $name, $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                if ( $dup ) {
                        return array( 'ref' => $ref, 'status' => 'error', 'message' => 'مرکزی با همین نام از قبل وجود دارد' );
                }
                if ( $id > 0 ) {
                        $ok = $wpdb->update( $table, array( 'name' => $name ), array( 'id' => $id ), array( '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                        if ( false === $ok ) {
                                return array( 'ref' => $ref, 'status' => 'error', 'message' => 'به‌روزرسانی مرکز ناموفق بود' );
                        }
                        return array( 'ref' => $ref, 'status' => 'applied', 'action' => 'updated', 'server_id' => $id );
                }
                $ok = $wpdb->insert( $table, array( 'name' => $name, 'created_at' => current_time( 'mysql' ) ), array( '%s', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                if ( ! $ok ) {
                        return array( 'ref' => $ref, 'status' => 'error', 'message' => 'افزودن مرکز ناموفق بود' );
                }
                $server_id = (int) $wpdb->insert_id;
                $id_map['center'][ $id ] = $server_id; // نگاشت برای opهای بعدی همین دسته
                return array( 'ref' => $ref, 'status' => 'applied', 'action' => 'created', 'server_id' => $server_id );
        }

        /**
         * حذف مرکز
         *
         * @param string $ref مرجع.
         * @param array  $op  عملیات.
         * @return array
         */
        private static function op_center_delete( $ref, $op, $id_map ) {
                global $wpdb;
                $id = isset( $op['center_id'] ) ? (int) $op['center_id'] : 0;
                if ( $id < 0 ) {
                        $rid = self::resolve_map( $id_map, 'center', $id );
                        if ( false === $rid || $rid <= 0 ) {
                                return array( 'ref' => $ref, 'status' => 'applied', 'action' => 'delete' );
                        }
                        $id = $rid;
                }
                if ( ! $id ) {
                        return array( 'ref' => $ref, 'status' => 'error', 'message' => 'شناسه مرکز نامعتبر' );
                }
                $wpdb->delete( $wpdb->prefix . 'tpp_salary_centers', array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                return array( 'ref' => $ref, 'status' => 'applied', 'action' => 'delete' );
        }

        /**
         * افزودن/به‌روزرسانی بانک
         *
         * @param string $ref مرجع.
         * @param array  $op  عملیات.
         * @return array
         */
        private static function op_bank_upsert( $ref, $op, $id_map ) {
                global $wpdb;
                $b    = isset( $op['bank'] ) && is_array( $op['bank'] ) ? $op['bank'] : array();
                $id   = isset( $b['id'] ) ? (int) $b['id'] : 0;
                $name = isset( $b['name'] ) ? sanitize_text_field( (string) $b['name'] ) : '';
                if ( '' === $name ) {
                        return array( 'ref' => $ref, 'status' => 'error', 'message' => 'نام بانک الزامی است' );
                }
                $table = $wpdb->prefix . 'tpp_salary_banks';
                if ( $id > 0 ) {
                        $wpdb->update( $table, array( 'name' => $name ), array( 'id' => $id ), array( '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                        return array( 'ref' => $ref, 'status' => 'applied', 'action' => 'updated', 'server_id' => $id );
                }
                $dup = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE name = %s LIMIT 1", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                if ( $dup ) {
                        return array( 'ref' => $ref, 'status' => 'applied', 'action' => 'updated', 'server_id' => (int) $dup );
                }
                $wpdb->insert( $table, array( 'name' => $name, 'sort_order' => 0 ), array( '%s', '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $server_id = (int) $wpdb->insert_id;
                $id_map['bank'][ $id ] = $server_id;
                return array( 'ref' => $ref, 'status' => 'applied', 'action' => 'created', 'server_id' => $server_id );
        }

        /**
         * حذف بانک
         *
         * @param string $ref مرجع.
         * @param array  $op  عملیات.
         * @return array
         */
        private static function op_bank_delete( $ref, $op, $id_map ) {
                global $wpdb;
                $id = isset( $op['bank_id'] ) ? (int) $op['bank_id'] : 0;
                if ( $id < 0 ) {
                        $rid = self::resolve_map( $id_map, 'bank', $id );
                        if ( false === $rid || $rid <= 0 ) {
                                return array( 'ref' => $ref, 'status' => 'applied', 'action' => 'delete' );
                        }
                        $id = $rid;
                }
                if ( ! $id ) {
                        return array( 'ref' => $ref, 'status' => 'error', 'message' => 'شناسه بانک نامعتبر' );
                }
                $wpdb->delete( $wpdb->prefix . 'tpp_salary_banks', array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                return array( 'ref' => $ref, 'status' => 'applied', 'action' => 'delete' );
        }
}
// TPP_SALARY GUARD END (TppSalary_Api)
}
