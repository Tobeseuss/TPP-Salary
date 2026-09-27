<?php
/**
 * نصب و راه‌اندازی — جدول‌ها، نقش‌ها، مقادیر اولیه
 *
 * @package TPP_Salary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/*
 * نشان نسخه بیلد — برای تشخیص فایل نصب قدیمی/ناهماهنگ.
 * اگر این ثابت با TPP_SALARY_VERSION برابر نباشد (یا تعریف نشده باشد)،
 * اجرای DDL جداول رد می‌شود تا خطای SQL مهلک (مثل ستون رزرو `values`
 * در فایل‌های میانی قدیمی) هرگز رخ ندهد.
 */
if ( ! defined( 'TPP_INSTALL_BUILD' ) ) {
        define( 'TPP_INSTALL_BUILD', '1.2.0' );
}

/**
 * Class TPP_Install
 */
if ( ! class_exists( 'TPP_Install' ) ) {
        // TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TPP_Install {

        /**
         * نسخه دیتابیس نصب‌شده
         *
         * @return void
         */
        public static function maybe_upgrade() {
                if ( get_option( 'tpp_db_version' ) !== TPP_SALARY_DB_VERSION ) {
                        self::create_tables();
                        update_option( 'tpp_db_version', TPP_SALARY_DB_VERSION );
                }
        }

        /**
         * اجرای فعال‌سازی
         *
         * @return void
         */
        public static function activate() {
                self::create_tables();
                self::add_roles();
                self::seed_data();
                update_option( 'tpp_db_version', TPP_SALARY_DB_VERSION );
        }

        /**
         * ساخت جدول‌ها
         *
         * @return void
         */
        public static function create_tables() {
                global $wpdb;
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                $charset = $wpdb->get_charset_collate();

                $centers = $wpdb->prefix . 'tpp_centers';
                $banks   = $wpdb->prefix . 'tpp_banks';
                $fields  = $wpdb->prefix . 'tpp_salary_fields';
                $records = $wpdb->prefix . 'tpp_salary_records';
                $backups = $wpdb->prefix . 'tpp_backups';

                dbDelta( "CREATE TABLE {$centers} (
                        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        name varchar(191) NOT NULL,
                        created_at datetime NULL,
                        PRIMARY KEY  (id),
                        UNIQUE KEY name (name)
                ) {$charset};" );

                dbDelta( "CREATE TABLE {$banks} (
                        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        name varchar(191) NOT NULL,
                        sort_order int(11) NOT NULL DEFAULT 0,
                        PRIMARY KEY  (id),
                        UNIQUE KEY name (name)
                ) {$charset};" );

                dbDelta( "CREATE TABLE {$fields} (
                        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        field_key varchar(64) NOT NULL,
                        label varchar(191) NOT NULL,
                        field_type varchar(20) NOT NULL DEFAULT 'number',
                        default_value text,
                        formula text,
                        options text,
                        is_profile tinyint(1) NOT NULL DEFAULT 0,
                        is_calculated tinyint(1) NOT NULL DEFAULT 0,
                        is_negative tinyint(1) NOT NULL DEFAULT 0,
                        allow_manual tinyint(1) NOT NULL DEFAULT 1,
                        show_in_payslip tinyint(1) NOT NULL DEFAULT 1,
                        sort_order int(11) NOT NULL DEFAULT 0,
                        is_system tinyint(1) NOT NULL DEFAULT 0,
                        is_active tinyint(1) NOT NULL DEFAULT 1,
                        PRIMARY KEY  (id),
                        UNIQUE KEY field_key (field_key)
                ) {$charset};" );

                dbDelta( "CREATE TABLE {$records} (
                        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        user_id bigint(20) unsigned NOT NULL,
                        center_id bigint(20) unsigned NOT NULL,
                        jyear smallint(6) NOT NULL,
                        jmonth tinyint(4) NOT NULL,
                        payload longtext,
                        gross bigint(20) NOT NULL DEFAULT 0,
                        insurable bigint(20) NOT NULL DEFAULT 0,
                        insurance_deduct bigint(20) NOT NULL DEFAULT 0,
                        other_deductions bigint(20) NOT NULL DEFAULT 0,
                        net bigint(20) NOT NULL DEFAULT 0,
                        created_by bigint(20) unsigned,
                        created_at datetime NULL,
                        updated_at datetime NULL,
                        PRIMARY KEY  (id),
                        UNIQUE KEY rec (user_id, center_id, jyear, jmonth),
                        KEY lookup (jyear, jmonth, center_id)
                ) {$charset};" );

                dbDelta( "CREATE TABLE {$backups} (
                        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        backup_type varchar(20) NOT NULL,
                        origin varchar(20) NOT NULL DEFAULT 'manual',
                        file_path text,
                        file_size bigint(20) NOT NULL DEFAULT 0,
                        created_at datetime NULL,
                        PRIMARY KEY  (id)
                ) {$charset};" );
        }

        /**
         * نقش‌های کاربری
         *
         * @return void
         */
        public static function add_roles() {
                add_role(
                        'tpp_Employe',
                        'کارمند (حقوق و دستمزد)',
                        array(
                                'read'            => true,
                                'tpp_view_salary' => true,
                        )
                );
                add_role(
                        'tpp_Accountant',
                        'حسابدار (حقوق و دستمزد)',
                        array(
                                'read'               => true,
                                'tpp_view_salary'    => true,
                                'tpp_manage_salary'  => true,
                                'tpp_import_salary'  => true,
                        )
                );
                // اطمینان از داشتن دسترسی مدیرکل.
                $admin = get_role( 'administrator' );
                if ( $admin ) {
                        $admin->add_cap( 'tpp_manage_salary' );
                        $admin->add_cap( 'tpp_import_salary' );
                        $admin->add_cap( 'tpp_view_salary' );
                }
        }

        /**
         * داده‌های اولیه: فیلدها، بانک‌های پیش‌فرض، فرمول‌ها
         *
         * @return void
         */
        public static function seed_data() {
                global $wpdb;

                // مقادیر پیش‌فرض تنظیمات.
                if ( ! get_option( 'tpp_settings' ) ) {
                        add_option( 'tpp_settings', tpp_get_settings() );
                }

                // فیلدهای پروفایل کارمند.
                $profile_fields = array(
                        array( 'daily_wage', 'دستمزد روزانه مرجع', 10 ),
                        array( 'seniority', 'پایه سنوات', 20 ),
                        array( 'overtime_rate', 'مبلغ هر ساعت اضافه کاری', 30 ),
                        array( 'holiday_rate', 'مبلغ تعطیل کاری', 40 ),
                        array( 'insurance_group', 'گروه اصلی بیمه', 50 ),
                        array( 'insurance_rate', 'نرخ درصد بیمه', 60 ),
                        array( 'insurable_default', 'حقوق مشمول بیمه', 70 ),
                        array( 'child_allowance_rate', 'حق اولاد هر فرزند', 80 ),
                        array( 'housing', 'حق مسکن', 90 ),
                        array( 'food', 'حق بن', 100 ),
                        array( 'marriage', 'حق تأهل', 110 ),
                        array( 'absence_rate', 'جریمه غیبت روزانه', 120 ),
                        array( 'job_title', 'عنوان شغلی', 130 ),
                        array( 'vehicle_type', 'نوع خودرو', 140 ),
                        array( 'vehicle_plate', 'پلاک خودرو', 150 ),
                        array( 'children_count', 'تعداد فرزند', 160 ),
                        array( 'commute', 'کمک هزینه ایاب و ذهاب', 170 ),
                );

                // فیلدهای صفحه ثبت حقوق (به ترتیب فرم مشخص‌شده در نیازمندی‌ها).
                $record_fields = array(
                        array( 'insurance_group', 'گروه اصلی بیمه', 0, 10 ),
                        array( 'daily_wage', 'دستمزد روزانه', 0, 20 ),
                        array( 'work_days', 'کارکرد (تعداد روز)', 0, 30 ),
                        array( 'base_salary', 'حقوق پایه', 1, 40 ),
                        array( 'housing', 'حق مسکن', 0, 50 ),
                        array( 'food', 'حق بن', 0, 60 ),
                        array( 'seniority', 'پایه سنوات', 0, 70 ),
                        array( 'marriage', 'حق تأهل', 0, 80 ),
                        array( 'children_count', 'تعداد فرزند', 0, 90 ),
                        array( 'child_allowance', 'حق اولاد', 1, 100 ),
                        array( 'commute', 'کمک هزینه ایاب و ذهاب', 0, 110 ),
                        array( 'overtime_hours', 'تعداد ساعات اضافه کاری', 0, 120 ),
                        array( 'overtime_pay', 'مبلغ اضافه کاری', 1, 130 ),
                        array( 'holiday_days', 'تعداد روز تعطیل کاری', 0, 140 ),
                        array( 'holiday_pay', 'مبلغ تعطیل کاری', 1, 150 ),
                        array( 'absence_days', 'تعداد روز غیبت', 0, 160 ),
                        array( 'absence_penalty', 'جریمه غیبت', 1, 170 ),
                        array( 'work_deduction', 'جریمه کسر از کار', 0, 180 ),
                        array( 'other', 'سایر', 0, 190 ),
                        array( 'gross', 'حقوق ناخالص', 1, 200 ),
                        array( 'insurable', 'حقوق مشمول بیمه', 1, 210 ),
                        array( 'insurance_deduct', 'کسر درصد بیمه', 1, 220 ),
                        array( 'other_deductions', 'کسورات دیگر', 0, 230 ),
                        array( 'net', 'حقوق خالص پرداختی', 1, 240 ),
                );

                $formulas = array(
                        'base_salary'      => '{daily_wage}*{work_days}',
                        'child_allowance'  => '{children_count}*{child_allowance_rate}',
                        'overtime_pay'     => '{overtime_hours}*{overtime_rate}',
                        'holiday_pay'      => '{holiday_days}*{holiday_rate}',
                        'absence_penalty'  => '-({absence_days}*{absence_rate})',
                        'insurance_deduct' => '-({insurable}*{insurance_rate}/100)',
                );

                $defaults = array(
                        'work_days'      => '31',
                        'insurance_rate' => '7',
                        'housing'        => '0',
                        'food'           => '0',
                        'marriage'       => '0',
                        'children_count' => '0',
                        'commute'        => '0',
                        'child_allowance_rate' => '0',
                        'overtime_rate'  => '0',
                        'holiday_rate'   => '0',
                        'absence_rate'   => '0',
                        'daily_wage'     => '0',
                        'seniority'      => '0',
                        'insurable_default' => '0',
                );

                $all = array();
                foreach ( $profile_fields as $pf ) {
                        $all[ $pf[0] ] = array(
                                'label'      => $pf[1],
                                'type'       => ( in_array( $pf[0], array( 'insurance_group', 'job_title', 'vehicle_type', 'vehicle_plate' ), true ) ? 'text' : 'number' ),
                                'profile'    => 1,
                                'calculated' => 0,
                                'sort'       => $pf[2],
                        );
                }
                foreach ( $record_fields as $rf ) {
                        if ( isset( $all[ $rf[0] ] ) ) {
                                // فیلد مشترک بین پروفایل و ثبت حقوق.
                                $all[ $rf[0] ]['in_record'] = 1;
                                $all[ $rf[0] ]['record_sort'] = $rf[3];
                                $all[ $rf[0] ]['record_label'] = $rf[1];
                                $all[ $rf[0] ]['calculated'] = $rf[2];
                                continue;
                        }
                        $all[ $rf[0] ] = array(
                                'label'      => $rf[1],
                                'type'       => 'number',
                                'profile'    => 0,
                                'in_record'  => 1,
                                'calculated' => $rf[2],
                                'record_sort' => $rf[3],
                        );
                }

                foreach ( $all as $key => $def ) {
                        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}tpp_salary_fields WHERE field_key = %s", $key ) ); // phpcs:ignore
                        if ( $exists ) {
                                continue;
                        }
                        $wpdb->insert( // phpcs:ignore
                                $wpdb->prefix . 'tpp_salary_fields',
                                array(
                                        'field_key'       => $key,
                                        'label'           => $def['label'],
                                        'field_type'      => $def['type'],
                                        'default_value'   => isset( $defaults[ $key ] ) ? $defaults[ $key ] : '',
                                        'formula'         => isset( $formulas[ $key ] ) ? $formulas[ $key ] : '',
                                        'is_profile'      => $def['profile'],
                                        'is_calculated'   => $def['calculated'],
                                        'is_negative'     => in_array( $key, array( 'absence_penalty', 'work_deduction', 'other_deductions' ), true ) ? 1 : 0,
                                        'allow_manual'    => 1,
                                        'show_in_payslip' => 1,
                                        'sort_order'      => $def['profile'] ? $def['sort'] : (int) $def['record_sort'],
                                        'is_system'       => 1,
                                        'is_active'       => 1,
                                ),
                                array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d' )
                        );
                }
        }
}
}
// TPP_SALARY GUARD END
