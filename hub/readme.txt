=== Replanta Hub ===
Contributors: replanta
Tags: multisite, management, automation, maintenance, hub
Requires at least: 5.0
Tested up to: 6.3
Requires PHP: 7.4
Stable tag: 2.5.5
License: Proprietary
License URI: https://replanta.es/license

Hub central para gestión de múltiples sitios WordPress con mantenimiento automatizado.

== Description ==

Replanta Hub es un sistema completo para la gestión centralizada de múltiples sitios WordPress. Incluye funcionalidades avanzadas de monitoreo, mantenimiento automático y gestión de tareas.

Características principales:
* Dashboard centralizado con métricas en tiempo real
* Gestión automática de actualizaciones y backups
* Sistema de tareas programadas
* Monitoreo de salud de sitios
* Reportes automáticos
* Integración WHM para gestión de hosting

== Installation ==

1. Sube la carpeta `replanta-hub` al directorio `/wp-content/plugins/`
2. Activa el plugin desde el menú 'Plugins' en WordPress
3. Configura la conexión WHM en la página de ajustes
4. Registra los sitios child para comenzar la gestión centralizada

== Changelog ==

= 1.5.2 (2025-07-24) =
* Fix: Dashboard — columnas DB corregidas (plan_type, care_version, last_report_date)
* Fix: PHP 8.2 — null deprecation warnings en admin-dashboard.php (strpos, substr, str_replace)
* Mayor: JetBackup reemplazado completamente por Backuply (nuevo class-backuply-integration.php + class-backuply-advanced.php)
* Mayor: Soporte multi-servidor WHM (EU + US) — class-whm-integration.php reescrito, admin-settings.php con formulario repetible por servidor
* Mayor: Tabla rphub_sites — nueva columna whm_server para mapeo dominio→servidor
* Mayor: Migración automática de configuración WHM single→multi en activación
* Fix: AJAX handlers no registrados (litespeed, wptoolkit, pagespeed, backuply, cloudflare) — timing issue con did_action('init')
* Fix: class-diagnostics.php — substr() null deprecation + nombres de acciones AJAX actualizados

= 1.5.1 (2026-02-20) =
* Mayor: Eliminado menú top-level "Security Analytics" completo (4 submenús, datos 100% falsos)
* Mayor: Eliminados 8 submenús fantasma (Network Mgmt, Network Analytics, Config Templates, Threat Center, Compliance, Threat Intelligence, Predictive Analytics, Executive Reports)
* Mayor: Desconectadas 3 clases stub sin funcionalidad real (AI Threat Predictor 674 lín, Advanced Analytics Dashboard 725 lín, Hub Instructions 635 lín)
* Mayor: Dashboard — health overview, task performance y recent activity ahora consultan BD real en vez de arrays hardcodeados
* Mayor: Security — compliance score calculado desde checks reales del servidor (SSL, debug, file-edit, prefix)
* Mayor: Security — threat intelligence ahora lee logs reales en vez de patrones hardcodeados
* Fix: Eliminado registro duplicado de menú "Reportes" en class-report-generator.php
* Fix: Enlace roto a "Instrucciones" en views/dashboard.php corregido
* Mejora: Wizard se oculta del menú automáticamente tras completar setup (rphub_setup_completed)
* Mejora: Submenú Analytics renombrado a "Analytics / WPO" alineado con oferta comercial
* Resultado: De 20 entradas de menú a 10 limpias (Dashboard, Sitios Web, Reportes, Configuración, Seguridad, Grupos, Operaciones Masivas, Analytics/WPO, Diagnósticos, +Wizard oculto)

= 1.5.0 (2026-02-19) =
* Mayor: Modelo de datos unificado — tabla rphub_site_meta reemplaza postmeta de CPT
* Mayor: 10 clases refactorizadas de get_post_meta a RPHUB_Database::get_site_meta
* Mayor: 14 métricas falsas (rand()) reemplazadas por queries reales o nulls honestos
* Mayor: ~1870 líneas de código AI/ML muerto desconectado (3 módulos)
* Seguridad: validate_request() con autenticación real (token, Bearer, admin)
* Seguridad: Eliminado __return_true en permisos REST
* Seguridad: Contraseñas en texto plano eliminadas de BD (migración v2.1)
* Seguridad: WHM IP hardcoded eliminada, curl_exec → wp_remote_request, SSL enforced
* Fix: Hooks AJAX duplicados eliminados (notifications, test_care_connection, bulk_action)
* Fix: error_log() debug envuelto en RPHUB_DEBUG check (19 instancias)
* Fix: Limpieza automática de security_logs en cleanup_old_data()
* Eliminado: demo.php (427 líneas), datos de ejemplo del wizard
* Eliminado: Handlers duplicados (~120 líneas sin sanitización)
* DB: Nueva tabla rphub_site_meta + helpers estáticos (get_site, get_all_sites, CRUD meta)

= 1.4.4 (2025-09-15) =
* Mayor: Conexión estable con plugin Replanta Care
* Nuevo: Campo token visible y copiable en modal de edición de sitios
* Fix: Corregido error de columna api_key → token en base de datos
* Fix: Sistema completo de eliminación de sitios con múltiples parámetros
* Mejora: Conversión completa de fetch() a jQuery.ajax() para evitar CORS
* Nuevo: Endpoint rphub_get_site_plan para detección automática de plan
* Mejora: Auto-sanitización de URLs mal configuradas
* Fix: Respuestas AJAX consistentes en formato WordPress
* Mejora: Logs mejorados para debugging y troubleshooting

= 1.1.6 =
* Sistema completo de tokens persistentes WHM
* Renovación automática de tokens
* Diagnóstico completo de conexión WHM
* Mejoras en manejo de errores
* Monitoreo automático cada hora

= 1.1.5 =
* Versión inicial con funcionalidades básicas

== Frequently Asked Questions ==

= ¿Cómo conectar con WHM? =
Ve a Ajustes > Replanta Hub y configura las credenciales WHM.

= ¿Qué incluye el mantenimiento automático? =
Actualizaciones de WordPress, plugins, themes, backups y monitoreo de seguridad.

== Screenshots ==

1. Dashboard principal con métricas
2. Gestión de sitios conectados
3. Configuración WHM
4. Reportes automáticos

== Upgrade Notice ==

= 1.1.6 =
Actualización importante con sistema de tokens persistentes y mejoras en estabilidad.
