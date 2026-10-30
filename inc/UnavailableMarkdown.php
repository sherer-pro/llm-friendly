<?php
/** Universal resolution of unavailable Markdown representations. */
namespace LLMFriendly;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UnavailableMarkdown {
	private Options $options;
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Resolve an unavailable representation without fetching its URL.
	 *
	 * @param array $context request_url, post_type, path and source_post_id.
	 * @return array|null Validated status and optional destination URL.
	 */
	public function resolve( array $context ): ?array {
		return Content::anonymous( function () use ( $context ) {
			/**
			 * Supply a replacement or withdrawal for an unavailable Markdown URL.
			 * Return null, array( 'status' => 410 ), or
			 * array( 'status' => 301, 'target_post_id' => a public post ID ).
			 * Providers own their rules; this plugin does not infer retirement status.
			 */
			$value = apply_filters( 'llmf_resolve_unavailable_markdown', null, $context );
			if ( ! is_array( $value ) || ! isset( $value['status'] ) ) {
				return null;
			}
			if ( 410 === $value['status'] ) {
				return array( 'status' => 410 );
			}
			if ( 301 !== $value['status'] || ! isset( $value['target_post_id'] ) || ! is_int( $value['target_post_id'] ) || $value['target_post_id'] < 1 ) {
				return null;
			}
			$post = get_post( $value['target_post_id'] );
			if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status || '' !== $post->post_password || ! $this->options->is_exportable_post_type( $post->post_type ) ) {
				return null;
			}
			$opt = $this->options->get();
			$url = ! empty( $opt['enabled_markdown'] ) && $this->options->is_selected_post_type( $post->post_type, $opt ) && $this->options->can_export_post( $post, 'markdown' )
				? $this->options->markdown_url_for_post( $post ) : get_permalink( $post );
			$parts = is_string( $url ) ? wp_parse_url( $url ) : false;
			$home = wp_parse_url( home_url( '/' ) );
			$base = rtrim( $home['path'] ?? '', '/' );
			$decoded_path = rawurldecode( $parts['path'] ?? '' );
			if ( ! is_array( $home ) || ! is_array( $parts ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || ! in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) || ( $parts['scheme'] ?? '' ) !== ( $home['scheme'] ?? '' ) || strtolower( $parts['host'] ?? '' ) !== strtolower( $home['host'] ?? '' ) || (int) ( $parts['port'] ?? 0 ) !== (int) ( $home['port'] ?? 0 ) || ( $base && 0 !== strpos( $parts['path'] ?? '', $base . '/' ) ) || preg_match( '/[\x00-\x20\x7f]/', $url ) ) {
				return null;
			}
			// Browsers normalize dot segments and backslashes before following Location.
			if ( strpos( $decoded_path, '\\' ) !== false || preg_match( '~(?:^|/)\.{1,2}(?:/|$)~', $decoded_path ) ) {
				return null;
			}
			$request_path = wp_parse_url( $context['request_url'] ?? '', PHP_URL_PATH );
			if ( $request_path === ( $parts['path'] ?? '' ) ) {
				return null;
			}
			return array( 'status' => 301, 'url' => $url );
		} );
	}
}
