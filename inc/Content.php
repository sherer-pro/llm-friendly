<?php
namespace LLMFriendly;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Content analysis without executing shortcodes or privileged dynamic blocks. */
final class Content {
	public const FORMAT = 'content-1';
	public const META_SOURCE_HASH = '_llmf_md_source_hash';

	/** Restore both the user and password cookie, including exception paths. */
	public static function anonymous( callable $callback ) {
		$user = get_current_user_id();
		$name = defined( 'COOKIEHASH' ) ? 'wp-postpass_' . COOKIEHASH : '';
		$had = $name !== '' && array_key_exists( $name, $_COOKIE );
		$cookie = $had ? $_COOKIE[ $name ] : null;
		if ( $name !== '' ) {
			unset( $_COOKIE[ $name ] );
		}
		wp_set_current_user( 0 );
		try {
			return $callback();
		} finally {
			if ( $had ) {
				$_COOKIE[ $name ] = $cookie;
			} elseif ( $name !== '' ) {
				unset( $_COOKIE[ $name ] );
			}
			wp_set_current_user( $user );
		}
	}

	public static function description( WP_Post $post, Options $options ): array {
		return self::anonymous( function () use ( $post, $options ) {
			$warnings = array();
			$custom = get_post_meta( $post->ID, Options::META_LLMS_DESCRIPTION, true );
			$text = $options->sanitize_llms_description( is_string( $custom ) ? $custom : '' );
			if ( $text !== '' ) {
				return array( 'text' => $text, 'source' => 'custom', 'warnings' => $warnings );
			}
			$seo = '';
			if ( function_exists( 'YoastSEO' ) ) {
				try {
					$yoast = YoastSEO();
					if ( isset( $yoast->meta ) && is_callable( array( $yoast->meta, 'for_post' ) ) ) {
					$surface = $yoast->meta->for_post( $post->ID );
						$seo = $surface && is_string( $surface->description ) ? $surface->description : '';
					}
				} catch ( \Throwable $error ) {
					$warnings[] = 'seo_api_unavailable';
				}
			}
			foreach ( array( $seo, get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true ), get_post_meta( $post->ID, 'wpseo_metadesc', true ) ) as $value ) {
				if ( ! is_string( $value ) || trim( $value ) === '' ) {
					continue;
				}
				if ( preg_match( '/%%[^%]+%%/', $value ) ) {
					$warnings[] = 'unresolved_seo_template';
					continue;
				}
				$text = $options->sanitize_llms_description( $value );
				if ( $text !== '' ) {
					return array( 'text' => $text, 'source' => 'seo', 'warnings' => $warnings );
				}
			}
			$text = $options->sanitize_llms_description( (string) $post->post_excerpt );
			if ( $text !== '' ) {
				return array( 'text' => $text, 'source' => 'excerpt', 'warnings' => $warnings );
			}
			$deps = array();
			$blocks = self::expand( parse_blocks( (string) $post->post_content ), $warnings, $deps );
			$text = self::first_text( $blocks );
			if ( $text === '' ) {
				$warnings[] = 'empty_description';
			}
			$text = $options->sanitize_llms_description( wp_trim_words( $text, 30, '…' ) );
			return array( 'text' => $text, 'source' => $text !== '' ? 'content' : 'none', 'warnings' => array_values( array_unique( $warnings ) ) );
		} );
	}

	private static function first_text( array $blocks ): string {
		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? '';
			if ( in_array( $name, array( 'core/code', 'core/preformatted', 'core/shortcode', 'core/html', 'core/embed', 'core/navigation', 'core/query' ), true ) || ( $name !== '' && $name !== null && strpos( $name, 'core/' ) !== 0 ) ) {
				continue;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$text = self::first_text( $block['innerBlocks'] );
				if ( $text !== '' ) {
					return $text;
				}
			}
			$html = (string) ( $block['innerHTML'] ?? '' );
			// Classic content: inspect paragraphs and list items, never service markup or code.
			$html = preg_replace( '~<(script|style|pre|code|nav|header|footer)\b[^>]*>.*?</\1>~is', '', $html );
			if ( preg_match_all( '~<(p|li)\b[^>]*>(.*?)</\1>~is', $html, $matches ) ) {
				foreach ( $matches[2] as $paragraph ) {
					$text = self::clean_summary( $paragraph );
					if ( $text !== '' ) {
						return $text;
					}
				}
			} elseif ( $name === 'core/paragraph' || $name === 'core/list-item' || $name === null || $name === '' ) {
				$text = self::clean_summary( $html );
				if ( $text !== '' ) {
					return $text;
				}
			}
		}
		return '';
	}

	private static function clean_summary( string $value ): string {
		// Unrendered shortcode bodies may contain service data rather than a summary.
		$value = preg_replace( '~\[([A-Za-z][\w-]*)\b[^\]]*\].*?\[/\1\]~s', '', $value );
		$value = preg_replace( '/\[[^\]\r\n]*\]/u', '', $value );
		$value = preg_replace( '~</?(?:p|li|ul|ol|br)\b[^>]*>~i', ' ', $value );
		return Markdown::plain_text_line( html_entity_decode( wp_strip_all_tags( $value, true ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/** A reusable source must remain public even when a runtime filter changes. */
	public static function public_reusable( $post ): bool {
		return $post instanceof WP_Post && $post->post_type === 'wp_block' && $post->post_status === 'publish' && $post->post_password === '' && (bool) apply_filters( 'llmf_can_export_post', true, $post, 'markdown' );
	}

	/** Expand published reusable blocks; prevent recursive references and unbounded trees. */
	public static function expand( array $blocks, array &$warnings, array &$dependencies, array $seen = array(), int $depth = 0, int &$budget = 2000 ): array {
		if ( $depth > 20 ) {
			$warnings[] = 'block_depth_limit';
			return array();
		}
		$out = array();
		foreach ( $blocks as $block ) {
			if ( -- $budget < 0 ) {
				$warnings[] = 'block_count_limit';
				break;
			}
			if ( ! is_array( $block ) ) {
				continue;
			}
			$name = $block['blockName'] ?? '';
			if ( $name === 'core/block' ) {
				$id = (int) ( $block['attrs']['ref'] ?? 0 );
				$ref = $id > 0 ? get_post( $id ) : null;
				$public = self::public_reusable( $ref );
				$dependencies[ $id ] = $ref instanceof WP_Post ? hash( 'sha256', $ref->post_content . '|' . $ref->post_status . '|' . $ref->post_password . '|' . ( $public ? 'public' : 'hidden' ) ) : 'missing';
				if ( isset( $seen[ $id ] ) || count( $dependencies ) > 100 || ! $public ) {
					$warnings[] = 'unavailable_reusable_block';
					continue;
				}
				$next_seen = $seen;
				$next_seen[ $id ] = true;
				$out = array_merge( $out, self::expand( parse_blocks( $ref->post_content ), $warnings, $dependencies, $next_seen, $depth + 1, $budget ) );
				continue;
			}
			if ( $name === 'core/shortcode' || preg_match( '/\[(?!\[)[A-Za-z][\w-]*(?:\s[^\]\r\n]*)?\]/', (string) ( $block['innerHTML'] ?? '' ) ) ) {
				$warnings[] = 'unresolved_shortcode';
			}
			if ( $name && ! in_array( $name, array( 'core/heading', 'core/paragraph', 'core/list', 'core/list-item', 'core/group', 'core/columns', 'core/column', 'core/cover', 'core/media-text', 'core/buttons', 'core/button', 'core/file', 'core/embed', 'core/audio', 'core/video', 'core/details', 'core/quote', 'core/pullquote', 'core/code', 'core/preformatted', 'core/verse', 'core/image', 'core/gallery', 'core/table', 'core/html', 'core/freeform', 'core/separator', 'core/spacer', 'core/shortcode' ), true ) ) {
				$warnings[] = 'unknown_block';
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::expand( $block['innerBlocks'], $warnings, $dependencies, $seen, $depth + 1, $budget );
			}
			$out[] = $block;
		}
		return $out;
	}

	public static function fingerprint( WP_Post $post ): string {
		$warnings = array();
		$dependencies = array();
		self::anonymous( function () use ( $post, &$warnings, &$dependencies ) {
			self::expand( parse_blocks( $post->post_content ), $warnings, $dependencies );
		} );
		ksort( $dependencies );
		return hash( 'sha256', $post->post_content . '|' . wp_json_encode( $dependencies ) );
	}

	public static function override_state( WP_Post $post ): string {
		$override = get_post_meta( $post->ID, Exporter::META_MD_OVERRIDE, true );
		if ( ! is_string( $override ) || trim( $override ) === '' ) {
			return 'automatic';
		}
		$stored = get_post_meta( $post->ID, self::META_SOURCE_HASH, true );
		return ! is_string( $stored ) || $stored === '' ? 'unconfirmed' : ( hash_equals( $stored, self::fingerprint( $post ) ) ? 'current' : 'outdated' );
	}

	/** Resolve HTTP references against the canonical page URL, including ../ and fragments. */
	public static function absolute_url( string $value, string $canonical ): string {
		$value = html_entity_decode( trim( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( preg_match( '~^https?://~i', $value ) || preg_match( '~^(mailto|tel):~i', $value ) ) {
			return Markdown::url_destination( $value );
		}
		if ( $value === '' || strpos( $value, '//' ) === 0 || preg_match( '~^[a-z][a-z0-9+.-]*:~i', $value ) || preg_match( '/[\x00-\x1f\x7f]/', $value ) ) {
			return '';
		}
		$base = wp_parse_url( $canonical );
		if ( ! is_array( $base ) || empty( $base['host'] ) || isset( $base['user'] ) || isset( $base['pass'] ) ) {
			return '';
		}
		$origin = $base['scheme'] . '://' . $base['host'] . ( isset( $base['port'] ) ? ':' . $base['port'] : '' );
		if ( $value[0] === '#' || $value[0] === '?' ) {
			return Markdown::url_destination( $origin . ( $base['path'] ?? '/' ) . $value );
		}
		$parts = preg_split( '/(?=[?#])/', $value, 2 );
		$path = $parts[0][0] === '/' ? $parts[0] : preg_replace( '~[^/]*$~', '', $base['path'] ?? '/' ) . $parts[0];
		$segments = array();
		foreach ( explode( '/', $path ) as $segment ) {
			if ( $segment === '..' ) {
				array_pop( $segments );
			} elseif ( $segment !== '.' ) {
				$segments[] = $segment;
			}
		}
		return Markdown::url_destination( $origin . implode( '/', $segments ) . ( $parts[1] ?? '' ) );
	}
}
