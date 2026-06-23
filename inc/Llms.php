<?php

namespace LLMFriendly;

use WP_Post;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * llms.txt generation, caching, and output.
 */
final class Llms {
	/**
	 * Regeneration lock transient key.
	 */
	private const LOCK_KEY = 'llmf_llms_regen_lock';

	/**
	 * @var Options
	 */
	private Options $options;

	/**
	 * @param Options $options
	 */
	public function __construct( Options $options ) {
		$this->options = $options;

		add_action( 'save_post', array( $this, 'maybe_regenerate_on_save' ), 20, 3 );
		add_action( 'transition_post_status', array( $this, 'maybe_regenerate_on_status_change' ), 20, 3 );
		add_action( 'delete_post', array( $this, 'maybe_regenerate_on_delete' ), 20, 2 );
	}

	/**
	 * Regenerate llms.txt cache if mode is auto and post fits criteria.
	 *
	 * @param int $post_id
	 * @param WP_Post $post
	 * @param bool $update
	 *
	 * @return void
	 */
	public function maybe_regenerate_on_save( int $post_id, WP_Post $post, bool $update ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( $post->post_status !== 'publish' ) {
			return;
		}

		$this->maybe_regenerate_for_post( $post );
	}

	/**
	 * Regenerate cache when a published exportable post changes visibility.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function maybe_regenerate_on_status_change( string $new_status, string $old_status, WP_Post $post ): void {
		if ( $new_status === $old_status ) {
			return;
		}

		if ( $new_status !== 'publish' && $old_status !== 'publish' ) {
			return;
		}

		$this->maybe_regenerate_for_post( $post );
	}

	/**
	 * Regenerate cache when an exportable post is deleted.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function maybe_regenerate_on_delete( int $post_id, WP_Post $post ): void {
		if ( ! ( $post instanceof WP_Post ) || $post->post_status !== 'publish' ) {
			return;
		}

		$this->maybe_regenerate_for_post( $post );
	}

	/**
	 * Regenerate llms.txt when auto mode is enabled and the post is selected.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	private function maybe_regenerate_for_post( WP_Post $post ): void {
		$opt = $this->options->get();

		if ( empty( $opt['enabled_llms_txt'] ) || (string) $opt['llms_regen_mode'] !== 'auto' ) {
			return;
		}

		$allowed = isset( $opt['post_types'] ) && is_array( $opt['post_types'] ) ? $this->options->sanitize_post_types( $opt['post_types'] ) : array();
		if ( ! $this->options->is_exportable_post_type( (string) $post->post_type ) || ! in_array( $post->post_type, $allowed, true ) ) {
			return;
		}

		$this->regenerate( true );
	}

	/**
	 * Force regeneration of cached llms.txt.
	 *
	 * @return void
	 */
	public function regenerate( bool $force = false ): void {
		// Manual regeneration must work regardless of the selected regeneration mode.
		// The mode controls only automatic regeneration on publish/update.
		$lock_acquired = false;

		if ( ! $force ) {
			$locked = get_transient( self::LOCK_KEY );
			if ( $locked ) {
				return;
			}
			set_transient( self::LOCK_KEY, 1, 10 );
			$lock_acquired = true;
		} else {
			delete_transient( self::LOCK_KEY );
		}

		$content = $this->build_llms_txt();

		// Update ONLY cache fields to avoid overwriting settings in concurrent requests.
		$saved = get_option( Options::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$prev_ts = isset( $saved['llms_cache_ts'] ) ? (int) $saved['llms_cache_ts'] : 0;
		$now     = time();
		$ts      = ( $now <= $prev_ts ) ? ( $prev_ts + 1 ) : $now;
		$rev     = isset( $saved['llms_cache_rev'] ) ? (int) $saved['llms_cache_rev'] : 0;
		$rev ++;

		$saved['llms_cache']     = (string) $content;
		$saved['llms_cache_ts']  = $ts;
		$saved['llms_cache_rev'] = $rev;

		$settings        = $this->options->get();
		$settings_subset = array(
			'enabled_markdown'          => ! empty( $settings['enabled_markdown'] ) ? 1 : 0,
			'enabled_llms_txt'          => ! empty( $settings['enabled_llms_txt'] ) ? 1 : 0,
			'base_path'                 => isset( $settings['base_path'] ) ? (string) $settings['base_path'] : '',
			'post_types'                => ( isset( $settings['post_types'] ) && is_array( $settings['post_types'] ) ) ? array_values( (array) $settings['post_types'] ) : array(),
			'llms_recent_limit'         => isset( $settings['llms_recent_limit'] ) ? (int) $settings['llms_recent_limit'] : 0,
			'site_title_override'       => isset( $settings['site_title_override'] ) ? (string) $settings['site_title_override'] : '',
			'site_description_override' => isset( $settings['site_description_override'] ) ? (string) $settings['site_description_override'] : '',
			'sitemap_url'               => isset( $settings['sitemap_url'] ) ? (string) $settings['sitemap_url'] : '',
			'llms_custom_markdown'      => isset( $settings['llms_custom_markdown'] ) ? (string) $settings['llms_custom_markdown'] : '',
			'llms_show_excerpt'         => ! empty( $settings['llms_show_excerpt'] ) ? 1 : 0,
			'excluded_posts'            => isset( $settings['excluded_posts'] ) && is_array( $settings['excluded_posts'] ) ? $settings['excluded_posts'] : array(),
		);

		$saved['llms_cache_hash']          = sha1( (string) $content );
		$saved['llms_cache_settings_hash'] = sha1( wp_json_encode( $settings_subset ) );

		update_option( Options::OPTION_KEY, $saved, false );

		if ( $lock_acquired ) {
			delete_transient( self::LOCK_KEY );
		}
	}


	/**
	 * Output llms.txt (cached when possible).
	 *
	 * @return void
	 */
	public function output(): void {
		$opt = $this->options->get();

		if ( empty( $opt['enabled_llms_txt'] ) ) {
			status_header( 404 );
			echo esc_html__( 'Not Found', 'llm-friendly' );
			exit;
		}

		$content = isset( $opt['llms_cache'] ) ? (string) $opt['llms_cache'] : '';
		$ts      = isset( $opt['llms_cache_ts'] ) ? (int) $opt['llms_cache_ts'] : 0;

		if ( trim( $content ) === '' ) {
			if ( get_transient( self::LOCK_KEY ) ) {
				Response::send_service_unavailable( 10 );
			}

			$this->regenerate( false );
			$opt     = $this->options->get();
			$content = isset( $opt['llms_cache'] ) ? (string) $opt['llms_cache'] : '';
			$ts      = isset( $opt['llms_cache_ts'] ) ? (int) $opt['llms_cache_ts'] : 0;

			if ( trim( $content ) === '' ) {
				Response::send_service_unavailable( 10 );
			}
		}

		$rev_etag_part = isset( $opt['llms_cache_rev'] ) ? (int) $opt['llms_cache_rev'] : 0;

		// Normalize Markdown for output and compute ETag from the actual response body.
		$md            = rtrim( Markdown::normalize_blocks( $content ) ) . "\n";
		$etag          = '"' . sha1( $md . '|' . $ts . '|' . $rev_etag_part ) . '"';
		$rev           = isset( $opt['llms_cache_rev'] ) ? (int) $opt['llms_cache_rev'] : 0;
		$hash          = isset( $opt['llms_cache_hash'] ) ? (string) $opt['llms_cache_hash'] : '';
		$settings_hash = isset( $opt['llms_cache_settings_hash'] ) ? (string) $opt['llms_cache_settings_hash'] : '';

		$headers = array(
			'Content-Type: text/markdown; charset=UTF-8',
			'X-Content-Type-Options: nosniff',
		);

		if ( ! empty( $opt['llms_send_noindex'] ) ) {
			$headers[] = 'X-Robots-Tag: noindex, nofollow';
		}

		if ( apply_filters( 'llmf_debug_headers_enabled', false ) ) {
			if ( $ts > 0 ) {
				$headers['X-LLMF-Build'] = (string) $ts;
			}
			if ( $rev > 0 ) {
				$headers['X-LLMF-Rev'] = (string) $rev;
			}
			if ( $hash !== '' ) {
				$headers['X-LLMF-Hash'] = $hash;
			}
			if ( $settings_hash !== '' ) {
				$headers['X-LLMF-Settings-Hash'] = $settings_hash;
			}
		}

		Response::send_conditional_headers(
			$headers,
			$etag,
			$ts > 0 ? $ts : time()
		);
		echo $md; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/markdown body is sanitized while being built.
		exit;
	}

	/**
	 * Build llms.txt content in llmstxt.org format.
	 *
	 * @return string
	 */
	private function build_llms_txt(): string {
		$opt = $this->options->get();

		$title = Markdown::plain_text_line( $this->options->site_title() );
		$desc  = Markdown::plain_text_line( $this->options->site_description() );

		if ( $desc === '' ) {
			$desc = __( 'LLM-friendly index of this website.', 'llm-friendly' );
		}

		$home         = Markdown::url_destination( home_url( '/' ), array( 'http', 'https' ), false );
		$rss          = Markdown::url_destination( get_feed_link(), array( 'http', 'https' ), false );
		$sitemap      = Markdown::url_destination( $this->options->sitemap_absolute_url(), array( 'http', 'https' ), false );
		$md_enabled   = ! empty( $opt['enabled_markdown'] );
		$show_excerpt = ! empty( $opt['llms_show_excerpt'] );

		$limit = isset( $opt['llms_recent_limit'] ) ? (int) $opt['llms_recent_limit'] : 30;
		if ( $limit < 1 ) {
			$limit = 1;
		}

		$post_types = ( isset( $opt['post_types'] ) && is_array( $opt['post_types'] ) ) ? $this->options->sanitize_post_types( $opt['post_types'] ) : array( 'post' );

		$blocks = array();

		$blocks[] = '# ' . ( $title !== '' ? $title : Markdown::plain_text_line( home_url() ) );
		$blocks[] = '> ' . $desc;

		// Optional custom markdown block (inserted between site meta and the content sections).
		$custom = isset( $opt['llms_custom_markdown'] ) ? (string) $opt['llms_custom_markdown'] : '';
		$custom = trim( $custom );
		if ( $custom !== '' ) {
			$blocks[] = $custom;
		}

		$blocks[] = '## ' . 'Main links';
		$blocks[] = implode(
			"\n",
			array(
				'- [' . $this->md_link_text( 'Home' ) . '](' . $home . '): ' . 'Website home page',
				'- [' . $this->md_link_text( 'Sitemap' ) . '](' . $sitemap . '): ' . 'XML sitemap',
				'- [' . $this->md_link_text( 'RSS' ) . '](' . $rss . '): ' . 'Latest updates feed',
			)
		);

		foreach ( $post_types as $pt ) {
			$pt = sanitize_key( (string) $pt );
			if ( $pt === '' ) {
				continue;
			}

			if ( ! $this->options->is_exportable_post_type( $pt ) ) {
				continue;
			}

			$label    = $this->post_type_label( $pt );
			$blocks[] = '## ' . $label;

			$items = $this->get_recent_posts_by_type( $pt, $limit );
			if ( empty( $items ) ) {
				$blocks[] = '- ' . __( '(no published items found)', 'llm-friendly' );
				continue;
			}

			$item_blocks = array();


			foreach ( $items as $item ) {
				$item_lines = array();
				$title_txt  = isset( $item['title'] ) ? (string) $item['title'] : '';
				$md_url     = isset( $item['md_url'] ) ? (string) $item['md_url'] : '';
				$canonical  = isset( $item['canonical'] ) ? (string) $item['canonical'] : '';
				$modified   = isset( $item['modified'] ) ? (string) $item['modified'] : '';
				$excerpt    = isset( $item['excerpt'] ) ? (string) $item['excerpt'] : '';

				if ( $md_enabled ) {
					$md_url       = Markdown::url_destination( $md_url, array( 'http', 'https' ), false );
					$notes        = sprintf(
					/* translators: 1: modified date, 2: canonical url */
						__( 'Updated %1$s. Canonical URL: %2$s', 'llm-friendly' ),
						$modified,
						$canonical
					);
					if ( $md_url !== '' ) {
						$item_lines[] = '- [' . $this->md_link_text( $title_txt ) . '](' . $md_url . '): ' . Markdown::plain_text_line( $notes );
					}
				} else {
					$notes        = sprintf(
					/* translators: 1: modified date */
						__( 'Updated %1$s.', 'llm-friendly' ),
						$modified
					);
					if ( $canonical !== '' ) {
						$item_lines[] = '- [' . $this->md_link_text( $title_txt ) . '](' . $canonical . '): ' . Markdown::plain_text_line( $notes );
					}
				}

				if ( ! empty( $item_lines ) && $show_excerpt && $excerpt !== '' ) {
					// Keep the excerpt inside the list item by indenting it.
					$item_lines[] = '  ';
					$item_lines[] = '  ' . Markdown::plain_text_line( $excerpt );
					$item_lines[] = '  ';
				}
				if ( ! empty( $item_lines ) ) {
					$item_blocks[] = implode( "\n", $item_lines );
				}
			}

			$blocks[] = implode( "\n\n", $item_blocks );
		}

		$blocks  = array_values( array_filter( array_map( 'trim', $blocks ), 'strlen' ) );
		$content = implode( "\n\n", $blocks );

		return rtrim( $content, "\n" ) . "\n";
	}

	/**
	 * Get recent posts for a given post type.
	 *
	 * @param string $post_type
	 * @param int $limit
	 *
	 * @return array<int,array<string,string>>
	 */
	private function get_recent_posts_by_type( $post_type, $limit ): array {
		$post_type = sanitize_key( (string) $post_type );
		if ( ! $this->options->is_exportable_post_type( $post_type ) ) {
			return array();
		}

		$excluded_ids = $this->options->excluded_post_ids( (string) $post_type );

		// Target number of items based on the requested limit.
		$max_items = max( 0, (int) $limit );
		if ( $max_items === 0 ) {
			return array();
		}

		// Account for excluded posts up front to avoid using post__not_in (expensive) and still fill the list.
		$requested_posts = $max_items;
		if ( ! empty( $excluded_ids ) ) {
			$requested_posts += count( $excluded_ids );
		}
		$requested_posts = max( 1, $requested_posts );

		$query_args = array(
			'post_type'              => $post_type,
			'post_status'            => 'publish',
			'posts_per_page'         => $requested_posts,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$q = new WP_Query( $query_args );

		$items = array();

		foreach ( (array) $q->posts as $p ) {
			if ( ! ( $p instanceof WP_Post ) ) {
				continue;
			}
			// Skip excluded posts in PHP to avoid expensive post__not_in in WP_Query.
			if ( in_array( (int) $p->ID, (array) $excluded_ids, true ) ) {
				continue;
			}
			// Skip password-protected posts.
			if ( ! empty( $p->post_password ) ) {
				continue;
			}
			$can = apply_filters( 'llmf_can_export_post', true, $p, 'llms' );
			if ( ! $can ) {
				continue;
			}
			if ( $this->options->is_post_excluded( $p ) ) {
				continue;
			}

			$items[] = array(
				'title'     => Markdown::plain_text_line( get_the_title( $p ) ),
				'md_url'    => (string) $this->options->markdown_url_for_post( $p ),
				'canonical' => Markdown::url_destination( get_permalink( $p ), array( 'http', 'https' ), false ),
				'modified'  => Markdown::plain_text_line( get_the_modified_date( 'Y-m-d', $p ) ),
				'excerpt'   => Markdown::plain_text_line( $this->post_excerpt_one_line( $p ) ),
			);

			if ( count( $items ) >= $max_items ) {
				break;
			}
		}

		return $items;
	}

	/**
	 * Get post type plural label.
	 *
	 * @param string $post_type
	 *
	 * @return string
	 */
	private function post_type_label( $post_type ) {
		$obj = get_post_type_object( $post_type );
		if ( $obj && isset( $obj->labels ) && isset( $obj->labels->name ) && is_string( $obj->labels->name ) && $obj->labels->name !== '' ) {
			return Markdown::plain_text_line( $obj->labels->name );
		}

		return Markdown::plain_text_line( $post_type );
	}

	/**
	 * Normalize string to one line.
	 *
	 * @param string $s
	 *
	 * @return string
	 */
	private function one_line( $s ) {
		return Markdown::plain_text_line( $s );
	}

	/**
	 * Get post excerpt as a single line of plain text.
	 *
	 * @param WP_Post $post
	 *
	 * @return string
	 */
	private function post_excerpt_one_line( WP_Post $post ): string {
		if ( ! ( $post instanceof WP_Post ) ) {
			return '';
		}

		$excerpt = '';

		// 1) Prefer an explicit WordPress excerpt (post_excerpt). Avoid auto-generated excerpts.
		$has_wp_excerpt = trim( (string) $post->post_excerpt ) !== '';
		if ( $has_wp_excerpt ) {
			$excerpt = get_the_excerpt( $post );
			if ( ! is_string( $excerpt ) ) {
				$excerpt = '';
			}
		}

		// 2) If there's no WP excerpt, try Yoast SEO meta description.
		if ( $excerpt === '' ) {
			$yoast = get_post_meta( $post->ID, 'wpseo_metadesc', true );
			if ( ! is_string( $yoast ) ) {
				$yoast = '';
			}
			$yoast = trim( $yoast );

			if ( $yoast === '' ) {
				$yoast = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
				if ( ! is_string( $yoast ) ) {
					$yoast = '';
				}
				$yoast = trim( $yoast );
			}

			if ( $yoast !== '' ) {
				$excerpt = $yoast;
			}
		}

		// 3) Fallback: derive a short snippet from content.
		if ( $excerpt === '' ) {
			$raw     = wp_strip_all_tags( (string) $post->post_content, true );
			$raw     = html_entity_decode( $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$raw     = $this->one_line( $raw );
			$excerpt = wp_trim_words( $raw, 30, '…' );
		}

		$excerpt = wp_strip_all_tags( (string) $excerpt, true );
		$excerpt = html_entity_decode( $excerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$excerpt = $this->one_line( $excerpt );

		return $excerpt;
	}

	/**
	 * Escape minimal Markdown for link text.
	 *
	 * @param string $s
	 *
	 * @return string
	 */
	private function md_link_text( $s ): string {
		return Markdown::link_text( $s );
	}
}
