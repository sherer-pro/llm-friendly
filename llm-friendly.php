<?php
/**
 * Plugin Name: LLM Friendly
 * Description: Adds llms.txt and Markdown endpoints to WordPress for LLM-friendly content access.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Pavel Sherer
 * License: GPL-2.0-or-later
 * Text Domain: llm-friendly
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Текущая версия плагина, используется для кешей и ссылок на ассеты.
 * Меняем здесь и в заголовке одновременно, чтобы WordPress корректно считал обновления.
 */
define('LLMF_VERSION', '0.1.0');
define('LLMF_FILE', __FILE__);
define('LLMF_DIR', plugin_dir_path(__FILE__));
define('LLMF_URL', plugin_dir_url(__FILE__));

/**
 * Check minimum requirements.
 *
 * @return bool
 */
function llmf_requirements_met() {
	$php_ok = version_compare(PHP_VERSION, '7.4', '>=');

	$wp_version = get_bloginfo('version');
	$wp_ok = is_string($wp_version) ? version_compare($wp_version, '6.0', '>=') : false;

	return $php_ok && $wp_ok;
}

/**
 * Show admin notice if requirements are not met.
 *
 * @return void
 */
function llmf_requirements_notice() {
	$php_ok = version_compare(PHP_VERSION, '7.4', '>=');
	$wp_version = get_bloginfo('version');
	$wp_ok = is_string($wp_version) ? version_compare($wp_version, '6.0', '>=') : false;

	$msg = array();

	if (!$php_ok) {
		$msg[] = sprintf(
			/* translators: 1: current PHP version, 2: required PHP version */
			esc_html__('LLM Friendly requires PHP %2$s or newer. You are running %1$s.', 'llm-friendly'),
			esc_html(PHP_VERSION),
			esc_html('7.4')
		);
	}

	if (!$wp_ok) {
		$msg[] = sprintf(
			/* translators: 1: current WP version, 2: required WP version */
			esc_html__('LLM Friendly requires WordPress %2$s or newer. You are running %1$s.', 'llm-friendly'),
			esc_html(is_string($wp_version) ? $wp_version : 'unknown'),
			esc_html('6.0')
		);
	}

	if (empty($msg)) {
		return;
	}

	echo '<div class="notice notice-error"><p>' . implode('<br>', $msg) . '</p></div>';
}

/**
 * Prevent activation when requirements are not met.
 *
 * @return void
 */
function llmf_activate() {
	if (!llmf_requirements_met()) {
		if (is_admin()) {
			add_action('admin_notices', 'llmf_requirements_notice');
		}
		wp_die(esc_html__('LLM Friendly cannot be activated because the environment does not meet the minimum requirements.', 'llm-friendly'));
	}
	\LLM_Friendly\Plugin::activate();
}

function llmf_deactivate() {
	\LLM_Friendly\Plugin::deactivate();
}

require_once LLMF_DIR . 'inc/Options.php';
require_once LLMF_DIR . 'inc/Exporter.php';
require_once LLMF_DIR . 'inc/Llms.php';
require_once LLMF_DIR . 'inc/Rewrites.php';
require_once LLMF_DIR . 'inc/Admin.php';
require_once LLMF_DIR . 'inc/Plugin.php';

register_activation_hook(__FILE__, 'llmf_activate');
register_deactivation_hook(__FILE__, 'llmf_deactivate');

if (!llmf_requirements_met()) {
	add_action('admin_notices', 'llmf_requirements_notice');
	add_action('network_admin_notices', 'llmf_requirements_notice');

	// Auto-deactivate if it was activated on an incompatible environment.
	add_action('admin_init', function () {
		if (!is_admin()) return;

		if (!function_exists('deactivate_plugins')) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if (function_exists('deactivate_plugins')) {
			deactivate_plugins(plugin_basename(__FILE__));
		}
	});

	return;
}

/**
 * Important: do NOT call Plugin->init() directly here.
 * Boot plugin after all plugins are loaded.
 */
add_action('plugins_loaded', array('\LLM_Friendly\Plugin', 'instance'));
