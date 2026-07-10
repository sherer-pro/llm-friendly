# Graph Report - llm-friendly-git  (2026-07-09)

## Corpus Check
- 20 files · ~45,129 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 333 nodes · 886 edges · 20 communities (17 shown, 3 thin omitted)
- Extraction: 70% EXTRACTED · 30% INFERRED · 0% AMBIGUOUS · INFERRED: 270 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `4a3d89ae`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- [[_COMMUNITY_Admin Interface Components|Admin Interface Components]]
- [[_COMMUNITY_Post and Options Management|Post and Options Management]]
- [[_COMMUNITY_Admin AJAX and Settings|Admin AJAX and Settings]]
- [[_COMMUNITY_Markdown Conversion Logic|Markdown Conversion Logic]]
- [[_COMMUNITY_LLM Content Generation|LLM Content Generation]]
- [[_COMMUNITY_Plugin Lifecycle and Setup|Plugin Lifecycle and Setup]]
- [[_COMMUNITY_Plugin Core Functionality|Plugin Core Functionality]]
- [[_COMMUNITY_Composer Package Metadata|Composer Package Metadata]]
- [[_COMMUNITY_current_user_can|current_user_can]]
- [[_COMMUNITY_Q Расскажи мне про секцию на странице настроек Диагностика AI-краулеров|Q: Расскажи мне про секцию на странице настроек Диагностика AI-краулеров]]
- [[_COMMUNITY_llms.txt Index|llms.txt Index]]
- [[_COMMUNITY_PHP|PHP]]
- [[_COMMUNITY_WordPress|WordPress]]

## God Nodes (most connected - your core abstractions)
1. `Admin` - 67 edges
2. `Options` - 53 edges
3. `Exporter` - 40 edges
4. `__()` - 40 edges
5. `WP_Post` - 28 edges
6. `Markdown` - 27 edges
7. `Llms` - 24 edges
8. `esc_html__()` - 20 edges
9. `Plugin` - 19 edges
10. `sanitize_key()` - 16 edges

## Surprising Connections (you probably didn't know these)
- `llmf_requirements_notice()` --calls--> `esc_html__()`  [INFERRED]
  llm-friendly.php → tests/run.php
- `llmf_activate()` --calls--> `esc_html__()`  [INFERRED]
  llm-friendly.php → tests/run.php
- `Markdown Exports` --conceptually_related_to--> `llmf_allow_external_sitemap_url`  [INFERRED]
  README.md → readme.txt
- `llmf_requirements_met()` --calls--> `get_bloginfo()`  [INFERRED]
  llm-friendly.php → tests/run.php
- `llmf_requirements_notice()` --calls--> `get_bloginfo()`  [INFERRED]
  llm-friendly.php → tests/run.php

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Core LLM Friendly Capabilities** — llms_txt_endpoint, markdown_exports [EXTRACTED 1.00]
- **Markdown Export Configuration Hooks** — markdown_exports, llmf_can_export_post, llmf_exportable_post_type, llmf_markdown_metadata [EXTRACTED 0.90]

## Communities (20 total, 3 thin omitted)

### Community 0 - "Admin Interface Components"
Cohesion: 0.09
Nodes (13): __(), Admin, llmf_add_settings_link(), admin_url(), checked(), esc_attr__(), esc_html__(), esc_textarea() (+5 more)

### Community 1 - "Post and Options Management"
Cohesion: 0.10
Nodes (7): Options, apply_filters(), get_post_meta(), sanitize_key(), wp_kses_post(), wp_parse_url(), WP_Post

### Community 2 - "Admin AJAX and Settings"
Cohesion: 0.07
Nodes (24): RuntimeException, add_option(), add_settings_field(), add_settings_section(), assert_contains_text(), assert_not_contains_text(), assert_occurrences(), assert_true() (+16 more)

### Community 3 - "Markdown Conversion Logic"
Cohesion: 0.12
Nodes (8): DOMDocument, Exporter, Markdown, get_permalink(), get_the_modified_date(), get_the_title(), wp_json_encode(), wp_strip_all_tags()

### Community 4 - "LLM Content Generation"
Cohesion: 0.15
Nodes (9): Llms, delete_transient(), get_feed_link(), get_post(), get_transient(), set_transient(), update_option(), wp_next_scheduled() (+1 more)

### Community 5 - "Plugin Lifecycle and Setup"
Cohesion: 0.11
Nodes (18): llmf_allow_external_sitemap_url, llmf_can_export_post, llmf_exportable_post_type, llmf_markdown_metadata, llms_send_noindex, llms_show_excerpt, /llms.txt Endpoint, Markdown Exports (+10 more)

### Community 6 - "Plugin Core Functionality"
Cohesion: 0.09
Nodes (9): Plugin, Rewrites, llmf_activate(), llmf_requirements_met(), llmf_requirements_notice(), add_action(), add_filter(), get_bloginfo() (+1 more)

### Community 7 - "Composer Package Metadata"
Cohesion: 0.20
Nodes (9): description, license, name, require, php, scripts, lint, test (+1 more)

### Community 15 - "current_user_can"
Cohesion: 0.11
Nodes (10): Response, check_ajax_referer(), current_user_can(), sanitize_text_field(), wp_is_post_autosave(), wp_is_post_revision(), wp_send_json_error(), wp_send_json_success() (+2 more)

### Community 16 - "Q: Расскажи мне про секцию на странице настроек Диагностика AI-краулеров"
Cohesion: 0.40
Nodes (4): Answer, Outcome, Q: Расскажи мне про секцию на странице настроек Диагностика AI-краулеров, Source Nodes

## Knowledge Gaps
- **28 isolated node(s):** `name`, `description`, `type`, `license`, `php` (+23 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **3 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Admin` connect `Admin Interface Components` to `Admin AJAX and Settings`, `Plugin Core Functionality`, `current_user_can`?**
  _High betweenness centrality (0.163) - this node is a cross-community bridge._
- **Why does `Exporter` connect `Markdown Conversion Logic` to `Post and Options Management`, `Admin AJAX and Settings`, `Plugin Core Functionality`, `current_user_can`?**
  _High betweenness centrality (0.125) - this node is a cross-community bridge._
- **Why does `Options` connect `Post and Options Management` to `Admin AJAX and Settings`, `Markdown Conversion Logic`, `LLM Content Generation`, `Plugin Core Functionality`, `current_user_can`?**
  _High betweenness centrality (0.118) - this node is a cross-community bridge._
- **Are the 39 inferred relationships involving `__()` (e.g. with `.admin_init()` and `.ajax_preview_llms()`) actually correct?**
  _`__()` has 39 INFERRED edges - model-reasoned connections that need verification._
- **What connects `name`, `description`, `type` to the rest of the system?**
  _28 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Admin Interface Components` be split into smaller, more focused modules?**
  _Cohesion score 0.09134808853118712 - nodes in this community are weakly interconnected._
- **Should `Post and Options Management` be split into smaller, more focused modules?**
  _Cohesion score 0.09954751131221719 - nodes in this community are weakly interconnected._