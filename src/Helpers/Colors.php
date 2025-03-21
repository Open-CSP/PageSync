<?php
/**
 * Created by  : Open CSP
 * Project     : PageSync
 * Filename    : Colors.php
 * Description :
 * Date        : 18-6-2024
 * Time        : 19:55
 */

namespace PageSync\Helpers;

class Colors {
	protected static $ANSI_CODES = [
		"off"        => 0,
		"bold"       => 1,
		"italic"     => 3,
		"underline"  => 4,
		"blink"      => 5,
		"inverse"    => 7,
		"hidden"     => 8,
		"black"      => 30,
		"red"        => 31,
		"green"      => 32,
		"yellow"     => 33,
		"blue"       => 34,
		"magenta"    => 35,
		"cyan"       => 36,
		"white"      => 37,
		"black_bg"   => 40,
		"red_bg"     => 41,
		"green_bg"   => 42,
		"yellow_bg"  => 43,
		"blue_bg"    => 44,
		"magenta_bg" => 45,
		"cyan_bg"    => 46,
		"white_bg"   => 47
	];

	/**
	 * @param string $str
	 * @param string $color
	 *
	 * @return string
	 */
	public static function set( string $str, string $color = "white" ): string {
		if ( empty( $color ) ) {
			$color = "white";
		}
		$color_attrs = explode(
			"+",
			$color
		);
		$ansi_str    = "";
		foreach ( $color_attrs as $attr ) {
			if ( !isset( self::$ANSI_CODES[$attr] ) ) {
				var_dump( $attr );
			}
			$ansi_str .= "\033[" . self::$ANSI_CODES[$attr] . "m";

		}
		$ansi_str .= $str . "\033[" . self::$ANSI_CODES["off"] . "m";

		return $ansi_str;
	}

	/**
	 * @param string $message
	 * @param string $color
	 * @param bool $nl
	 * @param string $after
	 * @param string $before
	 * @param bool $endInNewLine
	 *
	 * @return mixed
	 */
	public static function cEcho(
		string $message,
		string $color = "white",
		bool $nl = false,
		string $after = '',
		string $before = '',
		bool $endInNewLine = true,
		bool $echo = false
	) {
		$result = '';
		$nLine = "";
		if ( $endInNewLine === true ) {
			$nLine = "\n";
		}
		if ( $nl ) {
			$result .= "\n";
		}
		if ( !empty( $before ) ) {
			$before = $before . "\t";
		}
		if ( !empty( $after ) ) {
			$after = "\t" . $after;
		}
		$result .= self::set( $before, "white" ) .
			self::set( $message, $color ) .
			self::set( $after,	"bold" ) .
			$nLine;
		if ( $echo ) {
			echo $result;
		} else {
			return $result;
		}
	}
}
