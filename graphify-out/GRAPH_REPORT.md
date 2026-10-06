# Graph Report - llm-friendly-git  (2026-10-06)

## Corpus Check
- 19 files · ~50,679 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 344 nodes · 901 edges · 17 communities
- Extraction: 72% EXTRACTED · 28% INFERRED · 0% AMBIGUOUS · INFERRED: 255 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `5470de30`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- Admin
- Options
- run.php
- Exporter
- Llms
- LLM Friendly
- Plugin
- lint
- current_user_can
- llm-friendly.php

## God Nodes (most connected - your core abstractions)
1. `Admin` - 68 edges
2. `Options` - 54 edges
3. `Exporter` - 41 edges
4. `WP_Post` - 29 edges
5. `Markdown` - 28 edges
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

## Communities (17 total, 0 thin omitted)

### Community 0 - "Admin"
Cohesion: 0.08
Nodes (12): Admin, llmf_add_settings_link(), admin_url(), checked(), esc_attr__(), esc_html__(), esc_textarea(), esc_url() (+4 more)

### Community 1 - "Options"
Cohesion: 0.09
Nodes (10): Options, apply_filters(), get_option(), get_post_meta(), sanitize_key(), update_option(), wp_kses_post(), wp_parse_url() (+2 more)

### Community 2 - "run.php"
Cohesion: 0.08
Nodes (21): RuntimeException, add_option(), add_settings_field(), add_settings_section(), assert_contains_text(), assert_not_contains_text(), assert_occurrences(), assert_true() (+13 more)

### Community 3 - "Exporter"
Cohesion: 0.12
Nodes (8): DOMDocument, Exporter, Markdown, get_current_user_id(), render_block(), wp_json_encode(), wp_set_current_user(), wp_strip_all_tags()

### Community 4 - "Llms"
Cohesion: 0.13
Nodes (12): Llms, delete_transient(), get_feed_link(), get_permalink(), get_post(), get_the_date(), get_the_modified_date(), get_the_title() (+4 more)

### Community 5 - "LLM Friendly"
Cohesion: 0.18
Nodes (10): Developer filters, Development, Discovery, indexing, and usage policy, Features, Installation, License, LLM Friendly, Requirements (+2 more)

### Community 6 - "Plugin"
Cohesion: 0.10
Nodes (9): Plugin, Rewrites, add_filter(), is_404(), is_attachment(), is_feed(), is_preview(), is_singular() (+1 more)

### Community 7 - "lint"
Cohesion: 0.10
Nodes (20): description, license, name, require, php, scripts, lint, test (+12 more)

### Community 15 - "current_user_can"
Cohesion: 0.13
Nodes (10): Response, check_ajax_referer(), current_user_can(), sanitize_text_field(), wp_is_post_autosave(), wp_is_post_revision(), wp_send_json_error(), wp_send_json_success() (+2 more)

### Community 16 - "llm-friendly.php"
Cohesion: 0.24
Nodes (6): llmf_activate(), llmf_requirements_met(), llmf_requirements_notice(), add_action(), get_bloginfo(), is_admin()

## Knowledge Gaps
- **25 isolated node(s):** `name`, `description`, `type`, `license`, `php` (+20 more)
  These have ≤1 connection - possible missing edges or undocumented components.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Admin` connect `Admin` to `llm-friendly.php`, `run.php`, `Plugin`, `current_user_can`?**
  _High betweenness centrality (0.211) - this node is a cross-community bridge._
- **Why does `Exporter` connect `Exporter` to `Options`, `run.php`, `Llms`, `Plugin`?**
  _High betweenness centrality (0.133) - this node is a cross-community bridge._
- **Why does `Options` connect `Options` to `llm-friendly.php`, `Exporter`, `Llms`, `Plugin`?**
  _High betweenness centrality (0.119) - this node is a cross-community bridge._
- **Are the 22 inferred relationships involving `Markdown` (e.g. with `.blocks_to_markdown()` and `.build_image_markdown()`) actually correct?**
  _`Markdown` has 22 INFERRED edges - model-reasoned connections that need verification._
- **What connects `name`, `description`, `type` to the rest of the system?**
  _25 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Admin` be split into smaller, more focused modules?**
  _Cohesion score 0.0800804828973843 - nodes in this community are weakly interconnected._
- **Should `Options` be split into smaller, more focused modules?**
  _Cohesion score 0.08897243107769423 - nodes in this community are weakly interconnected._