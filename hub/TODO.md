# Replanta Hub — TODO de implementación

## Leyenda
`[x]` hecho · `[ ]` pendiente · `[~]` parcial

---

## Sprint 5 — Fixes activos (sesion actual)

| # | Plugin | Estado | Descripcion |
|---|--------|:------:|-------------|
| S5.1 | replanta-care | `[x]` | Dark mode en portal page (`body.toplevel_page_replanta-care-portal`) — admin.css |
| S5.2 | replanta-hub | `[x]` | Checkbox eCommerce en ficha de sitio — admin-sites.php + class-site-manager.php |
| S5.3 | replanta-hub | `[x]` | `rphub_sync_all` AJAX handler registrado — class-site-manager.php |
| S5.4 | replanta-hub | `[x]` | `ajax_sync_all()` — itera sitios activos, llama `sync_site_data()` por sitio |
| S5.5 | replanta-hub | `[x]` | Panel "Aplicar features del plan" en Operaciones — boton + modal + AJAX |
| S5.6 | replanta-hub | `[x]` | B2 fields en Ajustes (backup tab): key_id, app_key, bucket_id, bucket_name + test |
| S5.7 | replanta-care | `[x]` | Rebrand "Backblaze B2" → "Backup externo Replanta" en class-client-portal.php |
| S5.8 | replanta-hub | `[x]` | `save_addon_meta()` + `push_addon_config_to_care()` — push /config al guardar addon |
| S5.9 | replanta-hub | `[x]` | `ajax_get_plan_status()` + `ajax_apply_plan_features()` — envio config plan a Care |

### Pendiente de test manual

- [ ] Verificar que la pagina `admin.php?page=replanta-care` ya muestra fondo oscuro
- [ ] Verificar checkbox eCommerce persiste al guardar/editar sitio
- [ ] Verificar que Sincronizar todos ya no devuelve 400
- [ ] Verificar modal "Configurar plan" abre y muestra features correctamente
- [ ] Verificar que campos B2 se guardan y el test de conexion funciona
- [ ] Verificar que el portal del cliente ya no menciona "Backblaze"

---

## Hub — eCommerce Addon (docs pendientes de implementar en Hub)

Documentacion generada en `docs/hub-addon-ecommerce.php` — NO cargado en produccion.

| # | Archivo | Cambio |
|---|---------|--------|
| A.1 | `replanta-hub.php` o migration | Schema: `ALTER TABLE rphub_site_meta` — ya usa EAV, sin cambios de schema |
| A.2 | `inc/admin-sites.php` | `[x]` Fieldset "Addons contratados" con checkbox eCommerce + threshold + alert_email |
| A.3 | `inc/class-site-manager.php` | `[x]` `save_addon_meta()`, `push_addon_config_to_care()` |
| A.4 | `replanta-hub.php` | `[ ]` Webhook `wp_ajax_rphub_care_alert` — recibe eventos checkout_failure, revenue_anomaly desde Care y los muestra en el panel |
| A.5 | `inc/admin-operations.php` | `[ ]` Columna "Alertas eCommerce" en la tabla de operaciones cuando hay eventos pendientes |

---

## Hub — Cloudflare Onboarding via DR Bridge

El flujo completo de onboarding CF ya existe en dominios-reseller. Hub debe poder iniciarlo y monitorizarlo.

| # | Archivo | Cambio |
|---|---------|--------|
| CF.1 | `inc/class-dr-bridge.php` | `[ ]` `trigger_cf_onboarding(string $domain): array` — llama `RPHUB_DR_Cloudflare_Service::start_onboarding($domain)` si disponible |
| CF.2 | `inc/class-dr-bridge.php` | `[ ]` `get_cf_onboarding_state(string $domain): ?array` — lee `wp_dominios_reseller_cf_onboarding` |
| CF.3 | `inc/class-site-manager.php` | `[ ]` `ajax_start_cf_onboarding()` — extrae dominio de `$site->url`, llama DR Bridge, guarda `cf_onboarding_started_at` en site_meta |
| CF.4 | `replanta-hub.php` | `[ ]` Registrar `wp_ajax_rphub_start_cf_onboarding` |
| CF.5 | `inc/admin-operations.php` | `[x]` Cola de Onboarding CF ya visible (`get_onboarding_queue()`) |
| CF.6 | `inc/admin-operations.php` | `[ ]` Boton "Iniciar CF" por sitio cuando `cf_zone_id` esta vacio |
| CF.7 | `dominios-reseller` | `[~]` `class-onboarding-worker.php` ya maneja el flujo; verificar que expone funcion publica callable desde Hub |

