<?php
/**
 * پشتیبان‌گیری — خودکار (روزانه/هفتگی/ماهانه/سالانه) و دستی (JSON/اکسل/ZIP) + بازگردانی
 *
 * @package TPP_Salary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class TPP_Backup
 */
if ( ! class_exists( 'TPP_Backup' ) ) {
        // TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TPP_Backup {

        /**
         * مقداردهی اولیه
         *
         * @return void
         */
        public static function init() {
                add_action( 'admin_post_tpp_backup_create', array( __CLASS__, 'create_manual' ) );
                add_action( 'admin_post_tpp_backup_restore', array( __CLASS__, 'restore' ) );
                add_action( 'tpp_backup_daily_event', array( __CLASS__, 'run_daily' ) );
                add_action( 'tpp_backup_weekly_event', array( __CLASS__, 'run_weekly' ) );
                add_action( 'tpp_backup_monthly_event', array( __CLASS__, 'run_monthly' ) );
                add_action( 'tpp_backup_yearly_event', array( __CLASS__, 'run_yearly' ) );
        }

        /**
         * زمان‌بندی کرون‌ها مطابق تنظیمات
         *
         * @return void
         */
        public static function reschedule() {
                $settings = tpp_get_settings()['backup'];
                $schedule = array(
                        'daily'     => 'daily',
                        'weekly'    => 'weekly',
                        'monthly'   => 'monthly',
                        'yearly'    => 'yearly',
                );
                foreach ( $schedule as $key => $recurrence ) {
                        $hook = 'tpp_backup_' . $key . '_event';
                        wp_clear_scheduled_hook( $hook );
                        if ( ! empty( $settings[ $key ] ) ) {
                                $ts = strtotime( 'tomorrow 03:00' );
                                wp_schedule_event( $ts, $recurrence, $hook );
                        }
                }
        }

        /**
         * اجرای بکاپ‌های خودکار
         *
         * @param string $origin نوع.
         * @return void
         */
        private static function auto( $origin ) {
                $settings = tpp_get_settings()['backup'];
                if ( empty( $settings[ $origin ] ) ) {
                        return;
                }
                self::make( 'zip', $origin );
        }

        /**
         * رویدادهای کرون
         *
         * @return void
         */
        public static function run_daily() {
                self::auto( 'daily' );
        }
        public static function run_weekly() {
                self::auto( 'weekly' );
        }
        public static function run_monthly() {
                self::auto( 'monthly' );
        }
        public static function run_yearly() {
                self::auto( 'yearly' );
        }

        /**
         * ایجاد بکاپ دستی
         *
         * @return void
         */
        public static function create_manual() {
                if ( ! tpp_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_backup_create' );
                $type = in_array( (isset($_POST['backup_type'] )?$_POST['backup_type'] : ''), array( 'json', 'excel', 'zip' ), true ) ? sanitize_key( $_POST['backup_type'] ) : 'json';
                $file = self::make( $type, 'manual' );
                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-backup', 'created' => $file ? '1' : '0' ), admin_url( 'admin.php' ) ) );
                exit;
        }

        /**
         * ساخت بکاپ
         *
         * @param string $type  json|excel|zip.
         * @param string $origin manual|daily|weekly|monthly|yearly.
         * @return string|WP_Error مسیر فایل.
         */
        public static function make( $type, $origin = 'manual' ) {
                $dir = tpp_backup_dir();
                if ( ! file_exists( $dir ) ) {
                        wp_mkdir_p( $dir );
                        // محافظت از دسترسی مستقیم.
                        file_put_contents( $dir . '/.htaccess', "Deny from all\n" ); // phpcs:ignore
                        file_put_contents( $dir . '/index.html', '' ); // phpcs:ignore
                }
                $stamp = date_i18n( 'Ymd-His' );
                $path  = $dir . '/tpp-backup-' . $type . '-' . $stamp . '.' . $type;

                switch ( $type ) {
                        case 'json':
                                $data = self::collect_json();
                                $bytes = file_put_contents( $path, wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
                                break;
                        case 'excel':
                                $xlsx = self::collect_excel();
                                $data = $xlsx->to_string();
                                if ( is_wp_error( $data ) ) {
                                        return $data;
                                }
                                $bytes = file_put_contents( $path, $data );
                                break;
                        case 'zip':
                        default:
                                if ( ! class_exists( 'ZipArchive' ) ) {
                                        return new WP_Error( 'tpp_zip_missing', 'ZipArchive فعال نیست' );
                                }
                                $zip = new ZipArchive();
                                if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
                                        return new WP_Error( 'tpp_zip_open', 'ایجاد ZIP ناموفق' );
                                }
                                $zip->addFromString( 'data.json', wp_json_encode( self::collect_json(), JSON_UNESCAPED_UNICODE ) );
                                $xlsx = self::collect_excel();
                                $ex = $xlsx->to_string();
                                if ( ! is_wp_error( $ex ) ) {
                                        $zip->addFromString( 'data.xlsx', $ex );
                                }
                                $zip->close();
                                $bytes = filesize( $path );
                                break;
                }
                if ( ! $bytes ) {
                        return new WP_Error( 'tpp_backup_write', 'نوشتن فایل بکاپ ناموفق بود' );
                }
                global $wpdb;
                $wpdb->insert( // phpcs:ignore
                        $wpdb->prefix . 'tpp_backups',
                        array(
                                'backup_type' => $type,
                                'origin'      => $origin,
                                'file_path'   => $path,
                                'file_size'   => (int) $bytes,
                                'created_at'  => current_time( 'mysql' ),
                        ),
                        array( '%s', '%s', '%s', '%d', '%s' )
                );
                self::cleanup();
                return $path;
        }

        /**
         * جمع‌آوری داده به صورت JSON
         *
         * @return array
         */
        private static function collect_json() {
                global $wpdb;
                $users = get_users( array( 'role' => 'tpp_Employe', 'fields' => 'ID', 'number' => -1 ) );
                $profiles = array();
                foreach ( $users as $uid ) {
                        $profiles[] = array(
                                'user_id'      => $uid,
                                'user_login'   => get_userdata( $uid )->user_login,
                                'display_name' => get_userdata( $uid )->display_name,
                                'national_id'  => get_user_meta( $uid, 'tpp_national_id', true ),
                                'mobile'       => get_user_meta( $uid, 'tpp_mobile', true ),
                                'profile'      => tpp_get_profile( $uid ),
                        );
                }
                return array(
                        'version'     => TPP_SALARY_VERSION,
                        'stamp'       => current_time( 'mysql' ),
                        'settings'    => tpp_get_settings(),
                        'centers'     => (array) $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}tpp_centers" ), // phpcs:ignore
                        'banks'       => (array) $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}tpp_banks" ), // phpcs:ignore
                        'fields'      => (array) $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}tpp_salary_fields" ), // phpcs:ignore
                        'records'     => (array) $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}tpp_salary_records" ), // phpcs:ignore
                        'profiles'    => $profiles,
                );
        }

        /**
         * جمع‌آوری داده به صورت اکسل
         *
         * @return TPP_Xlsx_Writer
         */
        private static function collect_excel() {
                $xlsx = new TPP_Xlsx_Writer();

                // شیت کارمندان.
                $xlsx->add_sheet( 'کارمندان' );
                $headers = array( 'نام و نام خانوادگی', 'کد ملی', 'شماره همراه', 'تعداد فرزند', 'گروه اصلی', 'دستمزد روزانه مرجع', 'پایه سنوات', 'حق مسکن', 'حق بن', 'حق تأهل', 'مراکز' );
                foreach ( $headers as $i => $h ) {
                        $xlsx->set( 1, 1 + $i, $h, 'header' );
                }
                $users = tpp_get_employees();
                $row = 2;
                foreach ( $users as $u ) {
                        $p = tpp_get_profile( $u->ID );
                        $cnames = array();
                        foreach ( $p['centers'] as $cid ) {
                                $c = tpp_get_center( (int) $cid );
                                if ( $c ) {
                                        $cnames[] = $c->name;
                                }
                        }
                        $values = array(
                                $u->display_name,
                                get_user_meta( $u->ID, 'tpp_national_id', true ),
                                get_user_meta( $u->ID, 'tpp_mobile', true ),
                                isset( $p['children_count'] ) ? (float) $p['children_count'] : 0,
                                isset( $p['insurance_group'] ) ? $p['insurance_group'] : '',
                                isset( $p['daily_wage'] ) ? (float) $p['daily_wage'] : 0,
                                isset( $p['seniority'] ) ? (float) $p['seniority'] : 0,
                                isset( $p['housing'] ) ? (float) $p['housing'] : 0,
                                isset( $p['food'] ) ? (float) $p['food'] : 0,
                                isset( $p['marriage'] ) ? (float) $p['marriage'] : 0,
                                implode( '، ', $cnames ),
                        );
                        foreach ( $values as $i => $v ) {
                                $xlsx->set( $row, 1 + $i, $v, is_float( $v ) || is_int( $v ) ? 'num' : 'text' );
                        }
                        $row++;
                }

                // شیت رکوردهای حقوق.
                $xlsx->add_sheet( 'رکوردهای حقوق' );
                global $wpdb;
                $records = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}tpp_salary_records ORDER BY jyear, jmonth, center_id, id" ); // phpcs:ignore
                $fields  = tpp_get_fields();
                $headers = array( 'سال', 'ماه', 'مرکز', 'نام کارمند' );
                foreach ( $fields as $f ) {
                        $headers[] = $f->label;
                }
                foreach ( $headers as $i => $h ) {
                        $xlsx->set( 1, 1 + $i, $h, 'header' );
                }
                $row = 2;
                foreach ( $records as $r ) {
                        $u = get_userdata( $r->user_id );
                        $c = tpp_get_center( (int) $r->center_id );
                        $payload = tpp_record_payload( $r );
                        $vals = array( (int) $r->jyear, TPP_Jalali::month_name( (int) $r->jmonth ), $c ? $c->name : '', $u ? $u->display_name : '' );
                        foreach ( $vals as $i => $v ) {
                                $xlsx->set( $row, 1 + $i, $v, 'text' );
                        }
                        $col = 5;
                        foreach ( $fields as $f ) {
                                $v = isset( $payload[ $f->field_key ] ) ? $payload[ $f->field_key ] : '';
                                $xlsx->set( $row, $col, ( 'number' === $f->field_type ) ? (float) $v : (string) $v, ( 'number' === $f->field_type ) ? 'num' : 'text' );
                                $col++;
                        }
                        $row++;
                }
                $xlsx->freeze( 'A2' );
                return $xlsx;
        }

        /**
         * پاکسازی بکاپ‌های قدیمی
         *
         * @return void
         */
        private static function cleanup() {
                global $wpdb;
                $keep = (int) tpp_get_setting( 'retention', 24 );
                $old  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tpp_backups ORDER BY created_at DESC LIMIT 1000 OFFSET %d", $keep ) ); // phpcs:ignore
                foreach ( $old as $row ) {
                        if ( $row->file_path && file_exists( $row->file_path ) ) {
                                unlink( $row->file_path ); // phpcs:ignore
                        }
                        $wpdb->delete( $wpdb->prefix . 'tpp_backups', array( 'id' => $row->id ), array( '%d' ) ); // phpcs:ignore
                }
        }

        /**
         * بخش‌های مجاز فایل بکاپ و ستون‌های مجاز هر جدول —
         * جلوگیری از درج ستون دلخواه (mass assignment)
         *
         * @return array کلید JSON => (table, columns)
         */
        private static function restore_sections() {
                return array(
                        'centers' => array(
                                'table'   => 'tpp_centers',
                                'columns' => array( 'id', 'name', 'created_at' ),
                        ),
                        'banks'   => array(
                                'table'   => 'tpp_banks',
                                'columns' => array( 'id', 'name', 'sort_order' ),
                        ),
                        'fields'  => array(
                                'table'   => 'tpp_salary_fields',
                                'columns' => array( 'id', 'field_key', 'label', 'field_type', 'default_value', 'formula', 'options', 'is_profile', 'is_calculated', 'is_negative', 'allow_manual', 'show_in_payslip', 'sort_order', 'is_system', 'is_active' ),
                        ),
                        'records' => array(
                                'table'   => 'tpp_salary_records',
                                'columns' => array( 'id', 'user_id', 'center_id', 'jyear', 'jmonth', 'payload', 'gross', 'insurable', 'insurance_deduct', 'other_deductions', 'net', 'created_by', 'created_at', 'updated_at' ),
                        ),
                );
        }

        /**
         * پالایش تنظیمات بازگردانی‌شده — فقط کلیدهای شناخته‌شده
         *
         * @param mixed $raw تنظیمات خام از فایل بکاپ.
         * @return array
         */
        private static function sanitize_restored_settings( $raw ) {
                $clean = array();
                if ( ! is_array( $raw ) ) {
                        return $clean;
                }
                $text_keys  = array( 'company_name', 'currency' );
                $int_keys   = array( 'logo_id', 'per_page_a4', 'digits_fa' );
                $array_keys = array( 'formulas', 'backup', 'defaults' );
                foreach ( $text_keys as $k ) {
                        if ( isset( $raw[ $k ] ) ) {
                                $clean[ $k ] = sanitize_text_field( $raw[ $k ] );
                        }
                }
                foreach ( $int_keys as $k ) {
                        if ( isset( $raw[ $k ] ) ) {
                                $clean[ $k ] = (int) $raw[ $k ];
                        }
                }
                foreach ( $array_keys as $k ) {
                        if ( isset( $raw[ $k ] ) && is_array( $raw[ $k ] ) ) {
                                $clean[ $k ] = $raw[ $k ];
                        }
                }
                return $clean;
        }

        /**
         * بازگردانی از JSON — با اعتبارسنجی کامل و تراکنش دیتابیس
         *
         * اگر هر سطری نامعتبر باشد، هیچ جدولی خالی نمی‌شود (ROLLBACK)
         * تا برخلاف قبل، داده‌های فعلی از دست نروند.
         *
         * @return void
         */
        public static function restore() {
                if ( ! tpp_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_backup_restore' );
                if ( empty( $_FILES['restore_file']['tmp_name'] ) ) {
                        wp_die( 'فایلی آپلود نشده است' );
                }
                $tmp_file = sanitize_text_field( wp_unslash( $_FILES['restore_file']['tmp_name'] ) ); // phpcs:ignore
                $raw  = file_get_contents( $tmp_file ); // phpcs:ignore
                $data = json_decode( $raw, true );
                if ( ! is_array( $data ) || empty( $data['version'] ) ) {
                        wp_die( 'فایل بکاپ معتبر نیست' );
                }
                global $wpdb;

                $sections = self::restore_sections();

                /*
                 * مرحله ۱ — اعتبارسنجی کامل پیش از هر تغییری:
                 * هر سطر باید فقط ستون‌های مجاز داشته باشد؛ در غیر این صورت
                 * بازگردانی متوقف می‌شود بدون اینکه جدولی خالی شود.
                 */
                foreach ( $sections as $key => $def ) {
                        $rows = isset( $data[ $key ] ) ? $data[ $key ] : array();
                        if ( ! is_array( $rows ) ) {
                                wp_die( 'ساختار فایل بکاپ نامعتبر است: بخش ' . esc_html( $key ) );
                        }
                        foreach ( $rows as $row ) {
                                if ( ! is_array( $row ) ) {
                                        wp_die( 'سطر نامعتبر در بخش ' . esc_html( $key ) . ' — بازگردانی انجام نشد و داده‌های فعلی دست‌نخورده ماند.' );
                                }
                                foreach ( $row as $col => $val ) {
                                        if ( ! in_array( (string) $col, $def['columns'], true ) ) {
                                                wp_die( 'ستون ناشناخته «' . esc_html( $col ) . '» در بخش ' . esc_html( $key ) . ' — بازگردانی انجام نشد و داده‌های فعلی دست‌نخورده ماند.' );
                                        }
                                }
                        }
                }

                /*
                 * مرحله ۲ — تراکنش: پاک‌سازی و درج‌ها همه در یک تراکنش انجام می‌شود؛
                 * در هر خطا ROLLBACK کامل و داده‌های فعلی سالم می‌مانند.
                 * نکته: TRUNCATE در MySQL/MariaDB باعث COMMIT ضمنی می‌شود و قابل
                 * بازگشت نیست؛ بنابراین از DELETE استفاده می‌شود که DML است و
                 * داخل تراکنش قابل ROLLBACK است.
                 */
                $wpdb->query( 'START TRANSACTION' ); // phpcs:ignore

                $settings_raw = isset( $data['settings'] ) ? $data['settings'] : array();
                $settings_new = self::sanitize_restored_settings( $settings_raw );
                if ( ! empty( $settings_new ) ) {
                        update_option( 'tpp_settings', $settings_new, false );
                }

                foreach ( $sections as $def ) {
                        $wpdb->query( "DELETE FROM {$wpdb->prefix}{$def['table']}" ); // phpcs:ignore
                }

                $fail = '';
                foreach ( $sections as $key => $def ) {
                        $rows = isset( $data[ $key ] ) ? $data[ $key ] : array();
                        foreach ( $rows as $row ) {
                                $clean = array();
                                foreach ( $def['columns'] as $col ) {
                                        if ( array_key_exists( $col, $row ) ) {
                                                $clean[ $col ] = $row[ $col ];
                                        }
                                }
                                if ( empty( $clean ) ) {
                                        continue;
                                }
                                $res = $wpdb->insert( $wpdb->prefix . $def['table'], $clean ); // phpcs:ignore
                                if ( false === $res ) {
                                        $fail = 'خطای دیتابیس هنگام درج در ' . $def['table'] . ': ' . $wpdb->last_error;
                                        break 2;
                                }
                        }
                }

                if ( $fail ) {
                        $wpdb->query( 'ROLLBACK' ); // phpcs:ignore
                        wp_die( 'بازگردانی ناموفق بود و به حالت قبل برگشت (ROLLBACK). ' . esc_html( $fail ) );
                }

                // پروفایل‌ها و متا (خارج از تراکنش — عملیات user_meta مستقل‌اند).
                foreach ( ( (isset($data['profiles'] )?$data['profiles'] : array() )) as $item ) {
                        if ( ! is_array( $item ) ) {
                                continue;
                        }
                        $uid = (int) ( (isset($item['user_id'] )?$item['user_id'] : 0 ));
                        if ( ! $uid ) {
                                continue;
                        }
                        if ( ! empty( $item['national_id'] ) ) {
                                update_user_meta( $uid, 'tpp_national_id', sanitize_text_field( $item['national_id'] ) );
                        }
                        if ( ! empty( $item['mobile'] ) ) {
                                update_user_meta( $uid, 'tpp_mobile', sanitize_text_field( $item['mobile'] ) );
                        }
                        if ( ! empty( $item['profile'] ) && is_array( $item['profile'] ) ) {
                                tpp_save_profile( $uid, $item['profile'] );
                        }
                }

                $wpdb->query( 'COMMIT' ); // phpcs:ignore

                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-backup', 'restored' => '1' ), admin_url( 'admin.php' ) ) );
                exit;
        }
}
}
// TPP_SALARY GUARD END
