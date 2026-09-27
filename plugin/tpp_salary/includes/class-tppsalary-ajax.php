<?php
/**
 * اندپوینت‌های AJAX — فیش PDF و داده‌های ویزارد
 *
 * @package TppSalary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class TppSalary_Ajax
 */
if ( ! class_exists( 'TppSalary_Ajax' ) ) {
        // TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TppSalary_Ajax {

        /**
         * مقداردهی اولیه
         *
         * @return void
         */
        public static function init() {
                add_action( 'wp_ajax_tpp_salary_payslip_pdf', array( __CLASS__, 'payslip_pdf' ) );
                add_action( 'wp_ajax_tpp_salary_period_employees', array( __CLASS__, 'period_employees' ) );
                add_action( 'wp_ajax_tpp_salary_employee_payslip', array( __CLASS__, 'employee_payslip' ) );
                /* نسخه 1.6.3: پر کردن فیلدهای فرم ثبت حقوق بر اساس حقوق گذشته */
                add_action( 'wp_ajax_tpp_salary_past_salary', array( __CLASS__, 'past_salary' ) );
        }

        /**
         * فیش PDF یک رکورد (مدیر/حسابدار)
         *
         * @return void
         */
        public static function payslip_pdf() {
                check_ajax_referer( 'tpp_salary_payslip_pdf' );
                if ( ! tpp_salary_can_manage() ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                $id     = (int) ( (isset($_REQUEST['record_id'] )?$_REQUEST['record_id'] : 0 ));
                $record = tpp_salary_get_record( $id );
                if ( ! $record ) {
                        wp_die( 'رکورد یافت نشد' );
                }
                $data = TppSalary_Reports::build_payslip_pdf( $record );
                if ( is_wp_error( $data ) ) {
                        wp_die( esc_html( $data->get_error_message() ) );
                }
                $user = get_userdata( $record->user_id );
                $name = sanitize_file_name( ( $user ? $user->display_name : 'payslip' ) ) . '-' . (int) $record->jyear . '-' . str_pad( (string) $record->jmonth, 2, '0', STR_PAD_LEFT ) . '.pdf';
                tpp_salary_clean_output(); // حذف خروجی مزاحم — جلوگیری از فایل خراب.
                nocache_headers();
                header( 'Content-Type: application/pdf' );
                header( 'Content-Disposition: inline; filename="' . rawurlencode( $name ) . '"' );
                header( 'Content-Length: ' . strlen( $data ) );
                echo $data; // phpcs:ignore
                exit;
        }

        /**
         * کارمندان یک مرکز برای ویزارد (ثبت‌شده / ثبت‌نشده)
         *
         * @return void
         */
        public static function period_employees() {
                check_ajax_referer( 'tpp_salary_ajax' );
                if ( ! tpp_salary_can_manage() ) {
                        wp_send_json_error( 'دسترسی غیرمجاز' );
                }
                $center_id = (int) ( (isset($_POST['center_id'] )?$_POST['center_id'] : 0 ));
                $jyear     = (int) ( (isset($_POST['jyear'] )?$_POST['jyear'] : 0 ));
                $jmonth    = (int) ( (isset($_POST['jmonth'] )?$_POST['jmonth'] : 0 ));
                $employees = tpp_salary_get_employees( $center_id );
                $registered = array();
                $unregistered = array();
                foreach ( $employees as $u ) {
                        $rec = tpp_salary_get_record_period( $u->ID, $center_id, $jyear, $jmonth );
                        if ( $rec ) {
                                $registered[] = array( 'id' => $u->ID, 'name' => $u->display_name, 'net' => (float) $rec->net );
                        } else {
                                $unregistered[] = array( 'id' => $u->ID, 'name' => $u->display_name );
                        }
                }
                wp_send_json_success( array( 'registered' => $registered, 'unregistered' => $unregistered ) );
        }

        /**
         * حقوق گذشتهٔ یک کارمند برای پرکردن فرم مرحله ۳ — نسخه 1.6.3
         *
         * ورودی: user_id + center_id + src_year + src_month (دورهٔ مبدأ).
         * خروجی: مقادیر فیلدها (قالب‌بندی‌شده)، فهرست فیلدهای دستی، حالت مشمول بیمه
         * و برچسب دورهٔ مبدأ — هسته محاسبه در TppSalary_Salary_Pages::past_salary_payload.
         *
         * @return void
         */
        public static function past_salary() {
                check_ajax_referer( 'tpp_salary_ajax' );
                if ( ! tpp_salary_can_manage() ) {
                        wp_send_json_error( 'دسترسی غیرمجاز' );
                }
                $user_id   = (int) ( ( isset( $_POST['user_id'] ) ? $_POST['user_id'] : 0 ) );
                $center_id = (int) ( ( isset( $_POST['center_id'] ) ? $_POST['center_id'] : 0 ) );
                $src_year  = (int) ( ( isset( $_POST['src_year'] ) ? $_POST['src_year'] : 0 ) );
                $src_month = (int) ( ( isset( $_POST['src_month'] ) ? $_POST['src_month'] : 0 ) );
                $res = TppSalary_Salary_Pages::past_salary_payload( $user_id, $center_id, $src_year, $src_month );
                if ( is_wp_error( $res ) ) {
                        wp_send_json_error( $res->get_error_message() );
                }
                if ( false === $res ) {
                        wp_send_json_success( array( 'found' => false, 'message' => 'برای این کارمند در سال و ماه انتخاب‌شده رکوردی ثبت نشده است.' ) );
                }
                $res['found'] = true;
                wp_send_json_success( $res );
        }

        /**
         * فیش PDF کارمند خودش (پنل کاربری)
         *
         * @return void
         */
        public static function employee_payslip() {
                check_ajax_referer( 'tpp_salary_ajax' );
                $record_id = (int) ( (isset($_REQUEST['record_id'] )?$_REQUEST['record_id'] : 0 ));
                $record    = tpp_salary_get_record( $record_id );
                if ( ! $record || ( (int) $record->user_id !== get_current_user_id() && ! tpp_salary_can_manage() ) ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                $data = TppSalary_Reports::build_payslip_pdf( $record );
                if ( is_wp_error( $data ) ) {
                        wp_die( esc_html( $data->get_error_message() ) );
                }
                tpp_salary_clean_output(); // حذف خروجی مزاحم — جلوگیری از فایل خراب.
                nocache_headers();
                header( 'Content-Type: application/pdf' );
                header( 'Content-Disposition: inline; filename="payslip-' . (int) $record->jyear . '-' . (int) $record->jmonth . '.pdf"' );
                header( 'Content-Length: ' . strlen( $data ) );
                echo $data; // phpcs:ignore
                exit;
        }
}
}
// TPP_SALARY GUARD END