### Flujo de onboarding CF

```
1. Hub detecta sitio sin cf_zone_id
2. Admin pulsa "Iniciar CF onboarding"
3. Hub llama DR: OnboardingWorker->start(domain)
4. DR crea zona CF → guarda nameservers en wp_dominios_reseller_cf_onboarding
5. Hub muestra NS en ficha del sitio
6. Admin configura DNS en registrador
7. DR cron verifica NS cada hora → actualiza state a 'pending_ns' → 'onboarded'
8. Hub operations page muestra estado en "Cola de Onboarding CF"
```

---

## Phase 0 — Nombres de plan canonicos: semilla / raiz / ecosistema

| # | Archivo | Cambio |
|---|---------|--------|
| 0.1 | `replanta-hub.php` | Migracion v1.3: `UPDATE rphub_sites SET plan='semilla' WHERE plan='basic'`, etc. |
| 0.2 | `replanta-hub.php` | `ajax_update_site_plan()`: enum `['semilla','raiz','ecosistema']` |
| 0.3 | `inc/class-rest-api.php` | `register_site` enum: `['semilla','raiz','ecosistema']` |
| 0.4 | `inc/class-site-manager.php` | `add_site()` plan default `'semilla'`; enum en validacion |
| 0.5 | `inc/class-rest-api.php` | `get_plan_features()` fallback a `semilla` (ya correcto en utils) |

---

## Phase 1 — RPHUB_DR_Bridge (enriquecimiento DR en site_meta)

| # | Archivo | Cambio |
|---|---------|--------|
| 1.1 | `inc/class-dr-bridge.php` **(NEW)** | `RPHUB_DR_Bridge` — acceso estatico a tablas DR |
|     | | `is_available(): bool` |
|     | | `domain_from_url(string $url): string` — strip protocolo a host |
|     | | `get_domain_row(string $domain): ?array` — `wp_dominios_reseller` |
|     | | `get_cf_zone(string $domain): ?array` — `wp_dominios_reseller_cf_zones` |
|     | | `get_cf_onboarding(string $domain): ?array` — `wp_dominios_reseller_cf_onboarding` |
|     | | `get_php_version(array $row): string` — parsea `php_info` JSON |
|     | | `enrich_site(int $site_id, string $url): array` — escribe site_meta: php_version_whm, whm_server, whm_status, co2_evaded, trees_planted, cf_zone_id, cf_zone_status, cf_plan_name, cf_onboarding_state, dr_enriched_at |
|     | | `get_site_dr_data(int $site_id): array` — lee todas las claves DR de site_meta |
| 1.2 | `replanta-hub.php` | `require_once class-dr-bridge.php` en `load_dependencies()` |
| 1.3 | `replanta-hub.php` | `ajax_enrich_all_from_dr()`: itera sitios activos, llama `enrich_site()` |
| 1.4 | `replanta-hub.php` | Registrar `wp_ajax_rphub_enrich_all_from_dr` |
| 1.5 | `inc/class-site-manager.php` | `rest_site_heartbeat()`: llama `enrich_site()` si `dr_enriched_at` > 24h |
| 1.6 | `replanta-hub.php` | `ajax_get_sites_cards()`: incluir claves DR de site_meta en cada site |

---

## Phase 2 — Motor de auditoria remota

### 2a — RPHUB_CF_Audit

| # | Archivo | Cambio |
|---|---------|--------|
| 2.1 | `inc/class-cf-audit.php` **(NEW)** | `RPHUB_CF_Audit(string $zone_id)` |
|     | | `run(): array` — llama `get_zone_full_config()` de DR, evalua 11 settings CF |
|     | | Checks: always_use_https (critico), ssl (critico), brotli (aviso), min_tls_version (critico), security_level (aviso), development_mode (aviso), early_hints (info), automatic_https_rewrites (aviso), http3 (info), rocket_loader (info), hsts (info) |
|     | | Retorna `{score:int, checks:[{id, label, status, current, expected, fix_id}]}` |

### 2b — RPHUB_SEO_Audit

