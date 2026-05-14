# 🚀 Replanta Meta Fill - Guía de Inicio Rápido

## ⚡ Setup en 3 Pasos

### 1️⃣ Instalar y Activar

```bash
# Copiar plugin a WordPress
cp -r replanta-meta-fill /ruta/a/wordpress/wp-content/plugins/

# O comprimir y subir via WordPress admin
zip -r replanta-meta-fill.zip replanta-meta-fill/
```

Luego en WordPress:
- Ve a **Plugins**
- Busca **Replanta Meta Fill**
- Haz clic en **Activar**

### 2️⃣ Configurar OpenAI (2 minutos)

1. **Obtener API Key**:
   - Ve a https://platform.openai.com/api-keys
   - Haz clic en "Create new secret key"
   - Copia la key (empieza con `sk-...`)

2. **Configurar en WordPress**:
   - Ve a **Meta Fill > Configuración**
   - Pega tu API Key en el campo
   - Haz clic en **Validar Key** para verificar
   - Guarda los cambios

### 3️⃣ ¡Usar!

**Opción A: Generación Individual**
- Ve a **Entradas** o **Páginas**
- Busca la columna **Meta**
- Haz clic en **Generar** en posts sin meta
- ✅ ¡Meta creada en segundos!

**Opción B: Generación Masiva**
- Ve a **Meta Fill > Generación Masiva**
- Selecciona posts (o usa "select all")
- Haz clic en **Generar Meta Descripciones Seleccionadas**
- ✅ ¡Múltiples metas generadas automáticamente!

---

## 🎯 Casos de Uso

### Escenario 1: Tienes 100 posts sin meta descripción

```
1. Meta Fill > Generación Masiva
2. Seleccionar todos (checkbox superior)
3. Generar Meta Descripciones Seleccionadas
4. Esperar 5-10 minutos (se procesan en lotes)
5. ✅ 100 metas generadas automáticamente
```

**Coste aproximado**: $0.05 - $0.10 con GPT-4o Mini

### Escenario 2: Quieres auto-generar al publicar

```
1. Meta Fill > Configuración
2. Activar "Generación automática"
3. Guardar
4. Ahora cada post sin meta se generará al publicar
```

### Escenario 3: Regenerar meta existente (mejorarla)

```
1. Ve a Entradas
2. Busca post con meta existente
3. Haz clic en "Regenerar"
4. ✅ Nueva meta generada (más optimizada)
```

---

## ⚙️ Configuración Recomendada

### Para Blog/Magazine (SEO Estándar)

```
Modelo: GPT-4o Mini
Creatividad: 0.7
Longitud máxima: 155 caracteres
Auto-generación: ❌ Desactivada (revisar manualmente)
```

### Para E-commerce (Persuasivo)

```
Modelo: GPT-4o
Creatividad: 0.8
Longitud máxima: 155 caracteres
Prompt personalizado: "Genera una meta descripción persuasiva..."
Auto-generación: ✅ Activada
```

### Para Sitio Corporativo (Formal)

```
Modelo: GPT-4o Mini
Creatividad: 0.5
Longitud máxima: 150 caracteres
Prompt personalizado: "Genera una meta descripción profesional..."
Auto-generación: ❌ Desactivada
```

---

## 🔧 Personalizar Prompt

El prompt por defecto funciona bien, pero puedes personalizarlo:

### Prompt Básico (por defecto)
```
Genera una meta descripción SEO atractiva y concisa (máximo {max_length} caracteres) 
para el siguiente contenido. Debe ser persuasiva, incluir palabras clave relevantes 
y motivar al clic.

Título: {title}
Contenido: {content}

Meta descripción:
```

### Prompt E-commerce
```
Genera una meta descripción persuasiva de máximo {max_length} caracteres que destaque 
los beneficios del producto/servicio. Incluye llamada a la acción.

Título: {title}
Contenido: {content}

Meta descripción con CTA:
```

### Prompt Blog Técnico
```
Crea una meta descripción técnica pero accesible (máximo {max_length} caracteres) que 
resuma el contenido de forma clara. Incluye términos técnicos relevantes.

Título: {title}
Contenido: {content}

Meta descripción:
```

---

## 📊 Interpretación de Estados

