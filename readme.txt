=== LLM Friendly ===
Contributors: your-wordpress-org-username
Tags: llms.txt, markdown, ai, llm, export
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Expose llms.txt and Markdown versions of posts/pages to make your site easier for LLMs to navigate and consume.

== Description ==

LLM Friendly adds two capabilities to your WordPress site:

1) /llms.txt
An LLM-friendly index of the website with main links and a list of latest items per post type. You can exclude individual entries from the feed via Settings → LLM Friendly → llms.txt → “Excluded items”.

2) Markdown exports
For selected post types, the plugin exposes .md endpoints under:
{base}/{post_type}/{path}.md
Entries can override their Markdown body via the “Markdown override” sidebar panel (or Classic Editor metabox) and can also be excluded from export through the same settings page.

This is useful for LLMs, indexing bots, and users who prefer plain text.
You can opt in to excerpts in llms.txt via `llms_show_excerpt`, and you can send `X-Robots-Tag: noindex, nofollow` for Markdown exports via `md_send_noindex` if you want Markdown-only consumers without search engine indexing.
If the automatic Markdown conversion does not fit a post, use the “Markdown override” sidebar panel (Gutenberg) or the Classic Editor metabox to provide a custom Markdown or block-based replacement.

= Key features =

* llms.txt endpoint with cached generation, optional excerpts, a configurable custom Markdown block, and a per-post exclusion list.
* Markdown exports for selected post types with Gutenberg-to-Markdown conversion, per-post Markdown overrides (sidebar panel/metabox), and a per-post exclusion list shared with llms.txt.
* Configurable base path for exports (e.g. "llm") and per-post-type enable/disable toggles; changing the base path requires flushing rewrites.
* Manual or automatic regeneration of the cached llms.txt with ETag/Last-Modified headers.
* Optional X-Robots-Tag: noindex, nofollow for both /llms.txt and Markdown exports; the Markdown header is controlled by `md_send_noindex`.
* Toggle excerpts in llms.txt via `llms_show_excerpt` to add one-line summaries under each item.
* Optional site title/description overrides plus a sitemap URL field for the generated llms.txt.

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

== Frequently Asked Questions ==

= Where is llms.txt stored? =

The plugin serves llms.txt dynamically via WordPress. It is not a physical file on disk.

= Markdown exports return 404. Why? =

Most often this is a rewrite or web-server routing issue. If you run Nginx in front of Apache, make sure .md requests are routed to WordPress (not handled as static files).
When you change the base path, flush permalinks and confirm that `.md` and `/llms.txt` are not short-circuited by static file rules.

= How do I keep Markdown out of search results? =

Enable the “Send X-Robots-Tag: noindex, nofollow for Markdown” option (stored as `md_send_noindex`) to emit the header on all Markdown responses.

= Can I ship a custom Markdown body or exclude a single item? =

Yes. Open the post in Gutenberg and use the “Markdown override” sidebar panel; Classic Editor users get a “Markdown override (LLM Friendly)” metabox. The override accepts plain Markdown or block markup. If you want to hide a specific entry from llms.txt and Markdown exports, go to Settings → LLM Friendly → llms.txt → “Excluded items”, search by title, and add it to the exclusion list.

== Screenshots ==

1. Settings page (General)
2. Settings page (llms.txt, Site Meta overrides, Maintenance)
