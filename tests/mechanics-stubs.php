<?php
/** Extra WordPress test doubles for the catalog; runtime acceptance uses real WP. */
function trailingslashit( $value ) { return rtrim( $value, '/\\' ) . '/'; }
function get_taxonomy( $name ) {
	if ( ! in_array( $name, array( 'category', 'post_tag', 'hidden_tax' ), true ) ) { return null; }
	return (object) array( 'public' => $name !== 'hidden_tax', 'publicly_queryable' => $name !== 'hidden_tax', 'object_type' => array( 'post' ), 'labels' => (object) array( 'name' => $name === 'category' ? 'Categories' : 'Tags' ) );
}
function get_object_taxonomies( $type, $output = 'names' ) {
	$taxonomies = array();
	foreach ( array( 'category', 'post_tag', 'hidden_tax' ) as $name ) {
		$obj = get_taxonomy( $name );
		if ( in_array( $type, $obj->object_type, true ) ) { $taxonomies[ $name ] = $output === 'objects' ? $obj : $name; }
	}
	return $taxonomies;
}
function get_term( $id, $taxonomy = '' ) { return $GLOBALS['llmf_test_terms'][ $taxonomy ][ $id ] ?? null; }
function get_the_terms( $post, $taxonomy ) { return $GLOBALS['llmf_test_post_terms'][ $post->ID ][ $taxonomy ] ?? false; }
function is_wp_error( $value ) { return $value instanceof LLMF_Test_Error; }
class LLMF_Test_Error {}
class LLMF_Test_Wpdb {
	public string $posts = 'wp_posts';
	public function prepare( $sql, ...$args ) { return vsprintf( $sql, $args ); }
}
$GLOBALS['wpdb'] = new LLMF_Test_Wpdb();
function delete_option( $key ) { unset( $GLOBALS['llmf_test_options'][ $key ] ); return true; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['llmf_test_meta'][ $id ][ $key ] = $value; return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['llmf_test_meta'][ $id ][ $key ] ); return true; }
function wp_safe_remote_get( $url, $args ) {
	$GLOBALS['llmf_test_http'][] = array( 'url' => $url, 'args' => $args );
	if ( isset( $GLOBALS['llmf_test_http_callback'] ) ) { return $GLOBALS['llmf_test_http_callback']( $url, $args ); }
	return new LLMF_Test_Error();
}
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code'] ?? 0; }
function wp_remote_retrieve_body( $response ) { return $response['body'] ?? ''; }
function wp_remote_retrieve_header( $response, $name ) { return $response['headers'][ $name ] ?? ''; }
