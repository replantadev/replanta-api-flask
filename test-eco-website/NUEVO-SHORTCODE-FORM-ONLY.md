# 📋 NUEVO SHORTCODE: Solo Formulario de Huella Digital

## 🎯 **¿Qué es?**

He añadido un nuevo shortcode `[eco_form_only]` que genera **SOLO el formulario** de cálculo de huella digital y redirige a tu página existente `https://replanta.net/calculadora-huella/` para mostrar el informe automáticamente.

---

## ✨ **CARACTERÍSTICAS**

### **Shortcode Original:**
- `[eco_performance_snapshot]` - Formulario + informe en la misma página

### **Nuevo Shortcode:**
- `[eco_form_only]` - Solo formulario que redirige a página de resultados

---

## 🚀 **CÓMO USAR**

### **1. Uso Básico**

```html
[eco_form_only]
```

**Resultado:**
- Formulario con título "Análisis de Sostenibilidad Web"
- Redirige automáticamente a `/calculadora-huella/` con la URL introducida
- Al llegar a la página, **auto-inicia** el análisis inmediatamente
- Botón: "Generar informe"

### **2. Personalizar Página de Destino**

```html
[eco_form_only redirect_page="/mi-pagina-informe/"]
```

**Redirige a:** `/mi-pagina-informe/?audit_url=https://sitio.com&tew_form_redirect=1`

### **3. Personalizar Textos**

```html
[eco_form_only 
    title="Audita tu Web" 
    description="Descubre el impacto ambiental de tu sitio web" 
    button_text="Analizar ahora"]
```

### **4. Solo Formulario Mínimo (estilo limpio)**

```html
[eco_form_only title="" description=""]
```

**Resultado:**
- Solo el formulario sin título ni descripción
- Estilo más compacto sin badge decorativo
- Perfecto para sidebars o espacios pequeños

---

## 🔧 **PARÁMETROS DISPONIBLES**

| Parámetro | Por Defecto | Descripción |
|-----------|-------------|-------------|
| `redirect_page` | `/eco-informe/` | URL donde mostrar resultados |
| `button_text` | "Generar informe" | Texto del botón |
| `title` | "Análisis de Sostenibilidad Web" | Título del formulario |
| `description` | Texto descriptivo | Descripción del formulario |

---

## 📄 **FUNCIONAMIENTO AUTOMÁTICO**

### **URL de Resultados:**
```
https://replanta.net/calculadora-huella/?audit_url=https://sitio-analizar.com&tew_form_redirect=1
```

### **¿Qué Pasa?**

1. **Usuario completa formulario** con URL del sitio
2. **Redirección automática** a `/calculadora-huella/` (tu página existente)
3. **Auto-detección** - El shortcode detecta que viene de redirección  
4. **Análisis automático** - Se inicia inmediatamente sin intervención
5. **Resultados completos** - Se muestran en la página existente

### **Ventajas:**

- ✅ **Usa tu página existente** - No crea páginas nuevas
- ✅ **Auto-inicio inteligente** - Detecta redirecciones y auto-ejecuta
- ✅ **URLs limpias** para compartir informes
- ✅ **Mejor UX** - Usuario va directo al análisis
- ✅ **Facilita embedding** - Formulario se puede embeber en cualquier lugar
- ✅ **Sin configuración extra** - Funciona inmediatamente

---

## 🎨 **DISEÑO INTEGRADO REPLANTA**

El formulario usa **tu sistema de diseño Replanta** completamente:

### **✨ Características de Estilo:**

- **🎨 Variables CSS**: Usa todas tus variables `--rep-*`
- **🌈 Colores**: `--rep-teal`, `--rep-forest`, `--rep-sun`, `--rep-white`
- **📝 Tipografías**: `--rep-font-display` (Fraunces), `--rep-font-body` (DM Sans)
- **🖼️ Sombras**: `--rep-shadow-sm`, `--rep-shadow-md`, `--rep-shadow-lg`
- **📱 Responsive**: Se adapta perfectamente a móviles

### **🎯 Elementos Visuales:**

```css
✨ Badge decorativo: "✨ Análisis gratuito"
🔵 Borde principal: var(--rep-teal) 
🟢 Fondo suave: var(--rep-bg-light)
🌟 Hover effects: Elevación y transformación
🎯 Focus: Rings de color con accesibilidad
```

### **🔧 Variantes Automáticas:**

- **Completo**: Con título, descripción y badge decorativo
- **Mínimo**: Sin título/descripción → Badge desaparece automáticamente
- **Responsive**: Padding y tamaños se ajustan en móvil

### **🎨 CSS Personalizable:**

Si quieres modificar algo específico:

```css
/* Tu tema/child theme CSS */
.tew-form-only .tew-snapshot__form.tew-form-redirect {
    border-color: var(--rep-sun) !important; /* Cambiar borde */
}

.tew-form-only .tew-snapshot__submit {
    background: var(--rep-grad) !important; /* Usar gradiente */
}

/* Ocultar badge decorativo */
.tew-form-only.mi-clase-custom .tew-snapshot__form.tew-form-redirect::before {
    display: none !important;
}
```

