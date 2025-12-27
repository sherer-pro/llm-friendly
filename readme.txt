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
An LLM-friendly index of the website with main links and a list of latest items per post type.

2) Markdown exports
For selected post types, the plugin exposes .md endpoints under:
{base}/{post_type}/{path}.md

This is useful for LLMs, indexing bots, and users who prefer plain text.

= Key features =

* llms.txt endpoint with cached generation
* Markdown exports for selected post types
* Configurable base path for exports (e.g. "llm")
* Manual or automatic regeneration
* Optional X-Robots-Tag: noindex, nofollow for /llms.txt

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

== Screenshots ==

1. Settings page (General, llms.txt, Maintenance)

== Changelog ==

= 0.4.1 =
* Minimum requirements check (WordPress 6.0+, PHP 7.4+)
* Admin notice when requirements are not met

== Upgrade Notice ==

= 0.4.1 =
Adds minimum requirements checks and clearer behavior on unsupported environments.
