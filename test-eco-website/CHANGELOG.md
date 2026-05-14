# Changelog

## 0.2.0 · 2026-01-10

### 🔌 Nueva Integración: StaffKit Lead Capture

- **Captura automática de leads**: Integración con StaffKit Connector para envío automático de leads cuando usuarios completan auditorías
  - Envío vía webhook a StaffKit al guardar email en reporte
  - Datos enviados: email, dominio, score eco-performance, CO2/visita, métricas completas
  - Detección automática de función `staffkit_send_lead()` (no rompe si plugin no instalado)
  
- **Tracking completo**: Lead capturado incluye:
  - Email del usuario
  - Dominio auditado (website)
  - Eco-score (A-E) y valor numérico de CO2
  - Source: "Eco Performance Audit - [Sitio]"
  - Source URL: `/r/{domain}` para tracking
  - Audit data: scores móvil/escritorio, eco snapshot, peso página, hosting verde, carbon rating, report_id

### 📦 Cambios Técnicos
- `includes/rest/class-controller.php`: Agregado bloque StaffKit en `handle_save_email()` después de update_email exitoso
- `README.md`: Documentación completa de integración con StaffKit Connector
- Compatibilidad: Requiere StaffKit Connector v1.1.0+ para funcionalidad completa

### 🎯 Beneficios CRM
- Captura automática de empresas interesadas en sostenibilidad web
- Segmentación por score eco-performance para campañas personalizadas
- Tracking desde auditoría hasta conversión final
- Enriquecimiento automático de leads con datos técnicos

## 0.1.9 · 2025-10-22

### ✨ Mejoras UX/UI - Template Informe Individual

- **Título elegante con subtítulo**: Extraída fecha/hora del título para crear subtítulo delicado
  - Título principal con `line-height: 1em` (compacto como solicitaste)
  - Subtítulo con fecha separada visualmente con estilo fino
  - URL del sitio con icono y diseño tipo "badge" verde
  - Regex para extraer patrón "Título · fecha hora" y separar componentes

- **Botón compartir minimalista**: Reemplazado bloque grande por icono elegante
  - Icono share en esquina superior derecha (48x48px, estilo floating)
  - Modal popup centrado con input + botón copiar
  - Animaciones suaves (hover, transform, box-shadow)
  - JavaScript para clipboard API + fallback document.execCommand
  - Feedback visual "¡Copiado!" temporal

- **CTA final estilo Replanta**: Reemplazado bloque compartir repetido
  - Diseño con gradiente sutil y borde redondeado (24px)
  - Título "¿Listo para replantear tu presencia digital?"
  - Dos botones: "Analizar mi sitio web" (primario) + "Consultoría sostenible" (secundario)
  - Responsive: botones apilados en móvil, centrados en desktop
  - Iconos Material: analytics + eco

- **SEO automático para RankMath**: Meta tags completos para cada informe
  - Title: "Análisis Eco-Performance de {domain} - Score {score}"
  - Description: Score, grade, CO2, hosting verde + datos técnicos
  - Open Graph + Twitter Cards para redes sociales
  - Schema.org structured data tipo "Report"
  - Keywords automáticos: "sostenibilidad web, análisis eco-performance..."
  - Meta específicos RankMath: focus_keyword, seo_score

### 📦 Cambios Técnicos
- `templates/single-tew_audit.php`: Refactorizado header + regex fecha + hook wp_head
- `assets/css/frontend.css`: +150 líneas CSS para título, modal, CTA, responsive
- `assets/js/frontend.js`: Nueva función `initShareButton()` con clipboard + modal
- Hook `wp_head` con meta tags dinámicos basados en datos del informe
- Responsive mobile-first para todos los nuevos componentes

### 🎯 Beneficios SEO
- Informes ahora indexables con metadatos ricos
- Títulos únicos por dominio analizado  
- Descriptions descriptivas con métricas clave
- Schema markup para mejor comprensión Google
- Social sharing optimizado (OG + Twitter)

## 0.1.8 · 2025-10-21

### 🐛 Fixes Críticos - Meta Fields Faltantes
- **Meta fields guardados correctamente**: Corregido `save()` para guardar todos los meta fields
  - **PROBLEMA**: Los informes se guardaban sin `_tew_report_score`, `_tew_report_is_green`, etc.
  - **CAUSA**: El código buscaba `summary.overall_score` pero el payload usa `summary.score`
  - **SOLUCIÓN**: Ahora busca primero `summary.score`, luego `overall_score` como fallback
  - **TAMBIÉN**: Busca `metrics.greenweb` antes que `metrics.green_hosting`
  - **TAMBIÉN**: Busca `metrics.websitecarbon` antes que `metrics.carbon`
  - **RESULTADO**: Al editar un informe ahora SÍ aparece el score y el checkbox de hosting verde

- **Compatibilidad en summary**: Agregado campo `overall_score` como alias de `score`
  - Cambio en `class-insight-factory.php` línea 35
  - Ahora el payload tiene ambos: `summary.score` Y `summary.overall_score`
  - Garantiza compatibilidad con código que use cualquiera de los dos

- **Script de reparación**: Nuevo archivo `repair-old-reports.php`
  - Uso: Visitar `/wp-admin/?repair_tew_reports` (solo admins)
  - Regenera meta fields faltantes desde el payload JSON
  - Actualiza score, grade, is_green, provider, co2, fecha para informes antiguos
  - Útil para reparar los ~300 informes que se guardaron sin meta fields

### 📦 Cambios Técnicos
- `save()` ahora intenta múltiples rutas para cada métrica (greenweb → green_hosting, etc.)
- Meta field `_tew_report_is_green` ahora se guarda como string '1' o '0' (era bool antes)
- Variables intermedias ($score, $is_green, $co2, $provider) para claridad del código
- Agregado `repair-old-reports.php` cargado automáticamente en `test-eco-website.php`

