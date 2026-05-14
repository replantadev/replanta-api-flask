# 🚨 PLAN DE RECUPERACIÓN - CARPETA repos/front PERDIDA

**Fecha pérdida detectada:** 9 abril 2026  
**Última modificación conocida:** Desconocida (no versionado en git)  
**Contenido:** Landing pages - meses de trabajo

## ⚡ ACCIONES INMEDIATAS (Hacer YA)

### 1. DESCARGAR HERRAMIENTA DE RECUPERACIÓN
Descarga AHORA una de estas (gratuitas):
- **Recuva** (Piriform): https://www.ccleaner.com/recuva/download
- **PhotoRec** (gratuito, open source): https://www.cgsecurity.org/wiki/PhotoRec
- **Windows File Recovery** (Microsoft Store): `winget install Microsoft.WindowsFileRecovery`

### 2. INSTALAR CON URGENCIA
```powershell
# Opción 1: Windows File Recovery (oficial de Microsoft)
winget install Microsoft.WindowsFileRecovery

# Usarlo:
winfr C: C:\RecuperacionFront /n \Users\programacion2\Local Sites\repos\front\*
```

### 3. NO ESCRIBIR MÁS EN EL DISCO
- ⛔ **NO instales programas grandes**  
- ⛔ **NO descargues archivos grandes**  
- ⛔ **NO hagas backups de otras cosas**  
Cada operación de escritura reduce las posibilidades de recuperación.

## 🔍 VERIFICAR BACKUPS

### A. Backups en la nube
¿Tienes sincronización activa con alguno de estos?
- [ ] Google Drive
- [ ] Dropbox  
- [ ] OneDrive (ya verificado - NO)
- [ ] GitHub privado
- [ ] GitLab
- [ ] Bitbucket

### B. Discos externos
- [ ] Disco duro externo USB
- [ ] NAS de red (Synology, QNAP, etc.)
- [ ] Servidor de la empresa

### C. Tu hosting/producción
Si las landing pages están en:
- **replanta.net** → Descargar via FTP/SSH
- **Otros dominios** → Revisar cada sitio

```bash
# Comando para descargar desde servidor
scp -r usuario@replanta.net:/ruta/landing-pages ./front-recovered/
```

## 📋 INFORMACIÓN PARA RECUPERACIÓN

**Ruta perdida:** `C:\Users\programacion2\Local Sites\repos\front\`

**Tipo de archivos:**
- HTML
- CSS
- JavaScript
- Imágenes (PNG, JPG, SVG?)
- (especificar otros)

**Nombres de archivos recordados:**
- (Agregar aquí cualquier nombre específico que recuerdes)

## 🛠️ SI RECUVA/PHOTOREC FALLAN

### Windows File Recovery (avanzado)
```powershell
# Modo extensivo (más lento pero encuentra más)
winfr C: D:\Recuperacion /x /y:HTML,CSS,JS,JSON

# Buscar por nombre parcial si recuerdas alguno
winfr C: D:\Recuperacion /n *landing*
winfr C: D:\Recuperacion /n *replanta*
```

### Herramientas profesionales (PAGO)
- **EaseUS Data Recovery Wizard** (~70€)
- **Stellar Data Recovery** (~80€)  
- **R-Studio** (~80€)

## ⏱️ LÍNEA DE TIEMPO

- **08 abril 11:54:** Git reset en repos/ (solo afectó app.py)
- **09 abril 11:31:** Descarga de replanta-prices
- **09 abril 14:00:** Detectada pérdida de front/

## ❓ PREGUNTAS CRÍTICAS

1. ¿Cuándo trabajaste por última vez en esos archivos?
2. ¿Alguna landing está desplegada en replanta.net o algún dominio?
3. ¿Enviaste alguna por email a clientes?
4. ¿Están en Figma, Adobe XD o alguna herramienta de diseño?
5. ¿Tu empleado tiene copia?

## 🔒 PREVENCIÓN FUTURA

Una vez recuperado:
1. ✅ Versionar TODO en git (incluso archivos HTML estáticos)
2. ✅ Backup automático diario (GitHub, Backblaze, etc.)
3. ✅ Regla 3-2-1: 3 copias, 2 medios diferentes, 1 offsite
4. ✅ Usar `git status` antes de comandos destructivos

---

**ACTUAR YA - Cada minuto cuenta para la recuperación**
