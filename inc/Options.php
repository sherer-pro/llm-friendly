<?php
namespace LLMFriendly;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Options storage and sanitization.
 */
final class Options {
	/**
	 * Option key in wp_options.
	 */
	public const OPTION_KEY = 'llmf_options';

	/**
	 * Post meta key used for a one-line llms.txt and Markdown metadata description.
	 */
	public const META_LLMS_DESCRIPTION = '_llmf_llms_description';

	/**
	 * Maximum length for the custom Markdown block in llms.txt (in characters).
	 */
	private const CUSTOM_MD_MAX_LENGTH = 20000;

	/**
	 * Default maximum length for per-post Markdown overrides (in characters).
	 */
	private const MARKDOWN_OVERRIDE_MAX_LENGTH = 200000;

	/**
	 * Default maximum length for per-post llms.txt descriptions (in characters).
	 */
	private const LLMS_DESCRIPTION_MAX_LENGTH = 500;

	/**
	 * Default maximum amount of excluded posts stored per post type.
	 */
	private const EXCLUDED_POSTS_MAX_PER_TYPE = 500;

	/**
	 * Ensure defaults exist in DB.
	 *
	 * @return void
	 */
	public function ensure_defaults() {
		$saved = get_option( self::OPTION_KEY, null );
		if ( ! is_array( $saved ) ) {
			add_option( self::OPTION_KEY, $this->defaults(), '', false );
		}
	}

	/**
	 * Get merged options.
	 *
	 * @return array<string,mixed>
	 */
	public function get() {
		$defaults = $this->defaults();
		$saved    = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return array_merge( $defaults, $saved );
	}

	/**
	 * Update options (sanitized).
	 *
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>
	 */
	public function update( $input ) {
		$clean = $this->sanitize( $input );
		update_option( self::OPTION_KEY, $clean, false );
		return $clean;
	}

