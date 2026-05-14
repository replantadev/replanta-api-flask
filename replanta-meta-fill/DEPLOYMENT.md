# 📦 Instrucciones de Deployment - Replanta Meta Fill

## 🎯 Preparación para Producción

### 1. Verificar Archivos

Asegúrate de que tienes todos los archivos necesarios:

```
replanta-meta-fill/
├── replanta-meta-fill.php        ✅ Archivo principal
├── README.md                      ✅ Documentación
├── CHANGELOG.md                   ✅ Historial versiones
├── QUICKSTART.md                  ✅ Guía rápida
├── .gitignore                     ✅ Git ignore
├── includes/
│   ├── class-content-crawler.php  ✅
│   ├── class-openai-handler.php   ✅
│   ├── class-admin-columns.php    ✅
│   ├── class-ajax-handler.php     ✅
│   └── class-admin-settings.php   ✅
└── assets/
    ├── css/
    │   └── admin.css              ✅
    └── js/
        └── admin.js               ✅
```

### 2. Crear ZIP para Distribución

#### Windows (PowerShell)
```powershell
# Desde la carpeta padre (repos/)
Compress-Archive -Path .\replanta-meta-fill\ -DestinationPath .\replanta-meta-fill-v1.0.0.zip
```

#### Linux/Mac
```bash
# Desde la carpeta padre
zip -r replanta-meta-fill-v1.0.0.zip replanta-meta-fill/
```

### 3. Verificar ZIP

El ZIP debe contener:
- Carpeta raíz: `replanta-meta-fill/`
- Todos los archivos PHP
- Carpetas `includes/` y `assets/` completas
- Archivos de documentación

**NO debe contener**:
- `.git/`
- `node_modules/`
- `vendor/` (si usas composer)
- Archivos temporales (.tmp, .bak, etc.)

---

## 🚀 Instalación en WordPress

### Método 1: Subir ZIP via Admin

1. Ir a **Plugins > Añadir nuevo**
2. Clic en **Subir plugin**
3. Seleccionar `replanta-meta-fill-v1.0.0.zip`
4. Clic en **Instalar ahora**
5. Clic en **Activar**

### Método 2: FTP/SFTP

1. Descomprimir el ZIP
2. Subir carpeta `replanta-meta-fill/` a `/wp-content/plugins/`
3. En WordPress, ir a **Plugins**
4. Activar **Replanta Meta Fill**

### Método 3: SSH/CLI

```bash
# Conectar via SSH al servidor
ssh usuario@servidor.com

# Ir a plugins
cd /ruta/a/wordpress/wp-content/plugins/

# Subir y descomprimir
unzip replanta-meta-fill-v1.0.0.zip

# Activar via WP-CLI (opcional)
wp plugin activate replanta-meta-fill
```

---

## ⚙️ Configuración Post-Instalación

### 1. Configuración Básica

```
Meta Fill > Configuración
├── OpenAI API Key: [tu-api-key]
├── Modelo: gpt-4o-mini
├── Creatividad: 0.7
├── Longitud máxima: 155
└── Guardar Configuración
```

### 2. Validar Setup

```
1. Meta Fill > Configuración
2. Verificar "Estado del Sistema":
   ✅ Plugin SEO detectado: [tu plugin]
   ✅ Estado OpenAI: Configurado
   ✅ Versión del plugin: 1.0.0
```

### 3. Probar con Post de Prueba

```
1. Crear post de prueba
2. Ir a Entradas
3. Verificar columna "Meta" aparece
4. Clic en "Generar"
5. Verificar meta se crea correctamente
```

---

## 🔄 Actualización desde Versión Anterior

### Backup Previo

```bash
# Backup de plugin actual
cp -r /wp-content/plugins/replanta-meta-fill /backups/replanta-meta-fill-backup-$(date +%Y%m%d)

# Backup de base de datos (opciones)
wp db export backup-$(date +%Y%m%d).sql
```

### Actualizar Plugin

**Método Seguro**:
1. Desactivar plugin actual
2. Eliminar carpeta antigua
3. Subir nueva versión
4. Activar plugin

**Método Directo**:
1. Sobrescribir archivos vía FTP/SSH
2. WordPress detectará cambios
3. Refrescar página de plugins

### Verificar Actualización

```
1. Meta Fill > Configuración
2. Verificar "Versión del plugin: [nueva-versión]"
3. Revisar que configuración se mantuvo
4. Probar generación de meta
```

---

## 🌐 Deployment Multi-Sitio

### Para WordPress Multisite

1. **Subir plugin a `/wp-content/plugins/`**
2. **Network Activate** (opcional):
   ```bash
   wp plugin activate replanta-meta-fill --network
   ```
