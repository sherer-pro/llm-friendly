<?php

namespace LLMFriendly;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts WP content into LLM-friendly Markdown.
 */
final class Exporter {
	/**
	 * Post meta key used to override Markdown body.
	 */
	public const META_MD_OVERRIDE = '_llmf_md_content_override';

	/**
	 * @var Options
	 */
	private Options $options;

	/**
	 * @param Options $options
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Output post as Markdown and exit.
	 *
	 * @param WP_Post $post
	 *
	 * @return void
	 */
	public function output_markdown( WP_Post $post ): void {
		// Do not export password-protected content.
		if ( ! empty( $post->post_password ) || post_password_required( $post ) ) {
			if ( function_exists( 'nocache_headers' ) ) {
				nocache_headers();
			}
			status_header( 404 );
			echo esc_html__( 'Not Found', 'llm-friendly' );
			exit;
		}
		$can = apply_filters( 'llmf_can_export_post', true, $post, 'markdown' );
		if ( ! $can ) {
			if ( function_exists( 'nocache_headers' ) ) {
				nocache_headers();
			}
			status_header( 404 );
			echo esc_html__( 'Not Found', 'llm-friendly' );
			exit;
		}

		// Respect exclusions: hide specific posts from Markdown exports.
		if ( $this->options->is_post_excluded( $post ) ) {
			if ( function_exists( 'nocache_headers' ) ) {
				nocache_headers();
			}
			status_header( 404 );
			echo esc_html__( 'Not Found', 'llm-friendly' );
			exit;
		}

		$modified = $this->post_modified_timestamp( $post );
		$ver      = defined( 'LLMF_VERSION' ) ? (string) LLMF_VERSION : '0';

		// Markdown override affects cache key (post meta updates don't change post_modified).
		$override      = $this->get_markdown_override( $post );
		$override_hash = $override !== '' ? md5( $override ) : '0';

		$key = 'llmf_md_' . $ver . '_' . (int) $post->ID . '_' . (int) $modified . '_' . $override_hash;
		$md       = get_transient( $key );
		if ( ! is_string( $md ) || $md === '' ) {
			$md = $this->post_to_markdown( $post, $override );
			$ttl = (int) apply_filters( 'llmf_markdown_cache_ttl', 3600, $post );
			if ( $ttl < 0 ) {
				$ttl = 0;
			}
			set_transient( $key, $md, $ttl );
		}

		$headers = array(
			'Content-Type: text/markdown; charset=UTF-8',
			'X-Content-Type-Options: nosniff',
		);
		$opt     = $this->options->get();
		if ( ! empty( $opt['md_send_noindex'] ) ) {
			$headers[] = 'X-Robots-Tag: noindex, nofollow';
		}

		$this->send_common_headers(
			$headers,
			$this->etag_from_string( $md ),
			$this->post_modified_timestamp( $post )
		);

		echo wp_kses( $md, [] );
		exit;
	}

	/**
	 * Build Markdown for a post.
	 *
	 * @param WP_Post $post Post object.
	 * @param string  $override_md Custom Markdown that should replace post content.
	 *
	 * @return string
	 */
	private function post_to_markdown( WP_Post $post, string $override_md = '' ): string {
		$meta = array(
			'title'         => get_the_title( $post ),
			'url'           => get_permalink( $post ),
			'datePublished' => get_the_date( 'Y-m-d', $post ),
			'dateModified'  => get_the_modified_date( 'Y-m-d', $post ),
			'language'      => get_bloginfo( 'language' )
		);

		$meta_json = wp_json_encode( $meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );

		$override_md = is_string( $override_md ) ? $override_md : '';
		$override_md = trim( $override_md );

		if ( $override_md !== '' ) {
			// If override contains Gutenberg block markup, convert it; otherwise treat as Markdown body.
			if ( strpos( $override_md, '<!-- wp:' ) !== false ) {
				$blocks = parse_blocks( $override_md );
				$body   = trim( $this->blocks_to_markdown( $blocks ) );
			} else {
				$body = $override_md;
			}
		} else {
			$blocks = parse_blocks( (string) $post->post_content );
			$body   = trim( $this->blocks_to_markdown( $blocks ) );
		}
		$out   = array();
		$out[] = '```json';
		$out[] = $meta_json ? $meta_json : '{}';
		$out[] = '```';
		$out[] = '';
		$out[] = '# ' . $meta['title'];
		$out[] = '';
		$out[] = $body;

		return rtrim( $this->normalize_markdown_blocks( implode( "\n", $out ) ) ) . "\n";
	}

	/**
	 * Get the custom Markdown from the post meta.
	 *
	 * @param WP_Post $post Post to read the override from.
	 *
	 * @return string Clean text content or an empty string.
	 */
	private function get_markdown_override( WP_Post $post ): string {
		$val = get_post_meta( $post->ID, self::META_MD_OVERRIDE, true );

		return is_string( $val ) ? $val : '';
	}

