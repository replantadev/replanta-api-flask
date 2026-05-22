# Replanta Lite AI Blueprint

## 1) Product Goal
Build a WordPress solution that is:
- Extremely lightweight on frontend
- Fast and accessible by default
- Operable by non-technical users from a single dashboard
- AI-assisted for content creation/editing with strict safety rails
- Manual-edit friendly when users need direct control

Core principle:
- Theme renders
- Plugin orchestrates AI and operations

## 2) Architecture Split

### Theme: replanta-lite-theme
Responsibilities:
- Render semantic HTML
- Minimal CSS/JS runtime
- Template parts, navigation, accessibility primitives
- Zero business logic for AI workflows

Rules:
- No external font requests by default
- No jQuery
- One global CSS bundle + optional per-template CSS
- Optional tiny progressive enhancement JS

### Plugin: replanta-ai-control-center
Responsibilities:
- Dashboard UI (admin app)
- Prompt-to-content pipeline
- Page lifecycle operations (publish/unpublish/schedule/revert)
- Menu assignment and page-to-menu mapping
- History, revisions, rollback, audit logs
- Guardrails (SEO, accessibility, semantic checks)
- AI connector orchestration (WP 7 native connectors first)

## 3) Content Model
Use native page post type to avoid dual-state complexity.

Custom post meta:
- _raicc_mode: ai | manual
- _raicc_blueprint_json: structured section model
- _raicc_prompt_last
- _raicc_quality_score_json
- _raicc_lock_layout: 1|0
- _raicc_change_origin: ai | manual | import

Custom taxonomy (optional):
- raicc_workflow_state: draft, ready_review, scheduled, published, archived

History table (custom DB):
- wp_raicc_history
  - id
  - post_id
  - actor_id
  - actor_type (user|ai|system)
  - operation (generate|rewrite|publish|unpublish|rollback|menu_assign)
  - before_hash
  - after_hash
  - patch_json
  - created_at

## 4) Semantic Blueprint Schema
The AI generates and edits a constrained schema, not arbitrary HTML.

JSON shape:
- version: string
- lang: string
- page:
  - title
  - slug
  - description
  - canonical
- sections: array
  - id
  - type: hero|content|list|faq|cta|stats|timeline
  - heading
  - body_markdown
  - items (type-specific)
  - aria_label
- seo:
  - meta_title
  - meta_description
  - og_title
  - og_description
- a11y:
  - skip_link_label
  - landmarks_ok

Rendering path:
- Blueprint JSON -> trusted renderer -> semantic HTML blocks
- Sanitization at render and save boundaries

## 5) Dashboard UX (single control center)
Main view cards:
- Pages (status, language, menu assignment, last edit source)
- Menus (assigned pages and hierarchy)
- Quality (perf/accessibility/seo quick indicators)
- AI Queue (running, failed, completed jobs)

Per-page panel tabs:
- Overview
- Prompt Studio
- Text Editor
- Structure
- Menus
- Validate
- History

Fast actions:
- Publish
- Unpublish
- Save draft
- Rewrite selected section
- Rewrite only text
- Revert to revision
- Assign menu

## 6) Modes

### AI Mode
- Default for generation and guided edits
- AI can only modify allowed fields by policy
- Layout locked unless user enables structure edits

### Manual Mode
- Rich text/markdown editing for section content
- Optional structure drag/drop
- AI suggestions can be inserted, never forced

Mode switch rule:
- Changing mode stores a history checkpoint automatically

## 7) Prompt Contracts (strict)

System contract:
- Output JSON only
- Must validate against blueprint schema
- Must preserve ids for unchanged sections
- Must respect operation scope

Supported operations:
- create_page
- rewrite_text_only
- rewrite_section
- seo_optimize
- simplify_readability
- localize_language

Example request envelope:
- operation
- scope
- constraints
- brand_voice
- forbidden_terms
- max_length

Example response envelope:
- ok
- blueprint_json
- notes
- warnings

Connector execution policy:
- Route AI operations through WP 7 connectors first when available
- Keep provider-specific prompt wrappers outside business logic
- Normalize connector responses to a stable internal shape before validation

## 8) REST API Design
Namespace:
- replanta-ai/v1

