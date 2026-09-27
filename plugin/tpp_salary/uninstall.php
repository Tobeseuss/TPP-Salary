<?php
/**
 * حذف پلاگین — مدیریت داده‌ها بر اساس تنظیمات
 *
 * نسخه 1.4.1 — رفتار جدید: «پیش‌فرض، حفظ اطلاعات» است. با حذف افزونه،
 * جدول‌ها/تنظیمات/متای پروفایل کارمندان حذف نمی‌شوند و پس از نصب مجدد
 * در دسترس می‌مانند. فقط وقتی در تنظیمات (عمومی و پیش‌فرض‌ها) گزینه
 * «حذف کامل اطلاعات هنگام حذف افزونه» فعال شده باشد، همه داده‌ها
 * (جدول‌ها، تنظیمات، متا، فایل‌های بکاپ) به‌طور کامل حذف می‌شوند.
 * نقش‌ها/دسترسی‌ها/کرون‌ها/ترنزینت‌ها همیشه پاک‌سازی می‌شوند.
 *
 * @package TppSalary
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
        exit;
}

global $wpdb;

$delete_data = ( '1' === get_option( 'tpp_salary_delete_data', '0' ) );

if ( $delete_data ) {
        // حذف جدول‌ها (نام‌های یکتای نسخه 1.3.0+).
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tpp_salary_records" ); // phpcs:ignore
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tpp_salary_fields" ); // phpcs:ignore
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tpp_salary_banks" ); // phpcs:ignore
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tpp_salary_centers" ); // phpcs:ignore
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tpp_salary_backups" ); // phpcs:ignore

        // جدول‌های نام قدیمی (نسخه‌های ≤1.2.0) — اگر ارتقا داده بودید.
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tpp_records" ); // phpcs:ignore
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tpp_fields" ); // phpcs:ignore
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tpp_banks" ); // phpcs:ignore
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tpp_centers" ); // phpcs:ignore
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}tpp_backups" ); // phpcs:ignore

        delete_option( 'tpp_salary_settings' );
        delete_option( 'tpp_salary_db_version' );
        delete_option( 'tpp_salary_delete_data' );
        // نام قدیمی (نسخه‌های ≤1.4.0).
        delete_option( 'tpp_salary_keep_data' );
        // نام‌های قدیمی.
        delete_option( 'tpp_settings' );
        delete_option( 'tpp_db_version' );

        // حذف متای پروفایل کاربران (هر دو نسل نام).
        $wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('tpp_salary_employee_profile','tpp_salary_national_id','tpp_salary_mobile','tpp_employee_profile','tpp_national_id','tpp_mobile')" ); // phpcs:ignore
}

// پاک‌سازی زمان‌بندی‌های بکاپ (جدید + قدیمی).
wp_clear_scheduled_hook( 'tpp_salary_backup_daily_event' );
wp_clear_scheduled_hook( 'tpp_salary_backup_weekly_event' );
wp_clear_scheduled_hook( 'tpp_salary_backup_monthly_event' );
wp_clear_scheduled_hook( 'tpp_salary_backup_yearly_event' );
wp_clear_scheduled_hook( 'tpp_backup_daily_event' );
wp_clear_scheduled_hook( 'tpp_backup_weekly_event' );
wp_clear_scheduled_hook( 'tpp_backup_monthly_event' );
wp_clear_scheduled_hook( 'tpp_backup_yearly_event' );

// پاک‌سازی ترنزینت‌های پلاگین (هر دو نسل نام).
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_tpp\\_import\\_results\\_%' OR option_name LIKE '\\_transient\\_timeout\\_tpp\\_import\\_results\\_%' OR option_name LIKE '\\_transient\\_tpp\\_new\\_user\\_%' OR option_name LIKE '\\_transient\\_timeout\\_tpp\\_new\\_user\\_%' OR option_name LIKE '\\_transient\\_tpp\\_salary\\_%' OR option_name LIKE '\\_transient\\_timeout\\_tpp\\_salary\\_%'" ); // phpcs:ignore

// حذف نقش‌ها (جدید + قدیمی).
remove_role( 'tpp_salary_employee' );
remove_role( 'tpp_salary_accountant' );
remove_role( 'tpp_Employe' );
remove_role( 'tpp_Accountant' );

// بازیابی دسترسی‌های اعطاشده به مدیرکل (جدید + قدیمی).
$admin = get_role( 'administrator' );
if ( $admin ) {
        $admin->remove_cap( 'tpp_salary_manage' );
        $admin->remove_cap( 'tpp_salary_import' );
        $admin->remove_cap( 'tpp_salary_view' );
        $admin->remove_cap( 'tpp_manage_salary' );
        $admin->remove_cap( 'tpp_import_salary' );
        $admin->remove_cap( 'tpp_view_salary' );
}
