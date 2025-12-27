# LLM Friendly

LLM Friendly is a WordPress plugin that exposes:

- `/llms.txt` — an LLM-friendly index of your site
- Markdown exports for selected post types under `/{base}/{post_type}/{path}.md`

The goal is to make your site easier to navigate and consume for LLMs, indexing bots, and power users who prefer plain text.

## Features

- Generates an `llms.txt` index with links to your latest content
- Exposes `.md` endpoints for selected post types (posts, pages, custom post types)
- Configurable base path for Markdown exports (e.g. `llm`)
- Manual or automatic regeneration of the cached `llms.txt`
- Optional `X-Robots-Tag: noindex, nofollow` header for `llms.txt`

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
