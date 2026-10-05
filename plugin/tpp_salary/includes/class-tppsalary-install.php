<?php
/**
 * نصب و راه‌اندازی — جدول‌ها، نقش‌ها، مهاجرت داده از نسخه‌های قدیمی، مقادیر اولیه
 *
 * نسخه 1.3.2 — رفع 404 منوها (ترتیب ثبت والد/زیرمنو + جبران خودکار) و اصلاح کامل موتور اکسل:
 *   کلاس‌ها: TppSalary_* ، جدول‌ها: tpp_salary_* ، آپشن‌ها/هوک‌ها/متا: tpp_salary_*
 *   تا با هر افزونه دیگری (حتی با پیشوند tpp_) هیچ تداخلی رخ ندهد.
 *
 * @package TppSalary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/*
 * نشان نسخه بیلد — برای تشخیص فایل نصب قدیمی/ناهماهنگ.
 * اگر این ثابت با TPP_SALARY_VERSION برابر نباشد (یا تعریف نشده باشد)،
 * اجرای DDL جداول رد می‌شود تا خطای SQL مهلک هرگز رخ ندهد.
 */
if ( ! defined( 'TPP_SALARY_INSTALL_BUILD' ) ) {
        define( 'TPP_SALARY_INSTALL_BUILD', '1.7.9' );
}

/**
 * Class TppSalary_Install
 */
