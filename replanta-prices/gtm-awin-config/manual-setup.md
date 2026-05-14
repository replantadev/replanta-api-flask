# Configuración Manual GTM para Awin

Si prefieres configurar manualmente en lugar de importar el JSON:

## 1. Variables

### Variable: AWC - Cookie Value
- **Tipo**: 1st Party Cookie
- **Nombre de cookie**: `replanta_awin_awc`
- **Decodificar URI**: Sí

### Variable: AWC - URL Parameter  
- **Tipo**: URL
- **Tipo de componente**: Query
- **Clave de query**: `awc`

### Variable: Ecommerce - Transaction ID
- **Tipo**: Variable de capa de datos
- **Nombre**: `ecommerce.transaction_id`
- **Versión**: 2

### Variable: Ecommerce - Revenue
- **Tipo**: Variable de capa de datos
- **Nombre**: `ecommerce.value`
- **Versión**: 2

### Variable: Ecommerce - Currency
- **Tipo**: Variable de capa de datos
- **Nombre**: `ecommerce.currency`
- **Versión**: 2

---

## 2. Triggers

### Trigger: Awin - AWC in URL
- **Tipo**: Page View
- **Condición**: Page URL contiene `awc=`

### Trigger: Awin - Purchase Event
- **Tipo**: Custom Event
- **Nombre del evento**: `purchase`
- ⚠️ **IMPORTANTE**: Verificar que Upmind dispara este evento. Si no, ajustar.

---

## 3. Tags

### Tag: Awin - Set AWC Cookie

**Tipo**: Custom HTML

```html
<script>
(function() {
  var urlParams = new URLSearchParams(window.location.search);
  var awc = urlParams.get('awc');
  
  if (!awc) return;
  if (!/^[a-zA-Z0-9_-]+$/.test(awc)) return;
  
  var expires = new Date();
  expires.setDate(expires.getDate() + 90);
  document.cookie = 'replanta_awin_awc=' + encodeURIComponent(awc) 
    + ';domain=.replanta.net'
    + ';path=/'
    + ';expires=' + expires.toUTCString()
    + ';SameSite=Lax';
  
  console.log('[Awin GTM] Cookie set:', awc);
})();
</script>
```

**Trigger**: Awin - AWC in URL

---

### Tag: Awin - Conversion Pixel

**Tipo**: Custom HTML

```html
<script>
(function() {
  // Read AWC from cookie
  var awc = (function() {
    var cookies = document.cookie.split(';');
    for (var i = 0; i < cookies.length; i++) {
      var cookie = cookies[i].trim();
      if (cookie.indexOf('replanta_awin_awc=') === 0) {
        return decodeURIComponent(cookie.substring('replanta_awin_awc='.length));
      }
    }
    return null;
  })();
  
  // Si no hay AWC, igual enviamos la compra (afiliados por cupón).
  if (!awc) {
    console.log('[Awin GTM] No AWC cookie found, continuing without awc');
  }
  
  // Get ecommerce data - AJUSTAR SEGÚN DATALAYER DE UPMIND
  var transactionId = {{Ecommerce - Transaction ID}} || 'unknown';
  var revenue = {{Ecommerce - Revenue}} || 0;
  var currency = {{Ecommerce - Currency}} || 'EUR';
  
  if (!revenue || revenue <= 0) {
    console.log('[Awin GTM] No valid revenue, skipping');
    return;
  }
  
  // Awin S2S pixel
  var pixelUrl = 'https://www.awin1.com/sread.php'
    + '?tt=ss'
    + '&tv=2'
    + '&merchant=125596'
    + '&amount=' + parseFloat(revenue).toFixed(2)
    + '&ch=aw'
    + '&cr=' + encodeURIComponent(currency)
    + '&ref=' + encodeURIComponent(transactionId)
    + '&parts=DEFAULT:' + parseFloat(revenue).toFixed(2)
    + '&vc='
    + '&testmode=0'
    + '&cks=' + encodeURIComponent(awc);
  
  var img = new Image(1, 1);
  img.src = pixelUrl;
  
  console.log('[Awin GTM] Conversion sent:', {
    transactionId: transactionId,
    revenue: revenue,
    currency: currency,
    awc: awc
  });
})();
</script>
```

**Trigger**: Awin - Purchase Event

---

## 4. Verificación

### Test 1: Cookie se crea
1. Visita: `https://clientes.replanta.net/store?awc=TEST123`
2. Abre DevTools → Application → Cookies
3. Busca `replanta_awin_awc` con valor `TEST123`

### Test 2: Conversión se dispara
1. Con la cookie activa, completa una compra
2. Abre DevTools → Network
3. Filtra por `awin1.com`
4. Verifica que hay una request a `sread.php`

### Test 3: GTM Preview
1. Con Preview activo, haz una compra
2. En el panel, busca el evento `purchase`
3. Verifica que el tag "Awin - Conversion Pixel" se dispara

---

## Troubleshooting

### "El evento purchase no aparece"
- Upmind puede usar otro nombre: `transaction`, `order_complete`, etc.
- Revisa el dataLayer durante la compra y ajusta el trigger

### "La cookie no se crea"
- Verifica que el dominio es correcto (`.replanta.net`)
- Algunos navegadores bloquean cookies de terceros

### "El pixel no se dispara"
- Verifica que la cookie AWC existe
- Revisa la consola para mensajes de log
