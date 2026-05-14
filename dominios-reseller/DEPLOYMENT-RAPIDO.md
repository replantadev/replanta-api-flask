# ⚡ DEPLOYMENT RÁPIDO - API REST dominios-reseller

## 📦 Archivos a Subir (FTP a replanta.net)

### NUEVOS (crear):
```
/wp-content/plugins/dominios-reseller/includes/rest-api.php
```

### MODIFICADOS (sobrescribir):
```
/wp-content/plugins/dominios-reseller/dominios-reseller.php
/wp-content/plugins/dominios-reseller/CHANGELOG.md
```

---

## ✅ Checklist de Deployment

- [ ] **1. Conectar FTP a replanta.net**
- [ ] **2. Navegar a:** `/wp-content/plugins/dominios-reseller/`
- [ ] **3. Subir:** `includes/rest-api.php` (archivo NUEVO)
- [ ] **4. Sobrescribir:** `dominios-reseller.php` (versión 1.2.1)
- [ ] **5. Sobrescribir:** `CHANGELOG.md`
- [ ] **6. Verificar permisos:** 644 para todos los archivos .php
- [ ] **7. Probar endpoint:** `https://replanta.net/wp-json/replanta/v1/`
- [ ] **8. Probar con dominio real:** Usar script de test

---

## 🧪 Verificación Post-Deployment

### Test 1: Verificar que el endpoint existe
```
URL: https://replanta.net/wp-json/replanta/v1/
```
Deberías ver el endpoint `check_domain` listado.

### Test 2: Probar con un dominio real
**PowerShell:**
```powershell
.\test-api-endpoint.ps1 -Domain "dominio-cliente.com"
```

**Curl:**
```bash
curl -X POST https://replanta.net/wp-json/replanta/v1/check_domain \
  -H "Content-Type: application/json" \
  -d '{"domain":"dominio-cliente.com"}'
```

### Test 3: Verificar en web del cliente
1. Ir a la web del cliente
2. Si el sello no aparece, ejecutar en Code Snippets:
   ```php
   delete_option('sello_replanta_is_hosted');
   ```
3. Recargar la página
4. ✅ El sello debería aparecer

---

## ⏱️ Tiempo estimado: 5-10 minutos

## 🚨 Si algo falla:
1. Revisar `/wp-content/debug.log` en replanta.net
2. Verificar que `rest-api.php` esté en `/includes/`
3. Verificar permisos de archivos
4. Comprobar que la versión sea 1.2.1 en admin

---

## 📞 Soporte
Ver documentación completa en: `SOLUCION-API-REST.md`
