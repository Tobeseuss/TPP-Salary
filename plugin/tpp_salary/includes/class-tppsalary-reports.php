<?php
/**
 * گزارش‌ها و خروجی‌ها — لیست حقوق (اکسل/PDF)، فیش بانکی، فیش حقوقی تکی و عمده ZIP
 *
 * @package TppSalary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class TppSalary_Reports
 */
if ( ! class_exists( 'TppSalary_Reports' ) ) {
        // TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TppSalary_Reports {

        /**
         * مقداردهی اولیه
         *
         * @return void
         */
        public static function init() {
                add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
                add_action( 'admin_post_tpp_salary_report_excel', array( __CLASS__, 'report_excel' ) );
                add_action( 'admin_post_tpp_salary_report_pdf', array( __CLASS__, 'report_pdf' ) );
                add_action( 'admin_post_tpp_salary_bank_excel', array( __CLASS__, 'bank_excel' ) );
                add_action( 'admin_post_tpp_salary_bank_pdf', array( __CLASS__, 'bank_pdf' ) );
                add_action( 'admin_post_tpp_salary_bulk_zip', array( __CLASS__, 'bulk_zip' ) );
                add_action( 'admin_post_tpp_salary_backup_download', array( __CLASS__, 'backup_download' ) );
        }

        /**
         * منوها
         *
         * @return void
         */
        public static function menu() {
                add_submenu_page( 'tpp-salary', 'گزارش لیست حقوق', 'گزارش لیست حقوق', 'tpp_salary_manage', 'tpp-salary-report', array( __CLASS__, 'render_report' ) );
                add_submenu_page( 'tpp-salary', 'فیش بانکی', 'فیش بانکی', 'tpp_salary_manage', 'tpp-salary-bank-report', array( __CLASS__, 'render_bank' ) );
                add_submenu_page( 'tpp-salary', 'فیش‌های حقوقی', 'فیش‌های حقوقی', 'tpp_salary_manage', 'tpp-salary-payslips', array( __CLASS__, 'render_payslips' ) );
                add_submenu_page( 'tpp-salary', 'پشتیبان‌گیری', 'پشتیبان‌گیری', 'tpp_salary_manage', 'tpp-salary-backup', array( __CLASS__, 'render_backup' ) );
        }

        /**
         * دریافت دوره و مرکز از درخواست
         *
         * @return array
         */
        private static function period() {
                $today = TppSalary_Jalali::today();
                return array(
                        'jyear'     => isset( $_REQUEST['jyear'] ) ? (int) $_REQUEST['jyear'] : $today[0],
                        'jmonth'    => isset( $_REQUEST['jmonth'] ) ? (int) $_REQUEST['jmonth'] : $today[1],
                        'center_id' => isset( $_REQUEST['center_id'] ) ? (int) $_REQUEST['center_id'] : 0,
                );
        }

        /**
         * فیلدهای فرم ثبت حقوق (بدون فیلدهای فقط‌پروفایلی) — نسخه 1.4.1
         *
         * @return array<int,object>
         */
        private static function record_fields() {
                return array_values( array_filter( tpp_salary_get_fields(), function ( $f ) {
                        return tpp_salary_field_in_record( $f ); // نسخه 1.5.0: سپر هاردکد فیلدهای فقط‌پروفایلی.
                } ) );
        }

        /*
         * نسخه 1.7.3 — فیلدهای «فقط محاسباتی»: تعداد ساعات اضافه‌کاری،
         * تعداد روز تعطیل کاری و تعداد روز غیبت فقط ورودی محاسبات هستند
         * و در خروجی‌های چاپی (PDF/اکسل گزارش و فیش) درج نمی‌شوند (درخواست کاربر).
         */
        const CALC_ONLY_KEYS = array( 'overtime_hours', 'holiday_days', 'absence_days' );

        /**
         * فیلدهای قابل نمایش در خروجی چاپی — نسخه 1.7.3 (درخواست کاربر)
         *
         *  ۱) فیلدهای فقط‌محاسباتی (ساعات اضافه‌کاری/روز تعطیل کاری/روز غیبت) حذف می‌شوند.
         *  ۲) فیلدهای عددی که مقدارشان برای «همه» رکوردهای دوره صفر است نمایش داده نمی‌شوند.
         *
         * @param array $records رکوردهای دوره.
         * @return array<int,object>
         */
        private static function printable_fields( $records ) {
                $out = array();
                foreach ( self::record_fields() as $f ) {
                        if ( in_array( (string) $f->field_key, self::CALC_ONLY_KEYS, true ) ) {
                                continue;
                        }
                        if ( 'number' === $f->field_type && ! self::field_any_nonzero( (string) $f->field_key, $records ) ) {
                                continue;
                        }
                        $out[] = $f;
                }
                return $out;
        }

        /**
         * آیا این فیلد حداقل برای یک رکورد مقدار ناصفر دارد؟
         *
         * @param string $key     کلید فیلد.
         * @param array  $records رکوردهای دوره.
         * @return bool
         */
        private static function field_any_nonzero( $key, $records ) {
                foreach ( (array) $records as $r ) {
                        $payload = tpp_salary_record_payload( $r );
                        $val     = isset( $payload[ $key ] ) ? (float) $payload[ $key ] : 0.0;
                        if ( abs( $val ) > 0.0001 ) {
                                return true;
                        }
                }
                return false;
        }

        /**
         * صفحه گزارش لیست حقوق
         *
         * @return void
         */
        public static function render_report() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                list( $jyear, $jmonth, $center_id ) = array_values( self::period() );
                $records = $jyear ? tpp_salary_get_period_records( $jyear, $jmonth, $center_id ) : array();
                $fields  = self::record_fields();
                self::period_form( 'tpp-salary-report', 'گزارش لیست حقوق و جزئیات آن', $records );
                if ( $records ) {
                        $center = $center_id ? tpp_salary_get_center( $center_id ) : null;
                        $center_name = $center ? $center->name : 'همه مراکز';
                        $currency = tpp_salary_get_setting( 'currency', 'ریال' );
                        /*
                         * نسخه 1.6.1: دوره/مرکز/واحد یک‌بار بالای جدول + صفحه‌بندی ستون‌ها
                         * (ستون‌هایی که در یک صفحه جا نمی‌شوند به صفحات بعد می‌روند).
                         */
                        $per   = max( 1, (int) tpp_salary_get_setting( 'per_page_a4', 4 ) );
                        $pages = max( 1, (int) ceil( count( $records ) / $per ) );
                        $paged = min( tpp_salary_current_paged(), $pages );
                        $offset = ( $paged - 1 ) * $per;
                        ?>
                        <p class="description" style="margin:8px 0">
                                دوره: <strong><?php echo esc_html( TppSalary_Jalali::month_name( $jmonth ) . ' ' . TppSalary_Jalali::digits_fa( $jyear ) ); ?></strong>
                                — مرکز: <strong><?php echo esc_html( $center_name ); ?></strong>
                                — واحد: <strong><?php echo esc_html( $currency ); ?></strong>
                                (<?php echo esc_html( TppSalary_Jalali::digits_fa( count( $records ) ) ); ?> کارمند)
                        </p>
                        <?php
                        self::pivot_table( $records, $fields, $jyear, $jmonth, $center_name, $offset );
                        tpp_salary_pagination( count( $records ), $per );
                        $ex = add_query_arg( self::period_query(), admin_url( 'admin-post.php?action=tpp_salary_report_excel' ) );
                        $pd = add_query_arg( self::period_query(), admin_url( 'admin-post.php?action=tpp_salary_report_pdf' ) );
                        ?>
                        <p>
                                <a class="button button-primary" href="<?php echo esc_url( $ex ); ?>">خروجی اکسل</a>
                                <a class="button button-primary" target="_blank" href="<?php echo esc_url( $pd ); ?>">خروجی PDF</a>
                        </p>
                        <?php
                }
                echo '</div>';
        }

        /**
         * فرم انتخاب دوره (مشترک)
         *
         * @param string $page  صفحه.
         * @param string $title عنوان.
         * @param array  $records رکوردها.
         * @param bool   $with_bank انتخاب بانک هم در همین فرم باشد؟ (فیش بانکی — نسخه 1.4.1)
         * @return void
         */
        private static function period_form( $page, $title, $records = null, $with_bank = false ) {
                $p = self::period();
                $bank_id = $with_bank ? (int) ( (isset($_REQUEST['bank_id'] )?$_REQUEST['bank_id'] : 0 )) : 0;
                ?>
                <div class="wrap tpp-wrap" dir="rtl">
                        <h1><?php echo esc_html( $title ); ?></h1>
                        <!-- نسخه 1.4.1: در فیش بانکی همه انتخاب‌ها (سال/ماه/مرکز/بانک) در یک فرم با یک دکمه جستجو هستند. -->
                        <form method="get" style="margin:12px 0">
                                <input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>">
                                <?php $today = TppSalary_Jalali::today(); ?>
                                <select name="jyear">
                                        <?php for ( $y = $today[0] - 6; $y <= $today[0] + 1; $y++ ) : ?>
                                                <option value="<?php echo $y; ?>" <?php selected( $p['jyear'], $y ); ?>><?php echo TppSalary_Jalali::digits_fa( $y ); ?></option>
                                        <?php endfor; ?>
                                </select>
                                <select name="jmonth">
                                        <?php foreach ( TppSalary_Jalali::months() as $m => $label ) : ?>
                                                <option value="<?php echo $m; ?>" <?php selected( $p['jmonth'], $m ); ?>><?php echo esc_html( $label ); ?></option>
                                        <?php endforeach; ?>
                                </select>
                                <select name="center_id">
                                        <option value="">همه مراکز</option>
                                        <?php foreach ( tpp_salary_get_centers() as $c ) : ?>
                                                <option value="<?php echo (int) $c->id; ?>" <?php selected( $p['center_id'], $c->id ); ?>><?php echo esc_html( $c->name ); ?></option>
                                        <?php endforeach; ?>
                                </select>
                                <?php if ( $with_bank ) : ?>
                                <select name="bank_id" required>
                                        <option value="">— نام بانک —</option>
                                        <?php foreach ( tpp_salary_get_banks() as $b ) : ?>
                                                <option value="<?php echo (int) $b->id; ?>" <?php selected( $bank_id, $b->id ); ?>><?php echo esc_html( $b->name ); ?></option>
                                        <?php endforeach; ?>
                                </select>
                                <?php endif; ?>
                                <button class="button button-primary">جستجو</button>
                                <?php if ( null !== $records ) : ?>
                                        <span class="description" style="margin-right:12px"><?php echo TppSalary_Jalali::digits_fa( count( (array) $records ) ); ?> رکورد</span>
                                <?php endif; ?>
                        </form>
                <?php
        }

        /**
         * کوئری دوره
         *
         * @return array
         */
        private static function period_query() {
                $p = self::period();
                return array( 'jyear' => $p['jyear'], 'jmonth' => $p['jmonth'], 'center_id' => $p['center_id'] );
        }

        /**
         * جدول پیوت: ستون‌ها = کارمندان، سطرها = عناوین حقوق
         *
         * نسخه 1.6.1: سطرهای متا (سال/ماه/مرکز/واحد) حذف شدند — این اطلاعات
         * یک‌بار بالای جدول (خط دوره) نمایش داده می‌شود.
         *
         * @param array  $records رکوردها.
         * @param array  $fields  فیلدها.
         * @param int    $jyear   سال.
         * @param int    $jmonth  ماه.
         * @param string $center_name نام مرکز.
         * @param int    $offset  شروع ستون.
         * @return void
         */
        private static function pivot_table( $records, $fields, $jyear, $jmonth, $center_name, $offset = 0 ) {
                $records = array_slice( $records, $offset, (int) tpp_salary_get_setting( 'per_page_a4', 4 ) );
                if ( empty( $records ) ) {
                        return;
                }
                ?>
                <table class="widefat tpp-pivot" dir="rtl">
                        <thead>
                                <tr>
                                        <th>عناوین</th>
                                        <?php foreach ( $records as $r ) :
                                                $u = get_userdata( $r->user_id ); ?>
                                                <th><?php echo esc_html( $u ? $u->display_name : '?' ); ?></th>
                                        <?php endforeach; ?>
                                </tr>
                        </thead>
                        <tbody>
                                <?php foreach ( $fields as $f ) : ?>
                                        <tr>
                                                <td class="tpp-rowlabel"><?php echo esc_html( $f->label ); ?></td>
                                                <?php foreach ( $records as $r ) :
                                                        $payload = tpp_salary_record_payload( $r );
                                                        $val     = isset( $payload[ $f->field_key ] ) ? $payload[ $f->field_key ] : '';
                                                        ?>
                                                        <td <?php echo ( 'number' === $f->field_type ) ? 'dir="ltr"' : ''; ?> class="<?php echo ( 'number' === $f->field_type && (float) $val < 0 ) ? 'tpp-neg' : ''; ?>"><?php echo esc_html( ( 'number' === $f->field_type ) ? tpp_salary_format_number( (float) $val ) : $val ); ?></td>
                                                <?php endforeach; ?>
                                        </tr>
                                <?php endforeach; ?>
                        </tbody>
                </table>
                <?php
        }

        /**
         * خروجی اکسل گزارش لیست حقوق — جداول ستونی
         *
         * @return void
         */
        public static function report_excel() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                list( $jyear, $jmonth, $center_id ) = array_values( self::period() );
                $records = tpp_salary_get_period_records( $jyear, $jmonth, $center_id );
                if ( ! $records ) {
                        wp_die( 'رکوردی برای این دوره یافت نشد' );
                }
                $per = (int) tpp_salary_get_setting( 'per_page_a4', 4 );
                $xlsx = self::build_report_xlsx( $records, $jyear, $jmonth, $center_id, $per );
                tpp_salary_clean_output();
                $xlsx->download( 'salary-list-' . $jyear . '-' . str_pad( (string) $jmonth, 2, '0', STR_PAD_LEFT ) . '.xlsx' );
                exit;
        }

        /**
         * ساخت اکسل گزارش لیست حقوق — قالب ستونی (نسخه 1.6.1، قابل‌تست)
         *
         * قواعد (درخواست کاربر):
         *  — «نام ستون» = نام کارمند و «سطرها» = عناوین حقوق (قالب ستونی).
         *  — سال/ماه/مرکز/واحد یک‌بار در بالای هر شیت (سطر عنوان + سطر دوره) درج
         *    می‌شوند و دیگر به‌صورت سطرهای جداگانه برای هر کارمند تکرار نمی‌شوند؛
         *    سطرهای جدول فقط عناوین حقوق هستند.
         *
         * @param array $records   رکوردهای دوره.
         * @param int   $jyear     سال.
         * @param int   $jmonth    ماه.
         * @param int   $center_id مرکز.
         * @param int   $per       تعداد ستون در هر شیت.
         * @return TppSalary_Xlsx_Writer
         */
        public static function build_report_xlsx( $records, $jyear, $jmonth, $center_id, $per ) {
                $fields = self::printable_fields( $records ); // نسخه 1.7.3: هم‌سان با PDF — بدون فیلدهای فقط‌محاسباتی و ردیف‌های همه‌صفر
                $center = $center_id ? tpp_salary_get_center( (int) $center_id ) : null;
                $center_name = $center ? $center->name : 'همه مراکز';
                $company = tpp_salary_get_setting( 'company_name' );
                $currency = tpp_salary_get_setting( 'currency', 'ریال' );

                $xlsx = new TppSalary_Xlsx_Writer();
                $pages = array_chunk( $records, max( 1, (int) $per ) );
                foreach ( $pages as $pi => $chunk ) {
                        $sheet = 'صفحه ' . ( $pi + 1 );
                        $xlsx->add_sheet( $sheet );
                        // سطر ۱: عنوان — شرکت + دوره + مرکز.
                        $row = 1;
                        $xlsx->merge( $row, 1, $row, 1 + count( $chunk ) );
                        $xlsx->set( $row, 1, ( $company ? $company . ' — ' : '' ) . 'لیست حقوق ' . TppSalary_Jalali::month_name( $jmonth ) . ' ' . TppSalary_Jalali::digits_fa( $jyear ) . ' — ' . $center_name, 'title' );
                        // سطر ۲: واحد پول (متا یک‌بار در بالای صفحه — نسخه 1.6.1).
                        $row++;
                        $xlsx->merge( $row, 1, $row, 1 + count( $chunk ) );
                        $xlsx->set( $row, 1, 'واحد: ' . $currency, 'header' );
                        $row += 2;
                        // هدرها: نام کارمندان (نام ستون = نام کارمند).
                        $xlsx->set( $row, 1, 'عناوین', 'header' );
                        foreach ( $chunk as $ci => $r ) {
                                $u = get_userdata( $r->user_id );
                                $xlsx->set( $row, 2 + $ci, $u ? $u->display_name : '?', 'header' );
                        }
                        $row++;
                        // سطرها: فقط عناوین حقوق (بدون سطرهای متا — نسخه 1.6.1).
                        foreach ( $fields as $f ) {
                                $xlsx->set( $row, 1, $f->label, 'label' );
                                foreach ( $chunk as $ci => $r ) {
                                        $payload = tpp_salary_record_payload( $r );
                                        $val = isset( $payload[ $f->field_key ] ) ? $payload[ $f->field_key ] : '';
                                        if ( 'number' === $f->field_type ) {
                                                /* نسخه 1.4.1: عدد منفی (علامت منفی مقدار) قرمز نمایش داده می‌شود. */
                                                $xlsx->set( $row, 2 + $ci, (float) $val, ( (float) $val < 0 ) ? 'num_neg' : 'num' );
                                        } else {
                                                $xlsx->set( $row, 2 + $ci, (string) $val, 'text' );
                                        }
                                }
                                $row++;
                        }
                        $xlsx->set_width( 'A', 26 );
                        for ( $c = 2; $c <= 1 + count( $chunk ); $c++ ) {
                                $xlsx->set_width( TppSalary_Xlsx_Writer::col_letter( $c ), 22 );
                        }
                }
                return $xlsx;
        }

        /**
         * خروجی PDF گزارش لیست حقوق
         *
         * @return void
         */
        public static function report_pdf() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                list( $jyear, $jmonth, $center_id ) = array_values( self::period() );
                $records = tpp_salary_get_period_records( $jyear, $jmonth, $center_id );
                if ( ! $records ) {
                        wp_die( 'رکوردی برای این دوره یافت نشد' );
                }
                $per  = (int) tpp_salary_get_setting( 'per_page_a4', 4 );
                $logo = tpp_salary_logo_path();
                $pdf  = self::build_report_pdf( $records, $jyear, $jmonth, $center_id, $per, $logo );
                tpp_salary_clean_output(); // حذف خروجی مزاحم — جلوگیری از فایل خراب.
                nocache_headers();
                header( 'Content-Type: application/pdf' );
                header( 'Content-Disposition: inline; filename="salary-list-' . $jyear . '-' . $jmonth . '.pdf"' );
                $pdf->Output( 'I', 'salary-list.pdf' );
                exit;
        }

        /**
         * محاسبه فیت ستون‌ها/سطرهای PDF گزارش لیست حقوق — نسخه 1.7.3
         *
         * قواعد خروجی (درخواست کاربر):
         *  ۱) «همه فونت‌ها» Vazirmatn با اندازه ثابت 10pt و Bold هستند (حداقل مجاز).
         *  ۲) نسخه 1.7.3: ارتفاع سطر تا حد 4.6mm فشرده می‌شود تا «همه ردیف‌ها»
         *     حتی در فیلدهای زیاد در یک صفحه جا شوند؛ اگر تعداد فیلدها حتی با این
         *     حد جا نشد (مورد استثنایی)، سطرهای مازاد به صفحه بعد منتقل و هدر تکرار می‌شود.
         *  ۳) نسخه 1.7.3: ستون‌ها همیشه تا بیشینه گنجایش صفحه پر می‌شوند (per = max_fit)
         *     — هیچ ستونی که در صفحه جا می‌شود به صفحه بعد منتقل نمی‌شود؛
         *     حداقل عرض ستون 14mm و حداکثر 58mm است.
         *  ۴) فیلدهای فقط‌محاسباتی و ردیف‌های همه‌صفر از خروجی حذف می‌شوند (1.7.3).
         *  ۵) سطرهای متا (سال/ماه/مرکز/واحد) در سربرگ می‌آیند نه به‌صورت سطر.
         *
         * @param array $records رکوردهای دوره.
         * @param int   $per     تعداد ستون اعلام‌شده در تنظیمات (فقط برای وب/اکسل — در PDF بیشینه است).
         * @return array{w1:float,col_w:float,need_col:float,max_fit:int,per:int,rh:float,fs_body:float,fs_head:float,total_rows:int,usable_h:float,fields:array}
         */
        public static function report_pdf_fit( $records, $per ) {
                $fields = self::printable_fields( $records ); // نسخه 1.7.3: فیلدهای چاپی
                $pdf    = new TppSalary_PDF( 'L', 'mm', 'A4' );
                $pw     = $pdf->GetPageWidth() - 20;  // عرض مفید (حاشیه ۱۰ میلی‌متری دو طرف)
                $ph     = $pdf->GetPageHeight();      // ۲۱۰mm در A4 افقی

                /*
                 * — عمودی (نسخه 1.7.3): فونت همه‌جا Bold 10pt ثابت است؛ ارتفاع سطر
                 * تا حد 4.6mm فشرده می‌شود (حداقل برای فونت 10pt Bold) تا همه ردیف‌ها
                 * در یک صفحه جا شوند؛ فقط در موارد استثنایی سطرهای مازاد در
                 * build_report_pdf به صفحه بعد می‌روند (هدر تکرار می‌شود).
                 * سربرگ تا ۲۳mm + حاشیه پایین ۱۲mm؛ سطرها = سطر نام‌ها + سطرهای فیلدها.
                 */
                $total_rows = 1 + count( $fields );
                $usable_h   = max( 60.0, $ph - 23.0 - 12.0 );
                $rh         = 7.6;
                if ( $total_rows * $rh > $usable_h ) {
                        $rh = max( 4.6, $usable_h / $total_rows ); // نسخه 1.7.3: کف 4.6mm (قبلاً 6mm)
                }
                /* نسخه 1.7.1: اندازه فونت ثابت 10pt (حداقل مجاز کاربر) — همه Bold */
                $fs_body = 10.0;
                $fs_head = 10.0;

                // — ستون برچسب‌ها: بلندترین برچسب فیلد (+ عنوان ستون عناوین) با فونت نهایی.
                $pdf->SetFont( 'vazir', 'B', $fs_head );
                $label_w = $pdf->GetStringWidth( $pdf->fa( 'عناوین' ) );
                foreach ( $fields as $f ) {
                        $label_w = max( $label_w, $pdf->GetStringWidth( $pdf->fa( $f->label ) ) );
                }
                $w1 = max( 24.0, min( 48.0, $label_w + 5.0 ) ); // + لایی امن

                // — ستون کارمند: پهن‌ترین محتوا روی همه رکوردها (نام + همه اعداد) با فونت نهایی.
                $max_txt = 0;
                $pdf->SetFont( 'vazir', 'B', $fs_head );
                foreach ( (array) $records as $r ) {
                        $u = get_userdata( $r->user_id );
                        $max_txt = max( $max_txt, $pdf->GetStringWidth( $pdf->fa( $u ? $u->display_name : '?' ) ) );
                }
                /* نسخه 1.7.1: مقادیر هم Bold هستند — اندازه‌گیری با همان فونت نهایی. */
                $pdf->SetFont( 'vazir', 'B', $fs_body );
                foreach ( (array) $records as $r ) {
                        $payload = tpp_salary_record_payload( $r );
                        foreach ( $fields as $f ) {
                                $val = isset( $payload[ $f->field_key ] ) ? $payload[ $f->field_key ] : '';
                                $txt = ( 'number' === $f->field_type ) ? tpp_salary_format_number( (float) $val, false ) : (string) $val;
                                $max_txt = max( $max_txt, $pdf->GetStringWidth( $pdf->fa( (string) $txt ) ) );
                        }
                }
                $need_col = $max_txt + 3.4;          // متن + لایی سلول
                $col_w    = max( 14.0, min( $need_col, 58.0 ) ); // نسخه 1.7.3: نه باریک‌تر از ۱۴mm (قبلاً ۲۰)، نه پهن‌تر از ۵۸mm
                $max_fit  = (int) max( 1, floor( ( $pw - $w1 ) / $col_w ) );
                /* نسخه 1.7.3: ستون‌ها همیشه تا گنجایش کامل صفحه پر می‌شوند —
                 * ستونی که در صفحه جا می‌شود هرگز به صفحه بعد منتقل نمی‌شود. */
                $per = $max_fit;
                if ( $per < 1 ) {
                        $per = 1;
                }

                return compact( 'w1', 'col_w', 'need_col', 'max_fit', 'per', 'rh', 'fs_body', 'fs_head', 'total_rows', 'usable_h', 'fields' );
        }

        /**
         * ساخت PDF گزارش لیست حقوق — همیشه A4 افقی (نسخه 1.7.3، قابل‌تست)
         *
         * قواعد نسخه 1.7.3 (درخواست کاربر):
         *  — همه فونت‌ها Vazirmatn با اندازه حداقل 10pt و Bold هستند (چاپ خوانا).
         *  — همه ردیف‌ها (فیلدها) تا حد امکان با فشرده‌سازی ارتفاع سطر (کف 4.6mm)
         *    در یک صفحه جا می‌شوند؛ فقط در موارد استثنایی سطرهای مازاد به صفحه
         *    بعد می‌روند و هدر ستونی تکرار می‌شود.
         *  — ستون‌ها همیشه تا گنجایش کامل صفحه پر می‌شوند (per = max_fit) —
         *    ستونی که جا می‌شود به صفحه بعد منتقل نمی‌شود؛ هدر در صفحات ادامه تکرار می‌شود.
         *  — فیلدهای فقط‌محاسباتی و ردیف‌های همه‌صفر نمایش داده نمی‌شوند (1.7.3).
         *  — سال/ماه/مرکز/واحد یک‌بار در سربرگ بالای صفحه درج می‌شوند (نه به‌صورت سطر).
         *  — همه اعداد با ارقام انگلیسی.
         *
         * @param array    $records   رکوردهای دوره.
         * @param int      $jyear     سال.
         * @param int      $jmonth    ماه.
         * @param int|null $center_id مرکز.
         * @param int      $per       تعداد ستون از تنظیمات (در PDF بیشینه اعمال می‌شود).
         * @param string   $logo      مسیر لوگو (اختیاری).
         * @return TppSalary_PDF
         */
        public static function build_report_pdf( $records, $jyear, $jmonth, $center_id, $per, $logo = '' ) {
                $center  = $center_id ? tpp_salary_get_center( (int) $center_id ) : null;
                $center_name = $center ? $center->name : 'همه مراکز';
                $company = tpp_salary_get_setting( 'company_name' );
                $currency = tpp_salary_get_setting( 'currency', 'ریال' );

                $orientation = 'L'; // همیشه افقی
                $pdf = new TppSalary_PDF( $orientation, 'mm', 'A4' );
                $pdf->SetTitle( 'Salary List ' . $jyear . '-' . $jmonth );

                $fit = self::report_pdf_fit( $records, $per );
                $fields = $fit['fields']; // نسخه 1.7.3: همان فیلدهای چاپیِ محاسبه فیت — سطرها همیشه با فیت هم‌خوان
                $w1  = $fit['w1'];
                $rh  = $fit['rh'];
                $per = $fit['per'];
                $fs_body = $fit['fs_body'];
                $fs_head = $fit['fs_head'];
                $pw  = $pdf->GetPageWidth() - 20;
                $ph  = $pdf->GetPageHeight();
                $bottom_limit = $ph - 12; // حاشیه پایین (هماهنگ با report_pdf_fit)

                /* سربرگ فشرده: لوگو + شرکت + دوره/مرکز/واحد (متا یک‌بار — نسخه 1.6.1) */
                $subtitle = 'دوره: ' . TppSalary_Jalali::month_name( $jmonth ) . ' ' . TppSalary_Jalali::digits_fa( $jyear )
                        . ' — مرکز: ' . $center_name
                        . ' — واحد: ' . $currency;

                $pages = array_chunk( $records, $per );
                foreach ( $pages as $chunk ) {
                        $pdf->AddPage();
                        $y = 8;
                        if ( $logo ) {
                                $pdf->Image( $logo, 10, $y, 26, 12 );
                        }
                        $pdf->SetFont( 'vazir', 'B', 13 );
                        $pdf->SetXY( 10, $y );
                        $pdf->faCell( $pw, 7, $company ? $company : 'فهرست حقوق و دستمزد', 0, 1, 'C' );
                        /* نسخه 1.7.1: سربرگ هم Bold 10pt (حداقل مجاز) */
                        $pdf->SetFont( 'vazir', 'B', 10 );
                        $pdf->SetXY( 10, $y + 7 );
                        $pdf->faCell( $pw, 6, $subtitle, 0, 1, 'C' );
                        $pdf->SetY( 23 ); // شروع جدول — هماهنگ با report_pdf_fit

                        $wc = ( $pw - $w1 ) / count( $chunk ); // آخرین صفحه با سطر کمتر → ستون پهن‌تر (امن)

                        /*
                         * هدر ستونی (نام کارمندان) — در صفحات ستون‌های ادامه هم تکرار می‌شود.
                         */
                        $render_header = function () use ( $pdf, $chunk, $w1, $wc, $rh, $fs_head, $fs_body ) {
                                $pdf->SetFont( 'vazir', 'B', $fs_head );
                                $pdf->SetFillColor( 217, 226, 243 );
                                $pdf->faCell( $w1, $rh, 'عناوین', 1, 0, 'C', true );
                                foreach ( $chunk as $r ) {
                                        $u = get_userdata( $r->user_id );
                                        $pdf->faCell( $wc, $rh, $u ? $u->display_name : '?', 1, 0, 'C', true );
                                }
                                $pdf->Ln( $rh );
                                $pdf->SetFont( 'vazir', 'B', $fs_body ); // نسخه 1.7.1: مقادیر هم Bold
                        };
                        $render_header();

                        /*
                         * سطرهای حقوق — تا جایی که با حداقل ارتفاع سطر (6mm) ممکن باشد
                         * در همین صفحه جا می‌شوند؛ سطرهای مازاد به صفحه بعد می‌روند
                         * و هدر ستونی تکرار می‌شود (نسخه 1.7.1 — حداقل فونت 10pt Bold).
                         */
                        foreach ( $fields as $f ) {
                                if ( $pdf->GetY() + $rh > $bottom_limit ) {
                                        $pdf->AddPage();
                                        $render_header();
                                }
                                $pdf->SetFillColor( 246, 247, 250 );
                                $pdf->SetFont( 'vazir', 'B', $fs_body );
                                $pdf->faCell( $w1, $rh, $f->label, 1, 0, 'C', true );
                                $pdf->SetFont( 'vazir', 'B', $fs_body ); // نسخه 1.7.1: مقادیر هم Bold
                                foreach ( $chunk as $r ) {
                                        $payload = tpp_salary_record_payload( $r );
                                        $val = isset( $payload[ $f->field_key ] ) ? $payload[ $f->field_key ] : '';
                                        if ( 'number' === $f->field_type ) {
                                                $num = (float) $val;
                                                $pdf->faCell( $wc, $rh, tpp_salary_format_number( $num, false ), 1, 0, 'C' );
                                        } else {
                                                $pdf->faCell( $wc, $rh, (string) $val, 1, 0, 'C' );
                                        }
                                }
                                $pdf->Ln( $rh );
                        }
                }
                return $pdf;
        }

        /**
         * صفحه فیش بانکی
         *
         * @return void
         */
        public static function render_bank() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                list( $jyear, $jmonth, $center_id ) = array_values( self::period() );
                $bank_id = (int) ( (isset($_REQUEST['bank_id'] )?$_REQUEST['bank_id'] : 0 ));
                /* نسخه 1.4.1: یک فرم واحد — سال/ماه/مرکز/بانک با یک دکمه جستجو. */
                self::period_form( 'tpp-salary-bank-report', 'فیش بانکی', null, true );
                ?>
                <?php
                if ( $jyear && $bank_id ) {
                        $bank = tpp_salary_get_bank( $bank_id );
                        if ( ! $bank ) {
                                echo '<p>بانک یافت نشد.</p></div>';
                                return;
                        }
                        $records = tpp_salary_get_period_records( $jyear, $jmonth, $center_id );
                        $rows    = array();
                        foreach ( $records as $r ) {
                                $profile = tpp_salary_get_profile( $r->user_id );
                                $acc     = isset( $profile['bank_accounts'][ $bank_id ] ) ? $profile['bank_accounts'][ $bank_id ] : null;
                                if ( ! $acc || empty( $acc['account'] ) ) {
                                        continue;
                                }
                                $u    = get_userdata( $r->user_id );
                                $rows[] = array(
                                        'name'    => $u ? $u->display_name : '?',
                                        'account' => $acc['account'],
                                        'sheba'   => (isset($acc['sheba'] )?$acc['sheba'] : ''),
                                        'net'     => (float) $r->net,
                                );
                        }
                        $total = array_sum( wp_list_pluck( $rows, 'net' ) );
                        ?>
                        <p class="description">کارکنانی که در <?php echo esc_html( TppSalary_Jalali::period_label( $jyear, $jmonth ) ); ?> برایشان حقوق ثبت شده و در بانک «<?php echo esc_html( $bank->name ); ?>» شماره حساب دارند: <?php echo TppSalary_Jalali::digits_fa( count( $rows ) ); ?> نفر — جمع خالص: <strong><?php echo esc_html( tpp_salary_format_number( $total ) ); ?></strong></p>
                        <table class="widefat striped" dir="rtl">
                                <thead><tr><th>#</th><th>نام کارمند</th><th>شماره حساب</th><th>شماره شبا</th><th>حقوق خالص دریافتی</th></tr></thead>
                                <tbody>
                                <?php foreach ( $rows as $i => $row ) : ?>
                                        <tr>
                                                <td><?php echo TppSalary_Jalali::digits_fa( $i + 1 ); ?></td>
                                                <td><?php echo esc_html( $row['name'] ); ?></td>
                                                <td dir="ltr"><?php echo esc_html( $row['account'] ); ?></td>
                                                <td dir="ltr"><?php echo esc_html( $row['sheba'] ); ?></td>
                                                <td dir="ltr"><strong class="<?php echo (float) $row['net'] < 0 ? 'tpp-neg' : ''; ?>"><?php echo esc_html( tpp_salary_format_number( $row['net'] ) ); ?></strong></td>
                                        </tr>
                                <?php endforeach; ?>
                                <?php if ( empty( $rows ) ) : ?>
                                        <tr><td colspan="5">رکوردی یافت نشد.</td></tr>
                                <?php endif; ?>
                                </tbody>
                        </table>
                        <?php if ( $rows ) :
                                $ex = add_query_arg( array_merge( self::period_query(), array( 'bank_id' => $bank_id ) ), admin_url( 'admin-post.php?action=tpp_salary_bank_excel' ) );
                                $pd = add_query_arg( array_merge( self::period_query(), array( 'bank_id' => $bank_id ) ), admin_url( 'admin-post.php?action=tpp_salary_bank_pdf' ) );
                                ?>
                                <p>
                                        <a class="button button-primary" href="<?php echo esc_url( $ex ); ?>">خروجی اکسل</a>
                                        <a class="button button-primary" target="_blank" href="<?php echo esc_url( $pd ); ?>">خروجی PDF</a>
                                </p>
                        <?php endif; ?>
                        <?php
                }
                echo '</div>';
        }

        /**
         * سطرهای فیش بانکی (مشترک)
         *
         * @param int $jyear    سال.
         * @param int $jmonth   ماه.
         * @param int $center_id مرکز.
         * @param int $bank_id  بانک.
         * @return array
         */
        private static function bank_rows( $jyear, $jmonth, $center_id, $bank_id ) {
                $records = tpp_salary_get_period_records( $jyear, $jmonth, $center_id );
                $rows    = array();
                foreach ( $records as $r ) {
                        $profile = tpp_salary_get_profile( $r->user_id );
                        $acc     = isset( $profile['bank_accounts'][ $bank_id ] ) ? $profile['bank_accounts'][ $bank_id ] : null;
                        if ( ! $acc || empty( $acc['account'] ) ) {
                                continue;
                        }
                        $u = get_userdata( $r->user_id );
                        $rows[] = array(
                                'name'    => $u ? $u->display_name : '?',
                                'account' => $acc['account'],
                                'sheba'   => isset( $acc['sheba'] ) ? $acc['sheba'] : '',
                                'net'     => (float) $r->net,
                        );
                }
                return $rows;
        }

        /**
         * خروجی اکسل فیش بانکی
         *
         * @return void
         */
        public static function bank_excel() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                list( $jyear, $jmonth, $center_id ) = array_values( self::period() );
                $bank_id = (int) ( (isset($_REQUEST['bank_id'] )?$_REQUEST['bank_id'] : 0 ));
                $bank    = tpp_salary_get_bank( $bank_id );
                if ( ! $bank ) {
                        wp_die( 'بانک نامعتبر' );
                }
                $rows = self::bank_rows( $jyear, $jmonth, $center_id, $bank_id );
                $company = tpp_salary_get_setting( 'company_name' );
                $center = $center_id ? tpp_salary_get_center( $center_id ) : null;
                $center_name = $center ? $center->name : 'همه مراکز';

                $xlsx = new TppSalary_Xlsx_Writer();
                $xlsx->add_sheet( 'فیش بانکی' );
                $xlsx->merge( 1, 1, 1, 5 );
                $xlsx->set( 1, 1, ( $company ? $company . ' — ' : '' ) . 'فیش بانکی ' . $bank->name . ' — ' . TppSalary_Jalali::month_name( $jmonth ) . ' ' . TppSalary_Jalali::digits_fa( $jyear ) . ' — ' . $center_name, 'title' );
                $headers = array( '#', 'نام کارمند', 'شماره حساب', 'شماره شبا', 'حقوق خالص دریافتی' );
                foreach ( $headers as $i => $h ) {
                        $xlsx->set( 3, 1 + $i, $h, 'header' );
                }
                $row = 4;
                foreach ( $rows as $i => $r ) {
                        $xlsx->set( $row, 1, $i + 1, 'text' );
                        $xlsx->set( $row, 2, $r['name'], 'text' );
                        $xlsx->set( $row, 3, $r['account'], 'text' );
                        $xlsx->set( $row, 4, $r['sheba'], 'text' );
                        $xlsx->set( $row, 5, $r['net'], 'num' );
                        $row++;
                }
                $row++;
                $xlsx->merge( $row, 1, $row, 4 );
                $xlsx->set( $row, 1, 'جمع کل', 'total' );
                $xlsx->set( $row, 5, array_sum( wp_list_pluck( $rows, 'net' ) ), 'total' );
                $xlsx->set_width( 'A', 6 );
                $xlsx->set_width( 'B', 32 );
                $xlsx->set_width( 'C', 24 );
                $xlsx->set_width( 'D', 28 );
                $xlsx->set_width( 'E', 22 );
                $xlsx->download( 'bank-slip-' . $bank->name . '-' . $jyear . '-' . str_pad( (string) $jmonth, 2, '0', STR_PAD_LEFT ) . '.xlsx' );
        }

        /**
         * خروجی PDF فیش بانکی
         *
         * @return void
         */
        public static function bank_pdf() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                list( $jyear, $jmonth, $center_id ) = array_values( self::period() );
                $bank_id = (int) ( (isset($_REQUEST['bank_id'] )?$_REQUEST['bank_id'] : 0 ));
                $bank    = tpp_salary_get_bank( $bank_id );
                if ( ! $bank ) {
                        wp_die( 'بانک نامعتبر' );
                }
                $rows = self::bank_rows( $jyear, $jmonth, $center_id, $bank_id );
                $company = tpp_salary_get_setting( 'company_name' );
                $center = $center_id ? tpp_salary_get_center( $center_id ) : null;
                $center_name = $center ? $center->name : 'همه مراکز';
                $logo = tpp_salary_logo_path();
                $currency = tpp_salary_get_setting( 'currency', 'ریال' );

                $pdf = self::build_bank_pdf( $rows, $bank, $jyear, $jmonth, $center_name, $company, $currency, $logo );

                tpp_salary_clean_output(); // حذف خروجی مزاحم — جلوگیری از فایل خراب.
                nocache_headers();
                header( 'Content-Type: application/pdf' );
                header( 'Content-Disposition: inline; filename="bank-slip.pdf"' );
                $pdf->Output( 'I', 'bank-slip.pdf' );
                exit;
        }

        /**
         * ساخت PDF فیش بانکی — نسخه 1.7.1 (قابل‌تست)
         *
         * قواعد نسخه 1.7.1: همه فونت‌ها Vazirmatn با اندازه حداقل 10pt و Bold؛
         * همه اعداد با ارقام انگلیسی.
         *
         * @param array    $rows        سطرهای فیش.
         * @param object   $bank        بانک.
         * @param int      $jyear       سال.
         * @param int      $jmonth      ماه.
         * @param string   $center_name نام مرکز.
         * @param string   $company     نام شرکت.
         * @param string   $currency    واحد پول.
         * @param string   $logo        مسیر لوگو (اختیاری).
         * @return TppSalary_PDF
         */
        public static function build_bank_pdf( $rows, $bank, $jyear, $jmonth, $center_name, $company, $currency, $logo = '' ) {
                $pdf = new TppSalary_PDF( 'P', 'mm', 'A4' );
                $pdf->SetTitle( 'Bank Slip' );
                $pdf->AddPage(); // نسخه 1.7.1: صفحه باید قبل از ترسیم ساخته شود (باگ موجود در bank_pdf قدیمی)
                $pw = $pdf->GetPageWidth() - 20;
                $y  = 10;
                if ( $logo ) {
                        $pdf->Image( $logo, 10, $y, 24 );
                }
                $pdf->SetFont( 'vazir', 'B', 13 );
                $pdf->SetXY( 10, $y );
                $pdf->faCell( $pw, 8, $company ? $company : 'فیش بانکی', 0, 1, 'C' );
                /* نسخه 1.7.1: سربرگ Bold 10pt (حداقل مجاز) */
                $pdf->SetFont( 'vazir', 'B', 10 );
                $pdf->SetXY( 10, $y + 8 );
                $pdf->faCell( $pw, 6.5, 'فیش بانکی ' . $bank->name . ' — ' . TppSalary_Jalali::month_name( $jmonth ) . ' ' . TppSalary_Jalali::digits_fa( $jyear ) . ' — مرکز ' . $center_name, 0, 1, 'C' );
                $pdf->SetY( $y + 17 );

                $widths = array( 10, 62, 40, 48, 30 );
                $total  = array_sum( $widths );
                if ( $total > $pw ) {
                        $scale  = $pw / $total;
                        $widths = array_map(
                                function ( $w ) use ( $scale ) {
                                        return $w * $scale;
                                },
                                $widths
                        );
                }
                $headers = array( '#', 'نام کارمند', 'شماره حساب', 'شماره شبا', 'حقوق خالص' );
                $rh = 7.4;
                $pdf->SetFont( 'vazir', 'B', 10 ); // نسخه 1.7.1: Bold 10
                $pdf->SetFillColor( 217, 226, 243 );
                foreach ( $headers as $i => $h ) {
                        $pdf->faCell( $widths[ $i ], $rh, $h, 1, 0, 'C', true );
                }
                $pdf->Ln( $rh );
                $pdf->SetFont( 'vazir', 'B', 10 ); // نسخه 1.7.1: مقادیر هم Bold
                foreach ( $rows as $i => $r ) {
                        if ( $pdf->GetY() + $rh > $pdf->GetPageHeight() - 14 ) {
                                $pdf->AddPage();
                                $pdf->SetFont( 'vazir', 'B', 10 );
                                foreach ( $headers as $j => $h ) {
                                        $pdf->faCell( $widths[ $j ], $rh, $h, 1, 0, 'C', true );
                                }
                                $pdf->Ln( $rh );
                                $pdf->SetFont( 'vazir', 'B', 10 );
                        }
                        $pdf->faCell( $widths[0], $rh, tpp_salary_format_number( $i + 1 ), 1, 0, 'C' );
                        $pdf->faCell( $widths[1], $rh, $r['name'], 1, 0, 'R' );
                        $pdf->faCell( $widths[2], $rh, $r['account'], 1, 0, 'C' );
                        $pdf->faCell( $widths[3], $rh, $r['sheba'], 1, 0, 'C' );
                        $pdf->faCell( $widths[4], $rh, tpp_salary_format_number( $r['net'], false ), 1, 0, 'C' );
                        $pdf->Ln( $rh );
                }
                // جمع کل.
                $pdf->SetFont( 'vazir', 'B', 10 );
                $pdf->SetFillColor( 242, 242, 242 );
                $pdf->faCell( array_sum( array_slice( $widths, 0, 4 ) ), $rh, 'جمع کل (' . $currency . ')', 1, 0, 'C', true );
                $pdf->faCell( $widths[4], $rh, tpp_salary_format_number( array_sum( wp_list_pluck( $rows, 'net' ) ), false ), 1, 0, 'C', true );
                $pdf->Ln( $rh );

                return $pdf;
        }

        /**
         * صفحه فیش‌های حقوقی (تکی و عمده)
         *
         * @return void
         */
        public static function render_payslips() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                list( $jyear, $jmonth, $center_id ) = array_values( self::period() );
                $records = $jyear ? tpp_salary_get_period_records( $jyear, $jmonth, $center_id ) : array();
                self::period_form( 'tpp-salary-payslips', 'فیش‌های حقوقی — تکی و عمده (ZIP)' );
                $zip = add_query_arg( self::period_query(), admin_url( 'admin-post.php?action=tpp_salary_bulk_zip' ) );
                ?>
                <p class="description">با مشخص کردن سال، ماه و مرکز می‌توانید فیش حقوقی همه کارکنان ثبت‌شده را به صورت فایل‌های PDF مجزا در قالب یک فایل ZIP (با درج لوگوی شرکت) دریافت کنید.</p>
                <p>
                        <a class="button button-primary button-hero" href="<?php echo esc_url( $zip ); ?>">دریافت ZIP فیش‌های همه کارکنان (<?php echo TppSalary_Jalali::digits_fa( count( (array) $records ) ); ?> فیش)</a>
                </p>
                <?php if ( $records ) :
                        /* نسخه 1.6.1: صفحه‌بندی فهرست فیش‌ها (نتایج جستجوی دوره). */
                        $total = count( $records );
                        $per   = tpp_salary_list_per_page();
                        $pages = max( 1, (int) ceil( $total / $per ) );
                        $paged = min( tpp_salary_current_paged(), $pages );
                        $page_records = array_slice( $records, ( $paged - 1 ) * $per, $per );
                        ?>
                        <table class="widefat striped" dir="rtl">
                                <thead><tr><th>#</th><th>کارمند</th><th>مرکز</th><th>خالص پرداختی</th><th>فیش PDF</th></tr></thead>
                                <tbody>
                                <?php foreach ( $page_records as $i => $r ) :
                                        $u = get_userdata( $r->user_id );
                                        $c = tpp_salary_get_center( (int) $r->center_id );
                                        $pdf = add_query_arg( array( 'action' => 'tpp_salary_payslip_pdf', 'record_id' => $r->id, '_wpnonce' => wp_create_nonce( 'tpp_salary_payslip_pdf' ) ), admin_url( 'admin-ajax.php' ) );
                                        ?>
                                        <tr>
                                                <td><?php echo TppSalary_Jalali::digits_fa( $i + 1 ); ?></td>
                                                <td><?php echo esc_html( $u ? $u->display_name : '?' ); ?></td>
                                                <td><?php echo esc_html( $c ? $c->name : '?' ); ?></td>
                                                <td dir="ltr"><?php echo esc_html( tpp_salary_format_number( (float) $r->net ) ); ?></td>
                                                <td><a class="button button-small" target="_blank" href="<?php echo esc_url( $pdf ); ?>">مشاهده / دانلود</a></td>
                                        </tr>
                                <?php endforeach; ?>
                                </tbody>
                        </table>
                        <?php tpp_salary_pagination( $total, $per ); // نسخه 1.6.1: صفحه‌بندی نتایج جستجو ?>
                <?php endif; ?>
                <?php
                echo '</div>';
        }

        /**
         * خروجی ZIP فیش‌های حقوقی عمده
         *
         * @return void
         */
        public static function bulk_zip() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                list( $jyear, $jmonth, $center_id ) = array_values( self::period() );
                $records = tpp_salary_get_period_records( $jyear, $jmonth, $center_id );
                if ( ! $records ) {
                        wp_die( 'رکوردی برای این دوره یافت نشد' );
                }
                if ( ! class_exists( 'ZipArchive' ) ) {
                        wp_die( 'افزونه ZipArchive روی سرور فعال نیست' );
                }
                $tmp = tempnam( sys_get_temp_dir(), 'tppzip' );
                $zip = new ZipArchive();
                if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
                        wp_die( 'ایجاد فایل ZIP ناموفق بود' );
                }
                foreach ( $records as $r ) {
                        $u = get_userdata( $r->user_id );
                        $pdf_data = self::build_payslip_pdf( $r );
                        if ( is_wp_error( $pdf_data ) ) {
                                continue;
                        }
                        $safe = sanitize_file_name( ( $u ? $u->display_name : 'user-' . $r->user_id ) );
                        $zip->addFromString( $safe . '.pdf', $pdf_data );
                }
                $zip->close();
                $name = 'payslips-' . $jyear . '-' . str_pad( (string) $jmonth, 2, '0', STR_PAD_LEFT ) . ( $center_id ? '-center' . $center_id : '' ) . '.zip';
                tpp_salary_clean_output(); // حذف خروجی مزاحم — جلوگیری از فایل خراب.
                nocache_headers();
                header( 'Content-Type: application/zip' );
                header( 'Content-Disposition: attachment; filename="' . $name . '"' );
                header( 'Content-Length: ' . filesize( $tmp ) );
                readfile( $tmp ); // phpcs:ignore
                unlink( $tmp );
                exit;
        }

        /**
         * ردیف‌های قابل نمایش فیش حقوقی یک رکورد — نسخه 1.7.3 (درخواست کاربر)
         *
         *  ۱) فیلدهای فقط‌محاسباتی (ساعات اضافه‌کاری/روز تعطیل کاری/روز غیبت) حذف می‌شوند.
         *  ۲) فیلدهای عددی که مقدارشان برای همین رکورد صفر است نمایش داده نمی‌شوند.
         *
         * @param object $record رکورد حقوق.
         * @return array<int,object>
         */
        public static function payslip_rows( $record ) {
                $payload = tpp_salary_record_payload( $record );
                $out = array();
                foreach ( self::record_fields() as $f ) {
                        if ( ! $f->show_in_payslip ) {
                                continue;
                        }
                        if ( in_array( (string) $f->field_key, self::CALC_ONLY_KEYS, true ) ) {
                                continue;
                        }
                        if ( 'number' === $f->field_type ) {
                                $val = isset( $payload[ $f->field_key ] ) ? (float) $payload[ $f->field_key ] : 0.0;
                                if ( abs( $val ) <= 0.0001 ) {
                                        continue; // مقدار صفر چاپ نمی‌شود.
                                }
                        }
                        $out[] = $f;
                }
                return $out;
        }

        /**
         * ساخت PDF فیش حقوقی یک رکورد
         *
         * نسخه 1.5.0 — ابعاد فیش A5 (۱۴۸×۲۱۰ میلی‌متر) است و چیدمان به‌صورت
         * خودکار «فیت» می‌شود؛ برای فیش‌های بسیار بلند، صفحه دوم خودکار ساخته می‌شود.
         *
         * نسخه 1.7.1 — همه فونت‌ها Vazirmatn با اندازه حداقل 10pt و Bold هستند
         * (حداقل ارتفاع سطر 5.7mm)؛ سطرهای مازاد به صفحه دوم می‌روند و هدر جدول
         * تکرار می‌شود؛ همه اعداد با ارقام انگلیسی.
         *
         * نسخه 1.7.3 — فیلدهای فقط‌محاسباتی و مقادیر صفر چاپ نمی‌شوند؛
         * ارتفاع سطر بر اساس تعداد ردیف‌های واقعی چاپی محاسبه می‌شود.
         *
         * @param object $record رکورد.
         * @return string|WP_Error بایت‌های PDF.
         */
        public static function build_payslip_pdf( $record ) {
                $user = get_userdata( $record->user_id );
                $center = tpp_salary_get_center( (int) $record->center_id );
                $settings = tpp_salary_get_settings();
                $payload = tpp_salary_record_payload( $record );
                $profile = tpp_salary_get_profile( $record->user_id );
                $logo    = tpp_salary_logo_path();
                /* نسخه 1.7.3: فقط ردیف‌های چاپی (بدون فیلدهای فقط‌محاسباتی و مقادیر صفر) */
                $fields  = self::payslip_rows( $record );

                try {
                        /* نسخه 1.5.0: فیش حقوقی در ابعاد A5 چاپ می‌شود. */
                        $pdf = new TppSalary_PDF( 'P', 'mm', 'A5' );
                        $pdf->SetTitle( 'Payslip' );
                        $pdf->SetMargins( 8, 8, 8 );
                        $pdf->SetAutoPageBreak( true, 8 );
                        $pdf->AddPage();
                        $pw   = $pdf->GetPageWidth() - 16;
                        $y    = 8;

                        // ارتفاع سطر متناسب با تعداد ردیف‌های واقعی چاپی — تا کل فیش در A5 جا شود.
                        $rows     = count( $fields );
                        $page_h   = $pdf->GetPageHeight(); // 210 برای A5 عمودی.
                        $reserved = 24 + 18 + 15; // سربرگ + امضاها + تاریخ چاپ.
                        $avail    = $page_h - 16 - $reserved;
                        /* نسخه 1.7.1: حداقل ارتفاع سطر برای فونت Bold 10pt = 5.7mm */
                        $rh       = ( $rows > 0 ) ? floor( ( $avail / ( $rows + 4 ) ) * 10 ) / 10 : 5.7;
                        $rh       = max( 5.7, min( 6.4, (float) $rh ) );
                        $lh       = $rh + 0.6; // سطر سربرگ جدول.

                        // سربرگ.
                        if ( $logo ) {
                                $pdf->Image( $logo, 8, $y, 16 );
                        }
                        $pdf->SetFont( 'vazir', 'B', 12.5 );
                        $pdf->SetXY( 8, $y );
                        $pdf->faCell( $pw, 7.5, $settings['company_name'] ? $settings['company_name'] : 'فیش حقوقی و دستمزد', 0, 1, 'C' );
                        /* نسخه 1.7.1: سربرگ Bold 10pt (حداقل مجاز) */
                        $pdf->SetFont( 'vazir', 'B', 10 );
                        $pdf->SetXY( 8, $y + 7.5 );
                        $pdf->faCell( $pw, 6, 'فیش حقوقی ' . TppSalary_Jalali::month_name( (int) $record->jmonth ) . ' ' . TppSalary_Jalali::digits_fa( (int) $record->jyear ) . ' — مرکز ' . ( $center ? $center->name : '' ), 0, 1, 'C' );
                        $pdf->Line( 8, $y + 15, $pdf->GetPageWidth() - 8, $y + 15 );
                        $pdf->SetY( $y + 17 );

                        // اطلاعات کارمند — چهار ستون با عرض‌های نسبی (نسخه 1.7.1: همه Bold 10pt و برچسب پهن‌تر).
                        $lw = 32; // عرض برچسب.
                        $vw = ( $pw - 2 * $lw ) / 2; // عرض مقدار.
                        $national = get_user_meta( $record->user_id, 'tpp_salary_national_id', true );
                        $pdf->SetFont( 'vazir', 'B', 10 );
                        $pdf->SetFillColor( 240, 244, 251 );
                        $pdf->faCell( $lw, $rh, 'نام و نام خانوادگی', 1, 0, 'C', true );
                        $pdf->faCell( $vw, $rh, $user ? $user->display_name : '?', 1, 0, 'C' );
                        $pdf->faCell( $lw, $rh, 'کد ملی', 1, 0, 'C', true );
                        $pdf->faCell( $vw, $rh, $national, 1, 0, 'C' );
                        $pdf->Ln( $rh );
                        $job = isset( $profile['job_title'] ) ? $profile['job_title'] : '';
                        $pdf->faCell( $lw, $rh, 'عنوان شغلی', 1, 0, 'C', true );
                        $pdf->faCell( $vw, $rh, $job, 1, 0, 'C' );
                        $pdf->faCell( $lw, $rh, 'شماره پرسنلی', 1, 0, 'C', true );
                        $pdf->faCell( $vw, $rh, (int) $record->user_id, 1, 0, 'C' );
                        $pdf->Ln( $rh );
                        $pdf->Ln( 1.5 );

                        // جدول جزئیات — عرض ستون‌ها نصف عرض مفید صفحه.
                        $col_w = $pw / 2;
                        $render_table_header = function () use ( $pdf, $col_w, $lh, $settings ) {
                                $pdf->SetFillColor( 217, 226, 243 );
                                $pdf->SetFont( 'vazir', 'B', 10 );
                                $pdf->faCell( $col_w, $lh, 'عنوان', 1, 0, 'C', true );
                                $pdf->faCell( $col_w, $lh, 'مقدار (' . $settings['currency'] . ')', 1, 0, 'C', true );
                                $pdf->Ln( $lh );
                        };
                        $render_table_header();
                        $pdf->SetFont( 'vazir', 'B', 10 ); // نسخه 1.7.1: مقادیر هم Bold
                        $i = 0;
                        foreach ( $fields as $f ) {
                                if ( ! $f->show_in_payslip ) {
                                        continue;
                                }
                                $val = isset( $payload[ $f->field_key ] ) ? $payload[ $f->field_key ] : '';
                                if ( $pdf->GetY() + $rh > $page_h - 20 ) {
                                        /* نسخه 1.7.1: هدر جدول در صفحه دوم هم تکرار می‌شود. */
                                        $pdf->AddPage();
                                        $render_table_header();
                                }
                                $fill = ( 0 === $i % 2 );
                                $pdf->SetFillColor( 246, 247, 250 );
                                $pdf->faCell( $col_w, $rh, $f->label, 1, 0, 'R', $fill );
                                if ( 'number' === $f->field_type ) {
                                        /* نسخه 1.4.1: مبالغ منفی در PDF هم قرمز هستند. */
                                        $neg = ( (float) $val < 0 );
                                        if ( $neg ) { $pdf->SetTextColor( 185, 28, 28 ); }
                                        $pdf->faCell( $col_w, $rh, tpp_salary_format_number( (float) $val, false ), 1, 0, 'C', $fill );
                                        if ( $neg ) { $pdf->SetTextColor( 0, 0, 0 ); }
                                } else {
                                        $pdf->faCell( $col_w, $rh, (string) $val, 1, 0, 'C', $fill );
                                }
                                $pdf->Ln( $rh );
                                $i++;
                        }

                        // امضاها.
                        $pdf->Ln( 3 );
                        $pdf->SetFont( 'vazir', 'B', 10 );
                        $pdf->faCell( $col_w, 5.5, 'امضای کارمند', 0, 0, 'C' );
                        $pdf->faCell( $col_w, 5.5, 'امضای کارفرما', 0, 0, 'C' );
                        $pdf->Ln( 5.5 );
                        $pdf->Line( $pw * 0.22, $pdf->GetY(), $pw * 0.40, $pdf->GetY() );
                        $pdf->Line( $pw * 0.62, $pdf->GetY(), $pw * 0.80, $pdf->GetY() );
                        $pdf->SetY( $pdf->GetY() + 2.5 );
                        $pdf->SetFont( 'vazir', 'B', 10 );
                        $pdf->faCell( $pw, 4.5, 'تاریخ چاپ: ' . tpp_salary_today_fa(), 0, 1, 'C' );

                        return $pdf->Output( 'S' );
                } catch ( Exception $e ) {
                        return new WP_Error( 'tpp_salary_pdf_error', $e->getMessage() );
                }
        }

        /**
         * دانلود فیش PDF (AJAX)
         *
         * @return void
         */
        public static function backup_download() {
                check_admin_referer( 'tpp_salary_backup_download' );
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                $file = sanitize_text_field( wp_unslash( (isset($_GET['file'] )?$_GET['file'] : '' )) );
                $dir  = tpp_salary_backup_dir();
                $base = is_dir( $dir ) ? realpath( $dir ) : false;
                $path = $base ? realpath( $dir . '/' . $file ) : false;
                if ( ! $base || ! $path || 0 !== strpos( $path, $base ) || ! is_file( $path ) ) {
                        wp_die( 'فایل یافت نشد (فایل بکاپ روی سرور موجود نیست یا حذف شده است)' );
                }
                tpp_salary_clean_output(); // حذف خروجی مزاحم — جلوگیری از فایل خراب.
                nocache_headers();
                header( 'Content-Type: application/octet-stream' );
                header( 'Content-Disposition: attachment; filename="' . basename( $path ) . '"' );
                header( 'Content-Length: ' . filesize( $path ) );
                readfile( $path ); // phpcs:ignore
                exit;
        }

        /**
         * صفحه پشتیبان‌گیری
         *
         * @return void
         */
        public static function render_backup() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                global $wpdb;
                $logs = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}tpp_salary_backups ORDER BY created_at DESC LIMIT 50" ); // phpcs:ignore
                $settings = tpp_salary_get_settings();
                ?>
                <div class="wrap tpp-wrap" dir="rtl">
                        <h1>پشتیبان‌گیری و بازگردانی</h1>
                        <?php if ( ! empty( $_GET['created'] ) ) : ?>
                                <div class="notice notice-success is-dismissible"><p>بکاپ با موفقیت ایجاد شد.</p></div>
                        <?php endif; ?>
                        <?php if ( ! empty( $_GET['deleted'] ) ) : ?>
                                <div class="notice notice-success is-dismissible"><p>بکاپ حذف شد.</p></div>
                        <?php endif; ?>
                        <?php if ( ! empty( $_GET['restored'] ) ) :
                                $cur = get_current_user_id();
                                $rsum = $cur ? get_transient( 'tpp_salary_restore_summary_' . $cur ) : false;
                                if ( $cur ) { delete_transient( 'tpp_salary_restore_summary_' . $cur ); } ?>
                                <div class="notice notice-success is-dismissible"><p>
                                        <strong>بازگردانی انجام شد.</strong>
                                        <?php if ( is_array( $rsum ) ) :
                                                $parts = array();
                                                if ( ! empty( $rsum['records'] ) ) { $parts[] = tpp_salary_format_number( $rsum['records'] ) . ' رکورد حقوق'; }
                                                if ( ! empty( $rsum['users_created'] ) ) { $parts[] = tpp_salary_format_number( $rsum['users_created'] ) . ' کارمند جدید ساخته شد'; }
                                                if ( ! empty( $rsum['users_matched'] ) ) { $parts[] = tpp_salary_format_number( $rsum['users_matched'] ) . ' کارمند با حساب موجود تطبیق یافت'; }
                                                if ( ! empty( $rsum['centers'] ) ) { $parts[] = tpp_salary_format_number( $rsum['centers'] ) . ' مرکز'; }
                                                if ( ! empty( $rsum['banks'] ) ) { $parts[] = tpp_salary_format_number( $rsum['banks'] ) . ' بانک'; }
                                                if ( ! empty( $rsum['fields'] ) ) { $parts[] = tpp_salary_format_number( $rsum['fields'] ) . ' فیلد'; }
                                                if ( $parts ) { echo esc_html( implode( '، ', $parts ) . '.' ); }
                                                if ( ! empty( $rsum['records_skipped'] ) ) {
                                                        echo ' ' . esc_html( tpp_salary_format_number( $rsum['records_skipped'] ) . ' رکورد بدون کارمند قابل تطبیق رد شد.' );
                                                }
                                        endif; ?>
                                </p></div>
                        <?php endif; ?>
                        <p class="description">بکاپ خودکار: <?php
                        $types = array();
                        if ( ! empty( $settings['backup']['daily'] ) ) { $types[] = 'روزانه'; }
                        if ( ! empty( $settings['backup']['weekly'] ) ) { $types[] = 'هفتگی'; }
                        if ( ! empty( $settings['backup']['monthly'] ) ) { $types[] = 'ماهانه'; }
                        if ( ! empty( $settings['backup']['yearly'] ) ) { $types[] = 'سالانه'; }
                        echo $types ? esc_html( implode( '، ', $types ) ) : 'غیرفعال';
                        ?></p>

                        <h2>ایجاد بکاپ دستی</h2>
                        <p class="description">در همه قالب‌ها، «اطلاعات کارمندان» و «رکوردهای حقوق ثبت‌شده» به صورت مجزا ذخیره می‌شوند. انتخاب ZIP علاوه بر همه قالب‌ها (SQL + JSON + اکسل مجزا)، کل فایل‌های پوشه افزونه را هم بایگانی می‌کند.</p>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:12px;align-items:center">
                                <?php wp_nonce_field( 'tpp_salary_backup_create' ); ?>
                                <input type="hidden" name="action" value="tpp_salary_backup_create">
                                <select name="backup_type">
                                        <option value="json">JSON (کامل)</option>
                                        <option value="xlsx">اکسل (xlsx)</option>
                                        <option value="sql">SQL (کامل — قابل بازگردانی با phpMyAdmin)</option>
                                        <option value="zip">ZIP (بسته کامل: SQL + JSON + اکسل مجزا + فایل‌های افزونه)</option>
                                </select>
                                <button class="button button-primary">ایجاد بکاپ</button>
                        </form>

                        <h2>بازگردانی از فایل بکاپ (ZIP یا JSON) — نسخه 1.7.6</h2>
                        <p class="description">همه بکاپ‌های خودکار (روزانه/هفتگی/ماهانه/سالانه) بسته ZIP هستند و همین بسته‌ها مستقیماً قابل بازگردانی‌اند؛ یا فایل JSON بکاپ (پسوند .json) را انتخاب کنید. بازگردانی روی سرور/دامنه دیگر هم کار می‌کند: کارمندان در نصب مقصد خودکار تطبیق یا ساخته می‌شوند و رکوردها به حساب‌های واقعی متصل می‌شوند. اگر فایل بخش مجزا (employees.json / records.json) بدهید فقط همان بخش بازگردانی می‌شود.</p>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" onsubmit="return confirm('داده‌های فعلی حقوق و دستمزد (یا بخش مربوط به فایل‌های مجزا) با محتوای فایل بکاپ جایگزین می‌شود. پیش از ادامه، از وضعیت فعلی بکاپ بگیرید. مطمئن هستید؟')">
                                <?php wp_nonce_field( 'tpp_salary_backup_restore' ); ?>
                                <input type="hidden" name="action" value="tpp_salary_backup_restore">
                                <p><input type="file" name="restore_file" accept=".json,.zip" required></p>
                                <button class="button">بازگردانی</button>
                        </form>

                        <h2>آخرین بکاپ‌ها</h2>
                        <table class="widefat striped">
                                <thead><tr><th>زمان</th><th>نوع</th><th>منشأ</th><th>حجم</th><th>دانلود</th><th>حذف</th></tr></thead>
                                <tbody>
                                <?php if ( empty( $logs ) ) : ?>
                                        <tr><td colspan="6">بکاپی ثبت نشده است.</td></tr>
                                <?php endif; ?>
                                <?php foreach ( $logs as $log ) :
                                        $label = array( 'json' => 'JSON', 'xlsx' => 'اکسل', 'excel' => 'اکسل (قدیمی)', 'sql' => 'SQL', 'zip' => 'ZIP' );
                                        $origin = array( 'manual' => 'دستی', 'daily' => 'روزانه', 'weekly' => 'هفتگی', 'monthly' => 'ماهانه', 'yearly' => 'سالانه' );
                                        ?>
                                        <tr>
                                                <td><?php echo esc_html( $log->created_at ); ?></td>
                                                <td><?php echo esc_html( (isset($label[ $log->backup_type ] )?$label[ $log->backup_type ] : $log->backup_type )); ?></td>
                                                <td><?php echo esc_html( (isset($origin[ $log->origin ] )?$origin[ $log->origin ] : $log->origin )); ?></td>
                                                <td><?php echo esc_html( size_format( (float) $log->file_size ) ); ?></td>
                                                <td>
                                                        <?php
                                                        $exists = $log->file_path && file_exists( $log->file_path );
                                                        if ( $exists ) {
                                                                $du = wp_nonce_url( add_query_arg( array( 'action' => 'tpp_salary_backup_download', 'file' => basename( $log->file_path ) ), admin_url( 'admin-post.php' ) ), 'tpp_salary_backup_download' );
                                                                echo '<a class="button button-small" href="' . esc_url( $du ) . '">دانلود</a>';
                                                        } else {
                                                                echo '<span class="description">فایل موجود نیست</span>';
                                                        }
                                                        ?>
                                                </td>
                                                <td>
                                                        <?php
                                                        $dd = wp_nonce_url( add_query_arg( array( 'action' => 'tpp_salary_backup_delete', 'id' => (int) $log->id ), admin_url( 'admin-post.php' ) ), 'tpp_salary_backup_delete' );
                                                        echo '<a class="button button-small" style="color:#b32d2e;border-color:#b32d2e" href="' . esc_url( $dd ) . '" onclick="return confirm(\'این بکاپ و فایل آن برای همیشه حذف می‌شود. مطمئن هستید؟\')">حذف</a>';
                                                        ?>
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
