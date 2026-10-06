<?php
namespace LLMFriendly;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Owner initiated checks and resumable coverage/topic projection, never a public site scan. */
final class Diagnostics {
	private Options $options;
	private Catalog $catalog;
	private Exporter $exporter;

	public function __construct( Options $options, Catalog $catalog, Exporter $exporter, bool $hooks = true ) {
		$this->options = $options;
		$this->catalog = $catalog;
		$this->exporter = $exporter;
		if ( $hooks ) {
			add_action( 'llmf_catalog_scan', array( $this, 'run_scheduled_batch' ) );
			add_action( 'updated_post_meta', array( $this, 'record_override_source' ), 40, 4 );
			add_action( 'added_post_meta', array( $this, 'record_override_source' ), 40, 4 );
			add_action( 'deleted_post_meta', array( $this, 'record_override_source' ), 40, 4 );
		}
	}

	public function record_override_source( $meta_id, $id, $key, $value = null ): void {
		if ( $key !== Exporter::META_MD_OVERRIDE ) {
			return;
		}
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		$override = get_post_meta( $id, $key, true );
		if ( is_string( $override ) && trim( $override ) !== '' ) {
			update_post_meta( $id, Content::META_SOURCE_HASH, Content::fingerprint( $post ) );
		} else {
			delete_post_meta( $id, Content::META_SOURCE_HASH );
		}
	}

	public function post_report( WP_Post $post ): array {
		return Content::anonymous( function () use ( $post ) {
			$eligible = $this->catalog->eligible( $post );
			$description = $this->options->get()['content_profile'] === 'enhanced' ? Content::description( $post, $this->options ) : array( 'text' => $this->options->llms_description_for_post( $post ), 'source' => 'legacy', 'warnings' => array() );
			$render = $eligible ? $this->exporter->inspect( $post ) : array( 'warnings' => array( 'not_exportable' ), 'dependencies' => array(), 'markdown' => '' );
			$root = ( new Llms( $this->options, false ) )->generated_content();
			$url = ! empty( $this->options->get()['enabled_markdown'] ) ? $this->options->markdown_url_for_post( $post ) : get_permalink( $post );
			return array(
				'id' => $post->ID, 'title' => get_the_title( $post ),
				'eligibility' => $eligible ? 'exportable' : 'not_exportable',
				'inShortMap' => $eligible && strpos( $root, '](' . Markdown::url_destination( $url ) . ')' ) !== false,
				'descriptionSource' => $description['source'], 'description' => $description['text'],
				'warnings' => array_values( array_unique( array_merge( $description['warnings'], $render['warnings'] ) ) ),
				'dependencies' => $render['dependencies'], 'overrideState' => Content::override_state( $post ),
			);
		} );
	}

