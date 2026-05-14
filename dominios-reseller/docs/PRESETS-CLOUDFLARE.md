# Presets Cloudflare - Documentación Técnica

## Versión: 2.0 (Diciembre 2025)

---

## 📋 Resumen de Presets

| Preset | Uso | Security Level | SSL | Cache | Rocket Loader |
|--------|-----|----------------|-----|-------|---------------|
| `wp` | WordPress estándar | medium | strict | aggressive | OFF |
| `woo` | WooCommerce | high | strict | standard | OFF |

---

## 🔵 Preset: WordPress Básico (`wp`)

### Casos de uso:
- Blogs
- Sitios corporativos
- Portfolios
- Landing pages
- Sitios informativos

### Settings API

```json
{
  "settings": {
    "http3": "on",
    "brotli": "on",
    "0rtt": "on",
    "early_hints": "on",
    "rocket_loader": "off",
    "always_use_https": "on",
    "ssl": "strict",
    "min_tls_version": "1.2",
    "automatic_https_rewrites": "on",
    "opportunistic_encryption": "on",
    "security_level": "medium",
    "challenge_ttl": 1800,
    "browser_check": "on",
    "email_obfuscation": "on",
    "hotlink_protection": "on",
    "ip_geolocation": "on",
    "browser_cache_ttl": 14400,
    "cache_level": "aggressive",
    "development_mode": "off",
    "always_online": "on"
  }
}
```

### Cache Rules (Rulesets API)

```json
{
  "cache_rules": [
    {
      "name": "Bypass WordPress Admin",
      "expression": "(http.request.uri.path contains \"/wp-admin\") or (http.request.uri.path contains \"/wp-login.php\") or (http.request.uri.path contains \"/wp-cron.php\")",
      "action": "set_cache_settings",
      "action_parameters": {
        "cache": false
      }
    },
    {
      "name": "Bypass WP AJAX y REST",
      "expression": "(http.request.uri.path contains \"/wp-json\") or (http.request.uri.path contains \"admin-ajax.php\") or (http.request.uri.path contains \"wp-comments-post.php\")",
      "action": "set_cache_settings",
      "action_parameters": {
        "cache": false
      }
    },
    {
      "name": "Bypass Previews y Feeds",
      "expression": "(http.request.uri.query contains \"preview=true\") or (http.request.uri.path contains \"/feed\") or (http.request.uri.query contains \"s=\")",
      "action": "set_cache_settings",
      "action_parameters": {
        "cache": false
      }
    },
    {
      "name": "Cache Estaticos 30 dias",
      "expression": "(http.request.uri.path.extension in {\"css\" \"js\" \"jpg\" \"jpeg\" \"png\" \"gif\" \"webp\" \"avif\" \"ico\" \"svg\" \"woff\" \"woff2\" \"ttf\" \"eot\" \"otf\"}) and not (http.request.uri.path contains \"/wp-admin\")",
      "action": "set_cache_settings",
      "action_parameters": {
        "cache": true,
        "edge_ttl": {
          "mode": "override_origin",
          "default": 604800
        }
      }
    }
  ]
}
```

---

## 🟠 Preset: WooCommerce (`woo`)

### Casos de uso:
- Tiendas online
- E-commerce
- Marketplaces
- Sitios con pasarelas de pago

### Settings API

```json
{
  "settings": {
    "http3": "on",
    "brotli": "on",
    "0rtt": "on",
    "early_hints": "on",
    "rocket_loader": "off",
    "always_use_https": "on",
    "ssl": "strict",
    "min_tls_version": "1.2",
    "automatic_https_rewrites": "on",
    "opportunistic_encryption": "on",
    "security_level": "high",
    "challenge_ttl": 3600,
    "browser_check": "on",
    "email_obfuscation": "on",
    "hotlink_protection": "on",
    "ip_geolocation": "on",
    "browser_cache_ttl": 7200,
    "cache_level": "standard",
    "development_mode": "off",
    "always_online": "off"
  }
}
```

### Cache Rules (Rulesets API)

```json
{
  "cache_rules": [
    {
      "name": "Bypass WordPress Admin",
      "expression": "(http.request.uri.path contains \"/wp-admin\") or (http.request.uri.path contains \"/wp-login.php\") or (http.request.uri.path contains \"/wp-cron.php\")",
      "action": "set_cache_settings",
      "action_parameters": { "cache": false }
    },
    {
      "name": "Bypass WP AJAX y REST",
      "expression": "(http.request.uri.path contains \"/wp-json\") or (http.request.uri.path contains \"admin-ajax.php\")",
      "action": "set_cache_settings",
      "action_parameters": { "cache": false }
    },
    {
      "name": "Bypass WooCommerce Dinamico",
      "expression": "(http.request.uri.path contains \"/cart\") or (http.request.uri.path contains \"/carrito\") or (http.request.uri.path contains \"/checkout\") or (http.request.uri.path contains \"/finalizar-compra\") or (http.request.uri.path contains \"/my-account\") or (http.request.uri.path contains \"/mi-cuenta\")",
      "action": "set_cache_settings",
      "action_parameters": { "cache": false }
    },
    {
      "name": "Bypass WC-AJAX",
      "expression": "(http.request.uri.query contains \"wc-ajax\") or (http.request.uri.query contains \"add-to-cart\") or (http.request.uri.query contains \"remove_item\")",
      "action": "set_cache_settings",
      "action_parameters": { "cache": false }
    },
    {
      "name": "Bypass Usuarios Logueados WC",
      "expression": "(http.cookie contains \"woocommerce_cart_hash\") or (http.cookie contains \"woocommerce_items_in_cart\") or (http.cookie contains \"wp_woocommerce_session\") or (http.cookie contains \"wordpress_logged_in\")",
      "action": "set_cache_settings",
      "action_parameters": { "cache": false }
    },
    {
      "name": "Bypass Pagos y Webhooks",
      "expression": "(http.request.uri.path contains \"/wc-api\") or (http.request.uri.path contains \"paypal\") or (http.request.uri.path contains \"stripe\") or (http.request.uri.path contains \"redsys\") or (http.request.uri.path contains \"/payment\")",
      "action": "set_cache_settings",
      "action_parameters": { "cache": false }
    },
    {
      "name": "Cache Imagenes Productos",
      "expression": "(http.request.uri.path.extension in {\"jpg\" \"jpeg\" \"png\" \"gif\" \"webp\" \"avif\"}) and (http.request.uri.path contains \"/uploads/\")",
      "action": "set_cache_settings",
      "action_parameters": {
        "cache": true,
        "edge_ttl": { "mode": "respect_origin", "default": 86400 }
      }
    },
    {
      "name": "Cache Assets Estaticos",
      "expression": "(http.request.uri.path.extension in {\"css\" \"js\" \"woff\" \"woff2\" \"ttf\" \"eot\" \"otf\" \"ico\" \"svg\"}) and not (http.request.uri.path contains \"/wp-admin\")",
      "action": "set_cache_settings",
      "action_parameters": {
        "cache": true,
        "edge_ttl": { "mode": "override_origin", "default": 604800 }
      }
    }
  ]
}
```

