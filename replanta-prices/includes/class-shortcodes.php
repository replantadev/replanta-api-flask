<?php
/**
 * Shortcodes: [replanta_pricing], [replanta_price], [replanta_domains].
 *
 * @package Replanta_Prices
 */

defined( 'ABSPATH' ) || exit;

class Replanta_Prices_Shortcodes {

    /** @var bool Whether frontend CSS has been enqueued */
    private static $css_enqueued = false;

    public static function init() {
        add_shortcode( 'replanta_pricing',  array( __CLASS__, 'render_pricing_grid' ) );
        add_shortcode( 'replanta_price',    array( __CLASS__, 'render_price_inline' ) );
        add_shortcode( 'replanta_domains',  array( __CLASS__, 'render_domain_search' ) );
        add_shortcode( 'replanta_tld_grid', array( __CLASS__, 'render_tld_grid' ) );

        add_action( 'wp_ajax_replanta_prices_quote_lead', array( __CLASS__, 'handle_quote_lead' ) );
        add_action( 'wp_ajax_nopriv_replanta_prices_quote_lead', array( __CLASS__, 'handle_quote_lead' ) );
    }

    /**
     * Enqueue frontend CSS only when shortcode is used.
     */
    private static function maybe_enqueue_css() {
        if ( self::$css_enqueued ) {
            return;
        }
        wp_enqueue_style(
            'replanta-pricing-cards',
            REPLANTA_PRICES_URL . 'assets/css/pricing-cards.css',
            array( 'replanta-kit' ),
            REPLANTA_PRICES_VERSION
        );
        // Phosphor Icons — los features pueden contener <i class="ph-bold ph-*">
        if ( ! wp_script_is( 'phosphor-icons', 'enqueued' ) ) {
            wp_enqueue_script(
                'phosphor-icons',
                'https://unpkg.com/@phosphor-icons/web@2.1.1',
                array(),
                null,
                false
            );
        }

        wp_enqueue_script(
            'replanta-prices-quote-modal',
            REPLANTA_PRICES_URL . 'assets/js/quote-modal.js',
            array(),
            REPLANTA_PRICES_VERSION,
            true
        );

        wp_localize_script( 'replanta-prices-quote-modal', 'replantaPricesQuote', array(
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'replanta_prices_quote_nonce' ),
            'contactUrl' => home_url( '/contacto/' ),
            'strings'    => array(
                'title'       => __( 'Solicitar presupuesto', 'replanta-prices' ),
                'subtitle'    => __( 'Cuéntanos lo mínimo y te escribimos con propuesta y plazos.', 'replanta-prices' ),
                'name'        => __( 'Nombre', 'replanta-prices' ),
                'email'       => __( 'Email', 'replanta-prices' ),
                'phone'       => __( 'Teléfono (opcional)', 'replanta-prices' ),
                'website'     => __( 'Web (opcional)', 'replanta-prices' ),
                'message'     => __( 'Necesidades clave', 'replanta-prices' ),
                'submit'      => __( 'Enviar solicitud', 'replanta-prices' ),
                'sending'     => __( 'Enviando...', 'replanta-prices' ),
                'ok'          => __( 'Solicitud enviada. Te contactaremos pronto.', 'replanta-prices' ),
                'error'       => __( 'No se pudo enviar. Prueba de nuevo o usa /contacto/.', 'replanta-prices' ),
                'required'    => __( 'Nombre y email son obligatorios.', 'replanta-prices' ),
                'close'       => __( 'Cerrar', 'replanta-prices' ),
            ),
        ) );

