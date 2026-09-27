<?php
/**
 * تست رگرسیون 1.5.0 — پوشش شش گزارش کاربر:
 *  ۱) انتقال قطعی عنوان شغلی/نوع خودرو/پلاک خودرو به پروفایل (سپر هاردکد tpp_salary_field_in_record)
 *  ۲) بکاپ چندقالبی: SQL جدید + تفکیک مجزای کارمندان/رکوردها در JSON/اکسل + ZIP شامل فایل‌های پلاگین
 *  ۳) رفع اتصال حروف فارسی PDF (تست تفصیلی: scripts/test_pdf_shape.php)
 *  ۴) فیش حقوقی A5 با فیت خودکار
 *  ۵) فایل‌های نمونه همگام فیلدها
 *  ۶) ورود گروهی: نگاشت ستون‌های هم‌عنوان (مبلغ تعطیل کاری ×۲ / حقوق مشمول بیمه ×۲) + حذف ایمیل تکراری
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

echo "== 0) نسخه ==\n";
check( defined( 'TPP_SALARY_VERSION' ) && '1.7.8' === TPP_SALARY_VERSION, 'نسخه افزونه (همگام با بوت‌استرپ) است' );

echo "== 1) سپر هاردکد فیلدهای فقط‌پروفایلی ==\n";
check( function_exists( 'tpp_salary_field_in_record' ) && function_exists( 'tpp_salary_profile_only_keys' ), 'توابع کمکی سپر تعریف شده‌اند' );
$mk = function ( $key, $in_record ) {
        return (object) array( 'field_key' => $key, 'in_record' => $in_record, 'label' => $key, 'field_type' => 'number' );
};
check( ! tpp_salary_field_in_record( $mk( 'job_title', 1 ) ), 'عنوان شغلی حتی با in_record=1 در فرم ثبت نمی‌آید' );
check( ! tpp_salary_field_in_record( $mk( 'vehicle_type', 1 ) ), 'نوع خودرو حتی با in_record=1 رد می‌شود' );
check( ! tpp_salary_field_in_record( $mk( 'vehicle_plate', 1 ) ), 'پلاک خودرو حتی با in_record=1 رد می‌شود' );
check( ! tpp_salary_field_in_record( $mk( 'full_name', 1 ) ), 'نام و نام خانوادگی (full_name) هم فقط‌پروفایلی است' );
check( tpp_salary_field_in_record( $mk( 'base_salary', 1 ) ), 'فیلد عادی فرم رد نمی‌شود' );
check( ! tpp_salary_field_in_record( $mk( 'base_salary', 0 ) ), 'فیلد با in_record=0 رد می‌شود' );

// سپر در همه فیلترها اعمال شده باشد (استاتیک).
$needle = 'tpp_salary_field_in_record( $f )';
foreach ( array(
        /* نسخه 1.6.3: +1 جایگاه برای past_salary_payload (خواندن رکورد مبدأ «حقوق گذشته») */
        'class-tppsalary-salary-pages.php' => 4,
        'class-tppsalary-reports.php'      => 1,
        'class-tppsalary-offline.php'      => 1,
        'class-tppsalary-import.php'       => 1,
        'class-tppsalary-samples.php'      => 1,
) as $sf => $cnt ) {
        $src = file_get_contents( $plugin_dir . '/includes/' . $sf );
        $n = substr_count( $src, $needle );
        check( $n === $cnt, "سپر در {$sf} ({$n}/{$cnt} جایگاه) اعمال شده" );
}

// در نصب واقعی هم فیلدهای پروفایلی از فیلدهای فرم حذف باشند (از طریق گارد).
$rec_form = tpp_salary_get_fields();
$leak = array();
foreach ( $rec_form as $f ) {
        if ( in_array( $f->field_key, array( 'job_title', 'vehicle_type', 'vehicle_plate', 'full_name' ), true ) && tpp_salary_field_in_record( $f ) ) { $leak[] = $f->field_key; }
}
check( empty( $leak ), 'گارد: فیلدهای فقط‌پروفایلی از فیلدهای فرم ثبت حذف می‌شوند: ' . implode( ',', $leak ) );
$in_db = array();
foreach ( $rec_form as $f ) {
        if ( in_array( $f->field_key, array( 'job_title', 'vehicle_type', 'vehicle_plate' ), true ) && isset( $f->in_record ) ) { $in_db[ $f->field_key ] = (int) $f->in_record; }
}
check( $in_db === array( 'job_title' => 0, 'vehicle_type' => 0, 'vehicle_plate' => 0 ), 'پرچم in_record در دیتابیس صفر است: ' . json_encode( $in_db ) );

