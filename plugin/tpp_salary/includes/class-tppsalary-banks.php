<?php
/**
 * مدیریت بانک‌ها — با همگام‌سازی خودکار حساب‌های بانکی کارمندان
 *
 * @package TppSalary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class TppSalary_Banks
 */
if ( ! class_exists( 'TppSalary_Banks' ) ) {
        // TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TppSalary_Banks {

        /**
         * مقداردهی اولیه
         *
         * @return void
         */
        public static function init() {
                add_action( 'admin_menu', array( __CLASS__, 'menu' ), 10 );
                add_action( 'admin_post_tpp_salary_add_bank', array( __CLASS__, 'add' ) );
                add_action( 'admin_post_tpp_salary_del_bank', array( __CLASS__, 'delete' ) );
        }

        /**
         * منو
         *
         * @return void
         */
        public static function menu() {
                add_submenu_page(
                        'tpp-salary',
                        'بانک‌ها',
                        'بانک‌ها',
                        'tpp_salary_manage',
                        'tpp-salary-banks',
                        array( __CLASS__, 'render' )
                );
        }

        /**
         * افزودن بانک — پس از افزودن، در پنل کارمندان فیلدهای حساب/شبا/کارت فعال می‌شود
         *
         * @return void
         */
        public static function add() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_salary_add_bank' );
                global $wpdb;
                $name = sanitize_text_field( wp_unslash( (isset($_POST['name'] )?$_POST['name'] : '' )) );
                /*
                 * باگ رفع‌شده 1.4.1: ستون created_at در جدول بانک‌ها وجود ندارد
                 * (DDL فقط id/name/sort_order است) — درج ستون ناموجود باعث می‌شد
                 * wpdb->insert بی‌سروصدا false برگرداند و «هیچ بانکی اضافه نشود».
                 */
                $inserted = false;
                if ( $name ) {
                        $inserted = (bool) $wpdb->insert( $wpdb->prefix . 'tpp_salary_banks', array( 'name' => $name ), array( '%s' ) ); // phpcs:ignore
                }
                $args = array( 'page' => 'tpp-salary-banks' );
                if ( $inserted ) {
                        $args['added'] = '1';
                } else {
                        // معمولاً به‌دلیل UNIQUE بودن نام بانک (نام تکراری).
                        $args['bankerr'] = '1';
                }
                wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
                exit;
        }

        /**
         * حذف بانک — اطلاعات حساب بانکی مربوطه از پروفایل همه کارمندان حذف می‌شود
         *
         * @return void
         */
        public static function delete() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_salary_del_bank' );
                global $wpdb;
                $id = (int) ( (isset($_GET['id'] )?$_GET['id'] : 0 ));
                if ( $id ) {
                        $wpdb->delete( $wpdb->prefix . 'tpp_salary_banks', array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore
                        // حذف اطلاعات حساب این بانک از پروفایل همه کاربران.
                        $users = get_users( array( 'role' => 'tpp_salary_employee', 'fields' => 'ID', 'number' => -1 ) );
                        foreach ( $users as $uid ) {
                                $profile = tpp_salary_get_profile( $uid );
                                if ( isset( $profile['bank_accounts'][ $id ] ) ) {
                                        unset( $profile['bank_accounts'][ $id ] );
                                        tpp_salary_save_profile( $uid, $profile );
                                }
                        }
                }
                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-salary-banks', 'deleted' => '1' ), admin_url( 'admin.php' ) ) );
                exit;
        }

        /**
         * رندر
         *
         * @return void
         */
        public static function render() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                $banks = tpp_salary_get_banks();
                ?>
                <div class="wrap tpp-wrap" dir="rtl">
                        <h1>بانک‌ها</h1>
                        <p class="description">با افزودن هر بانک، در پنل کاربری و پروفایل کارمندان امکان ثبت شماره حساب، شبا و کارت برای آن بانک فعال می‌شود. با حذف بانک، اطلاعات حساب مربوط به آن از پروفایل همه کارمندان حذف می‌شود.</p>
                        <?php if ( isset( $_GET['added'] ) ) : ?><div class="notice notice-success"><p>بانک افزوده شد.</p></div><?php endif; ?>
                        <?php if ( isset( $_GET['bankerr'] ) ) : ?><div class="notice notice-error"><p>افزودن بانک انجام نشد — احتمالاً بانکی با همین نام قبلاً ثبت شده است.</p></div><?php endif; ?>
                        <?php if ( isset( $_GET['deleted'] ) ) : ?><div class="notice notice-success"><p>بانک و اطلاعات حساب‌های مرتبط حذف شد.</p></div><?php endif; ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:16px 0">
                                <?php wp_nonce_field( 'tpp_salary_add_bank' ); ?>
                                <input type="hidden" name="action" value="tpp_salary_add_bank">
                                <input type="text" name="name" placeholder="نام بانک" required>
                                <?php submit_button( 'افزودن بانک', 'primary', 'submit', false ); ?>
                        </form>
                        <table class="widefat striped">
                                <thead><tr><th>#</th><th>نام بانک</th><th>عملیات</th></tr></thead>
                                <tbody>
                                <?php if ( empty( $banks ) ) : ?>
                                        <tr><td colspan="3">بانکی تعریف نشده است.</td></tr>
                                <?php endif; ?>
                                <?php foreach ( $banks as $b ) : ?>
                                        <tr>
                                                <td><?php echo (int) $b->id; ?></td>
                                                <td><?php echo esc_html( $b->name ); ?></td>
                                                <td>
                                                        <?php $url = wp_nonce_url( admin_url( 'admin-post.php?action=tpp_salary_del_bank&id=' . (int) $b->id ), 'tpp_salary_del_bank' ); ?>
                                                        <a class="button button-small" style="color:#b32d2e" href="<?php echo esc_url( $url ); ?>" onclick="return confirm('با حذف بانک، اطلاعات حساب بانکی کارمندان در این بانک نیز حذف می‌شود. مطمئن هستید؟')">حذف</a>
                                                </td>
                                        </tr>
                                <?php endforeach; ?>
                                </tbody>
                        </table>
                </div>
                <?php
        }
}
}
// TPP_SALARY GUARD END
