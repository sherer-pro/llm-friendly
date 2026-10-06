<?php
/** Integration of opt-in modes, bounded catalog walks and private cache revocation. */
use LLMFriendly\Catalog;
use LLMFriendly\Content;
use LLMFriendly\Diagnostics;
use LLMFriendly\Options;
use LLMFriendly\Llms;
use LLMFriendly\Exporter;

$GLOBALS['llmf_test_user_id'] = 0;
$GLOBALS['llmf_test_options'][ Options::OPTION_KEY ] = array( 'base_path' => 'custom', 'post_types' => array( 'post', 'page' ), 'llms_essential_links' => 'Docs | /docs | Kept' );
assert_true( $options->get()['llms_index_mode'] === 'legacy' && $options->get()['content_profile'] === 'legacy', 'Existing installations default to both legacy modes without a migration write.' );
$baseline = $GLOBALS['llmf_test_options'];
$candidate = $options->candidate( array( 'llms_index_mode' => 'structured', 'content_profile' => 'enhanced', 'llms_pinned_ids' => '8,3,8,-1,2e2,3', 'base_path' => '../new' ) );
assert_true( $GLOBALS['llmf_test_options'] === $baseline, 'Candidate validation never changes options, caches or rewrite transients.' );
assert_true( $candidate->get()['llms_pinned_ids'] === array( 8, 3 ), 'Pinned IDs preserve order and reject malformed and duplicate values.' );
assert_true( $candidate->get()['enabled_markdown'] === 1 && $candidate->get()['llms_send_noindex'] === 1, 'A partial candidate retains existing checkbox values.' );
assert_true( $options->sanitize( array( 'content_profile' => 'enhanced' ) )['enabled_markdown'] === 1, 'Partial Settings API updates preserve absent checkboxes.' );
assert_true( $options->sanitize_pinned_ids( range( 1, 200 ) ) === range( 1, 100 ), 'Pinned storage has a hard 100-item cap.' );
assert_true( $candidate->candidate( array( 'llms_index_mode' => 'legacy', 'content_profile' => 'legacy' ) )->get()['llms_pinned_ids'] === array( 8, 3 ), 'Switching back preserves curated IDs.' );
assert_true( $candidate->get()['llms_essential_links'] === 'Docs | /docs | Kept', 'Legacy Essential strings are never converted or rewritten.' );
assert_true( $options->candidate( array( 'llms_index_mode' => array( 'structured' ), 'content_profile' => 'bad' ) )->get()['content_profile'] === 'legacy', 'Malformed profile inputs select safe defaults.' );

