# Testing

## Local Syntax Check

Run:

```bash
composer run lint
```

If Composer is unavailable, run `php -l` against the plugin entrypoint and every PHP file in `inc/`.

## Lightweight Regression Tests

Run:

```bash
composer run test
```

These tests use WordPress stubs to cover Markdown helper safety, settings sanitization, `llms.txt` linked-list structure, discovery relations, Markdown canonical/noindex/Vary headers, content-negotiation decisions, export eligibility, and Gutenberg block conversion for links, files, embeds, details, nested content, and code fences.

## WordPress Integration Scenarios

Verify in a local WordPress 6.0+ install with PHP 7.4+:

- Settings sanitization: save valid and invalid `post_types`, `base_path`, `sitemap_url`, `excluded_posts`, `site_author_override`, and long custom Markdown blocks.
- Essential links: save valid and invalid `llms_essential_links` lines (`Title | URL | Notes`), confirm unsafe protocols are dropped, site-relative URLs are expanded in `llms.txt`, configured front page/posts page/privacy policy links appear when available, and the section appears after "Main links" and before recent post-type sections.
- Custom llms.txt notes: submit ATX and setext headings in `llms_custom_markdown`; confirm heading markers are removed while fenced-code headings remain untouched.
- Sitemap validation: verify site-relative and same-site absolute sitemap URLs are saved, external URLs fall back to `/sitemap.xml`, and `llmf_allow_external_sitemap_url` can explicitly allow them.
- Markdown sanitization: as a user without `unfiltered_html`, save `<script>`/unsafe HTML in `llms_custom_markdown` and `_llmf_md_content_override`; confirm unsafe markup is removed and length caps apply without collapsing Markdown line breaks.
- Per-post descriptions: save `_llmf_llms_description` through the editor metabox, verify the 500-character default cap, and confirm it appears on the same linked line in `llms.txt` and in the plugin-defined Markdown export JSON metadata.
- Exclusion validation: submit forged IDs for another post type, draft/private/password-protected posts, duplicate IDs, and more than 500 IDs; confirm only valid exportable published posts remain.
- REST/editor permissions: a user can save `_llmf_md_content_override` and `_llmf_llms_description` only for posts they can edit.
- Markdown override preservation: multiline Markdown, fenced code blocks, blank lines, headings, lists, and escaped Markdown characters survive Classic Editor and Gutenberg metabox saves.
- Admin click zones: in post type selection and excluded item lists, clicking empty space to the right of a checkbox label does not toggle the checkbox; clicking the checkbox or label text does toggle it.
- Markdown metadata: `.md` exports include `description`, `author`, and `publisher`; description falls back from per-post LLM description to Yoast SEO meta, explicit excerpt, then generated content summary; author uses `site_author_override` before post author display name.
- Public endpoints: disabled Markdown, disabled `llms.txt`, excluded posts, password-protected posts, and non-public post types return 404.
- llms.txt structure: every generated list item in an H2 file-list section starts with a valid Markdown link, summaries remain on that same line, and post types without exportable items do not create empty sections or placeholder bullets.
- Discovery and headers: frontend HTML advertises `/llms.txt` with `rel="describedby"`; supported singular views also advertise their `.md` representation with `rel="alternate"`. `.md` and `/llms.txt` include `Content-Type`, `X-Content-Type-Options`, `ETag`, and `Last-Modified`; `.md` combines canonical and describedby relations in one `Link` header and sends `X-Robots-Tag: noindex` when `md_send_noindex` is enabled; neither endpoint sends `nofollow`.
- Content negotiation: with `enabled_content_negotiation` disabled, canonical URLs always keep their normal HTML behavior. With it enabled, explicit `Accept: text/markdown` GET and HEAD requests for eligible singular posts return the same representation and validators as `.md`; `q=0`, wildcard-only Accept, POST, REST, feeds, previews, 404s, attachments, drafts, private/password-protected/excluded posts, and unselected post types do not negotiate Markdown.
- Cache variation: when content negotiation is enabled, verify `Vary: Accept` is merged with existing values on HTML and Markdown responses. Test the actual page cache, reverse proxy, and CDN with alternating HTML and Markdown requests to the same URL and confirm neither variant leaks into the other cache key.
- Conditional responses: negotiated and explicit `.md` responses return `304` for matching ETags; metadata-only changes are not hidden by `If-Modified-Since`.
- Developer filters: verify `llmf_markdown_cache_ttl` changes Markdown transient lifetime and `llmf_debug_headers_enabled` adds the expected `X-LLMF-*` diagnostics to `/llms.txt` responses only when enabled.
- AI crawler diagnostics: verify the settings page shows OAI-SearchBot, GPTBot, ChatGPT-User without a robots.txt snippet, Googlebot/Search AI features, Google-Extended, and the configured sitemap URL. Confirm the UI separates discovery, indexing, crawler access, and training/licensing permissions and does not modify `robots.txt` automatically.
- Server routing: test pretty permalinks on Apache and Nginx, ensure `.md` and `/llms.txt` reach WordPress before static-file rules, and re-save Permalinks after endpoint/base-path changes.
- URL safety: unsafe link/image protocols are omitted, while URLs with spaces or parentheses remain valid Markdown destinations.
- Markdown conversion: verify buttons, file/download blocks, embeds, details blocks, group/columns/media-text containers, nested lists, and code containing backticks preserve useful text and URLs.
- AJAX exclusions: verify missing/invalid nonce, insufficient capability, invalid post type, duplicate/excluded results, and one-character multibyte searches return the expected errors or filtered results. Check that a newly checked exportable post type can be searched before saving the settings form.
- Cache behavior: manual regeneration, auto regeneration on publish/update, description meta changes, Yoast description meta changes, trash/delete/untrash/status changes, stale cache serving, and no-cache `503` during an active regeneration lock. Confirm Markdown transient keys change when metadata changes.
