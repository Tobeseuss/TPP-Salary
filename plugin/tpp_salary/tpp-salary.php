<?php
/**
 * Plugin Name: حقوق و دستمزد (tpp_Salary)
 * Plugin URI:  https://example.com/tpp-salary
 * Description: سامانه جامع حقوق و دستمزد — ثبت فیش حقوقی، مراکز (پروژه/کارگاه)، بانک‌ها، گزارش لیست حقوق و فیش بانکی، فیش حقوقی تکی و عمده (ZIP)، خروجی اکسل و PDF با لوگوی شرکت، بکاپ‌گیری خودکار و ورود گروهی اطلاعات.
 * Version:     1.7.7
 * Author:      TPP
 * Text Domain: tpp-salary
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 5.6
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/*
 * گارد ضد بارگذاری دوگانه — رفع خطای:
 * "Cannot declare class TppSalary_Xlsx_Writer, because the name is already in use"
 *
 * اگر دو نسخه از پوشه افزونه روی هاست موجود باشد (مثلاً tpp_salary و tpp_salary-1)
 * یا افزونه دوبار نصب شده باشد، فقط اولین نسخه بارگذاری می‌شود و نسخه دوم
 * بدون خطای مهلک، یک اعلان مدیریتی نمایش می‌دهد.
 */
if ( defined( 'TPP_SALARY_VERSION' ) ) {
        if ( ! function_exists( 'tpp_salary_duplicate_notice' ) ) {
                /**
                 * اعلان نصب دوگانه در پیشخوان.
                 *
                 * @return void
                 */
                function tpp_salary_duplicate_notice() {
                        if ( ! current_user_can( 'activate_plugins' ) ) {
                                return;
                        }
                        echo '<div class="notice notice-error"><p><strong>حقوق و دستمزد (tpp_Salary):</strong> ';
                        echo 'دو نسخه از این افزونه هم‌زمان بارگذاری شده است! برای جلوگیری از خطا، لطفاً در مسیر ';
                        echo '<code>wp-content/plugins</code> همه پوشه‌های افزونه به‌جز یکی را حذف کنید و فقط یک نسخه را فعال نگه دارید.';
                        echo '</p></div>';
                }
                add_action( 'admin_notices', 'tpp_salary_duplicate_notice' );
        }
        return; // نسخه دوم بارگذاری نمی‌شود.
}

define( 'TPP_SALARY_VERSION', '1.7.7' );
define( 'TPP_SALARY_DB_VERSION', '3' );
define( 'TPP_SALARY_FILE', __FILE__ );
define( 'TPP_SALARY_DIR', plugin_dir_path( __FILE__ ) );
define( 'TPP_SALARY_URL', plugin_dir_url( __FILE__ ) );

require_once TPP_SALARY_DIR . 'includes/class-tppsalary-compat.php';
require_once TPP_SALARY_DIR . 'includes/helpers.php';
require_once TPP_SALARY_DIR . 'includes/class-tppsalary-jalali.php';
require_once TPP_SALARY_DIR . 'includes/class-tppsalary-formula.php';
require_once TPP_SALARY_DIR . 'includes/class-tppsalary-xlsx-writer.php';
require_once TPP_SALARY_DIR . 'includes/class-tppsalary-xlsx-reader.php';
require_once TPP_SALARY_DIR . 'includes/class-tppsalary-install.php';
require_once TPP_SALARY_DIR . 'includes/class-tppsalary-samples.php';
require_once TPP_SALARY_DIR . 'includes/class-tppsalary-settings.php';
require_once TPP_SALARY_DIR . 'includes/class-tppsalary-centers.php';
require_once TPP_SALARY_DIR . 'includes/class-tppsalary-banks.php';
require_once TPP_SALARY_DIR . 'includes/class-tppsalary-employees.php';
require_once TPP_SALARY_DIR . 'includes/class-tppsalary-import.php';
require_once TPP_SALARY_DIR . 'includes/class-tppsalary-salary-pages.php';
require_once TPP_SALARY_DIR . 'includes/class-tppsalary-reports.php';
require_once TPP_SALARY_DIR . 'includes/class-tppsalary-backup.php';
require_once TPP_SALARY_DIR . 'includes/class-tppsalary-ajax.php';
require_once TPP_SALARY_DIR . 'includes/class-tppsalary-offline.php';
require_once TPP_SALARY_DIR . 'includes/class-tppsalary-api.php';
require_once TPP_SALARY_DIR . 'includes/class-tppsalary-frontend.php';

