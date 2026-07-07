# Graph Report - D:\DevProjects\wordpress\llm-friendly-all\llm-friendly-git  (2026-07-07)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 262 nodes · 666 edges · 16 communities (15 shown, 1 thin omitted)
- Extraction: 68% EXTRACTED · 32% INFERRED · 0% AMBIGUOUS · INFERRED: 212 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `e53bafad`
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

## Communities (16 total, 1 thin omitted)

### Community 0 - "run.php"
Cohesion: 0.07
Nodes (30): Llms, add_option(), assert_contains_text(), assert_not_contains_text(), assert_true(), delete_transient(), esc_url_raw(), get_feed_link() (+22 more)

### Community 1 - "Options"
Cohesion: 0.11
Nodes (7): Options, apply_filters(), get_post_meta(), home_url(), post_password_required(), sanitize_key(), wp_kses_post()

### Community 2 - "Exporter"
Cohesion: 0.13
Nodes (6): DOMDocument, Exporter, Markdown, get_permalink(), wp_json_encode(), wp_strip_all_tags()

### Community 3 - "Admin"
Cohesion: 0.11
Nodes (9): __(), Admin, checked(), current_user_can(), esc_attr(), esc_html__(), esc_textarea(), sanitize_text_field() (+1 more)

### Community 4 - "Plugin"
Cohesion: 0.13
Nodes (3): Plugin, Rewrites, add_filter()

### Community 5 - "AGENTS.md"
Cohesion: 0.18
Nodes (10): CI/CD, Graphify, Зависимости, Команды, Локализация, Навигация, Окружение, Подводные камни (+2 more)

### Community 6 - "llm-friendly.php"
Cohesion: 0.22
Nodes (6): llmf_activate(), llmf_add_settings_link(), llmf_requirements_met(), llmf_requirements_notice(), add_action(), get_bloginfo()

### Community 7 - "composer.json"
Cohesion: 0.20
Nodes (9): description, license, name, require, php, scripts, lint, test (+1 more)

## Knowledge Gaps
- **17 isolated node(s):** `Graphify`, `Навигация`, `Команды`, `Стиль`, `Окружение` (+12 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **1 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Options` connect `Options` to `run.php`, `Exporter`, `Plugin`, `llm-friendly.php`?**
  _High betweenness centrality (0.177) - this node is a cross-community bridge._
- **Why does `Exporter` connect `Exporter` to `run.php`, `Options`, `Plugin`?**
  _High betweenness centrality (0.168) - this node is a cross-community bridge._
- **Why does `Plugin` connect `Plugin` to `run.php`, `Options`, `Exporter`, `Admin`?**
  _High betweenness centrality (0.109) - this node is a cross-community bridge._
- **Are the 20 inferred relationships involving `Markdown` (e.g. with `.blocks_to_markdown()` and `.build_image_markdown()`) actually correct?**
  _`Markdown` has 20 INFERRED edges - model-reasoned connections that need verification._
- **What connects `Graphify`, `Навигация`, `Команды` to the rest of the system?**
  _17 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `run.php` be split into smaller, more focused modules?**
  _Cohesion score 0.06654567453115548 - nodes in this community are weakly interconnected._
- **Should `Options` be split into smaller, more focused modules?**
  _Cohesion score 0.10726950354609929 - nodes in this community are weakly interconnected._