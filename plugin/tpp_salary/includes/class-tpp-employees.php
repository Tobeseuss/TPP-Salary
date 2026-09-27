<?php
/**
 * مدیریت کارمندان — فهرست، پروفایل فیلدها، مرکزها و حساب‌های بانکی
 *
 * @package TPP_Salary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class TPP_Employees
 */
if ( ! class_exists( 'TPP_Employees' ) ) {
        // TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TPP_Employees {

        /**
         * مقداردهی اولیه
         *
         * @return void
         */
        public static function init() {
                add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
                add_action( 'show_user_profile', array( __CLASS__, 'profile_fields' ) );
                add_action( 'edit_user_profile', array( __CLASS__, 'profile_fields' ) );
                add_action( 'personal_options_update', array( __CLASS__, 'save_profile' ) );
                add_action( 'edit_user_profile_update', array( __CLASS__, 'save_profile' ) );
                add_action( 'admin_post_tpp_create_employee', array( __CLASS__, 'create_employee' ) );
                add_action( 'admin_post_tpp_del_employee', array( __CLASS__, 'delete_employee' ) );
                add_action( 'admin_notices', array( __CLASS__, 'created_notice' ) );
        }

        /**
         * نمایش یک‌باره نام کاربری/رمز کارمند تازه‌ساخته (از transient)
         *
         * @return void
         */
        public static function created_notice() {
                if ( ! tpp_can_manage() ) {
                        return;
                }
                $key  = 'tpp_new_user_' . get_current_user_id();
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
                        'tpp_manage_salary',
                        'tpp-employees',
                        array( __CLASS__, 'render_list' )
                );
        }

        /**
         * فهرست کارمندان
         *
         * @return void
         */
        public static function render_list() {
                if ( ! tpp_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                $search  = sanitize_text_field( wp_unslash( (isset($_GET['s'] )?$_GET['s'] : '' )) );
                $center  = (int) ( (isset($_GET['center'] )?$_GET['center'] : 0 ));
                $centers = tpp_get_centers();
                $users   = tpp_get_employees();
                if ( $search ) {
                        $users = array_filter(
                                $users,
                                function ( $u ) use ( $search ) {
                                        $profile = tpp_get_profile( $u->ID );
                                        $hay     = $u->display_name . ' ' . $u->user_login . ' ' . get_user_meta( $u->ID, 'tpp_national_id', true ) . ' ' . ( (isset($profile['job_title'] )?$profile['job_title'] : '' ));
                                        return false !== mb_stripos( $hay, $search );
                                }
                        );
                }
                if ( $center ) {
                        $users = array_values(
                                array_filter(
                                        $users,
                                        function ( $u ) use ( $center ) {
                                                $profile = tpp_get_profile( $u->ID );
                                                return in_array( $center, array_map( 'intval', $profile['centers'] ), true );
                                        }
                                )
                        );
                }
                ?>
                <div class="wrap tpp-wrap" dir="rtl">
                        <h1>کارمندان <a class="page-title-action" href="#new">افزودن کارمند</a> <a class="page-title-action" href="<?php echo esc_url( admin_url( 'admin.php?page=tpp-import' ) ); ?>">ورود گروهی از اکسل</a></h1>
                        <form method="get" style="margin:12px 0">
                                <input type="hidden" name="page" value="tpp-employees">
                                <input type="search" name="s" placeholder="جستجوی نام، کد ملی، عنوان شغلی…" value="<?php echo esc_attr( $search ); ?>">
                                <select name="center">
                                        <option value="0">همه مراکز</option>
                                        <?php foreach ( $centers as $c ) : ?>
                                                <option value="<?php echo (int) $c->id; ?>" <?php selected( $center, $c->id ); ?>><?php echo esc_html( $c->name ); ?></option>
                                        <?php endforeach; ?>
                                </select>
                                <button class="button">جستجو</button>
                        </form>
                        <table class="widefat striped">
                                <thead><tr>
                                        <th>نام و نام خانوادگی</th><th>کد ملی</th><th>عنوان شغلی</th><th>مراکز</th><th>دستمزد روزانه مرجع</th><th>عملیات</th>
                                </tr></thead>
                                <tbody>
                                <?php if ( empty( $users ) ) : ?>
                                        <tr><td colspan="6">کارمندی یافت نشد.</td></tr>
                                <?php endif; ?>
                                <?php foreach ( $users as $u ) :
                                        $profile    = tpp_get_profile( $u->ID );
                                        $names      = array();
                                        foreach ( $profile['centers'] as $cid ) {
                                                $c = tpp_get_center( (int) $cid );
                                                if ( $c ) {
                                                        $names[] = $c->name;
                                                }
                                        }
                                        ?>
                                        <tr>
                                                <td><strong><?php echo esc_html( $u->display_name ); ?></strong></td>
                                                <td><?php echo esc_html( get_user_meta( $u->ID, 'tpp_national_id', true ) ); ?></td>
                                                <td><?php echo esc_html( (isset($profile['job_title'] )?$profile['job_title'] : '' )); ?></td>
                                                <td><?php echo esc_html( implode( '، ', $names ) ); ?></td>
                                                <td><?php echo esc_html( tpp_format_number( (float) ( (isset($profile['daily_wage'] )?$profile['daily_wage'] : 0 )) ) ); ?></td>
                                                <td>
                                                        <a class="button button-small" href="<?php echo esc_url( get_edit_user_link( $u->ID ) ); ?>">ویرایش پروفایل</a>
                                                        <a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=tpp-salary-register&user_id=' . $u->ID ) ); ?>">ثبت حقوق</a>
                                                        <?php $du = wp_nonce_url( admin_url( 'admin-post.php?action=tpp_del_employee&id=' . (int) $u->ID ), 'tpp_del_employee' ); ?>
                                                        <a class="button button-small" style="color:#b32d2e" href="<?php echo esc_url( $du ); ?>" onclick="return confirm('حذف نقش کارمندی؟ (حساب کاربری حذف نمی‌شود)')">حذف نقش</a>
                                                </td>
                                        </tr>
                                <?php endforeach; ?>
                                </tbody>
                        </table>

                        <h2 id="new" style="margin-top:32px">افزودن کارمند جدید</h2>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;padding:16px;border:1px solid #ccd0d4;max-width:640px">
                                <?php wp_nonce_field( 'tpp_create_employee' ); ?>
                                <input type="hidden" name="action" value="tpp_create_employee">
                                <table class="form-table" role="presentation">
                                        <tr><th>نام و نام خانوادگی</th><td><input type="text" name="display_name" required class="regular-text"></td></tr>
                                        <tr><th>کد ملی</th><td><input type="text" name="national_id" class="regular-text" dir="ltr"><br><span class="description">اگر خالی باشد نام کاربری و رمز تصادفی ۱۰ رقمی ساخته می‌شود.</span></td></tr>
                                        <tr><th>شماره همراه</th><td><input type="text" name="mobile" class="regular-text" dir="ltr"></td></tr>
                                        <tr><th>ایمیل (اختیاری)</th><td><input type="email" name="email" class="regular-text" dir="ltr"></td></tr>
                                </table>
                                <?php submit_button( 'ایجاد کارمند' ); ?>
                                <p class="description">نام کاربری و رمز عبور کارمند برابر کد ملی او خواهد بود (یا عدد تصادفی ۱۰ رقمی در نبود کد ملی).</p>
                        </form>
                </div>
                <?php
        }

        /**
         * ایجاد کارمند جدید (کاربر وردپرس با نقش tpp_Employe)
         *
         * @return void
         */
        public static function create_employee() {
                if ( ! tpp_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_create_employee' );

                $display_name = sanitize_text_field( wp_unslash( (isset($_POST['display_name'] )?$_POST['display_name'] : '' )) );
                $national     = TPP_Jalali::digits_en( sanitize_text_field( wp_unslash( (isset($_POST['national_id'] )?$_POST['national_id'] : '' )) ) );
                $mobile       = TPP_Jalali::digits_en( sanitize_text_field( wp_unslash( (isset($_POST['mobile'] )?$_POST['mobile'] : '' )) ) );
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
                                'role'         => 'tpp_Employe',
                        )
                );
                if ( is_wp_error( $user_id ) ) {
                        wp_die( esc_html( $user_id->get_error_message() ) );
                }
                // اطلاع‌رسانی ایمیل (در هاست‌های بدون ایمیل بی‌ضرر رد می‌شود).
                wp_new_user_notification( $user_id, null, 'admin' );
                // نمایش یک‌باره نام کاربری/رمز به مدیر.
                set_transient(
                        'tpp_new_user_' . get_current_user_id(),
                        array(
                                'login' => $login,
                                'pass'  => $plain_pass,
                                'name'  => $display_name ? $display_name : $login,
                        ),
                        180
                );
                if ( $national ) {
                        update_user_meta( $user_id, 'tpp_national_id', $national );
                }
                if ( $mobile ) {
                        update_user_meta( $user_id, 'tpp_mobile', $mobile );
                }
                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-employees', 'created' => $user_id ), admin_url( 'admin.php' ) ) );
                exit;
        }

        /**
         * حذف نقش کارمندی
         *
         * @return void
         */
        public static function delete_employee() {
                if ( ! tpp_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_del_employee' );
                $id  = (int) ( (isset($_GET['id'] )?$_GET['id'] : 0 ));
                $usr = get_user_by( 'id', $id );
                if ( $usr && in_array( 'tpp_Employe', (array) $usr->roles, true ) ) {
                        $usr->remove_role( 'tpp_Employe' );
                }
                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-employees', 'deleted' => '1' ), admin_url( 'admin.php' ) ) );
                exit;
        }

        /**
         * فیلدهای پروفایل کارمند در صفحه کاربر
         *
         * @param WP_User $user کاربر.
         * @return void
         */
        public static function profile_fields( $user ) {
                if ( ! tpp_can_manage() && ! current_user_can( 'edit_users' ) ) {
                        return;
                }
                $profile  = tpp_get_profile( $user->ID );
                $settings = tpp_get_settings();
                $defaults = isset( $settings['defaults'] ) && is_array( $settings['defaults'] ) ? $settings['defaults'] : array();
                $fields   = tpp_get_profile_fields();
                $centers  = tpp_get_centers();
                $banks    = tpp_get_banks();
                $is_emp   = in_array( 'tpp_Employe', (array) $user->roles, true );
                ?>
                <h2>اطلاعات حقوق و دستمزد <?php echo $is_emp ? '' : '<span class="description">(کارمند نیست — نقش tpp_Employe ندارد)</span>'; ?></h2>
                <table class="form-table" role="presentation">
                        <tr>
                                <th><label for="tpp_national_id">کد ملی</label></th>
                                <td><input type="text" name="tpp_national_id" id="tpp_national_id" dir="ltr" value="<?php echo esc_attr( get_user_meta( $user->ID, 'tpp_national_id', true ) ); ?>"></td>
                        </tr>
                        <tr>
                                <th><label for="tpp_mobile">شماره همراه</label></th>
                                <td><input type="text" name="tpp_mobile" id="tpp_mobile" dir="ltr" value="<?php echo esc_attr( get_user_meta( $user->ID, 'tpp_mobile', true ) ); ?>"></td>
                        </tr>
                        <?php foreach ( $fields as $f ) :
                                $val = (isset($profile[ $f->field_key ] )?$profile[ $f->field_key ] : ( (isset($defaults[ $f->field_key ] )?$defaults[ $f->field_key ] : $f->default_value )));
                                ?>
                                <tr>
                                        <th><label><?php echo esc_html( $f->label ); ?></label></th>
                                        <td><input type="<?php echo esc_attr( $f->field_type ); ?>" name="tpp_profile[<?php echo esc_attr( $f->field_key ); ?>]" value="<?php echo esc_attr( $val ); ?>" class="regular-text" <?php echo ( 'number' === $f->field_type ) ? 'dir="ltr"' : ''; ?>></td>
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
                                                        <input type="checkbox" name="tpp_centers[]" value="<?php echo (int) $c->id; ?>" <?php checked( in_array( (int) $c->id, array_map( 'intval', $profile['centers'] ), true ) ); ?>>
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
                                                                <td><input type="text" dir="ltr" name="tpp_bank[<?php echo (int) $b->id; ?>][account]" value="<?php echo esc_attr( (isset($acc['account'] )?$acc['account'] : '' )); ?>"></td>
                                                                <td><input type="text" dir="ltr" name="tpp_bank[<?php echo (int) $b->id; ?>][sheba]" value="<?php echo esc_attr( (isset($acc['sheba'] )?$acc['sheba'] : '' )); ?>" placeholder="IR"></td>
                                                                <td><input type="text" dir="ltr" name="tpp_bank[<?php echo (int) $b->id; ?>][card]" value="<?php echo esc_attr( (isset($acc['card'] )?$acc['card'] : '' )); ?>"></td>
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
                if ( ! tpp_can_manage() && ! current_user_can( 'edit_users' ) ) {
                        return;
                }
                if ( ! isset( $_POST['tpp_profile'] ) && ! isset( $_POST['tpp_centers'] ) && ! isset( $_POST['tpp_bank'] ) ) {
                        return;
                }
                check_admin_referer( 'update-user_' . $user_id );

                if ( isset( $_POST['tpp_national_id'] ) ) {
                        update_user_meta( $user_id, 'tpp_national_id', TPP_Jalali::digits_en( sanitize_text_field( wp_unslash( $_POST['tpp_national_id'] ) ) ) );
                }
                if ( isset( $_POST['tpp_mobile'] ) ) {
                        update_user_meta( $user_id, 'tpp_mobile', TPP_Jalali::digits_en( sanitize_text_field( wp_unslash( $_POST['tpp_mobile'] ) ) ) );
                }

                $profile = tpp_get_profile( $user_id );
                foreach ( tpp_get_profile_fields() as $f ) {
                        if ( ! isset( $_POST['tpp_profile'][ $f->field_key ] ) ) {
                                continue;
                        }
                        $raw = wp_unslash( $_POST['tpp_profile'][ $f->field_key ] );
                        $profile[ $f->field_key ] = ( 'number' === $f->field_type ) ? tpp_parse_number( $raw ) : sanitize_text_field( $raw );
                }
                $profile['centers'] = isset( $_POST['tpp_centers'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['tpp_centers'] ) ) : array();

                $bank_accounts = array();
                foreach ( tpp_get_banks() as $b ) {
                        if ( isset( $_POST['tpp_bank'][ $b->id ] ) ) {
                                $bank_accounts[ $b->id ] = array(
                                        'account' => TPP_Jalali::digits_en( sanitize_text_field( wp_unslash( (isset($_POST['tpp_bank'][ $b->id ]['account'] )?$_POST['tpp_bank'][ $b->id ]['account'] : '' )) ) ),
                                        'sheba'   => strtoupper( TPP_Jalali::digits_en( sanitize_text_field( wp_unslash( (isset($_POST['tpp_bank'][ $b->id ]['sheba'] )?$_POST['tpp_bank'][ $b->id ]['sheba'] : '' )) ) ) ),
                                        'card'    => TPP_Jalali::digits_en( sanitize_text_field( wp_unslash( (isset($_POST['tpp_bank'][ $b->id ]['card'] )?$_POST['tpp_bank'][ $b->id ]['card'] : '' )) ) ),
                                );
                        }
                }
                $profile['bank_accounts'] = $bank_accounts;
                tpp_save_profile( $user_id, $profile );
        }
}
}
// TPP_SALARY GUARD END
