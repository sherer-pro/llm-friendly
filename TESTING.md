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
- Public REST reads: overrides and descriptions are absent from normal and `_fields=meta` responses, including password-protected/excluded posts and disabled Markdown exports. Authenticated `context=edit` reads and writes remain available to permitted editors.
- Markdown override preservation: multiline Markdown, fenced code blocks, blank lines, headings, lists, and escaped Markdown characters survive Classic Editor and Gutenberg metabox saves.
- Admin click zones: in post type selection and excluded item lists, clicking empty space to the right of a checkbox label does not toggle the checkbox; clicking the checkbox or label text does toggle it.
- Markdown metadata: `.md` exports include `description`, `author`, and `publisher`; description falls back from per-post LLM description to Yoast SEO meta, explicit excerpt, then generated content summary; author uses `site_author_override` before post author display name.
- Public endpoints: disabled Markdown, disabled `llms.txt`, excluded posts, password-protected posts, and non-public post types return 404.
- llms.txt structure: every generated list item in an H2 file-list section starts with a valid Markdown link, summaries remain on that same line, and post types without exportable items do not create empty sections or placeholder bullets.
- Discovery and headers: frontend HTML advertises `/llms.txt` with `rel="describedby"`; supported singular views also advertise their `.md` representation with `rel="alternate"`. `.md` and `/llms.txt` include `Content-Type`, `X-Content-Type-Options`, `ETag`, and `Last-Modified`; `.md` combines canonical and describedby relations in one `Link` header and sends `X-Robots-Tag: noindex` when `md_send_noindex` is enabled; neither endpoint sends `nofollow`.
- Content negotiation: with `enabled_content_negotiation` disabled, canonical URLs always keep their normal HTML behavior. With it enabled, explicit `Accept: text/markdown` GET and HEAD requests for eligible singular posts return the same representation and validators as `.md`; `q=0`, wildcard-only Accept, POST, REST, feeds, previews, 404s, attachments, drafts, private/password-protected/excluded posts, and unselected post types do not negotiate Markdown.
- Cache variation: when content negotiation is enabled, verify `Vary: Accept` is merged with existing values on HTML and Markdown responses. Test the actual page cache, reverse proxy, and CDN with alternating HTML and Markdown requests to the same URL and confirm neither variant leaks into the other cache key.
- Conditional responses: negotiated and explicit `.md` responses return `304` for matching ETags; metadata-only changes are not hidden by `If-Modified-Since`.
- Apache/FastCGI conditional responses: after a metadata-only update, an IMS-only request returns `200` with the new body and no Last-Modified header; an old ETag also returns `200`, while the current ETag returns `304`. Ordinary GET/HEAD responses retain Last-Modified.
- Shared Markdown context: use a public Query Loop containing post titles/excerpts and synthetic private/password-protected records. Warm the Markdown cache first as an editor and then anonymously; neither result may contain protected content. Repeat with a valid post password cookie, and confirm the original user/cookie is restored after a render exception.
- Developer filters: verify `llmf_markdown_cache_ttl` changes Markdown transient lifetime and `llmf_debug_headers_enabled` adds the expected `X-LLMF-*` diagnostics to `/llms.txt` responses only when enabled.
- AI crawler diagnostics: verify the settings page shows OAI-SearchBot, GPTBot, ChatGPT-User without a robots.txt snippet, Googlebot/Search AI features, Google-Extended, and the configured sitemap URL. Confirm the UI separates discovery, indexing, crawler access, and training/licensing permissions and does not modify `robots.txt` automatically.
- Server routing: test pretty permalinks on Apache and Nginx, ensure `.md` and `/llms.txt` reach WordPress before static-file rules, and re-save Permalinks after endpoint/base-path changes.
- URL safety: unsafe link/image protocols are omitted, while URLs with spaces or parentheses remain valid Markdown destinations.
- Markdown conversion: verify buttons, file/download blocks, embeds, details blocks, group/columns/media-text containers, nested lists, and code containing backticks preserve useful text and URLs.
- AJAX exclusions: verify missing/invalid nonce, insufficient capability, invalid post type, duplicate/excluded results, and one-character multibyte searches return the expected errors or filtered results. Check that a newly checked exportable post type can be searched before saving the settings form.
- Cache behavior: manual regeneration, auto regeneration on publish/update, description meta changes, Yoast description meta changes, trash/delete/untrash/status changes, stale cache serving, and no-cache `503` during an active regeneration lock. Confirm Markdown transient keys change when metadata changes.
- Privacy invalidation: warm llms.txt, then make a listed post private, draft, password-protected, trashed, or deleted. Verify immediate removal and rejection of the old ETag in both auto/manual modes, including real admin requests with Settings API sanitization registered.
- Administrative regeneration: verify a valid administrator nonce rebuilds cache/revision without changing user settings. An active lock returns a retryable `503` instead of a success redirect. Reject forged cache fields in settings input.
- Credential-bearing URLs: reject userinfo in sitemap URLs, including with the external sitemap opt-in filter enabled, and omit userinfo URLs from Markdown links/images and Essential links.

