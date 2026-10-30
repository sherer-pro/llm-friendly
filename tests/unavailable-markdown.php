<?php
/** Independent provider contract; no site-specific plugin or status. */
$unavailable_options_before = $GLOBALS['llmf_test_options'];
$unavailable_posts_before = $GLOBALS['llmf_test_posts'];
$unavailable_options = new \LLMFriendly\Options();
$GLOBALS['llmf_test_options'][ \LLMFriendly\Options::OPTION_KEY ] = $unavailable_options->defaults();
$unavailable = new \LLMFriendly\UnavailableMarkdown( $unavailable_options );
$unavailable_context = array( 'request_url' => '/previous-base/post/old.md', 'post_type' => '', 'path' => '', 'source_post_id' => 0 );
assert_true( null === $unavailable->resolve( $unavailable_context ), 'No provider preserves existing behavior.' );
$unavailable_value = null;
$unavailable_provider = static function ( $default, $context ) use ( &$unavailable_value, $unavailable_context ) {
	assert_true( $default === null && $context === $unavailable_context, 'Provider receives the generic unavailable request context.' );
	assert_true( get_current_user_id() === 0, 'Provider and destination validation run anonymously.' );
	return $unavailable_value;
};
add_filter( 'llmf_resolve_unavailable_markdown', $unavailable_provider, 10, 2 );
$GLOBALS['llmf_test_posts'][901] = new WP_Post( array( 'ID' => 901, 'post_name' => 'replacement', 'post_title' => 'Replacement', 'post_status' => 'publish', 'post_type' => 'post' ) );
$unavailable_value = array( 'status' => 301, 'target_post_id' => 901 );
assert_true( $unavailable->resolve( $unavailable_context ) === array( 'status' => 301, 'url' => 'https://example.test/llm/post/replacement.md' ), 'A public export receives a Markdown destination.' );
$GLOBALS['llmf_test_options'][ \LLMFriendly\Options::OPTION_KEY ]['enabled_markdown'] = 0;
assert_true( $unavailable->resolve( $unavailable_context )['url'] === 'https://example.test/post/replacement/', 'Disabled Markdown falls back to public HTML.' );
$GLOBALS['llmf_test_options'][ \LLMFriendly\Options::OPTION_KEY ]['enabled_markdown'] = 1;
$GLOBALS['llmf_test_options'][ \LLMFriendly\Options::OPTION_KEY ]['excluded_posts'] = array( 'post' => array(901) );
assert_true( $unavailable->resolve( $unavailable_context )['url'] === 'https://example.test/post/replacement/', 'Excluded Markdown falls back to public HTML.' );
foreach ( array( 'draft', 'private', 'future', 'trash', 'custom_hidden' ) as $status ) {
	$GLOBALS['llmf_test_posts'][901]->post_status = $status;
	assert_true( null === $unavailable->resolve( $unavailable_context ), 'Nonpublic destination rejected: ' . $status );
}
$GLOBALS['llmf_test_posts'][901]->post_status = 'publish';
$GLOBALS['llmf_test_posts'][901]->post_password = 'protected';
assert_true( null === $unavailable->resolve( $unavailable_context ), 'Password-protected destination rejected.' );
$GLOBALS['llmf_test_posts'][901]->post_password = '';
foreach ( array( true, 'https://example.test/', array( 'status' => 302, 'target_post_id' => 901 ), array( 'status' => 301, 'target_post_id' => '901' ), array( 'status' => 301, 'target_post_id' => 999999 ) ) as $invalid ) {
	$unavailable_value = $invalid;
	assert_true( null === $unavailable->resolve( $unavailable_context ), 'Malformed or unavailable destination ignored.' );
}
$unavailable_value = array( 'status' => 410 );
assert_true( $unavailable->resolve( $unavailable_context ) === array( 'status' => 410 ), 'Provider can withdraw an arbitrary unavailable representation.' );
$unavailable_value = array( 'status' => 301, 'target_post_id' => 901 );
$GLOBALS['llmf_test_options'][ \LLMFriendly\Options::OPTION_KEY ]['enabled_markdown'] = 0;
foreach ( array( 'http://example.test/replacement/', 'https://other.test/replacement/', 'https://user:pass@example.test/replacement/', 'https://example.test/previous-base/post/old.md' ) as $unsafe_url ) {
	$permalink = static function () use ( $unsafe_url ) { return $unsafe_url; };
	add_filter( 'post_link', $permalink );
	assert_true( $unavailable->resolve( $unavailable_context ) === null, 'Unsafe HTML destination rejected: ' . $unsafe_url );
	remove_filter( 'post_link', $permalink );
}
remove_filter( 'llmf_resolve_unavailable_markdown', $unavailable_provider );