	/** Six fixed, plugin-built requests. No supplied URL, credentials, redirects or TLS bypass. */
	public function check( int $id = 0 ): array {
		$opt = $this->options->get();
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post ) {
			$posts = $this->catalog->scan()['posts'];
			$post = $posts[0] ?? null;
		}
		$result = array( 'checkedAt' => gmdate( 'c' ), 'endpoints' => array(), 'post' => $post ? $this->post_report( $post ) : null, 'cache' => array( 'rootCached' => ! empty( $opt['llms_cache'] ), 'rootMode' => $opt['llms_regen_mode'], 'rootLocked' => (bool) get_transient( 'llmf_llms_regen_lock' ), 'catalogGeneration' => $this->catalog->generation() ), 'coverage' => $this->progress() );
		$urls = array();
		if ( ! empty( $opt['enabled_llms_txt'] ) ) {
			$urls['root'] = home_url( '/llms.txt' );
		}
		if ( ! empty( $opt['enabled_markdown'] ) && $post && $this->catalog->eligible( $post ) ) {
			$urls['markdown'] = $this->options->markdown_url_for_post( $post );
		}
		if ( ! empty( $opt['enabled_llms_txt'] ) && $opt['llms_index_mode'] === 'structured' ) {
			$urls['catalog'] = $this->catalog->url();
		}
		foreach ( $urls as $kind => $url ) {
			$result['endpoints'][ $kind ] = array();
			foreach ( array( 'GET', 'HEAD' ) as $method ) {
				$result['endpoints'][ $kind ][ $method ] = $this->probe( $url, $method, $kind );
			}
		}
		return $result;
	}

	private function probe( string $url, string $method, string $kind ): array {
		$home = wp_parse_url( home_url( '/' ) );
		$target = wp_parse_url( $url );
		if ( ! is_array( $home ) || ! is_array( $target ) || isset( $target['user'] ) || isset( $target['pass'] ) || ( $target['scheme'] ?? '' ) !== ( $home['scheme'] ?? '' ) || ( $target['host'] ?? '' ) !== ( $home['host'] ?? '' ) || ( $target['port'] ?? null ) !== ( $home['port'] ?? null ) || strpos( $target['path'] ?? '/', trailingslashit( $home['path'] ?? '/' ) ) !== 0 ) {
			return array( 'status' => 'unable_to_check', 'reason' => __( 'The generated URL is outside this site.', 'llm-friendly' ) );
		}
		$response = wp_safe_remote_get( $url, array( 'method' => $method, 'timeout' => 5, 'redirection' => 0, 'limit_response_size' => 262144, 'sslverify' => true, 'cookies' => array(), 'headers' => array( 'Accept' => 'text/markdown' ) ) );
		if ( is_wp_error( $response ) ) {
			return array( 'status' => 'unable_to_check', 'reason' => __( 'The site could not complete the HTTP check.', 'llm-friendly' ) );
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$type = (string) wp_remote_retrieve_header( $response, 'content-type' );
		$format = $method === 'HEAD' || ( $kind === 'markdown' ? (bool) preg_match( '/\A(`{3,})json\n.*?\n\1\n\n# /s', $body ) : strpos( $body, '# ' ) === 0 );
		$valid = $code === 200 && stripos( $type, 'text/markdown' ) === 0 && $format;
		return array( 'status' => strlen( $body ) >= 262144 ? 'unable_to_check' : ( $valid ? 'available' : 'failed' ), 'httpStatus' => $code, 'formatValid' => $format, 'contentType' => $type, 'etag' => (string) wp_remote_retrieve_header( $response, 'etag' ), 'bytes' => strlen( $body ), 'limited' => strlen( $body ) >= 262144 );
	}

	public function progress(): array {
		$job = get_option( Catalog::JOB_KEY, array() );
		if ( ! is_array( $job ) || empty( $job ) ) {
			return array( 'status' => 'not_started', 'scanned' => 0 );
		}
		// Never return the potentially large topic projection through AJAX.
		unset( $job['topics'] );
		$job['status'] = ( $job['generation'] ?? '' ) !== $this->catalog->generation() ? 'stale' : ( ! empty( $job['complete'] ) ? 'complete' : 'running' );
		return $job;
	}

	public function start(): array {
		$job = array( 'generation' => $this->catalog->generation(), 'complete' => false, 'after' => 0, 'scanned' => 0, 'exportable' => 0, 'notExportable' => 0, 'inShortMap' => 0, 'descriptionSources' => array(), 'warnings' => array(), 'overrides' => array(), 'topics' => array(), 'startedAt' => gmdate( 'c' ) );
		update_option( Catalog::JOB_KEY, $job, false );
		return $this->progress();
	}

	public function batch(): array {
		// Atomic option lock works across workers, unlike a get/set transient pair.
		$lock = 'llmf_catalog_scan_lock';
		if ( ! add_option( $lock, time(), '', false ) ) {
			if ( (int) get_option( $lock ) < time() - 120 ) {
				delete_option( $lock );
			}
			return $this->progress();
		}
		try {
			$job = get_option( Catalog::JOB_KEY, array() );
			if ( ! is_array( $job ) || ( $job['generation'] ?? null ) !== $this->catalog->generation() ) {
				$this->start();
				$job = get_option( Catalog::JOB_KEY );
			}
			if ( ! empty( $job['complete'] ) ) {
				return $this->progress();
			}
			$generation = $job['generation'];
			Content::anonymous( function () use ( &$job ) {
				$page = $this->catalog->scan( '', (int) $job['after'] );
				$root = ( new Llms( $this->options, false ) )->generated_content();
				$job['scanned'] += $page['scanned'];
				$job['exportable'] += count( $page['posts'] );
				$job['notExportable'] += $page['scanned'] - count( $page['posts'] );
				foreach ( $page['posts'] as $post ) {
					$description = $this->options->get()['content_profile'] === 'enhanced' ? Content::description( $post, $this->options ) : array( 'text' => $this->options->llms_description_for_post( $post ), 'source' => 'legacy', 'warnings' => array() );
					$this->count( $job['descriptionSources'], $description['source'] );
					$render = $this->exporter->inspect( $post );
					foreach ( array_unique( array_merge( $render['warnings'], $description['warnings'] ) ) as $warning ) {
						$this->count( $job['warnings'], $warning );
					}
					$this->count( $job['overrides'], Content::override_state( $post ) );
					$url = ! empty( $this->options->get()['enabled_markdown'] ) ? $this->options->markdown_url_for_post( $post ) : get_permalink( $post );
					if ( strpos( $root, '](' . Markdown::url_destination( $url ) . ')' ) !== false ) {
						$job['inShortMap'] ++;
					}
					foreach ( $this->catalog->taxonomies( $post->post_type ) as $taxonomy ) {
						$terms = get_the_terms( $post, $taxonomy );
						foreach ( is_array( $terms ) ? $terms : array() as $term ) {
							$job['topics'][ $post->post_type ][ $taxonomy ][ $term->term_id ] = true;
						}
					}
				}
				$job['after'] = $page['next'];
				$job['complete'] = $page['next'] === 0;
			} );
			// A concurrent edit makes this batch obsolete; never publish a mixed projection.
			if ( $generation === $this->catalog->generation() ) {
				$job['updatedAt'] = gmdate( 'c' );
				update_option( Catalog::JOB_KEY, $job, false );
			}
			return $this->progress();
		} finally {
			delete_option( $lock );
		}
	}

	private function count( array &$counts, string $key ): void {
		$counts[ $key ] = ( $counts[ $key ] ?? 0 ) + 1;
	}

	public function run_scheduled_batch(): void {
		$opt = $this->options->get();
		if ( empty( $opt['enabled_llms_txt'] ) || $opt['llms_index_mode'] !== 'structured' ) {
			return;
		}
		$progress = $this->batch();
		if ( $progress['status'] !== 'complete' && ! wp_next_scheduled( 'llmf_catalog_scan' ) ) {
			wp_schedule_single_event( time() + 10, 'llmf_catalog_scan' );
		}
	}
}
