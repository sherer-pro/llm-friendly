# Graph Report - D:\DevProjects\wordpress\llm-friendly-all\llm-friendly-git  (2026-07-07)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 319 nodes · 878 edges · 15 communities
- Extraction: 69% EXTRACTED · 31% INFERRED · 0% AMBIGUOUS · INFERRED: 270 edges (avg confidence: 0.8)
- Token cost: 823 input · 144 output

## Graph Freshness
- Built from commit: `bb3ffff1`
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
- `llmf_requirements_met()` --calls--> `get_bloginfo()`  [INFERRED]
  llm-friendly.php → tests/run.php
- `llmf_requirements_notice()` --calls--> `esc_html__()`  [INFERRED]
  llm-friendly.php → tests/run.php
- `llmf_requirements_notice()` --calls--> `get_bloginfo()`  [INFERRED]
  llm-friendly.php → tests/run.php
- `llmf_activate()` --calls--> `esc_html__()`  [INFERRED]
  llm-friendly.php → tests/run.php
- `Markdown Exports` --conceptually_related_to--> `llmf_allow_external_sitemap_url`  [INFERRED]
  README.md → readme.txt

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **LLM Friendly Core Features** — llms_txt_index, markdown_exports [EXTRACTED 1.00]
- **Core LLM Friendly Capabilities** — llms_txt_endpoint, markdown_exports [EXTRACTED 1.00]
- **Markdown Export Configuration Hooks** — markdown_exports, llmf_can_export_post, llmf_exportable_post_type, llmf_markdown_metadata [EXTRACTED 0.90]

## Communities (15 total, 0 thin omitted)

### Community 0 - "Admin Interface Components"
Cohesion: 0.09
Nodes (14): __(), Admin, llmf_add_settings_link(), admin_url(), checked(), esc_attr__(), esc_html__(), esc_textarea() (+6 more)

### Community 1 - "Post and Options Management"
Cohesion: 0.09
Nodes (9): Options, apply_filters(), get_bloginfo(), get_post_meta(), sanitize_key(), wp_kses_post(), wp_parse_url(), WP_Post (+1 more)

### Community 2 - "Admin AJAX and Settings"
Cohesion: 0.06
Nodes (29): Response, RuntimeException, add_option(), add_settings_field(), add_settings_section(), assert_contains_text(), assert_not_contains_text(), assert_occurrences() (+21 more)

### Community 3 - "Markdown Conversion Logic"
Cohesion: 0.12
Nodes (7): DOMDocument, Exporter, Markdown, esc_url_raw(), parse_blocks(), wp_json_encode(), wp_strip_all_tags()

### Community 4 - "LLM Content Generation"
Cohesion: 0.14
Nodes (13): Llms, delete_transient(), get_option(), get_permalink(), get_post(), get_the_date(), get_the_modified_date(), get_the_title() (+5 more)

### Community 5 - "Plugin Lifecycle and Setup"
Cohesion: 0.10
Nodes (17): llmf_activate(), llmf_requirements_met(), llmf_requirements_notice(), llmf_allow_external_sitemap_url, llmf_can_export_post, llmf_exportable_post_type, llmf_markdown_metadata, llms_send_noindex (+9 more)

### Community 6 - "Plugin Core Functionality"
Cohesion: 0.13
Nodes (3): Plugin, Rewrites, add_filter()

### Community 7 - "Composer Package Metadata"
Cohesion: 0.20
Nodes (9): description, license, name, require, php, scripts, lint, test (+1 more)

## Knowledge Gaps
- **17 isolated node(s):** `name`, `description`, `type`, `license`, `php` (+12 more)
  These have ≤1 connection - possible missing edges or undocumented components.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Admin` connect `Admin Interface Components` to `Admin AJAX and Settings`, `Plugin Lifecycle and Setup`, `Plugin Core Functionality`?**
  _High betweenness centrality (0.181) - this node is a cross-community bridge._
- **Why does `Exporter` connect `Markdown Conversion Logic` to `Post and Options Management`, `Admin AJAX and Settings`, `LLM Content Generation`, `Plugin Core Functionality`?**
  _High betweenness centrality (0.143) - this node is a cross-community bridge._
- **Why does `Options` connect `Post and Options Management` to `Markdown Conversion Logic`, `LLM Content Generation`, `Plugin Lifecycle and Setup`, `Plugin Core Functionality`?**
  _High betweenness centrality (0.133) - this node is a cross-community bridge._
- **Are the 39 inferred relationships involving `__()` (e.g. with `.admin_init()` and `.ajax_preview_llms()`) actually correct?**
  _`__()` has 39 INFERRED edges - model-reasoned connections that need verification._
- **What connects `name`, `description`, `type` to the rest of the system?**
  _17 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Admin Interface Components` be split into smaller, more focused modules?**
  _Cohesion score 0.08998435054773082 - nodes in this community are weakly interconnected._
- **Should `Post and Options Management` be split into smaller, more focused modules?**
  _Cohesion score 0.08590441621294616 - nodes in this community are weakly interconnected._