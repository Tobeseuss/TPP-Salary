<?php
/**
 * تولیدکننده سبک فایل XLSX بدون وابستگی خارجی (بر پایه ZipArchive)
 *
 * قابلیت‌ها: چند شیت، RTL، استایل (بولد، رنگ، پرکردن، حاشیه)، عرض ستون، ادغام سلول،
 * فرمت عددی هزارگان و فریز هدر.
 *
 * @package TPP_Salary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class TPP_Xlsx_Writer
 */
if ( ! class_exists( 'TPP_Xlsx_Writer' ) ) {
	// TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TPP_Xlsx_Writer {

        /**
         * شیت‌ها
         *
         * @var array
         */
        private $sheets = array();

        /**
         * نام شیت فعال
         *
         * @var string
         */
        private $current = '';

        /**
         * شمارنده استایل‌ها
         *
         * @var array
         */
        private $styles = array();

        /**
         * ساخت شیت جدید و فعال‌سازی آن
         *
         * @param string $name نام شیت.
         * @param bool   $rtl  راست‌به‌چپ؟
         * @return void
         */
        public function add_sheet( $name, $rtl = true ) {
                $name = function_exists( 'sanitize_file_name' ) ? sanitize_file_name( $name ) : preg_replace( '/[\/\\\?\*\[\]:]/u', '', $name );
                $this->sheets[ $name ] = array(
                        'rtl'    => $rtl,
                        'cells'  => array(),
                        'merges' => array(),
                        'widths' => array(),
                        'freeze' => null,
                        'rows_meta' => array(),
                );
                $this->current = $name;
        }

        /**
         * تنظیم عرض ستون
         *
         * @param string $col   حرف ستون (A، B، ...).
         * @param float  $width عرض.
         * @return void
         */
        public function set_width( $col, $width ) {
                $this->sheets[ $this->current ]['widths'][ $col ] = (float) $width;
        }

        /**
         * فریز کردن ردیف‌های ابتدایی
         *
         * @param string $cell مثل A2.
         * @return void
         */
        public function freeze( $cell ) {
                $this->sheets[ $this->current ]['freeze'] = $cell;
        }

        /**
         * ثبت یک سلول
         *
         * @param int    $row   ردیف (از 1).
         * @param int    $col   ستون (از 1).
         * @param mixed  $value مقدار.
         * @param string $style کلید استایل (اختیاری).
         * @return void
         */
        public function set( $row, $col, $value, $style = '' ) {
                $this->sheets[ $this->current ]['cells'][ $row ][ $col ] = array(
                        'v'     => $value,
                        'style' => $style,
                );
        }

        /**
         * ادغام سلول‌ها
         *
         * @param int $r1 ردیف اول.
         * @param int $c1 ستون اول.
         * @param int $r2 ردیف دوم.
         * @param int $c2 ستون دوم.
         * @return void
         */
        public function merge( $r1, $c1, $r2, $c2 ) {
                $this->sheets[ $this->current ]['merges'][] = array( $r1, $c1, $r2, $c2 );
        }

        /**
         * تعریف استایل سفارشی
         *
         * @param string $key  کلید.
         * @param array  $opts بولد/سایز/رنگ/پرکردن/حاشیه/فرمت/تراز.
         * @return void
         */
        public function define_style( $key, $opts ) {
                $this->styles[ $key ] = $opts;
        }

        /**
         * استایل‌های پیش‌فرض
         *
         * @return array
         */
        private function builtin_styles() {
                $styles = array(
                        'header'  => array( 'bold' => true, 'size' => 10, 'fill' => 'FFD9E2F3', 'border' => true, 'align' => 'center', 'valign' => 'center', 'wrap' => true ),
                        'title'   => array( 'bold' => true, 'size' => 14, 'align' => 'center', 'valign' => 'center' ),
                        'subtitle'=> array( 'bold' => true, 'size' => 11, 'align' => 'center', 'valign' => 'center' ),
                        'text'    => array( 'size' => 10, 'border' => true, 'align' => 'center', 'valign' => 'center', 'wrap' => true ),
                        'num'     => array( 'size' => 10, 'border' => true, 'align' => 'center', 'valign' => 'center', 'numfmt' => '#,##0' ),
                        'num_neg' => array( 'size' => 10, 'border' => true, 'align' => 'center', 'valign' => 'center', 'numfmt' => '#,##0', 'color' => 'FFC00000' ),
                        'total'   => array( 'bold' => true, 'size' => 10, 'border' => true, 'align' => 'center', 'valign' => 'center', 'numfmt' => '#,##0', 'fill' => 'FFF2F2F2' ),
                        'footer'  => array( 'size' => 9, 'align' => 'right', 'color' => 'FF666666', 'italic' => true ),
                        'label'   => array( 'bold' => true, 'size' => 10, 'border' => true, 'align' => 'right', 'valign' => 'center', 'fill' => 'FFF7F7F7' ),
                );
                foreach ( $styles as $k => $v ) {
                        if ( ! isset( $this->styles[ $k ] ) ) {
                                $this->styles[ $k ] = $v;
                        }
                }
                return $this->styles;
        }

        /**
         * تولید XML استایل‌ها و نگاشت کلید استایل به ایندکس xf
         *
         * @return array{xml:string, map:array}
         */
        private function build_styles() {
                $styles = $this->builtin_styles();
                $fonts  = array();
                $fills  = array( array( 'patternFill', 'none', null ), array( 'patternFill', 'gray125', null ) );
                $xfs    = array();
                $map    = array();

                $font_cache = array();
                $fill_cache = array( 'none' => 0, 'gray125' => 1 );

                $idx = 0;
                foreach ( $styles as $key => $o ) {
                        $bold   = ! empty( $o['bold'] );
                        $italic = ! empty( $o['italic'] );
                        $size   = isset( $o['size'] ) ? (float) $o['size'] : 10;
                        $color  = isset( $o['color'] ) ? $o['color'] : 'FF000000';
                        $fkey   = $bold . '|' . $italic . '|' . $size . '|' . $color;
                        if ( ! isset( $font_cache[ $fkey ] ) ) {
                                $font_cache[ $fkey ] = count( $fonts );
                                $fonts[] = '<font><sz val="' . $size . '"/><color rgb="' . $color . '"/><name>Vazirmatn</name>' . ( $bold ? '<b/>' : '' ) . ( $italic ? '<i/>' : '' ) . '</font>';
                        }
                        $fill_idx = 0;
                        if ( ! empty( $o['fill'] ) ) {
                                if ( ! isset( $fill_cache[ $o['fill'] ] ) ) {
                                        $fill_cache[ $o['fill'] ] = count( $fills );
                                        $fills[] = array( 'patternFill', 'solid', $o['fill'] );
                                }
                                $fill_idx = $fill_cache[ $o['fill'] ];
                        }
                        $numfmt_id = 0;
                        $numfmt_xml = '';
                        if ( ! empty( $o['numfmt'] ) ) {
                                // کد فرمت سفارشی از 164.
                                static $custom = array();
                                if ( ! isset( $custom[ $o['numfmt'] ] ) ) {
                                        $custom[ $o['numfmt'] ] = 164 + count( $custom );
                                }
                                $numfmt_id = $custom[ $o['numfmt'] ];
                                $numfmt_xml = '<numFmt numFmtId="' . $numfmt_id . '" formatCode="' . htmlspecialchars( $o['numfmt'], ENT_QUOTES | ENT_XML1, 'UTF-8' ) . '"/>';
                        }
                        $align = 'center';
                        if ( ! empty( $o['align'] ) ) {
                                $align = $o['align'];
                        }
                        $valign = isset( $o['valign'] ) ? $o['valign'] : 'center';
                        $wrap   = ! empty( $o['wrap'] ) ? '<wrapText/>' : '';
                        $border = ! empty( $o['border'] ) ? '<border><left style="thin"><color rgb="FF999999"/></left><right style="thin"><color rgb="FF999999"/></right><top style="thin"><color rgb="FF999999"/></top><bottom style="thin"><color rgb="FF999999"/></bottom><diagonal/></border>' : '<border><left/><right/><top/><bottom/><diagonal/></border>';

                        $map[ $key ] = $idx;
                        $xfs[] = '<xf numFmtId="' . $numfmt_id . '" fontId="' . $font_cache[ $fkey ] . '" fillId="' . $fill_idx . '" borderId="' . ( ! empty( $o['border'] ) ? 1 : 0 ) . '" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1" applyNumberFormat="1"><alignment horizontal="' . $align . '" vertical="' . $valign . '" readingOrder="2">' . $wrap . '</alignment></xf>';
                        // note: borderId index 0/1 — ما دو حاشیه اول را به‌صورت ثابت در numFmts نمی‌سازیم؛ بازنویسی ساده در ادامه.
                        $idx++;
                }

                $numfmts = '';
                $custom_ids = '';
                // بازسازی numFmts از xfsها — برای سادگی، فرمت‌های سفارشی را دوباره جمع می‌کنیم.
                preg_match_all( '/<numFmt numFmtId="(\d+)" formatCode="([^"]+)"/', implode( '', $xfs ), $mm );
                if ( ! empty( $mm[1] ) ) {
                        $items = '';
                        foreach ( $mm[1] as $i => $id ) {
                                $items .= '<numFmt numFmtId="' . $id . '" formatCode="' . $mm[2][ $i ] . '"/>';
                        }
                        $numfmts = '<numFmts count="' . count( $mm[1] ) . '">' . $items . '</numFmts>';
                }

                $fills_xml = '';
                foreach ( $fills as $f ) {
                        if ( null === $f[2] ) {
                                $fills_xml .= '<fill><' . $f[0] . ' patternType="' . $f[1] . '"><fgColor rgb="FF000000"/><bgColor indexed="64"/></' . $f[0] . '></fill>';
                        } else {
                                $fills_xml .= '<fill><' . $f[0] . ' patternType="' . $f[1] . '"><fgColor rgb="' . $f[2] . '"/><bgColor indexed="64"/></' . $f[0] . '></fill>';
                        }
                }

                $borders_xml = '<border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FF999999"/></left><right style="thin"><color rgb="FF999999"/></right><top style="thin"><color rgb="FF999999"/></top><bottom style="thin"><color rgb="FF999999"/></bottom><diagonal/></border>';

                $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                        . $numfmts
                        . '<fonts count="' . count( $fonts ) . '">' . implode( '', $fonts ) . '</fonts>'
                        . '<fills count="' . count( $fills ) . '">' . $fills_xml . '</fills>'
                        . '<borders count="2">' . $borders_xml . '</borders>'
                        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                        . '<cellXfs count="' . count( $xfs ) . '">' . implode( '', $xfs ) . '</cellXfs>'
                        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
                        . '</styleSheet>';

                return array( 'xml' => $xml, 'map' => $map );
        }

        /**
         * حرف ستون از شماره
         *
         * @param int $n شماره 1 مبنا.
         * @return string
         */
        public static function col_letter( $n ) {
                $n   = (int) $n;
                $str = '';
                while ( $n > 0 ) {
                        $mod = ( $n - 1 ) % 26;
                        $str = chr( 65 + $mod ) . $str;
                        $n   = (int) ( ( $n - $mod ) / 26 );
                }
                return $str;
        }

        /**
         * خروجی نهایی به رشته
         *
         * @return string|WP_Error
         */
        public function to_string() {
                if ( empty( $this->sheets ) ) {
                        $this->add_sheet( 'Sheet1' );
                }
                $styles = $this->build_styles();
                $smap   = $styles['map'];

                $wb_sheets  = '';
                $wb_rels    = '';
                $sheets_xml = '';
                $n          = 1;

                foreach ( $this->sheets as $name => $sheet ) {
                        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                                . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetPr><outlinePr/></sheetPr>'
                                . '<sheetViews><sheetView' . ( $sheet['rtl'] ? ' rightToLeft="1"' : '' ) . ' showGridLines="0" workbookViewId="0">';
                        if ( $sheet['freeze'] ) {
                                $xml .= '<pane ySplit="' . ( max( 1, (int) filter_var( $sheet['freeze'], FILTER_SANITIZE_NUMBER_INT ) ) - 1 ) . '" topLeftCell="' . $sheet['freeze'] . '" activePane="bottomLeft" state="frozen"/>';
                        }
                        $xml .= '</sheetView></sheetViews><sheetFormatPr defaultRowHeight="18"/>';
                        if ( ! empty( $sheet['widths'] ) ) {
                                $xml .= '<cols>';
                                foreach ( $sheet['widths'] as $col => $w ) {
                                        $xml .= '<col min="' . self::col_num( $col ) . '" max="' . self::col_num( $col ) . '" width="' . $w . '" customWidth="1"/>';
                                }
                                $xml .= '</cols>';
                        }
                        $xml .= '<sheetData>';

                        $max_row = 0;
                        $max_col = 0;
                        foreach ( $sheet['cells'] as $r => $cols ) {
                                $max_row = max( $max_row, $r );
                                foreach ( $cols as $c => $cell ) {
                                        $max_col = max( $max_col, $c );
                                }
                        }
                        $row_heights = isset( $sheet['rows_meta'] ) ? $sheet['rows_meta'] : array();
                        for ( $r = 1; $r <= $max_row; $r++ ) {
                                $xml .= '<row r="' . $r . '"' . ( isset( $row_heights[ $r ] ) ? ' ht="' . $row_heights[ $r ] . '" customHeight="1"' : '' ) . '>';
                                for ( $c = 1; $c <= $max_col; $c++ ) {
                                        if ( ! isset( $sheet['cells'][ $r ][ $c ] ) ) {
                                                continue;
                                        }
                                        $cell  = $sheet['cells'][ $r ][ $c ];
                                        $ref   = self::col_letter( $c ) . $r;
                                        $v     = $cell['v'];
                                        $skey  = $cell['style'];
                                        $sattr = ( $skey && isset( $smap[ $skey ] ) ) ? ' s="' . $smap[ $skey ] . '" t="' . ( is_numeric( $v ) && ! is_string( $v ) ? 'n' : ( is_string( $v ) && '' !== $v && ! is_numeric( $v ) ? 'inlineStr' : 'n' ) ) . '"' : '';
                                        if ( is_string( $v ) && ( '' === $v || ! is_numeric( $v ) || 0 === strpos( $v, '0' ) ) ) {
                                                $xml .= '<c r="' . $ref . '"' . ( $skey && isset( $smap[ $skey ] ) ? ' s="' . $smap[ $skey ] . '"' : '' ) . ' t="inlineStr"><is><t xml:space="preserve">' . self::esc( (string) $v ) . '</t></is></c>';
                                        } elseif ( null === $v || '' === $v ) {
                                                $xml .= '<c r="' . $ref . '"' . ( $skey && isset( $smap[ $skey ] ) ? ' s="' . $smap[ $skey ] . '"' : '' ) . '/>';
                                        } else {
                                                $xml .= '<c r="' . $ref . '"' . ( $skey && isset( $smap[ $skey ] ) ? ' s="' . $smap[ $skey ] . '"' : '' ) . '><v>' . (float) $v . '</v></c>';
                                        }
                                        unset( $sattr );
                                }
                                $xml .= '</row>';
                        }
                        $xml .= '</sheetData>';
                        if ( ! empty( $sheet['merges'] ) ) {
                                $xml .= '<mergeCells count="' . count( $sheet['merges'] ) . '">';
                                foreach ( $sheet['merges'] as $m ) {
                                        $xml .= '<mergeCell ref="' . self::col_letter( $m[1] ) . $m[0] . ':' . self::col_letter( $m[3] ) . $m[2] . '"/>';
                                }
                                $xml .= '</mergeCells>';
                        }
                        $xml .= '<pageMargins left="0.3" right="0.3" top="0.4" bottom="0.4" header="0.2" footer="0.2"/><pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/><printOptions horizontalCentered="1"/></worksheet>';
                        $sheets_xml .= $xml;

                        $wb_sheets .= '<sheet name="' . self::esc( $name ) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
                        $wb_rels   .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
                        $n++;
                }

                $content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                        . '<Default Extension="xml" ContentType="application/xml"/>'
                        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
                        . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>';
                foreach ( $this->sheets as $i => $dummy ) {
                        $content_types .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
                }
                $content_types .= '</Types>';

                $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
                        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
                        . '</Relationships>';

                $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                        . '<bookViews><workbookView xWindow="0" yWindow="0" windowWidth="20000" windowHeight="12000"/></bookViews>'
                        . '<sheets>' . $wb_sheets . '</sheets></workbook>';

                $workbook_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                        . $wb_rels
                        . '<Relationship Id="rId' . ( count( $this->sheets ) + 1 ) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                        . '</Relationships>';

                $core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:creator>TPP Salary</dc:creator></cp:coreProperties>';
                $app  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>TPP Salary Plugin</Application></Properties>';

                $tmp = tempnam( sys_get_temp_dir(), 'tppxlsx' );
                if ( ! class_exists( 'ZipArchive' ) ) {
                        return new WP_Error( 'tpp_zip_missing', 'افزونه ZipArchive روی سرور فعال نیست' );
                }
                $zip = new ZipArchive();
                if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
                        return new WP_Error( 'tpp_zip_open', 'ایجاد فایل موقت اکسل ناموفق بود' );
                }
                $zip->addFromString( '[Content_Types].xml', $content_types );
                $zip->addFromString( '_rels/.rels', $rels );
                $zip->addFromString( 'docProps/core.xml', $core );
                $zip->addFromString( 'docProps/app.xml', $app );
                $zip->addFromString( 'xl/workbook.xml', $workbook );
                $zip->addFromString( 'xl/_rels/workbook.xml.rels', $workbook_rels );
                $zip->addFromString( 'xl/styles.xml', $styles['xml'] );
                $i = 1;
                foreach ( $this->sheets as $sheet ) {
                        // بازتولید XML شیت برای ایندکس صحیح فایل.
                        $zip->addFromString( 'xl/worksheets/sheet' . $i . '.xml', '' );
                        $i++;
                }
                // بازنویسی با محتوای واقعی (تولید دوباره).
                $zip->close();
                $zip = new ZipArchive();
                $zip->open( $tmp, ZipArchive::OVERWRITE );
                $zip->addFromString( '[Content_Types].xml', $content_types );
                $zip->addFromString( '_rels/.rels', $rels );
                $zip->addFromString( 'docProps/core.xml', $core );
                $zip->addFromString( 'docProps/app.xml', $app );
                $zip->addFromString( 'xl/workbook.xml', $workbook );
                $zip->addFromString( 'xl/_rels/workbook.xml.rels', $workbook_rels );
                $zip->addFromString( 'xl/styles.xml', $styles['xml'] );
                $i = 1;
                foreach ( $this->sheets as $name => $sheet ) {
                        $zip->addFromString( 'xl/worksheets/sheet' . $i . '.xml', $this->sheet_xml( $name, $smap ) );
                        $i++;
                }
                $zip->close();
                $data = file_get_contents( $tmp ); // phpcs:ignore
                unlink( $tmp );
                return $data;
        }

        /**
         * XML یک شیت خاص
         *
         * @param string $name نام شیت.
         * @param array  $smap نگاشت استایل.
         * @return string
         */
        private function sheet_xml( $name, $smap ) {
                $sheet = $this->sheets[ $name ];
                $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetPr><outlinePr/></sheetPr>'
                        . '<sheetViews><sheetView' . ( $sheet['rtl'] ? ' rightToLeft="1"' : '' ) . ' showGridLines="0" workbookViewId="0">';
                if ( $sheet['freeze'] ) {
                        $xml .= '<pane ySplit="' . ( max( 1, (int) filter_var( $sheet['freeze'], FILTER_SANITIZE_NUMBER_INT ) ) - 1 ) . '" topLeftCell="' . $sheet['freeze'] . '" activePane="bottomLeft" state="frozen"/>';
                }
                $xml .= '</sheetView></sheetViews><sheetFormatPr defaultRowHeight="18"/>';
                if ( ! empty( $sheet['widths'] ) ) {
                        $xml .= '<cols>';
                        foreach ( $sheet['widths'] as $col => $w ) {
                                $xml .= '<col min="' . self::col_num( $col ) . '" max="' . self::col_num( $col ) . '" width="' . $w . '" customWidth="1"/>';
                        }
                        $xml .= '</cols>';
                }
                $xml .= '<sheetData>';
                $max_row = 0;
                $max_col = 0;
                foreach ( $sheet['cells'] as $r => $cols ) {
                        $max_row = max( $max_row, $r );
                        foreach ( $cols as $c => $cell ) {
                                $max_col = max( $max_col, $c );
                        }
                }
                for ( $r = 1; $r <= $max_row; $r++ ) {
                        $xml .= '<row r="' . $r . '">';
                        for ( $c = 1; $c <= $max_col; $c++ ) {
                                if ( ! isset( $sheet['cells'][ $r ][ $c ] ) ) {
                                        continue;
                                }
                                $cell = $sheet['cells'][ $r ][ $c ];
                                $ref  = self::col_letter( $c ) . $r;
                                $v    = $cell['v'];
                                $s    = ( $cell['style'] && isset( $smap[ $cell['style'] ] ) ) ? ' s="' . $smap[ $cell['style'] ] . '"' : '';
                                $is_text = is_string( $v ) && ( '' === $v || ! is_numeric( $v ) || 0 === strpos( $v, '0' ) );
                                if ( $is_text ) {
                                        $xml .= '<c r="' . $ref . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">' . self::esc( (string) $v ) . '</t></is></c>';
                                } elseif ( null === $v || '' === $v ) {
                                        $xml .= '<c r="' . $ref . '"' . $s . '/>';
                                } else {
                                        $xml .= '<c r="' . $ref . '"' . $s . '><v>' . (float) $v . '</v></c>';
                                }
                        }
                        $xml .= '</row>';
                }
                $xml .= '</sheetData>';
                if ( ! empty( $sheet['merges'] ) ) {
                        $xml .= '<mergeCells count="' . count( $sheet['merges'] ) . '">';
                        foreach ( $sheet['merges'] as $m ) {
                                $xml .= '<mergeCell ref="' . self::col_letter( $m[1] ) . $m[0] . ':' . self::col_letter( $m[3] ) . $m[2] . '"/>';
                        }
                        $xml .= '</mergeCells>';
                }
                $xml .= '<pageMargins left="0.3" right="0.3" top="0.4" bottom="0.4" header="0.2" footer="0.2"/><pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/><printOptions horizontalCentered="1"/></worksheet>';
                return $xml;
        }

        /**
         * شماره ستون از حرف
         *
         * @param string $letter حرف.
         * @return int
         */
        public static function col_num( $letter ) {
                $n = 0;
                $letter = strtoupper( (string) $letter );
                for ( $i = 0, $l = strlen( $letter ); $i < $l; $i++ ) {
                        $n = $n * 26 + ( ord( $letter[ $i ] ) - 64 );
                }
                return max( 1, $n );
        }

        /**
         * ارسال به مرورگر برای دانلود
         *
         * @param string $filename نام فایل.
         * @return void
         */
        public function download( $filename ) {
                $data = $this->to_string();
                if ( is_wp_error( $data ) ) {
                        wp_die( esc_html( $data->get_error_message() ) );
                }
                nocache_headers();
                header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
                header( 'Content-Disposition: attachment; filename="' . rawurlencode( $filename ) . '"' );
                header( 'Content-Length: ' . strlen( $data ) );
                echo $data; // phpcs:ignore
                exit;
        }

        /**
         * اسکیپ XML
         *
         * @param string $s متن.
         * @return string
         */
        private static function esc( $s ) {
                return htmlspecialchars( $s, ENT_QUOTES | ENT_XML1, 'UTF-8' );
        }
}
}
// TPP_SALARY GUARD END