## Security Hardening Verification: 0.2.1

Verified on 2026-10-06 with WordPress 7.1.2, PHP 8.4.19 and Apache/FastCGI at the local test site. PHP cURL retained TLS verification. The integration run passed 65 checks, including setup and cleanup checks.

| Finding | Confirmed behavior |
| --- | --- |
| SEC-01 | Public REST responses omit editor metadata for public, password-protected and excluded posts, including `_fields=meta` and disabled exports. Permitted editor reads and writes still work; subscriber edit-context reads return 403. |
| SEC-02 | A privileged Query Loop control can read synthetic private/password-protected excerpts, but editor-first Markdown cache warming cannot publish either excerpt. User and password-cookie state is restored after exceptions, including an initially absent cookie. |
| SEC-03 | Private, draft, password, trash, delete and exclusion changes immediately remove warmed content in both auto and manual modes. Old llms.txt ETags return the new 200 response, and the revoked Markdown endpoint returns 404. |
| SEC-04 | Missing/invalid nonces and insufficient capability return 403. Successful administrator regeneration advances the cache revision without changing user settings; a held lock returns 503. Ordinary settings cannot inject cache contents. |
| SEC-05 | Metadata-only changes update ETags. IMS-only GET/HEAD requests return 200 without Last-Modified; an explicit CGI Status prevents Apache's fallback condition handling and stays internal. Matching ETags return empty 304 responses and take precedence over IMS. HTML and negotiated Markdown retain Vary: Accept. |
| SEC-06 | WordPress-backed URL sanitization rejects userinfo, including with external sitemap opt-in. Generated sitemap and Markdown link destinations omit it. Safe opted-in external URLs remain supported. |

The run restored the exact raw settings baseline before refreshing cache, preserved every user setting during that refresh, restored rewrite rules and related cron events, and removed all fixture posts/users and their Markdown caches. The final public llms.txt response returned 200 without fixture markers.

The lightweight suite also passed 208 assertions on PHP 7.4.33. Production caches/CDNs, Nginx routing and other WordPress versions remain outside this integration verification.

## Opt-in Mechanics Verification

Version 0.3.0 adds independent `legacy/structured` index and `legacy/enhanced` content modes. Run the required reproducible gates:

```bash
composer validate --strict
composer run lint
composer run test
node --check assets/llmf-admin.js
```

The lightweight suite includes exact synthetic 0.2.1 output fixtures for the root map, Markdown metadata/body and Gutenberg conversion. It checks missing-key defaults without migration writes, partial settings preservation, mode switchback, pinned ordering/caps, public taxonomy and parent validation, forged query values, filtered cursor progression, current privacy/filter checks on cached IDs/dependencies, reusable-block changes and override review states. It also covers description priority, multibyte preview limits, tables/captions/media/code, anonymous exception restoration, new action capabilities/nonces and bounded HTTP checks. Its 10,000-record traversal is separate from the real SQL test below.

### Active WordPress integration

Run `tests/wordpress-mechanics.php` against a disposable/local WordPress with this checkout active. PHP cURL must be available. PowerShell example:

```powershell
$env:LLMF_WP_ROOT = 'D:/OSPanel/home/sherer.loc/public'
$env:LLMF_TEST_ALLOW_WRITES = '1'
$env:LLMF_HTTP_RESOLVE = 'sherer.loc:443:127.0.1.11' # Optional local DNS override
php -d "sys_temp_dir=$env:TEMP" tests/wordpress-mechanics.php
```

The script temporarily changes plugin configuration/rewrite rules and creates only uniquely named fixture posts, categories and users. It never changes existing posts/users. It snapshots raw plugin options, internal publication/job state, rewrite rules and plugin cron events, restores them in `finally`, deletes owned fixtures/caches, refreshes the original root without altering user settings and checks the clean public response. On a local Sherer Site Core installation requiring 2FA, only newly created fixture users are enrolled through its existing service; fixture recovery/enrollment notices are deleted on cleanup. Credentials are not printed or written to fixture files. TLS verification remains enabled.

