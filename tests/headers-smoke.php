<?php
/**
 * Простейшие тесты заголовков и ответов 304 для Exporter и Llms.
 *
 * Скрипт имитирует окружение WordPress минимальными заглушками и
 * проверяет, что набор заголовков формируется один раз, а условные
 * запросы корректно завершаются кодом 304.
 *
 * Запуск:
 *   php tests/headers-smoke.php
 */

// --------------------------------------------------------------
// Заглушки функций WordPress в пространстве имен плагина.
// --------------------------------------------------------------

namespace LLM_Friendly {

	/**
	 * Глобальный лог вызовов header()/status_header().
	 *
	 * @var array<int,string>
	 */
	$GLOBALS['__llmf_header_log'] = array();

	/**
	 * Заглушка status_header() — записывает код и, при необходимости, кидает исключение.
	 *
	 * @param int $code Код HTTP-статуса.
	 *
	 * @return void
	 * @throws \RuntimeException
	 */
	function status_header( $code ) {
		$GLOBALS['__llmf_header_log'][] = 'STATUS:' . (int) $code;

		// В тестах удобно прерывать выполнение на 3xx, чтобы не доходить до exit.
		if ( defined( 'LLMF_TEST_THROW_ON_STATUS' ) && (int) $code >= 300 ) {
			throw new \RuntimeException( 'STATUS_' . (int) $code );
		}
	}

	/**
	 * Заглушка header() — записывает сформированную строку.
	 *
	 * @param string $header Строка заголовка.
	 *
	 * @return void
	 */
	function header( $header ) {
		$GLOBALS['__llmf_header_log'][] = 'HEADER:' . (string) $header;
	}

	/**
	 * Заглушка add_action() — в тесте нам не нужна логика хуков.
	 *
	 * @return void
	 */
	function add_action() {
	}
}

// --------------------------------------------------------------
// Тестовый раннер.
// --------------------------------------------------------------

namespace {

	use LLM_Friendly\Exporter;
	use LLM_Friendly\Llms;

	/**
	 * Минимальный объект Options для удовлетворения конструкторов.
	 */
	class DummyOptions {
		/**
		 * Возвращает пустую конфигурацию.
		 *
		 * @return array<string,mixed>
		 */
		public function get() {
			return array();
		}
	}

