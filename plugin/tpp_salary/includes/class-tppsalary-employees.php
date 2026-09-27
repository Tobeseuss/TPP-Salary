<?php
/**
 * مدیریت کارمندان — فهرست، پروفایل فیلدها، مرکزها و حساب‌های بانکی
 *
 * @package TppSalary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class TppSalary_Employees
 */
if ( ! class_exists( 'TppSalary_Employees' ) ) {
        // TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TppSalary_Employees {

        /**
         * مقداردهی اولیه
         *
         * @return void
         */
        public static function init() {
                add_action( 'admin_menu', array( __CLASS__, 'menu' ), 10 );
                add_action( 'show_user_profile', array( __CLASS__, 'profile_fields' ) );
                add_action( 'edit_user_profile', array( __CLASS__, 'profile_fields' ) );
                add_action( 'personal_options_update', array( __CLASS__, 'save_profile' ) );
                add_action( 'edit_user_profile_update', array( __CLASS__, 'save_profile' ) );
                add_action( 'admin_post_tpp_salary_create_employee', array( __CLASS__, 'create_employee' ) );
                add_action( 'admin_post_tpp_salary_del_employee', array( __CLASS__, 'delete_employee' ) );
                /* نسخه 1.6.0: قطع/ادامه همکاری */
                add_action( 'admin_post_tpp_salary_toggle_termination', array( __CLASS__, 'toggle_termination' ) );
                /* نسخه 1.6.2: حذف گروهی نقش کارمندی */
                add_action( 'admin_post_tpp_salary_del_employee_bulk', array( __CLASS__, 'delete_employees_bulk' ) );
                add_action( 'admin_notices', array( __CLASS__, 'created_notice' ) );
        }

        /**
         * نمایش یک‌باره نام کاربری/رمز کارمند تازه‌ساخته (از transient)
         *
         * @return void
         */
        public static function created_notice() {
                if ( ! tpp_salary_can_manage() ) {
                        return;
                }
                $key  = 'tpp_salary_new_user_' . get_current_user_id();
                $info = get_transient( $key );
                if ( ! is_array( $info ) || empty( $info['login'] ) ) {
                        return;
                }
                delete_transient( $key );
                ?>
                <div class="notice notice-success is-dismissible">
                        <p><strong>کارمند ساخته شد:</strong> <?php echo esc_html( $info['name'] ); ?></p>
                        <p>
                                نام کاربری: <code dir="ltr"><?php echo esc_html( $info['login'] ); ?></code>
                                — رمز عبور: <code dir="ltr"><?php echo esc_html( $info['pass'] ); ?></code>
                        </p>
                        <p class="description">این رمز فقط همین یک‌بار نمایش داده می‌شود — آن را به کارمند بدهید. رمز دیگر برابر کد ملی نیست (به دلیل امنیت).</p>
                </div>
                <?php
        }

        /**
         * منو
         *
         * @return void
         */
        public static function menu() {
                add_submenu_page(
                        'tpp-salary',
                        'کارمندان',
                        'کارمندان',
                        'tpp_salary_manage',
                        'tpp-salary-employees',
                        array( __CLASS__, 'render_list' )
                );
        }

        /**
         * فهرست کارمندان
         *
         * @return void
         */
        public static function render_list() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                $search  = sanitize_text_field( wp_unslash( (isset($_GET['s'] )?$_GET['s'] : '' )) );
                $center  = (int) ( (isset($_GET['center'] )?$_GET['center'] : 0 ));
                $centers = tpp_salary_get_centers();
                /* نسخه 1.6.0: همه کارمندان (شامل قطع‌همکاری) با نشان وضعیت — تا دکمه «ادامه همکاری» در دسترس باشد. */
                $users   = tpp_salary_get_employees( null, true );
                if ( $search ) {
                        $search_en = TppSalary_Jalali::digits_en( $search );
                        $users = array_filter(
                                $users,
                                function ( $u ) use ( $search, $search_en ) {
                                        $profile = tpp_salary_get_profile( $u->ID );
                                        $full    = isset( $profile['full_name'] ) ? $profile['full_name'] : '';
                                        $hay     = $u->display_name . ' ' . $full . ' ' . $u->user_login . ' ' . get_user_meta( $u->ID, 'tpp_salary_national_id', true ) . ' ' . ( (isset($profile['job_title'] )?$profile['job_title'] : '' ));
                                        return false !== mb_stripos( $hay, $search ) || ( $search_en && false !== mb_stripos( $hay, $search_en ) );
                                }
                        );
                }
                if ( $center ) {
                        $users = array_values(
                                array_filter(
                                        $users,
                                        function ( $u ) use ( $center ) {
                                                $profile = tpp_salary_get_profile( $u->ID );
                                                return in_array( $center, array_map( 'intval', $profile['centers'] ), true );
                                        }
                                )
                        );
                }
                /*
                 * نسخه 1.6.1: صفحه‌بندی نتایج جستجو/فیلتر کارمندان —
                 * لینک صفحات پارامترهای جستجو (s/center) را حفظ می‌کند.
                 */
                $total = count( $users );
                $per   = tpp_salary_list_per_page();
                $pages = max( 1, (int) ceil( $total / $per ) );
                $paged = min( tpp_salary_current_paged(), $pages );
                $users = array_slice( $users, ( $paged - 1 ) * $per, $per );
                ?>
                <div class="wrap tpp-wrap" dir="rtl">
                        <h1>کارمندان <a class="page-title-action" href="#new">افزودن کارمند</a> <a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=tpp-salary-import' ) ); ?>">ورود گروهی از اکسل</a></h1>
                        <?php
                        /* نسخه 1.6.2: اعلان تعداد حذف‌شده (تکی/گروهی). */
                        $del_count = isset( $_GET['deleted'] ) ? absint( $_GET['deleted'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        if ( $del_count ) :
                                ?>
                                <div class="notice notice-success"><p>نقش کارمندی <?php echo esc_html( TppSalary_Jalali::digits_fa( $del_count ) ); ?> کارمند حذف شد (حساب کاربری حفظ شد).</p></div>
                        <?php endif; ?>
                        <form method="get" style="margin:12px 0">
                                <input type="hidden" name="page" value="tpp-salary-employees">
                                <input type="search" name="s" placeholder="جستجوی نام، کد ملی، عنوان شغلی…" value="<?php echo esc_attr( $search ); ?>">
                                <select name="center">
                                        <option value="0">همه مراکز</option>
                                        <?php foreach ( $centers as $c ) : ?>
                                                <option value="<?php echo (int) $c->id; ?>" <?php selected( $center, $c->id ); ?>><?php echo esc_html( $c->name ); ?></option>
                                        <?php endforeach; ?>
                                </select>
                                <button class="button">جستجو</button>
                        </form>
                        <?php
                        /* نسخه 1.6.2: فرم حذف گروهی نقش کارمندی — جستجو فرم جداگانه GET است. */
                        ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-tpp-bulk>
                                <?php wp_nonce_field( 'tpp_salary_del_employee_bulk' ); ?>
                                <input type="hidden" name="action" value="tpp_salary_del_employee_bulk">
                                <div class="tablenav top" style="display:flex;gap:6px;align-items:center;padding:4px 0">
                                        <select name="bulk_action" data-tpp-bulk-action>
                                                <option value="">اقدام گروهی</option>
                                                <option value="delete_role">حذف نقش کارمندی</option>
                                        </select>
                                        <?php submit_button( 'اعمال', 'action', 'submit_bulk', false ); ?>
                                        <span class="description">حذف نقش کارمندی، حساب کاربری را حذف نمی‌کند.</span>
                                </div>
                        <table class="widefat striped">
                                <thead><tr>
                                        <th style="width:32px"><input type="checkbox" class="tpp-cb-all" aria-label="انتخاب همه"></th>
                                        <th>نام و نام خانوادگی</th><th>کد ملی</th><th>عنوان شغلی</th><th>مراکز</th><th>دستمزد روزانه مرجع</th><th>وضعیت همکاری</th><th>عملیات</th>
                                </tr></thead>
                                <tbody>
                                <?php if ( empty( $users ) ) : ?>
                                        <tr><td colspan="8">کارمندی یافت نشد.</td></tr>
                                <?php endif; ?>
                                <?php foreach ( $users as $u ) :
                                        $profile    = tpp_salary_get_profile( $u->ID );
                                        $names      = array();
                                        foreach ( $profile['centers'] as $cid ) {
                                                $c = tpp_salary_get_center( (int) $cid );
                                                if ( $c ) {
                                                        $names[] = $c->name;
                                                }
                                        }
                                        ?>
                                        <tr>
                                                <td><input type="checkbox" class="tpp-cb" name="ids[]" value="<?php echo (int) $u->ID; ?>"></td>
                                                <td><strong><?php echo esc_html( $u->display_name ); ?></strong></td>
                                                <td><?php echo esc_html( get_user_meta( $u->ID, 'tpp_salary_national_id', true ) ); ?></td>
                                                <td><?php echo esc_html( (isset($profile['job_title'] )?$profile['job_title'] : '' )); ?></td>
                                                <td><?php echo esc_html( implode( '، ', $names ) ); ?></td>
                                                <td><?php echo esc_html( tpp_salary_format_number( (float) ( (isset($profile['daily_wage'] )?$profile['daily_wage'] : 0 )) ) ); ?></td>
                                                <td>
                                                        <?php if ( tpp_salary_is_terminated( $u->ID ) ) : ?>
                                                                <span style="color:#b32d2e;font-weight:600">⛔ قطع همکاری</span>
                                                        <?php else : ?>
                                                                <span style="color:#00a32a;font-weight:600">✅ در حال همکاری</span>
                                                        <?php endif; ?>
                                                </td>
                                                <td>
                                                        <a class="button button-small" href="<?php echo esc_url( get_edit_user_link( $u->ID ) ); ?>">ویرایش پروفایل</a>
                                                        <?php if ( ! tpp_salary_is_terminated( $u->ID ) ) : ?>
                                                                <a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=tpp-salary-register&user_id=' . $u->ID ) ); ?>">ثبت حقوق</a>
                                                        <?php endif; ?>
                                                        <?php
                                                        /* نسخه 1.6.0: قطع/ادامه همکاری */
                                                        if ( tpp_salary_is_terminated( $u->ID ) ) {
                                                                echo self::termination_link( $u->ID, 'ادامه همکاری', 'rehire', 'button button-small' ); // phpcs:ignore
                                                        } else {
                                                                echo self::termination_link( $u->ID, 'قطع همکاری', 'terminate', 'button button-small' ); // phpcs:ignore
                                                        }
                                                        ?>
                                                        <?php $du = wp_nonce_url( admin_url( 'admin-post.php?action=tpp_salary_del_employee&id=' . (int) $u->ID ), 'tpp_salary_del_employee' ); ?>
                                                        <a class="button button-small" style="color:#b32d2e" href="<?php echo esc_url( $du ); ?>" onclick="return confirm('حذف نقش کارمندی؟ (حساب کاربری حذف نمی‌شود)')">حذف نقش</a>
                                                </td>
                                        </tr>
                                <?php endforeach; ?>
                                </tbody>
                        </table>
                        </form>
                        <?php
                        tpp_salary_pagination( $total, $per ); // نسخه 1.6.1: صفحه‌بندی نتایج جستجو
                        tpp_salary_bulk_table_script( 'نقش کارمندی از همه کارمندان انتخاب‌شده حذف می‌شود (حساب کاربری حذف نمی‌شود). مطمئن هستید؟' ); // نسخه 1.6.2
                        ?>

                        <h2 id="new" style="margin-top:32px">افزودن کارمند جدید</h2>
                        <!-- نسخه 1.4.1: پس‌زمینه سفید inline حذف شد (دارک‌مود) — کلاس tpp-panel استایل تاریک/روشن دارد. -->
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tpp-panel" style="max-width:640px">
                                <?php wp_nonce_field( 'tpp_salary_create_employee' ); ?>
                                <input type="hidden" name="action" value="tpp_salary_create_employee">
                                <table class="form-table" role="presentation">
                                        <tr><th>نام و نام خانوادگی</th><td><input type="text" name="display_name" required class="regular-text"></td></tr>
                                        <tr><th>کد ملی</th><td><input type="text" name="national_id" class="regular-text" dir="ltr"><br><span class="description">اگر خالی باشد نام کاربری و رمز تصادفی ۱۰ رقمی ساخته می‌شود.</span></td></tr>
                                        <tr><th>شماره همراه</th><td><input type="text" name="mobile" class="regular-text" dir="ltr"></td></tr>
                                        <tr><th>ایمیل (اختیاری)</th><td><input type="email" name="email" class="regular-text" dir="ltr"></td></tr>
                                </table>
                                <?php submit_button( 'ایجاد کارمند' ); ?>
                                <p class="description">نام کاربری کارمند برابر کد ملی او (یا عدد تصادفی ۱۰ رقمی در نبود کد ملی) خواهد بود و رمز عبور تصادفی امن تولید می‌شود که فقط یک‌بار نمایش داده می‌شود.</p>
                        </form>
                </div>
                <?php
        }

        /**
         * ایجاد کارمند جدید (کاربر وردپرس با نقش tpp_salary_employee)
         *
         * @return void
         */
        public static function create_employee() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_salary_create_employee' );

                $display_name = sanitize_text_field( wp_unslash( (isset($_POST['display_name'] )?$_POST['display_name'] : '' )) );
                $national     = TppSalary_Jalali::digits_en( sanitize_text_field( wp_unslash( (isset($_POST['national_id'] )?$_POST['national_id'] : '' )) ) );
                $mobile       = TppSalary_Jalali::digits_en( sanitize_text_field( wp_unslash( (isset($_POST['mobile'] )?$_POST['mobile'] : '' )) ) );
                $email        = sanitize_email( wp_unslash( (isset($_POST['email'] )?$_POST['email'] : '' )) );

                /*
                 * امنیت: رمز عبور هرگز برابر کد ملی نیست — رمز تصادفی تولید می‌شود
                 * و فقط یک‌بار به مدیر نمایش داده می‌شود (و ایمیل اطلاع‌رسانی ارسال می‌گردد).
                 */
                $plain_pass = wp_generate_password( 12, false );
                $username   = ( preg_match( '/^\d{10}$/', $national ) ) ? $national : (string) wp_rand( 1000000000, 9999999999 );
                $login      = $username;
                $i          = 0;
                while ( username_exists( $login ) ) {
                        $login = $username . ( ++$i > 0 ? '_' . $i : '' );
                }
                $user_id = wp_insert_user(
                        array(
                                'user_login'   => $login,
                                'user_pass'    => $plain_pass,
                                'display_name' => $display_name ? $display_name : $login,
                                'user_email'   => $email,
                                'role'         => 'tpp_salary_employee',
                        )
                );
                if ( is_wp_error( $user_id ) ) {
                        wp_die( esc_html( $user_id->get_error_message() ) );
                }
                // اطلاع‌رسانی ایمیل (در هاست‌های بدون ایمیل بی‌ضرر رد می‌شود).
                wp_new_user_notification( $user_id, null, 'admin' );
                // نمایش یک‌باره نام کاربری/رمز به مدیر.
                set_transient(
                        'tpp_salary_new_user_' . get_current_user_id(),
                        array(
                                'login' => $login,
                                'pass'  => $plain_pass,
                                'name'  => $display_name ? $display_name : $login,
                        ),
                        180
                );
                if ( $national ) {
                        update_user_meta( $user_id, 'tpp_salary_national_id', $national );
                }
                if ( $mobile ) {
                        update_user_meta( $user_id, 'tpp_salary_mobile', $mobile );
                }
                // نام و نام خانوادگی به‌عنوان فیلد افزونه‌ای هم ذخیره می‌شود (برای تطبیق ورود گروهی حقوق).
                $profile = tpp_salary_get_profile( $user_id );
                $profile['full_name'] = $display_name ? $display_name : $login;
                tpp_salary_save_profile( $user_id, $profile );
                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-salary-employees', 'created' => $user_id ), admin_url( 'admin.php' ) ) );
                exit;
        }

        /**
         * قطع/ادامه همکاری کارمند — نسخه 1.6.0
         *
         * کارمند «قطع همکاری» شده در لیست‌های حقوق، فرم‌های ثبت حقوق،
         * گزارش‌ها و فیش‌ها نمایش داده نمی‌شود (tpp_salary_get_employees).
         *
         * @return void
         */
        public static function toggle_termination() {
                if ( ! tpp_salary_can_manage() && ! current_user_can( 'edit_users' ) ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_salary_toggle_termination' );
                $id    = (int) ( ( isset( $_GET['id'] ) ? $_GET['id'] : 0 ) );
                $state = ( isset( $_GET['state'] ) ? $_GET['state'] : '' );
                $usr   = get_user_by( 'id', $id );
                if ( $usr && in_array( 'tpp_salary_employee', (array) $usr->roles, true ) ) {
                        if ( 'terminate' === $state ) {
                                update_user_meta( $id, 'tpp_salary_terminated', '1' );
                        } else {
                                delete_user_meta( $id, 'tpp_salary_terminated' );
                        }
                }
                /* بازگشت به صفحه مبدا (ویرایش کاربر یا فهرست کارمندان). */
                $redirect = wp_get_referer();
                if ( ! $redirect ) {
                        $redirect = admin_url( 'admin.php?page=tpp-salary-employees' );
                }
                wp_safe_redirect( $redirect );
                exit;
        }

        /**
         * لینک قطع/ادامه همکاری با نانس
         *
         * @param int    $user_id شناسه کاربر.
         * @param string $label   برچسب دکمه.
         * @param string $state   terminate|rehire.
         * @param string $class   کلاس دکمه.
         * @return string
         */
        public static function termination_link( $user_id, $label, $state, $class = 'button' ) {
                $url = wp_nonce_url(
                        admin_url( 'admin-post.php?action=tpp_salary_toggle_termination&id=' . (int) $user_id . '&state=' . rawurlencode( $state ) ),
                        'tpp_salary_toggle_termination'
                );
                $confirm = ( 'terminate' === $state )
                        ? " onclick=\"return confirm('کارمند از لیست‌های حقوق و فرم‌های ثبت حقوق حذف (مخفی) می‌شود. مطمئن هستید؟')\""
                        : '';
                $style = ( 'terminate' === $state ) ? ' style="color:#b32d2e"' : ' style="color:#2271b1"';
                return '<a class="' . esc_attr( $class ) . '"' . $style . ' href="' . esc_url( $url ) . '"' . $confirm . '>' . esc_html( $label ) . '</a>';
        }

        /**
         * حذف نقش کارمندی
         *
         * @return void
         */
        public static function delete_employee() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_salary_del_employee' );
                $id  = (int) ( (isset($_GET['id'] )?$_GET['id'] : 0 ));
                $usr = get_user_by( 'id', $id );
                if ( $usr && in_array( 'tpp_salary_employee', (array) $usr->roles, true ) ) {
                        $usr->remove_role( 'tpp_salary_employee' );
                }
                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-salary-employees', 'deleted' => '1' ), admin_url( 'admin.php' ) ) );
                exit;
        }

        /**
         * حذف گروهی نقش کارمندی — نسخه 1.6.2
         *
         * @return void
         */
        public static function delete_employees_bulk() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_salary_del_employee_bulk' );
                $ids = tpp_salary_ids_from_request( ( isset( $_POST['ids'] ) ? $_POST['ids'] : array() ) );
                $n   = self::bulk_delete_employees( $ids );
                $back = wp_get_referer();
                $back = $back ? $back : admin_url( 'admin.php?page=tpp-salary-employees' );
                wp_safe_redirect( add_query_arg( array( 'deleted' => $n ), $back ) );
                exit;
        }

        /**
         * هسته حذف گروهی نقش کارمندی (قابل‌تست) — نسخه 1.6.2
         *
         * حساب کاربری حذف نمی‌شود؛ فقط نقش tpp_salary_employee برداشته می‌شود
         * (هم‌سمان دکمه حذف تکی). کاربران بدون نقش کارمندی نادیده گرفته می‌شوند.
         *
         * @param int[] $ids شناسه‌های کاربران.
         * @return int تعداد نقش حذف‌شده.
         */
        public static function bulk_delete_employees( $ids ) {
                $ids = tpp_salary_ids_from_request( $ids );
                $n   = 0;
                foreach ( $ids as $id ) {
                        $usr = get_user_by( 'id', $id );
                        if ( $usr && in_array( 'tpp_salary_employee', (array) $usr->roles, true ) ) {
                                $usr->remove_role( 'tpp_salary_employee' );
                                $n++;
                        }
                }
                return $n;
        }

        /**
         * فیلدهای پروفایل کارمند در صفحه کاربر
         *
         * @param WP_User $user کاربر.
         * @return void
         */
        public static function profile_fields( $user ) {
                if ( ! tpp_salary_can_manage() && ! current_user_can( 'edit_users' ) ) {
                        return;
                }
                $profile  = tpp_salary_get_profile( $user->ID );
                $settings = tpp_salary_get_settings();
                $defaults = isset( $settings['defaults'] ) && is_array( $settings['defaults'] ) ? $settings['defaults'] : array();
                $fields   = tpp_salary_get_profile_fields();
                $centers  = tpp_salary_get_centers();
                $banks    = tpp_salary_get_banks();
                $is_emp   = in_array( 'tpp_salary_employee', (array) $user->roles, true );
                ?>
                <h2>اطلاعات حقوق و دستمزد <?php echo $is_emp ? '' : '<span class="description">(کارمند نیست — نقش tpp_salary_employee ندارد)</span>'; ?></h2>
                <?php if ( $is_emp ) : ?>
                        <?php /* نسخه 1.6.0: وضعیت همکاری + دکمه‌های قطع/ادامه همکاری */ ?>
                        <table class="form-table" role="presentation">
                                <tr>
                                        <th><label>وضعیت همکاری</label></th>
                                        <td>
                                                <?php if ( tpp_salary_is_terminated( $user->ID ) ) : ?>
                                                        <span style="color:#b32d2e;font-weight:600">⛔ قطع همکاری</span>
                                                        <span class="description">— در لیست‌های حقوق، فرم‌های ثبت حقوق و گزارش‌ها نمایش داده نمی‌شود.</span>
                                                        <?php echo self::termination_link( $user->ID, 'ادامه همکاری', 'rehire', 'button button-primary' ); // phpcs:ignore ?>
                                                <?php else : ?>
                                                        <span style="color:#00a32a;font-weight:600">✅ در حال همکاری</span>
                                                        <?php echo self::termination_link( $user->ID, 'قطع همکاری', 'terminate', 'button' ); // phpcs:ignore ?>
                                                <?php endif; ?>
                                        </td>
                                </tr>
                        </table>
                <?php endif; ?>
                <table class="form-table" role="presentation">
                        <tr>
                                <th><label for="tpp_salary_national_id">کد ملی</label></th>
                                <td><input type="text" name="tpp_salary_national_id" id="tpp_salary_national_id" dir="ltr" value="<?php echo esc_attr( get_user_meta( $user->ID, 'tpp_salary_national_id', true ) ); ?>"></td>
                        </tr>
                        <tr>
                                <th><label for="tpp_salary_mobile">شماره همراه</label></th>
                                <td><input type="text" name="tpp_salary_mobile" id="tpp_salary_mobile" dir="ltr" value="<?php echo esc_attr( get_user_meta( $user->ID, 'tpp_salary_mobile', true ) ); ?>"></td>
                        </tr>
                        <?php foreach ( $fields as $f ) :
                                $val = (isset($profile[ $f->field_key ] )?$profile[ $f->field_key ] : ( (isset($defaults[ $f->field_key ] )?$defaults[ $f->field_key ] : $f->default_value )));
                                ?>
                                <tr>
                                        <th><label><?php echo esc_html( $f->label ); ?></label></th>
                                        <td><input type="<?php echo esc_attr( $f->field_type ); ?>" name="tpp_salary_profile[<?php echo esc_attr( $f->field_key ); ?>]" value="<?php echo esc_attr( $val ); ?>" class="regular-text" <?php echo ( 'number' === $f->field_type ) ? 'dir="ltr"' : ''; ?>></td>
                                </tr>
                        <?php endforeach; ?>
                        <tr>
                                <th>مراکز (پروژه / کارگاه)</th>
                                <td>
                                        <?php if ( empty( $centers ) ) : ?>
                                                <span class="description">مرکزی تعریف نشده است.</span>
                                        <?php endif; ?>
                                        <?php foreach ( $centers as $c ) : ?>
                                                <label style="display:inline-block;margin-left:12px">
                                                        <input type="checkbox" name="tpp_salary_centers[]" value="<?php echo (int) $c->id; ?>" <?php checked( in_array( (int) $c->id, array_map( 'intval', $profile['centers'] ), true ) ); ?>>
                                                        <?php echo esc_html( $c->name ); ?>
                                                </label>
                                        <?php endforeach; ?>
                                </td>
                        </tr>
                        <?php if ( ! empty( $banks ) ) : ?>
                        <tr>
                                <th>حساب‌های بانکی</th>
                                <td>
                                        <table class="widefat" style="max-width:640px">
                                                <thead><tr><th>بانک</th><th>شماره حساب</th><th>شماره شبا</th><th>شماره کارت</th></tr></thead>
                                                <tbody>
                                                <?php foreach ( $banks as $b ) :
                                                        $acc = (isset($profile['bank_accounts'][ $b->id ] )?$profile['bank_accounts'][ $b->id ] : array( 'account' => '', 'sheba' => '', 'card' => '' ));
                                                        ?>
                                                        <tr>
                                                                <td><?php echo esc_html( $b->name ); ?></td>
                                                                <td><input type="text" dir="ltr" name="tpp_salary_bank[<?php echo (int) $b->id; ?>][account]" value="<?php echo esc_attr( (isset($acc['account'] )?$acc['account'] : '' )); ?>"></td>
                                                                <td><input type="text" dir="ltr" name="tpp_salary_bank[<?php echo (int) $b->id; ?>][sheba]" value="<?php echo esc_attr( (isset($acc['sheba'] )?$acc['sheba'] : '' )); ?>" placeholder="IR"></td>
                                                                <td><input type="text" dir="ltr" name="tpp_salary_bank[<?php echo (int) $b->id; ?>][card]" value="<?php echo esc_attr( (isset($acc['card'] )?$acc['card'] : '' )); ?>"></td>
                                                        </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                        </table>
                                </td>
                        </tr>
                        <?php endif; ?>
                </table>
                <?php
        }

        /**
         * ذخیره فیلدهای پروفایل
         *
         * @param int $user_id شناسه کاربر.
         * @return void
         */
        public static function save_profile( $user_id ) {
                if ( ! tpp_salary_can_manage() && ! current_user_can( 'edit_users' ) ) {
                        return;
                }
                if ( ! isset( $_POST['tpp_salary_profile'] ) && ! isset( $_POST['tpp_salary_centers'] ) && ! isset( $_POST['tpp_salary_bank'] ) ) {
                        return;
                }
                check_admin_referer( 'update-user_' . $user_id );

                if ( isset( $_POST['tpp_salary_national_id'] ) ) {
                        update_user_meta( $user_id, 'tpp_salary_national_id', TppSalary_Jalali::digits_en( sanitize_text_field( wp_unslash( $_POST['tpp_salary_national_id'] ) ) ) );
                }
                if ( isset( $_POST['tpp_salary_mobile'] ) ) {
                        update_user_meta( $user_id, 'tpp_salary_mobile', TppSalary_Jalali::digits_en( sanitize_text_field( wp_unslash( $_POST['tpp_salary_mobile'] ) ) ) );
                }

                $profile = tpp_salary_get_profile( $user_id );
                foreach ( tpp_salary_get_profile_fields() as $f ) {
                        if ( ! isset( $_POST['tpp_salary_profile'][ $f->field_key ] ) ) {
                                continue;
                        }
                        $raw = wp_unslash( $_POST['tpp_salary_profile'][ $f->field_key ] );
                        $profile[ $f->field_key ] = ( 'number' === $f->field_type ) ? tpp_salary_parse_number( $raw ) : sanitize_text_field( $raw );
                }
                /*
                 * نسخه 1.4.1: فیلد «نام و نام خانوادگی» متعلق به افزونه — اگر پر شده
                 * باشد، نام نمایشی کاربر هم هماهنگ می‌شود تا تطبیق در ورود گروهی و
                 * گزارش‌ها همیشه با همین نام انجام شود.
                 */
                if ( isset( $profile['full_name'] ) && '' !== trim( (string) $profile['full_name'] ) ) {
                        $full = sanitize_text_field( $profile['full_name'] );
                        $profile['full_name'] = $full;
                        $current = get_userdata( $user_id );
                        if ( $current && $current->display_name !== $full ) {
                                wp_update_user( array( 'ID' => $user_id, 'display_name' => $full ) );
                        }
                }
                $profile['centers'] = isset( $_POST['tpp_salary_centers'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['tpp_salary_centers'] ) ) : array();

                $bank_accounts = array();
                foreach ( tpp_salary_get_banks() as $b ) {
                        if ( isset( $_POST['tpp_salary_bank'][ $b->id ] ) ) {
                                $bank_accounts[ $b->id ] = array(
                                        'account' => TppSalary_Jalali::digits_en( sanitize_text_field( wp_unslash( (isset($_POST['tpp_salary_bank'][ $b->id ]['account'] )?$_POST['tpp_salary_bank'][ $b->id ]['account'] : '' )) ) ),
                                        'sheba'   => strtoupper( TppSalary_Jalali::digits_en( sanitize_text_field( wp_unslash( (isset($_POST['tpp_salary_bank'][ $b->id ]['sheba'] )?$_POST['tpp_salary_bank'][ $b->id ]['sheba'] : '' )) ) ) ),
                                        'card'    => TppSalary_Jalali::digits_en( sanitize_text_field( wp_unslash( (isset($_POST['tpp_salary_bank'][ $b->id ]['card'] )?$_POST['tpp_salary_bank'][ $b->id ]['card'] : '' )) ) ),
                                );
                        }
                }
                $profile['bank_accounts'] = $bank_accounts;
                tpp_salary_save_profile( $user_id, $profile );
        }
}
}
// TPP_SALARY GUARD END
