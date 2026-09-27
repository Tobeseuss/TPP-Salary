<?php
/**
 * فایل‌های نمونه داینامیک — همیشه همگام با فیلدهای فعلی سیستم
 *
 * ستون‌های فایل‌های نمونه به صورت خودکار از فیلدهای فعال دیتابیس ساخته می‌شوند؛
 * با افزودن/ویرایش/حذف هر فیلد در تنظیمات، نمونه‌ها به‌روز می‌شوند
 * (بازتولید فایل‌های داخل بسته + دانلود زنده از تب «فایل‌های نمونه»).
 *
 * @package TPP_Salary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class TPP_Samples
 */
if ( ! class_exists( 'TPP_Samples' ) ) {
	// TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TPP_Samples {

        /**
         * مقداردهی اولیه
         *
         * @return void
         */
        public static function init() {
                add_action( 'admin_post_tpp_sample_employees', array( __CLASS__, 'download_employees' ) );
                add_action( 'admin_post_tpp_sample_records', array( __CLASS__, 'download_records' ) );
                // پس از هر تغییر فیلد، فایل‌های نمونه داخل بسته بازتولید می‌شوند.
                add_action( 'tpp_fields_changed', array( __CLASS__, 'refresh' ) );
        }

        /**
         * سرستون‌های فایل نمونه کارمندان (مطابق mapping ورود گروهی)
         *
         * @return array
         */
        public static function employees_headers() {
                $headers = array( 'نام و نام خانوادگی', 'کد ملی', 'شماره همراه', 'ایمیل', 'مرکز' );
                foreach ( tpp_get_profile_fields() as $f ) {
                        $headers[] = $f->label;
                }
                return $headers;
        }

        /**
         * سطرهای نمونه کارمندان
         *
         * @return array
         */
        public static function employees_rows() {
                $defaults = tpp_get_settings();
                $defaults = isset( $defaults['defaults'] ) ? $defaults['defaults'] : array();

                $people = array(
                        array( 'علی محمدی', '0012345678', '09121234567', 'ali@example.com', 'بومهن' ),
                        array( 'زهرا حسینی', '0098765432', '09351112233', 'zahra@example.com', 'رودهن' ),
                        array( 'حسین رضایی', '0078451229', '09194445566', '', 'بومهن, رودهن' ),
                );

                $rows = array();
                foreach ( $people as $base ) {
                        $row = $base;
                        foreach ( tpp_get_profile_fields() as $f ) {
                                $def   = isset( $defaults[ $f->field_key ] ) ? $defaults[ $f->field_key ] : $f->default_value;
                                $value = ( '' !== $def ) ? $def : '0';
                                if ( 'text' === $f->field_type ) {
                                        $value = ( 'insurance_group' === $f->field_key ) ? '۶' : '';
                                        if ( 'insurance_group' === $f->field_key && '' === (string) $def ) {
                                                $value = '۶';
                                        } elseif ( 'insurance_group' === $f->field_key ) {
                                                $value = $def;
                                        }
                                }
                                $row[] = $value;
                        }
                        $rows[] = $row;
                }
                return $rows;
        }

        /**
         * سرستون‌های فایل نمونه رکوردهای حقوق (مطابق فرم ثبت)
         *
         * @return array
         */
        public static function records_headers() {
                $headers = array( 'سال', 'ماه', 'مرکز', 'نام و نام خانوادگی' );
                foreach ( tpp_get_fields() as $f ) {
                        $headers[] = $f->label;
                }
                return $headers;
        }

        /**
         * سطرهای نمونه رکوردها
         *
         * @return array
         */
        public static function records_rows() {
                $defaults = tpp_get_settings();
                $defaults = isset( $defaults['defaults'] ) ? $defaults['defaults'] : array();

                $base = array(
                        array( 1405, 'مرداد', 'بومهن', 'علی محمدی' ),
                        array( 1405, 'مرداد', 'رودهن', 'زهرا حسینی' ),
                );

                $rows = array();
                foreach ( $base as $b ) {
                        $row = $b;
                        foreach ( tpp_get_fields() as $f ) {
                                $def   = isset( $defaults[ $f->field_key ] ) ? $defaults[ $f->field_key ] : $f->default_value;
                                $value = ( '' !== (string) $def ) ? $def : '0';
                                if ( $f->is_calculated ) {
                                        $value = '—'; // محاسبه خودکار.
                                }
                                $row[] = $value;
                        }
                        $rows[] = $row;
                }
                return $rows;
        }

        /**
         * ساخت فایل اکسل کارمندان
         *
         * @return TPP_Xlsx_Writer
         */
        public static function build_employees() {
                $x = new TPP_Xlsx_Writer();
                $x->add_sheet( 'کارمندان', true );
                $headers = self::employees_headers();
                foreach ( $headers as $i => $h ) {
                        $x->set( 1, $i + 1, $h, 'header' );
                }
                $r = 2;
                foreach ( self::employees_rows() as $row ) {
                        foreach ( $row as $ci => $v ) {
                                $x->set( $r, $ci + 1, $v, ( is_int( $v ) || is_float( $v ) ) ? 'num' : 'text' );
                        }
                        $r++;
                }
                $widths = array( 22, 14, 15, 22, 16 );
                for ( $i = count( $widths ); $i < count( $headers ); $i++ ) {
                        $widths[] = 16;
                }
                foreach ( $widths as $i => $w ) {
                        $x->set_width( self::col_letter( $i + 1 ), $w );
                }
                $x->freeze( 'A2' );
                return $x;
        }

        /**
         * ساخت فایل اکسل رکوردهای حقوق
         *
         * @return TPP_Xlsx_Writer
         */
        public static function build_records() {
                $x = new TPP_Xlsx_Writer();
                $x->add_sheet( 'رکوردهای حقوق', true );
                $headers = self::records_headers();
                foreach ( $headers as $i => $h ) {
                        $x->set( 1, $i + 1, $h, 'header' );
                }
                $r = 2;
                foreach ( self::records_rows() as $row ) {
                        foreach ( $row as $ci => $v ) {
                                $x->set( $r, $ci + 1, $v, ( is_int( $v ) || is_float( $v ) ) ? 'num' : 'text' );
                        }
                        $r++;
                }
                $widths = array( 8, 10, 14, 22 );
                for ( $i = count( $widths ); $i < count( $headers ); $i++ ) {
                        $widths[] = 16;
                }
                foreach ( $widths as $i => $w ) {
                        $x->set_width( self::col_letter( $i + 1 ), $w );
                }
                $x->freeze( 'E2' );
                return $x;
        }

        /**
         * حرف ستون از شماره (1 => A)
         *
         * @param int $n شماره ستون.
         * @return string
         */
        public static function col_letter( $n ) {
                $s = '';
                while ( $n > 0 ) {
                        $m = ( $n - 1 ) % 26;
                        $s = chr( 65 + $m ) . $s;
                        $n = (int) ( ( $n - $m - 1 ) / 26 );
                }
                return $s;
        }

        /**
         * دانلود زنده فایل نمونه کارمندان
         *
         * @return void
         */
        public static function download_employees() {
                if ( ! tpp_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_sample_employees' );
                self::build_employees()->download( 'employees-sample.xlsx' );
                exit;
        }

        /**
         * دانلود زنده فایل نمونه رکوردهای حقوق
         *
         * @return void
         */
        public static function download_records() {
                if ( ! tpp_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_sample_records' );
                self::build_records()->download( 'salary-records-sample.xlsx' );
                exit;
        }

        /**
         * بازتولید فایل‌های نمونه داخل بسته (در صورت قابل نوشتن بودن پوشه)
         *
         * @return bool
         */
        public static function refresh() {
                $dir = TPP_SALARY_DIR . 'samples';
                if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
                        return false;
                }
                $ok = true;
                try {
                        file_put_contents( $dir . '/employees-sample.xlsx', self::build_employees()->to_string() );
                        file_put_contents( $dir . '/salary-records-sample.xlsx', self::build_records()->to_string() );
                } catch ( Exception $e ) {
                        $ok = false;
                }
                return $ok;
        }
}
}
// TPP_SALARY GUARD END
