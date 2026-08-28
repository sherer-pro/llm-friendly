# Graph Report - llm-friendly-git  (2026-08-29)

## Corpus Check
- 19 files · ~49,145 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 338 nodes · 878 edges · 15 communities
- Extraction: 72% EXTRACTED · 28% INFERRED · 0% AMBIGUOUS · INFERRED: 244 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `549c60c8`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- Admin
- Options
- run.php
- Exporter
- LLM Friendly
- Plugin
- lint
- current_user_can

## God Nodes (most connected - your core abstractions)
1. `Admin` - 68 edges
2. `Options` - 53 edges
3. `Exporter` - 40 edges
4. `Markdown` - 28 edges
5. `WP_Post` - 28 edges
6. `Llms` - 24 edges
7. `Plugin` - 24 edges
8. `esc_html__()` - 20 edges
9. `sanitize_key()` - 16 edges
10. `home_url()` - 13 edges

## Surprising Connections (you probably didn't know these)
- `llmf_requirements_notice()` --calls--> `esc_html__()`  [INFERRED]
  llm-friendly.php → tests/run.php
- `llmf_activate()` --calls--> `esc_html__()`  [INFERRED]
  llm-friendly.php → tests/run.php
- `llmf_requirements_met()` --calls--> `get_bloginfo()`  [INFERRED]
  llm-friendly.php → tests/run.php
- `llmf_requirements_notice()` --calls--> `get_bloginfo()`  [INFERRED]
  llm-friendly.php → tests/run.php
- `llmf_activate()` --calls--> `add_action()`  [INFERRED]
  llm-friendly.php → tests/run.php

## Import Cycles
- None detected.

## Communities (15 total, 0 thin omitted)

### Community 0 - "Admin"
Cohesion: 0.08
Nodes (13): Admin, llmf_add_settings_link(), admin_url(), checked(), esc_attr__(), esc_html__(), esc_textarea(), esc_url() (+5 more)

### Community 1 - "Options"
Cohesion: 0.07
Nodes (18): Llms, Options, apply_filters(), delete_transient(), get_option(), get_post(), get_post_meta(), get_post_type_object() (+10 more)

### Community 2 - "run.php"
Cohesion: 0.06
Nodes (26): llmf_activate(), llmf_requirements_met(), llmf_requirements_notice(), RuntimeException, add_action(), add_option(), add_settings_field(), add_settings_section() (+18 more)

### Community 3 - "Exporter"
Cohesion: 0.11
Nodes (8): DOMDocument, Exporter, Markdown, get_permalink(), get_the_modified_date(), get_the_title(), wp_json_encode(), wp_strip_all_tags()

### Community 5 - "LLM Friendly"
Cohesion: 0.18
Nodes (10): Developer filters, Development, Discovery, indexing, and usage policy, Features, Installation, License, LLM Friendly, Requirements (+2 more)

### Community 6 - "Plugin"
Cohesion: 0.10
Nodes (8): Plugin, Rewrites, add_filter(), is_404(), is_attachment(), is_feed(), is_preview(), is_singular()

### Community 7 - "lint"
Cohesion: 0.10
Nodes (20): description, license, name, require, php, scripts, lint, test (+12 more)

### Community 15 - "current_user_can"
Cohesion: 0.11
Nodes (11): Response, check_ajax_referer(), current_user_can(), sanitize_text_field(), wp_is_post_autosave(), wp_is_post_revision(), wp_kses_post(), wp_send_json_error() (+3 more)

## Knowledge Gaps
- **25 isolated node(s):** `name`, `description`, `type`, `license`, `php` (+20 more)
  These have ≤1 connection - possible missing edges or undocumented components.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Admin` connect `Admin` to `run.php`, `Exporter`, `Plugin`, `current_user_can`?**
  _High betweenness centrality (0.214) - this node is a cross-community bridge._
- **Why does `Exporter` connect `Exporter` to `Options`, `run.php`, `Plugin`?**
  _High betweenness centrality (0.138) - this node is a cross-community bridge._
- **Why does `Options` connect `Options` to `Exporter`, `Plugin`, `current_user_can`?**
  _High betweenness centrality (0.119) - this node is a cross-community bridge._
- **Are the 22 inferred relationships involving `Markdown` (e.g. with `.blocks_to_markdown()` and `.build_image_markdown()`) actually correct?**
  _`Markdown` has 22 INFERRED edges - model-reasoned connections that need verification._
- **What connects `name`, `description`, `type` to the rest of the system?**
  _25 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Admin` be split into smaller, more focused modules?**
  _Cohesion score 0.08074534161490683 - nodes in this community are weakly interconnected._
- **Should `Options` be split into smaller, more focused modules?**
  _Cohesion score 0.06766917293233082 - nodes in this community are weakly interconnected._