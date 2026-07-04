<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'LLMF_VERSION' ) ) {
	define( 'LLMF_VERSION', 'test' );
}

$GLOBALS['llmf_test_options'] = array();
$GLOBALS['llmf_test_posts']   = array();
$GLOBALS['llmf_test_meta']    = array();
$GLOBALS['llmf_tests_run']    = 0;

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public int $ID = 0;
		public string $post_type = 'post';
		public string $post_status = 'publish';
		public string $post_password = '';
		public string $post_name = '';
		public string $post_title = '';
		public string $post_content = '';
		public string $post_excerpt = '';
		public string $post_modified_gmt = '2026-01-01 00:00:00';
		public string $post_date_gmt = '2026-01-01 00:00:00';
		public int $post_author = 1;

		public function __construct( array $props = array() ) {
			foreach ( $props as $key => $value ) {
				$this->{$key} = $value;
			}
		}
	}
}

if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		public array $posts = array();

		public function __construct( array $args = array() ) {
			$post_type = isset( $args['post_type'] ) ? (string) $args['post_type'] : 'post';
			$limit     = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 10;
			$posts     = array();

			foreach ( $GLOBALS['llmf_test_posts'] as $post ) {
				if ( $post instanceof WP_Post && $post->post_type === $post_type && $post->post_status === 'publish' ) {
					$posts[] = $post;
				}
			}

			$this->posts = array_slice( $posts, 0, max( 0, $limit ) );
		}
	}
}

function __( $text, $domain = null ) { return $text; }
function esc_html__( $text, $domain = null ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_textarea( $text ) { return esc_html( $text ); }
function checked( $checked, $current = true, $echo = true ) { return $checked == $current ? ' checked="checked"' : ''; }
function selected( $selected, $current = true, $echo = true ) { return $selected == $current ? ' selected="selected"' : ''; }
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { return true; }
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { return true; }
function apply_filters( $hook, $value ) { return $value; }
function wp_unslash( $value ) { return is_string( $value ) ? stripslashes( $value ) : $value; }
function wp_kses_post( $value ) { return (string) $value; }
function wp_strip_all_tags( $text, $remove_breaks = false ) { return strip_tags( (string) $text ); }
function sanitize_text_field( $value ) { return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $value ) ) ); }
function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) ); }
function current_user_can( $capability, ...$args ) { return true; }
function wp_json_encode( $value, $flags = 0, $depth = 512 ) { return json_encode( $value, $flags ); }
function wp_trim_words( $text, $num_words = 55, $more = null ) {
	$words = preg_split( '/\s+/', trim( (string) $text ) );
	if ( ! is_array( $words ) || count( $words ) <= $num_words ) {
		return trim( (string) $text );
	}
	return implode( ' ', array_slice( $words, 0, $num_words ) ) . ( $more === null ? '...' : $more );
}
function esc_url_raw( $url, $protocols = null ) {
	$url = trim( (string) $url );
	if ( $url === '' || preg_match( '/[\r\n]/', $url ) ) {
		return '';
	}
	return $url;
}
function home_url( $path = '' ) {
	return 'https://example.test' . ( $path !== '' && $path[0] === '/' ? $path : '/' . ltrim( (string) $path, '/' ) );
}
function get_feed_link() { return 'https://example.test/feed/'; }
function get_bloginfo( $show = '' ) {
	if ( $show === 'name' ) {
		return 'Test Site';
	}
	if ( $show === 'description' ) {
		return 'Test description';
	}
	if ( $show === 'language' ) {
		return 'en-US';
	}
	if ( $show === 'version' ) {
		return '6.5';
	}
	return '';
}
function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['llmf_test_options'] ) ? $GLOBALS['llmf_test_options'][ $key ] : $default;
}
function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['llmf_test_options'][ $key ] = $value;
	return true;
}
function add_option( $key, $value, $deprecated = '', $autoload = null ) {
	$GLOBALS['llmf_test_options'][ $key ] = $value;
	return true;
}
function set_transient( $key, $value, $expiration = 0 ) { $GLOBALS['llmf_test_options'][ 'transient_' . $key ] = $value; return true; }
function get_transient( $key ) { return $GLOBALS['llmf_test_options'][ 'transient_' . $key ] ?? false; }
function delete_transient( $key ) { unset( $GLOBALS['llmf_test_options'][ 'transient_' . $key ] ); return true; }
function post_type_exists( $post_type ) { return in_array( $post_type, array( 'post', 'page' ), true ); }
function get_post_type_object( $post_type ) {
	if ( ! post_type_exists( $post_type ) ) {
		return null;
	}
	$obj               = new stdClass();
	$obj->public       = true;
	$obj->hierarchical = $post_type === 'page';
	$obj->labels       = (object) array( 'name' => $post_type === 'page' ? 'Pages' : 'Posts' );
	return $obj;
}
function get_post( $post_id ) { return $GLOBALS['llmf_test_posts'][ (int) $post_id ] ?? null; }
function get_the_title( $post = null ) {
	if ( $post instanceof WP_Post ) {
		return $post->post_title;
	}
	$post = get_post( (int) $post );
	return $post instanceof WP_Post ? $post->post_title : '';
}
function get_permalink( $post ) {
	if ( $post instanceof WP_Post ) {
		return 'https://example.test/' . ( $post->post_type === 'page' ? '' : 'post/' ) . $post->post_name . '/';
	}
	return 'https://example.test/';
}
function get_the_date( $format, $post = null ) { return '2026-01-01'; }
function get_the_modified_date( $format, $post = null ) { return '2026-01-02'; }
function get_the_author_meta( $field, $user_id ) { return 'Author Name'; }
function get_page_uri( $post ) { return $post instanceof WP_Post ? $post->post_name : ''; }
function post_password_required( $post = null ) { return false; }
function get_post_meta( $post_id, $key = '', $single = false ) {
	$meta = $GLOBALS['llmf_test_meta'][ (int) $post_id ][ $key ] ?? '';
	return $single ? $meta : array( $meta );
}
function wp_parse_url( $url ) { return parse_url( (string) $url ); }
function parse_blocks( $content ) { return array(); }

