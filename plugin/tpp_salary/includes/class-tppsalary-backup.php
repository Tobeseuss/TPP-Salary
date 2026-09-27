<?php
/**
 * پشتیبان‌گیری — خودکار (روزانه/هفتگی/ماهانه/سالانه) و دستی (JSON/اکسل/ZIP) + بازگردانی
 *
 * @package TppSalary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class TppSalary_Backup
 */
if ( ! class_exists( 'TppSalary_Backup' ) ) {
        // TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TppSalary_Backup {

        /**
         * گزارش آخرین بازگردانی — نسخه 1.7.6
         *
         * شمارش بخش‌ها + کاربران تطبیق‌یافته/ساخته‌شده + رکوردهای رد‌شده + هشدارها.
         * پس از بازگردانی موفق در transient برای نمایش در صفحه پشتیبان‌گیری هم قرار می‌گیرد.
         *
         * @var array
         */
        public static $last_summary = array();

        /**
         * مقداردهی اولیه
         *
         * @return void
         */
        public static function init() {
                add_action( 'admin_post_tpp_salary_backup_create', array( __CLASS__, 'create_manual' ) );
                add_action( 'admin_post_tpp_salary_backup_restore', array( __CLASS__, 'restore' ) );
                add_action( 'admin_post_tpp_salary_backup_delete', array( __CLASS__, 'delete_backup' ) );
                add_action( 'tpp_salary_backup_daily_event', array( __CLASS__, 'run_daily' ) );
                add_action( 'tpp_salary_backup_weekly_event', array( __CLASS__, 'run_weekly' ) );
                add_action( 'tpp_salary_backup_monthly_event', array( __CLASS__, 'run_monthly' ) );
                add_action( 'tpp_salary_backup_yearly_event', array( __CLASS__, 'run_yearly' ) );
        }

        /**
         * زمان‌بندی کرون‌ها مطابق تنظیمات
         *
         * @return void
         */
        public static function reschedule() {
                $settings = tpp_salary_get_settings()['backup'];
                $schedule = array(
                        'daily'     => 'daily',
                        'weekly'    => 'weekly',
                        'monthly'   => 'monthly',
                        'yearly'    => 'yearly',
                );
                foreach ( $schedule as $key => $recurrence ) {
                        $hook = 'tpp_salary_backup_' . $key . '_event';
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
                $settings = tpp_salary_get_settings()['backup'];
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
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_salary_backup_create' );
                $raw_type = (isset($_POST['backup_type'] )?$_POST['backup_type'] : '');
                $type = in_array( $raw_type, array( 'json', 'xlsx', 'sql', 'zip', 'excel' ), true ) ? sanitize_key( $raw_type ) : 'json';
                if ( 'excel' === $type ) {
                        $type = 'xlsx'; // نام قدیمی‌مدیریتی — فایل همیشه با پسوند درست .xlsx ساخته می‌شود.
                }
                $file = self::make( $type, 'manual' );
                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-salary-backup', 'created' => $file ? '1' : '0' ), admin_url( 'admin.php' ) ) );
                exit;
        }

        /**
         * حذف یک بکاپ — فایل + رکورد دیتابیس (فقط اگر فایل داخل پوشه بکاپ باشد)
         *
         * @return void
         */
        public static function delete_backup() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_salary_backup_delete' );
                global $wpdb;

                $id = ( isset( $_GET['id'] ) ? $_GET['id'] : 0 );
                if ( ! $id ) {
                        wp_die( 'شناسه بکاپ نامعتبر است' );
                }
                $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tpp_salary_backups WHERE id = %d", $id ) ); // phpcs:ignore
                if ( ! $row ) {
                        wp_die( 'بکاپ یافت نشد' );
                }
                // حذف فایل فقط اگر داخل پوشه بکاپ پلاگین باشد (دفاع در برابر path traversal).
                if ( ! empty( $row->file_path ) ) {
                        $dir  = realpath( tpp_salary_backup_dir() );
                        $file = realpath( $row->file_path );
                        if ( $file && $dir && 0 === strpos( $file, $dir ) && file_exists( $file ) ) {
                                unlink( $file ); // phpcs:ignore
                        }
                }
                $wpdb->delete( $wpdb->prefix . 'tpp_salary_backups', array( 'id' => (int) $row->id ), array( '%d' ) ); // phpcs:ignore
                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-salary-backup', 'deleted' => '1' ), admin_url( 'admin.php' ) ) );
                exit;
        }

        /**
         * ساخت بکاپ
         *
         * نسخه 1.5.0 — نوع جدید «sql» و بازطراحی ZIP:
         * - داده‌های «کارمندان» و «رکوردهای حقوق» همیشه به صورت مجزا ذخیره می‌شوند
         *   (JSON جدا + اکسل جدا + SQL جدا).
         * - در بکاپ ZIP، فایل‌های خود افزونه (کل پوشه پلاگین) هم بایگانی می‌شود.
         *
         * @param string $type  json|xlsx|sql|zip (برای سازگاری قدیمی «excel» هم پذیرفته می‌شود و به xlsx نگاشت می‌شود).
         * @param string $origin manual|daily|weekly|monthly|yearly.
         * @return string|WP_Error مسیر فایل.
         */
        public static function make( $type, $origin = 'manual' ) {
                $dir = tpp_salary_backup_dir();
                if ( ! file_exists( $dir ) ) {
                        wp_mkdir_p( $dir );
                        // محافظت از دسترسی مستقیم.
                        file_put_contents( $dir . '/.htaccess', "Deny from all\n" ); // phpcs:ignore
                        file_put_contents( $dir . '/index.html', '' ); // phpcs:ignore
                }
                if ( 'excel' === $type ) {
                        $type = 'xlsx';
                }
                // پسوند فایل همیشه از نقشه ثابت گرفته می‌شود (هرگز مثل «.excel» در نمی‌آید).
                $ext_map = array( 'json' => 'json', 'xlsx' => 'xlsx', 'sql' => 'sql', 'zip' => 'zip' );
                if ( ! isset( $ext_map[ $type ] ) ) {
                        $type = 'json';
                }
                $stamp = date_i18n( 'Ymd-His' );
                $path  = $dir . '/tpp-salary-backup-' . $type . '-' . $stamp . '.' . $ext_map[ $type ];

                switch ( $type ) {
                        case 'json':
                                $data = self::collect_json();
                                $bytes = file_put_contents( $path, wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
                                break;
                        case 'xlsx':
                                $xlsx = self::collect_excel();
                                $data = $xlsx->to_string();
                                if ( is_wp_error( $data ) ) {
                                        return $data;
                                }
                                $bytes = file_put_contents( $path, $data );
                                break;
                        case 'sql':
                                $data  = self::collect_sql( array( 'centers', 'banks', 'fields', 'records', 'employees' ) );
                                $bytes = file_put_contents( $path, $data );
                                break;
                        case 'zip':
                        default:
                                if ( ! class_exists( 'ZipArchive' ) ) {
                                        return new WP_Error( 'tpp_salary_zip_missing', 'ZipArchive فعال نیست' );
                                }
                                $zip = new ZipArchive();
                                if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
                                        return new WP_Error( 'tpp_salary_zip_open', 'ایجاد ZIP ناموفق' );
                                }
                                $root = 'tpp-salary-backup-' . $stamp . '/';

                                // راهنمای فارسی محتوا.
                                $zip->addFromString( $root . 'README.txt', self::zip_readme() );

                                /* JSON — کامل + دو فایل مجزای کارمندان/رکوردها */
                                $zip->addFromString( $root . 'json/full.json', wp_json_encode( self::collect_json(), JSON_UNESCAPED_UNICODE ) );
                                $zip->addFromString( $root . 'json/employees.json', wp_json_encode( self::collect_json_employees(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
                                $zip->addFromString( $root . 'json/records.json', wp_json_encode( self::collect_json_records(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );

                                /* اکسل — دو فایل مجزای کارمندان/رکوردها */
                                $ex = self::collect_excel_employees()->to_string();
                                if ( ! is_wp_error( $ex ) ) {
                                        $zip->addFromString( $root . 'excel/employees.xlsx', $ex );
                                }
                                $ex = self::collect_excel_records()->to_string();
                                if ( ! is_wp_error( $ex ) ) {
                                        $zip->addFromString( $root . 'excel/records.xlsx', $ex );
                                }

                                /* SQL — کامل + دو فایل مجزا */
                                $zip->addFromString( $root . 'sql/full.sql', self::collect_sql( array( 'centers', 'banks', 'fields', 'records', 'employees' ) ) );
                                $zip->addFromString( $root . 'sql/employees.sql', self::collect_sql( array( 'employees' ) ) );
                                $zip->addFromString( $root . 'sql/records.sql', self::collect_sql( array( 'records' ) ) );

                                /* فایل‌های خود پلاگین — کل پوشه افزونه بایگانی می‌شود (نسخه 1.5.0) */
                                self::zip_add_plugin_files( $zip, $root . 'plugin/' );

                                $zip->close();
                                $bytes = filesize( $path );
                                break;
                }
                if ( ! $bytes ) {
                        return new WP_Error( 'tpp_salary_backup_write', 'نوشتن فایل بکاپ ناموفق بود' );
                }
                global $wpdb;
                $wpdb->insert( // phpcs:ignore
                        $wpdb->prefix . 'tpp_salary_backups',
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
                $users = get_users( array( 'role' => 'tpp_salary_employee', 'fields' => 'ID', 'number' => -1 ) );
                $profiles = array();
                foreach ( $users as $uid ) {
                        $ud = get_userdata( $uid );
                        /* نسخه 1.7.6: user_email هم ذخیره می‌شود — یکی از کلیدهای تطبیق کارمند در نصب مقصد. */
                        $profiles[] = array(
                                'user_id'      => $uid,
                                'user_login'   => $ud ? $ud->user_login : '',
                                'display_name' => $ud ? $ud->display_name : '',
                                'user_email'   => $ud ? $ud->user_email : '',
                                'national_id'  => get_user_meta( $uid, 'tpp_salary_national_id', true ),
                                'mobile'       => get_user_meta( $uid, 'tpp_salary_mobile', true ),
                                'profile'      => tpp_salary_get_profile( $uid ),
                        );
                }
                return array(
                        'version'     => TPP_SALARY_VERSION,
                        'stamp'       => current_time( 'mysql' ),
                        /* نسخه 1.7.6: نشانی سایت مبدأ — صرفاً اطلاعاتی؛ در بازگردانی نادیده گرفته می‌شود. */
                        'site'        => array( 'url' => home_url(), 'stamp' => current_time( 'mysql' ) ),
                        'settings'    => tpp_salary_get_settings(),
                        'centers'     => (array) $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}tpp_salary_centers" ), // phpcs:ignore
                        'banks'       => (array) $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}tpp_salary_banks" ), // phpcs:ignore
                        'fields'      => (array) $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}tpp_salary_fields" ), // phpcs:ignore
                        'records'     => (array) $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}tpp_salary_records" ), // phpcs:ignore
                        'profiles'    => $profiles,
                );
        }

        /**
         * جمع‌آوری داده به صورت اکسل — دو شیت مجزای کارمندان + رکوردهای حقوق
         *
         * @return TppSalary_Xlsx_Writer
         */
        private static function collect_excel() {
                $xlsx = self::collect_excel_employees();
                self::fill_excel_records( $xlsx );
                $xlsx->freeze( 'A2' );
                return $xlsx;
        }

        /**
         * جمع‌آوری اکسل «فقط اطلاعات کارمندان» — نسخه 1.5.0
         *
         * @return TppSalary_Xlsx_Writer
         */
        private static function collect_excel_employees() {
                $xlsx = new TppSalary_Xlsx_Writer();

                // شیت کارمندان.
                $xlsx->add_sheet( 'کارمندان' );
                $headers = array( 'نام و نام خانوادگی', 'کد ملی', 'شماره همراه', 'تعداد فرزند', 'گروه اصلی', 'دستمزد روزانه مرجع', 'پایه سنوات', 'حق مسکن', 'حق بن', 'حق تأهل', 'مراکز' );
                foreach ( $headers as $i => $h ) {
                        $xlsx->set( 1, 1 + $i, $h, 'header' );
                }
                $users = tpp_salary_get_employees( null, true ); // نسخه 1.6.0: بکاپ شامل کارمندان قطع‌همکاری هم هست
                $row = 2;
                foreach ( $users as $u ) {
                        $p = tpp_salary_get_profile( $u->ID );
                        $cnames = array();
                        foreach ( $p['centers'] as $cid ) {
                                $c = tpp_salary_get_center( (int) $cid );
                                if ( $c ) {
                                        $cnames[] = $c->name;
                                }
                        }
                        $values = array(
                                $u->display_name,
                                get_user_meta( $u->ID, 'tpp_salary_national_id', true ),
                                get_user_meta( $u->ID, 'tpp_salary_mobile', true ),
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
                return $xlsx;
        }

        /**
         * جمع‌آوری اکسل «فقط رکوردهای حقوق ثبت‌شده» — نسخه 1.5.0
         *
         * @return TppSalary_Xlsx_Writer
         */
        private static function collect_excel_records() {
                $xlsx = new TppSalary_Xlsx_Writer();
                $xlsx->add_sheet( 'رکوردهای حقوق' );
                self::fill_excel_records( $xlsx );
                $xlsx->freeze( 'A2' );
                return $xlsx;
        }

        /**
         * درج شیت رکوردهای حقوق در یک Writer موجود (مشترک بین بکاپ کامل و مجزا)
         *
         * @param TppSalary_Xlsx_Writer $xlsx نویسنده.
         * @return void
         */
        private static function fill_excel_records( $xlsx ) {
                global $wpdb;
                $xlsx->add_sheet( 'رکوردهای حقوق' );
                $records = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}tpp_salary_records ORDER BY jyear, jmonth, center_id, id" ); // phpcs:ignore
                $fields  = tpp_salary_get_fields();
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
                        $c = tpp_salary_get_center( (int) $r->center_id );
                        $payload = tpp_salary_record_payload( $r );
                        $vals = array( (int) $r->jyear, TppSalary_Jalali::month_name( (int) $r->jmonth ), $c ? $c->name : '', $u ? $u->display_name : '' );
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
        }

        /* ---------------------------------------------------------------------
         * بکاپ SQL — نسخه 1.5.0
         * ------------------------------------------------------------------- */

        /**
         * ساخت فایل SQL — بخش‌های دلخواه: centers/banks/fields/records/employees
         *
         * خروجی یک Dump استاندارد MySQL است (CREATE TABLE + INSERT) که با
         * phpMyAdmin / Adminer / mysql CLI قابل بازگردانی است. بخش «employees»
         * شامل سطرهای کاربران کارمند در جدول users و متاهای افزونه در usermeta
         * است (بدون DROP برای جداول هسته وردپرس).
         *
         * @param array $sections بخش‌های درخواستی.
         * @return string
         */
        public static function collect_sql( $sections = array( 'centers', 'banks', 'fields', 'records', 'employees' ) ) {
                global $wpdb;
                $sections = (array) $sections;
                $stamp = current_time( 'mysql' );
                $out  = "-- tpp_Salary — بکاپ SQL (نسخه " . TPP_SALARY_VERSION . ")\n";
                $out .= "-- تاریخ: {$stamp}\n";
                $out .= "-- پیشوند جداول وردپرس: {$wpdb->prefix}\n";
                $out .= "-- راهنما: این فایل با phpMyAdmin/Adminer یا «mysql < file.sql» قابل بازگردانی است.\n";
                $out .= "-- توجه: اجرای این فایل محتوای جداول افزونه را با محتوای بکاپ جایگزین می‌کند (DROP/CREATE).\n\n";
                $out .= "SET NAMES utf8mb4;\n\n";

                if ( in_array( 'employees', $sections, true ) ) {
                        $out .= self::sql_section_employees();
                }
                $tables = array( 'centers', 'banks', 'fields', 'records' );
                foreach ( $tables as $t ) {
                        if ( in_array( $t, $sections, true ) ) {
                                $out .= self::sql_dump_table( $t );
                        }
                }
                return $out;
        }

        /**
         * Dump یک جدول افزونه (CREATE TABLE + INSERTها)
         *
         * @param string $name نام بدون پیشوند.
         * @return string
         */
        private static function sql_dump_table( $name ) {
                global $wpdb;
                $table = $wpdb->prefix . 'tpp_salary_' . $name;
                $out   = "\n-- ================= {$table} =================\n";
                $create = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N ); // phpcs:ignore
                if ( is_array( $create ) && ! empty( $create[1] ) ) {
                        $out .= "DROP TABLE IF EXISTS `{$table}`;\n";
                        $out .= $create[1] . ";\n";
                } else {
                        /* محیط‌های غیر MySQL (مثل شبیه‌ساز تست): ساختار با فعال‌سازی افزونه ساخته می‌شود. */
                        $out .= "-- ساختار جدول در دسترس نیست — با فعال‌سازی افزونه در نصب مقصد ساخته می‌شود.\n";
                }
                $rows = $wpdb->get_results( "SELECT * FROM `{$table}`", ARRAY_A ); // phpcs:ignore
                if ( empty( $rows ) ) {
                        $out .= "-- (بدون داده)\n";
                        return $out;
                }
                $cols = array_keys( $rows[0] );
                $col_sql = '`' . implode( '`, `', $cols ) . '`';
                $chunk = array();
                foreach ( $rows as $row ) {
                        $vals = array();
                        foreach ( $cols as $c ) {
                                $vals[] = self::sql_value( isset( $row[ $c ] ) ? $row[ $c ] : null );
                        }
                        $chunk[] = '(' . implode( ', ', $vals ) . ')';
                        if ( count( $chunk ) >= 50 ) {
                                $out .= "INSERT INTO `{$table}` ({$col_sql}) VALUES\n" . implode( ",\n", $chunk ) . ";\n";
                                $chunk = array();
                        }
                }
                if ( $chunk ) {
                        $out .= "INSERT INTO `{$table}` ({$col_sql}) VALUES\n" . implode( ",\n", $chunk ) . ";\n";
                }
                return $out;
        }

        /**
         * Dump اطلاعات کارمندان — سطرهای users + متاهای افزونه در usermeta
         *
         * @return string
         */
        private static function sql_section_employees() {
                global $wpdb;
                $ids = array();
                foreach ( (array) tpp_salary_get_employees( null, true ) as $u ) { // نسخه 1.6.0: بکاپ کامل
                        $ids[] = (int) $u->ID;
                }
                if ( empty( $ids ) ) {
                        return "\n-- ================= کارمندان =================\n-- (کارمندی ثبت نشده است)\n";
                }
                $in   = implode( ',', $ids );
                $out  = "\n-- ================= کارمندان ({$wpdb->users} + {$wpdb->usermeta}) =================\n";
                $out .= "-- توجه: برای جداول هسته وردپرس هیچ DROP صادر نمی‌شود.\n";

                $users = $wpdb->get_results( "SELECT ID, user_login, user_pass, user_nicename, user_email, user_url, user_registered, display_name FROM {$wpdb->users} WHERE ID IN ({$in})", ARRAY_A ); // phpcs:ignore
                if ( ! empty( $users ) ) {
                        $cols = array_keys( $users[0] );
                        $col_sql = '`' . implode( '`, `', $cols ) . '`';
                        foreach ( $users as $row ) {
                                $vals = array();
                                foreach ( $cols as $c ) {
                                        $vals[] = self::sql_value( isset( $row[ $c ] ) ? $row[ $c ] : null );
                                }
                                $out .= "INSERT INTO `{$wpdb->users}` ({$col_sql}) VALUES (" . implode( ', ', $vals ) . ")\n";
                                $out .= "ON DUPLICATE KEY UPDATE `user_login` = VALUES(`user_login`), `user_pass` = VALUES(`user_pass`), `user_email` = VALUES(`user_email`), `display_name` = VALUES(`display_name`);\n";
                        }
                }

                // متاهای افزونه برای همین کاربران.
                $metas = $wpdb->get_results( "SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE user_id IN ({$in})", ARRAY_A ); // phpcs:ignore
                $plugin_metas = array();
                foreach ( (array) $metas as $m ) {
                        if ( is_string( $m['meta_key'] ) && 0 === strpos( $m['meta_key'], 'tpp_salary_' ) ) {
                                $plugin_metas[] = $m;
                        }
                }
                if ( ! empty( $plugin_metas ) ) {
                        $out .= "\n-- متاهای افزونه (tpp_salary_*) — ابتدای محتوای قبلی همین کلیدها پاک می‌شود:\n";
                        $out .= "DELETE FROM `{$wpdb->usermeta}` WHERE user_id IN ({$in}) AND meta_key LIKE 'tpp\\_salary\\_%';\n";
                        foreach ( $plugin_metas as $m ) {
                                $out .= "INSERT INTO `{$wpdb->usermeta}` (`user_id`, `meta_key`, `meta_value`) VALUES (" . self::sql_value( $m['user_id'] ) . ', ' . self::sql_value( $m['meta_key'] ) . ', ' . self::sql_value( $m['meta_value'] ) . ");\n";
                        }
                }
                return $out;
        }

        /**
         * ایمن‌سازی مقدار برای درج در SQL
         *
         * @param string|int|float|null $v مقدار.
         * @return string
         */
        private static function sql_value( $v ) {
                if ( null === $v ) {
                        return 'NULL';
                }
                /* esc_sql هستهٔ وردپرس است؛ در محیط‌های بدون آن (مثل شبیه‌ساز تست) مسیر جایگزین. */
                if ( function_exists( 'esc_sql' ) ) {
                        return "'" . esc_sql( (string) $v ) . "'";
                }
                global $wpdb;
                if ( is_object( $wpdb ) && method_exists( $wpdb, '_real_escape' ) ) {
                        return "'" . $wpdb->_real_escape( (string) $v ) . "'";
                }
                return "'" . addslashes( (string) $v ) . "'";
        }

        /* ---------------------------------------------------------------------
         * بکاپ ZIP — فایل‌های مجزا + فایل‌های پلاگین — نسخه 1.5.0
         * ------------------------------------------------------------------- */

        /**
         * جمع‌آوری JSON «فقط اطلاعات کارمندان»
         *
         * @return array
         */
        private static function collect_json_employees() {
                $full = self::collect_json();
                return array(
                        'version' => isset( $full['version'] ) ? $full['version'] : TPP_SALARY_VERSION,
                        'stamp'   => isset( $full['stamp'] ) ? $full['stamp'] : current_time( 'mysql' ),
                        'kind'    => 'employees',
                        'centers' => isset( $full['centers'] ) ? $full['centers'] : array(),
                        'profiles'=> isset( $full['profiles'] ) ? $full['profiles'] : array(),
                );
        }

        /**
         * جمع‌آوری JSON «فقط رکوردهای حقوق ثبت‌شده»
         *
         * نسخه 1.7.6: مراکز و پروفایل‌ها هم داخل این فایل مجزا هستند تا
         * بازگردانی آن روی سرور/نصب دیگر هم کارمند و مرکز را بشناسد و
         * رکوردها به حساب‌های واقعی مقصد نگاشت شوند.
         *
         * @return array
         */
        private static function collect_json_records() {
                $full = self::collect_json();
                return array(
                        'version' => isset( $full['version'] ) ? $full['version'] : TPP_SALARY_VERSION,
                        'stamp'   => isset( $full['stamp'] ) ? $full['stamp'] : current_time( 'mysql' ),
                        'kind'    => 'records',
                        'centers' => isset( $full['centers'] ) ? $full['centers'] : array(),
                        'fields'  => isset( $full['fields'] ) ? $full['fields'] : array(),
                        'records' => isset( $full['records'] ) ? $full['records'] : array(),
                        'profiles'=> isset( $full['profiles'] ) ? $full['profiles'] : array(),
                );
        }

        /**
         * افزودن کل فایل‌های پوشه پلاگین به ZIP بکاپ
         *
         * @param ZipArchive $zip    شیء ZIP باز.
         * @param string     $prefix پیشوند مسیر داخلی (مثل tpp-salary-backup-.../plugin/).
         * @return int تعداد فایل‌های افزوده‌شده.
         */
        private static function zip_add_plugin_files( $zip, $prefix ) {
                $base = rtrim( TPP_SALARY_DIR, '/\\' );
                $skip = array( 'worklog.md', 'debug.log', '.git', '.svn' );
                $count = 0;
                try {
                        $it = new RecursiveIteratorIterator(
                                new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ),
                                RecursiveIteratorIterator::LEAVES_ONLY
                        );
                        foreach ( $it as $file ) {
                                if ( ! $file->isFile() || ! $file->isReadable() ) {
                                        continue;
                                }
                                if ( in_array( $file->getBasename(), $skip, true ) ) {
                                        continue;
                                }
                                $path  = $file->getPathname();
                                $local = $prefix . str_replace( '\\', '/', substr( $path, strlen( $base ) + 1 ) );
                                if ( $zip->addFile( $path, $local ) ) {
                                        $count++;
                                }
                        }
                } catch ( Exception $e ) {
                        /* فایل‌های داده قبلاً افزوده شده‌اند — کاتالوگ پلاگین حیاتی نیست. */
                }
                return $count;
        }

        /**
         * متن README داخل بکاپ ZIP
         *
         * @return string
         */
        private static function zip_readme() {
                return "بکاپ افزونه حقوق و دستمزد (tpp_Salary)\n"
                        . "========================================\n\n"
                        . "محتوای این بسته:\n\n"
                        . "  json/full.json        — کل داده‌ها (فایل بازگردانی اصلی)\n"
                        . "  json/employees.json   — فقط اطلاعات کارمندان (به صورت مجزا)\n"
                        . "  json/records.json     — فقط رکوردهای حقوق ثبت‌شده (به صورت مجزا)\n"
                        . "  excel/employees.xlsx  — کارمندان به صورت اکسل مجزا\n"
                        . "  excel/records.xlsx    — رکوردهای حقوق به صورت اکسل مجزا\n"
                        . "  sql/full.sql          — کل جداول افزونه + اطلاعات کارمندان (SQL)\n"
                        . "  sql/employees.sql     — فقط کارمندان (SQL)\n"
                        . "  sql/records.sql       — فقط رکوردهای حقوق (SQL)\n"
                        . "  plugin/               — فایل‌های خود افزونه (کل پوشه پلاگین)\n\n"
                        . "بازگردانی روی همین سایت یا هر سرور/دامنه دیگر (نسخه 1.7.6):\n"
                        . "  پیشخوان > حقوق و دستمزد > پشتیبان‌گیری > بازگردانی\n"
                        . "  همین فایل ZIP را مستقیم آپلود کنید (یا فایل json/full.json را جداگانه).\n"
                        . "  کارمندان (حساب‌های وردپرس) در نصب مقصد خودکار تطبیق داده می‌شوند و اگر\n"
                        . "  نباشند ساخته می‌شوند؛ رکوردها به شناسه کاربری مقصد متصل می‌شوند — بدون\n"
                        . "  نیاز به انتقال دستی کاربران یا هم‌خوانی شناسه‌ها.\n\n"
                        . "بازگردانی SQL (اختیاری — پیشرفته): با phpMyAdmin / Adminer یا دستور mysql.\n"
                        . "  توجه: نام جداول داخل فایل SQL با پیشوند سایت مبدأ ساخته شده است؛ اگر\n"
                        . "  پیشوند نصب مقصد متفاوت است، نام جداول را پیش از اجرا ویرایش کنید یا\n"
                        . "  از مسیر بازگردانی ZIP/JSON استفاده کنید که به پیشوند وابسته نیست.\n"
                        . "  (فایل‌های sql دارای DROP TABLE برای جداول افزونه هستند.)\n";
        }


        /**
         * پاکسازی بکاپ‌های قدیمی
         *
         * @return void
         */
        private static function cleanup() {
                global $wpdb;
                $keep = (int) tpp_salary_get_setting( 'retention', 24 );
                $old  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tpp_salary_backups ORDER BY created_at DESC LIMIT 1000 OFFSET %d", $keep ) ); // phpcs:ignore
                foreach ( $old as $row ) {
                        if ( $row->file_path && file_exists( $row->file_path ) ) {
                                unlink( $row->file_path ); // phpcs:ignore
                        }
                        $wpdb->delete( $wpdb->prefix . 'tpp_salary_backups', array( 'id' => $row->id ), array( '%d' ) ); // phpcs:ignore
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
                                'table'   => 'tpp_salary_centers',
                                'columns' => array( 'id', 'name', 'created_at' ),
                        ),
                        'banks'   => array(
                                'table'   => 'tpp_salary_banks',
                                'columns' => array( 'id', 'name', 'sort_order' ),
                        ),
                        // نسخه 1.4.1: ستون in_record به whitelist بازگردانی اضافه شد.
                        'fields'  => array(
                                'table'   => 'tpp_salary_fields',
                                'columns' => array( 'id', 'field_key', 'label', 'field_type', 'default_value', 'formula', 'options', 'is_profile', 'in_record', 'is_calculated', 'is_negative', 'allow_manual', 'show_in_payslip', 'sort_order', 'is_system', 'is_active' ),
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
         * بازگردانی از فایل بکاپ — نسخه 1.7.6 (بازنویسی کامل)
         *
         * درخواست کاربر: «بکاپ تهیه‌شده وقتی در یک سرور و دامنه دیگر تلاش کردم
         * بازگردانی کنم اصلا کار نکرد». سه ریشه که همگی در این بازنویسی رفع شدند:
         *
         *   ۱) همه بکاپ‌های خودکار (روزانه/هفتگی/ماهانه/سالانه) «ZIP» هستند ولی
         *      بازگردانی قبلی فقط JSON می‌پذیرفت → فایل ZIP بلافاصله رد می‌شد.
         *      اکنون ZIP بکاپ مستقیماً پذیرفته می‌شود (json/full.json از داخل آن
         *      خوانده می‌شود) و فایل‌های بخش مجزا (employees/records.json) هم.
         *
         *   ۲) کارمندان، کاربر وردپرس‌اند؛ در نصب مقصد هیچ حسابی ساخته نمی‌شد و
         *      پروفایل‌ها/رکوردها به شناسه‌های کاربری ناموجود اشاره می‌کردند →
         *      پس از بازگردانی «هیچ چیزی» دیده نمی‌شد. اکنون کارمندان در نصب
         *      مقصد تطبیق داده می‌شوند (ورود → کد ملی → ایمیل → شناسه + نقش) و
         *      اگر نبودند با همان منطق create_employee ساخته می‌شوند و شناسه‌های
         *      رکوردها به شناسه جدید نگاشت می‌شود.
         *
         *   ۳) تنظیمات به‌صورت کامل جایگزین می‌شد؛ اکنون با تنظیمات مقصد ادغام
         *      می‌شود (کلیدهای غایب در بکاپ از بین نمی‌روند) و پس از بازگردانی
         *      کش فیلدها باطل و زمان‌بندی بکاپ خودکار دوباره چیده می‌شود.
         *
         * @return void
         */
        public static function restore() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_salary_backup_restore' );
                if ( empty( $_FILES['restore_file'] ) || ! is_array( $_FILES['restore_file'] ) ) { // phpcs:ignore
                        wp_die( 'فایلی آپلود نشده است' );
                }
                $up  = $_FILES['restore_file']; // phpcs:ignore
                $err = isset( $up['error'] ) ? (int) $up['error'] : 0;
                if ( UPLOAD_ERR_OK !== $err ) {
                        wp_die( 'آپلود فایل ناموفق بود (کد خطا: ' . $err . ') — معمولاً یعنی حجم فایل از حد مجاز سرور بیشتر است. دوباره تلاش کنید.' );
                }
                /*
                 * نکته بحرانی (باگ ویندوز/XAMPP): مسیر tmp_name را «هرگز» از
                 * wp_unslash/sanitize عبور ندهید — stripslashes بک‌اسلش‌های مسیر
                 * ویندوز (C:\xampp\tmp\phpXXXX.tmp) را حذف می‌کند و مسیر به
                 * «C:xampptmpphpXXXX.tmp» تبدیل می‌شود → file_get_contents شکست.
                 * tmp_name توسط PHP تولید می‌شود و ورودی کاربر نیست؛ فقط اعتبارش را
                 * با is_uploaded_file بررسی می‌کنیم.
                 */
                $tmp_file = (string) ( isset( $up['tmp_name'] ) ? $up['tmp_name'] : '' ); // phpcs:ignore
                if ( '' === $tmp_file || ! is_uploaded_file( $tmp_file ) || ! is_readable( $tmp_file ) ) {
                        wp_die( 'فایل آپلودشده قابل خواندن نیست — دوباره تلاش کنید.' );
                }

                $loaded = self::load_backup_file( $tmp_file );
                if ( is_wp_error( $loaded ) ) {
                        wp_die( esc_html( $loaded->get_error_message() ) );
                }

                $applied = self::apply_restore( $loaded['data'] );
                if ( is_wp_error( $applied ) ) {
                        wp_die( esc_html( $applied->get_error_message() ) );
                }

                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-salary-backup', 'restored' => '1' ), admin_url( 'admin.php' ) ) );
                exit;
        }

        /**
         * خواندن فایل بکاپ از مسیر — JSON خام یا ZIP (بسته کامل/خودکار) — نسخه 1.7.6
         *
         * تشخیص نوع با «بایت‌های جادویی» انجام می‌شود نه پسوند — چون اکسل هم
         * ساختار ZIP دارد (هر دو با PK شروع می‌شوند) و پسوندها گمراه‌کننده‌اند.
         * ترتیب جست‌وجو در ZIP: json/full.json → json/employees.json → json/records.json
         * (با FL_NODIR تا نام پوشه ریشه — که مهر زمانی است — مهم نباشد).
         *
         * @param string $path مسیر فایل بکاپ.
         * @return array|WP_Error آرایه ( data => داده، source => منشأ ).
         */
        public static function load_backup_file( $path ) {
                $raw = file_get_contents( $path ); // phpcs:ignore
                if ( false === $raw || '' === $raw ) {
                        return new WP_Error( 'tpp_salary_restore_empty', 'محتوای فایل بکاپ خالی است یا خوانده نشد.' );
                }
                $head = substr( $raw, 0, 4 );

                if ( "PK\x03\x04" === $head || "PK\x05\x06" === $head ) {
                        /* ZIP — بسته کامل بکاپ (و یا به اشتباه، فایل اکسل بکاپ) */
                        if ( ! class_exists( 'ZipArchive' ) ) {
                                return new WP_Error( 'tpp_salary_zip_missing', 'افزونه PHP ZipArchive روی این سرور فعال نیست — بسته ZIP را در کامپیوتر باز کنید و فایل json/full.json داخل آن را جداگانه آپلود کنید.' );
                        }
                        $zip = new ZipArchive();
                        if ( true !== $zip->open( $path ) ) {
                                return new WP_Error( 'tpp_salary_zip_open', 'فایل ZIP باز نشد — احتمالاً ناقص دانلود یا انتقال یافته است. دوباره بکاپ را دانلود کنید.' );
                        }
                        $picked = null;
                        foreach ( array( 'json/full.json', 'json/employees.json', 'json/records.json' ) as $want ) {
                                $idx = $zip->locateName( basename( $want ), ZipArchive::FL_NODIR );
                                if ( false !== $idx ) {
                                        $picked = array( $want, $idx );
                                        break;
                                }
                        }
                        if ( null === $picked ) {
                                $names = array();
                                $total = min( (int) $zip->numFiles, 6 );
                                for ( $i = 0; $i < $total; $i++ ) {
                                        $names[] = (string) $zip->getNameIndex( $i );
                                }
                                $zip->close();
                                $hint = implode( '، ', array_filter( $names ) );
                                return new WP_Error( 'tpp_salary_restore_nojson', 'در این بسته ZIP، فایل بازگردانی (json/full.json) پیدا نشد. اگر فایل «اکسل» بکاپ را آپلود کرده‌اید بدانید که بازگردانی فقط با بسته ZIP بکاپ یا فایل JSON انجام می‌شود. محتوای ابتدای بسته: ' . $hint );
                        }
                        $json = $zip->getFromIndex( $picked[1] );
                        $zip->close();
                        $data = json_decode( (string) $json, true );
                        if ( ! is_array( $data ) || ( empty( $data['version'] ) && empty( $data['kind'] ) ) ) {
                                return new WP_Error( 'tpp_salary_restore_json', 'فایل JSON داخل بسته معتبر نیست (' . $picked[0] . ') — بسته ناقص یا خراب است.' );
                        }
                        return array( 'data' => $data, 'source' => 'zip:' . $picked[0] );
                }

                /* JSON خام — اولین نویسه معتبر باید { یا [ باشد */
                $first = substr( ltrim( $raw ), 0, 1 );
                if ( '{' !== $first && '[' !== $first ) {
                        return new WP_Error( 'tpp_salary_restore_type', 'فایل بکاپ معتبر نیست — بازگردانی با «بسته ZIP بکاپ» (شامل همه بکاپ‌های خودکار) یا «فایل بکاپ JSON» امکان‌پذیر است. فایل اکسل فقط برای مشاهده/آرشیو ساخته می‌شود.' );
                }
                $data = json_decode( $raw, true );
                if ( ! is_array( $data ) || ( empty( $data['version'] ) && empty( $data['kind'] ) ) ) {
                        return new WP_Error( 'tpp_salary_restore_json', 'فایل JSON قابل رمزگشایی نیست — فایل ناقص یا خراب است.' );
                }
                return array( 'data' => $data, 'source' => 'json' );
        }

        /**
         * اعمال داده بکاپ JSON روی دیتابیس — هستهٔ قابل‌تست بازگردانی (نسخه 1.7.6)
         *
         * اعتبارسنجی کامل + تراکنش: اگر هر سطری نامعتبر باشد، هیچ جدولی
         * خالی نمی‌شود و داده‌های فعلی دست‌نخورده می‌مانند (ROLLBACK).
         *
         * تازه در 1.7.6 — قابلیت انتقال بین سرور/دامنه:
         * - فایل‌های بخش مجزا (kind=employees/records) فقط بخش خودشان را لمس می‌کنند.
         * - کارمندان (کاربران وردپرس) در نصب مقصد تطبیق یا ساخته می‌شوند و
         *   user_id رکوردها به شناسه واقعی مقصد نگاشت می‌شود (restore_resolve_user).
         * - تنظیمات به‌جای جایگزینی کامل، با تنظیمات مقصد ادغام می‌شوند.
         * - پس از commit: باطل‌سازی کش فیلدها + بازچینی زمان‌بندی بکاپ خودکار.
         * - گزارش تفصیلی در self::$last_summary و transient کاربر جاری.
         *
         * @param array $data داده JSON رمزگشایی‌شده.
         * @return true|WP_Error
         */
        public static function apply_restore( $data ) {
                global $wpdb;

                if ( ! is_array( $data ) ) {
                        return new WP_Error( 'tpp_salary_restore_data', 'داده بکاپ نامعتبر است.' );
                }

                $all_sections = self::restore_sections();

                /*
                 * بازگردانی بخشی — فایل‌های مجزا فقط بخش خودشان را بازگردانی
                 * می‌کنند و جدول‌های غایب در فایل دست‌نخورده می‌مانند (قبل از
                 * 1.7.6 همه جدول‌ها بی‌قید و شرط خالی می‌شدند).
                 */
                $kind = isset( $data['kind'] ) ? (string) $data['kind'] : 'full';
                if ( 'employees' === $kind ) {
                        $section_keys = array( 'centers' );
                } elseif ( 'records' === $kind ) {
                        /* نسخه 1.7.6: مراکز هم بخش فایل records.json هستند (ارجاع center_id) */
                        $section_keys = array( 'centers', 'fields', 'records' );
                } else {
                        $section_keys = array_keys( $all_sections );
                }

                /*
                 * مرحله ۱ — اعتبارسنجی کامل پیش از هر تغییری:
                 * هر سطر باید فقط ستون‌های مجاز داشته باشد؛ در غیر این صورت
                 * بازگردانی متوقف می‌شود بدون اینکه جدولی خالی شود.
                 */
                foreach ( $section_keys as $key ) {
                        $def  = $all_sections[ $key ];
                        $rows = isset( $data[ $key ] ) ? $data[ $key ] : array();
                        if ( ! is_array( $rows ) ) {
                                return new WP_Error( 'tpp_salary_restore_section', 'ساختار فایل بکاپ نامعتبر است: بخش ' . $key );
                        }
                        foreach ( $rows as $row ) {
                                if ( ! is_array( $row ) ) {
                                        return new WP_Error( 'tpp_salary_restore_row', 'سطر نامعتبر در بخش ' . $key . ' — بازگردانی انجام نشد و داده‌های فعلی دست‌نخورده ماند.' );
                                }
                                foreach ( $row as $col => $val ) {
                                        if ( ! in_array( (string) $col, $def['columns'], true ) ) {
                                                return new WP_Error( 'tpp_salary_restore_col', 'ستون ناشناخته «' . $col . '» در بخش ' . $key . ' — بازگردانی انجام نشد و داده‌های فعلی دست‌نخورده ماند.' );
                                        }
                                }
                        }
                }

                /*
                 * گارد ایمنی — فایل «بدون هیچ داده‌ای» هرگز نباید همه جدول‌ها را
                 * خالی کند (مثل فایل خراب/ناقص یا JSON بیگانه که ساختار ما را دارد).
                 */
                if ( 'full' === $kind ) {
                        $present = 0;
                        foreach ( array_keys( $all_sections ) as $k ) {
                                if ( isset( $data[ $k ] ) && is_array( $data[ $k ] ) ) {
                                        $present++;
                                }
                        }
                        if ( 0 === $present ) {
                                return new WP_Error( 'tpp_salary_restore_emptydata', 'فایل بکاپ هیچ داده‌ای (مراکز/بانک‌ها/فیلدها/رکوردها) ندارد — بازگردانی انجام نشد تا داده‌های فعلی پاک نشوند.' );
                        }
                }

                self::$last_summary = array(
                        'kind'            => $kind,
                        'centers'         => 0,
                        'banks'           => 0,
                        'fields'          => 0,
                        'records'         => 0,
                        'users_matched'   => 0,
                        'users_created'   => 0,
                        'records_skipped' => 0,
                        'warnings'        => array(),
                );

                /*
                 * مرحله ۲ — تراکنش: پاک‌سازی و درج‌ها همه در یک تراکنش انجام می‌شود؛
                 * در هر خطا ROLLBACK کامل و داده‌های فعلی سالم می‌مانند.
                 * نکته: TRUNCATE در MySQL/MariaDB باعث COMMIT ضمنی می‌شود و قابل
                 * بازگشت نیست؛ بنابراین از DELETE استفاده می‌شود که DML است و
                 * داخل تراکنش قابل ROLLBACK است.
                 * نکته 1.7.6: ساخت/تطبیق حساب کارمندان هم روی همین اتصال دیتابیس
                 * انجام می‌شود؛ یعنی در خطا، حساب‌های تازه‌ساخت هم برمی‌گردند.
                 */
                $wpdb->query( 'START TRANSACTION' ); // phpcs:ignore

                /* تنظیمات — ادغام با تنظیمات فعلی (کلیدهای غایب در بکاپ حفظ می‌شوند) */
                $settings_raw = isset( $data['settings'] ) ? $data['settings'] : array();
                $settings_new = self::sanitize_restored_settings( $settings_raw );
                if ( ! empty( $settings_new ) ) {
                        update_option( 'tpp_salary_settings', self::merge_settings( $settings_new ), false );
                }

                $fail   = '';
                $map    = array(); // old user_id => array( id, created )
                $skip_records = ! in_array( 'records', $section_keys, true );

                foreach ( $section_keys as $key ) {
                        if ( 'records' === $key ) {
                                continue; /* رکوردها پس از ساخت/تطبیق کاربران درج می‌شوند */
                        }
                        $def  = $all_sections[ $key ];
                        $wpdb->query( "DELETE FROM {$wpdb->prefix}{$def['table']}" ); // phpcs:ignore
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
                                self::$last_summary[ $key ]++;
                        }
                }

                if ( $fail ) {
                        $wpdb->query( 'ROLLBACK' ); // phpcs:ignore
                        return new WP_Error( 'tpp_salary_restore_db', 'بازگردانی ناموفق بود و به حالت قبل برگشت (ROLLBACK). ' . $fail );
                }

                /*
                 * مرحله ۳ — کارمندان (ریشه اصلی ناکامی بازگردانی روی سرور دیگر):
                 * برای هر پروفایل بکاپ، کاربر متناسب در نصب مقصد پیدا یا ساخته
                 * می‌شود، سپس متاها و پروفایل روی همان کاربر نوشته می‌شود.
                 */
                $profiles = isset( $data['profiles'] ) && is_array( $data['profiles'] ) ? $data['profiles'] : array();
                foreach ( $profiles as $item ) {
                        if ( ! is_array( $item ) ) {
                                continue;
                        }
                        $old = (int) ( isset( $item['user_id'] ) ? $item['user_id'] : 0 );
                        $res = self::restore_resolve_user( $item );
                        if ( is_wp_error( $res ) ) {
                                $wpdb->query( 'ROLLBACK' ); // phpcs:ignore
                                return new WP_Error( 'tpp_salary_restore_user', 'بازگردانی کارمندان ناموفق بود و به حالت قبل برگشت (ROLLBACK). ' . $res->get_error_message() );
                        }
                        $map[ $old ] = $res;
                        if ( $res['created'] ) {
                                self::$last_summary['users_created']++;
                        } else {
                                self::$last_summary['users_matched']++;
                        }

                        $uid = (int) $res['id'];
                        if ( ! $uid ) {
                                continue;
                        }
                        if ( ! empty( $item['national_id'] ) ) {
                                update_user_meta( $uid, 'tpp_salary_national_id', TppSalary_Jalali::digits_en( sanitize_text_field( (string) $item['national_id'] ) ) );
                        }
                        if ( ! empty( $item['mobile'] ) ) {
                                update_user_meta( $uid, 'tpp_salary_mobile', TppSalary_Jalali::digits_en( sanitize_text_field( (string) $item['mobile'] ) ) );
                        }
                        if ( ! empty( $item['profile'] ) && is_array( $item['profile'] ) ) {
                                tpp_salary_save_profile( $uid, $item['profile'] );
                        }
                }

                /*
                 * مرحله ۴ — رکوردهای حقوق با نگاشت کارمند:
                 * رکوردی که کارمندش نه در بکاپ بود و نه در نصب مقصد پیدا شد،
                 * «رد» می‌شود (با شمارش و هشدار) تا یک سطر یتیم، کل بازگردانی
                 * را از کار نیندازد — بقیه سطرها سالم بازگردانی می‌شوند.
                 */
                if ( ! $skip_records ) {
                        $def  = $all_sections['records'];
                        $wpdb->query( "DELETE FROM {$wpdb->prefix}{$def['table']}" ); // phpcs:ignore
                        $rows = isset( $data['records'] ) ? $data['records'] : array();
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

                                $old_uid = (int) ( isset( $clean['user_id'] ) ? $clean['user_id'] : 0 );
                                $new_uid = self::restore_map_user( $map, $old_uid );
                                if ( ! $new_uid ) {
                                        self::$last_summary['records_skipped']++;
                                        self::$last_summary['warnings'][] = 'رکورد دوره ' . ( isset( $clean['jyear'] ) ? $clean['jyear'] : '?' ) . '/' . ( isset( $clean['jmonth'] ) ? $clean['jmonth'] : '?' ) . ' رد شد: کارمند آن (شناسه بکاپ ' . $old_uid . ') نه در بکاپ بود و نه در نصب مقصد پیدا شد.';
                                        continue;
                                }
                                $clean['user_id'] = $new_uid;

                                $res = $wpdb->insert( $wpdb->prefix . $def['table'], $clean ); // phpcs:ignore
                                if ( false === $res ) {
                                        $fail = 'خطای دیتابیس هنگام درج در ' . $def['table'] . ': ' . $wpdb->last_error;
                                        break;
                                }
                                self::$last_summary['records']++;
                        }
                }

                if ( $fail ) {
                        $wpdb->query( 'ROLLBACK' ); // phpcs:ignore
                        return new WP_Error( 'tpp_salary_restore_db', 'بازگردانی ناموفق بود و به حالت قبل برگشت (ROLLBACK). ' . $fail );
                }

                $wpdb->query( 'COMMIT' ); // phpcs:ignore

                /* کش فیلدها و زمان‌بندی بکاپ خودکار باید با داده تازه هم‌گام شود */
                if ( function_exists( 'tpp_salary_bump_fields_version' ) ) {
                        tpp_salary_bump_fields_version();
                }
                self::reschedule();

                /* گزارش تفصیلی برای صفحه پشتیبان‌گیری (کاربر جاری) */
                $cur = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
                if ( $cur && function_exists( 'set_transient' ) ) {
                        set_transient( 'tpp_salary_restore_summary_' . $cur, self::$last_summary, 600 );
                }
                return true;
        }

        /**
         * ادغام تنظیمات بازگردانی‌شده با تنظیمات فعلی — نسخه 1.7.6
         *
         * قبلاً کل option جایگزین می‌شد و کلیدهای غایب در بکاپ (مثل کلیدهای
         * تازه‌افزوده در نسخه‌های جدیدتر) از بین می‌رفتند. اکنون فقط کلیدهای
         * موجود در بکاپ بازنویسی می‌شوند (یک سطح تودرتو برای formulas/backup/defaults).
         *
         * @param array $restored تنظیمات پالایش‌شده بکاپ.
         * @return array
         */
        private static function merge_settings( $restored ) {
                $current = tpp_salary_get_settings();
                foreach ( (array) $restored as $k => $v ) {
                        if ( is_array( $v ) && isset( $current[ $k ] ) && is_array( $current[ $k ] ) ) {
                                $current[ $k ] = array_merge( $current[ $k ], $v );
                        } else {
                                $current[ $k ] = $v;
                        }
                }
                return $current;
        }

        /**
         * پیدا یا ساخت کاربر متناسب یک پروفایل بکاپ — نسخه 1.7.6
         *
         * ترتیب تطبیق (هم‌سان با منطق همگام‌سازی REST در class-tppsalary-api):
         *   ۱) نام کاربری بکاپ (login) — کاربران کارمند معمولاً login = کد ملی دارند؛
         *      اگر کاربر یافت‌شده مدیر سایت باشد، نقش به او داده نمی‌شود و از
         *      این تطبیق صرف‌نظر می‌شود (جلوگیری از تصاحب حساب مدیر).
         *   ۲) کد ملی در متاهای نصب مقصد (برای وقتی login مقصد متفاوت است)
         *   ۳) ایمیل پروفایل
         *   ۴) همان شناسه بکاپ، به شرط کارمند بودن (بازگردانی روی همان سایت)
         *   ۵) ساخت حساب جدید — با منطق create_employee افزونه (رمز تصادفی،
         *      نقش tpp_salary_employee، پسوند _1.. برای تکراری نبودن login)
         *
         * @param array $item پروفایل بکاپ.
         * @return array|WP_Error آرایه ( id => شناسه مقصد، created => ساخته شد؟ )
         */
        private static function restore_resolve_user( $item ) {
                $old      = (int) ( isset( $item['user_id'] ) ? $item['user_id'] : 0 );
                $login    = isset( $item['user_login'] ) ? sanitize_user( (string) $item['user_login'], true ) : '';
                $national = isset( $item['national_id'] ) ? TppSalary_Jalali::digits_en( sanitize_text_field( (string) $item['national_id'] ) ) : '';
                $email    = '';
                if ( isset( $item['user_email'] ) && is_email( (string) $item['user_email'] ) ) {
                        $email = (string) $item['user_email'];
                } elseif ( isset( $item['profile']['email'] ) && is_email( (string) $item['profile']['email'] ) ) {
                        $email = (string) $item['profile']['email'];
                }

                $ensure_role = function ( $uid ) {
                        $u = get_user_by( 'id', (int) $uid );
                        if ( $u && ! in_array( 'tpp_salary_employee', (array) $u->roles, true ) ) {
                                $u->add_role( 'tpp_salary_employee' );
                        }
                };

                /* ۱) تطبیق با نام کاربری بکاپ */
                if ( '' !== $login ) {
                        $uid = username_exists( $login );
                        if ( $uid ) {
                                $u = get_user_by( 'id', (int) $uid );
                                /* حساب مدیر را تصاحب نکن — به گام‌های بعدی می‌رویم */
                                $is_admin = $u && function_exists( 'user_can' ) ? user_can( $u, 'manage_options' ) : false;
                                if ( $u && ! $is_admin ) {
                                        $ensure_role( $uid );
                                        return array( 'id' => (int) $uid, 'created' => false );
                                }
                        }
                }

                /* ۲) تطبیق با کد ملی */
                if ( '' !== $national ) {
                        global $wpdb;
                        $uid = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'tpp_salary_national_id' AND meta_value = %s LIMIT 1", $national ) ); // phpcs:ignore
                        if ( $uid ) {
                                $ensure_role( $uid );
                                return array( 'id' => (int) $uid, 'created' => false );
                        }
                }

                /* ۳) تطبیق با ایمیل */
                if ( '' !== $email ) {
                        $uid = email_exists( $email );
                        if ( $uid ) {
                                $ensure_role( $uid );
                                return array( 'id' => (int) $uid, 'created' => false );
                        }
                }

                /* ۴) همان شناسه، کاربرِ موجود و کارمند (بازگردانی همان سایت) */
                if ( $old > 0 ) {
                        $u = get_user_by( 'id', $old );
                        if ( $u && in_array( 'tpp_salary_employee', (array) $u->roles, true ) ) {
                                return array( 'id' => $old, 'created' => false );
                        }
                }

                /* ۵) ساخت حساب جدید — منطبق بر create_employee افزونه */
                $username = preg_match( '/^\d{10}$/', $national ) ? $national : ( '' !== $login ? $login : (string) wp_rand( 1000000000, 9999999999 ) );
                $candidate = ( '' !== $login ) ? $login : sanitize_user( $username, true );
                if ( '' === $candidate ) {
                        $candidate = (string) wp_rand( 1000000000, 9999999999 );
                }
                $final_login = $candidate;
                $i = 0;
                while ( username_exists( $final_login ) ) {
                        $i++;
                        $final_login = $candidate . '_' . $i;
                }
                $name = isset( $item['display_name'] ) ? sanitize_text_field( (string) $item['display_name'] ) : '';
                if ( '' === $name && isset( $item['profile']['full_name'] ) ) {
                        $name = sanitize_text_field( (string) $item['profile']['full_name'] );
                }
                $uid = wp_insert_user(
                        array(
                                'user_login'   => $final_login,
                                'user_pass'    => wp_generate_password( 12, false ),
                                'display_name' => $name ? $name : $final_login,
                                'user_email'   => $email,
                                'role'         => 'tpp_salary_employee',
                        )
                );
                if ( is_wp_error( $uid ) || ! $uid ) {
                        return new WP_Error( 'tpp_salary_restore_user_create', 'ساخت حساب کارمند «' . ( $name ? $name : $final_login ) . '» ناموفق بود: ' . ( is_wp_error( $uid ) ? $uid->get_error_message() : 'خطای ناشناخته' ) );
                }
                return array( 'id' => (int) $uid, 'created' => true );
        }

        /**
         * نگاشت شناسه کارمند برای رکوردها — نسخه 1.7.6
         *
         * پروفایل‌ها قبلاً حل شده‌اند؛ برای رکوردی که پروفایلی در بکاپ ندارد
         * (کارمند حذف‌شده در مبدأ)، فقط تطبیق «همان شناسه + نقش کارمندی»
         * انجام می‌شود تا حساب دیگری به اشتباه کارمند فرض نشود.
         *
         * @param array $map     نقشه شناسه (مرجع).
         * @param int   $old_uid شناسه بکاپ.
         * @return int شناسه مقصد یا ۰ (نشانه رد شدن رکورد).
         */
        private static function restore_map_user( &$map, $old_uid ) {
                $old_uid = (int) $old_uid;
                if ( isset( $map[ $old_uid ] ) ) {
                        return (int) $map[ $old_uid ]['id'];
                }
                $u = $old_uid > 0 ? get_user_by( 'id', $old_uid ) : false;
                if ( $u && in_array( 'tpp_salary_employee', (array) $u->roles, true ) ) {
                        $map[ $old_uid ] = array( 'id' => (int) $u->ID, 'created' => false );
                        self::$last_summary['users_matched']++;
                        return (int) $u->ID;
                }
                $map[ $old_uid ] = array( 'id' => 0, 'created' => false );
                return 0;
        }
}
}
// TPP_SALARY GUARD END