Endpoints:
- GET /pages
- GET /pages/{id}
- POST /pages/create-from-prompt
- POST /pages/{id}/rewrite
- POST /pages/{id}/publish
- POST /pages/{id}/unpublish
- POST /pages/{id}/save-draft
- POST /pages/{id}/assign-menu
- POST /pages/{id}/validate
- GET /pages/{id}/history
- POST /pages/{id}/rollback
- GET /menus
- POST /menus/{id}/reorder

Connector endpoints (plugin-level):
- GET /connectors/status
- POST /connectors/test
- GET /connectors/capabilities

Permissions:
- manage_options for global settings
- edit_pages for content operations

Connector strategy:
- Preferred path: WP 7 connector selected in plugin settings
- Fallback path: direct provider adapter only if connector is unavailable
- Offline-safe mode: queue operation and return actionable error state

## 9) Validation Pipeline
Run before publish:
- semantic: heading order, landmark presence, unique h1
- a11y: alt checks, aria checks, contrast token policy
- seo: title/meta length, canonical, internal links
- quality: blocked words, excessive sentence length
- perf: image dimensions, lazy flags, no render-blocking custom CSS bloat

Publish gating policy:
- hard_fail blocks publish
- warn allows publish with confirmation

## 10) Performance Budget
Hard targets (mobile):
- HTML TTFB budget handled at host layer
- CSS <= 25KB gzip initial
- JS <= 35KB gzip initial
- CLS <= 0.03
- LCP <= 1.8s on representative pages

Theme-level rules:
- critical CSS inline only for shell
- defer all non-critical JS
- no blocking third-party scripts by default
- responsive images and explicit dimensions

## 11) Accessibility Baseline
- WCAG AA baseline
- Keyboard navigable dashboard and frontend
- Focus visibility always on
- Skip link mandatory
- Form controls always labeled
- Semantic landmarks in every template

## 12) MVP Build Plan (4 weeks)

Week 1:
- Create plugin skeleton and admin shell
- Pages list + status actions
- Basic prompt create endpoint

Week 2:
- Blueprint renderer + section editor
- Publish/unpublish/save draft
- Menu assignment UI

Week 3:
- Validation pipeline
- History + rollback
- Text-only rewrite operation

Week 4:
- Perf hardening + accessibility QA
- Editorial UX polish
- Migration script from existing setup

## 13) Migration Strategy From Current Project
- Keep existing content in native pages
- Add one-time tool to map old metadata into blueprint JSON
- Preserve current permalinks
- Freeze old promote/adopt endpoints behind feature flag
- Gradually switch dashboard users to new control center

## 14) Non-Goals (to prevent complexity creep)
- No visual page builder clone in v1
- No free-form AI HTML injection
- No multi-provider abstraction layer in MVP (single provider first)
- No custom frontend framework dependency

Note for WP 7:
- The MVP still uses one active connector at a time even if WP exposes multiple connectors.
- Multi-connector routing can be added later once telemetry and failure policies are stable.

## 15) First Implementation Slice (tomorrow-ready)
1. Plugin bootstrap + admin page
2. GET /pages and POST /pages/{id}/publish|unpublish
3. Prompt Studio with create_page operation
4. Blueprint JSON stored in _raicc_blueprint_json
5. Renderer for hero + content + cta section types
6. Connector service wired to WP 7 connector API with fallback adapter

This slice already enables:
- AI-assisted page drafting
- Publish/unpublish from one dashboard
- Manual text adjustments without breaking structure
- Semantic HTML output with predictable performance

## 16) WP 7 Connector Integration Detail

Internal service:
- AIConnectorService (single entry point for all AI operations)

Interface:
- execute(operation, payload, context): ConnectorResult
- healthcheck(): ConnectorHealth
- capabilities(): ConnectorCapabilities

Resolution order:
1. Active WP 7 connector from settings
2. Validate connector supports requested operation
3. Execute with timeout + retries policy
4. Normalize response
5. Validate against blueprint schema
6. Persist history row with connector metadata

Telemetry fields saved per AI operation:
- connector_id
- model_id
- latency_ms
- token_usage (if available)
- retry_count
- validation_result

Failure policy:
- Connector unavailable: return queued status and retry option
- Connector timeout: retry once, then surface concise operator message
- Schema invalid output: reject and request corrective retry with strict schema reminder

Security notes:
- Store connector configuration in WP options with capability checks
- Never expose secret values in REST responses or logs
- Log only hashed request fingerprints, not full sensitive prompt payloads
