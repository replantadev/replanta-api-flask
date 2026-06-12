#!/bin/bash
# Crea un nuevo sitio WordPress en CyberPanel via CLI
# Uso: bash new-wp-site.sh dominio.com usuario_cp email_admin
# Ejemplo: bash new-wp-site.sh cliente.com cliente_cp admin@cliente.com

DOMAIN=$1
CP_USER=$2
ADMIN_EMAIL=$3
PHP_VERSION="PHP 8.3"

if [ -z "$DOMAIN" ] || [ -z "$CP_USER" ] || [ -z "$ADMIN_EMAIL" ]; then
    echo "Uso: bash new-wp-site.sh <dominio> <usuario_cyberpanel> <email_admin>"
    exit 1
fi

echo "=== Creando sitio: $DOMAIN ==="

# Crear el sitio en CyberPanel
cyberpanel createWebsite \
    --domainName "$DOMAIN" \
    --ownerEmail "$ADMIN_EMAIL" \
    --phpVersion "$PHP_VERSION" \
    --package "Default" \
    --websiteOwner "$CP_USER"

echo "Sitio $DOMAIN creado."

# Instalar WordPress
echo "=== Instalando WordPress en $DOMAIN ==="
cyberpanel installWordPress \
    --domainName "$DOMAIN" \
    --title "$DOMAIN" \
    --adminUser "wpadmin" \
    --adminPassword "$(openssl rand -base64 16)" \
    --adminEmail "$ADMIN_EMAIL"

echo ""
echo "=== Activando SSL (Let's Encrypt) ==="
cyberpanel issueSSL --domainName "$DOMAIN"

echo ""
echo "======================================================"
echo "  Sitio $DOMAIN listo."
echo "  WP admin: https://$DOMAIN/wp-admin"
echo "  Email admin: $ADMIN_EMAIL"
echo ""
echo "  SIGUIENTE: Instalar LSCache plugin en WP y activar."
echo "======================================================"
