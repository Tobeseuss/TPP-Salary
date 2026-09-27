<?php
/**
 * گزارش‌ها و خروجی‌ها — لیست حقوق (اکسل/PDF)، فیش بانکی، فیش حقوقی تکی و عمده ZIP
 *
 * @package TPP_Salary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TPP_Reports
 */
if ( ! class_exists( 'TPP_Reports' ) ) {
	// TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TPP_Reports {

	/**
	 * مقداردهی اولیه
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_tpp_report_excel', array( __CLASS__, 'report_excel' ) );
		add_action( 'admin_post_tpp_report_pdf', array( __CLASS__, 'report_pdf' ) );
		add_action( 'admin_post_tpp_bank_excel', array( __CLASS__, 'bank_excel' ) );
		add_action( 'admin_post_tpp_bank_pdf', array( __CLASS__, 'bank_pdf' ) );
		add_action( 'admin_post_tpp_bulk_zip', array( __CLASS__, 'bulk_zip' ) );
		add_action( 'admin_post_tpp_backup_download', array( __CLASS__, 'backup_download' ) );
	}

	/**
	 * منوها
	 *
	 * @return void
	 */
	public static function menu() {
		add_submenu_page( 'tpp-salary', 'گزارش لیست حقوق', 'گزارش لیست حقوق', 'tpp_manage_salary', 'tpp-report', array( __CLASS__, 'render_report' ) );
		add_submenu_page( 'tpp-salary', 'فیش بانکی', 'فیش بانکی', 'tpp_manage_salary', 'tpp-bank-report', array( __CLASS__, 'render_bank' ) );
		add_submenu_page( 'tpp-salary', 'فیش‌های حقوقی', 'فیش‌های حقوقی', 'tpp_manage_salary', 'tpp-payslips', array( __CLASS__, 'render_payslips' ) );
		add_submenu_page( 'tpp-salary', 'پشتیبان‌گیری', 'پشتیبان‌گیری', 'tpp_manage_salary', 'tpp-backup', array( __CLASS__, 'render_backup' ) );
	}

	/**
	 * دریافت دوره و مرکز از درخواست
	 *
	 * @return array
	 */
	private static function period() {
		$today = TPP_Jalali::today();
		return array(
			'jyear'     => isset( $_REQUEST['jyear'] ) ? (int) $_REQUEST['jyear'] : $today[0],
			'jmonth'    => isset( $_REQUEST['jmonth'] ) ? (int) $_REQUEST['jmonth'] : $today[1],
			'center_id' => isset( $_REQUEST['center_id'] ) ? (int) $_REQUEST['center_id'] : 0,
		);
	}

	/**
	 * صفحه گزارش لیست حقوق
	 *
	 * @return void
	 */
	public static function render_report() {
		if ( ! tpp_can_manage() ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		list( $jyear, $jmonth, $center_id ) = array_values( self::period() );
		$records = $jyear ? tpp_get_period_records( $jyear, $jmonth, $center_id ) : array();
		$fields  = tpp_get_fields();
		self::period_form( 'tpp-report', 'گزارش لیست حقوق و جزئیات آن', $records );
		if ( $records ) {
			$center = $center_id ? tpp_get_center( $center_id ) : null;
			self::pivot_table( $records, $fields, $jyear, $jmonth, $center ? $center->name : 'همه مراکز' );
			$ex = add_query_arg( self::period_query(), admin_url( 'admin-post.php?action=tpp_report_excel' ) );
			$pd = add_query_arg( self::period_query(), admin_url( 'admin-post.php?action=tpp_report_pdf' ) );
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
	 * @return void
	 */
	private static function period_form( $page, $title, $records = null ) {
		$p = self::period();
		?>
		<div class="wrap tpp-wrap" dir="rtl">
			<h1><?php echo esc_html( $title ); ?></h1>
			<form method="get" style="margin:12px 0">
				<input type="hidden" name="page" value="<?php echo esc_attr( $page ); ?>">
				<?php $today = TPP_Jalali::today(); ?>
				<select name="jyear">
					<?php for ( $y = $today[0] - 6; $y <= $today[0] + 1; $y++ ) : ?>
						<option value="<?php echo $y; ?>" <?php selected( $p['jyear'], $y ); ?>><?php echo TPP_Jalali::digits_fa( $y ); ?></option>
					<?php endfor; ?>
				</select>
				<select name="jmonth">
					<?php foreach ( TPP_Jalali::months() as $m => $label ) : ?>
						<option value="<?php echo $m; ?>" <?php selected( $p['jmonth'], $m ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="center_id">
					<option value="">همه مراکز</option>
					<?php foreach ( tpp_get_centers() as $c ) : ?>
						<option value="<?php echo (int) $c->id; ?>" <?php selected( $p['center_id'], $c->id ); ?>><?php echo esc_html( $c->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<button class="button button-primary">نمایش</button>
				<?php if ( null !== $records ) : ?>
					<span class="description" style="margin-right:12px"><?php echo TPP_Jalali::digits_fa( count( (array) $records ) ); ?> رکورد</span>
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
	 * @param array  $records رکوردها.
	 * @param array  $fields  فیلدها.
	 * @param int    $jyear   سال.
	 * @param int    $jmonth  ماه.
	 * @param string $center_name نام مرکز.
	 * @param int    $offset  شروع ستون.
	 * @return void
	 */
	private static function pivot_table( $records, $fields, $jyear, $jmonth, $center_name, $offset = 0 ) {
		$records = array_slice( $records, $offset, (int) tpp_get_setting( 'per_page_a4', 4 ) );
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
				<tr><td class="tpp-rowlabel">سال</td><?php foreach ( $records as $r ) : ?><td><?php echo esc_html( TPP_Jalali::digits_fa( $r->jyear ) ); ?></td><?php endforeach; ?></tr>
				<tr><td class="tpp-rowlabel">ماه</td><?php foreach ( $records as $r ) : ?><td><?php echo esc_html( TPP_Jalali::month_name( $r->jmonth ) ); ?></td><?php endforeach; ?></tr>
				<tr><td class="tpp-rowlabel">مرکز</td><?php foreach ( $records as $r ) : ?><td><?php echo esc_html( $center_name ); ?></td><?php endforeach; ?></tr>
				<?php foreach ( $fields as $f ) : ?>
					<tr>
						<td class="tpp-rowlabel"><?php echo esc_html( $f->label ); ?></td>
						<?php foreach ( $records as $r ) :
							$payload = tpp_record_payload( $r );
							$val     = isset( $payload[ $f->field_key ] ) ? $payload[ $f->field_key ] : '';
							?>
							<td <?php echo ( 'number' === $f->field_type ) ? 'dir="ltr"' : ''; ?>><?php echo esc_html( ( 'number' === $f->field_type ) ? tpp_format_number( (float) $val ) : $val ); ?></td>
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
		if ( ! tpp_can_manage() ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		list( $jyear, $jmonth, $center_id ) = array_values( self::period() );
		$records = tpp_get_period_records( $jyear, $jmonth, $center_id );
		if ( ! $records ) {
			wp_die( 'رکوردی برای این دوره یافت نشد' );
		}
		$fields = tpp_get_fields();
		$center = $center_id ? tpp_get_center( $center_id ) : null;
		$center_name = $center ? $center->name : 'همه مراکز';
		$company = tpp_get_setting( 'company_name' );
		$per     = (int) tpp_get_setting( 'per_page_a4', 4 );
		$currency = tpp_get_setting( 'currency', 'ریال' );

		$xlsx = new TPP_Xlsx_Writer();
		$pages = array_chunk( $records, $per );
		foreach ( $pages as $pi => $chunk ) {
			$sheet = 'صفحه ' . ( $pi + 1 );
			$xlsx->add_sheet( $sheet );
			$row = 1;
			$xlsx->merge( $row, 1, $row, 1 + count( $chunk ) );
			$xlsx->set( $row, 1, ( $company ? $company . ' — ' : '' ) . 'لیست حقوق ' . TPP_Jalali::month_name( $jmonth ) . ' ' . TPP_Jalali::digits_fa( $jyear ) . ' — ' . $center_name, 'title' );
			$row += 2;
			// هدرها: نام کارمندان.
			$xlsx->set( $row, 1, 'عناوین', 'header' );
			foreach ( $chunk as $ci => $r ) {
				$u = get_userdata( $r->user_id );
				$xlsx->set( $row, 2 + $ci, $u ? $u->display_name : '?', 'header' );
			}
			$row++;
			// سطرهای دوره.
			$meta = array( 'سال' => TPP_Jalali::digits_fa( $jyear ), 'ماه' => TPP_Jalali::month_name( $jmonth ), 'مرکز' => $center_name, 'واحد' => $currency );
			foreach ( $meta as $label => $val ) {
				$xlsx->set( $row, 1, $label, 'label' );
				foreach ( $chunk as $ci => $r ) {
					$xlsx->set( $row, 2 + $ci, $val, 'text' );
				}
				$row++;
			}
			// فیلدها.
			foreach ( $fields as $f ) {
				$xlsx->set( $row, 1, $f->label, 'label' );
				foreach ( $chunk as $ci => $r ) {
					$payload = tpp_record_payload( $r );
					$val = isset( $payload[ $f->field_key ] ) ? $payload[ $f->field_key ] : '';
					if ( 'number' === $f->field_type ) {
						$xlsx->set( $row, 2 + $ci, (float) $val, ( (float) $val < 0 || $f->is_negative ) ? 'num_neg' : 'num' );
					} else {
						$xlsx->set( $row, 2 + $ci, (string) $val, 'text' );
					}
				}
				$row++;
			}
			$xlsx->set_width( 'A', 26 );
			for ( $c = 2; $c <= 1 + count( $chunk ); $c++ ) {
				$xlsx->set_width( TPP_Xlsx_Writer::col_letter( $c ), 22 );
			}
		}
		$xlsx->download( 'salary-list-' . $jyear . '-' . str_pad( (string) $jmonth, 2, '0', STR_PAD_LEFT ) . '.xlsx' );
	}

	/**
	 * خروجی PDF گزارش لیست حقوق
	 *
	 * @return void
	 */
	public static function report_pdf() {
		if ( ! tpp_can_manage() ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		list( $jyear, $jmonth, $center_id ) = array_values( self::period() );
		$records = tpp_get_period_records( $jyear, $jmonth, $center_id );
		if ( ! $records ) {
			wp_die( 'رکوردی برای این دوره یافت نشد' );
		}
		$fields  = tpp_get_fields();
		$center  = $center_id ? tpp_get_center( $center_id ) : null;
		$center_name = $center ? $center->name : 'همه مراکز';
		$company = tpp_get_setting( 'company_name' );
		$per     = (int) tpp_get_setting( 'per_page_a4', 4 );
		$logo    = tpp_logo_path();
		$currency = tpp_get_setting( 'currency', 'ریال' );

		$orientation = ( $per >= 5 ) ? 'L' : 'P';
		$pdf = new TPP_PDF( $orientation, 'mm', 'A4' );
		$pdf->SetTitle( 'Salary List ' . $jyear . '-' . $jmonth );

		$pages = array_chunk( $records, $per );
		$pw    = $pdf->GetPageWidth() - 20;
		foreach ( $pages as $chunk ) {
			$pdf->AddPage();
			// سربرگ.
			$y = 10;
			if ( $logo ) {
				$pdf->Image( $logo, 10, $y, 26 );
			}
			$pdf->SetFont( 'vazir', 'B', 14 );
			$pdf->SetXY( 10, $y );
			$pdf->faCell( $pw, 8, $company ? $company : 'فهرست حقوق و دستمزد', 0, 1, 'C' );
			$pdf->SetFont( 'vazir', '', 11 );
			$pdf->SetXY( 10, $y + 8 );
			$pdf->faCell( $pw, 7, 'لیست حقوق و جزئیات — ' . TPP_Jalali::month_name( $jmonth ) . ' ' . TPP_Jalali::digits_fa( $jyear ) . ' — مرکز ' . $center_name, 0, 1, 'C' );
			$y += 18;
			$pdf->SetY( $y );

			$n    = count( $chunk ) + 1;
			$w1   = 34;
			$wc   = ( $pw - $w1 ) / count( $chunk );
			$rh   = 7.6;

			// ردیف نام کارمندان.
			$pdf->SetFont( 'vazir', 'B', 9 );
			$pdf->SetFillColor( 217, 226, 243 );
			$pdf->faCell( $w1, $rh, 'عناوین', 1, 0, 'C', true );
			foreach ( $chunk as $r ) {
				$u = get_userdata( $r->user_id );
				$pdf->faCell( $wc, $rh, $u ? $u->display_name : '?', 1, 0, 'C', true );
			}
			$pdf->Ln( $rh );

			$pdf->SetFont( 'vazir', '', 8.5 );
			// متادیتا.
			$meta = array( 'سال' => TPP_Jalali::digits_fa( $jyear ), 'ماه' => TPP_Jalali::month_name( $jmonth ), 'مرکز' => $center_name, 'واحد' => $currency );
			foreach ( $meta as $label => $val ) {
				$pdf->faCell( $w1, $rh, $label, 1, 0, 'C', true );
				foreach ( $chunk as $r ) {
					$pdf->faCell( $wc, $rh, $val, 1, 0, 'C' );
				}
				$pdf->Ln( $rh );
			}
			// فیلدها.
			$fill = false;
			foreach ( $fields as $f ) {
				if ( $pdf->GetY() + $rh > $pdf->GetPageHeight() - 14 ) {
					$pdf->AddPage();
				}
				$pdf->SetFillColor( 246, 247, 250 );
				$pdf->faCell( $w1, $rh, $f->label, 1, 0, 'C', true );
				$pdf->SetFont( 'vazir', '', 8.5 );
				foreach ( $chunk as $r ) {
					$payload = tpp_record_payload( $r );
					$val = isset( $payload[ $f->field_key ] ) ? $payload[ $f->field_key ] : '';
					if ( 'number' === $f->field_type ) {
						$num = (float) $val;
						$pdf->faCell( $wc, $rh, tpp_format_number( $num, false ), 1, 0, 'C' );
					} else {
						$pdf->faCell( $wc, $rh, (string) $val, 1, 0, 'C' );
					}
				}
				$pdf->Ln( $rh );
			}
		}
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="salary-list-' . $jyear . '-' . $jmonth . '.pdf"' );
		$pdf->Output( 'I', 'salary-list.pdf' );
		exit;
	}

	/**
	 * صفحه فیش بانکی
	 *
	 * @return void
	 */
	public static function render_bank() {
		if ( ! tpp_can_manage() ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		list( $jyear, $jmonth, $center_id ) = array_values( self::period() );
		$bank_id = (int) ( (isset($_REQUEST['bank_id'] )?$_REQUEST['bank_id'] : 0 ));
		$banks   = tpp_get_banks();
		self::period_form( 'tpp-bank-report', 'فیش بانکی' );
		?>
		<form method="get" style="margin:0 0 12px">
			<input type="hidden" name="page" value="tpp-bank-report">
			<input type="hidden" name="jyear" value="<?php echo (int) $jyear; ?>">
			<input type="hidden" name="jmonth" value="<?php echo (int) $jmonth; ?>">
			<input type="hidden" name="center_id" value="<?php echo (int) $center_id; ?>">
			<select name="bank_id" required>
				<option value="">— نام بانک —</option>
				<?php foreach ( $banks as $b ) : ?>
					<option value="<?php echo (int) $b->id; ?>" <?php selected( $bank_id, $b->id ); ?>><?php echo esc_html( $b->name ); ?></option>
				<?php endforeach; ?>
			</select>
			<button class="button button-primary">نمایش</button>
		</form>
		<?php
		if ( $jyear && $bank_id ) {
			$bank = tpp_get_bank( $bank_id );
			if ( ! $bank ) {
				echo '<p>بانک یافت نشد.</p></div>';
				return;
			}
			$records = tpp_get_period_records( $jyear, $jmonth, $center_id );
			$rows    = array();
			foreach ( $records as $r ) {
				$profile = tpp_get_profile( $r->user_id );
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
			<p class="description">کارکنانی که در <?php echo esc_html( TPP_Jalali::period_label( $jyear, $jmonth ) ); ?> برایشان حقوق ثبت شده و در بانک «<?php echo esc_html( $bank->name ); ?>» شماره حساب دارند: <?php echo TPP_Jalali::digits_fa( count( $rows ) ); ?> نفر — جمع خالص: <strong><?php echo esc_html( tpp_format_number( $total ) ); ?></strong></p>
			<table class="widefat striped" dir="rtl">
				<thead><tr><th>#</th><th>نام کارمند</th><th>شماره حساب</th><th>شماره شبا</th><th>حقوق خالص دریافتی</th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $i => $row ) : ?>
					<tr>
						<td><?php echo TPP_Jalali::digits_fa( $i + 1 ); ?></td>
						<td><?php echo esc_html( $row['name'] ); ?></td>
						<td dir="ltr"><?php echo esc_html( $row['account'] ); ?></td>
						<td dir="ltr"><?php echo esc_html( $row['sheba'] ); ?></td>
						<td dir="ltr"><strong><?php echo esc_html( tpp_format_number( $row['net'] ) ); ?></strong></td>
					</tr>
				<?php endforeach; ?>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="5">رکوردی یافت نشد.</td></tr>
				<?php endif; ?>
				</tbody>
			</table>
			<?php if ( $rows ) :
				$ex = add_query_arg( array_merge( self::period_query(), array( 'bank_id' => $bank_id ) ), admin_url( 'admin-post.php?action=tpp_bank_excel' ) );
				$pd = add_query_arg( array_merge( self::period_query(), array( 'bank_id' => $bank_id ) ), admin_url( 'admin-post.php?action=tpp_bank_pdf' ) );
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
		$records = tpp_get_period_records( $jyear, $jmonth, $center_id );
		$rows    = array();
		foreach ( $records as $r ) {
			$profile = tpp_get_profile( $r->user_id );
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
		if ( ! tpp_can_manage() ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		list( $jyear, $jmonth, $center_id ) = array_values( self::period() );
		$bank_id = (int) ( (isset($_REQUEST['bank_id'] )?$_REQUEST['bank_id'] : 0 ));
		$bank    = tpp_get_bank( $bank_id );
		if ( ! $bank ) {
			wp_die( 'بانک نامعتبر' );
		}
		$rows = self::bank_rows( $jyear, $jmonth, $center_id, $bank_id );
		$company = tpp_get_setting( 'company_name' );
		$center = $center_id ? tpp_get_center( $center_id ) : null;
		$center_name = $center ? $center->name : 'همه مراکز';

		$xlsx = new TPP_Xlsx_Writer();
		$xlsx->add_sheet( 'فیش بانکی' );
		$xlsx->merge( 1, 1, 1, 5 );
		$xlsx->set( 1, 1, ( $company ? $company . ' — ' : '' ) . 'فیش بانکی ' . $bank->name . ' — ' . TPP_Jalali::month_name( $jmonth ) . ' ' . TPP_Jalali::digits_fa( $jyear ) . ' — ' . $center_name, 'title' );
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
		if ( ! tpp_can_manage() ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		list( $jyear, $jmonth, $center_id ) = array_values( self::period() );
		$bank_id = (int) ( (isset($_REQUEST['bank_id'] )?$_REQUEST['bank_id'] : 0 ));
		$bank    = tpp_get_bank( $bank_id );
		if ( ! $bank ) {
			wp_die( 'بانک نامعتبر' );
		}
		$rows = self::bank_rows( $jyear, $jmonth, $center_id, $bank_id );
		$company = tpp_get_setting( 'company_name' );
		$center = $center_id ? tpp_get_center( $center_id ) : null;
		$center_name = $center ? $center->name : 'همه مراکز';
		$logo = tpp_logo_path();
		$currency = tpp_get_setting( 'currency', 'ریال' );

		$pdf = new TPP_PDF( 'P', 'mm', 'A4' );
		$pdf->SetTitle( 'Bank Slip' );
		$pw = $pdf->GetPageWidth() - 20;
		$y  = 10;
		if ( $logo ) {
			$pdf->Image( $logo, 10, $y, 24 );
		}
		$pdf->SetFont( 'vazir', 'B', 13 );
		$pdf->SetXY( 10, $y );
		$pdf->faCell( $pw, 8, $company ? $company : 'فیش بانکی', 0, 1, 'C' );
		$pdf->SetFont( 'vazir', '', 10.5 );
		$pdf->SetXY( 10, $y + 8 );
		$pdf->faCell( $pw, 6.5, 'فیش بانکی ' . $bank->name . ' — ' . TPP_Jalali::month_name( $jmonth ) . ' ' . TPP_Jalali::digits_fa( $jyear ) . ' — مرکز ' . $center_name, 0, 1, 'C' );
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
		$pdf->SetFont( 'vazir', 'B', 9 );
		$pdf->SetFillColor( 217, 226, 243 );
		foreach ( $headers as $i => $h ) {
			$pdf->faCell( $widths[ $i ], $rh, $h, 1, 0, 'C', true );
		}
		$pdf->Ln( $rh );
		$pdf->SetFont( 'vazir', '', 9 );
		foreach ( $rows as $i => $r ) {
			if ( $pdf->GetY() + $rh > $pdf->GetPageHeight() - 14 ) {
				$pdf->AddPage();
				$pdf->SetFont( 'vazir', 'B', 9 );
				foreach ( $headers as $j => $h ) {
					$pdf->faCell( $widths[ $j ], $rh, $h, 1, 0, 'C', true );
				}
				$pdf->Ln( $rh );
				$pdf->SetFont( 'vazir', '', 9 );
			}
			$pdf->faCell( $widths[0], $rh, tpp_format_number( $i + 1 ), 1, 0, 'C' );
			$pdf->faCell( $widths[1], $rh, $r['name'], 1, 0, 'R' );
			$pdf->faCell( $widths[2], $rh, $r['account'], 1, 0, 'C' );
			$pdf->faCell( $widths[3], $rh, $r['sheba'], 1, 0, 'C' );
			$pdf->faCell( $widths[4], $rh, tpp_format_number( $r['net'], false ), 1, 0, 'C' );
			$pdf->Ln( $rh );
		}
		// جمع کل.
		$pdf->SetFont( 'vazir', 'B', 9.5 );
		$pdf->SetFillColor( 242, 242, 242 );
		$pdf->faCell( array_sum( array_slice( $widths, 0, 4 ) ), $rh, 'جمع کل (' . $currency . ')', 1, 0, 'C', true );
		$pdf->faCell( $widths[4], $rh, tpp_format_number( array_sum( wp_list_pluck( $rows, 'net' ) ), false ), 1, 0, 'C', true );
		$pdf->Ln( $rh );

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="bank-slip.pdf"' );
		$pdf->Output( 'I', 'bank-slip.pdf' );
		exit;
	}

	/**
	 * صفحه فیش‌های حقوقی (تکی و عمده)
	 *
	 * @return void
	 */
	public static function render_payslips() {
		if ( ! tpp_can_manage() ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		list( $jyear, $jmonth, $center_id ) = array_values( self::period() );
		$records = $jyear ? tpp_get_period_records( $jyear, $jmonth, $center_id ) : array();
		self::period_form( 'tpp-payslips', 'فیش‌های حقوقی — تکی و عمده (ZIP)' );
		$zip = add_query_arg( self::period_query(), admin_url( 'admin-post.php?action=tpp_bulk_zip' ) );
		?>
		<p class="description">با مشخص کردن سال، ماه و مرکز می‌توانید فیش حقوقی همه کارکنان ثبت‌شده را به صورت فایل‌های PDF مجزا در قالب یک فایل ZIP (با درج لوگوی شرکت) دریافت کنید.</p>
		<p>
			<a class="button button-primary button-hero" href="<?php echo esc_url( $zip ); ?>">دریافت ZIP فیش‌های همه کارکنان (<?php echo TPP_Jalali::digits_fa( count( (array) $records ) ); ?> فیش)</a>
		</p>
		<?php if ( $records ) : ?>
			<table class="widefat striped" dir="rtl">
				<thead><tr><th>#</th><th>کارمند</th><th>مرکز</th><th>خالص پرداختی</th><th>فیش PDF</th></tr></thead>
				<tbody>
				<?php foreach ( $records as $i => $r ) :
					$u = get_userdata( $r->user_id );
					$c = tpp_get_center( (int) $r->center_id );
					$pdf = add_query_arg( array( 'action' => 'tpp_payslip_pdf', 'record_id' => $r->id, '_wpnonce' => wp_create_nonce( 'tpp_payslip_pdf' ) ), admin_url( 'admin-ajax.php' ) );
					?>
					<tr>
						<td><?php echo TPP_Jalali::digits_fa( $i + 1 ); ?></td>
						<td><?php echo esc_html( $u ? $u->display_name : '?' ); ?></td>
						<td><?php echo esc_html( $c ? $c->name : '?' ); ?></td>
						<td dir="ltr"><?php echo esc_html( tpp_format_number( (float) $r->net ) ); ?></td>
						<td><a class="button button-small" target="_blank" href="<?php echo esc_url( $pdf ); ?>">مشاهده / دانلود</a></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
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
		if ( ! tpp_can_manage() ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		list( $jyear, $jmonth, $center_id ) = array_values( self::period() );
		$records = tpp_get_period_records( $jyear, $jmonth, $center_id );
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
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp ); // phpcs:ignore
		unlink( $tmp );
		exit;
	}

	/**
	 * ساخت PDF فیش حقوقی یک رکورد
	 *
	 * @param object $record رکورد.
	 * @return string|WP_Error بایت‌های PDF.
	 */
	public static function build_payslip_pdf( $record ) {
		$user = get_userdata( $record->user_id );
		$center = tpp_get_center( (int) $record->center_id );
		$settings = tpp_get_settings();
		$payload = tpp_record_payload( $record );
		$profile = tpp_get_profile( $record->user_id );
		$logo    = tpp_logo_path();
		$fields  = tpp_get_fields();

		try {
			$pdf = new TPP_PDF( 'P', 'mm', 'A4' );
			$pdf->SetTitle( 'Payslip' );
			$pdf->AddPage();
			$pw = $pdf->GetPageWidth() - 20;
			$y  = 10;

			// سربرگ.
			if ( $logo ) {
				$pdf->Image( $logo, 10, $y, 24 );
			}
			$pdf->SetFont( 'vazir', 'B', 15 );
			$pdf->SetXY( 10, $y );
			$pdf->faCell( $pw, 9, $settings['company_name'] ? $settings['company_name'] : 'فیش حقوقی و دستمزد', 0, 1, 'C' );
			$pdf->SetFont( 'vazir', '', 11.5 );
			$pdf->SetXY( 10, $y + 9 );
			$pdf->faCell( $pw, 7.5, 'فیش حقوقی ' . TPP_Jalali::month_name( (int) $record->jmonth ) . ' ' . TPP_Jalali::digits_fa( (int) $record->jyear ) . ' — مرکز ' . ( $center ? $center->name : '' ), 0, 1, 'C' );
			$pdf->Line( 10, $y + 18, $pdf->GetPageWidth() - 10, $y + 18 );
			$pdf->SetY( $y + 21 );

			// اطلاعات کارمند.
			$national = get_user_meta( $record->user_id, 'tpp_national_id', true );
			$pdf->SetFont( 'vazir', '', 10.5 );
			$rh = 7.2;
			$pdf->SetFillColor( 240, 244, 251 );
			$pdf->faCell( 30, $rh, 'نام و نام خانوادگی', 1, 0, 'C', true );
			$pdf->faCell( 65, $rh, $user ? $user->display_name : '?', 1, 0, 'C' );
			$pdf->faCell( 30, $rh, 'کد ملی', 1, 0, 'C', true );
			$pdf->faCell( 65, $rh, $national, 1, 0, 'C' );
			$pdf->Ln( $rh );
			$job = isset( $profile['job_title'] ) ? $profile['job_title'] : '';
			$pdf->faCell( 30, $rh, 'عنوان شغلی', 1, 0, 'C', true );
			$pdf->faCell( 65, $rh, $job, 1, 0, 'C' );
			$pdf->faCell( 30, $rh, 'شماره پرسنلی', 1, 0, 'C', true );
			$pdf->faCell( 65, $rh, (int) $record->user_id, 1, 0, 'C' );
			$pdf->Ln( $rh );
			$pdf->Ln( 2 );

			// جدول جزئیات.
			$pdf->SetFillColor( 217, 226, 243 );
			$pdf->SetFont( 'vazir', 'B', 10 );
			$pdf->faCell( 80, $rh + 0.8, 'عنوان', 1, 0, 'C', true );
			$pdf->faCell( 80, $rh + 0.8, 'مقدار (' . $settings['currency'] . ')', 1, 0, 'C', true );
			$pdf->Ln( $rh + 0.8 );
			$pdf->SetFont( 'vazir', '', 9.5 );
			$i = 0;
			foreach ( $fields as $f ) {
				if ( ! $f->show_in_payslip ) {
					continue;
				}
				$val = isset( $payload[ $f->field_key ] ) ? $payload[ $f->field_key ] : '';
				if ( $pdf->GetY() + $rh > $pdf->GetPageHeight() - 26 ) {
					$pdf->AddPage();
					$pdf->SetFont( 'vazir', '', 9.5 );
				}
				$fill = ( 0 === $i % 2 );
				$pdf->SetFillColor( 246, 247, 250 );
				$pdf->faCell( 80, $rh, $f->label, 1, 0, 'R', $fill );
				if ( 'number' === $f->field_type ) {
					$pdf->faCell( 80, $rh, tpp_format_number( (float) $val, false ), 1, 0, 'C', $fill );
				} else {
					$pdf->faCell( 80, $rh, (string) $val, 1, 0, 'C', $fill );
				}
				$pdf->Ln( $rh );
				$i++;
			}

			// امضاها.
			$pdf->Ln( 6 );
			$pdf->SetFont( 'vazir', '', 10 );
			$pdf->faCell( 80, 7, 'امضای کارمند', 0, 0, 'C' );
			$pdf->faCell( 80, 7, 'امضای کارفرما', 0, 0, 'C' );
			$pdf->Ln( 7 );
			$pdf->Line( 30, $pdf->GetY(), 80, $pdf->GetY() );
			$pdf->Line( 110, $pdf->GetY(), 160, $pdf->GetY() );
			$pdf->SetY( $pdf->GetY() + 4 );
			$pdf->SetFont( 'vazir', '', 8 );
			$pdf->faCell( $pw, 5, 'تاریخ چاپ: ' . tpp_today_fa(), 0, 1, 'C' );

			return $pdf->Output( 'S' );
		} catch ( Exception $e ) {
			return new WP_Error( 'tpp_pdf_error', $e->getMessage() );
		}
	}

	/**
	 * دانلود فیش PDF (AJAX)
	 *
	 * @return void
	 */
	public static function backup_download() {
		check_admin_referer( 'tpp_backup_download' );
		if ( ! tpp_can_manage() ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		$file = sanitize_text_field( wp_unslash( (isset($_GET['file'] )?$_GET['file'] : '' )) );
		$dir  = tpp_backup_dir();
		$path = realpath( $dir . '/' . $file );
		if ( ! $path || 0 !== strpos( $path, realpath( $dir ) ) || ! file_exists( $path ) ) {
			wp_die( 'فایل یافت نشد' );
		}
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
		if ( ! tpp_can_manage() ) {
			wp_die( 'دسترسی غیرمجاز' );
		}
		global $wpdb;
		$logs = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}tpp_backups ORDER BY created_at DESC LIMIT 50" ); // phpcs:ignore
		$settings = tpp_get_settings();
		?>
		<div class="wrap tpp-wrap" dir="rtl">
			<h1>پشتیبان‌گیری و بازگردانی</h1>
			<p class="description">بکاپ خودکار: <?php
			$types = array();
			if ( ! empty( $settings['backup']['daily'] ) ) { $types[] = 'روزانه'; }
			if ( ! empty( $settings['backup']['weekly'] ) ) { $types[] = 'هفتگی'; }
			if ( ! empty( $settings['backup']['monthly'] ) ) { $types[] = 'ماهانه'; }
			if ( ! empty( $settings['backup']['yearly'] ) ) { $types[] = 'سالانه'; }
			echo $types ? esc_html( implode( '، ', $types ) ) : 'غیرفعال';
			?></p>

			<h2>ایجاد بکاپ دستی</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:12px;align-items:center">
				<?php wp_nonce_field( 'tpp_backup_create' ); ?>
				<input type="hidden" name="action" value="tpp_backup_create">
				<select name="backup_type">
					<option value="json">JSON (کامل)</option>
					<option value="excel">اکسل</option>
					<option value="zip">ZIP (کامل + فایل‌ها)</option>
				</select>
				<button class="button button-primary">ایجاد بکاپ</button>
			</form>

			<h2>بازگردانی از فایل JSON</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" onsubmit="return confirm('تمام داده‌های فعلی حقوق و دستمزد با محتوای فایل بکاپ جایگزین می‌شود. مطمئن هستید؟')">
				<?php wp_nonce_field( 'tpp_backup_restore' ); ?>
				<input type="hidden" name="action" value="tpp_backup_restore">
				<p><input type="file" name="restore_file" accept=".json" required></p>
				<button class="button">بازگردانی</button>
			</form>

			<h2>آخرین بکاپ‌ها</h2>
			<table class="widefat striped">
				<thead><tr><th>زمان</th><th>نوع</th><th>منشأ</th><th>حجم</th><th>دانلود</th></tr></thead>
				<tbody>
				<?php if ( empty( $logs ) ) : ?>
					<tr><td colspan="5">بکاپی ثبت نشده است.</td></tr>
				<?php endif; ?>
				<?php foreach ( $logs as $log ) :
					$label = array( 'json' => 'JSON', 'excel' => 'اکسل', 'zip' => 'ZIP' );
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
								$du = wp_nonce_url( add_query_arg( array( 'action' => 'tpp_backup_download', 'file' => basename( $log->file_path ) ), admin_url( 'admin-post.php' ) ), 'tpp_backup_download' );
								echo '<a class="button button-small" href="' . esc_url( $du ) . '">دانلود</a>';
							} else {
								echo '<span class="description">فایل موجود نیست</span>';
							}
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