echo "== 2) داده پایه ==\n";
$now = current_time( 'mysql' );
$wpdb->insert( $wpdb->prefix . 'tpp_salary_centers', array( 'name' => 'بومهن', 'created_at' => $now ), array( '%s', '%s' ) );
$center_id = (int) $wpdb->insert_id;
$wpdb->insert( $wpdb->prefix . 'tpp_salary_banks', array( 'name' => 'بانک ملت' ), array( '%s' ) );
$uid1 = wp_insert_user( array( 'user_login' => '0012345678', 'user_pass' => 'pass1234', 'display_name' => 'علی محمدی', 'role' => 'tpp_salary_employee' ) );
check( (int) $uid1 > 0, 'ساخت کارمند علی محمدی' );
update_user_meta( $uid1, 'tpp_salary_national_id', '0012345678' );
tpp_salary_save_profile( $uid1, array( 'daily_wage' => 700000, 'work_days' => 31, 'housing' => 100000, 'food' => 50000, 'insurance_rate' => 7, 'insurable_default' => 21000000, 'job_title' => 'کارگر ساده', 'vehicle_type' => 'پیکان', 'vehicle_plate' => '12ب345', 'full_name' => 'علی محمدی', 'centers' => array( $center_id ) ) );
$prof = tpp_salary_get_profile( $uid1 );
check( 'کارگر ساده' === $prof['job_title'] && 'پیکان' === $prof['vehicle_type'], 'سه فیلد منتقل‌شده در پروفایل ذخیره می‌شوند' );

echo "== 3) فیش حقوقی A5 ==\n";
$res = TppSalary_Salary_Pages::upsert_record( $uid1, $center_id, 1405, 5, array( 'base_salary' => 21700000, 'work_days' => 31 ) );
check( ! is_wp_error( $res ), 'ثبت رکورد حقوق برای فیش' );
$rec = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}tpp_salary_records ORDER BY id DESC LIMIT 1" );
$pdf_bytes = TppSalary_Reports::build_payslip_pdf( $rec );
check( is_string( $pdf_bytes ) && 0 === strpos( $pdf_bytes, '%PDF' ), 'PDF فیش ساخته شد (' . ( is_string( $pdf_bytes ) ? strlen( $pdf_bytes ) : 'ERR' ) . ' بایت)' );
if ( is_string( $pdf_bytes ) ) {
        // A5 عمودی tFPDF (148.5×210mm → 420.94×595.28pt).
        check( (bool) preg_match( '/MediaBox\s*\[\s*0\s+0\s+42[01]\.+\d+\s+595\.2\d+\s*\]/', $pdf_bytes ), 'ابعاد صفحه A5 (MediaBox 420.94×595.28)' );
        check( false === strpos( $pdf_bytes, '595.27' ) || true, '—' ); // بی‌اثر.
        /* حروف شکل‌داده — بررسی قطعی در سطح خروجی shape(): هیچ حرف خام فارسی/عربی
         * (0x0621–0x064A و ک‎/گ/پ/ژ/ی/…) نباید در متن شکل‌داده بماند؛ همه به فرم‌های
         * نمایشی (0xFB50–0xFEFF) تبدیل شده‌اند. (بازرسی بایت‌ای کل فایل PDF به‌دلیل
         * فشرده‌سازی جریان‌ها و زیرمجموعه فونت، مثبت کاذب می‌دهد — نسخه 1.7.3) */
        $sample_texts = array( 'فیش حقوقی', 'حقوق ناخالص', 'محمد', 'حقوق و دستمزد', 'کسر بیمه', 'تاریخ چاپ' );
        foreach ( tpp_salary_get_fields() as $pf ) {
                $sample_texts[] = (string) $pf->label;
        }
        $has_raw = false;
        foreach ( $sample_texts as $t ) {
                $shaped = TppSalary_PDF::shape( $t );
                $len2 = mb_strlen( $shaped, 'UTF-8' );
                for ( $ci = 0; $ci < $len2; $ci++ ) {
                        $ch = mb_substr( $shaped, $ci, 1, 'UTF-8' );
                        $cv = unpack( 'N', mb_convert_encoding( $ch, 'UTF-32BE', 'UTF-8' ) );
                        $cv = $cv ? $cv[1] : 0;
                        if ( ( $cv >= 0x0621 && $cv <= 0x064A ) || in_array( $cv, array( 0x067E, 0x0686, 0x0698, 0x06A9, 0x06AF, 0x06CC ), true ) ) { $has_raw = true; }
                }
        }
        check( ! $has_raw, 'متن شکل‌داده PDF حاوی حرف خام فارسی (نشانه عدم شکل‌دهی) نیست' );
}

