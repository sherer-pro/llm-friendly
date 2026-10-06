<?php
namespace LLMFriendly;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Bounded public catalog queries. Cached IDs are never an authorization decision. */
final class Catalog {
	public const FORMAT = 'catalog-1';
	public const BATCH_SIZE = 100;
	public const JOB_KEY = 'llmf_catalog_job';
	private Options $options;

	public function __construct( Options $options, bool $hooks = true ) {
		$this->options = $options;
		if ( $hooks ) {
			add_action( 'save_post', array( $this, 'invalidate' ), 30, 2 );
			add_action( 'delete_post', array( $this, 'invalidate' ), 30, 2 );
			add_action( 'added_post_meta', array( $this, 'meta_changed' ), 30, 4 );
			add_action( 'updated_post_meta', array( $this, 'meta_changed' ), 30, 4 );
			add_action( 'deleted_post_meta', array( $this, 'meta_changed' ), 30, 4 );
			add_action( 'set_object_terms', array( $this, 'invalidate' ), 30, 2 );
			add_action( 'edited_term', array( $this, 'invalidate' ) );
			add_action( 'delete_term', array( $this, 'invalidate' ) );
			add_action( 'update_option_' . Options::OPTION_KEY, array( $this, 'settings_changed' ), 30, 2 );
		}
	}

	public function generation(): string {
		return (string) get_option( 'llmf_catalog_generation', '0' );
	}

	public function invalidate( $id = 0, $post = null ): void {
		// Changes to attachments and unrelated private types cannot affect this catalog.
		if ( $post instanceof WP_Post && $post->post_type !== 'wp_block' && ! in_array( $post->post_type, $this->options->selected_post_types( $this->options->get() ), true ) ) {
			return;
		}
		update_option( 'llmf_catalog_generation', function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( '', true ), false );
		if ( ! empty( $this->options->get()['enabled_llms_txt'] ) && $this->options->get()['llms_index_mode'] === 'structured' ) {
			if ( ! wp_next_scheduled( 'llmf_catalog_scan' ) ) {
				wp_schedule_single_event( time() + 5, 'llmf_catalog_scan' );
			}
		}
	}

	public function meta_changed( $meta_id, $id, $key, $value ): void {
		if ( in_array( $key, array( Options::META_LLMS_DESCRIPTION, Exporter::META_MD_OVERRIDE, '_yoast_wpseo_metadesc', 'wpseo_metadesc' ), true ) ) {
			$this->invalidate( $id, get_post( $id ) );
		}
	}

	public function settings_changed( $old, $new ): void {
		$keys = array( 'llms_index_mode', 'content_profile', 'enabled_llms_txt', 'enabled_markdown', 'base_path', 'post_types', 'excluded_posts', 'catalog_taxonomies', 'llms_pinned_ids' );
		foreach ( $keys as $key ) {
			if ( ( $old[ $key ] ?? null ) !== ( $new[ $key ] ?? null ) ) {
				$this->invalidate();
				break;
			}
		}
	}

	public function eligible( WP_Post $post ): bool {
		return in_array( $post->post_type, $this->options->selected_post_types( $this->options->get() ), true ) && $this->options->can_export_post( $post, 'llms' );
	}

	public function taxonomies( string $type ): array {
		$opt = $this->options->get();
		$selected = array_key_exists( $type, $opt['catalog_taxonomies'] ) ? $opt['catalog_taxonomies'][ $type ] : array( 'category' );
		$out = array();
		foreach ( $selected as $taxonomy ) {
			$obj = get_taxonomy( $taxonomy );
			if ( $obj && ! empty( $obj->public ) && ! empty( $obj->publicly_queryable ) && in_array( $type, (array) $obj->object_type, true ) ) {
				$out[] = $taxonomy;
			}
		}
		return $out;
	}

	public function url( string $type = '', string $taxonomy = '', int $term = 0, int $after = 0, ?int $parent = null ): string {
		$base = $this->options->sanitize_base_path( $this->options->get()['base_path'] );
		$path = '/' . $base . '/catalog/';
		if ( $type === 'essential' ) {
			$path .= 'essential.txt';
		} else {
			$path .= $type !== '' ? rawurlencode( $type ) . '/' : '';
			$path .= $taxonomy !== '' ? rawurlencode( $taxonomy ) . '/' : '';
			$path .= $term > 0 ? $term . '.txt' : 'index.txt';
		}
		$args = array();
		if ( $after > 0 ) {
			$args['llmf_after'] = $after;
		}
		if ( $parent !== null ) {
			$args['llmf_parent'] = $parent;
		}
		return empty( $args ) ? home_url( $path ) : add_query_arg( $args, home_url( $path ) );
	}

