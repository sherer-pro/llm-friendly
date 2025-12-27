<?php

namespace LLM_Friendly;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin orchestrator.
 */
final class Plugin {
	/**
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * @var Options
	 */
	private $options;

	/**
	 * @var Rewrites
	 */
	private $rewrites;

	/**
	 * @var Exporter
	 */
	private $exporter;

	/**
	 * @var Llms
	 */
	private $llms;

	/**
	 * @var Admin
	 */
	private $admin;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
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
	private function boot() {
		$this->options  = new Options();
		$this->rewrites = new Rewrites( $this->options );
		$this->exporter = new Exporter( $this->options );
		$this->llms     = new Llms( $this->options );
		$this->admin    = new Admin( $this->options, $this->llms );

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'template_redirect', array( $this, 'template_redirect' ), 0 );

		add_action( 'wp_head', array( $this, 'output_alternate_markdown_link' ), 1 );

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Activation hook.
	 *
	 * @return void
	 */
	public static function activate() {
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
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public function init() {
		$this->rewrites->add_rules();
	}

	/**
	 * Load plugin translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'llm-friendly',
			false,
			dirname( plugin_basename( LLMF_FILE ) ) . '/languages'
		);
	}

	/**
	 * Serve llms.txt and .md exports.
	 *
	 * @return void
	 */
	public function template_redirect() {
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
	}

	/**
	 * Send a 404 response.
	 *
	 * @return void
	 */
	private function send_404() {
		global $wp_query;

		if ( $wp_query ) {
			$wp_query->set_404();
		}

		status_header( 404 );
		nocache_headers();

		// Try to render theme 404 template if available.
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
	private function find_post_by_path( $post_type, $path ) {
		$post_type = sanitize_key( (string) $post_type );
		$path      = trim( (string) $path );

		if ( $post_type === '' || $path === '' ) {
			return null;
		}

		$opt     = $this->options->get();
		$allowed = is_array( $opt['post_types'] ) ? $opt['post_types'] : array();
		if ( ! in_array( $post_type, $allowed, true ) ) {
			return null;
		}

		$path = ltrim( $path, '/' );

		$post = get_page_by_path( $path, OBJECT, $post_type );
		if ( $post instanceof WP_Post && $post->post_status === 'publish' ) {
			return $post;
		}

		return null;
	}

	/**
	 * Add <link rel="alternate" type="text/markdown"> for supported singular posts.
	 *
	 * @return void
	 */
	public function output_alternate_markdown_link() {
		if ( ! is_singular() ) {
			return;
		}

		$opt = $this->options->get();

		if ( empty( $opt['enabled_markdown'] ) ) {
			return;
		}

		global $post;
		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}

		$allowed = is_array( $opt['post_types'] ) ? $opt['post_types'] : array();
		if ( ! in_array( $post->post_type, $allowed, true ) ) {
			return;
		}

		$base = $this->options->sanitize_base_path( (string) $opt['base_path'] );
		$path = $this->options->post_path( $post );

		if ( $base === '' || $path === '' ) {
			return;
		}

		$url = home_url( '/' . $base . '/' . $post->post_type . '/' . $path . '.md' );
		echo "\n" . '<link rel="alternate" type="text/markdown" href="' . esc_url( $url ) . '" />' . "\n";
	}
}
