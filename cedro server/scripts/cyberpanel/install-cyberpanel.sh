#!/bin/bash
# Instalacion de CyberPanel + OpenLiteSpeed en Ubuntu 22.04
# Ejecutar como root o con sudo
# Tiempo estimado: 20-30 minutos

set -e

echo "=== Pre-checks ==="
if [ "$EUID" -ne 0 ]; then
    echo "Ejecuta este script como root: sudo bash install-cyberpanel.sh"
    exit 1
fi

OS=$(lsb_release -rs)
if [ "$OS" != "22.04" ]; then
    echo "AVISO: Este script esta probado en Ubuntu 22.04. Version detectada: $OS"
    read -p "Continuar de todas formas? (s/N): " confirm
    [ "$confirm" != "s" ] && exit 1
fi

echo "Sistema: Ubuntu $OS OK"
echo "Memoria RAM: $(free -h | awk '/^Mem:/{print $2}')"
echo "Disco libre: $(df -h / | awk 'NR==2{print $4}')"
echo ""

echo "=== Actualizando sistema antes de instalar ==="
apt update && apt upgrade -y

echo ""
echo "=== Iniciando instalacion CyberPanel ==="
echo ""
echo "El instalador te hara estas preguntas. Responde asi:"
echo ""
echo "  1. CyberPanel version:     1 (OpenLiteSpeed - GRATIS)"
echo "  2. Full service:           n (no necesitamos Postfix/Dovecot, usamos email externo)"
echo "  3. Remote MySQL:           n"
echo "  4. CyberPanel + DNS:       1 (con PowerDNS)"
echo "  5. Install Memcached:      y"
echo "  6. Install Redis:          y"
echo "  7. Install WatchDog:       y"
echo "  8. Admin password:         [ELIGE UNA CONTRASENA FUERTE Y GUARDALA]"
echo ""
read -p "Listo para instalar? (s/N): " go
[ "$go" != "s" ] && exit 0

sh <(curl https://cyberpanel.net/install.sh || wget -O - https://cyberpanel.net/install.sh)

echo ""
echo "======================================================"
echo "  CyberPanel instalado."
echo ""
echo "  Acceso via SSH tunnel desde tu maquina local:"
echo "  Ejecuta en PowerShell:"
echo "    .\\scripts\\server-setup\\ssh-tunnel.ps1 -ServerIP $(curl -s ifconfig.me)"
echo ""
echo "  Luego abre: https://localhost:8090"
echo "  Usuario: admin"
echo "  Pass: la que elegiste durante la instalacion"
echo ""
echo "  SIGUIENTE PASO: Activar licencia Premium en Settings > License"
echo "======================================================"
