<?php
/**
 * Destructive only to fixtures it creates. Run against a disposable/local WordPress.
 * LLMF_WP_ROOT=/absolute/wp/path LLMF_TEST_ALLOW_WRITES=1 php tests/wordpress-mechanics.php
 * Optional LLMF_HTTP_RESOLVE=host:443:127.0.0.1; no TLS verification bypass.
 */
if ( getenv( 'LLMF_TEST_ALLOW_WRITES' ) !== '1' || ! getenv( 'LLMF_WP_ROOT' ) ) {
	fwrite( STDERR, "Set LLMF_WP_ROOT and LLMF_TEST_ALLOW_WRITES=1 for a disposable/local site.\n" );
	exit( 2 );
}
require rtrim( getenv( 'LLMF_WP_ROOT' ), '/\\' ) . '/wp-load.php';
if ( ! class_exists( 'LLMFriendly\Catalog' ) ) {
	fwrite( STDERR, "Activate this checkout of LLM Friendly first.\n" );
	exit( 2 );
}

use LLMFriendly\Options;
use LLMFriendly\Catalog;
use LLMFriendly\Content;
use LLMFriendly\Diagnostics;
use LLMFriendly\Exporter;
use LLMFriendly\Llms;
use LLMFriendly\Rewrites;

$options = new Options();
$run = 'llmf-mechanics-' . strtolower( wp_generate_password( 10, false, false ) );
$posts = array();
$users = array();
$terms = array();
$checks = 0;
$failures = array();
$original_user = get_current_user_id();
$original_cookie = $_COOKIE;
$snapshots = array();
foreach ( array( Options::OPTION_KEY, 'rewrite_rules', 'llmf_catalog_generation', Catalog::JOB_KEY, 'llmf_root_ids', 'llmf_root_dependencies', 'llmf_catalog_scan_lock', '_transient_llmf_flush_rewrite_rules', '_transient_timeout_llmf_flush_rewrite_rules', '_transient_llmf_llms_regen_lock', '_transient_timeout_llmf_llms_regen_lock', '_transient_llmf_llms_regen_pending', '_transient_timeout_llmf_llms_regen_pending' ) as $key ) {
	$marker = new stdClass();
	$value = get_option( $key, $marker );
	$snapshots[ $key ] = array( 'exists' => $value !== $marker, 'value' => $value );
}
$hooks = array( 'llmf_catalog_scan', 'llmf_regenerate_llms_cache' );
$cron = array();
foreach ( _get_cron_array() as $timestamp => $events ) {
	foreach ( $hooks as $hook ) {
		if ( isset( $events[ $hook ] ) ) { $cron[ $timestamp ][ $hook ] = $events[ $hook ]; }
	}
}