| # | Archivo | Cambio |
|---|---------|--------|
| 2.2 | `inc/class-seo-audit.php` **(NEW)** | `RPHUB_SEO_Audit(string $site_url)` |
|     | | `run(): array` — fetch HTTP: homepage, /robots.txt, sitemap (3 rutas fallback) |
|     | | Checks: robots.txt existe + no bloquea todo, sitemap existe, meta title (30-60 chars), meta description (120-160 chars), og:title, og:image, canonical, h1, schema JSON-LD |
|     | | Retorna `{score:int, checks:[{id, label, status, details}]}` |

### 2c — RPHUB_Perf_Audit

| # | Archivo | Cambio |
|---|---------|--------|
| 2.3 | `inc/class-perf-audit.php` **(NEW)** | `RPHUB_Perf_Audit(string $site_url, string $api_key)` |
|     | | `run(): array` — llamada PSI API (strategy=mobile), parsea LH + CrUX |
|     | | Retorna `{score:int, lcp, cls, fcp, tbt, ttfb, crux_category, checks:[...]}` |

### 2d — RPHUB_Site_Auditor (orquestador)

| # | Archivo | Cambio |
|---|---------|--------|
| 2.4 | `inc/class-site-auditor.php` **(NEW)** | `RPHUB_Site_Auditor` |
|     | | `run_audit(int $site_id, bool $force=false): array` |
|     | | Cache 24h en site_meta (`audit_last_run`), `$force` la salta |
|     | | Lanza CF audit si `cf_zone_id` disponible |
|     | | Lanza SEO + Perf audit con URL del sitio |
|     | | Guarda en site_meta: cf_score, cf_issues_json, seo_score, seo_issues_json, perf_score, perf_data_json, audit_last_run |
|     | | Retorna resultado combinado |
| 2.5 | `replanta-hub.php` | `require_once` de los 4 ficheros Phase 2 |
| 2.6 | `replanta-hub.php` | `ajax_run_site_audit()` handler |
| 2.7 | `replanta-hub.php` | Registrar `wp_ajax_rphub_run_site_audit` |

---

## Phase 3 — RPHUB_CF_Fixer (fixes CF por plan via DR)

| # | Archivo | Cambio |
|---|---------|--------|
| 3.1 | `inc/class-cf-fixer.php` **(NEW)** | `RPHUB_CF_Fixer` |
|     | | `get_allowed_fixes(string $plan): array` — tabla de permisos por plan |
|     | | semilla: always_use_https, brotli, http3, min_tls_12, dev_mode_off |
|     | | raiz: + early_hints, automatic_https_rewrites, rocket_loader_off |
|     | | ecosistema: + security_medium, hsts, bot_fight_mode, cf_purge_cache |
|     | | `execute(string $zone_id, string $fix_id): array` — llama DR CF service |
|     | | `execute_plan_defaults(string $zone_id, string $plan): array` |
| 3.2 | `replanta-hub.php` | `require_once class-cf-fixer.php` |
| 3.3 | `replanta-hub.php` | `ajax_apply_cf_fix()` + `ajax_apply_plan_cf_fixes()` |
| 3.4 | `replanta-hub.php` | Registrar ambas acciones AJAX |

---

## Phase 4 — Care: POST /execute-fix

| # | Archivo | Cambio |
|---|---------|--------|
| 4.1 | `replanta-care/inc/class-rest.php` | Registrar `POST /execute-fix` en `register_routes()` |
| 4.2 | `replanta-care/inc/class-rest.php` | `execute_fix($request)` |
|     | | Valida `fix_id` contra lista blanca |
|     | | Implementa: `wp_debug_off`, `wp_memory_limit`, `wp_cron_disable`, `heartbeat_optimize`, `db_clean_revisions`, `db_clean_transients`, `db_clean_spam`, `ls_enable_object_cache` |
|     | | Modifica wp-config.php (debug/memory/cron) via regex segura |
|     | | SQL para db_* fixes |
|     | | Opcion WP para heartbeat / LiteSpeed |
|     | | Retorna `{success, fix_id, message, details}` |

---

## Phase 5 — RPHUB_WP_Fixer (dispatch WP fixes a Care)

