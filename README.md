# LLM Friendly

LLM Friendly is a WordPress plugin that exposes:

- `/llms.txt` -- an LLM-friendly index of your site
- Markdown exports for selected post types under `/{base}/{post_type}/{path}.md`

Current version: **0.2.0**

The goal is to make your site easier to navigate and consume for LLMs, indexing bots, and power users who prefer plain text.

## Features

- Generates a compact `llms.txt` v2-compatible index with main links, an Essential section for key site resources, latest content, optional LLM descriptions, and an optional custom Markdown notes block.
- Exposes `.md` endpoints for selected public, publicly queryable post types (posts, pages, custom post types) with Gutenberg-to-Markdown conversion, plugin-defined JSON metadata, canonical and `describedby` `Link` relations, and per-post Markdown overrides through the "Markdown override (LLM Friendly)" editor metabox.
- Optionally returns the same Markdown representation from canonical singular URLs when a client explicitly sends `Accept: text/markdown`; this mode is disabled by default and sends `Vary: Accept` when enabled.
- Configurable base path for Markdown exports (e.g. `llm`) and per-post-type enable/disable toggles; changing the base path requires updating rewrite rules.
- Manual or automatic regeneration of the cached `llms.txt`, complete with ETag/Last-Modified headers.
- Optional `X-Robots-Tag: noindex` headers for both `llms.txt` and Markdown exports. `/llms.txt` is controlled by `llms_send_noindex`; `.md` endpoints are controlled by `md_send_noindex`; both are enabled by default.
- Toggle excerpts in `llms.txt` via `llms_show_excerpt` to add one-line summaries under each item. The summary uses the per-post llms.txt description first, then SEO meta description, explicit excerpt, and a generated content summary.
- Exclude specific items from both `llms.txt` and Markdown exports through the per-post-type exclusion picker in Settings -> LLM Friendly.
- Outputs `<link rel="describedby" type="text/markdown">` for `/llms.txt` on frontend pages and `<link rel="alternate" type="text/markdown">` on supported singular views.
- Optional site title/description/author overrides and a same-site sitemap URL for generated outputs.
- AI crawler diagnostics for OAI-SearchBot, GPTBot, ChatGPT-User, Googlebot/Search AI features, Google-Extended, and the configured sitemap URL. The plugin does not edit `robots.txt` automatically.

## Requirements

- WordPress 6.0+
- PHP 7.4+

If requirements are not met, the plugin shows an admin warning and does not run.

## Installation

1. Upload the plugin folder to `wp-content/plugins/llm-friendly/`
2. Activate "LLM Friendly" in WordPress Admin -> Plugins
3. Open Settings -> LLM Friendly and configure:
   - Enable llms.txt
   - Enable Markdown exports
   - Select post types
   - Set base path (optional)
4. Save changes

## Development

- Run `composer run lint` to syntax-check the plugin PHP files.
- Run `composer run test` to execute lightweight regression tests for Markdown conversion, `llms.txt` format, headers, and sanitization.
- See `TESTING.md` for WordPress integration scenarios.

## Usage

- Open `https://example.com/llms.txt`
- Open a Markdown export, for example:
  - `https://example.com/llm/post/hello-world.md`
  - `https://example.com/llm/page/about.md`
- Enable `llms_show_excerpt` to include short descriptions in `llms.txt`:
  - Settings -> LLM Friendly -> llms.txt -> "Show excerpts in llms.txt"
- Control whether `/llms.txt` sends a noindex header:
  - Settings -> LLM Friendly -> llms.txt -> "Send X-Robots-Tag: noindex for /llms.txt"
- Enable `md_send_noindex` to keep Markdown exports out of search indices:
  - Settings -> LLM Friendly -> General -> "Send noindex header for Markdown exports"
- Optionally enable Markdown content negotiation:
  - Settings -> LLM Friendly -> Markdown exports -> "Serve Markdown when a client explicitly requests it"
  - Confirm that every page cache, reverse proxy, and CDN in front of WordPress respects `Vary: Accept` before enabling it.
- Add curated resources to the `llms.txt` Essential section:
  - Settings -> LLM Friendly -> llms.txt -> "Essential links"
  - Use one line per item: `Title | URL | Notes`; URLs may be absolute or site-relative. The Essential section also includes configured front page, posts page, and privacy policy links when available.