/*
 * تابع‌ها به‌صورت شرطی تعریف می‌شوند (گارد function_exists) تا PHP آن‌ها را در
 * زمان کامپایل early-bind نکند؛ در غیر این صورت بارگذاری دوم حتی با return اولیه
 * هم با خطای "Cannot redeclare function" متوقف می‌شد.
 */
if ( ! function_exists( 'tpp_salary_autoload' ) ) {
        /**
         * بارگذاری تنبل موتور PDF — فقط در اولین استفاده (سازگاری هاست‌های ضعیف)
         *
         * @param string $class نام کلاس.
         * @return void
         */
        function tpp_salary_autoload( $class ) {
                if ( 'TppSalary_PDF' === $class && file_exists( TPP_SALARY_DIR . 'includes/class-tppsalary-pdf.php' ) ) {
                        require_once TPP_SALARY_DIR . 'includes/class-tppsalary-pdf.php';
                }
        }
}
spl_autoload_register( 'tpp_salary_autoload' );

if ( ! function_exists( 'tpp_salary_init' ) ) {
        /**
         * راه‌اندازی اصلی — مقاوم در برابر فایل‌های ناهماهنگ
         *
         * اگر فایل‌های پوشه افزونه ترکیبی از نسخه‌های قدیمی/جدید باشد
         * (مثلاً بوت‌استرپ جدید + کلاس قدیمی بدون init)، به‌جای خطای مهلک
         * «Call to undefined method» فقط یک اعلان واضح برای نصب مجدد نمایش داده می‌شود.
         *
         * @return void
         */
        function tpp_salary_init() {
                /*
                 * اجرای جداول فقط با فایل نصب هم‌خوان — اگر class-tppsalary-install.php قدیمی باشد
                 * (مثلاً با جدول field_archives و ستون رزرو `values`)، DDL اجرا نمی‌شود
                 * تا خطای SQL مهلک هنگام فعال‌سازی رخ ندهد.
                 */
                $install_ok = defined( 'TPP_SALARY_INSTALL_BUILD' ) && TPP_SALARY_INSTALL_BUILD === TPP_SALARY_VERSION;
                $modules    = array();
                if ( $install_ok ) {
                        $modules[] = array( 'TppSalary_Install', 'maybe_upgrade' );
                }
                $modules = array_merge(
                        $modules,
                        array(
                                array( 'TppSalary_Settings', 'init' ),
                                array( 'TppSalary_Samples', 'init' ),
                                array( 'TppSalary_Centers', 'init' ),
                                array( 'TppSalary_Banks', 'init' ),
                                array( 'TppSalary_Employees', 'init' ),
                                array( 'TppSalary_Import', 'init' ),
                                array( 'TppSalary_Salary_Pages', 'init' ),
                                array( 'TppSalary_Reports', 'init' ),
                                array( 'TppSalary_Backup', 'init' ),
                                array( 'TppSalary_Ajax', 'init' ),
                                array( 'TppSalary_Offline', 'init' ),
                                array( 'TppSalary_Api', 'init' ),
                                array( 'TppSalary_Frontend', 'init' ),
                        )
                );
                $missing   = array();
                $activated = array();
                foreach ( $modules as $m ) {
                        if ( class_exists( $m[0] ) && is_callable( array( $m[0], $m[1] ) ) ) {
                                call_user_func( array( $m[0], $m[1] ) );
                                $activated[] = $m[0];
                        } else {
                                $missing[] = $m[0] . '::' . $m[1] . '()';
                        }
                }
                // برای تشخیص در تب «وضعیت سیستم» — فهرست ماژول‌هایی که واقعاً بالا آمده‌اند.
                $GLOBALS['tpp_salary_active_modules'] = $activated;
                if ( ! empty( $missing ) || ! $install_ok ) {
                                        $GLOBALS['tpp_salary_missing_modules'] = array_merge( $missing, $install_ok ? array() : array( 'TppSalary_Install (نسخه فایل نصب ناهماهنگ)' ) );
                        if ( ! function_exists( 'tpp_salary_mismatch_notice' ) ) {
                                /**
                                 * اعلان ناهماهنگی فایل‌های افزونه در پیشخوان.
                                 *
                                 * @return void
                                 */
                                function tpp_salary_mismatch_notice() {
                                        global $tpp_salary_missing_modules;
                                        echo '<div class="notice notice-error"><p><strong>حقوق و دستمزد (tpp_Salary):</strong> ';
                                        echo 'فایل‌های افزونه ناقص یا از نسخه‌های متفاوت است، یا افزونه دیگری با کلاس‌های هم‌نام (پیشوند tpp_) نصب است. ';
                                        if ( ! empty( $tpp_salary_missing_modules ) ) {
                                                echo 'ماژول‌های ناهماهنگ: <code>' . esc_html( implode( '، ', $tpp_salary_missing_modules ) ) . '</code> — ';
                                        }
                                        echo 'لطفاً: ';
                                        echo '۱) در <code>wp-content/plugins</code> پوشه افزونه را کامل حذف کنید؛ ';
                                        echo '۲) اگر افزونه یا کپی دیگری با پیشوند tpp_ نصب است (مثل نسخه قدیمی همین افزونه)، آن را هم غیرفعال و حذف کنید؛ ';
                                        echo '۳) نسخه جدید 1.5.0 را نصب کنید (کلاس‌ها و جدول‌های این نسخه یکتا هستند و با هیچ افزونه دیگری تداخل ندارند)؛ ۴) در صورت ادامه مشکل، Apache/سرور را یک‌بار ری‌استارت کنید (پاک‌شدن کش PHP/OPcache).';
                                        echo '</p></div>';
                                }
                                add_action( 'admin_notices', 'tpp_salary_mismatch_notice' );
                        }
                }
        }
}
add_action( 'plugins_loaded', 'tpp_salary_init' );

