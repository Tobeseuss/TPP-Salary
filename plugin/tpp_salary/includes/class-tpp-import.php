<?php
/**
 * ورود گروهی اطلاعات کارمندان از فایل اکسل — با ساخت خودکار کاربر وردپرس
 *
 * @package TPP_Salary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class TPP_Import
 */
if ( ! class_exists( 'TPP_Import' ) ) {
        // TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TPP_Import {

        /**
         * نگاشت عنوان ستون‌های فارسی به کلید فیلدها
         *
         * @return array
         */
        private static function header_map() {
                $normalize = function ( $s ) {
                        $s = TPP_Jalali::digits_en( (string) $s );
                        $s = str_replace( array( "\u{200C}", 'ی', 'ي', 'ك' ), array( '', 'ی', 'ی', 'ک' ), $s );
                        $s = preg_replace( '/[\s_\-\.:،()]+/u', '', $s );
                        return trim( $s );
                };
                $map = array();
                $add = function ( $keys, $field ) use ( &$map, $normalize ) {
                        foreach ( (array) $keys as $k ) {
                                $map[ $normalize( $k ) ] = $field;
                        }
                };
                $add( array( 'نام و نام خانوادگی', 'نام و نام‌خانوادگی', 'نام خانوادگی', 'نام' ), 'display_name' );
                $add( array( 'کد ملی', 'کدملی', 'کد ملی ' ), 'national_id' );
                $add( array( 'شماره همراه', 'موبایل', 'تلفن همراه', 'همراه' ), 'mobile' );
                $add( array( 'ایمیل' ), 'email' );
                $add( array( 'تعداد فرزند', 'فرزند' ), 'children_count' );
                $add( array( 'گروه اصلی', 'گروه اصلی بیمه', 'گروه بیمه' ), 'insurance_group' );
                $add( array( 'دستمزد روزانه مرجع', 'دستمزد روزانه', 'دستمزد' ), 'daily_wage' );
                $add( array( 'پایه سنوات', 'سنوات' ), 'seniority' );
                $add( array( 'مبلغ هر ساعت اضافه کاری', 'هر ساعت اضافه کاری', 'اضافه کاری' ), 'overtime_rate' );
                $add( array( 'مبلغ تعطیل کاری', 'تعطیل کاری' ), 'holiday_rate' );
                $add( array( 'نرخ درصد بیمه', 'نرخ بیمه', 'درصد بیمه' ), 'insurance_rate' );
                $add( array( 'حق اولاد هر فرزند', 'حق اولاد' ), 'child_allowance_rate' );
                $add( array( 'حق مسکن', 'مسکن' ), 'housing' );
                $add( array( 'حق بن', 'بن' ), 'food' );
                $add( array( 'حق تأهل', 'حق تاهل', 'تأهل' ), 'marriage' );
                $add( array( 'جریمه غیبت روزانه', 'جریمه غیبت' ), 'absence_rate' );
                $add( array( 'عنوان شغلی', 'شغل' ), 'job_title' );
                $add( array( 'نوع خودرو', 'خودرو' ), 'vehicle_type' );
                $add( array( 'پلاک خودرو', 'پلاک' ), 'vehicle_plate' );
                $add( array( 'کمک هزینه ایاب و ذهاب', 'ایاب و ذهاب' ), 'commute' );
                $add( array( 'حقوق مشمول بیمه', 'مشمول بیمه' ), 'insurable_default' );
                $add( array( 'مرکز', 'مراکز', 'پروژه', 'کارگاه' ), 'centers' );
                return $map;
        }

        /**
         * مقداردهی اولیه
         *
         * @return void
         */
        public static function init() {
                add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
                add_action( 'admin_post_tpp_import_employees', array( __CLASS__, 'handle' ) );
        }

        /**
         * منو
         *
         * @return void
         */
        public static function menu() {
                add_submenu_page(
                        'tpp-salary',
                        'ورود گروهی اطلاعات',
                        'ورود گروهی',
                        'tpp_import_salary',
                        'tpp-import',
                        array( __CLASS__, 'render' )
                );
        }

        /**
         * رندر صفحه
         *
         * @return void
         */
        public static function render() {
                if ( ! current_user_can( 'tpp_import_salary' ) ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                $results = get_transient( 'tpp_import_results_' . get_current_user_id() );
                ?>
                <div class="wrap tpp-wrap" dir="rtl">
                        <h1>ورود گروهی اطلاعات کارمندان</h1>
                        <p class="description">فایل اکسل با سطر اول حاوی عناوین ستون‌ها آپلود شود. عناوین شناخته‌شده: نام و نام خانوادگی، کد ملی، شماره همراه، تعداد فرزند، گروه اصلی، دستمزد روزانه مرجع، پایه سنوات، مبلغ هر ساعت اضافه کاری، مبلغ تعطیل کاری، نرخ درصد بیمه، حق اولاد هر فرزند، حق مسکن، حق بن، حق تأهل، جریمه غیبت روزانه، عنوان شغلی، نوع خودرو، پلاک خودرو، کمک هزینه ایاب و ذهاب، حقوق مشمول بیمه، مرکز/مراکز (با ، جدا شود).</p>
                        <p class="description"><strong>ساخت خودکار کاربر:</strong> اگر کاربر وردپرسی با آن کد ملی وجود نداشته باشد، کاربر جدیدی با نام کاربری و رمز عبور برابر همان کد ملی ساخته می‌شود؛ در نبود کد ملی، نام کاربری/رمز تصادفی ۱۰ رقمی ساخته می‌شود.</p>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                                <?php wp_nonce_field( 'tpp_import_employees' ); ?>
                                <input type="hidden" name="action" value="tpp_import_employees">
                                <p><input type="file" name="import_file" accept=".xlsx,.csv" required></p>
                                <p>
                                        <label><input type="checkbox" name="dry_run" value="1"> اجرای آزمایشی (بدون ذخیره — فقط نمایش نتیجه)</label>
                                </p>
                                <?php submit_button( 'شروع ورود اطلاعات', 'primary' ); ?>
                        </form>

                        <?php if ( is_array( $results ) ) : ?>
                                <h2>نتیجه ورود اطلاعات</h2>
                                <p>موفق: <strong><?php echo (int) $results['ok']; ?></strong> — خطا: <strong><?php echo (int) $results['fail']; ?></strong> — کاربر جدید: <strong><?php echo (int) $results['created']; ?></strong></p>
                                <table class="widefat striped">
                                        <thead><tr><th>سطر</th><th>وضعیت</th><th>پیام</th></tr></thead>
                                        <tbody>
                                        <?php foreach ( $results['log'] as $row ) : ?>
                                                <tr>
                                                        <td><?php echo (int) $row['row']; ?></td>
                                                        <td><?php echo esc_html( $row['status'] ); ?></td>
                                                        <td><?php echo esc_html( $row['message'] ); ?></td>
                                                </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                </table>
                        <?php endif; ?>
                </div>
                <?php
                if ( $results ) {
                        delete_transient( 'tpp_import_results_' . get_current_user_id() );
                }
        }

        /**
         * پردازش آپلود و ورود اطلاعات
         *
         * @return void
         */
        public static function handle() {
                if ( ! current_user_can( 'tpp_import_salary' ) ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_import_employees' );
                if ( empty( $_FILES['import_file']['tmp_name'] ) ) {
                        wp_die( 'فایلی آپلود نشده است' );
                }
                $file    = sanitize_text_field( wp_unslash( $_FILES['import_file']['tmp_name'] ) );
                $name    = sanitize_text_field( wp_unslash( $_FILES['import_file']['name'] ) );
                $dry_run = ! empty( $_POST['dry_run'] );

                if ( preg_match( '/\.csv$/i', $name ) ) {
                        $rows = array();
                        $fh   = fopen( $file, 'r' ); // phpcs:ignore
                        if ( $fh ) {
                                while ( ( $line = fgetcsv( $fh ) ) !== false ) { // phpcs:ignore
                                        $rows[] = array_map(
                                                function ( $v ) {
                                                        return function_exists( 'mb_convert_encoding' ) ? mb_convert_encoding( $v, 'UTF-8', 'UTF-8' ) : $v;
                                                },
                                                $line
                                        );
                                }
                                fclose( $fh ); // phpcs:ignore
                        }
                } else {
                        $rows = TPP_Xlsx_Reader::read( $file );
                }
                if ( is_wp_error( $rows ) ) {
                        wp_die( esc_html( $rows->get_error_message() ) );
                }
                if ( count( $rows ) < 2 ) {
                        wp_die( 'فایل فاقد داده است (سطر عنوان + حداقل یک سطر داده لازم است)' );
                }

                $header_map = self::header_map();
                $normalize  = function ( $s ) {
                        $s = TPP_Jalali::digits_en( (string) $s );
                        $s = str_replace( array( "\u{200C}", 'ي', 'ك' ), array( '', 'ی', 'ک' ), $s );
                        $s = preg_replace( '/[\s_\-\.:،()]+/u', '', $s );
                        return trim( $s );
                };

                $headers = array_shift( $rows );
                $col_map = array();
                foreach ( $headers as $i => $title ) {
                        $key = (isset($header_map[ $normalize( $title ) ] )?$header_map[ $normalize( $title ) ] : null);
                        if ( $key ) {
                                $col_map[ $i ] = $key;
                        }
                }
                if ( empty( $col_map['display_name'] ) ) {
                        wp_die( 'ستون «نام و نام خانوادگی» یافت نشد' );
                }

                $centers = tpp_get_centers();
                $center_by_name = array();
                foreach ( $centers as $c ) {
                        $center_by_name[ $normalize( $c->name ) ] = (int) $c->id;
                }

                $ok = 0;
                $fail = 0;
                $created = 0;
                $log = array();
                $profile_fields = tpp_get_profile_fields();

                foreach ( $rows as $ri => $row ) {
                        $data = array();
                        foreach ( $col_map as $i => $key ) {
                                $data[ $key ] = isset( $row[ $i ] ) ? trim( $row[ $i ] ) : '';
                        }
                        $display_name = (isset($data['display_name'] )?$data['display_name'] : '');
                        if ( '' === $display_name ) {
                                $fail++;
                                $log[] = array( 'row' => $ri + 2, 'status' => 'خطا', 'message' => 'نام خالی است' );
                                continue;
                        }

                        $national = TPP_Jalali::digits_en( (isset($data['national_id'] )?$data['national_id'] : '' ));
                        if ( $national && ! preg_match( '/^\d{10}$/', $national ) ) {
                                $national = '';
                        }

                        // یافتن یا ساخت کاربر.
                        $user = null;
                        if ( $national ) {
                                $existing = get_users(
                                        array(
                                                'meta_key'   => 'tpp_national_id', // phpcs:ignore
                                                'meta_value' => $national, // phpcs:ignore
                                                'number'     => 1,
                                                'fields'     => 'all',
                                        )
                                );
                                if ( ! empty( $existing ) ) {
                                        $user = $existing[0];
                                }
                        }
                        $is_new = false;
                        $new_pass = '';
                        if ( ! $user ) {
                                $username = $national ? $national : (string) wp_rand( 1000000000, 9999999999 );
                                $login    = $username;
                                $i        = 0;
                                while ( username_exists( $login ) ) {
                                        $login = $username . '_' . ( ++$i );
                                }
                                /*
                                 * امنیت: رمز عبور هرگز برابر کد ملی نیست — رمز تصادفی تولید
                                 * و در گزارش ورود گروهی فقط یک‌بار نمایش داده می‌شود.
                                 */
                                $new_pass = wp_generate_password( 12, false );
                                $user_id = wp_insert_user(
                                        array(
                                                'user_login'   => $login,
                                                'user_pass'    => $new_pass,
                                                'display_name' => $display_name,
                                                'user_email'   => sanitize_email( (isset($data['email'] )?$data['email'] : '' )),
                                                'role'         => 'tpp_Employe',
                                        )
                                );
                                if ( is_wp_error( $user_id ) ) {
                                        $fail++;
                                        $log[] = array( 'row' => $ri + 2, 'status' => 'خطا', 'message' => $user_id->get_error_message() );
                                        continue;
                                }
                                wp_new_user_notification( $user_id, null, 'admin' );
                                $user = get_user_by( 'id', $user_id );
                                $is_new = true;
                                $created++;
                        } elseif ( ! in_array( 'tpp_Employe', (array) $user->roles, true ) && ! $dry_run ) {
                                $user->add_role( 'tpp_Employe' );
                        }

                        if ( $dry_run ) {
                                $ok++;
                                $log[] = array( 'row' => $ri + 2, 'status' => 'آزمایشی', 'message' => ( $is_new ? 'کاربر جدید ساخته خواهد شد: ' : 'به‌روزرسانی پروفایل: ' ) . $display_name . ( $national ? ' (کد ملی ' . $national . ')' : ' (کد ملی ندارد)') );
                                continue;
                        }

                        // ذخیره متادیتا و پروفایل.
                        update_user_meta( $user->ID, 'tpp_national_id', $national );
                        if ( ! empty( $data['mobile'] ) ) {
                                update_user_meta( $user->ID, 'tpp_mobile', TPP_Jalali::digits_en( $data['mobile'] ) );
                        }
                        if ( $display_name ) {
                                wp_update_user(
                                        array(
                                                'ID'           => $user->ID,
                                                'display_name' => $display_name,
                                        )
                                );
                        }

                        $profile = tpp_get_profile( $user->ID );
                        foreach ( $profile_fields as $f ) {
                                $key = $f->field_key;
                                if ( ! isset( $data[ $key ] ) || '' === $data[ $key ] ) {
                                        continue;
                                }
                                $profile[ $key ] = ( 'number' === $f->field_type ) ? tpp_parse_number( $data[ $key ] ) : sanitize_text_field( $data[ $key ] );
                        }
                        // مراکز.
                        if ( ! empty( $data['centers'] ) ) {
                                $ids = array();
                                foreach ( preg_split( '/[،,]/u', $data['centers'] ) as $cn ) {
                                        $cn = $normalize( $cn );
                                        if ( '' === $cn ) {
                                                continue;
                                        }
                                        if ( isset( $center_by_name[ $cn ] ) ) {
                                                $ids[] = $center_by_name[ $cn ];
                                        }
                                }
                                if ( $ids ) {
                                        $profile['centers'] = array_values( array_unique( array_merge( array_map( 'intval', $profile['centers'] ), $ids ) ) );
                                }
                        }
                        tpp_save_profile( $user->ID, $profile );

                        $ok++;
                        $log[] = array(
                                'row' => $ri + 2,
                                'status' => $is_new ? 'کاربر جدید' : 'به‌روزرسانی',
                                'message' => $display_name . ( $national ? ' — کد ملی ' . $national : '' ) . ( $is_new ? ' — نام کاربری: ' . $user->user_login . ' — رمز عبور: ' . $new_pass . ' (فقط همین یک‌بار نمایش داده می‌شود)' : '' ),
                        );
                }

                set_transient(
                        'tpp_import_results_' . get_current_user_id(),
                        array(
                                'ok'      => $ok,
                                'fail'    => $fail,
                                'created' => $created,
                                'log'     => $log,
                        ),
                        300
                );
                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-import', 'done' => '1' ), admin_url( 'admin.php' ) ) );
                exit;
        }
}
}
// TPP_SALARY GUARD END
