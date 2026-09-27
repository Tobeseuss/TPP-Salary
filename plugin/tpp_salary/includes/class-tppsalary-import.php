<?php
/**
 * ورود گروهی اطلاعات کارمندان از فایل اکسل — با ساخت خودکار کاربر وردپرس
 *
 * @package TppSalary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class TppSalary_Import
 */
if ( ! class_exists( 'TppSalary_Import' ) ) {
        // TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TppSalary_Import {

        /**
         * یکسان‌سازی متن برای تطبیق عناوین ستون‌ها/نام‌ها
         *
         * نسخه 1.4.1 — نرمال‌سازی تهاجمی: هر کاراکتری غیر از «حرف یا رقم» حذف
         * می‌شود (فاصله، نیم‌فاصله، نشان‌های جهت LRM/RLM، علائم نقل‌قول «»،
         * نقطه/خط تیره/پرانتز/کاما و ...)، حروف عربی به فارسی یکسان و ارقام
         * فارسی/عربی به لاتین تبدیل می‌شوند. به این ترتیب عنوان‌هایی مثل
         * «نام و نام خانوادگی»، «نام‌و‌نام‌خانوادگی» یا «نام و نام‌ خانوادگی »
         * همگی به یک کلید تبدیل می‌شوند و تشخیص ستون‌ها دیگر به شکل‌ظاهری
         * متن حساس نیست.
         *
         * @param string $s متن ورودی.
         * @return string
         */
        public static function normalize_key( $s ) {
                $s = TppSalary_Jalali::digits_en( (string) $s );
                // یکسان‌سازی حروف هم‌شکل عربی/فارسی.
                $s = str_replace(
                        array( 'ي', 'ى', 'ك', 'ة', 'أ', 'إ', 'آ', 'ؤ', 'ۀ', 'ء', 'ٙ', 'َ', 'ِ', 'ُ', 'ّ', 'ْ', 'ً', 'ٌ', 'ٍ' ),
                        array( 'ی', 'ی', 'ک', 'ه', 'ا', 'ا', 'ا', 'و', 'ه', '', '', '', '', '', '', '', '', '', '' ),
                        $s
                );
                // حذف کاراکترهای کنترلی/قالبی (ZWNJ، LRM، RLM، تطویل، NBSP و ...).
                $s = preg_replace( '/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{0640}\x{00A0}\x{2007}\x{202F}\x{FEFF}]/u', '', $s );
                // فقط حروف و رقم‌ها می‌مانند — همه علائم و فاصله‌ها حذف.
                $s = preg_replace( '/[^\p{L}\p{N}]+/u', '', $s );
                return trim( $s );
        }

        /**
         * نگاشت عنوان ستون‌های فارسی به کلید فیلدها
         *
         * @return array
         */
        private static function header_map() {
                $map = array();
                $add = function ( $keys, $field ) use ( &$map ) {
                        foreach ( (array) $keys as $k ) {
                                $map[ self::normalize_key( $k ) ] = $field;
                        }
                };
                $add( array( 'نام و نام خانوادگی', 'نام و نام‌خانوادگی', 'نام خانوادگی', 'نام', 'نام کارمند', 'کارمند', 'نام کامل', 'نام و فامیل', 'فامیل' ), 'display_name' );
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
         * یافتن کارمند با نام (عادی‌سازی‌شده) در میان کاربران دارای نقش کارمندی
         *
         * نسخه 1.4.1 — جستجوی کارمند بر اساس کد ملی «یا» نام و نام خانوادگی؛
         * پیش‌تر ورود گروهی کارمندان فقط با کد ملی تطبیق می‌کرد و در نبود آن
         * همیشه کاربر تکراری ساخته می‌شد.
         *
         * @param string $name نام و نام خانوادگی (متن خام فایل).
         * @return WP_User|null
         */
        public static function find_employee_by_name( $name ) {
                $norm = self::normalize_key( $name );
                if ( '' === $norm ) {
                        return null;
                }
                /* نسخه 1.6.0: کارمندان قطع‌همکاری هم تطبیق می‌شوند (ساخت کاربر تکراری ممنوع). */
                foreach ( tpp_salary_get_employees( null, true ) as $u ) {
                        if ( self::normalize_key( $u->display_name ) === $norm ) {
                                return $u;
                        }
                        $p = tpp_salary_get_profile( $u->ID );
                        if ( ! empty( $p['full_name'] ) && self::normalize_key( $p['full_name'] ) === $norm ) {
                                return $u;
                        }
                }
                return null;
        }

        /**
         * ساخت کاربر کارمند جدید (مشترک بین ورود گروهی کارمندان/حقوق)
         *
         * @param string $display_name نام و نام خانوادگی.
         * @param string $national     کد ملی (اختیاری).
         * @param string $email        ایمیل (اختیاری).
         * @return array|WP_Error آرایه (user_id, login, pass)
         */
        public static function create_employee_user( $display_name, $national = '', $email = '' ) {
                $username = ( preg_match( '/^\d{10}$/', (string) $national ) ) ? $national : (string) wp_rand( 1000000000, 9999999999 );
                $login    = $username;
                $i        = 0;
                while ( username_exists( $login ) ) {
                        $login = $username . '_' . ( ++$i );
                }
                /* امنیت: رمز هرگز برابر کد ملی نیست — رمز تصادفی فقط یک‌بار نمایش داده می‌شود. */
                $new_pass = wp_generate_password( 12, false );
                $user_id  = wp_insert_user(
                        array(
                                'user_login'   => $login,
                                'user_pass'    => $new_pass,
                                'display_name' => $display_name ? $display_name : $login,
                                'user_email'   => sanitize_email( $email ),
                                'role'         => 'tpp_salary_employee',
                        )
                );
                if ( is_wp_error( $user_id ) ) {
                        return $user_id;
                }
                wp_new_user_notification( $user_id, null, 'admin' );
                $user = get_user_by( 'id', $user_id );
                return array(
                        'user_id' => (int) $user_id,
                        'login'   => $user ? $user->user_login : $login,
                        'pass'    => $new_pass,
                );
        }

        /**
         * مقداردهی اولیه
         *
         * @return void
         */
        public static function init() {
                add_action( 'admin_menu', array( __CLASS__, 'menu' ), 10 );
                add_action( 'admin_post_tpp_salary_import_employees', array( __CLASS__, 'handle' ) );
                add_action( 'admin_post_tpp_salary_import_records', array( __CLASS__, 'handle_records' ) );
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
                        'tpp_salary_import',
                        'tpp-salary-import',
                        array( __CLASS__, 'render' )
                );
                add_submenu_page(
                        'tpp-salary',
                        'ورود گروهی حقوق',
                        'ورود گروهی حقوق',
                        'tpp_salary_import',
                        'tpp-salary-import-records',
                        array( __CLASS__, 'render_records' )
                );
        }

        /**
         * رندر صفحه
         *
         * @return void
         */
        public static function render() {
                if ( ! current_user_can( 'tpp_salary_import' ) ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                $results = get_transient( 'tpp_salary_import_results_' . get_current_user_id() );
                ?>
                <div class="wrap tpp-wrap" dir="rtl">
                        <h1>ورود گروهی اطلاعات کارمندان</h1>
                        <p class="description">فایل اکسل با سطر اول حاوی عناوین ستون‌ها آپلود شود. عناوین شناخته‌شده: نام و نام خانوادگی، کد ملی، شماره همراه، تعداد فرزند، گروه اصلی، دستمزد روزانه مرجع، پایه سنوات، مبلغ هر ساعت اضافه کاری، مبلغ تعطیل کاری، نرخ درصد بیمه، حق اولاد هر فرزند، حق مسکن، حق بن، حق تأهل، جریمه غیبت روزانه، عنوان شغلی، نوع خودرو، پلاک خودرو، کمک هزینه ایاب و ذهاب، حقوق مشمول بیمه، مرکز/مراکز (با ، جدا شود).</p>
                        <p class="description"><strong>ساخت خودکار کاربر:</strong> اگر کاربر وردپرسی با آن کد ملی وجود نداشته باشد، کاربر جدیدی با نام کاربری و رمز عبور برابر همان کد ملی ساخته می‌شود؛ در نبود کد ملی، نام کاربری/رمز تصادفی ۱۰ رقمی ساخته می‌شود.</p>
                        <?php if ( tpp_salary_can_manage() ) : ?>
                                <p>
                                        <a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tpp_salary_sample_employees' ), 'tpp_salary_sample_employees' ) ); ?>">دانلود فایل نمونه کارمندان (xlsx)</a>
                                        <a class="button" href="<?php echo esc_url( plugins_url( 'samples/employees-sample-filled.xlsx', TPP_SALARY_FILE ) ); ?>">نمونه پرشدهٔ واقعی (۷۶ کارمند)</a>
                                </p>
                        <?php endif; ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                                <?php wp_nonce_field( 'tpp_salary_import_employees' ); ?>
                                <input type="hidden" name="action" value="tpp_salary_import_employees">
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
                        delete_transient( 'tpp_salary_import_results_' . get_current_user_id() );
                }
        }

        /**
         * پردازش آپلود و ورود اطلاعات
         *
         * @return void
         */
        public static function handle() {
                if ( ! current_user_can( 'tpp_salary_import' ) ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_salary_import_employees' );
                if ( empty( $_FILES['import_file']['tmp_name'] ) ) {
                        wp_die( 'فایلی آپلود نشده است' );
                }
                /*
                 * باگ ویندوز/XAMPP: مسیر tmp_name باید خام استفاده شود — wp_unslash
                 * بک‌اسلش‌های مسیر ویندوز (C:\xampp\tmp\phpXXXX.tmp) را حذف می‌کرد و
                 * مسیر به C:xampptmpphpXXXX.tmp تبدیل می‌شد → «فایل اکسل قابل
                 * بازکردن نیست». tmp_name توسط PHP تولید می‌شود، نه کاربر.
                 */
                $file = (string) $_FILES['import_file']['tmp_name']; // phpcs:ignore
                $name = isset( $_FILES['import_file']['name'] ) ? sanitize_text_field( (string) $_FILES['import_file']['name'] ) : ''; // phpcs:ignore
                $dry_run = ! empty( $_POST['dry_run'] );
                if ( ! is_uploaded_file( $file ) || ! is_readable( $file ) ) {
                        wp_die( 'فایل آپلودشده قابل خواندن نیست — دوباره تلاش کنید.' );
                }

                $rows = self::read_rows( $file, $name );
                if ( is_wp_error( $rows ) ) {
                        wp_die( esc_html( $rows->get_error_message() ) );
                }
                /*
                 * نسخه 1.6.0 — هسته پردازش به process_employees() منتقل شد
                 * (قابل‌تست مثل process_records) تا هر دو ورود گروهی بتوانند
                 * با فایل‌های واقعی تست یکپارچه شوند.
                 */
                $results = self::process_employees( $rows, $dry_run );
                if ( is_wp_error( $results ) ) {
                        wp_die( esc_html( $results->get_error_message() ) );
                }

                set_transient(
                        'tpp_salary_import_results_' . get_current_user_id(),
                        $results,
                        300
                );
                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-salary-import', 'done' => '1' ), admin_url( 'admin.php' ) ) );
                exit;
        }

        /**
         * نرخ درصدی از سلول — تبدیل کسر اعشاری به عدد درصد
         *
         * اکسل وقتی کاربر «٪۷» وارد کند، مقدار 0.07 (گاه با نویز ممیز شناور
         * مثل 7.0000000000000007E-2) ذخیره می‌کند؛ قرارداد افزونه «۷» یعنی ۷٪.
         * هر عدد بین 0 و 1 کسر درصد فرض و در 100 ضرب می‌شود.
         *
         * @param string $v مقدار سلول.
         * @return string عدد درصد به‌صورت رشته.
         */
        public static function parse_rate_percent( $v ) {
                $n = tpp_salary_parse_number( $v );
                if ( ! is_numeric( $n ) ) {
                        return $n;
                }
                $n = (float) $n;
                if ( $n > 0 && $n < 1 ) {
                        $n = round( $n * 100, 4 );
                }
                return (string) $n;
        }

        /**
         * پردازش سطرهای فایل کارمندان — هستهٔ قابل‌تست ورود گروهی کارمندان (نسخه 1.6.0)
         *
         * سطر اول باید عناوین ستون‌ها باشد. نگاشت عناوین با normalize_key
         * انجام می‌شود (بدون حساسیت به نیم‌فاصله، ی/ک عربی، فاصله و علائم).
         *
         * @param array $rows    سطرها (شامل سطر عنوان در ابتدا).
         * @param bool  $dry_run فقط اعتبارسنجی بدون ذخیره؟
         * @return array{ok:int, fail:int, created:int, log:array}|WP_Error
         */
        public static function process_employees( $rows, $dry_run = false ) {
                if ( count( $rows ) < 2 ) {
                        return new WP_Error( 'tpp_salary_import_empty', 'فایل فاقد داده است (سطر عنوان + حداقل یک سطر داده لازم است)' );
                }

                $header_map = self::header_map();

                $headers = array_shift( $rows );
                $col_map = array();
                $seen_titles = array();
                foreach ( $headers as $i => $title ) {
                        $norm = self::normalize_key( $title );
                        $seen_titles[] = trim( (string) $title );
                        $key = (isset($header_map[ $norm ] )?$header_map[ $norm ] : null);
                        if ( $key ) {
                                $col_map[ $i ] = $key;
                        }
                }
                /*
                 * نسخه 1.6.0 — ریشه قطعی باگ «ستون نام و نام خانوادگی یافت نشد»:
                 * col_map به ایندکس ستون کلید دارد (0،1،2 و...) و مقدارش کلید فیلد
                 * است؛ بررسی قبلی empty($col_map['display_name']) همیشه درست بود
                 * (کلید «display_name» اصلاً در ایندکس‌ها وجود ندارد) حتی وقتی
                 * نگاشت کامل و سالم انجام شده بود.
                 * نکته ظریف: باید isset باشد نه empty — چون ایندکس ستون «نام»
                 * معمولاً 0 است و empty(0) هم true می‌شود!
                 */
                $col_fields = array_flip( $col_map ); // field_key => ایندکس ستون
                if ( ! isset( $col_fields['display_name'] ) ) {
                        /* پیام تشخیصی: عناوینی که واقعاً در سطر اول خوانده شده تا کاربر ببیند مشکل از کجاست. */
                        return new WP_Error( 'tpp_salary_import_name_col', 'ستون «نام و نام خانوادگی» یافت نشد — عناوین شناسایی‌شده در سطر اول فایل شما: «' . implode( ' | ', array_slice( $seen_titles, 0, 12 ) ) . '». لطفاً ساختار فایل نمونه (تب فایل‌های نمونه) را دقیقاً رعایت کنید.' );
                }

                $centers = tpp_salary_get_centers();
                $center_by_name = array();
                foreach ( $centers as $c ) {
                        $center_by_name[ self::normalize_key( $c->name ) ] = (int) $c->id;
                }

                $ok = 0;
                $fail = 0;
                $created = 0;
                $log = array();
                $profile_fields = tpp_salary_get_profile_fields();

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

                        $national = TppSalary_Jalali::digits_en( (isset($data['national_id'] )?$data['national_id'] : '' ));
                        if ( $national && ! preg_match( '/^\d{10}$/', $national ) ) {
                                $national = '';
                        }

                        // یافتن یا ساخت کاربر — با کد ملی «یا» نام و نام خانوادگی.
                        $user = null;
                        if ( $national ) {
                                $existing = get_users(
                                        array(
                                                'meta_key'   => 'tpp_salary_national_id', // phpcs:ignore
                                                'meta_value' => $national, // phpcs:ignore
                                                'number'     => 1,
                                                'fields'     => 'all',
                                        )
                                );
                                if ( ! empty( $existing ) ) {
                                        $user = $existing[0];
                                }
                        }
                        if ( ! $user ) {
                                // نسخه 1.4.1: تطبیق با نام و نام خانوادگی (جلوگیری از ساخت کاربر تکراری).
                                $user = self::find_employee_by_name( $display_name );
                        }
                        $is_new = false;
                        $new_pass = '';
                        if ( ! $user ) {
                                /*
                                 * نسخه 1.6.0 — باگ اجرای آزمایشی: تا قبل از این نسخه
                                 * کاربر وردپرس حتی در «اجرای آزمایشی» واقعاً ساخته
                                 * می‌شد (ساخت قبل از گارد dry-run) و آزمایش رد پا
                                 * می‌گذاشت. اکنون در dry-run هیچ کاربری ساخته نمی‌شود
                                 * و فقط نتیجه پیش‌بینی می‌شود.
                                 */
                                if ( $dry_run ) {
                                        $ok++;
                                        $created++; // شمارش «کاربر جدیدی که ساخته خواهد شد».
                                        $log[] = array( 'row' => $ri + 2, 'status' => 'آزمایشی', 'message' => 'کاربر جدید ساخته خواهد شد: ' . $display_name . ( $national ? ' (کد ملی ' . $national . ')' : ' (کد ملی ندارد)') );
                                        continue;
                                }
                                $created_row = self::create_employee_user( $display_name, $national, (isset($data['email'] )?$data['email'] : '' ));
                                if ( is_wp_error( $created_row ) ) {
                                        $fail++;
                                        $log[] = array( 'row' => $ri + 2, 'status' => 'خطا', 'message' => $created_row->get_error_message() );
                                        continue;
                                }
                                /* نکته: wp_new_user_notification داخل create_employee_user فراخوانی می‌شود — ارسال دوباره حذف شد (1.5.0). */
                                $user = get_user_by( 'id', $created_row['user_id'] );
                                $is_new = true;
                                $created++;
                                $new_pass = $created_row['pass'];
                        } elseif ( ! in_array( 'tpp_salary_employee', (array) $user->roles, true ) && ! $dry_run ) {
                                $user->add_role( 'tpp_salary_employee' );
                        }

                        if ( $dry_run ) {
                                $ok++;
                                $log[] = array( 'row' => $ri + 2, 'status' => 'آزمایشی', 'message' => 'به‌روزرسانی پروفایل: ' . $display_name . ( $national ? ' (کد ملی ' . $national . ')' : ' (کد ملی ندارد)') );
                                continue;
                        }

                        // ذخیره متادیتا و پروفایل.
                        update_user_meta( $user->ID, 'tpp_salary_national_id', $national );
                        if ( ! empty( $data['mobile'] ) ) {
                                update_user_meta( $user->ID, 'tpp_salary_mobile', TppSalary_Jalali::digits_en( $data['mobile'] ) );
                        }
                        if ( $display_name ) {
                                wp_update_user(
                                        array(
                                                'ID'           => $user->ID,
                                                'display_name' => $display_name,
                                        )
                                );
                        }

                        $profile = tpp_salary_get_profile( $user->ID );
                        // نام و نام خانوادگی افزونه‌ای (برای تطبیق در ورود گروهی حقوق).
                        if ( $display_name ) {
                                $profile['full_name'] = $display_name;
                        }
                        foreach ( $profile_fields as $f ) {
                                $key = $f->field_key;
                                if ( ! isset( $data[ $key ] ) || '' === $data[ $key ] ) {
                                        continue;
                                }
                                if ( 'number' === $f->field_type ) {
                                        /* نرخ درصد بیمه: کسر اعشاری اکسل (0.07 / 7E-2) → ۷ */
                                        $profile[ $key ] = ( 'insurance_rate' === $key ) ? self::parse_rate_percent( $data[ $key ] ) : tpp_salary_parse_number( $data[ $key ] );
                                } else {
                                        $profile[ $key ] = sanitize_text_field( $data[ $key ] );
                                }
                        }
                        // مراکز.
                        if ( ! empty( $data['centers'] ) ) {
                                $ids = array();
                                foreach ( preg_split( '/[،,]/u', $data['centers'] ) as $cn ) {
                                        $cn = self::normalize_key( $cn );
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
                        tpp_salary_save_profile( $user->ID, $profile );

                        $ok++;
                        $log[] = array(
                                'row' => $ri + 2,
                                'status' => $is_new ? 'کاربر جدید' : 'به‌روزرسانی',
                                'message' => $display_name . ( $national ? ' — کد ملی ' . $national : '' ) . ( $is_new ? ' — نام کاربری: ' . $user->user_login . ' — رمز عبور: ' . $new_pass . ' (فقط همین یک‌بار نمایش داده می‌شود)' : '' ),
                        );
                }

                return array(
                        'ok'      => $ok,
                        'fail'    => $fail,
                        'created' => $created,
                        'log'     => $log,
                );
        }

        /**
         * رندر صفحه ورود گروهی حقوق‌های ثبت‌شده
         *
         * @return void
         */
        public static function render_records() {
                if ( ! current_user_can( 'tpp_salary_import' ) ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                $results = get_transient( 'tpp_salary_import_records_results_' . get_current_user_id() );
                /* نسخه 1.5.0: فقط فیلدهای فرم ثبت حقوق در توضیح ستون‌ها می‌آیند (فیلدهای فقط‌پروفایلی ستون رکورد نیستند). */
                $fields  = array_values( array_filter( tpp_salary_get_fields(), 'tpp_salary_field_in_record' ) );
                ?>
                <div class="wrap tpp-wrap" dir="rtl">
                        <h1>ورود گروهی حقوق‌های ثبت‌شده</h1>
                        <p class="description">فایل اکسل با سطر اول حاوی عناوین ستون‌ها آپلود شود. ساختار فایل دقیقاً مثل «فایل نمونه رکوردهای حقوق» است:
                                <strong>سال</strong> و <strong>ماه</strong> (عدد یا نام ماه مثل مرداد)، <strong>مرکز</strong>، <strong>نام و نام خانوادگی</strong>
                                (یا ستون اختیاری <strong>کد ملی</strong> برای شناسایی دقیق‌تر کارمند)، و سپس یک ستون برای هر فیلد فیش حقوقی:
                                <?php
                                $labels = array();
                                foreach ( $fields as $f ) {
                                        $labels[] = esc_html( $f->label );
                                }
                                echo implode( '، ', $labels );
                                ?>.
                        </p>
                        <p class="description"><strong>فیلدهای فرمولی:</strong> اگر مقدار سلول «—» یا خالی باشد، مقدار از فرمول محاسبه می‌شود؛ عدد دستی واردشده حفظ می‌شود (مثل رفتار فرم ثبت).</p>
                        <p class="description"><strong>شناسایی کارمند:</strong> ابتدا با <strong>کد ملی</strong> و سپس با <strong>نام و نام خانوادگی</strong> تطبیق داده می‌شود (بدون حساسیت به نیم‌فاصله، ی/ک عربی، فاصله‌های اضافه و علائم).</p>
                        <?php if ( tpp_salary_can_manage() ) : ?>
                                <p>
                                        <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tpp_salary_sample_records' ), 'tpp_salary_sample_records' ) ); ?>">دانلود فایل نمونه رکوردهای حقوق</a>
                                        <a class="button" href="<?php echo esc_url( plugins_url( 'samples/salary-records-sample-filled.xlsx', TPP_SALARY_FILE ) ); ?>">نمونه پرشدهٔ واقعی (۷۶ رکورد)</a>
                                </p>
                        <?php endif; ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                                <?php wp_nonce_field( 'tpp_salary_import_records' ); ?>
                                <input type="hidden" name="action" value="tpp_salary_import_records">
                                <p><input type="file" name="import_file" accept=".xlsx,.csv" required></p>
                                <p>
                                        <label><input type="checkbox" name="auto_create" value="1" checked> ساخت خودکار کارمند جدید اگر در سیستم پیدا نشود (با کد ملی یا نام سطر)</label>
                                </p>
                                <p>
                                        <label><input type="checkbox" name="dry_run" value="1"> اجرای آزمایشی (بدون ذخیره — فقط نمایش نتیجه)</label>
                                </p>
                                <?php submit_button( 'شروع ورود حقوق‌ها', 'primary' ); ?>
                        </form>

                        <?php if ( is_array( $results ) ) : ?>
                                <h2>نتیجه ورود اطلاعات</h2>
                                <p>موفق: <strong><?php echo (int) $results['ok']; ?></strong> — خطا: <strong><?php echo (int) $results['fail']; ?></strong></p>
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
                        delete_transient( 'tpp_salary_import_records_results_' . get_current_user_id() );
                }
        }

        /**
         * خواندن سطرهای فایل آپلودشده (xlsx یا csv)
         *
         * @param string $file مسیر فایل موقت آپلود.
         * @param string $name نام اصلی فایل (برای تشخیص پسوند).
         * @return array|WP_Error
         */
        private static function read_rows( $file, $name ) {
                if ( preg_match( '/\.csv$/i', (string) $name ) ) {
                        $rows = array();
                        $fh   = fopen( $file, 'r' ); // phpcs:ignore
                        if ( $fh ) {
                                while ( ( $line = fgetcsv( $fh ) ) !== false ) { // phpcs:ignore
                                        $rows[] = array_map(
                                                function ( $v ) {
                                                        return function_exists( 'mb_convert_encoding' ) ? mb_convert_encoding( (string) $v, 'UTF-8', 'UTF-8' ) : $v;
                                                },
                                                (array) $line
                                        );
                                }
                                fclose( $fh ); // phpcs:ignore
                        }
                        return $rows;
                }
                return TppSalary_Xlsx_Reader::read( $file );
        }

        /**
         * تبدیل مقدار ماه به شماره 1..12 — عدد یا نام فارسی (مرداد، آبان و ...)
         *
         * @param string $s مقدار سلول ماه.
         * @return int 0 یعنی نامعتبر.
         */
        public static function parse_month( $s ) {
                $s = trim( (string) $s );
                if ( '' === $s ) {
                        return 0;
                }
                $latin = TppSalary_Jalali::digits_en( $s );
                if ( preg_match( '/^\s*(\d{1,2})\s*$/', $latin, $m ) ) {
                        $n = (int) $m[1];
                        return ( $n >= 1 && $n <= 12 ) ? $n : 0;
                }
                $norm  = self::normalize_key( $s );
                $names = array( 1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند' );
                foreach ( $names as $i => $name ) {
                        if ( $norm === $name || 0 === strpos( $norm, $name ) ) {
                                return $i;
                        }
                }
                return 0;
        }

        /**
         * مقدار سلول «محاسباتی/خالی»؟ — خط تیره‌های رایج و سلول خالی یعنی محاسبه با فرمول
         *
         * @param string $v مقدار سلول.
         * @return bool
         */
        private static function is_blank_calc( $v ) {
                $v = trim( (string) $v );
                if ( '' === $v ) {
                        return true;
                }
                return (bool) preg_match( '/^[\x{2010}-\x{2015}\-–—ـ]+$/u', $v );
        }

        /**
         * پردازش آپلود ورود گروهی حقوق‌ها
         *
         * @return void
         */
        public static function handle_records() {
                if ( ! current_user_can( 'tpp_salary_import' ) ) {
                        wp_die( 'دسترسی غیرمجاز' );
                }
                check_admin_referer( 'tpp_salary_import_records' );
                if ( empty( $_FILES['import_file']['tmp_name'] ) ) {
                        wp_die( 'فایلی آپلود نشده است' );
                }
                /* مسیر tmp_name خام — توضیح کامل در handle(): wp_unslash مسیر ویندوز را خراب می‌کند. */
                $file = (string) $_FILES['import_file']['tmp_name']; // phpcs:ignore
                $name = isset( $_FILES['import_file']['name'] ) ? sanitize_text_field( (string) $_FILES['import_file']['name'] ) : ''; // phpcs:ignore
                $dry_run = ! empty( $_POST['dry_run'] );
                $auto_create = ! empty( $_POST['auto_create'] );
                if ( ! is_uploaded_file( $file ) || ! is_readable( $file ) ) {
                        wp_die( 'فایل آپلودشده قابل خواندن نیست — دوباره تلاش کنید.' );
                }

                $rows = self::read_rows( $file, $name );
                if ( is_wp_error( $rows ) ) {
                        wp_die( esc_html( $rows->get_error_message() ) );
                }
                if ( count( $rows ) < 2 ) {
                        wp_die( 'فایل فاقد داده است (سطر عنوان + حداقل یک سطر داده لازم است)' );
                }

                $results = self::process_records( $rows, $dry_run, $auto_create );

                set_transient(
                        'tpp_salary_import_records_results_' . get_current_user_id(),
                        $results,
                        300
                );
                wp_safe_redirect( add_query_arg( array( 'page' => 'tpp-salary-import-records', 'done' => '1' ), admin_url( 'admin.php' ) ) );
                exit;
        }

        /**
         * پردازش سطرهای فایل حقوق — هستهٔ قابل‌تست ورود گروهی حقوق
         *
         * سطر اول باید عناوین ستون‌ها باشد: سال، ماه، مرکز، نام و نام خانوادگی
         * (یا کد ملی) + یک ستون برای هر فیلد فیش. سلول «—»/خالی برای فیلدهای
         * محاسباتی یعنی محاسبه از فرمول.
         *
         * @param array $rows    سطرها (شامل سطر عنوان در ابتدا).
         * @param bool  $dry_run فقط اعتبارسنجی بدون ذخیره؟
         * @param bool  $auto_create ساخت خودکار کارمند جدید وقتی کارمند یافت نشد؟ (نسخه 1.4.1)
         * @return array{ok:int, fail:int, log:array}
         */
        public static function process_records( $rows, $dry_run = false, $auto_create = true ) {
                $headers = array_shift( $rows );

                /* نگاشت ستون‌ها: ستون‌های ثابت + هر فیلد فیش بر اساس label یا field_key */
                $col_year = $col_month = $col_center = $col_name = $col_national = null;
                $col_field = array(); // ایندکس ستون => field_key
                $fields    = tpp_salary_get_fields();
                $field_by_norm_key   = array();
                /*
                 * نسخه 1.5.0 — برچسب‌های تکراری: دو جفت فیلد برچسب یکسان دارند
                 * («مبلغ تعطیل کاری» = holiday_rate و holiday_pay؛ «حقوق مشمول
                 * بیمه» = insurable_default و insurable). نگاشت تک‌به‌تک قبلی هر
                 * دو ستون را به یک فیلد می‌برد و یک مقدار یا گم یا اشتباه ثبت
                 * می‌شد. اکنون برای هر عنوان «فهرست» فیلدها نگه داشته می‌شود و
                 * ستون‌های هم‌عنوان به ترتیب ظهور، به فیلدها به همان ترتیب
                 * تعریف (sort_order) اختصاص می‌یابند + هر فیلد فقط یک ستون.
                 */
                $field_keys_by_norm_label = array();
                foreach ( $fields as $f ) {
                        // فقط فیلدهای فرم ثبت حقوق (فیلدهای فقط‌پروفایلی مثل عنوان شغلی/خودرو ستون رکورد نیستند).
                        if ( ! tpp_salary_field_in_record( $f ) ) { // نسخه 1.5.0: سپر هاردکد فیلدهای فقط‌پروفایلی.
                                continue;
                        }
                        $field_keys_by_norm_label[ self::normalize_key( $f->label ) ][] = $f->field_key;
                        $field_by_norm_key[ self::normalize_key( $f->field_key ) ] = $f->field_key;
                }
                /*
                 * نسخه 1.4.1: مترادف‌های رایج عنوان ستون‌ها — چند فیلد پروفایل/رکورد
                 * مشترک، برچسب متفاوتی در پروفایل و فرم دارند (مثلاً «دستمزد روزانه
                 * مرجع» در پروفایل و «دستمزد روزانه» در فرم ثبت).
                 */
                $record_synonyms = array(
                        'دستمزد روزانه'       => 'daily_wage',
                        'دستمزد'              => 'daily_wage',
                        'مبلغ هر ساعت اضافه کاری' => 'overtime_rate',
                        'مبلغ تعطیل کاری'     => 'holiday_rate',
                        'حق اولاد هر فرزند'   => 'child_allowance_rate',
                        'جریمه غیبت روزانه'   => 'absence_rate',
                        'حقوق مشمول بیمه'     => 'insurable',
                );
                foreach ( $record_synonyms as $syn => $key ) {
                        $n = self::normalize_key( $syn );
                        if ( ! isset( $field_keys_by_norm_label[ $n ] ) ) {
                                $field_keys_by_norm_label[ $n ] = array( $key );
                        }
                }
                $is_calc_key = array();
                foreach ( $fields as $f ) {
                        if ( (int) $f->is_calculated ) {
                                $is_calc_key[ $f->field_key ] = true;
                        }
                }
                /*
                 * نسخه 1.5.0 — تخصیص فیلدها به ستون‌ها با ردیابی مصرف:
                 * - عنوان‌های تکراری به ترتیب ظهور ستون‌ها به فیلدهای هم‌عنوان
                 *   (به ترتیب sort_order) اختصاص می‌یابند.
                 * - هر field_key حداکثر به یک ستون نگاشت می‌شود (جلوگیری از
                 *   دوگانگی مترادف + برچسب واقعی).
                 */
                $label_seen = array(); // مکان‌یاب موقعیتی برای عنوان‌های تکراری.
                $used_keys  = array(); // field_key => مصرف‌شده.
                foreach ( $headers as $i => $title ) {
                        $norm = self::normalize_key( $title );
                        if ( '' === $norm ) {
                                continue;
                        }
                        if ( null === $col_year && in_array( $norm, array( 'سال', 'سالجلالی', 'سالمالی' ), true ) ) {
                                $col_year = $i;
                                continue;
                        }
                        if ( null === $col_month && in_array( $norm, array( 'ماه', 'ماهجلالی', 'ماهمالی' ), true ) ) {
                                $col_month = $i;
                                continue;
                        }
                        if ( null === $col_center && in_array( $norm, array( 'مرکز', 'مراکز', 'پروژه', 'کارگاه' ), true ) ) {
                                $col_center = $i;
                                continue;
                        }
                        if ( null === $col_national && in_array( $norm, array( 'کدملی' ), true ) ) {
                                $col_national = $i;
                                continue;
                        }
                        if ( null === $col_name && in_array( $norm, array( 'ناموخانوادگی', 'نامخانوادگی', 'نامونامخانوادگی', 'نام', 'کارمند', 'نامکارمند' ), true ) ) {
                                $col_name = $i;
                                continue;
                        }
                        $cand = array();
                        if ( isset( $field_keys_by_norm_label[ $norm ] ) ) {
                                $keys = $field_keys_by_norm_label[ $norm ];
                                if ( count( $keys ) > 1 ) {
                                        $c = isset( $label_seen[ $norm ] ) ? $label_seen[ $norm ] : 0;
                                        $cand = array( $keys[ min( $c, count( $keys ) - 1 ) ] );
                                        $label_seen[ $norm ] = $c + 1;
                                } else {
                                        $cand = $keys;
                                }
                        } elseif ( isset( $field_by_norm_key[ $norm ] ) ) {
                                $cand = array( $field_by_norm_key[ $norm ] );
                        }
                        foreach ( $cand as $k ) {
                                if ( ! isset( $used_keys[ $k ] ) ) {
                                        $col_field[ $i ] = $k;
                                        $used_keys[ $k ] = true;
                                        break;
                                }
                        }
                }
                if ( null === $col_year || null === $col_month ) {
                        return array( 'ok' => 0, 'fail' => 0, 'log' => array( array( 'row' => 1, 'status' => 'خطا', 'message' => 'ستون «سال» و «ماه» در سطر عنوان یافت نشد — ساختار فایل نمونه را ببینید.' ) ) );
                }
                if ( null === $col_name && null === $col_national ) {
                        return array( 'ok' => 0, 'fail' => 0, 'log' => array( array( 'row' => 1, 'status' => 'خطا', 'message' => 'ستون «نام و نام خانوادگی» یا «کد ملی» یافت نشد — عناوین شناسایی‌شده در سطر اول: «' . implode( ' | ', array_slice( array_map( 'strval', (array) $headers ), 0, 12 ) ) . '».' ) ) );
                }

                /* نگاشت مراکز و کارمندان برای تطبیق سریع */
                $center_by_name = array();
                foreach ( tpp_salary_get_centers() as $c ) {
                        $center_by_name[ self::normalize_key( $c->name ) ] = (int) $c->id;
                }
                $user_by_name = array();
                $user_by_nid  = array();
                /* نسخه 1.6.0: تطبیق شامل کارمندان قطع‌همکاری هم هست (بدون خطای «کارمند یافت نشد» / بدون کاربر تکراری). */
                foreach ( tpp_salary_get_employees( null, true ) as $u ) {
                        $user_by_name[ self::normalize_key( $u->display_name ) ] = (int) $u->ID;
                        $p = tpp_salary_get_profile( $u->ID );
                        if ( ! empty( $p['full_name'] ) ) {
                                $user_by_name[ self::normalize_key( $p['full_name'] ) ] = (int) $u->ID;
                        }
                        $nid = get_user_meta( $u->ID, 'tpp_salary_national_id', true );
                        if ( $nid ) {
                                $user_by_nid[ TppSalary_Jalali::digits_en( $nid ) ] = (int) $u->ID;
                        }
                }

                $ok = 0;
                $fail = 0;
                $log = array();

                foreach ( $rows as $ri => $row ) {
                        $line_no = $ri + 2;
                        $get = function ( $idx ) use ( $row ) {
                                return ( null !== $idx && isset( $row[ $idx ] ) ) ? trim( (string) $row[ $idx ] ) : '';
                        };

                        $jyear = (int) TppSalary_Jalali::digits_en( $get( $col_year ) );
                        $jmonth = self::parse_month( $get( $col_month ) );
                        if ( $jyear < 1300 || $jyear > 1600 || ! $jmonth ) {
                                $fail++;
                                $log[] = array( 'row' => $line_no, 'status' => 'خطا', 'message' => 'سال/ماه نامعتبر است: «' . $get( $col_year ) . ' / ' . $get( $col_month ) . '»' );
                                continue;
                        }

                        /* مرکز پیش از کارمند — برای ساخت خودکار کارمند لازم است (ثبت مرکز در پروفایل او). */
                        $center_id = 0;
                        $cname = $get( $col_center );
                        if ( '' !== $cname ) {
                                $cnorm = self::normalize_key( $cname );
                                if ( isset( $center_by_name[ $cnorm ] ) ) {
                                        $center_id = $center_by_name[ $cnorm ];
                                }
                        }
                        if ( ! $center_id ) {
                                $fail++;
                                $log[] = array( 'row' => $line_no, 'status' => 'خطا', 'message' => 'مرکز یافت نشد: «' . $cname . '» — مرکز باید قبلاً در بخش «مراکز» تعریف شده باشد.' );
                                continue;
                        }

                        $user_id = 0;
                        $nid = TppSalary_Jalali::digits_en( $get( $col_national ) );
                        $emp_name = $get( $col_name );
                        if ( $nid && isset( $user_by_nid[ $nid ] ) ) {
                                $user_id = $user_by_nid[ $nid ];
                        }
                        if ( ! $user_id && null !== $col_name ) {
                                $uname = self::normalize_key( $emp_name );
                                if ( '' !== $uname && isset( $user_by_name[ $uname ] ) ) {
                                        $user_id = $user_by_name[ $uname ];
                                }
                        }
                        /*
                         * نسخه 1.4.1 — ساخت خودکار کارمند: اگر کارمند یافت نشد و گزینه
                         * «ساخت خودکار» فعال باشد، کاربر کارمند جدید با کد ملی/نام سطر
                         * ساخته می‌شود (قبلاً سطر با خطا رد می‌شد).
                         */
                        $created_creds = '';
                        if ( ! $user_id && $auto_create && ( '' !== $emp_name || '' !== $nid ) ) {
                                $created_row = self::create_employee_user( '' !== $emp_name ? $emp_name : $nid, $nid, '' );
                                if ( is_wp_error( $created_row ) ) {
                                        $fail++;
                                        $log[] = array( 'row' => $line_no, 'status' => 'خطا', 'message' => 'ساخت خودکار کارمند ناموفق بود: ' . $created_row->get_error_message() );
                                        continue;
                                }
                                $user_id = $created_row['user_id'];
                                $user_by_nid[ $nid ] = $user_id;
                                if ( '' !== $emp_name ) {
                                        $user_by_name[ self::normalize_key( $emp_name ) ] = $user_id;
                                }
                                if ( $center_id ) {
                                        $p = tpp_salary_get_profile( $user_id );
                                        $p['centers'] = array_values( array_unique( array_merge( array_map( 'intval', $p['centers'] ), array( (int) $center_id ) ) ) );
                                        if ( '' !== $emp_name ) {
                                                $p['full_name'] = $emp_name;
                                        }
                                        tpp_salary_save_profile( $user_id, $p );
                                }
                                if ( $nid ) {
                                        update_user_meta( $user_id, 'tpp_salary_national_id', $nid );
                                }
                                $created_creds = ' — کارمند جدید ساخته شد (نام کاربری: ' . $created_row['login'] . ' — رمز: ' . $created_row['pass'] . ' — فقط همین یک‌بار نمایش داده می‌شود)';
                                $log[] = array( 'row' => $line_no, 'status' => 'کارمند جدید', 'message' => 'ساخت خودکار کارمند: «' . ( '' !== $emp_name ? $emp_name : $nid ) . '»' . $created_creds );
                        }
                        if ( ! $user_id ) {
                                $fail++;
                                $log[] = array( 'row' => $line_no, 'status' => 'خطا', 'message' => 'کارمند یافت نشد: «' . ( '' !== $nid ? $nid : $emp_name ) . '» — ابتدا کارمند را ثبت کنید، کد ملی را درست وارد کنید یا گزینه «ساخت خودکار کارمند» را فعال کنید.' );
                                continue;
                        }

                        /* مقادیر فیلدها — سلول «—»/خالی یعنی محاسبه با فرمول */
                        $raw = array();
                        $insurable_formula = false;
                        $force_compute = array();
                        foreach ( $col_field as $idx => $key ) {
                                $cell = $get( $idx );
                                if ( self::is_blank_calc( $cell ) ) {
                                        if ( 'insurable' === $key ) {
                                                $insurable_formula = true;
                                        }
                                        /* فیلد محاسباتیِ خالی → اجبار محاسبه از فرمول (نه صفرِ دستی). */
                                        if ( isset( $is_calc_key[ $key ] ) ) {
                                                $force_compute[] = $key;
                                        }
                                        continue;
                                }
                                $raw[ $key ] = $cell;
                        }

                        if ( $dry_run ) {
                                $ok++;
                                $log[] = array( 'row' => $line_no, 'status' => 'آزمایشی', 'message' => 'ثبت/به‌روزرسانی حقوق برای دوره «' . TppSalary_Jalali::period_label( $jyear, $jmonth ) . '» — کارمند #' . $user_id . ' — مرکز #' . $center_id . ( $insurable_formula ? ' — مشمول بیمه از فرمول' : '' ) );
                                continue;
                        }

                        $res = TppSalary_Salary_Pages::upsert_record( $user_id, $center_id, $jyear, $jmonth, $raw, $insurable_formula, $force_compute );
                        if ( is_wp_error( $res ) ) {
                                $fail++;
                                $log[] = array( 'row' => $line_no, 'status' => 'خطا', 'message' => $res->get_error_message() );
                                continue;
                        }
                        $ok++;
                        $log[] = array(
                                'row' => $line_no,
                                'status' => 'updated' === $res['status'] ? 'به‌روزرسانی' : 'ثبت جدید',
                                'message' => 'دوره «' . TppSalary_Jalali::period_label( $jyear, $jmonth ) . '» — کارمند #' . $user_id . ' — مرکز #' . $center_id,
                        );
                }

                return array( 'ok' => $ok, 'fail' => $fail, 'log' => $log );
        }
}
}
// TPP_SALARY GUARD END
