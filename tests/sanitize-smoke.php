<?php
/**
 * Простой набор smoke-тестов для метода Options::sanitize().
 *
 * Скрипт не требует WordPress, так как ниже определены минимальные заглушки
 * функций и констант, которые использует плагин. Запускать так:
 *
 *   php tests/sanitize-smoke.php
 */

// --------------------------------------------------------------
// Минимальные заглушки WordPress, чтобы метод sanitize() работал изолированно.
// --------------------------------------------------------------

// Глобальное хранилище опций и transients.
$GLOBALS['__wp_options']    = array();
$GLOBALS['__wp_transients'] = array();

// Определяем ABSPATH, чтобы защитный блок плагина не завершил выполнение.
if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__);
}

if (!defined('MINUTE_IN_SECONDS')) {
	// Константа используется при выставлении transient для сброса правил.
	define('MINUTE_IN_SECONDS', 60);
}

/**
 * Заглушка get_option().
 *
 * @param string     $key     Ключ опции.
 * @param mixed|null $default Значение по умолчанию.
 * @return mixed
 */
function get_option($key, $default = null) {
	return array_key_exists($key, $GLOBALS['__wp_options']) ? $GLOBALS['__wp_options'][$key] : $default;
}

/**
 * Заглушка add_option().
 *
 * @param string $key   Ключ опции.
 * @param mixed  $value Значение.
 * @return void
 */
function add_option($key, $value) {
	$GLOBALS['__wp_options'][$key] = $value;
}

/**
 * Заглушка update_option().
 *
 * @param string $key   Ключ опции.
 * @param mixed  $value Значение.
 * @return void
 */
function update_option($key, $value) {
	$GLOBALS['__wp_options'][$key] = $value;
}

/**
 * Минимальный sanitize_key() — удаляем всё, кроме [a-z0-9_].
 *
 * @param string $key
 * @return string
 */
function sanitize_key($key) {
	return preg_replace('/[^a-z0-9_]/', '', strtolower((string) $key));
}

/**
 * Минимальный esc_url_raw() — обрезает пробелы и удаляет недопустимые байты.
 *
 * @param string $url
 * @return string
 */
function esc_url_raw($url) {
	return filter_var(trim((string) $url), FILTER_SANITIZE_URL);
}

/**
 * Заглушка get_bloginfo().
 *
 * @param string $field
 * @return string
 */
function get_bloginfo($field) {
	if ($field === 'name') {
		return 'Demo Site';
	}
	if ($field === 'description') {
		return 'Demo Description';
	}

	return '';
}

/**
 * Заглушка home_url() — преобразует относительный путь в URL.
 *
 * @param string $path
 * @return string
 */
function home_url($path = '') {
	return 'https://example.test' . $path;
}

/**
 * Минимальный wp_strip_all_tags().
 *
 * @param string $text
 * @return string
 */
function wp_strip_all_tags($text) {
	return strip_tags((string) $text);
}

/**
 * Заглушка set_transient().
 *
 * @param string $key
 * @param mixed  $value
 * @param int    $expiration
 * @return void
 */
function set_transient($key, $value, $expiration) {
	$GLOBALS['__wp_transients'][$key] = array(
		'value'      => $value,
		'expires_in' => $expiration,
	);
}

// --------------------------------------------------------------
// Подключаем тестируемый класс.
// --------------------------------------------------------------
require_once __DIR__ . '/../inc/Options.php';

use LLM_Friendly\Options;

// --------------------------------------------------------------
// Простейший раннер с проверками и выводом результата.
// --------------------------------------------------------------

/**
 * Бросает исключение, если выражение ложно.
 *
 * @param bool   $condition
 * @param string $message
 * @return void
 * @throws RuntimeException
 */