| # | Archivo | Cambio |
|---|---------|--------|
| 5.1 | `inc/class-wp-fixer.php` **(NEW)** | `RPHUB_WP_Fixer` |
|     | | `get_allowed_fixes(string $plan): array` |
|     | | semilla: wp_debug_off, heartbeat_optimize |
|     | | raiz: + db_clean_revisions, db_clean_transients, db_clean_spam, wp_memory_limit |
|     | | ecosistema: + wp_cron_disable, ls_enable_object_cache |
|     | | `send_fix(int $site_id, string $fix_id, array $ctx=[]): array` — POST a Care `/execute-fix` con Bearer |
|     | | `send_plan_fixes(int $site_id, string $plan): array` |
| 5.2 | `replanta-hub.php` | `require_once class-wp-fixer.php` |
| 5.3 | `replanta-hub.php` | `ajax_send_wp_fix()` + `ajax_send_plan_wp_fixes()` |
| 5.4 | `replanta-hub.php` | Registrar ambas acciones AJAX |

---

## Phase 6 — Cableado Hub main

| # | Archivo | Cambio |
|---|---------|--------|
| 6.1 | `replanta-hub.php` | Todos los `require_once` nuevos en `load_dependencies()` |
| 6.2 | `replanta-hub.php` | Todos los `add_action('wp_ajax_...')` nuevos |
| 6.3 | `replanta-hub.php` | `ajax_get_sites_cards()`: anadir cf_score, seo_score, perf_score, whm_server, cf_zone_status, co2_evaded, trees_planted de site_meta |

---

## Phase 7 — Cards UI

| # | Archivo | Cambio |
|---|---------|--------|
| 7.1 | `inc/admin-sites.php` | CSS: score-bars, CF badge, server flag, ecology pill |
| 7.2 | `inc/admin-sites.php` | `rphubBuildCard()` JS: CF badge (active/pending/none), server (UK/US), ecology (X arboles), 3 audit mini-bars (CF/SEO/WPO) |
| 7.3 | `inc/admin-sites.php` | Boton "Auditar" a `rphubAuditSite(id, btn)` |
| 7.4 | `inc/admin-sites.php` | Boton "Fixes CF" a `rphubApplyPlanFixes(id, plan, btn)` |
| 7.5 | `inc/admin-sites.php` | Boton "Sync DR" por card |

---

## Phase 8 — Informe mensual enriquecido

| # | Archivo | Cambio |
|---|---------|--------|
| 8.1 | `inc/class-reports-system.php` | Seccion DR: servidor, CF plan, php_version_whm, arboles, CO2 |
| 8.2 | `inc/class-reports-system.php` | Seccion auditoria: cf_score, seo_score, perf_score vs mes anterior |

---

## dominios-reseller — Pendiente

| # | Archivo | Cambio |
|---|---------|--------|
| DR.1 | `includes/class-onboarding-worker.php` | `[ ]` Exponer `start_onboarding_for_domain(string $domain)` como funcion publica callable desde Hub sin instanciar el contexto admin |
| DR.2 | `includes/class-cloudflare-service.php` | `[ ]` Verificar que `get_zone_full_config(string $zone_id)` es accesible sin admin context (para RPHUB_CF_Audit) |
| DR.3 | `includes/ajax-handlers.php` | `[ ]` Handler `dominios_reseller_hub_trigger_onboarding` con nonce propio para peticiones desde Hub |
| DR.4 | General | `[ ]` Crear TODO.md en el repositorio |

---

## replanta-site-audit — Pendiente

| # | Archivo | Cambio |
|---|---------|--------|
| RSA.1 | `includes/class-dr-bridge.php` | `[~]` Verificar que `RSA_DR_Bridge` expone `get_cf_token()` de forma que Hub pueda reutilizar el patron |
| RSA.2 | `includes/class-rest-api.php` | `[ ]` Endpoint `GET /wp-json/replanta/v1/sa/summary` ya existe — documentar contrato de respuesta para Hub |
| RSA.3 | `includes/class-rest-api.php` | `[ ]` Endpoint `GET /wp-json/replanta/v1/sa/issues` ya existe — documentar fix_id disponibles para RPHUB_WP_Fixer |
| RSA.4 | `includes/class-auto-fixer.php` | `[ ]` Añadir fix `wp_debug_off` + `heartbeat_optimize` + `db_clean_revisions` para ser invocados via Care `/execute-fix` |
| RSA.5 | General | `[ ]` Crear TODO.md en el repositorio |

---