	/** Scan at most 100 candidates, advancing past records rejected by filters. */
	public function scan( string $type = '', int $after = 0, string $taxonomy = '', int $term = 0, ?int $parent = null ): array {
		$types = $this->options->selected_post_types( $this->options->get() );
		if ( empty( $types ) || ( $type !== '' && ! in_array( $type, $types, true ) ) ) {
			return array( 'posts' => array(), 'candidates' => array(), 'next' => 0, 'scanned' => 0 );
		}
		$args = array(
			'post_type' => $type !== '' ? $type : $types,
			'post_status' => 'publish', 'has_password' => false,
			'posts_per_page' => self::BATCH_SIZE, 'orderby' => 'ID', 'order' => 'ASC',
			'no_found_rows' => true, 'ignore_sticky_posts' => true,
			'update_post_meta_cache' => true, 'update_post_term_cache' => true,
			'llmf_catalog_after' => max( 0, $after ),
		);
		if ( $taxonomy !== '' && $term > 0 ) {
			$args['tax_query'] = array( array( 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => array( $term ), 'include_children' => false ) );
		}
		if ( $parent !== null ) {
			$args['post_parent'] = $parent;
		}
		global $wpdb;
		$where = function ( $sql, $query ) use ( $after, $wpdb ) {
			if ( $after > 0 && $query->get( 'llmf_catalog_after' ) === $after ) {
				$sql .= $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", $after );
			}
			return $sql;
		};
		add_filter( 'posts_where', $where, 10, 2 );
		try {
			$query = new \WP_Query( $args );
		} finally {
			remove_filter( 'posts_where', $where, 10 );
		}
		$posts = array();
		$last = $after;
		foreach ( $query->posts as $post ) {
			$last = (int) $post->ID;
			if ( $this->eligible( $post ) ) {
				$posts[] = $post;
			}
		}
		return array( 'posts' => $posts, 'candidates' => $query->posts, 'next' => count( $query->posts ) === self::BATCH_SIZE ? $last : 0, 'scanned' => count( $query->posts ) );
	}

	public function pinned(): array {
		$ids = $this->options->sanitize_pinned_ids( $this->options->get()['llms_pinned_ids'] );
		if ( empty( $ids ) ) {
			return array();
		}
		$query = new \WP_Query( array( 'post__in' => $ids, 'post_type' => $this->options->selected_post_types( $this->options->get() ), 'post_status' => 'publish', 'has_password' => false, 'orderby' => 'post__in', 'posts_per_page' => 100, 'no_found_rows' => true, 'ignore_sticky_posts' => true, 'update_post_meta_cache' => true ) );
		return array_values( array_filter( $query->posts, array( $this, 'eligible' ) ) );
	}

	public function recent( string $type ): array {
		$query = new \WP_Query( array( 'post_type' => $type, 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => 100, 'orderby' => array( 'modified' => 'DESC', 'ID' => 'DESC' ), 'no_found_rows' => true, 'ignore_sticky_posts' => true, 'update_post_meta_cache' => true ) );
		return array_slice( array_values( array_filter( $query->posts, array( $this, 'eligible' ) ) ), 0, 5 );
	}

	public function line( WP_Post $post ): string {
		$opt = $this->options->get();
		$canonical = Markdown::url_destination( get_permalink( $post ), array( 'http', 'https' ), false );
		$url = ! empty( $opt['enabled_markdown'] ) ? $this->options->markdown_url_for_post( $post ) : $canonical;
		$url = Markdown::url_destination( $url, array( 'http', 'https' ), false );
		if ( $url === '' ) {
			return '';
		}
		$description = $this->options->llms_description_for_post( $post );
		/* translators: %s: modification date in YYYY-MM-DD format. */
		$notes = trim( $description . ' ' . sprintf( __( 'Updated %s.', 'llm-friendly' ), get_the_modified_date( 'Y-m-d', $post ) ) );
		if ( ! empty( $opt['enabled_markdown'] ) && $canonical !== '' ) {
			/* translators: %s: canonical URL of the source page. */
			$notes .= ' ' . sprintf( __( 'Canonical URL: %s', 'llm-friendly' ), $canonical );
		}
		return self::link( get_the_title( $post ), $url, $notes );
	}

