<?php
namespace LLM_Friendly;

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
			add_action('admin_menu', array($this, 'admin_menu'));
			add_action('admin_init', array($this, 'admin_init'));
			add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
			add_action('add_meta_boxes', array($this, 'maybe_add_md_override_metabox'), 10, 2);
			add_action('save_post', array($this, 'save_md_override_metabox'), 10, 2);
			add_action('admin_post_llmf_regenerate_llms', array($this, 'handle_regenerate_llms'));
		}
	}

	/**
	 * Register settings page.
	 *
	 * @return void
	 */
	public function admin_menu() {
		add_options_page(
			__('LLM Friendly'),
			__('LLM Friendly'),
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
			array($this, 'sanitize_options')
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
			'llmf_general'
		);

		add_settings_field(
			'md_send_noindex',
			__('Send "noindex" header for Markdown exports', 'llm-friendly'),
			array($this, 'field_md_noindex'),
			'llm-friendly',
			'llmf_general'
		);

		add_settings_field(
			'base_path',
			__('Base path for Markdown exports', 'llm-friendly'),
			array($this, 'field_base_path'),
			'llm-friendly',
			'llmf_general'
		);

		add_settings_field(
			'post_types',
			__('Post types to include', 'llm-friendly'),
			array($this, 'field_post_types'),
			'llm-friendly',
			'llmf_general'
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
			'llmf_llms'
		);

		add_settings_field(
			'llms_send_noindex',
			__('Send "noindex" header for llms.txt', 'llm-friendly'),
			array($this, 'field_llms_noindex'),
			'llm-friendly',
			'llmf_llms'
		);

		add_settings_field(
			'llms_regen_mode',
			__('Regeneration mode', 'llm-friendly'),
			array($this, 'field_llms_regen_mode'),
			'llm-friendly',
			'llmf_llms'
		);

		add_settings_field(
			'llms_recent_limit',
			__('Items per post type', 'llm-friendly'),
			array($this, 'field_llms_recent_limit'),
			'llm-friendly',
			'llmf_llms'
		);

		add_settings_field(
			'llms_show_excerpt',
			__( 'Show excerpt', 'llm-friendly' ),
			array( $this, 'field_llms_show_excerpt' ),
			'llm-friendly',
			'llmf_llms'
		);

		add_settings_field(
			'sitemap_url',
			__('Sitemap URL', 'llm-friendly'),
			array($this, 'field_sitemap_url'),
			'llm-friendly',
			'llmf_llms'
		);

		add_settings_field(
			'llms_custom_markdown',
			__('Custom markdown block', 'llm-friendly'),
			array($this, 'field_llms_custom_markdown'),
			'llm-friendly',
			'llmf_llms'
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
			'llmf_overrides'
		);

		add_settings_field(
			'site_description_override',
			__('Site description override', 'llm-friendly'),
			array($this, 'field_site_description_override'),
			'llm-friendly',
			'llmf_overrides'
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
	 * Render settings page.
	 *
	 * @return void
	 */
	
	/**
	 * Enqueue Gutenberg sidebar UI for Markdown override.
	 *
	 * @return void
	 */
	public function enqueue_block_editor_assets() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$opt   = $this->options->get();
		$types = isset( $opt['post_types'] ) && is_array( $opt['post_types'] ) ? $opt['post_types'] : array();
		$types = array_values( array_filter( array_map( 'sanitize_key', $types ) ) );

		$handle = 'llmf-md-override';
		$src    = trailingslashit( LLMF_URL ) . 'assets/llmf-md-override.js';

		wp_enqueue_script(
			$handle,
			$src,
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data' ),
			defined( 'LLMF_VERSION' ) ? (string) LLMF_VERSION : false,
			true
		);

		wp_set_script_translations( $handle, 'llm-friendly' );

		wp_add_inline_script(
			$handle,
			'window.LLMF_MD_OVERRIDE = ' . wp_json_encode(
				array(
					'metaKey'    => Exporter::META_MD_OVERRIDE,
					'postTypes'  => $types,
					'panelTitle' => __( 'Markdown override', 'llm-friendly' ),
					'label'      => __( 'Override content for Markdown export', 'llm-friendly' ),
					'help'       => __( 'If filled, this text will be exported as the Markdown body instead of the post content. Leave empty to use the post content. You can paste Markdown here; if it contains Gutenberg block markup (<!-- wp: ... -->), it will be converted to Markdown.', 'llm-friendly' ),
				),
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			) . ';',
			'before'
		);
	}

public function render_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__('LLM Friendly') . '</h1>';

		if (isset($_GET['llmf_msg']) && $_GET['llmf_msg'] === 'regen_ok') {
			echo '<div class="notice notice-success is-dismissible"><p>' .
			     esc_html__('llms.txt was regenerated.', 'llm-friendly') .
			     '</p></div>';
		}

		// Main Settings API form (ONLY ONE form that posts to options.php).
		echo '<form method="post" action="options.php">';
		settings_fields('llmf');
		do_settings_sections('llm-friendly');
		submit_button(__('Save changes', 'llm-friendly'));
		echo '</form>';

		// Separate form for manual regeneration (admin-post.php), NOT nested.
		echo '<h2>' . esc_html__('Maintenance', 'llm-friendly') . '</h2>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		echo '<input type="hidden" name="action" value="llmf_regenerate_llms" />';
		wp_nonce_field('llmf_regenerate_llms');
		submit_button(__('Regenerate llms.txt now', 'llm-friendly'), 'secondary');
		echo '</form>';
		echo '<p class="description">' . esc_html__('Rebuilds cached llms.txt immediately.', 'llm-friendly') . '</p>';

		echo '</div>';
	}

	/**
	 * Handle manual llms.txt regeneration.
	 *
	 * @return void
	 */
	public function handle_regenerate_llms() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Access denied.', 'llm-friendly'));
		}

		check_admin_referer('llmf_regenerate_llms');

		$this->llms->regenerate(true);

		$back = wp_get_referer();
		if (!$back) {
			$back = admin_url('options-general.php?page=llm-friendly');
		}

		wp_safe_redirect(add_query_arg('llmf_msg', 'regen_ok', $back));
		exit;
	}

	/**
	 * Field: Enable Markdown exports.
	 *
	 * @return void
	 */
	public function field_enabled_markdown() {
		$opt = $this->options->get();
		$v = !empty($opt['enabled_markdown']) ? 1 : 0;

		echo '<label>';
		echo '<input type="checkbox" name="' . esc_attr(Options::OPTION_KEY) . '[enabled_markdown]" value="1" ' . checked(1, $v, false) . ' />';
		echo ' ' . esc_html__('Enable .md endpoints for selected post types', 'llm-friendly');
		echo '</label>';
	}/**
 * Field: send X-Robots-Tag: noindex, nofollow for Markdown exports.
 *
 * @return void
 */
