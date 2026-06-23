<?php

namespace LLMFriendly;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared Markdown formatting and sanitization helpers.
 */
final class Markdown {
	/**
	 * Normalize line breaks to Unix format.
	 *
	 * @param string $value Text with arbitrary line breaks.
	 * @return string Text with \n line breaks.
	 */
	public static function normalize_newlines( string $value ): string {
		return str_replace( array( "\r\n", "\r" ), "\n", $value );
	}

	/**
	 * Collapse a value to one plain-text line.
	 *
	 * @param mixed $value Raw value.
	 * @return string Plain text without control characters or repeated spaces.
	 */
	public static function plain_text_line( $value ): string {
		$text = wp_strip_all_tags( (string) $value, true );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = self::normalize_newlines( $text );
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', (string) $text );
		$text = str_replace( "\n", ' ', (string) $text );
		$text = preg_replace( '/\s+/u', ' ', (string) $text );

		return trim( (string) $text );
	}

	/**
	 * Escape Markdown link or image label text.
	 *
	 * @param mixed $value Raw label.
	 * @return string Safe label.
	 */
	public static function link_text( $value ): string {
		$text = self::plain_text_line( $value );
		$text = str_replace( '\\', '\\\\', $text );
		$text = str_replace( array( '[', ']' ), array( '\[', '\]' ), $text );

		return $text;
	}

	/**
	 * Sanitize a URL for use inside a Markdown destination.
	 *
	 * @param mixed        $url URL or site-relative path.
	 * @param array<int,string> $allowed_protocols Allowed schemes.
	 * @param bool         $allow_relative Whether relative URLs are allowed.
	 * @return string Safe Markdown destination, or empty string.
	 */
	public static function url_destination( $url, array $allowed_protocols = array( 'http', 'https', 'mailto' ), bool $allow_relative = true ): string {
		$url = html_entity_decode( wp_strip_all_tags( (string) $url, true ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$url = preg_replace( '/[\x00-\x1F\x7F]+/u', '', (string) $url );
		$url = trim( (string) $url );

		if ( $url === '' ) {
			return '';
		}

		$scheme = '';
		if ( preg_match( '~^([a-z][a-z0-9+.-]*):~i', $url, $m ) ) {
			$scheme = strtolower( (string) $m[1] );
		}

		$allowed = array_map( 'strtolower', $allowed_protocols );
		if ( $scheme !== '' && ! in_array( $scheme, $allowed, true ) ) {
			return '';
		}

		if ( $scheme === '' ) {
			if ( ! $allow_relative ) {
				return '';
			}
			if ( strpos( $url, '//' ) === 0 ) {
				return '';
			}
		}

		$clean = esc_url_raw( $url, $allowed );
		if ( $clean === '' ) {
			return '';
		}

		$clean = str_replace( array( ' ', '(', ')' ), array( '%20', '%28', '%29' ), $clean );

		return $clean;
	}

	/**
	 * Normalize Markdown block spacing without changing fenced code content.
	 *
	 * @param string $markdown Raw Markdown.
	 * @return string Markdown with consistent block spacing.
	 */
	public static function normalize_blocks( string $markdown ): string {
		$markdown = self::normalize_newlines( $markdown );
		$lines    = explode( "\n", $markdown );

		$blocks    = array();
		$cur_lines = array();
		$cur_type  = '';
		$in_code   = false;

		$flush = static function() use ( &$blocks, &$cur_lines, &$cur_type ): void {
			if ( empty( $cur_lines ) ) {
				$cur_type = '';
				return;
			}

			$tmp = array();
			foreach ( $cur_lines as $line ) {
				$tmp[] = rtrim( (string) $line );
			}

			while ( ! empty( $tmp ) && trim( (string) $tmp[0] ) === '' ) {
				array_shift( $tmp );
			}
			while ( ! empty( $tmp ) && trim( (string) $tmp[ count( $tmp ) - 1 ] ) === '' ) {
				array_pop( $tmp );
			}

			if ( ! empty( $tmp ) ) {
				$blocks[] = $tmp;
			}

			$cur_lines = array();
			$cur_type  = '';
		};

		foreach ( $lines as $line ) {
			$raw  = (string) $line;
			$trim = trim( $raw );

			$is_fence = preg_match( '/^\s{0,3}```/', $raw ) === 1;
			if ( $is_fence ) {
				if ( ! $in_code ) {
					$flush();
					$in_code    = true;
					$cur_type   = 'code';
					$cur_lines[] = rtrim( $raw );
				} else {
					$cur_lines[] = rtrim( $raw );
					$flush();
					$in_code = false;
				}
				continue;
			}

			if ( $in_code ) {
				$cur_lines[] = rtrim( $raw );
				continue;
			}

			if ( $trim === '' ) {
				$flush();
				continue;
			}

			$type = 'para';
			if ( preg_match( '/^\s{0,3}#{1,6}\s+/', $raw ) ) {
				$type = 'heading';
			} elseif ( preg_match( '/^\s{0,3}>\s?/', $raw ) ) {
				$type = 'quote';
			} elseif ( preg_match( '/^\s{0,3}(\d+\.|[-+*])\s+/', $raw ) ) {
				$type = 'list';
			} elseif ( strpos( $raw, '|' ) !== false && preg_match( '/^\s*\|?.*\|.*$/', $raw ) ) {
				$type = 'table';
			}

			if ( $type === 'heading' ) {
				$flush();
				$blocks[] = array( rtrim( $raw ) );
				continue;
			}

			if ( $cur_type !== '' && $cur_type !== $type ) {
				$flush();
			}

			$cur_type    = $type;
			$cur_lines[] = rtrim( $raw );
		}

		$flush();

		$out = array();
		foreach ( $blocks as $i => $block_lines ) {
			if ( $i > 0 ) {
				$out[] = '';
			}
			foreach ( $block_lines as $block_line ) {
				$out[] = (string) $block_line;
			}
		}

		return implode( "\n", $out );
	}
}