## 0.1.7 · 2025-01-11

### 🐛 Fixes Críticos
- **Menú admin arreglado**: Orden de submenús corregido para evitar conflicto con CPT
  - Agregado submenú explícito "Configuración" como primer ítem en `register_menu()`
  - CPT `show_in_menu => 'tew-settings'` vuelve a estar bajo "Eco Snapshot"
  - Ahora "Eco Snapshot" lleva a Configuración (admin.php?page=tew-settings)
  - Orden final: Configuración > Dashboard > Casos de Éxito > Informes Eco
  - **Fix técnico**: WordPress hace que el primer submenú tome el URL del padre. Agregar submenú explícito con mismo slug que el padre previene que el CPT (agregado alfabéticamente después) tome control del URL.
- **Cache invalidado al guardar**: Limpieza automática de transients al guardar nuevo informe
  - Agregado `$cache->delete($url)` en Report_Storage::save()
  - Evita que informes recién generados muestren datos cacheados antiguos
- **Cache invalidado al borrar**: Limpieza automática de transients al eliminar informes
  - Hook `before_delete_post` añadido en Report_Storage::register()
  - Método `clear_cache_on_delete()` limpia transient cuando se borra un informe
  - **Solución al problema**: Borrar informe en admin ahora limpia cache, permitiendo regenerar con datos frescos
- **Score 0 mejorado**: Fallback más robusto para extracción de score
  - Detecta tanto `0.0` como string vacío `''` de get_post_meta()
  - Condición cambiada a `empty($score) || $score === 0.0`
  - Garantiza que informes nuevos y antiguos muestren score correcto en dropdowns

### 📦 Cambios Técnicos
- Submenú "Configuración" agregado explícitamente en class-admin-page.php
- CPT label mantenido como "Informes Eco"
- Cache::delete() invocado automáticamente antes de wp_insert_post()
- Cache::delete() también invocado en hook before_delete_post
- Mejora en validación de meta vacío vs 0

## 0.1.6 · 2025-01-11

### ✨ Nuevas Funcionalidades
- **TTFB visible**: Agregado Time To First Byte (TTFB) en todos los informes
  - Extraído de PageSpeed audits['server-response-time']['numericValue']
  - Mostrado en casos de éxito (tarjetas de mejora ANTES → DESPUÉS)
  - Mostrado en informes regulares del showcase
  - Incluido en scorecard components metadata

### 🐛 Fixes
- **Score 0 resuelto**: Fix para informes con score 0 en dropdown de creación de casos de éxito
  - Agregado fallback en get_all_analyses() para extraer score del payload si meta no existe
  - Actualización automática del meta _tew_report_score al cargar informes antiguos
- **Estilos single report**: Template single-tew_audit.php ahora tiene CSS centrado y márgenes correctos
  - Agregada clase .tew-report-view con max-width 1200px y margin auto
  - Estilos responsive para móvil y desktop

### 🎨 Mejoras UX
- **Menús unificados**: Consolidado menú admin en un solo "Eco Snapshot"
  - CPT "Informes Eco" ahora aparece como submenú (show_in_menu: 'tew-settings')
  - Estructura: Configuración > Dashboard > Casos de Éxito > Informes Eco
  - Eliminado menú duplicado del Custom Post Type

### 📦 Arquitectura
- Método extract_ttfb_from_report() en Report_Storage para extracción consistente de TTFB
- Estadísticas de mejora (get_improvement_stats) ahora incluyen array 'ttfb' con before/after/diff/percent

## 0.1.2 · 2025-10-20

### 🐛 Fixes
- Agregado logging extensivo para debugging del error 400 al eliminar casos de éxito
- Implementado file_put_contents() en ajax_delete_success_case() para logs forzados en wp-content/tew-debug.log
- Agregados console.log() detallados en admin.js para monitorear requests AJAX

### 🎨 Mejoras
- Rediseño completo del showcase de casos de éxito siguiendo el Manual de Marca Replanta
- Aplicado sistema de colores Replanta: #93F1C9, #1E2F23, #41999F, #F7D450
- Cambiada tipografía a Fraunces (serif) + DM Sans (sans-serif)
- Cards minimalistas y condensadas estilo Semrush
- Métricas con colores semánticos: rojo para "before", teal para "after"
- Badge "Hosting Verde" con checkmark SVG inline
- Animaciones fadeInUp con delays escalonados
- Hover effects sutiles (translateY, border-color)

## 0.1.1 · 2025-10-07

- Sustituido EcoGrader por el Eco Snapshot Score propio combinando PageSpeed (móvil/escritorio), Website Carbon y Green Web Foundation.
- Rediseño del informe: narrativa técnica vs emocional, breakdown de score y tarjeta de huella de carbono destacada.
- Migrada la integración de Website Carbon al endpoint `/data`, calculando los bytes desde PageSpeed y eliminando la API key opcional.
- Simplificado el panel de credenciales y el flujo de prueba de conexiones.
- Documentación y frontend actualizados para reflejar la nueva metodología de scoring.

## 0.1.0 · 2025-10-06

- Primera versión pública del plugin **Eco-Performance Snapshot**.
- Panel de ajustes con estilo Material/Bootstrap para gestionar credenciales y preferencias.
- Shortcode `[eco_performance_snapshot]` con formulario wow-effect y resultados en tarjetas.
- Integración con PageSpeed Insights, Website Carbon y Green Web Foundation.
- Capa de caché configurable y modo sandbox con datos ficticios.
- REST API `tew/v1/audit` y `tew/v1/test-credentials` para automatizar auditorías.
- Sistema de insights que genera resumen ejecutivo, hallazgos y acciones priorizadas.
