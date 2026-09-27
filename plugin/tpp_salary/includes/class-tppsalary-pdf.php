<?php
/**
 * موتور PDF فارسی — بر پایه tFPDF با شکل‌دهی حروف (Presentation Forms) و چیدمان راست‌به‌چپ
 *
 * @package TppSalary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

require_once TPP_SALARY_DIR . 'lib/tfpdf/tfpdf.php';
require_once TPP_SALARY_DIR . 'lib/tfpdf/font/unifont/ttfonts.php';

/**
 * Class TppSalary_PDF
 */
if ( ! class_exists( 'TppSalary_PDF' ) ) {
        // TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TppSalary_PDF extends tFPDF {

        /**
         * نقشه شکل‌دهی حروف فارسی/عربی → [جداشده، پایانی، آغازین، میانی]
         *
         * @var array<string,array>
         */
        private static $forms = array(
                'آ' => array( 0xFE81, 0xFE82, null, null ),
                'أ' => array( 0xFE83, 0xFE84, null, null ),
                'إ' => array( 0xFE87, 0xFE88, null, null ),
                'ا' => array( 0xFE8D, 0xFE8E, null, null ),
                'ب' => array( 0xFE8F, 0xFE90, 0xFE91, 0xFE92 ),
                'پ' => array( 0xFB56, 0xFB57, 0xFB58, 0xFB59 ),
                'ت' => array( 0xFE95, 0xFE96, 0xFE97, 0xFE98 ),
                'ث' => array( 0xFE99, 0xFE9A, 0xFE9B, 0xFE9C ),
                'ج' => array( 0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0 ),
                'چ' => array( 0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D ),
                'ح' => array( 0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4 ),
                'خ' => array( 0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8 ),
                'د' => array( 0xFEA9, 0xFEAA, null, null ),
                'ذ' => array( 0xFEAB, 0xFEAC, null, null ),
                'ر' => array( 0xFEAD, 0xFEAE, null, null ),
                'ز' => array( 0xFEAF, 0xFEB0, null, null ),
                'ژ' => array( 0xFB8A, 0xFB8B, null, null ),
                'س' => array( 0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4 ),
                'ش' => array( 0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8 ),
                'ص' => array( 0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC ),
                'ض' => array( 0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0 ),
                'ط' => array( 0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4 ),
                'ظ' => array( 0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8 ),
                'ع' => array( 0xFEC9, 0xFECA, 0xFECB, 0xFECC ),
                'غ' => array( 0xFECD, 0xFECE, 0xFECF, 0xFED0 ),
                'ف' => array( 0xFED1, 0xFED2, 0xFED3, 0xFED4 ),
                'ق' => array( 0xFED5, 0xFED6, 0xFED7, 0xFED8 ),
                'ک' => array( 0xFB8E, 0xFB8F, 0xFB90, 0xFB91 ),
                'گ' => array( 0xFB92, 0xFB93, 0xFB94, 0xFB95 ),
                'ل' => array( 0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0 ),
                'م' => array( 0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4 ),
                'ن' => array( 0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8 ),
                'و' => array( 0xFEED, 0xFEEE, null, null ),
                'ه' => array( 0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC ),
                'ی' => array( 0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF ),
                'ي' => array( 0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4 ),
                'ك' => array( 0xFEDB, 0xFEDC, 0xFED9, 0xFEDA ),
                'ة' => array( 0xFE93, 0xFE94, null, null ),
                'ى' => array( 0xFEEF, 0xFEF0, null, null ),
                'ئ' => array( 0xFE89, 0xFE8A, 0xFE8B, 0xFE8C ),
                'ؤ' => array( 0xFE85, 0xFE86, null, null ),
                'ء' => array( 0xFE80, 0xFE80, null, null ),
        );

        /**
         * حروفی که از سمت چپ می‌چسبند (به حرف قبل متصل می‌شوند)
         *
         * @param string $ch حرف.
         * @return bool
         */
        private static function joins_prev( $ch ) {
                $f = isset( self::$forms[ $ch ] ) ? self::$forms[ $ch ] : null;
                return $f ? ( null !== $f[2] ) : false; // اگر شکل آغازین دارد به قبل می‌چسبد.
        }

        /**
         * آیا حرف فارسی/عربی است؟
         *
         * @param string $ch کاراکتر.
         * @return bool
         */
        private static function is_arabic( $ch ) {
                if ( '' === $ch ) {
                        return false;
                }
                $cp = self::ord_utf8( $ch );
                /* نسخه 1.5.0: فرم‌های نمایشی (0xFB50–0xFEFF) هم «حرف» شمرده می‌شوند —
                 * وگرنه اسکن حرف قبلی روی متنِ نیمه‌شکل‌داده شکست می‌خورد. */
                return ( $cp >= 0x0620 && $cp <= 0x064A ) || ( $cp >= 0xFB50 && $cp <= 0xFEFF )
                        || $cp === 0x067E || $cp === 0x0686 || $cp === 0x0698 || $cp === 0x06A9 || $cp === 0x06AF || $cp === 0x06CC || $cp === 0x0640;
        }

        /**
         * کد‌پوینت کاراکتر UTF-8
         *
         * @param string $ch کاراکتر.
         * @return int
         */
        private static function ord_utf8( $ch ) {
                $v = unpack( 'N', mb_convert_encoding( $ch, 'UTF-32BE', 'UTF-8' ) );
                return $v ? $v[1] : 0;
        }

        /**
         * کاراکتر از کد‌پوینت
         *
         * @param int $cp کد‌پوینت.
         * @return string
         */
        private static function chr_utf8( $cp ) {
                return mb_convert_encoding( pack( 'N', $cp ), 'UTF-8', 'UTF-32BE' );
        }

        /**
         * حرف اعراب/علامت شفاف؟ (در اتصال حروف شریک محسوب نمی‌شود و از آن عبور می‌کنیم)
         *
         * @param string $ch کاراکتر.
         * @return bool
         */
        private static function is_diacritic( $ch ) {
                $cp = self::ord_utf8( $ch );
                return ( $cp >= 0x064B && $cp <= 0x0655 ) || 0x0670 === $cp;
        }

        /**
         * شکل‌دهی متن فارسی برای PDF (حروف چسبان + ترتیب نمایش RTL)
         *
         * @param string $text متن منطقی (UTF-8).
         * @return string متن آماده نمایش (UTF-8 با فرم‌های نمایشی).
         */
        public static function shape( $text ) {
                $text   = (string) $text;
                $len    = mb_strlen( $text, 'UTF-8' );
                $chars  = array();
                $orig   = array();
                for ( $i = 0; $i < $len; $i++ ) {
                        $c        = mb_substr( $text, $i, 1, 'UTF-8' );
                        $chars[]  = $c;
                        $orig[]   = $c;
                }

                // 1) شکل‌دهی حروف.
                $n = count( $chars );
                for ( $i = 0; $i < $n; $i++ ) {
                        $ch = $chars[ $i ];
                        if ( ! isset( self::$forms[ $ch ] ) ) {
                                continue;
                        }
                        /*
                         * نسخه 1.5.0 — ریشه واقعی باگ «حروف جدا از هم»:
                         * اسکن حرف قبلی/بعدی باید روی «متن اصلی» انجام شود نه روی
                         * آرایه در حال ویرایش؛ چون حروف قبلی به فرم نمایشی
                         * (0xFE91 و…) تبدیل شده‌اند و is_arabic() آن‌ها را
                         * نمی‌شناخت → اتصال به حرف قبل هیچ‌گاه برقرار نمی‌شد و
                         * بیشتر کلمات با فرم آغازین/جداشده رندر می‌شدند.
                         * سایر قواعد: ZWNJ اتصال را قطع می‌کند، اعراب شفاف است،
                         * فاصله/رقم/لاتین اتصال را قطع می‌کند.
                         */
                        $prev = null;
                        for ( $j = $i - 1; $j >= 0; $j-- ) {
                                if ( "\u{200C}" === $orig[ $j ] ) {
                                        break;
                                }
                                if ( self::is_diacritic( $orig[ $j ] ) ) {
                                        continue;
                                }
                                if ( self::is_arabic( $orig[ $j ] ) ) {
                                        $prev = $orig[ $j ];
                                }
                                break;
                        }
                        $next = null;
                        for ( $j = $i + 1; $j < $n; $j++ ) {
                                if ( "\u{200C}" === $orig[ $j ] ) {
                                        break;
                                }
                                if ( self::is_diacritic( $orig[ $j ] ) ) {
                                        continue;
                                }
                                if ( self::is_arabic( $orig[ $j ] ) ) {
                                        $next = $orig[ $j ];
                                }
                                break;
                        }

                        $form  = self::$forms[ $ch ];
                        /*
                         * نسخه 1.5.0 — ریشه باگ «حروف جدا از هم»:
                         * پیش‌تر وقتی حرفِ بعدی از حروف غیرچسبان (ا د ذ ر ز ژ و) بود،
                         * اتصال حرف فعلی به بعدی به‌اشتباه قطع می‌شد (مثل «حقوق»،
                         * «محمد»، «دستمزد» که ق/م قبل از و/د جدا می‌افتاد).
                         * قاعده درست: حرف فعلی به بعدی می‌چسبد اگر (الف) خودش شکل
                         * آغازین داشته باشد و (ب) حرف بعدی یک حرف چسبان‌پذیر باشد —
                         * همه حروف فارسی/عربی از سمت راست اتصال را می‌پذیرند.
                         */
                        $link_p = $prev && isset( self::$forms[ $prev ] ) && self::joins_prev( $prev );
                        $link_n = $next && isset( self::$forms[ $next ] ) && self::joins_prev( $ch );

                        if ( $link_p && $link_n && null !== $form[3] ) {
                                $chars[ $i ] = self::chr_utf8( $form[3] ); // میانی.
                        } elseif ( $link_p && null !== $form[1] ) {
                                $chars[ $i ] = self::chr_utf8( $form[1] ); // پایانی.
                        } elseif ( $link_n && null !== $form[2] ) {
                                $chars[ $i ] = self::chr_utf8( $form[2] ); // آغازین.
                        } else {
                                $chars[ $i ] = self::chr_utf8( $form[0] ); // جداشده.
                        }
                }

                /*
                 * 2) لگاتور لا (ل + ا):
                 * پس از شکل‌دهی، ا بعد از ل همیشه «پایانی» (0xFE8E) است؛
                 * نسخه‌های قبلی فقط شکل جداشده (0xFE8D) را می‌دیدند و لگاتور
                 * هرگز ساخته نمی‌شد. هر دو فرم پذیرفته می‌شوند.
                 */
                $lam_forms = array( self::chr_utf8( 0xFEDF ), self::chr_utf8( 0xFEE0 ) ); // ل آغازین/میانی.
                $alef_forms = array( self::chr_utf8( 0xFE8D ), self::chr_utf8( 0xFE8E ) ); // ا جداشده/پایانی.
                for ( $i = 0; $i < count( $chars ) - 1; $i++ ) {
                        if ( in_array( $chars[ $i ], $lam_forms, true ) && in_array( $chars[ $i + 1 ], $alef_forms, true ) ) {
                                $chars[ $i ] = self::chr_utf8( 0xFEE0 === self::ord_utf8( $chars[ $i ] ) ? 0xFEFC : 0xFEFB );
                                unset( $chars[ $i + 1 ] );
                                $chars = array_values( $chars );
                        }
                }

                // 3) ترتیب نمایش: اجراها را بر اساس جهت بازآرایی می‌کنیم.
                $mirror = array( '(' => ')', ')' => '(', '[' => ']', ']' => '[', '{' => '}', '}' => '{', '<' => '>', '>' => '<', '«' => '»', '»' => '«' );
                $visual = array();
                $run    = array();
                $run_rtl = true; // پیش‌فرض: متن فارسی.
                if ( ! empty( $chars ) ) {
                        $run_rtl = self::char_dir( $chars[0], true );
                }
                foreach ( $chars as $ch ) {
                        $is_rtl_char = self::char_dir( $ch, $run_rtl );
                        if ( $is_rtl_char !== $run_rtl ) {
                                $visual[] = array( 'rtl' => $run_rtl, 'run' => $run );
                                $run      = array();
                                $run_rtl  = $is_rtl_char;
                        }
                        $run[] = $ch;
                }
                $visual[] = array( 'rtl' => $run_rtl, 'run' => $run );

                $out = array();
                foreach ( array_reverse( $visual ) as $seg ) {
                        $run = $seg['run'];
                        if ( $seg['rtl'] ) {
                                $run = array_reverse( $run );
                                foreach ( $run as $idx => $ch ) {
                                        if ( isset( $mirror[ $ch ] ) ) {
                                                $run[ $idx ] = $mirror[ $ch ];
                                        }
                                }
                        }
                        $out = array_merge( $out, $run );
                }
                return implode( '', $out );
        }

        /**
         * تعیین جهت کاراکتر (ساده‌سازی الگوریتم Bidi)
         *
         * @param string $ch      کاراکتر.
         * @param bool   $cur_rtl جهت جاری اجرا (برای کاراکترهای خنثی).
         * @return bool true = راست‌به‌چپ
         */
        private static function char_dir( $ch, $cur_rtl ) {
                if ( '' === $ch ) {
                        return $cur_rtl;
                }
                // خنثی‌ها (فاصله، نیم‌فاصله، تب): ادامه‌ی جهت جاری.
                if ( "\u{200C}" === $ch || ' ' === $ch || "\t" === $ch ) {
                        return $cur_rtl;
                }
                $cp = self::ord_utf8( $ch );
                // حروف فارسی/عربی + فرم‌های نمایشی.
                $is_letter = ( $cp >= 0x0621 && $cp <= 0x064A ) || in_array( $cp, array( 0x067E, 0x0686, 0x0698, 0x06A9, 0x06AF, 0x06CC, 0x0640 ), true ) || ( $cp >= 0xFB50 && $cp <= 0xFEFF );
                if ( $is_letter ) {
                        return true;
                }
                // علائم سجاوندی فارسی (جداکننده‌های رقم ۰۶۶B/۰۶۶C جزو «عدد» هستند
                // و نباید اجرای عدد را بشکنند — نسخه 1.5.0).
                if ( in_array( $cp, array( 0x060C, 0x061B, 0x061F ), true ) ) {
                        return true;
                }
                // پرانتزها و گیومه در متن RTL همسطح راست‌به‌چپ هستند (و هنگام معکوس‌سازی، آینه می‌شوند).
                if ( in_array( $ch, array( '(', ')', '[', ']', '{', '}', '«', '»' ), true ) ) {
                        return true;
                }
                // ارقام (لاتین، فارسی، عربی) و بقیه → چپ‌به‌راست.
                return false;
        }

        /**
         * نسخه 1.7.1 — سیاست ارقام انگلیسی: این تابع (که تا 1.7.0 ارقام را
         * فارسی می‌کرد) اکنون برای سازگاری با فراخوانی‌های قدیمی، خروجی خود را
         * همیشه با ارقام لاتین برمی‌گرداند (هر رقم فارسی/عربی به لاتین بدل می‌شود).
         *
         * @param string $text متن.
         * @return string
         */
        public static function fa_digits( $text ) {
                $fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
                $ar = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
                $en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
                return str_replace( $ar, $en, str_replace( $fa, $en, (string) $text ) );
        }

        /** Fonts dir */
        const FONT_DIR = TPP_SALARY_DIR . 'lib/tfpdf/font/unifont/';

        /**
         * سازنده — تنظیمات پایه
         */
        public function __construct( $orientation = 'P', $unit = 'mm', $size = 'A4' ) {
                parent::__construct( $orientation, $unit, $size );
                $this->SetAutoPageBreak( true, 12 );
                $this->SetMargins( 10, 10, 10 );
                $this->AliasNbPages();
                if ( function_exists( 'TppSalary_PDF_addfont' ) ) {
                        // no-op.
                }
                $this->AddFont( 'vazir', '', 'Vazirmatn-Regular.ttf', true );
                $this->AddFont( 'vazir', 'B', 'Vazirmatn-Bold.ttf', true );
        }

        /**
         * متن شکل‌داده‌شده
         *
         * @param string $text متن.
         * @return string
         */
        public function fa( $text ) {
                /* نسخه 1.7.1: همه اعداد انگلیسی — ارقام فارسی/عربی ورودی هم لاتین می‌شوند. */
                return self::shape( self::fa_digits( (string) $text ) );
        }

        /**
         * Cell راست‌به‌چپ با شکل‌دهی
         *
         * @param float  $w      عرض.
         * @param float  $h      ارتفاع.
         * @param string $text   متن.
         * @param mixed  $border حاشیه.
         * @param int    $ln     خط جدید.
         * @param string $align  تراز.
         * @param bool   $fill   پرکردن.
         * @return void
         */
        public function faCell( $w, $h, $text, $border = 0, $ln = 0, $align = 'C', $fill = false ) {
                parent::Cell( $w, $h, $this->fa( $text ), $border, $ln, $align, $fill );
        }

        /**
         * MultiCell راست‌به‌چپ با شکل‌دهی
         *
         * @param float  $w    عرض.
         * @param float  $h    ارتفاع خط.
         * @param string $text متن.
         * @param mixed  $border حاشیه.
         * @param string $align تراز.
         * @param bool   $fill پرکردن.
         * @return void
         */
        public function faMultiCell( $w, $h, $text, $border = 0, $align = 'R', $fill = false ) {
                parent::MultiCell( $w, $h, $this->fa( $text ), $border, $align, $fill );
        }

        /**
         * GetstringWidth با شکل‌دهی
         *
         * @param string $text متن.
         * @return float
         */
        public function faGetStringWidth( $text ) {
                return parent::GetStringWidth( $this->fa( $text ) );
        }
}
}
// TPP_SALARY GUARD END
