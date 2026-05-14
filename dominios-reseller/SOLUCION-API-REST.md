# 🔧 SOLUCIÓN: Conexión entre sello-replanta y dominios-reseller

## 📋 **Problema Identificado**

El plugin `sello-replanta` instalado en las webs de los clientes mostraba el mensaje:
> **"El dominio no está alojado en Replanta"**

Incluso cuando el dominio SÍ estaba registrado en la base de datos del plugin `dominios-reseller` en replanta.net.

---

## 🔍 **Causa Raíz**

### **Plugin sello-replanta (web del cliente)**
- Intenta verificar si el dominio está en Replanta llamando a:
  ```php
  $url = 'https://replanta.net/wp-json/replanta/v1/check_domain';
  ```
- Espera recibir una respuesta JSON: `{"hosted": true}` o `{"hosted": false}`
- Código en `sello-replanta.php` líneas 287-306

### **Plugin dominios-reseller (replanta.net)**
- ❌ **NO tenía ningún endpoint REST API registrado**
- ❌ **No había ningún `register_rest_route`** en todo el código
- Solo gestionaba dominios internamente en la base de datos
- **Por eso no respondía a las peticiones del sello-replanta**

---

## ✅ **Solución Implementada**

### **1. Archivo nuevo creado:**
```
dominios-reseller/includes/rest-api.php
```

Este archivo registra:

#### **Endpoint principal:** `/wp-json/replanta/v1/check_domain`
- **Método:** POST
- **Parámetro:** `domain` (string, requerido)
- **Funcionalidad:**
  - Busca el dominio en la tabla `wp_dominios_reseller`
  - Verifica en ambos servidores (UK y USA)
  - Prioriza dominios Activos sobre Suspendidos
  - Devuelve información completa del dominio

**Ejemplo de respuesta (dominio encontrado):**
```json
{
  "hosted": true,
  "domain": "ejemplo.com",
  "server": "UK",
  "status": "Activo",
  "trees_planted": 25,
  "co2_evaded": 150.50,
  "fecha_emision": "2024-01-15",
  "validez": "2025-01-15"
}
```

**Ejemplo de respuesta (dominio NO encontrado):**
```json
{
  "hosted": false,
  "domain": "noexiste.com",
  "message": "Domain not found in our hosting database"
}
```

#### **Endpoint secundario (opcional):** `/wp-json/replanta/v1/stats`
- **Método:** GET
- **Permisos:** Solo administradores
- **Funcionalidad:** Devuelve estadísticas globales de dominios

---

### **2. Modificación en archivo principal:**
```
dominios-reseller/dominios-reseller.php
```

**Línea ~226:** Añadido `'includes/rest-api.php'` a la lista de archivos a cargar:
```php
foreach ([
    'includes/whm-functions.php',
    'includes/emisiones-functions.php',
    'includes/ajax-handlers.php',
    'includes/shortcodes.php',
    'includes/scripts.php',
    'includes/rest-api.php'  // ← NUEVO
] as $file) {
    $path = plugin_dir_path(__FILE__) . $file;
    if (file_exists($path)) require_once $path;
}
```

---

## 🚀 **Pasos para Aplicar la Solución**

### **En replanta.net (vía FTP):**

1. **Subir el archivo nuevo:**
   ```
   /wp-content/plugins/dominios-reseller/includes/rest-api.php
   ```

2. **Sobrescribir el archivo principal:**
   ```
   /wp-content/plugins/dominios-reseller/dominios-reseller.php
   ```

3. **Verificar que se cargó correctamente:**
   - Ve a: `https://replanta.net/wp-json/replanta/v1/`
   - Deberías ver el endpoint listado

4. **Probar el endpoint:**
   ```bash
   curl -X POST https://replanta.net/wp-json/replanta/v1/check_domain \
     -H "Content-Type: application/json" \
     -d '{"domain":"tudominio.com"}'
   ```

---

## 🧪 **Cómo Verificar que Funciona**

### **Desde la web del cliente:**

1. **Opción A - Limpiar caché:**
   ```php
   // En wp-admin de la web del cliente
   delete_option('sello_replanta_is_hosted');
   ```
   Luego recargar la página

2. **Opción B - Código de prueba:**
   ```php
   $domain = 'tudominio.com';
   $url = 'https://replanta.net/wp-json/replanta/v1/check_domain';
   $response = wp_remote_post($url, array(
       'body' => json_encode(array('domain' => $domain)),
       'headers' => array('Content-Type' => 'application/json')
   ));
   
   $body = wp_remote_retrieve_body($response);
   $data = json_decode($body, true);
   print_r($data);
   ```

3. **Resultado esperado:**
   - ✅ Si el dominio está en replanta.net: `hosted = true` → El sello se muestra
   - ❌ Si el dominio NO está: `hosted = false` → El sello NO se muestra

---

## 🔐 **Seguridad**

- El endpoint es **público** (`permission_callback => '__return_true'`)
- Solo expone información que ya es pública (hosting ecológico)
- Valida el formato del dominio antes de buscar en BD
- Usa `$wpdb->prepare()` para prevenir SQL injection
- No expone información sensible del servidor

---

## 📊 **Endpoint de Estadísticas (Bonus)**

**Solo para administradores:**
```
GET https://replanta.net/wp-json/replanta/v1/stats
```

**Respuesta:**
```json
{
  "total_domains": 156,
  "active": 142,
  "suspended": 8,
  "addon": 6,
  "by_server": {
    "uk": 120,
    "usa": 36
  },
  "environmental_impact": {
    "total_trees": 3450,
    "total_co2_evaded_kg": 18750.50
  }
}
```

---

## 🐛 **Debug (si algo falla)**

### **Activar logs en WordPress:**
En `wp-config.php` de replanta.net:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### **Ver logs:**
```
/wp-content/debug.log
```

Buscar líneas como:
```
[Dominios Reseller API] Verificando dominio: ejemplo.com
[Dominios Reseller API] Dominio encontrado: ejemplo.com - Status: Activo - Server: uk
```

---

## 📝 **Notas Importantes**

1. **NO es necesario actualizar el plugin sello-replanta** - Ya estaba esperando este endpoint
2. **El cambio es 100% retrocompatible** - No afecta funcionalidad existente
3. **Funciona con ambos servidores** (UK y USA) automáticamente
4. **Respeta el estado del dominio** - Solo considera "hosted" si está Activo o es Addon
5. **Caché en cliente:** El sello-replanta cachea el resultado en `sello_replanta_is_hosted`

---

## ✨ **Resumen Ejecutivo**

**Antes:**
- ❌ dominios-reseller no tenía API REST
- ❌ sello-replanta no podía verificar dominios
- ❌ Mensaje: "El dominio no está alojado en Replanta"

**Después:**
- ✅ dominios-reseller expone API REST en `/wp-json/replanta/v1/check_domain`
- ✅ sello-replanta puede verificar correctamente
- ✅ El sello se muestra automáticamente en webs alojadas en Replanta

---

**Fecha:** 10 de noviembre de 2025  
**Archivos modificados:**
- ✅ `dominios-reseller/includes/rest-api.php` (NUEVO)
- ✅ `dominios-reseller/dominios-reseller.php` (1 línea añadida)

**Próximos pasos:**
1. Subir archivos a replanta.net vía FTP
2. Probar endpoint con curl o Postman
3. Pedir al cliente que recargue su web (limpiar caché si es necesario)
