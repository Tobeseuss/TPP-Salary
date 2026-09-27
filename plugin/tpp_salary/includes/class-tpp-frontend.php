<?php
/**
 * پنل کارمند — شورت‌کد مشاهده فیش‌های حقوقی و ثبت اطلاعات بانکی
 *
 * @package TPP_Salary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class TPP_Frontend
 */
if ( ! class_exists( 'TPP_Frontend' ) ) {
        // TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TPP_Frontend {

        /**
         * مقداردهی اولیه
         *
         * @return void
         */
        public static function init() {
                add_shortcode( 'tpp_salary_panel', array( __CLASS__, 'panel_shortcode' ) );
                add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
                add_action( 'init', array( __CLASS__, 'handle_actions' ), 20 );
                add_action( 'template_redirect', array( __CLASS__, 'maybe_view_payslip' ) );
        }

        /**
         * استایل فرانت
         *
         * @return void
         */
        public static function assets() {
                wp_register_style( 'tpp-frontend', TPP_SALARY_URL . 'assets/tpp-frontend.css', array(), TPP_SALARY_VERSION );
        }

        /**
         * شورت‌کد پنل
         *
         * @return string
         */
        public static function panel_shortcode() {
                if ( ! is_user_logged_in() ) {
                        return '<div class="tpp-notice">' . esc_html__( 'برای مشاهده، وارد حساب کاربری خود شوید.', 'tpp-salary' ) . '</div>';
                }
                $user    = wp_get_current_user();
                $profile = tpp_get_profile( $user->ID );
                $banks   = tpp_get_banks();
                $records = self::user_records( $user->ID );
                ob_start();
                wp_enqueue_style( 'tpp-frontend' );
                ?>
                <div class="tpp-panel" dir="rtl">
                        <h3 class="tpp-panel-title">پنل حقوق و دستمزد — <?php echo esc_html( $user->display_name ); ?></h3>

                        <?php if ( isset( $_GET['tpp_saved'] ) ) : ?>
                                <div class="tpp-notice tpp-success">اطلاعات بانکی ذخیره شد.</div>
                        <?php endif; ?>

                        <h4>حساب‌های بانکی من</h4>
                        <form method="post" class="tpp-bank-form">
                                <?php wp_nonce_field( 'tpp_save_banks' ); ?>
                                <input type="hidden" name="tpp_action" value="save_banks">
                                <table class="tpp-table">
                                        <thead><tr><th>بانک</th><th>شماره حساب</th><th>شماره شبا</th><th>شماره کارت</th></tr></thead>
                                        <tbody>
                                        <?php if ( empty( $banks ) ) : ?>
                                                <tr><td colspan="4">بانکی تعریف نشده است.</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ( $banks as $b ) :
                                                $acc = (isset($profile['bank_accounts'][ $b->id ] )?$profile['bank_accounts'][ $b->id ] : array());
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
                                <button type="submit" class="tpp-btn">ذخیره اطلاعات بانکی</button>
                        </form>

                        <h4>فیش‌های حقوقی من</h4>
                        <table class="tpp-table">
                                <thead><tr><th>دوره</th><th>مرکز</th><th>خالص پرداختی</th><th>فیش</th></tr></thead>
                                <tbody>
                                <?php if ( empty( $records ) ) : ?>
                                        <tr><td colspan="4">فیشی برای شما ثبت نشده است.</td></tr>
                                <?php endif; ?>
                                <?php foreach ( $records as $r ) :
                                        $c = tpp_get_center( (int) $r->center_id );
                                        $view = add_query_arg( array( 'tpp_action' => 'view_payslip', 'record_id' => (int) $r->id, 'tpp_nonce' => wp_create_nonce( 'tpp_view_' . $r->id ) ), get_permalink() );
                                        $pdf  = add_query_arg( array( 'action' => 'tpp_employee_payslip', 'record_id' => (int) $r->id, '_wpnonce' => wp_create_nonce( 'tpp_ajax' ) ), admin_url( 'admin-ajax.php' ) );
                                        ?>
                                        <tr>
                                                <td><?php echo esc_html( TPP_Jalali::period_label( (int) $r->jyear, (int) $r->jmonth ) ); ?></td>
                                                <td><?php echo esc_html( $c ? $c->name : '' ); ?></td>
                                                <td dir="ltr"><?php echo esc_html( tpp_format_number( (float) $r->net ) ); ?></td>
                                                <td>
                                                        <a class="tpp-btn tpp-btn-small" href="<?php echo esc_url( $view ); ?>">مشاهده</a>
                                                        <a class="tpp-btn tpp-btn-small" target="_blank" href="<?php echo esc_url( $pdf ); ?>">دانلود PDF</a>
                                                </td>
                                        </tr>
                                <?php endforeach; ?>
                                </tbody>
                        </table>
                </div>
                <?php
                return ob_get_clean();
        }

        /**
         * رکوردهای کاربر مرتب‌شده
         *
         * @param int $user_id کاربر.
         * @return array
         */
        private static function user_records( $user_id ) {
                global $wpdb;
                return (array) $wpdb->get_results( // phpcs:ignore
                        $wpdb->prepare(
                                "SELECT * FROM {$wpdb->prefix}tpp_salary_records WHERE user_id = %d ORDER BY jyear DESC, jmonth DESC", // phpcs:ignore
                                $user_id
                        )
                );
        }

        /**
         * پردازش اکشن‌های فرانت (ذخیره اطلاعات بانکی)
         *
         * @return void
         */
        public static function handle_actions() {
                if ( ! isset( $_POST['tpp_action'] ) || 'save_banks' !== $_POST['tpp_action'] ) {
                        return;
                }
                if ( ! is_user_logged_in() || ! current_user_can( 'tpp_view_salary' ) ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_save_banks' );

                $user_id = get_current_user_id();
                $profile = tpp_get_profile( $user_id );
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

                $redirect = remove_query_arg( 'tpp_saved', wp_get_referer() );
                wp_safe_redirect( add_query_arg( 'tpp_saved', '1', $redirect ? $redirect : home_url() ) );
                exit;
        }

        /**
         * مشاهده فیش حقوقی به‌صورت صفحه HTML (نسخه وب برای چاپ)
         *
         * فقط کارمند صاحب رکورد (یا مدیر) با نان معتبر اجازه مشاهده دارد.
         *
         * @return void
         */
        public static function maybe_view_payslip() {
                if ( ! isset( $_GET['tpp_action'] ) || 'view_payslip' !== $_GET['tpp_action'] ) {
                        return;
                }
                if ( ! is_user_logged_in() ) {
                        wp_die( esc_html__( 'برای مشاهده، وارد حساب کاربری خود شوید.', 'tpp-salary' ) );
                }
                $record_id = isset( $_GET['record_id'] ) ? (int) $_GET['record_id'] : 0;
                check_admin_referer( 'tpp_view_' . $record_id, 'tpp_nonce' );
                $record = tpp_get_record( $record_id );
                if ( ! $record || ( (int) $record->user_id !== get_current_user_id() && ! tpp_can_manage() ) ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }

                $user     = get_userdata( $record->user_id );
                $center   = tpp_get_center( (int) $record->center_id );
                $payload  = tpp_record_payload( $record );
                $manual   = isset( $payload['manual'] ) && is_array( $payload['manual'] ) ? $payload['manual'] : array();
                $fields   = tpp_get_fields();
                $settings = tpp_get_settings();
                $fa       = ! empty( $settings['digits_fa'] );

                nocache_headers();
                ?>
                <!DOCTYPE html>
                <html <?php language_attributes(); ?> dir="rtl">
                <head>
                        <meta charset="<?php bloginfo( 'charset' ); ?>">
                        <meta name="viewport" content="width=device-width, initial-scale=1">
                        <title><?php echo esc_html( 'فیش حقوقی — ' . ( $user ? $user->display_name : '' ) . ' — ' . TPP_Jalali::period_label( (int) $record->jyear, (int) $record->jmonth ) ); ?></title>
                        <style>
                                body{font-family:Vazirmatn,Tahoma,sans-serif;background:#f2f4f8;margin:0;padding:24px;color:#1e293b}
                                .slip{max-width:760px;margin:0 auto;background:#fff;border:1px solid #dbe2ef;border-radius:12px;padding:28px 32px;box-shadow:0 2px 10px rgba(15,23,42,.06)}
                                .slip h1{font-size:19px;margin:0 0 4px;text-align:center}
                                .slip .sub{text-align:center;color:#64748b;font-size:13px;margin-bottom:18px}
                                .meta{display:flex;flex-wrap:wrap;gap:8px 24px;border:1px solid #e2e8f0;border-radius:8px;padding:12px 16px;margin-bottom:18px;font-size:14px}
                                table{width:100%;border-collapse:collapse;font-size:13.5px}
                                th,td{border:1px solid #e2e8f0;padding:7px 10px;text-align:right}
                                th{background:#f1f5f9}
                                td.num{direction:ltr;text-align:left;font-variant-numeric:tabular-nums}
                                tr.summary td{background:#f8fafc;font-weight:700}
                                tr.net td{background:#e7f6ec;font-weight:800;font-size:15px}
                                .actions{max-width:760px;margin:14px auto 0;display:flex;gap:10px;justify-content:flex-end}
                                .btn{border:0;border-radius:8px;padding:10px 22px;font-family:inherit;font-size:14px;cursor:pointer;background:#2563eb;color:#fff}
                                .btn.gray{background:#475569}
                                a.btn{text-decoration:none}
                                .sign{display:flex;justify-content:space-between;margin-top:34px;color:#475569;font-size:13px}
                                .sign span{border-top:1px dashed #94a3b8;padding-top:6px;min-width:180px;text-align:center}
                                @media print{body{background:#fff;padding:0}.actions{display:none}.slip{border:0;box-shadow:none;max-width:100%}}
                        </style>
                </head>
                <body>
                        <div class="slip">
                                <h1><?php echo esc_html( $settings['company_name'] ? $settings['company_name'] : 'فیش حقوقی' ); ?></h1>
                                <div class="sub">فیش حقوقی دوره <?php echo esc_html( TPP_Jalali::period_label( (int) $record->jyear, (int) $record->jmonth ) ); ?></div>
                                <div class="meta">
                                        <div><strong>کارمند:</strong> <?php echo esc_html( $user ? $user->display_name : '?' ); ?></div>
                                        <div><strong>مرکز:</strong> <?php echo esc_html( $center ? $center->name : '?' ); ?></div>
                                        <div><strong>واحد پول:</strong> <?php echo esc_html( $settings['currency'] ); ?></div>
                                        <div><strong>تاریخ چاپ:</strong> <?php echo esc_html( tpp_today_fa() ); ?></div>
                                </div>
                                <table>
                                        <thead><tr><th>#</th><th>شرح</th><th style="width:190px">مبلغ (<?php echo esc_html( $settings['currency'] ); ?>)</th></tr></thead>
                                        <tbody>
                                        <?php $i = 0; foreach ( $fields as $f ) : if ( empty( $f->show_in_payslip ) || ! isset( $payload[ $f->field_key ] ) ) { continue; } $i++;
                                                $is_num  = ( 'number' === $f->field_type );
                                                $val     = $payload[ $f->field_key ];
                                                $shown   = $is_num ? tpp_format_number( (float) $val, $fa ) : (string) $val;
                                                $is_sum  = in_array( $f->field_key, array( 'gross', 'insurable', 'insurance_deduct', 'other_deductions' ), true );
                                        ?>
                                                <tr class="<?php echo 'net' === $f->field_key ? 'net' : ( $is_sum ? 'summary' : '' ); ?>">
                                                        <td><?php echo esc_html( tpp_format_number( $i, $fa ) ); ?></td>
                                                        <td><?php echo esc_html( $f->label ); ?><?php echo in_array( $f->field_key, $manual, true ) ? ' <span style="color:#94a3b8">(دستی)</span>' : ''; ?></td>
                                                        <td class="num"><?php echo esc_html( $shown ); ?></td>
                                                </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                </table>
                                <div class="sign"><span>امضای کارمند</span><span>امضای کارفرما</span></div>
                        </div>
                        <div class="actions">
                                <button type="button" class="btn" onclick="window.print()">چاپ / ذخیره PDF</button>
                                <a class="btn gray" href="<?php echo esc_url( wp_get_referer() ? wp_get_referer() : home_url() ); ?>">بازگشت</a>
                        </div>
                </body>
                </html>
                <?php
                exit;
        }
}
}
// TPP_SALARY GUARD END