3. **Configurar en cada sitio**:
   - Cada sitio necesita su propia API key
   - O compartir API key (configurar en primer sitio)

### Para Múltiples Instalaciones

**Opción A: Manual**
- Subir plugin a cada instalación
- Configurar independientemente

**Opción B: Script de Deployment**
```bash
#!/bin/bash
# deploy-to-all.sh

SITES=(
  "usuario1@servidor1.com:/path/to/wp1"
  "usuario2@servidor2.com:/path/to/wp2"
)

for site in "${SITES[@]}"; do
  scp -r replanta-meta-fill "$site/wp-content/plugins/"
  echo "Deployed to $site"
done
```

---

## 🔐 Configuración de Seguridad

### 1. Proteger API Key

La API key se guarda en `wp_options`:
```sql
-- Verificar en base de datos
SELECT option_value FROM wp_options 
WHERE option_name = 'replanta_meta_fill_options';
```

**Recomendación**: 
- No compartir backup de BD públicamente
- Usar `.htaccess` para proteger wp-config.php

### 2. Permisos de Archivos

```bash
# Permisos recomendados
find replanta-meta-fill/ -type f -exec chmod 644 {} \;
find replanta-meta-fill/ -type d -exec chmod 755 {} \;
```

### 3. Firewall/WAF

Si usas Cloudflare/WAF:
- Permitir conexiones a `api.openai.com`
- Whitelist IP del servidor en OpenAI (si es necesario)

---

## 📊 Monitorización Post-Deployment

### 1. Verificar Logs

```bash
# PHP error log
tail -f /var/log/php-errors.log | grep "Replanta Meta Fill"

# WP Debug log (si WP_DEBUG está activado)
tail -f /wp-content/debug.log | grep "Replanta Meta Fill"
```

### 2. Métricas a Monitorizar

- **Generaciones exitosas vs errores** (en logs de plugin)
- **Tiempo de respuesta** de OpenAI (debería ser <5s)
- **Uso de API** en OpenAI Platform
- **Coste acumulado** (revisar mensualmente)

### 3. Health Check

```bash
# Verificar plugin activo
wp plugin list | grep replanta-meta-fill

# Verificar opciones configuradas
wp option get replanta_meta_fill_options

# Test de generación (manual via admin)
```

---

## 🔄 Rollback en Caso de Problemas

### Si algo falla:

1. **Desactivar plugin**:
   ```bash
   wp plugin deactivate replanta-meta-fill
   ```

2. **Restaurar backup**:
   ```bash
   rm -rf /wp-content/plugins/replanta-meta-fill
   cp -r /backups/replanta-meta-fill-backup-YYYYMMDD /wp-content/plugins/replanta-meta-fill
   ```

3. **Reactivar versión anterior**:
   ```bash
   wp plugin activate replanta-meta-fill
   ```

---

## 📝 Checklist de Deployment

### Pre-Deployment
- [ ] Código testeado localmente
- [ ] Documentación actualizada
- [ ] CHANGELOG.md con nueva versión
- [ ] Versión actualizada en archivo principal
- [ ] ZIP creado sin archivos innecesarios
- [ ] Backup de producción realizado

### Deployment
- [ ] Plugin subido a producción
- [ ] Plugin activado correctamente
- [ ] Configuración verificada
- [ ] Test de generación realizado
- [ ] Logs verificados (sin errores)

### Post-Deployment
- [ ] Monitorización activada
- [ ] Usuarios notificados (si aplicable)
- [ ] Documentación compartida
- [ ] Support preparado para preguntas

---

## 🆘 Soporte Post-Deployment

### Canales de Soporte

- **Email**: support@replanta.net
- **Documentación**: README.md, QUICKSTART.md
- **Logs**: `/wp-content/debug.log`
- **GitHub**: [crear issue si tienes repo]

### Información para Reportar Bugs

```
1. Versión de WordPress: [X.X.X]
2. Versión de PHP: [X.X]
3. Plugin SEO activo: [RankMath/Yoast/etc]
4. Versión del plugin: [1.0.0]
5. Descripción del error: [detalle]
6. Logs relevantes: [copiar]
7. Pasos para reproducir: [1, 2, 3...]
```

---

## 📅 Mantenimiento

### Mensual
- Revisar uso de API OpenAI
- Verificar costes acumulados
- Revisar logs de errores

### Trimestral
- Actualizar plugin si hay nuevas versiones
- Revisar configuración de prompts
- Analizar CTR de metas generadas

### Anual
- Auditoría completa de metas generadas
- Optimización de prompts según resultados
- Evaluar cambio de modelo OpenAI si hay mejoras

---

**¡Deployment exitoso! 🎉**