// Subdirectory boundaries must survive browser URL normalization.
$subsite = static function ( $url, $path ) { return 'https://example.test/site' . $path; };
add_filter( 'home_url', $subsite, 10, 2 );
add_filter( 'llmf_resolve_unavailable_markdown', $unavailable_provider, 10, 2 );
foreach ( array( 'https://example.test/outside/', 'https://example.test/site/../outside/', 'https://example.test/site/%2e%2e/outside/', 'https://example.test/site/%5c../outside/' ) as $unsafe_url ) {
	$permalink = static function () use ( $unsafe_url ) { return $unsafe_url; };
	add_filter( 'post_link', $permalink );
	assert_true( $unavailable->resolve( $unavailable_context ) === null, 'Subdirectory escape rejected: ' . $unsafe_url );
	remove_filter( 'post_link', $permalink );
}
$permalink = static function () { return 'https://example.test/site/replacement/'; };
add_filter( 'post_link', $permalink );
assert_true( $unavailable->resolve( $unavailable_context )['url'] === 'https://example.test/site/replacement/', 'A public destination within the WordPress subdirectory remains usable.' );
remove_filter( 'post_link', $permalink );
remove_filter( 'home_url', $subsite );
remove_filter( 'llmf_resolve_unavailable_markdown', $unavailable_provider );

// A privacy change during generation must not republish the just-invalidated cache.
foreach ( array( 'legacy', 'structured' ) as $index_mode ) {
	$GLOBALS['llmf_test_options'][ \LLMFriendly\Options::OPTION_KEY ] = array_merge( $unavailable_options->defaults(), array( 'llms_index_mode' => $index_mode, 'llms_regen_mode' => 'manual', 'llms_pinned_ids' => array( 901 ) ) );
	$GLOBALS['llmf_test_posts'] = array( 901 => new WP_Post( array( 'ID' => 901, 'post_name' => 'withdrawn-during-build', 'post_title' => 'PRIVATE_RACE_MARKER' ) ) );
	$root = new \LLMFriendly\Llms( $unavailable_options, false );
	$changed = false;
	$privacy_change = static function ( $title, $id ) use ( &$changed, $root ) {
		if ( $id === 901 && ! $changed ) {
			$changed = true;
			$GLOBALS['llmf_test_posts'][901]->post_status = 'private';
			$root->maybe_regenerate_on_status_change( 'private', 'publish', $GLOBALS['llmf_test_posts'][901] );
			( new \LLMFriendly\Catalog( new \LLMFriendly\Options(), false ) )->invalidate();
		}
		return $title;
	};
	add_filter( 'the_title', $privacy_change, 10, 2 );
	assert_true( ! $root->regenerate( true ) && $changed, 'Concurrent privacy change aborts stale root publication: ' . $index_mode );
	assert_not_contains_text( 'PRIVATE_RACE_MARKER', $unavailable_options->get()['llms_cache'], 'Invalidated root stays free of private content: ' . $index_mode );
	remove_filter( 'the_title', $privacy_change );
	$GLOBALS['llmf_test_posts'][901]->post_status = 'publish';
	assert_true( $root->regenerate( true ), 'A clean regeneration recovers after an interrupted build: ' . $index_mode );
	$GLOBALS['llmf_test_posts'][901]->post_status = 'private';
	assert_true( ! call_private( $root, 'cached_root_is_public' ), 'Cached root revalidates current visibility in both modes: ' . $index_mode );
}
$GLOBALS['llmf_test_options'] = $unavailable_options_before;
$GLOBALS['llmf_test_posts'] = $unavailable_posts_before;