	// Минимальный ABSPATH, чтобы защитный код плагина не завершал выполнение.
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ );
	}

	// Подключаем классы после объявления заглушек и определения ABSPATH.
	require_once __DIR__ . '/../inc/Exporter.php';
	require_once __DIR__ . '/../inc/Llms.php';

	// Объявляем константу один раз: она не мешает статусу 200, но останавливает выполнение при 304.
	if ( ! defined( 'LLMF_TEST_THROW_ON_STATUS' ) ) {
		define( 'LLMF_TEST_THROW_ON_STATUS', true );
	}

	/**
	 * Сбрасывает лог заголовков и условные заголовки запроса.
	 *
	 * @return void
	 */
	function reset_header_state() {
		$GLOBALS['__llmf_header_log'] = array();
		unset( $_SERVER['HTTP_IF_NONE_MATCH'], $_SERVER['HTTP_IF_MODIFIED_SINCE'] );
	}

	/**
	 * Бросает исключение, если выражение ложно.
	 *
	 * @param bool   $condition Проверяемое условие.
	 * @param string $message   Сообщение об ошибке.
	 *
	 * @return void
	 * @throws RuntimeException
	 */
	function assert_true( $condition, $message ) {
		if ( ! $condition ) {
			throw new RuntimeException( $message );
		}
	}

	/**
	 * Проверяет, что массив содержит элемент по индексу и он совпадает со строкой.
	 *
	 * @param array<int,string> $haystack Массив, в котором ищем.
	 * @param int               $index    Ожидаемый индекс.
	 * @param string            $expected Ожидаемое значение.
	 * @param string            $message  Сообщение об ошибке.
	 *
	 * @return void
	 */
	function assert_item( $haystack, $index, $expected, $message ) {
		$actual = isset( $haystack[ $index ] ) ? $haystack[ $index ] : null;
		assert_true( $actual === $expected, $message . ' (ожидалось "' . $expected . '", получили "' . (string) $actual . '")' );
	}

	$tests = array();

	$tests['exporter_headers_once'] = function () {
		reset_header_state();

		$exporter = new Exporter( new DummyOptions() );
		$method   = new ReflectionMethod( Exporter::class, 'send_common_headers' );
		$method->setAccessible( true );

		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			'X-Test' => 'one',
		);
		$etag     = '"etag-exporter"';
		$modified = 1_690_000_000;

		$method->invoke( $exporter, $headers, $etag, $modified );

		$log = $GLOBALS['__llmf_header_log'];

		assert_item( $log, 0, 'STATUS:200', 'Должен быть отправлен код 200' );
		assert_item( $log, 1, 'HEADER:Content-Type: text/plain; charset=UTF-8', 'Content-Type должен быть в логах один раз' );
		assert_item( $log, 2, 'HEADER:X-Test: one', 'Ассоциативный заголовок должен быть сформирован один раз' );
		assert_item( $log, 3, 'HEADER:ETag: ' . $etag, 'ETag должен быть сформирован из переданного значения' );
		assert_item( $log, 4, 'HEADER:Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $modified ) . ' GMT', 'Должен быть корректный Last-Modified' );
		assert_item( $log, 5, 'HEADER:Cache-Control: public, max-age=0, must-revalidate', 'Cache-Control отправляется один раз' );
		assert_true( count( $log ) === 6, 'Не должно быть дублирующих заголовков' );
	};

	$tests['exporter_304_on_etag_match'] = function () {
		reset_header_state();

		$_SERVER['HTTP_IF_NONE_MATCH'] = '"etag-exporter-304"';

		$exporter = new Exporter( new DummyOptions() );
		$method   = new ReflectionMethod( Exporter::class, 'send_common_headers' );
		$method->setAccessible( true );

		try {
			$method->invoke( $exporter, array(), '"etag-exporter-304"', 1_690_000_000 );
		} catch ( RuntimeException $e ) {
			assert_true( $e->getMessage() === 'STATUS_304', 'При совпадении ETag должен быть статус 304' );
		}

		$log = $GLOBALS['__llmf_header_log'];

		assert_item( $log, 0, 'STATUS:200', 'Перед 304 должен отправляться 200 для совместимости с WordPress' );
		assert_item( $log, count( $log ) - 1, 'STATUS:304', 'Финальный статус должен быть 304' );
	};

	$tests['llms_headers_once'] = function () {
		reset_header_state();

		$llms  = new Llms( new DummyOptions() );
		$method = new ReflectionMethod( Llms::class, 'send_common_headers' );
		$method->setAccessible( true );

		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			'X-LLMF-Test' => 'llms',
		);

		$etag     = '"etag-llms"';
		$modified = 1_690_000_100;
		$build_ts = 1_690_000_000;
		$rev      = 42;
		$hash     = 'abc';
		$settings = 'def';

		$method->invoke( $llms, $headers, $etag, $modified, $build_ts, $rev, $hash, $settings );

		$log = $GLOBALS['__llmf_header_log'];

		assert_item( $log, 0, 'STATUS:200', 'Llms должен отправить статус 200' );
		assert_item( $log, 1, 'HEADER:Content-Type: text/plain; charset=UTF-8', 'Content-Type llms.txt отправляется один раз' );
		assert_item( $log, 2, 'HEADER:X-LLMF-Test: llms', 'Пары ключ=>значение должны превращаться в строку один раз' );
		assert_true( in_array( 'HEADER:X-LLMF-Build: ' . $build_ts, $log, true ), 'Должен присутствовать X-LLMF-Build' );
		assert_true( in_array( 'HEADER:X-LLMF-Rev: ' . $rev, $log, true ), 'Должен присутствовать X-LLMF-Rev' );
		assert_true( in_array( 'HEADER:X-LLMF-Hash: ' . $hash, $log, true ), 'Должен присутствовать X-LLMF-Hash' );
		assert_true( in_array( 'HEADER:X-LLMF-Settings-Hash: ' . $settings, $log, true ), 'Должен присутствовать X-LLMF-Settings-Hash' );
		assert_true( in_array( 'HEADER:Cache-Control: public, max-age=0, must-revalidate', $log, true ), 'Cache-Control отправлен' );
	};

	$tests['llms_304_on_if_modified_since'] = function () {
		reset_header_state();

		$modified = 1_700_000_000;
		$_SERVER['HTTP_IF_MODIFIED_SINCE'] = gmdate( 'D, d M Y H:i:s', $modified ) . ' GMT';

		$llms  = new Llms( new DummyOptions() );
		$method = new ReflectionMethod( Llms::class, 'send_common_headers' );
		$method->setAccessible( true );

		try {
			$method->invoke( $llms, array(), '"etag-llms-304"', $modified );
		} catch ( RuntimeException $e ) {
			assert_true( $e->getMessage() === 'STATUS_304', 'При If-Modified-Since не свежее last_modified должен быть 304' );
		}

		$log = $GLOBALS['__llmf_header_log'];
		assert_item( $log, 0, 'STATUS:200', 'Llms сначала отправляет 200' );
		assert_item( $log, count( $log ) - 1, 'STATUS:304', 'Финальный статус должен быть 304' );
	};

	foreach ( $tests as $name => $callback ) {
		$callback();
		echo '[ok] ' . $name . PHP_EOL;
	}
}