The fixture set contains ten fixed content questions with identifiable facts and sources: Classic paragraphs, headings, an older Gutenberg list, nested lists, a simple table, code, an image/caption, a relative link, a complex table and a reusable block. All ten source URLs must be found through the type index and must preserve their answer fact in Markdown. This is deterministic content/navigation acceptance, not an evaluation of a third-party LLM's answers.

| Scope | Evidence from the runner |
| --- | --- |
| Content discovery | Ten source/fact checks, pretty catalog routes, numeric term routes, nonempty topic projection and hierarchical parent scope |
| HTTP | GET/HEAD equivalence, ETag 304, IMS-only metadata refresh and explicit Accept/Vary negotiation |
| Public boundaries | Private/draft/password/excluded items, forged direct query vars, warmed catalog/root revocation in manual mode |
| Dependencies | Changed/hidden reusable sources, filtered nested container fallback and manual override source state |
| Permissions | Anonymous and five standard roles; valid comparison access only for administrators and invalid diagnostic nonces denied |
| Compatibility | Switchback preserves pins, disables direct catalog access and repeated activation hooks preserve settings/historical overrides |
| Cleanup | Fixture post/user/term deletion, exact raw settings/rewrite/plugin-cron restoration, setting-preserving cache refresh and clean root |

### Real 10,000-record SQL traversal

`tests/wordpress-scale.php` requires a separate loopback WordPress with an isolated table prefix beginning `llmf_`. It deliberately refuses the normal local site's prefix. Configure `LLMF_WP_ROOT` and `LLMF_TEST_ALLOW_WRITES=1` as above, then run:

```bash
php tests/wordpress-scale.php
```

The runner inserts 10,000 uniquely marked records of a temporary process-local public type, walks real `WP_Query` ID cursors, rejects the entire first page and every thirteenth later record, checks SQL LIMIT/no count queries, and deletes only its marked rows in `finally`. Plugin settings and scan jobs are not saved. Expected result: 101 bounded pages, 9,138 eligible records, no gaps/duplicates, completed cleanup. This tests query shape and complete traversal; it is not a production latency benchmark or a simulation of concurrent writes during pagination.

### Browser acceptance

On an isolated site, verify new mode choices, pin search/add/reorder/removal, unsaved comparison, complete form save, switchback and coverage start/continue. Check that disabled buttons recover after errors, result text is inserted through safe DOM APIs, and endpoint errors remain distinct from an environment unable to complete a check. Compare desktop and 390 px layouts for horizontal overflow and access to the controls. Live local TLS or an unusual loopback port may prevent WordPress's own safe HTTP probes; cURL endpoint success does not turn that diagnostic limitation into an availability pass.

Verified on 2026-10-06:

| Environment / check | Result |
| --- | --- |
| PHP 8.4.19 | Composer strict validation, complete PHP lint, 439 assertions and admin JavaScript syntax passed |
| PHP 7.4.33 | 439 assertions and PHP lint, including generated translation runtime files, passed |
| WordPress 7.1.2 / PHP 8.4.19 / Apache FastCGI / HTTPS | 89 integration checks passed; TLS verification retained |
| WordPress 6.0 / PHP 7.4.33 / built-in loopback HTTP server | 89 integration checks passed |
| WordPress 6.0 real SQL traversal | 10,000 fixture records, 101 bounded pages and 9,138 eligible records, without gaps/duplicates; fixture rows removed |
| Six plugin locales | New strings translated; placeholder consistency and generated catalog/runtime files checked |
| WordPress 6.0 browser UI | Search/add/reorder/remove pins, unsaved comparison, save/reload, mode switchback with retained pin order and coverage start/continue passed |
| Responsive layout | Desktop comparison has two columns; the 390 px viewport has one. Document scroll width equals client width in both layouts (1,265 px desktop, 375 px mobile, excluding the scrollbar) |

All ten fixed source/fact questions passed in each WordPress integration environment. Browser diagnostics correctly reported an inability to complete the safe HTTP probes on the isolated loopback port; those probes were not recorded as availability passes. The integration runners restored the local site's original settings/rewrite/plugin-cron state and removed their fixture data. The separate minimum-version site and its twelve isolated database tables were removed after browser acceptance; its loopback server was stopped.

Production page caches/CDNs, Nginx/static-file routing, multisite, a live Yoast installation and other PHP/WordPress combinations remain outside this run. The real SQL test does not establish production latency or behavior under concurrent writes.
