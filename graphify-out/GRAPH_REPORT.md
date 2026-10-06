# Graph Report - llm-friendly-git  (2026-10-06)

## Corpus Check
- 28 files · ~75,245 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 450 nodes · 1299 edges · 20 communities (19 shown, 1 thin omitted)
- Extraction: 68% EXTRACTED · 32% INFERRED · 0% AMBIGUOUS · INFERRED: 422 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `df7d5d1e`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- Admin
- Options
- Catalog
- Exporter
- WP_Post
- LLM Friendly
- run.php
- lint
- current_user_can
- add_action
- legacy-markdown.md

## God Nodes (most connected - your core abstractions)
1. `Options` - 82 edges
2. `Admin` - 79 edges
3. `Exporter` - 47 edges
4. `WP_Post` - 40 edges
5. `Markdown` - 37 edges
6. `Content` - 30 edges
7. `Llms` - 30 edges
8. `Catalog` - 28 edges
9. `Plugin` - 26 edges
10. `esc_html__()` - 24 edges

## Surprising Connections (you probably didn't know these)
- `llmf_requirements_met()` --calls--> `get_bloginfo()`  [INFERRED]
  llm-friendly.php → tests/run.php
- `llmf_requirements_notice()` --calls--> `esc_html__()`  [INFERRED]
  llm-friendly.php → tests/run.php
- `llmf_requirements_notice()` --calls--> `get_bloginfo()`  [INFERRED]
  llm-friendly.php → tests/run.php
- `llmf_activate()` --calls--> `esc_html__()`  [INFERRED]
  llm-friendly.php → tests/run.php
- `llmf_activate()` --calls--> `add_action()`  [INFERRED]
  llm-friendly.php → tests/run.php

## Import Cycles
- None detected.

## Communities (20 total, 1 thin omitted)

### Community 0 - "Admin"
Cohesion: 0.07
Nodes (17): Admin, llmf_add_settings_link(), trailingslashit(), admin_url(), checked(), esc_attr__(), esc_html__(), esc_textarea() (+9 more)

### Community 1 - "Options"
Cohesion: 0.10
Nodes (6): Options, apply_filters(), get_bloginfo(), home_url(), sanitize_key(), wp_kses_post()

### Community 2 - "Catalog"
Cohesion: 0.08
Nodes (21): Catalog, Diagnostics, delete_option(), get_object_taxonomies(), get_taxonomy(), get_term(), get_the_terms(), is_wp_error() (+13 more)

### Community 3 - "Exporter"
Cohesion: 0.13
Nodes (7): DOMDocument, Exporter, Markdown, esc_url_raw(), render_block(), wp_parse_url(), wp_strip_all_tags()

### Community 4 - "WP_Post"
Cohesion: 0.07
Nodes (22): Content, Llms, delete_post_meta(), update_post_meta(), delete_transient(), get_current_user_id(), get_feed_link(), get_permalink() (+14 more)

### Community 5 - "LLM Friendly"
Cohesion: 0.13
Nodes (14): Developer filters, Development, Discovery, indexing, and usage policy, Enhanced content and diagnostics, Existing controls, Features, Installation, License (+6 more)

### Community 6 - "run.php"
Cohesion: 0.06
Nodes (24): Plugin, Rewrites, RuntimeException, add_filter(), add_settings_field(), add_settings_section(), assert_contains_text(), assert_not_contains_text() (+16 more)

### Community 7 - "lint"
Cohesion: 0.07
Nodes (27): description, license, name, require, php, scripts, lint, test (+19 more)

### Community 15 - "current_user_can"
Cohesion: 0.11
Nodes (8): Response, check_ajax_referer(), current_user_can(), sanitize_text_field(), wp_send_json_error(), wp_send_json_success(), wp_unslash(), wp_verify_nonce()

### Community 16 - "add_action"
Cohesion: 0.20
Nodes (5): llmf_activate(), llmf_requirements_met(), llmf_requirements_notice(), add_action(), is_admin()

## Knowledge Gaps
- **37 isolated node(s):** `name`, `description`, `type`, `license`, `php` (+32 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **1 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Options` connect `Options` to `Admin`, `Catalog`, `Exporter`, `WP_Post`, `run.php`, `current_user_can`, `add_action`?**
  _High betweenness centrality (0.173) - this node is a cross-community bridge._
- **Why does `Admin` connect `Admin` to `Options`, `WP_Post`, `run.php`, `current_user_can`, `add_action`?**
  _High betweenness centrality (0.145) - this node is a cross-community bridge._
- **Why does `Exporter` connect `Exporter` to `Admin`, `Options`, `Catalog`, `WP_Post`, `run.php`, `add_action`?**
  _High betweenness centrality (0.083) - this node is a cross-community bridge._
- **Are the 31 inferred relationships involving `Markdown` (e.g. with `.build_document()` and `.line()`) actually correct?**
  _`Markdown` has 31 INFERRED edges - model-reasoned connections that need verification._
- **What connects `name`, `description`, `type` to the rest of the system?**
  _37 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Admin` be split into smaller, more focused modules?**
  _Cohesion score 0.07059607059607059 - nodes in this community are weakly interconnected._
- **Should `Options` be split into smaller, more focused modules?**
  _Cohesion score 0.10030165912518854 - nodes in this community are weakly interconnected._