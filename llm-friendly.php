<?php
/**
 *  Plugin Name:    LLM Friendly
 *  Plugin URI:     https://github.com/sherer-pro/llm-friendly
 *  Description:    Adds llms.txt and Markdown endpoints to WordPress for LLM-friendly content access.
 *  Version:        0.1.0
 *  Author:         Pavel Sherer
 *  Author URI:     https://sherer.pro
 *  License:        GPL-3.0-or-later
 *  License URI:    https://www.gnu.org/licenses/gpl-3.0.html
 *  Text Domain:    llm-friendly
 *  Domain Path:    /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Текущая версия плагина, используется для кешей и ссылок на ассеты.
 * Меняем здесь и в заголовке одновременно, чтобы WordPress корректно считал обновления.
 */
define( 'LLMF_VERSION', '0.1.0' );
define( 'LLMF_FILE', __FILE__ );
define( 'LLMF_DIR', plugin_dir_path( __FILE__ ) );
define( 'LLMF_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check minimum requirements.
 *
 * @return bool
 */
function llmf_requirements_met() {
	$php_ok = version_compare( PHP_VERSION, '7.4', '>=' );

	$wp_version = get_bloginfo( 'version' );
	$wp_ok      = is_string( $wp_version ) ? version_compare( $wp_version, '6.0', '>=' ) : false;

	return $php_ok && $wp_ok;
}

/**
 * Show admin notice if requirements are not met.
 *
 * @return void
 */
function llmf_requirements_notice() {
	// Show this notice only on plugin list screens to avoid site-wide admin notices.
	if ( ! function_exists( 'get_current_screen' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || ( 'plugins' !== $screen->id && 'plugins-network' !== $screen->id ) ) {
		return;
	}

	$php_ok     = version_compare( PHP_VERSION, '7.4', '>=' );
	$wp_version = get_bloginfo( 'version' );
	$wp_ok      = is_string( $wp_version ) ? version_compare( $wp_version, '6.0', '>=' ) : false;

	$errors = array();

	if ( ! $php_ok ) {
		$errors[] = sprintf(
		/* translators: 1: Current PHP version, 2: Required PHP version */
			esc_html__( 'PHP version %1$s detected. Required: %2$s+', 'llm-friendly' ),
			esc_html( PHP_VERSION ),
			'7.4'
		);
	}

	if ( ! $wp_ok ) {
		$errors[] = sprintf(
		/* translators: 1: Current WP version, 2: Required WP version */
			esc_html__( 'WordPress version %1$s detected. Required: %2$s+', 'llm-friendly' ),
			esc_html( is_string( $wp_version ) ? $wp_version : '' ),
			'6.0'
		);
	}

	if ( empty( $errors ) ) {
		return;
	}

	$heading = esc_html__( 'LLM Friendly is disabled due to unmet requirements:', 'llm-friendly' );

	echo '<div class="notice notice-error"><p><strong>' . $heading . '</strong></p><ul>';

	foreach ( $errors as $msg ) {
		echo '<li>' . esc_html( $msg ) . '</li>';
	}

	echo '</ul></div>';
}

/**
 * Prevent activation when requirements are not met.
 *
 * @return void
 */
function llmf_activate() {
	if ( ! llmf_requirements_met() ) {
		if ( is_admin() ) {
			add_action( 'admin_notices', 'llmf_requirements_notice' );
		}
		wp_die( esc_html__( 'LLM Friendly cannot be activated because the environment does not meet the minimum requirements.', 'llm-friendly' ) );
	}
	\LLMFriendly\Plugin::activate();
}

function llmf_deactivate() {
	\LLMFriendly\Plugin::deactivate();
}

require_once LLMF_DIR . 'inc/Options.php';
require_once LLMF_DIR . 'inc/Exporter.php';
require_once LLMF_DIR . 'inc/Llms.php';
require_once LLMF_DIR . 'inc/Rewrites.php';
require_once LLMF_DIR . 'inc/Admin.php';
require_once LLMF_DIR . 'inc/Plugin.php';

register_activation_hook( __FILE__, 'llmf_activate' );
register_deactivation_hook( __FILE__, 'llmf_deactivate' );

if ( ! llmf_requirements_met() ) {
	add_action( 'admin_notices', 'llmf_requirements_notice' );
	add_action( 'network_admin_notices', 'llmf_requirements_notice' );

	// Auto-deactivate if it was activated on an incompatible environment.
	add_action( 'admin_init', function () {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! function_exists( 'deactivate_plugins' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( function_exists( 'deactivate_plugins' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
		}
	} );

	return;
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'llmf_add_settings_link' );

function llmf_add_settings_link( $links ) {
	$url = admin_url( 'options-general.php?page=llm-friendly' );

	$settings_link = '<a href="' . esc_url( $url ) . '">'
	                 . esc_html__( 'Settings', 'llm-friendly' )
	                 . '</a>';

	array_unshift( $links, $settings_link );

	return $links;
}

/**
 * Important: do NOT call Plugin->init() directly here.
 * Boot plugin after all plugins are loaded.
 */
add_action( 'plugins_loaded', array( '\LLMFriendly\Plugin', 'instance' ) );