## replanta-care — Pendiente post-Sprint-5

| # | Archivo | Cambio |
|---|---------|--------|
| C.1 | `inc/class-rest.php` | `[ ]` Phase 4: endpoint `POST /execute-fix` (ver Phase 4 arriba) |
| C.2 | `inc/class-client-portal.php` | `[ ]` Mostrar alerta cuando checkout_status == 'fail' con timestamp del ultimo fallo |
| C.3 | `inc/task-checkout-monitor.php` | `[ ]` Test manual: verificar que se registra correctamente en Action Scheduler |
| C.4 | `inc/task-revenue-anomaly.php` | `[ ]` Test manual: verificar alertas con MIN_BASELINE en tienda nueva |

---

## site_meta keys completo

| Clave | Fuente | Escrito por |
|-------|--------|-------------|
| `care_version` | Care heartbeat | class-site-manager |
| `wp_version` | Care heartbeat | class-site-manager |
| `php_version` | Care heartbeat | class-site-manager |
| `pending_updates_count` | Care heartbeat | class-site-manager |
| `last_backup` | Care heartbeat | class-site-manager |
| `uptime_monitoring_enabled` | Hub admin | class-site-manager |
| `addon_ecommerce` | Hub admin | class-site-manager (save_addon_meta) |
| `ecom_revenue_threshold` | Hub admin | class-site-manager (save_addon_meta) |
| `ecom_alert_email` | Hub admin | class-site-manager (save_addon_meta) |
| `care_last_config_push` | Hub admin | class-site-manager (ajax_apply_plan_features) |
| `php_version_whm` | DR dominios_reseller | RPHUB_DR_Bridge |
| `whm_server` | DR dominios_reseller | RPHUB_DR_Bridge |
| `whm_status` | DR dominios_reseller | RPHUB_DR_Bridge |
| `whm_primary_domain` | DR dominios_reseller | RPHUB_DR_Bridge |
| `co2_evaded` | DR dominios_reseller | RPHUB_DR_Bridge |
| `trees_planted` | DR dominios_reseller | RPHUB_DR_Bridge |
| `cf_zone_id` | DR cf_zones | RPHUB_DR_Bridge |
| `cf_zone_status` | DR cf_zones | RPHUB_DR_Bridge |
| `cf_plan_name` | DR cf_zones | RPHUB_DR_Bridge |
| `cf_onboarding_state` | DR cf_onboarding | RPHUB_DR_Bridge |
| `dr_enriched_at` | timestamp | RPHUB_DR_Bridge |
| `cf_score` | CF audit | RPHUB_Site_Auditor |
| `cf_issues_json` | CF audit | RPHUB_Site_Auditor |
| `seo_score` | SEO audit | RPHUB_Site_Auditor |
| `seo_issues_json` | SEO audit | RPHUB_Site_Auditor |
| `perf_score` | PSI audit | RPHUB_Site_Auditor |
| `perf_data_json` | PSI audit | RPHUB_Site_Auditor |
| `audit_last_run` | timestamp | RPHUB_Site_Auditor |
| `cf_onboarding_started_at` | Hub admin | ajax_start_cf_onboarding |

---

## Plan gates

### CF Fixes (RPHUB_CF_Fixer)

| Fix | semilla | raiz | ecosistema |
|-----|:-------:|:----:|:----------:|
| always_use_https | ok | ok | ok |
| brotli | ok | ok | ok |
| http3 | ok | ok | ok |
| min_tls_12 | ok | ok | ok |
| dev_mode_off | ok | ok | ok |
| early_hints | | ok | ok |
| automatic_https_rewrites | | ok | ok |
| rocket_loader_off | | ok | ok |
| security_medium | | | ok |
| hsts | | | ok |
| bot_fight_mode | | | ok |
| cf_purge_cache | | | ok |

### WP Fixes (RPHUB_WP_Fixer a Care)

| Fix | semilla | raiz | ecosistema |
|-----|:-------:|:----:|:----------:|
| wp_debug_off | ok | ok | ok |
| heartbeat_optimize | ok | ok | ok |
| db_clean_revisions | | ok | ok |
| db_clean_transients | | ok | ok |
| db_clean_spam | | ok | ok |
| wp_memory_limit | | ok | ok |
| wp_cron_disable | | | ok |
| ls_enable_object_cache | | | ok |