if ( ! class_exists( 'TppSalary_Install' ) ) {
        // TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TppSalary_Install {

        /**
         * ارتقای دیتابیس در زمان اجرا (بعد از plugins_loaded)
         *
         * @return void
         */
        public static function maybe_upgrade() {
                if ( get_option( 'tpp_salary_db_version' ) !== TPP_SALARY_DB_VERSION ) {
                        self::upgrade();
                }
        }

        /**
         * اجرای فعال‌سازی
         *
         * @return void
         */
        public static function activate() {
                self::upgrade();
        }

        /**
         * اجرای کامل نصب/ارتقا — idempotent (هر بار فقط کار باقی‌مانده را انجام می‌دهد)
         *
         * @return void
         */
        public static function upgrade() {
                self::create_tables();
                self::migrate_legacy();
                self::add_roles();
                self::migrate_roles();
                self::seed_data();
                self::migrate_field_flags();
                update_option( 'tpp_salary_db_version', TPP_SALARY_DB_VERSION );
                /*
                 * مهاجرت گزینه «حذف داده هنگام حذف افزونه» — نسخه 1.4.1:
                 * پیش‌فرض جدید «حفظ داده‌ها» است؛ حذف فقط با تیک صریح تنظیمات.
                 * گزینه قدیمی tpp_salary_keep_data جمع‌آوری می‌شود.
                 */
                if ( false !== get_option( 'tpp_salary_keep_data', false ) ) {
                        delete_option( 'tpp_salary_keep_data' );
                }
                /*
                 * فایل‌های نمونه داخل بسته با فیلدهای واقعی همین نصب بازتولید می‌شوند
                 * تا نمونه‌های همراه افزونه همیشه با ساختار فیلدهای کاربر هماهنگ و
                 * با موتور اکسل همین نسخه ساخته شده باشند (رفع «نمونه معیوب»).
                 */
                if ( class_exists( 'TppSalary_Samples' ) && is_callable( array( 'TppSalary_Samples', 'refresh' ) ) ) {
                        TppSalary_Samples::refresh();
                }
        }

        /**
         * ساخت جدول‌ها (نام‌های یکتا با پیشوند tpp_salary_)
         *
         * @return void
         */
        public static function create_tables() {
                global $wpdb;
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                $charset = $wpdb->get_charset_collate();

                $centers = $wpdb->prefix . 'tpp_salary_centers';
                $banks   = $wpdb->prefix . 'tpp_salary_banks';
                $fields  = $wpdb->prefix . 'tpp_salary_fields';
                $records = $wpdb->prefix . 'tpp_salary_records';
                $backups = $wpdb->prefix . 'tpp_salary_backups';

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
                        in_record tinyint(1) NOT NULL DEFAULT 1,
                        is_calculated tinyint(1) NOT NULL DEFAULT 0,
                        is_negative tinyint(1) NOT NULL DEFAULT 0,
                        allow_manual tinyint(1) NOT NULL DEFAULT 1,
                        show_in_payslip tinyint(1) NOT NULL DEFAULT 1,
                        sort_order int(11) NOT NULL DEFAULT 0,
                        is_system tinyint(1) NOT NULL DEFAULT 0,
                        is_active tinyint(1) NOT NULL DEFAULT 1,
                        PRIMARY KEY  (id),
                        UNIQUE KEY field_key (field_key),
                        KEY is_active (is_active)
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
                        PRIMARY KEY  (id),
                        KEY created_at (created_at)
                ) {$charset};" );
        }

        /**
         * وجود جدول
         *
         * @param string $table نام کامل جدول.
         * @return bool
         */
        private static function table_exists( $table ) {
                global $wpdb;
                $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore
                return ( $found === $table );
        }

        /**
         * مهاجرت داده از نسخه‌های قدیمی (≤1.2.0 با نام‌های عمومی tpp_)
         *
         * همه مراحل محافظت‌شده هستند:
         * - فقط اگر داده قدیمی واقعا متعلق به این افزونه باشد (تشخیص ساختار)
         * - فقط اگر مقصد خالی باشد (هرگز داده جدید را بازنویسی نمی‌کند)
         * - جدول‌ها/گزینه‌های قدیمی حذف نمی‌شوند (فقط کپی؛ حذف دستی در صورت تمایل)
         *
         * @return void
         */
        public static function migrate_legacy() {
                global $wpdb;

                // ۱) گزینه تنظیمات — فقط با اثر انگشت ساختار (کلید defaults).
                if ( false === get_option( 'tpp_salary_settings', false ) ) {
                        $old = get_option( 'tpp_settings', false );
                        if ( is_array( $old ) && isset( $old['defaults'] ) ) {
                                add_option( 'tpp_salary_settings', $old );
                        }
                }

                // ۲) جدول‌های تغییرنام‌یافته — کپی یک‌باره اگر مقصد خالی است.
                // ترتیب مهم است: centers/banks پیش از records (center_id به آن‌ها ارجاع دارد).
                self::migrate_table( 'tpp_centers', 'tpp_salary_centers', array( 'id', 'name', 'created_at' ) );
                self::migrate_table( 'tpp_banks', 'tpp_salary_banks', array( 'id', 'name', 'sort_order' ) );
                self::migrate_table( 'tpp_fields', 'tpp_salary_fields', array(
                        'id',
                        'field_key',
                        'label',
                        'field_type',
                        'default_value',
                        'formula',
                        'options',
                        'is_profile',
                        'is_calculated',
                        'is_negative',
                        'allow_manual',
                        'show_in_payslip',
                        'sort_order',
                        'is_system',
                        'is_active',
                ) );
                self::migrate_table( 'tpp_records', 'tpp_salary_records', array(
                        'id',
                        'user_id',
                        'center_id',
                        'jyear',
                        'jmonth',
                        'payload',
                        'gross',
                        'insurable',
                        'insurance_deduct',
                        'other_deductions',
                        'net',
                        'created_by',
                        'created_at',
                        'updated_at',
                ) );
                self::migrate_table( 'tpp_backups', 'tpp_salary_backups', array( 'id', 'backup_type', 'origin', 'file_path', 'file_size', 'created_at' ) );

                // ۳) متای پروفایل کاربران.
                self::migrate_meta( 'tpp_employee_profile', 'tpp_salary_employee_profile' );
                self::migrate_meta( 'tpp_national_id', 'tpp_salary_national_id' );
                self::migrate_meta( 'tpp_mobile', 'tpp_salary_mobile' );

                // ۴) پاک‌سازی کرون‌ها و ترنزینت‌های نام قدیمی.
                foreach ( array( 'daily', 'weekly', 'monthly', 'yearly' ) as $k ) {
                        wp_clear_scheduled_hook( 'tpp_backup_' . $k . '_event' );
                }
                $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_tpp\\_import\\_results\\_%' OR option_name LIKE '\\_transient\\_timeout\\_tpp\\_import\\_results\\_%' OR option_name LIKE '\\_transient\\_tpp\\_new\\_user\\_%' OR option_name LIKE '\\_transient\\_timeout\\_tpp\\_new\\_user\\_%'" ); // phpcs:ignore
        }

        /**
         * کپی سطرها از جدول قدیمی به جدید (فقط اگر ساختار منطبق و مقصد خالی باشد)
         *
         * @param string $old  نام جدول قدیمی (بدون پیشوند).
         * @param string $new  نام جدول جدید (بدون پیشوند).
         * @param array  $cols ستون‌های مورد انتظار.
         * @return void
         */
        private static function migrate_table( $old, $new, $cols ) {
                global $wpdb;
                $old_t = $wpdb->prefix . $old;
                $new_t = $wpdb->prefix . $new;

                if ( ! self::table_exists( $old_t ) || ! self::table_exists( $new_t ) ) {
                        return;
                }
                // اثر انگشت ساختار: همه ستون‌های مورد انتظار در جدول قدیمی موجود باشند.
                $existing = $wpdb->get_col( "SHOW COLUMNS FROM {$old_t}", 0 ); // phpcs:ignore
                if ( ! is_array( $existing ) ) {
                        return;
                }
                foreach ( $cols as $c ) {
                        if ( ! in_array( $c, $existing, true ) ) {
                                return; // جدول قدیمی متعلق به این افزونه نیست — دست نمی‌زنیم.
                        }
                }
                // فقط اگر مقصد خالی باشد.
                $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$new_t}" ); // phpcs:ignore
                if ( $count > 0 ) {
                        return;
                }
                $list = implode( ', ', $cols );
                $wpdb->query( "INSERT INTO {$new_t} ({$list}) SELECT {$list} FROM {$old_t}" ); // phpcs:ignore
        }

        /**
         * تغییر نام متای کاربران (فقط برای کاربرانی که کلید جدید ندارند)
         *
         * @param string $old نام متای قدیمی.
         * @param string $new نام متای جدید.
         * @return void
         */
        private static function migrate_meta( $old, $new ) {
                global $wpdb;
                $has_new = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s", $new ) ); // phpcs:ignore
                if ( ! empty( $has_new ) ) {
                        $in = implode( ',', array_map( 'intval', $has_new ) );
                        $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->usermeta} SET meta_key = %s WHERE meta_key = %s AND user_id NOT IN ({$in})", $new, $old ) ); // phpcs:ignore
                } else {
                        $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->usermeta} SET meta_key = %s WHERE meta_key = %s", $new, $old ) ); // phpcs:ignore
                }
        }

        /**
         * نقش‌های کاربری (نام‌های یکتا)
         *
         * @return void
         */
        public static function add_roles() {
                add_role(
                        'tpp_salary_employee',
                        'کارمند (حقوق و دستمزد)',
                        array(
                                'read'            => true,
                                'tpp_salary_view' => true,
                        )
                );
                add_role(
                        'tpp_salary_accountant',
                        'حسابدار (حقوق و دستمزد)',
                        array(
                                'read'               => true,
                                'tpp_salary_view'    => true,
                                'tpp_salary_manage'  => true,
                                'tpp_salary_import'  => true,
                        )
                );
                // اطمینان از داشتن دسترسی مدیرکل.
                $admin = get_role( 'administrator' );
                if ( $admin ) {
                        $admin->add_cap( 'tpp_salary_manage' );
                        $admin->add_cap( 'tpp_salary_import' );
                        $admin->add_cap( 'tpp_salary_view' );
                }
        }

        /**
         * مهاجرت کاربران از نقش‌های قدیمی به جدید + حذف نقش‌ها/دسترسی‌های قدیمی
         *
         * @return void
         */
        public static function migrate_roles() {
                $map = array(
                        'tpp_Employe'    => 'tpp_salary_employee',
                        'tpp_Accountant' => 'tpp_salary_accountant',
                );
                foreach ( $map as $old_role => $new_role ) {
                        if ( ! get_role( $old_role ) ) {
                                continue;
                        }
                        $users = get_users( array( 'role' => $old_role, 'number' => 5000 ) );
                        foreach ( $users as $u ) {
                                if ( $u instanceof WP_User ) {
                                        $u->add_role( $new_role );
                                        $u->remove_role( $old_role );
                                }
                        }
                        remove_role( $old_role );
                }
                // حذف دسترسی‌های قدیمی از مدیرکل (جایگزین‌ها در add_roles اضافه شدند).
                $admin = get_role( 'administrator' );
                if ( $admin ) {
                        foreach ( array( 'tpp_view_salary', 'tpp_manage_salary', 'tpp_import_salary' ) as $old_cap ) {
                                $admin->remove_cap( $old_cap );
                        }
                }
        }

        /**
         * داده‌های اولیه: فیلدها، تنظیمات پیش‌فرض
         *
         * @return void
         */
        public static function seed_data() {
                global $wpdb;

                // مقادیر پیش‌فرض تنظیمات.
                if ( ! get_option( 'tpp_salary_settings' ) ) {
                        add_option( 'tpp_salary_settings', tpp_salary_get_settings() );
                }

                // فیلدهای پروفایل کارمند.
                $profile_fields = array(
                        array( 'full_name', 'نام و نام خانوادگی', 5 ),
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
                        // فیلدهای فقط‌پروفایلی — در فرم ثبت حقوق نمایش داده نمی‌شوند (نسخه 1.4.1: انتقال عنوان شغلی/خودرو به پروفایل).
                        $profile_only = in_array( $pf[0], array( 'full_name', 'job_title', 'vehicle_type', 'vehicle_plate' ), true );
                        $all[ $pf[0] ] = array(
                                'label'      => $pf[1],
                                'type'       => ( in_array( $pf[0], array( 'insurance_group', 'job_title', 'vehicle_type', 'vehicle_plate', 'full_name' ), true ) ? 'text' : 'number' ),
                                'profile'    => 1,
                                'calculated' => 0,
                                'in_record'  => $profile_only ? 0 : 1,
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
                                        'in_record'       => isset( $def['in_record'] ) ? (int) $def['in_record'] : 1,
                                        'is_calculated'   => $def['calculated'],
                                        /* نسخه 1.4.1: مفهوم «ذاتاً منفی» حذف شد — علامت منفی عدد ملاک است. */
                                        'is_negative'     => 0,
                                        'allow_manual'    => 1,
                                        'show_in_payslip' => 1,
                                        'sort_order'      => $def['profile'] ? $def['sort'] : (int) $def['record_sort'],
                                        'is_system'       => 1,
                                        'is_active'       => 1,
                                ),
                                array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d' )
                        );
                }
        }

        /**
         * مهاجرت پرچم‌های فیلدها برای نصب‌های موجود (نسخه 1.4.1)
         *
         * - ستون in_record (فیلدهای فقط‌پروفایلی از فرم ثبت حقوق حذف می‌شوند)
         * - is_negative برای همه فیلدها صفر می‌شود (مفهوم «ذاتاً منفی» حذف شد؛
         *   علامت منفی خودِ مقدار ملاک نمایش قرمز است)
         *
         * @return void
         */
        private static function migrate_field_flags() {
                global $wpdb;
                $table = $wpdb->prefix . 'tpp_salary_fields';
                if ( ! self::table_exists( $table ) ) {
                        return;
                }
                $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$table}", 0 ); // phpcs:ignore
                if ( ! is_array( $cols ) || ! in_array( 'in_record', $cols, true ) ) {
                        return; // ستون هنوز ساخته نشده — اجرای دوباره upgrade آن را می‌سازد.
                }
                $profile_only = array( 'full_name', 'job_title', 'vehicle_type', 'vehicle_plate' );
                $in = "'" . implode( "','", $profile_only ) . "'";
                $wpdb->query( "UPDATE {$table} SET in_record = 0 WHERE field_key IN ({$in})" ); // phpcs:ignore
                $record_keys = array( 'insurance_group', 'daily_wage', 'work_days', 'base_salary', 'housing', 'food', 'seniority', 'marriage', 'children_count', 'child_allowance', 'commute', 'overtime_hours', 'overtime_pay', 'holiday_days', 'holiday_pay', 'absence_days', 'absence_penalty', 'work_deduction', 'other', 'gross', 'insurable', 'insurance_deduct', 'other_deductions', 'net' );
                $in2 = "'" . implode( "','", $record_keys ) . "'";
                $wpdb->query( "UPDATE {$table} SET in_record = 1 WHERE field_key IN ({$in2})" ); // phpcs:ignore
                // مفهوم «ذاتاً منفی» حذف شد — همه صفر.
                $wpdb->query( "UPDATE {$table} SET is_negative = 0" ); // phpcs:ignore
        }
}
}
// TPP_SALARY GUARD END
