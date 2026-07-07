<?php

namespace LLMFriendly;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * WordPress admin settings UI (Settings API).
 *
 * Notes:
 * - The settings page must contain only ONE settings <form> posting to options.php.
 * - The "Regenerate llms.txt" action is rendered as a separate form (admin-post.php)
 *   outside of the main settings form to avoid invalid nested forms.
 */
final class Admin {
	/**
	 * @var Options
	 */
	private $options;

	/**
	 * @var Llms
	 */
	private $llms;

	/**
	 * Admin constructor.
	 *
	 * @param Options $options Options service.
	 * @param Llms    $llms    llms.txt service.
	 */
	public function __construct($options, $llms) {
		$this->options = $options;
		$this->llms    = $llms;

		if (is_admin()) {
			add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
			add_action('admin_menu', array($this, 'admin_menu'));
			add_action('admin_init', array($this, 'admin_init'));
			add_action('add_meta_boxes', array($this, 'maybe_add_md_override_metabox'), 10, 2);
			add_action('save_post', array($this, 'save_md_override_metabox'), 10, 2);
			add_action('admin_post_llmf_regenerate_llms', array($this, 'handle_regenerate_llms'));
			add_action('wp_ajax_llmf_search_posts', array($this, 'ajax_search_posts'));
			add_action('wp_ajax_llmf_preview_llms', array($this, 'ajax_preview_llms'));
			add_action('wp_ajax_llmf_save_settings', array($this, 'ajax_save_settings'));
		}
	}

	/**
	 * Register settings page.
	 *
	 * @return void
	 */
	public function admin_menu() {
		add_options_page(
			'LLM Friendly',
			'LLM Friendly',
			'manage_options',
			'llm-friendly',
			array($this, 'render_page')
		);
	}

	/**
	 * Register settings sections and fields.
	 *
	 * @return void
	 */
	public function admin_init() {
		register_setting(
			'llmf',
			Options::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'default'           => $this->options->defaults(),
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			'llmf_general',
			__('General', 'llm-friendly'),
			'__return_null',
			'llm-friendly'
		);

		add_settings_field(
			'enabled_markdown',
			__('Enable Markdown exports', 'llm-friendly'),
			array($this, 'field_enabled_markdown'),
			'llm-friendly',
			'llmf_general',
			array( 'label_for' => $this->option_field_id( 'enabled_markdown' ) )
		);

		add_settings_field(
			'md_send_noindex',
			__('Send "noindex" header for Markdown exports', 'llm-friendly'),
			array($this, 'field_md_noindex'),
			'llm-friendly',
			'llmf_general',
			array( 'label_for' => $this->option_field_id( 'md_send_noindex' ) )
		);

		add_settings_field(
			'base_path',
			__('Base path for Markdown exports', 'llm-friendly'),
			array($this, 'field_base_path'),
			'llm-friendly',
			'llmf_general',
			array( 'label_for' => $this->option_field_id( 'base_path' ) )
		);

		add_settings_field(
			'post_types',
			__('Post types to include', 'llm-friendly'),
			array($this, 'field_post_types'),
			'llm-friendly',
			'llmf_general'
		);

		add_settings_section(
			'llmf_exclusions',
			__( 'Exclusions', 'llm-friendly' ),
			'__return_null',
			'llm-friendly'
		);

		add_settings_field(
			'excluded_posts',
			__( 'Excluded items', 'llm-friendly' ),
			array( $this, 'field_excluded_posts' ),
			'llm-friendly',
			'llmf_exclusions'
		);

		add_settings_section(
			'llmf_llms',
			__('llms.txt', 'llm-friendly'),
			'__return_null',
			'llm-friendly'
		);

		add_settings_field(
			'enabled_llms_txt',
			__('Enable llms.txt', 'llm-friendly'),
			array($this, 'field_enabled_llms_txt'),
			'llm-friendly',
			'llmf_llms',
			array( 'label_for' => $this->option_field_id( 'enabled_llms_txt' ) )
		);

		add_settings_field(
			'llms_send_noindex',
			__('Send "noindex" header for llms.txt', 'llm-friendly'),
			array($this, 'field_llms_noindex'),
			'llm-friendly',
			'llmf_llms',
			array( 'label_for' => $this->option_field_id( 'llms_send_noindex' ) )
		);

		add_settings_field(
			'llms_regen_mode',
			__('Regeneration mode', 'llm-friendly'),
			array($this, 'field_llms_regen_mode'),
			'llm-friendly',
			'llmf_llms',
			array( 'label_for' => $this->option_field_id( 'llms_regen_mode' ) )
		);

		add_settings_field(
			'llms_recent_limit',
			__('Items per post type', 'llm-friendly'),
			array($this, 'field_llms_recent_limit'),
			'llm-friendly',
			'llmf_llms',
			array( 'label_for' => $this->option_field_id( 'llms_recent_limit' ) )
		);

		add_settings_field(
			'llms_show_excerpt',
			__( 'Show excerpt', 'llm-friendly' ),
			array( $this, 'field_llms_show_excerpt' ),
			'llm-friendly',
			'llmf_llms',
			array( 'label_for' => $this->option_field_id( 'llms_show_excerpt' ) )
		);

		add_settings_field(
			'sitemap_url',
			__('Sitemap URL', 'llm-friendly'),
			array($this, 'field_sitemap_url'),
			'llm-friendly',
			'llmf_llms',
			array( 'label_for' => $this->option_field_id( 'sitemap_url' ) )
		);

		add_settings_field(
			'llms_custom_markdown',
			__('Custom markdown block', 'llm-friendly'),
			array($this, 'field_llms_custom_markdown'),
			'llm-friendly',
			'llmf_llms',
			array( 'label_for' => $this->option_field_id( 'llms_custom_markdown' ) )
		);

		add_settings_field(
			'llms_essential_links',
			__( 'Essential links', 'llm-friendly' ),
			array( $this, 'field_llms_essential_links' ),
			'llm-friendly',
			'llmf_llms',
			array( 'label_for' => $this->option_field_id( 'llms_essential_links' ) )
		);

		add_settings_section(
			'llmf_overrides',
			__('Site meta overrides', 'llm-friendly'),
			'__return_null',
			'llm-friendly'
		);

		add_settings_field(
			'site_title_override',
			__('Site title override', 'llm-friendly'),
			array($this, 'field_site_title_override'),
			'llm-friendly',
			'llmf_overrides',
			array( 'label_for' => $this->option_field_id( 'site_title_override' ) )
		);

		add_settings_field(
			'site_description_override',
			__('Site description override', 'llm-friendly'),
			array($this, 'field_site_description_override'),
			'llm-friendly',
			'llmf_overrides',
			array( 'label_for' => $this->option_field_id( 'site_description_override' ) )
		);

		add_settings_field(
			'site_author_override',
			__( 'Author override', 'llm-friendly' ),
			array( $this, 'field_site_author_override' ),
			'llm-friendly',
			'llmf_overrides',
			array( 'label_for' => $this->option_field_id( 'site_author_override' ) )
		);

		add_settings_section(
			'llmf_crawlers',
			__( 'AI crawler diagnostics', 'llm-friendly' ),
			'__return_null',
			'llm-friendly'
		);

		add_settings_field(
			'crawler_diagnostics',
			__( 'Crawler recommendations', 'llm-friendly' ),
			array( $this, 'field_crawler_diagnostics' ),
			'llm-friendly',
			'llmf_crawlers'
		);
	}

	/**
	 * Sanitize callback for Settings API.
	 *
	 * @param array<string,mixed> $input Raw input.
	 * @return array<string,mixed>
	 */
	public function sanitize_options($input) {
		return $this->options->sanitize($input);
	}

	/**
	 * Return selected post types after applying canonical option sanitization.
	 *
	 * @return array<int,string> Selected post type keys.
	 */
	private function selected_post_types(): array {
		return $this->options->selected_post_types();
	}

	/**
	 * Return a Unicode-aware string length.
	 *
	 * @param string $value Text value.
	 * @return int Character length.
	 */
	private function text_length( string $value ): int {
		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( $value, 'UTF-8' );
		}

		if ( preg_match_all( '/./us', $value, $matches ) ) {
			return count( $matches[0] );
		}