function runtime_assert( bool $condition, string $message ): void {
	$GLOBALS['checks'] ++;
	if ( ! $condition ) { $GLOBALS['failures'][] = $message; }
}
function runtime_http( string $url, string $method = 'GET', array $headers = array(), array $fields = array(), string $cookies = '' ): array {
	$curl = curl_init( $url );
	$response_headers = array();
	$args = array( CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_HTTPHEADER => $headers, CURLOPT_HEADERFUNCTION => function ( $handle, $line ) use ( &$response_headers ) {
		if ( strpos( $line, ':' ) !== false ) { list( $key, $value ) = explode( ':', $line, 2 ); $response_headers[ strtolower( trim( $key ) ) ] = trim( $value ); }
		return strlen( $line );
	} );
	if ( getenv( 'LLMF_HTTP_RESOLVE' ) ) { $args[ CURLOPT_RESOLVE ] = array( getenv( 'LLMF_HTTP_RESOLVE' ) ); }
	if ( $method === 'HEAD' ) { $args[ CURLOPT_NOBODY ] = true; }
	if ( $method === 'POST' ) { $args[ CURLOPT_POST ] = true; $args[ CURLOPT_POSTFIELDS ] = http_build_query( $fields ); }
	if ( $cookies !== '' ) { $args[ CURLOPT_COOKIE ] = $cookies; }
	curl_setopt_array( $curl, $args );
	$body = curl_exec( $curl );
	$code = (int) curl_getinfo( $curl, CURLINFO_HTTP_CODE );
	$error = curl_errno( $curl );
	curl_close( $curl );
	return array( 'status' => $code, 'headers' => $response_headers, 'body' => is_string( $body ) ? $body : '', 'transportError' => $error );
}
function runtime_post( array $args ): int {
	$args = array_merge( array( 'post_title' => $GLOBALS['run'] . ' fixture', 'post_name' => $GLOBALS['run'] . '-' . ( count( $GLOBALS['posts'] ) + 1 ), 'post_status' => 'publish', 'post_type' => 'post', 'post_content' => '<p>Fixture evidence.</p>' ), $args );
	$id = wp_insert_post( $args, true );
	if ( is_wp_error( $id ) ) { throw new RuntimeException( 'Fixture creation failed.' ); }
	$GLOBALS['posts'][] = (int) $id;
	update_post_meta( $id, '_llmf_test_run', $GLOBALS['run'] );
	return (int) $id;
}
function runtime_auth( int $id ): string {
	wp_set_current_user( $id );
	$expiry = time() + 1800;
	$token = WP_Session_Tokens::get_instance( $id )->create( $expiry );
	$logged = wp_generate_auth_cookie( $id, $expiry, 'logged_in', $token );
	$_COOKIE[ LOGGED_IN_COOKIE ] = $logged;
	$auth = wp_generate_auth_cookie( $id, $expiry, 'auth', $token );
	$secure = wp_generate_auth_cookie( $id, $expiry, 'secure_auth', $token );
	return LOGGED_IN_COOKIE . '=' . $logged . '; ' . AUTH_COOKIE . '=' . $auth . '; ' . SECURE_AUTH_COOKIE . '=' . $secure;
}

