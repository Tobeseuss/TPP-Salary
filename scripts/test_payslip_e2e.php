<?php
/**
 * تست E2E رندر فیش حقوقی و گزارش — با استاب وردپرس
 */

define( 'ABSPATH', '/tmp/' );
define( 'TPP_SALARY_DIR', '/home/z/my-project/build/tpp_salary/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['__options'] = array( 'tpp_salary_settings' => array( 'company_name' => 'شرکت عمران TPP', 'currency' => 'ریال', 'digits_fa' => 1, 'per_page_a4' => 4, 'logo_id' => 0, 'defaults' => array(), 'formulas' => array(
        'gross' => '{base_salary}+{housing}+{food}+{seniority}+{marriage}+{child_allowance}+{commute}+{overtime_pay}+{holiday_pay}+{absence_penalty}+{work_deduction}+{other}',
        'insurable' => '{base_salary}+{housing}+{food}+{seniority}+{marriage}+{overtime_pay}+{holiday_pay}+{absence_penalty}+{work_deduction}',
        'net' => '{gross}+{insurance_deduct}+{other_deductions}',
), 'backup' => array( 'daily' => 0, 'weekly' => 0, 'monthly' => 1, 'yearly' => 1, 'retention' => 24 ) ) );

function get_option( $k, $d = false ) { return $GLOBALS['__options'][ $k ] ?? $d; }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, (array) $args ); }
function current_time( $type ) { return time(); }
function current_time_mysql() { return date( 'Y-m-d H:i:s' ); }
function date_i18n( $f, $ts = null ) { return date( $f, $ts ?: time() ); }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $k ) ); }
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', (string) $s ) ); }
function wp_die( $m = '' ) { throw new Exception( 'WP_DIE: ' . $m ); }
function nocache_headers() {}
function size_format( $b ) { return round( $b / 1024, 1 ) . ' KB'; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_textarea( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return $s; }
function admin_url( $p = '' ) { return 'http://x/wp-admin/' . $p; }
function wp_create_nonce( $a ) { return 'n'; }
function add_query_arg( $a, $u = '' ) { return $u; }
function selected( $a, $b, $echo = true ) { return $a === $b ? 'checked' : ''; }
function checked( $a, $b = true, $echo = true ) { return $a === $b ? 'checked' : ''; }
class WP_Error { private $m; public function __construct( $c = '', $m = '' ) { $this->m = $m; } public function get_error_message() { return $this->m; } }
function is_wp_error( $x ) { return $x instanceof WP_Error; }

// کاربران و مرکز شبیه‌سازی‌شده.
$GLOBALS['__users'] = array(
        1 => (object) array( 'ID' => 1, 'display_name' => 'اسماعیل کمال آبادی', 'user_login' => '1234567890' ),
        2 => (object) array( 'ID' => 2, 'display_name' => 'محمد غلامی', 'user_login' => '2345678901' ),
        3 => (object) array( 'ID' => 3, 'display_name' => 'حسن چراغچی', 'user_login' => '3456789012' ),
        4 => (object) array( 'ID' => 4, 'display_name' => 'محمدرضا ریاحی', 'user_login' => '4567890123' ),
);
$GLOBALS['__profiles'] = array(
        1 => array( 'job_title' => 'کارگر ساختمانی', 'insurable_default' => 270697756, 'daily_wage' => 5593757, 'children_count' => 0, 'bank_accounts' => array( 1 => array( 'account' => '1234567890', 'sheba' => 'IR120570028110012345678901', 'card' => '6037-9971-1234-5678' ) ) ),
        2 => array( 'job_title' => 'عملیات', 'insurable_default' => 270697756, 'daily_wage' => 5593757, 'children_count' => 2, 'bank_accounts' => array( 1 => array( 'account' => '2345678901', 'sheba' => 'IR220570028110023456789012', 'card' => '' ) ) ),
        3 => array( 'job_title' => 'انباردار', 'insurable_default' => 270697756, 'daily_wage' => 5593757, 'children_count' => 1, 'bank_accounts' => array() ),
        4 => array( 'job_title' => 'سرپرست کارگاه', 'insurable_default' => 270697756, 'daily_wage' => 5593757, 'children_count' => 2, 'bank_accounts' => array( 1 => array( 'account' => '4567890123', 'sheba' => '', 'card' => '' ) ) ),
);
function get_userdata( $id ) { return $GLOBALS['__users'][ $id ] ?? null; }
function get_user_meta( $id, $k, $s = false ) { return '1234567890'; }
function tpp_salary_get_profile( $id ) { return $GLOBALS['__profiles'][ $id ] ?? array(); }
function tpp_salary_get_center( $id ) { return (object) array( 'id' => 1, 'name' => 'بومهن' ); }
function tpp_salary_get_banks() { return array( (object) array( 'id' => 1, 'name' => 'ملت' ) ); }
function tpp_salary_logo_path() { return null; }
function tpp_salary_get_setting( $k, $d = '' ) { return ( $GLOBALS['__options']['tpp_salary_settings'][ $k ] ?? $d ); }
function tpp_salary_get_settings() { return $GLOBALS['__options']['tpp_salary_settings']; }

require TPP_SALARY_DIR . 'includes/class-tppsalary-jalali.php';
require TPP_SALARY_DIR . 'includes/class-tppsalary-pdf.php';
function tpp_salary_record_payload( $record ) {
        $payload = json_decode( (string) ( $record ? $record->payload : '' ), true );
        return is_array( $payload ) ? $payload : array();
}
function tpp_salary_format_number( $n, $fa = true, $dec = 0 ) {
        /* نسخه 1.7.3: همیشه ارقام انگلیسی (هم‌سان helpers.php) */
        return number_format( (float) $n, $dec, '.', ',' );
}

// فیلدهای فعال (شبیه دیتابیس seed شده).
function tpp_salary_get_fields() {
        $mk = function ( $key, $label, $type = 'number', $calc = 0 ) {
                return (object) array(
                        'field_key' => $key, 'label' => $label, 'field_type' => $type,
                        'show_in_payslip' => 1, 'is_negative' => in_array( $key, array( 'absence_penalty', 'work_deduction', 'other_deductions' ), true ) ? 1 : 0,
                        'is_calculated' => $calc, 'formula' => '',
                );
        };
        return array(
                $mk( 'insurance_group', 'گروه اصلی بیمه', 'text' ),
                $mk( 'daily_wage', 'دستمزد روزانه' ),
                $mk( 'work_days', 'کارکرد (تعداد روز)' ),
                $mk( 'base_salary', 'حقوق پایه' ),
                $mk( 'housing', 'حق مسکن' ),
                $mk( 'food', 'حق بن' ),
                $mk( 'seniority', 'پایه سنوات' ),
                $mk( 'marriage', 'حق تأهل' ),
                $mk( 'children_count', 'تعداد فرزند' ),
                $mk( 'child_allowance', 'حق اولاد' ),
                $mk( 'commute', 'کمک هزینه ایاب و ذهاب' ),
                $mk( 'overtime_hours', 'تعداد ساعات اضافه کاری' ),
                $mk( 'overtime_pay', 'مبلغ اضافه کاری' ),
                $mk( 'holiday_days', 'تعداد روز تعطیل کاری' ),
                $mk( 'holiday_pay', 'مبلغ تعطیل کاری' ),
                $mk( 'absence_days', 'تعداد روز غیبت' ),
                $mk( 'absence_penalty', 'جریمه غیبت' ),
                $mk( 'work_deduction', 'جریمه کسر از کار' ),
                $mk( 'other', 'سایر' ),
                $mk( 'gross', 'حقوق ناخالص' ),
                $mk( 'insurable', 'حقوق مشمول بیمه' ),
                $mk( 'insurance_deduct', 'کسر درصد بیمه' ),
                $mk( 'other_deductions', 'کسورات دیگر' ),
                $mk( 'net', 'حقوق خالص پرداختی' ),
        );
}

// نسخه 1.5.0: گارد فیلدهای فقط‌پروفایلی (سپر هاردکد) — مطابق helpers.php.
if ( ! function_exists( 'tpp_salary_field_in_record' ) ) {
        function tpp_salary_field_in_record( $f ) {
                if ( isset( $f->in_record ) && ! (int) $f->in_record ) { return false; }
                return ! in_array( (string) $f->field_key, array( 'full_name', 'job_title', 'vehicle_type', 'vehicle_plate' ), true );
        }
}

function tpp_salary_today_fa() { return '13 شهریور 1405'; }

function mk_record( $uid, $gross, $net ) {
        return (object) array(
                'id' => $uid, 'user_id' => $uid, 'center_id' => 1, 'jyear' => 1405, 'jmonth' => 5,
                'gross' => $gross, 'insurable' => 270697756, 'insurance_deduct' => -18948842, 'other_deductions' => 0, 'net' => $net,
                'payload' => wp_json_encode( array(
                        'insurance_group' => '6', 'daily_wage' => 5593757, 'work_days' => 31,
                        'base_salary' => 173406467, 'housing' => 30000000, 'food' => 22000000,
                        'seniority' => 40291289, 'marriage' => 5000000, 'children_count' => ( $uid % 3 ),
                        'child_allowance' => ( $uid % 3 ) * 16625550, 'commute' => 100000000,
                        'overtime_hours' => 0, 'overtime_pay' => 0, 'holiday_days' => 0, 'holiday_pay' => 0,
                        'absence_days' => 0, 'absence_penalty' => 0, 'work_deduction' => 0, 'other' => 10683840,
                        'gross' => $gross, 'insurable' => 270697756, 'insurance_deduct' => -18948842,
                        'other_deductions' => 0, 'net' => $net,
                ) ),
        );
}

// بارگذاری کلاس گزارش (بدون اجرای init چون add_action وجود ندارد).
require_once TPP_SALARY_DIR . 'includes/class-tppsalary-reports.php';
class TppSalary_Reports_Proxy extends TppSalary_Reports { public static function payslip( $r ) { return self::build_payslip_pdf( $r ); } }

echo "=== E2E: فیش حقوقی تکی ===\n";
try {
        $pdf_bytes = TppSalary_Reports_Proxy::payslip( mk_record( 1, 381381596, 362432754 ) );
        if ( is_wp_error( $pdf_bytes ) ) {
                echo "خطا: " . $pdf_bytes->get_error_message() . "\n";
                exit( 1 );
        }
        file_put_contents( '/home/z/my-project/build/test_payslip.pdf', $pdf_bytes );
        echo '✓ فیش تولید شد: ' . strlen( $pdf_bytes ) . " بایت\n";
} catch ( Exception $e ) {
        echo '✗ Exception: ' . $e->getMessage() . "\n";
        exit( 1 );
}

echo "=== E2E: ZIP عمده (سطح پایین) ===\n";
if ( class_exists( 'ZipArchive' ) ) {
        $zip = new ZipArchive();
        $zip->open( '/home/z/my-project/build/test_bulk.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE );
        foreach ( array( 1, 2, 3, 4 ) as $uid ) {
                $rec = mk_record( $uid, 400000000, 381051158 );
                $bytes = TppSalary_Reports_Proxy::payslip( $rec );
                if ( ! is_wp_error( $bytes ) ) {
                        $zip->addFromString( $GLOBALS['__users'][ $uid ]->display_name . '.pdf', $bytes );
                }
        }
        $zip->close();
        echo '✓ ZIP: ' . filesize( '/home/z/my-project/build/test_bulk.zip' ) . " بایت\n";
}

echo "OK\n";
