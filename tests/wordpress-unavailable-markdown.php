<?php
/** Local HTTP acceptance. LLMF_WP_ROOT and LLMF_TEST_ALLOW_WRITES=1 are required. */
if ( getenv( 'LLMF_TEST_ALLOW_WRITES' ) !== '1' || ! getenv( 'LLMF_WP_ROOT' ) ) {
	fwrite( STDERR, "Set LLMF_WP_ROOT and LLMF_TEST_ALLOW_WRITES=1 for a local site.\n" );
	exit( 2 );
}
require rtrim( getenv( 'LLMF_WP_ROOT' ), '/\\' ) . '/wp-load.php';
if ( ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'sherer.loc', 'localhost', '127.0.0.1', '::1' ), true ) || ! class_exists( 'LLMFriendly\\UnavailableMarkdown' ) ) {
	fwrite( STDERR, "A local WordPress with LLM Friendly activated is required.\n" );
	exit( 2 );
}

$options = new LLMFriendly\Options();
$run = 'llmf-http-' . strtolower( wp_generate_password( 10, false, false ) );
$key = $run . '-provider';
$provider_file = WPMU_PLUGIN_DIR . '/' . $run . '.php';
$posts = array();
$failures = array();
$checks = 0;
$snapshot = array();
$hooks = array( 'llmf_catalog_scan', 'llmf_regenerate_llms_cache' );
global $wpdb;
$rows = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name = 'llmf_options' OR option_name = 'rewrite_rules' OR option_name LIKE 'llmf\_%' OR option_name LIKE '\_transient\_llmf\_%' OR option_name LIKE '\_transient\_timeout\_llmf\_%'" );
foreach ( $rows as $row ) { $snapshot[ $row->option_name ] = maybe_unserialize( $row->option_value ); }
$cron = array();
foreach ( _get_cron_array() as $time => $events ) { foreach ( $hooks as $hook ) { if ( isset( $events[ $hook ] ) ) { $cron[ $time ][ $hook ] = $events[ $hook ]; } } }
function unavailable_assert( bool $ok, string $message ): void {
	$GLOBALS['checks'] ++;
	if ( ! $ok ) { $GLOBALS['failures'][] = $message; }
}
function unavailable_http( string $url, string $method = 'GET', array $headers = array() ): array {
	$received = array();
	$curl = curl_init( $url );
	$args = array( CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_HTTPHEADER => $headers, CURLOPT_HEADERFUNCTION => function ( $handle, $line ) use ( &$received ) {
		if ( strpos( $line, ':' ) !== false ) { list( $name, $value ) = explode( ':', $line, 2 ); $received[ strtolower( trim( $name ) ) ] = trim( $value ); }
		return strlen( $line );
	} );
	if ( $method === 'HEAD' ) { $args[ CURLOPT_NOBODY ] = true; }
	if ( $method === 'POST' ) { $args[ CURLOPT_POST ] = true; }
	if ( getenv( 'LLMF_HTTP_RESOLVE' ) ) { $args[ CURLOPT_RESOLVE ] = array( getenv( 'LLMF_HTTP_RESOLVE' ) ); }
	curl_setopt_array( $curl, $args );
	$body = curl_exec( $curl );
	$result = array( 'status' => (int) curl_getinfo( $curl, CURLINFO_HTTP_CODE ), 'headers' => $received, 'body' => is_string( $body ) ? $body : '', 'error' => curl_errno( $curl ) );
	curl_close( $curl );
	return $result;
}
function unavailable_case( string $key, string $path, $value, array $extra = array() ): void {
	update_option( $key, array_merge( array( 'path' => wp_parse_url( $path, PHP_URL_PATH ), 'value' => $value ), $extra ), false );
}
try {
	foreach ( array( 'publish', 'draft', 'private', 'future', 'trash' ) as $status ) {
		$id = wp_insert_post( array( 'post_status' => $status, 'post_type' => 'post', 'post_title' => $run . ' ' . $status, 'post_name' => $run . '-' . $status, 'post_content' => '<p>' . $run . ' public fact</p>', 'post_date' => $status === 'future' ? gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) : current_time( 'mysql' ) ), true );
		if ( is_wp_error( $id ) ) { throw new RuntimeException( 'Cannot create fixture.' ); }
		$posts[ $status ] = (int) $id;
	}
	$target = $posts['publish'];
	$options->update( array( 'base_path' => $run, 'post_types' => array( 'post', 'page' ), 'enabled_markdown' => 1, 'enabled_llms_txt' => 1, 'llms_regen_mode' => 'manual' ) );
	( new LLMFriendly\Rewrites( $options ) )->add_rules();
	flush_rewrite_rules( false );
	$old = home_url( '/historic-' . $run . '/old.md' );
	$missing = unavailable_http( $old );
	unavailable_assert( $missing['status'] === 404, 'Without a provider, an unknown historical path remains 404.' );
	$md = $options->markdown_url_for_post( get_post( $target ) );
	$valid = unavailable_http( $md );
	unavailable_assert( $valid['status'] === 200 && strpos( $valid['body'], $run . ' public fact' ) !== false, 'Standalone Markdown works.' );
	if ( ! is_dir( WPMU_PLUGIN_DIR ) ) { wp_mkdir_p( WPMU_PLUGIN_DIR ); }
	$provider = '<?php' . "\n" . '$key = ' . var_export( $key, true ) . ';' . <<<'PHP'

