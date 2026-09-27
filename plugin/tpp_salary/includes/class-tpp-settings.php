<?php
/**
 * صفحه تنظیمات افزونه — پیش‌فرض‌ها، فرمول‌ها، مدیریت فیلدها، لوگو، بکاپ خودکار
 *
 * @package TPP_Salary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class TPP_Settings
 */
if ( ! class_exists( 'TPP_Settings' ) ) {
	// TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TPP_Settings {

        /**
         * مقداردهی اولیه
         *
         * @return void
         */
        public static function init() {
                add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
                add_action( 'admin_post_tpp_save_settings', array( __CLASS__, 'save' ) );
                add_action( 'admin_post_tpp_save_field', array( __CLASS__, 'save_field' ) );
                add_action( 'admin_post_tpp_toggle_field', array( __CLASS__, 'toggle_field' ) );
        }

        /**
         * افزودن به منوی مدیریت
         *
         * @return void
         */
        public static function menu() {
                add_submenu_page(
                        'tpp-salary',
                        'تنظیمات حقوق و دستمزد',
                        'تنظیمات',
                        'tpp_manage_salary',
                        'tpp-settings',
                        array( __CLASS__, 'render' )
                );
        }

        /**
         * ذخیره تنظیمات
         *
         * @return void
         */
        public static function save() {
                if ( ! current_user_can( 'manage_options' ) ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_save_settings' );

                $settings                 = tpp_get_settings();
                $settings['company_name'] = sanitize_text_field( wp_unslash( (isset($_POST['company_name'] )?$_POST['company_name'] : '' )) );
                $settings['currency']     = sanitize_text_field( wp_unslash( (isset($_POST['currency'] )?$_POST['currency'] : 'ریال' )) );
                $settings['per_page_a4']  = max( 1, min( 12, (int) ( (isset($_POST['per_page_a4'] )?$_POST['per_page_a4'] : 4 )) ) );
                $settings['digits_fa']    = empty( $_POST['digits_fa'] ) ? 0 : 1;
                $settings['logo_id']      = (int) ( (isset($_POST['logo_id'] )?$_POST['logo_id'] : 0 ));

                // مقادیر پیش‌فرض فیلدهای پروفایل.
                $defaults = array();
                foreach ( tpp_get_profile_fields() as $f ) {
                        $raw             = wp_unslash( (isset($_POST[ 'default_' . $f->field_key ] )?$_POST[ 'default_' . $f->field_key ] : '' ));
                        $defaults[ $f->field_key ] = ( 'number' === $f->field_type ) ? tpp_parse_number( $raw ) : sanitize_text_field( $raw );
                }
                $settings['defaults'] = $defaults;

                // فرمول‌ها.
                $formulas = array();
                foreach ( array( 'gross', 'insurable', 'net' ) as $k ) {
                        $f = wp_unslash( (isset($_POST[ 'formula_' . $k ] )?$_POST[ 'formula_' . $k ] : '' ));
                        $v = TPP_Formula::validate( $f );
                        if ( is_wp_error( $v ) ) {
                                add_settings_error( 'tpp', 'formula_' . $k, 'فرمول «' . $k . '» نامعتبر است: ' . $v->get_error_message() );
                                $formulas[ $k ] = $settings['formulas'][ $k ];
                        } else {
                                $formulas[ $k ] = sanitize_text_field( $f );
                        }
                }
                $settings['formulas'] = $formulas;

                // بکاپ خودکار.
                $settings['backup'] = array(
                        'daily'     => empty( $_POST['backup_daily'] ) ? 0 : 1,
                        'weekly'    => empty( $_POST['backup_weekly'] ) ? 0 : 1,
                        'monthly'   => empty( $_POST['backup_monthly'] ) ? 0 : 1,
                        'yearly'    => empty( $_POST['backup_yearly'] ) ? 0 : 1,
                        'retention' => max( 1, min( 120, (int) ( (isset($_POST['backup_retention'] )?$_POST['backup_retention'] : 24 )) ) ),
                );

                update_option( 'tpp_settings', $settings );
                TPP_Backup::reschedule();

                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-settings', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
                exit;
        }

        /**
         * افزودن/ویرایش فیلد فیش حقوقی
         *
         * @return void
         */
        public static function save_field() {
                if ( ! current_user_can( 'manage_options' ) ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_save_field' );

                global $wpdb;
                $id    = (int) ( (isset($_POST['field_id'] )?$_POST['field_id'] : 0 ));
                $key   = sanitize_key( wp_unslash( (isset($_POST['field_key'] )?$_POST['field_key'] : '' )) );
                $label = sanitize_text_field( wp_unslash( (isset($_POST['label'] )?$_POST['label'] : '' )) );
                if ( ! $key || ! $label ) {
                        wp_die( 'کلید و عنوان فیلد الزامی است' );
                }
                $calculated = empty( $_POST['is_calculated'] ) ? 0 : 1;
                $formula    = sanitize_text_field( wp_unslash( (isset($_POST['formula'] )?$_POST['formula'] : '' )) );
                if ( $calculated ) {
                        $v = TPP_Formula::validate( $formula );
                        if ( is_wp_error( $v ) ) {
                                wp_die( 'فرمول نامعتبر: ' . esc_html( $v->get_error_message() ) );
                        }
                }
                $data = array(
                        'field_key'     => $key,
                        'label'         => $label,
                        'field_type'    => in_array( (isset($_POST['field_type'] )?$_POST['field_type'] : ''), array( 'number', 'text' ), true ) ? sanitize_key( $_POST['field_type'] ) : 'number',
                        'default_value' => sanitize_text_field( wp_unslash( (isset($_POST['default_value'] )?$_POST['default_value'] : '' )) ),
                        'formula'       => $calculated ? $formula : '',
                        'is_calculated' => $calculated,
                        'is_negative'   => empty( $_POST['is_negative'] ) ? 0 : 1,
                        'show_in_payslip' => empty( $_POST['show_in_payslip'] ) ? 0 : 1,
                        'sort_order'    => (int) ( (isset($_POST['sort_order'] )?$_POST['sort_order'] : 0 )),
                        'is_system'     => 0,
                        'is_active'     => 1,
                );
                if ( $id ) {
                        // فیلدهای سیستمی فقط فرمول‌شان قابل ویرایش است.
                        $system = (int) $wpdb->get_var( $wpdb->prepare( "SELECT is_system FROM {$wpdb->prefix}tpp_salary_fields WHERE id = %d", $id ) ); // phpcs:ignore
                        if ( $system ) {
                                unset( $data['field_key'] );
                        }
                        $wpdb->update( $wpdb->prefix . 'tpp_salary_fields', $data, array( 'id' => $id ) ); // phpcs:ignore
                } else {
                        $data['is_system'] = 0;
                        $wpdb->insert( $wpdb->prefix . 'tpp_salary_fields', $data ); // phpcs:ignore
                }
                do_action( 'tpp_fields_changed' );
                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-settings', 'tab' => 'fields', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
                exit;
        }

        /**
         * فعال/غیرفعال کردن یا حذف فیلد
         *
         * @return void
         */
        public static function toggle_field() {
                if ( ! current_user_can( 'manage_options' ) ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_toggle_field' );
                global $wpdb;
                $id     = (int) ( (isset($_GET['field_id'] )?$_GET['field_id'] : 0 ));
                $action = sanitize_key( (isset($_GET['field_action'] )?$_GET['field_action'] : '' ));
                $system = (int) $wpdb->get_var( $wpdb->prepare( "SELECT is_system FROM {$wpdb->prefix}tpp_salary_fields WHERE id = %d", $id ) ); // phpcs:ignore
                if ( 'delete' === $action && ! $system ) {
                        $wpdb->delete( $wpdb->prefix . 'tpp_salary_fields', array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore
                        do_action( 'tpp_fields_changed' );
                } elseif ( 'toggle' === $action ) {
                        $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}tpp_salary_fields SET is_active = 1 - is_active WHERE id = %d", $id ) ); // phpcs:ignore
                        do_action( 'tpp_fields_changed' );
                }
                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-settings', 'tab' => 'fields' ), admin_url( 'admin.php' ) ) );
                exit;
        }

        /**
         * نمایش صفحه تنظیمات
         *
         * @return void
         */
        public static function render() {
                if ( ! current_user_can( 'manage_options' ) ) {
                        wp_die( 'دسترسی غیرمجاز — تنظیمات فقط برای مدیرکل' );
                }
                $settings = tpp_get_settings();
                $tab      = sanitize_key( (isset($_GET['tab'] )?$_GET['tab'] : 'general' ));
                ?>
                <div class="wrap tpp-wrap" dir="rtl">
                        <h1>تنظیمات حقوق و دستمزد</h1>
                        <?php if ( isset( $_GET['updated'] ) ) : ?>
                                <div class="notice notice-success"><p>ذخیره شد.</p></div>
                        <?php endif; ?>
                        <nav class="nav-tab-wrapper">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpp-settings&tab=general' ) ); ?>" class="nav-tab <?php echo 'general' === $tab ? 'nav-tab-active' : ''; ?>">عمومی و پیش‌فرض‌ها</a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpp-settings&tab=formulas' ) ); ?>" class="nav-tab <?php echo 'formulas' === $tab ? 'nav-tab-active' : ''; ?>">فرمول‌ها</a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpp-settings&tab=fields' ) ); ?>" class="nav-tab <?php echo 'fields' === $tab ? 'nav-tab-active' : ''; ?>">فیلدهای فیش حقوقی</a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpp-settings&tab=samples' ) ); ?>" class="nav-tab <?php echo 'samples' === $tab ? 'nav-tab-active' : ''; ?>">فایل‌های نمونه</a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpp-settings&tab=backup' ) ); ?>" class="nav-tab <?php echo 'backup' === $tab ? 'nav-tab-active' : ''; ?>">بکاپ خودکار</a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpp-settings&tab=system' ) ); ?>" class="nav-tab <?php echo 'system' === $tab ? 'nav-tab-active' : ''; ?>">وضعیت سیستم</a>
                        </nav>

                        <?php if ( 'general' === $tab ) : ?>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                        <?php wp_nonce_field( 'tpp_save_settings' ); ?>
                                        <input type="hidden" name="action" value="tpp_save_settings">
                                        <table class="form-table" role="presentation">
                                                <tr>
                                                        <th>نام شرکت</th>
                                                        <td><input type="text" class="regular-text" name="company_name" value="<?php echo esc_attr( $settings['company_name'] ); ?>"></td>
                                                </tr>
                                                <tr>
                                                        <th>لوگوی شرکت (در سربرگ PDFها)</th>
                                                        <td>
                                                                <input type="hidden" name="logo_id" id="tpp_logo_id" value="<?php echo esc_attr( $settings['logo_id'] ); ?>">
                                                                <span id="tpp_logo_preview"><?php
                                                                if ( $settings['logo_id'] ) {
                                                                        echo wp_get_attachment_image( (int) $settings['logo_id'], 'thumbnail' );
                                                                }
                                                                ?></span><br>
                                                                <button type="button" class="button" id="tpp_pick_logo">انتخاب لوگو</button>
                                                                <button type="button" class="button" id="tpp_clear_logo">حذف</button>
                                                        </td>
                                                </tr>
                                                <tr>
                                                        <th>واحد پول</th>
                                                        <td><input type="text" class="regular-text" name="currency" value="<?php echo esc_attr( $settings['currency'] ); ?>"></td>
                                                </tr>
                                                <tr>
                                                        <th>تعداد اسامی در هر صفحه A4 (گزارش لیست حقوق)</th>
                                                        <td><input type="number" min="1" max="12" name="per_page_a4" value="<?php echo esc_attr( $settings['per_page_a4'] ); ?>"> <span class="description">خروجی PDF گزارش به تناسب این تعداد ستون در هر صفحه تولید می‌شود.</span></td>
                                                </tr>
                                                <tr>
                                                        <th>نمایش ارقام به صورت فارسی در PDF</th>
                                                        <td><input type="checkbox" name="digits_fa" value="1" <?php checked( ! empty( $settings['digits_fa'] ) ); ?>></td>
                                                </tr>
                                        </table>
                                        <h2>مقادیر پیش‌فرض فیلدهای کارمند</h2>
                                        <p class="description">این مقادیر به صورت خودکار در پروفایل کارمندان جدید و صفحه ثبت حقوق استفاده می‌شود. تغییر آن‌ها هیچ تغییری در فیش‌های ثبت‌شده قبلی ایجاد نمی‌کند.</p>
                                        <table class="widefat striped" style="max-width:800px">
                                                <thead><tr><th>فیلد</th><th>مقدار پیش‌فرض</th></tr></thead>
                                                <tbody>
                                                <?php foreach ( tpp_get_profile_fields() as $f ) : ?>
                                                        <tr>
                                                                <td><?php echo esc_html( $f->label ); ?></td>
                                                                <td>
                                                                        <?php $val = (isset($settings['defaults'][ $f->field_key ] )?$settings['defaults'][ $f->field_key ] : $f->default_value); ?>
                                                                        <input type="<?php echo esc_attr( $f->field_type ); ?>" name="default_<?php echo esc_attr( $f->field_key ); ?>" value="<?php echo esc_attr( $val ); ?>" class="regular-text">
                                                                </td>
                                                        </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                        </table>
                                        <?php submit_button( 'ذخیره تنظیمات' ); ?>
                                </form>
                                <?php wp_enqueue_media(); ?>
                                <script>
                                jQuery(function($){
                                        $('#tpp_pick_logo').on('click', function(e){
                                                e.preventDefault();
                                                var frame = wp.media({ title: 'انتخاب لوگو', multiple: false, library: { type: 'image' } });
                                                frame.on('select', function(){
                                                        var att = frame.state().get('selection').first().toJSON();
                                                        $('#tpp_logo_id').val(att.id);
                                                        $('#tpp_logo_preview').html('<img src="'+(att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url)+'" style="max-width:150px">');
                                                });
                                                frame.open();
                                        });
                                        $('#tpp_clear_logo').on('click', function(){
                                                $('#tpp_logo_id').val(''); $('#tpp_logo_preview').empty();
                                        });
                                });
                                </script>

                        <?php elseif ( 'formulas' === $tab ) : ?>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                        <?php wp_nonce_field( 'tpp_save_settings' ); ?>
                                        <input type="hidden" name="action" value="tpp_save_settings">
                                        <p class="description">توکن‌های فیلد داخل آکولاد نوشته می‌شوند مانند <code>{base_salary}</code> — عملگرهای مجاز: <code>+ - * / ( )</code>. فهرست کلیدها در تب «فیلدهای فیش حقوقی» قابل مشاهده است.</p>
                                        <table class="form-table" role="presentation">
                                                <tr>
                                                        <th>فرمول حقوق ناخالص</th>
                                                        <td><textarea name="formula_gross" class="large-text code" rows="3"><?php echo esc_textarea( $settings['formulas']['gross'] ); ?></textarea></td>
                                                </tr>
                                                <tr>
                                                        <th>فرمول حقوق مشمول بیمه (حالت «محاسبه با فرمول»)</th>
                                                        <td><textarea name="formula_insurable" class="large-text code" rows="3"><?php echo esc_textarea( $settings['formulas']['insurable'] ); ?></textarea></td>
                                                </tr>
                                                <tr>
                                                        <th>فرمول حقوق خالص پرداختی</th>
                                                        <td><textarea name="formula_net" class="large-text code" rows="3"><?php echo esc_textarea( $settings['formulas']['net'] ); ?></textarea></td>
                                                </tr>
                                        </table>
                                        <?php submit_button( 'ذخیره فرمول‌ها' ); ?>
                                </form>

                        <?php elseif ( 'fields' === $tab ) : ?>
                                <?php
                                $edit_id = (int) ( (isset($_GET['edit'] )?$_GET['edit'] : 0 ));
                                global $wpdb;
                                $editing = $edit_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tpp_salary_fields WHERE id = %d", $edit_id ) ) : null; // phpcs:ignore
                                ?>
                                <h2>افزودن / ویرایش فیلد</h2>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;padding:16px;border:1px solid #ccd0d4;max-width:900px">
                                        <?php wp_nonce_field( 'tpp_save_field' ); ?>
                                        <input type="hidden" name="action" value="tpp_save_field">
                                        <input type="hidden" name="field_id" value="<?php echo esc_attr( $editing ? $editing->id : 0 ); ?>">
                                        <table class="form-table" role="presentation">
                                                <tr><th>کلید (انگلیسی)</th><td><input type="text" name="field_key" value="<?php echo esc_attr( $editing ? $editing->field_key : '' ); ?>" <?php echo ( $editing && $editing->is_system ) ? 'readonly' : ''; ?> required></td></tr>
                                                <tr><th>عنوان</th><td><input type="text" name="label" value="<?php echo esc_attr( $editing ? $editing->label : '' ); ?>" required></td></tr>
                                                <tr><th>نوع</th><td>
                                                        <select name="field_type">
                                                                <option value="number" <?php selected( $editing ? $editing->field_type : '', 'number' ); ?>>عدد</option>
                                                                <option value="text" <?php selected( $editing ? $editing->field_type : '', 'text' ); ?>>متن</option>
                                                        </select>
                                                </td></tr>
                                                <tr><th>مقدار پیش‌فرض</th><td><input type="text" name="default_value" value="<?php echo esc_attr( $editing ? $editing->default_value : '' ); ?>"></td></tr>
                                                <tr><th>محاسبه با فرمول</th><td>
                                                        <label><input type="checkbox" name="is_calculated" value="1" <?php checked( $editing ? $editing->is_calculated : 0 ); ?>> فیلد محاسباتی</label><br>
                                                        <input type="text" name="formula" class="large-text code" value="<?php echo esc_attr( $editing ? $editing->formula : '' ); ?>" placeholder="{daily_wage}*{work_days}">
                                                </td></tr>
                                                <tr><th>نمایش به صورت منفی</th><td><input type="checkbox" name="is_negative" value="1" <?php checked( $editing ? $editing->is_negative : 0 ); ?>></td></tr>
                                                <tr><th>نمایش در فیش حقوقی</th><td><input type="checkbox" name="show_in_payslip" value="1" <?php checked( $editing ? $editing->show_in_payslip : 1 ); ?>></td></tr>
                                                <tr><th>ترتیب</th><td><input type="number" name="sort_order" value="<?php echo esc_attr( $editing ? $editing->sort_order : 0 ); ?>"></td></tr>
                                        </table>
                                        <?php submit_button( $editing ? 'ذخیره تغییرات فیلد' : 'افزودن فیلد' ); ?>
                                </form>
                                <h2>فیلدهای فعال</h2>
                                <table class="widefat striped">
                                        <thead><tr>
                                                <th>#</th><th>کلید</th><th>عنوان</th><th>نوع</th><th>فرمول</th><th>پروفایل</th><th>محاسباتی</th><th>وضعیت</th><th>عملیات</th>
                                        </tr></thead>
                                        <tbody>
                                        <?php
                                        global $wpdb;
                                        $all_fields = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}tpp_salary_fields ORDER BY sort_order ASC, id ASC" ); // phpcs:ignore
                                        foreach ( $all_fields as $f ) :
                                                $base = admin_url( 'admin.php?page=tpp-settings&tab=fields' );
                                                ?>
                                                <tr style="<?php echo $f->is_active ? '' : 'opacity:.5'; ?>">
                                                        <td><?php echo (int) $f->sort_order; ?></td>
                                                        <td><code><?php echo esc_html( $f->field_key ); ?></code></td>
                                                        <td><?php echo esc_html( $f->label ); ?> <?php echo $f->is_system ? '<span class="description">(سیستمی)</span>' : ''; ?></td>
                                                        <td><?php echo esc_html( $f->field_type ); ?></td>
                                                        <td><code dir="ltr"><?php echo esc_html( $f->formula ); ?></code></td>
                                                        <td><?php echo $f->is_profile ? 'بله' : '—'; ?></td>
                                                        <td><?php echo $f->is_calculated ? 'بله' : '—'; ?></td>
                                                        <td><?php echo $f->is_active ? 'فعال' : 'غیرفعال'; ?></td>
                                                        <td>
                                                                <a class="button button-small" href="<?php echo esc_url( add_query_arg( 'edit', $f->id, $base ) ); ?>">ویرایش</a>
                                                                <?php $tu = wp_nonce_url( add_query_arg( array( 'field_id' => $f->id, 'field_action' => 'toggle' ), admin_url( 'admin-post.php?action=tpp_toggle_field' ) ), 'tpp_toggle_field' ); ?>
                                                                <a class="button button-small" href="<?php echo esc_url( $tu ); ?>"><?php echo $f->is_active ? 'غیرفعال' : 'فعال'; ?></a>
                                                                <?php if ( ! $f->is_system ) :
                                                                        $du = wp_nonce_url( add_query_arg( array( 'field_id' => $f->id, 'field_action' => 'delete' ), admin_url( 'admin-post.php?action=tpp_toggle_field' ) ), 'tpp_toggle_field' );
                                                                        ?>
                                                                        <a class="button button-small" style="color:#b32d2e" href="<?php echo esc_url( $du ); ?>" onclick="return confirm('حذف شود؟')">حذف</a>
                                                                <?php endif; ?>
                                                        </td>
                                                </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                </table>

                        <?php elseif ( 'backup' === $tab ) : ?>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                        <?php wp_nonce_field( 'tpp_save_settings' ); ?>
                                        <input type="hidden" name="action" value="tpp_save_settings">
                                        <table class="form-table" role="presentation">
                                                <tr><th>بکاپ روزانه</th><td><input type="checkbox" name="backup_daily" value="1" <?php checked( ! empty( $settings['backup']['daily'] ) ); ?>></td></tr>
                                                <tr><th>بکاپ هفتگی</th><td><input type="checkbox" name="backup_weekly" value="1" <?php checked( ! empty( $settings['backup']['weekly'] ) ); ?>></td></tr>
                                                <tr><th>بکاپ ماهانه</th><td><input type="checkbox" name="backup_monthly" value="1" <?php checked( ! empty( $settings['backup']['monthly'] ) ); ?>></td></tr>
                                                <tr><th>بکاپ سالانه</th><td><input type="checkbox" name="backup_yearly" value="1" <?php checked( ! empty( $settings['backup']['yearly'] ) ); ?>></td></tr>
                                                <tr><th>نگهداری آخرین (تعداد)</th><td><input type="number" name="backup_retention" min="1" max="120" value="<?php echo esc_attr( $settings['backup']['retention'] ); ?>"></td></tr>
                                        </table>
                                        <?php submit_button( 'ذخیره زمان‌بندی بکاپ' ); ?>
                                </form>

                        <?php elseif ( 'samples' === $tab ) : ?>
                                <div class="tpp-scope tpp-panel">
                                        <h2>فایل‌های نمونه همگام با فیلدها</h2>
                                        <p class="description">این فایل‌ها در لحظه از روی فیلدهای فعال فعلی سیستم ساخته می‌شوند؛ اگر فیلدی اضافه یا حذف کرده باشید، ستون‌های فایل نمونه همان لحظه مطابق می‌شوند. سطر اول عناوین است و سطرهای بعدی صرفاً مثال هستند (در ورود گروهی سطرهای مثال را حذف کنید).</p>
                                        <p>
                                                <a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tpp_sample_employees' ), 'tpp_sample_employees' ) ); ?>">دانلود نمونه کارمندان (xlsx)</a>
                                                &nbsp;
                                                <a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tpp_sample_records' ), 'tpp_sample_records' ) ); ?>">دانلود نمونه رکوردهای حقوق (xlsx)</a>
                                        </p>
                                        <h3>پیش‌نمایش ستون‌های فعلی</h3>
                                        <p><strong>نمونه کارمندان:</strong></p>
                                        <p><code dir="rtl"><?php echo esc_html( implode( ' | ', TPP_Samples::employees_headers() ) ); ?></code></p>
                                        <p><strong>نمونه رکوردهای حقوق:</strong></p>
                                        <p><code dir="rtl"><?php echo esc_html( implode( ' | ', TPP_Samples::records_headers() ) ); ?></code></p>
                                </div>

                        <?php elseif ( 'system' === $tab ) : ?>
                                <?php
                                global $wpdb;
                                $tables = array(
                                        $wpdb->prefix . 'tpp_centers',
                                        $wpdb->prefix . 'tpp_banks',
                                        $wpdb->prefix . 'tpp_salary_fields',
                                        $wpdb->prefix . 'tpp_salary_records',
                                        $wpdb->prefix . 'tpp_backups',
                                );
                                $checks = array(
                                        'نسخه PHP'      => array( PHP_VERSION, version_compare( PHP_VERSION, '7.0', '>=' ) ),
                                        'نسخه وردپرس'    => array( get_bloginfo( 'version' ), version_compare( get_bloginfo( 'version' ), '5.8', '>=' ) ),
                                        'اکستنشن mbstring' => array( extension_loaded( 'mbstring' ) ? 'فعال' : 'غیرفعال (پولی‌فیل داخلی استفاده می‌شود)', true ),
                                        'اکستنشن zip (ZipArchive)' => array( class_exists( 'ZipArchive' ) ? 'فعال' : 'غیرفعال — خروجی ZIP و ورود اکسل کار نمی‌کند', class_exists( 'ZipArchive' ) ),
                                        'اکستنشن gd'     => array( extension_loaded( 'gd' ) ? 'فعال' : 'غیرفعال (لازم نیست)', true ),
                                        'حد حافظه'        => array( (string) ini_get( 'memory_limit' ), true ),
                                        'پوشه آپلود قابل نوشتن' => array( wp_is_writable( wp_upload_dir()['basedir'] ) ? 'بله' : 'خیر', wp_is_writable( wp_upload_dir()['basedir'] ) ),
                                        'پوشه فونت پلاگین قابل نوشتن' => array( wp_is_writable( TPP_SALARY_DIR . 'lib/tfpdf/font/unifont' ) ? 'بله' : 'خیر (فقط سرعت کش PDF کم می‌شود)', true ),
                                );
                                foreach ( $tables as $t ) {
                                        $exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t );
                                        $checks[ 'جدول ' . $t ] = array( $exists ? 'موجود' : 'ناموجود!', $exists );
                                }
                                ?>
                                <div class="tpp-scope tpp-panel">
                                        <h2>وضعیت سیستم و سازگاری هاست</h2>
                                        <p class="description">اگر افزونه فعال نمی‌شود یا مشکلی در اجرا دارید، این بخش را بررسی کنید و در صورت قرمز بودن موارد، با پشتیبانی هاست تماس بگیرید.</p>
                                        <table class="widefat striped" style="max-width:900px">
                                                <thead><tr><th>موارد</th><th>مقدار</th><th>وضعیت</th></tr></thead>
                                                <tbody>
                                                <?php foreach ( $checks as $label => $c ) : ?>
                                                        <tr>
                                                                <td><strong><?php echo esc_html( $label ); ?></strong></td>
                                                                <td><?php echo esc_html( $c[0] ); ?></td>
                                                                <td>
                                                                        <span class="tpp-badge <?php echo $c[1] ? 'tpp-badge-ok' : 'tpp-badge-bad'; ?>"><?php echo $c[1] ? '✓ سالم' : '✗ مشکل'; ?></span>
                                                                </td>
                                                        </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                        </table>
                                        <p class="description">راهنمای خطای «فعال‌سازی ناموفق»: در wp-config.php خطوط <code>define('WP_DEBUG', true);</code> و <code>define('WP_DEBUG_LOG', true);</code> را اضافه کنید، دوباره فعال‌سازی را امتحان کنید و سپس فایل <code>wp-content/debug.log</code> را بررسی کنید.</p>
                                </div>

                        <?php endif; ?>
                </div>
                <?php
        }
}
}
// TPP_SALARY GUARD END
