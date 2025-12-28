<?php
namespace LLM_Friendly;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Options storage and sanitization.
 */
final class Options {
	/**
	 * Option key in wp_options.
	 */
	public const OPTION_KEY = 'llmf_options';

	/**
	 * Ensure defaults exist in DB.
	 *
	 * @return void
	 */
	public function ensure_defaults() {
		$saved = get_option( self::OPTION_KEY, null );
		if ( ! is_array( $saved ) ) {
			add_option( self::OPTION_KEY, $this->defaults(), '', false );
		}
	}

	/**
	 * Get merged options.
	 *
	 * @return array<string,mixed>
	 */
	public function get() {
		$defaults = $this->defaults();
		$saved    = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return array_merge( $defaults, $saved );
	}

	/**
	 * Update options (sanitized).
	 *
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>
	 */
	public function update( $input ) {
		$clean = $this->sanitize( $input );
		update_option( self::OPTION_KEY, $clean, false );
		return $clean;
	}

	/**
	 * Sanitize options for Settings API.
	 *
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$prev  = $this->get();

		$out = $prev;

		$out['enabled_markdown'] = ! empty( $input['enabled_markdown'] ) ? 1 : 0;
		$out['enabled_llms_txt'] = ! empty( $input['enabled_llms_txt'] ) ? 1 : 0;

		$out['base_path'] = $this->sanitize_base_path( isset( $input['base_path'] ) ? (string) $input['base_path'] : $prev['base_path'] );

		$post_types = array();
		if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			foreach ( $input['post_types'] as $pt ) {
				$pt = sanitize_key( (string) $pt );
				if ( $pt !== '' ) {
					$post_types[] = $pt;
				}
			}
		}
		$out['post_types'] = array_values( array_unique( $post_types ) );
		if ( empty( $out['post_types'] ) ) {
			$out['post_types'] = array( 'post' );
		}

		$out['llms_send_noindex'] = ! empty( $input['llms_send_noindex'] ) ? 1 : 0;
		$out['md_send_noindex']   = ! empty( $input['md_send_noindex'] ) ? 1 : 0;

		$mode = isset( $input['llms_regen_mode'] ) ? (string) $input['llms_regen_mode'] : (string) $prev['llms_regen_mode'];
		$mode = $mode === 'manual' ? 'manual' : 'auto';
		$out['llms_regen_mode'] = $mode;

		$limit = isset( $input['llms_recent_limit'] ) ? (int) $input['llms_recent_limit'] : (int) $prev['llms_recent_limit'];
		if ( $limit < 1 ) {
			$limit = 1;
		}
		if ( $limit > 200 ) {
			$limit = 200;
		}
		$out['llms_recent_limit'] = $limit;

		$out['site_title_override']      = isset( $input['site_title_override'] ) ? $this->sanitize_textline( $input['site_title_override'] ) : (string) $prev['site_title_override'];
		$out['site_description_override'] = isset( $input['site_description_override'] ) ? $this->sanitize_textline( $input['site_description_override'] ) : (string) $prev['site_description_override'];

		$out['sitemap_url'] = isset( $input['sitemap_url'] ) ? $this->sanitize_sitemap_url( (string) $input['sitemap_url'] ) : (string) $prev['sitemap_url'];

		$out['llms_custom_markdown'] = isset( $input['llms_custom_markdown'] )
			? $this->sanitize_llms_custom_markdown( (string) $input['llms_custom_markdown'] )
			: (string) $prev['llms_custom_markdown'];

		$out['llms_show_excerpt'] = ! empty( $input['llms_show_excerpt'] ) ? 1 : 0;

		// Кеш управляется внутри класса, поддерживаем обязательные ключи.
		if ( ! isset( $out['llms_cache'] ) ) {
			$out['llms_cache'] = '';
		}
		if ( ! isset( $out['llms_cache_ts'] ) ) {
			$out['llms_cache_ts'] = 0;
		}

		// Эти ключи влияют на содержимое llms.txt, поэтому при их изменении сбрасываем кеш.
		$affect_keys = array(
			'enabled_markdown',
			'enabled_llms_txt',
			'base_path',
			'post_types',
			'llms_recent_limit',
			'site_title_override',
			'site_description_override',
			'sitemap_url',
			'llms_custom_markdown',
			'llms_show_excerpt',
		);

		$changed = false;
		foreach ( $affect_keys as $k ) {
			$prev_v = isset( $prev[ $k ] ) ? $prev[ $k ] : null;
			$now_v  = isset( $out[ $k ] ) ? $out[ $k ] : null;
			if ( $prev_v != $now_v ) {
				$changed = true;
				break;
			}
		}

		if ( $changed ) {
			// Сбрасываем кеш и связанные метаданные, чтобы пересборка прошла корректно.
			$out['llms_cache'] = '';
			$out['llms_cache_ts'] = 0;
			$out['llms_cache_rev'] = 0;
			$out['llms_cache_hash'] = '';
			$out['llms_cache_settings_hash'] = '';
		} else {
			// Поддерживаем служебные поля кеша, если сохраненные данные их не содержат.
			if ( ! isset( $out['llms_cache_rev'] ) ) {
				$out['llms_cache_rev'] = 0;
			}
			if ( ! isset( $out['llms_cache_hash'] ) ) {
				$out['llms_cache_hash'] = '';
			}
			if ( ! isset( $out['llms_cache_settings_hash'] ) ) {
				$out['llms_cache_settings_hash'] = '';
			}
		}

		// Эти параметры влияют на URL, поэтому ставим transient для последующего сброса правил.
		$rewrite_keys     = array( 'enabled_markdown', 'enabled_llms_txt', 'base_path', 'post_types' );
		$rewrite_changed = false;
		foreach ( $rewrite_keys as $k ) {
			$prev_v = isset( $prev[ $k ] ) ? $prev[ $k ] : null;
			$now_v  = isset( $out[ $k ] ) ? $out[ $k ] : null;
			if ( $prev_v != $now_v ) {
				$rewrite_changed = true;
				break;
			}
		}

		if ( $rewrite_changed ) {
			// Откладываем flush до admin_init, чтобы избежать повторных вызовов во время сохранения настроек.
			set_transient( 'llmf_flush_rewrite_rules', 1, 10 * MINUTE_IN_SECONDS );
		}

		return $out;
	}

	/**
	 * Default options.
	 *
	 * @return array<string,mixed>
	 */
	public function defaults() {
		return array(
			'enabled_markdown' => 1,
			'enabled_llms_txt' => 1,
			'base_path'        => 'llm',
			'post_types'       => array( 'post' ),
			'llms_send_noindex'=> 1,
			'md_send_noindex'   => 0,
			'llms_regen_mode'  => 'auto',   // auto | manual
			'llms_recent_limit'=> 30,       // per post type
			'site_title_override' => '',
			'site_description_override' => '',
			'sitemap_url'      => '/sitemap.xml',
			'llms_custom_markdown' => '',
			'llms_show_excerpt' => 0,
			'llms_cache'       => '',
			'llms_cache_ts'    => 0,
			'llms_cache_rev'   => 0,
			'llms_cache_hash'  => '',
			'llms_cache_settings_hash' => '',
		);
	}