		return strlen( $value );
	}

	/**
	 * Return a stable DOM id for an option control.
	 *
	 * @param string $key Option key.
	 * @return string Control id.
	 */
	private function option_field_id( string $key ): string {
		return 'llmf-' . str_replace( '_', '-', sanitize_key( $key ) );
	}

	/**
	 * Return a stable DOM id for an option description.
	 *
	 * @param string $key Option key.
	 * @return string Description id.
	 */
	private function option_description_id( string $key ): string {
		return $this->option_field_id( $key ) . '-description';
	}

	/**
	 * Render a standard field description and expose it for assistive tech.
	 *
	 * @param string $key         Option key.
	 * @param string $description Description text.
	 * @return void
	 */
	private function render_field_description( string $key, string $description ): void {
		echo '<p class="description llmf-field-description" id="' . esc_attr( $this->option_description_id( $key ) ) . '">' . esc_html( $description ) . '</p>';
	}

	/**
	 * Return settings navigation items.
	 *
	 * @return array<string,string>
	 */
	private function settings_nav_items(): array {
		return array(
			'llmf-overview'    => __( 'Overview', 'llm-friendly' ),
			'llmf-markdown'    => __( 'Markdown exports', 'llm-friendly' ),
			'llmf-scope'       => __( 'Content scope', 'llm-friendly' ),
			'llmf-exclusions'  => __( 'Exclusions', 'llm-friendly' ),
			'llmf-llms'        => __( 'llms.txt', 'llm-friendly' ),
			'llmf-metadata'    => __( 'Site metadata', 'llm-friendly' ),
			'llmf-crawlers'    => __( 'AI crawler diagnostics', 'llm-friendly' ),
			'llmf-maintenance' => __( 'Maintenance', 'llm-friendly' ),
		);
	}

	/**
	 * Return the public llms.txt URL.
	 *
	 * @return string
	 */
	private function llms_url(): string {
		return home_url( '/llms.txt' );
	}

	/**
	 * Return the Markdown endpoint URL pattern.
	 *
	 * @return string
	 */
	private function markdown_url_pattern(): string {
		$opt  = $this->options->get();
		$base = isset( $opt['base_path'] ) ? $this->options->sanitize_base_path( (string) $opt['base_path'] ) : 'llm';

		return home_url( '/' . trim( $base, '/' ) . '/{post_type}/{path}.md' );
	}

	/**
	 * Render a copy button for endpoint helpers.
	 *
	 * @param string $label Button label.
	 * @param string $value Value to copy.
	 * @param string $class Extra CSS classes.
	 * @return void
	 */
	private function render_copy_button( string $label, string $value, string $class = 'button button-secondary' ): void {
		echo '<button type="button" class="' . esc_attr( $class ) . '" data-llmf-copy="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</button>';
	}

	/**
	 * Render the page hero and endpoint actions.
	 *
	 * @return void
	 */
	private function render_app_header(): void {
		$llms_url         = $this->llms_url();
		$markdown_pattern = $this->markdown_url_pattern();

		echo '<div class="llmf-app-header">';
		echo '<div class="llmf-app-header__main">';
		echo '<p class="llmf-app-header__eyebrow">' . esc_html__( 'LLM access settings', 'llm-friendly' ) . '</p>';
		echo '<h1>LLM Friendly</h1>';
		echo '<p class="llmf-app-header__summary">' . esc_html__( 'Control Markdown exports, llms.txt, AI crawler guidance, and exclusions from one place.', 'llm-friendly' ) . '</p>';
		echo '</div>';
		echo '<div class="llmf-app-header__actions">';
		echo '<a class="button button-primary" href="' . esc_url( $llms_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open llms.txt', 'llm-friendly' ) . '</a>';
		$this->render_copy_button( __( 'Copy llms.txt URL', 'llm-friendly' ), $llms_url );
		$this->render_copy_button( __( 'Copy Markdown pattern', 'llm-friendly' ), $markdown_pattern );
		echo '</div>';
		echo '<div class="screen-reader-text" id="llmf-copy-status" role="status" aria-live="polite" aria-atomic="true"></div>';
		echo '</div>';
	}

	/**
	 * Render the sticky page navigation.
	 *
	 * @return void
	 */
	private function render_app_nav(): void {
		echo '<nav class="llmf-settings-nav" aria-label="' . esc_attr__( 'LLM Friendly settings sections', 'llm-friendly' ) . '">';
		foreach ( $this->settings_nav_items() as $id => $label ) {
			echo '<a class="llmf-settings-nav__link" href="#' . esc_attr( $id ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	/**
	 * Open a panel section.
	 *
	 * @param string $id          Panel id.
	 * @param string $title       Panel title.
	 * @param string $description Optional panel description.
	 * @return void
	 */
	private function render_panel_open( string $id, string $title, string $description = '' ): void {
		$title_id = $id . '-title';

		echo '<section class="llmf-panel" id="' . esc_attr( $id ) . '" aria-labelledby="' . esc_attr( $title_id ) . '">';
		echo '<div class="llmf-panel__header">';
		echo '<h2 id="' . esc_attr( $title_id ) . '">' . esc_html( $title ) . '</h2>';
		if ( $description !== '' ) {
			echo '<p>' . esc_html( $description ) . '</p>';
		}
		echo '</div>';
		echo '<div class="llmf-panel__body">';
	}

	/**
	 * Close a panel section.
	 *
	 * @return void
	 */
	private function render_panel_close(): void {
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Render one settings field row in the custom panel layout.
	 *
	 * @param string   $title    Field title. Empty for self-labeled controls.
	 * @param callable $callback Field renderer.
	 * @param string   $for      Optional control id.
	 * @return void
	 */
	private function render_setting_field( string $title, callable $callback, string $for = '' ): void {
		$class = $title === '' ? 'llmf-field llmf-field--full' : 'llmf-field';

		echo '<div class="' . esc_attr( $class ) . '">';
		if ( $title !== '' ) {
			echo '<div class="llmf-field__meta">';
			if ( $for !== '' ) {
				echo '<label class="llmf-field__label" for="' . esc_attr( $for ) . '">' . esc_html( $title ) . '</label>';
			} else {
				echo '<div class="llmf-field__label">' . esc_html( $title ) . '</div>';
			}
			echo '</div>';
		}
		echo '<div class="llmf-field__control">';
		call_user_func( $callback );
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render a status badge.
	 *
	 * @param string $label Badge label.
	 * @param string $tone  Badge tone.
	 * @return void
	 */
	private function render_badge( string $label, string $tone = 'neutral' ): void {
		echo '<span class="llmf-badge llmf-badge--' . esc_attr( $tone ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Return admin cache status for the overview.
	 *
	 * @param array<string,mixed> $opt Current options.
	 * @return string Cache status.
	 */
	private function cache_status_from_options( array $opt ): string {
		if ( empty( $opt['enabled_llms_txt'] ) ) {
			return 'disabled';
		}

		$content = isset( $opt['llms_cache'] ) ? trim( (string) $opt['llms_cache'] ) : '';
		$hash    = isset( $opt['llms_cache_settings_hash'] ) ? (string) $opt['llms_cache_settings_hash'] : '';

		if ( $content === '' || $hash === '' ) {
			return 'not_cached';
		}

		$current = $this->llms_settings_hash_from_options( $opt );
		if ( ! hash_equals( $hash, $current ) ) {
			return 'needs_regeneration';
		}

		return 'cached';
	}

	/**
	 * Build the same settings hash used by the llms.txt cache.
	 *
	 * @param array<string,mixed> $settings Plugin options.
	 * @return string Settings hash.
	 */
	private function llms_settings_hash_from_options( array $settings ): string {
		$subset = array(
			'enabled_markdown'          => ! empty( $settings['enabled_markdown'] ) ? 1 : 0,
			'enabled_llms_txt'          => ! empty( $settings['enabled_llms_txt'] ) ? 1 : 0,
			'base_path'                 => isset( $settings['base_path'] ) ? (string) $settings['base_path'] : '',
			'post_types'                => $this->options->selected_post_types( $settings ),
			'llms_recent_limit'         => isset( $settings['llms_recent_limit'] ) ? (int) $settings['llms_recent_limit'] : 0,
			'site_title_override'       => isset( $settings['site_title_override'] ) ? (string) $settings['site_title_override'] : '',
			'site_description_override' => isset( $settings['site_description_override'] ) ? (string) $settings['site_description_override'] : '',
			'sitemap_url'               => isset( $settings['sitemap_url'] ) ? (string) $settings['sitemap_url'] : '',
			'llms_custom_markdown'      => isset( $settings['llms_custom_markdown'] ) ? (string) $settings['llms_custom_markdown'] : '',
			'llms_essential_links'      => isset( $settings['llms_essential_links'] ) ? (string) $settings['llms_essential_links'] : '',
			'llms_show_excerpt'         => ! empty( $settings['llms_show_excerpt'] ) ? 1 : 0,
			'excluded_posts'            => isset( $settings['excluded_posts'] ) && is_array( $settings['excluded_posts'] ) ? $settings['excluded_posts'] : array(),
		);

		$encoded = wp_json_encode( $subset );
		if ( is_string( $encoded ) && $encoded !== '' ) {
			return sha1( $encoded );
		}

		return sha1( serialize( $subset ) );
	}

	/**
	 * Return translated cache status label.
	 *
	 * @param string $status Cache status.
	 * @return string Label.
	 */
	private function cache_status_label( string $status ): string {
		if ( $status === 'cached' ) {
			return __( 'Cached', 'llm-friendly' );
		}
		if ( $status === 'disabled' ) {
			return __( 'Disabled', 'llm-friendly' );
		}
		if ( $status === 'needs_regeneration' ) {
			return __( 'Needs regeneration', 'llm-friendly' );
		}

		return __( 'Not cached', 'llm-friendly' );
	}

	/**
	 * Return tone for a cache status.
	 *
	 * @param string $status Cache status.
	 * @return string Tone.
	 */
	private function cache_status_tone( string $status ): string {
		if ( $status === 'cached' ) {
			return 'success';
		}
		if ( $status === 'disabled' ) {
			return 'muted';
		}
		if ( $status === 'needs_regeneration' ) {
			return 'warning';
		}

		return 'warning';
	}

	/**
	 * Count excluded items across all post types.
	 *
	 * @param array<string,mixed> $opt Current options.
	 * @return int Excluded item count.
	 */
	private function excluded_total( array $opt ): int {
		$total = 0;
		$map   = isset( $opt['excluded_posts'] ) && is_array( $opt['excluded_posts'] ) ? $opt['excluded_posts'] : array();
		foreach ( $map as $ids ) {
			if ( is_array( $ids ) ) {
				$total += count( $ids );
			}
		}

		return $total;
	}

	/**
	 * Render one overview card.
	 *
	 * @param string $title       Card title.
	 * @param string $value       Main value.
	 * @param string $description Supporting text.
	 * @param string $badge       Badge label.
	 * @param string $tone        Badge tone.
	 * @return void
	 */
	private function render_status_card( string $title, string $value, string $description, string $badge, string $tone ): void {
		echo '<div class="llmf-status-card">';
		echo '<div class="llmf-status-card__top">';
		echo '<h3>' . esc_html( $title ) . '</h3>';
		$this->render_badge( $badge, $tone );
		echo '</div>';
		echo '<p class="llmf-status-card__value">' . esc_html( $value ) . '</p>';
		echo '<p class="llmf-status-card__description">' . esc_html( $description ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render the overview panel.
	 *
	 * @return void
	 */
	private function render_overview_panel(): void {
		$opt              = $this->options->get();
		$selected         = $this->selected_post_types();
		$excluded_total   = $this->excluded_total( $opt );
		$cache_status     = $this->cache_status_from_options( $opt );
		$markdown_enabled = ! empty( $opt['enabled_markdown'] );
		$llms_enabled     = ! empty( $opt['enabled_llms_txt'] );
		$regen_mode       = isset( $opt['llms_regen_mode'] ) && (string) $opt['llms_regen_mode'] === 'manual' ? 'manual' : 'auto';
		$md_noindex       = ! empty( $opt['md_send_noindex'] );
		$llms_noindex     = ! empty( $opt['llms_send_noindex'] );
		$sitemap_url      = $this->options->sitemap_absolute_url();

		$this->render_panel_open(
			'llmf-overview',
			__( 'Overview', 'llm-friendly' ),
			__( 'Current saved state for public AI-readable endpoints and export coverage.', 'llm-friendly' )
		);

		echo '<div class="llmf-status-grid">';
		$this->render_status_card(
			__( 'Markdown exports', 'llm-friendly' ),
			$markdown_enabled ? __( 'Enabled', 'llm-friendly' ) : __( 'Disabled', 'llm-friendly' ),
			$this->markdown_url_pattern(),
			$markdown_enabled ? __( 'Enabled', 'llm-friendly' ) : __( 'Disabled', 'llm-friendly' ),
			$markdown_enabled ? 'success' : 'muted'
		);
		$this->render_status_card(
			__( 'llms.txt', 'llm-friendly' ),
			$llms_enabled ? __( 'Published', 'llm-friendly' ) : __( 'Disabled', 'llm-friendly' ),
			$this->llms_url(),
			$llms_enabled ? __( 'Enabled', 'llm-friendly' ) : __( 'Disabled', 'llm-friendly' ),
			$llms_enabled ? 'success' : 'muted'
		);
		$this->render_status_card(
			__( 'Content scope', 'llm-friendly' ),
			sprintf(
				/* translators: %d: Number of selected post types. */
				_n( '%d post type', '%d post types', count( $selected ), 'llm-friendly' ),
				count( $selected )
			),
			sprintf(
				/* translators: %d: Number of excluded items. */
				_n( '%d excluded item', '%d excluded items', $excluded_total, 'llm-friendly' ),
				$excluded_total
			),
			count( $selected ) > 0 ? __( 'Ready', 'llm-friendly' ) : __( 'Needs setup', 'llm-friendly' ),
			count( $selected ) > 0 ? 'success' : 'warning'
		);
		$this->render_status_card(
			__( 'Cache', 'llm-friendly' ),
			$this->cache_status_label( $cache_status ),
			$regen_mode === 'manual' ? __( 'Manual regeneration mode', 'llm-friendly' ) : __( 'Auto regeneration mode', 'llm-friendly' ),
			$this->cache_status_label( $cache_status ),
			$this->cache_status_tone( $cache_status )
		);
		$this->render_status_card(
			__( 'Regeneration', 'llm-friendly' ),
			$regen_mode === 'manual' ? __( 'Manual only', 'llm-friendly' ) : __( 'Automatic', 'llm-friendly' ),
			$regen_mode === 'manual' ? __( 'Use Maintenance to rebuild llms.txt.', 'llm-friendly' ) : __( 'Cache rebuilds after publish/update events.', 'llm-friendly' ),
			$regen_mode === 'manual' ? __( 'Manual', 'llm-friendly' ) : __( 'Auto', 'llm-friendly' ),
			$regen_mode === 'manual' ? 'neutral' : 'success'
		);
		$this->render_status_card(
			__( 'Noindex headers', 'llm-friendly' ),
			$md_noindex || $llms_noindex ? __( 'Configured', 'llm-friendly' ) : __( 'Indexable', 'llm-friendly' ),
			sprintf(
				/* translators: 1: Markdown endpoint indexability, 2: llms.txt indexability. */
				__( 'Markdown: %1$s. llms.txt: %2$s.', 'llm-friendly' ),
				$md_noindex ? __( 'noindex', 'llm-friendly' ) : __( 'indexable', 'llm-friendly' ),
				$llms_noindex ? __( 'noindex', 'llm-friendly' ) : __( 'indexable', 'llm-friendly' )
			),
			$md_noindex || $llms_noindex ? __( 'Noindex', 'llm-friendly' ) : __( 'Indexable', 'llm-friendly' ),
			$md_noindex || $llms_noindex ? 'warning' : 'success'
		);
		$this->render_status_card(
			__( 'Sitemap', 'llm-friendly' ),
			$sitemap_url,
			__( 'Used as the sitemap reference in generated llms.txt.', 'llm-friendly' ),
			__( 'Configured', 'llm-friendly' ),
			'success'
		);
		echo '</div>';

		echo '<div class="llmf-endpoint-strip">';
		echo '<div>';
		echo '<span class="llmf-endpoint-strip__label">' . esc_html__( 'llms.txt URL', 'llm-friendly' ) . '</span>';
		echo '<code>' . esc_html( $this->llms_url() ) . '</code>';
		echo '</div>';
		$this->render_copy_button( __( 'Copy', 'llm-friendly' ), $this->llms_url(), 'button button-secondary llmf-copy-small' );
		echo '</div>';

		$this->render_panel_close();
	}

	/**
	 * Render Markdown export settings.
	 *
	 * @return void
	 */
	private function render_markdown_panel(): void {
		$this->render_panel_open(
			'llmf-markdown',
			__( 'Markdown exports', 'llm-friendly' ),
			__( 'Configure per-item .md endpoints and their indexing behavior.', 'llm-friendly' )
		);
		$this->render_setting_field( '', array( $this, 'field_enabled_markdown' ) );
		$this->render_setting_field( '', array( $this, 'field_md_noindex' ) );
		$this->render_setting_field( __( 'Base path for Markdown exports', 'llm-friendly' ), array( $this, 'field_base_path' ), $this->option_field_id( 'base_path' ) );
		echo '<div class="llmf-inline-preview" id="llmf-markdown-pattern-preview">';
		echo '<span>' . esc_html__( 'Current pattern', 'llm-friendly' ) . '</span>';
		echo '<code data-llmf-markdown-pattern="' . esc_attr( home_url( '/' ) ) . '">' . esc_html( $this->markdown_url_pattern() ) . '</code>';
		$this->render_copy_button( __( 'Copy pattern', 'llm-friendly' ), $this->markdown_url_pattern(), 'button button-secondary llmf-copy-small' );
		echo '</div>';
		$this->render_panel_close();
	}

	/**
	 * Render content scope settings.
	 *
	 * @return void
	 */
	private function render_scope_panel(): void {
		$this->render_panel_open(
			'llmf-scope',
			__( 'Content scope', 'llm-friendly' ),
			__( 'Choose which public content types are exported and listed for AI readers.', 'llm-friendly' )
		);
		$this->render_setting_field( __( 'Post types to include', 'llm-friendly' ), array( $this, 'field_post_types' ) );
		$this->render_panel_close();
	}

	/**
	 * Render exclusion settings.
	 *
	 * @return void
	 */
	private function render_exclusions_panel(): void {
		$this->render_panel_open(
			'llmf-exclusions',
			__( 'Exclusions', 'llm-friendly' ),
			__( 'Exclude individual items from llms.txt and Markdown exports without changing their WordPress visibility.', 'llm-friendly' )
		);
		$this->field_excluded_posts();
		$this->render_panel_close();
	}

	/**
	 * Render llms.txt settings and preview.
	 *
	 * @return void
	 */
	private function render_llms_panel(): void {
		$this->render_panel_open(
			'llmf-llms',
			__( 'llms.txt', 'llm-friendly' ),
			__( 'Control the public llms.txt index, content density, and generated helper sections.', 'llm-friendly' )
		);
		$this->render_setting_field( '', array( $this, 'field_enabled_llms_txt' ) );
		$this->render_setting_field( '', array( $this, 'field_llms_noindex' ) );
		$this->render_setting_field( __( 'Regeneration mode', 'llm-friendly' ), array( $this, 'field_llms_regen_mode' ) );
		$this->render_setting_field( __( 'Items per post type', 'llm-friendly' ), array( $this, 'field_llms_recent_limit' ), $this->option_field_id( 'llms_recent_limit' ) );
		$this->render_setting_field( '', array( $this, 'field_llms_show_excerpt' ) );
		$this->render_setting_field( __( 'Sitemap URL', 'llm-friendly' ), array( $this, 'field_sitemap_url' ), $this->option_field_id( 'sitemap_url' ) );
		$this->render_setting_field( __( 'Custom Markdown block', 'llm-friendly' ), array( $this, 'field_llms_custom_markdown' ), $this->option_field_id( 'llms_custom_markdown' ) );
		$this->render_setting_field( __( 'Essential links', 'llm-friendly' ), array( $this, 'field_llms_essential_links' ), $this->option_field_id( 'llms_essential_links' ) );
		$this->render_preview_panel();
		$this->render_panel_close();
	}

	/**
	 * Render the generated llms.txt preview controls.
	 *
	 * @return void
	 */
	private function render_preview_panel(): void {
		echo '<div class="llmf-preview" id="llmf-preview">';
		echo '<div class="llmf-preview__header">';
		echo '<div>';
		echo '<h3>' . esc_html__( 'Generated preview', 'llm-friendly' ) . '</h3>';
		echo '<p>' . esc_html__( 'Preview uses saved settings and never updates the cache.', 'llm-friendly' ) . '</p>';
		echo '</div>';
		echo '<div class="llmf-preview__actions">';
		echo '<button type="button" class="button button-secondary" data-llmf-preview-load>' . esc_html__( 'Load / refresh preview', 'llm-friendly' ) . '</button>';
		echo '<button type="button" class="button button-secondary" data-llmf-preview-copy disabled="disabled">' . esc_html__( 'Copy content', 'llm-friendly' ) . '</button>';
		echo '<a class="button button-secondary" href="' . esc_url( $this->llms_url() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open llms.txt', 'llm-friendly' ) . '</a>';
		echo '</div>';
		echo '</div>';
		echo '<p class="llmf-preview__dirty" data-llmf-preview-dirty hidden>' . esc_html__( 'Preview uses saved settings. Save changes to preview edits.', 'llm-friendly' ) . '</p>';
		echo '<div class="llmf-preview__meta" data-llmf-preview-meta></div>';
		echo '<pre class="llmf-preview__content" tabindex="0" data-llmf-preview-content>' . esc_html__( 'Load the preview to inspect generated llms.txt content.', 'llm-friendly' ) . '</pre>';
		echo '<div class="screen-reader-text" data-llmf-preview-status role="status" aria-live="polite" aria-atomic="true"></div>';
		echo '</div>';
	}

	/**
	 * Render site metadata fields.
	 *
	 * @return void
	 */
	private function render_metadata_panel(): void {
		$this->render_panel_open(
			'llmf-metadata',
			__( 'Site metadata', 'llm-friendly' ),
			__( 'Override the site-level metadata used in generated Markdown and llms.txt output.', 'llm-friendly' )
		);
		$this->render_setting_field( __( 'Site title override', 'llm-friendly' ), array( $this, 'field_site_title_override' ), $this->option_field_id( 'site_title_override' ) );
		$this->render_setting_field( __( 'Site description override', 'llm-friendly' ), array( $this, 'field_site_description_override' ), $this->option_field_id( 'site_description_override' ) );
		$this->render_setting_field( __( 'Author override', 'llm-friendly' ), array( $this, 'field_site_author_override' ), $this->option_field_id( 'site_author_override' ) );
		$this->render_panel_close();
	}

	/**
	 * Render AI crawler diagnostics.
	 *
	 * @return void
	 */
	private function render_crawlers_panel(): void {
		$this->render_panel_open(
			'llmf-crawlers',
			__( 'AI crawler diagnostics', 'llm-friendly' ),
			__( 'Review indexability and crawler policy decisions. This plugin never edits robots.txt automatically.', 'llm-friendly' )
		);
		$this->field_crawler_diagnostics();
		$this->render_panel_close();
	}

	/**
	 * Render the separate maintenance form.
	 *
	 * @return void
	 */
	private function render_maintenance_panel(): void {
		$this->render_panel_open(
			'llmf-maintenance',
			__( 'Maintenance', 'llm-friendly' ),
			__( 'Run manual actions that are separate from settings save.', 'llm-friendly' )
		);
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="llmf-maintenance-form">';
		echo '<input type="hidden" name="action" value="llmf_regenerate_llms" />';
		wp_nonce_field( 'llmf_regenerate_llms', 'llmf_regenerate_nonce' );
		echo '<button type="submit" class="button button-secondary" id="llmf-regenerate-submit" name="llmf_regenerate_submit">' . esc_html__( 'Regenerate llms.txt now', 'llm-friendly' ) . '</button>';
		echo '</form>';
		echo '<p class="description">' . esc_html__( 'Rebuilds cached llms.txt immediately.', 'llm-friendly' ) . '</p>';
		$this->render_panel_close();
	}

	/**
	 * Return display title for an admin post picker item.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string Non-empty title.
	 */
	private function post_picker_title( \WP_Post $post ): string {
		$title = get_the_title( $post );

		return $title !== '' ? (string) $title : sprintf(
			/* translators: %d: Post ID. */
			__( 'Item #%d', 'llm-friendly' ),
			$post->ID
		);
	}

	/**
	 * Render a checkbox bound to a boolean plugin option.
	 *
	 * @param string $key         Option key.
	 * @param string $label       Field label.
	 * @param string $description Field description.
	 * @return void
	 */
	private function render_option_checkbox( string $key, string $label, string $description = '' ): void {
		$opt     = $this->options->get();
		$v       = ! empty( $opt[ $key ] ) ? 1 : 0;
		$id      = $this->option_field_id( $key );
		$desc_id = $description !== '' ? $this->option_description_id( $key ) : '';

		echo '<label class="llmf-switch-field" for="' . esc_attr( $id ) . '">';
		echo '<input type="checkbox" class="llmf-switch-field__input" id="' . esc_attr( $id ) . '" name="' . esc_attr( Options::OPTION_KEY ) . '[' . esc_attr( $key ) . ']" value="1" ' . checked( 1, $v, false );
		if ( $desc_id !== '' ) {
			echo ' aria-describedby="' . esc_attr( $desc_id ) . '"';
		}
		echo ' />';
		echo '<span class="llmf-switch-field__track" aria-hidden="true"><span class="llmf-switch-field__thumb"></span></span>';
		echo '<span class="llmf-switch-field__copy"><span class="llmf-switch-field__label">' . esc_html( $label ) . '</span>';
		if ( $description !== '' ) {
			echo '<span class="description llmf-switch-field__description" id="' . esc_attr( $desc_id ) . '">' . esc_html( $description ) . '</span>';
		}
		echo '</span>';
		echo '</label>';
	}

	/**
	 * Load excluded picker items for a post type.
	 *
	 * @param string         $post_type    Post type key.
	 * @param array<int,int> $excluded_ids Stored excluded IDs.
	 * @return array<int,array{id:int,title:string}> Picker item data.
	 */
	private function excluded_items_for_type( string $post_type, array $excluded_ids ): array {
		if ( empty( $excluded_ids ) ) {
			return array();
		}

		$query_args = array(
			'post_type'              => $post_type,
			'post__in'               => $excluded_ids,
			'orderby'                => 'post__in',
			'posts_per_page'         => count( $excluded_ids ),
			'post_status'            => 'publish',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		$items = array();
		$found = get_posts( $query_args );
		foreach ( (array) $found as $post ) {
			if ( ! ( $post instanceof \WP_Post ) ) {
				continue;
			}

			$items[] = array(
				'id'    => (int) $post->ID,
				'title' => $this->post_picker_title( $post ),
			);
		}

		return $items;
	}

	/**
	 * Render one selected excluded item.
	 *
	 * @param string $post_type Post type key.
	 * @param int    $post_id   Post ID.
	 * @param string $title     Display title.
	 * @return void
	 */
	private function render_excluded_item( string $post_type, int $post_id, string $title ): void {
		$field_id = 'llmf-excluded-' . sanitize_key( $post_type ) . '-' . (string) $post_id;

		echo '<div class="llmf-excluded-posts__selected-item" data-post-id="' . esc_attr( (string) $post_id ) . '" data-title="' . esc_attr( $title ) . '">';
		echo '<label class="llmf-inline-checkbox llmf-excluded-posts__selected-label" for="' . esc_attr( $field_id ) . '">';
		echo '<input type="checkbox" id="' . esc_attr( $field_id ) . '" class="llmf-excluded-posts__checkbox" name="' . esc_attr( Options::OPTION_KEY ) . '[excluded_posts][' . esc_attr( $post_type ) . '][]" value="' . esc_attr( (string) $post_id ) . '" checked="checked" /> ';
		echo esc_html( $title ) . ' <span class="description">(' . esc_html( sprintf( '#%d', $post_id ) ) . ')</span>';
		echo '</label>';
		echo '<button type="button" class="button-link llmf-excluded-posts__remove" aria-label="' . esc_attr( sprintf(
			/* translators: %s: Item title. */
			__( 'Remove "%s" from exclusions', 'llm-friendly' ),
			$title
		) ) . '"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>';
		echo '</div>';
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */

	/**
	 * Enqueue JS assets for the plugin settings page.
	 *
	 * @param string $hook Current admin screen.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		// Load only on the plugin settings page to avoid cluttering other screens.
		if ( $hook !== 'settings_page_llm-friendly' ) {
			return;
		}

		$handle = 'llmf-admin';
		$src    = trailingslashit( LLMF_URL ) . 'assets/llmf-admin.js';
		$ver    = defined( 'LLMF_VERSION' ) ? (string) LLMF_VERSION : false;

		wp_enqueue_style(
			$handle,
			trailingslashit( LLMF_URL ) . 'assets/llmf-admin.css',
			array(),
			$ver
		);

		wp_enqueue_script(
			$handle,
			$src,
			array(),
			$ver,
			true
		);

		wp_localize_script(
			$handle,
			'LLMF_ADMIN',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'llmf_search_posts' ),
				'previewNonce'   => wp_create_nonce( 'llmf_preview_llms' ),
				'llmsUrl'        => $this->llms_url(),
				'markdownPattern' => $this->markdown_url_pattern(),
				'minChars'       => 2,
				'i18n'           => array(
					'searchPlaceholder' => __( 'Start typing from 2 characters…', 'llm-friendly' ),
					'searching'         => __( 'Searching…', 'llm-friendly' ),
					'nothingFound'      => __( 'Nothing found for this query.', 'llm-friendly' ),
					'addAction'         => __( 'Add to exclusions', 'llm-friendly' ),
					/* translators: %s: Item title. */
					'addItemAction'     => __( 'Add "%s" to exclusions', 'llm-friendly' ),
					/* translators: %s: Item title. */
					'removeItemAction'  => __( 'Remove "%s" from exclusions', 'llm-friendly' ),
					'resultsUpdated'    => __( 'Search results updated.', 'llm-friendly' ),
					/* translators: %s: Item title. */
					'itemAdded'         => __( 'Added "%s" to exclusions.', 'llm-friendly' ),
					/* translators: %s: Item title. */
					'itemRemoved'       => __( 'Removed "%s" from exclusions.', 'llm-friendly' ),
					'selectedEmpty'     => __( 'No items are excluded yet.', 'llm-friendly' ),
					'searchError'       => __( 'Search failed, please try again.', 'llm-friendly' ),
					'copySuccess'       => __( 'Copied to clipboard.', 'llm-friendly' ),
					'copyError'         => __( 'Copy failed. Select and copy manually.', 'llm-friendly' ),
					'previewLoading'    => __( 'Generating preview…', 'llm-friendly' ),
					'previewReady'      => __( 'Preview generated.', 'llm-friendly' ),
					'previewError'      => __( 'Preview failed, please try again.', 'llm-friendly' ),
					'previewDisabled'   => __( 'llms.txt is disabled in saved settings.', 'llm-friendly' ),
					'previewTruncated'  => __( 'Preview was truncated for display.', 'llm-friendly' ),
					'cacheCached'       => __( 'Cached', 'llm-friendly' ),
					'cacheNotCached'    => __( 'Not cached', 'llm-friendly' ),
					'cacheNeedsRegen'   => __( 'Needs regeneration', 'llm-friendly' ),
					'cacheDisabled'     => __( 'Disabled', 'llm-friendly' ),
					'unsavedChanges'    => __( 'Unsaved changes', 'llm-friendly' ),
					'savingSettings'    => __( 'Saving settings...', 'llm-friendly' ),
					'settingsSaved'     => __( 'Settings saved.', 'llm-friendly' ),
					'settingsSaveError' => __( 'Settings could not be saved. Please try again.', 'llm-friendly' ),
					'exclusionsChanged' => __( 'Exclusions changed. Save settings to apply.', 'llm-friendly' ),
					'clearTypeAction'   => __( 'Clear this type', 'llm-friendly' ),
				),
			)
		);
	}

	/**
	 * Render the plugin settings page in wp-admin.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap llmf-settings-page">';
		$this->render_app_header();

		$notice       = isset( $_GET['llmf_msg'] ) ? sanitize_key( wp_unslash( (string) $_GET['llmf_msg'] ) ) : '';
		$notice_nonce = isset( $_GET['llmf_msg_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['llmf_msg_nonce'] ) ) : '';

		if ( $notice === 'regen_ok' && wp_verify_nonce( $notice_nonce, 'llmf_admin_notice' ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' .
			     esc_html__('llms.txt was regenerated.', 'llm-friendly') .
			     '</p></div>';
		}

		echo '<div class="llmf-settings-shell">';
		$this->render_app_nav();
		echo '<div class="llmf-settings-content">';

		// Main Settings API form (ONLY ONE form that posts to options.php).
		echo '<form method="post" action="options.php" class="llmf-settings-form" id="llmf-settings-form">';
		settings_fields('llmf');
		$this->render_overview_panel();
		$this->render_markdown_panel();
		$this->render_scope_panel();
		$this->render_exclusions_panel();
		$this->render_llms_panel();
		$this->render_metadata_panel();
		$this->render_crawlers_panel();
		echo '<div class="llmf-form-actions">';
		echo '<button type="submit" class="button button-primary" id="llmf-save-settings" name="llmf_save_settings">' . esc_html__( 'Save changes', 'llm-friendly' ) . '</button>';
		echo '</div>';
		echo '<div class="llmf-save-bar" data-llmf-save-bar hidden>';
		echo '<div class="llmf-save-bar__message">' . esc_html__( 'Unsaved changes', 'llm-friendly' ) . '</div>';
		echo '<div class="llmf-save-bar__actions">';
		echo '<button type="button" class="button button-secondary" data-llmf-discard>' . esc_html__( 'Discard changes', 'llm-friendly' ) . '</button>';
		echo '<button type="submit" class="button button-primary" id="llmf-save-settings-sticky" name="llmf_save_settings">' . esc_html__( 'Save changes', 'llm-friendly' ) . '</button>';
		echo '</div>';
		echo '</div>';
		echo '</form>';

		// Separate form for manual regeneration (admin-post.php), NOT nested.
		$this->render_maintenance_panel();

		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * AJAX search for the exclusion list.
	 *
	 * Triggered by the admin JS as the user types. Returns JSON with matching
	 * posts for the selected type or an error message.
	 *
	 * @return void
	 */
	public function ajax_search_posts() {
		// Check permissions early to avoid leaking post existence.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'llm-friendly' ) ), 403 );
		}

		check_ajax_referer( 'llmf_search_posts', 'nonce' );

		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( (string) $_GET['post_type'] ) : '';
		$query     = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) : '';
		$query     = trim( $query );

		// Require at least 2 characters to avoid unnecessary DB load.
		if ( $this->text_length( $query ) < 2 ) {
			wp_send_json_error( array( 'message' => __( 'Enter at least 2 characters to search.', 'llm-friendly' ) ), 400 );
		}

		// Allow any public exportable type here, including a type the user just
		// checked on the settings page before saving the whole form.
		if ( $post_type === '' || ! $this->options->is_exportable_post_type( $post_type ) ) {
			wp_send_json_error( array( 'message' => __( 'Post type is not allowed.', 'llm-friendly' ) ), 400 );
		}

		$excluded_ids = $this->options->excluded_post_ids( $post_type );

		// Query only published posts and skip heavy calculations.
		$args = array(
			'post_type'              => $post_type,
			'post_status'            => 'publish',
			's'                      => $query,
			'posts_per_page'         => 20,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- exclude selected posts to prevent duplicates.
			'post__not_in'           => $excluded_ids,
		);

		$found = get_posts( $args );
		$items = array();

		foreach ( (array) $found as $p ) {
			if ( ! ( $p instanceof \WP_Post ) ) {
				continue;
			}

			if ( ! $this->options->can_export_post( $p, 'llms_search' ) ) {
				continue;
			}

			$items[] = array(
				'id'    => (int) $p->ID,
				'title' => $this->post_picker_title( $p ),
			);
		}

		wp_send_json_success( array( 'items' => $items ) );
	}

	/**
	 * AJAX read-only preview for generated llms.txt.
	 *
	 * @return void
	 */
	public function ajax_preview_llms() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'llm-friendly' ) ), 403 );
		}

		check_ajax_referer( 'llmf_preview_llms', 'nonce' );

		wp_send_json_success( $this->llms->preview() );
	}

	/**
	 * AJAX save handler for the main settings form.
	 *
	 * @return void
	 */
	public function ajax_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'llm-friendly' ) ), 403 );
		}

		check_ajax_referer( 'llmf-options', '_wpnonce' );

		if ( ! isset( $_POST[ Options::OPTION_KEY ] ) || ! is_array( $_POST[ Options::OPTION_KEY ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Settings payload is missing.', 'llm-friendly' ) ), 400 );
		}

		$input = wp_unslash( $_POST[ Options::OPTION_KEY ] );
		$saved = $this->options->update( is_array( $input ) ? $input : array() );

		wp_send_json_success(
			array(
				'message' => __( 'Settings saved.', 'llm-friendly' ),
				'options' => $saved,
			)
		);
	}

	/**
	 * Handle manual llms.txt regeneration.
	 *
	 * @return void
	 */
	public function handle_regenerate_llms() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'llm-friendly' ) );
		}

		check_admin_referer( 'llmf_regenerate_llms', 'llmf_regenerate_nonce' );

		$this->llms->regenerate( true );

		$back = wp_get_referer();
		if ( ! $back ) {
			$back = admin_url( 'options-general.php?page=llm-friendly' );
		}

		$notice_url = add_query_arg(
			array(
				'llmf_msg'       => 'regen_ok',
				'llmf_msg_nonce' => wp_create_nonce( 'llmf_admin_notice' ),
			),
			$back
		);

		wp_safe_redirect( $notice_url );
		exit;
	}

	/**
	 * Field: Enable Markdown exports.
	 *
	 * @return void
	 */
	public function field_enabled_markdown() {
		$this->render_option_checkbox(
			'enabled_markdown',
			__( 'Enable .md endpoints for selected post types', 'llm-friendly' ),
			__( 'Published items from the selected post types get Markdown export URLs.', 'llm-friendly' )
		);
	}

	/**
	 * Field: send X-Robots-Tag: noindex for Markdown exports.
	 *
	 * @return void
	 */
	public function field_md_noindex() {
		$this->render_option_checkbox(
			'md_send_noindex',
			__( 'Add X-Robots-Tag: noindex header for .md endpoints', 'llm-friendly' ),
			__( 'Discourages search engines from indexing Markdown export endpoints.', 'llm-friendly' )
		);
	}


	/**
	 * Field: Base path for exports.
	 *
	 * @return void
	 */
	public function field_base_path() {
		$opt = $this->options->get();
		$v = isset($opt['base_path']) ? (string)$opt['base_path'] : 'llm';
		$id = $this->option_field_id( 'base_path' );

		echo '<input type="text" class="regular-text llmf-text-field" id="' . esc_attr( $id ) . '" name="' . esc_attr(Options::OPTION_KEY) . '[base_path]" value="' . esc_attr($v) . '" aria-describedby="' . esc_attr( $this->option_description_id( 'base_path' ) ) . '" />';
		echo '<p class="description llmf-field-description" id="' . esc_attr( $this->option_description_id( 'base_path' ) ) . '">' . esc_html__('Example: "llm" → /llm/{post_type}/{path}.md', 'llm-friendly') . '</p>';
	}

	/**
	 * Field: Post types to include.
	 *
	 * @return void
	 */
	public function field_post_types() {
		$selected = $this->selected_post_types();

		$pts = $this->options->exportable_post_types();

		echo '<fieldset class="llmf-post-type-grid" aria-describedby="llmf-post-types-description">';
		echo '<legend class="screen-reader-text">' . esc_html__( 'Post types to include', 'llm-friendly' ) . '</legend>';
		foreach ($pts as $pt => $obj) {
			$pt = sanitize_key((string)$pt);
			if ($pt === '') continue;

			$label = isset($obj->labels->name) ? (string)$obj->labels->name : $pt;
			$id    = 'llmf-post-type-' . $pt;
			$is_checked = in_array( $pt, $selected, true );

			echo '<label class="llmf-post-type-card" for="' . esc_attr( $id ) . '">';
			echo '<input type="checkbox" id="' . esc_attr( $id ) . '" class="llmf-post-type-toggle" data-post-type="' . esc_attr( $pt ) . '" name="' . esc_attr(Options::OPTION_KEY) . '[post_types][]" value="' . esc_attr($pt) . '" ' . checked( true, $is_checked, false ) . ' />';
			echo '<span class="llmf-post-type-card__body">';
			echo '<span class="llmf-post-type-card__label">' . esc_html($label) . '</span>';
			echo '<code>' . esc_html($pt) . '</code>';
			echo '</span>';
			if ( $is_checked ) {
				echo '<span class="llmf-post-type-card__badge">' . esc_html__( 'Included', 'llm-friendly' ) . '</span>';
			}
			echo '</label>';
		}
		echo '<p class="description llmf-field-description" id="llmf-post-types-description">' . esc_html__('These types will appear in llms.txt and will have Markdown exports.', 'llm-friendly') . '</p>';
		echo '</fieldset>';
	}

	/**
	 * Field: Enable llms.txt.
	 *
	 * @return void
	 */
	public function field_enabled_llms_txt() {
		$this->render_option_checkbox(
			'enabled_llms_txt',
			__( 'Serve /llms.txt', 'llm-friendly' ),
			__( 'Publishes the llms.txt index at the site root.', 'llm-friendly' )
		);
	}

	/**
	 * Field: X-Robots-Tag for llms.txt.
	 *
	 * @return void
	 */
	public function field_llms_noindex() {
		$this->render_option_checkbox(
			'llms_send_noindex',
			__( 'Send X-Robots-Tag: noindex for /llms.txt', 'llm-friendly' ),
			__( 'Discourages search engines from indexing the llms.txt file itself.', 'llm-friendly' )
		);
	}

	/**
	 * Field: Regeneration mode.
	 *
	 * @return void
	 */
	public function field_llms_regen_mode() {
		$opt = $this->options->get();
		$v = isset($opt['llms_regen_mode']) ? (string)$opt['llms_regen_mode'] : 'auto';
		$id = $this->option_field_id( 'llms_regen_mode' );

		echo '<fieldset class="llmf-segmented" id="' . esc_attr( $id ) . '" aria-describedby="' . esc_attr( $this->option_description_id( 'llms_regen_mode' ) ) . '">';
		echo '<legend class="screen-reader-text">' . esc_html__( 'Regeneration mode', 'llm-friendly' ) . '</legend>';
		echo '<label class="llmf-segmented__option" for="' . esc_attr( $id . '-auto' ) . '">';
		echo '<input type="radio" id="' . esc_attr( $id . '-auto' ) . '" name="' . esc_attr(Options::OPTION_KEY) . '[llms_regen_mode]" value="auto"' . checked( 'auto', $v, false ) . ' />';
		echo '<span>' . esc_html__('Auto (on publish/update)', 'llm-friendly') . '</span>';
		echo '</label>';
		echo '<label class="llmf-segmented__option" for="' . esc_attr( $id . '-manual' ) . '">';
		echo '<input type="radio" id="' . esc_attr( $id . '-manual' ) . '" name="' . esc_attr(Options::OPTION_KEY) . '[llms_regen_mode]" value="manual"' . checked( 'manual', $v, false ) . ' />';
		echo '<span>' . esc_html__('Manual only', 'llm-friendly') . '</span>';
		echo '</label>';
		echo '</fieldset>';

		$this->render_field_description( 'llms_regen_mode', __( 'In manual mode, use the button in the Maintenance section to rebuild llms.txt.', 'llm-friendly' ) );
	}

	/**
	 * Field: Items per post type.
	 *
	 * @return void
	 */
	public function field_llms_recent_limit() {
		$opt = $this->options->get();
		$v = isset($opt['llms_recent_limit']) ? (int)$opt['llms_recent_limit'] : 30;
		$id = $this->option_field_id( 'llms_recent_limit' );

		echo '<input type="number" class="small-text llmf-number-field" id="' . esc_attr( $id ) . '" min="1" max="200" name="' . esc_attr(Options::OPTION_KEY) . '[llms_recent_limit]" value="' . esc_attr((string)$v) . '" aria-describedby="' . esc_attr( $this->option_description_id( 'llms_recent_limit' ) ) . '" />';
		$this->render_field_description( 'llms_recent_limit', __( 'How many latest items to list for each post type.', 'llm-friendly' ) );
	}

	/**
	 * Field: Show excerpt for each listed item in llms.txt.
	 *
	 * @return void
	 */
	public function field_llms_show_excerpt() {
		$this->render_option_checkbox(
			'llms_show_excerpt',
			__( 'Add excerpt (if available) under each item in llms.txt', 'llm-friendly' ),
			__( 'Uses post excerpts or generated summaries when available.', 'llm-friendly' )
		);
	}

	/**
	 * Field: Sitemap URL.
	 *
	 * @return void
	 */
	public function field_sitemap_url() {
		$opt = $this->options->get();
		$v = isset($opt['sitemap_url']) ? (string)$opt['sitemap_url'] : '/sitemap.xml';
		$id = $this->option_field_id( 'sitemap_url' );

		echo '<input type="text" class="regular-text llmf-text-field" id="' . esc_attr( $id ) . '" name="' . esc_attr(Options::OPTION_KEY) . '[sitemap_url]" value="' . esc_attr($v) . '" aria-describedby="' . esc_attr( $this->option_description_id( 'sitemap_url' ) ) . '" />';
		$this->render_field_description( 'sitemap_url', __( 'Same-site absolute URL or site-relative path. Default: /sitemap.xml', 'llm-friendly' ) );
	}

	/**
	 * Field: Custom markdown block for llms.txt.
	 *
	 * Inserted between site meta and the link sections.
	 *
	 * @return void
	 */
	public function field_llms_custom_markdown() {
		$opt = $this->options->get();
		$v = isset($opt['llms_custom_markdown']) ? (string) $opt['llms_custom_markdown'] : '';
		$id = $this->option_field_id( 'llms_custom_markdown' );

		echo '<textarea class="large-text code llmf-code-field" rows="8" id="' . esc_attr( $id ) . '" name="' . esc_attr(Options::OPTION_KEY) . '[llms_custom_markdown]" aria-describedby="' . esc_attr( $this->option_description_id( 'llms_custom_markdown' ) ) . '">' . esc_textarea($v) . '</textarea>';
		$this->render_field_description( 'llms_custom_markdown', __( 'Optional Markdown inserted into llms.txt between the site meta and the content sections. Heading markers are removed so this block cannot break the llms.txt section order.', 'llm-friendly' ) );
	}

	/**
	 * Field: curated important links for llms.txt.
	 *
	 * @return void
	 */
	public function field_llms_essential_links() {
		$opt = $this->options->get();
		$v   = isset( $opt['llms_essential_links'] ) ? (string) $opt['llms_essential_links'] : '';
		$id  = $this->option_field_id( 'llms_essential_links' );

		echo '<textarea class="large-text code llmf-code-field" rows="5" id="' . esc_attr( $id ) . '" name="' . esc_attr( Options::OPTION_KEY ) . '[llms_essential_links]" aria-describedby="' . esc_attr( $this->option_description_id( 'llms_essential_links' ) ) . '">' . esc_textarea( $v ) . '</textarea>';
		$this->render_field_description( 'llms_essential_links', __( 'Optional curated links for the llms.txt Essential section. One per line: Title | URL | Notes. URLs may be absolute or site-relative.', 'llm-friendly' ) );
	}

	/**
	 * Field: AI crawler diagnostics and recommendations.
	 *
	 * @return void
	 */
	public function field_crawler_diagnostics() {
		$opt     = $this->options->get();
		$sitemap = $this->options->sitemap_absolute_url();
		$robots  = home_url( '/robots.txt' );

		echo '<div class="llmf-crawler-diagnostics">';
		echo '<div class="llmf-diagnostic-grid">';

		echo '<div class="llmf-diagnostic-card">';
		echo '<div class="llmf-diagnostic-card__top"><h3>' . esc_html__( 'Search visibility', 'llm-friendly' ) . '</h3>';
		$this->render_badge( empty( $opt['llms_send_noindex'] ) ? __( 'Indexable', 'llm-friendly' ) : __( 'Noindex', 'llm-friendly' ), empty( $opt['llms_send_noindex'] ) ? 'success' : 'warning' );
		echo '</div>';
		echo '<p>' . esc_html__( 'The llms.txt noindex header controls whether search engines should index the file itself.', 'llm-friendly' ) . '</p>';
		echo '</div>';

		echo '<div class="llmf-diagnostic-card">';
		echo '<div class="llmf-diagnostic-card__top"><h3>' . esc_html__( 'Markdown exports', 'llm-friendly' ) . '</h3>';
		$this->render_badge( empty( $opt['md_send_noindex'] ) ? __( 'Indexable', 'llm-friendly' ) : __( 'Noindex', 'llm-friendly' ), empty( $opt['md_send_noindex'] ) ? 'success' : 'warning' );
		echo '</div>';
		echo '<p>' . esc_html__( 'Markdown endpoint headers are separate from normal WordPress post visibility.', 'llm-friendly' ) . '</p>';
		echo '</div>';

		echo '<div class="llmf-diagnostic-card">';
		echo '<div class="llmf-diagnostic-card__top"><h3>' . esc_html__( 'Sitemap', 'llm-friendly' ) . '</h3>';
		$this->render_badge( __( 'Configured', 'llm-friendly' ), 'success' );
		echo '</div>';
		echo '<p><code>' . esc_html( $sitemap ) . '</code></p>';
		echo '<p><a href="' . esc_url( $sitemap ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open sitemap', 'llm-friendly' ) . '</a></p>';
		echo '</div>';

		echo '<div class="llmf-diagnostic-card">';
		echo '<div class="llmf-diagnostic-card__top"><h3>' . esc_html__( 'robots.txt', 'llm-friendly' ) . '</h3>';
		$this->render_badge( __( 'Manual policy', 'llm-friendly' ), 'neutral' );
		echo '</div>';
		echo '<p>' . esc_html__( 'LLM Friendly does not edit robots.txt automatically.', 'llm-friendly' ) . '</p>';
		echo '<p><a href="' . esc_url( $robots ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open robots.txt', 'llm-friendly' ) . '</a></p>';
		echo '</div>';

		echo '</div>';
		echo '<ul class="llmf-crawler-diagnostics__list">';
		echo '<li>' . esc_html__( 'Allow OAI-SearchBot when you want pages to appear in ChatGPT search answers.', 'llm-friendly' ) . '</li>';
		echo '<li>' . esc_html__( 'Decide on GPTBot separately; it is used for OpenAI model training rather than ChatGPT search inclusion.', 'llm-friendly' ) . '</li>';
		echo '<li>' . esc_html__( 'For Google AI Overviews and AI Mode, Googlebot crawl and normal Search index eligibility remain the key controls.', 'llm-friendly' ) . '</li>';
		echo '<li>' . esc_html__( 'Use Google-Extended separately if you need to limit training or grounding in some Google AI systems outside Search.', 'llm-friendly' ) . '</li>';
		echo '</ul>';
		echo '<p class="description">' . esc_html__( 'Recommended robots.txt policy depends on your content strategy; this plugin only surfaces the decision points.', 'llm-friendly' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Field: Exclude posts from llms.txt and Markdown exports.
	 *
	 * Shows the selected post types, enables async title search,
	 * and lets users add/remove items in the exclusion list.
	 *
	 * @return void
	 */
	public function field_excluded_posts() {
		$selected = $this->selected_post_types();
		$pts = $this->options->exportable_post_types();

		if ( empty( $pts ) ) {
			echo '<p class="description">' . esc_html__( 'Select at least one post type above to manage exclusions.', 'llm-friendly' ) . '</p>';
			return;
		}

		echo '<p class="description llmf-field-description" id="llmf-excluded-posts-description">' . wp_kses(
			__( 'Find items by title in real time, add them to the exclusion list, and remove them with one click. Excluded items are omitted from llms.txt and Markdown exports.', 'llm-friendly' ),
			array()
		) . '</p>';

		// Container for JS data: hints and nonce are already provided via wp_localize_script().
		echo '<div class="llmf-excluded-posts__wrap" id="llmf-excluded-posts" aria-describedby="llmf-excluded-posts-description">';
		echo '<p class="llmf-excluded-posts__dirty" data-llmf-exclusions-dirty hidden>' . esc_html__( 'Exclusions changed. Save settings to apply.', 'llm-friendly' ) . '</p>';

		foreach ( $pts as $pt => $obj ) {
			$pt         = sanitize_key( (string) $pt );
			$is_enabled = in_array( $pt, $selected, true );
			$label      = isset( $obj->labels->name ) ? (string) $obj->labels->name : $pt;

			if ( $pt === '' ) {
				continue;
			}

			$excluded_ids = $this->options->excluded_post_ids( $pt );
			$items        = $this->excluded_items_for_type( $pt, $excluded_ids );

			$hidden_class = $is_enabled ? '' : ' llmf-excluded-posts__type--hidden';

			$search_id   = 'llmf-search-' . $pt;
			$results_id  = $search_id . '-results';
			$status_id   = $search_id . '-status';
			$help_id     = $search_id . '-help';
			$selected_id = $search_id . '-selected';

			echo '<div class="llmf-excluded-posts__type' . esc_attr( $hidden_class ) . '" data-post-type="' . esc_attr( $pt ) . '" data-post-label="' . esc_attr( $label ) . '">';
			echo '<div class="llmf-excluded-posts__type-header">';
			echo '<div>';
			echo '<h3 class="llmf-excluded-posts__type-title">' . esc_html( $label ) . ' <code>' . esc_html( $pt ) . '</code></h3>';
			echo '<p class="llmf-excluded-posts__type-meta">';
			printf(
				/* translators: %d: Number of excluded items. */
				esc_html( _n( '%d excluded item', '%d excluded items', count( $items ), 'llm-friendly' ) ),
				(int) count( $items )
			);
			echo '</p>';
			echo '</div>';
			echo '<button type="button" class="button button-secondary llmf-excluded-posts__clear" data-post-type="' . esc_attr( $pt ) . '"' . ( empty( $items ) ? ' disabled="disabled"' : '' ) . '>' . esc_html__( 'Clear this type', 'llm-friendly' ) . '</button>';
			echo '</div>';

			echo '<div class="llmf-excluded-posts__search">';
			echo '<label class="llmf-excluded-posts__search-label" for="' . esc_attr( $search_id ) . '">' . esc_html__( 'Search by title', 'llm-friendly' ) . '</label>';
			echo '<div class="llmf-excluded-posts__search-control">';
			echo '<input type="text" class="regular-text llmf-excluded-posts__search-input" id="' . esc_attr( $search_id ) . '" data-post-type="' . esc_attr( $pt ) . '" placeholder="' . esc_attr__( 'Start typing from 2 characters…', 'llm-friendly' ) . '" aria-autocomplete="list" aria-expanded="false" aria-controls="' . esc_attr( $results_id ) . '" aria-describedby="' . esc_attr( $help_id . ' ' . $status_id ) . '" />';
			echo '<div class="llmf-excluded-posts__dropdown" id="' . esc_attr( $results_id ) . '" data-post-type="' . esc_attr( $pt ) . '" role="list" hidden></div>';
			echo '</div>';
			echo '<p class="description llmf-field-description" id="' . esc_attr( $help_id ) . '">' . esc_html__( 'Type at least two characters to search. Results appear in the dropdown instantly.', 'llm-friendly' ) . '</p>';
			echo '<div class="screen-reader-text llmf-excluded-posts__status" id="' . esc_attr( $status_id ) . '" role="status" aria-live="polite" aria-atomic="true"></div>';
			echo '</div>';

			echo '<p class="llmf-excluded-posts__selected-title" id="' . esc_attr( $selected_id ) . '">' . esc_html__( 'Currently excluded', 'llm-friendly' ) . ' <span class="llmf-excluded-posts__count" data-llmf-excluded-count>' . esc_html( (string) count( $items ) ) . '</span></p>';
			echo '<div class="llmf-excluded-posts__selected" data-post-type="' . esc_attr( $pt ) . '" aria-labelledby="' . esc_attr( $selected_id ) . '">';

			if ( empty( $items ) ) {
				echo '<p class="description llmf-excluded-posts__empty">' . esc_html__( 'No items are excluded yet.', 'llm-friendly' ) . '</p>';
			} else {
				foreach ( $items as $item ) {
					$post_id = isset( $item['id'] ) ? (int) $item['id'] : 0;
					$title   = isset( $item['title'] ) ? (string) $item['title'] : '';
					if ( $post_id <= 0 || $title === '' ) {
						continue;
					}

					$this->render_excluded_item( $pt, $post_id, $title );
				}
			}

			echo '</div>';
			echo '</div>';
		}

		echo '</div>';
	}


	/**
	 * Field: Site title override.
	 *
	 * @return void
	 */
	public function field_site_title_override() {
		$opt = $this->options->get();
		$v = isset($opt['site_title_override']) ? (string)$opt['site_title_override'] : '';
		$id = $this->option_field_id( 'site_title_override' );

		echo '<input type="text" class="regular-text llmf-text-field" id="' . esc_attr( $id ) . '" name="' . esc_attr(Options::OPTION_KEY) . '[site_title_override]" value="' . esc_attr($v) . '" aria-describedby="' . esc_attr( $this->option_description_id( 'site_title_override' ) ) . '" />';
		$this->render_field_description( 'site_title_override', __( 'If empty, uses WordPress setting: Site Title.', 'llm-friendly' ) );
	}

	/**
	 * Field: Site description override.
	 *
	 * @return void
	 */
	public function field_site_description_override() {
		$opt = $this->options->get();
		$v = isset($opt['site_description_override']) ? (string)$opt['site_description_override'] : '';
		$id = $this->option_field_id( 'site_description_override' );

		echo '<input type="text" class="regular-text llmf-text-field" id="' . esc_attr( $id ) . '" name="' . esc_attr(Options::OPTION_KEY) . '[site_description_override]" value="' . esc_attr($v) . '" aria-describedby="' . esc_attr( $this->option_description_id( 'site_description_override' ) ) . '" />';
		$this->render_field_description( 'site_description_override', __( 'If empty, uses WordPress setting: Tagline.', 'llm-friendly' ) );
	}

	/**
	 * Field: Author override.
	 *
	 * @return void
	 */
	public function field_site_author_override() {
		$opt = $this->options->get();
		$v   = isset( $opt['site_author_override'] ) ? (string) $opt['site_author_override'] : '';
		$id  = $this->option_field_id( 'site_author_override' );

		echo '<input type="text" class="regular-text llmf-text-field" id="' . esc_attr( $id ) . '" name="' . esc_attr( Options::OPTION_KEY ) . '[site_author_override]" value="' . esc_attr( $v ) . '" aria-describedby="' . esc_attr( $this->option_description_id( 'site_author_override' ) ) . '" />';
		$this->render_field_description( 'site_author_override', __( 'If empty, Markdown metadata uses the post author display name.', 'llm-friendly' ) );
	}

	/**
	 * Register the "Markdown override" metabox for Classic and Gutenberg editors.
	 *
	 * Gutenberg renders classic metaboxes under the editor (Additional settings),
	 * so we keep a single registration point and do not add a separate sidebar panel.
	 *
	 * @param string  $post_type Current post type.
	 * @param WP_Post $post      Post object.
	 * @return void
	 */
	public function maybe_add_md_override_metabox( $post_type, $post ) {
		if ( ! ( $post instanceof \WP_Post ) ) {
			return;
		}

		$allowed = $this->selected_post_types();
		if ( ! $this->options->is_exportable_post_type( (string) $post_type ) || ! in_array( (string) $post_type, $allowed, true ) ) {
			return;
		}

		add_meta_box(
			'llmf-md-override',
			__( 'Markdown override (LLM Friendly)', 'llm-friendly' ),
			array( $this, 'render_md_override_metabox' ),
			$post_type,
			'normal',
			'high'
		);
	}

	/**
	 * Render the Markdown override metabox (Classic + Gutenberg).
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_md_override_metabox( $post ) {
		if ( ! ( $post instanceof \WP_Post ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		$val = get_post_meta( $post->ID, Exporter::META_MD_OVERRIDE, true );
		$val = is_string( $val ) ? $val : '';
		$description = get_post_meta( $post->ID, Options::META_LLMS_DESCRIPTION, true );
		$description = is_string( $description ) ? $description : '';
		$description_max = (int) apply_filters( 'llmf_llms_description_max_length', 500 );
		if ( $description_max < 1 ) {
			$description_max = 500;
		}

		wp_nonce_field( 'llmf_md_override_save', 'llmf_md_override_nonce' );

		echo '<p class="description">' . esc_html__( 'If filled, this text will replace the post content in the Markdown export.', 'llm-friendly' ) . '</p>';
		echo '<textarea style="width:100%;min-height:240px" name="llmf_md_content_override">' . esc_textarea( $val ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Leave empty to export the post content. You can paste plain Markdown or Gutenberg block markup (<!-- wp: ... -->).', 'llm-friendly' ) . '</p>';
		echo '<hr />';
		echo '<p><label for="llmf-llms-description"><strong>' . esc_html__( 'llms.txt description (overrides excerpt)', 'llm-friendly' ) . '</strong></label></p>';
		echo '<input type="text" class="widefat" id="llmf-llms-description" name="llmf_llms_description" maxlength="' . esc_attr( (string) $description_max ) . '" value="' . esc_attr( $description ) . '" />';
		echo '<p class="description">' . esc_html__( 'Optional one-line summary used in llms.txt and Markdown metadata. Leave empty to use the SEO meta description, excerpt, or a generated summary.', 'llm-friendly' ) . '</p>';
	}

	/**
	 * Save Classic Editor metabox value.
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 * @return void
	 */
	public function save_md_override_metabox( $post_id, $post ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['llmf_md_override_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['llmf_md_override_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'llmf_md_override_save' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! ( $post instanceof \WP_Post ) ) {
			$post = get_post( $post_id );
			if ( ! ( $post instanceof \WP_Post ) ) {
				return;
			}
		}

		$allowed = $this->selected_post_types();
		if ( ! $this->options->is_exportable_post_type( (string) $post->post_type ) || ! in_array( (string) $post->post_type, $allowed, true ) ) {
			return;
		}

		// Extract user input, keep Markdown intact, and sanitize to avoid unsafe tags.
		$raw_value = isset( $_POST['llmf_md_content_override'] )
			? $_POST['llmf_md_content_override']
			: '';
		$value     = $this->options->sanitize_markdown_override( $raw_value );

		if ( $value === '' ) {
			delete_post_meta( $post_id, Exporter::META_MD_OVERRIDE );
		} else {
			update_post_meta( $post_id, Exporter::META_MD_OVERRIDE, $value );
		}

		$raw_description = isset( $_POST['llmf_llms_description'] )
			? wp_unslash( $_POST['llmf_llms_description'] )
			: '';
		$description = $this->options->sanitize_llms_description( $raw_description );

		if ( $description === '' ) {
			delete_post_meta( $post_id, Options::META_LLMS_DESCRIPTION );
			return;
		}

		update_post_meta( $post_id, Options::META_LLMS_DESCRIPTION, $description );
	}


}
