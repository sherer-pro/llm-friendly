<?php

namespace LLMFriendly;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared HTTP response helpers for text endpoints.
 */
final class Response {
	/**
	 * Send headers and handle ETag/Last-Modified conditional requests.
	 *
	 * @param array<int|string,string> $headers Response headers.
	 * @param string                   $etag Strong ETag value.
	 * @param int                      $last_modified_ts Last modified timestamp.
	 * @param bool                     $allow_last_modified_conditional Whether If-Modified-Since can return 304 without ETag.
	 * @return void
	 */
	public static function send_conditional_headers(
		array $headers,
		string $etag,
		int $last_modified_ts,
		bool $allow_last_modified_conditional = true
	): void {
		status_header( 200 );

		foreach ( self::header_lines( $headers ) as $line ) {
			header( $line );
		}

		$last_modified_ts = max( 1, (int) $last_modified_ts );
		$last_modified    = gmdate( 'D, d M Y H:i:s', $last_modified_ts ) . ' GMT';

		header( 'ETag: ' . self::clean_header_value( $etag ) );
		header( 'Last-Modified: ' . $last_modified );
		header( 'Cache-Control: public, max-age=0, must-revalidate' );

		$if_none_match     = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_IF_NONE_MATCH'] ) ) : '';
		$if_modified_since = isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) : '';

		if ( $if_none_match !== '' && self::etag_matches( $if_none_match, $etag ) ) {
			status_header( 304 );
			exit;
		}

		if ( $allow_last_modified_conditional && $if_none_match === '' && $if_modified_since !== '' ) {
			$since_ts = strtotime( $if_modified_since );
			if ( $since_ts !== false && $since_ts >= $last_modified_ts ) {
				status_header( 304 );
				exit;
			}
		}
	}

	/**
	 * Build a stable ETag for a response body.
	 *
	 * @param string $body Response body.
	 * @param string $salt Additional cache salt.
	 * @return string Strong ETag.
	 */
	public static function etag_from_string( string $body, string $salt = '' ): string {
		$version = defined( 'LLMF_VERSION' ) ? (string) LLMF_VERSION : '0';

		return '"' . sha1( $body . '|' . $version . '|' . $salt ) . '"';
	}

	/**
	 * Send a temporary service-unavailable response.
	 *
	 * @param int $retry_after Retry-After seconds.
	 * @return void
	 */
	public static function send_service_unavailable( int $retry_after = 10 ): void {
		status_header( 503 );
		header( 'Content-Type: text/plain; charset=UTF-8' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
		header( 'Retry-After: ' . max( 1, (int) $retry_after ) );

		echo esc_html( get_status_header_desc( 503 ) );
		exit;
	}

	/**
	 * Normalize headers into safe header lines.
	 *
	 * @param array<int|string,string> $headers Header list.
	 * @return array<int,string> Header lines.
	 */
	private static function header_lines( array $headers ): array {
		$lines = array();

		foreach ( $headers as $key => $value ) {
			if ( is_string( $key ) && $key !== '' ) {
				$name  = self::clean_header_name( $key );
				$value = self::clean_header_value( (string) $value );
				if ( $name !== '' && $value !== '' ) {
					$lines[] = $name . ': ' . $value;
				}
				continue;
			}

			if ( is_string( $value ) && $value !== '' ) {
				$line = str_replace( array( "\r", "\n" ), '', $value );
				if ( $line !== '' ) {
					$lines[] = $line;
				}
			}
		}

		return $lines;
	}

	/**
	 * Clean a header name.
	 *
	 * @param string $name Header name.
	 * @return string Header name or empty string.
	 */
	private static function clean_header_name( string $name ): string {
		$name = trim( $name );

		return preg_match( '/^[A-Za-z0-9-]+$/', $name ) ? $name : '';
	}

	/**
	 * Clean a header value.
	 *
	 * @param string $value Header value.
	 * @return string Header value.
	 */
	private static function clean_header_value( string $value ): string {
		return trim( str_replace( array( "\r", "\n" ), '', $value ) );
	}

	/**
	 * Check whether an If-None-Match value matches the current ETag.
	 *
	 * @param string $header If-None-Match header value.
	 * @param string $etag Current ETag.
	 * @return bool True on match.
	 */
	private static function etag_matches( string $header, string $etag ): bool {
		$header = trim( $header );
		if ( $header === '*' ) {
			return true;
		}

		$parts = array_map( 'trim', explode( ',', $header ) );
		foreach ( $parts as $part ) {
			if ( $part === $etag || preg_replace( '/^W\//', '', $part ) === $etag ) {
				return true;
			}
		}

		return false;
	}
}
