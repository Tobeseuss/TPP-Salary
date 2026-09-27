<?php
/**
 * سازگاری محیط — پولی‌فیل توابع mbstring برای هاست‌های رایگان بدون این اکستنشن
 * تبدیل‌های UTF-8 ↔ UTF-16BE/LE ↔ UTF-32BE/LE در PHP خالص (نیاز موتور PDF)
 *
 * @package TPP_Salary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'mb_strlen' ) ) {
	function mb_strlen( $s, $enc = null ) {
		preg_match_all( '/./us', (string) $s, $m );
		return count( $m[0] );
	}
}

if ( ! function_exists( 'mb_substr' ) ) {
	function mb_substr( $s, $start, $len = null, $enc = null ) {
		preg_match_all( '/./us', (string) $s, $m );
		$chars = $m[0];
		if ( null === $len ) {
			return implode( '', array_slice( $chars, (int) $start ) );
		}
		return implode( '', array_slice( $chars, (int) $start, (int) $len ) );
	}
}

if ( ! function_exists( 'mb_strpos' ) ) {
	function mb_strpos( $haystack, $needle, $offset = 0, $enc = null ) {
		$byte = strpos( (string) $haystack, (string) $needle, (int) $offset );
		if ( false === $byte ) {
			return false;
		}
		if ( 0 === $byte ) {
			return 0;
		}
		return mb_strlen( substr( $haystack, 0, $byte ), 'UTF-8' );
	}
}

if ( ! function_exists( 'mb_stripos' ) ) {
	function mb_stripos( $haystack, $needle, $offset = 0, $enc = null ) {
		return mb_strpos( strtolower( $haystack ), strtolower( $needle ), (int) $offset );
	}
}

if ( ! function_exists( 'tpp_cp_to_utf8' ) ) {
	function tpp_cp_to_utf8( $cp ) {
		if ( $cp < 0x80 ) { return chr( $cp ); }
		if ( $cp < 0x800 ) { return chr( 0xC0 | ( $cp >> 6 ) ) . chr( 0x80 | ( $cp & 0x3F ) ); }
		if ( $cp < 0x10000 ) { return chr( 0xE0 | ( $cp >> 12 ) ) . chr( 0x80 | ( ( $cp >> 6 ) & 0x3F ) ) . chr( 0x80 | ( $cp & 0x3F ) ); }
		return chr( 0xF0 | ( $cp >> 18 ) ) . chr( 0x80 | ( ( $cp >> 12 ) & 0x3F ) ) . chr( 0x80 | ( ( $cp >> 6 ) & 0x3F ) ) . chr( 0x80 | ( $cp & 0x3F ) );
	}
}

if ( ! function_exists( 'tpp_utf8_ord' ) ) {
	function tpp_utf8_ord( $ch ) {
		$len = strlen( $ch );
		if ( 1 === $len ) { return ord( $ch[0] ); }
		if ( 2 === $len ) { return ( ( ord( $ch[0] ) & 0x1F ) << 6 ) | ( ord( $ch[1] ) & 0x3F ); }
		if ( 3 === $len ) { return ( ( ord( $ch[0] ) & 0x0F ) << 12 ) | ( ( ord( $ch[1] ) & 0x3F ) << 6 ) | ( ord( $ch[2] ) & 0x3F ); }
		if ( 4 === $len ) { return ( ( ord( $ch[0] ) & 0x07 ) << 18 ) | ( ( ord( $ch[1] ) & 0x3F ) << 12 ) | ( ( ord( $ch[2] ) & 0x3F ) << 6 ) | ( ord( $ch[3] ) & 0x3F ); }
		return 0;
	}
}

if ( ! function_exists( 'mb_convert_encoding' ) ) {
	function mb_convert_encoding( $s, $to, $from = null ) {
		$s    = (string) $s;
		$to   = strtoupper( (string) $to );
		$from = strtoupper( is_array( $from ) ? (string) reset( $from ) : (string) $from );

		if ( 'UTF-32BE' === $from ) {
			$vals = unpack( 'N*', $s );
			$out  = '';
			foreach ( $vals as $cp ) { $out .= tpp_cp_to_utf8( $cp ); }
			$s = $out;
		} elseif ( 'UTF-32LE' === $from ) {
			$vals = unpack( 'V*', $s );
			$out  = '';
			foreach ( $vals as $cp ) { $out .= tpp_cp_to_utf8( $cp ); }
			$s = $out;
		} elseif ( 'UTF-16BE' === $from || 'UTF-16LE' === $from ) {
			$vals = ( 'UTF-16BE' === $from ) ? unpack( 'n*', $s ) : unpack( 'v*', $s );
			$out  = '';
			$n    = count( $vals );
			for ( $i = 1; $i <= $n; $i++ ) {
				$cp = $vals[ $i ];
				if ( $cp >= 0xD800 && $cp <= 0xDBFF && $i < $n && $vals[ $i + 1 ] >= 0xDC00 && $vals[ $i + 1 ] <= 0xDFFF ) {
					$cp = 0x10000 + ( ( $cp - 0xD800 ) << 10 ) + ( $vals[ $i + 1 ] - 0xDC00 );
					$i++;
				}
				$out .= tpp_cp_to_utf8( $cp );
			}
			$s = $out;
		} elseif ( function_exists( 'iconv' ) && 'UTF-8' !== $from ) {
			$s = @iconv( $from, 'UTF-8//IGNORE', $s );
		}

		if ( 'UTF-8' === $to || '' === $to ) {
			return $s;
		}

		$out = '';
		preg_match_all( '/./us', $s, $m );
		foreach ( $m[0] as $ch ) {
			$cp = tpp_utf8_ord( $ch );
			if ( 'UTF-32BE' === $to ) {
				$out .= pack( 'N', $cp );
			} elseif ( 'UTF-32LE' === $to ) {
				$out .= pack( 'V', $cp );
			} elseif ( 'UTF-16BE' === $to ) {
				$out .= ( $cp >= 0x10000 ) ? pack( 'n', 0xD800 + ( ( $cp - 0x10000 ) >> 10 ) ) . pack( 'n', 0xDC00 + ( ( $cp - 0x10000 ) & 0x3FF ) ) : pack( 'n', $cp );
			} elseif ( 'UTF-16LE' === $to ) {
				$out .= ( $cp >= 0x10000 ) ? pack( 'v', 0xD800 + ( ( $cp - 0x10000 ) >> 10 ) ) . pack( 'v', 0xDC00 + ( ( $cp - 0x10000 ) & 0x3FF ) ) : pack( 'v', $cp );
			} else {
				$out .= $ch;
			}
		}
		return $out;
	}
}
