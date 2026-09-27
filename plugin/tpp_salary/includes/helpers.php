<?php
/**
 * توابع کمکی سراسری پلاگین
 *
 * @package TppSalary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * دریافت کل تنظیمات پلاگین
 *
 * @return array
 */
if ( ! function_exists( 'tpp_salary_get_settings' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_get_settings() {
        $defaults = array(
                'company_name' => '',
                'logo_id'      => 0,
                'per_page_a4'  => 4,
                'per_page_list' => 20,
                'digits_fa'    => 0, // نسخه 1.7.1: همه اعداد انگلیسی (کلید فقط برای سازگاری).
                'currency'     => 'ریال',
                'formulas'     => array(
                        'gross'       => '{base_salary}+{housing}+{food}+{seniority}+{marriage}+{child_allowance}+{commute}+{overtime_pay}+{holiday_pay}+{absence_penalty}+{work_deduction}+{other}',
                        'insurable'   => '{base_salary}+{housing}+{food}+{seniority}+{marriage}+{overtime_pay}+{holiday_pay}+{absence_penalty}+{work_deduction}',
                        'net'         => '{gross}+{insurance_deduct}+{other_deductions}',
                ),
                'backup'       => array(
                        'daily'     => 0,
                        'weekly'    => 0,
                        'monthly'   => 1,
                        'yearly'    => 1,
                        'retention' => 24,
                ),
                // مقادیر پیش‌فرض فیلدها (در تب تنظیمات ذخیره می‌شود) — همیشه کلید موجود باشد.
                'defaults'     => array(),
        );
        $settings = get_option( 'tpp_salary_settings', array() );
        if ( ! is_array( $settings ) ) {
                $settings = array();
        }
        return wp_parse_args( $settings, $defaults );
}
// TPP_SALARY GUARD END (tpp_salary_get_settings)
}

/**
 * دریافت یک تنظیم
 *
 * @param string $key     کلید.
 * @param mixed  $default پیش‌فرض.
 * @return mixed
 */
if ( ! function_exists( 'tpp_salary_get_setting' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_get_setting( $key, $default = '' ) {
        $settings = tpp_salary_get_settings();
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}
// TPP_SALARY GUARD END (tpp_salary_get_setting)
}

/**
 * نرمال‌سازی ورودی عددی (تبدیل ارقام فارسی و حذف جداکننده)
 *
 * @param mixed $val مقدار ورودی.
 * @return float
 */
if ( ! function_exists( 'tpp_salary_parse_number' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_parse_number( $val ) {
        $str = TppSalary_Jalali::digits_en( (string) $val );
        /* جداکننده هزارگان فارسی (٬ U+066C) و عربی هم حذف شود — وگرنه ۷۰۰٬۰۰۰ به ۷۰۰ پارس می‌شد. */
        $str = str_replace( array( '،', '٬', ',', ' ' ), '', $str );
        $str = trim( $str );
        if ( '' === $str || '-' === $str ) {
                return 0.0;
        }
        return (float) $str;
}
// TPP_SALARY GUARD END (tpp_salary_parse_number)
}

/**
 * قالب‌بندی عدد با جداکننده هزارگان
 *
 * نسخه 1.7.1 — سیاست ارقام انگلیسی: همه اعداد در تمام سایت با ارقام لاتین
 * نمایش داده می‌شوند؛ پارامتر $fa صرفاً برای سازگاری با فراخوانی‌های قدیمی
 * حفظ شده و دیگر اثری ندارد.
 *
 * @param float $n      عدد.
 * @param bool  $fa     (بی‌اثر — سازگاری نسخه‌های قبل از 1.7.1).
 * @param int   $dec    رقم اعشار.
 * @return string
 */
if ( ! function_exists( 'tpp_salary_format_number' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_format_number( $n, $fa = true, $dec = 0 ) {
        return number_format( (float) $n, $dec, '.', ',' );
}
// TPP_SALARY GUARD END (tpp_salary_format_number)
}

/**
 * فهرست مراکز
 *
 * @return array<int,object>
 */
if ( ! function_exists( 'tpp_salary_get_centers' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_get_centers() {
        global $wpdb;
        $table = $wpdb->prefix . 'tpp_salary_centers';
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" ); // phpcs:ignore
}
// TPP_SALARY GUARD END (tpp_salary_get_centers)
}

/**
 * فهرست بانک‌ها
 *
 * @return array<int,object>
 */
if ( ! function_exists( 'tpp_salary_get_banks' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_get_banks() {
        global $wpdb;
        $table = $wpdb->prefix . 'tpp_salary_banks';
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY sort_order ASC, name ASC" ); // phpcs:ignore
}
// TPP_SALARY GUARD END (tpp_salary_get_banks)
}

/**
 * دریافت یک بانک
 *
 * @param int $id شناسه.
 * @return object|null
 */
if ( ! function_exists( 'tpp_salary_get_bank' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_get_bank( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpp_salary_banks';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore
}
// TPP_SALARY GUARD END (tpp_salary_get_bank)
}

/**
 * دریافت یک مرکز
 *
 * @param int $id شناسه.
 * @return object|null
 */
if ( ! function_exists( 'tpp_salary_get_center' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_get_center( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpp_salary_centers';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore
}
// TPP_SALARY GUARD END (tpp_salary_get_center)
}

/**
 * فیلدهای حقوقی فعال مرتب‌شده — با کش درون-درخواستی
 *
 * برای امنیت، شرط‌ها فقط به‌صورت پارامترهای مجاز پذیرفته می‌شود
 * (هیچ بخشی از SQL به‌صورت رشته خام از فراخوان ساخته نمی‌شود).
 *
 * @param array $args آرگومان‌های اختیاری: profile => 1 فقط فیلدهای پروفایل.
 * @return array<int,object>
 */
if ( ! function_exists( 'tpp_salary_get_fields' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_get_fields( $args = array() ) {
        static $cache = array();

        $profile = ! empty( $args['profile'] ) ? 1 : 0;
        /*
         * نسخه 1.7.3: کش با نسخه فیلدها کلیدگذاری می‌شود؛ اکشن
         * tpp_salary_fields_changed (افزودن/ویرایش/حذف/فعال‌سازی فیلد) نسخه را
         * بالا می‌برد و کش در همان درخواست هم بازسازی می‌شود — پیش‌تر در یک
         * درخواست، فیلد تازه‌ساخته‌شده تا پایان همان درخواست دیده نمی‌شد.
         */
        $ver  = isset( $GLOBALS['tpp_salary_fields_ver'] ) ? (int) $GLOBALS['tpp_salary_fields_ver'] : 0;
        $ckey = $profile . ':' . $ver;
        if ( isset( $cache[ $ckey ] ) ) {
                return $cache[ $ckey ];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'tpp_salary_fields';
        $sql   = "SELECT * FROM {$table} WHERE is_active = 1";
        if ( $profile ) {
                $sql .= ' AND is_profile = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        $rows = $wpdb->get_results( $sql ); // phpcs:ignore
        $cache[ $ckey ] = $rows ? $rows : array();
        return $cache[ $ckey ];
}
// TPP_SALARY GUARD END (tpp_salary_get_fields)
}

/*
 * نسخه 1.7.3: با هر تغییر فیلدها (افزودن/ویرایش/حذف/فعال‌سازی از تنظیمات و
 * هر مسیر دیگر) نسخه بالا می‌رود و کش tpp_salary_get_fields باطل می‌شود.
 */
if ( ! function_exists( 'tpp_salary_bump_fields_version' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_bump_fields_version() {
        $GLOBALS['tpp_salary_fields_ver'] = ( isset( $GLOBALS['tpp_salary_fields_ver'] ) ? (int) $GLOBALS['tpp_salary_fields_ver'] : 0 ) + 1;
}
// TPP_SALARY GUARD END (tpp_salary_bump_fields_version)
}
if ( function_exists( 'add_action' ) ) {
        add_action( 'tpp_salary_fields_changed', 'tpp_salary_bump_fields_version', 5 );
}

/**
 * کلید فیلدهای «فقط‌پروفایلی» — هرگز در فرم ثبت حقوق/نمونه رکورد/گزارش رکوردی
 *
 * نسخه 1.5.0 — سپر دوم: علاوه بر پرچم in_record دیتابیس، این فیلدها در همه
 * فیلترهای فرم ثبت/نمونه/گزارش به صورت هاردکد حذف می‌شوند تا حتی اگر به هر
 * دلیلی (مهاجرت ناقص، دستکاری دستی دیتابیس و…) پرچم اشتباه شود، عنوان شغلی،
 * نوع خودرو و پلاک خودرو هرگز در صفحه ثبت حقوق نمایش داده نشوند.
 *
 * @return array<string>
 */
if ( ! function_exists( 'tpp_salary_profile_only_keys' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_profile_only_keys() {
        return array( 'full_name', 'job_title', 'vehicle_type', 'vehicle_plate' );
}
// TPP_SALARY GUARD END (tpp_salary_profile_only_keys)
}

/**
 * آیا فیلد در فرم ثبت حقوق نمایش داده می‌شود؟
 *
 * ترکیب پرچم in_record دیتابیس + سپر هاردکد فیلدهای فقط‌پروفایلی.
 *
 * @param object $f ردیف فیلد.
 * @return bool
 */
if ( ! function_exists( 'tpp_salary_field_in_record' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_field_in_record( $f ) {
        if ( isset( $f->in_record ) && ! (int) $f->in_record ) {
                return false;
        }
        return ! in_array( (string) $f->field_key, tpp_salary_profile_only_keys(), true );
}
// TPP_SALARY GUARD END (tpp_salary_field_in_record)
}

/**
 * فیلدهای پروفایل کارمند (که در پروفایل هر کارمند ثبت می‌شوند)
 *
 * @return array<int,object>
 */
if ( ! function_exists( 'tpp_salary_get_profile_fields' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_get_profile_fields() {
        return tpp_salary_get_fields( array( 'profile' => 1 ) );
}
// TPP_SALARY GUARD END (tpp_salary_get_profile_fields)
}

/**
 * پروفایل کارمند
 *
 * @param int $user_id شناسه کاربر.
 * @return array
 */
if ( ! function_exists( 'tpp_salary_get_profile' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_get_profile( $user_id ) {
        $profile = get_user_meta( $user_id, 'tpp_salary_employee_profile', true );
        if ( ! is_array( $profile ) ) {
                $profile = array();
        }
        if ( ! isset( $profile['centers'] ) || ! is_array( $profile['centers'] ) ) {
                $profile['centers'] = array();
        }
        if ( ! isset( $profile['bank_accounts'] ) || ! is_array( $profile['bank_accounts'] ) ) {
                $profile['bank_accounts'] = array();
        }
        return $profile;
}
// TPP_SALARY GUARD END (tpp_salary_get_profile)
}

/**
 * ذخیره پروفایل کارمند
 *
 * @param int   $user_id شناسه کاربر.
 * @param array $profile آرایه پروفایل.
 * @return bool
 */
if ( ! function_exists( 'tpp_salary_save_profile' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_save_profile( $user_id, $profile ) {
        return update_user_meta( $user_id, 'tpp_salary_employee_profile', $profile );
}
// TPP_SALARY GUARD END (tpp_salary_save_profile)
}

/**
 * کاربران کارمند فعال
 *
 * نسخه 1.6.0 — کارمندانی که «قطع همکاری» شده‌اند (متا tpp_salary_terminated)
 * به‌صورت پیش‌فرض از خروجی حذف می‌شوند تا در لیست‌های حقوق، فرم‌های ثبت حقوق،
 * گزارش‌ها و فیش‌ها ظاهر نشوند؛ صفحه مدیریت کارمندان و تطبیق ورود گروهی با
 * پارامتر $include_terminated همه را می‌بینند.
 *
 * @param int|null $center_id          صافی مرکز.
 * @param bool     $include_terminated کارمندان قطع‌همکاری هم برگردند؟
 * @return array<int,WP_User>
 */
if ( ! function_exists( 'tpp_salary_get_employees' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_get_employees( $center_id = null, $include_terminated = false ) {
        $args  = array(
                'role'    => 'tpp_salary_employee',
                'orderby' => 'display_name',
                'order'   => 'ASC',
                'number'  => -1,
        );
        $users = get_users( $args );
        if ( ! $include_terminated ) {
                $users = array_values( array_filter( $users, function ( $u ) {
                        return ! tpp_salary_is_terminated( $u->ID );
                } ) );
        }
        if ( null === $center_id ) {
                return $users;
        }
        $out = array();
        foreach ( $users as $u ) {
                $profile = tpp_salary_get_profile( $u->ID );
                if ( in_array( (int) $center_id, array_map( 'intval', $profile['centers'] ), true ) ) {
                        $out[] = $u;
                }
        }
        return $out;
}
// TPP_SALARY GUARD END (tpp_salary_get_employees)
}

/**
 * وضعیت قطع همکاری کارمند — نسخه 1.6.0
 *
 * @param int $user_id شناسه کاربر.
 * @return bool
 */
if ( ! function_exists( 'tpp_salary_is_terminated' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_is_terminated( $user_id ) {
        return '1' === (string) get_user_meta( (int) $user_id, 'tpp_salary_terminated', true );
}
// TPP_SALARY GUARD END (tpp_salary_is_terminated)
}

/**
 * آیا کاربر جاری اجازه مدیریت حقوق دارد؟
 *
 * @return bool
 */
if ( ! function_exists( 'tpp_salary_can_manage' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_can_manage() {
        return current_user_can( 'tpp_salary_manage' );
}
// TPP_SALARY GUARD END (tpp_salary_can_manage)
}

/**
 * دریافت رکورد حقوق
 *
 * @param int $id شناسه رکورد.
 * @return object|null
 */
if ( ! function_exists( 'tpp_salary_get_record' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_get_record( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpp_salary_records';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore
}
// TPP_SALARY GUARD END (tpp_salary_get_record)
}

/**
 * دریافت رکورد حقوق یک کارمند در دوره/مرکز مشخص
 *
 * @param int $user_id   کاربر.
 * @param int $center_id مرکز.
 * @param int $jyear     سال.
 * @param int $jmonth    ماه.
 * @return object|null
 */
if ( ! function_exists( 'tpp_salary_get_record_period' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_get_record_period( $user_id, $center_id, $jyear, $jmonth ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpp_salary_records';
        return $wpdb->get_row(
                $wpdb->prepare(
                        "SELECT * FROM {$table} WHERE user_id = %d AND center_id = %d AND jyear = %d AND jmonth = %d", // phpcs:ignore
                        $user_id,
                        $center_id,
                        $jyear,
                        $jmonth
                )
        );
}
// TPP_SALARY GUARD END (tpp_salary_get_record_period)
}

/**
 * رکوردهای یک دوره و مرکز
 *
 * @param int      $jyear     سال.
 * @param int      $jmonth    ماه.
 * @param int|null $center_id مرکز (null = همه).
 * @return array<int,object>
 */
if ( ! function_exists( 'tpp_salary_get_period_records' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_get_period_records( $jyear, $jmonth, $center_id = null ) {
        global $wpdb;
        $table = $wpdb->prefix . 'tpp_salary_records';
        if ( $center_id ) {
                return $wpdb->get_results( // phpcs:ignore
                        $wpdb->prepare(
                                "SELECT * FROM {$table} WHERE jyear = %d AND jmonth = %d AND center_id = %d ORDER BY id ASC", // phpcs:ignore
                                $jyear,
                                $jmonth,
                                $center_id
                        )
                );
        }
        return $wpdb->get_results( // phpcs:ignore
                $wpdb->prepare(
                        "SELECT * FROM {$table} WHERE jyear = %d AND jmonth = %d ORDER BY id ASC", // phpcs:ignore
                        $jyear,
                        $jmonth
                )
        );
}
// TPP_SALARY GUARD END (tpp_salary_get_period_records)
}

/**
 * پیلود رکورد به آرایه
 *
 * @param object $record رکورد.
 * @return array
 */
if ( ! function_exists( 'tpp_salary_record_payload' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_record_payload( $record ) {
        $payload = json_decode( (string) ( $record ? $record->payload : '' ), true );
        return is_array( $payload ) ? $payload : array();
}
// TPP_SALARY GUARD END (tpp_salary_record_payload)
}

/**
 * محاسبه کل مقادیر رکورد — فیلدهای محاسباتی خودکار محاسبه می‌شوند مگر آنکه
 * مقدار ارسالی کاربر با حاصل فرمول تفاوت داشته باشد (ویرایش دستی).
 *
 * @param array $values  مقادیر ورودی (کلید => مقدار).
 * @param array $manual  کلیدهایی که قطعا دستی هستند (اختیاری).
 * @param array $fields  فیلدهای فعال (اختیاری؛ در صورت خالی بودن از دیتابیس خوانده می‌شود).
 * @return array آرایه (values => مقادیر نهایی، manual => کلیدهای دستی).
 */
if ( ! function_exists( 'tpp_salary_compute_values' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_compute_values( $values, $manual = array(), $fields = array(), $force = array() ) {
        $fields = $fields ? $fields : tpp_salary_get_fields();
        $values = array_map( 'floatval', $values );
        $manual = array_map( 'strval', (array) $manual );
        $force  = array_map( 'strval', (array) $force );

        /*
         * فرمول‌های سراسری تنظیمات (gross/insurable/net) به‌عنوان مکمل فیلدهایی
         * که فرمول ذخیره‌شده ندارند استفاده می‌شوند — پیش‌تر این فرمول‌ها فقط در
         * تنظیمات نمایش داده می‌شدند و «هیچ‌جا اعمال نمی‌شدند».
         */
        $settings = tpp_salary_get_settings();
        $global   = isset( $settings['formulas'] ) && is_array( $settings['formulas'] ) ? $settings['formulas'] : array();

        foreach ( $fields as $f ) {
                if ( empty( $f->is_calculated ) ) {
                        continue;
                }
                $formula = ( '' !== (string) $f->formula ) ? (string) $f->formula : ( isset( $global[ $f->field_key ] ) ? (string) $global[ $f->field_key ] : '' );
                if ( '' === trim( $formula ) ) {
                        continue;
                }
                $posted = isset( $values[ $f->field_key ] ) ? (float) $values[ $f->field_key ] : 0.0;
                if ( in_array( $f->field_key, $manual, true ) ) {
                        continue;
                }
                $val = TppSalary_Formula::evaluate( $formula, $values );
                if ( is_wp_error( $val ) ) {
                        continue;
                }
                // گرد کردن: مثبت‌ها نرمال، منفی‌ها (کسورات) به سمت صفر — مطابق رویه نمونه‌های محاسباتی.
                $val = $val < 0 ? ceil( (float) $val ) : round( (float) $val );
                if ( in_array( $f->field_key, $force, true ) ) {
                        // اجبار محاسبه (مثل سلول «—» در ورود گروهی) — بدون بررسی ویرایش دستی.
                        $values[ $f->field_key ] = $val;
                        continue;
                }
                if ( abs( $val - $posted ) > 0.5 ) {
                        // کاربر مقدار را دستی ویرایش کرده است.
                        $manual[] = $f->field_key;
                        continue;
                }
                $values[ $f->field_key ] = $val;
        }

        // گرد کردن نهایی همه اعداد.
        foreach ( $values as $k => $v ) {
                $values[ $k ] = $v < 0 ? ceil( (float) $v ) : round( (float) $v );
        }
        return array( 'values' => $values, 'manual' => array_values( array_unique( $manual ) ) );
}
// TPP_SALARY GUARD END (tpp_salary_compute_values)
}

/**
 * آدرس لوگوی شرکت (فایل فیزیکی)
 *
 * @return string|null
 */
if ( ! function_exists( 'tpp_salary_logo_path' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_logo_path() {
        $logo_id = (int) tpp_salary_get_setting( 'logo_id', 0 );
        if ( ! $logo_id ) {
                return null;
        }
        $path = get_attached_file( $logo_id );
        return ( $path && file_exists( $path ) ) ? $path : null;
}
// TPP_SALARY GUARD END (tpp_salary_logo_path)
}

/**
 * آدرس پوشه بکاپ‌ها
 *
 * @return string
 */
if ( ! function_exists( 'tpp_salary_backup_dir' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_backup_dir() {
        $dir = wp_upload_dir();
        return trailingslashit( $dir['basedir'] ) . 'tpp-backups';
}
// TPP_SALARY GUARD END (tpp_salary_backup_dir)
}

/**
 * تاریخ شمسی امروز به صورت رشته
 *
 * @return string
 */
if ( ! function_exists( 'tpp_salary_today_fa' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_today_fa() {
        $t = TppSalary_Jalali::today();
        return TppSalary_Jalali::period_label( $t[0], $t[1] ) . '، ' . TppSalary_Jalali::digits_fa( $t[2] );
}
// TPP_SALARY GUARD END (tpp_salary_today_fa)
}

if ( ! function_exists( 'tpp_salary_clean_output' ) ) {
        /**
         * پاک‌سازی همه بافرهای خروجی پیش از ارسال فایل (xlsx / PDF / ZIP / JSON)
         *
         * اگر افزونه دیگری (یا یک PHP Notice) قبل از ما خروجی چاپ کرده باشد،
         * محتوای فایل دانلودی خراب می‌شود (اکسل «معیوب» باز می‌شود).
         * این تابع همه بافرهای باز را بسته و خروجی جمع‌شده را دور می‌ریزد تا
         * فایل دانلودی همیشه بایت‌به‌بایت همان خروجی موتور باشد.
         *
         * @return void
         */
        function tpp_salary_clean_output() {
                while ( ob_get_level() > 0 ) {
                        ob_end_clean();
                }
        }
}

/**
 * تعداد ردیف هر صفحه در فهرست‌ها و نتایج جستجو — نسخه 1.6.1
 *
 * @return int
 */
if ( ! function_exists( 'tpp_salary_list_per_page' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_list_per_page() {
        return max( 1, (int) tpp_salary_get_setting( 'per_page_list', 20 ) );
}
// TPP_SALARY GUARD END (tpp_salary_list_per_page)
}

/**
 * شماره صفحه جاری از درخواست — نسخه 1.6.1
 *
 * @return int
 */
if ( ! function_exists( 'tpp_salary_current_paged' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_current_paged() {
        $p = isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return max( 1, $p );
}
// TPP_SALARY GUARD END (tpp_salary_current_paged)
}

/**
 * لینک‌های صفحه‌بندی مشترک — نسخه 1.6.1
 *
 * در همه فهرست‌ها و «نتایج جستجو» استفاده می‌شود؛ همه پارامترهای فعلی
 * URL (جستجو، فیلتر سال/ماه/مرکز و…) در لینک صفحات حفظ می‌شوند.
 *
 * @param int $total_rows تعداد کل ردیف‌ها.
 * @param int $per_page   تعداد در هر صفحه.
 * @return void
 */
if ( ! function_exists( 'tpp_salary_pagination' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_pagination( $total_rows, $per_page = 0 ) {
        $per_page = $per_page > 0 ? $per_page : tpp_salary_list_per_page();
        $total    = max( 0, (int) $total_rows );
        $pages    = max( 1, (int) ceil( $total / $per_page ) );
        if ( $pages <= 1 ) {
                return;
        }
        $paged = tpp_salary_current_paged();
        $paged = min( $paged, $pages );

        // حفظ همه پارامترهای فعلی به‌جز paged (جستجو/فیلترها).
        // add_query_arg خودش مقادیر را encode می‌کند.
        $args = array();
        foreach ( $_GET as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                if ( 'paged' === $k || is_array( $v ) || strlen( $k ) > 64 ) {
                        continue;
                }
                $args[ $k ] = (string) $v;
        }
        $link = function ( $p ) use ( $args ) {
                $args['paged'] = $p;
                return esc_url( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        };

        // پنجره نمایش شماره‌ها (حداکثر ۹ لینک + اول/آخر).
        $start = max( 1, $paged - 4 );
        $end   = min( $pages, $start + 8 );
        $start = max( 1, $end - 8 );

        echo '<div class="tablenav" style="margin:10px 0"><div class="tablenav-pages" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">';
        echo '<span class="displaying-num">' . esc_html( TppSalary_Jalali::digits_fa( $total ) ) . ' ردیف — صفحه ' . esc_html( TppSalary_Jalali::digits_fa( $paged ) ) . ' از ' . esc_html( TppSalary_Jalali::digits_fa( $pages ) ) . '</span>';
        if ( $paged > 1 ) {
                echo '<a class="button button-small" href="' . $link( $paged - 1 ) . '">« قبلی</a>'; // phpcs:ignore
        }
        if ( $start > 1 ) {
                echo '<a class="button button-small" href="' . $link( 1 ) . '">' . esc_html( TppSalary_Jalali::digits_fa( 1 ) ) . '</a>'; // phpcs:ignore
                if ( $start > 2 ) {
                        echo '<span>…</span>';
                }
        }
        for ( $i = $start; $i <= $end; $i++ ) {
                if ( $i === $paged ) {
                        echo '<span class="button button-primary button-small">' . esc_html( TppSalary_Jalali::digits_fa( $i ) ) . '</span>';
                } else {
                        echo '<a class="button button-small" href="' . $link( $i ) . '">' . esc_html( TppSalary_Jalali::digits_fa( $i ) ) . '</a>'; // phpcs:ignore
                }
        }
        if ( $end < $pages ) {
                if ( $end < $pages - 1 ) {
                        echo '<span>…</span>';
                }
                echo '<a class="button button-small" href="' . $link( $pages ) . '">' . esc_html( TppSalary_Jalali::digits_fa( $pages ) ) . '</a>'; // phpcs:ignore
        }
        if ( $paged < $pages ) {
                echo '<a class="button button-small" href="' . $link( $paged + 1 ) . '">بعدی »</a>'; // phpcs:ignore
        }
        echo '</div></div>';
}
// TPP_SALARY GUARD END (tpp_salary_pagination)
}

/**
 * پاک‌سازی آرایه شناسه‌های انتخاب‌شده در حذف گروهی — نسخه 1.6.2
 *
 * ورودی کاربر (checkbox های ids[]) را به فهرست امن int های مثبت و یکتا تبدیل می‌کند.
 *
 * @param mixed $raw ورودی خام (آرایه یا رشته).
 * @return int[]
 */
if ( ! function_exists( 'tpp_salary_ids_from_request' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_ids_from_request( $raw ) {
        $ids = is_array( $raw ) ? $raw : array();
        // فقط اعداد مثبت (اعداد صحیح/عدد یا رشته عددی) — بقیه (منفی/غیرعددی/صفر) دور ریخته می‌شود.
        $ids = array_filter(
                $ids,
                function ( $v ) {
                        return is_numeric( $v ) && (float) $v > 0;
                }
        );
        $ids = array_map( 'absint', $ids );
        $ids = array_filter( $ids ); // جبران احتیاطی (مثلاً رشته‌های اعشاری کوچک‌تر از ۱).
        return array_values( array_unique( $ids ) );
}
// TPP_SALARY GUARD END (tpp_salary_ids_from_request)
}

/**
 * اسکریپت مشترک جدول‌های «اقدام گروهی» — نسخه 1.6.2
 *
 * دو کار انجام می‌دهد:
 *  ۱) چک‌باکس سربرگ (.tpp-cb-all) همه چک‌باکس‌های سطرها (.tpp-cb) را علامت می‌زند/برمی‌دارد.
 *  ۲) پیش از ارسال فرم بررسی می‌کند که هم ردیفی انتخاب شده و هم اقدامی برگزیده شده است،
 *     سپس پنجره تأیید حذف نمایش می‌دهد (دفاع دوم؛ هندلر PHP هم مستقل اعتبارسنجی می‌کند).
 *
 * در همه فهرست‌های دارای حذف گروهی (حقوق‌ها/کارمندان/مراکز) استفاده می‌شود؛
 * هر فرم با attribute های data-tpp-bulk و data-tpp-bulk-action شناسایی می‌گردد.
 * اسکریپت idempotent است؛ در هر رندر صفحه (یک فهرست) یک‌بار فراخوانی می‌شود.
 *
 * @param string $confirm_text متن پنجره تأیید حذف گروهی.
 * @return void
 */
if ( ! function_exists( 'tpp_salary_bulk_table_script' ) ) {
        // TPP_SALARY GUARD: جلوگیری از Cannot redeclare function
function tpp_salary_bulk_table_script( $confirm_text ) {
        $confirm = esc_js( (string) $confirm_text );
        ?>
        <script>
        (function () {
                function tppBulkReady(fn) {
                        if ( 'loading' !== document.readyState ) { fn(); }
                        else { document.addEventListener( 'DOMContentLoaded', fn ); }
                }
                tppBulkReady( function () {
                        var forms = document.querySelectorAll( 'form[data-tpp-bulk]' );
                        Array.prototype.forEach.call( forms, function ( form ) {
                                var all = form.querySelector( '.tpp-cb-all' );
                                if ( all ) {
                                        all.addEventListener( 'change', function () {
                                                var boxes = form.querySelectorAll( '.tpp-cb' );
                                                Array.prototype.forEach.call( boxes, function ( cb ) { cb.checked = all.checked; } );
                                        } );
                                }
                                form.addEventListener( 'submit', function ( ev ) {
                                        var any = form.querySelectorAll( '.tpp-cb:checked' ).length;
                                        var sel = form.querySelector( '[data-tpp-bulk-action]' );
                                        var act = sel ? sel.value : '';
                                        if ( ! act && ! any ) { ev.preventDefault(); return; }
                                        if ( ! any ) { ev.preventDefault(); window.alert( 'حداقل یک ردیف را انتخاب کنید.' ); return; }
                                        if ( ! act ) { ev.preventDefault(); window.alert( 'ابتدا یک اقدام گروهی انتخاب کنید.' ); return; }
                                        if ( ! window.confirm( '<?php echo $confirm; // phpcs:ignore WordPress.Security.EscapeOutput ?>' ) ) { ev.preventDefault(); }
                                } );
                        } );
                } );
        } )();
        </script>
        <?php
}
// TPP_SALARY GUARD END (tpp_salary_bulk_table_script)
}
