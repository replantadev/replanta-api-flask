## Testing Plan for /tew/v1/cta-quick

### ✅ Pre-requisitos
- WordPress activo con test-eco-website plugin instalado
- REST API funcionando (`/wp-json/` accesible)

### 🧪 Test 1: Sitio con Histórico

**Request**:
```bash
curl -X POST http://localhost/wp-json/tew/v1/cta-quick \
  -H "Content-Type: application/json" \
  -d '{"url": "https://replanta.es"}'
```

**Expected Response** (~50ms):
```json
{
  "co2_monthly": 15.75,
  "co2_replanta": 18.90,
  "speed_ms": 2500,
  "trees_yearly": 9,
  "source": "historical",
  "url": "https://replanta.es"
}
```

### 🧪 Test 2: Sitio sin Histórico (Estimación)

**Request**:
```bash
curl -X POST http://localhost/wp-json/tew/v1/cta-quick \
  -H "Content-Type: application/json" \
  -d '{"url": "https://example-nuevo.com"}'
```

**Expected Response** (~500-800ms):
```json
{
  "co2_monthly": 20.50,
  "co2_replanta": 24.60,
  "speed_ms": 3000,
  "trees_yearly": 12,
  "source": "estimated",
  "url": "https://example-nuevo.com"
}
```

### 🧪 Test 3: URL Inválida

**Request**:
```bash
curl -X POST http://localhost/wp-json/tew/v1/cta-quick \
  -H "Content-Type: application/json" \
  -d '{"url": "not-a-url"}'
```

**Expected Response**:
```json
{
  "code": "tew_invalid_url",
  "message": "La URL no es válida.",
  "data": { "status": 400 }
}
```

### 🧪 Test 4: Shortcode en Página

**Crear página** con:
```
[eco_cta]
```

**Verificar**:
1. Formulario se renderiza correctamente
2. Al ingresar URL válida (ej: `replanta.es`)
3. Loading aparece
4. Resultados en <1 segundo
5. Se muestran 3 líneas de impacto

**Chrome DevTools → Network**:
- Request a `/wp-json/tew/v1/cta-quick`
- Status: 200
- Time: <1000ms

### 📊 Métricas de Éxito

| Métrica | Target | Verificación |
|---------|--------|--------------|
| Tiempo de respuesta (histórico) | <100ms | ✅ Chrome DevTools |
| Tiempo de respuesta (estimación) | <1000ms | ✅ Chrome DevTools |
| Tasa de éxito | >99% | ✅ Sin errores 400/500 |
| Datos válidos | Siempre | ✅ co2_monthly > 0 |

### 🐛 Troubleshooting

**Si no responde**:
```bash
# Verificar REST API activa
curl http://localhost/wp-json/
```

**Si error 404**:
```bash
# Verificar ruta registrada
wp rest-api list --url=http://localhost
```

**Si timeout**:
```bash
# Verificar Website Carbon API
curl "https://api.websitecarbon.com/data?bytes=2500000&green=0"
```

### ✅ Checklist Final

- [ ] Endpoint `/tew/v1/cta-quick` registrado
- [ ] Response <1s con estimación
- [ ] Response <100ms con histórico
- [ ] Shortcode `[eco_cta]` usa nuevo endpoint
- [ ] UI muestra resultados correctamente
- [ ] No errores en consola JavaScript
- [ ] No errores PHP en logs

### 📝 Comando Rápido de Verificación

```bash
# Test completo en una línea
time curl -s -X POST http://localhost/wp-json/tew/v1/cta-quick \
  -H "Content-Type: application/json" \
  -d '{"url":"https://replanta.es"}' | jq .
```

**Output esperado**:
```
{
  "co2_monthly": 15.75,
  "co2_replanta": 18.90,
  "speed_ms": 2500,
  "trees_yearly": 9,
  "source": "historical",
  "url": "https://replanta.es"
}

real    0m0.087s  <-- ⚡ Sub-segundo!
```
