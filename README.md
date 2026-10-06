# LLM Friendly

LLM Friendly is a WordPress plugin that exposes:

- `/llms.txt` -- an LLM-friendly index of your site
- Markdown exports for selected post types under `/{base}/{post_type}/{path}.md`

Current version: **0.3.0**

Version 0.3.0 adds an optional structured catalog, enhanced content processing and diagnostics. Existing and new installations keep legacy behavior by default; enable either new mode independently after comparing its output.

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

### Opt-in mechanics and compatibility

Two independent settings control the new behavior. Both default to `legacy`, including when an existing installation has no new keys:

| Setting | Legacy behavior | Opt-in behavior |
| --- | --- | --- |
| `llms_index_mode` | Existing recent-content `/llms.txt` | `structured`: a short navigation map with detailed catalog indexes |
| `content_profile` | Existing descriptions and Markdown conversion | `enhanced`: paragraph-based descriptions, improved static conversion and reusable-source tracking |

Existing `.md` URLs, custom base paths, the `/blog/` alias, content negotiation, JSON metadata fields, developer filters and existing AJAX response shapes remain supported. No content is rewritten on update. Switching either mode back preserves pinned IDs, taxonomy selections, Essential links and manual Markdown. Partial settings updates retain omitted values; the complete admin form still clears unchecked controls deliberately.

Use **Compare modes before saving** to compare the existing output with the current unsaved form values. The comparison does not save plugin settings, rebuild public caches, change rewrite rules or start background jobs. Select an item ID to inspect a particular Markdown export. Long previews are truncated for display without truncating the actual output.

### Structured catalog

The root map keeps the existing section order: site metadata, custom notes, Main links, Essential, then content-type sections. It adds no more than 50 automatic links, including up to 10 pinned items and up to five recently modified items per type. Duplicate automatically added destinations are omitted. Existing notes and manually curated Essential links keep their existing limits and are not shortened to fit the automatic-link budget; the comparison warns when the resulting root is large. The legacy item-count and excerpt controls continue to apply to legacy mode.

Select and order up to 100 pinned public items in settings. Only items in selected types that pass existing export eligibility and the `llms` filter context are listed publicly. Hidden, excluded or deleted pins remain harmless stored selections and are skipped. The full pinned list and all selected content types remain reachable through the catalog.

Catalog routes use the current base path and require both `enabled_llms_txt` and `llms_index_mode=structured`:

```text
/{base}/catalog/index.txt
/{base}/catalog/essential.txt
/{base}/catalog/{post_type}/index.txt
/{base}/catalog/{post_type}/{taxonomy}/index.txt
/{base}/catalog/{post_type}/{taxonomy}/{term_id}.txt
```

Each content page scans at most 100 published, password-free candidates in ascending ID order. Follow the generated Next page link: its `llmf_after` cursor advances past the last scanned candidate even when filters reject an entire page. An empty filtered page can therefore still have a next page. Hierarchical types support `llmf_parent=0` for root items or a valid public parent ID for its direct children. Topic routes use numeric term IDs; only explicitly selected public, publicly queryable taxonomies are accepted. Categories are selected by default where applicable; an explicitly empty selection disables topics for that type.

Nonempty topic indexes are projected by a resumable background job in batches of 100. Until the projection completes, topic indexes link to the immediately available type index. The job resumes through WP-Cron or the Continue scan button. Detailed indexes update automatically even in manual root-regeneration mode. First content pages cache IDs for five minutes; arbitrary cursor pages are not cached. Every cached ID is checked against current visibility and filters before rendering. When Markdown is disabled, catalog entries link to canonical HTML.

Catalog responses support GET/HEAD, ETags, nosniff and the existing llms.txt noindex setting. Use ETags for conditional validation; metadata changes are not hidden by date-only validation. Route `.txt` catalog paths through WordPress alongside `.md` and `/llms.txt`, and re-save Permalinks if custom server routing requires it.

### Enhanced content and diagnostics

Enhanced description priority is custom LLM description, a resolved Yoast SEO description, explicit excerpt, then the first meaningful content paragraph or list item. Derived summaries use up to 30 words within the existing description cap. Code, service markup and unexecuted shortcodes are skipped. The Yoast API is optional; unresolved template tokens are skipped with a warning. The editor and diagnostics show the chosen description source.

Enhanced Markdown preserves older/newer Gutenberg lists and Classic HTML lists, nested numbering, simple table cells, media source URLs, images, captions, links and code. Relative references resolve against the canonical post URL. Complex table spans remain as restricted safe HTML with a warning. Published reusable `core/block` references are tracked with bounded traversal, cycle detection and current visibility/filter checks. Changed or hidden dependencies select a fresh Markdown cache entry without requiring a parent edit. Analysis does not execute shortcodes or arbitrary dynamic blocks; unsupported content is reported for review. DOM-based HTML conversion requires PHP's DOM extension; environments without it retain the existing fallback.

Manual Markdown remains authoritative. Saving a changed override or checking **I reviewed this override against the current source** records a source fingerprint. An older override without a fingerprint is shown as unconfirmed; changes to the source or reusable dependencies mark it as needing review. Nothing overwrites a manual version automatically. The fingerprint is internal post meta and is not a new public REST field. Rendering, comparison and analysis use an anonymous context and restore the caller and password cookie, including exception paths.

**Diagnostics and coverage** requires an administrator capability and a valid nonce. Availability checks use saved settings and at most six anonymous same-origin GET/HEAD requests constructed by the plugin: five seconds and 256 KiB per request, with TLS verification and no redirects, cookies or supplied credentials. A blocked local environment is reported as Unable to check, not as an available endpoint. Coverage distinguishes exportable content from content absent from the short map and reports description sources, conversion warnings, override review states and progress. It scans only published, password-free candidates of selected types; it is not an inventory of private or draft content. Technical details are available separately.

Settings remain in `llmf_options`. Internal root publication IDs/dependencies, catalog generation and scan progress use separate non-autoloaded options; there are no custom tables or destructive migrations. Privacy revocations apply synchronously in auto and manual modes. Manual regeneration controls the root file; detailed catalog pages and Markdown remain current independently.

### Existing controls

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

Note: if you are running Nginx in front of Apache (or have aggressive static rules), make sure `.md`, `/llms.txt` and structured catalog `.txt` requests are routed to WordPress (not treated as static files).

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
- Editor override and description metadata are available only in REST `context=edit` to users who can edit the post.
- Shared Markdown is rendered as an anonymous visitor without a post password cookie, even when requested by a logged-in editor.
- Closing access or deleting a published post immediately invalidates llms.txt, including manual regeneration mode.
- URLs containing username/password information are rejected before public output.
- Markdown uses ETags for conditional validation. IMS-only Markdown requests omit Last-Modified to prevent Apache/FastCGI from hiding metadata changes behind a 304 response.
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