// Temporary local acceptance provider. Only the exact configured fixture path is handled.
add_filter( 'llmf_resolve_unavailable_markdown', static function ( $value, $context ) use ( $key ) {
	wp_cache_delete( $key, 'options' );
	$config = get_option( $key );
	if ( ! is_array( $config ) || wp_parse_url( $context['request_url'], PHP_URL_PATH ) !== $config['path'] ) { return $value; }
	$config['context'] = $context;
	$config['anonymous'] = get_current_user_id() === 0;
	update_option( $key, $config, false );
	return $config['value'];
}, 10, 2 );
add_filter( 'post_link', static function ( $url, $post ) use ( $key ) {
	$config = get_option( $key );
	return is_array( $config ) && isset( $config['url'] ) && $post->ID === $config['target'] ? $config['url'] : $url;
}, 100, 2 );
PHP;
	if ( file_put_contents( $provider_file, $provider ) === false ) { throw new RuntimeException( 'Cannot create temporary provider.' ); }
	$redirect = array( 'status' => 301, 'target_post_id' => $target );
	foreach ( array( $old, home_url( '/' . $run . '/post/missing.md' ), home_url( '/' . $run . '/blog/missing.md' ), home_url( '/historic-' . $run . '/%D1%81%D1%82%D0%B0%D1%80%D0%BE%D0%B5.md?utm_source=fixture' ) ) as $url ) {
		unavailable_case( $key, $url, $redirect );
		foreach ( array( 'GET', 'HEAD' ) as $method ) {
			$response = unavailable_http( $url, $method, array( 'If-None-Match: ' . ( $valid['headers']['etag'] ?? '"old"' ), 'If-Modified-Since: Wed, 31 Dec 2036 23:59:59 GMT' ) );
			unavailable_assert( $response['status'] === 301 && ( $response['headers']['location'] ?? '' ) === $md, 'Historical/current/legacy/encoded path redirects to current Markdown: ' . $method );
			unavailable_assert( strpos( $response['headers']['cache-control'] ?? '', 'no-store' ) !== false && $response['body'] === '', 'Redirect has no body and cannot be cached: ' . $method );
		}
	}
	wp_cache_delete( $key, 'options' );
	$config = get_option( $key );
	unavailable_assert( ! empty( $config['anonymous'] ) && isset( $config['context']['post_type'], $config['context']['path'], $config['context']['source_post_id'] ), 'Provider receives neutral context anonymously.' );
	unavailable_case( $key, $md, array( 'status' => 410 ) );
	unavailable_assert( unavailable_http( $md )['status'] === 200 && empty( get_option( $key )['context'] ), 'Available Markdown bypasses the provider.' );
	unavailable_case( $key, $old, array( 'status' => 410 ) );
	foreach ( array( 'GET', 'HEAD' ) as $method ) {
		$response = unavailable_http( $old, $method, array( 'If-None-Match: *', 'If-Modified-Since: Wed, 31 Dec 2036 23:59:59 GMT' ) );
		unavailable_assert( $response['status'] === 410 && strpos( $response['body'], $run ) === false && ( $method !== 'HEAD' || $response['body'] === '' ), 'Withdrawal ignores stale validators and does not disclose source content: ' . $method );
		unavailable_assert( strpos( $response['headers']['cache-control'] ?? '', 'no-store' ) !== false && ( $response['headers']['x-content-type-options'] ?? '' ) === 'nosniff' && ( $response['headers']['x-robots-tag'] ?? '' ) === 'noindex', 'Withdrawal headers are safe: ' . $method );
	}
	unavailable_assert( unavailable_http( $old, 'POST' )['status'] !== 410, 'POST cannot activate the provider.' );
	foreach ( array( null, true, 'url', array( 'status' => '301', 'target_post_id' => $target ), array( 'status' => 302, 'target_post_id' => $target ), array( 'status' => 301, 'target_post_id' => (string) $target ), array( 'status' => 301, 'target_post_id' => 0 ) ) as $bad ) {
		unavailable_case( $key, $old, $bad );
		unavailable_assert( unavailable_http( $old )['status'] === 404, 'Invalid provider value is ignored.' );
	}
	foreach ( $posts as $status => $id ) {
		if ( $status === 'publish' ) { continue; }
		unavailable_case( $key, $old, array( 'status' => 301, 'target_post_id' => $id ) );
		unavailable_assert( unavailable_http( $old )['status'] === 404, 'Forbidden destination rejected: ' . $status );
	}
	wp_update_post( array( 'ID' => $target, 'post_password' => 'synthetic-fixture-only' ) );
	unavailable_case( $key, $old, $redirect );
	unavailable_assert( unavailable_http( $old )['status'] === 404, 'Password-protected destination rejected.' );
	wp_update_post( array( 'ID' => $target, 'post_password' => '' ) );
	$options->update( array( 'excluded_posts' => array( 'post' => array( $target ) ) ) );
	$response = unavailable_http( $old );
	unavailable_assert( $response['status'] === 301 && ( $response['headers']['location'] ?? '' ) === get_permalink( $target ), 'Excluded Markdown falls back to public HTML.' );
	$options->update( array( 'enabled_markdown' => 0, 'excluded_posts' => array() ) );
	$response = unavailable_http( $old );
	unavailable_assert( $response['status'] === 301 && ( $response['headers']['location'] ?? '' ) === get_permalink( $target ), 'Disabled Markdown falls back to public HTML.' );
	$unsafe = array( 'external' => 'https://example.invalid/escape/', 'credentials' => str_replace( '://', '://user:pass@', home_url( '/escape/' ) ), 'self' => $old );
	if ( wp_parse_url( home_url(), PHP_URL_SCHEME ) === 'https' ) { $unsafe['scheme downgrade'] = preg_replace( '~^https:~', 'http:', home_url( '/escape/' ) ); }
	foreach ( $unsafe as $label => $url ) {
		unavailable_case( $key, $old, $redirect, array( 'target' => $target, 'url' => $url ) );
		unavailable_assert( unavailable_http( $old )['status'] === 404, 'Unsafe HTML redirect rejected: ' . $label );
	}
	foreach ( array( 'legacy', 'structured' ) as $mode ) {
		wp_update_post( array( 'ID' => $target, 'post_status' => 'publish' ) );
		$options->update( array( 'llms_index_mode' => $mode, 'llms_pinned_ids' => array( $target ), 'llms_recent_limit' => 200 ) );
		$changed = false;
		$privacy = static function ( $title, $id ) use ( $target, &$changed ) {
			if ( (int) $id === $target && ! $changed ) { $changed = true; wp_update_post( array( 'ID' => $target, 'post_status' => 'private' ) ); }
			return $title;
		};
		add_filter( 'the_title', $privacy, 10, 2 );
		$published = ( new LLMFriendly\Llms( $options, false ) )->regenerate( true );
		remove_filter( 'the_title', $privacy, 10 );
		unavailable_assert( $changed && ! $published, 'Privacy change during generation aborts stale publication: ' . $mode );
		$root = unavailable_http( home_url( '/llms.txt' ) );
		unavailable_assert( $root['status'] === 200 && strpos( $root['body'], $run . ' publish' ) === false, 'Public root excludes content withdrawn during generation: ' . $mode );
	}
} catch ( Throwable $error ) {
	$failures[] = get_class( $error ) . ': ' . $error->getMessage();
} finally {
	if ( is_file( $provider_file ) ) { unlink( $provider_file ); }
	delete_option( $key );
	foreach ( $posts as $id ) { wp_delete_post( $id, true ); }
	$live = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name = 'llmf_options' OR option_name = 'rewrite_rules' OR option_name LIKE 'llmf\_%' OR option_name LIKE '\_transient\_llmf\_%' OR option_name LIKE '\_transient\_timeout\_llmf\_%'" );
	foreach ( $live as $name ) { if ( ! array_key_exists( $name, $snapshot ) ) { delete_option( $name ); } }
	foreach ( $snapshot as $name => $value ) { update_option( $name, $value ); }
	$live_cron = _get_cron_array();
	foreach ( $live_cron as $time => &$events ) { foreach ( $hooks as $hook ) { unset( $events[ $hook ] ); } if ( empty( $events ) ) { unset( $live_cron[ $time ] ); } }
	unset( $events );
	foreach ( $cron as $time => $events ) { foreach ( $events as $hook => $event ) { $live_cron[ $time ][ $hook ] = $event; } }
	_set_cron_array( $live_cron );
	unavailable_assert( ! is_file( $provider_file ) && get_option( $key ) === false, 'Temporary provider and its state removed.' );
	unavailable_assert( count( array_filter( $posts, function ( $id ) { return get_post( $id ) !== null; } ) ) === 0, 'All fixture posts removed.' );
	unavailable_assert( get_option( 'llmf_options' ) === ( $snapshot['llmf_options'] ?? false ) && get_option( 'rewrite_rules' ) === ( $snapshot['rewrite_rules'] ?? false ), 'Original settings and rewrites restored.' );
}
echo 'Unavailable HTTP / WordPress ' . get_bloginfo( 'version' ) . ' / PHP ' . PHP_VERSION . ': ' . $checks . ' checks; ' . count( $failures ) . " failures.\n";
foreach ( $failures as $failure ) { echo 'FAIL: ' . $failure . "\n"; }
exit( empty( $failures ) ? 0 : 1 );
