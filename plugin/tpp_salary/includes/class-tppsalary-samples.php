<?php
/**
 * فایل‌های نمونه داینامیک — همیشه همگام با فیلدهای فعلی سیستم
 *
 * ستون‌های فایل‌های نمونه به صورت خودکار از فیلدهای فعال دیتابیس ساخته می‌شوند؛
 * با افزودن/ویرایش/حذف هر فیلد در تنظیمات، نمونه‌ها به‌روز می‌شوند
 * (بازتولید فایل‌های داخل بسته + دانلود زنده از تب «فایل‌های نمونه»).
 *
 * @package TppSalary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class TppSalary_Samples
 */
if ( ! class_exists( 'TppSalary_Samples' ) ) {
        // TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TppSalary_Samples {

        /**
         * مقداردهی اولیه
         *
         * @return void
         */
        public static function init() {
                add_action( 'admin_post_tpp_salary_sample_employees', array( __CLASS__, 'download_employees' ) );
                add_action( 'admin_post_tpp_salary_sample_records', array( __CLASS__, 'download_records' ) );
                // پس از هر تغییر فیلد، فایل‌های نمونه داخل بسته بازتولید می‌شوند.
                add_action( 'tpp_salary_fields_changed', array( __CLASS__, 'refresh' ) );
        }

        /**
         * سرستون‌های فایل نمونه کارمندان (مطابق mapping ورود گروهی)
         *
         * @return array
         */
        public static function employees_headers() {
                $headers = array( 'نام و نام خانوادگی', 'کد ملی', 'شماره همراه', 'ایمیل', 'مرکز' );
                foreach ( tpp_salary_get_profile_fields() as $f ) {
                        /* ستون اول همین است — فیلد full_name تکراری نمی‌شود (نسخه 1.4.1). */
                        if ( 'full_name' === $f->field_key ) { continue; }
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
                $defaults = tpp_salary_get_settings();
                $defaults = isset( $defaults['defaults'] ) ? $defaults['defaults'] : array();

                /*
                 * نسخه 1.6.0 — سطرهای نمونه بر اساس داده واقعی کاربر (فایل completed
                 * پیوست‌شده) بازنویسی شد تا ساختار/مقیاس اعداد با واقعیت یکی باشد.
                 */
                $people = array(
                        array( 'اسماعیل کمال آبادی', '9000000001', '09011000001', 'user001@example.com', 'بومهن' ),
                        array( 'محمد غلامی', '9000000002', '09011000002', 'user002@example.com', 'بومهن' ),
                        array( 'محمدرضا ریاحی', '9000000003', '09011000003', 'user003@example.com', 'بومهن' ),
                );

                $rows = array();
                foreach ( $people as $base ) {
                        $row = $base;
                        foreach ( tpp_salary_get_profile_fields() as $f ) {
                                if ( 'full_name' === $f->field_key ) { continue; }
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
                foreach ( self::record_form_fields() as $f ) {
                        $headers[] = $f->label;
                }
                return $headers;
        }

        /**
         * فیلدهای فرم ثبت حقوق (بدون فیلدهای فقط‌پروفایلی) — نسخه 1.4.1
         *
         * @return array<int,object>
         */
        public static function record_form_fields() {
                return array_values( array_filter( tpp_salary_get_fields(), function ( $f ) {
                        return tpp_salary_field_in_record( $f ); // نسخه 1.5.0: سپر هاردکد فیلدهای فقط‌پروفایلی.
                } ) );
        }

        /**
         * سطرهای نمونه رکوردها
         *
         * @return array
         */
        public static function records_rows() {
                $defaults = tpp_salary_get_settings();
                $defaults = isset( $defaults['defaults'] ) ? $defaults['defaults'] : array();

                /*
                 * نسخه 1.6.0 — سطرهای نمونه با داده واقعی کاربر (مرکز بومهن،
                 * مرداد ۱۴۰۵)؛ فیلدهای محاسباتی «—» می‌مانند تا از فرمول محاسبه شوند.
                 */
                $base = array(
                        array( 1405, 'مرداد', 'بومهن', 'اسماعیل کمال آبادی' ),
                        array( 1405, 'مرداد', 'بومهن', 'محمد غلامی' ),
                );

                $rows = array();
                foreach ( $base as $b ) {
                        $row = $b;
                        foreach ( self::record_form_fields() as $f ) {
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
         * @return TppSalary_Xlsx_Writer
         */
        public static function build_employees() {
                $x = new TppSalary_Xlsx_Writer();
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
         * @return TppSalary_Xlsx_Writer
         */
        public static function build_records() {
                $x = new TppSalary_Xlsx_Writer();
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
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_salary_sample_employees' );
                self::build_employees()->download( 'employees-sample.xlsx' );
                exit;
        }

        /**
         * دانلود زنده فایل نمونه رکوردهای حقوق
         *
         * @return void
         */
        public static function download_records() {
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_salary_sample_records' );
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
