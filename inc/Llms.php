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
	 * @var Options
	 */
	private Options $options;

	/**
	 * @param Options $options
	 */
	public function __construct( Options $options ) {
		$this->options = $options;

		add_action( 'save_post', array( $this, 'maybe_regenerate_on_save' ), 20, 3 );
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
		$opt = $this->options->get();

		if ( empty( $opt['enabled_llms_txt'] ) ) {
			return;
		}

		if ( (string) $opt['llms_regen_mode'] !== 'auto' ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}

		if ( $post->post_status !== 'publish' ) {
			return;
		}

		$allowed = is_array( $opt['post_types'] ) ? $opt['post_types'] : array();
		if ( ! in_array( $post->post_type, $allowed, true ) ) {
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
		$lock_key = 'llmf_llms_regen_lock';

		if ( ! $force ) {
			$locked = get_transient( $lock_key );
			if ( $locked ) {
				return;
			}
			set_transient( $lock_key, 1, 10 );
		} else {
			delete_transient( $lock_key );
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
			$this->regenerate( true );
			$opt     = $this->options->get();
			$content = (string) $opt['llms_cache'];
			$ts      = (int) $opt['llms_cache_ts'];
		}

		if ( ! empty( $opt['llms_send_noindex'] ) ) {
			header( 'X-Robots-Tag: noindex, nofollow' );
		}

		$rev_etag_part = isset( $opt['llms_cache_rev'] ) ? (int) $opt['llms_cache_rev'] : 0;

		// Normalize Markdown for output and compute ETag from the actual response body.
		$md            = $this->llmf_normalize_markdown_blocks( $content );
		$etag          = '"' . sha1( $md . '|' . $ts . '|' . $rev_etag_part ) . '"';
		$rev           = isset( $opt['llms_cache_rev'] ) ? (int) $opt['llms_cache_rev'] : 0;
		$hash          = isset( $opt['llms_cache_hash'] ) ? (string) $opt['llms_cache_hash'] : '';
		$settings_hash = isset( $opt['llms_cache_settings_hash'] ) ? (string) $opt['llms_cache_settings_hash'] : '';

		$this->send_common_headers(
			array( 'Content-Type: text/markdown; charset=UTF-8' ),
			$etag,
			$ts > 0 ? $ts : time(),
			$ts,
			$rev,
			$hash,
			$settings_hash
		);
		echo wp_strip_all_tags( $md, false );
		exit;
	}

	/**
	 * Build llms.txt content in llmstxt.org format.
	 *
	 * @return string
	 */
	private function build_llms_txt(): string {
		$opt = $this->options->get();

		$title = $this->one_line( $this->options->site_title() );
		$desc  = $this->one_line( $this->options->site_description() );

		if ( $desc === '' ) {
			$desc = __( 'LLM-friendly index of this website.', 'llm-friendly' );
		}

		$base         = $this->options->sanitize_base_path( isset( $opt['base_path'] ) ? (string) $opt['base_path'] : 'llm' );
		$home         = home_url( '/' );
		$rss          = get_feed_link();
		$sitemap      = $this->options->sitemap_absolute_url();
		$md_enabled   = ! empty( $opt['enabled_markdown'] );
		$show_excerpt = ! empty( $opt['llms_show_excerpt'] );

		$limit = isset( $opt['llms_recent_limit'] ) ? (int) $opt['llms_recent_limit'] : 30;
		if ( $limit < 1 ) {
			$limit = 1;
		}

		$post_types = ( isset( $opt['post_types'] ) && is_array( $opt['post_types'] ) ) ? (array) $opt['post_types'] : array( 'post' );

		$blocks = array();

		$blocks[] = '# ' . ( $title !== '' ? $title : $this->one_line( home_url() ) );
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
				$path       = isset( $item['path'] ) ? (string) $item['path'] : '';
				$canonical  = isset( $item['canonical'] ) ? (string) $item['canonical'] : '';
				$modified   = isset( $item['modified'] ) ? (string) $item['modified'] : '';
				$excerpt    = isset( $item['excerpt'] ) ? (string) $item['excerpt'] : '';

				if ( $md_enabled ) {
					$md_url       = home_url( '/' . $base . '/' . rawurlencode( $pt ) . '/' . $this->rawurlencode_path( $path ) . '.md' );
					$notes        = sprintf(
					/* translators: 1: modified date, 2: canonical url */
						__( 'Updated %1$s. Canonical URL: %2$s', 'llm-friendly' ),
						$modified,
						$canonical
					);
					$item_lines[] = '- [' . $this->md_link_text( $title_txt ) . '](' . $md_url . '): ' . $notes;
				} else {
					$notes        = sprintf(
					/* translators: 1: modified date */
						__( 'Updated %1$s.', 'llm-friendly' ),
						$modified
					);
					$item_lines[] = '- [' . $this->md_link_text( $title_txt ) . '](' . $canonical . '): ' . $notes;
				}

				if ( $show_excerpt && $excerpt !== '' ) {
					// Keep the excerpt inside the list item by indenting it.
					$item_lines[] = '  ';
					$item_lines[] = '  ' . $excerpt;
					$item_lines[] = '  ';
				}
				$item_blocks[] = implode( "\n", $item_lines );
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
		$excluded_ids = $this->options->excluded_post_ids( (string) $post_type );

		// Желаемое количество элементов с учётом исходного лимита.
		$max_items = max( 0, (int) $limit );
		if ( $max_items === 0 ) {
			return array();
		}

		// Учитываем исключённые записи заранее, чтобы не использовать пост__not_in (дорогой параметр) и всё равно собрать нужное количество публикаций.
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
			// Пропускаем исключённые записи уже на уровне PHP, избегая дорогостоящего post__not_in в WP_Query.
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
				'title'     => (string) get_the_title( $p ),
				'path'      => (string) $this->options->post_path( $p ),
				'canonical' => (string) get_permalink( $p ),
				'modified'  => (string) get_the_modified_date( 'Y-m-d', $p ),
				'excerpt'   => (string) $this->post_excerpt_one_line( $p ),
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
			return $obj->labels->name;
		}

		return $post_type;
	}

	/**
	 * Normalize string to one line.
	 *
	 * @param string $s
	 *
	 * @return string
	 */
	private function one_line( $s ) {
		$s = (string) $s;
		$s = str_replace( array( "\r\n", "\r", "\n" ), ' ', $s );
		$s = preg_replace( '/\s+/u', ' ', $s );

		return trim( (string) $s );
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
		$s = wp_strip_all_tags( (string) $s, true );
		$s = html_entity_decode( $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$s = str_replace( array( '[', ']' ), array( '\[', '\]' ), $s );

		return trim( $s );
	}

	/**
	 * Rawurlencode each segment of a path (keeps slashes).
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	private function rawurlencode_path( $path ): string {
		$path  = ltrim( (string) $path, '/' );
		$parts = explode( '/', $path );
		$out   = array();

		foreach ( $parts as $p ) {
			$out[] = rawurlencode( $p );
		}

		return implode( '/', $out );
	}

	/**
	 * Conditional headers helper.
	 *
	 * @param array<int,string> $headers
	 * @param string $etag
	 * @param int $last_modified_ts
	 *
	 * @return void
	 */
	private function send_common_headers( array $headers, string $etag, int $last_modified_ts, int $build_ts = 0, int $rev = 0, string $hash = '', string $settings_hash = '' ): void {
		status_header( 200 );

		$header_lines = array();

		if ( is_array( $headers ) ) {
			foreach ( $headers as $k => $v ) {
				if ( is_string( $k ) && $k !== '' ) {
					// Собираем строки заголовков заранее, чтобы исключить повторную отправку далее.
					$v = (string) $v;
					if ( $v !== '' ) {
						$header_lines[] = $k . ': ' . $v;
					}
					continue;
				}

				if ( is_string( $v ) && $v !== '' ) {
					$header_lines[] = $v;
				}
			}
		}

		foreach ( $header_lines as $line ) {
			header( $line );
		}

		$last_modified_ts = max( 1, (int) $last_modified_ts );
		$last_modified    = gmdate( 'D, d M Y H:i:s', $last_modified_ts ) . ' GMT';

		$build_ts      = max( 0, (int) $build_ts );
		$rev           = (int) $rev;
		$hash          = (string) $hash;
		$settings_hash = (string) $settings_hash;

		header( 'ETag: ' . $etag );
		header( 'Last-Modified: ' . $last_modified );

		if ( $build_ts > 0 ) {
			header( 'X-LLMF-Build: ' . $build_ts );
		}
		if ( $rev > 0 ) {
			header( 'X-LLMF-Rev: ' . $rev );
		}
		if ( $hash !== '' ) {
			header( 'X-LLMF-Hash: ' . $hash );
		}
		if ( $settings_hash !== '' ) {
			header( 'X-LLMF-Settings-Hash: ' . $settings_hash );
		}

		header( 'Cache-Control: public, max-age=0, must-revalidate' );

		$if_none_match     = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_IF_NONE_MATCH'] ) ) : '';
		$if_modified_since = isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) : '';

		if ( $if_none_match !== '' ) {
			if ( $if_none_match === $etag ) {
				status_header( 304 );
				exit;
			}
		} elseif ( $if_modified_since !== '' ) {
			$ims = strtotime( $if_modified_since );
			if ( $ims !== false && $ims >= $last_modified_ts ) {
				status_header( 304 );
				exit;
			}
		}

		if ( $if_modified_since !== '' ) {
			$ims = strtotime( $if_modified_since );
			if ( $ims !== false && $ims >= $last_modified_ts ) {
				status_header( 304 );
				exit;
			}
		}

	}

	/**
	 * Нормализует блоки Markdown так, чтобы теги (заголовки, цитаты, списки)
	 * были отделены пустыми строками, как в "чистом" Markdown.
	 *
	 * @param string $md Исходный Markdown.
	 *
	 * @return string Markdown с единообразными пустыми строками между блоками.
	 */
	private function llmf_normalize_markdown_blocks( string $md ): string {
		// Приводим переносы строк к Unix-формату, чтобы регулярки были стабильны.
		$md = str_replace( [ "\r\n", "\r" ], "\n", $md );

		// Гарантируем пустую строку ПОСЛЕ заголовков.
		$md = preg_replace( '/^(#{1,6}[^\n]*)\n(?!\n)/m', "$1\n\n", $md );

		// Гарантируем пустую строку ПЕРЕД заголовками (кроме самого начала текста).
		$md = preg_replace( '/\n(#{1,6}\s+)/', "\n\n$1", $md );

		// Гарантируем пустую строку ПЕРЕД цитатами, если перед ними нет пустой строки.
		$md = preg_replace( '/\n(>)/', "\n\n$1", $md );

		// Гарантируем пустую строку ПОСЛЕ строки цитаты, если следующая строка не цитата.
		$md = preg_replace( '/^(>[^\n]*)\n(?!\n|>)/m', "$1\n\n", $md );

		// Гарантируем пустую строку ПЕРЕД списками.
		$md = preg_replace( '/\n(-\s+)/', "\n\n$1", $md );

		// Гарантируем пустую строку ПОСЛЕ последнего пункта списка, если дальше не список.
		$md = preg_replace( '/^(-\s[^\n]*)\n(?!\n|-\\s)/m', "$1\n\n", $md );

		// Схлопываем 3+ переводов строки до двух, чтобы не раздувать отступы.
		$md = preg_replace( "/\n{3,}/", "\n\n", $md );

		return trim( $md ) . "\n";
	}

}
