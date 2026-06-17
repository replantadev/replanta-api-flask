# Changelog

Todos los cambios notables en **SAP Woo Control Center** se documentan en este archivo.

El formato estÃ¡ basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere a [Versionado SemÃ¡ntico](https://semver.org/lang/es/).

---
## [1.2.47] - 2026-06-17

### Añadido

- **Botón "Exportado #N" en alertas classified_failures del Vigilante** — para cada pedido en una alerta de error de sincronización (`failure_type_*`) aparece el botón que llama a `control/mark-exported` del sitio remoto. Marca el pedido como exportado manualmente, limpia el estado de reintentos y lo excluye de futuros crons. Útil cuando el pedido ya existe en SAP (creado por el operador) y el Vigilante sigue alertando por el error anterior. Requiere SAP Woo Suite >= 2.19.3.

---
## [1.2.45] - 2026-06-17

### Corregido

- **`cURL error 28` en health check cuando SAP está lento** — el endpoint `/health` hace login a SAP (timeout 20s por defecto); el CC esperaba solo 10s → timeout falso aunque el sitio estuviera OK. Fix: timeout del CC en health elevado a 25s. El timeout del login SAP en contexto health se limita a 5s via `sapwc_api_login_args` filter (suite v2.19.2), garantizando que la respuesta siempre llegue antes del timeout del CC.

---
## [1.2.44] - 2026-06-16

### Corregido

- **Card del dashboard mostraba versión antigua tras actualización exitosa** — después de `control/update`, el PHP hacía un re-ping inmediato a `/health` del remote, pero opcache del servidor remoto todavía tenía el header de versión antigua en caché. El transient quedaba con la versión vieja, y el `location.reload()` inmediato leía ese transient malo. Fix en dos partes: (1) eliminado el `fetch_health()` síncrono post-update del handler PHP (solo se borra el transient); (2) el JS espera 3,5 s antes de disparar `sapwcc_check_health` (nueva petición real al remote, ya con opcache renovado) y luego recarga — la card muestra la versión correcta.

---
## [1.2.35] - 2026-05-26

### Añadido

- **Resolver/Revertir tareas SAP desde el Vigilante del CC** — Sin necesidad de entrar al WP-Admin del cliente. En cada alerta con `audience=sap_user` aparece el botón **"✓ Resolver"** (pide nota opcional). Si ya está resuelta, aparece **↩ Deshacer** (válido dentro de la ventana de 72h del remoto). Los endpoints `control/resolve-task` y `control/unresolve-task` están whitelisted y auditados.

### Requisito

- Necesita SAP Woo Suite ≥ **2.18.2** en el sitio remoto (que añade los endpoints REST).

---
## [1.2.34] - 2026-05-26

### Corregido

- **`SAPWCC_Flags::get_path()` descartaba la ruta guardada** si el directorio padre no existía físicamente en el filesystem del CC, cayendo al `DEFAULT_PATH` (idéntico). El usuario configuraba la ruta correcta en Settings, pulsaba Guardar, y el aviso seguía apareciendo porque la opción se ignoraba silenciosamente. Ahora se respeta siempre que esté no vacía; si el usuario guardó solo el directorio, se autocompleta con `flags.json` al final.

### Añadido

- **`SAPWCC_Flags::diagnose()`**: devuelve `path/exists/readable/writable_dir/error` para diagnosticar por qué `read()` devuelve vacío.
- **Aviso del dashboard mejorado**: en lugar de un mensaje genérico, muestra ruta + estado existencia/lectura/escritura + sugerencia accionable (`mkdir -p ...`).

---
## [1.2.33] - 2026-05-26

### Corregido

- **Versión de plugin obsoleta tras update**: tras `POST /control/update` exitoso, el handler AJAX ahora borra el transient `sapwcc_health_{site_key}` y dispara un re-ping inmediato (`SAPWCC_Sites::fetch_health()`). Antes el TTL de 5 min mantenía la versión vieja en la card hasta el siguiente ciclo.
- **Card de plan incoherente**: las dos filas "Asignar Plan" (selector + check) y "Plan" (texto coloreado) se fusionan en una sola fila `sapwcc-plan-row` que muestra: pill con el plan efectivo (el que reporta el cliente vía health) + selector compacto para reasignar + icono ⚠ si el plan asignado en `flags.json` diverge del efectivo.

### Estilos

- Nuevos: `.sapwcc-plan-row`, `.sapwcc-plan-pill`, `.sapwcc-plan-pill--none`, `.sapwcc-plan-select-inline`.

---
## [1.2.5] - 2026-05-18

### Actualizado

- **`SAPWCC_LATEST_SUITE_VERSION`**: actualizado a `2.15.11` (fix preview categorÃ­as + suprimir aviso HMAC en CC co-ubicado).

---
## [1.2.4] - 2026-05-18

### Corregido

- **HMAC secret no propagado a sitios existentes**: `sapwcc_save_settings` ahora empuja el HMAC secret a todos los sitios registrados al guardar configuraciÃ³n, no solo a los reciÃ©n aÃ±adidos. Soluciona el admin notice persistente en instalaciones registradas antes de la v1.2.3.

---
## [1.2.3] - 2026-05-14

### Seguridad

- **Auto-generaciÃ³n de HMAC secret**: en `plugins_loaded` (priority 1) se genera un secret aleatorio cifrado con `SAPWCC_Sites::encrypt()` si la constante usa el valor por defecto. Garantiza que cada instancia del CC tenga un secret Ãºnico sin requerir configuraciÃ³n en `wp-config.php`.
- **`sapwcc_get_flags_hmac_secret()`**: helper con cadena de prioridad: constante `wp-config` â†’ opciÃ³n cifrada en `wp_options` â†’ vacÃ­o. Usado por `SAPWCC_Flags::write()` para firmar `flags.json`.
- **PropagaciÃ³n de HMAC secret al aÃ±adir sitio**: `sapwcc_add_site` empuja el secret via `POST /control/set-flags-hmac-secret` al nuevo sitio, cerrando la cadena CC â†’ cliente sin paso manual.
- **Admin notice con scope de pÃ¡gina**: el aviso de HMAC solo aparece en pÃ¡ginas `sapwcc`, no en todo el panel de administraciÃ³n.
- **Admin notice condicionado**: suprimido si el secret ya existe en `wp_options` (post-generaciÃ³n automÃ¡tica).
- **`wp_unslash()` en `sapwcc_assign_plan`**: aÃ±adido para consistencia con el resto del archivo (patrÃ³n PHPCS).
- **Nuevo endpoint en whitelist y audit map**: `control/set-flags-hmac-secret` aÃ±adido a `$allowed_endpoints`, `$audit_map`, `ACTION_LABELS` e `ACTION_ICONS`.
- **`SAPWCC_LATEST_SUITE_VERSION`**: actualizado a `2.15.6`.

---
## [1.2.2] - 2026-05-12

### Seguridad

- **GitHub token cifrado**: guardado como `AES-256-CBC + random_bytes(16)` en `wp_options`; descifrado solo en tiempo de uso.
- **Rate limiting en todos los endpoints `/control/*`**: aplicado via `SAPWC_REST_API::check_rate_limit()` (ahora `public static`).
- **IP allowlist en endpoints destructivos**: `check_ip()` aplicado a `run_cron`, `toggle_maintenance`, `rotate_secret`, `run_update`, `set_cc_ip`, `set_flags_hmac_secret`.
- **`random_bytes(16)` para IVs de cifrado**: migrado desde `openssl_random_pseudo_bytes` en `SAPWCC_Sites::encrypt()`.
- **Admin notice para HMAC secret por defecto**: avisa cuando la constante usa el valor pÃºblico.
- **HTTPS forzado al aÃ±adir sitio**: URLs sin `https://` son rechazadas.
- **PropagaciÃ³n de IP del CC a todos los sitios**: `sapwcc_save_settings` llama a `POST /control/set-cc-ip` en cada sitio registrado.
- **Nuevo endpoint `/control/set-cc-ip`**: permite al CC sincronizar su IP con los sites cliente.
- **BotÃ³n "Rotar Secret"**: UI en el dashboard para rotar el `X-SAPWC-Secret` de cada sitio.

---
## [1.2.1] - 2026-04-09

### AÃ±adido

- Alertas proactivas (documentaciÃ³n).

---
## [1.2.0] - 2026-03-28

### AÃ±adido

- Release inicial del Control Center.
