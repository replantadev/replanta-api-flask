# Replanta AI Control Center (MVP Slice)

Minimal plugin scaffold for:
- Connector-first AI service
- Connector status endpoint
- Create native page from prompt endpoint
- Blueprint validation and semantic HTML rendering
- Replanta AI admin dashboard for create/publish/unpublish

## Included Endpoints

- GET /wp-json/replanta-ai/v1/connectors/status
  - Permission: manage_options

- POST /wp-json/replanta-ai/v1/pages/create-from-prompt
  - Permission: edit_pages
  - JSON body:
    - prompt (required)
    - title (optional)
    - slug (optional)
    - lang (optional, default es)

- POST /wp-json/replanta-ai/v1/pages/{id}/publish
  - Permission: edit_pages

- POST /wp-json/replanta-ai/v1/pages/{id}/unpublish
  - Permission: edit_pages

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
