=== LLM Friendly ===
Contributors: skreep
Tags: llms.txt, markdown, ai, llm, export
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.2
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Create a clean /llms.txt and Markdown exports for WordPress content, with controls for AI crawlers, metadata, excerpts, exclusions, and cache regeneration.

== Description ==

LLM Friendly gives your WordPress site predictable, machine-readable entry points for AI assistants, search/indexing bots, internal knowledge tools, and readers who prefer plain text.

Instead of asking crawlers to infer your site structure from HTML alone, the plugin publishes a focused `/llms.txt` index and optional Markdown versions of selected public content. You stay in control of what is exposed, how dense the index should be, which endpoints should be noindexed, and which posts or pages should be excluded.

= What the plugin creates =

1. `/llms.txt`

The generated llms.txt file includes the site title and description, main links, sitemap and RSS references, an Essential section for important pages or curated resources, and recent items grouped by selected post type. Items can link to Markdown exports when Markdown is enabled, or to canonical HTML URLs when it is not.

2. Markdown exports

For selected public, publicly queryable post types, the plugin exposes `.md` endpoints under:

`/{base}/{post_type}/{path}.md`

Each Markdown export includes a JSON metadata block with title, URL, published and modified dates, language, description, author, and publisher, followed by a Markdown conversion of the post content. Singular views also receive alternate Markdown links, so compatible tools can discover the plain-text version.

= Why site owners use it =

* Give AI tools a concise map of your most useful public content.
* Offer Markdown versions of posts, pages, and safe public custom post types without changing the canonical HTML experience.
* Keep Markdown endpoints out of search results by default with separate noindex controls for `/llms.txt` and `.md` exports.
* Add short AI-facing descriptions from a per-post field, SEO meta description, excerpt, or generated content summary.
* Exclude individual items from both `/llms.txt` and Markdown exports without changing their WordPress visibility.
* Preview generated llms.txt content before relying on the public endpoint.
* Review practical AI crawler policy guidance for OAI-SearchBot, GPTBot, Googlebot/Search AI features, and Google-Extended.

= Key features =

* llms.txt endpoint with cached generation, ETag/Last-Modified support, main links, sitemap/RSS references, an Essential section, selected post-type sections, optional item descriptions, and an optional custom Markdown notes block.
* Markdown exports for selected public, publicly queryable post types with Gutenberg/HTML-to-Markdown conversion, JSON metadata, canonical Link headers, alternate Markdown discovery links, and transient-based body caching.
* Overview dashboard with endpoint status, cache status, noindex status, sitemap URL, and quick copy buttons for `/llms.txt` and the Markdown URL pattern.
* Configurable Markdown base path, for example `/llm/post/example.md`, with post type controls for posts, pages, and safe public custom post types.
* Per-post-type exclusion picker with live title search, selected-item lists, clear buttons, and server-side validation.
* Separate noindex header controls for `/llms.txt` and Markdown exports.
* Auto or manual llms.txt regeneration; manual rebuilds are available from the Maintenance panel.
* Live llms.txt preview that uses saved settings and does not update the public cache.
* Optional excerpts/descriptions in llms.txt. Description priority is: per-post LLM description, SEO meta description, explicit excerpt, then a generated content summary.
* Per-post "Markdown override (LLM Friendly)" metabox for custom Markdown or Gutenberg block markup, plus a one-line llms.txt description field.
* Optional site title, site description, and author overrides for generated metadata.
* Same-site sitemap URL validation. External sitemap URLs are rejected unless a developer explicitly allows them with a filter.
* AI crawler diagnostics with policy examples and documentation links for OAI-SearchBot, GPTBot, Googlebot/Search AI features, and Google-Extended. The plugin does not edit robots.txt automatically.
* Hardened public export boundaries: attachments, drafts, private content, password-protected posts, and non-public/non-queryable post types are excluded by default.
* KSES sanitization for users without `unfiltered_html`, length caps for custom Markdown fields, and developer filters for edge cases.

= Requirements =

* WordPress 6.0+
* PHP 7.4+

If requirements are not met, the plugin displays an admin notice and does not run.

== Installation ==

