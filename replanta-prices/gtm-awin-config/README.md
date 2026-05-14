# Configuración GTM para Awin Tracking

## Contenedor: GTM-M6WJKQ7K (clientes.replanta.net)

Esta configuración permite trackear conversiones de Awin cuando:
1. El usuario llega a replanta.net con `?awc=XXXXX`
2. Navega al checkout en clientes.replanta.net
3. Completa una compra

## Archivos

- `gtm-awin-import.json` - Configuración importable en GTM
- `manual-setup.md` - Instrucciones paso a paso si prefieres configurar manualmente

## Cómo importar

1. Ve a GTM → Administrar → Importar contenedor
2. Selecciona `gtm-awin-import.json`
3. Elige "Combinar" → "Renombrar etiquetas, activadores y variables en conflicto"
4. Revisa y publica

## Variables creadas

| Variable | Tipo | Descripción |
|----------|------|-------------|
| AWC - Cookie Value | 1st Party Cookie | Lee cookie `replanta_awin_awc` |
| AWC - URL Parameter | URL Variable | Lee `?awc=` del URL |
| AWC - Combined | Custom JS | Devuelve AWC de URL o cookie |
| Ecommerce - Transaction ID | Data Layer | ID de transacción |
| Ecommerce - Revenue | Data Layer | Importe de la compra |
| Ecommerce - Currency | Data Layer | Moneda (EUR) |

## Tags creados

| Tag | Tipo | Trigger |
|-----|------|---------|
| Awin - Set Cookie | Custom HTML | All Pages (si hay AWC en URL) |
| Awin - Conversion Pixel | Custom HTML | Purchase Event |

## Triggers creados

| Trigger | Tipo | Condición |
|---------|------|-----------|
| AWC Present in URL | Page View | URL contains awc= |
| Purchase Event | Custom Event | Event = purchase |

## Testing

1. Visita: `https://clientes.replanta.net/store/product?awc=TEST123`
2. Verifica que la cookie `replanta_awin_awc` se crea
3. Completa una compra
4. Verifica en Network tab que se dispara request a `awin1.com/sread.php`

## Notas importantes

- El pixel solo se dispara si existe AWC (evita falsos positivos)
- La cookie usa dominio `.replanta.net` para compartir entre subdominios
- Duración: 90 días (igual que la cookie de WordPress)
