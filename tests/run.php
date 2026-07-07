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
$GLOBALS['llmf_test_filters'] = array();
$GLOBALS['llmf_test_scheduled_events'] = array();
$GLOBALS['llmf_test_current_user_can'] = true;
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

if ( ! class_exists( 'LLMF_Test_Json_Response' ) ) {
	class LLMF_Test_Json_Response extends RuntimeException {
		public array $response;
		public int $status;

		public function __construct( array $response, int $status = 200 ) {
			parent::__construct( 'JSON response intercepted.' );
			$this->response = $response;
			$this->status   = $status;
		}
	}
}

function __( $text, $domain = null ) { return $text; }
function _n( $single, $plural, $number, $domain = null ) { return (int) $number === 1 ? $single : $plural; }
function esc_html__( $text, $domain = null ) { return $text; }
function esc_attr__( $text, $domain = null ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_url( $url, $protocols = null ) { return esc_attr( $url ); }
function esc_textarea( $text ) { return esc_html( $text ); }
function checked( $checked, $current = true, $echo = true ) { return $checked == $current ? ' checked="checked"' : ''; }
function selected( $selected, $current = true, $echo = true ) { return $selected == $current ? ' selected="selected"' : ''; }
function is_admin() { return false; }
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { return true; }
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['llmf_test_filters'][ $hook ][] = $callback;
	return true;
}
function apply_filters( $hook, $value, ...$args ) {
	if ( empty( $GLOBALS['llmf_test_filters'][ $hook ] ) ) {
		return $value;
	}
	foreach ( $GLOBALS['llmf_test_filters'][ $hook ] as $callback ) {
		$value = $callback( $value, ...$args );
	}
	return $value;
}
function remove_all_filters( $hook = null ) {
	if ( $hook === null ) {
		$GLOBALS['llmf_test_filters'] = array();
		return true;
	}
	unset( $GLOBALS['llmf_test_filters'][ $hook ] );
	return true;
}
function wp_unslash( $value ) { return is_string( $value ) ? stripslashes( $value ) : $value; }
function wp_kses_post( $value ) { return (string) $value; }
function wp_kses( $value, $allowed_html = array() ) { return (string) $value; }
function wp_strip_all_tags( $text, $remove_breaks = false ) { return strip_tags( (string) $text ); }
function sanitize_text_field( $value ) { return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $value ) ) ); }
function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) ); }
function current_user_can( $capability, ...$args ) { return ! empty( $GLOBALS['llmf_test_current_user_can'] ); }
function wp_json_encode( $value, $flags = 0, $depth = 512 ) { return json_encode( $value, $flags ); }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' ); }
function wp_create_nonce( $action = -1 ) { return 'test-nonce'; }
function wp_verify_nonce( $nonce, $action = -1 ) { return $nonce === 'test-nonce'; }
function check_ajax_referer( $action = -1, $query_arg = false, $stop = true ) {
	$key   = $query_arg ? (string) $query_arg : '_ajax_nonce';
	$nonce = isset( $_REQUEST[ $key ] ) ? (string) $_REQUEST[ $key ] : '';

	if ( wp_verify_nonce( $nonce, $action ) ) {
		return 1;
	}

	if ( $stop ) {
		throw new LLMF_Test_Json_Response(
			array(
				'success' => false,
				'data'    => array( 'message' => 'Invalid nonce.' ),
			),
			403
		);
	}

	return false;
}
function wp_send_json_success( $data = null, $status_code = null, $flags = 0 ) {
	throw new LLMF_Test_Json_Response(
		array(
			'success' => true,
			'data'    => $data,
		),
		$status_code ? (int) $status_code : 200
	);
}
function wp_send_json_error( $data = null, $status_code = null, $flags = 0 ) {
	throw new LLMF_Test_Json_Response(
		array(
			'success' => false,
			'data'    => $data,
		),
		$status_code ? (int) $status_code : 200
	);
}
function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
	$html = '<input type="hidden" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="test-nonce" />';
	if ( $display ) {
		echo $html;
	}
	return $html;
}
function register_setting( $option_group, $option_name, $args = array() ) { return true; }
function add_settings_section( $id, $title, $callback, $page ) {
	$GLOBALS['wp_settings_sections'][ $page ][ $id ] = array(
		'id'       => $id,
		'title'    => $title,
		'callback' => $callback,
	);
}
function add_settings_field( $id, $title, $callback, $page, $section = 'default', $args = array() ) {
	$GLOBALS['wp_settings_fields'][ $page ][ $section ][ $id ] = array(
		'id'       => $id,
		'title'    => $title,
		'callback' => $callback,
		'args'     => $args,
	);
}
function settings_fields( $option_group ) {
	echo '<input type="hidden" name="option_page" value="' . esc_attr( $option_group ) . '" />';
	echo '<input type="hidden" name="action" value="update" />';
	wp_nonce_field( $option_group . '-options' );
}
function do_settings_fields( $page, $section ) {
	if ( empty( $GLOBALS['wp_settings_fields'][ $page ][ $section ] ) ) {
		return;
	}
	foreach ( $GLOBALS['wp_settings_fields'][ $page ][ $section ] as $field ) {
		$title = (string) $field['title'];
		if ( ! empty( $field['args']['label_for'] ) ) {
			$title = '<label for="' . esc_attr( (string) $field['args']['label_for'] ) . '">' . $title . '</label>';
		}
		echo '<tr><th scope="row">' . $title . '</th><td>';
		call_user_func( $field['callback'] );
		echo '</td></tr>';
	}
}
function submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null ) {
	$attrs = '';
	$id    = $name;
	if ( is_array( $other_attributes ) ) {
		if ( isset( $other_attributes['id'] ) ) {
			$id = (string) $other_attributes['id'];
		}
		foreach ( $other_attributes as $key => $value ) {
			if ( $key === 'id' ) {
				continue;
			}
			$attrs .= ' ' . esc_attr( (string) $key ) . '="' . esc_attr( (string) $value ) . '"';
		}
	}
	$button = '<input type="submit" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" class="button button-' . esc_attr( $type ) . '" value="' . esc_attr( (string) $text ) . '"' . $attrs . ' />';
	echo $wrap ? '<p class="submit">' . $button . '</p>' : $button;
}
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
function wp_next_scheduled( $hook ) { return isset( $GLOBALS['llmf_test_scheduled_events'][ $hook ] ) ? $GLOBALS['llmf_test_scheduled_events'][ $hook ] : false; }
function wp_schedule_single_event( $timestamp, $hook, $args = array() ) { $GLOBALS['llmf_test_scheduled_events'][ $hook ] = (int) $timestamp; return true; }
function post_type_exists( $post_type ) { return in_array( $post_type, array( 'post', 'page', 'attachment', 'private_type', 'public_hidden' ), true ); }
function get_post_type_object( $post_type ) {
	if ( ! post_type_exists( $post_type ) ) {
		return null;
	}
	$obj               = new stdClass();
	$obj->public       = $post_type !== 'private_type';
	$obj->publicly_queryable = ! in_array( $post_type, array( 'page', 'private_type', 'public_hidden' ), true );
	$obj->hierarchical = $post_type === 'page';
	$obj->labels       = (object) array( 'name' => $post_type === 'page' ? 'Pages' : 'Posts' );
	return $obj;
}
function get_post_types( $args = array(), $output = 'names' ) {
	$types = array();
	foreach ( array( 'post', 'page', 'attachment', 'private_type', 'public_hidden' ) as $type ) {
		$obj = get_post_type_object( $type );
		if ( ! $obj ) {
			continue;
		}
		if ( isset( $args['public'] ) && (bool) $args['public'] !== (bool) $obj->public ) {
			continue;
		}
		$types[ $type ] = $output === 'objects' ? $obj : $type;
	}
	return $types;
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
function wp_is_post_revision( $post_id ) { return false; }
function wp_is_post_autosave( $post_id ) { return false; }
function parse_blocks( $content ) { return array(); }

require_once __DIR__ . '/../inc/Markdown.php';
require_once __DIR__ . '/../inc/Options.php';
require_once __DIR__ . '/../inc/Response.php';
require_once __DIR__ . '/../inc/Exporter.php';
require_once __DIR__ . '/../inc/Llms.php';
require_once __DIR__ . '/../inc/Admin.php';

use LLMFriendly\Admin;
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

function assert_occurrences( string $needle, string $haystack, int $expected, string $message ): void {
	assert_true( substr_count( $haystack, $needle ) === $expected, $message . ' Expected ' . $expected . ' occurrences of: ' . $needle );
}

function call_private( object $object, string $method, array $args = array() ) {
	$ref = new ReflectionMethod( $object, $method );
	$ref->setAccessible( true );
	return $ref->invokeArgs( $object, $args );
}

function capture_json_response( callable $callback ): array {
	try {
		$callback();
	} catch ( LLMF_Test_Json_Response $response ) {
		return array( $response->response, $response->status );
	}

	throw new RuntimeException( 'Expected a JSON response.' );
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
assert_true( Markdown::url_destination( '//evil.test/x' ) === '', 'Protocol-relative URLs are rejected.' );
assert_true( Markdown::url_destination( "https://example.test/a\r\nX-Test: bad" ) === '', 'URLs with control characters are rejected.' );
assert_true( $options->sanitize_sitemap_url( 'https://evil.test/sitemap.xml' ) === '/sitemap.xml', 'External sitemap URLs are rejected by default.' );
assert_true( $options->sanitize_sitemap_url( '//evil.test/sitemap.xml' ) === '/sitemap.xml', 'Protocol-relative sitemap URLs are rejected.' );
assert_true( $options->sanitize_sitemap_url( "/sitemap.xml\r\nBad: yes" ) === '/sitemap.xml', 'Sitemap URLs with control characters fall back to default.' );
assert_true( $options->absolute_http_url( '/docs' ) === 'https://example.test/docs', 'Site-relative URLs are normalized to absolute HTTP URLs.' );

assert_true( $options->is_exportable_post_type( 'post' ), 'Public queryable posts are exportable.' );
assert_true( $options->is_exportable_post_type( 'page' ), 'Built-in pages are exportable even though they are not publicly queryable.' );
assert_true( ! $options->is_exportable_post_type( 'attachment' ), 'Attachments are never exportable.' );
assert_true( ! $options->is_exportable_post_type( 'private_type' ), 'Non-public post types are not exportable.' );
assert_true( ! $options->is_exportable_post_type( 'public_hidden' ), 'Public but non-queryable post types are not exportable by default.' );
add_filter(
	'llmf_exportable_post_type',
	function ( $eligible, $post_type, $obj ) {
		return $post_type === 'public_hidden' ? true : $eligible;
	}
);
assert_true( $options->is_exportable_post_type( 'public_hidden' ), 'The exportable post type filter can opt in public edge-case types.' );
remove_all_filters( 'llmf_exportable_post_type' );

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

add_filter(
	'llmf_llms_description_max_length',
	function ( $value ) {
		return 0;
	}
);
assert_true( strlen( $options->sanitize_llms_description( str_repeat( 'a', 600 ) ) ) === 500, 'Invalid description length filter falls back to the default.' );
remove_all_filters( 'llmf_llms_description_max_length' );

add_filter(
	'llmf_llms_description_max_length',
	function ( $value ) {
		return 100000;
	}
);
assert_true( strlen( $options->sanitize_llms_description( str_repeat( 'b', 2500 ) ) ) === 2000, 'Excessive description length filter is capped.' );
remove_all_filters( 'llmf_llms_description_max_length' );

add_filter(
	'llmf_markdown_override_max_length',
	function ( $value ) {
		return 3;
	}
);
assert_true( $options->sanitize_markdown_override( 'abcdef' ) === 'abc', 'Markdown override length filters still allow lower site-specific caps.' );
remove_all_filters( 'llmf_markdown_override_max_length' );

$GLOBALS['llmf_test_options'][ Options::OPTION_KEY ] = array_merge( $options->defaults(), $clean );

$llms = new Llms( $options );

$GLOBALS['wp_settings_sections'] = array();
$GLOBALS['wp_settings_fields']   = array();
$admin = new Admin( $options, $llms );
$admin->admin_init();
ob_start();
$admin->render_page();
$admin_html = (string) ob_get_clean();
assert_contains_text( 'class="wrap llmf-settings-page"', $admin_html, 'Settings page uses the scoped layout wrapper.' );
assert_contains_text( 'class="llmf-settings-shell"', $admin_html, 'Settings page renders the modern app shell.' );
assert_contains_text( 'class="llmf-settings-nav"', $admin_html, 'Settings page renders sticky section navigation.' );
assert_contains_text( 'id="llmf-overview"', $admin_html, 'Settings page renders the overview panel.' );
assert_contains_text( 'id="llmf-markdown"', $admin_html, 'Settings page renders the Markdown exports panel.' );
assert_contains_text( 'id="llmf-scope"', $admin_html, 'Settings page renders the content scope panel.' );
assert_contains_text( 'id="llmf-exclusions"', $admin_html, 'Exclusions are rendered as a panel.' );
assert_contains_text( 'id="llmf-llms"', $admin_html, 'llms.txt settings are rendered as a panel.' );
assert_contains_text( 'id="llmf-maintenance"', $admin_html, 'Maintenance actions are rendered as a separate panel.' );
assert_not_contains_text( 'form-table', $admin_html, 'Modern settings page does not render the table-first form layout.' );
assert_contains_text( 'for="llmf-base-path"', $admin_html, 'Settings API label_for is wired for the base path field.' );
assert_contains_text( 'id="llmf-base-path"', $admin_html, 'Base path field has a stable id.' );
assert_contains_text( 'aria-describedby="llmf-base-path-description"', $admin_html, 'Base path field references its description.' );
assert_contains_text( 'name="llmf_options[base_path]"', $admin_html, 'Existing option name is preserved for base path.' );
assert_contains_text( 'name="llmf_options[post_types][]"', $admin_html, 'Existing option name is preserved for post types.' );
assert_contains_text( 'name="llmf_options[llms_regen_mode]"', $admin_html, 'Existing option name is preserved for regeneration mode.' );
assert_contains_text( 'class="llmf-segmented"', $admin_html, 'Regeneration mode uses a segmented native radio control.' );
assert_contains_text( 'class="llmf-post-type-grid"', $admin_html, 'Post types are rendered as selectable cards.' );
assert_contains_text( 'value="page"', $admin_html, 'Built-in pages are available in the content scope picker.' );
assert_contains_text( 'id="llmf-search-post-results"', $admin_html, 'Exclusion search has a controlled results region.' );
assert_contains_text( 'aria-autocomplete="list"', $admin_html, 'Exclusion search advertises list autocomplete.' );
assert_contains_text( 'aria-expanded="false"', $admin_html, 'Exclusion search starts collapsed for assistive tech.' );
assert_contains_text( 'role="status" aria-live="polite"', $admin_html, 'Exclusion search status is announced to screen readers.' );
assert_contains_text( 'data-llmf-exclusions-dirty hidden', $admin_html, 'Exclusion changes get a pending-save note.' );
assert_contains_text( 'class="button button-secondary llmf-excluded-posts__clear"', $admin_html, 'Exclusion cards include clear-this-type controls.' );
assert_contains_text( 'id="llmf-preview"', $admin_html, 'Generated llms.txt preview is rendered.' );
assert_contains_text( 'data-llmf-preview-load', $admin_html, 'Preview has a load/refresh control.' );
assert_contains_text( 'data-llmf-preview-copy disabled="disabled"', $admin_html, 'Preview copy button starts disabled.' );
assert_contains_text( 'data-llmf-preview-dirty hidden', $admin_html, 'Preview warns when form changes are unsaved.' );
assert_contains_text( 'data-llmf-save-bar hidden', $admin_html, 'Sticky save bar is present and initially hidden.' );
assert_contains_text( 'id="llmf-save-settings-sticky"', $admin_html, 'Sticky save button has a unique id.' );
assert_contains_text( 'name="llmf_regenerate_nonce"', $admin_html, 'Maintenance form uses a unique nonce field name.' );
assert_contains_text( 'id="llmf-regenerate-submit"', $admin_html, 'Maintenance submit button has a unique id.' );
assert_not_contains_text( 'id="submit"', $admin_html, 'Settings page does not emit duplicate submit ids.' );
assert_occurrences( 'id="_wpnonce"', $admin_html, 1, 'Only the settings form uses the default WordPress nonce id.' );

$preview_options = get_option( Options::OPTION_KEY, array() );
$preview_options['llms_cache']               = 'cached-before-preview';
$preview_options['llms_cache_ts']            = 123;
$preview_options['llms_cache_rev']           = 7;
$preview_options['llms_cache_hash']          = 'before-hash';
$preview_options['llms_cache_settings_hash'] = 'stale-settings-hash';
update_option( Options::OPTION_KEY, $preview_options, false );
$before_preview = get_option( Options::OPTION_KEY, array() );
$preview        = $llms->preview();
$after_preview  = get_option( Options::OPTION_KEY, array() );
assert_true( $after_preview === $before_preview, 'Llms preview does not update cached option fields.' );
assert_true( isset( $preview['enabled'] ) && $preview['enabled'] === true, 'Llms preview reports enabled state.' );
assert_true( isset( $preview['url'] ) && $preview['url'] === 'https://example.test/llms.txt', 'Llms preview returns the public URL.' );
assert_contains_text( '# Test Site', (string) $preview['content'], 'Llms preview returns generated content.' );
assert_true( isset( $preview['contentHash'] ) && (string) $preview['contentHash'] !== '', 'Llms preview returns a content hash.' );
assert_true( isset( $preview['cacheStatus'] ) && $preview['cacheStatus'] === 'needs_regeneration', 'Llms preview detects stale cache state.' );
assert_true( isset( $preview['truncated'] ) && $preview['truncated'] === false, 'Llms preview reports truncation state.' );

$_REQUEST = array( 'nonce' => 'bad-nonce' );
list( $json, $status ) = capture_json_response(
	function () use ( $admin ) {
		$admin->ajax_preview_llms();
	}
);
assert_true( $status === 403 && $json['success'] === false, 'Preview AJAX rejects invalid nonces.' );

$GLOBALS['llmf_test_current_user_can'] = false;
$_REQUEST = array( 'nonce' => 'test-nonce' );
list( $json, $status ) = capture_json_response(
	function () use ( $admin ) {
		$admin->ajax_preview_llms();
	}
);
$GLOBALS['llmf_test_current_user_can'] = true;
assert_true( $status === 403 && $json['success'] === false, 'Preview AJAX rejects insufficient capabilities.' );

$_REQUEST = array( 'nonce' => 'test-nonce' );
list( $json, $status ) = capture_json_response(
	function () use ( $admin ) {
		$admin->ajax_preview_llms();
	}
);
$_REQUEST = array();
assert_true( $status === 200 && $json['success'] === true, 'Preview AJAX returns a successful JSON response.' );
assert_true( isset( $json['data']['enabled'], $json['data']['url'], $json['data']['content'], $json['data']['contentHash'], $json['data']['generatedAt'], $json['data']['cacheStatus'], $json['data']['truncated'] ), 'Preview AJAX success shape is stable.' );

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

$fenced_meta_md = call_private(
	$exporter,
	'post_to_markdown',
	array(
		new WP_Post( array( 'ID' => 22, 'post_type' => 'post', 'post_name' => 'fenced', 'post_title' => 'Fence ``` Title' ) ),
		'',
		array(
			'title'       => 'Fence ``` Title',
			'description' => 'Description containing ``` inside JSON.',
		),
	)
);
assert_contains_text( "````json\n", $fenced_meta_md, 'Metadata JSON fence expands when metadata contains triple backticks.' );
assert_contains_text( "\n````\n\n# Fence ``` Title", $fenced_meta_md, 'Expanded metadata fence closes before the Markdown H1.' );

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
$attachment = new WP_Post( array( 'ID' => 32, 'post_type' => 'attachment', 'post_name' => 'file' ) );
$private_type = new WP_Post( array( 'ID' => 33, 'post_type' => 'private_type', 'post_name' => 'hidden' ) );
$public_hidden = new WP_Post( array( 'ID' => 34, 'post_type' => 'public_hidden', 'post_name' => 'hidden-public' ) );
$excluded = new WP_Post( array( 'ID' => 35, 'post_type' => 'post', 'post_name' => 'excluded', 'post_title' => 'Excluded' ) );
$GLOBALS['llmf_test_posts'][35] = $excluded;
$saved_options = $GLOBALS['llmf_test_options'][ Options::OPTION_KEY ];
$GLOBALS['llmf_test_options'][ Options::OPTION_KEY ] = array_merge(
	$saved_options,
	array(
		'excluded_posts' => array(
			'post' => array( 35 ),
		),
	)
);
assert_true( ! $options->can_export_post( $draft, 'markdown' ), 'Draft posts are not exportable.' );
assert_true( ! $options->can_export_post( $locked, 'markdown' ), 'Password-protected posts are not exportable.' );
assert_true( ! $options->can_export_post( $attachment, 'markdown' ), 'Attachments are not exportable.' );
assert_true( ! $options->can_export_post( $private_type, 'markdown' ), 'Non-public post types are not exportable.' );
assert_true( ! $options->can_export_post( $public_hidden, 'markdown' ), 'Public but non-queryable post types are not exportable by default.' );
assert_true( ! $options->can_export_post( $excluded, 'markdown' ), 'Excluded posts are not exportable.' );
$GLOBALS['llmf_test_options'][ Options::OPTION_KEY ] = $saved_options;

$GLOBALS['llmf_test_scheduled_events'] = array();
$auto_post = new WP_Post( array( 'ID' => 36, 'post_type' => 'post', 'post_name' => 'auto', 'post_title' => 'Auto' ) );
$llms->maybe_regenerate_on_save( 36, $auto_post, true );
assert_true( isset( $GLOBALS['llmf_test_scheduled_events']['llmf_regenerate_llms_cache'] ), 'Automatic regeneration is scheduled instead of rebuilt immediately.' );
$scheduled_at = $GLOBALS['llmf_test_scheduled_events']['llmf_regenerate_llms_cache'];
$llms->maybe_regenerate_on_save( 36, $auto_post, true );
assert_true( $GLOBALS['llmf_test_scheduled_events']['llmf_regenerate_llms_cache'] === $scheduled_at, 'Repeated automatic regeneration requests are coalesced.' );
$llms->regenerate_from_schedule();
$after_schedule = get_option( Options::OPTION_KEY, array() );
assert_true( isset( $after_schedule['llms_cache'] ) && trim( (string) $after_schedule['llms_cache'] ) !== '', 'Scheduled regeneration writes llms.txt cache.' );
assert_true( isset( $after_schedule['llms_cache_settings_hash'] ) && (string) $after_schedule['llms_cache_settings_hash'] !== '', 'Scheduled regeneration writes settings hash.' );

$locked_options = get_option( Options::OPTION_KEY, array() );
$locked_options['llms_cache'] = 'locked-cache';
$locked_options['llms_cache_settings_hash'] = 'locked-hash';
update_option( Options::OPTION_KEY, $locked_options, false );
set_transient( 'llmf_llms_regen_lock', 1, 10 );
$llms->regenerate( true );
$after_locked_regen = get_option( Options::OPTION_KEY, array() );
assert_true( $after_locked_regen['llms_cache'] === 'locked-cache', 'Manual regeneration respects an active regeneration lock.' );
assert_true( (bool) get_transient( 'llmf_llms_regen_lock' ), 'Manual regeneration does not delete an active regeneration lock it did not acquire.' );
delete_transient( 'llmf_llms_regen_lock' );

$stale_options = get_option( Options::OPTION_KEY, array() );
$stale_options['llms_cache_settings_hash'] = 'stale';
update_option( Options::OPTION_KEY, $stale_options, false );
$llms->regenerate( false );
$after_stale_regen = get_option( Options::OPTION_KEY, array() );
$expected_hash = call_private( $llms, 'settings_hash_from_options', array( $after_stale_regen ) );
assert_true( $after_stale_regen['llms_cache_settings_hash'] === $expected_hash, 'Regeneration stores the current settings hash.' );

echo 'OK: ' . (int) $GLOBALS['llmf_tests_run'] . " assertions\n";
