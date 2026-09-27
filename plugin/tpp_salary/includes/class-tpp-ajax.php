<?php
/**
 * اندپوینت‌های AJAX — فیش PDF و داده‌های ویزارد
 *
 * @package TPP_Salary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TPP_Ajax
 */
if ( ! class_exists( 'TPP_Ajax' ) ) {
	// TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TPP_Ajax {

	/**
	 * مقداردهی اولیه
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_tpp_payslip_pdf', array( __CLASS__, 'payslip_pdf' ) );
		add_action( 'wp_ajax_tpp_period_employees', array( __CLASS__, 'period_employees' ) );
		add_action( 'wp_ajax_tpp_employee_payslip', array( __CLASS__, 'employee_payslip' ) );
	}

	/**
	 * فیش PDF یک رکورد (مدیر/حسابدار)
	 *
	 * @return void
	 */
	public static function payslip_pdf() {
		check_ajax_referer( 'tpp_payslip_pdf' );
		if ( ! tpp_can_manage() ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$id     = (int) ( (isset($_REQUEST['record_id'] )?$_REQUEST['record_id'] : 0 ));
		$record = tpp_get_record( $id );
		if ( ! $record ) {
			wp_die( 'رکورد یافت نشد' );
		}
		$data = TPP_Reports::build_payslip_pdf( $record );
		if ( is_wp_error( $data ) ) {
			wp_die( esc_html( $data->get_error_message() ) );
		}
		$user = get_userdata( $record->user_id );
		$name = sanitize_file_name( ( $user ? $user->display_name : 'payslip' ) ) . '-' . (int) $record->jyear . '-' . str_pad( (string) $record->jmonth, 2, '0', STR_PAD_LEFT ) . '.pdf';
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
		check_ajax_referer( 'tpp_ajax' );
		if ( ! tpp_can_manage() ) {
			wp_send_json_error( 'دسترسی غیرمجاز' );
		}
		$center_id = (int) ( (isset($_POST['center_id'] )?$_POST['center_id'] : 0 ));
		$jyear     = (int) ( (isset($_POST['jyear'] )?$_POST['jyear'] : 0 ));
		$jmonth    = (int) ( (isset($_POST['jmonth'] )?$_POST['jmonth'] : 0 ));
		$employees = tpp_get_employees( $center_id );
		$registered = array();
		$unregistered = array();
		foreach ( $employees as $u ) {
			$rec = tpp_get_record_period( $u->ID, $center_id, $jyear, $jmonth );
			if ( $rec ) {
				$registered[] = array( 'id' => $u->ID, 'name' => $u->display_name, 'net' => (float) $rec->net );
			} else {
				$unregistered[] = array( 'id' => $u->ID, 'name' => $u->display_name );
			}
		}
		wp_send_json_success( array( 'registered' => $registered, 'unregistered' => $unregistered ) );
	}

	/**
	 * فیش PDF کارمند خودش (پنل کاربری)
	 *
	 * @return void
	 */
	public static function employee_payslip() {
		check_ajax_referer( 'tpp_ajax' );
		$record_id = (int) ( (isset($_REQUEST['record_id'] )?$_REQUEST['record_id'] : 0 ));
		$record    = tpp_get_record( $record_id );
		if ( ! $record || ( (int) $record->user_id !== get_current_user_id() && ! tpp_can_manage() ) ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$data = TPP_Reports::build_payslip_pdf( $record );
		if ( is_wp_error( $data ) ) {
			wp_die( esc_html( $data->get_error_message() ) );
		}
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