	public static function link( string $title, string $url, string $notes = '' ): string {
		$url = Markdown::url_destination( $url, array( 'http', 'https' ), false );
		return $url === '' ? '' : '- [' . Markdown::link_text( $title ) . '](' . $url . ')' . ( $notes !== '' ? ': ' . Markdown::plain_text_line( $notes ) : '' );
	}

	/** Validate both pretty routes and direct query vars before constructing a query. */
	public function request( array $vars ): ?array {
		$opt = $this->options->get();
		if ( empty( $opt['enabled_llms_txt'] ) || $opt['llms_index_mode'] !== 'structured' ) {
			return null;
		}
		foreach ( array( 'type', 'taxonomy', 'term', 'after', 'parent' ) as $key ) {
			if ( isset( $vars[ $key ] ) && ! is_scalar( $vars[ $key ] ) ) {
				return null;
			}
		}
		$type = (string) ( $vars['type'] ?? '' );
		$taxonomy = (string) ( $vars['taxonomy'] ?? '' );
		foreach ( array( $type, $taxonomy ) as $key ) {
			if ( $key !== '' && ( sanitize_key( $key ) !== $key || strlen( $key ) > 32 ) ) {
				return null;
			}
		}
		foreach ( array( 'term', 'after', 'parent' ) as $key ) {
			if ( isset( $vars[ $key ] ) && $vars[ $key ] !== '' && ! preg_match( '/^(0|[1-9][0-9]{0,17})$/D', (string) $vars[ $key ] ) ) {
				return null;
			}
		}
		$term = (int) ( $vars['term'] ?? 0 );
		$after = (int) ( $vars['after'] ?? 0 );
		$parent = isset( $vars['parent'] ) && $vars['parent'] !== '' ? (int) $vars['parent'] : null;
		$types = $this->options->selected_post_types( $opt );
		if ( $type !== '' && $type !== 'essential' && ! in_array( $type, $types, true ) ) {
			return null;
		}
		if ( $taxonomy !== '' && ( ! in_array( $taxonomy, $this->taxonomies( $type ), true ) || $type === '' || $type === 'essential' ) ) {
			return null;
		}
		if ( $term > 0 && ( $taxonomy === '' || ! get_term( $term, $taxonomy ) || is_wp_error( get_term( $term, $taxonomy ) ) ) ) {
			return null;
		}
		if ( $parent !== null ) {
			$obj = get_post_type_object( $type );
			$p = $parent > 0 ? get_post( $parent ) : null;
			if ( ! $obj || empty( $obj->hierarchical ) || ( $parent > 0 && ( ! $p || $p->post_type !== $type || ! $this->eligible( $p ) ) ) || $taxonomy !== '' ) {
				return null;
			}
		}
		if ( $type === 'essential' && ( $taxonomy !== '' || $term || $after || $parent !== null ) ) {
			return null;
		}
		return array( 'type' => $type, 'taxonomy' => $taxonomy, 'term' => $term, 'after' => $after, 'parent' => $parent );
	}

	/** ID pages only; content, visibility and filter decisions are rechecked on every read. */
	public function document( array $request, bool $cache = true ): string {
		return Content::anonymous( function () use ( $request, $cache ) { return $this->build_document( $request, $cache ); } );
	}

