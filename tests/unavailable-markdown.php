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
remove_filter( 'llmf_resolve_unavailable_markdown', $unavailable_provider );
$GLOBALS['llmf_test_options'] = $unavailable_options_before;
$GLOBALS['llmf_test_posts'] = $unavailable_posts_before;