$GLOBALS['llmf_test_posts'] = array();
$GLOBALS['llmf_test_meta'] = array();
$GLOBALS['llmf_test_options'][ Options::OPTION_KEY ] = array_merge( $options->defaults(), array( 'llms_index_mode' => 'structured', 'content_profile' => 'enhanced', 'post_types' => array( 'post', 'page' ), 'llms_pinned_ids' => array( 3, 1, 7 ), 'excluded_posts' => array( 'post' => array( 7 ) ) ) );
for ( $id = 1; $id <= 8; $id ++ ) {
	$GLOBALS['llmf_test_posts'][ $id ] = new WP_Post( array( 'ID' => $id, 'post_title' => 'Public ' . $id, 'post_name' => 'public-' . $id, 'post_content' => '<p>Evidence ' . $id . '</p>', 'post_modified_gmt' => $id === 1 ? '2026-10-05 00:00:00' : '2026-01-01 00:00:00' ) );
}
$GLOBALS['llmf_test_posts'][4]->post_status = 'draft';
$GLOBALS['llmf_test_posts'][5]->post_status = 'private';
$GLOBALS['llmf_test_posts'][6]->post_password = 'synthetic';
$GLOBALS['llmf_test_posts'][8]->post_type = 'attachment';
$GLOBALS['llmf_test_posts'][9] = new WP_Post( array( 'ID' => 9, 'post_type' => 'page', 'post_name' => 'parent', 'post_title' => 'Parent' ) );
$GLOBALS['llmf_test_posts'][10] = new WP_Post( array( 'ID' => 10, 'post_type' => 'page', 'post_parent' => 9, 'post_name' => 'child', 'post_title' => 'Child' ) );
$GLOBALS['llmf_test_posts'][11] = new WP_Post( array( 'ID' => 11, 'post_type' => 'private_type', 'post_name' => 'closed', 'post_title' => 'Closed type' ) );
$GLOBALS['llmf_test_parse_blocks_callback'] = function ( $html ) { return array( array( 'blockName' => null, 'innerHTML' => $html, 'innerContent' => array( $html ) ) ); };
$catalog = new Catalog( $options, false );
$diagnostics = new Diagnostics( $options, $catalog, new Exporter( $options ), false );
assert_true( array_map( function ( $post ) { return $post->ID; }, $catalog->pinned() ) === array( 3, 1 ), 'Pins are fetched in owner order, excluding hidden and excluded items.' );
assert_true( $catalog->recent( 'post' )[0]->ID === 1, 'Structured recent lists prefer modified time over insertion/publication order.' );
$page = $catalog->scan( 'post' );
assert_true( array_map( function ( $post ) { return $post->ID; }, $page['posts'] ) === array( 1, 2, 3 ), 'Catalog eligibility excludes drafts, private, passwords, attachments and exclusions.' );
assert_true( empty( $GLOBALS['llmf_test_filters']['posts_where'] ), 'Cursor SQL filters are removed after catalog queries.' );
assert_true( $catalog->taxonomies( 'post' ) === array( 'category' ), 'Category is the default public taxonomy when available.' );
assert_true( $catalog->taxonomies( 'page' ) === array(), 'A type without category does not gain a fake topic index.' );
assert_true( $options->candidate( array( 'catalog_taxonomies' => array( 'post' => array( 'hidden_tax', 'post_tag', array( 'bad' ) ) ) ) )->get()['catalog_taxonomies']['post'] === array( 'post_tag' ), 'Taxonomy configuration rejects private and malformed choices.' );
foreach ( array( array( 'type' => 'attachment' ), array( 'type' => 'private_type' ), array( 'type' => '../post' ), array( 'type' => array( 'post' ) ), array( 'type' => 'post', 'after' => '-1' ), array( 'type' => 'post', 'after' => '1 OR 1=1' ), array( 'type' => 'post', 'after' => '2e3' ), array( 'type' => 'post', 'taxonomy' => 'hidden_tax' ), array( 'type' => 'post', 'term' => '99' ), array( 'type' => 'post', 'parent' => '9' ), array( 'type' => 'page', 'parent' => '999' ) ) as $request ) {
	assert_true( $catalog->request( $request ) === null, 'Direct catalog query variables reject invalid scopes: ' . json_encode( $request ) );
}
$parent_request = $catalog->request( array( 'type' => 'page', 'parent' => '9' ) );
assert_true( $parent_request !== null, 'A public hierarchical parent can scope its children.' );
assert_contains_text( 'Child', $catalog->document( $parent_request ), 'Hierarchical child catalog is reachable.' );
assert_not_contains_text( '- [Parent]', $catalog->document( $parent_request ), 'Parent-scoped catalogs contain only direct children.' );
$root = ( new Llms( $options, false ) )->preview()['content'];
assert_contains_text( '/llm/catalog/index.txt', $root, 'Structured root links to the complete catalog.' );
assert_contains_text( '/llm/catalog/essential.txt', $root, 'Structured root exposes the full pin list.' );
assert_true( strpos( $root, '## Main links' ) < strpos( $root, '## Essential' ) && strpos( $root, '## Essential' ) < strpos( $root, '## Posts' ), 'Structured root retains the documented section order.' );
assert_occurrences( '](' . $options->markdown_url_for_post( $GLOBALS['llmf_test_posts'][1] ) . ')', $root, 1, 'Automatically pinned and recent links are deduplicated in the short map.' );
foreach ( array( 'Public 4', 'Public 5', 'Public 6', 'Public 7', 'Public 8', 'Closed type' ) as $title ) { assert_not_contains_text( $title, $root, 'Hidden fixture is absent from root: ' . $title ); }
$request = $catalog->request( array( 'type' => 'post' ) );
$body = $catalog->document( $request );
assert_contains_text( 'Public 2', $body, 'The first type page contains eligible content.' );
$GLOBALS['llmf_test_posts'][2]->post_status = 'private';
assert_not_contains_text( 'Public 2', $catalog->document( $request ), 'A warmed ID cache rechecks privacy even without a save hook.' );
add_filter( 'llmf_can_export_post', function ( $allowed, $post, $context ) { return $context === 'llms' && $post->ID === 3 ? false : $allowed; } );
assert_not_contains_text( 'Public 3', $catalog->document( $request ), 'Cached catalog uses the existing llms context and rechecks its filter.' );
remove_all_filters( 'llmf_can_export_post' );
assert_true( ( new Catalog( $options->candidate( array( 'llms_index_mode' => 'legacy' ) ), false ) )->request( array( 'type' => 'post' ) ) === null, 'Catalog routes are unavailable after switching back.' );
assert_true( ( new Catalog( $options->candidate( array( 'enabled_llms_txt' => 0 ) ), false ) )->request( array( 'type' => 'post' ) ) === null, 'Disabling llms.txt also disables direct catalog query access.' );
$html_catalog = new Catalog( $options->candidate( array( 'enabled_markdown' => 0 ) ), false );
assert_contains_text( '](https://example.test/post/public-1/)', $html_catalog->document( $request, false ), 'Catalog links fall back to canonical HTML when Markdown is disabled.' );