	/**
	 * Convert Gutenberg blocks to Markdown.
	 *
	 * @param array<int,mixed> $blocks
	 * @param int $list_depth
	 *
	 * @return string
	 */
	private function blocks_to_markdown( $blocks, $list_depth = 0 ) {
		$out = array();

		foreach ( (array) $blocks as $b ) {
			if ( ! is_array( $b ) ) {
				continue;
			}

			$name         = isset( $b['blockName'] ) ? (string) $b['blockName'] : '';
			$attrs        = isset( $b['attrs'] ) && is_array( $b['attrs'] ) ? $b['attrs'] : array();
			$inner        = isset( $b['innerBlocks'] ) && is_array( $b['innerBlocks'] ) ? $b['innerBlocks'] : array();
			$innerHTML    = isset( $b['innerHTML'] ) ? (string) $b['innerHTML'] : '';
			$innerContent = isset( $b['innerContent'] ) && is_array( $b['innerContent'] ) ? $b['innerContent'] : array();

			if ( $name === 'core/heading' ) {
				$level = isset( $attrs['level'] ) ? (int) $attrs['level'] : 2;
				$level = max( 1, min( 6, $level ) );
				$text  = $this->html_inline_to_md( $innerHTML );
				$out[] = str_repeat( '#', $level ) . ' ' . $text;
				$out[] = '';
				continue;
			}

			if ( $name === 'core/paragraph' ) {
				$text = $this->html_inline_to_md( $innerHTML );
				if ( $text !== '' ) {
					$out[] = $text;
					$out[] = '';
				}
				continue;
			}

			if ( $name === 'core/list' ) {
				$ordered = ! empty( $attrs['ordered'] );
				$start   = isset( $attrs['start'] ) ? (int) $attrs['start'] : 1;
				$lines   = $this->list_blocks_to_md( $inner, $ordered, $start, $list_depth );
				if ( $lines !== '' ) {
					$out[] = $lines;
					$out[] = '';
				}
				continue;
			}

			if ( $name === 'core/quote' || $name === 'core/pullquote' ) {
				$html = function_exists( 'render_block' ) ? (string) render_block( $b ) : $innerHTML;
				$txt  = trim( $this->html_block_to_md( $html ) );
				if ( $txt !== '' ) {
					$out[] = $txt;
					$out[] = '';
				}
				continue;
			}

			if ( $name === 'core/code' ) {
				$code  = isset( $attrs['content'] ) ? (string) $attrs['content'] : '';
				$code  = html_entity_decode( $code, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$out[] = "```";
				$out[] = rtrim( $code );
				$out[] = "```";
				$out[] = '';
				continue;
			}

			if ( $name === 'core/preformatted' || $name === 'core/verse' ) {
				$text = $this->html_inline_to_md( $innerHTML );
				if ( $text !== '' ) {
					$out[] = "```";
					$out[] = $text;
					$out[] = "```";
					$out[] = '';
				}
				continue;
			}

			if ( $name === 'core/image' ) {
				// Single image handling to reuse caption/alt parsing logic.
				$image = $this->extract_image_data_from_block( $b );
				if ( $image['url'] !== '' ) {
					$out[] = $this->build_image_markdown( $image['url'], $image['alt'], $image['caption'] );
					$out[] = '';
				}
				continue;
			}

			if ( $name === 'core/gallery' ) {
				// A gallery is a sequence of images; export each one to Markdown.
				$gallery_md = $this->gallery_blocks_to_md( $inner, $innerHTML, $b );
				if ( $gallery_md !== '' ) {
					$out[] = $gallery_md;
					$out[] = '';
				}
				continue;
			}

			if ( $name === 'core/table' ) {
				$md_table = $this->core_table_to_markdown( $b );
				if ( $md_table !== '' ) {
					$out[] = $md_table;
					$out[] = '';
				}
				continue;
			}

			if ( $name === 'core/html' || $name === 'core/freeform' ) {
				$html = function_exists( 'render_block' ) ? (string) render_block( $b ) : $innerHTML;
				if ( trim( $html ) === '' && $innerHTML !== '' ) {
					$html = $innerHTML;
				}
				$txt = trim( $this->html_block_to_md( $html ) );
				if ( $txt !== '' ) {
					$out[] = $txt;
					$out[] = '';
				}
				continue;
			}

			if ( $name === 'core/separator' || $name === 'core/spacer' ) {
				continue;
			}

			if ( $name !== '' ) {
				$html = function_exists( 'render_block' ) ? (string) render_block( $b ) : $innerHTML;
				$txt  = trim( $this->html_block_to_md( $html ) );
				if ( $txt !== '' ) {
					$out[] = $txt;
					$out[] = '';
				}
			} else {
				$txt = trim( $this->html_block_to_md( implode( '', $innerContent ) ) );
				if ( $txt !== '' ) {
					$out[] = $txt;
					$out[] = '';
				}
			}
		}

		$md = implode( "\n", $out );

		return trim( $this->normalize_newlines( $md ) ) . "\n";
	}

	/**
	 * Convert inline HTML to Markdown-ish text.
	 *
	 * Supports: links, code, kbd, strong/em, del/s, br.
	 *
	 * @param string $html
	 *
	 * @return string
	 */
	private function html_inline_to_md( $html ) {
		$html = wp_kses_post( (string) $html );

		$html = preg_replace_callback(
			'~<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)</a>~is',
			function ( $m ) {
				$href = esc_url_raw( trim( (string) $m[1] ) );
				$text = trim( wp_strip_all_tags( (string) $m[2], true ) );
				if ( $href === '' ) {
					return $text;
				}
				if ( $text === '' ) {
					$text = $href;
				}

				return '[' . $this->escape_md( $text ) . '](' . $href . ')';
			},
			$html
		);

		$html = preg_replace_callback(
			'~<code[^>]*>(.*?)</code>~is',
			function ( $m ) {
				$t = wp_strip_all_tags( (string) $m[1], true );
				$t = html_entity_decode( $t, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$t = str_replace( '`', '\`', $t );

				return '`' . $t . '`';
			},
			$html
		);

		$html = preg_replace_callback(
			'~<kbd[^>]*>(.*?)</kbd>~is',
			function ( $m ) {
				$t = wp_strip_all_tags( (string) $m[1], true );
				$t = html_entity_decode( $t, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$t = str_replace( '`', '\`', $t );

				return '`' . $t . '`';
			},
			$html
		);

		$html = preg_replace( '~<(strong|b)>(.*?)</\1>~is', '**$2**', $html );
		$html = preg_replace( '~<(em|i)>(.*?)</\1>~is', '*$2*', $html );
		$html = preg_replace( '~<(del|s)>(.*?)</\1>~is', '~~$2~~', $html );

		$html = preg_replace( '~<br\s*/?>~i', "\n", $html );

		// Preserve paragraph-like breaks when inline conversion is used on larger HTML fragments.
		$html = preg_replace( '~</p>\s*<p[^>]*>~i', "\n\n", $html );
		$html = preg_replace( '~</p>~i', "\n\n", $html );
		$html = preg_replace( '~<p[^>]*>~i', '', $html );


		$text = wp_strip_all_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		$text = $this->normalize_newlines( $text );
		$text = preg_replace( "/[ \t]+/", " ", $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );

		return trim( (string) $text );
	}

	/**
	 * Minimal escaping for Markdown.
	 *
	 * @param string $s
	 *
	 * @return string
	 */
	private function escape_md( $s ) {
		$s = (string) $s;
		$s = str_replace( array( '[', ']' ), array( '\[', '\]' ), $s );

		return $s;
	}

	/**
	 * Carefully separate Markdown blocks with blank lines without touching fenced blocks.
	 *
	 * @param string $md Source Markdown.
	 *
	 * @return string Formatted Markdown with consistent spacing between blocks.
	 */
	private function normalize_markdown_blocks( string $md ): string {
		$md = $this->normalize_newlines( (string) $md );
		$lines = explode( "\n", $md );

		$blocks = array();
		$cur_lines = array();
		$cur_type  = '';
		$in_code   = false;

		$flush = static function() use ( &$blocks, &$cur_lines, &$cur_type ) {
			if ( ! empty( $cur_lines ) ) {
				// Trim trailing whitespace on lines, but keep internal structure.
				$tmp = array();
				foreach ( $cur_lines as $ln ) {
					$tmp[] = rtrim( (string) $ln );
				}
				// Remove leading/trailing empty lines inside the block.
				while ( ! empty( $tmp ) && trim( (string) $tmp[0] ) === '' ) {
					array_shift( $tmp );
				}
				while ( ! empty( $tmp ) && trim( (string) $tmp[ count( $tmp ) - 1 ] ) === '' ) {
					array_pop( $tmp );
				}
				if ( ! empty( $tmp ) ) {
					$blocks[] = $tmp;
				}
			}
			$cur_lines = array();
			$cur_type  = '';
		};

		foreach ( $lines as $ln ) {
			$raw  = (string) $ln;
			$trim = trim( $raw );

			// Fenced code blocks: keep verbatim until closing fence.
			$is_fence = preg_match( '/^\s{0,3}```/', $raw ) === 1;
			if ( $is_fence ) {
				if ( ! $in_code ) {
					$flush();
					$in_code  = true;
					$cur_type = 'code';
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

			// Blank line separates blocks.
			if ( $trim === '' ) {
				$flush();
				continue;
			}

			// Determine block type for grouping.
			$type = 'para';
			if ( preg_match( '/^\s{0,3}#{1,6}\s+/', $raw ) ) {
				$type = 'heading';
			} elseif ( preg_match( '/^\s{0,3}>\s?/', $raw ) ) {
				$type = 'quote';
			} elseif ( preg_match( '/^\s{0,3}(\d+\.|[-+*])\s+/', $raw ) ) {
				$type = 'list';
			} elseif ( strpos( $raw, '|' ) !== false && preg_match( '/^\s*\|?.*\|.*$/', $raw ) ) {
				// Simple heuristic for Markdown tables (pipes).
				$type = 'table';
			}

			// Headings are standalone blocks.
			if ( $type === 'heading' ) {
				$flush();
				$blocks[] = array( rtrim( $raw ) );
				continue;
			}

			if ( $cur_type !== '' && $cur_type !== $type ) {
				$flush();
			}
			$cur_type = $type;
			$cur_lines[] = rtrim( $raw );
		}

		$flush();

		// Join blocks with a single blank line.
		$out = array();
		foreach ( $blocks as $i => $b_lines ) {
			if ( $i > 0 ) {
				$out[] = '';
			}
			foreach ( $b_lines as $b_ln ) {
				$out[] = (string) $b_ln;
			}
		}

		return implode( "\n", $out );
	}

	/**
	 * Normalize line breaks to Unix format \n.
	 *
	 * @param string $s Any string with arbitrary line breaks.
	 *
	 * @return string String with a unified \n separator.
	 */
	private function normalize_newlines( string $s ): string {
		return str_replace( array( "\r\n", "\r" ), "\n", $s );
	}

	/**
	 * Safely parse an HTML fragment into DOM with external entities disabled.
	 *
	 * Use a restricted libxml parser without DOCTYPE/ENTITY to minimize
	 * XXE/SSRF risk when handling user blocks and Markdown overrides.
	 *
	 * @param string $html  HTML fragment without wrapping in <html>.
	 * @param int    $flags Additional libxml flags (merged with safe defaults).
	 *
	 * @return \DOMDocument|null DOM document or null on failure/disabled DOM.
	 */
	private function dom_from_html_fragment( string $html, int $flags = 0 ): ?\DOMDocument {
		if ( ! class_exists( 'DOMDocument' ) ) {
			return null;
		}

		// Remove potential DOCTYPE/ENTITY declarations so libxml does not process them.
		$safe_html = preg_replace( '/<!DOCTYPE.*?>/is', '', (string) $html );
		$safe_html = preg_replace( '/<!ENTITY.*?>/is', '', (string) $safe_html );

		libxml_use_internal_errors( true );
		$doc = new \DOMDocument( '1.0', 'UTF-8' );

		$safe_flags = LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD;
		if ( defined( 'LIBXML_NONET' ) ) {
			$safe_flags |= LIBXML_NONET;
		}

		$loaded = $doc->loadHTML( '<?xml encoding="utf-8" ?>' . $safe_html, $safe_flags | $flags );
		libxml_clear_errors();

		if ( ! $loaded ) {
			return null;
		}

		return $doc;
	}

	/**
	 * Convert list blocks (core/list -> core/list-item) to Markdown.
	 *
	 * @param array<int,mixed> $innerBlocks
	 * @param bool $ordered
	 * @param int $start
	 * @param int $depth
	 *
	 * @return string
	 */
	private function list_blocks_to_md( $innerBlocks, $ordered, $start, $depth ) {
		$lines = array();
		$i     = 0;

		foreach ( (array) $innerBlocks as $b ) {
			if ( ! is_array( $b ) ) {
				continue;
			}

			$name      = isset( $b['blockName'] ) ? (string) $b['blockName'] : '';
			$inner     = isset( $b['innerBlocks'] ) && is_array( $b['innerBlocks'] ) ? $b['innerBlocks'] : array();
			$innerHTML = isset( $b['innerHTML'] ) ? (string) $b['innerHTML'] : '';

			if ( $name !== 'core/list-item' ) {
				$txt = '';
				if ( function_exists( 'render_block' ) ) {
					$txt = trim( $this->html_block_to_md( (string) render_block( $b ) ) );
				} else {
					$txt = trim( $this->html_block_to_md( $innerHTML ) );
				}
				if ( $txt !== '' ) {
					$indent  = str_repeat( '  ', max( 0, (int) $depth ) );
					$prefix  = $ordered ? ( (int) $start + $i ) . '. ' : '- ';
					$lines[] = $indent . $prefix . $txt;
					$i ++;
				}
				continue;
			}

			$itemText = trim( $this->html_inline_to_md( $innerHTML ) );

			$indent  = str_repeat( '  ', max( 0, (int) $depth ) );
			$prefix  = $ordered ? ( (int) $start + $i ) . '. ' : '- ';
			$lines[] = $indent . $prefix . $itemText;

			foreach ( $inner as $child ) {
				if ( is_array( $child ) && isset( $child['blockName'] ) && $child['blockName'] === 'core/list' ) {
					$childAttrs   = isset( $child['attrs'] ) && is_array( $child['attrs'] ) ? $child['attrs'] : array();
					$childOrdered = ! empty( $childAttrs['ordered'] );
					$childStart   = isset( $childAttrs['start'] ) ? (int) $childAttrs['start'] : 1;
					$childInner   = isset( $child['innerBlocks'] ) && is_array( $child['innerBlocks'] ) ? $child['innerBlocks'] : array();
					$nested       = $this->list_blocks_to_md( $childInner, $childOrdered, $childStart, (int) $depth + 1 );
					if ( $nested !== '' ) {
						$lines[] = $nested;
					}
				}
			}

			$i ++;
		}

		return trim( implode( "\n", $lines ) );
	}

	/**
	 * Convert block-level HTML to Markdown.
	 *
	 * Supports headings, paragraphs, blockquotes (incl. wp-block-quote),
	 * pullquotes (figure.wp-block-pullquote), pre/code, lists, and falls back to inline conversion.
	 *
	 * @param string $html
	 *
	 * @return string
	 */
	private function html_block_to_md( $html ) {
		$html = (string) $html;
		if ( trim( $html ) === '' ) {
			return '';
		}

		$doc = $this->dom_from_html_fragment( '<div>' . $html . '</div>' );

		if ( ! ( $doc instanceof \DOMDocument ) ) {
			if ( preg_match_all( '~<blockquote\b[^>]*>(.*?)</blockquote>~is', $html, $m ) ) {
				$chunks = array();
				foreach ( $m[1] as $chunk ) {
					$t        = $this->html_inline_to_md( (string) $chunk );
					$chunks[] = $this->prefix_quote( $t );
				}
				$chunks = array_values( array_filter( array_map( 'trim', $chunks ) ) );
				if ( ! empty( $chunks ) ) {
					return trim( implode( "\n\n", $chunks ) );
				}
			}

			return trim( $this->html_inline_to_md( $html ) );
		}

		$root = $doc->getElementsByTagName( 'div' )->item( 0 );
		if ( ! $root ) {
			return trim( $this->html_inline_to_md( $html ) );
		}

		$parts = array();

		foreach ( $root->childNodes as $node ) {
			if ( $node->nodeType === XML_TEXT_NODE ) {
				$t = trim( (string) $node->textContent );
				if ( $t !== '' ) {
					$parts[] = $t;
				}
				continue;
			}

			if ( ! ( $node instanceof \DOMElement ) ) {
				continue;
			}

			$tag = strtolower( $node->tagName );

			if ( preg_match( '~^h([1-6])$~', $tag, $mm ) ) {
				$lvl = (int) $mm[1];
				$txt = trim( $this->html_inline_to_md( $this->dom_inner_html( $node ) ) );
				if ( $txt !== '' ) {
					$parts[] = str_repeat( '#', $lvl ) . ' ' . $txt;
				}
				continue;
			}

			if ( $tag === 'p' ) {
				$txt = trim( $this->html_inline_to_md( $this->dom_inner_html( $node ) ) );
				if ( $txt !== '' ) {
					$parts[] = $txt;
				}
				continue;
			}

			if ( $tag === 'blockquote' ) {
				$q = $this->dom_blockquote_to_md( $node );
				if ( $q !== '' ) {
					$parts[] = $q;
				}
				continue;
			}

			if ( $tag === 'figure' ) {
				$fig = $this->dom_figure_to_md( $node );
				if ( $fig !== '' ) {
					$parts[] = $fig;
					continue;
				}
			}

			if ( $tag === 'pre' ) {
				$code = (string) $node->textContent;
				$code = html_entity_decode( $code, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$code = rtrim( $code );
				if ( $code !== '' ) {
					$parts[] = "```\n" . $code . "\n```";
				}
				continue;
			}

			if ( $tag === 'ul' || $tag === 'ol' ) {
				$parts[] = $this->dom_list_to_md( $node, $tag === 'ol', 0 );
				continue;
			}

			$txt = trim( $this->html_inline_to_md( $doc->saveHTML( $node ) ) );
			if ( $txt !== '' ) {
				$parts[] = $txt;
			}
		}

		$parts = array_values( array_filter( array_map( 'trim', $parts ) ) );

		return trim( implode( "\n\n", $parts ) );
	}

	/**
	 * Prefix text as Markdown quote.
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	private function prefix_quote( $text ) {
		$text  = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
		$lines = explode( "\n", $text );

		$out = array();
		foreach ( $lines as $ln ) {
			$ln    = rtrim( $ln );
			$out[] = $ln === '' ? '>' : '> ' . $ln;
		}

		return implode( "\n", $out );
	}

	/**
	 * @param \DOMNode $node
	 *
	 * @return string
	 */
	private function dom_inner_html( $node ) {
		$html = '';
		if ( ! $node || ! isset( $node->childNodes ) ) {
			return $html;
		}

		foreach ( $node->childNodes as $child ) {
			$html .= $node->ownerDocument->saveHTML( $child );
		}

		return $html;
	}

	/**
	 * Convert DOM blockquote into Markdown quote.
	 *
	 * @param \DOMElement $bq
	 *
	 * @return string
	 */
	private function dom_blockquote_to_md( $bq ) {
		if ( ! ( $bq instanceof \DOMElement ) ) {
			return '';
		}

		$paras = array();

		foreach ( $bq->childNodes as $child ) {
			if ( $child instanceof \DOMElement && strtolower( $child->tagName ) === 'p' ) {
				$t = trim( $this->html_inline_to_md( $this->dom_inner_html( $child ) ) );
				if ( $t !== '' ) {
					$paras[] = $t;
				}
			}
		}

		if ( empty( $paras ) ) {
			$t = trim( $this->html_inline_to_md( $this->dom_inner_html( $bq ) ) );
			if ( $t !== '' ) {
				$paras[] = $t;
			}
		}

		$text = trim( implode( "\n\n", $paras ) );
		if ( $text === '' ) {
			return '';
		}

		return $this->prefix_quote( $text );
	}

	/**
	 * Convert DOM figure (supports pullquote, image+caption).
	 *
	 * @param \DOMElement $fig
	 *
	 * @return string
	 */
	private function dom_figure_to_md( $fig ) {
		if ( ! ( $fig instanceof \DOMElement ) ) {
			return '';
		}

		$class = (string) $fig->getAttribute( 'class' );

		// Pullquote: <figure class="wp-block-pullquote"><blockquote>..</blockquote><figcaption>..</figcaption></figure>
		if ( strpos( $class, 'wp-block-pullquote' ) !== false ) {
			$bq = null;
			foreach ( $fig->getElementsByTagName( 'blockquote' ) as $el ) {
				$bq = $el;
				break;
			}

			if ( $bq instanceof \DOMElement ) {
				$quote = $this->dom_blockquote_to_md( $bq );

				$cap = '';
				foreach ( $fig->getElementsByTagName( 'figcaption' ) as $fc ) {
					$cap = trim( $this->html_inline_to_md( $this->dom_inner_html( $fc ) ) );
					break;
				}

				if ( $cap !== '' ) {
					$quote = rtrim( $quote ) . "\n> \n> — " . $cap;
				}

				return trim( $quote );
			}
		}

		// Image figure
		foreach ( $fig->getElementsByTagName( 'img' ) as $img ) {
			if ( ! ( $img instanceof \DOMElement ) ) {
				continue;
			}

			$src = trim( (string) $img->getAttribute( 'src' ) );
			if ( $src === '' ) {
				continue;
			}

			$alt   = trim( (string) $img->getAttribute( 'alt' ) );
			$label = $alt !== '' ? $alt : 'image';

			$out = '![' . $this->escape_md( $label ) . '](' . $src . ')';

			foreach ( $fig->getElementsByTagName( 'figcaption' ) as $fc ) {
				$cap = trim( $this->html_inline_to_md( $this->dom_inner_html( $fc ) ) );
				if ( $cap !== '' ) {
					$out .= "\n\n" . $cap;
				}
				break;
			}

			return $out;
		}

		return '';
	}

	/**
	 * Build Markdown for an image with alt text and caption.
	 *
	 * @param string $url     Image URL.
	 * @param string $alt     Alt attribute.
	 * @param string $caption Image caption.
	 *
	 * @return string Markdown fragment with the image.
	 */
	private function build_image_markdown( string $url, string $alt, string $caption ): string {
		$url     = trim( $url );
		$alt     = trim( $alt );
		$caption = trim( $caption );

		if ( $url === '' ) {
			return '';
		}

		$label = $alt !== '' ? $alt : ( $caption !== '' ? $caption : 'image' );
		$md    = '![' . $this->escape_md( $label ) . '](' . $url . ')';

		if ( $caption !== '' && $caption !== $alt ) {
			// Keep caption on its own line so it is not lost during parsing.
			$md .= "\n" . $caption;
		}

		return $md;
	}

	/**
	 * Extract image data from a Gutenberg core/image block or similar HTML.
	 *
	 * @param array<string,mixed> $block Block array with attrs/innerHTML keys.
	 *
	 * @return array{url:string,alt:string,caption:string} Clean image data.
	 */
	private function extract_image_data_from_block( array $block ): array {
		$attrs         = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
		$innerHTML     = isset( $block['innerHTML'] ) ? (string) $block['innerHTML'] : '';
		$innerContent  = isset( $block['innerContent'] ) && is_array( $block['innerContent'] ) ? $block['innerContent'] : array();
		$inner_html    = trim( $innerHTML );
		$rendered_html = '';

		// Pull data from attributes if WordPress stored them.
		$url     = isset( $attrs['url'] ) ? (string) $attrs['url'] : '';
		$alt     = isset( $attrs['alt'] ) ? (string) $attrs['alt'] : '';
		$caption = '';

		// Caption may be in attrs['caption'] (if saved without figure) or in figcaption.
		if ( isset( $attrs['caption'] ) && is_string( $attrs['caption'] ) ) {
			$caption = $this->html_inline_to_md( $attrs['caption'] );
		}

		if ( $caption === '' && preg_match( '~<figcaption[^>]*>(.*?)</figcaption>~is', $inner_html, $m ) ) {
			$caption = $this->html_inline_to_md( $m[1] );
		}

		// If innerHTML is empty, build HTML from innerContent.
		if ( $inner_html === '' && ! empty( $innerContent ) ) {
			$content_html = array();
			foreach ( $innerContent as $chunk ) {
				if ( is_string( $chunk ) && trim( $chunk ) !== '' ) {
					$content_html[] = $chunk;
				}
			}
			$inner_html = trim( implode( '', $content_html ) );
		}

		// On some Gutenberg versions, rendering the block yields full figure/img HTML.
		if ( function_exists( 'render_block' ) ) {
			$rendered_html = (string) render_block( $block );
		}

		// Collect HTML candidates to extract missing url/alt/caption values in order.
		$html_candidates = array_filter( array( $inner_html, $rendered_html ), function ( $val ) {
			return is_string( $val ) && trim( $val ) !== '';
		} );

		foreach ( $html_candidates as $candidate_html ) {
			$parsed = $this->extract_image_data_from_html( (string) $candidate_html, $caption );

			// Fill missing fields only when empty to avoid overwriting attribute values.
			if ( $url === '' && $parsed['url'] !== '' ) {
				$url = $parsed['url'];
			}
			if ( $alt === '' && $parsed['alt'] !== '' ) {
				$alt = $parsed['alt'];
			}
			if ( $caption === '' && $parsed['caption'] !== '' ) {
				$caption = $parsed['caption'];
			}

			// Stop parsing once all fields are collected.
			if ( $url !== '' && $alt !== '' && $caption !== '' ) {
				break;
			}
		}

		return array(
			'url'     => $url,
			'alt'     => $alt,
			'caption' => $caption,
		);
	}

	/**
	 * Safely extract image data from an HTML fragment with figure/img.
	 *
	 * @param string $html             Raw HTML with <img> or <figure>.
	 * @param string $default_caption  Caption to use if HTML provides none.
	 *
	 * @return array{url:string,alt:string,caption:string} Final src/alt/caption values.
	 */
	private function extract_image_data_from_html( string $html, string $default_caption = '' ): array {
		$clean_html = trim( $html );
		$data       = array(
			'url'     => '',
			'alt'     => '',
			'caption' => $default_caption,
		);

		if ( $clean_html === '' ) {
			return $data;
		}

		$doc = $this->dom_from_html_fragment( '<div>' . $clean_html . '</div>' );

		if ( $doc instanceof \DOMDocument ) {
			$root = $doc->getElementsByTagName( 'div' )->item( 0 );

			if ( $root ) {
				// First try figure: it can contain both alt and caption.
				$figures = $root->getElementsByTagName( 'figure' );
				if ( $figures->length > 0 ) {
					foreach ( $figures as $figure ) {
						if ( ! ( $figure instanceof \DOMElement ) ) {
							continue;
						}

						$img_nodes = $figure->getElementsByTagName( 'img' );
						if ( $img_nodes->length === 0 ) {
							continue;
						}

						$img = $img_nodes->item( 0 );
						if ( ! ( $img instanceof \DOMElement ) ) {
							continue;
						}

						$data['url'] = trim( (string) $img->getAttribute( 'src' ) );
						$data['alt'] = trim( (string) $img->getAttribute( 'alt' ) );

						$figcaptions = $figure->getElementsByTagName( 'figcaption' );
						if ( $figcaptions->length > 0 ) {
							$data['caption'] = $this->html_inline_to_md( $doc->saveHTML( $figcaptions->item( 0 ) ) );
						}

						// Found a complete data set, return it.
						return $data;
					}
				}

				// If no figure exists, look for the first img at the root level.
				$imgs = $root->getElementsByTagName( 'img' );
				if ( $imgs->length > 0 ) {
					$img = $imgs->item( 0 );
					if ( $img instanceof \DOMElement ) {
						$data['url'] = trim( (string) $img->getAttribute( 'src' ) );
						$data['alt'] = trim( (string) $img->getAttribute( 'alt' ) );
					}
				}
			}
		}

		// Basic regex fallback in case DOM is unavailable or lacks figure/img tags.
		if ( $data['url'] === '' && preg_match( '~<img[^>]+src=[\"\\\']([^\"\\\']+)[\"\\\']~i', $clean_html, $mm ) ) {
			$data['url'] = trim( (string) $mm[1] );
		}
		if ( $data['alt'] === '' && preg_match( '~<img[^>]+alt=[\"\\\']([^\"\\\']*)[\"\\\']~i', $clean_html, $mm ) ) {
			$data['alt'] = trim( (string) $mm[1] );
		}

		return $data;
	}

	/**
	 * Build Markdown for a core/gallery block by outputting each image.
	 *
	 * @param array<int,mixed> $innerBlocks Nested gallery blocks (usually core/image).
	 * @param string           $innerHTML   Raw gallery HTML for fallback parsing.
	 * @param array            $block       Original gallery block (used for render/innerContent).
	 *
	 * @return string Markdown for all gallery images.
	 */
	private function gallery_blocks_to_md( $innerBlocks, $innerHTML, array $block = array() ): string {
		$images = array();

		foreach ( (array) $innerBlocks as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}

			if ( isset( $child['blockName'] ) && $child['blockName'] === 'core/image' ) {
				$img = $this->extract_image_data_from_block( $child );
				if ( $img['url'] !== '' ) {
					$images[] = $img;
				}
			}
		}

		if ( empty( $images ) ) {
			// HTML candidates: innerHTML, then innerContent, then rendered block.
			$html_candidates = array();

			if ( is_string( $innerHTML ) && trim( $innerHTML ) !== '' ) {
				$html_candidates[] = $innerHTML;
			}

			// If Gutenberg stored real HTML in innerContent, use it.
			if ( empty( $html_candidates ) && isset( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
				$content_html = array();
				foreach ( $block['innerContent'] as $chunk ) {
					if ( is_string( $chunk ) && trim( $chunk ) !== '' ) {
						$content_html[] = $chunk;
					}
				}
				$merged = trim( implode( '', $content_html ) );
				if ( $merged !== '' ) {
					$html_candidates[] = $merged;
				}
			}

			// Rendering the block can return ready HTML, useful for non-standard setups.
			if ( function_exists( 'render_block' ) && is_array( $block ) && ! empty( $block ) ) {
				$rendered = (string) render_block( $block );
				if ( trim( $rendered ) !== '' ) {
					$html_candidates[] = $rendered;
				}
			}

			foreach ( $html_candidates as $candidate_html ) {
				$images = $this->extract_images_from_gallery_html( (string) $candidate_html );
				if ( ! empty( $images ) ) {
					break;
				}
			}
		}

		$images = array_values( array_filter( $images, function ( $img ) {
			return is_array( $img ) && isset( $img['url'] ) && trim( (string) $img['url'] ) !== '';
		} ) );

		if ( empty( $images ) ) {
			return '';
		}

		// Separate each image with a blank line to keep Markdown valid.
		$chunks = array();
		foreach ( $images as $img ) {
			$chunks[] = $this->build_image_markdown(
				(string) $img['url'],
				isset( $img['alt'] ) ? (string) $img['alt'] : '',
				isset( $img['caption'] ) ? (string) $img['caption'] : ''
			);
		}

		return implode( "\n\n", array_filter( $chunks ) );
	}

	/**
	 * Parse an HTML gallery and return an array of images.
	 *
	 * @param string $html Raw gallery HTML (may contain figure or img).
	 *
	 * @return array<int,array{url:string,alt:string,caption:string}> Image list.
	 */
	private function extract_images_from_gallery_html( string $html ): array {
		$clean_html = trim( $html );
		if ( $clean_html === '' ) {
			return array();
		}

		$images = array();
		$doc    = $this->dom_from_html_fragment( '<div>' . $clean_html . '</div>' );

		if ( $doc instanceof \DOMDocument ) {
			$root = $doc->getElementsByTagName( 'div' )->item( 0 );
			if ( $root ) {
				$figures = $root->getElementsByTagName( 'figure' );
				if ( $figures->length > 0 ) {
					foreach ( $figures as $figure ) {
						if ( ! ( $figure instanceof \DOMElement ) ) {
							continue;
						}
						$images[] = $this->extract_image_data_from_html( $doc->saveHTML( $figure ) );
					}
				} else {
					$imgs = $root->getElementsByTagName( 'img' );
					foreach ( $imgs as $img ) {
						if ( ! ( $img instanceof \DOMElement ) ) {
							continue;
						}
						$images[] = array(
							'url'     => trim( (string) $img->getAttribute( 'src' ) ),
							'alt'     => trim( (string) $img->getAttribute( 'alt' ) ),
							'caption' => '',
						);
					}
				}
			}
		}

		// Simplest fallback without DOM: extract <img> tags from HTML.
		if ( empty( $images ) && preg_match_all( '~<img[^>]+src=[\"\\\']([^\"\\\']+)[\"\\\'][^>]*~i', $clean_html, $m ) ) {
			$srcs = $m[1];
			foreach ( $srcs as $src ) {
				$alt_match = '';
				if ( preg_match( '~<img[^>]+src=[\"\\\']' . preg_quote( $src, '~' ) . '[\"\\\'][^>]*alt=[\"\\\']([^\"\\\']*)[\"\\\']~i', $clean_html, $mm ) ) {
					$alt_match = (string) $mm[1];
				}
				$images[] = array(
					'url'     => trim( (string) $src ),
					'alt'     => trim( $alt_match ),
					'caption' => '',
				);
			}
		}

		return array_values( array_filter( $images, function ( $img ) {
			return is_array( $img ) && isset( $img['url'] ) && trim( (string) $img['url'] ) !== '';
		} ) );
	}

	/**
	 * Convert a DOM list to Markdown (supports nesting).
	 *
	 * @param \DOMElement $list
	 * @param bool $ordered
	 * @param int $depth
	 *
	 * @return string
	 */
	private function dom_list_to_md( $list, $ordered, $depth ) {
		if ( ! ( $list instanceof \DOMElement ) ) {
			return '';
		}

		$lines = array();
		$i     = 1;

		foreach ( $list->childNodes as $child ) {
			if ( ! ( $child instanceof \DOMElement ) ) {
				continue;
			}
			if ( strtolower( $child->tagName ) !== 'li' ) {
				continue;
			}

			$indent = str_repeat( '  ', max( 0, (int) $depth ) );
			$prefix = $ordered ? ( $i . '. ' ) : '- ';

			$li_text_parts = array();
			foreach ( $child->childNodes as $li_child ) {
				if ( $li_child instanceof \DOMElement ) {
					$t = strtolower( $li_child->tagName );
					if ( $t === 'ul' || $t === 'ol' ) {
						continue;
					}
				}
				$li_text_parts[] = $child->ownerDocument->saveHTML( $li_child );
			}

			$txt     = trim( $this->html_inline_to_md( implode( '', $li_text_parts ) ) );
			$lines[] = $indent . $prefix . $txt;

			foreach ( $child->childNodes as $li_child ) {
				if ( ! ( $li_child instanceof \DOMElement ) ) {
					continue;
				}
				$t = strtolower( $li_child->tagName );
				if ( $t === 'ul' || $t === 'ol' ) {
					$nested = $this->dom_list_to_md( $li_child, $t === 'ol', (int) $depth + 1 );
					if ( trim( $nested ) !== '' ) {
						$lines[] = $nested;
					}
				}
			}

			$i ++;
		}

		return trim( implode( "\n", $lines ) );
	}

	/**
	 * Convert core/table block to a Markdown table.
	 *
	 * @param array<string,mixed> $block
	 *
	 * @return string
	 */
	private function core_table_to_markdown( $block ) {
		$html = '';
		if ( function_exists( 'render_block' ) ) {
			$html = (string) render_block( $block );
		}
		if ( trim( $html ) === '' && isset( $block['innerHTML'] ) ) {
			$html = (string) $block['innerHTML'];
		}
		if ( trim( $html ) === '' ) {
			return '';
		}

		if ( ! preg_match( '~<table\b[^>]*>.*?</table>~is', $html, $m ) ) {
			return $this->fallback_plain_table( $html );
		}

		$table_html = $m[0];

		$doc = $this->dom_from_html_fragment( $table_html );
		if ( ! ( $doc instanceof \DOMDocument ) ) {
			return $this->fallback_plain_table( $table_html );
		}

		$xpath = new \DOMXPath( $doc );
		$table = $xpath->query( '//table' )->item( 0 );
		if ( ! $table ) {
			return '';
		}

		$rows = array();

		$thead_rows = $xpath->query( './/thead/tr', $table );
		foreach ( $thead_rows as $tr ) {
			$rows[] = $this->dom_tr_to_row( $tr );
		}

		$tbody_rows = $xpath->query( './/tbody/tr', $table );
		if ( $tbody_rows->length > 0 ) {
			foreach ( $tbody_rows as $tr ) {
				$rows[] = $this->dom_tr_to_row( $tr );
			}
		} else {
			$all_rows = $xpath->query( './/tr', $table );
			foreach ( $all_rows as $tr ) {
				if ( $tr->parentNode && $tr->parentNode->nodeName === 'thead' ) {
					continue;
				}
				$rows[] = $this->dom_tr_to_row( $tr );
			}
		}

		$rows = array_values( array_filter( $rows, function ( $r ) {
			if ( ! is_array( $r ) || empty( $r ) ) {
				return false;
			}
			foreach ( $r as $c ) {
				if ( trim( (string) $c ) !== '' ) {
					return true;
				}
			}

			return false;
		} ) );

		if ( count( $rows ) === 0 ) {
			return '';
		}

		$max_cols = 0;
		foreach ( $rows as $r ) {
			$max_cols = max( $max_cols, count( $r ) );
		}
		if ( $max_cols < 1 ) {
			return '';
		}

		foreach ( $rows as &$r ) {
			while ( count( $r ) < $max_cols ) {
				$r[] = '';
			}
		}
		unset( $r );

		$header = $rows[0];
		$body   = array_slice( $rows, 1 );

		$header_line = '| ' . implode( ' | ', array_map( array( $this, 'md_table_cell' ), $header ) ) . ' |';
		$sep_line    = '| ' . implode( ' | ', array_fill( 0, $max_cols, '---' ) ) . ' |';

		$out = array( $header_line, $sep_line );

		foreach ( $body as $r ) {
			$out[] = '| ' . implode( ' | ', array_map( array( $this, 'md_table_cell' ), $r ) ) . ' |';
		}

		return implode( "\n", $out );
	}

	/**
	 * Fallback table conversion: strip tags to plain text.
	 *
	 * @param string $html
	 *
	 * @return string
	 */
	private function fallback_plain_table( $html ) {
		$text = wp_strip_all_tags( (string) $html, true );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = trim( preg_replace( "/[ \t]+/", " ", $text ) );

		return $text;
	}

	/**
	 * Convert a <tr> into a row array (supports colspan).
	 *
	 * @param \DOMNode $tr
	 *
	 * @return array<int,string>
	 */
	private function dom_tr_to_row( $tr ) {
		$cells = array();
		if ( ! $tr || ! isset( $tr->childNodes ) ) {
			return $cells;
		}

		foreach ( $tr->childNodes as $node ) {
			if ( ! ( $node instanceof \DOMElement ) ) {
				continue;
			}

			$tag = strtolower( $node->tagName );
			if ( $tag !== 'td' && $tag !== 'th' ) {
				continue;
			}

			$text = (string) ( $node->textContent ? $node->textContent : '' );
			$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$text = preg_replace( "/\s+/u", " ", $text );
			$text = trim( $text );

			$colspan = 1;
			if ( $node->hasAttribute( 'colspan' ) ) {
				$cs = (int) $node->getAttribute( 'colspan' );
				if ( $cs > 1 ) {
					$colspan = min( $cs, 20 );
				}
			}

			$cells[] = $text;
			for ( $i = 1; $i < $colspan; $i ++ ) {
				$cells[] = '';
			}
		}

		return $cells;
	}

	/**
	 * Send standard cache/conditional headers.
	 *
	 * @param array<int,string> $headers
	 * @param string $etag
	 * @param int $last_modified_ts
	 *
	 * @return void
	 */
	private function send_common_headers( $headers, $etag, $last_modified_ts ) {
		status_header( 200 );

		$header_lines = array();

		if ( is_array( $headers ) ) {
			foreach ( $headers as $k => $v ) {
				if ( is_string( $k ) && $k !== '' ) {
					// Build the header line for "key => value" pairs once to avoid repetition below.
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

		header( 'ETag: ' . $etag );
		header( 'Last-Modified: ' . $last_modified );
		header( 'Cache-Control: public, max-age=0, must-revalidate' );

		$if_none_match     = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_IF_NONE_MATCH'] ) ) : '';
		$if_modified_since = isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) : '';

		if ( $if_none_match !== '' && $if_none_match === $etag ) {
			status_header( 304 );
			exit;
		}

		if ( $if_modified_since !== '' ) {
			$since_ts = strtotime( $if_modified_since );
			if ( $since_ts && $since_ts >= $last_modified_ts ) {
				status_header( 304 );
				exit;
			}
		}

	}

	/**
	 * ETag from string body.
	 *
	 * @param string $body
	 *
	 * @return string
	 */
	private function etag_from_string( $body ) {
		return '"' . sha1( (string) $body . '|' . ( defined( 'LLMF_VERSION' ) ? LLMF_VERSION : '0' ) ) . '"';
	}

	/**
	 * Get modified timestamp (GMT).
	 *
	 * @param WP_Post $post
	 *
	 * @return int
	 */
	private function post_modified_timestamp( WP_Post $post ) {
		$ts = strtotime( (string) $post->post_modified_gmt . ' GMT' );

		return $ts ? (int) $ts : time();
	}

	/**
	 * Sanitize a cell for Markdown table.
	 *
	 * @param mixed $s
	 *
	 * @return string
	 */
	public function md_table_cell( $s ) {
		$s = trim( $this->normalize_newlines( (string) $s ) );
		if ( $s === '' ) {
			return '';
		}

		$s = str_replace( '|', '\|', $s );
		$s = str_replace( "\n", ' ', $s );
		$s = preg_replace( "/\s{2,}/u", " ", $s );

		return $s;
	}

	/**
	 * Build plain text from post content.
	 *
	 * @param WP_Post $post
	 *
	 * @return string
	 */
	private function post_plain_text( WP_Post $post ) {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- using a core WordPress filter.
		$html = apply_filters( 'the_content', (string) $post->post_content );
		$html = $this->normalize_newlines( $html );
		$text = wp_strip_all_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( "/[ \t]+/", " ", $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );

		return trim( (string) $text );
	}
}
