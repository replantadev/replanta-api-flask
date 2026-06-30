# Changelog — Replanta Hub

## [2.5.5]

- CF onboarding: se verifican los nameservers reales del dominio antes de avanzar a `onboarded`.
- CF onboarding: una zona existente en Cloudflare sin delegacion DNS queda en `pending_ns` con mensaje operativo.
- CF onboarding: se actualizan los nameservers asignados por Cloudflare cuando faltan en la cola antes de reintentar verificacion.

## [2.5.4]

- Operaciones: se eliminan `onclick` con JSON embebido en atributos HTML para evitar `Unexpected end of input`.
- Operaciones: botones de fixes SA y aplicacion de plan usan `data-*` + listeners delegados, mas robusto con nombres de sitio y planes.

## [2.5.3]

- Operaciones: nonces tolerantes para sincronizar, ejecutar tareas y fixes SA; evita 403 tras recargas parciales.
- UI: botones de acciones en tablas con contraste fijo y estados de carga legibles.
- Encoding: cabecera del plugin, variables JS localizadas y textos visibles de Operaciones sin mojibake.
- Admin JS: se retiran logs de arranque con nonces/variables en consola de produccion.

## [2.5.2]

- Updates: el alias público `rphub/v1/care-release-info` limpia salida previa/BOM y responde JSON parseable igual que `replanta-hub/v1/updates/care`.

## [2.5.1]

- Backblaze: prefijo B2 determinista por cliente y propagado a Care para aislar backups.
- Backups: Hub usa los endpoints REST B2 de Care para crear, listar y restaurar copias.
- Planes: sincronización de frecuencia, retención y ventana de actualización hacia Care.
- Operaciones: restauración remota segura por defecto a base de datos, ampliable por scopes.
- Operaciones: llamadas remotas a Care con `sslverify` activo en paths de configuración y self-update.
- Monitorización: cron por minuto registrado antes de programar el fallback de WP-Cron.
- CI/CD: release de Hub bloqueada por `php -l` igual que Care.

## [2.5.0]

- Operaciones: fix AJAX `rphub_sync_site` (handler estaba registrado solo como `rphub_sync_site_data`); el botón "Sincronizar sitio" ya no falla con 400
- Backblaze: token B2 cacheado con expiración verificable y refresh automático cuando faltan <5 min
- Backblaze: test de conexión unificado a API v3 + verifica acceso real al bucket (no sólo auth)
- Backblaze: al guardar credenciales B2 en Ajustes, Hub propaga la config a todos los sitios activos automáticamente (antes había que hacer "Apply plan features" manual por sitio)
- Backblaze: `push_config_to_all_sites()` reutilizable; transient de auth se invalida en cada cambio de credenciales
- CF onboarding: motor de estados completo (`RPHUB_CF_Onboarding_Engine`). Cron horario consume la cola de `wp_dominios_reseller_cf_onboarding` y avanza: pending → pending_ns (crea zona) → onboarded (NS verificados) → completed (preset SSL/HTTPS/Brotli/TLS aplicado) | partial | needs_manual_ns (tras 24 intentos)
- CF onboarding: botones "Reintentar", "Manual" y "Ejecutar ciclo ahora" en la cola de Operaciones
- CF onboarding: notificaciones via `RPHUB_Alerting::notify()` en cada transición relevante (error / partial / needs_manual_ns / completed)
- UI: SEO Autopilot y páginas hidden de Multisite ocultas tras flag `rphub_show_experimental` (default off) para no exponer módulos incompletos al cliente

## [2.3.0]

- Addon system: recibe addons[] y ecommerce_config{} via /config de Care; pushea a sitios al guardar
- Addon eCommerce: checkbox en formulario de sitio (addon_ecommerce, umbral de ingresos, email de alerta)
- Operaciones: modal "Configurar plan" por sitio — muestra estado de features y boton aplicar
- Operaciones: ajax_sync_all() — sincroniza todos los sitios activos con un click
- ajax_get_plan_status(): devuelve checklist de features del plan (Care conectado, B2, addon activo)
- ajax_apply_plan_features(): construye payload completo y pushea config a Care con credenciales B2
- rphub_care_alert: webhook handler para alertas addon de Care (checkout_failure, revenue_anomaly, etc.)
- Settings: seccion "Backup externo Replanta" con campos B2 (key_id, app_key cifrada, bucket)
- Settings: b2_app_key cifrada en reposo con RPHUB_Crypto; get_global_config() descifra al leer
- ajax_test_connection() B2: usa valores del formulario (no los guardados) para verificar antes de guardar
- Backblaze: authorizeWithCredentials() para test con credenciales en vuelo sin guardar en DB
- Site Manager: push_addon_config_to_care() envia addons activos + config a Care via REST /config
- CI/CD: .github/workflows/deploy.yml — build ZIP, GitHub Release en cada push a main
- Hub: 2.3.0

## [2.2.0]

- ClientPortal: push portal_cache desde Hub tras cada ciclo de actualizacion
- REST /config en Care: acepta portal_cache para carga instantanea
- Site Manager: sincronizacion de datos del portal al cliente
- Hub: 2.2.0

## [2.1.0]

- Backups unificados en Backblaze B2 para todos los planes
- Risk Scorer con Claude AI: evalua changelog de plugins antes de actualizar
- Delta Reporter: snapshots SA + metricas antes/despues de cada ciclo
- SmartUpdates: backup pre-update via Care REST /run?task=backup
- REST /config: acepta credenciales B2 (b2_key_id, b2_app_key, b2_bucket_id, b2_bucket_name)
- Hub: 2.1.0

## [2.0.10]

- /updates/care: buffers limpios para evitar BOM en respuesta JSON (fix puc-invalid-json)

## [2.0.x]

- Produccion: hardening de nonces, limpieza AJAX fantasmas, rewiring Backuply
- REST rphub/v1/notifications endpoint
- Dashboard ecologico (CO2, arboles)
- CF onboarding automatico al crear sitio