---

## 🛠️ **CONFIGURACIÓN TÉCNICA**

### **1. Auto-Detección Inteligente**

Tu shortcode original `[eco_performance_snapshot]` ahora detecta automáticamente cuando alguien llega desde el formulario `[eco_form_only]` y:

- ✅ **Detecta parámetros** `audit_url` y `tew_form_redirect`
- ✅ **Pre-llena el campo** URL automáticamente
- ✅ **Auto-inicia análisis** después de 500ms
- ✅ **Muestra resultados** inmediatamente

### **2. Sin Configuración Adicional**

No necesitas:
- ❌ Crear páginas nuevas
- ❌ Configurar redirecciones especiales  
- ❌ Añadir shortcodes adicionales
- ❌ Modificar tu página existente

### **3. Compatibilidad Total**

- ✅ **Funciona con tu página actual** `/calculadora-huella/`
- ✅ **Compatible con cualquier tema**
- ✅ **Compatible con page builders**
- ✅ **Responsive automático**
- ✅ **SEO optimizado**

---

## 🔄 **CASOS DE USO**

### **Caso 1: Landing Page**

```html
<!-- En página de servicios -->
<h2>Auditoría de Sostenibilidad Web</h2>
<p>Conoce el impacto ambiental de tu sitio web</p>

[eco_form_only title="" description="Introduce la URL de tu sitio:"]

<p>Recibirás un informe completo con métricas de sostenibilidad...</p>
```

### **Caso 2: Widget en Sidebar**

```html
[eco_form_only 
    title="Test Rápido" 
    description="" 
    button_text="Analizar"]
```

### **Caso 3: Página de Contacto**

```html
<h3>¿Quieres conocer la huella de tu web?</h3>
[eco_form_only 
    description="Analizaremos tu sitio gratuitamente"
    button_text="Obtener informe gratis"]
```

### **Caso 4: Pop-up o Modal**

```html
<!-- Formulario mínimo para modal -->
[eco_form_only title="" description="" button_text="Analizar"]
```

---

## 📊 **COMPARATIVA**

| Característica | `[eco_performance_snapshot]` | `[eco_form_only]` |
|----------------|-------------------------------|-------------------|
| **Formulario** | ✅ | ✅ |
| **Resultados en misma página** | ✅ | ❌ |
| **Redirige a página existente** | ❌ | ✅ `/calculadora-huella/` |
| **Auto-inicio inteligente** | ❌ | ✅ |
| **URL compartible** | ❌ | ✅ |
| **Personalización formulario** | Limitada | ✅ Completa |
| **Mejor para** | Páginas completas | Embebido en contenido |
| **SEO** | Regular | ✅ Mejor |

---

## 🚨 **IMPORTANTE: Sin Configuración Extra**

**¡Tu shortcode está listo para usar inmediatamente!**

No necesitas hacer nada especial en tu página `/calculadora-huella/`. El plugin automáticamente detecta cuando alguien llega con parámetros de redirección y auto-inicia el análisis.

---

## 🔧 **DESARROLLO**

### **Archivos Modificados:**

1. **`class-tew-shortcode.php`** - Añade shortcode `eco_form_only` + auto-detección
2. **`assets/js/frontend.js`** - Auto-inicio cuando detecta redirección  
3. **`test-eco-website.php`** - Versión 0.2.0

### **Funcionalidad Técnica:**

```php
// El formulario envía a:
GET /calculadora-huella/?audit_url=https://sitio.com&tew_form_redirect=1

// Tu shortcode original detecta parámetros y:
1. Pre-llena campo URL automáticamente
2. Auto-inicia análisis después de 500ms 
3. Muestra resultados inmediatamente
4. Experiencia totalmente automática
```

---

## ✅ **ESTADO**

- ✅ **Shortcode creado:** `[eco_form_only]`
- ✅ **Auto-redirección:** A tu página `/calculadora-huella/`
- ✅ **Auto-detección:** Detecta redirecciones automáticamente
- ✅ **Auto-inicio:** Inicia análisis sin intervención
- ✅ **Parámetros personalizables**
- ✅ **Estilos incluidos**
- ✅ **Versión 0.2.0** lista

---

## 🎉 **¡LISTO PARA USAR!**

**Ejemplos para probar inmediatamente:**

```html
<!-- Mínimo -->
[eco_form_only]

<!-- Personalizado -->
[eco_form_only 
    title="Audita tu Web Gratis" 
    description="Análisis completo en 2 minutos"
    button_text="Comenzar auditoría"]

<!-- Solo formulario -->
[eco_form_only title="" description=""]
```

**¡El formulario aparecerá inmediatamente y redirigirá a tu página existente donde el análisis se iniciará automáticamente!** 🌱

### **🔥 VENTAJA CLAVE**

**No interfiere con nada existente** - Usa tu página actual y la mejora con auto-inicio inteligente. **¡Perfecto!** ✨