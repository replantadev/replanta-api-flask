<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  SAP WooCommerce Suite — Referencia interna de operaciones
 *  Solo visible para administradores en /wp-admin/
 * ═══════════════════════════════════════════════════════════════════
 *
 *  NO ES UN ARCHIVO EJECUTABLE. Es documentación interna que se
 *  renderiza en la pestaña de ayuda del admin cuando proceda.
 *
 *  @package Replanta_Prices
 */

/*
╔═══════════════════════════════════════════════════════════════════╗
║  1. ESTRUCTURA DE PRECIOS — DECISIONES INTERNAS                  ║
╚═══════════════════════════════════════════════════════════════════╝

SETUP (one-time, PID: 2e071d93-1d5e-468e-935c-646028758396)
  ┌───────────────┬────────┬──────────────────────────────────────┐
  │ Plan          │ EUR    │ Margen estimado                      │
  ├───────────────┼────────┼──────────────────────────────────────┤
  │ Starter       │  990 € │ ~20h trabajo → 49,5 €/h (+overhead) │
  │ Business      │ 1500 € │ ~30h trabajo → 50 €/h               │
  │ Enterprise    │ 2500 € │ ~40h trabajo → 62,5 €/h (Miravia)   │
  └───────────────┴────────┴──────────────────────────────────────┘
  
  Si el análisis revela >+30 % horas estimadas → reclasificar al
  tier superior o generar presupuesto custom. El análisis pre-venta
  SIEMPRE se hace antes de cobrar el setup.

MENSUALIDAD (recurring, PID: 61e50989-73d2-4753-988c-e45e610832d7)
  ┌───────────────┬────────┬──────────────────────────────────────┐
  │ Plan          │ EUR    │ Coste estimado real                  │
  ├───────────────┼────────┼──────────────────────────────────────┤
  │ Starter       │   99 € │ ~2h/mes soporte + infra monit.      │
  │ Business      │  149 € │ ~3h/mes soporte + SLA 24h overhead  │
  │ Enterprise    │  249 € │ ~5h/mes + consultoría trimestral     │
  └───────────────┴────────┴──────────────────────────────────────┘

  Break-even con 1 developer:
  - 5 Starter = 495 €/mes → ~10h soporte
  - 3 Business = 447 €/mes → ~9h soporte
  - 2 Enterprise = 498 €/mes → ~10h soporte
  MRR objetivo mínimo: 1.500 €/mes (mix de ~12-15 clientes)


╔═══════════════════════════════════════════════════════════════════╗
║  2. SLA — COMPROMISOS INTERNOS                                   ║
╚═══════════════════════════════════════════════════════════════════╝

  ┌───────────────┬──────────────┬────────────────────────────────┐
  │ Plan          │ Respuesta    │ Canal                          │
  ├───────────────┼──────────────┼────────────────────────────────┤
  │ Starter       │ 48h labor.   │ Email soporte@replanta.net     │
  │ Business      │ 24h labor.   │ Email + ticket prioritario     │
  │ Enterprise    │  8h labor.   │ Canal directo (Slack/WhatsApp) │
  └───────────────┴──────────────┴────────────────────────────────┘

  HORARIO: L-V 9:00-18:00 CET (festivos nacionales ES excluidos)
  
  ENTIDAD LEGAL:
    Replanta LLC — Wyoming, US
    EIN: 30-1447308
    1021 E Lincolnway, 8261, Cheyenne, WY 82001, Laramie, US
    Jurisdicción: Laramie County, Wyoming
    Clientes UE: aplica Roma I (normas imperativas consumidor)
  
  «Respuesta» = primer contacto útil, NO resolución.
  Resolución depende de severidad:
    - Crítico (sync rota, pedidos no llegan): 4h/8h/24h según tier
    - Alto (datos parciales, errores intermitentes): 1 día/2 días/5 días
    - Bajo (consulta config, mejoras): backlog normal


╔═══════════════════════════════════════════════════════════════════╗
║  3. PERMANENCIA Y CANCELACIÓN                                    ║
╚═══════════════════════════════════════════════════════════════════╝

  - Permanencia: 6 MESES desde go-live
  - Cancelación pre-setup: 100 % reembolso
  - Cancelación mid-setup: horas trabajadas a 90 €/h, resto devuelto
  - Cancelación en permanencia: pagar meses restantes
  - Cancelación ordinaria (post-6m): preaviso 30 días

  JUSTIFICACIÓN: setup de 990-2500 € no se amortiza si el cliente
  se va al mes 2. La permanencia protege el ROI de la puesta en
  marcha y da estabilidad al MRR.


╔═══════════════════════════════════════════════════════════════════╗
║  4. CAPACIDAD Y BACKLOG                                          ║
╚═══════════════════════════════════════════════════════════════════╝

  CAPACIDAD REALISTA (1 developer):
    - Max 2-3 setups simultáneos (28 días cada uno)
    - Si entran más → cola con fecha de arranque comunicada

  REGLA: Cuando el backlog tenga ≥3 setups activos:
    1. Nuevos clientes reciben fecha estimada de arranque
    2. El plazo de 28 días empieza en la fecha de arranque asignada
    3. Si el cliente quiere prioridad → recargo del 25 % en setup

  TRACKING:
    - Usar tablero Kanban: Lead → Análisis → Pagado → En curso → Go-live
    - Cada setup tiene: fecha arranque, fecha fin estimada, checklist accesos


╔═══════════════════════════════════════════════════════════════════╗
║  5. RIESGOS Y MITIGACIONES                                      ║
╚═══════════════════════════════════════════════════════════════════╝

  RIESGO 1: Setup Starter con SAP complejo
    Mitigación: Análisis pre-venta OBLIGATORIO. Si se detectan:
      - >1 almacén
      - Custom UDFs en ítems/BP
      - Service Layer publicado pero sin SSL
      - SAP B1 < 10.0 FP2305
    → Proponer upgrade a Business o presupuesto custom.
    Nunca arrancar un Starter si hay dudas de alcance.

  RIESGO 2: Cliente no entrega accesos
    Mitigación: Plazo de 28 días = "desde recepción de accesos
    completos". Poner en T&C y en email de onboarding.
    Si pasan 60 días sin accesos → derecho a cancelar y facturar
    horas de análisis realizadas.

  RIESGO 3: Churn temprano
    Mitigación: Permanencia de 6 meses (ya en T&C).
    Además: onboarding proactivo → primer mes hacer
    2 check-ins para asegurar que todo funciona.

  RIESGO 4: WooCommerce/SAP update rompe sync
    Mitigación: Monitorización automatizada + alertas.
    Staging updates primero. La mensualidad cubre esto.


╔═══════════════════════════════════════════════════════════════════╗
║  6. FUNNEL DE VENTAS — DOS CANALES                               ║
╚═══════════════════════════════════════════════════════════════════╝

  CANAL A: Landing directa (/conector-sap-woocommerce/)
    Landing → Pricing shortcode → CTA "Solicitar presupuesto"
    → Intake form → Llamada análisis → Factura setup → Go-live
    
    KPI a medir: visitas landing, clicks CTA, intakes completados,
    tasa conversión intake→pago, revenue

  CANAL B: Plugin Lite en WordPress.org
    WP.org → Instalan Lite gratis → Dashboard upsell → Landing PRO
    → Intake → Análisis → Setup
    
    KPI a medir: instalaciones activas, clicks upsell dashboard,
    llegadas a landing desde wp-admin, conversiones

  ⚠️ Son FUNNELS DISTINTOS con mensajes distintos:
    - Canal A: "problema SAP manual" → solución completa
    - Canal B: ya probaron Lite → conocen el producto → cerrar rápido

  TODO: Implementar UTM tracking para separar ambos canales en
  analytics. utm_source=wporg vs utm_source=landing.


╔═══════════════════════════════════════════════════════════════════╗
║  7. ADDONS Y UPSELLING                                          ║
╚═══════════════════════════════════════════════════════════════════╝

  REGLA SIMPLE:
    - Si el canal está en la tabla del plan → INCLUIDO en mensualidad
    - Si el canal NO está en el plan → upgrade de plan, no addon suelto

  ¿Por qué no addons sueltos?
    - Percepción de "pricing trampa" si el multicanal tiene letra pequeña
    - Simplifica facturación y comunicación
    - El upgrade de plan genera más MRR que un addon de 50 €/mes

  EXCEPCIONES (posibles addons futuros):
    - WAF perimetral Cloudflare Pro → servicio transversal
    - Desarrollo custom a medida → presupuesto por horas
    - Formación equipo cliente → tarifa día (350 €/día)


╔═══════════════════════════════════════════════════════════════════╗
║  8. ONBOARDING CHECKLIST                                         ║
╚═══════════════════════════════════════════════════════════════════╝

  □ Llamada análisis completada
  □ Plan confirmado (Starter/Business/Enterprise)
  □ Factura de setup emitida y pagada
  □ Acceso SAP B1 Service Layer recibido (URL + user + pass)
  □ Acceso WooCommerce admin recibido
  □ Acceso servidor (SSH/SFTP) si requerido
  □ Mapeo almacenes documentado
  □ Mapeo listas de precios documentado
  □ Canales de venta activos confirmados
  □ Interlocutor designado (nombre + email + disponibilidad)
  □ Fecha de arranque comunicada
  □ → START: Cronómetro de 28 días arranca

  □ Configuración base completada
  □ Test pedido real → SAP verificado
  □ Test stock sync verificado
  □ Catálogo importado (si Business/Enterprise)
  □ Canales adicionales configurados (si aplica)
  □ Go-live confirmado
  □ → ACTIVAR suscripción mensual en Upmind
  □ Check-in semana 2 post-go-live
  □ Check-in mes 1 post-go-live

*/