if ( ! function_exists( 'tpp_salary_activate' ) ) {
        /**
         * فعال‌سازی
         *
         * @return void
         */
        function tpp_salary_activate() {
                /*
                 * اجرای DDL فقط وقتی فایل نصب با این بوت‌استرپ هم‌خوان است.
                 * فایل نصب قدیمی (مثل نسخه میانی دارای ستون رزرو `values`) دیگر
                 * هرگز خطای SQL مهلک تولید نمی‌کند — فقط اعلان راهنما نمایش داده می‌شود.
                 */
                if ( ! defined( 'TPP_SALARY_INSTALL_BUILD' ) || TPP_SALARY_INSTALL_BUILD !== TPP_SALARY_VERSION ) {
                        if ( ! function_exists( 'tpp_salary_install_stale_notice' ) ) {
                                /**
                                 * اعلان فایل نصب ناهماهنگ هنگام فعال‌سازی.
                                 *
                                 * @return void
                                 */
                                function tpp_salary_install_stale_notice() {
                                        echo '<div class="notice notice-error"><p><strong>حقوق و دستمزد (tpp_Salary):</strong> ';
                                        echo 'فایل نصب افزونه (class-tppsalary-install.php) قدیمی و ناهماهنگ با بوت‌استرپ است؛ ساخت جدول‌ها رد شد تا خطای SQL رخ ندهد. ';
                                        echo 'لطفاً پوشه افزونه را <b>کاملاً</b> حذف و نسخه جدید را نصب کنید و سپس Apache را یک‌بار ری‌استارت کنید.</p></div>';
                                }
                                add_action( 'admin_notices', 'tpp_salary_install_stale_notice' );
                        }
                        return;
                }
                TppSalary_Install::activate();
        }
}
register_activation_hook( __FILE__, 'tpp_salary_activate' );

if ( ! function_exists( 'tpp_salary_deactivate' ) ) {
        /**
         * غیرفعال‌سازی — حذف زمان‌بندی‌های کرون
         *
         * @return void
         */
        function tpp_salary_deactivate() {
                wp_clear_scheduled_hook( 'tpp_salary_backup_daily_event' );
                wp_clear_scheduled_hook( 'tpp_salary_backup_weekly_event' );
                wp_clear_scheduled_hook( 'tpp_salary_backup_monthly_event' );
                wp_clear_scheduled_hook( 'tpp_salary_backup_yearly_event' );
                // نام‌های قدیمی (نسخه‌های ≤1.2.0) — برای ارتقا از نسخه‌های قبلی.
                wp_clear_scheduled_hook( 'tpp_backup_daily_event' );
                wp_clear_scheduled_hook( 'tpp_backup_weekly_event' );
                wp_clear_scheduled_hook( 'tpp_backup_monthly_event' );
                wp_clear_scheduled_hook( 'tpp_backup_yearly_event' );
        }
}
register_deactivation_hook( __FILE__, 'tpp_salary_deactivate' );

if ( ! function_exists( 'tpp_salary_load_textdomain' ) ) {
        /**
         * بارگذاری ترجمه‌ها
         *
         * @return void
         */
        function tpp_salary_load_textdomain() {
                load_plugin_textdomain( 'tpp-salary', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        }
}
add_action( 'init', 'tpp_salary_load_textdomain' );
