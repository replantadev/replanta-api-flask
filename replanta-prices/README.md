# Replanta Prices v1.0.0

Precios dinámicos de hosting y mantenimiento sincronizados con Upmind.  
Shortcodes con detección geográfica por país y moneda local LATAM. Impacto 0 en rendimiento.

## Requisitos

- WordPress 6.0+
- PHP 7.4+
- Plugin GeoPrice (opcional, para geo-detección; funciona sin él defaulting a EUR)

## Instalación

1. Subir la carpeta `replanta-prices/` a `wp-content/plugins/`
2. Activar en Plugins
3. Ir a **Ajustes → Replanta Prices**
4. Configurar el API Token de Upmind (opcional para sync automática)

## Shortcodes

### Grid de precios completo

```
[replanta_pricing type="hosting"]
[replanta_pricing type="mantenimiento"]
```

### Precio inline

```
Desde [replanta_price plan="sauce" period="monthly"] al mes
```

**Parámetros de `[replanta_price]`:**

| Parámetro | Valores | Default |
|-----------|---------|---------|
| `type` | `hosting`, `mantenimiento` | `hosting` |
| `plan` | `sauce`, `roble`, `cedro`, `semilla`, `raiz`, `ecosistema` | — |
| `period` | `monthly`, `annual` | `monthly` |

## Geo-detección

- **EUR**: Europa (EU27 + EEA + GB + Balcanes + Europa del Este)
- **USD**: Norteamérica, páginas en inglés y fallback global
- **LATAM local**: MXN, COP, CLP, ARS, PEN y resto según país, con fallback a USD cuando un plan no tiene precio local declarado
- Compatible con LiteSpeed Cache variando por región y moneda efectiva (`replanta_geo_region`, `replanta_geo_currency`)
- Si GeoPrice no está activo, detecta por cabecera Cloudflare `CF-IPCountry`

## Sincronización con Upmind

- WP-Cron automático cada 6 horas
- Botón "Sincronizar ahora" en admin
- Si la API no responde, se mantienen los precios configurados en admin
- API endpoint: `GET /api/admin/catalogue/products/{pid}` con Bearer token

## Arquitectura

```
replanta-prices/
├── replanta-prices.php              # Main plugin
├── includes/
│   ├── class-geo.php                # Geo-detección EUR/USD
│   ├── class-upmind-api.php         # API client
│   ├── class-price-cache.php        # Cache wp_options + cron
│   ├── class-admin-settings.php     # Settings > Replanta Prices
│   └── class-shortcodes.php         # Shortcodes
├── assets/
│   ├── css/
│   │   ├── pricing-cards.css        # Frontend styles
│   │   └── admin.css                # Admin styles
│   └── js/
│       └── admin.js                 # Admin AJAX sync
└── templates/
    ├── hosting-grid.php             # 3 cards + billing toggle
    └── mantenimiento-grid.php       # 3 cards + tooltips
```

## Changelog

### 1.0.0
- Release inicial
- 6 planes (3 hosting + 3 mantenimiento)
- Shortcodes `[replanta_pricing]` y `[replanta_price]`
- Admin con 2 tabs (Configuración + Productos)
- Sync automática con Upmind cada 6h
- Geo-detección EUR/USD compatible con GeoPrice
