<?php
/**
 * صفحه ثبت حقوق (ویزارد ۳ مرحله‌ای) و فهرست حقوق‌های ثبت‌شده
 *
 * @package TppSalary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class TppSalary_Salary_Pages
 */
if ( ! class_exists( 'TppSalary_Salary_Pages' ) ) {
        // TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TppSalary_Salary_Pages {

        /**
         * مقداردهی اولیه
         *
         * @return void
         */
        public static function init() {
                /*
                 * اولویت ۹ — ثبت منوی «والد» باید پیش از همه add_submenu_page ها انجام شود.
                 * اگر زیرمنو پیش از add_menu_page ثبت شود، وردپرس hookname صفحه را با
                 * «admin_page_» می‌سازد در حالی که در زمان رندر منو با پیشوند
                 * hookِ والد بازسازی می‌شود؛ نتیجه: لینک منو به slug خام تبدیل می‌شود
                 * (wp-admin/tpp-salary-centers) و بازکردن آن خطای 404 یا
                 * «متأسفانه شما مجاز به دسترسی به این صفحه نیستید» می‌دهد.
                 */
                add_action( 'admin_menu', array( __CLASS__, 'menu' ), 9 );
                // صفحات فرعی سایر ماژول‌ها — اگر هر یک با ترتیب اشتباه ثبت شده باشد، جبران می‌شود.
                add_action( 'admin_menu', array( __CLASS__, 'ensure_pages' ), 999 );
                add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
                add_action( 'admin_post_tpp_salary_save_salary', array( __CLASS__, 'save_record' ) );
                add_action( 'admin_post_tpp_salary_del_salary', array( __CLASS__, 'delete_record' ) );
                /* نسخه 1.6.2: حذف گروهی حقوق‌های ثبت‌شده */
                add_action( 'admin_post_tpp_salary_del_salary_bulk', array( __CLASS__, 'delete_records_bulk' ) );
        }

        /**
         * فهرست صفحات افزونه — برای جبران خودکار ثبت‌های ناقص
         *
         * @return array slug => callable رندر.
         */
        private static function expected_pages() {
                return array(
                        'tpp-salary-register'    => array( __CLASS__, 'render_register' ),
                        'tpp-salary-records'     => array( __CLASS__, 'render_records' ),
                        'tpp-salary-centers'     => array( 'TppSalary_Centers', 'render' ),
                        'tpp-salary-banks'       => array( 'TppSalary_Banks', 'render' ),
                        'tpp-salary-employees'   => array( 'TppSalary_Employees', 'render_list' ),
                        'tpp-salary-import'      => array( 'TppSalary_Import', 'render' ),
                        'tpp-salary-import-records' => array( 'TppSalary_Import', 'render_records' ),
                        'tpp-salary-settings'    => array( 'TppSalary_Settings', 'render' ),
                        'tpp-salary-report'      => array( 'TppSalary_Reports', 'render_report' ),
                        'tpp-salary-bank-report' => array( 'TppSalary_Reports', 'render_bank' ),
                        'tpp-salary-payslips'    => array( 'TppSalary_Reports', 'render_payslips' ),
                        'tpp-salary-backup'      => array( 'TppSalary_Reports', 'render_backup' ),
                        'tpp-salary-offline'     => array( 'TppSalary_Offline', 'render' ),
                );
        }

        /**
         * جبران خودکار hookname صفحات — اولویت 999 (بعد از همه ثبت‌های منو)
         *
         * اگر به هر دلیلی (ترتیب لود، تداخل افزونه دیگر، کد سفارشی) زیرمنویی
         * پیش از وجود منوی والد ثبت شده باشد، callback آن زیر hookname اشتباه
         * متصل شده است. این متد همه صفحات را روی hookname نهایی و صحیح وردپرس
         * وصل می‌کند تا URL و دسترسی همه صفحات همیشه درست باشد.
         *
         * @return void
         */
        public static function ensure_pages() {
                if ( ! isset( $GLOBALS['_registered_pages'] ) ) {
                        return;
                }
                foreach ( self::expected_pages() as $slug => $cb ) {
                        if ( ! is_callable( $cb ) ) {
                                continue; // ماژول ناهماهنگ — مطابق دفاع init رد می‌شود.
                        }
                        $hookname = get_plugin_page_hookname( $slug, 'tpp-salary' );
                        if ( '' === $hookname ) {
                                continue;
                        }
                        if ( ! has_action( $hookname ) ) {
                                add_action( $hookname, $cb );
                        }
                        $GLOBALS['_registered_pages'][ $hookname ] = true;
                }
        }

        /**
         * منو
         *
         * @return void
         */
        public static function menu() {
                add_menu_page( 'حقوق و دستمزد', 'حقوق و دستمزد', 'tpp_salary_manage', 'tpp-salary', array( __CLASS__, 'render_register' ), 'dashicons-money-alt', 26 );
                add_submenu_page( 'tpp-salary', 'ثبت حقوق', 'ثبت حقوق', 'tpp_salary_manage', 'tpp-salary-register', array( __CLASS__, 'render_register' ) );
                add_submenu_page( 'tpp-salary', 'حقوق‌های ثبت‌شده', 'حقوق‌های ثبت‌شده', 'tpp_salary_manage', 'tpp-salary-records', array( __CLASS__, 'render_records' ) );
        }

        /**
         * بارگذاری اسکریپت و استایل
         *
         * @param string $hook هوک صفحه.
         * @return void
         */
        public static function assets( $hook ) {
                /* صفحات افزونه + صفحه پروفایل/ویرایش کاربر (بخش اطلاعات حقوق و دستمزد هم دارک بماند). */
                if ( false !== strpos( $hook, 'tpp-' ) || 'profile.php' === $hook || 'user-edit.php' === $hook ) {
                        wp_enqueue_style( 'tpp-salary-admin', TPP_SALARY_URL . 'admin/css/tpp-salary-admin.css', array(), TPP_SALARY_VERSION );
                        /* دیالوگ «محاسبه خودکار سایر» به jQuery UI Dialog نیاز دارد؛
                         * بدون این دو، $('#…').dialog تعریف‌نشده است → خطای JS و دکمه کارنمی‌کند. */
                        wp_enqueue_script( 'jquery-ui-dialog' );
                        wp_enqueue_style( 'wp-jquery-ui-dialog' );
                        wp_enqueue_script( 'tpp-salary-admin', TPP_SALARY_URL . 'admin/js/tpp-salary-admin.js', array( 'jquery', 'jquery-ui-dialog' ), TPP_SALARY_VERSION, true );
                        wp_localize_script(
                                'tpp-salary-admin',
                                'TPP',
                                array(
                                        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                                        'nonce'     => wp_create_nonce( 'tpp_salary_ajax' ),
                                        'formulas'  => tpp_salary_get_settings()['formulas'],
                                        'fields'    => self::fields_js(),
                                )
                        );
                }
        }

        /**
         * داده فیلدها برای جاوااسکریپت
         *
         * @return array
         */
        public static function fields_js() {
                $out      = array();
                /* فرمول‌های سراسری تنظیمات — مکمل فیلدهای بدون فرمول ذخیره‌شده (gross/insurable/net). */
                $settings = tpp_salary_get_settings();
                $global   = isset( $settings['formulas'] ) && is_array( $settings['formulas'] ) ? $settings['formulas'] : array();
                foreach ( tpp_salary_get_fields() as $f ) {
                        // فقط فیلدهای فرم ثبت حقوق (نسخه 1.4.1: فیلدهای پروفایلی مثل عنوان شغلی/خودرو اینجا نیستند).
                        if ( ! tpp_salary_field_in_record( $f ) ) { // نسخه 1.5.0: سپر هاردکد فیلدهای فقط‌پروفایلی.
                                continue;
                        }
                        $formula = ( '' !== (string) $f->formula ) ? (string) $f->formula : ( isset( $global[ $f->field_key ] ) ? (string) $global[ $f->field_key ] : '' );
                        $out[] = array(
                                'key'         => $f->field_key,
                                'label'       => $f->label,
                                'type'        => $f->field_type,
                                'formula'     => $formula,
                                'calculated'  => (int) $f->is_calculated,
                                'showInSlip'  => (int) $f->show_in_payslip,
                        );
                }
                return $out;
        }

        /**
         * صفحه ثبت حقوق — مرحله ۱ و ۲ و ۳
         *
         * @return void
         */
        public static function render_register() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                $today    = TppSalary_Jalali::today();
                $jyear    = isset( $_REQUEST['jyear'] ) ? (int) $_REQUEST['jyear'] : $today[0];
                $jmonth   = isset( $_REQUEST['jmonth'] ) ? (int) $_REQUEST['jmonth'] : $today[1];
                $center_id = isset( $_REQUEST['center_id'] ) ? (int) $_REQUEST['center_id'] : 0;
                $edit_user = isset( $_REQUEST['user_id'] ) ? (int) $_REQUEST['user_id'] : 0;
                $centers  = tpp_salary_get_centers();

                // مرحله ۳: فرم ثبت/ویرایش یک کارمند.
                if ( $edit_user && $center_id && $jyear && $jmonth ) {
                        self::render_form( $edit_user, $center_id, $jyear, $jmonth );
                        return;
                }
                ?>
                <div class="wrap tpp-wrap" dir="rtl">
                        <h1>ثبت حقوق</h1>
                        <div class="tpp-steps">
                                <span class="tpp-step tpp-step-active">۱. انتخاب دوره و مرکز</span>
                                <span class="tpp-step">۲. انتخاب کارمند</span>
                                <span class="tpp-step">۳. ثبت جزئیات حقوق</span>
                        </div>

                        <?php if ( empty( $centers ) ) : ?>
                                <div class="notice notice-warning"><p>ابتدا <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpp-salary-centers' ) ); ?>">مرکز (پروژه/کارگاه)</a> تعریف کنید.</p></div>
                        <?php endif; ?>

                        <form method="get" action="">
                                <input type="hidden" name="page" value="tpp-salary-register">
                                <table class="form-table" role="presentation">
                                        <tr>
                                                <th>سال</th>
                                                <td><select name="jyear">
                                                        <?php for ( $y = $today[0] - 6; $y <= $today[0] + 1; $y++ ) : ?>
                                                                <option value="<?php echo $y; ?>" <?php selected( $jyear, $y ); ?>><?php echo TppSalary_Jalali::digits_fa( $y ); ?></option>
                                                        <?php endfor; ?>
                                                </select> <span class="description">بر اساس تاریخ سیستم: <?php echo esc_html( TppSalary_Jalali::digits_fa( $today[0] ) ); ?></span></td>
                                        </tr>
                                        <tr>
                                                <th>ماه</th>
                                                <td><select name="jmonth">
                                                        <?php foreach ( TppSalary_Jalali::months() as $m => $label ) : ?>
                                                                <option value="<?php echo $m; ?>" <?php selected( $jmonth, $m ); ?>><?php echo esc_html( $label ); ?></option>
                                                        <?php endforeach; ?>
                                                </select></td>
                                        </tr>
                                        <tr>
                                                <th>مرکز (پروژه / کارگاه)</th>
                                                <td><select name="center_id" required>
                                                        <option value="">— انتخاب کنید —</option>
                                                        <?php foreach ( $centers as $c ) : ?>
                                                                <option value="<?php echo (int) $c->id; ?>" <?php selected( $center_id, $c->id ); ?>><?php echo esc_html( $c->name ); ?></option>
                                                        <?php endforeach; ?>
                                                </select></td>
                                        </tr>
                                </table>
                                <?php submit_button( 'ادامه ← مرحله ۲' ); ?>
                        </form>

                        <?php if ( $center_id && $jyear && $jmonth ) :
                                $center      = tpp_salary_get_center( $center_id );
                                $employees   = tpp_salary_get_employees( $center_id );
                                $registered  = array();
                                $unregistered = array();
                                foreach ( $employees as $u ) {
                                        $rec = tpp_salary_get_record_period( $u->ID, $center_id, $jyear, $jmonth );
                                        if ( $rec ) {
                                                $registered[] = array( 'user' => $u, 'record' => $rec );
                                        } else {
                                                $unregistered[] = $u;
                                        }
                                }
                                $base = array( 'page' => 'tpp-salary-register', 'jyear' => $jyear, 'jmonth' => $jmonth, 'center_id' => $center_id );
                                ?>
                                <h2>مرحله ۲ — <?php echo esc_html( TppSalary_Jalali::period_label( $jyear, $jmonth ) ); ?> / مرکز <?php echo esc_html( $center ? $center->name : '' ); ?></h2>

                                <div class="tpp-columns">
                                        <div class="tpp-col">
                                                <h3>کارمندانی که حقوقشان ثبت نشده (<?php echo TppSalary_Jalali::digits_fa( count( $unregistered ) ); ?> نفر)</h3>
                                                <table class="widefat striped">
                                                        <tbody>
                                                        <?php if ( empty( $unregistered ) ) : ?>
                                                                <tr><td>همه کارمندان این مرکز برای این دوره ثبت شده‌اند.</td></tr>
                                                        <?php endif; ?>
                                                        <?php foreach ( $unregistered as $u ) :
                                                                $url = add_query_arg( array_merge( $base, array( 'user_id' => $u->ID ) ), admin_url( 'admin.php' ) );
                                                                ?>
                                                                <tr>
                                                                        <td><?php echo esc_html( $u->display_name ); ?></td>
                                                                        <td style="width:120px"><a class="button button-primary button-small" href="<?php echo esc_url( $url ); ?>">ثبت حقوق</a></td>
                                                                </tr>
                                                        <?php endforeach; ?>
                                                        </tbody>
                                                </table>
                                        </div>
                                        <div class="tpp-col">
                                                <h3>کارمندانی که حقوقشان ثبت شده (<?php echo TppSalary_Jalali::digits_fa( count( $registered ) ); ?> نفر)</h3>
                                                <table class="widefat striped">
                                                        <tbody>
                                                        <?php if ( empty( $registered ) ) : ?>
                                                                <tr><td>هنوز رکوردی در این دوره ثبت نشده است.</td></tr>
                                                        <?php endif; ?>
                                                        <?php foreach ( $registered as $item ) :
                                                                $rec = $item['record'];
                                                                $u   = $item['user'];
                                                                $url = add_query_arg( array_merge( $base, array( 'user_id' => $u->ID ) ), admin_url( 'admin.php' ) );
                                                                $pdf = add_query_arg( array( 'action' => 'tpp_salary_payslip_pdf', 'record_id' => $rec->id, '_wpnonce' => wp_create_nonce( 'tpp_salary_payslip_pdf' ) ), admin_url( 'admin-ajax.php' ) );
                                                                ?>
                                                                <tr>
                                                                        <td><?php echo esc_html( $u->display_name ); ?></td>
                                                                        <td><?php echo esc_html( tpp_salary_format_number( (float) $rec->net ) ); ?></td>
                                                                        <td style="width:200px">
                                                                                <a class="button button-small" href="<?php echo esc_url( $url ); ?>">ویرایش</a>
                                                                                <a class="button button-small" target="_blank" href="<?php echo esc_url( $pdf ); ?>">فیش PDF</a>
                                                                                <?php $du = wp_nonce_url( admin_url( 'admin-post.php?action=tpp_salary_del_salary&id=' . (int) $rec->id ), 'tpp_salary_del_salary' ); ?>
                                                                                <a class="button button-small" style="color:#b32d2e" href="<?php echo esc_url( $du ); ?>" onclick="return confirm('حذف رکورد حقوق؟')">حذف</a>
                                                                        </td>
                                                                </tr>
                                                        <?php endforeach; ?>
                                                        </tbody>
                                                </table>
                                        </div>
                                </div>
                        <?php endif; ?>
                </div>
                <?php
        }

        /**
         * فرم مرحله ۳ — ثبت/ویرایش جزئیات حقوق یک کارمند
         *
         * @param int $user_id   کاربر.
         * @param int $center_id مرکز.
         * @param int $jyear     سال.
         * @param int $jmonth    ماه.
         * @return void
         */
        private static function render_form( $user_id, $center_id, $jyear, $jmonth ) {
                $user    = get_user_by( 'id', $user_id );
                $center  = tpp_salary_get_center( $center_id );
                if ( ! $user || ! $center ) {
                        echo '<div class="wrap"><p>کاربر یا مرکز یافت نشد.</p></div>';
                        return;
                }
                $existing = tpp_salary_get_record_period( $user_id, $center_id, $jyear, $jmonth );
                $payload  = $existing ? tpp_salary_record_payload( $existing ) : array();
                $profile  = tpp_salary_get_profile( $user_id );
                $settings = tpp_salary_get_settings();
                $defaults = isset( $settings['defaults'] ) && is_array( $settings['defaults'] ) ? $settings['defaults'] : array();
                $fields   = tpp_salary_get_fields();
                /* نسخه 1.4.1: فیلدهای فقط‌پروفایلی (عنوان شغلی، نوع/پلاک خودرو، نام و نام خانوادگی)
                 * از فرم ثبت حقوق حذف شدند — در پروفایل کاربر ثبت می‌شوند. */
                $fields   = array_values( array_filter( $fields, function ( $f ) {
                        return tpp_salary_field_in_record( $f ); // نسخه 1.5.0: سپر هاردکد فیلدهای فقط‌پروفایلی.
                } ) );

                $values = array();
                $manual = array();
                $insurable_mode = 'profile';
                if ( $existing ) {
                        $manual = isset( $payload['manual'] ) && is_array( $payload['manual'] ) ? $payload['manual'] : array();
                        $insurable_mode = isset( $payload['insurable_mode'] ) ? $payload['insurable_mode'] : 'profile';
                        foreach ( $fields as $f ) {
                                $values[ $f->field_key ] = isset( $payload[ $f->field_key ] ) ? $payload[ $f->field_key ] : '';
                        }
                } else {
                        // مقادیر پیش‌فرض از پروفایل کارمند.
                        foreach ( $fields as $f ) {
                                $key = $f->field_key;
                                $profile_key = ( 'daily_wage' === $key ) ? 'daily_wage' : $key;
                                $values[ $key ] = '';
                                if ( '' !== (string) $f->default_value ) {
                                        $values[ $key ] = $f->default_value;
                                }
                                if ( isset( $profile[ $profile_key ] ) && '' !== (string) $profile[ $profile_key ] ) {
                                        $values[ $key ] = $profile[ $profile_key ];
                                } elseif ( isset( $defaults[ $key ] ) && '' !== (string) $defaults[ $key ] ) {
                                        $values[ $key ] = $defaults[ $key ];
                                }
                                if ( 'insurable' === $key ) {
                                        $values[ $key ] = isset( $profile['insurable_default'] ) ? $profile['insurable_default'] : ( (isset($defaults['insurable_default'] )?$defaults['insurable_default'] : 0 ));
                                }
                        }
                }

                $base_query = array( 'page' => 'tpp-salary-register', 'jyear' => $jyear, 'jmonth' => $jmonth, 'center_id' => $center_id, 'user_id' => $user_id );

                /* نسخه 1.6.3: پیش‌فرض دیالوگ «حقوق گذشته» = ماه قبل از دوره جاری فرم. */
                $today          = TppSalary_Jalali::today();
                $past_def_year  = ( $jmonth > 1 ) ? $jyear : $jyear - 1;
                $past_def_month = ( $jmonth > 1 ) ? $jmonth - 1 : 12;
                ?>
                <div class="wrap tpp-wrap" dir="rtl">
                        <h1>
                                ثبت حقوق — <?php echo esc_html( $user->display_name ); ?>
                                <span class="tpp-sub">(<?php echo esc_html( TppSalary_Jalali::period_label( $jyear, $jmonth ) ); ?> / <?php echo esc_html( $center->name ); ?>)</span>
                        </h1>
                        <div class="tpp-steps">
                                <span class="tpp-step tpp-step-done"><a href="<?php echo esc_url( remove_query_arg( 'user_id', admin_url( 'admin.php?page=tpp-salary-register' ) ) ); ?>">۱. دوره و مرکز</a></span>
                                <span class="tpp-step tpp-step-done"><a href="<?php echo esc_url( remove_query_arg( 'user_id', admin_url( 'admin.php?page=tpp-salary-register' ) ) ); ?>">۲. کارمند</a></span>
                                <span class="tpp-step tpp-step-active">۳. جزئیات حقوق</span>
                        </div>

                        <?php if ( $existing ) : ?>
                                <p class="tpp-note">این رکورد قبلاً ثبت شده است — فیلدهایی که با اطلاعات اولیه پروفایل کارمند تفاوت دارند با <span class="tpp-blue">رنگ آبی</span> نمایش داده می‌شوند.</p>
                        <?php endif; ?>

                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="tpp-salary-form">
                                <?php wp_nonce_field( 'tpp_salary_save_salary' ); ?>
                                <input type="hidden" name="action" value="tpp_salary_save_salary">
                                <input type="hidden" name="user_id" value="<?php echo (int) $user_id; ?>">
                                <input type="hidden" name="center_id" value="<?php echo (int) $center_id; ?>">
                                <input type="hidden" name="jyear" value="<?php echo (int) $jyear; ?>">
                                <input type="hidden" name="jmonth" value="<?php echo (int) $jmonth; ?>">

                                <?php /* نسخه 1.6.3: دکمه «پر کردن فیلدها بر اساس حقوق گذشته» — مرحله ۳ */ ?>
                                <div class="tpp-past-toolbar">
                                        <button type="button" class="button button-secondary" id="tpp-past-fill">پر کردن فیلدها بر اساس حقوق گذشته</button>
                                        <span class="description">دوره‌ای را که می‌خواهید فیلدهای این ماه بر اساس آن تکمیل شود انتخاب و اعمال کنید.</span>
                                </div>
                                <div id="tpp-past-fill-note"></div>

                                <table class="widefat tpp-form-table">
                                        <tbody>
                                        <?php
                                        foreach ( $fields as $f ) :
                                                $key   = $f->field_key;
                                                $value = $values[ $key ];
                                                $is_num = ( 'number' === $f->field_type );
                                                $is_calc = (int) $f->is_calculated && '' !== (string) $f->formula;
                                                // هایلایت آبی: مقدار ثبت‌شده با پروفایل تفاوت دارد.
                                                $profile_val = null;
                                                if ( isset( $profile[ $key ] ) ) {
                                                        $profile_val = $profile[ $key ];
                                                } elseif ( 'insurable' === $key && isset( $profile['insurable_default'] ) ) {
                                                        $profile_val = $profile['insurable_default'];
                                                }
                                                if ( null === $profile_val && isset( $defaults[ $key ] ) ) {
                                                        $profile_val = $defaults[ $key ];
                                                }
                                                $differs = $existing && null !== $profile_val && is_numeric( $profile_val ) && is_numeric( $value ) && ( (float) $profile_val !== (float) $value );
                                                $manual_flag = in_array( $key, $manual, true );
                                                ?>
                                                <tr data-field="<?php echo esc_attr( $key ); ?>" class="<?php echo $differs ? 'tpp-row-differs' : ''; ?>">
                                                        <th class="tpp-label">
                                                                <?php echo esc_html( $f->label ); ?>
                                                                <code dir="ltr" class="tpp-key-hint"><?php echo esc_html( $key ); ?></code>
                                                        </th>
                                                        <td class="tpp-input-cell">
                                                                <?php if ( 'text' === $f->field_type ) : ?>
                                                                        <input type="text" name="field[<?php echo esc_attr( $key ); ?>]" id="fld_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" data-manual="<?php echo $manual_flag ? '1' : '0'; ?>">
                                                                <?php else : ?>
                                                                        <input type="text" inputmode="decimal" dir="ltr" name="field[<?php echo esc_attr( $key ); ?>]" id="fld_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $is_num ? tpp_salary_format_number( (float) $value, false ) : $value ); ?>" class="regular-text tpp-num <?php echo $differs ? 'tpp-blue' : ''; ?> <?php echo ( $is_num && (float) $value < 0 ) ? 'tpp-neg' : ''; ?>" data-manual="<?php echo $manual_flag ? '1' : '0'; ?>" data-key="<?php echo esc_attr( $key ); ?>" <?php echo ( $is_calc && ! $manual_flag ) ? 'data-auto="1"' : ''; ?>>
                                                                        <?php if ( $is_calc ) : ?>
                                                                                <span class="description tpp-calc-hint" dir="ltr"><?php echo esc_html( $f->formula ); ?></span>
                                                                        <?php endif; ?>
                                                                        <?php if ( 'other' === $key ) : ?>
                                                                                <button type="button" class="button button-small" id="tpp-other-auto">محاسبه خودکار این فیلد</button>
                                                                        <?php endif; ?>
                                                                        <?php if ( 'insurable' === $key ) : ?>
                                                                                <label class="tpp-inline">
                                                                                        <input type="checkbox" name="insurable_formula" value="1" <?php checked( 'formula', $insurable_mode ); ?>> محاسبه با فرمول
                                                                                </label>
                                                                        <?php endif; ?>
                                                                        <?php if ( $manual_flag ) : ?>
                                                                                <span class="description">(دستی ثبت شده)</span>
                                                                        <?php endif; ?>
                                                                <?php endif; ?>
                                                        </td>
                                                </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                </table>

                                <div class="tpp-summary">
                                        <div>حقوق ناخالص: <strong id="tpp-sum-gross">0</strong></div>
                                        <div>حقوق خالص پرداختی: <strong id="tpp-sum-net">0</strong> (<?php echo esc_html( $settings['currency'] ); ?>)</div>
                                </div>

                                <p>
                                        <?php submit_button( $existing ? 'ذخیره تغییرات' : 'ثبت فیش حقوقی', 'primary large', 'submit', false ); ?>
                                        <a class="button" href="<?php echo esc_url( remove_query_arg( 'user_id', admin_url( 'admin.php?page=tpp-salary-register' ) ) ); ?>">بازگشت</a>
                                </p>
                        </form>

                        <!-- دیالوگ محاسبه خودکار سایر -->
                        <div id="tpp-other-dialog" style="display:none" title="محاسبه خودکار فیلد سایر">
                                <p>مبلغ حقوق خالص پرداختی که می‌خواهید به آن برسید:</p>
                                <input type="text" id="tpp-other-target" dir="ltr" class="regular-text">
                                <p><button type="button" class="button button-primary" id="tpp-other-calc">محاسبه سایر</button></p>
                                <p id="tpp-other-result" class="description"></p>
                        </div>

                        <!-- نسخه 1.6.3: دیالوگ «پر کردن فیلدها بر اساس حقوق گذشته» -->
                        <div id="tpp-past-dialog" style="display:none" title="پر کردن فیلدها بر اساس حقوق گذشته">
                                <p>ماه و سالی را که می‌خواهید فیلدهای حقوق <?php echo esc_html( TppSalary_Jalali::period_label( $jyear, $jmonth ) ); ?> بر اساس آن تکمیل شود، انتخاب کنید:</p>
                                <p>
                                        <select id="tpp-past-year" aria-label="سال">
                                                <?php for ( $y = $today[0] - 6; $y <= $today[0] + 1; $y++ ) : ?>
                                                        <option value="<?php echo $y; ?>" <?php selected( $past_def_year, $y ); ?>><?php echo TppSalary_Jalali::digits_fa( $y ); ?></option>
                                                <?php endfor; ?>
                                        </select>
                                        <select id="tpp-past-month" aria-label="ماه">
                                                <?php foreach ( TppSalary_Jalali::months() as $m => $label ) : ?>
                                                        <option value="<?php echo $m; ?>" <?php selected( $past_def_month, $m ); ?>><?php echo esc_html( $label ); ?></option>
                                                <?php endforeach; ?>
                                        </select>
                                </p>
                                <p><button type="button" class="button button-primary" id="tpp-past-apply">اعمال</button></p>
                                <p id="tpp-past-result" class="description"></p>
                        </div>
                        <script>
                        jQuery(function($){
                                var $dlg = $('#tpp-past-dialog');
                                var dialogOk = !!$.fn.dialog;
                                if (dialogOk) {
                                        $dlg.dialog({
                                                autoOpen: false,
                                                modal: true,
                                                width: 440,
                                                dir: 'rtl',
                                                dialogClass: 'tpp-dialog wp-dialog',
                                                closeText: 'بستن'
                                        });
                                }
                                $('#tpp-past-fill').on('click', function(){
                                        $('#tpp-past-result').text('');
                                        if (dialogOk) { $dlg.dialog('open'); } else { $dlg.addClass('tpp-past-inline').show(); }
                                });
                                $('#tpp-past-apply').on('click', function(){
                                        var $btn = $(this);
                                        if ($btn.prop('disabled')) { return; }
                                        if (!window.TPP || !TPP.parseNum) { $('#tpp-past-result').text('موتور محاسبه بارگذاری نشده است — صفحه را دوباره باز کنید.'); return; }
                                        $btn.prop('disabled', true);
                                        $('#tpp-past-result').text('در حال دریافت حقوق دوره انتخابی…');
                                        $.post(TPP.ajaxUrl, {
                                                action: 'tpp_salary_past_salary',
                                                _wpnonce: TPP.nonce,
                                                user_id: <?php echo (int) $user_id; ?>,
                                                center_id: <?php echo (int) $center_id; ?>,
                                                src_year: $('#tpp-past-year').val(),
                                                src_month: $('#tpp-past-month').val()
                                        }).done(function(r){
                                                $btn.prop('disabled', false);
                                                if (!r || !r.success) {
                                                        $('#tpp-past-result').text((r && r.data) ? String(r.data) : 'خطا در دریافت اطلاعات.');
                                                        return;
                                                }
                                                if (!r.data || !r.data.found) {
                                                        $('#tpp-past-result').text((r.data && r.data.message) ? r.data.message : 'برای این دوره رکوردی یافت نشد.');
                                                        return;
                                                }
                                                var d = r.data, manual = d.manual || [];
                                                $.each(d.values || {}, function(k, v){
                                                        var $f = $('#fld_' + k);
                                                        if (!$f.length) { return; }
                                                        $f.val(v);
                                                        $f.attr('data-manual', (manual.indexOf(String(k)) !== -1) ? '1' : '0');
                                                        $f.toggleClass('tpp-neg', TPP.parseNum(v) < 0);
                                                });
                                                if (d.insurable_mode === 'formula') { $('input[name="insurable_formula"]').prop('checked', true); }
                                                else { $('input[name="insurable_formula"]').prop('checked', false); }
                                                if (TPP.recalc) { TPP.recalc(); }
                                                var msg = 'فیلدها بر اساس حقوق ' + (d.period_label || '') + ' پر شد' +
                                                        (d.same_center ? '' : ' (مرکز ' + (d.center_name || '') + ')') +
                                                        '. پس از بازبینی، ذخیره کنید.';
                                                $('#tpp-past-result').text(msg);
                                                $('#tpp-past-fill-note').text(msg).show();
                                                if (dialogOk) { $dlg.dialog('close'); }
                                        }).fail(function(){
                                                $btn.prop('disabled', false);
                                                $('#tpp-past-result').text('خطا در ارتباط با سرور.');
                                        });
                                });
                        });
                        </script>
                        <script>
                        jQuery(function($){
                                /* اگر به هر دلیل jQuery UI Dialog بارگذاری نشده باشد،
                                 * نباید کل اسکریپت صفحه با خطا متوقف شود. */
                                if (!$.fn.dialog) { return; }
                                $('#tpp-other-dialog').dialog({
                                        autoOpen: false,
                                        modal: true,
                                        width: 420,
                                        dir: 'rtl',
                                        dialogClass: 'tpp-dialog wp-dialog',
                                        closeText: 'بستن'
                                });
                                $('#tpp-other-auto').on('click', function(){ $('#tpp-other-dialog').dialog('open'); });
                                $('#tpp-other-calc').on('click', function(){
                                        if (!window.TPP || !TPP.parseNum) { return; }
                                        var target = TPP.parseNum($('#tpp-other-target').val());
                                        var currentNet = TPP.parseNum($('#fld_net').val());
                                        var currentOther = TPP.parseNum($('#fld_other').val());
                                        var diff = target - currentNet;
                                        var newOther = currentOther + diff;
                                        $('#fld_other').val(TPP.fmt(newOther)).trigger('change').attr('data-manual','1');
                                        $('#tpp-other-result').text('مبلغ فعلی خالص: ' + TPP.fmtFa(currentNet) + ' — مقدار جدید «سایر»: ' + TPP.fmtFa(newOther) + (diff >= 0 ? ' (افزایش ' : ' (کاهش ') + TPP.fmtFa(Math.abs(diff)) + ')');
                                });
                        });
                        </script>
                </div>
                <?php
        }

        /**
         * ذخیره/به‌روزرسانی رکورد حقوق — هسته مشترک فرم و همگام‌سازی آفلاین
         *
         * @param int   $user_id           کاربر.
         * @param int   $center_id         مرکز.
         * @param int   $jyear             سال.
         * @param int   $jmonth            ماه.
         * @param array $raw               مقادیر خام (کلید فیلد => مقدار).
         * @param bool  $insurable_formula مشمول بیمه با فرمول؟
         * @param array $force_compute     کلیدهایی که «اجباراً» از فرمول محاسبه می‌شوند حتی اگر مقدار پست‌شده متفاوت باشد (ورود گروهی: سلول «—»).
         * @return array|WP_Error
         */
        public static function upsert_record( $user_id, $center_id, $jyear, $jmonth, $raw, $insurable_formula = false, $force_compute = array() ) {
                if ( ! $user_id || ! $center_id || ! $jyear || ! $jmonth ) {
                        return new WP_Error( 'tpp_salary_params', 'پارامترهای ناقص' );
                }
                $fields = tpp_salary_get_fields();
                /* نسخه 1.4.1: فقط فیلدهای فرم ثبت حقوق در پیلود ذخیره می‌شوند. */
                $fields = array_values( array_filter( $fields, function ( $f ) {
                        return tpp_salary_field_in_record( $f ); // نسخه 1.5.0: سپر هاردکد فیلدهای فقط‌پروفایلی.
                } ) );
                $values = array();
                $manual = array();
                foreach ( $fields as $f ) {
                        $key = $f->field_key;
                        $val = isset( $raw[ $key ] ) ? $raw[ $key ] : '';
                        if ( 'number' === $f->field_type ) {
                                $values[ $key ] = tpp_salary_parse_number( $val );
                        } else {
                                $values[ $key ] = sanitize_text_field( $val );
                        }
                }
                // در حالت غیر فرمولی، «حقوق مشمول بیمه» همیشه دستی است (مقدار پنل کاربر).
                if ( ! $insurable_formula ) {
                        $manual[] = 'insurable';
                }

                /*
                 * باگ رفع‌شده 1.4.1 («دکمه محاسبه با فرمول حقوق مشمول بیمه کار نمی‌کند»):
                 * وقتی کاربر گزینه «محاسبه با فرمول» را فعال می‌کند، مقدار پیش‌پرشده
                 * (از پروفایل) با حاصل فرمول متفاوت است و منطق «تشخیص ویرایش دستی»
                 * حاصل فرمول را رد می‌کرد! اکنون در حالت فرمولی، مشمول بیمه اجباراً
                 * از فرمول محاسبه می‌شود.
                 */
                if ( $insurable_formula && ! in_array( 'insurable', (array) $force_compute, true ) ) {
                        $force_compute[] = 'insurable';
                }
                /*
                 * نسخه 1.4.1 — فیلد محاسباتیِ «غایب در ورودی» = محاسبه از فرمول:
                 * در فرم ثبت همه فیلدها پست می‌شوند؛ اما در ورود گروهی اگر ستون
                 * فیلد محاسباتی در فایل نباشد، نباید صفرِ دستی فرض شود — باید از
                 * فرمول محاسبه شود (مثل سلول «—»).
                 */
                foreach ( $fields as $f ) {
                        if ( empty( $f->is_calculated ) ) { continue; }
                        if ( array_key_exists( $f->field_key, (array) $raw ) ) { continue; }
                        if ( ! in_array( $f->field_key, (array) $force_compute, true ) ) {
                                $force_compute[] = $f->field_key;
                        }
                }

                $result    = tpp_salary_compute_values( $values, $manual, $fields, $force_compute );
                $computed  = $result['values'];
                $manual    = $result['manual'];

                $payload = array();
                foreach ( $fields as $f ) {
                        $payload[ $f->field_key ] = $computed[ $f->field_key ];
                }
                $payload['manual']         = $manual;
                $payload['insurable_mode'] = $insurable_formula ? 'formula' : 'profile';

                global $wpdb;
                $now  = current_time( 'mysql' );
                $data = array(
                        'user_id'           => $user_id,
                        'center_id'         => $center_id,
                        'jyear'             => $jyear,
                        'jmonth'            => $jmonth,
                        'payload'           => wp_json_encode( $payload ),
                        'gross'             => (float) $computed['gross'],
                        'insurable'         => (float) $computed['insurable'],
                        'insurance_deduct'  => (float) $computed['insurance_deduct'],
                        'other_deductions'  => (float) $computed['other_deductions'],
                        'net'               => (float) $computed['net'],
                        'created_by'        => get_current_user_id(),
                        'updated_at'        => $now,
                );
                $existing = tpp_salary_get_record_period( $user_id, $center_id, $jyear, $jmonth );
                $formats  = array( '%d', '%d', '%d', '%d', '%s', '%f', '%f', '%f', '%f', '%f', '%d', '%s' );
                if ( $existing ) {
                        $wpdb->update( $wpdb->prefix . 'tpp_salary_records', $data, array( 'id' => $existing->id ), $formats, array( '%d' ) ); // phpcs:ignore
                        $record_id = (int) $existing->id;
                        $status    = 'updated';
                } else {
                        $data['created_at'] = $now;
                        $formats[] = '%s';
                        $wpdb->insert( $wpdb->prefix . 'tpp_salary_records', $data, $formats ); // phpcs:ignore
                        $record_id = (int) $wpdb->insert_id;
                        $status    = 'created';
                }

                /*
                 * نسخه 1.7.8 — همگام‌سازی خودکار پروفایل کارمند با آخرین فیش صادرشده:
                 * اگر رکورد تازه‌ثبت «آخرین دوره» کارمند باشد، فیلدهای پروفایل او
                 * (دستمزد روزانه مرجع، پایه سنوات، نرخ‌ها، حق مسکن/بن/تأهل و …)
                 * به‌طور خودکار از همان فیش به‌روزرسانی می‌شود؛ ثبت پس‌گیرانه
                 * دوره‌های قدیمی‌تر پروفایل را تغییر نمی‌دهد.
                 */
                tpp_salary_sync_profile_from_latest_record( $user_id );

                return array( 'status' => $status, 'record_id' => $record_id );
        }

        /**
         * ذخیره رکورد حقوق (فرم ویزارد)
         *
         * @return void
         */
        public static function save_record() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_salary_save_salary' );

                $user_id   = (int) ( (isset($_POST['user_id'] )?$_POST['user_id'] : 0 ));
                $center_id = (int) ( (isset($_POST['center_id'] )?$_POST['center_id'] : 0 ));
                $jyear     = (int) ( (isset($_POST['jyear'] )?$_POST['jyear'] : 0 ));
                $jmonth    = (int) ( (isset($_POST['jmonth'] )?$_POST['jmonth'] : 0 ));

                $raw               = isset( $_POST['field'] ) ? wp_unslash( $_POST['field'] ) : array();
                $insurable_formula = ! empty( $_POST['insurable_formula'] );

                $res = self::upsert_record( $user_id, $center_id, $jyear, $jmonth, $raw, $insurable_formula );
                if ( is_wp_error( $res ) ) {
                        wp_die( esc_html( $res->get_error_message() ) );
                }
                $record_id = $res['record_id'];

                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-salary-register', 'jyear' => $jyear, 'jmonth' => $jmonth, 'center_id' => $center_id, 'saved' => $record_id ), admin_url( 'admin.php' ) ) );
                exit;
        }

        /**
         * حذف رکورد (تکی) — نسخه 1.6.2: اعلان تعداد حذف‌شده
         *
         * @return void
         */
        public static function delete_record() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_salary_del_salary' );
                global $wpdb;
                $id      = (int) ( (isset($_GET['id'] )?$_GET['id'] : 0 ));
                $deleted = 0;
                if ( $id ) {
                        /* نسخه 1.7.8: کاربرِ رکورد پیش از حذف برای همگام‌سازی مجدد پروفایل */
                        $rec_user = (int) $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$wpdb->prefix}tpp_salary_records WHERE id = %d", $id ) ); // phpcs:ignore
                        $deleted = (int) $wpdb->delete( $wpdb->prefix . 'tpp_salary_records', array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore
                        if ( $deleted && $rec_user ) {
                                /* پروفایل با آخرین فیش باقی‌مانده همگام می‌شود (یا دست‌نخورده می‌ماند اگر فیشی نماند). */
                                tpp_salary_sync_profile_from_latest_record( $rec_user );
                        }
                }
                $back = wp_get_referer();
                $back = $back ? $back : admin_url( 'admin.php?page=tpp-salary-records' );
                wp_safe_redirect( add_query_arg( array( 'deleted' => $deleted ), $back ) );
                exit;
        }

        /**
         * حذف گروهی حقوق‌های ثبت‌شده — نسخه 1.6.2
         *
         * @return void
         */
        public static function delete_records_bulk() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_salary_del_salary_bulk' );
                $ids = tpp_salary_ids_from_request( ( isset( $_POST['ids'] ) ? $_POST['ids'] : array() ) );
                self::bulk_delete_records( $ids );
                $back = wp_get_referer();
                $back = $back ? $back : admin_url( 'admin.php?page=tpp-salary-records' );
                wp_safe_redirect( add_query_arg( array( 'deleted' => count( $ids ) ), $back ) );
                exit;
        }

        /**
         * هسته حذف گروهی رکوردها (قابل‌تست) — نسخه 1.6.2
         *
         * @param int[] $ids شناسه‌های رکورد.
         * @return int تعداد حذف‌شده.
         */
        public static function bulk_delete_records( $ids ) {
                global $wpdb;
                $ids = tpp_salary_ids_from_request( $ids );
                if ( empty( $ids ) ) {
                        return 0;
                }
                $table = $wpdb->prefix . 'tpp_salary_records';
                $ph    = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
                /* نسخه 1.7.8: کاربرانِ رکوردها پیش از حذف — پروفایل هر یک با آخرین فیش باقی‌مانده همگام می‌شود. */
                $user_ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$table} WHERE id IN ({$ph})", $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
                $deleted  = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$ph})", $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
                if ( $deleted ) {
                        foreach ( array_map( 'intval', (array) $user_ids ) as $uid ) {
                                if ( $uid ) {
                                        tpp_salary_sync_profile_from_latest_record( $uid );
                                }
                        }
                }
                return $deleted;
        }

        /**
         * پیلود «پر کردن فیلدها بر اساس حقوق گذشته» — نسخه 1.6.3
         *
         * رکورد ثبت‌شدهٔ کارمند در دورهٔ انتخابی را می‌خواند و مقادیر فیلدها را
         * برای پرکردن فرم مرحله ۳ برمی‌گرداند. اولویت با رکورد «همان مرکز» است؛
         * اگر در آن دوره فقط رکورد مرکز دیگری موجود باشد (جابه‌جایی کارمند)،
         * همان استفاده می‌شود و در پاسخ علامت‌گذاری می‌گردد.
         *
         * @param int $user_id   کاربر.
         * @param int $center_id مرکز فرم جاری.
         * @param int $src_year  سال دورهٔ مبدأ.
         * @param int $src_month ماه دورهٔ مبدأ.
         * @return array|false آرایه پیلود، یا false اگر رکوردی یافت نشد. WP_Error برای پارامتر ناقص.
         */
        public static function past_salary_payload( $user_id, $center_id, $src_year, $src_month ) {
                global $wpdb;
                $user_id   = (int) $user_id;
                $center_id = (int) $center_id;
                $src_year  = (int) $src_year;
                $src_month = (int) $src_month;
                if ( ! $user_id || ! $src_year || ! $src_month ) {
                        return new WP_Error( 'tpp_salary_params', 'پارامترهای ناقص' );
                }
                $table = $wpdb->prefix . 'tpp_salary_records';
                $rec   = $wpdb->get_row( $wpdb->prepare(
                        "SELECT * FROM {$table} WHERE user_id = %d AND jyear = %d AND jmonth = %d ORDER BY (center_id = %d) DESC, id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                        $user_id,
                        $src_year,
                        $src_month,
                        $center_id
                ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                if ( ! $rec ) {
                        return false;
                }
                $payload = tpp_salary_record_payload( $rec );
                $manual  = isset( $payload['manual'] ) && is_array( $payload['manual'] ) ? $payload['manual'] : array();
                $fields  = array_values( array_filter( tpp_salary_get_fields(), function ( $f ) {
                        return tpp_salary_field_in_record( $f );
                } ) );
                $values = array();
                foreach ( $fields as $f ) {
                        $key = $f->field_key;
                        $v   = isset( $payload[ $key ] ) ? $payload[ $key ] : '';
                        /* قالب‌بندی هم‌سان با رندر فرم — اعداد لاتین با جداکننده هزار تا موتور JS بلافاصله بخواند. */
                        $values[ $key ] = ( 'number' === $f->field_type ) ? tpp_salary_format_number( (float) $v, false ) : (string) $v;
                }
                $src_center = tpp_salary_get_center( (int) $rec->center_id );
                return array(
                        'user_id'        => $user_id,
                        'center_id'      => (int) $rec->center_id,
                        'center_name'    => $src_center ? $src_center->name : '',
                        'jyear'          => $src_year,
                        'jmonth'         => $src_month,
                        'same_center'    => ( (int) $rec->center_id === $center_id ),
                        'period_label'   => TppSalary_Jalali::period_label( $src_year, $src_month ),
                        'values'         => $values,
                        'manual'         => array_values( array_map( 'strval', $manual ) ),
                        'insurable_mode' => ( isset( $payload['insurable_mode'] ) && 'formula' === $payload['insurable_mode'] ) ? 'formula' : 'profile',
                );
        }

        /**
         * فهرست حقوق‌های ثبت‌شده با جستجو
         *
         * @return void
         */
        public static function render_records() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                global $wpdb;
                $table  = $wpdb->prefix . 'tpp_salary_records';
                $jyear  = isset( $_GET['jyear'] ) ? (int) $_GET['jyear'] : 0;
                $jmonth = isset( $_GET['jmonth'] ) ? (int) $_GET['jmonth'] : 0;
                $center = (int) ( (isset($_GET['center_id'] )?$_GET['center_id'] : 0 ));
                $search = sanitize_text_field( wp_unslash( (isset($_GET['s'] )?$_GET['s'] : '' )) );

                $where  = ' WHERE 1=1';
                $params = array();
                if ( $jyear ) {
                        $where   .= ' AND r.jyear = %d';
                        $params[] = $jyear;
                }
                if ( $jmonth ) {
                        $where   .= ' AND r.jmonth = %d';
                        $params[] = $jmonth;
                }
                if ( $center ) {
                        $where   .= ' AND r.center_id = %d';
                        $params[] = $center;
                }
                if ( $search ) {
                        $search_en = TppSalary_Jalali::digits_en( $search );
                        /* نسخه 1.4.1: جستجو بر اساس نام کارمند «یا» کد ملی */
                        $where   .= ' AND ( u.display_name LIKE %s OR EXISTS (
                                SELECT 1 FROM {$wpdb->usermeta} um
                                WHERE um.user_id = u.ID AND um.meta_key = %s AND um.meta_value LIKE %s ) )';
                        $params[] = '%' . $wpdb->esc_like( $search ) . '%';
                        $params[] = 'tpp_salary_national_id';
                        $params[] = '%' . $wpdb->esc_like( $search_en ) . '%';
                }
                $sql = "SELECT r.*, u.display_name, c.name AS center_name FROM {$table} r
                        JOIN {$wpdb->users} u ON u.ID = r.user_id
                        LEFT JOIN {$wpdb->prefix}tpp_salary_centers c ON c.id = r.center_id
                        {$where} ORDER BY r.jyear DESC, r.jmonth DESC, r.id ASC" . ( $params ? '' : '' );

                /*
                 * نسخه 1.6.1: صفحه‌بندی نتایج جستجو/فیلتر — همه ردیف‌ها یک‌جا
                 * رندر نمی‌شوند؛ لینک صفحات همه فیلترها را حفظ می‌کند.
                 */
                $per    = tpp_salary_list_per_page();
                $paged  = tpp_salary_current_paged();
                $offset = ( $paged - 1 ) * $per;

                $count_sql = "SELECT COUNT(*) FROM {$table} r
                        JOIN {$wpdb->users} u ON u.ID = r.user_id
                        LEFT JOIN {$wpdb->prefix}tpp_salary_centers c ON c.id = r.center_id
                        {$where}";
                $count_sql = $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql; // phpcs:ignore
                $total     = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore
                $pages     = max( 1, (int) ceil( $total / $per ) );
                $paged     = min( $paged, $pages );
                $offset    = ( $paged - 1 ) * $per;

                $sql .= ' LIMIT %d OFFSET %d';
                $params[] = $per;
                $params[] = $offset;
                $sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore
                $rows = $wpdb->get_results( $sql ); // phpcs:ignore

                $settings = tpp_salary_get_settings();
                $fields   = tpp_salary_get_fields();
                ?>
                <div class="wrap tpp-wrap" dir="rtl">
                        <h1>حقوق‌های ثبت‌شده</h1>
                        <?php
                        /* نسخه 1.6.2: اعلان تعداد حذف‌شده (تکی/گروهی). */
                        $del_count = isset( $_GET['deleted'] ) ? absint( $_GET['deleted'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        if ( $del_count ) :
                                ?>
                                <div class="notice notice-success"><p><?php echo esc_html( TppSalary_Jalali::digits_fa( $del_count ) ); ?> رکورد حقوق حذف شد.</p></div>
                        <?php endif; ?>
                        <form method="get" style="margin:12px 0">
                                <input type="hidden" name="page" value="tpp-salary-records">
                                <input type="search" name="s" placeholder="جستجوی نام کارمند…" value="<?php echo esc_attr( $search ); ?>">
                                <select name="jyear">
                                        <option value="">همه سال‌ها</option>
                                        <?php
                                        $today = TppSalary_Jalali::today();
                                        for ( $y = $today[0] - 6; $y <= $today[0] + 1; $y++ ) :
                                                ?>
                                                <option value="<?php echo $y; ?>" <?php selected( $jyear, $y ); ?>><?php echo TppSalary_Jalali::digits_fa( $y ); ?></option>
                                        <?php endfor; ?>
                                </select>
                                <select name="jmonth">
                                        <option value="">همه ماه‌ها</option>
                                        <?php foreach ( TppSalary_Jalali::months() as $m => $label ) : ?>
                                                <option value="<?php echo $m; ?>" <?php selected( $jmonth, $m ); ?>><?php echo esc_html( $label ); ?></option>
                                        <?php endforeach; ?>
                                </select>
                                <select name="center_id">
                                        <option value="">همه مراکز</option>
                                        <?php foreach ( tpp_salary_get_centers() as $c ) : ?>
                                                <option value="<?php echo (int) $c->id; ?>" <?php selected( $center, $c->id ); ?>><?php echo esc_html( $c->name ); ?></option>
                                        <?php endforeach; ?>
                                </select>
                                <button class="button">اعمال فیلتر</button>
                        </form>
                        <?php
                        /* نسخه 1.6.2: فرم حذف گروهی — جستجو/فیلتر فرم جداگانه GET است و تداخلی ندارد. */
                        ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-tpp-bulk>
                                <?php wp_nonce_field( 'tpp_salary_del_salary_bulk' ); ?>
                                <input type="hidden" name="action" value="tpp_salary_del_salary_bulk">
                                <div class="tablenav top" style="display:flex;gap:6px;align-items:center;padding:4px 0">
                                        <select name="bulk_action" data-tpp-bulk-action>
                                                <option value="">اقدام گروهی</option>
                                                <option value="delete">حذف</option>
                                        </select>
                                        <?php submit_button( 'اعمال', 'action', 'submit_bulk', false, array( 'onclick' => 'return true;' ) ); ?>
                                        <span class="description">پس از انتخاب ردیف‌ها، «حذف» را برگزینید — حذف برگشت‌پذیر نیست.</span>
                                </div>
                        <table class="widefat striped">
                                <thead><tr>
                                        <th style="width:32px"><input type="checkbox" class="tpp-cb-all" aria-label="انتخاب همه"></th>
                                        <th>سال/ماه</th><th>مرکز</th><th>کارمند</th>
                                        <?php foreach ( $fields as $f ) : ?>
                                                <?php if ( in_array( $f->field_key, array( 'gross', 'insurable', 'insurance_deduct', 'other_deductions', 'net' ), true ) ) : ?>
                                                        <th><?php echo esc_html( $f->label ); ?></th>
                                                <?php endif; ?>
                                        <?php endforeach; ?>
                                        <th>عملیات</th>
                                </tr></thead>
                                <tbody>
                                <?php if ( empty( $rows ) ) : ?>
                                        <tr><td colspan="<?php echo 5 + 5; ?>">رکوردی یافت نشد.</td></tr>
                                <?php endif; ?>
                                <?php foreach ( $rows as $r ) : ?>
                                        <tr>
                                                <td><input type="checkbox" class="tpp-cb" name="ids[]" value="<?php echo (int) $r->id; ?>"></td>
                                                <td><?php echo esc_html( TppSalary_Jalali::period_label( $r->jyear, $r->jmonth ) ); ?></td>
                                                <td><?php echo esc_html( $r->center_name ); ?></td>
                                                <td><strong><?php echo esc_html( $r->display_name ); ?></strong></td>
                                                <td class="<?php echo (float) $r->gross < 0 ? 'tpp-neg' : ''; ?>"><?php echo esc_html( tpp_salary_format_number( (float) $r->gross ) ); ?></td>
                                                <td class="<?php echo (float) $r->insurable < 0 ? 'tpp-neg' : ''; ?>"><?php echo esc_html( tpp_salary_format_number( (float) $r->insurable ) ); ?></td>
                                                <td class="<?php echo (float) $r->insurance_deduct < 0 ? 'tpp-neg' : ''; ?>"><?php echo esc_html( tpp_salary_format_number( (float) $r->insurance_deduct ) ); ?></td>
                                                <td class="<?php echo (float) $r->other_deductions < 0 ? 'tpp-neg' : ''; ?>"><?php echo esc_html( tpp_salary_format_number( (float) $r->other_deductions ) ); ?></td>
                                                <td class="<?php echo (float) $r->net < 0 ? 'tpp-neg' : ''; ?>"><strong><?php echo esc_html( tpp_salary_format_number( (float) $r->net ) ); ?></strong></td>
                                                <td>
                                                        <?php $edit = add_query_arg( array( 'page' => 'tpp-salary-register', 'jyear' => $r->jyear, 'jmonth' => $r->jmonth, 'center_id' => $r->center_id, 'user_id' => $r->user_id ), admin_url( 'admin.php' ) ); ?>
                                                        <a class="button button-small" href="<?php echo esc_url( $edit ); ?>">ویرایش</a>
                                                        <?php $pdf = add_query_arg( array( 'action' => 'tpp_salary_payslip_pdf', 'record_id' => $r->id, '_wpnonce' => wp_create_nonce( 'tpp_salary_payslip_pdf' ) ), admin_url( 'admin-ajax.php' ) ); ?>
                                                        <a class="button button-small" target="_blank" href="<?php echo esc_url( $pdf ); ?>">فیش PDF</a>
                                                        <?php
                                                        /* نسخه 1.6.2: دکمه حذف تکی در فهرست (قبلاً فقط در صفحه ثبت حقوق بود). */
                                                        $du = wp_nonce_url( admin_url( 'admin-post.php?action=tpp_salary_del_salary&id=' . (int) $r->id ), 'tpp_salary_del_salary' );
                                                        ?>
                                                        <a class="button button-small" style="color:#b32d2e" href="<?php echo esc_url( $du ); ?>" onclick="return confirm('حذف این رکورد حقوق؟ برگشت‌پذیر نیست.')">حذف</a>
                                                </td>
                                        </tr>
                                <?php endforeach; ?>
                                </tbody>
                        </table>
                        </form>
                        <?php
                        tpp_salary_pagination( $total, $per ); // نسخه 1.6.1: صفحه‌بندی نتایج جستجو
                        tpp_salary_bulk_table_script( 'همه حقوق‌های انتخاب‌شده برای همیشه حذف می‌شوند. مطمئن هستید؟' ); // نسخه 1.6.2
                        ?>
                </div>
                <?php
        }
}
}
// TPP_SALARY GUARD END