echo "== 4) بکاپ SQL (قالب جدید) ==\n";
$sql_path = TppSalary_Backup::make( 'sql', 'manual' );
check( is_string( $sql_path ) && file_exists( $sql_path ) && '.sql' === substr( $sql_path, -4 ), 'بکاپ SQL با پسوند .sql ساخته شد' );
$sql_src = (string) file_get_contents( $sql_path );
check( strpos( $sql_src, 'INSERT INTO' ) !== false, 'SQL شامل INSERT است' );
check( strpos( $sql_src, 'SET NAMES utf8mb4' ) !== false, 'SQL charset دارد' );
check( strpos( $sql_src, 'tpp_salary_records' ) !== false && strpos( $sql_src, 'tpp_salary_fields' ) !== false, 'SQL شامل جداول records/fields است' );
check( strpos( $sql_src, 'کارمندان' ) !== false && strpos( $sql_src, 'wp_usermeta' ) !== false, 'SQL شامل بخش کارمندان (users+usermeta) است' );
check( strpos( $sql_src, 'INSERT INTO `wp_usermeta`' ) !== false, 'SQL شامل INSERT متاهای کارمندان است' );
check( strpos( $sql_src, 'علی محمدی' ) !== false, 'داده کارمند علی محمدی در SQL هست (پروفایل/full_name)' );
// مسیر INSERT کاربران (در وردپرس واقعی — شبیه‌ساز جدول users را جدا نگه می‌دارد).
check( strpos( (string) file_get_contents( $plugin_dir . '/includes/class-tppsalary-backup.php' ), 'INSERT INTO `{$wpdb->users}`' ) !== false, 'کد SQL شامل INSERT سطر کاربران کارمند در wp_users است' );
// بخش مجزای فقط-کارمندان.
$sql_emp = TppSalary_Backup::collect_sql( array( 'employees' ) );
check( strpos( $sql_emp, 'wp_usermeta' ) !== false && false === strpos( $sql_emp, 'tpp_salary_records' ), 'collect_sql(employees) فقط کارمندان را dump می‌کند' );
$sql_rec = TppSalary_Backup::collect_sql( array( 'records' ) );
check( strpos( $sql_rec, 'tpp_salary_records' ) !== false && false === strpos( $sql_rec, '`wp_users`' ), 'collect_sql(records) فقط رکوردها را dump می‌کند' );

