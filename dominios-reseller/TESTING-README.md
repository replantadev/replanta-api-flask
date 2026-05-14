# 🧪 Testing del Sistema de Onboarding Asíncrono

## 📋 Scripts de Prueba

### 1. `create-test-preset.php`
Crea un preset básico de ejemplo para testing.

**Uso:**
```bash
php create-test-preset.php
```

**Qué hace:**
- Crea el preset `test-basic` con configuración mínima
- Incluye settings básicos de SSL y cache
- Añade una regla simple de security headers

### 2. `test-onboarding-server.php`
Prueba completa del sistema de onboarding.

**Uso:**
```bash
php test-onboarding-server.php
```

**Qué verifica:**
1. ✅ **Tablas de BD**: Verifica que existan todas las tablas necesarias
2. ✅ **Presets**: Confirma que hay presets disponibles
3. ✅ **Encolado**: Intenta encolar un dominio de prueba
4. ✅ **Run creado**: Verifica que se creó el registro del run
5. ✅ **Eventos**: Confirma que se programaron los eventos de WordPress
6. ✅ **Estado legacy**: Verifica compatibilidad con el sistema anterior

## 🚀 Proceso de Testing

### Paso 1: Crear preset de prueba
```bash
php create-test-preset.php
```
Deberías ver: `✅ Preset 'test-basic' creado exitosamente`

### Paso 2: Ejecutar test completo
```bash
php test-onboarding-server.php
```

### Paso 3: Verificar resultados
Todos los checks deberían mostrar ✅. Si hay algún ❌, indica el problema.

**Resultado esperado:**
```
=== TEST SISTEMA ONBOARDING ASÍNCRONO ===

✅ Cargado: class-onboarding-db.php
✅ Cargado: class-onboarding-worker.php

1. VERIFICANDO TABLAS...
   - dominios_reseller_cf_onboarding: ✅
   - dominios_reseller_cf_runs: ✅
   - dominios_reseller_cf_presets: ✅
   - dominios_reseller_cf_onboarding_logs: ✅

2. VERIFICANDO PRESETS...
   - Presets encontrados: 2
   - Primer preset: test-basic - Preset Básico de Testing

3. PROBANDO ENCOLADO...
   - Dominio: test-1734739200.com
   - Preset: test-basic
   - Resultado: ✅ ÉXITO
   - Run ID: uuid-generado
   - Mensaje: Dominio encolado correctamente

4. VERIFICANDO RUN CREADO...
   - Run encontrado: ✅
   - Estado: queued
   - Dominio: test-1734739200.com
   - Preset: test-basic

5. VERIFICANDO EVENTOS PROGRAMADOS...
   - Eventos de onboarding: 1
     * dr_onboarding_zone_check en +5s
       Args: run-uuid, test-1734739200.com

6. VERIFICANDO ESTADO LEGACY...
   - Estado legacy encontrado: ✅
   - Estado: queued
   - Run ID: run-uuid

=== TEST COMPLETADO ===
Si todo está en ✅, el sistema funciona correctamente.
```

## 🔍 Diagnóstico de Problemas

### ❌ Tablas no existen
- Las tablas no se crearon automáticamente
- Ejecuta: `Dominios_Reseller_Onboarding_DB::create_tables()`

### ❌ No hay presets
- No se encontraron presets en la BD
- Ejecuta primero: `php create-test-preset.php`

### ❌ Error en encolado
- Verifica que el preset existe
- Revisa logs de WordPress para errores

### ❌ No se creó el run
- Problema con `init_onboarding_run()`
- Verifica permisos de BD

### ❌ No hay eventos programados
- WordPress cron no está funcionando
- Los hooks no están registrados correctamente

## 📊 Verificación Manual

Si quieres verificar manualmente:

### Ver runs en BD:
```sql
SELECT * FROM wp_dominios_reseller_cf_runs ORDER BY started_at DESC LIMIT 5;
```

### Ver eventos programados:
```php
var_dump(_get_cron_array());
```

### Ver logs:
```sql
SELECT * FROM wp_dominios_reseller_cf_onboarding_logs ORDER BY created_at DESC LIMIT 10;
```

## 🎯 Próximos Pasos

1. Si el test pasa ✅: El sistema funciona correctamente
2. Si hay errores ❌: Revisa los logs y diagnostica el problema específico
3. Una vez funcionando: Prueba con un dominio real de Cloudflare

---
**Nota:** Los scripts buscan automáticamente `wp-load.php` en rutas comunes de WordPress.