// Description extraction never executes a shortcode or uses unresolved SEO templates.
$post = $GLOBALS['llmf_test_posts'][1];
$post->post_content = '<pre>Do not summarize this code</pre><p>[unknown] </p><p>First useful paragraph &amp; evidence.</p><p>Later paragraph.</p>';
$GLOBALS['llmf_test_meta'][1]['_yoast_wpseo_metadesc'] = '%%title%% %%excerpt%%';
$description = Content::description( $post, $options );
assert_true( $description['source'] === 'content' && $description['text'] === 'First useful paragraph & evidence.', 'Enhanced descriptions select the first meaningful paragraph.' );
assert_true( in_array( 'unresolved_seo_template', $description['warnings'], true ), 'Unexpanded SEO templates are skipped with a warning.' );
$post->post_excerpt = 'Explicit excerpt';
assert_true( Content::description( $post, $options )['source'] === 'excerpt', 'Explicit excerpts take priority over derived content.' );
$GLOBALS['llmf_test_meta'][1]['_yoast_wpseo_metadesc'] = 'Resolved SEO description';
assert_true( Content::description( $post, $options )['text'] === 'Resolved SEO description', 'Ready SEO descriptions take priority over excerpts.' );
$GLOBALS['llmf_test_meta'][1][ Options::META_LLMS_DESCRIPTION ] = 'Manual LLM description';
assert_true( Content::description( $post, $options )['source'] === 'custom', 'Manual LLM descriptions retain highest priority.' );
unset( $GLOBALS['llmf_test_meta'][1] );
$post->post_excerpt = '';
$post->post_content = '<pre>code only</pre>[unknown]';
assert_true( Content::description( $post, $options )['text'] === '', 'Code and unresolved shortcodes alone yield an empty description.' );
$post->post_content = '<p>[widget]Service-only body[/widget]</p><ul><li>Parent<ul><li>Nested</li></ul></li></ul>';
assert_true( Content::description( $post, $options )['text'] === 'Parent Nested', 'Descriptions skip unexecuted shortcode bodies and separate nested list text.' );
assert_true( Content::absolute_url( '../guide/?q=1#part', 'https://example.test/post/page/' ) === 'https://example.test/post/guide/?q=1#part', 'Relative destinations resolve against the canonical page path.' );
assert_true( Content::absolute_url( '/image.png', 'https://example.test/post/page/' ) === 'https://example.test/image.png', 'Root-relative images resolve to the canonical origin.' );
foreach ( array( '//evil.test/x', 'javascript:alert(1)', 'data:text/html,x', "https://example.test/a\nBad", 'https://user:secret@example.test/x' ) as $url ) { assert_true( Content::absolute_url( $url, get_permalink( $post ) ) === '', 'Enhanced URL normalization rejects unsafe reference.' ); }