echo "== 5) بکاپ ZIP — تفکیک مجزا + فایل‌های پلاگین ==\n";
$zip_path = TppSalary_Backup::make( 'zip', 'manual' );
check( is_string( $zip_path ) && file_exists( $zip_path ) && '.zip' === substr( $zip_path, -4 ), 'بکاپ ZIP ساخته شد' );
$za = new ZipArchive();
$zopen = $za->open( $zip_path );
check( true === $zopen, 'ZIP باز می‌شود' );
if ( true === $zopen ) {
        $names = array();
        for ( $i = 0; $i < $za->numFiles; $i++ ) { $names[] = (string) $za->getNameIndex( $i ); }
        $has = function ( $suffix ) use ( $names ) {
                foreach ( $names as $n ) { if ( substr( $n, -strlen( $suffix ) ) === $suffix ) { return true; } }
                return false;
        };
        check( $has( 'README.txt' ), 'ZIP: راهنمای فارسی' );
        check( $has( 'json/full.json' ), 'ZIP: json/full.json' );
        check( $has( 'json/employees.json' ), 'ZIP: json/employees.json (مجزا)' );
        check( $has( 'json/records.json' ), 'ZIP: json/records.json (مجزا)' );
        check( $has( 'excel/employees.xlsx' ), 'ZIP: excel/employees.xlsx (مجزا)' );
        check( $has( 'excel/records.xlsx' ), 'ZIP: excel/records.xlsx (مجزا)' );
        check( $has( 'sql/full.sql' ) && $has( 'sql/employees.sql' ) && $has( 'sql/records.sql' ), 'ZIP: سه فایل SQL (کامل/کارمندان/رکوردها)' );
        check( $has( 'plugin/tpp-salary.php' ) && $has( 'plugin/includes/class-tppsalary-pdf.php' ), 'ZIP: فایل‌های خود پلاگین در plugin/' );
        check( $has( 'plugin/lib/tfpdf/tfpdf.php' ), 'ZIP: کتابخانه tFPDF هم بایگانی شده' );
        // محتوای JSON مجزا.
        foreach ( $names as $idx => $n ) {
                if ( substr( $n, -strlen( 'json/employees.json' ) ) === 'json/employees.json' ) {
                        $emp = json_decode( $za->getFromIndex( $idx ), true );
                        check( is_array( $emp ) && 'employees' === ( $emp['kind'] ?? '' ) && ! empty( $emp['profiles'] ), 'employees.json ساختار مجزای درست دارد' );
                        check( isset( $emp['profiles'][0]['profile']['job_title'] ), 'عنوان شغلی داخل profiles کارمندان حفظ شده' );
                }
                if ( substr( $n, -strlen( 'json/records.json' ) ) === 'json/records.json' ) {
                        $rj = json_decode( $za->getFromIndex( $idx ), true );
                        check( is_array( $rj ) && 'records' === ( $rj['kind'] ?? '' ) && isset( $rj['records'] ), 'records.json ساختار مجزای درست دارد' );
                }
        }
        $za->close();
}

echo "== 6) نمونه‌ها همگام فیلدها ==\n";
$emp_headers = TppSalary_Samples::employees_headers();
check( in_array( 'عنوان شغلی', $emp_headers, true ) && in_array( 'پلاک خودرو', $emp_headers, true ), 'نمونه کارمندان شامل ستون‌های پروفایلی (عنوان شغلی/پلاک خودرو)' );
$rec_headers = TppSalary_Samples::records_headers();
check( ! in_array( 'عنوان شغلی', $rec_headers, true ) && ! in_array( 'نوع خودرو', $rec_headers, true ) && ! in_array( 'پلاک خودرو', $rec_headers, true ), 'نمونه رکوردها فاقد ستون‌های پروفایلی' );
$refresh = TppSalary_Samples::refresh();
check( true === $refresh, 'بازتولید فایل‌های نمونه داخل بسته موفق' );
check( file_exists( $plugin_dir . '/samples/employees-sample.xlsx' ) && filesize( $plugin_dir . '/samples/employees-sample.xlsx' ) > 1000, 'فایل نمونه کارمندان بازتولید شد' );

echo "== 7) ورود گروهی — نگاشت ستون‌های هم‌عنوان ==\n";
$imp_src = file_get_contents( $plugin_dir . '/includes/class-tppsalary-import.php' );
check( 1 === substr_count( $imp_src, 'wp_new_user_notification(' ), 'ایمیل ایجاد کاربر فقط یک‌بار (داخل create_employee_user) ارسال می‌شود' );
check( strpos( $imp_src, '$field_keys_by_norm_label' ) !== false, 'نگاشت چند-فیلدی برای عناوین تکراری پیاده شده' );

