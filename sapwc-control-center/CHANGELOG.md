# Changelog

Todos los cambios notables en **SAP Woo Control Center** se documentan en este archivo.

El formato está basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.0.0/),
y este proyecto adhiere a [Versionado Semántico](https://semver.org/lang/es/).

---
## [1.2.4] - 2026-05-18

### Corregido

- **HMAC secret no propagado a sitios existentes**: `sapwcc_save_settings` ahora empuja el HMAC secret a todos los sitios registrados al guardar configuración, no solo a los recién añadidos. Soluciona el admin notice persistente en instalaciones registradas antes de la v1.2.3.

---
## [1.2.3] - 2026-05-14

### Seguridad

- **Auto-generación de HMAC secret**: en `plugins_loaded` (priority 1) se genera un secret aleatorio cifrado con `SAPWCC_Sites::encrypt()` si la constante usa el valor por defecto. Garantiza que cada instancia del CC tenga un secret único sin requerir configuración en `wp-config.php`.
- **`sapwcc_get_flags_hmac_secret()`**: helper con cadena de prioridad: constante `wp-config` → opción cifrada en `wp_options` → vacío. Usado por `SAPWCC_Flags::write()` para firmar `flags.json`.
- **Propagación de HMAC secret al añadir sitio**: `sapwcc_add_site` empuja el secret via `POST /control/set-flags-hmac-secret` al nuevo sitio, cerrando la cadena CC → cliente sin paso manual.
- **Admin notice con scope de página**: el aviso de HMAC solo aparece en páginas `sapwcc`, no en todo el panel de administración.
- **Admin notice condicionado**: suprimido si el secret ya existe en `wp_options` (post-generación automática).
- **`wp_unslash()` en `sapwcc_assign_plan`**: añadido para consistencia con el resto del archivo (patrón PHPCS).
- **Nuevo endpoint en whitelist y audit map**: `control/set-flags-hmac-secret` añadido a `$allowed_endpoints`, `$audit_map`, `ACTION_LABELS` e `ACTION_ICONS`.
- **`SAPWCC_LATEST_SUITE_VERSION`**: actualizado a `2.15.6`.

---
## [1.2.2] - 2026-05-12

### Seguridad

- **GitHub token cifrado**: guardado como `AES-256-CBC + random_bytes(16)` en `wp_options`; descifrado solo en tiempo de uso.
- **Rate limiting en todos los endpoints `/control/*`**: aplicado via `SAPWC_REST_API::check_rate_limit()` (ahora `public static`).
- **IP allowlist en endpoints destructivos**: `check_ip()` aplicado a `run_cron`, `toggle_maintenance`, `rotate_secret`, `run_update`, `set_cc_ip`, `set_flags_hmac_secret`.
- **`random_bytes(16)` para IVs de cifrado**: migrado desde `openssl_random_pseudo_bytes` en `SAPWCC_Sites::encrypt()`.
- **Admin notice para HMAC secret por defecto**: avisa cuando la constante usa el valor público.
- **HTTPS forzado al añadir sitio**: URLs sin `https://` son rechazadas.
- **Propagación de IP del CC a todos los sitios**: `sapwcc_save_settings` llama a `POST /control/set-cc-ip` en cada sitio registrado.
- **Nuevo endpoint `/control/set-cc-ip`**: permite al CC sincronizar su IP con los sites cliente.
- **Botón "Rotar Secret"**: UI en el dashboard para rotar el `X-SAPWC-Secret` de cada sitio.

---
## [1.2.1] - 2026-04-09

### Añadido

- Alertas proactivas (documentación).

---
## [1.2.0] - 2026-03-28

### Añadido

- Release inicial del Control Center.
