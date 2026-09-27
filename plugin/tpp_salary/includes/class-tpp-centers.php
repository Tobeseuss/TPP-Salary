<?php
/**
 * مدیریت مراکز (پروژه / کارگاه)
 *
 * @package TPP_Salary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class TPP_Centers
 */
if ( ! class_exists( 'TPP_Centers' ) ) {
	// TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TPP_Centers {

        /**
         * مقداردهی اولیه
         *
         * @return void
         */
        public static function init() {
                add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
                add_action( 'admin_post_tpp_add_center', array( __CLASS__, 'add' ) );
                add_action( 'admin_post_tpp_del_center', array( __CLASS__, 'delete' ) );
        }

        /**
         * منو
         *
         * @return void
         */
        public static function menu() {
                add_submenu_page(
                        'tpp-salary',
                        'مراکز (پروژه / کارگاه)',
                        'مراکز',
                        'tpp_manage_salary',
                        'tpp-centers',
                        array( __CLASS__, 'render' )
                );
        }

        /**
         * افزودن مرکز
         *
         * @return void
         */
        public static function add() {
                if ( ! tpp_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_add_center' );
                global $wpdb;
                $name = sanitize_text_field( wp_unslash( (isset($_POST['name'] )?$_POST['name'] : '' )) );
                if ( $name ) {
                        $wpdb->insert( $wpdb->prefix . 'tpp_centers', array( 'name' => $name, 'created_at' => current_time( 'mysql' ) ), array( '%s', '%s' ) ); // phpcs:ignore
                }
                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-centers', 'added' => '1' ), admin_url( 'admin.php' ) ) );
                exit;
        }

        /**
         * حذف مرکز
         *
         * @return void
         */
        public static function delete() {
                if ( ! tpp_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_del_center' );
                global $wpdb;
                $id = (int) ( (isset($_GET['id'] )?$_GET['id'] : 0 ));
                if ( $id ) {
                        $wpdb->delete( $wpdb->prefix . 'tpp_centers', array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore
                }
                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-centers', 'deleted' => '1' ), admin_url( 'admin.php' ) ) );
                exit;
        }

        /**
         * رندر صفحه
         *
         * @return void
         */
        public static function render() {
                if ( ! tpp_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                $centers = tpp_get_centers();
                ?>
                <div class="wrap tpp-wrap" dir="rtl">
                        <h1>مراکز (پروژه / کارگاه)</h1>
                        <?php if ( isset( $_GET['added'] ) ) : ?><div class="notice notice-success"><p>مرکز افزوده شد.</p></div><?php endif; ?>
                        <?php if ( isset( $_GET['deleted'] ) ) : ?><div class="notice notice-success"><p>مرکز حذف شد.</p></div><?php endif; ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:16px 0">
                                <?php wp_nonce_field( 'tpp_add_center' ); ?>
                                <input type="hidden" name="action" value="tpp_add_center">
                                <input type="text" name="name" placeholder="نام مرکز / پروژه / کارگاه" required>
                                <?php submit_button( 'افزودن مرکز', 'primary', 'submit', false ); ?>
                        </form>
                        <table class="widefat striped">
                                <thead><tr><th>#</th><th>نام مرکز</th><th>تاریخ ایجاد</th><th>عملیات</th></tr></thead>
                                <tbody>
                                <?php if ( empty( $centers ) ) : ?>
                                        <tr><td colspan="4">مرکزی تعریف نشده است.</td></tr>
                                <?php endif; ?>
                                <?php foreach ( $centers as $c ) : ?>
                                        <tr>
                                                <td><?php echo (int) $c->id; ?></td>
                                                <td><?php echo esc_html( $c->name ); ?></td>
                                                <td><?php echo esc_html( $c->created_at ); ?></td>
                                                <td>
                                                        <?php $url = wp_nonce_url( admin_url( 'admin-post.php?action=tpp_del_center&id=' . (int) $c->id ), 'tpp_del_center' ); ?>
                                                        <a class="button button-small" style="color:#b32d2e" href="<?php echo esc_url( $url ); ?>" onclick="return confirm('با حذف مرکز، ارتباط کارمندان و رکوردهای آن حفظ می‌شود اما دیگر در فهرست نمایش داده نمی‌شود. حذف شود؟')">حذف</a>
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
