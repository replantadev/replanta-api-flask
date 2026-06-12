#!/bin/bash
# Configuracion inicial del servidor Cedro - Hetzner CCX13
# Ejecutar como root justo despues del primer arranque:
#   bash 01-initial-setup.sh
# Tiempo estimado: 5-10 minutos

set -e

SERVER_HOSTNAME="replanta-cedro-fsn1"
ADMIN_USER="replanta"
TIMEZONE="Europe/Madrid"

echo "=== [1/6] Actualizando sistema ==="
apt update && apt upgrade -y

echo "=== [2/6] Instalando utilidades basicas ==="
apt install -y \
    curl wget git vim nano \
    htop unzip \
    fail2ban \
    ufw

echo "=== [3/6] Configurando hostname y timezone ==="
hostnamectl set-hostname "$SERVER_HOSTNAME"
timedatectl set-timezone "$TIMEZONE"
echo "Hostname: $(hostname)"
echo "Timezone: $(timedatectl | grep 'Time zone')"

echo "=== [4/6] Creando usuario admin: $ADMIN_USER ==="
if id "$ADMIN_USER" &>/dev/null; then
    echo "Usuario $ADMIN_USER ya existe, saltando..."
else
    adduser --gecos "" "$ADMIN_USER"
    usermod -aG sudo "$ADMIN_USER"
fi

# Copiar authorized_keys de root al nuevo usuario
mkdir -p /home/$ADMIN_USER/.ssh
if [ -f /root/.ssh/authorized_keys ]; then
    cp /root/.ssh/authorized_keys /home/$ADMIN_USER/.ssh/
    chown -R $ADMIN_USER:$ADMIN_USER /home/$ADMIN_USER/.ssh
    chmod 700 /home/$ADMIN_USER/.ssh
    chmod 600 /home/$ADMIN_USER/.ssh/authorized_keys
    echo "SSH key copiada al usuario $ADMIN_USER"
fi

echo "=== [5/6] Configurando SSH (desactivar root login y passwords) ==="
# Backup del config original
cp /etc/ssh/sshd_config /etc/ssh/sshd_config.backup

# Aplicar configuracion segura
sed -i 's/^#\?PermitRootLogin.*/PermitRootLogin no/' /etc/ssh/sshd_config
sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config
sed -i 's/^#\?PubkeyAuthentication.*/PubkeyAuthentication yes/' /etc/ssh/sshd_config

echo "Reiniciando SSH..."
systemctl restart sshd
echo "SSH configurado. Root login desactivado. Solo clave SSH permitida."

echo "=== [6/6] Configurando fail2ban ==="
systemctl enable fail2ban
systemctl start fail2ban

echo ""
echo "======================================================"
echo "  Setup inicial completado en $(hostname)"
echo "  Timezone: $(timedatectl | grep 'Time zone' | awk '{print $3}')"
echo ""
echo "  IMPORTANTE: Abre una NUEVA terminal y verifica que"
echo "  puedes conectar como '$ADMIN_USER' antes de cerrar esta."
echo ""
echo "  ssh $ADMIN_USER@$(curl -s ifconfig.me) -i ~/.ssh/replanta_hetzner"
echo "======================================================"
