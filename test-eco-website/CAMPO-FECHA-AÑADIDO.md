# ✅ Campo de Fecha Añadido a Informes Manuales

## 🎯 ¿Qué se añadió?

Ahora los **informes manuales tienen un campo de fecha editable** que permite:

1. ✅ Establecer la fecha exacta del informe (útil para informes antiguos)
2. ✅ Dejar el campo vacío para usar la fecha actual automáticamente
3. ✅ Visualizar la fecha en la lista de informes (columna "Date")
4. ✅ Ver la fecha en los casos de éxito
5. ✅ Editar la fecha posteriormente si es necesario

---

## 📝 Detalles Técnicos

### Campo HTML
- **Tipo:** `<input type="datetime-local">`
- **Formato de entrada:** `YYYY-MM-DDTHH:MM` (ej: `2024-01-15T14:30`)
- **Ubicación:** Justo después del campo "URL del Sitio"
- **Placeholder:** Ninguno (el navegador muestra un selector de fecha/hora)

### Procesamiento Backend
```php
// Conversión de datetime-local a MySQL UTC
$date_input = '2024-01-15T14:30';
$timestamp = strtotime( $date_input );
$date_mysql = gmdate( 'Y-m-d H:i:s', $timestamp ); // '2024-01-15 14:30:00'
```

### Almacenamiento
- **Meta key:** `Report_Storage::META_GENERATED`
- **Formato:** MySQL datetime UTC (`YYYY-MM-DD HH:MM:SS`)
- **Ejemplo:** `2024-01-15 14:30:00`

### Visualización
- **Lista de informes:** Columna "Date" con formato localizado
- **Casos de éxito:** Muestra la fecha en cada card (ANTES/DESPUÉS)
- **Editor:** Se convierte automáticamente de MySQL a datetime-local para edición

---

## 🎨 Cómo se ve

### En el Editor de Informes
```
┌────────────────────────────────────────────┐
│ URL del Sitio                              │
│ [https://ejemplo.com________________]      │
│ URL completa del sitio web analizado.      │
└────────────────────────────────────────────┘

┌────────────────────────────────────────────┐
│ Fecha del Informe                          │
│ [📅 15/01/2024  ⏰ 14:30]                  │
│ Fecha y hora en que se generó el informe   │
│ (o la fecha que quieras registrar).        │
└────────────────────────────────────────────┘
```

### En la Lista de Informes
```
URL              | Score | CO₂  | Green | Date
-----------------|-------|------|-------|----------------
ejemplo.com      | 75.5  | 0.50 | ✓ Sí  | 15/01/2024 14:30
antiguo.com      | 35.0  | 1.20 | ✗ No  | 01/01/2024 10:00
```

### En Casos de Éxito
```
┌─────────────────────────────┐  ┌─────────────────────────────┐
│        ANTES                │  │        DESPUÉS              │
│  📅 01/01/2024 10:00        │  │  📅 15/01/2024 14:30        │
│  🌐 antiguo.com             │  │  🌐 ejemplo.com             │
│  📊 Score: 35               │  │  📊 Score: 75.5             │
│  💨 CO₂: 1.20g              │  │  💨 CO₂: 0.50g              │
│  ⚡ Hosting: ✗ No           │  │  ⚡ Hosting: ✓ Sí           │
└─────────────────────────────┘  └─────────────────────────────┘
```

---

## 💡 Casos de Uso

### 1. **Informe de Cliente Antiguo**
Tienes un cliente que migró hace 6 meses pero no tienes capturas del sitio "antes":
```
1. Crea informe manual "ANTES"
2. Establece fecha: 6 meses atrás
3. Rellena datos estimados del sitio antiguo
4. Guarda

Resultado: El caso de éxito mostrará la fecha correcta de 6 meses atrás
```

### 2. **Importar Datos Históricos**
Quieres importar informes de una herramienta antigua:
```
1. Por cada informe antiguo, crea uno manual
2. Establece la fecha original del análisis
3. Importa los valores (score, CO₂, etc.)
4. Guarda

Resultado: Línea temporal precisa de todos tus informes
```

### 3. **Dejar Fecha Automática**
Para informes nuevos, simplemente:
```
1. Deja el campo fecha VACÍO
2. El sistema usará la fecha/hora actual automáticamente
3. Guarda

Resultado: Fecha actual sin intervención manual
```

---

## 🔧 Compatibilidad

### Con Informes Existentes
Los informes antiguos que ya tienen `META_GENERATED`:
- ✅ Mostrarán su fecha existente en el editor
- ✅ Podrás editarla si es necesario
- ✅ Se mostrará correctamente en listas y casos de éxito

### Con Informes Nuevos
Los informes creados de cero:
- ✅ Puedes establecer fecha personalizada
- ✅ O dejarla vacía para usar la actual
- ✅ Se guardará en formato UTC para consistencia

---

## 📋 Checklist de Funcionalidad

- [ ] Campo "Fecha del Informe" visible después de "URL del Sitio"
- [ ] Selector de fecha/hora nativo del navegador funciona
- [ ] Al guardar con fecha personalizada, se almacena correctamente
- [ ] Al guardar sin fecha (vacía), usa la actual
- [ ] Al editar informe, la fecha guardada aparece en el campo
- [ ] La columna "Date" en lista muestra la fecha correcta
- [ ] Los casos de éxito muestran las fechas en ambas cards
- [ ] Puedo editar la fecha de un informe existente

---

## 🎯 Resultado Final

**Ahora tienes control total sobre las fechas de tus informes manuales:**

✅ Puedes registrar informes antiguos con su fecha original  
✅ Puedes crear informes actuales automáticamente  
✅ Puedes corregir fechas si te equivocaste  
✅ Las fechas se muestran consistentemente en toda la interfaz  
✅ Los casos de éxito tienen cronología precisa  

**¡Perfecto para gestionar tu portfolio histórico de sostenibilidad!** 🌱