### ✅ Verde - "145 chars (OK)"
- **Significado**: Meta descripción perfecta
- **Longitud**: 120-160 caracteres
- **Acción**: Ninguna, está optimizada

### ⚠️ Amarillo - "95 chars (Corta)"
- **Significado**: Meta muy corta para SEO
- **Longitud**: <120 caracteres
- **Acción**: Regenerar para obtener mejor resultado

### ⚠️ Amarillo - "175 chars (Larga)"
- **Significado**: Meta muy larga, se cortará en móvil
- **Longitud**: >160 caracteres
- **Acción**: Regenerar o ajustar longitud máxima en settings

### ❌ Rojo - "Sin meta"
- **Significado**: No hay meta descripción
- **Acción**: Generar con el botón "Generar"

---

## 🐛 Solución Rápida de Problemas

### ❌ "Error de conexión con OpenAI"

**Causas posibles**:
1. API key incorrecta → Verifica en OpenAI Platform
2. Sin créditos → Recarga en OpenAI
3. Firewall bloquea HTTPS → Contacta hosting

**Solución**:
```
1. Meta Fill > Configuración
2. Validar Key (botón junto a API key)
3. Si falla, copiar nueva key desde OpenAI
```

### ⚠️ "Timeout en generación masiva"

**Solución**:
```
1. Reduce cantidad de posts seleccionados
2. Genera en grupos de 20-30 máximo
3. Espera entre lotes
```

### 🔍 "Plugin SEO no detectado"

**Verifica**:
```
1. Meta Fill > Configuración
2. Sección "Estado del Sistema"
3. Mira "Plugin SEO detectado"
4. Si es "Ninguno", fuerza manualmente en dropdown
```

---

## 💰 Costes Aproximados

### GPT-4o Mini (Recomendado)
- **1 meta descripción**: ~$0.0005 ($0.0005)
- **100 metas**: ~$0.05
- **1000 metas**: ~$0.50

### GPT-4o
- **1 meta descripción**: ~$0.002
- **100 metas**: ~$0.20
- **1000 metas**: ~$2.00

### GPT-3.5 Turbo
- **1 meta descripción**: ~$0.0002
- **100 metas**: ~$0.02
- **1000 metas**: ~$0.20

**Nota**: Costes aproximados a Nov 2024. Verifica pricing actual en OpenAI.

---

## 🎓 Mejores Prácticas

### ✅ DO

- ✅ Revisar metas generadas antes de publicar (primeras veces)
- ✅ Ajustar prompt según tu nicho
- ✅ Usar GPT-4o Mini para balance calidad/precio
- ✅ Mantener longitud entre 120-155 caracteres
- ✅ Regenerar si el resultado no te convence
- ✅ Monitorizar CTR en Search Console

### ❌ DON'T

- ❌ No generes sin revisar en sitios críticos
- ❌ No uses temperature >0.9 (demasiado aleatorio)
- ❌ No excedas 160 caracteres (se corta en móvil)
- ❌ No uses el mismo prompt para todos los nichos
- ❌ No generes masivamente sin probar primero

---

## 📈 Workflow Recomendado

### Para Sitio Nuevo

```
Día 1: Configurar plugin + probar con 5 posts
Día 2: Ajustar prompt según resultados
Día 3: Generar masivamente posts existentes
Día 4: Activar auto-generación para nuevos posts
Día 5+: Monitorizar CTR y ajustar si es necesario
```

### Para Sitio Existente (muchos posts)

```
Semana 1: Configurar + probar + ajustar prompt
Semana 2: Generar top 50 posts (más tráfico)
Semana 3: Generar resto en lotes de 100
Semana 4: Revisar resultados + regenerar si necesario
```

---

## 🔗 Enlaces Útiles

- **OpenAI Platform**: https://platform.openai.com
- **Pricing OpenAI**: https://openai.com/pricing
- **Guía SEO Meta**: https://moz.com/learn/seo/meta-description
- **Google SERP Preview**: https://www.highervisibility.com/seo/tools/serp-snippet-optimizer/

---

## 🆘 Soporte

**¿Problemas?**
1. Revisa esta guía
2. Consulta README.md completo
3. Verifica logs en PHP error_log
4. Contacta: support@replanta.net

---

**¡Listo para generar metas como un pro! 🚀**
