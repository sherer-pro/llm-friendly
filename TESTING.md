# Testing

## Local Syntax Check

Run:

```bash
composer run lint
```

If Composer is unavailable, run `php -l` against the plugin entrypoint and every PHP file in `inc/`.

## WordPress Integration Scenarios

Verify in a local WordPress 6.0+ install with PHP 7.4+:

- Settings sanitization: save valid and invalid `post_types`, `base_path`, `sitemap_url`, `excluded_posts`, `site_author_override`, and long custom Markdown blocks.
- Sitemap validation: verify site-relative and same-site absolute sitemap URLs are saved, external URLs fall back to `/sitemap.xml`, and `llmf_allow_external_sitemap_url` can explicitly allow them.
- Markdown sanitization: as a user without `unfiltered_html`, save `<script>`/unsafe HTML in `llms_custom_markdown` and `_llmf_md_content_override`; confirm unsafe markup is removed and length caps apply without collapsing Markdown line breaks.
- Per-post descriptions: save `_llmf_llms_description` through the editor metabox, verify the 500-character default cap, and confirm it appears in both `llms.txt` item descriptions and Markdown export JSON metadata.
- Exclusion validation: submit forged IDs for another post type, draft/private/password-protected posts, duplicate IDs, and more than 500 IDs; confirm only valid exportable published posts remain.
- REST/editor permissions: a user can save `_llmf_md_content_override` and `_llmf_llms_description` only for posts they can edit.
- Markdown override preservation: multiline Markdown, fenced code blocks, blank lines, headings, lists, and escaped Markdown characters survive Classic Editor and Gutenberg metabox saves.
- Admin click zones: in post type selection and excluded item lists, clicking empty space to the right of a checkbox label does not toggle the checkbox; clicking the checkbox or label text does toggle it.
- Markdown metadata: `.md` exports include `description`, `author`, and `publisher`; description falls back from per-post LLM description to Yoast SEO meta, explicit excerpt, then generated content summary; author uses `site_author_override` before post author display name.
- Public endpoints: disabled Markdown, disabled `llms.txt`, excluded posts, password-protected posts, and non-public post types return 404.
- Headers: `.md` and `/llms.txt` include `Content-Type`, `X-Content-Type-Options`, `ETag`, and `Last-Modified`; `.md` returns `304` only for matching ETags so metadata-only changes are not hidden by `If-Modified-Since`.
- URL safety: unsafe link/image protocols are omitted, while URLs with spaces or parentheses remain valid Markdown destinations.
- AJAX exclusions: verify missing/invalid nonce, insufficient capability, invalid post type, duplicate/excluded results, and one-character multibyte searches return the expected errors or filtered results. Check that a newly checked exportable post type can be searched before saving the settings form.
- Cache behavior: manual regeneration, auto regeneration on publish/update, description meta changes, Yoast description meta changes, trash/delete/untrash/status changes, stale cache serving, and no-cache `503` during an active regeneration lock. Confirm Markdown transient keys change when metadata changes.
