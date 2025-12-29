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
			add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
			add_action('add_meta_boxes', array($this, 'maybe_add_md_override_metabox'), 10, 2);
			add_action('save_post', array($this, 'save_md_override_metabox'), 10, 2);
			add_action('admin_post_llmf_regenerate_llms', array($this, 'handle_regenerate_llms'));
			add_action('wp_ajax_llmf_search_posts', array($this, 'ajax_search_posts'));
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

		add_settings_field(
			'excluded_posts',
			__( 'Excluded items', 'llm-friendly' ),
			array( $this, 'field_excluded_posts' ),
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

	/**
	 * Подключает JS ассеты для страницы настроек плагина.
	 *
	 * @param string $hook Текущий экран в админке.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		// Грузим только на странице настроек плагина, чтобы не засорять остальные экраны.
		if ( $hook !== 'settings_page_llm-friendly' ) {
			return;
		}

		$handle = 'llmf-admin';
		$src    = trailingslashit( LLMF_URL ) . 'assets/llmf-admin.js';

		wp_enqueue_script(
			$handle,
			$src,
			array(),
			defined( 'LLMF_VERSION' ) ? (string) LLMF_VERSION : false,
			true
		);

		wp_localize_script(
			$handle,
			'LLMF_ADMIN',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'llmf_search_posts' ),
				'minChars' => 2,
				'i18n'     => array(
					'searchPlaceholder' => __( 'Start typing from 2 characters…', 'llm-friendly' ),
					'searching'         => __( 'Searching…', 'llm-friendly' ),
					'typeMore'          => __( 'Enter at least 2 characters to search.', 'llm-friendly' ),
					'nothingFound'      => __( 'Nothing found for this query.', 'llm-friendly' ),
					'addAction'         => __( 'Add to exclusions', 'llm-friendly' ),
					'removeAction'      => __( 'Remove from exclusions', 'llm-friendly' ),
					'selectedEmpty'     => __( 'No items are excluded yet.', 'llm-friendly' ),
					'searchError'       => __( 'Search failed, please try again.', 'llm-friendly' ),
				),
			)
		);
	}

	/**
	 * Рисует страницу настроек плагина в админке.
	 *
	 * @return void
	 */
	public function render_page() {
		if (!current_user_can('manage_options')) {
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>LLM Friendly</h1>';

		$notice       = isset( $_GET['llmf_msg'] ) ? sanitize_key( wp_unslash( (string) $_GET['llmf_msg'] ) ) : '';
		$notice_nonce = isset( $_GET['llmf_msg_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['llmf_msg_nonce'] ) ) : '';

		if ( $notice === 'regen_ok' && wp_verify_nonce( $notice_nonce, 'llmf_admin_notice' ) ) {
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
	 * AJAX-поиск записей для списка исключений.
	 *
	 * Вызывается из админского JS по мере ввода текста. Возвращает JSON
	 * с подходящими записями выбранного типа или сообщение об ошибке.
	 *
	 * @return void
	 */
	public function ajax_search_posts() {
		// Проверяем права раньше, чтобы не раскрывать наличие записей.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'llm-friendly' ) ), 403 );
		}

		check_ajax_referer( 'llmf_search_posts', 'nonce' );

		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( (string) $_GET['post_type'] ) : '';
		$query     = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) : '';
		$query     = trim( $query );

		// Требуем минимум 2 символа, чтобы не нагружать БД.
		if ( strlen( $query ) < 2 ) {
			wp_send_json_error( array( 'message' => __( 'Enter at least 2 characters to search.', 'llm-friendly' ) ), 400 );
		}

		$opt           = $this->options->get();
		$allowed_types = isset( $opt['post_types'] ) && is_array( $opt['post_types'] ) ? array_map( 'sanitize_key', $opt['post_types'] ) : array();

		if ( $post_type === '' || ! in_array( $post_type, $allowed_types, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Post type is not allowed.', 'llm-friendly' ) ), 400 );
		}

		$excluded_ids = $this->options->excluded_post_ids( $post_type );

		// Готовим запрос только по опубликованным записям и без тяжелых подсчетов.
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
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- нужно исключить выбранные записи, чтобы администратор не добавил их повторно.
			'post__not_in'           => $excluded_ids,
		);

		$found = get_posts( $args );
		$items = array();

		foreach ( (array) $found as $p ) {
			if ( ! ( $p instanceof \WP_Post ) ) {
				continue;
			}

			// Не выдаем защищенные паролем материалы и уважаем внешний фильтр.
			if ( ! empty( $p->post_password ) ) {
				continue;
			}

			$can = apply_filters( 'llmf_can_export_post', true, $p, 'llms_search' );
			if ( ! $can ) {
				continue;
			}

			$title = get_the_title( $p );
			/* translators: %d: Post ID. */
			$title = $title !== '' ? $title : sprintf( __( 'Item #%d', 'llm-friendly' ), $p->ID );

			$items[] = array(
				'id'    => (int) $p->ID,
				'title' => (string) $title,
			);
		}

		wp_send_json_success( array( 'items' => $items ) );
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
			echo '<input type="checkbox" class="llmf-post-type-toggle" data-post-type="' . esc_attr( $pt ) . '" name="' . esc_attr(Options::OPTION_KEY) . '[post_types][]" value="' . esc_attr($pt) . '" ' . wp_kses( $checked, [] ) . ' />';
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
	 * Поле: Исключение записей из llms.txt и Markdown-экспорта.
	 *
	 * Показывает выбранные типы записей, позволяет искать материалы по названию
	 * асинхронно (без перезагрузки страницы) и добавлять/удалять элементы списка исключений.
	 *
	 * @return void
	 */
	public function field_excluded_posts() {
		$opt      = $this->options->get();
		$selected = isset( $opt['post_types'] ) && is_array( $opt['post_types'] ) ? $opt['post_types'] : array();

		$pts = get_post_types( array( 'public' => true ), 'objects' );
		if ( ! is_array( $pts ) ) {
			$pts = array();
		}

		// Исключаем вложения, чтобы не предлагать медиабиблиотеку.
		unset( $pts['attachment'] );

		if ( empty( $pts ) ) {
			echo '<p class="description">' . esc_html__( 'Select at least one post type above to manage exclusions.', 'llm-friendly' ) . '</p>';
			return;
		}

		// Небольшие стили для удобства чтения нового интерфейса поиска/исключений.
		echo '<style>
		.llmf-excluded-posts__wrap{border:1px solid #dcdcde;border-radius:6px;padding:12px;max-width:900px;}
		.llmf-excluded-posts__type{border:1px solid #e0e0e0;border-radius:4px;padding:12px;margin-bottom:12px;background:#fff;position:relative;}
		.llmf-excluded-posts__type--hidden{display:none;}
		.llmf-excluded-posts__search{position:relative;max-width:520px;}
		.llmf-excluded-posts__dropdown{position:absolute;z-index:10;top:38px;left:0;right:0;background:#fff;border:1px solid #ccd0d4;box-shadow:0 2px 6px rgba(0,0,0,0.08);max-height:220px;overflow:auto;display:none;}
		.llmf-excluded-posts__dropdown-item{padding:8px 10px;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;gap:8px;align-items:center;}
		.llmf-excluded-posts__dropdown-item:last-child{border-bottom:0;}
		.llmf-excluded-posts__dropdown-item button{white-space:nowrap;}
		.llmf-excluded-posts__selected{border:1px solid #e5e5e5;border-radius:4px;padding:8px;max-height:240px;overflow:auto;background:#f8f9fa;}
		.llmf-excluded-posts__selected-item{display:flex;align-items:center;justify-content:space-between;padding:6px 4px;border-bottom:1px solid #ececec;gap:8px;}
		.llmf-excluded-posts__selected-item:last-child{border-bottom:0;}
		.llmf-excluded-posts__selected-item .button-link{color:#b32d2e;}
		.llmf-excluded-posts__empty{margin:0;}
		</style>';

		echo '<p class="description">' . esc_html__( 'Find items by title in real time, add them to the exclusion list, and remove them with one click. Excluded items are omitted from llms.txt and Markdown exports.', 'llm-friendly' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Search starts after typing at least two characters and runs asynchronously without reloading the page.', 'llm-friendly' ) . '</p>';

		// Контейнер с данными для JS: подсказки и nonce уже выдаются через wp_localize_script().
		echo '<div class="llmf-excluded-posts__wrap" id="llmf-excluded-posts">';

		foreach ( $pts as $pt => $obj ) {
			$pt         = sanitize_key( (string) $pt );
			$is_enabled = in_array( $pt, $selected, true );
			$label      = isset( $obj->labels->name ) ? (string) $obj->labels->name : $pt;

			if ( $pt === '' ) {
				continue;
			}

			$excluded_ids = $this->options->excluded_post_ids( $pt );
			$items        = array();

			if ( ! empty( $excluded_ids ) ) {
				$query_args = array(
					'post_type'              => $pt,
					'post__in'               => $excluded_ids,
					'orderby'                => 'post__in',
					'posts_per_page'         => count( $excluded_ids ),
					'post_status'            => 'publish',
					'no_found_rows'          => true,
					'ignore_sticky_posts'    => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				);
				$found      = get_posts( $query_args );
				foreach ( (array) $found as $p ) {
					if ( ! ( $p instanceof \WP_Post ) ) {
						continue;
					}
					$title = get_the_title( $p );
					/* translators: %d: Post ID. */
					$title = $title !== '' ? $title : sprintf( __( 'Item #%d', 'llm-friendly' ), $p->ID );
					$items[] = array(
						'id'    => (int) $p->ID,
						'title' => $title,
					);
				}
			}

			$hidden_class = $is_enabled ? '' : ' llmf-excluded-posts__type--hidden';

			echo '<div class="llmf-excluded-posts__type' . esc_attr( $hidden_class ) . '" data-post-type="' . esc_attr( $pt ) . '" data-post-label="' . esc_attr( $label ) . '">';
			echo '<h4 style="margin:0 0 10px;">' . esc_html( $label ) . ' <code>' . esc_html( $pt ) . '</code></h4>';

			echo '<div class="llmf-excluded-posts__search">';
			echo '<label style="display:block;margin-bottom:4px;" for="llmf-search-' . esc_attr( $pt ) . '">' . esc_html__( 'Search by title', 'llm-friendly' ) . '</label>';
			echo '<input type="text" class="regular-text llmf-excluded-posts__search-input" id="llmf-search-' . esc_attr( $pt ) . '" data-post-type="' . esc_attr( $pt ) . '" placeholder="' . esc_attr__( 'Start typing from 2 characters…', 'llm-friendly' ) . '" />';
			echo '<div class="llmf-excluded-posts__dropdown" data-post-type="' . esc_attr( $pt ) . '"></div>';
			echo '<p class="description" style="margin-top:6px;">' . esc_html__( 'Type at least two characters to search. Results appear in the dropdown instantly.', 'llm-friendly' ) . '</p>';
			echo '</div>';

			echo '<p style="margin:10px 0 6px;font-weight:600;">' . esc_html__( 'Currently excluded', 'llm-friendly' ) . '</p>';
			echo '<div class="llmf-excluded-posts__selected" data-post-type="' . esc_attr( $pt ) . '">';

			if ( empty( $items ) ) {
				echo '<p class="description llmf-excluded-posts__empty">' . esc_html__( 'No items are excluded yet.', 'llm-friendly' ) . '</p>';
			} else {
				foreach ( $items as $item ) {
					$post_id = isset( $item['id'] ) ? (int) $item['id'] : 0;
					$title   = isset( $item['title'] ) ? (string) $item['title'] : '';
					if ( $post_id <= 0 || $title === '' ) {
						continue;
					}

					echo '<div class="llmf-excluded-posts__selected-item" data-post-id="' . esc_attr( (string) $post_id ) . '">';
					echo '<label class="llmf-excluded-posts__selected-label" style="flex:1;">';
					echo '<input type="checkbox" class="llmf-excluded-posts__checkbox" name="' . esc_attr( Options::OPTION_KEY ) . '[excluded_posts][' . esc_attr( $pt ) . '][]" value="' . esc_attr( (string) $post_id ) . '" checked="checked" /> ';
					echo esc_html( $title ) . ' <span class="description">(' . esc_html( sprintf( '#%d', $post_id ) ) . ')</span>';
					echo '</label>';
					echo '<button type="button" class="button-link llmf-excluded-posts__remove" aria-label="' . esc_attr__( 'Remove from exclusions', 'llm-friendly' ) . '">×</button>';
					echo '</div>';
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
		// Разрешаем только безопасный подмножество Markdown/HTML, чтобы исключить опасные теги и атрибуты.
		$value = wp_kses_post( $value );

		if ( $value === '' ) {
			delete_post_meta( $post_id, Exporter::META_MD_OVERRIDE );
			return;
		}

		update_post_meta( $post_id, Exporter::META_MD_OVERRIDE, $value );
	}


}