$enhanced_exporter = new Exporter( $options );
$post->post_content = '<ul><li>Parent<ul><li>Nested fact</li></ul></li><li>Second fact</li></ul><p><a href="../guide/">Guide</a><img src="/image.png" alt="Illustration" /></p><pre><code>&amp;lt;literal&amp;gt;</code></pre><table><tr><th colspan="2">Merged header</th></tr><tr><td>A</td><td>B</td></tr></table>';
$render = $enhanced_exporter->inspect( $post );
assert_contains_text( 'Nested fact', $render['markdown'], 'Enhanced Classic nested lists preserve their content.' );
assert_contains_text( '[Guide](https://example.test/post/guide/)', $render['markdown'], 'Enhanced Classic links become absolute.' );
assert_contains_text( '![Illustration](https://example.test/image.png)', $render['markdown'], 'Inline Classic images retain alt text and absolute source.' );
assert_contains_text( '&lt;literal&gt;', $render['markdown'], 'DOM code is not decoded twice.' );
assert_contains_text( 'colspan="2"', $render['markdown'], 'Complex table spans remain in safe HTML.' );
assert_true( in_array( 'complex_table_html', $render['warnings'], true ), 'Complex table fallback is reported without guessing semantic loss.' );
$post->post_content = '<p>Literal <code>&amp;lt;tag&amp;gt;</code> and <kbd>&lt;key&gt;</kbd>.</p><figure><table><tr><th colspan="10">Wide header</th></tr><tr><td>Fact A</td></tr></table><figcaption>Wide table evidence</figcaption></figure><figure><table><tr><th>Key</th><th>Value</th></tr><tr><td>Answer</td><td>42</td></tr></table><figcaption>Simple evidence</figcaption></figure>';
$render = $enhanced_exporter->inspect( $post );
assert_contains_text( '`&lt;tag&gt;`', $render['markdown'], 'Inline code entities are decoded exactly once.' );
assert_contains_text( '`<key>`', $render['markdown'], 'Code with literal angle brackets survives HTML stripping.' );
assert_contains_text( 'colspan="10"', $render['markdown'], 'Multi-digit spans retain their actual safe HTML geometry.' );
assert_contains_text( 'Wide table evidence', $render['markdown'], 'Complex figure tables retain captions.' );
assert_contains_text( '| Answer | 42 |', $render['markdown'], 'Classic figure tables preserve the cell structure.' );
assert_contains_text( 'Simple evidence', $render['markdown'], 'Classic figure tables retain captions.' );
$post->post_content = '<ol start="12"><li>Parent fact<ul><li>Nested fact</li></ul></li></ol><p><video src="/film.mp4"></video><audio><source src="/speech.ogg" /></audio></p><figure><img src="/first.png" alt="First" /><img src="/second.png" alt="Second" /><figcaption>Both images</figcaption></figure><table><tr><th>Source</th></tr><tr><td><a href="/guide/">Guide</a> <code>&amp;lt;fact&amp;gt;</code></td></tr></table>';
$render = $enhanced_exporter->inspect( $post );
assert_contains_text( "12. Parent fact\n    - Nested fact", $render['markdown'], 'Nested lists align with the width of their ordered parent marker.' );
assert_contains_text( '[film.mp4](https://example.test/film.mp4)', $render['markdown'], 'Video exports preserve their canonical source file.' );
assert_contains_text( '[speech.ogg](https://example.test/speech.ogg)', $render['markdown'], 'Audio source elements preserve their canonical source file.' );
assert_contains_text( '![Second](https://example.test/second.png)', $render['markdown'], 'Classic image figures retain all images.' );
assert_contains_text( '[Guide](https://example.test/guide/) `&lt;fact&gt;`', $render['markdown'], 'Table cells preserve links and code entities.' );
$GLOBALS['llmf_test_parse_blocks_callback'] = function ( $content ) {
	if ( $content === 'reference' ) { return array( array( 'blockName' => 'core/block', 'attrs' => array( 'ref' => 80 ) ) ); }
	if ( $content === 'old-list' ) { return array( array( 'blockName' => 'core/list', 'innerHTML' => '<ol start="3"><li>Old list fact</li></ol>' ) ); }
	return array( array( 'blockName' => null, 'innerHTML' => $content, 'innerContent' => array( $content ) ) );
};
$post->post_content = 'old-list';
assert_contains_text( 'Old list fact', $enhanced_exporter->inspect( $post )['markdown'], 'Old Gutenberg lists without innerBlocks are preserved in enhanced mode.' );
$GLOBALS['llmf_test_posts'][80] = new WP_Post( array( 'ID' => 80, 'post_type' => 'wp_block', 'post_content' => '<p>Reusable fact one</p>' ) );
$post->post_content = 'reference';
$first = call_private( $enhanced_exporter, 'public_markdown_for_post', array( $post ) );
assert_contains_text( 'Reusable fact one', $first, 'Published reusable blocks are converted.' );
$GLOBALS['llmf_test_posts'][80]->post_content = '<p>Reusable fact two</p>';
$second = call_private( $enhanced_exporter, 'public_markdown_for_post', array( $post ) );
assert_contains_text( 'Reusable fact two', $second, 'A dependency content change selects fresh Markdown even without parent modification.' );
assert_true( $first !== $second, 'Reusable changes invalidate the body and ETag input.' );
$root_llms = new Llms( $options, false );
$root_llms->regenerate( true );
assert_true( in_array( 80, get_option( 'llmf_root_dependencies' ), true ), 'Root cache records the public reusable sources behind its descriptions.' );
add_filter( 'llmf_can_export_post', function ( $allowed, $item ) { return $item->ID === 80 ? false : $allowed; }, 10, 2 );
assert_not_contains_text( 'Reusable fact two', call_private( $enhanced_exporter, 'public_markdown_for_post', array( $post ) ), 'A live dependency denial selects a safe representation without a parent edit.' );
assert_true( call_private( $root_llms, 'cached_root_is_public' ) === false, 'A live dependency denial revokes the root description cache.' );
remove_all_filters( 'llmf_can_export_post' );
$large_options = $options->candidate( array( 'llms_custom_markdown' => str_repeat( '測', 20000 ) ) );
$large_llms = new Llms( $large_options, false );
assert_true( $large_llms->preview()['truncated'] && preg_match( '//u', $large_llms->preview()['content'] ) === 1, 'Large multibyte previews are capped without cutting a character.' );
$large_catalog = new Catalog( $large_options, false );
$large_diagnostics = new Diagnostics( $large_options, $large_catalog, new Exporter( $large_options ), false );
assert_true( $large_diagnostics->post_report( $post )['inShortMap'], 'Coverage uses the full root even when user notes truncate the displayed preview.' );
$GLOBALS['llmf_test_meta'][1][ Exporter::META_MD_OVERRIDE ] = 'Manual fact';
assert_true( Content::override_state( $post ) === 'unconfirmed', 'Old overrides without fingerprints are explicitly unconfirmed.' );
$diagnostics->record_override_source( 0, 1, Exporter::META_MD_OVERRIDE, 'Manual fact' );
assert_true( Content::override_state( $post ) === 'current', 'Saving a changed override records the current source fingerprint.' );
$GLOBALS['llmf_test_posts'][80]->post_content = '<p>Reusable fact three</p>';
assert_true( Content::override_state( $post ) === 'outdated', 'Reusable source edits make a manual override outdated.' );
assert_contains_text( 'Manual fact', $enhanced_exporter->inspect( $post )['markdown'], 'Outdated manual Markdown remains authoritative until reviewed.' );
unset( $GLOBALS['llmf_test_meta'][1][ Exporter::META_MD_OVERRIDE ] );
$GLOBALS['llmf_test_posts'][80]->post_status = 'private';
assert_not_contains_text( 'Reusable fact three', call_private( $enhanced_exporter, 'public_markdown_for_post', array( $post ) ), 'Private reusable blocks cannot survive dependency cache warming.' );
assert_true( in_array( 'unavailable_reusable_block', $enhanced_exporter->inspect( $post )['warnings'], true ), 'Unavailable reusable content is reported.' );
$GLOBALS['llmf_test_user_id'] = 7;
$_COOKIE['wp-postpass_' . COOKIEHASH] = 'synthetic-cookie';
$GLOBALS['llmf_test_parse_blocks_callback'] = function () { throw new RuntimeException( 'Synthetic parser failure.' ); };
try { $enhanced_exporter->inspect( $post ); } catch ( RuntimeException $error ) {}
assert_true( get_current_user_id() === 7 && $_COOKIE['wp-postpass_' . COOKIEHASH] === 'synthetic-cookie', 'Enhanced preview restores the caller and password cookie on failure.' );
unset( $_COOKIE['wp-postpass_' . COOKIEHASH] );
$GLOBALS['llmf_test_user_id'] = 0;
$GLOBALS['llmf_test_parse_blocks_callback'] = function ( $content ) { return array( array( 'blockName' => null, 'innerHTML' => $content, 'innerContent' => array( $content ) ) ); };

