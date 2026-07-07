# AGENTS.md

Этот файл - рабочая шпаргалка для автоматизации в репозитории `llm-friendly`. Обновляй его в том же PR/коммите, если меняются структура проекта, команды, зависимости, минимальные версии, CI/CD, локализация, релизный процесс или правила graphify.

## Graphify

В проекте есть каталог `graphify-out/`, но готовый `graphify-out/graph.json` может отсутствовать.

- Для вопросов по кодовой базе сначала проверь `graphify-out/graph.json`. Если он есть, запускай `graphify query "<question>"` до широкого поиска по исходникам.
- Для связей используй `graphify path "<A>" "<B>"`, для локального объяснения концепта - `graphify explain "<concept>"`.
- Если есть `graphify-out/wiki/index.md`, используй его для широкой навигации.
- Не считай dirty-файлы в `graphify-out/` проблемой: hooks и incremental update могут менять граф.
- После изменений кода запускай `graphify update .`, если граф уже инициализирован. Если `graphify-out/graph.json` отсутствует или CLI сообщает, что обновлять нечего, зафиксируй это в итоговом сообщении и не запускай полный rebuild без отдельного запроса.

## Навигация

- `llm-friendly.php` - входная точка WordPress-плагина: header metadata, версии, требования PHP/WP, загрузка переводов, require классов, activation/deactivation hooks, bootstrap через `plugins_loaded`.
- `inc/Plugin.php` - оркестратор сервисов, rewrite flush, public endpoint dispatch, alternate Markdown links.
- `inc/Options.php` - defaults, sanitization, export eligibility, sitemap/base path logic, description fallbacks, exclusion validation.
- `inc/Exporter.php` - `.md` endpoint: headers, metadata JSON fence, post override, Gutenberg/HTML to Markdown conversion, Markdown cache key.
- `inc/Llms.php` - `/llms.txt`: cache, scheduled regeneration, ETag/Last-Modified, Essential links, recent post sections.
- `inc/Admin.php` - Settings UI, AJAX exclusion search, regenerate action, editor metabox.
- `inc/Rewrites.php` - query vars and rewrite rules for `/llms.txt` and `/{base}/{post_type}/{path}.md`.
- `inc/Response.php` и `inc/Markdown.php` - shared helpers for HTTP conditional responses and Markdown-safe strings/URLs.
- `assets/llmf-admin.js` и `assets/llmf-admin.css` - plain admin JS/CSS, no bundler.
- `tests/run.php` - lightweight regression runner with WordPress stubs; it is not PHPUnit.
- `languages/` - translation source and generated runtime files.
- `assets-svn/` - WordPress.org plugin directory assets/blueprint; ignore for code changes unless the task is release/listing related.

Safe to ignore during normal code navigation: `vendor/`, `graphify-out/cache/`, `graphify-out/cost.json`, `.codex/`, `.agents/`, `.idea/`, logs, `node_modules/`, `data/`, `assets/public/`, `upload/`, `security/`. Generated localization files `languages/*.mo` and `languages/*.l10n.php` are usually ignored for code reading, but must be updated when strings/locales change.

## Команды

```bash
composer validate --strict
composer run lint
composer run test
```

- `composer run lint` runs `php -l` on `llm-friendly.php`, every file in `inc/`, and `tests/run.php`.
- `composer run test` runs `php tests/run.php`; current expected result is `OK: 51 assertions`.
- If Composer is unavailable, run `php tests/run.php` directly and manually lint changed PHP files with `php -l <file>`.
- There is no migration command: the plugin stores settings in one option key (`llmf_options`) and post meta, with no custom DB tables.
- There is no Docker/image build command, no frontend build, and no deploy script in the repo.
- For release work, manually keep these in sync: plugin header `Version`, `LLMF_VERSION`, `README.md` current version, `readme.txt` `Stable tag`, translation catalogs, and `assets-svn/` if WordPress.org listing assets change.

Local Composer note: in the current Windows/Codex sandbox, Composer may fail before scripts run because PHP `sys_temp_dir` points to `D:/OSPanel/temp/PHP-8.4/default`. Use a writable PHP temp dir, request sandbox escalation for Composer scripts, or fall back to direct `php` commands.

## Стиль