try {
	// Local fixture roles, including an administrator; never touch existing accounts.
	foreach ( array( 'subscriber', 'contributor', 'author', 'editor', 'administrator' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => $run . '-' . $role, 'user_pass' => wp_generate_password( 40, true, true ), 'user_email' => $run . '-' . $role . '@example.invalid', 'role' => $role ) );
		if ( is_wp_error( $id ) ) { throw new RuntimeException( 'Fixture user creation failed.' ); }
		$users[ $role ] = (int) $id;
		// Some local sites require 2FA for admin roles. Enroll only this new fixture
		// through the site's public service, without changing the site's policy.
		if ( class_exists( 'Sherer_Pro_V4\Security' ) ) {
			$security = new Sherer_Pro_V4\Security();
			if ( $security->user_requires_two_factor( get_userdata( $id ) ) ) {
				wp_set_current_user( $id );
				$secret = $security->generate_totp_secret();
				$enrollment = $security->issue_enrollment_token( $id );
				if ( is_wp_error( $enrollment ) ) { throw new RuntimeException( 'Fixture 2FA enrollment could not be issued.' ); }
				$previous_post = $_POST;
				$previous_method = $_SERVER['REQUEST_METHOD'] ?? null;
				$_SERVER['REQUEST_METHOD'] = 'POST';
				$_POST = array( 'sherer_security_2fa_enable' => '1', 'sherer_security_2fa_secret' => $secret, 'sherer_security_2fa_confirm' => $security->generate_totp_code( $secret ), 'sherer_security_2fa_enrollment_token' => $enrollment, 'sherer_security_2fa_nonce' => wp_create_nonce( 'sherer_security_2fa_' . $id ) );
				$security->save_two_factor_profile( $id );
				$_POST = $previous_post;
				if ( $previous_method === null ) { unset( $_SERVER['REQUEST_METHOD'] ); } else { $_SERVER['REQUEST_METHOD'] = $previous_method; }
				if ( ! $security->user_has_two_factor_enabled( get_userdata( $id ) ) ) { throw new RuntimeException( 'Fixture 2FA enrollment failed.' ); }
				unset( $secret, $enrollment );
			}
		}
	}
	wp_set_current_user( $users['administrator'] );
	$term = wp_insert_term( $run . ' topic', 'category', array( 'slug' => $run . '-topic' ) );
	if ( is_wp_error( $term ) ) { throw new RuntimeException( 'Fixture term creation failed.' ); }
	$terms[] = (int) $term['term_id'];
	$empty = wp_insert_term( $run . ' empty', 'category', array( 'slug' => $run . '-empty' ) );
	$terms[] = (int) $empty['term_id'];
	$reuse = runtime_post( array( 'post_type' => 'wp_block', 'post_content' => '<!-- wp:paragraph --><p>LLMF_FACT_10 shared fact.</p><!-- /wp:paragraph -->' ) );
	$cases = array(
		'What is the first plain fact?' => '<p>LLMF_FACT_1 plain paragraph.</p>',
		'What is the nested list fact?' => '<ul><li>Parent<ul><li>LLMF_FACT_2 nested list.</li></ul></li></ul>',
		'What value is in the table?' => '<table><tr><th>Key</th><th>Value</th></tr><tr><td>Fact</td><td>LLMF_FACT_3</td></tr></table>',
		'What fact is in the code?' => '<pre><code>LLMF_FACT_4 &amp;lt;literal&amp;gt;</code></pre>',
		'What does the caption say?' => '<!-- wp:image --><figure class="wp-block-image"><img src="/fixture.png" alt="Figure" /><figcaption>LLMF_FACT_5 caption.</figcaption></figure><!-- /wp:image -->',
		'What is in the old block list?' => '<!-- wp:list --><ul><li>LLMF_FACT_6 old list.</li></ul><!-- /wp:list -->',
		'What is in the new block list?' => '<!-- wp:list --><ul><!-- wp:list-item --><li>LLMF_FACT_7 new list.</li><!-- /wp:list-item --></ul><!-- /wp:list -->',
		'What does the relative link reference?' => '<p><a href="../reference/">LLMF_FACT_8 link</a></p>',
		'What does the spanning table say?' => '<!-- wp:table --><figure class="wp-block-table"><table><tbody><tr><th colspan="10">LLMF_FACT_9 spans.</th></tr><tr><td>A</td><td>B</td></tr></tbody></table><figcaption>Span caption.</figcaption></figure><!-- /wp:table -->',
		'What is the shared block fact?' => '<!-- wp:block {"ref":' . $reuse . '} /-->',
	);
	$questions = array();
	foreach ( $cases as $question => $content ) {
		$id = runtime_post( array( 'post_title' => $run . ' question ' . ( count( $questions ) + 1 ), 'post_content' => $content, 'post_author' => $users['author'] ) );
		wp_set_object_terms( $id, array( $terms[0] ), 'category' );
		$questions[ $id ] = array( 'question' => $question, 'fact' => 'LLMF_FACT_' . ( count( $questions ) + 1 ) );
	}
	$private = runtime_post( array( 'post_status' => 'private', 'post_content' => '<p>LLMF_PRIVATE_MARKER</p>' ) );
	$draft = runtime_post( array( 'post_status' => 'draft', 'post_content' => '<p>LLMF_DRAFT_MARKER</p>' ) );
	$password = runtime_post( array( 'post_password' => 'synthetic', 'post_content' => '<p>LLMF_PASSWORD_MARKER</p>' ) );
	$excluded = runtime_post( array( 'post_content' => '<p>LLMF_EXCLUDED_MARKER</p>' ) );
	$parent = runtime_post( array( 'post_type' => 'page', 'post_title' => $run . ' parent' ) );
	$child = runtime_post( array( 'post_type' => 'page', 'post_parent' => $parent, 'post_title' => $run . ' child' ) );
	$ids = array_keys( $questions );
	$settings = array_merge( $options->get(), array( 'enabled_markdown' => 1, 'enabled_llms_txt' => 1, 'enabled_content_negotiation' => 1, 'llms_index_mode' => 'structured', 'content_profile' => 'enhanced', 'llms_regen_mode' => 'manual', 'post_types' => array( 'post', 'page' ), 'base_path' => 'llmf-test-' . substr( $run, -10 ), 'llms_pinned_ids' => $ids, 'excluded_posts' => array( 'post' => array( $excluded ) ), 'catalog_taxonomies' => array( 'post' => array( 'category' ) ) ) );
	$options->update( $settings );
	( new Rewrites( $options ) )->add_rules();
	flush_rewrite_rules( false );
	$catalog = new Catalog( $options, false );
	$exporter = new Exporter( $options );
	$llms = new Llms( $options, false );
	$diagnostics = new Diagnostics( $options, $catalog, $exporter, false );
	runtime_assert( $llms->regenerate( true ), 'Root regeneration succeeds.' );
	$root = runtime_http( home_url( '/llms.txt' ) );
	runtime_assert( $root['status'] === 200 && strpos( $root['body'], $catalog->url() ) !== false, 'Structured root is served over verified HTTPS.' );
	$index = runtime_http( $catalog->url( 'post', '', 0, $reuse ) );
	runtime_assert( $index['status'] === 200 && strpos( $index['body'], $run ) !== false, 'Type catalog is reachable over HTTPS.' );
	foreach ( $questions as $id => $case ) {
		$post = get_post( $id );
		$url = $options->markdown_url_for_post( $post );
		runtime_assert( strpos( $index['body'], '](' . $url . ')' ) !== false, 'Catalog finds the source: ' . $case['question'] );
		$md = runtime_http( $url );
		runtime_assert( $md['status'] === 200 && strpos( $md['body'], $case['fact'] ) !== false, 'Source preserves the answer: ' . $case['question'] );
		$head = runtime_http( $url, 'HEAD' );
		runtime_assert( $head['status'] === 200 && $head['body'] === '' && $head['headers']['etag'] === $md['headers']['etag'], 'Markdown HEAD matches GET validators.' );
		$etag = runtime_http( $url, 'GET', array( 'If-None-Match: ' . $md['headers']['etag'] ) );
		runtime_assert( $etag['status'] === 304 && $etag['body'] === '', 'Markdown ETag returns 304.' );
	}
	$shortcode = runtime_post( array( 'post_content' => '<!-- wp:shortcode -->[llmf_unknown_audit]<!-- /wp:shortcode -->' ) );
	runtime_assert( in_array( 'unresolved_shortcode', $exporter->inspect( get_post( $shortcode ) )['warnings'], true ), 'Unexpanded shortcode is diagnosed.' );
	foreach ( array( $private, $draft, $password, $excluded ) as $id ) {
		runtime_assert( runtime_http( $options->markdown_url_for_post( get_post( $id ) ) )['status'] === 404, 'Protected fixture Markdown returns 404.' );
		runtime_assert( strpos( $index['body'], '](' . $options->markdown_url_for_post( get_post( $id ) ) . ')' ) === false, 'Protected fixture is absent from catalog.' );
	}
	$direct = home_url( '/?' ) . http_build_query( array( 'llmf_catalog' => 1, 'llmf_pt' => 'attachment', 'llmf_after' => '1 OR 1=1' ) );
	$direct_response = runtime_http( $direct );
	runtime_assert( $direct_response['status'] === 404, 'Invalid direct query vars are rejected. Observed: ' . $direct_response['status'] );
	$parent_page = runtime_http( $catalog->url( 'page', '', 0, 0, $parent ) );
	runtime_assert( $parent_page['status'] === 200 && strpos( $parent_page['body'], $options->markdown_url_for_post( get_post( $child ) ) ) !== false, 'Hierarchical parent scope works.' );
	$before = runtime_http( $options->markdown_url_for_post( get_post( $ids[0] ) ) );
	update_post_meta( $ids[0], Options::META_LLMS_DESCRIPTION, 'Metadata only LLMF_FACT_1 updated.' );
	$ims = runtime_http( $options->markdown_url_for_post( get_post( $ids[0] ) ), 'GET', array( 'If-Modified-Since: ' . $before['headers']['last-modified'] ) );
	runtime_assert( $ims['status'] === 200 && strpos( $ims['body'], 'Metadata only' ) !== false && ! isset( $ims['headers']['last-modified'] ), 'IMS-only metadata update is not hidden by Apache/FastCGI.' );
	$negotiated = runtime_http( get_permalink( $ids[0] ), 'GET', array( 'Accept: text/markdown' ) );
	runtime_assert( $negotiated['status'] === 200 && strpos( $negotiated['body'], 'LLMF_FACT_1' ) !== false && stripos( $negotiated['headers']['vary'] ?? '', 'Accept' ) !== false, 'Canonical Markdown negotiation merges Vary.' );
	$catalog_before = runtime_http( $catalog->url( 'post' ) );
	wp_update_post( array( 'ID' => $ids[0], 'post_status' => 'private' ) );
	$catalog_after = runtime_http( $catalog->url( 'post' ), 'GET', array( 'If-None-Match: ' . $catalog_before['headers']['etag'] ) );
	runtime_assert( $catalog_after['status'] === 200 && strpos( $catalog_after['body'], '](' . $options->markdown_url_for_post( get_post( $ids[0] ) ) . ')' ) === false, 'Privacy revokes warmed catalog and old validators in manual mode.' );
	$root_after = runtime_http( home_url( '/llms.txt' ) );
	runtime_assert( strpos( $root_after['body'], '](' . $options->markdown_url_for_post( get_post( $ids[0] ) ) . ')' ) === false, 'Privacy revokes warmed root in manual mode.' );
	$reuse_post = get_post( $ids[9] );
	$reuse_before = runtime_http( $options->markdown_url_for_post( $reuse_post ) );
	wp_update_post( array( 'ID' => $reuse, 'post_content' => '<!-- wp:paragraph --><p>LLMF_FACT_10 changed shared fact.</p><!-- /wp:paragraph -->' ) );
	$reuse_after = runtime_http( $options->markdown_url_for_post( $reuse_post ), 'GET', array( 'If-None-Match: ' . $reuse_before['headers']['etag'] ) );
	runtime_assert( $reuse_after['status'] === 200 && strpos( $reuse_after['body'], 'changed shared fact' ) !== false, 'Reusable edits invalidate dependent Markdown.' );
	update_post_meta( $ids[9], Exporter::META_MD_OVERRIDE, 'Manual LLMF_FACT_10' );
	runtime_assert( Content::override_state( get_post( $ids[9] ) ) === 'current', 'REST/meta override saves record a source fingerprint.' );
	wp_update_post( array( 'ID' => $reuse, 'post_status' => 'private' ) );
	runtime_assert( Content::override_state( get_post( $ids[9] ) ) === 'outdated', 'Reusable privacy change makes override review due.' );
	delete_post_meta( $ids[9], Exporter::META_MD_OVERRIDE );
	runtime_assert( strpos( runtime_http( $options->markdown_url_for_post( get_post( $ids[9] ) ) )['body'], 'changed shared fact' ) === false, 'Hidden reusable content is revoked.' );
	$nested_hidden = runtime_post( array( 'post_content' => '<!-- wp:group --><div class="wp-block-group"><!-- wp:block {"ref":' . $reuse . '} /--></div><!-- /wp:group -->' ) );
	runtime_assert( strpos( $exporter->inspect( get_post( $nested_hidden ) )['markdown'], 'changed shared fact' ) === false, 'A container cannot re-render a reusable child removed by privacy checks.' );
	$diagnostics->start();
	for ( $batch = 0; $batch < 200; $batch ++ ) { $progress = $diagnostics->batch(); if ( $progress['status'] === 'complete' ) { break; } }
	runtime_assert( $progress['status'] === 'complete', 'Coverage completes on the local fixture scope.' );
	$topics = runtime_http( $catalog->url( 'post', 'category' ) );
	runtime_assert( strpos( $topics['body'], $run . ' topic' ) !== false && strpos( $topics['body'], $run . ' empty' ) === false, 'Topic projection lists only nonempty topics.' );
	$topic = runtime_http( $catalog->url( 'post', 'category', $terms[0] ) );
	runtime_assert( $topic['status'] === 200 && strpos( $topic['body'], 'question 2' ) !== false, 'Term ID route returns its sources.' );
	foreach ( $users as $role => $id ) {
		$cookies = runtime_auth( $id );
		$nonce = wp_create_nonce( 'llmf_mechanics' );
		$ajax = runtime_http( admin_url( 'admin-ajax.php' ), 'POST', array(), array( 'action' => 'llmf_compare_modes', 'nonce' => $nonce ), $cookies );
		$json = json_decode( $ajax['body'], true );
		runtime_assert( $role === 'administrator' ? ( $ajax['status'] === 200 && ! empty( $json['success'] ) ) : $ajax['status'] === 403, 'New comparison permission for ' . $role . '. Observed: ' . $ajax['status'] );
		$invalid = runtime_http( admin_url( 'admin-ajax.php' ), 'POST', array(), array( 'action' => 'llmf_diagnostics', 'nonce' => 'invalid' ), $cookies );
		runtime_assert( $invalid['status'] === 403, 'Invalid nonce denied for ' . $role . '.' );
	}
	wp_set_current_user( 0 );
	$_COOKIE = array();
	$anonymous = runtime_http( admin_url( 'admin-ajax.php' ), 'POST', array(), array( 'action' => 'llmf_compare_modes', 'nonce' => 'invalid' ) );
	runtime_assert( $anonymous['status'] >= 400 && empty( json_decode( $anonymous['body'], true )['success'] ), 'Anonymous users cannot call the new comparison.' );
	$options->update( array( 'llms_index_mode' => 'legacy', 'content_profile' => 'legacy' ) );
	runtime_assert( $options->get()['llms_pinned_ids'] === $ids, 'Switchback preserves pin order.' );
	runtime_assert( runtime_http( add_query_arg( array( 'llmf_catalog' => 1, 'llmf_pt' => 'post' ), home_url( '/' ) ) )['status'] === 404, 'Switchback disables direct catalog access immediately.' );
	$old_override_post = runtime_post( array( 'post_content' => '<p>Legacy manual source.</p>' ) );
	$old_override = "# Legacy manual version\n\n```text\nOld manual fact.\n```";
	update_post_meta( $old_override_post, Exporter::META_MD_OVERRIDE, $old_override );
	delete_post_meta( $old_override_post, Content::META_SOURCE_HASH );
	$reactivation_settings = get_option( Options::OPTION_KEY );
	LLMFriendly\Plugin::deactivate();
	LLMFriendly\Plugin::activate();
	LLMFriendly\Plugin::activate();
	runtime_assert( get_option( Options::OPTION_KEY ) === $reactivation_settings, 'Repeated activation hooks preserve all stored settings and mode choices.' );
	runtime_assert( get_post_meta( $old_override_post, Exporter::META_MD_OVERRIDE, true ) === $old_override && Content::override_state( get_post( $old_override_post ) ) === 'unconfirmed', 'Repeated activation does not migrate or confirm a historical override.' );
} catch ( Throwable $error ) {
	$failures[] = 'Runtime exception: ' . get_class( $error ) . '. ' . $error->getMessage();
} finally {
	wp_set_current_user( $users['administrator'] ?? 0 );
	foreach ( $posts as $id ) {
		if ( get_post_meta( $id, '_llmf_test_run', true ) === $run ) { wp_delete_post( $id, true ); }
	}
	foreach ( $terms as $id ) { $term = get_term( $id, 'category' ); if ( $term && strpos( $term->slug, $run ) === 0 ) { wp_delete_term( $id, 'category' ); } }
	if ( ! function_exists( 'wp_delete_user' ) ) { require ABSPATH . 'wp-admin/includes/user.php'; }
	foreach ( $users as $id ) {
		$user = get_userdata( $id );
		if ( $user && strpos( $user->user_login, $run ) === 0 ) {
			if ( class_exists( 'Sherer_Pro_V4\\Security' ) ) {
				$security = new Sherer_Pro_V4\Security();
				delete_transient( $security->recovery_notice_transient_key( $id ) );
				delete_transient( 'sherer_security_2fa_enrollment_notice_' . $id );
			}
			wp_delete_user( $id );
		}
	}
	foreach ( $snapshots as $key => $snapshot ) {
		if ( $key === Options::OPTION_KEY ) {
			// Internal restore, without the Settings API altering the baseline record.
			if ( $snapshot['exists'] ) {
				remove_all_filters( 'sanitize_option_' . $key );
				update_option( $key, $snapshot['value'], false );
			} else { delete_option( $key ); }
		} elseif ( $snapshot['exists'] ) { update_option( $key, $snapshot['value'], false ); } else { delete_option( $key ); }
	}
	$live_cron = _get_cron_array();
	foreach ( $live_cron as $timestamp => &$events ) { foreach ( $hooks as $hook ) { unset( $events[ $hook ] ); } if ( empty( $events ) ) { unset( $live_cron[ $timestamp ] ); } }
	unset( $events );
	foreach ( $cron as $timestamp => $events ) { foreach ( $events as $hook => $event ) { $live_cron[ $timestamp ][ $hook ] = $event; } }
	_set_cron_array( $live_cron );
	wp_set_current_user( $original_user );
	$_COOKIE = $original_cookie;
	// Remove only known plugin transients generated while using the unique test base.
	global $wpdb;
	$transients = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_llmf_cat_' ) . '%', $wpdb->esc_like( '_transient_llmf_md_' ) . '%' ) );
	foreach ( $transients as $transient ) {
		$value = maybe_unserialize( $transient->option_value );
		$own = is_string( $value ) && strpos( $value, $run ) !== false;
		$own = $own || ( is_array( $value ) && ! empty( $value['ids'] ) && array_intersect( $value['ids'], $posts ) );
		if ( $own ) { delete_transient( substr( $transient->option_name, strlen( '_transient_' ) ) ); }
	}
	runtime_assert( count( array_filter( $posts, function ( $id ) { return get_post( $id ) !== null; } ) ) === 0, 'All created fixture posts were removed.' );
	runtime_assert( get_option( Options::OPTION_KEY ) === $snapshots[ Options::OPTION_KEY ]['value'], 'Original plugin settings were restored exactly.' );
	runtime_assert( get_option( 'rewrite_rules' ) === $snapshots['rewrite_rules']['value'], 'Original rewrite rules were restored exactly.' );
	runtime_assert( count( array_filter( $users, function ( $id ) { return (bool) get_userdata( $id ); } ) ) === 0, 'All created fixture users were removed.' );
	runtime_assert( count( array_filter( $terms, function ( $id ) { $term = get_term( $id, 'category' ); return $term && ! is_wp_error( $term ); } ) ) === 0, 'All created fixture terms were removed.' );
	$restored_cron = array();
	foreach ( _get_cron_array() as $timestamp => $events ) { foreach ( $hooks as $hook ) { if ( isset( $events[ $hook ] ) ) { $restored_cron[ $timestamp ][ $hook ] = $events[ $hook ]; } } }
	runtime_assert( $restored_cron === $cron, 'Original plugin cron events were restored exactly.' );
	$cache_keys = array_flip( array( 'llms_cache', 'llms_cache_ts', 'llms_cache_rev', 'llms_cache_hash', 'llms_cache_settings_hash' ) );
	$settings = array_diff_key( $options->get(), $cache_keys );
	if ( ! get_transient( 'llmf_llms_regen_lock' ) ) {
		( new Llms( $options, false ) )->regenerate( true );
		runtime_assert( array_diff_key( $options->get(), $cache_keys ) === $settings, 'Post-test cache refresh preserves every user setting.' );
	}
	$clean_root = runtime_http( home_url( '/llms.txt' ) );
	runtime_assert( $clean_root['status'] === 200 && strpos( $clean_root['body'], $run ) === false, 'Restored public root is available without fixture content.' );
}
echo 'WordPress ' . get_bloginfo( 'version' ) . ' / PHP ' . PHP_VERSION . ': ' . $checks . ' checks; ' . count( $failures ) . " failures.\n";
foreach ( $failures as $failure ) { echo 'FAIL: ' . $failure . "\n"; }
exit( empty( $failures ) ? 0 : 1 );