// All new owner actions share capability and CSRF protection; comparison performs no writes.
foreach ( array( 'ajax_compare_modes', 'ajax_search_pins', 'ajax_diagnostics', 'ajax_coverage' ) as $method ) {
	$GLOBALS['llmf_test_current_user_can'] = false;
	$_REQUEST = $_POST = array( 'nonce' => 'test-nonce' );
	list( $json, $status ) = capture_json_response( function () use ( $admin, $method ) { $admin->$method(); } );
	assert_true( $status === 403, 'Non-owner cannot call ' . $method . '.' );
	$GLOBALS['llmf_test_current_user_can'] = true;
	$_REQUEST = $_POST = array( 'nonce' => 'invalid' );
	list( $json, $status ) = capture_json_response( function () use ( $admin, $method ) { $admin->$method(); } );
	assert_true( $status === 403, 'Invalid nonce cannot call ' . $method . '.' );
}
$_REQUEST = $_POST = array( 'nonce' => 'test-nonce', 'post_id' => 1, Options::OPTION_KEY => array( 'llms_index_mode' => 'structured', 'content_profile' => 'enhanced' ) );
$before = $GLOBALS['llmf_test_options'];
list( $json, $status ) = capture_json_response( function () use ( $admin ) { $admin->ajax_compare_modes(); } );
assert_true( $status === 200 && isset( $json['data']['legacy']['root'], $json['data']['selected']['markdown'] ), 'Comparison returns both requested representations.' );
assert_true( $GLOBALS['llmf_test_options'] === $before, 'Comparison cannot mutate plugin settings, root cache, jobs or rewrite rules.' );
$GLOBALS['llmf_test_http'] = array();
$report = $diagnostics->check( 1 );
assert_true( count( $GLOBALS['llmf_test_http'] ) === 6, 'Diagnostics performs no more than six fixed HTTP probes.' );
assert_true( $report['endpoints']['root']['GET']['status'] === 'unable_to_check', 'A blocked environment is not reported as success.' );
foreach ( $GLOBALS['llmf_test_http'] as $probe ) {
	assert_true( $probe['args']['sslverify'] && $probe['args']['redirection'] === 0 && $probe['args']['timeout'] === 5 && $probe['args']['limit_response_size'] === 262144 && $probe['args']['cookies'] === array(), 'Probe limits, TLS and anonymous transport are enforced.' );
}
$GLOBALS['llmf_test_http_callback'] = function ( $url, $args ) { return array( 'response' => array( 'code' => 200 ), 'headers' => array( 'content-type' => 'text/html' ), 'body' => '<html>Not Markdown</html>' ); };
assert_true( $diagnostics->check( 1 )['endpoints']['root']['GET']['status'] === 'failed', 'A 200 HTML fallback is not mistaken for a working Markdown endpoint.' );
unset( $GLOBALS['llmf_test_http_callback'] );

