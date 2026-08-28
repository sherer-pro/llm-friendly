<?php

namespace LLMFriendly;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin orchestrator.
 */
final class Plugin {
	/**
	 * @var Plugin|null Singleton plugin instance.
	 */
	private static ?Plugin $instance = null;

	/**
	 * @var Options Options service.
	 */
	private Options $options;

	/**
	 * @var Rewrites Rewrite rules service.
	 */
	private Rewrites $rewrites;

	/**
	 * @var Exporter Markdown content exporter.
	 */
	private Exporter $exporter;

	/**
	 * @var Llms llms.txt generator.
	 */
	private Llms $llms;

	/**
	 * @var Admin Admin UI service.
	 */
	private Admin $admin;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( self::$instance instanceof self ) {
			return self::$instance;
		}

		self::$instance = new self();
		self::$instance->boot();

		return self::$instance;
	}

	/**
	 * Bootstrap plugin.
	 *
	 * @return void
	 */
	private function boot(): void {
		$this->options  = new Options();
		$this->rewrites = new Rewrites( $this->options );
		$this->exporter = new Exporter( $this->options );
		$this->llms     = new Llms( $this->options );
		$this->admin    = new Admin( $this->options, $this->llms );

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'template_redirect', array( $this, 'template_redirect' ), 0 );

		add_action( 'wp_head', array( $this, 'output_discovery_links' ), 1 );
		add_filter( 'wp_headers', array( $this, 'filter_response_headers' ) );

		add_action( 'admin_init', array( $this, 'maybe_flush_rewrites' ) );
	}

	/**
	 * Activation hook.
	 *
	 * @return void
	 */
	public static function activate(): void {
		$options = new Options();
		$options->ensure_defaults();

		$rewrites = new Rewrites( $options );
		$rewrites->add_rules();
		flush_rewrite_rules();
	}

	/**
	 * Deactivation hook.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Flush rewrite rules when endpoint-related settings change.
	 *
	 * This is triggered via a transient set during options sanitization.
	 *
	 * @return void
	 */
	public function maybe_flush_rewrites(): void {
		if ( ! is_admin() ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! function_exists( 'get_transient' ) || ! function_exists( 'delete_transient' ) ) {
			return;
		}

		$flag = get_transient( 'llmf_flush_rewrite_rules' );
		if ( ! $flag ) {
			return;
		}

		delete_transient( 'llmf_flush_rewrite_rules' );

		// Ensure rules are registered before flushing.
		$this->rewrites->add_rules();
		flush_rewrite_rules( false );
	}

	/**
	 * Register editor post meta for Markdown override field.
	 *
	 * @return void
	 */
	public function register_editor_meta(): void {
		$opt   = $this->options->get();
		$types = $this->options->selected_post_types( $opt );
		if ( empty( $types ) ) {
			return;
		}

		foreach ( $types as $post_type ) {
			if ( ! $this->options->is_exportable_post_type( $post_type ) ) {
				continue;
			}

			$auth_callback = function ( $allowed = false, $meta_key = '', $post_id = 0 ) {
				$post_id = (int) $post_id;

				return $post_id > 0 && current_user_can( 'edit_post', $post_id );
			};

			register_post_meta(
				$post_type,
				Exporter::META_MD_OVERRIDE,
				array(
					'type'              => 'string',
					'single'            => true,
					'sanitize_callback' => array( $this, 'sanitize_md_override_meta' ),
					'auth_callback'     => $auth_callback,
					'show_in_rest'      => array(
						'schema' => array(
							'type' => 'string',
						),
					),
				)
			);

			register_post_meta(
				$post_type,
				Options::META_LLMS_DESCRIPTION,
				array(
					'type'              => 'string',
					'single'            => true,
					'sanitize_callback' => array( $this, 'sanitize_llms_description_meta' ),
					'auth_callback'     => $auth_callback,
					'show_in_rest'      => array(
						'schema' => array(
							'type'      => 'string',
							'maxLength' => 500,
						),
					),
				)
			);
		}
	}

	/**
	 * Sanitize Markdown override post meta.
	 *
	 * We keep the value as-is for users with unfiltered_html, otherwise run it through wp_kses_post.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return string
	 */
	public function sanitize_md_override_meta( $value ): string {
		return $this->options->sanitize_markdown_override( $value );
	}

	/**
	 * Sanitize llms.txt description post meta.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return string
	 */
	public function sanitize_llms_description_meta( $value ): string {
		return $this->options->sanitize_llms_description( $value );
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->rewrites->add_rules();
		$this->register_editor_meta();
	}

	/**
	 * Serve llms.txt and .md exports.
	 *
	 * @return void
	 */
	public function template_redirect(): void {
		if ( (int) get_query_var( Rewrites::QV_LLMS ) === 1 ) {
			$this->llms->output();
			exit;
		}

		if ( (int) get_query_var( Rewrites::QV_MD ) === 1 ) {
			$opt = $this->options->get();
			if ( empty( $opt['enabled_markdown'] ) ) {
				$this->send_404();
				return;
			}

			$post_type = (string) get_query_var( Rewrites::QV_PT );
			$path      = (string) get_query_var( Rewrites::QV_PATH );

			$post = $this->find_post_by_path( $post_type, $path );

			if ( ! ( $post instanceof WP_Post ) ) {
				$this->send_404();
				return;
			}

			$this->exporter->output_markdown( $post );
			exit;
		}

		if ( $this->should_negotiate_markdown_request() ) {
			global $post;

			$method    = $this->request_method();
			$send_body = $method !== 'HEAD';
			$this->exporter->output_markdown( $post, $send_body );
		}
	}

	/**
	 * Add Accept to Vary when canonical URLs may return Markdown.
	 *
	 * @param array<string,string> $headers WordPress response headers.
	 * @return array<string,string>
	 */
	public function filter_response_headers( array $headers ): array {
		$opt = $this->options->get();
		if ( empty( $opt['enabled_markdown'] ) || empty( $opt['enabled_content_negotiation'] ) ) {
			return $headers;
		}

		$vary_key = '';
		foreach ( array_keys( $headers ) as $key ) {
			if ( is_string( $key ) && strcasecmp( $key, 'Vary' ) === 0 ) {
				$vary_key = $key;
				break;
			}
		}

		if ( $vary_key === '' ) {
			$headers['Vary'] = 'Accept';
			return $headers;
		}

		$tokens = array_filter( array_map( 'trim', explode( ',', (string) $headers[ $vary_key ] ) ), 'strlen' );
		foreach ( $tokens as $token ) {
			if ( $token === '*' || strcasecmp( $token, 'Accept' ) === 0 ) {
				return $headers;
			}
		}

		$tokens[]             = 'Accept';
		$headers[ $vary_key ] = implode( ', ', $tokens );

		return $headers;
	}

	/**
	 * Decide whether the current canonical request should return Markdown.
	 *
	 * @return bool
	 */
	private function should_negotiate_markdown_request(): bool {
		$opt = $this->options->get();
		if ( empty( $opt['enabled_markdown'] ) || empty( $opt['enabled_content_negotiation'] ) ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( ! in_array( $this->request_method(), array( 'GET', 'HEAD' ), true ) ) {
			return false;
		}

		if ( is_feed() || is_preview() || is_attachment() || is_404() || ! is_singular() ) {
			return false;
		}

		if ( ! $this->request_accepts_markdown() ) {
			return false;
		}

		global $post;
		if ( ! ( $post instanceof WP_Post ) ) {
			return false;
		}

		return $this->options->is_selected_post_type( (string) $post->post_type, $opt )
			&& $this->options->can_export_post( $post, 'markdown' );
	}

	/**
	 * Return the normalized HTTP request method.
	 *
	 * @return string
	 */
	private function request_method(): string {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) : 'GET';

		return strtoupper( trim( $method ) );
	}

	/**
	 * Check for an explicit acceptable text/markdown media range.
	 *
	 * Wildcards do not opt a request into Markdown. Invalid quality values make
	 * only that media range unacceptable and do not affect later ranges.
	 *
	 * @return bool
	 */
	private function request_accepts_markdown(): bool {
		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? (string) wp_unslash( $_SERVER['HTTP_ACCEPT'] ) : '';
		$accept = str_replace( array( "\r", "\n" ), '', $accept );
		if ( trim( $accept ) === '' ) {
			return false;
		}

		foreach ( explode( ',', $accept ) as $media_range ) {
			$parts = array_map( 'trim', explode( ';', $media_range ) );
			$type  = array_shift( $parts );
			if ( ! is_string( $type ) || strcasecmp( $type, 'text/markdown' ) !== 0 ) {
				continue;
			}

			$quality = 1.0;
			foreach ( $parts as $parameter ) {
				$pair = array_map( 'trim', explode( '=', $parameter, 2 ) );
				if ( count( $pair ) !== 2 || strcasecmp( $pair[0], 'q' ) !== 0 ) {
					continue;
				}

				$value = trim( $pair[1], " \t\n\r\0\x0B\"'" );
				if ( ! preg_match( '/^(?:0(?:\.\d{0,3})?|1(?:\.0{0,3})?)$/', $value ) ) {
					$quality = 0.0;
					break;
				}

				$quality = (float) $value;
			}

			if ( $quality > 0.0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Send a 404 response.
	 *
	 * @return void
	 */
	private function send_404(): void {
		global $wp_query;

		if ( $wp_query ) {
			$wp_query->set_404();
		}

		status_header( 404 );
		nocache_headers();

		$template = get_404_template();
		if ( $template && file_exists( $template ) ) {
			include $template;
			return;
		}

		echo esc_html__( 'Not Found', 'llm-friendly' );
	}

	/**
	 * Find a post by post type and path.
	 *
	 * Supports hierarchical paths (e.g. "parent/child") via get_page_by_path.
	 *
	 * @param string $post_type
	 * @param string $path
	 *
	 * @return WP_Post|null
	 */
	private function find_post_by_path( string $post_type, string $path ): ?WP_Post {
		$post_type = sanitize_key( (string) $post_type );
		$path      = trim( (string) $path );

		if ( $post_type === '' || $path === '' ) {
			return null;
		}

		$opt = $this->options->get();
		if ( ! $this->options->is_selected_post_type( $post_type, $opt ) ) {
			return null;
		}

		$path = ltrim( $path, '/' );

		$post = get_page_by_path( $path, OBJECT, $post_type );
		if ( $post instanceof WP_Post && $this->options->can_export_post( $post, 'markdown' ) ) {
			return $post;
		}

		return null;
	}

	/**
	 * Add discovery links for llms.txt and supported Markdown exports.
	 *
	 * @return void
	 */
	public function output_discovery_links(): void {
		if ( is_feed() || is_preview() || is_404() ) {
			return;
		}

		$opt = $this->options->get();
		$links = array();

		if ( ! empty( $opt['enabled_llms_txt'] ) ) {
			$llms_url = Markdown::url_destination( home_url( '/llms.txt' ), array( 'http', 'https' ), false );
			if ( $llms_url !== '' ) {
				$links[] = '<link rel="describedby" type="text/markdown" href="' . esc_url( $llms_url ) . '" />';
			}
		}

		if ( is_singular() && ! empty( $opt['enabled_markdown'] ) ) {
			global $post;
			if ( $post instanceof WP_Post
				&& $this->options->is_selected_post_type( (string) $post->post_type, $opt )
				&& $this->options->can_export_post( $post, 'markdown' )
			) {
				$url = $this->options->markdown_url_for_post( $post );
				if ( $url !== '' ) {
					$links[] = '<link rel="alternate" type="text/markdown" href="' . esc_url( $url ) . '" />';
				}
			}
		}

		if ( empty( $links ) ) {
			return;
		}

		echo "\n" . implode( "\n", $links ) . "\n";
	}

	/**
	 * Backward-compatible wrapper for the previous discovery callback name.
	 *
	 * @return void
	 */
	public function output_alternate_markdown_link(): void {
		$this->output_discovery_links();
	}
}
