# Release Checklist (Staging -> Production)

## 1) Pre-flight
- [ ] Plugin activated and no PHP fatal errors
- [ ] Theme active and rendering semantic HTML landmarks
- [ ] Connector status healthy in Replanta AI dashboard

## 2) Functional smoke
- [ ] Create page from prompt (draft)
- [ ] Edit content manually in editor
- [ ] Publish page (must pass publish gates)
- [ ] Unpublish page
- [ ] Re-publish page after manual adjustments

## 3) Security and control
- [ ] REST create endpoint enforces rate limiting
- [ ] REST publish/unpublish endpoints enforce rate limiting
- [ ] Admin create/publish actions enforce rate limiting
- [ ] Operation logs recorded (option raicc_operation_logs and debug log)

## 4) Quality gates
- [ ] Publish blocked if no main landmark
- [ ] Publish blocked if no single h1
- [ ] Publish blocked when image has no alt
- [ ] Warning-only issues reported but non-blocking (short content, title length)

## 5) Performance benchmark against Astra
- [ ] Install Lighthouse CLI: npm i -g lighthouse
- [ ] Run benchmark script:
  - powershell -ExecutionPolicy Bypass -File ./scripts/benchmark-vs-astra.ps1 -ReplantaUrl "https://staging.example.com/replanta-page" -AstraUrl "https://staging.example.com/astra-page"
- [ ] Confirm Replanta is at least parity or better on Performance/LCP/TBT/CLS

## 6) Final deploy gate
- [ ] No critical accessibility blockers
- [ ] No critical SEO blockers
- [ ] Rollback path validated
- [ ] Deployment runbook approved
