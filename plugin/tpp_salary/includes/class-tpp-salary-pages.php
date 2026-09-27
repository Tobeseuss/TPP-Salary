<?php
/**
 * صفحه ثبت حقوق (ویزارد ۳ مرحله‌ای) و فهرست حقوق‌های ثبت‌شده
 *
 * @package TPP_Salary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class TPP_Salary_Pages
 */
if ( ! class_exists( 'TPP_Salary_Pages' ) ) {
        // TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TPP_Salary_Pages {

        /**
         * مقداردهی اولیه
         *
         * @return void
         */
        public static function init() {
                add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
                add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
                add_action( 'admin_post_tpp_save_salary', array( __CLASS__, 'save_record' ) );
                add_action( 'admin_post_tpp_del_salary', array( __CLASS__, 'delete_record' ) );
        }

        /**
         * منو
         *
         * @return void
         */
        public static function menu() {
                add_menu_page( 'حقوق و دستمزد', 'حقوق و دستمزد', 'tpp_manage_salary', 'tpp-salary', array( __CLASS__, 'render_register' ), 'dashicons-money-alt', 26 );
                add_submenu_page( 'tpp-salary', 'ثبت حقوق', 'ثبت حقوق', 'tpp_manage_salary', 'tpp-salary-register', array( __CLASS__, 'render_register' ) );
                add_submenu_page( 'tpp-salary', 'حقوق‌های ثبت‌شده', 'حقوق‌های ثبت‌شده', 'tpp_manage_salary', 'tpp-salary-records', array( __CLASS__, 'render_records' ) );
        }

        /**
         * بارگذاری اسکریپت و استایل
         *
         * @param string $hook هوک صفحه.
         * @return void
         */
        public static function assets( $hook ) {
                if ( false !== strpos( $hook, 'tpp-' ) ) {
                        wp_enqueue_style( 'tpp-admin', TPP_SALARY_URL . 'admin/css/tpp-admin.css', array(), TPP_SALARY_VERSION );
                        wp_enqueue_script( 'tpp-admin', TPP_SALARY_URL . 'admin/js/tpp-admin.js', array( 'jquery' ), TPP_SALARY_VERSION, true );
                        wp_localize_script(
                                'tpp-admin',
                                'TPP',
                                array(
                                        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                                        'nonce'     => wp_create_nonce( 'tpp_ajax' ),
                                        'formulas'  => tpp_get_settings()['formulas'],
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
                $out = array();
                foreach ( tpp_get_fields() as $f ) {
                        $out[] = array(
                                'key'         => $f->field_key,
                                'label'       => $f->label,
                                'type'        => $f->field_type,
                                'formula'     => (string) $f->formula,
                                'calculated'  => (int) $f->is_calculated,
                                'negative'    => (int) $f->is_negative,
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
                if ( ! tpp_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                $today    = TPP_Jalali::today();
                $jyear    = isset( $_REQUEST['jyear'] ) ? (int) $_REQUEST['jyear'] : $today[0];
                $jmonth   = isset( $_REQUEST['jmonth'] ) ? (int) $_REQUEST['jmonth'] : $today[1];
                $center_id = isset( $_REQUEST['center_id'] ) ? (int) $_REQUEST['center_id'] : 0;
                $edit_user = isset( $_REQUEST['user_id'] ) ? (int) $_REQUEST['user_id'] : 0;
                $centers  = tpp_get_centers();

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
                                <div class="notice notice-warning"><p>ابتدا <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpp-centers' ) ); ?>">مرکز (پروژه/کارگاه)</a> تعریف کنید.</p></div>
                        <?php endif; ?>

                        <form method="get" action="">
                                <input type="hidden" name="page" value="tpp-salary-register">
                                <table class="form-table" role="presentation">
                                        <tr>
                                                <th>سال</th>
                                                <td><select name="jyear">
                                                        <?php for ( $y = $today[0] - 6; $y <= $today[0] + 1; $y++ ) : ?>
                                                                <option value="<?php echo $y; ?>" <?php selected( $jyear, $y ); ?>><?php echo TPP_Jalali::digits_fa( $y ); ?></option>
                                                        <?php endfor; ?>
                                                </select> <span class="description">بر اساس تاریخ سیستم: <?php echo esc_html( TPP_Jalali::digits_fa( $today[0] ) ); ?></span></td>
                                        </tr>
                                        <tr>
                                                <th>ماه</th>
                                                <td><select name="jmonth">
                                                        <?php foreach ( TPP_Jalali::months() as $m => $label ) : ?>
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
                                $center      = tpp_get_center( $center_id );
                                $employees   = tpp_get_employees( $center_id );
                                $registered  = array();
                                $unregistered = array();
                                foreach ( $employees as $u ) {
                                        $rec = tpp_get_record_period( $u->ID, $center_id, $jyear, $jmonth );
                                        if ( $rec ) {
                                                $registered[] = array( 'user' => $u, 'record' => $rec );
                                        } else {
                                                $unregistered[] = $u;
                                        }
                                }
                                $base = array( 'page' => 'tpp-salary-register', 'jyear' => $jyear, 'jmonth' => $jmonth, 'center_id' => $center_id );
                                ?>
                                <h2>مرحله ۲ — <?php echo esc_html( TPP_Jalali::period_label( $jyear, $jmonth ) ); ?> / مرکز <?php echo esc_html( $center ? $center->name : '' ); ?></h2>

                                <div class="tpp-columns">
                                        <div class="tpp-col">
                                                <h3>کارمندانی که حقوقشان ثبت نشده (<?php echo TPP_Jalali::digits_fa( count( $unregistered ) ); ?> نفر)</h3>
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
                                                <h3>کارمندانی که حقوقشان ثبت شده (<?php echo TPP_Jalali::digits_fa( count( $registered ) ); ?> نفر)</h3>
                                                <table class="widefat striped">
                                                        <tbody>
                                                        <?php if ( empty( $registered ) ) : ?>
                                                                <tr><td>هنوز رکوردی در این دوره ثبت نشده است.</td></tr>
                                                        <?php endif; ?>
                                                        <?php foreach ( $registered as $item ) :
                                                                $rec = $item['record'];
                                                                $u   = $item['user'];
                                                                $url = add_query_arg( array_merge( $base, array( 'user_id' => $u->ID ) ), admin_url( 'admin.php' ) );
                                                                $pdf = add_query_arg( array( 'action' => 'tpp_payslip_pdf', 'record_id' => $rec->id, '_wpnonce' => wp_create_nonce( 'tpp_payslip_pdf' ) ), admin_url( 'admin-ajax.php' ) );
                                                                ?>
                                                                <tr>
                                                                        <td><?php echo esc_html( $u->display_name ); ?></td>
                                                                        <td><?php echo esc_html( tpp_format_number( (float) $rec->net ) ); ?></td>
                                                                        <td style="width:200px">
                                                                                <a class="button button-small" href="<?php echo esc_url( $url ); ?>">ویرایش</a>
                                                                                <a class="button button-small" target="_blank" href="<?php echo esc_url( $pdf ); ?>">فیش PDF</a>
                                                                                <?php $du = wp_nonce_url( admin_url( 'admin-post.php?action=tpp_del_salary&id=' . (int) $rec->id ), 'tpp_del_salary' ); ?>
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
                $center  = tpp_get_center( $center_id );
                if ( ! $user || ! $center ) {
                        echo '<div class="wrap"><p>کاربر یا مرکز یافت نشد.</p></div>';
                        return;
                }
                $existing = tpp_get_record_period( $user_id, $center_id, $jyear, $jmonth );
                $payload  = $existing ? tpp_record_payload( $existing ) : array();
                $profile  = tpp_get_profile( $user_id );
                $settings = tpp_get_settings();
                $defaults = isset( $settings['defaults'] ) && is_array( $settings['defaults'] ) ? $settings['defaults'] : array();
                $fields   = tpp_get_fields();

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
                ?>
                <div class="wrap tpp-wrap" dir="rtl">
                        <h1>
                                ثبت حقوق — <?php echo esc_html( $user->display_name ); ?>
                                <span class="tpp-sub">(<?php echo esc_html( TPP_Jalali::period_label( $jyear, $jmonth ) ); ?> / <?php echo esc_html( $center->name ); ?>)</span>
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
                                <?php wp_nonce_field( 'tpp_save_salary' ); ?>
                                <input type="hidden" name="action" value="tpp_save_salary">
                                <input type="hidden" name="user_id" value="<?php echo (int) $user_id; ?>">
                                <input type="hidden" name="center_id" value="<?php echo (int) $center_id; ?>">
                                <input type="hidden" name="jyear" value="<?php echo (int) $jyear; ?>">
                                <input type="hidden" name="jmonth" value="<?php echo (int) $jmonth; ?>">

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
                                                                <?php if ( $f->is_negative ) : ?><span class="description">(منفی)</span><?php endif; ?>
                                                                <code dir="ltr" class="tpp-key-hint"><?php echo esc_html( $key ); ?></code>
                                                        </th>
                                                        <td class="tpp-input-cell">
                                                                <?php if ( 'text' === $f->field_type ) : ?>
                                                                        <input type="text" name="field[<?php echo esc_attr( $key ); ?>]" id="fld_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" data-manual="<?php echo $manual_flag ? '1' : '0'; ?>">
                                                                <?php else : ?>
                                                                        <input type="text" inputmode="decimal" dir="ltr" name="field[<?php echo esc_attr( $key ); ?>]" id="fld_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $is_num ? tpp_format_number( (float) $value, false ) : $value ); ?>" class="regular-text tpp-num <?php echo $differs ? 'tpp-blue' : ''; ?>" data-manual="<?php echo $manual_flag ? '1' : '0'; ?>" data-key="<?php echo esc_attr( $key ); ?>" <?php echo ( $is_calc && ! $manual_flag ) ? 'data-auto="1"' : ''; ?>>
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
                        <script>
                        jQuery(function($){
                                $('#tpp-other-dialog').dialog({ autoOpen: false, modal: true, width: 420, dir: 'rtl' });
                                $('#tpp-other-auto').on('click', function(){ $('#tpp-other-dialog').dialog('open'); });
                                $('#tpp-other-calc').on('click', function(){
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
         * @return array|WP_Error
         */
        public static function upsert_record( $user_id, $center_id, $jyear, $jmonth, $raw, $insurable_formula = false ) {
                if ( ! $user_id || ! $center_id || ! $jyear || ! $jmonth ) {
                        return new WP_Error( 'tpp_params', 'پارامترهای ناقص' );
                }
                $fields = tpp_get_fields();
                $values = array();
                $manual = array();
                foreach ( $fields as $f ) {
                        $key = $f->field_key;
                        $val = isset( $raw[ $key ] ) ? $raw[ $key ] : '';
                        if ( 'number' === $f->field_type ) {
                                $values[ $key ] = tpp_parse_number( $val );
                        } else {
                                $values[ $key ] = sanitize_text_field( $val );
                        }
                }
                // در حالت غیر فرمولی، «حقوق مشمول بیمه» همیشه دستی است (مقدار پنل کاربر).
                if ( ! $insurable_formula ) {
                        $manual[] = 'insurable';
                }

                $result    = tpp_compute_values( $values, $manual, $fields );
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
                $existing = tpp_get_record_period( $user_id, $center_id, $jyear, $jmonth );
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
                return array( 'status' => $status, 'record_id' => $record_id );
        }

        /**
         * ذخیره رکورد حقوق (فرم ویزارد)
         *
         * @return void
         */
        public static function save_record() {
                if ( ! tpp_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_save_salary' );

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
         * حذف رکورد
         *
         * @return void
         */
        public static function delete_record() {
                if ( ! tpp_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_del_salary' );
                global $wpdb;
                $id = (int) ( (isset($_GET['id'] )?$_GET['id'] : 0 ));
                if ( $id ) {
                        $wpdb->delete( $wpdb->prefix . 'tpp_salary_records', array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore
                }
                $back = wp_get_referer();
                wp_safe_redirect( $back ? $back : admin_url( 'admin.php?page=tpp-salary-records' ) );
                exit;
        }

        /**
         * فهرست حقوق‌های ثبت‌شده با جستجو
         *
         * @return void
         */
        public static function render_records() {
                if ( ! tpp_can_manage() ) {
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
                        $where   .= ' AND u.display_name LIKE %s';
                        $params[] = '%' . $wpdb->esc_like( $search ) . '%';
                }
                $sql = "SELECT r.*, u.display_name, c.name AS center_name FROM {$table} r
                        JOIN {$wpdb->users} u ON u.ID = r.user_id
                        LEFT JOIN {$wpdb->prefix}tpp_centers c ON c.id = r.center_id
                        {$where} ORDER BY r.jyear DESC, r.jmonth DESC, r.id ASC" . ( $params ? '' : '' );
                $sql = $params ? $wpdb->prepare( $sql, $params ) : $sql; // phpcs:ignore
                $rows = $wpdb->get_results( $sql ); // phpcs:ignore

                $settings = tpp_get_settings();
                $fields   = tpp_get_fields();
                ?>
                <div class="wrap tpp-wrap" dir="rtl">
                        <h1>حقوق‌های ثبت‌شده</h1>
                        <form method="get" style="margin:12px 0">
                                <input type="hidden" name="page" value="tpp-salary-records">
                                <input type="search" name="s" placeholder="جستجوی نام کارمند…" value="<?php echo esc_attr( $search ); ?>">
                                <select name="jyear">
                                        <option value="">همه سال‌ها</option>
                                        <?php
                                        $today = TPP_Jalali::today();
                                        for ( $y = $today[0] - 6; $y <= $today[0] + 1; $y++ ) :
                                                ?>
                                                <option value="<?php echo $y; ?>" <?php selected( $jyear, $y ); ?>><?php echo TPP_Jalali::digits_fa( $y ); ?></option>
                                        <?php endfor; ?>
                                </select>
                                <select name="jmonth">
                                        <option value="">همه ماه‌ها</option>
                                        <?php foreach ( TPP_Jalali::months() as $m => $label ) : ?>
                                                <option value="<?php echo $m; ?>" <?php selected( $jmonth, $m ); ?>><?php echo esc_html( $label ); ?></option>
                                        <?php endforeach; ?>
                                </select>
                                <select name="center_id">
                                        <option value="">همه مراکز</option>
                                        <?php foreach ( tpp_get_centers() as $c ) : ?>
                                                <option value="<?php echo (int) $c->id; ?>" <?php selected( $center, $c->id ); ?>><?php echo esc_html( $c->name ); ?></option>
                                        <?php endforeach; ?>
                                </select>
                                <button class="button">اعمال فیلتر</button>
                        </form>
                        <table class="widefat striped">
                                <thead><tr>
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
                                        <tr><td colspan="<?php echo 4 + 5; ?>">رکوردی یافت نشد.</td></tr>
                                <?php endif; ?>
                                <?php foreach ( $rows as $r ) : ?>
                                        <tr>
                                                <td><?php echo esc_html( TPP_Jalali::period_label( $r->jyear, $r->jmonth ) ); ?></td>
                                                <td><?php echo esc_html( $r->center_name ); ?></td>
                                                <td><strong><?php echo esc_html( $r->display_name ); ?></strong></td>
                                                <td><?php echo esc_html( tpp_format_number( (float) $r->gross ) ); ?></td>
                                                <td><?php echo esc_html( tpp_format_number( (float) $r->insurable ) ); ?></td>
                                                <td><?php echo esc_html( tpp_format_number( (float) $r->insurance_deduct ) ); ?></td>
                                                <td><?php echo esc_html( tpp_format_number( (float) $r->other_deductions ) ); ?></td>
                                                <td><strong><?php echo esc_html( tpp_format_number( (float) $r->net ) ); ?></strong></td>
                                                <td>
                                                        <?php $edit = add_query_arg( array( 'page' => 'tpp-salary-register', 'jyear' => $r->jyear, 'jmonth' => $r->jmonth, 'center_id' => $r->center_id, 'user_id' => $r->user_id ), admin_url( 'admin.php' ) ); ?>
                                                        <a class="button button-small" href="<?php echo esc_url( $edit ); ?>">ویرایش</a>
                                                        <?php $pdf = add_query_arg( array( 'action' => 'tpp_payslip_pdf', 'record_id' => $r->id, '_wpnonce' => wp_create_nonce( 'tpp_payslip_pdf' ) ), admin_url( 'admin-ajax.php' ) ); ?>
                                                        <a class="button button-small" target="_blank" href="<?php echo esc_url( $pdf ); ?>">فیش PDF</a>
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
