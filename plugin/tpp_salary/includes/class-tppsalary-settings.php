<?php
/**
 * صفحه تنظیمات افزونه — پیش‌فرض‌ها، فرمول‌ها، مدیریت فیلدها، لوگو، بکاپ خودکار
 *
 * @package TppSalary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class TppSalary_Settings
 */
if ( ! class_exists( 'TppSalary_Settings' ) ) {
        // TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TppSalary_Settings {

        /**
         * مقداردهی اولیه
         *
         * @return void
         */
        public static function init() {
                add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
                add_action( 'admin_post_tpp_salary_save_settings', array( __CLASS__, 'save' ) );
                add_action( 'admin_post_tpp_salary_save_field', array( __CLASS__, 'save_field' ) );
                add_action( 'admin_post_tpp_salary_toggle_field', array( __CLASS__, 'toggle_field' ) );
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
                        'tpp_salary_manage',
                        'tpp-salary-settings',
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
                check_admin_referer( 'tpp_salary_save_settings' );

                self::apply_section_save( $_POST );
                TppSalary_Backup::reschedule();

                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-salary-settings', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
                exit;
        }

        /**
         * اعمال ذخیره تنظیمات از آرایه POST — نسخه 1.7.3 (قابل‌تست)
         *
         * هر تب فرم خودش را دارد؛ پیش‌تر همه تب‌ها به هندلر ذخیره ارسال می‌شدند
         * و هندلر «همه» بخش‌ها را از POST بازسازی می‌کرد — بنابراین ذخیره تب
         * «بکاپ خودکار» (یا «فرمول‌ها») نام شرکت، لوگو، واحد پول، پیش‌فرض‌ها و
         * فرمول‌ها را پاک می‌کرد (باگ گزارش‌شده کاربر). اکنون فقط بخش اعلام‌شده
         * در فرم (tpp_section) به‌روزرسانی می‌شود و بقیه تنظیمات دست‌نخورده
         * می‌مانند. مقدار all = رفتار قدیمی (همه بخش‌ها) برای سازگاری.
         *
         * @param array $post آرایه POST (معمولاً $_POST).
         * @return void
         */
        public static function apply_section_save( $post ) {
                $post     = is_array( $post ) ? $post : array();
                $settings = tpp_salary_get_settings();

                $section = sanitize_key( wp_unslash( (isset($post['tpp_section'] )?$post['tpp_section'] : 'all' )) );

                if ( in_array( $section, array( 'all', 'general' ), true ) ) {
                        $settings['company_name'] = sanitize_text_field( wp_unslash( (isset($post['company_name'] )?$post['company_name'] : '' )) );
                        $settings['currency']     = sanitize_text_field( wp_unslash( (isset($post['currency'] )?$post['currency'] : 'ریال' )) );
                        $settings['per_page_a4']  = max( 1, min( 12, (int) ( (isset($post['per_page_a4'] )?$post['per_page_a4'] : 4 )) ) );
                        /* نسخه 1.6.1: تعداد ردیف در هر صفحه برای فهرست‌ها و نتایج جستجو */
                        $settings['per_page_list'] = max( 1, min( 200, (int) ( (isset($post['per_page_list'] )?$post['per_page_list'] : 20 )) ) );
                        /* نسخه 1.7.1: همه اعداد انگلیسی — گزینه ارقام فارسی حذف شد (کلید برای سازگاری صفر می‌ماند). */
                        $settings['digits_fa']    = 0;
                        $settings['logo_id']      = (int) ( (isset($post['logo_id'] )?$post['logo_id'] : 0 ));
                        /*
                         * نسخه 1.4.1 — حذف افزونه: پیش‌فرض «حفظ داده‌ها» است؛ حذف کامل فقط
                         * با تیک صریح این گزینه. (قبلاً برعکس بود و با حذف افزونه، داده‌ها
                         * به‌صورت پیش‌فرض از بین می‌رفتند.)
                         */
                        update_option( 'tpp_salary_delete_data', empty( $post['delete_data'] ) ? '0' : '1', false );
                        delete_option( 'tpp_salary_keep_data' );

                        // مقادیر پیش‌فرض فیلدهای پروفایل.
                        $defaults = array();
                        foreach ( tpp_salary_get_profile_fields() as $f ) {
                                $raw             = wp_unslash( (isset($post[ 'default_' . $f->field_key ] )?$post[ 'default_' . $f->field_key ] : '' ));
                                $defaults[ $f->field_key ] = ( 'number' === $f->field_type ) ? tpp_salary_parse_number( $raw ) : sanitize_text_field( $raw );
                        }
                        $settings['defaults'] = $defaults;
                }

                if ( in_array( $section, array( 'all', 'formulas' ), true ) ) {
                        // فرمول‌ها.
                        $formulas = array();
                        foreach ( array( 'gross', 'insurable', 'net' ) as $k ) {
                                $f = wp_unslash( (isset($post[ 'formula_' . $k ] )?$post[ 'formula_' . $k ] : '' ));
                                $v = TppSalary_Formula::validate( $f );
                                if ( is_wp_error( $v ) ) {
                                        add_settings_error( 'tpp', 'formula_' . $k, 'فرمول «' . $k . '» نامعتبر است: ' . $v->get_error_message() );
                                        $formulas[ $k ] = $settings['formulas'][ $k ];
                                } else {
                                        $formulas[ $k ] = sanitize_text_field( $f );
                                }
                        }
                        $settings['formulas'] = $formulas;
                }

                if ( in_array( $section, array( 'all', 'backup' ), true ) ) {
                        // بکاپ خودکار.
                        $settings['backup'] = array(
                                'daily'     => empty( $post['backup_daily'] ) ? 0 : 1,
                                'weekly'    => empty( $post['backup_weekly'] ) ? 0 : 1,
                                'monthly'   => empty( $post['backup_monthly'] ) ? 0 : 1,
                                'yearly'    => empty( $post['backup_yearly'] ) ? 0 : 1,
                                'retention' => max( 1, min( 120, (int) ( (isset($post['backup_retention'] )?$post['backup_retention'] : 24 )) ) ),
                        );
                }

                update_option( 'tpp_salary_settings', $settings );
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
                check_admin_referer( 'tpp_salary_save_field' );

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
                        $v = TppSalary_Formula::validate( $formula );
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
                        /* مفهوم «ذاتاً منفی» حذف شد — علامت منفی خود مقدار ملاک است. */
                        'is_negative'   => 0,
                        'is_profile'    => empty( $_POST['is_profile'] ) ? 0 : 1,
                        'in_record'     => empty( $_POST['in_record'] ) ? 0 : 1,
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
                do_action( 'tpp_salary_fields_changed' );
                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-salary-settings', 'tab' => 'fields', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
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
                check_admin_referer( 'tpp_salary_toggle_field' );
                global $wpdb;
                $id     = (int) ( (isset($_GET['field_id'] )?$_GET['field_id'] : 0 ));
                $action = sanitize_key( (isset($_GET['field_action'] )?$_GET['field_action'] : '' ));
                $system = (int) $wpdb->get_var( $wpdb->prepare( "SELECT is_system FROM {$wpdb->prefix}tpp_salary_fields WHERE id = %d", $id ) ); // phpcs:ignore
                if ( 'delete' === $action && ! $system ) {
                        $wpdb->delete( $wpdb->prefix . 'tpp_salary_fields', array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore
                        do_action( 'tpp_salary_fields_changed' );
                } elseif ( 'toggle' === $action ) {
                        $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}tpp_salary_fields SET is_active = 1 - is_active WHERE id = %d", $id ) ); // phpcs:ignore
                        do_action( 'tpp_salary_fields_changed' );
                }
                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-salary-settings', 'tab' => 'fields' ), admin_url( 'admin.php' ) ) );
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
                $settings = tpp_salary_get_settings();
                $tab      = sanitize_key( (isset($_GET['tab'] )?$_GET['tab'] : 'general' ));
                ?>
                <div class="wrap tpp-wrap" dir="rtl">
                        <h1>تنظیمات حقوق و دستمزد</h1>
                        <?php if ( isset( $_GET['updated'] ) ) : ?>
                                <div class="notice notice-success"><p>ذخیره شد.</p></div>
                        <?php endif; ?>
                        <nav class="nav-tab-wrapper">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpp-salary-settings&tab=general' ) ); ?>" class="nav-tab <?php echo 'general' === $tab ? 'nav-tab-active' : ''; ?>">عمومی و پیش‌فرض‌ها</a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpp-salary-settings&tab=formulas' ) ); ?>" class="nav-tab <?php echo 'formulas' === $tab ? 'nav-tab-active' : ''; ?>">فرمول‌ها</a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpp-salary-settings&tab=fields' ) ); ?>" class="nav-tab <?php echo 'fields' === $tab ? 'nav-tab-active' : ''; ?>">فیلدهای فیش حقوقی</a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpp-salary-settings&tab=samples' ) ); ?>" class="nav-tab <?php echo 'samples' === $tab ? 'nav-tab-active' : ''; ?>">فایل‌های نمونه</a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpp-salary-settings&tab=backup' ) ); ?>" class="nav-tab <?php echo 'backup' === $tab ? 'nav-tab-active' : ''; ?>">بکاپ خودکار</a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpp-salary-settings&tab=offline' ) ); ?>" class="nav-tab <?php echo 'offline' === $tab ? 'nav-tab-active' : ''; ?>">برنامه آفلاین (API)</a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpp-salary-settings&tab=system' ) ); ?>" class="nav-tab <?php echo 'system' === $tab ? 'nav-tab-active' : ''; ?>">وضعیت سیستم</a>
                        </nav>

                        <?php if ( 'general' === $tab ) : ?>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                        <?php wp_nonce_field( 'tpp_salary_save_settings' ); ?>
                                        <input type="hidden" name="action" value="tpp_salary_save_settings">
                                        <input type="hidden" name="tpp_section" value="general">
                                        <table class="form-table" role="presentation">
                                                <tr>
                                                        <th>نام شرکت</th>
                                                        <td><input type="text" class="regular-text" name="company_name" value="<?php echo esc_attr( $settings['company_name'] ); ?>"></td>
                                                </tr>
                                                <tr>
                                                        <th>لوگوی شرکت (در سربرگ PDFها)</th>
                                                        <td>
                                                                <input type="hidden" name="logo_id" id="tpp_salary_logo_id" value="<?php echo esc_attr( $settings['logo_id'] ); ?>">
                                                                <span id="tpp_salary_logo_preview"><?php
                                                                if ( $settings['logo_id'] ) {
                                                                        echo wp_get_attachment_image( (int) $settings['logo_id'], 'thumbnail' );
                                                                }
                                                                ?></span><br>
                                                                <button type="button" class="button" id="tpp_salary_pick_logo">انتخاب لوگو</button>
                                                                <button type="button" class="button" id="tpp_salary_clear_logo">حذف</button>
                                                        </td>
                                                </tr>
                                                <tr>
                                                        <th>واحد پول</th>
                                                        <td><input type="text" class="regular-text" name="currency" value="<?php echo esc_attr( $settings['currency'] ); ?>"></td>
                                                </tr>
                                                <tr>
                                                        <th>تعداد اسامی در هر صفحه A4 (گزارش لیست حقوق)</th>
                                                        <td><input type="number" min="1" max="12" name="per_page_a4" value="<?php echo esc_attr( $settings['per_page_a4'] ); ?>"> <span class="description">از نسخه 1.7.3 خروجی PDF گزارش همیشه ستون‌ها را تا گنجایش کامل صفحه A4 افقی پر می‌کند (این تنظیم برای صفحه‌بندی جدول وب و شیت‌های اکسل به‌کار می‌رود). همه ردیف‌های حقوق در یک صفحه جا می‌شوند و فیلدهای فقط‌محاسباتی و مقادیر صفر در خروجی چاپی (PDF/اکسل) درج نمی‌شوند.</span></td>
                                                </tr>
                                                <tr>
                                                        <th>تعداد ردیف در هر صفحه (فهرست‌ها و نتایج جستجو)</th>
                                                        <td><input type="number" min="1" max="200" name="per_page_list" value="<?php echo esc_attr( isset( $settings['per_page_list'] ) ? $settings['per_page_list'] : 20 ); ?>"> <span class="description">تعداد ردیف هر صفحه در فهرست کارمندان، حقوق‌های ثبت‌شده، فیش‌های حقوقی و پیوت گزارش (صفحه‌بندی نتایج جستجو).</span></td>
                                                </tr>
                                                <tr>
                                                        <th>ارقام اعداد</th>
                                                        <td><span class="description">نسخه 1.7.1 — همه اعداد در تمام صفحات و خروجی‌ها (PDF/اکسل) با ارقام انگلیسی نمایش داده می‌شوند و این مورد دیگر قابل تغییر نیست.</span></td>
                                                </tr>
                                                <tr>
                                                        <th>حذف کامل اطلاعات هنگام حذف افزونه</th>
                                                        <td>
                                                                <input type="checkbox" name="delete_data" value="1" <?php checked( '1', get_option( 'tpp_salary_delete_data', '0' ) ); ?>>
                                                                <span class="description">به‌صورت پیش‌فرض همه اطلاعات (مراکز، بانک‌ها، فیلدها، رکوردهای حقوق، تنظیمات و پروفایل کارمندان) هنگام حذف افزونه <strong>حفظ می‌شوند</strong> و پس از نصب مجدد در دسترس می‌مانند. فقط اگر این گزینه را فعال کنید، با حذف افزونه همه داده‌ها برای همیشه حذف می‌شوند.</span>
                                                        </td>
                                                </tr>
                                        </table>
                                        <h2>مقادیر پیش‌فرض فیلدهای کارمند</h2>
                                        <p class="description">این مقادیر به صورت خودکار در پروفایل کارمندان جدید و صفحه ثبت حقوق استفاده می‌شود. تغییر آن‌ها هیچ تغییری در فیش‌های ثبت‌شده قبلی ایجاد نمی‌کند.</p>
                                        <table class="widefat striped" style="max-width:800px">
                                                <thead><tr><th>فیلد</th><th>مقدار پیش‌فرض</th></tr></thead>
                                                <tbody>
                                                <?php foreach ( tpp_salary_get_profile_fields() as $f ) : ?>
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
                                        $('#tpp_salary_pick_logo').on('click', function(e){
                                                e.preventDefault();
                                                var frame = wp.media({ title: 'انتخاب لوگو', multiple: false, library: { type: 'image' } });
                                                frame.on('select', function(){
                                                        var att = frame.state().get('selection').first().toJSON();
                                                        $('#tpp_salary_logo_id').val(att.id);
                                                        $('#tpp_salary_logo_preview').html('<img src="'+(att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url)+'" style="max-width:150px">');
                                                });
                                                frame.open();
                                        });
                                        $('#tpp_salary_clear_logo').on('click', function(){
                                                $('#tpp_salary_logo_id').val(''); $('#tpp_salary_logo_preview').empty();
                                        });
                                });
                                </script>

                        <?php elseif ( 'formulas' === $tab ) : ?>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                        <?php wp_nonce_field( 'tpp_salary_save_settings' ); ?>
                                        <input type="hidden" name="action" value="tpp_salary_save_settings">
                                        <input type="hidden" name="tpp_section" value="formulas">
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
                                <!-- نسخه 1.4.1: پس‌زمینه سفید inline حذف شد (دارک‌مود). -->
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tpp-panel" style="max-width:900px">
                                        <?php wp_nonce_field( 'tpp_salary_save_field' ); ?>
                                        <input type="hidden" name="action" value="tpp_salary_save_field">
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
                                                <tr><th>محل نمایش</th><td>
                                                        <label><input type="checkbox" name="is_profile" value="1" <?php checked( $editing ? (int) $editing->is_profile : 0 ); ?>> ثبت در پروفایل کارمند</label> &nbsp;
                                                        <label><input type="checkbox" name="in_record" value="1" <?php checked( $editing ? (int) ( isset( $editing->in_record ) ? $editing->in_record : 1 ) : 1 ); ?>> نمایش در فرم ثبت حقوق</label>
                                                </td></tr>
                                                <tr><th>نمایش در فیش حقوقی</th><td><input type="checkbox" name="show_in_payslip" value="1" <?php checked( $editing ? $editing->show_in_payslip : 1 ); ?>></td></tr>
                                                <tr><th>ترتیب</th><td><input type="number" name="sort_order" value="<?php echo esc_attr( $editing ? $editing->sort_order : 0 ); ?>"></td></tr>
                                        </table>
                                        <?php submit_button( $editing ? 'ذخیره تغییرات فیلد' : 'افزودن فیلد' ); ?>
                                </form>
                                <h2>فیلدهای فعال</h2>
                                <table class="widefat striped">
                                        <thead><tr>
                                                <th>#</th><th>کلید</th><th>عنوان</th><th>نوع</th><th>فرمول</th><th>پروفایل</th><th>فرم ثبت</th><th>محاسباتی</th><th>وضعیت</th><th>عملیات</th>
                                        </tr></thead>
                                        <tbody>
                                        <?php
                                        global $wpdb;
                                        $all_fields = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}tpp_salary_fields ORDER BY sort_order ASC, id ASC" ); // phpcs:ignore
                                        foreach ( $all_fields as $f ) :
                                                $base = admin_url( 'admin.php?page=tpp-salary-settings&tab=fields' );
                                                ?>
                                                <tr style="<?php echo $f->is_active ? '' : 'opacity:.5'; ?>">
                                                        <td><?php echo (int) $f->sort_order; ?></td>
                                                        <td><code><?php echo esc_html( $f->field_key ); ?></code></td>
                                                        <td><?php echo esc_html( $f->label ); ?> <?php echo $f->is_system ? '<span class="description">(سیستمی)</span>' : ''; ?></td>
                                                        <td><?php echo esc_html( $f->field_type ); ?></td>
                                                        <td><code dir="ltr"><?php echo esc_html( $f->formula ); ?></code></td>
                                                        <td><?php echo $f->is_profile ? 'بله' : '—'; ?></td>
                                                        <td><?php echo ( isset( $f->in_record ) ? (int) $f->in_record : 1 ) ? 'بله' : '—'; ?></td>
                                                        <td><?php echo $f->is_calculated ? 'بله' : '—'; ?></td>
                                                        <td><?php echo $f->is_active ? 'فعال' : 'غیرفعال'; ?></td>
                                                        <td>
                                                                <a class="button button-small" href="<?php echo esc_url( add_query_arg( 'edit', $f->id, $base ) ); ?>">ویرایش</a>
                                                                <?php $tu = wp_nonce_url( add_query_arg( array( 'field_id' => $f->id, 'field_action' => 'toggle' ), admin_url( 'admin-post.php?action=tpp_salary_toggle_field' ) ), 'tpp_salary_toggle_field' ); ?>
                                                                <a class="button button-small" href="<?php echo esc_url( $tu ); ?>"><?php echo $f->is_active ? 'غیرفعال' : 'فعال'; ?></a>
                                                                <?php if ( ! $f->is_system ) :
                                                                        $du = wp_nonce_url( add_query_arg( array( 'field_id' => $f->id, 'field_action' => 'delete' ), admin_url( 'admin-post.php?action=tpp_salary_toggle_field' ) ), 'tpp_salary_toggle_field' );
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
                                        <?php wp_nonce_field( 'tpp_salary_save_settings' ); ?>
                                        <input type="hidden" name="action" value="tpp_salary_save_settings">
                                        <input type="hidden" name="tpp_section" value="backup">
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
                                                <a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tpp_salary_sample_employees' ), 'tpp_salary_sample_employees' ) ); ?>">دانلود نمونه کارمندان (xlsx)</a>
                                                &nbsp;
                                                <a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tpp_salary_sample_records' ), 'tpp_salary_sample_records' ) ); ?>">دانلود نمونه رکوردهای حقوق (xlsx)</a>
                                        </p>
                                        <h3>نمونه‌های پرشدهٔ واقعی (تست‌شده)</h3>
                                        <p class="description">این دو فایل با داده واقعی پر شده‌اند و کامل با ورود گروهی تست شده‌اند — می‌توانید ساختار و مقیاس اعداد را بر اساس آن‌ها رعایت کنید.</p>
                                        <p>
                                                <a class="button" href="<?php echo esc_url( plugins_url( 'samples/employees-sample-filled.xlsx', TPP_SALARY_FILE ) ); ?>">نمونه پرشدهٔ کارمندان (۷۶ نفر)</a>
                                                &nbsp;
                                                <a class="button" href="<?php echo esc_url( plugins_url( 'samples/salary-records-sample-filled.xlsx', TPP_SALARY_FILE ) ); ?>">نمونه پرشدهٔ رکوردهای حقوق (۷۶ رکورد)</a>
                                        </p>
                                        <h3>پیش‌نمایش ستون‌های فعلی</h3>
                                        <p><strong>نمونه کارمندان:</strong></p>
                                        <p><code dir="rtl"><?php echo esc_html( implode( ' | ', TppSalary_Samples::employees_headers() ) ); ?></code></p>
                                        <p><strong>نمونه رکوردهای حقوق:</strong></p>
                                        <p><code dir="rtl"><?php echo esc_html( implode( ' | ', TppSalary_Samples::records_headers() ) ); ?></code></p>
                                </div>

                        <?php elseif ( 'system' === $tab ) : ?>
                                <?php
                                global $wpdb;
                                $tables = array(
                                        $wpdb->prefix . 'tpp_salary_centers',
                                        $wpdb->prefix . 'tpp_salary_banks',
                                        $wpdb->prefix . 'tpp_salary_fields',
                                        $wpdb->prefix . 'tpp_salary_records',
                                        $wpdb->prefix . 'tpp_salary_backups',
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

                                /*
                                 * تشخیص خودکار «404 شدن صفحات مدیریت» و خرابی دانلودها:
                                 * وضعیت ماژول‌های بارگذاری‌شده، صفحات منوی ثبت‌شده و دسترسی کاربر.
                                 */
                                $active_mods  = isset( $GLOBALS['tpp_salary_active_modules'] ) ? $GLOBALS['tpp_salary_active_modules'] : array();
                                $all_mods     = array( 'TppSalary_Install', 'TppSalary_Settings', 'TppSalary_Samples', 'TppSalary_Centers', 'TppSalary_Banks', 'TppSalary_Employees', 'TppSalary_Import', 'TppSalary_Salary_Pages', 'TppSalary_Reports', 'TppSalary_Backup', 'TppSalary_Ajax', 'TppSalary_Offline', 'TppSalary_Frontend' );
                                $inactive_mods = array_diff( $all_mods, $active_mods );

                                global $submenu;
                                $expected_pages = array( 'tpp-salary', 'tpp-salary-register', 'tpp-salary-records', 'tpp-salary-centers', 'tpp-salary-banks', 'tpp-salary-employees', 'tpp-salary-import', 'tpp-salary-report', 'tpp-salary-bank-report', 'tpp-salary-payslips', 'tpp-salary-backup', 'tpp-salary-offline', 'tpp-salary-settings' );
                                $registered_slugs = array();
                                if ( isset( $submenu['tpp-salary'] ) && is_array( $submenu['tpp-salary'] ) ) {
                                        foreach ( $submenu['tpp-salary'] as $item ) {
                                                if ( isset( $item[2] ) ) { $registered_slugs[] = $item[2]; }
                                        }
                                }
                                $missing_pages = array_diff( $expected_pages, $registered_slugs );
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

                                        <h2 style="margin-top:22px">تشخیص صفحات مدیریت و دانلودها (برای خطای 404 و فایل معیوب)</h2>
                                        <table class="widefat striped" style="max-width:900px">
                                                <tbody>
                                                <tr>
                                                        <td><strong>ماژول‌های بارگذاری‌شده</strong></td>
                                                        <td><?php echo esc_html( count( $active_mods ) . ' از ' . count( $all_mods ) ); ?></td>
                                                        <td><span class="tpp-badge <?php echo empty( $inactive_mods ) ? 'tpp-badge-ok' : 'tpp-badge-bad'; ?>"><?php echo empty( $inactive_mods ) ? '✓ همه سالم' : '✗ ناقص'; ?></span>
                                                        <?php if ( ! empty( $inactive_mods ) ) : ?><div class="description">نماینده: <?php echo esc_html( implode( '، ', $inactive_mods ) ); ?> — پوشه افزونه را کامل حذف و نسخه جدید را نصب کنید و Apache را ری‌استارت کنید.</div><?php endif; ?></td>
                                                </tr>
                                                <tr>
                                                        <td><strong>صفحات منوی ثبت‌شده</strong></td>
                                                        <td><?php echo esc_html( count( $registered_slugs ) . ' از ' . count( $expected_pages ) ); ?></td>
                                                        <td><span class="tpp-badge <?php echo empty( $missing_pages ) ? 'tpp-badge-ok' : 'tpp-badge-bad'; ?>"><?php echo empty( $missing_pages ) ? '✓ همه ثبت شده‌اند' : '✗ ناقص'; ?></span>
                                                        <?php if ( ! empty( $missing_pages ) ) : ?><div class="description">صفحات غایب: <?php echo esc_html( implode( '، ', $missing_pages ) ); ?> — یعنی همان لینک‌ها 404 می‌شوند؛ علت معمولاً پوشه ناقص/ترکیبی یا افزونه امنیتی سرکوب‌کننده منو است.</div><?php endif; ?></td>
                                                </tr>
                                                <tr>
                                                        <td><strong>دسترسی «tpp_salary_manage» برای کاربر فعلی</strong></td>
                                                        <td><?php echo current_user_can( 'tpp_salary_manage' ) ? 'دارد' : 'ندارد!'; ?></td>
                                                        <td><span class="tpp-badge <?php echo current_user_can( 'tpp_salary_manage' ) ? 'tpp-badge-ok' : 'tpp-badge-bad'; ?>"><?php echo current_user_can( 'tpp_salary_manage' ) ? '✓' : '✗'; ?></span>
                                                        <?php if ( ! current_user_can( 'tpp_salary_manage' ) ) : ?><div class="description">با حساب مدیرکل وارد شوید؛ اگر باز هم «ندارد»، افزونه را یک‌بار غیرفعال و دوباره فعال کنید (دسترسی‌ها هنگام فعال‌سازی ساخته می‌شوند).</div><?php endif; ?></td>
                                                </tr>
                                                <tr>
                                                        <td><strong>نصب افزونه‌های دیگر با پیشوند tpp_</strong></td>
                                                        <td><?php
                                                        $tpp_others = array();
                                                        foreach ( (array) get_option( 'active_plugins', array() ) as $p ) {
                                                                if ( 0 === strpos( basename( dirname( $p ) ), 'tpp_' ) && 0 !== strpos( $p, 'tpp_salary/' ) ) { $tpp_others[] = $p; }
                                                        }
                                                        echo $tpp_others ? esc_html( implode( '، ', $tpp_others ) ) : 'یافت نشد';
                                                        ?></td>
                                                        <td><span class="tpp-badge <?php echo empty( $tpp_others ) ? 'tpp-badge-ok' : 'tpp-badge-ok'; ?>"><?php echo empty( $tpp_others ) ? '✓ فقط همین افزونه' : '⚠ موجود'; ?></span>
                                                        <?php if ( $tpp_others ) : ?><div class="description">این افزونه‌ها هم‌زمان فعال‌اند. نسخه ۱.۳ به بعد با آن‌ها تداخل نام‌گذاری ندارد، اما اگر صفحات یا دانلودها رفتار عجیب دارند، برای آزمایش موقتاً غیرفعالشان کنید.</div><?php endif; ?></td>
                                                </tr>
                                                </tbody>
                                        </table>

                                        <p class="description">راهنمای خطای «فعال‌سازی ناموفق»: در wp-config.php خطوط <code>define('WP_DEBUG', true);</code> و <code>define('WP_DEBUG_LOG', true);</code> را اضافه کنید، دوباره فعال‌سازی را امتحان کنید و سپس فایل <code>wp-content/debug.log</code> را بررسی کنید.</p>
                                </div>

                        <?php elseif ( 'offline' === $tab ) :
                                $api_state   = TppSalary_Api::get_state();
                                $api_active  = TppSalary_Api::has_key();
                                $api_base    = function_exists( 'rest_url' ) ? rest_url( TppSalary_Api::REST_NS ) : home_url( '/?rest_route=/' . TppSalary_Api::REST_NS );
                                $new_api_key = isset( $_GET['api_key'] ) ? sanitize_text_field( wp_unslash( $_GET['api_key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                                ?>
                                <div class="tpp-scope tpp-panel">
                                        <h2>برنامه آفلاین پایتون (دسکتاپ)</h2>
                                        <p class="description">نرم‌افزار آفلاین حقوق و دستمزد داخل پوشه افزونه قرار دارد: <code>wp-content/plugins/tpp_salary/python-app/</code> — کافیست روی فایل <code>TPP Salary.bat</code> (یا <code>tpp_salary_app.py</code>) دوبار کلیک کنید؛ وابستگی‌ها به‌صورت خودکار نصب و برنامه اجرا می‌شود. این برنامه حتی بدون اینترنت کار می‌کند و تغییرات به محض اتصال با سایت همگام می‌شود.</p>

                                        <?php if ( $new_api_key ) : ?>
                                                <div class="notice notice-success"><p><strong>کلید API ساخته شد — این کلید فقط همین یک‌بار نمایش داده می‌شود، آن را کپی و در برنامه پایتون (تنظیمات) وارد کنید:</strong><br><code style="font-size:14px;direction:ltr;display:inline-block;padding:6px 10px;user-select:all"><?php echo esc_html( $new_api_key ); ?></code></p></div>
                                        <?php endif; ?>
                                        <?php if ( isset( $_GET['revoked'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                                                <div class="notice notice-warning"><p>کلید API لغو شد؛ برنامه‌های متصل دیگر دسترسی ندارند تا کلید جدیدی بسازید و در برنامه وارد کنید.</p></div>
                                        <?php endif; ?>

                                        <table class="form-table" role="presentation">
                                                <tr>
                                                        <th>وضعیت API</th>
                                                        <td>
                                                                <span class="tpp-badge <?php echo $api_active ? 'tpp-badge-ok' : 'tpp-badge-bad'; ?>"><?php echo $api_active ? '✓ فعال — کلید موجود است' : '✗ کلیدی ساخته نشده'; ?></span>
                                                                <?php if ( $api_active ) : ?>
                                                                        <div class="description">ساخته‌شده: <?php echo esc_html( isset( $api_state['created_at'] ) ? $api_state['created_at'] : '—' ); ?> — آخرین استفاده: <?php echo esc_html( ! empty( $api_state['last_used'] ) ? $api_state['last_used'] : 'هنوز استفاده نشده' ); ?></div>
                                                                <?php endif; ?>
                                                        </td>
                                                </tr>
                                                <tr>
                                                        <th>آدرس API (در برنامه پایتون وارد شود)</th>
                                                        <td><code style="direction:ltr;display:inline-block;padding:4px 8px;user-select:all"><?php echo esc_html( $api_base ); ?></code><div class="description">در برنامه پایتون از منوی «تنظیمات» این آدرس و کلید را وارد کنید و «تست اتصال» را بزنید.</div></td>
                                                </tr>
                                        </table>

                                        <p>
                                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block">
                                                        <?php wp_nonce_field( 'tpp_salary_api_generate' ); ?>
                                                        <input type="hidden" name="action" value="tpp_salary_api_generate">
                                                        <?php submit_button( $api_active ? 'ساخت کلید جدید (لغو قبلی)' : 'ساخت کلید API', 'primary', 'submit', false ); ?>
                                                </form>
                                                <?php if ( $api_active ) : ?>
                                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-inline-start:8px">
                                                        <?php wp_nonce_field( 'tpp_salary_api_revoke' ); ?>
                                                        <input type="hidden" name="action" value="tpp_salary_api_revoke">
                                                        <?php submit_button( 'لغو کلید', 'delete', 'submit', false ); ?>
                                                </form>
                                                <?php endif; ?>
                                        </p>
                                        <hr>
                                        <h3>راهنمای امنیت</h3>
                                        <p class="description">کلید فقط به‌صورت هش در دیتابیس ذخیره می‌شود و پس از ساخت فقط یک‌بار نمایش داده می‌شود؛ اگر گم شد، کلید جدید بسازید و در برنامه جایگزین کنید. هر درخواست برنامه باید هدر <code>X-TPP-Key</code> را ارسال کند. در صورت لغو کلید، برنامه تا وارد کردن کلید جدید فقط به‌صورت آفلاین کار می‌کند و تغییرات در صف می‌ماند تا پس از همگام‌سازی اعمال شود.</p>
                                </div>

                        <?php endif; ?>
                </div>
                <?php
        }
}
}
// TPP_SALARY GUARD END
