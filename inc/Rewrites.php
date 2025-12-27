<?php

namespace LLM_Friendly;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrite rules & query vars.
 */
final class Rewrites {
	/**
	 * Query vars.
	 */
	const QV_LLMS = 'llmf_llms';
	const QV_MD = 'llmf_md';
	const QV_PT = 'llmf_pt';
	const QV_PATH = 'llmf_path';

	/**
	 * @var Options
	 */
	private $options;

	/**
	 * @param Options $options
	 */
	public function __construct( $options ) {
		$this->options = $options;

		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
	}

	/**
	 * Add plugin query vars.
	 *
	 * @param array<int,string> $vars
	 *
	 * @return array<int,string>
	 */
	public function add_query_vars( $vars ) {
		$vars[] = self::QV_LLMS;
		$vars[] = self::QV_MD;
		$vars[] = self::QV_PT;
		$vars[] = self::QV_PATH;

		return $vars;
	}

	/**
	 * Register rewrite rules.
	 *
	 * @return void
	 */
	public function add_rules() {
		// Ensure rewrite API is available (e.g. during activation).
		global $wp_rewrite;
		if ( ! ( $wp_rewrite instanceof \WP_Rewrite ) && class_exists( '\\WP_Rewrite' ) ) {
			$wp_rewrite = new \WP_Rewrite();
		}

		$opt = $this->options->get();

		// llms.txt
		if ( ! empty( $opt['enabled_llms_txt'] ) ) {
			add_rewrite_rule(
				'^llms\.txt$',
				'index.php?' . self::QV_LLMS . '=1',
				'top'
			);
		}

		// Markdown exports
		if ( ! empty( $opt['enabled_markdown'] ) ) {
			$base = $this->options->sanitize_base_path( (string) $opt['base_path'] );

			// Back-compat: /{base}/blog/{path}.md -> post
			$allowed = is_array( $opt['post_types'] ) ? array_map( 'sanitize_key', (array) $opt['post_types'] ) : array();
			if ( in_array( 'post', $allowed, true ) ) {
				add_rewrite_rule(
					'^' . preg_quote( $base, '~' ) . '/blog/(.+)\.md$',
					'index.php?' . self::QV_MD . '=1&' . self::QV_PT . '=post&' . self::QV_PATH . '=$matches[1]',
					'top'
				);


			}
// Generic: /{base}/{post_type}/{path}.md
			add_rewrite_rule(
				'^' . preg_quote( $base, '~' ) . '/([^/]+)/(.+)\.md$',
				'index.php?' . self::QV_MD . '=1&' . self::QV_PT . '=$matches[1]&' . self::QV_PATH . '=$matches[2]',
				'top'
			);
		}
	}
}
