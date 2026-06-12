# Cedro Server — Replanta Hosting Premium

Servidor Hetzner CCX13 para el plan Cedro de replanta.net.
Hosting WordPress optimizado: WooCommerce, ecommerce, LiteSpeed Cache, WP verde.

## Specs del servidor

| Campo | Valor |
|---|---|
| Provider | Hetzner Cloud |
| Plan | CCX13 (2 vCPU dedicados, 8 GB RAM, 80 GB NVMe) |
| Location | Falkenstein FSN1 (Alemania, 100% hidroeléctrica) |
| OS | Ubuntu 22.04 LTS |
| Panel | CyberPanel + OpenLiteSpeed |
| Email | Servicio externo (MXroute o similar) |
| Backup | CyberPanel Backup V2 → Backblaze B2 |
| Nombre | replanta-cedro-fsn1 |

## Acceso SSH

```powershell
# Conexión al servidor
ssh replanta@<SERVER_IP> -i "$env:USERPROFILE\.ssh\replanta_hetzner"

# Tunnel para CyberPanel (ejecutar antes de abrir el panel)
.\scripts\server-setup\ssh-tunnel.ps1 -ServerIP <SERVER_IP>
# Luego abrir: https://localhost:8090
```

## Estructura del workspace

```
cedro server/
├── scripts/
│   ├── server-setup/     # Setup inicial del servidor
│   ├── cyberpanel/       # Scripts de gestión de sitios
│   └── backups/          # Config y scripts de backup
├── config/
│   ├── firewall/         # Reglas de firewall Hetzner
│   ├── cyberpanel/       # Configuración de CyberPanel
│   └── nginx-ssl/        # Configs SSL extra
├── docs/                 # Documentación de procesos
└── clients/              # Fichas por cliente Cedro
```

## Checklist de puesta en marcha

### Fase 1 — Crear servidor en Hetzner
- [ ] Generar SSH key (`scripts/server-setup/generate-ssh-key.ps1`)
- [ ] Crear servidor CCX13 en FSN1 con Ubuntu 22.04
- [ ] Añadir SSH key pública en Hetzner al crear el servidor
- [ ] Anotar la IP pública del servidor aquí: `SERVER_IP=`

### Fase 2 — Configuración inicial del servidor
- [ ] Conexión SSH inicial como root
- [ ] Ejecutar `scripts/server-setup/01-initial-setup.sh`
- [ ] Crear usuario `replanta` con sudo
- [ ] Deshabilitar login root por SSH
- [ ] Configurar firewall Hetzner (ver `config/firewall/hetzner-firewall.md`)

### Fase 3 — Instalar CyberPanel
- [ ] Ejecutar `scripts/cyberpanel/install-cyberpanel.sh`
- [ ] Activar licencia Premium en el panel
- [ ] Verificar 6 add-ons activos
- [ ] Configurar acceso via SSH tunnel

### Fase 4 — Primer sitio: replanta.net
- [ ] Crear sitio en CyberPanel (PHP 8.3, LSCache)
- [ ] Configurar Cloudflare DNS → IP Hetzner
- [ ] Activar SSL en CyberPanel (Let's Encrypt)
- [ ] Migrar WordPress desde Stablepoint
- [ ] Verificar WooCommerce y plugins activos

### Fase 5 — Infraestructura complementaria
- [ ] Configurar Backup V2 → Backblaze B2 (`scripts/backups/`)
- [ ] Verificar backups automáticos diarios
- [ ] Solicitar verificación Green Web Foundation
- [ ] Actualizar DNS MX a MXroute

### Fase 6 — Primer cliente Cedro
- [ ] Crear cuenta en CyberPanel (ver `docs/nuevo-cliente-cedro.md`)
- [ ] Crear ficha en `clients/<nombre-cliente>/`
- [ ] Onboarding al cliente

## Contactos y cuentas clave

| Servicio | URL panel | Usuario |
|---|---|---|
| Hetzner | cloud.hetzner.com | - |
| CyberPanel | https://localhost:8090 (tunnel) | admin |
| Cloudflare | dash.cloudflare.com | - |
| Backblaze B2 | secure.backblaze.com | - |
| Green Web Foundation | thegreenwebfoundation.org/admin | - |