        self::$css_enqueued = true;
    }

    /**
     * Resolve CTA href/text/attrs for a plan.
     *
     * @param string $type
     * @param string $slug
     * @param array  $plan
     * @param string $default_url
     * @return array{href:string,text:string,attrs:array<string,string>}
     */
    public static function get_plan_cta_config( $type, $slug, $plan, $default_url ) {
        $cta_text = ! empty( $plan['cta_text'] ) ? $plan['cta_text'] : __( 'Contratar', 'replanta-prices' );

        if ( 'sapwoo' !== $type ) {
            return array(
                'href'  => $default_url,
                'text'  => $cta_text,
                'attrs' => array(),
            );
        }

        $category     = Replanta_Prices_Cache::get_category( $type );
        $quote_target = isset( $category['quote_target'] ) ? $category['quote_target'] : 'contact';
        $has_quote_flag = array_key_exists( 'quote_request', $plan );
        $has_any_quote_enabled = false;

        if ( isset( $category['plans'] ) && is_array( $category['plans'] ) ) {
            foreach ( $category['plans'] as $cat_plan ) {
                if ( ! empty( $cat_plan['quote_request'] ) ) {
                    $has_any_quote_enabled = true;
                    break;
                }
            }
        }

        // Backward-compatible fallback: if no plan is explicitly enabled,
        // treat all SAPWOO plans as quote-enabled when quote target is set.
        if ( ! $has_any_quote_enabled ) {
            $use_quote_flow = in_array( $quote_target, array( 'contact', 'modal' ), true );
        } else {
            $use_quote_flow = $has_quote_flag ? ! empty( $plan['quote_request'] ) : false;
        }

        if ( ! $use_quote_flow ) {
            return array(
                'href'  => $default_url,
                'text'  => $cta_text,
                'attrs' => array(),
            );
        }

        $contact_url  = add_query_arg(
            array(
                'src'  => 'pricing',
                'type' => sanitize_key( $type ),
                'plan' => sanitize_key( $slug ),
            ),
            home_url( '/contacto/' )
        );

        if ( 'modal' === $quote_target && class_exists( 'RCM_CPT' ) ) {
            return array(
                'href' => '#',
                'text' => __( 'Solicitar presupuesto', 'replanta-prices' ),
                'attrs' => array(
                    'data-quote-modal' => '1',
                    'data-quote-type'  => sanitize_key( $type ),
                    'data-quote-plan'  => sanitize_key( $slug ),
                    'data-skip-bcm'    => '1',
                ),
            );
        }

        return array(
            'href' => $contact_url,
            'text' => __( 'Solicitar presupuesto', 'replanta-prices' ),
            'attrs' => array(
                'data-skip-bcm' => '1',
            ),
        );
    }

    /**
     * Sustainability badge options available per pricing card.
     *
     * @return array<string,string>
     */
    public static function getPlanBadgeOptions() {
        return array(
            ''                         => __( 'Sin badge', 'replanta-prices' ),
            'green_distribution'       => __( 'Green Distribution', 'replanta-prices' ),
            'green_origin_certificado' => __( 'Green Origin certificado', 'replanta-prices' ),
        );
    }

    /**
     * Resolve a plan sustainability badge into render data.
     *
     * @param array $plan Plan definition.
    * @return array{class:string,label:string,tip:string,icon:string}|null
     */
    public static function getPlanBadgeData( $plan ) {
        $key = isset( $plan['sustainability_badge'] ) ? sanitize_key( $plan['sustainability_badge'] ) : '';

        // Backward compatibility with removed options.
        if ( in_array( $key, array( 'renewable_100', 'csrd_scope_3' ), true ) ) {
            $key = 'green_origin_certificado';
        }

        $map = array(
            'green_distribution' => array(
                'class' => 'rep-plan-badge--green-distribution',
                'label' => __( 'Green Distribution', 'replanta-prices' ),
                'tip'   => __( 'Green Distribution: optimización y distribución en capa edge con Cloudflare. Mejora eficiencia y latencia, pero no implica que el origen completo sea 100% renovable.', 'replanta-prices' ),
                'icon'  => 'ph-bold ph-cloud',
            ),
            'green_origin_certificado' => array(
                'class' => 'rep-plan-badge--green-origin',
                'label' => __( 'Green Origin certificado', 'replanta-prices' ),
                'tip'   => __( '100% verde de origen: la infraestructura principal opera sobre energía renovable en toda la cadena técnica del plan.', 'replanta-prices' ),
                'icon'  => 'ph-bold ph-seal-check',
            ),
        );

        return isset( $map[ $key ] ) ? $map[ $key ] : null;
    }

    /**
     * AJAX handler for quote modal submissions.
     */
    public static function handle_quote_lead() {
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'replanta_prices_quote_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce inválido.', 'replanta-prices' ) ), 403 );
        }

        $name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        $website = isset( $_POST['website'] ) ? esc_url_raw( wp_unslash( $_POST['website'] ) ) : '';
        $message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
        $type    = isset( $_POST['pricing_type'] ) ? sanitize_key( wp_unslash( $_POST['pricing_type'] ) ) : '';
        $plan    = isset( $_POST['pricing_plan'] ) ? sanitize_key( wp_unslash( $_POST['pricing_plan'] ) ) : '';
        $url     = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : home_url( '/' );

        if ( 'sapwoo' !== $type ) {
            wp_send_json_error( array( 'message' => __( 'Solo disponible para SAP WooCommerce.', 'replanta-prices' ) ), 400 );
        }

        if ( '' === $name || '' === $email ) {
            wp_send_json_error( array( 'message' => __( 'Nombre y email son obligatorios.', 'replanta-prices' ) ), 400 );
        }

        if ( ! class_exists( 'RCM_CPT' ) ) {
            wp_send_json_error( array( 'message' => __( 'Contact Manager no está activo. Usa /contacto/.', 'replanta-prices' ) ), 500 );
        }

        $ip      = '';
        $country = 'XX';
        if ( class_exists( 'RCM_Security' ) ) {
            $security = RCM_Security::instance();
            $geo      = $security->verify_country();
            if ( is_array( $geo ) && ! empty( $geo['allowed'] ) ) {
                $country = isset( $geo['country'] ) ? $geo['country'] : 'XX';
                $ip      = isset( $geo['ip'] ) ? $geo['ip'] : '';
            } elseif ( is_array( $geo ) && isset( $geo['allowed'] ) && ! $geo['allowed'] ) {
                wp_send_json_error( array( 'message' => __( 'País no permitido para solicitudes.', 'replanta-prices' ) ), 403 );
            }

            $rate_check = $security->check_rate_limit();
            if ( is_wp_error( $rate_check ) ) {
                wp_send_json_error( array( 'message' => $rate_check->get_error_message() ), 429 );
            }

            $dup_check = $security->check_duplicate( $email, 'sapwoo', 24 );
            if ( is_wp_error( $dup_check ) ) {
                wp_send_json_error( array( 'message' => $dup_check->get_error_message() ), 409 );
            }
        }

        $contact_data = array(
            'type'       => 'sapwoo',
            'name'       => $name,
            'email'      => $email,
            'phone'      => $phone,
            'message'    => $message,
            'ip'         => $ip,
            'country'    => $country,
            'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
            'source'     => 'Replanta Prices Modal - SAPWoo',
            'data'       => array(
                'pricing_type' => $type,
                'pricing_plan' => $plan,
                'website'      => $website,
                'source_url'   => $url,
            ),
        );

        $post_id = RCM_CPT::create_contact( $contact_data );
        if ( ! $post_id ) {
            wp_send_json_error( array( 'message' => __( 'No se pudo crear la solicitud.', 'replanta-prices' ) ), 500 );
        }

        self::send_quote_notification( $post_id );
        self::send_quote_to_staffkit( $contact_data );

        wp_send_json_success( array(
            'message' => __( 'Solicitud enviada correctamente.', 'replanta-prices' ),
            'post_id' => $post_id,
        ) );
    }

    /**
     * Send email notification for quote modal leads.
     *
     * @param int $post_id Contact post id in RCM.
     */
    private static function send_quote_notification( $post_id ) {
        $to      = get_option( 'rcm_email_to', get_option( 'admin_email' ) );
        $name    = get_post_meta( $post_id, '_rcm_name', true );
        $email   = get_post_meta( $post_id, '_rcm_email', true );
        $message = get_post_meta( $post_id, '_rcm_message', true );
        $data    = get_post_meta( $post_id, '_rcm_data', true );

        $subject = sprintf( '[%s] Nuevo lead SAP/Woo (pricing modal)', get_bloginfo( 'name' ) );
        $body    = sprintf(
            "Nueva solicitud desde pricing modal:\n\nNombre: %s\nEmail: %s\nTipo: %s\nPlan: %s\nWeb: %s\n\nMensaje:\n%s\n\nAdmin: %s",
            $name,
            $email,
            isset( $data['pricing_type'] ) ? $data['pricing_type'] : '',
            isset( $data['pricing_plan'] ) ? $data['pricing_plan'] : '',
            isset( $data['website'] ) ? $data['website'] : '',
            $message,
            admin_url( 'post.php?post=' . absint( $post_id ) . '&action=edit' )
        );

        wp_mail( $to, $subject, $body );
    }

    /**
     * Forward quote leads to StaffKit when configured in Contact Manager.
     *
     * @param array $contact_data Lead payload.
     */
    private static function send_quote_to_staffkit( $contact_data ) {
        $staffkit_url = get_option( 'rcm_staffkit_url' );
        $staffkit_key = get_option( 'rcm_staffkit_api_key' );

        if ( empty( $staffkit_url ) || empty( $staffkit_key ) ) {
            return;
        }

        $payload = array(
            'email'      => $contact_data['email'],
            'name'       => $contact_data['name'],
            'phone'      => isset( $contact_data['phone'] ) ? $contact_data['phone'] : null,
            'website'    => isset( $contact_data['data']['website'] ) ? $contact_data['data']['website'] : null,
            'source'     => isset( $contact_data['source'] ) ? $contact_data['source'] : 'Replanta Prices',
            'source_url' => isset( $contact_data['data']['source_url'] ) ? $contact_data['data']['source_url'] : home_url( '/' ),
            'user_agent' => isset( $contact_data['user_agent'] ) ? $contact_data['user_agent'] : '',
            'ip_address' => isset( $contact_data['ip'] ) ? $contact_data['ip'] : '',
            'metadata'   => array(
                'type'       => 'sapwoo',
                'country'    => isset( $contact_data['country'] ) ? $contact_data['country'] : 'XX',
                'extra_data' => isset( $contact_data['data'] ) ? $contact_data['data'] : array(),
            ),
        );

        wp_remote_post( rtrim( $staffkit_url, '/' ) . '/api/webhooks/lead-capture.php', array(
            'headers' => array(
                'X-API-Key'   => $staffkit_key,
                'Content-Type'=> 'application/json',
            ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 15,
        ) );
    }

    /**
     * [replanta_pricing type="hosting"]
     * [replanta_pricing type="mantenimiento"]
     */
    public static function render_pricing_grid( $atts ) {
        $atts = shortcode_atts( array(
            'type'     => 'hosting',
            'discount' => 0,
            'schema'   => 'yes', // yes/no - generar schema Product
        ), $atts, 'replanta_pricing' );

        $type     = sanitize_key( $atts['type'] );
        $discount = max( 0, min( 100, (int) $atts['discount'] ) );
        $category = Replanta_Prices_Cache::get_category( $type );

        if ( ! $category || empty( $category['plans'] ) ) {
            return '<!-- Replanta Prices: category "' . esc_html( $type ) . '" not found -->';
        }

        self::maybe_enqueue_css();

        $region = Replanta_Prices_Geo::get_region();

        // Load the appropriate template
        $template = REPLANTA_PRICES_DIR . 'templates/' . $type . '-grid.php';
        if ( ! file_exists( $template ) ) {
            return '<!-- Replanta Prices: template "' . esc_html( $type ) . '-grid.php" not found -->';
        }

        ob_start();
        // Variables available to templates: $category, $region, $type, $discount
        include $template;
        $html = ob_get_clean();
        
        // Generar Schema Product si está habilitado
        if ( 'yes' === $atts['schema'] ) {
            $html .= self::generate_products_schema( $type, $category, $discount );
        }
        
        return $html;
    }

    /**
     * [replanta_price plan="sauce" period="monthly" type="hosting"]
     *
     * Renders just the formatted price inline, e.g. "12,99€" or "$12.99"
     */
    public static function render_price_inline( $atts ) {
        $atts = shortcode_atts( array(
            'type'   => 'hosting',
            'plan'   => '',
            'period' => 'monthly',
        ), $atts, 'replanta_price' );

        $type   = sanitize_key( $atts['type'] );
        $slug   = sanitize_key( $atts['plan'] );
        $period = sanitize_key( $atts['period'] );

        if ( empty( $slug ) ) {
            return '';
        }

        $amount = Replanta_Prices_Cache::get_price( $type, $slug, $period );
        if ( $amount <= 0 ) {
            return '';
        }

        $plan     = Replanta_Prices_Cache::get_plan( $type, $slug );
        $currency = $plan ? Replanta_Prices_Cache::get_effective_currency( $plan ) : null;

        return '<span class="replanta-price-inline" data-plan="' . esc_attr( $slug ) . '" data-period="' . esc_attr( $period ) . '">' 
            . Replanta_Prices_Geo::format_price( $amount, $currency )
            . '</span>';
    }

    /* ────────────────────────────────────────────────────────────────
     * [replanta_tld_grid]
     *
     * Renders the TLD showcase grid used on the /dominios/ landing.
     * Data is read from the WP option `replanta_tld_data` (JSON array).
     *
     * Each TLD entry shape:
     *   { "ext":"com", "desc":"La extensión universal", "price":0, "eco":false }
     *   price: annual EUR float — 0 means unknown → shows "Ver precio" link.
     *   eco:   true → shows the green eco badge.
     *
     * When the Upmind pro API is available, hook into
     * `replanta_prices_sync_cron` to populate prices automatically.
     *
     * Usage: [replanta_tld_grid]
     *        [replanta_tld_grid widget_url="#dom-widget"]
     * ──────────────────────────────────────────────────────────────── */

    /**
     * [replanta_tld_grid]
     *
     * @param array $atts
     * @return string
     */
    public static function render_tld_grid( $atts ) {
        $atts = shortcode_atts( array(
            'widget_url' => '#dom-widget',
        ), $atts, 'replanta_tld_grid' );

        $widget_url = esc_url( $atts['widget_url'] );
        $tlds       = Replanta_Prices_Cache::get_tlds();

        if ( empty( $tlds ) ) {
            return '';
        }

        $delays = array( '1', '2', '3', '4' );
        $html   = '<div class="tld-grid">';

        foreach ( $tlds as $i => $tld ) {
            $ext   = isset( $tld['ext'] )   ? sanitize_key( $tld['ext'] )  : '';
            $desc  = isset( $tld['desc'] )  ? esc_html( $tld['desc'] )     : '';
            $price = isset( $tld['price'] ) ? (float) $tld['price']        : 0;
            $eco   = ! empty( $tld['eco'] );

            if ( ! $ext ) {
                continue;
            }

            $delay = $delays[ $i % 4 ];
            $html .= '<div class="tld-card reveal" data-delay="' . $delay . '">';
            $html .= '<div class="tld-ext"><span>.</span>' . esc_html( $ext ) . '</div>';
            $html .= '<div class="tld-desc">' . $desc . '</div>';

            if ( $price > 0 ) {
                $html .= '<span class="tld-price">desde ' . number_format( $price, 2, ',', '.' ) . ' &euro;/a&ntilde;o</span>';
            } else {
                $html .= '<a href="' . $widget_url . '" class="tld-price">'
                    . 'Ver precio <i class="ph ph-arrow-right" style="font-size:11px;"></i>'
                    . '</a>';
            }

            if ( $eco ) {
                $html .= '<div class="tld-eco-badge"><i class="ph-bold ph-leaf"></i> Eco</div>';
            }

            $html .= '</div>'; // .tld-card
        }

        $html .= '</div>'; // .tld-grid

        return $html;
    }

    /* ────────────────────────────────────────────────────────────────
     * [replanta_domains]
     *
     * Renders the Upmind Domain Availability Checker (upm-dac web component)
     * with the correct currency-code derived from geo detection:
     *   eur region  → currency-code="EUR"  (España / Europa)
     *   usd/latam   → currency-code="USD"  (Upmind checkout only handles EUR + USD)
     *
     * The order-config-url is read from Settings > Replanta Precios > Upmind Order URL.
     * ──────────────────────────────────────────────────────────────── */

    /**
     * [replanta_domains]
     *
     * @return string
     */
    public static function render_domain_search() {
        $settings  = get_option( 'replanta_prices_settings', array() );
        $order_url = ! empty( $settings['upmind_order_url'] )
            ? esc_url( $settings['upmind_order_url'] )
            : 'https://clientes.replanta.net/order/';

        // Map geo region to a currency Upmind checkout actually accepts.
        // LATAM visitors pay in USD (Upmind does not process all local currencies).
        $region        = Replanta_Prices_Geo::get_region();
        $currency_code = ( 'eur' === $region ) ? 'EUR' : 'USD';

        // Enqueue the Upmind DAC web-component script once per page.
        wp_enqueue_script(
            'upmind-dac',
            'https://widgets.upmind.app/dac/upm-dac.min.js',
            array(),
            null,
            false  // in <head>, as required by web components
        );

        // upm-dac uses Shadow DOM (Vue 3 defineCustomElement default).
        // External CSS cannot penetrate the shadow boundary, so we inject
        // a <style> directly into the shadow root via JS.
        static $shadow_js_added = false;
        if ( ! $shadow_js_added ) {
            $shadow_js_added = true;
            $css = implode( '', [
                '.field.is-grouped{display:flex!important;gap:0;align-items:stretch}',
                '.control.is-expanded{flex:1 1 auto}',
                '.field.is-grouped>.control:not(:last-child){margin-right:0!important}',
                '.input{height:52px!important;padding:0 18px!important;border:2px solid rgba(255,255,255,.18)!important;border-right:0!important;border-radius:8px 0 0 8px!important;background:rgba(255,255,255,.1)!important;color:#fff!important;box-shadow:none!important;transition:border-color .2s,background .2s}',
                '.input::placeholder{color:rgba(255,255,255,.45)}',
                '.input:focus{border-color:#93f1c9!important;background:rgba(255,255,255,.15)!important;box-shadow:none!important}',
                '.button.is-dark{height:52px!important;padding:0 28px!important;border:2px solid #93f1c9!important;border-radius:0 8px 8px 0!important;background:#93f1c9!important;color:#1e2f23!important;font-weight:400!important;cursor:pointer;transition:background .2s,border-color .2s}',
                '.button.is-dark:hover{background:#f7d450!important;border-color:#f7d450!important}',
                '.upm-dac-results{margin-top:20px!important}',
                '.dac-domain-row{display:flex!important;flex-wrap:wrap;align-items:center!important;gap:12px;background:rgba(255,255,255,.07)!important;border:1px solid rgba(255,255,255,.12)!important;border-radius:8px!important;padding:14px 18px!important;margin-bottom:8px!important;grid-template-columns:unset!important;transition:background .2s}',
                '.dac-domain-row:hover{background:rgba(255,255,255,.12)!important}',
                '.dac-domain-icon{flex-shrink:0;width:20px}',
                '.dac-domain-icon.has-text-success{color:#93f1c9!important}',
                '.dac-domain-icon.has-text-danger{color:#ff7373!important}',
                '.dac-domain-name{flex:1 1 auto;min-width:0;overflow:hidden}',
                '.dac-domain-fqdn{margin:0!important;font-size:1.05rem!important;color:#fff!important;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}',
                '.has-opacity-75{opacity:.65!important}',
                '.dac-domain-fqdn strong{color:#fff!important;font-weight:700!important}',
                '.dac-domain-status{margin:0!important;font-size:.78rem!important;font-weight:600!important}',
                '.dac-domain-status.has-text-success{color:#93f1c9!important}',
                '.dac-domain-status.has-text-danger{color:#ff9999!important}',
                '.dac-domain-price{margin:0!important;font-weight:700!important;color:#fff!important;white-space:nowrap}',
                '.dac-domain-term{margin:0!important;font-size:.75rem!important;color:rgba(255,255,255,.5)!important;white-space:nowrap;text-align:right!important}',
                '.dac-domain-cta.button.is-success{background:#93f1c9!important;border-color:#93f1c9!important;color:#1e2f23!important;font-weight:700!important;border-radius:8px!important;padding:0 16px!important;height:36px!important;white-space:nowrap;flex-shrink:0;transition:background .2s}',
                '.dac-domain-cta.button.is-success:hover{background:#f7d450!important;border-color:#f7d450!important}',
                '.button.is-outlined.is-rounded.is-fullwidth{background:transparent!important;border:2px solid rgba(255,255,255,.25)!important;color:rgba(255,255,255,.7)!important;border-radius:999px!important;height:44px!important;margin-top:16px!important;transition:border-color .2s,color .2s}',
                '.button.is-outlined.is-rounded.is-fullwidth:hover{border-color:#93f1c9!important;color:#93f1c9!important}',
                '@media(max-width:640px){.dac-domain-price,.dac-domain-term{display:none!important}}',
            ] );
            $inject_js = 'var _rDacCss=' . wp_json_encode( $css ) . ';'
                . '(function(C){'
                . 'var done=false,tries=0;'
                . 'var poll=setInterval(function(){'
                .   'tries++;'
                .   'if(done||tries>100){clearInterval(poll);return;}'
                .   'var el=document.querySelector("upm-dac");if(!el)return;'
                .   'var r=el.shadowRoot;'
                .   'if(!r||!r.children.length)return;'
                .   'clearInterval(poll);'
                .   'if(window.CSSStyleSheet&&"replaceSync"in CSSStyleSheet.prototype){'
                .     'try{var sh=new CSSStyleSheet();sh.replaceSync(C);r.adoptedStyleSheets=[sh];done=true;return;}catch(e){}'
                .   '}'
                .   'if(!document.getElementById("rep-dac-theme")){var s=document.createElement("style");s.id="rep-dac-theme";s.textContent=C;document.head.appendChild(s);}'
                .   'done=true;'
                . '},100);'
                . '})(_rDacCss);';
            wp_add_inline_script( 'upmind-dac', $inject_js, 'after' );
        }

        return '<div class="rep-domain-search">'
            . '<upm-dac'
            . ' order-config-url="' . $order_url . '"'
            . ' currency-code="'   . esc_attr( $currency_code ) . '"'
            . '></upm-dac>'
            . '</div>';
    }
    
    /* ────────────────────────────────────────────────────────────────
     * SCHEMA.ORG PRODUCT GENERATOR
     *
     * Genera JSON-LD Product válido para cada plan del shortcode.
     * Cumple con los requisitos de Google Rich Results para Product.
     * ──────────────────────────────────────────────────────────────── */
    
    /**
     * Genera Schema Product para todos los planes de una categoría.
     *
     * @param string $type     Tipo de producto (hosting, mantenimiento).
     * @param array  $category Datos de la categoría con planes.
     * @param int    $discount Descuento aplicado (0-100).
     * @return string JSON-LD script tags.
     */
    private static function generate_products_schema( $type, $category, $discount = 0 ) {
        if ( empty( $category['plans'] ) ) {
            return '';
        }
        
        $output = "\n<!-- Replanta Prices - Schema.org Product (SEO) -->\n";
        
        // Categoría según tipo
        $schema_category = self::get_schema_category( $type );
        
        // Imagen del producto (logo de Replanta o imagen específica)
        $product_image = self::get_product_image( $type );
        
        // URL base del contenido donde se renderiza el shortcode.
        // Evita schemas apuntando a home cuando los planes viven en una landing específica.
        $base_url = self::get_schema_base_url();
        
        foreach ( $category['plans'] as $slug => $plan ) {
            $product_schema = self::build_single_product_schema(
                $slug,
                $plan,
                $type,
                $schema_category,
                $product_image,
                $base_url,
                $discount
            );
            
            if ( $product_schema ) {
                $output .= '<script type="application/ld+json">' . "\n";
                $output .= wp_json_encode( $product_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
                $output .= "\n</script>\n";
            }
        }
        
        return $output;
    }
    
    /**
     * Construye el schema de un producto individual.
     */
    private static function build_single_product_schema( $slug, $plan, $type, $category, $image, $base_url, $discount ) {
        $name = $plan['name'] ?? ucfirst( $slug );
        $subtitle = $plan['subtitle'] ?? '';
        
        // Descripción del producto
        $description = self::build_product_description( $plan, $type );
        
        // Obtener moneda según región del visitante (coincide con lo mostrado en pantalla)
        $currency = Replanta_Prices_Cache::get_effective_currency( $plan );
        
        // Obtener precios en la moneda del visitante
        $price_monthly = Replanta_Prices_Cache::get_localized_amount( $plan, 'monthly' );
        $price_yearly  = Replanta_Prices_Cache::get_localized_amount( $plan, 'annual' );
        
        // Aplicar descuento si existe
        if ( $discount > 0 ) {
            $multiplier = ( 100 - $discount ) / 100;
            $price_monthly = round( $price_monthly * $multiplier, 2 );
            $price_yearly  = round( $price_yearly * $multiplier, 2 );
        }
        
        // URL del producto (anchor del card en la landing).
        $product_url = untrailingslashit( $base_url ) . '#plan-' . $slug;
        
        // Construir schema base
        $schema = array(
            '@context' => 'https://schema.org',
            '@type'    => 'Product',
            'name'     => self::format_product_name( $name, $type ),
            'description' => $description,
            'sku'      => $slug,
            'brand'    => array(
                '@type' => 'Brand',
                'name'  => 'Replanta',
            ),
            'category' => $category,
            'image'    => $image,
            'url'      => $product_url,
        );
        
        // Añadir offers según precios disponibles
        $offers = self::build_offers( $slug, $plan, $price_monthly, $price_yearly, $product_url, $currency );
        
        if ( ! empty( $offers ) ) {
            if ( count( $offers ) > 1 ) {
                // AggregateOffer para múltiples opciones de precio
                $prices = array_column( $offers, 'price' );
                $schema['offers'] = array(
                    '@type'         => 'AggregateOffer',
                    'lowPrice'      => min( $prices ),
                    'highPrice'     => max( $prices ),
                    'priceCurrency' => $currency,
                    'offerCount'    => count( $offers ),
                    'offers'        => $offers,
                );
            } else {
                // Una sola oferta
                $schema['offers'] = $offers[0];
            }
        }
        
        // Añadir review agregado si está configurado (solo si tienes reviews reales)
        $rating_data = get_option( 'replanta_prices_aggregate_rating' );
        if ( ! empty( $rating_data['enabled'] ) && ! empty( $rating_data['rating'] ) && ! empty( $rating_data['count'] ) ) {
            $schema['aggregateRating'] = array(
                '@type'       => 'AggregateRating',
                'ratingValue' => (string) $rating_data['rating'],
                'reviewCount' => (string) $rating_data['count'],
                'bestRating'  => '5',
                'worstRating' => '1',
            );
        }
        
        return $schema;
    }
    
    /**
     * Construye las ofertas (Offer) para un producto.
     *
     * @param string $slug         Plan slug.
     * @param array  $plan         Plan data.
     * @param float  $price_monthly Monthly price.
     * @param float  $price_yearly  Yearly price.
     * @param string $url          Product URL.
     * @param string $currency     Currency code (EUR, USD, etc.).
     * @return array
     */
    private static function build_offers( $slug, $plan, $price_monthly, $price_yearly, $url, $currency = 'EUR' ) {
        $offers = array();
        $valid_until = gmdate( 'Y-m-d', strtotime( '+1 year' ) );
        $offer_url = ! empty( $plan['cta_url'] ) ? esc_url_raw( $plan['cta_url'] ) : $url;
        
        // Seller info (recomendado por Google)
        $seller = array(
            '@type' => 'Organization',
            'name'  => get_bloginfo( 'name' ),
            'url'   => home_url( '/' ),
        );
        
        // Oferta mensual
        if ( $price_monthly > 0 ) {
            $offer_monthly = array(
                '@type'           => 'Offer',
                'name'            => 'Suscripción Mensual',
                'price'           => number_format( $price_monthly, 2, '.', '' ),
                'priceCurrency'   => $currency,
                'availability'    => 'https://schema.org/InStock',
                'url'             => $offer_url,
                'priceValidUntil' => $valid_until,
                'seller'          => $seller,
                'priceSpecification' => array(
                    '@type'           => 'UnitPriceSpecification',
                    'price'           => number_format( $price_monthly, 2, '.', '' ),
                    'priceCurrency'   => $currency,
                    'referenceQuantity' => array(
                        '@type' => 'QuantitativeValue',
                        'value' => '1',
                        'unitCode' => 'MON',
                    ),
                ),
            );
            $offers[] = $offer_monthly;
        }
        
        // Oferta anual (si existe y es > 0)
        if ( $price_yearly > 0 ) {
            $offer_yearly = array(
                '@type'           => 'Offer',
                'name'            => 'Suscripción Anual',
                'price'           => number_format( $price_yearly, 2, '.', '' ),
                'priceCurrency'   => $currency,
                'availability'    => 'https://schema.org/InStock',
                'url'             => $offer_url,
                'priceValidUntil' => $valid_until,
                'seller'          => $seller,
                'priceSpecification' => array(
                    '@type'           => 'UnitPriceSpecification',
                    'price'           => number_format( $price_yearly, 2, '.', '' ),
                    'priceCurrency'   => $currency,
                    'referenceQuantity' => array(
                        '@type' => 'QuantitativeValue',
                        'value' => '1',
                        'unitCode' => 'ANN',
                    ),
                ),
            );
            $offers[] = $offer_yearly;
        }
        
        return $offers;
    }
    
    /**
     * Construye la descripción del producto a partir de features.
     */
    private static function build_product_description( $plan, $type ) {
        $parts = array();
        
        // Añadir subtitle si existe
        if ( ! empty( $plan['subtitle'] ) ) {
            $parts[] = wp_strip_all_tags( $plan['subtitle'] );
        }
        
        // Extraer features principales (primeros 4)
        if ( ! empty( $plan['features'] ) ) {
            $count = 0;
            foreach ( $plan['features'] as $feature ) {
                if ( $count >= 4 ) break;
                
                // Feature puede ser string o array con 'text'
                $text = is_array( $feature ) ? ( $feature['text'] ?? '' ) : $feature;
                if ( $text ) {
                    $parts[] = wp_strip_all_tags( $text );
                    $count++;
                }
            }
        }
        
        $description = implode( '. ', $parts );
        
        // Limitar a 300 caracteres para schema
        if ( strlen( $description ) > 300 ) {
            $description = substr( $description, 0, 297 ) . '...';
        }
        
        return $description;
    }
    
    /**
     * Formatea el nombre del producto completo.
     */
    private static function format_product_name( $name, $type ) {
        $type_labels = array(
            'hosting'       => 'Hosting WordPress',
            'mantenimiento' => 'Mantenimiento WordPress',
        );
        
        $type_label = $type_labels[ $type ] ?? ucfirst( $type );
        
        return $type_label . ' ' . $name;
    }
    
    /**
     * Obtiene la categoría de schema según el tipo.
     */
    private static function get_schema_category( $type ) {
        $categories = array(
            'hosting'       => 'Web Hosting Services',
            'mantenimiento' => 'Website Maintenance Services',
        );
        
        return $categories[ $type ] ?? 'Software Services';
    }
    
    /**
     * Obtiene la imagen del producto (REQUERIDA por Google).
     * Intenta múltiples fuentes para garantizar una imagen válida.
     */
    private static function get_product_image( $type ) {
        // 1. Imagen personalizada por tipo
        $custom_image = get_option( 'replanta_prices_' . $type . '_image' );
        if ( $custom_image && filter_var( $custom_image, FILTER_VALIDATE_URL ) ) {
            return $custom_image;
        }
        
        // 2. Logo del sitio (WordPress Site Icon)
        $custom_logo_id = get_theme_mod( 'custom_logo' );
        if ( $custom_logo_id ) {
            $logo_url = wp_get_attachment_image_url( $custom_logo_id, 'full' );
            if ( $logo_url ) {
                return $logo_url;
            }
        }
        
        // 3. Site Icon
        $site_icon_id = get_option( 'site_icon' );
        if ( $site_icon_id ) {
            $icon_url = wp_get_attachment_image_url( $site_icon_id, 'full' );
            if ( $icon_url ) {
                return $icon_url;
            }
        }
        
        // 4. Fallback: buscar logo en uploads
        $upload_dir = wp_upload_dir();
        $possible_logos = array( 'replanta-logo.png', 'logo.png', 'replanta-logo.svg' );
        foreach ( $possible_logos as $logo_file ) {
            $logo_path = $upload_dir['basedir'] . '/' . $logo_file;
            if ( file_exists( $logo_path ) ) {
                return $upload_dir['baseurl'] . '/' . $logo_file;
            }
        }
        
        // 5. Último fallback: placeholder (mejor que nada, pero deberías subir un logo)
        return 'https://replanta.net/wp-content/uploads/replanta-logo.png';
    }

    /**
     * URL base para el schema (página actual cuando exista).
     *
     * @return string
     */
    private static function get_schema_base_url() {
        if ( is_singular() ) {
            $permalink = get_permalink();
            if ( $permalink ) {
                return $permalink;
            }
        }

        return home_url( '/' );
    }
}
