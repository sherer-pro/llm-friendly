<?php

namespace LLM_Friendly;

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
	private $options;

	/**
	 * @param Options $options
	 */
	public function __construct( $options ) {
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
	public function maybe_regenerate_on_save( $post_id, $post, $update ) {
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

		$this->regenerate();
	}

	/**
	 * Force regeneration of cached llms.txt.
	 *
	 * @return void
	 */
	public function regenerate( $force = false ) {
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

		update_option( Options::OPTION_KEY, $saved, false );
	}


	/**
	 * Output llms.txt (cached when possible).
	 *
	 * @return void
	 */
	public function output() {
		$opt = $this->options->get();

		if ( empty( $opt['enabled_llms_txt'] ) ) {
			status_header( 404 );
			echo esc_html__( 'Not Found', 'llm-friendly' );
			exit;
		}

		$content = isset( $opt['llms_cache'] ) ? (string) $opt['llms_cache'] : '';
		$ts      = isset( $opt['llms_cache_ts'] ) ? (int) $opt['llms_cache_ts'] : 0;

		if ( trim( $content ) === '' ) {
			$this->regenerate();
			$opt     = $this->options->get();
			$content = (string) $opt['llms_cache'];
			$ts      = (int) $opt['llms_cache_ts'];
		}

		if ( ! empty( $opt['llms_send_noindex'] ) ) {
			header( 'X-Robots-Tag: noindex, nofollow' );
		}

		$rev_etag_part = isset( $opt['llms_cache_rev'] ) ? (int) $opt['llms_cache_rev'] : 0;
		$etag          = '\"' . sha1( $content . '|' . $ts . '|' . $rev_etag_part ) . '\"';
		$rev           = isset( $opt['llms_cache_rev'] ) ? (int) $opt['llms_cache_rev'] : 0;
		$hash          = isset( $opt['llms_cache_hash'] ) ? (string) $opt['llms_cache_hash'] : '';
		$settings_hash = isset( $opt['llms_cache_settings_hash'] ) ? (string) $opt['llms_cache_settings_hash'] : '';

		$this->send_common_headers(
			array( 'Content-Type: text/plain; charset=UTF-8' ),
			$etag,
			$ts > 0 ? $ts : time(),
			$ts,
			$rev,
			$hash,
			$settings_hash
		);
		echo $content;
		exit;
	}

	/**
	 * Build llms.txt content in llmstxt.org format.
	 *
	 * @return string
	 */
	private function build_llms_txt() {
		$opt = $this->options->get();

		$title = $this->one_line( $this->options->site_title() );
		$desc  = $this->one_line( $this->options->site_description() );

		if ( $desc === '' ) {
			$desc = __( 'LLM-friendly index of this website.', 'llm-friendly' );
		}

		$base    = $this->options->sanitize_base_path( (string) $opt['base_path'] );
		$home    = home_url( '/' );
		$rss     = get_feed_link();
		$sitemap = $this->options->sitemap_absolute_url();

		$md_enabled = ! empty( $opt['enabled_markdown'] );

		$limit = (int) $opt['llms_recent_limit'];
		if ( $limit < 1 ) {
			$limit = 1;
		}

		$post_types = is_array( $opt['post_types'] ) ? $opt['post_types'] : array( 'post' );

		$out = array();

		// H1
		$out[] = '# ' . $title;
		$out[] = '';

		// blockquote
		$out[] = '> ' . $desc;
		$out[] = '';

		// Optional custom markdown block (inserted between site meta and content).
		$custom = isset( $opt['llms_custom_markdown'] ) ? (string) $opt['llms_custom_markdown'] : '';
		$custom = str_replace( array(
			"
",
			"
"
		), "
", $custom );
		$custom = trim( $custom );

		if ( $custom !== '' ) {
			foreach (
				explode( "
", $custom ) as $line
			) {
				$out[] = rtrim( $line, "
" );
			}
			$out[] = '';
		}

		// H2 sections: file lists
		$out[] = '
		
		## ' . __( 'Main links', 'llm-friendly' );
		$out[] = '- [' . $this->md_link_text( __( 'Home', 'llm-friendly' ) ) . '](' . $home . '): ' . __( 'Website home page', 'llm-friendly' );
		$out[] = '- [' . $this->md_link_text( __( 'Sitemap', 'llm-friendly' ) ) . '](' . $sitemap . '): ' . __( 'XML sitemap', 'llm-friendly' );
		$out[] = '- [' . $this->md_link_text( __( 'RSS', 'llm-friendly' ) ) . '](' . $rss . '): ' . __( 'Latest updates feed', 'llm-friendly' );

		foreach ( $post_types as $pt ) {
			$pt = sanitize_key( (string) $pt );
			if ( $pt === '' ) {
				continue;
			}

			$label = $this->post_type_label( $pt );
			$out[] = '';
			$out[] = '## ' . sprintf(
				/* translators: %s = post type label (plural) */
					__( '%s', 'llm-friendly' ),
					$label
				);

			$items = $this->get_recent_posts_by_type( $pt, $limit );

			if ( empty( $items ) ) {
				$out[] = '- ' . __( '(no published items found)', 'llm-friendly' );
				continue;
			}

			foreach ( $items as $p ) {
				$notes = '';

				if ( $md_enabled ) {
					$md_url = home_url( '/' . $base . '/' . rawurlencode( $pt ) . '/' . $this->rawurlencode_path( $p['path'] ) . '.md' );

					$notes = sprintf(
					/* translators: 1: modified date, 2: canonical url */
						__( 'Updated %1$s. Canonical URL: %2$s', 'llm-friendly' ),
						$p['modified'],
						$p['canonical']
					);

					$out[] = '- [' . $this->md_link_text( $p['title'] ) . '](' . $md_url . '): ' . $notes;
				} else {
					$notes = sprintf(
					/* translators: 1: modified date */
						__( 'Updated %1$s.', 'llm-friendly' ),
						$p['modified']
					);

					$out[] = '- [' . $this->md_link_text( $p['title'] ) . '](' . $p['canonical'] . '): ' . $notes;
				}
			}
		}

		return implode( "\n", $out ) . "\n";
	}

	/**
	 * Get recent posts for a given post type.
	 *
	 * @param string $post_type
	 * @param int $limit
	 *
	 * @return array<int,array<string,string>>
	 */
	private function get_recent_posts_by_type( $post_type, $limit ) {
		$q = new WP_Query( array(
			'post_type'              => $post_type,
			'post_status'            => 'publish',
			'posts_per_page'         => (int) $limit,
			'orderby'                => 'modified',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		) );

		$items = array();

		foreach ( (array) $q->posts as $p ) {
			if ( ! ( $p instanceof WP_Post ) ) {
				continue;
			}

			$items[] = array(
				'title'     => (string) get_the_title( $p ),
				'path'      => (string) $this->options->post_path( $p ),
				'canonical' => (string) get_permalink( $p ),
				'modified'  => (string) get_the_modified_date( 'Y-m-d', $p ),
			);
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
	 * Escape minimal Markdown for link text.
	 *
	 * @param string $s
	 *
	 * @return string
	 */
	private function md_link_text( $s ) {
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
	private function rawurlencode_path( $path ) {
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
	private function send_common_headers( $headers, $etag, $last_modified_ts, $build_ts = 0, $rev = 0, $hash = '', $settings_hash = '' ) {
		nocache_headers();
		status_header( 200 );

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

		header( 'Cache-Control: no-store, max-age=0, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$if_none_match     = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? trim( (string) $_SERVER['HTTP_IF_NONE_MATCH'] ) : '';
		$if_modified_since = isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ? trim( (string) $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) : '';

		if ( $if_none_match !== '' && $if_none_match === $etag ) {
			status_header( 304 );
			exit;
		}

		if ( $if_modified_since !== '' ) {
			$ims = strtotime( $if_modified_since );
			if ( $ims !== false && $ims >= $last_modified_ts ) {
				status_header( 304 );
				exit;
			}
		}

		foreach ( (array) $headers as $k => $v ) {
			if ( is_string( $k ) ) {
				header( $k . ': ' . $v );
			} else {
				header( $v );
			}
		}
	}

}
