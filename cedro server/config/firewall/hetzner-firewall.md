# Firewall Hetzner — Cedro Server

## Estrategia con IP dinámica

Como la IP doméstica es dinámica, la estrategia es:

- **SSH (22)**: abierto a todo el mundo, pero SOLO con clave SSH (sin passwords). Con ed25519 es seguro aunque los bots lo intenten.
- **CyberPanel (8090) y OLS admin (7080)**: NO expuestos. Acceso via SSH tunnel local.
- **HTTP/HTTPS**: abiertos a todos (necesario para sitios y Let's Encrypt).

Esto elimina la necesidad de actualizar reglas cuando cambia la IP.

## Reglas a crear en Hetzner Cloud > Firewalls

Nombre del firewall: `cedro-cyberpanel-firewall`

### Reglas INBOUND

| Protocolo | Puerto | Origen | Descripcion |
|---|---|---|---|
| TCP | 22 | 0.0.0.0/0, ::/0 | SSH (key only, sin passwords) |
| TCP | 80 | 0.0.0.0/0, ::/0 | HTTP (Let's Encrypt + redirect) |
| TCP | 443 | 0.0.0.0/0, ::/0 | HTTPS (sitios clientes) |

### Puertos NO expuestos (acceso solo via tunnel)

| Puerto | Servicio | Como acceder |
|---|---|---|
| 8090 | CyberPanel admin | SSH tunnel (ver ssh-tunnel.ps1) |
| 7080 | OpenLiteSpeed admin | SSH tunnel |
| 25, 465, 587, 993, 995 | Email | No aplica, usamos email externo |

### Reglas OUTBOUND

Dejar por defecto (allow all outbound).

## Como crear el firewall en Hetzner

1. Ir a cloud.hetzner.com > Firewalls > Create Firewall
2. Nombre: `cedro-cyberpanel-firewall`
3. Añadir las 3 reglas inbound de arriba
4. Guardar
5. Ir al servidor > Actions > Apply Firewall > seleccionar `cedro-cyberpanel-firewall`

## Como acceder a CyberPanel con IP dinámica

Usar el SSH tunnel (ver `../../scripts/server-setup/ssh-tunnel.ps1`):

```powershell
# En PowerShell local
.\scripts\server-setup\ssh-tunnel.ps1 -ServerIP <IP-DEL-SERVER>

# Luego abrir en navegador:
# https://localhost:8090  (CyberPanel)
# https://localhost:7080  (OLS admin)
```

El tunnel funciona desde cualquier ubicacion y con cualquier IP.
