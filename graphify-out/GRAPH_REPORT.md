# Graph Report - llm-friendly-git  (2026-07-07)

## Corpus Check
- 19 files · ~44,394 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 316 nodes · 873 edges · 16 communities
- Extraction: 69% EXTRACTED · 31% INFERRED · 0% AMBIGUOUS · INFERRED: 268 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `44f0cac6`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- [[_COMMUNITY_run.php|run.php]]
- [[_COMMUNITY_Options|Options]]
- [[_COMMUNITY_Exporter|Exporter]]
- [[_COMMUNITY_Admin|Admin]]
- [[_COMMUNITY_Plugin|Plugin]]
- [[_COMMUNITY_AGENTS|AGENTS.md]]
- [[_COMMUNITY_Response|Response]]
- [[_COMMUNITY_composer.json|composer.json]]
- [[_COMMUNITY_LLM Friendly|LLM Friendly]]

## God Nodes (most connected - your core abstractions)
1. `Admin` - 66 edges
2. `Options` - 53 edges
3. `__()` - 40 edges
4. `Exporter` - 39 edges
5. `WP_Post` - 28 edges
6. `Markdown` - 27 edges
7. `Llms` - 23 edges
8. `esc_html__()` - 20 edges
9. `Plugin` - 19 edges
10. `sanitize_key()` - 16 edges

## Surprising Connections (you probably didn't know these)
- `llmf_activate()` --calls--> `esc_html__()`  [INFERRED]
  llm-friendly.php → tests/run.php
- `llmf_requirements_notice()` --calls--> `esc_html__()`  [INFERRED]
  llm-friendly.php → tests/run.php
- `llmf_requirements_met()` --calls--> `get_bloginfo()`  [INFERRED]
  llm-friendly.php → tests/run.php
- `llmf_requirements_notice()` --calls--> `get_bloginfo()`  [INFERRED]
  llm-friendly.php → tests/run.php
- `llmf_add_settings_link()` --calls--> `esc_html__()`  [INFERRED]
  llm-friendly.php → tests/run.php

## Import Cycles
- None detected.

## Communities (16 total, 0 thin omitted)

### Community 0 - "run.php"
Cohesion: 0.05
Nodes (30): Response, RuntimeException, add_option(), add_settings_field(), add_settings_section(), assert_contains_text(), assert_not_contains_text(), assert_occurrences() (+22 more)

### Community 1 - "Options"
Cohesion: 0.10
Nodes (9): Options, apply_filters(), get_bloginfo(), get_option(), get_post_meta(), sanitize_key(), wp_kses_post(), wp_parse_url() (+1 more)

### Community 2 - "Exporter"
Cohesion: 0.11
Nodes (10): DOMDocument, Exporter, Markdown, esc_url_raw(), get_permalink(), get_post(), get_the_modified_date(), get_the_title() (+2 more)

### Community 3 - "Admin"
Cohesion: 0.09
Nodes (14): __(), Admin, llmf_add_settings_link(), admin_url(), checked(), esc_attr__(), esc_html__(), esc_textarea() (+6 more)

### Community 4 - "Plugin"
Cohesion: 0.13
Nodes (3): Plugin, Rewrites, add_filter()

### Community 5 - "AGENTS.md"
Cohesion: 0.13
Nodes (9): Llms, delete_transient(), get_feed_link(), get_transient(), set_transient(), update_option(), wp_next_scheduled(), WP_Post (+1 more)

### Community 6 - "Response"
Cohesion: 0.24
Nodes (5): llmf_activate(), llmf_requirements_met(), llmf_requirements_notice(), add_action(), is_admin()

### Community 7 - "composer.json"
Cohesion: 0.20
Nodes (9): description, license, name, require, php, scripts, lint, test (+1 more)

### Community 16 - "LLM Friendly"
Cohesion: 0.20
Nodes (9): Developer filters, Development, Features, Installation, License, LLM Friendly, Requirements, Security notes (+1 more)

## Knowledge Gaps
- **15 isolated node(s):** `name`, `description`, `type`, `license`, `php` (+10 more)
  These have ≤1 connection - possible missing edges or undocumented components.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Admin` connect `Admin` to `run.php`, `Plugin`, `Response`?**
  _High betweenness centrality (0.139) - this node is a cross-community bridge._
- **Why does `Options` connect `Options` to `Exporter`, `Plugin`, `AGENTS.md`, `Response`?**
  _High betweenness centrality (0.139) - this node is a cross-community bridge._
- **Why does `Exporter` connect `Exporter` to `Options`, `Plugin`, `AGENTS.md`?**
  _High betweenness centrality (0.110) - this node is a cross-community bridge._
- **Are the 39 inferred relationships involving `__()` (e.g. with `.admin_init()` and `.ajax_preview_llms()`) actually correct?**
  _`__()` has 39 INFERRED edges - model-reasoned connections that need verification._
- **What connects `name`, `description`, `type` to the rest of the system?**
  _15 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `run.php` be split into smaller, more focused modules?**
  _Cohesion score 0.052597402597402594 - nodes in this community are weakly interconnected._
- **Should `Options` be split into smaller, more focused modules?**
  _Cohesion score 0.09725490196078432 - nodes in this community are weakly interconnected._