	private function build_document( array $request, bool $cache ): string {
		extract( $request, EXTR_SKIP );
		$title = __( 'Content catalog', 'llm-friendly' );
		$lines = array();
		$next = 0;
		if ( $type === '' ) {
			foreach ( $this->options->selected_post_types( $this->options->get() ) as $pt ) {
				$obj = get_post_type_object( $pt );
				$lines[] = self::link( $obj->labels->name, $this->url( $pt ) );
			}
			$lines[] = self::link( __( 'Pinned content', 'llm-friendly' ), $this->url( 'essential' ) );
		} elseif ( $type === 'essential' ) {
			$title = __( 'Pinned content', 'llm-friendly' );
			foreach ( $this->pinned() as $post ) {
				$lines[] = $this->line( $post );
			}
		} elseif ( $taxonomy !== '' && $term === 0 ) {
			$title = get_taxonomy( $taxonomy )->labels->name;
			$job = get_option( self::JOB_KEY, array() );
			$ids = $job['generation'] ?? '';
			$ids = $ids === $this->generation() && ! empty( $job['complete'] ) ? array_keys( $job['topics'][ $type ][ $taxonomy ] ?? array() ) : array();
			sort( $ids, SORT_NUMERIC );
			$ids = array_values( array_filter( $ids, function ( $id ) use ( $after ) { return (int) $id > $after; } ) );
			$chunk = array_slice( $ids, 0, self::BATCH_SIZE );
			foreach ( $chunk as $id ) {
				$t = get_term( $id, $taxonomy );
				if ( $t && ! is_wp_error( $t ) ) {
					$lines[] = self::link( $t->name, $this->url( $type, $taxonomy, (int) $id ) );
				}
			}
			$next = count( $ids ) > self::BATCH_SIZE ? (int) end( $chunk ) : 0;
			$lines[] = self::link( __( 'All content in this type', 'llm-friendly' ), $this->url( $type ), empty( $job['complete'] ) || ( $job['generation'] ?? '' ) !== $this->generation() ? __( 'Topic catalog is being built.', 'llm-friendly' ) : '' );
		} else {
			$obj = get_post_type_object( $type );
			$title = $term > 0 ? get_term( $term, $taxonomy )->name : $obj->labels->name;
			$key = 'llmf_cat_' . md5( self::FORMAT . $this->generation() . wp_json_encode( array( $request, $this->settings() ) ) );
			$page = $cache && $after === 0 ? get_transient( $key ) : false;
			if ( ! is_array( $page ) ) {
				$page = $this->scan( $type, $after, $taxonomy, $term, $parent );
				$page['ids'] = array_map( function ( $post ) { return (int) $post->ID; }, $page['posts'] );
				unset( $page['posts'] );
				unset( $page['candidates'] );
				if ( $cache && $after === 0 ) {
					set_transient( $key, $page, 300 );
				}
			}
			if ( ! empty( $page['ids'] ) ) {
				$posts = new \WP_Query( array( 'post__in' => $page['ids'], 'post_type' => $type, 'post_status' => 'publish', 'has_password' => false, 'orderby' => 'post__in', 'posts_per_page' => 100, 'no_found_rows' => true, 'ignore_sticky_posts' => true, 'update_post_meta_cache' => true ) );
				foreach ( $posts->posts as $post ) {
					if ( $this->eligible( $post ) ) {
						$lines[] = $this->line( $post );
					}
				}
			}
			$next = $page['next'];
			if ( $term === 0 ) {
				foreach ( $this->taxonomies( $type ) as $tax ) {
					$lines[] = self::link( get_taxonomy( $tax )->labels->name, $this->url( $type, $tax ) );
				}
			}
		}
		if ( $next > 0 ) {
			$lines[] = self::link( __( 'Next page', 'llm-friendly' ), $this->url( $type, $taxonomy, $term, $next, $parent ) );
		}
		$lines[] = self::link( __( 'Content catalog', 'llm-friendly' ), $this->url() );
		$seen = array();
		$lines = array_values( array_filter( $lines, function ( $line ) use ( &$seen ) {
			if ( ! preg_match( '/\]\(([^)]+)\)/', $line, $match ) || isset( $seen[ $match[1] ] ) ) {
				return false;
			}
			$seen[ $match[1] ] = true;
			return true;
		} ) );
		return '# ' . Markdown::plain_text_line( $title ) . "\n\n> " . __( 'Public content selected for LLM access.', 'llm-friendly' ) . "\n\n## " . __( 'Links', 'llm-friendly' ) . "\n\n" . implode( "\n", $lines ) . "\n";
	}

	private function settings(): array {
		$opt = $this->options->get();
		return array_intersect_key( $opt, array_flip( array( 'base_path', 'post_types', 'enabled_markdown', 'excluded_posts', 'content_profile', 'catalog_taxonomies' ) ) );
	}

	public function output( array $vars ): void {
		$request = $this->request( $vars );
		if ( $request === null ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}
		$body = $this->document( $request );
		$headers = array( 'Content-Type: text/markdown; charset=UTF-8', 'X-Content-Type-Options: nosniff' );
		if ( ! empty( $this->options->get()['llms_send_noindex'] ) ) {
			$headers[] = 'X-Robots-Tag: noindex';
		}
		Response::send_conditional_headers( $headers, Response::etag_from_string( $body, self::FORMAT ), time(), false );
		if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'HEAD' ) {
			echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markdown links are formatted above.
		}
		exit;
	}
}
