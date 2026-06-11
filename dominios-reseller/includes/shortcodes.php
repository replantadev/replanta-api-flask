<?php
/**
 * Dominios Reseller — Shortcodes públicos
 *
 * [mostrar_dominio]
 *   Detecta el dominio de origen del visitante (via ?dominio= o HTTP_REFERER)
 *   y renderiza el hero personalizado de la página Huella Digital.
 *   Si no detecta ningún dominio cliente, muestra un hero genérico.
 *
 * Uso en Elementor: Shortcode Widget o HTML Widget con do_shortcode().
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─────────────────────────────────────────────────────────────────────────────
// Registro
// ─────────────────────────────────────────────────────────────────────────────
add_shortcode( 'mostrar_dominio', 'dr_shortcode_mostrar_dominio' );

// ─────────────────────────────────────────────────────────────────────────────
// Helper: obtener datos del dominio desde la BD
// ─────────────────────────────────────────────────────────────────────────────
function dr_obtener_datos_dominio_actual(): ?array {
    global $wpdb;
    $table = $wpdb->prefix . 'dominios_reseller';

    // 1. Parámetro GET explícito → ?dominio= | ?domain= (badge sello) | ?sitio= (Schema.org sello)
    $detected = '';
    foreach ( [ 'dominio', 'domain', 'sitio' ] as $param ) {
        if ( ! empty( $_GET[ $param ] ) ) {
            $detected = sanitize_text_field( wp_unslash( $_GET[ $param ] ) );
            // Eliminar www. y normalizar
            $detected = preg_replace( '/^www\./i', '', strtolower( trim( $detected ) ) );
            break;
        }
    }

    // 2. HTTP_REFERER como fallback
    if ( empty( $detected ) && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
        $host = wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ), PHP_URL_HOST );
        if ( $host ) {
            $host = preg_replace( '/^www\./i', '', strtolower( trim( $host ) ) );
            // No usar nuestro propio dominio como origen
            $own = preg_replace( '/^www\./i', '', strtolower( wp_parse_url( home_url(), PHP_URL_HOST ) ?? '' ) );
            if ( $host !== $own ) {
                $detected = $host;
            }
        }
    }

    if ( empty( $detected ) ) {
        return null;
    }

    // Búsqueda exacta primero, luego dominio raíz (sin subdominio)
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT domain, server, trees_planted, co2_evaded, fecha_emision, startdate, status
               FROM {$table}
              WHERE domain = %s AND status = 'Activo'
              LIMIT 1",
            $detected
        ),
        ARRAY_A
    );

    // Si no coincide exactamente, buscar por dominio raíz ignorando subdominios
    if ( ! $row ) {
        $parts = explode( '.', $detected );
        if ( count( $parts ) > 2 ) {
            $root = implode( '.', array_slice( $parts, -2 ) );
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT domain, server, trees_planted, co2_evaded, fecha_emision, startdate, status
                       FROM {$table}
                      WHERE domain LIKE %s AND status = 'Activo'
                      LIMIT 1",
                    '%' . $wpdb->esc_like( $root )
                ),
                ARRAY_A
            );
        }
    }

    return $row ?: null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Shortcode principal
// ─────────────────────────────────────────────────────────────────────────────
function dr_shortcode_mostrar_dominio( $atts ): string {
    $atts = shortcode_atts( [
        'mostrar_cta' => 'si',
    ], $atts, 'mostrar_dominio' );

    $datos       = dr_obtener_datos_dominio_actual();
    $mostrar_cta = filter_var( $atts['mostrar_cta'], FILTER_VALIDATE_BOOLEAN );

    /* ── Opciones fallback configuradas en el admin ── */
    $opts          = get_option( 'dominios_reseller_options', [] );
    $fallback_title = esc_html( $opts['hero_title'] ?? 'Hosting Ecológico con Impacto Positivo' );
    $fallback_desc  = esc_html( $opts['hero_description'] ?? 'Nuestro hosting funciona con energía 100 % renovable y contribuye activamente a la reforestación del planeta.' );

    if ( $datos ) {
        return dr_render_hero_dominio( $datos, $mostrar_cta );
    }

    return dr_render_hero_generico( $fallback_title, $fallback_desc, $mostrar_cta );
}

