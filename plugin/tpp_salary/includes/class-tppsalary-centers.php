<?php
/**
 * مدیریت مراکز (پروژه / کارگاه)
 *
 * @package TppSalary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class TppSalary_Centers
 */
if ( ! class_exists( 'TppSalary_Centers' ) ) {
        // TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TppSalary_Centers {

        /**
         * مقداردهی اولیه
         *
         * @return void
         */
        public static function init() {
                add_action( 'admin_menu', array( __CLASS__, 'menu' ), 10 );
                add_action( 'admin_post_tpp_salary_add_center', array( __CLASS__, 'add' ) );
                add_action( 'admin_post_tpp_salary_del_center', array( __CLASS__, 'delete' ) );
                /* نسخه 1.6.2: حذف گروهی مراکز */
                add_action( 'admin_post_tpp_salary_del_center_bulk', array( __CLASS__, 'delete_bulk' ) );
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
                        'tpp_salary_manage',
                        'tpp-salary-centers',
                        array( __CLASS__, 'render' )
                );
        }

        /**
         * افزودن مرکز
         *
         * @return void
         */
        public static function add() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_salary_add_center' );
                global $wpdb;
                $name = sanitize_text_field( wp_unslash( (isset($_POST['name'] )?$_POST['name'] : '' )) );
                if ( $name ) {
                        $wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => $name, 'created_at' => current_time( 'mysql' ) ), array( '%s', '%s' ) ); // phpcs:ignore
                }
                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-salary-centers', 'added' => '1' ), admin_url( 'admin.php' ) ) );
                exit;
        }

        /**
         * حذف مرکز (تکی)
         *
         * @return void
         */
        public static function delete() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_salary_del_center' );
                global $wpdb;
                $id = (int) ( (isset($_GET['id'] )?$_GET['id'] : 0 ));
                if ( $id ) {
                        $wpdb->delete( $wpdb->prefix . 'tpp_salary_centers', array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore
                }
                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-salary-centers', 'deleted' => $id ? '1' : '0' ), admin_url( 'admin.php' ) ) );
                exit;
        }

        /**
         * حذف گروهی مراکز — نسخه 1.6.2
         *
         * @return void
         */
        public static function delete_bulk() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_salary_del_center_bulk' );
                $ids = tpp_salary_ids_from_request( ( isset( $_POST['ids'] ) ? $_POST['ids'] : array() ) );
                self::bulk_delete_centers( $ids );
                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-salary-centers', 'deleted' => count( $ids ) ), admin_url( 'admin.php' ) ) );
                exit;
        }

        /**
         * هسته حذف گروهی مراکز (قابل‌تست) — نسخه 1.6.2
         *
         * مانند حذف تکی: ارتباط کارمندان و رکوردها حفظ می‌شود و فقط
         * خود مرکز از فهرست حذف می‌گردد.
         *
         * @param int[] $ids شناسه‌های مراکز.
         * @return int تعداد حذف‌شده.
         */
        public static function bulk_delete_centers( $ids ) {
                global $wpdb;
                $ids = tpp_salary_ids_from_request( $ids );
                if ( empty( $ids ) ) {
                        return 0;
                }
                $table = $wpdb->prefix . 'tpp_salary_centers';
                $ph    = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
                return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$ph})", $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
        }

        /**
         * رندر صفحه — نسخه 1.6.2: جستجو + صفحه‌بندی + حذف گروهی
         *
         * @return void
         */
        public static function render() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                /* جستجوی نام مرکز (یا شناسه عددی) — نتیجه جستجو صفحه‌بندی می‌شود. */
                $search  = sanitize_text_field( wp_unslash( ( isset( $_GET['s'] ) ? $_GET['s'] : '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $centers = tpp_salary_get_centers();
                if ( $search ) {
                        $search_en = TppSalary_Jalali::digits_en( $search );
                        $centers   = array_values(
                                array_filter(
                                        $centers,
                                        function ( $c ) use ( $search, $search_en ) {
                                                $hay = $c->name . ' ' . (int) $c->id;
                                                return false !== mb_stripos( $hay, $search ) || ( $search_en && false !== mb_stripos( $hay, $search_en ) );
                                        }
                                )
                        );
                }
                /* صفحه‌بندی نتایج جستجو — لینک صفحات پارامتر s را حفظ می‌کنند. */
                $total = count( $centers );
                $per   = tpp_salary_list_per_page();
                $pages = max( 1, (int) ceil( $total / $per ) );
                $paged = min( tpp_salary_current_paged(), $pages );
                $centers = array_slice( $centers, ( $paged - 1 ) * $per, $per );
                ?>
                <div class="wrap tpp-wrap" dir="rtl">
                        <h1>مراکز (پروژه / کارگاه)</h1>
                        <?php if ( isset( $_GET['added'] ) ) : ?><div class="notice notice-success"><p>مرکز افزوده شد.</p></div><?php endif; ?>
                        <?php
                        /* نسخه 1.6.2: اعلان تعداد حذف‌شده (تکی/گروهی). */
                        $del_count = isset( $_GET['deleted'] ) ? absint( $_GET['deleted'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        if ( $del_count ) :
                                ?>
                                <div class="notice notice-success"><p><?php echo 1 === $del_count ? 'مرکز حذف شد.' : esc_html( TppSalary_Jalali::digits_fa( $del_count ) ) . ' مرکز حذف شد.'; ?></p></div>
                        <?php endif; ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:16px 0">
                                <?php wp_nonce_field( 'tpp_salary_add_center' ); ?>
                                <input type="hidden" name="action" value="tpp_salary_add_center">
                                <input type="text" name="name" placeholder="نام مرکز / پروژه / کارگاه" required>
                                <?php submit_button( 'افزودن مرکز', 'primary', 'submit', false ); ?>
                        </form>
                        <form method="get" style="margin:12px 0">
                                <input type="hidden" name="page" value="tpp-salary-centers">
                                <input type="search" name="s" placeholder="جستجوی نام مرکز…" value="<?php echo esc_attr( $search ); ?>">
                                <button class="button">جستجو</button>
                        </form>
                        <?php
                        /* نسخه 1.6.2: فرم حذف گروهی مراکز. */
                        ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-tpp-bulk>
                                <?php wp_nonce_field( 'tpp_salary_del_center_bulk' ); ?>
                                <input type="hidden" name="action" value="tpp_salary_del_center_bulk">
                                <div class="tablenav top" style="display:flex;gap:6px;align-items:center;padding:4px 0">
                                        <select name="bulk_action" data-tpp-bulk-action>
                                                <option value="">اقدام گروهی</option>
                                                <option value="delete">حذف</option>
                                        </select>
                                        <?php submit_button( 'اعمال', 'action', 'submit_bulk', false ); ?>
                                        <span class="description">با حذف مرکز، ارتباط کارمندان و رکوردها حفظ می‌شود.</span>
                                </div>
                        <table class="widefat striped">
                                <thead><tr><th style="width:32px"><input type="checkbox" class="tpp-cb-all" aria-label="انتخاب همه"></th><th>#</th><th>نام مرکز</th><th>تاریخ ایجاد</th><th>عملیات</th></tr></thead>
                                <tbody>
                                <?php if ( empty( $centers ) ) : ?>
                                        <tr><td colspan="5"><?php echo $search ? 'مرکزی با این جستجو یافت نشد.' : 'مرکزی تعریف نشده است.'; ?></td></tr>
                                <?php endif; ?>
                                <?php foreach ( $centers as $c ) : ?>
                                        <tr>
                                                <td><input type="checkbox" class="tpp-cb" name="ids[]" value="<?php echo (int) $c->id; ?>"></td>
                                                <td><?php echo (int) $c->id; ?></td>
                                                <td><?php echo esc_html( $c->name ); ?></td>
                                                <td><?php echo esc_html( $c->created_at ); ?></td>
                                                <td>
                                                        <?php $url = wp_nonce_url( admin_url( 'admin-post.php?action=tpp_salary_del_center&id=' . (int) $c->id ), 'tpp_salary_del_center' ); ?>
                                                        <a class="button button-small" style="color:#b32d2e" href="<?php echo esc_url( $url ); ?>" onclick="return confirm('با حذف مرکز، ارتباط کارمندان و رکوردهای آن حفظ می‌شود اما دیگر در فهرست نمایش داده نمی‌شود. حذف شود؟')">حذف</a>
                                                </td>
                                        </tr>
                                <?php endforeach; ?>
                                </tbody>
                        </table>
                        </form>
                        <?php
                        tpp_salary_pagination( $total, $per ); // نسخه 1.6.2: صفحه‌بندی نتایج جستجو
                        tpp_salary_bulk_table_script( 'همه مراکز انتخاب‌شده حذف می‌شوند (ارتباط کارمندان و رکوردها حفظ می‌شود). مطمئن هستید؟' ); // نسخه 1.6.2
                        ?>
                </div>
                <?php
        }
}
}
// TPP_SALARY GUARD END
