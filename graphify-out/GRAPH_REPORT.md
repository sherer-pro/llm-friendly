# Graph Report - llm-friendly-git  (2026-07-10)

## Corpus Check
- 20 files · ~45,129 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 322 nodes · 751 edges · 18 communities (15 shown, 3 thin omitted)
- Extraction: 81% EXTRACTED · 19% INFERRED · 0% AMBIGUOUS · INFERRED: 142 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `f81bc51e`
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

## God Nodes (most connected - your core abstractions)
1. `Admin` - 67 edges
2. `Options` - 53 edges
3. `Exporter` - 40 edges
4. `Markdown` - 27 edges
5. `WP_Post` - 27 edges
6. `Llms` - 24 edges
7. `Plugin` - 19 edges
8. `apply_filters()` - 10 edges
9. `sanitize_key()` - 10 edges
10. `LLM Friendly` - 9 edges

## Surprising Connections (you probably didn't know these)
- `Plugin` --references--> `Admin`  [EXTRACTED]
  inc/Plugin.php → inc/Admin.php
- `Exporter` --references--> `Options`  [EXTRACTED]
  inc/Exporter.php → inc/Options.php
- `Plugin` --references--> `Exporter`  [EXTRACTED]
  inc/Plugin.php → inc/Exporter.php
- `Llms` --references--> `Options`  [EXTRACTED]
  inc/Llms.php → inc/Options.php
- `Plugin` --references--> `Llms`  [EXTRACTED]
  inc/Plugin.php → inc/Llms.php

## Import Cycles
- None detected.

## Communities (18 total, 3 thin omitted)

### Community 1 - "Post and Options Management"
Cohesion: 0.10
Nodes (10): Options, apply_filters(), current_user_can(), get_bloginfo(), get_post_meta(), home_url(), sanitize_key(), wp_kses_post() (+2 more)

### Community 2 - "Admin AJAX and Settings"
Cohesion: 0.05
Nodes (22): RuntimeException, add_option(), assert_contains_text(), assert_not_contains_text(), assert_occurrences(), assert_true(), check_ajax_referer(), get_option() (+14 more)

### Community 3 - "Markdown Conversion Logic"
Cohesion: 0.11
Nodes (11): __(), DOMDocument, Exporter, Markdown, esc_url_raw(), get_permalink(), get_post(), get_the_modified_date() (+3 more)

### Community 4 - "LLM Content Generation"
Cohesion: 0.11
Nodes (12): Llms, delete_transient(), get_feed_link(), get_transient(), is_admin(), set_transient(), update_option(), wp_is_post_autosave() (+4 more)

### Community 5 - "Plugin Lifecycle and Setup"
Cohesion: 0.20
Nodes (9): Developer filters, Development, Features, Installation, License, LLM Friendly, Requirements, Security notes (+1 more)

### Community 6 - "Plugin Core Functionality"
Cohesion: 0.10
Nodes (6): Plugin, Rewrites, add_action(), add_filter(), esc_html__(), esc_url()

### Community 7 - "Composer Package Metadata"
Cohesion: 0.20
Nodes (9): description, license, name, require, php, scripts, lint, test (+1 more)

### Community 16 - "Q: Расскажи мне про секцию на странице настроек Диагностика AI-краулеров"
Cohesion: 0.40
Nodes (4): Answer, Outcome, Q: Расскажи мне про секцию на странице настроек Диагностика AI-краулеров, Source Nodes

## Knowledge Gaps
- **18 isolated node(s):** `Features`, `Requirements`, `Installation`, `Development`, `Usage` (+13 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **3 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Admin` connect `Admin Interface Components` to `Admin AJAX and Settings`, `Plugin Core Functionality`?**
  _High betweenness centrality (0.317) - this node is a cross-community bridge._
- **Why does `Exporter` connect `Markdown Conversion Logic` to `Post and Options Management`, `Admin AJAX and Settings`, `LLM Content Generation`, `Plugin Core Functionality`?**
  _High betweenness centrality (0.147) - this node is a cross-community bridge._
- **Why does `Options` connect `Post and Options Management` to `Admin AJAX and Settings`, `Markdown Conversion Logic`, `LLM Content Generation`, `Plugin Core Functionality`?**
  _High betweenness centrality (0.138) - this node is a cross-community bridge._
- **Are the 21 inferred relationships involving `Markdown` (e.g. with `.blocks_to_markdown()` and `.build_image_markdown()`) actually correct?**
  _`Markdown` has 21 INFERRED edges - model-reasoned connections that need verification._
- **What connects `Features`, `Requirements`, `Installation` to the rest of the system?**
  _18 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Admin Interface Components` be split into smaller, more focused modules?**
  _Cohesion score 0.07643600180913614 - nodes in this community are weakly interconnected._
- **Should `Post and Options Management` be split into smaller, more focused modules?**
  _Cohesion score 0.1003921568627451 - nodes in this community are weakly interconnected._