	/**
	 * Sanitize custom markdown inserted into llms.txt.
	 *
	 * We do NOT attempt to fully parse Markdown here; the goal is to keep it as-is
	 * while normalizing newlines and trimming.
	 *
	 * @param string $value
	 * @return string
	 */
	private function sanitize_llms_custom_markdown( $value ) {
		return $this->sanitize_markdown_block( (string) $value );
	}

	/**
	 * Sanitize base path used in URLs (no leading/trailing slashes).
	 *
	 * @param string $path
	 * @return string
	 */
	public function sanitize_base_path( $path ) {
		$path = (string) $path;
		$path = trim( $path );
		$path = trim( $path, "/ \t\n\r\0\x0B" );
		$path = preg_replace( '~[^a-zA-Z0-9\-_]~', '-', $path );
		$path = preg_replace( '~-{2,}~', '-', (string) $path );
		$path = strtolower( (string) $path );
		return $path !== '' ? $path : 'llm';
	}

	/**
	 * Compute export path for a post (supports hierarchy).
	 *
	 * @param WP_Post $post
	 * @return string
	 */
	public function post_path( WP_Post $post ) {
		$pt_obj = get_post_type_object( $post->post_type );
		$hier   = $pt_obj && ! empty( $pt_obj->hierarchical );

		if ( $hier ) {
			$uri = get_page_uri( $post );
			$uri = is_string( $uri ) ? $uri : '';
			return ltrim( $uri, '/' );
		}

		return (string) $post->post_name;
	}