require_once __DIR__ . '/../inc/Markdown.php';
require_once __DIR__ . '/../inc/Options.php';
require_once __DIR__ . '/../inc/Response.php';
require_once __DIR__ . '/../inc/Exporter.php';
require_once __DIR__ . '/../inc/Llms.php';

use LLMFriendly\Exporter;
use LLMFriendly\Llms;
use LLMFriendly\Markdown;
use LLMFriendly\Options;

function assert_true( bool $condition, string $message ): void {
	$GLOBALS['llmf_tests_run'] ++;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function assert_contains_text( string $needle, string $haystack, string $message ): void {
	assert_true( strpos( $haystack, $needle ) !== false, $message . ' Missing: ' . $needle );
}

function assert_not_contains_text( string $needle, string $haystack, string $message ): void {
	assert_true( strpos( $haystack, $needle ) === false, $message . ' Unexpected: ' . $needle );
}

function call_private( object $object, string $method, array $args = array() ) {
	$ref = new ReflectionMethod( $object, $method );
	$ref->setAccessible( true );
	return $ref->invokeArgs( $object, $args );
}

$options = new Options();
$GLOBALS['llmf_test_options'][ Options::OPTION_KEY ] = $options->defaults();
$GLOBALS['llmf_test_options']['page_on_front'] = 10;
$GLOBALS['llmf_test_options']['page_for_posts'] = 11;
$GLOBALS['llmf_test_options']['wp_page_for_privacy_policy'] = 12;
$GLOBALS['llmf_test_posts'][10] = new WP_Post( array( 'ID' => 10, 'post_type' => 'page', 'post_name' => 'front', 'post_title' => 'Front Page' ) );
$GLOBALS['llmf_test_posts'][11] = new WP_Post( array( 'ID' => 11, 'post_type' => 'page', 'post_name' => 'blog', 'post_title' => 'Blog' ) );
$GLOBALS['llmf_test_posts'][12] = new WP_Post( array( 'ID' => 12, 'post_type' => 'page', 'post_name' => 'privacy', 'post_title' => 'Privacy' ) );

assert_true( Markdown::url_destination( 'javascript:alert(1)' ) === '', 'Unsafe URL protocols are rejected.' );
assert_true( Markdown::link_text( 'A [B]' ) === 'A \[B\]', 'Markdown link labels are escaped.' );

$clean = $options->sanitize(
	array(
		'enabled_markdown'      => 1,
		'enabled_llms_txt'      => 1,
		'md_send_noindex'       => 1,
		'post_types'            => array( 'post', 'page' ),
		'llms_custom_markdown'  => "# Bad Heading\n\nSetext heading\n---\n\nHelpful note\n\n```\n# kept in code\n```",
		'llms_essential_links'  => "Docs | /docs | Key documentation\nBad | javascript:alert(1)",
		'llms_recent_limit'     => 5,
		'sitemap_url'           => '/sitemap.xml',
	)
);

assert_not_contains_text( '# Bad Heading', $clean['llms_custom_markdown'], 'Custom llms.txt Markdown cannot keep ATX headings.' );
assert_not_contains_text( "Setext heading\n---", $clean['llms_custom_markdown'], 'Custom llms.txt Markdown cannot keep setext headings.' );
assert_contains_text( 'Bad Heading', $clean['llms_custom_markdown'], 'Custom heading text is preserved as plain text.' );
assert_contains_text( '# kept in code', $clean['llms_custom_markdown'], 'Headings inside code fences are preserved.' );
assert_contains_text( 'Docs | /docs | Key documentation', $clean['llms_essential_links'], 'Valid essential link is preserved.' );
assert_not_contains_text( 'javascript:', $clean['llms_essential_links'], 'Unsafe essential link is rejected.' );

$GLOBALS['llmf_test_options'][ Options::OPTION_KEY ] = array_merge( $options->defaults(), $clean );

$llms = new Llms( $options );
$txt  = call_private( $llms, 'build_llms_txt' );
assert_true( strpos( $txt, '# Test Site' ) === 0, 'llms.txt starts with an H1 site title.' );
assert_true( strpos( $txt, '> Test description' ) !== false, 'llms.txt includes site description blockquote.' );
assert_true( strpos( $txt, '## Main links' ) < strpos( $txt, '## Essential' ), 'Essential section follows Main links.' );
assert_true( strpos( $txt, '## Essential' ) < strpos( $txt, '## Posts' ), 'Recent post type sections follow Essential.' );
assert_contains_text( '- [Docs](https://example.test/docs): Key documentation', $txt, 'Configured essential links are emitted as absolute URLs.' );

$exporter = new Exporter( $options );
$post     = new WP_Post( array( 'ID' => 21, 'post_type' => 'post', 'post_name' => 'example-post', 'post_title' => 'Example Post' ) );
$headers  = call_private( $exporter, 'markdown_headers_for_post', array( $post ) );
$headers_text = implode( "\n", $headers );
assert_contains_text( 'Link: <https://example.test/post/example-post/>; rel="canonical"', $headers_text, 'Markdown export sends a canonical Link header.' );
assert_contains_text( 'X-Robots-Tag: noindex', $headers_text, 'Markdown export sends noindex by default.' );
assert_not_contains_text( 'nofollow', $headers_text, 'Markdown export does not send nofollow.' );

$blocks = array(
	array(
		'blockName' => 'core/paragraph',
		'innerHTML' => '<p>Use <code>foo `bar`</code> and <a href="https://example.test/a (b)">a link</a>.</p>',
	),
	array(
		'blockName' => 'core/code',
		'attrs'     => array( 'content' => "echo \"```\";\n" ),
	),
	array(
		'blockName'   => 'core/buttons',
		'innerBlocks' => array(
			array(
				'blockName' => 'core/button',
				'innerHTML' => '<div class="wp-block-button"><a href="https://example.test/download">Download</a></div>',
			),
		),
	),
	array(
		'blockName' => 'core/file',
		'innerHTML' => '<div class="wp-block-file"><a href="https://example.test/file.pdf">Spec PDF</a><a href="https://example.test/file.pdf" download>Download</a></div>',
	),
	array(
		'blockName' => 'core/embed',
		'attrs'     => array( 'url' => 'https://youtu.be/example', 'providerNameSlug' => 'youtube' ),
	),
	array(
		'blockName' => 'core/details',
		'innerHTML' => '<details><summary>More info</summary><p>Hidden but useful.</p></details>',
	),
);

$md = call_private( $exporter, 'blocks_to_markdown', array( $blocks ) );
assert_contains_text( '`` foo `bar` ``', $md, 'Inline code with backticks uses a wider code span.' );
assert_contains_text( "````\necho \"```\";\n````", $md, 'Code fences expand when content contains triple backticks.' );
assert_contains_text( '[Download](https://example.test/download)', $md, 'Button URLs are preserved.' );
assert_contains_text( '[Spec PDF](https://example.test/file.pdf)', $md, 'File URLs are preserved.' );
assert_contains_text( '[Embed: youtube](https://youtu.be/example)', $md, 'Embed source URLs are preserved.' );
assert_contains_text( '**More info**', $md, 'Details summary is preserved.' );

$draft = new WP_Post( array( 'ID' => 30, 'post_type' => 'post', 'post_status' => 'draft' ) );
$locked = new WP_Post( array( 'ID' => 31, 'post_type' => 'post', 'post_password' => 'secret' ) );
assert_true( ! $options->can_export_post( $draft, 'markdown' ), 'Draft posts are not exportable.' );
assert_true( ! $options->can_export_post( $locked, 'markdown' ), 'Password-protected posts are not exportable.' );

echo 'OK: ' . (int) $GLOBALS['llmf_tests_run'] . " assertions\n";