	/**
	 * Sanitize options for Settings API.
	 *
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$prev  = $this->get();

		$out = $prev;

		$out['enabled_markdown'] = ! empty( $input['enabled_markdown'] ) ? 1 : 0;
		$out['enabled_llms_txt'] = ! empty( $input['enabled_llms_txt'] ) ? 1 : 0;

		$out['base_path'] = $this->sanitize_base_path( isset( $input['base_path'] ) ? (string) $input['base_path'] : $prev['base_path'] );

		$out['post_types'] = $this->sanitize_post_types( isset( $input['post_types'] ) ? $input['post_types'] : array() );

		$out['llms_send_noindex'] = ! empty( $input['llms_send_noindex'] ) ? 1 : 0;
		$out['md_send_noindex']   = ! empty( $input['md_send_noindex'] ) ? 1 : 0;

		$mode = isset( $input['llms_regen_mode'] ) ? (string) $input['llms_regen_mode'] : (string) $prev['llms_regen_mode'];
		$mode = $mode === 'manual' ? 'manual' : 'auto';
		$out['llms_regen_mode'] = $mode;

		$limit = isset( $input['llms_recent_limit'] ) ? (int) $input['llms_recent_limit'] : (int) $prev['llms_recent_limit'];
		if ( $limit < 1 ) {
			$limit = 1;
		}
		if ( $limit > 200 ) {
			$limit = 200;
		}
		$out['llms_recent_limit'] = $limit;

		$out['site_title_override']      = isset( $input['site_title_override'] ) ? $this->sanitize_textline( $input['site_title_override'] ) : (string) $prev['site_title_override'];
		$out['site_description_override'] = isset( $input['site_description_override'] ) ? $this->sanitize_textline( $input['site_description_override'] ) : (string) $prev['site_description_override'];
		$out['site_author_override']     = isset( $input['site_author_override'] ) ? $this->sanitize_textline( $input['site_author_override'] ) : (string) $prev['site_author_override'];

		$out['sitemap_url'] = isset( $input['sitemap_url'] ) ? $this->sanitize_sitemap_url( (string) $input['sitemap_url'] ) : (string) $prev['sitemap_url'];

		$out['llms_custom_markdown'] = isset( $input['llms_custom_markdown'] )
			? $this->sanitize_llms_custom_markdown( (string) $input['llms_custom_markdown'] )
			: (string) $prev['llms_custom_markdown'];

		$out['llms_show_excerpt'] = ! empty( $input['llms_show_excerpt'] ) ? 1 : 0;

		$out['excluded_posts'] = $this->sanitize_excluded_posts( isset( $input['excluded_posts'] ) ? $input['excluded_posts'] : array() );

		// Drop exclusions for post types that are no longer exported,
		// so we do not keep stale IDs or show them in the UI.
		if ( ! empty( $out['excluded_posts'] ) ) {
			$allowed_map = array_fill_keys( $out['post_types'], true );

			$out['excluded_posts'] = array_filter(
				$out['excluded_posts'],
				function ( $ids, $type ) use ( $allowed_map ) {
					return isset( $allowed_map[ $type ] );
				},
				ARRAY_FILTER_USE_BOTH
			);
		}

		// Cache fields are managed inside the class; ensure required keys exist.
		if ( ! isset( $out['llms_cache'] ) ) {
			$out['llms_cache'] = '';
		}
		if ( ! isset( $out['llms_cache_ts'] ) ) {
			$out['llms_cache_ts'] = 0;
		}

		// These keys affect llms.txt output, so clear the cache when they change.
		$affect_keys = array(
			'enabled_markdown',
			'enabled_llms_txt',
			'base_path',
			'post_types',
			'llms_recent_limit',
			'site_title_override',
			'site_description_override',
			'sitemap_url',
			'llms_custom_markdown',
			'llms_show_excerpt',
			'excluded_posts',
		);

		$changed = false;
		foreach ( $affect_keys as $k ) {
			$prev_v = isset( $prev[ $k ] ) ? $prev[ $k ] : null;
			$now_v  = isset( $out[ $k ] ) ? $out[ $k ] : null;
			if ( $prev_v != $now_v ) {
				$changed = true;
				break;
			}
		}

		if ( $changed ) {
			// Reset cache and related metadata to ensure a clean rebuild.
			$out['llms_cache'] = '';
			$out['llms_cache_ts'] = 0;
			$out['llms_cache_rev'] = 0;
			$out['llms_cache_hash'] = '';
			$out['llms_cache_settings_hash'] = '';
		} else {
			// Preserve cache metadata fields if they are missing in saved data.
			if ( ! isset( $out['llms_cache_rev'] ) ) {
				$out['llms_cache_rev'] = 0;
			}
			if ( ! isset( $out['llms_cache_hash'] ) ) {
				$out['llms_cache_hash'] = '';
			}
			if ( ! isset( $out['llms_cache_settings_hash'] ) ) {
				$out['llms_cache_settings_hash'] = '';
			}
		}

		// These settings affect URLs, so set a transient to flush rewrite rules later.
		$rewrite_keys     = array( 'enabled_markdown', 'enabled_llms_txt', 'base_path', 'post_types' );
		$rewrite_changed = false;
		foreach ( $rewrite_keys as $k ) {
			$prev_v = isset( $prev[ $k ] ) ? $prev[ $k ] : null;
			$now_v  = isset( $out[ $k ] ) ? $out[ $k ] : null;
			if ( $prev_v != $now_v ) {
				$rewrite_changed = true;
				break;
			}
		}

		if ( $rewrite_changed ) {
			// Defer flush to admin_init to avoid repeated flushes during option saves.
			set_transient( 'llmf_flush_rewrite_rules', 1, 10 * MINUTE_IN_SECONDS );
		}

		return $out;
	}

	/**
	 * Default options.
	 *
	 * @return array<string,mixed>
	 */
	public function defaults() {
		return array(
			'enabled_markdown' => 1,
			'enabled_llms_txt' => 1,
			'base_path'        => 'llm',
			'post_types'       => array( 'post' ),
			'llms_send_noindex'=> 1,
			'md_send_noindex'   => 0,
			'llms_regen_mode'  => 'auto',   // auto | manual
			'llms_recent_limit'=> 30,       // per post type
			'site_title_override' => '',
			'site_description_override' => '',
			'site_author_override' => '',
			'sitemap_url'      => '/sitemap.xml',
			'llms_custom_markdown' => '',
			'llms_show_excerpt' => 0,
			'excluded_posts'   => array(),
			'llms_cache'       => '',
			'llms_cache_ts'    => 0,
			'llms_cache_rev'   => 0,
			'llms_cache_hash'  => '',
			'llms_cache_settings_hash' => '',
		);
	}

