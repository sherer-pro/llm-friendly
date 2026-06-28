=== LLM Friendly ===
Contributors: skreep
Tags: llms.txt, markdown, ai, llm, export
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Expose llms.txt and Markdown versions of posts/pages to make your site easier for LLMs to navigate and consume.

== Description ==

LLM Friendly adds two capabilities to your WordPress site:

1) /llms.txt
An LLM-friendly index of the website with main links and a list of latest items per post type. You can show AI-facing one-line descriptions and exclude individual entries from the feed via Settings → LLM Friendly.

2) Markdown exports
For selected post types, the plugin exposes .md endpoints under:
{base}/{post_type}/{path}.md
Entries include a JSON metadata block with title, URL, dates, language, description, author, and publisher. They can override their Markdown body and one-line LLM description via the “Markdown override (LLM Friendly)” editor metabox.

This is useful for LLMs, indexing bots, and users who prefer plain text.
You can opt in to descriptions in llms.txt via `llms_show_excerpt`, and you can send `X-Robots-Tag: noindex, nofollow` for Markdown exports via `md_send_noindex` if you want Markdown-only consumers without search engine indexing.
If the automatic Markdown conversion does not fit a post, use the “Markdown override (LLM Friendly)” editor metabox to provide a custom Markdown or block-based replacement. In Gutenberg it appears with the editor’s additional panels/metaboxes.

= Key features =

* llms.txt endpoint with cached generation, optional AI-facing descriptions, a configurable custom Markdown block, and a per-post exclusion list.
* Markdown exports for selected post types with Gutenberg-to-Markdown conversion, expanded JSON metadata, per-post Markdown overrides, and a per-post exclusion list shared with llms.txt.
* Configurable base path for exports (e.g. "llm") and per-post-type enable/disable toggles; changing the base path requires flushing rewrites.
* Manual or automatic regeneration of the cached llms.txt with ETag/Last-Modified headers.
* Optional X-Robots-Tag: noindex, nofollow for both /llms.txt and Markdown exports; the Markdown header is controlled by `md_send_noindex`.
* Toggle descriptions in llms.txt via `llms_show_excerpt` to add one-line summaries under each item.
* Optional site title/description/author overrides plus a same-site sitemap URL field for generated outputs.

= Requirements =

* WordPress 6.0+
* PHP 7.4+

If requirements are not met, the plugin displays an admin notice and does not run.

== Installation ==

1. Upload the plugin to the /wp-content/plugins/ directory, or install it through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to Settings → LLM Friendly.
4. Enable llms.txt and/or Markdown exports, choose post types, configure base path (optional).
5. Save changes.
6. If you changed the base path or endpoints, re-save Permalinks if your server has custom rewrite rules.

== Development ==

Run `composer run lint` to syntax-check the plugin PHP files.
See `TESTING.md` for WordPress integration scenarios.

== Frequently Asked Questions ==

= Where is llms.txt stored? =

The plugin serves llms.txt dynamically via WordPress. It is not a physical file on disk.

= Markdown exports return 404. Why? =

Most often this is a rewrite or web-server routing issue. If you run Nginx in front of Apache, make sure .md requests are routed to WordPress (not handled as static files).
When you change the base path, flush permalinks and confirm that `.md` and `/llms.txt` are not short-circuited by static file rules.

= How do I keep Markdown out of search results? =

Enable the “Send X-Robots-Tag: noindex, nofollow for Markdown” option (stored as `md_send_noindex`) to emit the header on all Markdown responses.

= Can I ship a custom Markdown body, description, or exclude a single item? =

Yes. Open the post editor and use the “Markdown override (LLM Friendly)” metabox. In Gutenberg it appears with the editor’s additional panels/metaboxes. The override accepts plain Markdown or block markup, and the llms.txt description field provides a one-line summary for llms.txt and Markdown metadata. If you want to hide a specific entry from llms.txt and Markdown exports, go to Settings → LLM Friendly → llms.txt → “Excluded items”, search by title, and add it to the exclusion list.

= Can I use an external sitemap URL? =

By default, the sitemap field accepts site-relative paths and same-site absolute URLs. External sitemap URLs are rejected unless a developer opts in with the `llmf_allow_external_sitemap_url` filter.

== Developer Notes ==

* `llmf_can_export_post` can deny a post for `markdown`, `llms`, or `llms_search` contexts.
* `llmf_markdown_override_max_length` changes the per-post Markdown override length cap. Default: 200000 characters.
* `llmf_llms_description_max_length` changes the per-post llms.txt description length cap. Default: 500 characters.
* `llmf_markdown_metadata` filters the JSON metadata array emitted at the top of each Markdown export.
* `llmf_max_excluded_posts_per_type` changes the per-post-type exclusion cap. Default: 500.
* `llmf_allow_external_sitemap_url` allows an external sitemap URL when returning `true`.
* Users without `unfiltered_html` have custom Markdown sanitized with WordPress KSES.

== Screenshots ==

1. Settings page (General)
2. Settings page (llms.txt, Site Meta overrides, Maintenance)
