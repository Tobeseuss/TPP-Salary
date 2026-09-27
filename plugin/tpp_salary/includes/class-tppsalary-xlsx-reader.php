<?php
/**
 * خواننده سبک XLSX بدون وابستگی (بر پایه ZipArchive + SimpleXML)
 *
 * از شیت اول فایل، سطرها را به صورت آرایه برمی‌گرداند.
 *
 * @package TppSalary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TppSalary_Xlsx_Reader
 */
if ( ! class_exists( 'TppSalary_Xlsx_Reader' ) ) {
	// TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TppSalary_Xlsx_Reader {

	/**
	 * خواندن سطرهای شیت اول
	 *
	 * @param string $path مسیر فایل.
	 * @return array|WP_Error آرایه سطرها (هر سطر آرایه سلول‌ها) یا خطا.
	 */
	public static function read( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'tpp_salary_zip_missing', 'افزونه ZipArchive روی سرور فعال نیست' );
		}
		$zip = new ZipArchive();
		$res = $zip->open( $path );
		if ( true !== $res ) {
			return new WP_Error( 'tpp_salary_zip_open', 'فایل اکسل قابل بازکردن نیست' );
		}

		// sharedStrings.
		$shared = array();
		$ss_xml = $zip->getFromName( 'xl/sharedStrings.xml' );
		if ( false !== $ss_xml && '' !== $ss_xml ) {
			$ss = simplexml_load_string( $ss_xml );
			if ( $ss ) {
				foreach ( $ss->si as $si ) {
					$text = '';
					if ( isset( $si->t ) ) {
						$text = (string) $si->t;
					} else {
						foreach ( $si->r as $run ) {
							$text .= isset( $run->t ) ? (string) $run->t : '';
						}
					}
					$shared[] = $text;
				}
			}
		}

		// یافتن شیت اول.
		$sheet_xml = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
		if ( false === $sheet_xml || '' === $sheet_xml ) {
			// جستجوی اولین فایل شیت.
			for ( $i = 0; $i < $zip->numFiles; $i++ ) {
				$name = (string) $zip->getNameIndex( $i );
				if ( preg_match( '#xl/worksheets/sheet\d+\.xml$#', $name ) ) {
					$sheet_xml = $zip->getFromName( $name );
					break;
				}
			}
		}
		$zip->close();

		if ( false === $sheet_xml || '' === $sheet_xml ) {
			return new WP_Error( 'tpp_salary_no_sheet', 'هیچ شیتی در فایل اکسل یافت نشد' );
		}

		$sheet = simplexml_load_string( $sheet_xml );
		if ( ! $sheet ) {
			return new WP_Error( 'tpp_salary_bad_sheet', 'ساختار فایل اکسل نامعتبر است' );
		}

		$rows = array();
		if ( ! isset( $sheet->sheetData->row ) ) {
			return $rows;
		}
		foreach ( $sheet->sheetData->row as $row ) {
			$r = array();
			foreach ( $row->c as $cell ) {
				$ref = (string) $cell['r'];
				preg_match( '/^([A-Z]+)/', $ref, $mm );
				$col = TppSalary_Xlsx_Writer::col_num( (isset($mm[1] )?$mm[1] : 'A' )) - 1;
				$type = (string) $cell['t'];
				$v    = '';
				if ( 'inlineStr' === $type && isset( $cell->is->t ) ) {
					$v = (string) $cell->is->t;
				} elseif ( 's' === $type && isset( $cell->v ) ) {
					$idx = (int) $cell->v;
					$v   = isset( $shared[ $idx ] ) ? $shared[ $idx ] : '';
				} elseif ( isset( $cell->v ) ) {
					$v = (string) $cell->v;
				}
				$r[ $col ] = trim( $v );
			}
			if ( $r ) {
				$max   = max( array_keys( $r ) );
				$full  = array();
				for ( $i = 0; $i <= $max; $i++ ) {
					$full[] = isset( $r[ $i ] ) ? $r[ $i ] : '';
				}
				$rows[] = $full;
			}
		}
		return $rows;
	}
}
}
// TPP_SALARY GUARD END
