<?php
/**
 * تقویم جلالی — تبدیل میلادی/جلالی و توابع کمکی ماه و سال
 *
 * @package TppSalary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class TppSalary_Jalali
 */
if ( ! class_exists( 'TppSalary_Jalali' ) ) {
        // TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TppSalary_Jalali {

        /**
         * تبدیل میلادی به جلالی
         *
         * @param int $gy سال میلادی.
         * @param int $gm ماه میلادی.
         * @param int $gd روز میلادی.
         * @return array{0:int,1:int,2:int}
         */
        public static function to_jalali( $gy, $gm, $gd ) {
                $g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );
                $gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
                $days  = 355666 + ( 365 * $gy ) + ( (int) ( ( $gy2 + 3 ) / 4 ) ) - ( (int) ( ( $gy2 + 99 ) / 100 ) ) + ( (int) ( ( $gy2 + 399 ) / 400 ) ) + $gd + $g_d_m[ $gm - 1 ];
                $jy    = -1595 + ( 33 * ( (int) ( $days / 12053 ) ) );
                $days %= 12053;
                $jy   += 4 * ( (int) ( $days / 1461 ) );
                $days %= 1461;
                if ( $days > 365 ) {
                        $jy  += (int) ( ( $days - 1 ) / 365 );
                        $days = ( $days - 1 ) % 365;
                }
                if ( $days < 186 ) {
                        $jm = 1 + (int) ( $days / 31 );
                        $jd = 1 + ( $days % 31 );
                } else {
                        $jm = 7 + (int) ( ( $days - 186 ) / 30 );
                        $jd = 1 + ( ( $days - 186 ) % 30 );
                }
                return array( (int) $jy, (int) $jm, (int) $jd );
        }

        /**
         * تبدیل جلالی به میلادی
         *
         * @param int $jy سال جلالی.
         * @param int $jm ماه جلالی.
         * @param int $jd روز جلالی.
         * @return array{0:int,1:int,2:int}
         */
        public static function to_gregorian( $jy, $jm, $jd ) {
                $jy   += 1595;
                $days  = -355668 + ( 365 * $jy ) + ( ( (int) ( $jy / 33 ) ) * 8 ) + ( (int) ( ( ( $jy % 33 ) + 3 ) / 4 ) ) + $jd + ( ( $jm < 7 ) ? ( $jm - 1 ) * 31 : ( ( $jm - 7 ) * 30 ) + 186 );
                $gy    = 400 * ( (int) ( $days / 146097 ) );
                $days %= 146097;
                if ( $days > 36524 ) {
                        $gy   += 100 * ( (int) ( -- $days / 36524 ) );
                        $days %= 36524;
                        if ( $days >= 365 ) {
                                $days ++;
                        }
                }
                $gy   += 4 * ( (int) ( $days / 1461 ) );
                $days %= 1461;
                if ( $days > 365 ) {
                        $gy  += (int) ( ( $days - 1 ) / 365 );
                        $days = ( $days - 1 ) % 365;
                }
                $gd = $days + 1;
                $sal_a = array( 0, 31, ( ( ( $gy % 4 === 0 && $gy % 100 !== 0 ) || ( $gy % 400 === 0 ) ) ? 29 : 28 ), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
                $gm    = 0;
                while ( $gm < 13 && $gd > $sal_a[ $gm ] ) {
                        $gd -= $sal_a[ $gm ];
                        $gm ++;
                }
                return array( (int) $gy, (int) $gm, (int) $gd );
        }

        /**
         * تاریخ جلالی امروز بر اساس زمان سایت
         *
         * @return array{0:int,1:int,2:int} [سال، ماه، روز]
         */
        public static function today() {
                $ts = current_time( 'timestamp' );
                return self::to_jalali( (int) date( 'Y', $ts ), (int) date( 'n', $ts ), (int) date( 'j', $ts ) );
        }

        /**
         * نام ماه جلالی
         *
         * @param int $m شماره ماه 1..12.
         * @return string
         */
        public static function month_name( $m ) {
                $names = array( 1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند' );
                $m     = (int) $m;
                return isset( $names[ $m ] ) ? $names[ $m ] : '';
        }

        /**
         * لیست کامل ماه‌ها
         *
         * @return array<int,string>
         */
        public static function months() {
                return array( 1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند' );
        }

        /**
         * تعداد روزهای یک ماه جلالی (با تبدیل رفت و برگشت، دقیق برای همه سال‌ها)
         *
         * @param int $jy سال جلالی.
         * @param int $jm ماه جلالی.
         * @return int
         */
        public static function month_days( $jy, $jm ) {
                if ( $jm <= 6 ) {
                        return 31;
                }
                if ( $jm <= 11 ) {
                        return 30;
                }
                $g1 = self::to_gregorian( $jy, 12, 1 );
                $t1 = mktime( 12, 0, 0, $g1[1], $g1[2], $g1[0] );
                $g2 = self::to_gregorian( $jy + 1, 1, 1 );
                $t2 = mktime( 12, 0, 0, $g2[1], $g2[2], $g2[0] );
                return (int) round( ( $t2 - $t1 ) / 86400 );
        }

        /**
         * رشته‌ی قابل نمایش «مرداد 1405» (نسخه 1.7.1: ارقام انگلیسی)
         *
         * @param int $jy سال.
         * @param int $jm ماه.
         * @return string
         */
        public static function period_label( $jy, $jm ) {
                return self::month_name( $jm ) . ' ' . self::digits_fa( $jy );
        }

        /**
         * نسخه 1.7.1 — سیاست جدید ارقام: «همه اعداد در تمام سایت انگلیسی».
         * این تابع (که تا 1.7.0 ارقام را فارسی می‌کرد) اکنون به‌صورت سازگار با
         * فراخوانی‌های قدیمی عمل می‌کند و خروجی آن همیشه ارقام لاتین است؛
         * یعنی حتی اگر ورودی ارقام فارسی/عربی داشته باشد به لاتین تبدیل می‌شود.
         *
         * @param string $str متن ورودی.
         * @return string
         */
        public static function digits_fa( $str ) {
                return self::digits_en( $str );
        }

        /**
         * تبدیل ارقام فارسی/عربی به لاتین
         *
         * @param string $str متن ورودی.
         * @return string
         */
        public static function digits_en( $str ) {
                $fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
                $ar = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
                $en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
                return str_replace( $ar, $en, str_replace( $fa, $en, (string) $str ) );
        }
}
}
// TPP_SALARY GUARD END