function assert_true($condition, $message) {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

/**
 * Удобный ассерт для строгого сравнения.
 *
 * @param mixed  $expected
 * @param mixed  $actual
 * @param string $message
 * @return void
 */
function assert_same($expected, $actual, $message) {
	assert_true($expected === $actual, $message . ' (ожидалось ' . var_export($expected, true) . ', получили ' . var_export($actual, true) . ')');
}

/**
 * Сбрасывает внутреннее состояние между тестами.
 *
 * @return void
 */
function reset_state() {
	$GLOBALS['__wp_options']    = array();
	$GLOBALS['__wp_transients'] = array();
}

// --------------------------------------------------------------
// Набор smoke-тестов.
// --------------------------------------------------------------

$tests = array();

$tests['defaults_are_created'] = function () {
	reset_state();
	$options = new Options();
	$options->ensure_defaults();
	$stored = get_option(Options::OPTION_KEY);

	assert_true(is_array($stored), 'Должен сохраниться массив по умолчанию');
	assert_true($stored['enabled_markdown'] === 1, 'Маркер enabled_markdown должен быть равен 1');
};

$tests['cache_is_cleared_on_affecting_changes'] = function () {
	reset_state();
	$options = new Options();

	// Предыдущие опции с заполненным кешем.
	$previous = $options->defaults();
	$previous['llms_cache']               = 'cached';
	$previous['llms_cache_ts']            = 999;
	$previous['llms_cache_rev']           = 5;
	$previous['llms_cache_hash']          = 'hash';
	$previous['llms_cache_settings_hash'] = 'settings-hash';
	add_option(Options::OPTION_KEY, $previous);

	// Меняем ключ, влияющий на содержимое файла.
	$input  = $previous;
	$input['llms_custom_markdown'] = '# новый блок';

	$result = $options->sanitize($input);

	assert_same('', $result['llms_cache'], 'Кеш содержимого должен быть сброшен');
	assert_same(0, $result['llms_cache_ts'], 'Метка времени кеша должна быть сброшена');
	assert_same(0, $result['llms_cache_rev'], 'Версия кеша должна быть сброшена');
	assert_same('', $result['llms_cache_hash'], 'Хеш кеша должен быть сброшен');
	assert_same('', $result['llms_cache_settings_hash'], 'Хеш настроек кеша должен быть сброшен');
};

$tests['rewrite_transient_is_set_when_needed'] = function () {
	reset_state();
	$options = new Options();

	add_option(Options::OPTION_KEY, $options->defaults());

	$input = $options->defaults();
	$input['base_path'] = 'changed-path';

	$options->sanitize($input);

	assert_true(isset($GLOBALS['__wp_transients']['llmf_flush_rewrite_rules']), 'Transient для flush должен быть установлен');
};

$tests['recent_limit_is_clamped'] = function () {
	reset_state();
	$options = new Options();
	add_option(Options::OPTION_KEY, $options->defaults());

	$input_low                     = $options->defaults();
	$input_low['llms_recent_limit'] = -5;
	$result_low                     = $options->sanitize($input_low);
	assert_same(1, $result_low['llms_recent_limit'], 'Лимит должен быть не меньше 1');

	$input_high                     = $options->defaults();
	$input_high['llms_recent_limit'] = 500;
	$result_high                    = $options->sanitize($input_high);
	assert_same(200, $result_high['llms_recent_limit'], 'Лимит должен быть не больше 200');
};

$tests['custom_markdown_is_normalized'] = function () {
	reset_state();
	$options = new Options();
	add_option(Options::OPTION_KEY, $options->defaults());

	$input = $options->defaults();
	$input['llms_custom_markdown'] = " \0# Заголовок\r\nСтрока\r\n\r\n";

	$result = $options->sanitize($input);

	assert_same("# Заголовок\nСтрока", $result['llms_custom_markdown'], 'Markdown должен очищаться от нулевых байтов и нормализовать переносы строк');
};

$tests['base_path_falls_back_to_default'] = function () {
	reset_state();
	$options = new Options();
	add_option(Options::OPTION_KEY, $options->defaults());

	$input = $options->defaults();
	$input['base_path'] = '   ';

	$result = $options->sanitize($input);

	assert_same('llm', $result['base_path'], 'Пустой или некорректный base_path должен заменяться на llm');
};

// --------------------------------------------------------------
// Выполнение тестов.
// --------------------------------------------------------------

$passed = 0;

foreach ($tests as $name => $fn) {
	try {
		$fn();
		$passed++;
		echo "[OK] {$name}\n";
	} catch (Throwable $e) {
		echo "[FAIL] {$name}: " . $e->getMessage() . "\n";
		exit(1);
	}
}

echo "Все тесты пройдены: {$passed}\n";