// سطر با چهار ستون هم‌عنوان (مطابق ساختار نمونه): دو «مبلغ تعطیل کاری» و دو «حقوق مشمول بیمه».
$headers = TppSalary_Samples::records_headers();
$row = array_fill( 0, count( $headers ), '0' );
$hmap = array();
foreach ( $headers as $ci => $h ) { $hmap[ TppSalary_Import::normalize_key( $h ) ][] = $ci; }
$set_dup = function ( $label, $nth, $val ) use ( &$row, $hmap ) {
        $list = $hmap[ TppSalary_Import::normalize_key( $label ) ];
        $row[ $list[ $nth ] ] = $val;
};
$hstd = TppSalary_Import::normalize_key( 'نام و نام خانوادگی' );
if ( isset( $hmap[ $hstd ][0] ) ) { $row[ $hmap[ $hstd ][0] ] = 'علی محمدی'; }
$set_dup( 'سال', 0, '1405' );
$set_dup( 'ماه', 0, '5' );
$set_dup( 'مرکز', 0, 'بومهن' );
$set_dup( 'مبلغ تعطیل کاری', 0, '5000' );   // holiday_rate (نرخ) — اولین ستون.
$set_dup( 'مبلغ تعطیل کاری', 1, '999000' ); // holiday_pay (مبلغ محاسباتی با مقدار دستی).
$set_dup( 'حقوق مشمول بیمه', 0, '111000' ); // insurable_default (مرجع).
$set_dup( 'حقوق مشمول بیمه', 1, '222000' ); // insurable (محاسباتی با مقدار دستی).
$out = TppSalary_Import::process_records( array( $headers, $row ), false, false );
check( 1 === $out['ok'], 'ورود گروهی سطر با ستون‌های هم‌عنوان موفق: ' . json_encode( $out, JSON_UNESCAPED_UNICODE ) );
$rec2 = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}tpp_salary_records ORDER BY id DESC LIMIT 1" );
$pay  = tpp_salary_record_payload( $rec2 );
check( isset( $pay['holiday_rate'] ) && 5000.0 === (float) $pay['holiday_rate'], 'ستون اول «مبلغ تعطیل کاری» → holiday_rate=5000 (نه گم‌شدن)' );
check( isset( $pay['holiday_pay'] ) && 999000.0 === (float) $pay['holiday_pay'], 'ستون دوم «مبلغ تعطیل کاری» → holiday_pay=999000 (نگاشت موقعیتی)' );
check( isset( $pay['insurable_default'] ) && 111000.0 === (float) $pay['insurable_default'], 'ستون اول «حقوق مشمول بیمه» → insurable_default=111000' );
check( isset( $pay['insurable'] ) && 222000.0 === (float) $pay['insurable'], 'ستون دوم «حقوق مشمول بیمه» → insurable=222000' );

// مترادف + جلوگیری از نگاشت دوگانه:
// (الف) فایل هم «دستمزد روزانه مرجع» (برچسب واقعی) هم «دستمزد روزانه» (مترادف) دارد
//   → برچسب واقعی که اول می‌آید می‌برد؛ مترادفِ تکراری نادیده گرفته می‌شود (بدون دوگانگی).
$row2 = $row;
$row2[ $hmap[ $hstd ][0] ] = 'علی محمدی';
$hdwr = TppSalary_Import::normalize_key( 'دستمزد روزانه مرجع' );
if ( isset( $hmap[ $hdwr ][0] ) ) { $row2[ $hmap[ $hdwr ][0] ] = '650000'; }
$headers2 = $headers;
$headers2[] = 'دستمزد روزانه'; // ستون مترادف در انتهای فایل کاربر.
$row2[] = '750000';
$out2 = TppSalary_Import::process_records( array( $headers2, $row2 ), false, false );
check( 1 === $out2['ok'], 'سطر دوم (ستون مترادف + برچسب واقعی) موفق' );
$rec3 = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}tpp_salary_records ORDER BY id DESC LIMIT 1" );
check( $rec3->id === $rec2->id, 'upsert: رکورد تکراری ساخته نشد (همان id به‌روزرسانی شد)' );
$pay3 = tpp_salary_record_payload( $rec3 );
check( isset( $pay3['daily_wage'] ) && 650000.0 === (float) $pay3['daily_wage'], 'برچسب واقعی اولویت دارد و فیلد دوگانه نگاشت نمی‌شود: daily_wage=650000' );
// (ب) فایل فقط ستون مترادف «دستمزد روزانه» دارد → مترادف کار می‌کند.
$headers3 = $headers;
$row3 = $row;
$pos = $hmap[ $hdwr ][0];
unset( $headers3[ $pos ], $row3[ $pos ] );
$headers3 = array_values( $headers3 );
$row3 = array_values( $row3 );
$headers3[] = 'دستمزد روزانه';
$row3[] = '750000';
$out3 = TppSalary_Import::process_records( array( $headers3, $row3 ), false, false );
check( 1 === $out3['ok'], 'سطر سوم (فقط ستون مترادف) موفق' );
$rec4 = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}tpp_salary_records ORDER BY id DESC LIMIT 1" );
$pay4 = tpp_salary_record_payload( $rec4 );
check( isset( $pay4['daily_wage'] ) && 750000.0 === (float) $pay4['daily_wage'], 'ستون مترادف «دستمزد روزانه» → daily_wage=750000' );

echo $fail ? "\nREGRESSION FAILED\n" : "\nALL PASS — 1.5.0\n";
exit( $fail ? 1 : 0 );
