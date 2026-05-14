<?php
/**
 * Template: SAP WooCommerce pricing grid (setup + monthly).
 *
 * Same HTML structure and class names as mantenimiento-grid.php
 * with a dual-price layout: one-time setup + monthly fee.
 *
 * Available vars: $category, $region, $type, $discount
 *
 * @package Replanta_Prices
 */

defined( 'ABSPATH' ) || exit;

$plans       = $category['plans'];
$footer_note = isset( $category['footer_note'] ) ? $category['footer_note'] : '';
$first       = reset( $plans );
$currency    = Replanta_Prices_Cache::get_effective_currency( $first );
?>
<section class="replanta-plans replanta-plans--sapwoo" id="precios-sapwoo">

    <!-- Plans bar -->
    <?php if ( ! empty( $category['title'] ) || ! empty( $category['subtitle'] ) ) : ?>
    <div class="plans-bar">
        <div class="left">
            <?php if ( ! empty( $category['title'] ) ) : ?>
                <h2 class="elementor-heading-title rep-heading-2 rep-text-forest"><?php echo wp_kses_post( $category['title'] ); ?></h2>
            <?php endif; ?>
            <?php if ( ! empty( $category['subtitle'] ) ) : ?>
                <div class="rep-text-body"><?php echo wp_kses_post( $category['subtitle'] ); ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Cards grid -->
    <div class="plans-grid">
        <?php foreach ( $plans as $slug => $plan ) :
            $card_class  = 'replanta-pricing-card';
            $card_class .= $plan['featured'] ? ' replanta-pricing-featured' : '';
            $price_setup = Replanta_Prices_Cache::format_plan_price( $plan, 'setup' );
            $price_m     = Replanta_Prices_Cache::format_plan_price( $plan, 'monthly' );
            // Setup product URL (bcm removed — one-time charge)
            $order_url   = remove_query_arg( 'bcm', Replanta_Prices_Geo::get_order_url( $plan['pid'], $plan ) );
            $cta         = Replanta_Prices_Shortcodes::get_plan_cta_config( $type, $slug, $plan, $order_url );

            if ( $discount > 0 ) {
                $plan_currency = Replanta_Prices_Cache::get_effective_currency( $plan );
                $raw_setup     = Replanta_Prices_Cache::get_localized_amount( $plan, 'setup' );
                $raw_m         = Replanta_Prices_Cache::get_localized_amount( $plan, 'monthly' );
                $price_setup   = Replanta_Prices_Geo::format_price_html( round( $raw_setup * ( 1 - $discount / 100 ), 2 ), $plan_currency );
                $price_m       = Replanta_Prices_Geo::format_price_html( round( $raw_m * ( 1 - $discount / 100 ), 2 ), $plan_currency );
            }
        ?>
        <article class="<?php echo esc_attr( $card_class ); ?>"
                 id="plan-<?php echo esc_attr( $slug ); ?>"
                 data-plan="<?php echo esc_attr( $slug ); ?>"
                 <?php if ( $plan['featured'] ) : ?>aria-label="<?php esc_attr_e( 'Plan recomendado', 'replanta-prices' ); ?>"<?php endif; ?>>

            <!-- Header -->
            <header class="plan-head">
                <div>
                    <h3 class="rep-heading-3"><?php echo esc_html( $plan['name'] ); ?></h3>
                    <p class="plan-subtitle"><?php echo esc_html( $plan['subtitle'] ); ?></p>
                </div>
            </header>

            <!-- Pricing: Setup + Monthly -->
            <div class="sapwoo-pricing" style="margin:16px 0 20px;">
                <div class="sapwoo-setup" style="margin-bottom:8px;">
                    <span class="rep-text-small" style="text-transform:uppercase;letter-spacing:.06em;color:var(--rep-text-muted,#6B7D76);font-weight:600;font-size:.72rem;">Setup</span>
                    <div class="rep-heading-3 price" data-plan="<?php echo esc_attr( $slug ); ?>">
                        <?php echo wp_kses_post( $price_setup ); ?>
                    </div>
                </div>
                <div class="sapwoo-monthly" style="display:flex;align-items:baseline;gap:4px;">
                    <span style="font-size:.85rem;color:var(--rep-text-muted,#6B7D76);">+</span>
                    <span class="rep-heading-4 price" style="font-size:1.1rem;">
                        <?php echo wp_kses_post( $price_m ); ?>
                    </span>
                    <span class="rep-text-small" style="color:var(--rep-text-muted,#6B7D76);">/<?php esc_html_e( 'mes', 'replanta-prices' ); ?></span>
                </div>
            </div>

            <!-- Features with tooltips -->
            <ul class="rep-list rep-text-body plan-list">
                <?php foreach ( $plan['features'] as $feat ) :
                    if ( is_array( $feat ) ) :
                        $text = $feat['text'];
                        $tip  = isset( $feat['tip'] ) ? $feat['tip'] : '';
                    else :
                        $text = $feat;
                        $tip  = '';
                    endif;
                ?>
                    <li>
                        <svg class="tick" width="18" height="18" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M229.66,77.66l-128,128a8,8,0,0,1-11.32,0l-56-56a8,8,0,0,1,11.32-11.32L96,188.69,218.34,66.34a8,8,0,0,1,11.32,11.32Z"/></svg>
                        <span>
                            <?php echo wp_kses_post( $text ); ?>
                            <?php if ( ! empty( $tip ) ) : ?>
                            <span class="rep-tipwrap">
                                <button class="rep-tip rep-tip--mini rep-tip--sup" type="button" aria-label="<?php esc_attr_e( 'Más info', 'replanta-prices' ); ?>">i</button>
                                <span class="rep-tooltip" role="tooltip"><?php echo wp_kses_post( $tip ); ?></span>
                            </span>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- CTA -->
            <div class="plan-ctas" style="margin-top:auto;padding-top:16px;">
                <a href="<?php echo esc_url( $cta['href'] ); ?>"
                   class="elementor-button <?php echo $plan['featured'] ? 'rep-btn-accent' : 'rep-btn-primary'; ?> plan-card-cta"
                   style="width:100%;justify-content:center;"
                   <?php foreach ( $cta['attrs'] as $attr_key => $attr_val ) : ?><?php echo esc_attr( $attr_key ) . '="' . esc_attr( $attr_val ) . '" '; ?><?php endforeach; ?>>
                    <?php echo esc_html( $cta['text'] ); ?>
                </a>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <?php if ( ! empty( $footer_note ) ) : ?>
        <p class="replanta-plans-footer">
            <?php echo wp_kses_post( $footer_note ); ?>
            <a href="/condiciones-sapwoo/" style="margin-left:6px;color:var(--rep-teal,#41999F);text-decoration:underline;text-underline-offset:2px;font-size:inherit;"><?php esc_html_e( 'Ver condiciones del servicio', 'replanta-prices' ); ?></a>
        </p>
    <?php endif; ?>
</section>