	/**
	 * Get site title for llms.txt (override or WP setting).
	 *
	 * @return string
	 */
	public function site_title() {
		$opt = $this->get();
		$t   = trim( (string) $opt['site_title_override'] );
		if ( $t !== '' ) {
			return $t;
		}

		$wp = (string) get_bloginfo( 'name' );
		return trim( $wp );
	}

	/**
	 * Get site description for llms.txt (override or WP setting).
	 *
	 * @return string
	 */
	public function site_description() {
		$opt = $this->get();
		$d   = trim( (string) $opt['site_description_override'] );
		if ( $d !== '' ) {
			return $d;
		}

		$wp = (string) get_bloginfo( 'description' );
		return trim( $wp );
	}

	/**
	 * Normalize and validate sitemap URL/path.
	 *
	 * Accepts absolute URL or site-relative path.
	 *
	 * @param string $value
	 * @return string
	 */
	public function sanitize_sitemap_url( $value ) {
		$value = trim( (string) $value );
		if ( $value === '' ) {
			return '/sitemap.xml';
		}

		if ( preg_match( '~^https?://~i', $value ) ) {
			return esc_url_raw( $value );
		}

		if ( $value[0] !== '/' ) {
			$value = '/' . $value;
		}

		// Keep as path; will be expanded via home_url() when output.
		return $value;
	}

	/**
	 * Санитизирует произвольный блок Markdown для вставки в llms.txt.
	 *
	 * Метод нормализует переносы строк, удаляет нулевые байты и обрезает пробелы,
	 * чтобы подготовить содержимое к безопасной вставке без разрушения разметки.
	 *
	 * @param string $md Произвольный Markdown-блок, полученный от пользователя.
	 *
	 * @return string Очищенный Markdown без управляющих байтов и с единым переводом строк.
	 */
	private function sanitize_markdown_block( $md ) {
		$md = (string) $md;
		$md = str_replace( array( "\r\n", "\r" ), "\n", $md );
		$md = preg_replace( '/\x00+/', '', $md );
		$md = trim( $md );

		return $md;
	}

	/**
	 * Convert a sitemap setting into an absolute URL.
	 *
	 * @return string
	 */
	public function sitemap_absolute_url() {
		$opt = $this->get();
		$v   = (string) $opt['sitemap_url'];

		if ( preg_match( '~^https?://~i', $v ) ) {
			return esc_url_raw( $v );
		}

		$v = $this->sanitize_sitemap_url( $v );
		return home_url( $v );
	}

	/**
	 * Sanitize one-line text.
	 *
	 * @param mixed $value
	 * @return string
	 */
	private function sanitize_textline( $value ) {
		$s = (string) $value;
		$s = wp_strip_all_tags( $s, true );
		$s = html_entity_decode( $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$s = str_replace( array( "\r\n", "\r", "\n" ), ' ', $s );
		$s = preg_replace( '/\s+/u', ' ', $s );
		return trim( (string) $s );
	}
}
