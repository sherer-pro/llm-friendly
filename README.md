# LLM Friendly

LLM Friendly is a WordPress plugin that exposes:

- `/llms.txt` — an LLM-friendly index of your site
- Markdown exports for selected post types under `/{base}/{post_type}/{path}.md`

Current version: **0.4.1**

The goal is to make your site easier to navigate and consume for LLMs, indexing bots, and power users who prefer plain text.

## Features

- Generates an `llms.txt` index with links to your latest content, optional excerpts, and an optional custom Markdown block.
- Exposes `.md` endpoints for selected post types (posts, pages, custom post types) with Gutenberg-to-Markdown conversion and per-post Markdown overrides.
- Configurable base path for Markdown exports (e.g. `llm`) and per-post-type enable/disable toggles.
- Manual or automatic regeneration of the cached `llms.txt`, complete with ETag/Last-Modified headers.
- Optional `X-Robots-Tag: noindex, nofollow` header for both `llms.txt` and Markdown exports.
- Outputs `<link rel="alternate" type="text/markdown">` tags on singular views for supported post types.
- Optional site title/description overrides and sitemap URL for the generated `llms.txt`.

## Requirements

- WordPress 6.0+
- PHP 7.4+

If requirements are not met, the plugin shows an admin warning and does not run.

## Installation

1. Upload the plugin folder to `wp-content/plugins/llm-friendly/`
2. Activate “LLM Friendly” in WordPress Admin → Plugins
3. Open Settings → LLM Friendly and configure:
   - Enable llms.txt
   - Enable Markdown exports
   - Select post types
   - Set base path (optional)
4. Save changes

## Usage

- Open `https://example.com/llms.txt`
- Open a Markdown export, for example:
  - `https://example.com/llm/post/hello-world.md`
  - `https://example.com/llm/page/about.md`

Note: if you are running Nginx in front of Apache (or have aggressive static rules), make sure `.md` and `/llms.txt` requests are routed to WordPress (not treated as static files).

## Security notes

- Do not enable post types that should not be publicly accessible.
- Password-protected content should not be exported.

## License

GPL-2.0-or-later