	/**
	 * Check whether a post type can be exported by the plugin.
	 *
	 * @param string $post_type Post type key.
	 * @return bool True when the post type is public and supported.
	 */
	public function is_exportable_post_type( string $post_type ): bool {
		$post_type = sanitize_key( (string) $post_type );
		if ( $post_type === '' || $post_type === 'attachment' ) {
			return false;
		}

		if ( ! function_exists( 'post_type_exists' ) || ! post_type_exists( $post_type ) ) {
			return false;
		}

		$obj = get_post_type_object( $post_type );

		return $obj && ! empty( $obj->public );
	}

	/**
	 * Check whether a post may be exposed by public plugin outputs.
	 *
	 * @param WP_Post $post    Post object.
	 * @param string  $context Export context passed to llmf_can_export_post.
	 * @return bool True when the post is safe to expose.
	 */
	public function can_export_post( WP_Post $post, string $context ): bool {
		if ( ! $this->is_public_export_candidate( $post ) ) {
			return false;
		}

		if ( $this->is_post_excluded( $post ) ) {
			return false;
		}

		return (bool) apply_filters( 'llmf_can_export_post', true, $post, $context );
	}

	/**
	 * Return exportable public post type objects.
	 *
	 * @return array<string,object> Public post type objects keyed by name.
	 */
	public function exportable_post_types(): array {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		if ( ! is_array( $post_types ) ) {
			return array();
		}

		$out = array();
		foreach ( $post_types as $name => $obj ) {
			$key = sanitize_key( (string) $name );
			if ( $this->is_exportable_post_type( $key ) ) {
				$out[ $key ] = $obj;
			}
		}

		return $out;
	}

	/**
	 * Sanitize a list of selected post types.
	 *
	 * @param mixed $raw Raw post type list.
	 * @return array<int,string> Exportable post type keys.
	 */
	public function sanitize_post_types( $raw ): array {
		$raw = is_array( $raw ) ? $raw : array();
		$out = array();

		foreach ( $raw as $post_type ) {
			$post_type = sanitize_key( (string) $post_type );
			if ( $this->is_exportable_post_type( $post_type ) ) {
				$out[] = $post_type;
			}
		}

		$out = array_values( array_unique( $out ) );
		if ( ! empty( $out ) ) {
			return $out;
		}

		if ( $this->is_exportable_post_type( 'post' ) ) {
			return array( 'post' );
		}

		$available = array_keys( $this->exportable_post_types() );

		return ! empty( $available ) ? array( (string) $available[0] ) : array();
	}

	/**
	 * Sanitize custom markdown inserted into llms.txt.
	 *
	 * We do NOT attempt to fully parse Markdown here; the goal is to keep it as-is
	 * while normalizing newlines and trimming.
	 *
	 * @param string $value
	 * @return string
	 */
	private function sanitize_llms_custom_markdown( $value ) {
		$value = wp_unslash( (string) $value );
		$clean = $this->sanitize_markdown_block( (string) $value );
		$clean = $this->limit_markdown_length( $clean, self::CUSTOM_MD_MAX_LENGTH );

		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$clean = wp_kses_post( $clean );
		}

