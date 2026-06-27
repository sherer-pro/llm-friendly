# Testing

## Local Syntax Check

Run:

```bash
composer run lint
```

If Composer is unavailable, run `php -l` against the plugin entrypoint and every PHP file in `inc/`.

## WordPress Integration Scenarios

Verify in a local WordPress 6.0+ install with PHP 7.4+:

- Settings sanitization: save valid and invalid `post_types`, `base_path`, `sitemap_url`, `excluded_posts`, and long custom Markdown blocks.
- Sitemap validation: verify site-relative and same-site absolute sitemap URLs are saved, external URLs fall back to `/sitemap.xml`, and `llmf_allow_external_sitemap_url` can explicitly allow them.
- Markdown sanitization: as a user without `unfiltered_html`, save `<script>`/unsafe HTML in `llms_custom_markdown` and `_llmf_md_content_override`; confirm unsafe markup is removed and length caps apply.
- Exclusion validation: submit forged IDs for another post type, draft/private/password-protected posts, duplicate IDs, and more than 500 IDs; confirm only valid exportable published posts remain.
- REST/editor permissions: a user can save `_llmf_md_content_override` only for posts they can edit.
- Markdown override preservation: multiline Markdown, fenced code blocks, and escaped Markdown characters survive a metabox save.
- Public endpoints: disabled Markdown, disabled `llms.txt`, excluded posts, password-protected posts, and non-public post types return 404.
- Headers: `.md` and `/llms.txt` include `Content-Type`, `X-Content-Type-Options`, `ETag`, `Last-Modified`, and expected `304` behavior.
- URL safety: unsafe link/image protocols are omitted, while URLs with spaces or parentheses remain valid Markdown destinations.
- AJAX exclusions: verify missing/invalid nonce, insufficient capability, invalid post type, duplicate/excluded results, and one-character multibyte searches return the expected errors or filtered results.
- Cache behavior: manual regeneration, auto regeneration on publish/update, trash/delete/untrash/status changes, stale cache serving, and no-cache `503` during an active regeneration lock.