// A complete 10,000-record traversal, with a rejected full page, uses stable ID cursors.
$GLOBALS['llmf_test_posts'] = array();
$GLOBALS['llmf_test_meta'] = array();
$GLOBALS['llmf_test_options'][ Options::OPTION_KEY ] = array_merge( $options->defaults(), array( 'llms_index_mode' => 'structured' ) );
for ( $id = 1; $id <= 10000; $id ++ ) { $GLOBALS['llmf_test_posts'][ $id ] = new WP_Post( array( 'ID' => $id, 'post_name' => 'scale-' . $id, 'post_title' => 'Scale ' . $id, 'post_content' => '<p>Fact ' . $id . '</p>' ) ); }
add_filter( 'llmf_can_export_post', function ( $allowed, $post, $context ) { return $post->ID > 100 && $post->ID % 13 !== 0; } );
$GLOBALS['llmf_test_queries'] = array();
$after = 0;
$seen = array();
$iterations = 0;
do {
	$page = $catalog->scan( 'post', $after );
	$iterations ++;
	assert_true( $page['scanned'] <= 100 && ( $page['next'] === 0 || $page['next'] > $after ), 'Every scale page is bounded and advances past rejected candidates.' );
	foreach ( $page['posts'] as $post ) { $seen[] = $post->ID; }
	$after = $page['next'];
} while ( $after && $iterations < 105 );
$expected = array_values( array_filter( range( 1, 10000 ), function ( $id ) { return $id > 100 && $id % 13 !== 0; } ) );
assert_true( $seen === $expected, 'Complete 10,000-post traversal loses or duplicates no eligible ID.' );
assert_true( $iterations === 101 && count( $GLOBALS['llmf_test_queries'] ) === 101, 'Catalog scans use one bounded query per page, without offset or full-site fetches.' );
assert_true( empty( $GLOBALS['llmf_test_filters']['posts_where'] ), 'Scale queries leave no SQL filter installed.' );
remove_all_filters( 'llmf_can_export_post' );