		return $clean;
	}

	/**
	 * Sanitize per-post Markdown override while preserving Markdown structure.
	 *
	 * @param mixed $value Raw meta value.
	 * @return string Sanitized Markdown.
	 */
	public function sanitize_markdown_override( $value ): string {
		$value = is_string( $value ) ? $value : '';
		$value = wp_unslash( $value );
		$value = $this->sanitize_markdown_block( $value );
		$max   = (int) apply_filters( 'llmf_markdown_override_max_length', self::MARKDOWN_OVERRIDE_MAX_LENGTH );
		$value = $this->limit_markdown_length( $value, $max );

		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$value = wp_kses_post( $value );
		}

		return (string) $value;
	}

	/**
	 * Sanitize the per-post one-line llms.txt description.
	 *
	 * @param mixed $value Raw description.
	 * @return string Sanitized description.
	 */
	public function sanitize_llms_description( $value ): string {
		$value = is_string( $value ) ? $value : '';
		$value = $this->sanitize_textline( $value );
		$max   = (int) apply_filters( 'llmf_llms_description_max_length', self::LLMS_DESCRIPTION_MAX_LENGTH );
		if ( $max < 1 ) {
			$max = self::LLMS_DESCRIPTION_MAX_LENGTH;
		}

		return $this->limit_markdown_length( $value, $max );
	}

	/**
	 * Sanitize base path used in URLs (no leading/trailing slashes).
	 *
	 * @param string $path
	 * @return string
	 */
	public function sanitize_base_path( $path ) {
		$path = (string) $path;
		$path = trim( $path );
		$path = trim( $path, "/ \t\n\r\0\x0B" );
		$path = preg_replace( '~[^a-zA-Z0-9\-_]~', '-', $path );
		$path = preg_replace( '~-{2,}~', '-', (string) $path );
		$path = strtolower( (string) $path );
		return $path !== '' ? $path : 'llm';
	}

	/**
	 * Compute export path for a post (supports hierarchy).
	 *
	 * @param WP_Post $post
	 * @return string
	 */
	public function post_path( WP_Post $post ) {
		$pt_obj = get_post_type_object( $post->post_type );
		$hier   = $pt_obj && ! empty( $pt_obj->hierarchical );

		if ( $hier ) {
			$uri = get_page_uri( $post );
			$uri = is_string( $uri ) ? $uri : '';
			return ltrim( $uri, '/' );
		}

		return (string) $post->post_name;
	}

	/**
	 * Build the public Markdown export URL for a post.
	 *
	 * @param WP_Post $post Post object.
	 * @return string Markdown URL or empty string.
	 */
	public function markdown_url_for_post( WP_Post $post ): string {
		if ( ! $this->is_exportable_post_type( (string) $post->post_type ) ) {
			return '';
		}

		$opt  = $this->get();
		$base = $this->sanitize_base_path( isset( $opt['base_path'] ) ? (string) $opt['base_path'] : 'llm' );
		$path = $this->post_path( $post );

		if ( $base === '' || $path === '' ) {
			return '';
		}

		return home_url( '/' . $base . '/' . rawurlencode( (string) $post->post_type ) . '/' . $this->rawurlencode_path( $path ) . '.md' );
	}

	/**
	 * Get site title for llms.txt (override or WP setting).
	 *
	 * @return string
	 */
	public function site_title() {
		$opt = $this->get();
		$t   = trim( (string) $opt['site_title_override'] );
		if ( $t !== '' ) {
			return $t;
		}

		$wp = (string) get_bloginfo( 'name' );
		return trim( $wp );
	}

	/**
	 * Get site description for llms.txt (override or WP setting).
	 *
	 * @return string
	 */
	public function site_description() {
		$opt = $this->get();
		$d   = trim( (string) $opt['site_description_override'] );
		if ( $d !== '' ) {
			return $d;
		}

		$wp = (string) get_bloginfo( 'description' );
		return trim( $wp );
	}

	/**
	 * Get the global Markdown metadata author override.
	 *
	 * @return string
	 */
	public function site_author_override(): string {
		$opt = $this->get();

		return $this->sanitize_textline( isset( $opt['site_author_override'] ) ? $opt['site_author_override'] : '' );
	}

	/**
	 * Get the author name for Markdown metadata.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	public function author_name_for_post( WP_Post $post ): string {
		$override = $this->site_author_override();
		if ( $override !== '' ) {
			return $override;
		}

		$author = get_the_author_meta( 'display_name', (int) $post->post_author );

		return $this->sanitize_textline( is_string( $author ) ? $author : '' );
	}

	/**
	 * Get the publisher name for Markdown metadata.
	 *
	 * @return string
	 */
	public function publisher_name(): string {
		return $this->sanitize_textline( $this->site_title() );
	}

	/**
	 * Get the best one-line description for llms.txt and Markdown metadata.
	 *
	 * Priority: explicit LLM description, SEO meta description, explicit excerpt,
	 * then a short content-derived fallback.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	public function llms_description_for_post( WP_Post $post ): string {
		if ( ! ( $post instanceof WP_Post ) ) {
			return '';
		}

		$custom = get_post_meta( $post->ID, self::META_LLMS_DESCRIPTION, true );
		$custom = $this->sanitize_llms_description( is_string( $custom ) ? $custom : '' );
		if ( $custom !== '' ) {
			return $custom;
		}

		$seo = $this->seo_description_for_post( $post );
		if ( $seo !== '' ) {
			return $seo;
		}

		$excerpt = $this->explicit_excerpt_for_post( $post );
		if ( $excerpt !== '' ) {
			return $excerpt;
		}

		$raw     = wp_strip_all_tags( (string) $post->post_content, true );
		$raw     = html_entity_decode( $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$raw     = $this->sanitize_textline( $raw );
		$summary = wp_trim_words( $raw, 30, '…' );

		return $this->sanitize_llms_description( $summary );
	}

	/**
	 * Normalize and validate sitemap URL/path.
	 *
	 * Accepts absolute URL or site-relative path.
	 *
	 * @param string $value
	 * @return string
	 */
	public function sanitize_sitemap_url( $value ) {
		$value = html_entity_decode( wp_strip_all_tags( (string) $value, true ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$value = preg_replace( '/[\x00-\x1F\x7F]+/u', '', (string) $value );
		$value = trim( (string) $value );
		if ( $value === '' ) {
			return '/sitemap.xml';
		}

		if ( preg_match( '~^https?://~i', $value ) ) {
			$url = esc_url_raw( $value, array( 'http', 'https' ) );

			if ( $url === '' ) {
				return '/sitemap.xml';
			}

			if ( $this->is_same_origin_url( $url ) || apply_filters( 'llmf_allow_external_sitemap_url', false, $url ) ) {
				return $url;
			}

			return '/sitemap.xml';
		}

		if ( preg_match( '~^[a-z][a-z0-9+.-]*:~i', $value ) || strpos( $value, '//' ) === 0 ) {
			return '/sitemap.xml';
		}

		if ( $value[0] !== '/' ) {
			$value = '/' . $value;
		}

		$value = str_replace( array( '<', '>', '"', "'" ), '', $value );
		$value = esc_url_raw( $value );

		// Keep as path; will be expanded via home_url() when output.
		return $value !== '' ? $value : '/sitemap.xml';
	}

	/**
	 * Sanitize a custom Markdown block for insertion into llms.txt.
	 *
	 * The method normalizes newlines, removes null bytes, and trims whitespace
	 * to prepare safe insertion without breaking Markdown formatting.
	 *
	 * @param string $md Custom Markdown block provided by the user.
	 *
	 * @return string Cleaned Markdown without control bytes and with unified line breaks.
	 */
	private function sanitize_markdown_block( $md ) {
		$md = (string) $md;
		$md = str_replace( array( "\r\n", "\r" ), "\n", $md );
		$md = preg_replace( '/\x00+/', '', $md );
		$md = trim( $md );

		return $md;
	}

	/**
	 * Get SEO plugin description metadata for a post.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	private function seo_description_for_post( WP_Post $post ): string {
		$keys = array( '_yoast_wpseo_metadesc', 'wpseo_metadesc' );

		foreach ( $keys as $key ) {
			$value = get_post_meta( $post->ID, $key, true );
			$value = $this->sanitize_llms_description( is_string( $value ) ? $value : '' );
			if ( $value !== '' ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Get only the explicit WordPress excerpt for a post.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	private function explicit_excerpt_for_post( WP_Post $post ): string {
		if ( trim( (string) $post->post_excerpt ) === '' ) {
			return '';
		}

		$excerpt = get_the_excerpt( $post );

		return $this->sanitize_llms_description( is_string( $excerpt ) ? $excerpt : '' );
	}

	/**
	 * Normalize the exclusion list into a safe structure.
	 *
	 * Stored structure:
	 * [
	 *   'post' => [ 12, 15 ],
	 *   'page' => [ 22 ]
	 * ]
	 *
	 * @param mixed $raw Input data from the settings form.
	 *
	 * @return array<string,array<int,int>> Filtered IDs by post type.
	 */
	public function sanitize_excluded_posts( $raw ): array {
		$result = array();

		if ( ! is_array( $raw ) ) {
			return $result;
		}

		foreach ( $raw as $type => $ids ) {
			$type = sanitize_key( (string) $type );
			if ( $type === '' || ! $this->is_exportable_post_type( $type ) || ! is_array( $ids ) ) {
				continue;
			}

			$max_ids = (int) apply_filters( 'llmf_max_excluded_posts_per_type', self::EXCLUDED_POSTS_MAX_PER_TYPE, $type );
			if ( $max_ids < 1 ) {
				$max_ids = self::EXCLUDED_POSTS_MAX_PER_TYPE;
			}

			$clean_ids = array();
			foreach ( $ids as $id ) {
				$id = (int) $id;
				if ( $id <= 0 || in_array( $id, $clean_ids, true ) ) {
					continue;
				}

				$post = get_post( $id );
				if ( ! ( $post instanceof WP_Post ) || (string) $post->post_type !== $type ) {
					continue;
				}

				if ( ! $this->is_public_export_candidate( $post ) ) {
					continue;
				}

				if ( ! apply_filters( 'llmf_can_export_post', true, $post, 'llms' ) ) {
					continue;
				}

				$clean_ids[] = $id;
				if ( count( $clean_ids ) >= $max_ids ) {
					break;
				}
			}

			if ( ! empty( $clean_ids ) ) {
				$result[ $type ] = $clean_ids;
			}
		}

		return $result;
	}

	/**
	 * Return list of IDs excluded from export for a given post type.
	 *
	 * @param string $post_type Post type.
	 *
	 * @return array<int,int> List of unique post IDs.
	 */
	public function excluded_post_ids( string $post_type ): array {
		$post_type = sanitize_key( (string) $post_type );
		if ( $post_type === '' ) {
			return array();
		}

		$opt = $this->get();
		$map = isset( $opt['excluded_posts'] ) && is_array( $opt['excluded_posts'] ) ? $opt['excluded_posts'] : array();
		if ( ! isset( $map[ $post_type ] ) || ! is_array( $map[ $post_type ] ) ) {
			return array();
		}

		$out = array();
		foreach ( $map[ $post_type ] as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				$out[] = $id;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Check whether a post is excluded from llms.txt and Markdown exports.
	 *
	 * @param WP_Post $post Post object.
	 *
	 * @return bool True if the post is marked as excluded.
	 */
	public function is_post_excluded( WP_Post $post ): bool {
		if ( ! ( $post instanceof WP_Post ) ) {
			return false;
		}

		$list = $this->excluded_post_ids( $post->post_type );

		return in_array( (int) $post->ID, $list, true );
	}

	/**
	 * Convert a sitemap setting into an absolute URL.
	 *
	 * @return string
	 */
	public function sitemap_absolute_url() {
		$opt = $this->get();
		$v   = $this->sanitize_sitemap_url( (string) $opt['sitemap_url'] );

		if ( preg_match( '~^https?://~i', $v ) ) {
			return esc_url_raw( $v, array( 'http', 'https' ) );
		}

		return home_url( $v );
	}

	/**
	 * Check whether a post is a public non-password-protected export candidate.
	 *
	 * @param WP_Post $post Post object.
	 * @return bool True when the post passes base export constraints.
	 */
	private function is_public_export_candidate( WP_Post $post ): bool {
		if ( ! $this->is_exportable_post_type( (string) $post->post_type ) ) {
			return false;
		}

		if ( $post->post_status !== 'publish' ) {
			return false;
		}

		return empty( $post->post_password ) && ! post_password_required( $post );
	}

	/**
	 * Limit Markdown text length without breaking multibyte strings when possible.
	 *
	 * @param string $value Markdown text.
	 * @param int    $max   Maximum character length.
	 * @return string Limited Markdown text.
	 */
	private function limit_markdown_length( string $value, int $max ): string {
		if ( $max <= 0 ) {
			return $value;
		}

		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $value, 'UTF-8' ) > $max ) {
				return mb_substr( $value, 0, $max, 'UTF-8' );
			}

			return $value;
		}

		return strlen( $value ) > $max ? substr( $value, 0, $max ) : $value;
	}

	/**
	 * Check whether an absolute URL points to the current site's origin.
	 *
	 * @param string $url Absolute URL.
	 * @return bool True when scheme, host, and port match home_url().
	 */
	private function is_same_origin_url( string $url ): bool {
		$target = wp_parse_url( $url );
		$home   = wp_parse_url( home_url( '/' ) );

		if ( ! is_array( $target ) || ! is_array( $home ) ) {
			return false;
		}

		$target_scheme = isset( $target['scheme'] ) ? strtolower( (string) $target['scheme'] ) : '';
		$home_scheme   = isset( $home['scheme'] ) ? strtolower( (string) $home['scheme'] ) : '';
		$target_host   = isset( $target['host'] ) ? strtolower( (string) $target['host'] ) : '';
		$home_host     = isset( $home['host'] ) ? strtolower( (string) $home['host'] ) : '';

		if ( $target_scheme === '' || $home_scheme === '' || $target_host === '' || $home_host === '' ) {
			return false;
		}

		$target_port = isset( $target['port'] ) ? (int) $target['port'] : $this->default_port_for_scheme( $target_scheme );
		$home_port   = isset( $home['port'] ) ? (int) $home['port'] : $this->default_port_for_scheme( $home_scheme );

		return $target_scheme === $home_scheme && $target_host === $home_host && $target_port === $home_port;
	}

	/**
	 * Return default port for an URL scheme.
	 *
	 * @param string $scheme URL scheme.
	 * @return int Default port or 0.
	 */
	private function default_port_for_scheme( string $scheme ): int {
		if ( $scheme === 'https' ) {
			return 443;
		}

		if ( $scheme === 'http' ) {
			return 80;
		}

		return 0;
	}

	/**
	 * Rawurlencode each segment of a path while preserving slashes.
	 *
	 * @param string $path Raw path.
	 * @return string Encoded path.
	 */
	private function rawurlencode_path( string $path ): string {
		$path  = ltrim( (string) $path, '/' );
		$parts = explode( '/', $path );
		$out   = array();

		foreach ( $parts as $part ) {
			$out[] = rawurlencode( (string) $part );
		}

		return implode( '/', $out );
	}

	/**
	 * Sanitize one-line text.
	 *
	 * @param mixed $value
	 * @return string
	 */
	private function sanitize_textline( $value ) {
		$s = (string) $value;
		$s = wp_strip_all_tags( $s, true );
		$s = html_entity_decode( $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$s = str_replace( array( "\r\n", "\r", "\n" ), ' ', $s );
		$s = preg_replace( '/\s+/u', ' ', $s );
		return trim( (string) $s );
	}
}
