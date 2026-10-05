<?php
/**
 * تست‌های نسخه 1.7.0 — REST API برنامه آفلاین پایتون:
 *  ۱) کلید API داینامیک (تولید/هش/لغو — بدون هاردکد)
 *  ۲) ثبت مسیرهای REST با permission_callback
 *  ۳) احراز هویت هدر X-TPP-Key (hash_equals)
 *  ۴) بسته کامل (bundle): کارمندان/مراکز/بانک‌ها/فیلدها/پروفایل/رکوردها/تنظیمات
 *  ۵) sync ops: رکورد/کارمند/مرکز/بانک upsert+delete + تشخیص تداخل + نگاشت شناسه
 *
 * اجرا: ./tools/php scripts/test_api_170.php
 */

define( 'SIM_SQLITE_WPDB', 1 );
define( 'SIM_SQLITE_USERS', 1 );
error_reporting( E_ALL );
require __DIR__ . '/wp_sim_bootstrap.php';

$fail = 0;
function check( $cond, $msg ) {
        global $fail;
        echo ( $cond ? '  PASS: ' : '  FAIL: ' ) . $msg . "\n";
        if ( ! $cond ) { $fail = 1; }
}

global $wpdb;
$plugin_dir = dirname( __DIR__ ) . '/build/tpp_salary';

echo "== 0) نسخه و کلاس API ==\n";
check( defined( 'TPP_SALARY_VERSION' ) && '1.7.9' === TPP_SALARY_VERSION, 'نسخه افزونه 1.7.0 است' );
check( class_exists( 'TppSalary_Api' ), 'کلاس TppSalary_Api بارگذاری شد' );
check( defined( 'TPP_SALARY_INSTALL_BUILD' ) && TPP_SALARY_INSTALL_BUILD === TPP_SALARY_VERSION, 'INSTALL_BUILD همگام با نسخه است' );

echo "== 1) ثبت مسیرهای REST ==\n";
TppSalary_Api::routes();
$routes = $GLOBALS['tpp_rest_routes'];
check( count( $routes ) === 3, 'سه مسیر ping/bundle/sync ثبت شد: ' . count( $routes ) );
$routes_ok = 0;
foreach ( $routes as $r ) {
        if ( 'tpp_salary/v1' === $r['ns'] && ! empty( $r['args']['permission_callback'] ) && is_callable( $r['args']['permission_callback'] ) && is_callable( $r['args']['callback'] ) ) {
                $routes_ok++;
        }
}
check( $routes_ok === 3, 'همه مسیرها callback + permission_callback قابل‌فراخوانی دارند' );

echo "== 2) کلید API داینامیک ==\n";
check( ! TppSalary_Api::has_key(), 'در ابتدا کلیدی وجود ندارد' );
$raw_key = TppSalary_Api::generate_key();
check( is_string( $raw_key ) && 0 === strpos( $raw_key, 'tppk_' ) && strlen( $raw_key ) > 20, 'کلید خام با پیشوند tppk_ تولید شد (در وردپرس واقعی ۶۹ نویسه — در شبیه‌ساز کوتاه‌تر)' );
$state = TppSalary_Api::get_state();
check( ! empty( $state['key_hash'] ) && $state['key_hash'] !== $raw_key, 'در دیتابیس فقط هش ذخیره شده (خود کلید ذخیره نمی‌شود)' );
check( hash( 'sha256', $raw_key ) === $state['key_hash'], 'هش ذخیره‌شده با sha256 کلید برابر است' );
check( TppSalary_Api::has_key(), 'وضعیت has_key فعال است' );

// بازتولید کلید → کلید قبلی باطل
$raw_key2 = TppSalary_Api::generate_key();
$state2   = TppSalary_Api::get_state();
check( hash( 'sha256', $raw_key2 ) === $state2['key_hash'], 'ساخت کلید جدید، کلید قبلی را جایگزین می‌کند' );
TppSalary_Api::revoke_key();
check( ! TppSalary_Api::has_key(), 'لغو کلید وضعیت را غیرفعال می‌کند' );
$raw_key = TppSalary_Api::generate_key(); // برای ادامه تست

