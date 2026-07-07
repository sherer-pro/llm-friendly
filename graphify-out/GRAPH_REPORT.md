# Graph Report - llm-friendly-git  (2026-07-07)

## Corpus Check
- 20 files · ~43,520 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 320 nodes · 868 edges · 17 communities (16 shown, 1 thin omitted)
- Extraction: 70% EXTRACTED · 30% INFERRED · 0% AMBIGUOUS · INFERRED: 259 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `5c5f6825`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- [[_COMMUNITY_run.php|run.php]]
- [[_COMMUNITY_Options|Options]]
- [[_COMMUNITY_Exporter|Exporter]]
- [[_COMMUNITY_Admin|Admin]]
- [[_COMMUNITY_Plugin|Plugin]]
- [[_COMMUNITY_AGENTS|AGENTS.md]]
- [[_COMMUNITY_composer.json|composer.json]]
- [[_COMMUNITY_LLM Friendly|LLM Friendly]]
- [[_COMMUNITY_Response|Response]]
- [[_COMMUNITY_Q explain the architecture of this project|Q: explain the architecture of this project]]

## God Nodes (most connected - your core abstractions)
1. `Admin` - 65 edges
2. `Options` - 53 edges
3. `__()` - 39 edges
4. `Exporter` - 39 edges
5. `WP_Post` - 28 edges
6. `Markdown` - 24 edges
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

## Communities (17 total, 1 thin omitted)

### Community 0 - "run.php"
Cohesion: 0.06
Nodes (30): RuntimeException, add_option(), add_settings_field(), add_settings_section(), assert_contains_text(), assert_not_contains_text(), assert_occurrences(), assert_true() (+22 more)

### Community 1 - "Options"
Cohesion: 0.10
Nodes (11): Options, apply_filters(), get_bloginfo(), get_option(), get_post_meta(), get_the_author_meta(), home_url(), sanitize_key() (+3 more)

### Community 2 - "Exporter"
Cohesion: 0.11
Nodes (10): DOMDocument, Exporter, Markdown, esc_url_raw(), get_permalink(), get_post(), get_the_modified_date(), get_the_title() (+2 more)

### Community 3 - "Admin"
Cohesion: 0.10
Nodes (11): __(), Admin, llmf_add_settings_link(), admin_url(), checked(), esc_attr__(), esc_html__(), esc_textarea() (+3 more)

### Community 4 - "Plugin"
Cohesion: 0.08
Nodes (8): Plugin, Rewrites, llmf_activate(), llmf_requirements_met(), llmf_requirements_notice(), add_action(), add_filter(), is_admin()

### Community 5 - "AGENTS.md"
Cohesion: 0.14
Nodes (9): Llms, delete_transient(), get_feed_link(), get_transient(), set_transient(), update_option(), wp_next_scheduled(), WP_Post (+1 more)

### Community 7 - "composer.json"
Cohesion: 0.20
Nodes (9): description, license, name, require, php, scripts, lint, test (+1 more)

### Community 16 - "LLM Friendly"
Cohesion: 0.20
Nodes (9): Developer filters, Development, Features, Installation, License, LLM Friendly, Requirements, Security notes (+1 more)

### Community 18 - "Q: explain the architecture of this project"
Cohesion: 0.40
Nodes (4): Answer, Outcome, Q: explain the architecture of this project, Source Nodes

## Knowledge Gaps
- **18 isolated node(s):** `name`, `description`, `type`, `license`, `php` (+13 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **1 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Options` connect `Options` to `Exporter`, `Plugin`, `AGENTS.md`?**
  _High betweenness centrality (0.137) - this node is a cross-community bridge._
- **Why does `Admin` connect `Admin` to `run.php`, `Plugin`?**
  _High betweenness centrality (0.134) - this node is a cross-community bridge._
- **Why does `Exporter` connect `Exporter` to `Options`, `Plugin`, `AGENTS.md`?**
  _High betweenness centrality (0.109) - this node is a cross-community bridge._
- **Are the 38 inferred relationships involving `__()` (e.g. with `.admin_init()` and `.ajax_preview_llms()`) actually correct?**
  _`__()` has 38 INFERRED edges - model-reasoned connections that need verification._
- **What connects `name`, `description`, `type` to the rest of the system?**
  _18 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `run.php` be split into smaller, more focused modules?**
  _Cohesion score 0.058673469387755105 - nodes in this community are weakly interconnected._
- **Should `Options` be split into smaller, more focused modules?**
  _Cohesion score 0.09506531204644413 - nodes in this community are weakly interconnected._