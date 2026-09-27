<?php
/**
 * پنل کارمند — شورت‌کد مشاهده فیش‌های حقوقی و ثبت اطلاعات بانکی
 *
 * @package TppSalary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class TppSalary_Frontend
 */
if ( ! class_exists( 'TppSalary_Frontend' ) ) {
        // TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TppSalary_Frontend {

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
                wp_register_style( 'tpp-salary-frontend', TPP_SALARY_URL . 'assets/tpp-salary-frontend.css', array(), TPP_SALARY_VERSION );
        }

        /**
         * شورت‌کد پنل
         *
         * نسخه 1.7.7 — بازطراحی نمایش:
         *  - فیش‌ها پیش از فرم بانکی (تمرکز صفحه روی دریافت فیش)
         *  - کارت‌های آماری (تعداد فیش / آخرین دوره / جمع خالص پرداختی)
         *  - گروه‌بندی فیش‌ها بر اساس سال شمسی (سال جاری باز، سال‌های قبل بسته)
         *  - نشان دوره، نشان «جدید» برای آخرین دوره، خالص سبز + واحد پول
         *  - حالت خالی گویا، راهنمای فرم بانکی، دکمه ذخیره سبز، ریسپانسیو
         *
         * @return string
         */
        public static function panel_shortcode() {
                if ( ! is_user_logged_in() ) {
                        return '<div class="tpp-notice">' . esc_html__( 'برای مشاهده، وارد حساب کاربری خود شوید.', 'tpp-salary' ) . '</div>';
                }
                $user     = wp_get_current_user();
                $profile  = tpp_salary_get_profile( $user->ID );
                $banks    = tpp_salary_get_banks();
                $records  = self::user_records( $user->ID );
                $settings = tpp_salary_get_settings();
                $currency = ( isset( $settings['currency'] ) && '' !== $settings['currency'] ) ? $settings['currency'] : 'ریال';
                $company  = ( isset( $settings['company_name'] ) && '' !== $settings['company_name'] ) ? $settings['company_name'] : '';

                /* آمار — رکوردها نزولی (جدیدترین اول) هستند. */
                $count     = count( $records );
                $total_net = 0.0;
                foreach ( $records as $r ) {
                        $total_net += (float) $r->net;
                }
                $last     = $count ? $records[0] : null;
                $last_key = $last ? ( (int) $last->jyear . '-' . (int) $last->jmonth ) : '';

                /* گروه‌بندی بر اساس سال شمسی — جدیدترین سال بالا. */
                $by_year = array();
                foreach ( $records as $r ) {
                        $by_year[ (int) $r->jyear ][] = $r;
                }
                krsort( $by_year );

                ob_start();
                wp_enqueue_style( 'tpp-salary-frontend' );
                ?>
                <div class="tpp-panel" dir="rtl">
                        <h3 class="tpp-panel-title">پنل حقوق و دستمزد — <?php echo esc_html( $user->display_name ); ?><?php echo $company ? ' <span class="tpp-chip">' . esc_html( $company ) . '</span>' : ''; ?></h3>
                        <p class="tpp-sub">فیش‌های حقوقی خود را مشاهده کنید یا نسخه PDF هر دوره را دانلود نمایید.</p>

                        <?php if ( isset( $_GET['tpp_salary_saved'] ) ) : ?>
                                <div class="tpp-notice tpp-success">اطلاعات بانکی ذخیره شد.</div>
                        <?php endif; ?>

                        <?php if ( $count ) : ?>
                        <div class="tpp-stats">
                                <div class="tpp-stat">
                                        <span class="tpp-stat-num"><?php echo esc_html( tpp_salary_format_number( $count ) ); ?></span>
                                        <span class="tpp-stat-label">فیش صادرشده</span>
                                </div>
                                <div class="tpp-stat">
                                        <span class="tpp-stat-num tpp-stat-sm"><?php echo esc_html( $last ? TppSalary_Jalali::period_label( (int) $last->jyear, (int) $last->jmonth ) : '' ); ?></span>
                                        <span class="tpp-stat-label">آخرین دوره</span>
                                </div>
                                <div class="tpp-stat">
                                        <span class="tpp-stat-num tpp-stat-net"><?php echo esc_html( tpp_salary_format_number( $total_net ) ); ?></span>
                                        <span class="tpp-stat-label">جمع خالص پرداختی (<?php echo esc_html( $currency ); ?>)</span>
                                </div>
                        </div>
                        <?php endif; ?>

                        <h4 class="tpp-sec-title">فیش‌های حقوقی من</h4>
                        <?php if ( empty( $records ) ) : ?>
                                <div class="tpp-empty">هنوز فیشی برای شما ثبت نشده است.<small>پس از صدور فیش توسط واحد حقوق و دستمزد، در همین بخش نمایش داده می‌شود.</small></div>
                        <?php else : ?>
                                <?php $first_group = true; foreach ( $by_year as $y => $rows ) : ?>
                                <details class="tpp-year"<?php echo $first_group ? ' open' : ''; ?>>
                                        <summary>
                                                <span>سال <?php echo esc_html( TppSalary_Jalali::digits_fa( $y ) ); ?></span>
                                                <span class="tpp-year-count"><?php echo esc_html( tpp_salary_format_number( count( $rows ) ) ); ?> فیش</span>
                                        </summary>
                                        <div class="tpp-table-wrap">
                                        <table class="tpp-table tpp-slip-table">
                                                <thead><tr><th>دوره</th><th>مرکز</th><th>خالص پرداختی (<?php echo esc_html( $currency ); ?>)</th><th>دریافت فیش</th></tr></thead>
                                                <tbody>
                                                <?php foreach ( $rows as $r ) :
                                                        $c     = tpp_salary_get_center( (int) $r->center_id );
                                                        $isnew = ( $last_key === ( (int) $r->jyear . '-' . (int) $r->jmonth ) );
                                                        $view  = add_query_arg( array( 'tpp_salary_action' => 'view_payslip', 'record_id' => (int) $r->id, 'tpp_salary_nonce' => wp_create_nonce( 'tpp_salary_view_' . $r->id ) ), get_permalink() );
                                                        $pdf   = add_query_arg( array( 'action' => 'tpp_salary_employee_payslip', 'record_id' => (int) $r->id, '_wpnonce' => wp_create_nonce( 'tpp_salary_ajax' ) ), admin_url( 'admin-ajax.php' ) );
                                                        ?>
                                                        <tr<?php echo $isnew ? ' class="tpp-row-new"' : ''; ?>>
                                                                <td><span class="tpp-badge tpp-badge-period"><?php echo esc_html( TppSalary_Jalali::period_label( (int) $r->jyear, (int) $r->jmonth ) ); ?></span><?php echo $isnew ? ' <span class="tpp-badge tpp-badge-new">جدید</span>' : ''; ?></td>
                                                                <td><span class="tpp-badge tpp-badge-soft"><?php echo esc_html( $c ? $c->name : '—' ); ?></span></td>
                                                                <td class="tpp-net-cell"><span class="tpp-net" dir="ltr"><?php echo esc_html( tpp_salary_format_number( (float) $r->net ) ); ?></span></td>
                                                                <td class="tpp-actions-cell">
                                                                        <a class="tpp-btn tpp-btn-small" target="_blank" rel="noopener" href="<?php echo esc_url( $view ); ?>">مشاهده</a>
                                                                        <a class="tpp-btn tpp-btn-small tpp-btn-ghost" target="_blank" rel="noopener" href="<?php echo esc_url( $pdf ); ?>">دانلود PDF</a>
                                                                </td>
                                                        </tr>
                                                <?php endforeach; ?>
                                                </tbody>
                                        </table>
                                        </div>
                                </details>
                                <?php $first_group = false; endforeach; ?>
                        <?php endif; ?>

                        <h4 class="tpp-sec-title">حساب‌های بانکی من</h4>
                        <p class="tpp-help">شماره حساب، شبا و کارت هر بانک را وارد کنید تا واحد حقوق و دستمزد از آن برای واریز حقوق استفاده کند. شماره شبا به‌صورت IR + ۲۴ رقم است.</p>
                        <form method="post" class="tpp-bank-form">
                                <?php wp_nonce_field( 'tpp_salary_save_banks' ); ?>
                                <input type="hidden" name="tpp_salary_action" value="save_banks">
                                <div class="tpp-table-wrap">
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
                                                        <td class="tpp-bank-name"><?php echo esc_html( $b->name ); ?></td>
                                                        <td><input type="text" dir="ltr" inputmode="numeric" name="tpp_salary_bank[<?php echo (int) $b->id; ?>][account]" value="<?php echo esc_attr( (isset($acc['account'] )?$acc['account'] : '' )); ?>" placeholder="1234567890"></td>
                                                        <td><input type="text" dir="ltr" name="tpp_salary_bank[<?php echo (int) $b->id; ?>][sheba]" value="<?php echo esc_attr( (isset($acc['sheba'] )?$acc['sheba'] : '' )); ?>" placeholder="IR"></td>
                                                        <td><input type="text" dir="ltr" inputmode="numeric" name="tpp_salary_bank[<?php echo (int) $b->id; ?>][card]" value="<?php echo esc_attr( (isset($acc['card'] )?$acc['card'] : '' )); ?>" placeholder="0000000000000000"></td>
                                                </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                </table>
                                </div>
                                <button type="submit" class="tpp-btn tpp-btn-green">ذخیره اطلاعات بانکی</button>
                        </form>
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
                if ( ! isset( $_POST['tpp_salary_action'] ) || 'save_banks' !== $_POST['tpp_salary_action'] ) {
                        return;
                }
                if ( ! is_user_logged_in() || ! current_user_can( 'tpp_salary_view' ) ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_salary_save_banks' );

                $user_id = get_current_user_id();
                $profile = tpp_salary_get_profile( $user_id );
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

                $redirect = remove_query_arg( 'tpp_salary_saved', wp_get_referer() );
                wp_safe_redirect( add_query_arg( 'tpp_salary_saved', '1', $redirect ? $redirect : home_url() ) );
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
                if ( ! isset( $_GET['tpp_salary_action'] ) || 'view_payslip' !== $_GET['tpp_salary_action'] ) {
                        return;
                }
                if ( ! is_user_logged_in() ) {
                        wp_die( esc_html__( 'برای مشاهده، وارد حساب کاربری خود شوید.', 'tpp-salary' ) );
                }
                $record_id = isset( $_GET['record_id'] ) ? (int) $_GET['record_id'] : 0;
                check_admin_referer( 'tpp_salary_view_' . $record_id, 'tpp_salary_nonce' );
                $record = tpp_salary_get_record( $record_id );
                if ( ! $record || ( (int) $record->user_id !== get_current_user_id() && ! tpp_salary_can_manage() ) ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }

                $user     = get_userdata( $record->user_id );
                $center   = tpp_salary_get_center( (int) $record->center_id );
                $payload  = tpp_salary_record_payload( $record );
                $manual   = isset( $payload['manual'] ) && is_array( $payload['manual'] ) ? $payload['manual'] : array();
                $fields   = tpp_salary_get_fields();
                $settings = tpp_salary_get_settings();
                /* نسخه 1.7.1: همه اعداد انگلیسی — متغیر $fa فقط برای سازگاری با false ثابت می‌شود. */
                $fa       = false;

                nocache_headers();
                ?>
                <!DOCTYPE html>
                <html <?php language_attributes(); ?> dir="rtl">
                <head>
                        <meta charset="<?php bloginfo( 'charset' ); ?>">
                        <meta name="viewport" content="width=device-width, initial-scale=1">
                        <title><?php echo esc_html( 'فیش حقوقی — ' . ( $user ? $user->display_name : '' ) . ' — ' . TppSalary_Jalali::period_label( (int) $record->jyear, (int) $record->jmonth ) ); ?></title>
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
                                td.num.tpp-neg{color:#dc2626;font-weight:700}
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
                                <div class="sub">فیش حقوقی دوره <?php echo esc_html( TppSalary_Jalali::period_label( (int) $record->jyear, (int) $record->jmonth ) ); ?></div>
                                <div class="meta">
                                        <div><strong>کارمند:</strong> <?php echo esc_html( $user ? $user->display_name : '?' ); ?></div>
                                        <div><strong>مرکز:</strong> <?php echo esc_html( $center ? $center->name : '?' ); ?></div>
                                        <div><strong>واحد پول:</strong> <?php echo esc_html( $settings['currency'] ); ?></div>
                                        <div><strong>تاریخ چاپ:</strong> <?php echo esc_html( tpp_salary_today_fa() ); ?></div>
                                </div>
                                <table>
                                        <thead><tr><th>#</th><th>شرح</th><th style="width:190px">مبلغ (<?php echo esc_html( $settings['currency'] ); ?>)</th></tr></thead>
                                        <tbody>
                                        <?php $i = 0; foreach ( $fields as $f ) : if ( empty( $f->show_in_payslip ) || ! isset( $payload[ $f->field_key ] ) ) { continue; } $i++;
                                                $is_num  = ( 'number' === $f->field_type );
                                                $val     = $payload[ $f->field_key ];
                                                $shown   = $is_num ? tpp_salary_format_number( (float) $val, $fa ) : (string) $val;
                                                $is_sum  = in_array( $f->field_key, array( 'gross', 'insurable', 'insurance_deduct', 'other_deductions' ), true );
                                        ?>
                                                <tr class="<?php echo 'net' === $f->field_key ? 'net' : ( $is_sum ? 'summary' : '' ); ?>">
                                                        <td><?php echo esc_html( tpp_salary_format_number( $i, $fa ) ); ?></td>
                                                        <td><?php echo esc_html( $f->label ); ?><?php echo in_array( $f->field_key, $manual, true ) ? ' <span style="color:#94a3b8">(دستی)</span>' : ''; ?></td>
                                                        <td class="num<?php echo ( $is_num && (float) $val < 0 ) ? ' tpp-neg' : ''; ?>"><?php echo esc_html( $shown ); ?></td>
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