echo "== 3) احراز هویت ==\n";
class FakeRestRequest {
        public $headers = array();
        public $json = null;
        public function get_header( $k ) { return isset( $this->headers[ $k ] ) ? $this->headers[ $k ] : ''; }
        public function get_json_params() { return $this->json; }
}
$req = new FakeRestRequest();
$err = TppSalary_Api::check_auth( $req );
check( is_wp_error( $err ), 'بدون هدر کلید → خطا' );
$req->headers['X-TPP-Key'] = 'wrong-key';
$err = TppSalary_Api::check_auth( $req );
check( is_wp_error( $err ), 'کلید اشتباه → خطا' );
$req->headers['X-TPP-Key'] = $raw_key;
$ok = TppSalary_Api::check_auth( $req );
check( true === $ok, 'کلید صحیح → دسترسی مجاز' );

echo "== 4) بسته کامل (bundle) ==\n";
// داده پایه: مرکز + کارمند
$wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => 'مرکز تست API', 'created_at' => current_time( 'mysql' ) ), array( '%s', '%s' ) );
$center_id = (int) $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}tpp_salary_centers WHERE name = 'مرکز تست API'" );
check( $center_id > 0, 'مرکز پایه ساخته شد' );
$uid = (int) wp_insert_user( array( 'user_login' => '9000000001', 'user_pass' => 'pass1234', 'display_name' => 'علی رضایی', 'role' => 'tpp_salary_employee' ) );
update_user_meta( $uid, 'tpp_salary_national_id', '9000000001' );
update_user_meta( $uid, 'tpp_salary_mobile', '09120000000' );
tpp_salary_save_profile( $uid, array( 'full_name' => 'علی رضایی', 'daily_wage' => 5000000, 'work_days' => 30, 'insurance_rate' => 7, 'centers' => array( $center_id ) ) );
check( $uid > 0, 'کارمند پایه ساخته شد' );

$resp = TppSalary_Api::handle_bundle( $req );
check( is_array( $resp ) && isset( $resp['employees'] ) && isset( $resp['records'] ), 'bundle ساختار کامل دارد' );
check( count( $resp['employees'] ) === 1 && (int) $resp['employees'][0]['id'] === $uid, 'کارمند در bundle هست (شامل قطع‌همکاری‌ها)' );
check( isset( $resp['employees'][0]['profile']['daily_wage'] ), 'پروفایل کامل کارمند در bundle هست' );
check( isset( $resp['employees'][0]['terminated'] ), 'فلگ قطع همکاری در bundle هست' );
check( count( $resp['centers'] ) === 1 && 'مرکز تست API' === $resp['centers'][0]['name'], 'مرکز در bundle هست' );
check( ! empty( $resp['fields'] ) && isset( $resp['fields'][0]['key'] ), 'فیلدهای فرم ثبت در bundle هست: ' . count( $resp['fields'] ) );
check( ! empty( $resp['profile_fields'] ), 'فیلدهای پروفایل در bundle هست' );
check( isset( $resp['formulas']['gross'] ) && isset( $resp['formulas']['net'] ), 'فرمول‌های محاسبه در bundle هست' );
check( isset( $resp['period']['jyear'] ) && $resp['period']['jyear'] > 1300, 'دوره جاری شمسی در bundle هست' );
check( isset( $resp['company_name'] ) && isset( $resp['currency'] ) && isset( $resp['defaults'] ), 'تنظیمات (شرکت/واحد پول/پیش‌فرض‌ها) در bundle هست' );

echo "== 5) sync — رکورد حقوق ==\n";
$req->json = array( 'ops' => array(
        array( 'ref' => 'r1', 'action' => 'record.upsert', 'client_updated_at' => '2026-09-01 10:00:00',
               'payload' => array( 'user_id' => $uid, 'center_id' => $center_id, 'jyear' => 1404, 'jmonth' => 5,
                                    'values' => array( 'daily_wage' => 6000000, 'work_days' => 30, 'insurance_rate' => 7 ),
                                    'insurable_formula' => false ) ),
) );
$resp = TppSalary_Api::handle_sync( $req );
check( isset( $resp['results'][0] ) && 'applied' === $resp['results'][0]['status'], 'record.upsert اعمال شد' );
$rec_id = (int) $resp['results'][0]['record_id'];
check( $rec_id > 0, 'شناسه رکورد بازگشت: ' . $rec_id );
$row = tpp_salary_get_record( $rec_id );
check( $row && (float) $row->gross > 0, 'رکورد با محاسبه سرور ذخیره شد (ناخالص > 0): ' . ( $row ? $row->gross : 0 ) );
$bundle_records = $resp['bundle']['records'];
check( count( $bundle_records ) === 1, 'bundle تازه در پاسخ sync هست (رکورد جدید)' );