---

## 🔧 APIs de Cloudflare Utilizadas

### 1. Zone Settings API
```
PATCH https://api.cloudflare.com/client/v4/zones/{zone_id}/settings/{setting_name}
Body: { "value": "on" }
```

### 2. Rulesets API (Cache Rules)
```
# Listar rulesets
GET https://api.cloudflare.com/client/v4/zones/{zone_id}/rulesets

# Crear ruleset de cache
POST https://api.cloudflare.com/client/v4/zones/{zone_id}/rulesets
Body: {
  "name": "Cache Rules",
  "kind": "zone",
  "phase": "http_request_cache_settings",
  "rules": [...]
}

# Añadir regla a ruleset existente
POST https://api.cloudflare.com/client/v4/zones/{zone_id}/rulesets/{ruleset_id}/rules
```

---

## ⚠️ Problemas Comunes y Soluciones

### 1. Rocket Loader rompe JavaScript
**Problema:** WooCommerce checkout deja de funcionar
**Solución:** `rocket_loader: "off"` SIEMPRE

### 2. Cache de páginas dinámicas
**Problema:** Carrito muestra datos de otro usuario
**Solución:** Bypass por cookies WooCommerce:
```
http.cookie contains "woocommerce_cart_hash"
```

### 3. Settings solo disponibles en Pro
**Problema:** `polish`, `mirage`, `minify` fallan en Free
**Solución:** El worker detecta y los marca como "skipped (requiere Pro)"

### 4. SSL "Full" vs "Full (Strict)"
**Problema:** Error 525/526 con certificados inválidos
**Solución:** 
- Usar `ssl: "full"` si el origen NO tiene cert válido
- Usar `ssl: "strict"` si el origen SÍ tiene cert válido (recomendado)

### 5. Pasarelas de pago bloqueadas
**Problema:** PayPal/Stripe no completa callback
**Solución:** Bypass `/wc-api`, `/payment`, y endpoints de pasarelas

---

## 📊 Comparativa Rápida

| Aspecto | WordPress | WooCommerce |
|---------|-----------|-------------|
| `rocket_loader` | ❌ OFF | ❌ OFF |
| `minify.js` | ✅ ON | ❌ OFF |
| `security_level` | medium | high |
| `browser_cache_ttl` | 4h | 2h |
| `cache_level` | aggressive | standard |
| `always_online` | ✅ ON | ❌ OFF |
| Cache rules | 4 | 8 |

---

## 🔄 Flujo de Aplicación

```
1. Usuario selecciona preset (wp/woo)
   ↓
2. Worker obtiene payload de DB
   ↓
3. Validación del payload
   ↓
4. Aplicar settings (idempotente)
   - Si ya tiene el valor → skip
   - Si error por plan → skip (degradación)
   - Si error real → partial
   ↓
5. Aplicar cache rules
   - Si regla existe (mismo nombre) → skip
   - Si error por plan → registrar degradación
   ↓
6. Log resultado
   - success: Todo aplicado
   - partial: Algunos settings/rules fallaron
   - error: Fallo crítico
```

---

## 🆕 Añadir Nuevo Preset

1. Editar `class-onboarding-db.php`:
```php
$custom_preset = [
    'settings' => [...],
    'cache_rules' => [...],
    'notes' => 'Descripción...'
];

$wpdb->insert($table, [
    'preset_key'  => 'custom',
    'name'        => 'Mi Preset Custom',
    'description' => 'Para casos especiales',
    'payload'     => json_encode($custom_preset),
    'is_default'  => 0
]);
```

2. El selector de preset en UI lo mostrará automáticamente.

---

## 📝 Notas de Versión

### v2.0 (Diciembre 2025)
- ✅ Añadido `0rtt`, `early_hints`
- ✅ Bypass por cookies WooCommerce
- ✅ Soporte ES: `/carrito`, `/mi-cuenta`, `/finalizar-compra`
- ✅ Bypass pasarelas: PayPal, Stripe, Redsys
- ✅ Detección automática de settings Pro-only
- ✅ Auto-actualización de presets v1.0 → v2.0

### v1.0 (Original)
- Settings básicos SSL, cache, seguridad
- 2 cache rules para WP, 4 para WooCommerce
