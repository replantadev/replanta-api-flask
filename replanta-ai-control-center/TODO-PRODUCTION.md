# Replanta AI + Replanta Lite - Production TODO

## Completed
- [x] Ultra-light semantic classic theme scaffold
- [x] Header/Footer builder with rows and columns
- [x] Per-column module selection
- [x] Global palette and custom color controls
- [x] Header behavior controls (normal/transparent + fixed)
- [x] Per-page overrides (header mode, fixed, palette)
- [x] Connector-first AI service with fallback
- [x] Admin dashboard to create prompt-based drafts
- [x] Publish/Unpublish actions (admin and REST)
- [x] Basic frontend bloat cleanup

## In Progress
- [x] Admin UI polish with reusable SVG icon set and visual hierarchy
- [x] Release checklist and staging verification workflow (base implemented)

## Next Hardening (high priority)
- [x] REST rate limiting for sensitive operations
- [x] Structured operation logging (connector, latency, result)
- [x] Validation gates before publish (a11y + semantic + seo)
- [x] Regression benchmark script against Astra baseline (script scaffold)

## Final Release Gate
- [ ] Performance targets met on staging baseline pages
- [ ] Accessibility checks with no critical issues
- [ ] Smoke test: create -> edit -> publish -> unpublish -> rollback
- [ ] Deployment runbook validated
