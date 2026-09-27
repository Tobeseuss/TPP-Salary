<?php
/**
 * موتور فرمول امن — ارزیابی عبارات ریاضی با توکن فیلدها {field_key}
 *
 * عملگرهای پشتیبانی‌شده: + - * / ( ) و عدد و توکن فیلد.
 * هیچ تابع PHP یا ورودی پویای دیگری اجرا نمی‌شود (Shunting-yard امن).
 *
 * @package TppSalary
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class TppSalary_Formula
 */
if ( ! class_exists( 'TppSalary_Formula' ) ) {
        // TPP_SALARY GUARD: جلوگیری از خطای Cannot declare class (بارگذاری دوگانه)
class TppSalary_Formula {

        /**
         * تبدیل فرمول به RPN
         *
         * @param string $formula فرمول ورودی.
         * @return array|WP_Error آرایه توکن‌های RPN یا خطا.
         */
        public static function compile( $formula ) {
                $formula = (string) $formula;
                if ( '' === trim( $formula ) ) {
                        return new WP_Error( 'tpp_salary_formula_empty', 'فرمول خالی است' );
                }

                $tokens = array();
                $len    = mb_strlen( $formula, 'UTF-8' );
                $i      = 0;
                while ( $i < $len ) {
                        $ch = mb_substr( $formula, $i, 1, 'UTF-8' );
                        if ( ' ' === $ch || "\t" === $ch || "\n" === $ch ) {
                                $i++;
                                continue;
                        }
                        if ( '{' === $ch ) {
                                $end = mb_strpos( $formula, '}', $i, 'UTF-8' );
                                if ( false === $end ) {
                                        return new WP_Error( 'tpp_salary_formula_token', 'براکت توکن فیلد بسته نشده است' );
                                }
                                $key      = trim( mb_substr( $formula, $i + 1, $end - $i - 1, 'UTF-8' ) );
                                $tokens[] = array( 'field', $key );
                                $i        = $end + 1;
                                continue;
                        }
                        if ( preg_match( '/[0-9.،٬,]/u', $ch ) ) {
                                $num    = '';
                                while ( $i < $len && preg_match( '/[0-9.،٬,]/u', mb_substr( $formula, $i, 1, 'UTF-8' ) ) ) {
                                        $c   = mb_substr( $formula, $i, 1, 'UTF-8' );
                                        $num .= ( '،' === $c || '٬' === $c || ',' === $c ) ? '' : $c;
                                        $i++;
                                }
                                if ( ! is_numeric( $num ) ) {
                                        return new WP_Error( 'tpp_salary_formula_number', 'عدد نامعتبر: ' . $num );
                                }
                                $tokens[] = array( 'num', (float) $num );
                                continue;
                        }
                        if ( '+' === $ch || '-' === $ch || '*' === $ch || '×' === $ch || '/' === $ch || '÷' === $ch || '(' === $ch || ')' === $ch ) {
                                $op = $ch;
                                if ( '×' === $op ) {
                                        $op = '*';
                                }
                                if ( '÷' === $op ) {
                                        $op = '/';
                                }
                                $tokens[] = array( 'op', $op );
                                $i++;
                                continue;
                        }
                        return new WP_Error( 'tpp_salary_formula_char', 'کاراکتر نامعتبر در فرمول: ' . $ch );
                }

                // تبدیل unary minus به 0-X (برای سادگی: اگر - در ابتدا یا بعد از عملگر یا پرانتز باز بیاید).
                $fixed   = array();
                $prev    = null;
                $idx_uni = 0;
                foreach ( $tokens as $tk ) {
                        if ( 'op' === $tk[0] && '-' === $tk[1] && ( null === $prev || ( 'op' === $prev[0] && ')' !== $prev[1] ) ) ) {
                                $fixed[] = array( 'num', 0.0 );
                                $fixed[] = array( 'op', '-' );
                                // ادامه: به‌جای unary، از 0 - استفاده می‌کنیم؛ برای جلوگیری از گم شدن اولویت در حالت ( -x ) درست است.
                        } else {
                                $fixed[] = $tk;
                        }
                        $prev = $tk;
                        $idx_uni++;
                }
                $tokens = $fixed;

                // Shunting-yard.
                $prec  = array( '+' => 1, '-' => 1, '*' => 2, '/' => 2 );
                $stack = array();
                $rpn   = array();
                foreach ( $tokens as $tk ) {
                        if ( 'op' !== $tk[0] ) {
                                $rpn[] = $tk;
                                continue;
                        }
                        $op = $tk[1];
                        if ( '(' === $op ) {
                                $stack[] = $op;
                                continue;
                        }
                        if ( ')' === $op ) {
                                while ( ! empty( $stack ) && end( $stack ) !== '(' ) {
                                        $rpn[] = array( 'op', array_pop( $stack ) );
                                }
                                if ( empty( $stack ) ) {
                                        return new WP_Error( 'tpp_salary_formula_paren', 'پرانتزها نامتوازن هستند' );
                                }
                                array_pop( $stack );
                                continue;
                        }
                        while ( ! empty( $stack ) && end( $stack ) !== '(' && isset( $prec[ end( $stack ) ] ) && $prec[ end( $stack ) ] >= $prec[ $op ] ) {
                                $rpn[] = array( 'op', array_pop( $stack ) );
                        }
                        $stack[] = $op;
                }
                while ( ! empty( $stack ) ) {
                        $op = array_pop( $stack );
                        if ( '(' === $op ) {
                                return new WP_Error( 'tpp_salary_formula_paren', 'پرانتزها نامتوازن هستند' );
                        }
                        $rpn[] = array( 'op', $op );
                }
                return $rpn;
        }

        /**
         * ارزیابی فرمول با مقادیر فیلدها
         *
         * @param string $formula فرمول.
         * @param array  $values  آرایه کلید => مقدار.
         * @return float|WP_Error
         */
        public static function evaluate( $formula, $values ) {
                $rpn = self::compile( $formula );
                if ( is_wp_error( $rpn ) ) {
                        return $rpn;
                }
                $stack = array();
                foreach ( $rpn as $tk ) {
                        if ( 'num' === $tk[0] ) {
                                $stack[] = (float) $tk[1];
                                continue;
                        }
                        if ( 'field' === $tk[0] ) {
                                $key      = $tk[1];
                                $stack[] = isset( $values[ $key ] ) ? (float) $values[ $key ] : 0.0;
                                continue;
                        }
                        $op = $tk[1];
                        if ( count( $stack ) < 2 ) {
                                return new WP_Error( 'tpp_salary_formula_stack', 'ساختار فرمول نامعتبر است' );
                        }
                        $b = array_pop( $stack );
                        $a = array_pop( $stack );
                        switch ( $op ) {
                                case '+':
                                        $stack[] = $a + $b;
                                        break;
                                case '-':
                                        $stack[] = $a - $b;
                                        break;
                                case '*':
                                        $stack[] = $a * $b;
                                        break;
                                case '/':
                                        $stack[] = ( 0.0 === (float) $b ) ? 0.0 : $a / $b;
                                        break;
                                default:
                                        return new WP_Error( 'tpp_salary_formula_op', 'عملگر نامعتبر' );
                        }
                }
                if ( 1 !== count( $stack ) ) {
                        return new WP_Error( 'tpp_salary_formula_stack', 'ساختار فرمول نامعتبر است' );
                }
                return (float) end( $stack );
        }

        /**
         * اعتبارسنجی فرمول (فقط بررسی کامپایل)
         *
         * @param string $formula فرمول.
         * @return bool|WP_Error
         */
        public static function validate( $formula ) {
                $rpn = self::compile( $formula );
                return is_wp_error( $rpn ) ? $rpn : true;
        }

        /**
         * استخراج کلید فیلدهای استفاده‌شده در فرمول
         *
         * @param string $formula فرمول.
         * @return string[]
         */
        public static function fields_used( $formula ) {
                preg_match_all( '/\{([a-zA-Z0-9_]+)\}/u', (string) $formula, $m );
                return array_values( array_unique( $m[1] ) );
        }
}
}
// TPP_SALARY GUARD END
