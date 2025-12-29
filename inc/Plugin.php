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
	 * @var Plugin|null Единственный экземпляр плагина (Singleton).
	 */
	private static ?Plugin $instance = null;

	/**
	 * @var Options Объект работы с настройками.
	 */
	private Options $options;

	/**
	 * @var Rewrites Сервис регистрации rewrite-правил.
	 */
	private Rewrites $rewrites;

	/**
	 * @var Exporter Конвертер контента в Markdown.
	 */
	private Exporter $exporter;

	/**
	 * @var Llms Генератор llms.txt.
	 */
	private Llms $llms;

	/**
	 * @var Admin Админ-интерфейс плагина.
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

		add_action( 'wp_head', array( $this, 'output_alternate_markdown_link' ), 1 );

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
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
		$types = isset( $opt['post_types'] ) && is_array( $opt['post_types'] ) ? $opt['post_types'] : array();
		$types = array_values( array_filter( array_map( 'sanitize_key', $types ) ) );
		if ( empty( $types ) ) {
			return;
		}

		foreach ( $types as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}

			$pto = get_post_type_object( $post_type );
			$cap = ( $pto && isset( $pto->cap ) && isset( $pto->cap->edit_posts ) ) ? (string) $pto->cap->edit_posts : 'edit_posts';

			register_post_meta(
				$post_type,
				Exporter::META_MD_OVERRIDE,
				array(
					'type'              => 'string',
					'single'            => true,
					'sanitize_callback' => array( $this, 'sanitize_md_override_meta' ),
					'auth_callback'     => function () use ( $cap ) {
						return current_user_can( $cap );
					},
					'show_in_rest'      => array(
						'schema' => array(
							'type' => 'string',
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
		$value = is_string( $value ) ? $value : '';
		$value = wp_unslash( $value );

		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$value = wp_kses_post( $value );
		}

		return (string) $value;
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
	 * Load plugin translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
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

		$opt     = $this->options->get();
		$allowed = is_array( $opt['post_types'] ) ? $opt['post_types'] : array();
		if ( ! in_array( $post_type, $allowed, true ) ) {
			return null;
		}

		$path = ltrim( $path, '/' );

		$post = get_page_by_path( $path, OBJECT, $post_type );
		if ( $post instanceof WP_Post && $post->post_status === 'publish' ) {
			// Do not export password-protected content.
			if ( ! empty( $post->post_password ) || post_password_required( $post ) ) {
				return null;
			}

			$can = apply_filters( 'llmf_can_export_post', true, $post, 'markdown' );
			if ( ! $can ) {
				return null;
			}

			// Если запись отмечена как исключенная, не отдаем Markdown-версию.
			if ( $this->options->is_post_excluded( $post ) ) {
				return null;
			}

			return $post;
		}

		return null;
	}

	/**
	 * Add <link rel="alternate" type="text/markdown"> for supported singular posts.
	 *
	 * @return void
	 */
	public function output_alternate_markdown_link(): void {
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

		// Исключенные записи не должны публиковать ссылку на Markdown.
		if ( $this->options->is_post_excluded( $post ) ) {
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