- PHP targets 7.4+. Do not introduce syntax requiring PHP 8.x.
- Source files follow WordPress-style PHP more than PSR: tabs for indentation, spaces inside function/control parentheses, `array()` syntax, strict comparisons, early returns.
- Classes under `inc/` use namespace `LLMFriendly`; entrypoint functions use `llmf_` prefixes in the global namespace.
- Escape output close to echo (`esc_html__`, `esc_attr__`, `esc_url`) and pair every `$_POST`/AJAX path with nonce and capability checks.
- Keep public export boundaries hardened: attachments, drafts, private/password-protected posts, and non-public/non-queryable post types are excluded unless an explicit filter allows a safe edge case.
- JS is vanilla IIFE code using `const`, DOM APIs, localized config via `window.LLMF_ADMIN`, and no transpilation.
- CSS uses `.llmf-...` class prefixes and small WordPress-admin compatible rules.
- No `.editorconfig`, PHPCS, PHP-CS-Fixer, Prettier, ESLint, or pre-commit hooks are configured. Preserve surrounding style manually.
- No hard line-length rule is enforced. Real source contains long admin `echo` lines; keep new lines readable and avoid rewrapping unrelated code.
- Commit history is mostly short English imperative messages (`Improve...`, `Harden...`, `Update...`, `Fix...`) with occasional Conventional Commits (`docs:`, `fix:`, `chore:`). Prefer `type: summary` for automation-generated docs/fix/chore commits, unless matching an existing release message like `Version 0.1.1`.

## Окружение

- Minimum runtime from code/docs: WordPress 6.0+ and PHP 7.4+.
- `readme.txt` currently says `Tested up to: 7.0`.
- No `.env` is required for tests or local syntax checks.
- Safe local option defaults from `Options::defaults()`: Markdown exports on, `llms.txt` on, `base_path=llm`, `post_types=['post']`, both noindex headers on, regeneration mode `auto`, recent limit `30`, sitemap `/sitemap.xml`.
- Runtime rewrite changes (`enabled_markdown`, `enabled_llms_txt`, `base_path`, `post_types`) set a transient and flush rewrite rules later in admin. Manual WP testing after endpoint changes should include re-saving permalinks.

## Зависимости

- Package manager: Composer only.
- `composer.json` requires only `php >=7.4` and defines scripts; there are no runtime or dev package dependencies.
- `composer.lock` is not tracked. Do not create or commit it just to run tests.
- `vendor/` is ignored and not needed for the current test suite.
- No npm/yarn/pnpm/bun, Python, Go, Rust, or Docker dependency manager is active in this repo.
- Use `composer validate --strict` after editing `composer.json`. `composer audit` is low-value until package dependencies/lock files are added.

## CI/CD

- There is no `.github/workflows/` directory and no repository CI config in the current tree.
- Treat local `composer validate --strict`, `composer run lint`, and `composer run test` as the required reproducible checks before commit.
- If CI is added later, document required checks, env vars, secrets, and the local reproduction command here.

## Локализация

- Text domain: `llm-friendly`; domain path: `/languages`; loaded in `llmf_load_textdomain()`.
- Wrap user-facing PHP strings in WordPress i18n functions (`__`, `esc_html__`, `esc_attr__`, etc.) with text domain `llm-friendly`.
- Add translator comments before placeholder-heavy strings.
- Current locales: `de_DE`, `es_ES`, `fr_FR`, `ru_RU`, `uk`, `uk_UA`, plus `languages/llm-friendly.pot`.
- Current POT header shows WP-CLI 2.12.0. There is no repo script for translations.
- Recommended POT refresh:

```bash
wp i18n make-pot . languages/llm-friendly.pot --domain=llm-friendly --exclude=vendor,graphify-out,assets-svn,.codex,.agents
```

Regenerate `.mo` and `.l10n.php` from `.po` files with the same WP-CLI/i18n tooling; do not edit generated binary/runtime files manually.

## Подводные камни

- Changing `.md` endpoint behavior usually touches `Options`, `Rewrites`, `Plugin`, `Exporter`, docs, and tests. Include Nginx/static-file routing scenarios from `TESTING.md`.
- Changing `/llms.txt` output usually touches `Llms`, `Options`, docs, translations, and tests. Preserve section order: site heading/meta, optional custom notes, `Main links`, `Essential`, then post-type sections.
- Metadata-only changes must not be hidden by `If-Modified-Since`; ETags/settings hashes are intentionally part of cache behavior.
- Markdown override and llms description meta changes affect cache invalidation. Keep `_llmf_md_content_override` and `_llmf_llms_description` behavior covered.
- Admin AJAX for exclusions must reject missing/invalid nonce, insufficient capability, invalid post type, duplicates, excluded results, and too-short multibyte searches.
- External sitemap URLs are rejected by default unless `llmf_allow_external_sitemap_url` opts in.
- Users without `unfiltered_html` get custom Markdown sanitized by KSES; preserve Markdown line breaks and code fences when changing sanitization.
- Translation/runtime files are frequently updated in history. If UI strings change, do not leave `languages/` stale.

## Полезные ссылки

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- [WordPress Plugin Internationalization](https://developer.wordpress.org/plugins/internationalization/)
- [WP-CLI i18n make-pot](https://developer.wordpress.org/cli/commands/i18n/make-pot/)
- [llms.txt proposal](https://llmstxt.org/)

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

When the user types `/graphify`, use the installed graphify skill or instructions before doing anything else.

Rules:
- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- Dirty graphify-out/ files are expected after hooks or incremental updates; dirty graph files are not a reason to skip graphify. Only skip graphify if the task is about stale or incorrect graph output, or the user explicitly says not to use it.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