1. Upload the plugin to the /wp-content/plugins/ directory, or install it through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to Settings -> LLM Friendly.
4. Enable llms.txt and/or Markdown exports, choose post types, configure base path (optional).
5. Save changes.
6. If you changed the base path or endpoints, re-save Permalinks if your server has custom rewrite rules.

== Development ==

Run `composer run lint` to syntax-check the plugin PHP files.
Run `composer run test` to execute lightweight regression tests for Markdown conversion, llms.txt format, headers, and sanitization.
See `TESTING.md` for WordPress integration scenarios.

== Frequently Asked Questions ==

= Where is llms.txt stored? =

The plugin serves llms.txt dynamically via WordPress. It is not a physical file on disk.

= Markdown exports return 404. Why? =

Most often this is a rewrite or web-server routing issue. If you run Nginx in front of Apache, make sure .md requests are routed to WordPress (not handled as static files).
When you change the base path, flush permalinks and confirm that `.md` and `/llms.txt` are not short-circuited by static file rules.

= How do I keep Markdown out of search results? =

Enable the "Send noindex header for Markdown exports" option (stored as `md_send_noindex`) to emit the header on all Markdown responses.

= How do I configure the llms.txt Essential section? =

Use Settings -> LLM Friendly -> llms.txt -> "Essential links". Add one item per line as `Title | URL | Notes`; URLs may be absolute or site-relative. The plugin also adds configured front page, posts page, and privacy policy links when available.

= Can I ship a custom Markdown body, description, or exclude a single item? =

Yes. Open the post editor and use the "Markdown override (LLM Friendly)" metabox. In Gutenberg it appears with the editor's additional panels/metaboxes. The override accepts plain Markdown or block markup, and the llms.txt description field provides a one-line summary for llms.txt and Markdown metadata. If you want to hide a specific entry from llms.txt and Markdown exports, go to Settings -> LLM Friendly -> llms.txt -> "Excluded items", search by title, and add it to the exclusion list.

= Can I use an external sitemap URL? =

By default, the sitemap field accepts site-relative paths and same-site absolute URLs. External sitemap URLs are rejected unless a developer opts in with the `llmf_allow_external_sitemap_url` filter.

= Can a non-publicly-queryable post type be exported? =

Not by default. Public Markdown and llms.txt exports require public, non-attachment, publicly queryable post types. Developers can explicitly opt in safe edge-case types with the `llmf_exportable_post_type` filter.

== Developer Notes ==

* `llmf_can_export_post` can deny a post for `markdown`, `llms`, or `llms_search` contexts.
* `llmf_exportable_post_type` can explicitly opt in a public edge-case post type that is safe to expose even though WordPress marks it as not publicly queryable.
* `llmf_markdown_override_max_length` changes the per-post Markdown override length cap. Default: 200000 characters; absolute maximum: 500000.
* `llmf_llms_description_max_length` changes the per-post llms.txt description length cap. Default: 500 characters; absolute maximum: 2000.
* `llmf_markdown_metadata` filters the JSON metadata array emitted at the top of each Markdown export.
* `llmf_markdown_cache_ttl` changes the transient TTL for cached Markdown export bodies. Default: 3600 seconds.
* `llmf_llms_essential_links` filters curated link items emitted in the llms.txt Essential section.
* `llmf_debug_headers_enabled` enables diagnostic X-LLMF-* headers for llms.txt responses when returning `true`.
* `llmf_max_excluded_posts_per_type` changes the per-post-type exclusion cap. Default: 500; absolute maximum: 5000.
* `llmf_allow_external_sitemap_url` allows an external sitemap URL when returning `true`.
* Users without `unfiltered_html` have custom Markdown sanitized with WordPress KSES. Heading markers are removed from the custom llms.txt notes block so user-provided notes cannot break the required llms.txt section order.

== Screenshots ==

1. Settings overview dashboard with endpoint status, cache status, noindex status, sitemap URL, and copy actions.
2. Markdown export controls, base path pattern, and selected content types.
3. Per-post-type exclusions with live title search and selected excluded items.
4. Generated llms.txt preview with cache metadata and site metadata override controls.