public function field_md_noindex() {
	$opt = $this->options->get();
	$v = !empty($opt['md_send_noindex']) ? 1 : 0;

	echo '<label>';
	echo '<input type="checkbox" name="' . esc_attr(Options::OPTION_KEY) . '[md_send_noindex]" value="1" ' . checked(1, $v, false) . ' />';
	echo ' ' . esc_html__('Add X-Robots-Tag: noindex, nofollow header for .md endpoints', 'llm-friendly');
	echo '</label>';
}


	/**
	 * Field: Base path for exports.
	 *
	 * @return void
	 */
	public function field_base_path() {
		$opt = $this->options->get();
		$v = isset($opt['base_path']) ? (string)$opt['base_path'] : 'llm';

		echo '<input type="text" class="regular-text" name="' . esc_attr(Options::OPTION_KEY) . '[base_path]" value="' . esc_attr($v) . '" />';
		echo '<p class="description">' . esc_html__('Example: "llm" → /llm/{post_type}/{path}.md', 'llm-friendly') . '</p>';
	}

	/**
	 * Field: Post types to include.
	 *
	 * @return void
	 */
	public function field_post_types() {
		$opt = $this->options->get();
		$selected = isset($opt['post_types']) && is_array($opt['post_types']) ? $opt['post_types'] : array('post');

		$pts = get_post_types(array('public' => true), 'objects');
		if (!is_array($pts)) $pts = array();

		echo '<fieldset>';
		foreach ($pts as $pt => $obj) {
			$pt = sanitize_key((string)$pt);
			if ($pt === '') continue;

			// Skip attachments by default.
			if ($pt === 'attachment') continue;

			$label = isset($obj->labels->name) ? (string)$obj->labels->name : $pt;

			$checked = in_array($pt, $selected, true) ? 'checked="checked"' : '';

			echo '<label style="display:block;margin:4px 0;">';
			echo '<input type="checkbox" name="' . esc_attr(Options::OPTION_KEY) . '[post_types][]" value="' . esc_attr($pt) . '" ' . $checked . ' />';
			echo ' ' . esc_html($label) . ' <code>' . esc_html($pt) . '</code>';
			echo '</label>';
		}
		echo '<p class="description">' . esc_html__('These types will appear in llms.txt and will have Markdown exports.', 'llm-friendly') . '</p>';
		echo '</fieldset>';
	}

	/**
	 * Field: Enable llms.txt.
	 *
	 * @return void
	 */
	public function field_enabled_llms_txt() {
		$opt = $this->options->get();
		$v = !empty($opt['enabled_llms_txt']) ? 1 : 0;

		echo '<label>';
		echo '<input type="checkbox" name="' . esc_attr(Options::OPTION_KEY) . '[enabled_llms_txt]" value="1" ' . checked(1, $v, false) . ' />';
		echo ' ' . esc_html__('Serve /llms.txt', 'llm-friendly');
		echo '</label>';
	}

	/**
	 * Field: X-Robots-Tag for llms.txt.
	 *
	 * @return void
	 */
	public function field_llms_noindex() {
		$opt = $this->options->get();
		$v = !empty($opt['llms_send_noindex']) ? 1 : 0;

		echo '<label>';
		echo '<input type="checkbox" name="' . esc_attr(Options::OPTION_KEY) . '[llms_send_noindex]" value="1" ' . checked(1, $v, false) . ' />';
		echo ' ' . esc_html__('Send X-Robots-Tag: noindex, nofollow for /llms.txt', 'llm-friendly');
		echo '</label>';
	}

	/**
	 * Field: Regeneration mode.
	 *
	 * @return void
	 */
	public function field_llms_regen_mode() {
		$opt = $this->options->get();
		$v = isset($opt['llms_regen_mode']) ? (string)$opt['llms_regen_mode'] : 'auto';

		echo '<select name="' . esc_attr(Options::OPTION_KEY) . '[llms_regen_mode]">';
		echo '<option value="auto"' . selected('auto', $v, false) . '>' . esc_html__('Auto (on publish/update)', 'llm-friendly') . '</option>';
		echo '<option value="manual"' . selected('manual', $v, false) . '>' . esc_html__('Manual only', 'llm-friendly') . '</option>';
		echo '</select>';

		echo '<p class="description">' . esc_html__('In manual mode, use the button in the Maintenance section to rebuild llms.txt.', 'llm-friendly') . '</p>';
	}

	/**
	 * Field: Items per post type.
	 *
	 * @return void
	 */
	public function field_llms_recent_limit() {
		$opt = $this->options->get();
		$v = isset($opt['llms_recent_limit']) ? (int)$opt['llms_recent_limit'] : 30;

		echo '<input type="number" min="1" max="200" name="' . esc_attr(Options::OPTION_KEY) . '[llms_recent_limit]" value="' . esc_attr((string)$v) . '" />';
		echo '<p class="description">' . esc_html__('How many latest items to list for each post type.', 'llm-friendly') . '</p>';
	}

	/**
	 * Field: Show excerpt for each listed item in llms.txt.
	 *
	 * @return void
	 */
	public function field_llms_show_excerpt() {
		$opt = $this->options->get();
		$v   = ! empty( $opt['llms_show_excerpt'] ) ? 1 : 0;

		echo '<label>';
		echo '<input type="checkbox" name="' . esc_attr( Options::OPTION_KEY ) . '[llms_show_excerpt]" value="1" ' . checked( 1, $v, false ) . ' />';
		echo ' ' . esc_html__( 'Add excerpt (if available) under each item in llms.txt', 'llm-friendly' );
		echo '</label>';
	}

	/**
	 * Field: Sitemap URL.
	 *
	 * @return void
	 */
	public function field_sitemap_url() {
		$opt = $this->options->get();
		$v = isset($opt['sitemap_url']) ? (string)$opt['sitemap_url'] : '/sitemap.xml';

		echo '<input type="text" class="regular-text" name="' . esc_attr(Options::OPTION_KEY) . '[sitemap_url]" value="' . esc_attr($v) . '" />';
		echo '<p class="description">' . esc_html__('Absolute URL or site-relative path. Default: /sitemap.xml', 'llm-friendly') . '</p>';
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

	echo '<textarea class="large-text code" rows="8" name="' . esc_attr(Options::OPTION_KEY) . '[llms_custom_markdown]">' . esc_textarea($v) . '</textarea>';
	echo '<p class="description">' . esc_html__('Optional markdown inserted into llms.txt between the site meta and the content sections. Leave empty to insert nothing.', 'llm-friendly') . '</p>';
}


	/**
	 * Field: Site title override.
	 *
	 * @return void
	 */
	public function field_site_title_override() {
		$opt = $this->options->get();
		$v = isset($opt['site_title_override']) ? (string)$opt['site_title_override'] : '';

		echo '<input type="text" class="regular-text" name="' . esc_attr(Options::OPTION_KEY) . '[site_title_override]" value="' . esc_attr($v) . '" />';
		echo '<p class="description">' . esc_html__('If empty, uses WordPress setting: Site Title.', 'llm-friendly') . '</p>';
	}

	/**
	 * Field: Site description override.
	 *
	 * @return void
	 */
	public function field_site_description_override() {
		$opt = $this->options->get();
		$v = isset($opt['site_description_override']) ? (string)$opt['site_description_override'] : '';

		echo '<input type="text" class="regular-text" name="' . esc_attr(Options::OPTION_KEY) . '[site_description_override]" value="' . esc_attr($v) . '" />';
		echo '<p class="description">' . esc_html__('If empty, uses WordPress setting: Tagline.', 'llm-friendly') . '</p>';
	}

	/**
	 * Classic Editor metabox: add UI for Markdown override (only when Gutenberg is not used).
	 *
	 * @param string  $post_type
	 * @param WP_Post $post
	 * @return void
	 */
	public function maybe_add_md_override_metabox( $post_type, $post ) {
		if ( ! ( $post instanceof \WP_Post ) ) {
			return;
		}

		$opt     = $this->options->get();
		$allowed = isset( $opt['post_types'] ) && is_array( $opt['post_types'] ) ? $opt['post_types'] : array();
		if ( ! in_array( (string) $post_type, $allowed, true ) ) {
			return;
		}

		// Hide metabox in Gutenberg, because we already have a sidebar panel there.
		if ( function_exists( 'use_block_editor_for_post' ) && use_block_editor_for_post( $post ) ) {
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
	 * Render Classic Editor metabox.
	 *
	 * @param WP_Post $post
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

		wp_nonce_field( 'llmf_md_override_save', 'llmf_md_override_nonce' );

		echo '<p class="description">' . esc_html__( 'If filled, this text will replace the post content in the Markdown export.', 'llm-friendly' ) . '</p>';
		echo '<textarea style="width:100%;min-height:240px" name="llmf_md_content_override">' . esc_textarea( $val ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Leave empty to export the post content. You can paste plain Markdown or Gutenberg block markup (<!-- wp: ... -->).', 'llm-friendly' ) . '</p>';
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

		$opt     = $this->options->get();
		$allowed = isset( $opt['post_types'] ) && is_array( $opt['post_types'] ) ? $opt['post_types'] : array();
		if ( ! in_array( (string) $post->post_type, $allowed, true ) ) {
			return;
		}

		$value = isset( $_POST['llmf_md_content_override'] ) ? (string) wp_unslash( $_POST['llmf_md_content_override'] ) : '';
		$value = trim( $value );

		if ( $value === '' ) {
			delete_post_meta( $post_id, Exporter::META_MD_OVERRIDE );
			return;
		}

		update_post_meta( $post_id, Exporter::META_MD_OVERRIDE, $value );
	}


}