// ─────────────────────────────────────────────────────────────────────────────
// Render: hero personalizado (dominio detectado)
// ─────────────────────────────────────────────────────────────────────────────
function dr_render_hero_dominio( array $d, bool $mostrar_cta ): string {
    $domain        = esc_html( $d['domain'] );
    $trees         = (int) $d['trees_planted'];
    $co2_kg        = (float) $d['co2_evaded'];
    $fecha_inicio  = ! empty( $d['fecha_emision'] ) ? $d['fecha_emision'] : null;
    $is_new        = ( $trees === 0 );

    /* Formateos */
    $trees_fmt = $trees > 0 ? number_format( $trees ) . '+' : '0';
    if ( $co2_kg >= 1000 ) {
        $co2_fmt = number_format( $co2_kg / 1000, 1, ',', '.' ) . ' t';
    } else {
        $co2_fmt = number_format( $co2_kg, 1, ',', '.' ) . ' kg';
    }

    $desde_html = '';
    if ( $fecha_inicio ) {
        $ts        = strtotime( $fecha_inicio );
        $desde_fmt = $ts ? wp_date( 'M Y', $ts ) : esc_html( $fecha_inicio );
        $desde_html = '<span class="md-since"><i class="ph-bold ph-calendar-blank"></i> Carbono negativo desde ' . esc_html( $desde_fmt ) . '</span>';
    }

    /* Mensaje para dominios nuevos sin árboles aún */
    $opts           = get_option( 'dominios_reseller_options', [] );
    $new_msg        = esc_html( $opts['new_domain_message'] ?? '' );
    $trees_row      = $is_new
        ? '<p class="md-new-msg"><i class="ph-bold ph-leaf"></i> ' . ( $new_msg ?: 'Pronto plantaremos el primer árbol para este dominio. ¡Gracias por ser parte del cambio!' ) . '</p>'
        : '';

    /* Ticker */
    $ticker_trees = $is_new ? '—' : esc_html( $trees_fmt );
    $ticker_co2   = $is_new ? '—' : esc_html( $co2_fmt );

    $cta_html = '';
    if ( $mostrar_cta ) {
        $cta_html = '
        <div class="md-cta-row">
          <a href="/hosting-wordpress/" class="md-btn md-btn--primary">
            <i class="ph-bold ph-tree"></i>
            Hacer mi web carbono negativa
          </a>
          <a href="#hd-que-significa" class="md-btn md-btn--ghost">
            <i class="ph-bold ph-book-open"></i>
            Cómo funciona
          </a>
        </div>';
    }

    ob_start();
    ?>
    <section class="md-hero md-hero--domain">
      <div class="md-hero__bg" aria-hidden="true"></div>
      <div class="md-hero__inner">

        <div class="md-seal-ring-wrap" aria-hidden="true">
          <svg class="md-seal-tree" viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg"><path fill="#93F1C9" d="M168,240.2h24v-16c0-17.7,14.3-32,32-32s32,14.3,32,32v79.9h24c5.9,0,9.8,6.3,7.2,11.6L256,368.1V480c0,17.7-14.2,32-31.9,32S192,497.7,192,480V304.1l-31.1-52.4C158.2,246.4,162.1,240.2,168,240.2z"/><path fill="#93F1C9" fill-opacity="0.4" d="M414.8,448H256v-79.9l31.1-52.4c2.7-5.3-1.2-11.6-7.2-11.6h-24v-79.9c0-17.7-14.3-32-32-32s-32,14.3-32,32v16h-24c-5.9,0-9.8,6.3-7.2,11.6l31.1,52.4v143.9H32.9c-28.5,0-43.7-34.5-24.7-56.4l69-79.6h-15c-25.6,0-39.5-29.2-23.2-48.5l60.9-71.5H89.2c-21.3,0-32.9-22.5-19.3-37.3L204.7,8.3C215.1-3,233-3,243.4,8.2l134.9,146.5c13.6,14.8,1.1,37.3-19.3,37.3h-10.8l60.9,71.5c16.3,19.3,2.4,48.5-23.2,48.5h-15.2l69,79.6C458.5,413.4,443.4,448,414.8,448z"/></svg>
          <span class="md-seal-ring"></span>
        </div>

        <div class="md-badge">
          <i class="ph-bold ph-cursor-click"></i>
          Has pulsado el sello correcto
        </div>

        <h1 class="md-hero__title">
          <span class="md-domain-name"><?php echo $domain; ?></span><br>
          es <em class="md-grad">carbono negativo</em>
        </h1>

        <p class="md-hero__sub">
          Este sitio web está alojado en servidores de energía 100 % renovable.
          Por cada plan activo, Replanta planta un árbol real en proyectos de reforestación verificados.
        </p>

        <?php echo $desde_html; ?>
        <?php echo $trees_row; ?>

        <?php if ( ! $is_new ) : ?>
        <div class="md-ticker">
          <div class="md-ticker__item">
            <span class="md-ticker__num"><?php echo $ticker_trees; ?></span>
            <span class="md-ticker__lbl">Árboles plantados</span>
          </div>
          <div class="md-ticker__item">
            <span class="md-ticker__num"><?php echo $ticker_co2; ?></span>
            <span class="md-ticker__lbl">CO₂ evitado</span>
          </div>
          <div class="md-ticker__item">
            <span class="md-ticker__num">100 %</span>
            <span class="md-ticker__lbl">Energía renovable</span>
          </div>
          <div class="md-ticker__item">
            <span class="md-ticker__num">1,1</span>
            <span class="md-ticker__lbl">PUE centro de datos</span>
          </div>
        </div>
        <?php endif; ?>

        <?php echo $cta_html; ?>

      </div>
    </section>
    <?php
    return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────────────────────
// Render: hero genérico (sin dominio detectado)
// ─────────────────────────────────────────────────────────────────────────────
function dr_render_hero_generico( string $titulo, string $descripcion, bool $mostrar_cta ): string {
    $cta_html = '';
    if ( $mostrar_cta ) {
        $cta_html = '
        <div class="md-cta-row">
          <a href="/hosting-wordpress/" class="md-btn md-btn--primary">
            <i class="ph-bold ph-tree"></i>
            Hacer mi web carbono negativa
          </a>
          <a href="#hd-que-significa" class="md-btn md-btn--ghost">
            <i class="ph-bold ph-book-open"></i>
            Descubrir más
          </a>
        </div>';
    }

    ob_start();
    ?>
    <section class="md-hero md-hero--generic">
      <div class="md-hero__bg" aria-hidden="true"></div>
      <div class="md-hero__inner">

        <div class="md-seal-ring-wrap" aria-hidden="true">
          <svg class="md-seal-tree" viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg"><path fill="#93F1C9" d="M168,240.2h24v-16c0-17.7,14.3-32,32-32s32,14.3,32,32v79.9h24c5.9,0,9.8,6.3,7.2,11.6L256,368.1V480c0,17.7-14.2,32-31.9,32S192,497.7,192,480V304.1l-31.1-52.4C158.2,246.4,162.1,240.2,168,240.2z"/><path fill="#93F1C9" fill-opacity="0.4" d="M414.8,448H256v-79.9l31.1-52.4c2.7-5.3-1.2-11.6-7.2-11.6h-24v-79.9c0-17.7-14.3-32-32-32s-32,14.3-32,32v16h-24c-5.9,0-9.8,6.3-7.2,11.6l31.1,52.4v143.9H32.9c-28.5,0-43.7-34.5-24.7-56.4l69-79.6h-15c-25.6,0-39.5-29.2-23.2-48.5l60.9-71.5H89.2c-21.3,0-32.9-22.5-19.3-37.3L204.7,8.3C215.1-3,233-3,243.4,8.2l134.9,146.5c13.6,14.8,1.1,37.3-19.3,37.3h-10.8l60.9,71.5c16.3,19.3,2.4,48.5-23.2,48.5h-15.2l69,79.6C458.5,413.4,443.4,448,414.8,448z"/></svg>
          <span class="md-seal-ring"></span>
        </div>

        <div class="md-badge md-badge--sun">
          <i class="ph-bold ph-leaf"></i>
          Hosting Carbono Negativo Certificado
        </div>

        <h1 class="md-hero__title">
          <?php echo esc_html( $titulo ); ?>
        </h1>

        <p class="md-hero__sub">
          <?php echo esc_html( $descripcion ); ?>
        </p>

        <div class="md-ticker">
          <div class="md-ticker__item">
            <span class="md-ticker__num" id="md-hero-trees">—</span>
            <span class="md-ticker__lbl">Árboles plantados</span>
          </div>
          <div class="md-ticker__item">
            <span class="md-ticker__num" id="md-hero-co2">—</span>
            <span class="md-ticker__lbl">CO₂ evitado</span>
          </div>
          <div class="md-ticker__item">
            <span class="md-ticker__num">100 %</span>
            <span class="md-ticker__lbl">Energía renovable</span>
          </div>
          <div class="md-ticker__item">
            <span class="md-ticker__num">1,1</span>
            <span class="md-ticker__lbl">PUE centro de datos</span>
          </div>
        </div>

        <?php echo $cta_html; ?>

      </div>
    </section>
    <?php
    return ob_get_clean();
}

// ─────────────────────────────────────────────────────────────────────────────
// Estilos inline del hero (encolados una sola vez)
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_head', 'dr_shortcode_hero_styles' );
function dr_shortcode_hero_styles(): void {
    static $done = false;
    if ( $done ) return;
    $done = true;
    ?>
    <style id="dr-mostrar-dominio-css">
    /* ── mostrar_dominio hero ── */
    .md-hero {
      position: relative;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 100px 24px 80px;
      overflow: hidden;
      text-align: center;
      background: #0B1710;
    }
    .md-hero__bg {
      position: absolute; inset: 0; pointer-events: none;
      background:
        radial-gradient(ellipse 60% 55% at 25% 40%, rgba(65,153,159,.18) 0%, transparent 65%),
        radial-gradient(ellipse 50% 45% at 80% 60%, rgba(147,241,201,.10) 0%, transparent 65%);
    }
    .md-hero__inner {
      max-width: 900px; margin: 0 auto;
      position: relative; z-index: 1;
    }

    /* Seal icon */
    .md-seal-ring-wrap {
      display: inline-flex; align-items: center; justify-content: center;
      width: 52px; height: 52px;
      background: rgba(147,241,201,.08);
      border: 1.5px solid rgba(147,241,201,.3);
      border-radius: 50%; margin-bottom: 1rem;
      position: relative;
      animation: md-glow 3s ease-in-out infinite;
    }
    .md-seal-tree {
      width: auto; height: 26px;
      display: block;
    }
    .md-seal-ring {
      position: absolute; inset: -8px;
      border: 1px solid rgba(147,241,201,.15);
      border-radius: 50%;
      animation: md-ring 3s ease-in-out infinite;
    }
    @keyframes md-glow {
      0%,100% { box-shadow: 0 0 28px rgba(147,241,201,.18), 0 0 56px rgba(147,241,201,.07); }
      50%      { box-shadow: 0 0 48px rgba(147,241,201,.32), 0 0 88px rgba(147,241,201,.13); }
    }
    @keyframes md-ring {
      0%,100% { transform: scale(1); opacity: .5; }
      50%      { transform: scale(1.07); opacity: .2; }
    }

    /* Badge */
    .md-badge {
      display: inline-flex; align-items: center; gap: 7px;
      font: 600 .75rem/1 'Inter',system-ui,sans-serif;
      text-transform: uppercase; letter-spacing: .1em;
      padding: 6px 14px; border-radius: 999px;
      background: rgba(247,212,80,.12);
      border: 1px solid rgba(247,212,80,.3);
      color: #F7D450; margin-bottom: 1.4rem;
    }
    .md-badge--sun { /* same, default */ }

    /* Title */
    .md-hero__title {
      font-family: 'Sora', system-ui, sans-serif !important;
      font-size: clamp(2.1rem, 5.2vw, 3.8rem) !important;
      font-weight: 700 !important;
      line-height: 1.15 !important;
      color: #fff !important;
      letter-spacing: -.022em;
      margin-bottom: 1.2rem !important;
    }
    .md-domain-name {
      display: inline-block;
      color: #93F1C9;
      font-style: normal;
    }
    .md-grad {
      font-style: normal;
      background: linear-gradient(90deg, #93F1C9 0%, #41999F 55%, #2A6B70 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    /* Sub */
    .md-hero__sub {
      font: 400 clamp(.97rem,2vw,1.12rem)/1.65 'Inter',system-ui,sans-serif;
      color: rgba(255,255,255,.55);
      max-width: 640px; margin: 0 auto 1.75rem;
    }

    /* Since / new msg */
    .md-since {
      display: inline-flex; align-items: center; gap: 7px;
      font: 600 .8rem/1 'Inter',system-ui,sans-serif;
      color: rgba(255,255,255,.35);
      margin-bottom: 1.5rem; display: block;
    }
    .md-new-msg {
      font-size: .9rem; color: rgba(255,255,255,.45);
      max-width: 560px; margin: 0 auto 1.5rem;
      background: rgba(147,241,201,.06);
      border: 1px solid rgba(147,241,201,.15);
      border-radius: 12px; padding: 12px 16px;
      display: flex; align-items: flex-start; gap: 8px;
      text-align: left;
    }
    .md-new-msg i { color: #93F1C9; flex-shrink: 0; margin-top: 2px; }

    /* Ticker */
    .md-ticker {
      display: flex; justify-content: center;
      background: rgba(147,241,201,.06);
      border: 1px solid rgba(147,241,201,.15);
      border-radius: 14px; overflow: hidden;
      margin-bottom: 2.25rem;
    }
    .md-ticker__item {
      flex: 1; min-width: 120px;
      padding: 16px 18px; text-align: center;
      position: relative;
    }
    .md-ticker__item + .md-ticker__item::before {
      content: ''; position: absolute; left: 0; top: 20%; height: 60%;
      width: 1px; background: rgba(147,241,201,.15);
    }
    .md-ticker__num {
      display: block;
      font: 700 1.45rem/1 'Sora',system-ui,sans-serif;
      color: #93F1C9; margin-bottom: 4px;
    }
    .md-ticker__lbl {
      font-size: .72rem; color: rgba(255,255,255,.38);
      text-transform: uppercase; letter-spacing: .06em;
    }

    /* CTAs */
    .md-cta-row {
      display: flex; gap: 14px; justify-content: center; flex-wrap: wrap;
    }
    .md-btn {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 13px 26px; border-radius: 14px; border: none; cursor: pointer;
      font: 600 .92rem/1 'Inter',system-ui,sans-serif;
      text-decoration: none !important; white-space: nowrap;
      transition: all .22s ease;
    }
    .md-btn--primary {
      background: #41999F; color: #fff !important;
    }
    .md-btn--primary:hover {
      background: #37878d;
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(65,153,159,.4);
    }
    .md-btn--ghost {
      background: transparent;
      color: #93F1C9 !important;
      border: 1.5px solid rgba(147,241,201,.4);
    }
    .md-btn--ghost:hover {
      border-color: #93F1C9;
      background: rgba(147,241,201,.08);
    }

    /* Responsive */
    @media (max-width: 600px) {
      .md-hero { padding: 80px 20px 60px; }
      .md-ticker { flex-direction: column; }
      .md-ticker__item + .md-ticker__item::before { display: none; }
    }
    </style>
    <?php
}

// ─────────────────────────────────────────────────────────────────────────────
// JS: cargar totales globales en hero genérico via REST
// ─────────────────────────────────────────────────────────────────────────────
add_action( 'wp_footer', 'dr_shortcode_hero_script' );
function dr_shortcode_hero_script(): void {
    // Solo cargar si el shortcode está en la página
    if ( ! has_shortcode( get_post_field( 'post_content', get_the_ID() ), 'mostrar_dominio' ) ) {
        return;
    }
    ?>
    <script>
    (function(){
      var trees = document.getElementById('md-hero-trees');
      var co2   = document.getElementById('md-hero-co2');
      if (!trees && !co2) return; // hero personalizado, no genérico

      var api = (typeof replantaApiRoot !== 'undefined')
        ? replantaApiRoot
        : (window.location.origin + '/wp-json/dr/v1/trees');

      fetch(api, { cache: 'no-store' })
        .then(function(r){ return r.ok ? r.json() : null; })
        .then(function(data){
          if (!data) return;
          if (trees && data.trees > 0) trees.textContent = data.trees + '+';
          if (co2   && data.co2 > 0) {
            var kg = Math.round(data.co2);
            co2.textContent = kg >= 1000
              ? (kg / 1000).toFixed(1).replace('.', ',') + ' t'
              : kg + ' kg';
          }
        })
        .catch(function(){
          if (trees) trees.textContent = '163+';
          if (co2)   co2.textContent   = '3,5 t';
        });
    })();
    </script>
    <?php
}
