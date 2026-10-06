<?php
/** Real SQL cursor test. Only an isolated, loopback site with an llmf_ table prefix. */
if ( getenv( 'LLMF_TEST_ALLOW_WRITES' ) !== '1' || ! getenv( 'LLMF_WP_ROOT' ) ) {
	fwrite( STDERR, "Set LLMF_WP_ROOT and LLMF_TEST_ALLOW_WRITES=1 for an isolated site.\n" );
	exit( 2 );
}
require rtrim( getenv( 'LLMF_WP_ROOT' ), '/\\' ) . '/wp-load.php';
global $wpdb, $table_prefix;
if ( ! class_exists( 'LLMFriendly\\Catalog' ) || strpos( $table_prefix, 'llmf_' ) !== 0 || ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( '127.0.0.1', 'localhost', '::1' ), true ) ) {
	fwrite( STDERR, "This test requires an isolated llmf_ database prefix and a loopback site.\n" );
	exit( 2 );
}
register_post_type( 'llmf_scale', array( 'public' => true, 'publicly_queryable' => true, 'label' => 'Scale fixtures' ) );
$run = 'llmf-scale-' . strtolower( wp_generate_password( 10, false, false ) ) . '-';
$ids = array();
$failures = array();
$queries = array();
$denial = null;
$trace = null;
try {
	// No existing rows, user settings or plugin jobs are edited. Empty text fields
	// are explicit so this also works with strict SQL modes and the WP 6.0 schema.
	$date = gmdate( 'Y-m-d H:i:s' );
	for ( $offset = 0; $offset < 10000; $offset += 250 ) {
		$rows = array();
		for ( $number = $offset + 1; $number <= $offset + 250; $number ++ ) {
			$rows[] = $wpdb->prepare( '(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)', $date, $date, $date, $date, '<p>Synthetic public fact.</p>', 'Scale fixture ' . $number, $run . $number, '', 'publish', 'closed', 'closed', 'llmf_scale', '', '', '', '' );
		}
		$inserted = $wpdb->query( "INSERT INTO {$wpdb->posts} (post_date,post_date_gmt,post_modified,post_modified_gmt,post_content,post_title,post_name,post_excerpt,post_status,comment_status,ping_status,post_type,to_ping,pinged,post_content_filtered,guid) VALUES " . implode( ',', $rows ) );
		if ( $inserted !== 250 ) { throw new RuntimeException( 'Synthetic batch insertion failed.' ); }
	}
	$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type=%s AND post_name LIKE %s ORDER BY ID", 'llmf_scale', $wpdb->esc_like( $run ) . '%' ) ) );
	if ( count( $ids ) !== 10000 ) { throw new RuntimeException( 'Incomplete synthetic archive.' ); }
	$expected = array();
	foreach ( $ids as $index => $id ) { if ( $index >= 100 && ( $index + 1 ) % 13 !== 0 ) { $expected[] = $id; } }
	$denial = function ( $allowed, $post, $context ) use ( $run ) {
		if ( $post->post_type !== 'llmf_scale' || strpos( $post->post_name, $run ) !== 0 ) { return false; }
		$number = (int) substr( $post->post_name, strlen( $run ) );
		return $allowed && $number > 100 && $number % 13 !== 0;
	};
	$trace = function ( $sql, $query ) use ( &$queries ) {
		if ( $query->get( 'post_type' ) === 'llmf_scale' ) { $queries[] = $sql; }
		return $sql;
	};
	add_filter( 'llmf_can_export_post', $denial, 10, 3 );
	add_filter( 'posts_request', $trace, 10, 2 );
	$settings = ( new LLMFriendly\Options() )->candidate( array( 'post_types' => array( 'llmf_scale' ), 'llms_index_mode' => 'structured', 'content_profile' => 'enhanced' ) );
	$catalog = new LLMFriendly\Catalog( $settings, false );
	$after = $ids[0] - 1;
	$found = array();
	$pages = 0;
	do {
		$page = LLMFriendly\Content::anonymous( function () use ( $catalog, $after ) { return $catalog->scan( 'llmf_scale', $after ); } );
		$pages ++;
		if ( $page['scanned'] > 100 || $pages > 101 ) { throw new RuntimeException( 'Cursor or query bound exceeded.' ); }
		if ( $pages === 1 && ( ! empty( $page['posts'] ) || $page['next'] === 0 ) ) { throw new RuntimeException( 'A rejected page failed to advance.' ); }
		foreach ( $page['posts'] as $post ) { $found[] = (int) $post->ID; }
		if ( $page['next'] !== 0 && $page['next'] <= $after ) { throw new RuntimeException( 'Non-increasing cursor.' ); }
		$after = $page['next'];
	} while ( $after !== 0 );
	if ( $found !== $expected || count( array_unique( $found ) ) !== count( $found ) ) { throw new RuntimeException( 'Skipped or duplicate eligible records.' ); }
	foreach ( $queries as $sql ) {
		if ( strpos( $sql, 'SQL_CALC_FOUND_ROWS' ) !== false || ! preg_match( '/LIMIT\s+0,\s*100\b/i', $sql ) ) { throw new RuntimeException( 'Unbounded or count-based catalog query.' ); }
	}
	if ( count( $queries ) !== 101 ) { throw new RuntimeException( 'Unexpected query count.' ); }
	echo 'WordPress ' . get_bloginfo( 'version' ) . ' / PHP ' . PHP_VERSION . ': 10,000 real SQL fixtures, 101 bounded pages, ' . count( $found ) . ' eligible records, no gaps or duplicates.' . PHP_EOL;
} catch ( Throwable $error ) {
	$failures[] = $error->getMessage();
} finally {
	if ( $denial ) { remove_filter( 'llmf_can_export_post', $denial, 10 ); }
	if ( $trace ) { remove_filter( 'posts_request', $trace, 10 ); }
	// The unique marker and type are checked together; no other rows are targeted.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->posts} WHERE post_type=%s AND post_name LIKE %s", 'llmf_scale', $wpdb->esc_like( $run ) . '%' ) );
	foreach ( $ids as $id ) { clean_post_cache( $id ); }
	$remaining = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=%s AND post_name LIKE %s", 'llmf_scale', $wpdb->esc_like( $run ) . '%' ) );
	if ( $remaining !== 0 ) { $failures[] = 'Synthetic archive cleanup failed.'; }
	echo 'Synthetic archive cleanup: ' . ( $remaining === 0 ? 'complete' : 'failed' ) . PHP_EOL;
}
foreach ( $failures as $failure ) { echo 'FAIL: ' . $failure . PHP_EOL; }
exit( empty( $failures ) ? 0 : 1 );