// تداخل: ارسال با client_updated_at قدیمی‌تر از سرور
$req->json = array( 'ops' => array(
        array( 'ref' => 'r2', 'action' => 'record.upsert', 'client_updated_at' => '2020-01-01 00:00:00',
               'payload' => array( 'user_id' => $uid, 'center_id' => $center_id, 'jyear' => 1404, 'jmonth' => 5,
                                    'values' => array( 'daily_wage' => 1 ), 'insurable_formula' => false ) ),
) );
$resp = TppSalary_Api::handle_sync( $req );
check( isset( $resp['results'][0] ) && 'conflict' === $resp['results'][0]['status'], 'تداخل تشخیص داده شد (نسخه سرور جدیدتر)' );
check( isset( $resp['results'][0]['server']['id'] ) && (int) $resp['results'][0]['server']['id'] === $rec_id, 'شناسه نسخه سرور در نتیجه تداخل هست' );

echo "== 6) sync — کارمند (ایجاد/ویرایش/حذف) ==\n";
$req->json = array( 'ops' => array(
        array( 'ref' => 'e1', 'action' => 'employee.upsert',
               'employee' => array( 'id' => -5, 'name' => 'مریم احمدی', 'national' => '۹۰۰۰۰۰۰۰۰۲', 'mobile' => '09350000000',
                                     'centers' => array( $center_id ), 'profile' => array( 'job_title' => 'کارشناس' ) ) ),
) );
$resp = TppSalary_Api::handle_sync( $req );
$res0 = $resp['results'][0];
check( 'applied' === $res0['status'] && 'created' === $res0['action'], 'employee.upsert با شناسه منفی → ایجاد حساب جدید' );
$new_uid = (int) $res0['server_id'];
check( $new_uid > 0 && $new_uid !== $uid, 'شناسه سرور بازگشت: ' . $new_uid );
$new_user = get_user_by( 'id', $new_uid );
check( $new_user && in_array( 'tpp_salary_employee', (array) $new_user->roles, true ), 'نقش کارمندی به حساب جدید داده شد' );
check( 'مریم احمدی' === $new_user->display_name, 'نام نمایشی ذخیره شد' );
check( '9000000002' === get_user_meta( $new_uid, 'tpp_salary_national_id', true ), 'کد ملی فارسی به لاتین تبدیل و ذخیره شد' );
check( ! empty( $res0['login'] ), 'نام کاربری تولیدشده بازگشت (برای نگاشت برنامه): ' . $res0['login'] );

// ویرایش همان کارمند با id مثبت
$req->json = array( 'ops' => array(
        array( 'ref' => 'e2', 'action' => 'employee.upsert',
               'employee' => array( 'id' => $new_uid, 'name' => 'مریم احمدی‌فر', 'national' => '9000000002',
                                     'mobile' => '09350000000', 'centers' => array( $center_id ),
                                     'terminated' => 1, 'profile' => array( 'job_title' => 'مدیر' ) ) ),
) );
$resp = TppSalary_Api::handle_sync( $req );
$res0 = $resp['results'][0];
check( 'applied' === $res0['status'] && 'updated' === $res0['action'], 'employee.upsert با id مثبت → به‌روزرسانی' );
check( 'مریم احمدی‌فر' === get_user_by( 'id', $new_uid )->display_name, 'نام به‌روز شد' );
check( '1' === get_user_meta( $new_uid, 'tpp_salary_terminated', true ), 'وضعیت قطع همکاری اعمال شد' );

