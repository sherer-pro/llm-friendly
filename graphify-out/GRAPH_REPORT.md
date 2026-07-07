# Graph Report - llm-friendly-git  (2026-07-07)

## Corpus Check
- 20 files · ~31,958 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 251 nodes · 656 edges · 16 communities
- Extraction: 68% EXTRACTED · 32% INFERRED · 0% AMBIGUOUS · INFERRED: 212 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `5164d1ca`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- [[_COMMUNITY_run.php|run.php]]
- [[_COMMUNITY_Options|Options]]
- [[_COMMUNITY_Exporter|Exporter]]
- [[_COMMUNITY_Admin|Admin]]
- [[_COMMUNITY_Plugin|Plugin]]
- [[_COMMUNITY_AGENTS|AGENTS.md]]
- [[_COMMUNITY_llm-friendly.php|llm-friendly.php]]
- [[_COMMUNITY_composer.json|composer.json]]
- [[_COMMUNITY_Response|Response]]

## God Nodes (most connected - your core abstractions)
1. `Options` - 53 edges
2. `Exporter` - 40 edges
3. `Admin` - 36 edges
4. `WP_Post` - 28 edges
5. `Markdown` - 26 edges
6. `Llms` - 22 edges
7. `esc_html__()` - 22 edges
8. `Plugin` - 19 edges
9. `__()` - 18 edges
10. `esc_attr()` - 15 edges

## Surprising Connections (you probably didn't know these)
- `llmf_add_settings_link()` --calls--> `esc_html__()`  [INFERRED]
  llm-friendly.php → tests/run.php
- `llmf_requirements_notice()` --calls--> `esc_html__()`  [INFERRED]
  llm-friendly.php → tests/run.php
- `llmf_activate()` --calls--> `esc_html__()`  [INFERRED]
  llm-friendly.php → tests/run.php
- `llmf_requirements_met()` --calls--> `get_bloginfo()`  [INFERRED]
  llm-friendly.php → tests/run.php
- `llmf_requirements_notice()` --calls--> `get_bloginfo()`  [INFERRED]
  llm-friendly.php → tests/run.php

## Import Cycles
- None detected.

## Communities (16 total, 0 thin omitted)

### Community 0 - "run.php"
Cohesion: 0.09
Nodes (21): add_option(), assert_contains_text(), assert_not_contains_text(), assert_true(), checked(), get_feed_link(), get_option(), get_page_uri() (+13 more)

### Community 1 - "Options"
Cohesion: 0.10
Nodes (8): Options, apply_filters(), esc_url_raw(), get_post_meta(), home_url(), sanitize_key(), wp_parse_url(), wp_strip_all_tags()

### Community 2 - "Exporter"
Cohesion: 0.14
Nodes (4): DOMDocument, Exporter, Markdown, wp_json_encode()

### Community 3 - "Admin"
Cohesion: 0.14
Nodes (5): __(), Admin, esc_attr(), esc_html__(), esc_textarea()

### Community 4 - "Plugin"
Cohesion: 0.13
Nodes (3): Plugin, Rewrites, add_filter()

### Community 5 - "AGENTS.md"
Cohesion: 0.17
Nodes (8): Llms, delete_transient(), get_transient(), set_transient(), update_option(), wp_next_scheduled(), WP_Post, wp_schedule_single_event()

### Community 6 - "llm-friendly.php"
Cohesion: 0.22
Nodes (6): llmf_activate(), llmf_add_settings_link(), llmf_requirements_met(), llmf_requirements_notice(), add_action(), get_bloginfo()

### Community 7 - "composer.json"
Cohesion: 0.20
Nodes (9): description, license, name, require, php, scripts, lint, test (+1 more)

### Community 8 - "Response"
Cohesion: 0.12
Nodes (7): Response, current_user_can(), sanitize_text_field(), wp_is_post_autosave(), wp_is_post_revision(), wp_kses_post(), wp_unslash()

## Knowledge Gaps
- **7 isolated node(s):** `name`, `description`, `type`, `license`, `php` (+2 more)
  These have ≤1 connection - possible missing edges or undocumented components.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Options` connect `Options` to `run.php`, `Exporter`, `Plugin`, `AGENTS.md`, `llm-friendly.php`, `Response`?**
  _High betweenness centrality (0.193) - this node is a cross-community bridge._
- **Why does `Exporter` connect `Exporter` to `run.php`, `Options`, `Plugin`, `AGENTS.md`?**
  _High betweenness centrality (0.184) - this node is a cross-community bridge._
- **Why does `Plugin` connect `Plugin` to `Options`, `Exporter`, `Admin`, `AGENTS.md`?**
  _High betweenness centrality (0.118) - this node is a cross-community bridge._
- **Are the 20 inferred relationships involving `Markdown` (e.g. with `.blocks_to_markdown()` and `.build_image_markdown()`) actually correct?**
  _`Markdown` has 20 INFERRED edges - model-reasoned connections that need verification._
- **What connects `name`, `description`, `type` to the rest of the system?**
  _7 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `run.php` be split into smaller, more focused modules?**
  _Cohesion score 0.0873440285204991 - nodes in this community are weakly interconnected._
- **Should `Options` be split into smaller, more focused modules?**
  _Cohesion score 0.10283687943262411 - nodes in this community are weakly interconnected._