- Use the "Markdown override (LLM Friendly)" editor metabox to replace the generated Markdown with your own content or block markup; in Gutenberg it appears with the editor's additional panels/metaboxes.
- Use the "llms.txt description (overrides excerpt)" field in the same metabox to provide a one-line AI-facing summary for both `llms.txt` and Markdown metadata.
- Use "Excluded items" in Settings -> LLM Friendly -> llms.txt to search by title and exclude specific entries from both `llms.txt` and Markdown exports.
- Use "Author override" in Settings -> LLM Friendly -> Site meta overrides when Markdown metadata should attribute posts to a company or publication rather than individual WordPress authors.
- Use a site-relative or same-site absolute sitemap URL. External sitemap URLs are rejected by default unless a site owner opts in with the `llmf_allow_external_sitemap_url` filter.
- To change the base path for exports (default `llm`), update "Base path" and re-save Permalinks if your server uses custom rewrites.

Note: if you are running Nginx in front of Apache (or have aggressive static rules), make sure `.md` and `/llms.txt` requests are routed to WordPress (not treated as static files).

If Markdown endpoints return 404 after changing the base path, flush permalinks and confirm that your web server does not short-circuit `.md` requests. On Nginx, ensure the PHP location block handles `.md` and `/llms.txt` before static file rules.

## Discovery, indexing, and usage policy

- `llms.txt`, Markdown endpoints, `alternate`, and `describedby` help agents discover and retrieve public content.
- `X-Robots-Tag: noindex` controls whether a generated representation should appear as a separate search result; it does not block fetching.
- `robots.txt` controls automated crawler access. LLM Friendly reports policy choices but never edits it.
- Training and licensing permissions are a separate legal and technical layer. Publishing Markdown or `llms.txt` does not grant usage rights.
- Google states that `llms.txt` and Markdown are not specially used for AI Overviews or AI Mode; normal Search crawling, indexing, and snippet eligibility still apply.

The JSON block at the top of each Markdown export is a stable, plugin-defined metadata contract. It is not YAML frontmatter or JSON-LD. Use `llmf_markdown_metadata` to extend its fields without changing the representation format.

## Security notes

- Post types must be public and publicly queryable by default. Attachments are never exportable, and custom post types with `publicly_queryable => false` must be explicitly opted in with `llmf_exportable_post_type`.
- Password-protected content should not be exported.
- Custom Markdown in `llms.txt` is capped at 20,000 characters, per-post Markdown overrides are capped at 200,000 characters, and per-post llms.txt descriptions are capped at 500 characters by default.
- Length-related filters are bounded defensively: Markdown overrides cannot exceed 500,000 characters, llms.txt descriptions cannot exceed 2,000 characters, and exclusion lists cannot exceed 5,000 items per post type.
- Heading markers are removed from the custom `llms.txt` notes block so user-provided notes cannot break the required `llms.txt` section order.
- Users without `unfiltered_html` have custom Markdown sanitized with WordPress KSES.
- Exclusion lists are validated server-side and capped at 500 items per post type by default.

## Developer filters

- `llmf_can_export_post` can deny a post for `markdown`, `llms`, or `llms_search` contexts.
- `llmf_exportable_post_type` can explicitly opt in a public edge-case post type that is safe to expose even though WordPress marks it as not publicly queryable.
- `llmf_markdown_override_max_length` changes the per-post Markdown override length cap.
- `llmf_llms_description_max_length` changes the per-post llms.txt description length cap.
- `llmf_markdown_metadata` filters the JSON metadata array emitted at the top of each Markdown export.
- `llmf_markdown_cache_ttl` changes the transient TTL for cached Markdown export bodies.
- `llmf_llms_essential_links` filters curated link items emitted in the `llms.txt` Essential section.
- `llmf_debug_headers_enabled` enables diagnostic `X-LLMF-*` headers for `llms.txt` responses when returning `true`.
- `llmf_max_excluded_posts_per_type` changes the per-post-type exclusion cap.
- `llmf_allow_external_sitemap_url` allows an external sitemap URL when returning `true`.

## License

GPL-3.0-or-later