// حذف (فقط نقش — هم‌سان با افزونه)
$req->json = array( 'ops' => array(
        array( 'ref' => 'e3', 'action' => 'employee.delete', 'employee_id' => $new_uid ),
) );
$resp = TppSalary_Api::handle_sync( $req );
check( 'applied' === $resp['results'][0]['status'], 'employee.delete اعمال شد' );
$after = get_user_by( 'id', $new_uid );
check( $after && ! in_array( 'tpp_salary_employee', (array) $after->roles, true ), 'فقط نقش کارمندی حذف شد (حساب حفظ شد)' );

echo "== 7) sync — مرکز و بانک ==\n";
$req->json = array( 'ops' => array(
        array( 'ref' => 'c1', 'action' => 'center.upsert', 'center' => array( 'id' => -3, 'name' => 'کارگاه شمالی' ) ),
        array( 'ref' => 'c2', 'action' => 'center.upsert', 'center' => array( 'id' => -3, 'name' => 'مرکز تست API' ) ), // نام تکراری؟ نه — id -3 یعنی جدید؛ ولی نام تکراری است
) );
$resp = TppSalary_Api::handle_sync( $req );
check( 'applied' === $resp['results'][0]['status'] && (int) $resp['results'][0]['server_id'] > 0, 'center.upsert جدید اعمال شد' );
$new_center_id = (int) $resp['results'][0]['server_id'];
check( 'error' === $resp['results'][1]['status'], 'نام تکراری مرکز → خطا (یکتایی نام حفظ شد)' );

$req->json = array( 'ops' => array( array( 'ref' => 'c3', 'action' => 'center.delete', 'center_id' => $new_center_id ) ) );
$resp = TppSalary_Api::handle_sync( $req );
check( 'applied' === $resp['results'][0]['status'], 'center.delete اعمال شد' );
check( ! tpp_salary_get_center( $new_center_id ), 'مرکز حذف‌شده در دیتابیس نیست' );

$req->json = array( 'ops' => array(
        array( 'ref' => 'b1', 'action' => 'bank.upsert', 'bank' => array( 'id' => -1, 'name' => 'بانک ملت' ) ),
) );
$resp = TppSalary_Api::handle_sync( $req );
check( 'applied' === $resp['results'][0]['status'], 'bank.upsert اعمال شد' );
$bank_id = (int) ( isset( $resp['results'][0]['server_id'] ) ? $resp['results'][0]['server_id'] : 0 );
check( $bank_id > 0, 'شناسه بانک بازگشت: ' . $bank_id );
$req->json = array( 'ops' => array( array( 'ref' => 'b2', 'action' => 'bank.delete', 'bank_id' => $bank_id ) ) );
$resp = TppSalary_Api::handle_sync( $req );
check( 'applied' === $resp['results'][0]['status'], 'bank.delete اعمال شد' );

echo "== 8) sync — حذف رکورد و op ناشناخته ==\n";
$req->json = array( 'ops' => array(
        array( 'ref' => 'x1', 'action' => 'record.delete', 'record_id' => $rec_id, 'client_updated_at' => current_time( 'mysql' ) ),
        array( 'ref' => 'x2', 'action' => 'nonsense.op' ),
) );
$resp = TppSalary_Api::handle_sync( $req );
check( 'applied' === $resp['results'][0]['status'] && ! tpp_salary_get_record( $rec_id ), 'record.delete اعمال شد و رکورد حذف شد' );
check( 'error' === $resp['results'][1]['status'], 'op ناشناخته → خطا (بدون کرش)' );
check( count( $resp['bundle']['records'] ) === 0, 'bundle پس از حذف خالی است' );

echo "== 9) گارد ورودی ==\n";
$req->json = array( 'ops' => array( array( 'ref' => 'g1', 'action' => 'record.upsert', 'payload' => array( 'user_id' => 0 ) ) ) );
$resp = TppSalary_Api::handle_sync( $req );
check( 'error' === $resp['results'][0]['status'], 'پارامترهای ناقص رکورد → خطای کنترل‌شده' );
$req->json = 'not-an-array';
$resp = TppSalary_Api::handle_sync( $req );
check( isset( $resp['results'] ) && array() === $resp['results'], 'بدنه نامعتبر → بدون کرش و نتیجه خالی' );

echo "\n" . ( $fail ? 'SOME TESTS FAILED' : 'ALL PASS' ) . "\n";
exit( $fail ? 1 : 0 );
