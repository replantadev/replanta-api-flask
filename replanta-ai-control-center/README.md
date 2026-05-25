# Replanta AI Control Center (MVP Slice)

Minimal plugin scaffold for:
- Connector-first AI service
- Connector status endpoint
- Create native page from prompt endpoint
- Blueprint validation and semantic HTML rendering
- Replanta AI admin dashboard for create/publish/unpublish
- REST/Admin rate limiting for sensitive operations
- Publish-time validation gates (a11y + semantic + seo baseline)
- Structured operation logging
- AI-assisted theme layout editing (header/footer rows, columns, modules)

## Included Endpoints

- GET /wp-json/replanta-ai/v1/connectors/status
  - Permission: manage_options

- POST /wp-json/replanta-ai/v1/pages/create-from-prompt
  - Permission: edit_pages
  - Rate limit: 12 requests per minute per user
  - JSON body:
    - prompt (required)
    - title (optional)
    - slug (optional)
    - lang (optional, default es)

- POST /wp-json/replanta-ai/v1/pages/{id}/publish
  - Permission: edit_pages
  - Rate limit: 30 requests per minute per user
  - Blocked if publish gates fail

- POST /wp-json/replanta-ai/v1/pages/{id}/unpublish
  - Permission: edit_pages
  - Rate limit: 30 requests per minute per user

- GET /wp-json/replanta-ai/v1/theme/layout
  - Permission: customize

- POST /wp-json/replanta-ai/v1/theme/layout/generate-from-prompt
  - Permission: customize
  - Body: { "prompt": "..." }

- POST /wp-json/replanta-ai/v1/theme/layout/apply
  - Permission: customize
  - Body: { "layout": { ... } }

## Connector-First Contract

The plugin executes AI through this filter first:
- raicc_connector_execute

Expected filter signature:
- input: null, activeConnector, operation, payload, context
- output: array with:
  - ok (bool)
  - blueprint_json (array)
  - warnings (array, optional)
  - notes (string, optional)
  - model_id (string, optional)
  - token_usage (array, optional)

If no connector returns a valid array, fallback-local blueprint generation is used for create_page.

## Stored Meta on Created Pages

- _raicc_blueprint_json
- _raicc_mode = ai
- _raicc_prompt_last
- _raicc_change_origin = ai

## Publish Gates

Before changing a page to publish, the plugin validates:
- main landmark present
- exactly one h1
- all img tags include alt

Warnings (non-blocking) include:
- short content
- title length outside recommended SEO range

If blockers exist, publish is rejected with a detailed gate payload.

## Operation Logs

Structured logs are saved in:
- option: raicc_operation_logs (bounded list)
- debug log line prefix: RAICC_LOG

Logged events include connector execution, rate limit hits, create status, and publish/unpublish outcomes.

## Benchmark Workflow vs Astra

- Script: ./scripts/benchmark-vs-astra.ps1
- Checklist: ./RELEASE-CHECKLIST.md

Example:
- powershell -ExecutionPolicy Bypass -File ./scripts/benchmark-vs-astra.ps1 -ReplantaUrl "https://staging.example.com/replanta-page" -AstraUrl "https://staging.example.com/astra-page"

## Quick Test (example)

POST /wp-json/replanta-ai/v1/pages/create-from-prompt
Body:
{
  "prompt": "Crear landing de alojamiento web ecologico con enfoque conversion",
  "title": "Alojamiento Web Ecologico",
  "slug": "alojamiento-web-ecologico",
  "lang": "es"
}

Expected:
- Draft page created
- Semantic sections rendered into post_content
- Blueprint saved in post meta

## Admin Dashboard

After activation, use:
- WP Admin > Replanta AI

Capabilities included:
- Create draft from prompt
- View connector mode/health
- List recent pages
- Publish or unpublish in one click
- Generate and apply header/footer layout from prompt