// Topic projection and coverage use resumable batches, not a public request scan.
$GLOBALS['llmf_test_posts'] = array_slice( $GLOBALS['llmf_test_posts'], 0, 110, true );
$GLOBALS['llmf_test_terms']['category'][2] = (object) array( 'term_id' => 2, 'name' => 'Topic two' );
$GLOBALS['llmf_test_terms']['category'][3] = (object) array( 'term_id' => 3, 'name' => 'Empty topic' );
$GLOBALS['llmf_test_post_terms'][1]['category'] = array( $GLOBALS['llmf_test_terms']['category'][2] );
$diagnostics->start();
$batch = $diagnostics->batch();
assert_true( $batch['scanned'] === 100 && $batch['status'] === 'running', 'Coverage reports resumable progress after one 100-candidate batch.' );
$batch = $diagnostics->batch();
assert_true( $batch['scanned'] === 110 && $batch['status'] === 'complete', 'A second batch completes without rescanning the first page.' );
assert_true( ! isset( $batch['topics'] ), 'Coverage AJAX never ships its large internal topic projection.' );
$topic_index = $catalog->document( $catalog->request( array( 'type' => 'post', 'taxonomy' => 'category' ) ) );
assert_contains_text( 'Topic two', $topic_index, 'Nonempty topic is published after the projection completes.' );
assert_not_contains_text( 'Empty topic', $topic_index, 'Empty topics are not published.' );
$catalog->invalidate();
assert_true( $diagnostics->progress()['status'] === 'stale', 'A content change marks coverage and topic projection stale.' );
assert_not_contains_text( 'Topic two', $catalog->document( $catalog->request( array( 'type' => 'post', 'taxonomy' => 'category' ) ) ), 'A stale topic projection is not used for public navigation.' );
set_transient( 'llmf_catalog_scan_lock', true, 10 );
add_option( 'llmf_catalog_scan_lock', time() );
$before = get_option( Catalog::JOB_KEY );
$diagnostics->batch();
assert_true( get_option( Catalog::JOB_KEY ) === $before, 'An occupied coverage worker lock cannot update a job.' );
delete_option( 'llmf_catalog_scan_lock' );
unset( $GLOBALS['llmf_test_parse_blocks_callback'] );
