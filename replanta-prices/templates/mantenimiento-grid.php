<?php
/**
 * Template: Mantenimiento pricing grid (3 cards, monthly only, with tooltips).
 *
 * Outputs the EXACT same HTML structure and class names as the original
 * Elementor template so the existing CSS works out of the box.
 *
 * Available vars: $category, $region, $type
 *
 * @package Replanta_Prices
 */

defined( 'ABSPATH' ) || exit;

$plans       = $category['plans'];
$footer_note = isset( $category['footer_note'] ) ? $category['footer_note'] : '';
$first       = reset( $plans );
$currency    = Replanta_Prices_Cache::get_effective_currency( $first );
?>
<section class="replanta-plans replanta-plans--mantenimiento" id="planes">

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
            $plan_badge   = Replanta_Prices_Shortcodes::getPlanBadgeData( $plan );
            $price       = Replanta_Prices_Cache::format_plan_price( $plan, 'monthly' );
            $order_url   = Replanta_Prices_Geo::get_order_url( $plan['pid'], $plan );
        ?>
        <article class="<?php echo esc_attr( $card_class ); ?>"
                 id="plan-<?php echo esc_attr( $slug ); ?>"
                 data-plan="<?php echo esc_attr( $slug ); ?>"
                 <?php if ( $plan['featured'] ) : ?>aria-label="<?php esc_attr_e( 'Plan recomendado', 'replanta-prices' ); ?>"<?php endif; ?>>

            <?php if ( ! empty( $plan_badge ) ) : ?>
                <span class="rep-plan-badge <?php echo esc_attr( $plan_badge['class'] ); ?>">
                    <i class="<?php echo esc_attr( $plan_badge['icon'] ); ?>" aria-hidden="true"></i>
                    <?php echo esc_html( $plan_badge['label'] ); ?>
                    <?php if ( ! empty( $plan_badge['tip'] ) ) : ?>
                        <span class="rep-tipwrap" data-dir="up" data-align="left">
                            <button class="rep-tip rep-plan-badge__tip" type="button" aria-label="<?php esc_attr_e( 'Más info', 'replanta-prices' ); ?>">i</button>
                            <span class="rep-tooltip" role="tooltip"><?php echo esc_html( $plan_badge['tip'] ); ?></span>
                        </span>
                    <?php endif; ?>
                </span>
            <?php endif; ?>

            <!-- Header -->
            <header class="plan-head">
                <div>
                    <h3 class="rep-heading-3"><?php echo esc_html( $plan['name'] ); ?></h3>
                    <p class="plan-subtitle"><?php echo esc_html( $plan['subtitle'] ); ?></p>
                </div>
                <div class="rep-heading-3 price" data-plan="<?php echo esc_attr( $slug ); ?>">
                    <?php echo wp_kses_post( $price ); ?><span class="rep-text-small">/<?php esc_html_e( 'mes', 'replanta-prices' ); ?></span>
                </div>
            </header>

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
            <div class="plan-ctas">
                <a href="<?php echo esc_url( $order_url ); ?>"
                   class="elementor-button <?php echo $plan['featured'] ? 'rep-btn-accent' : 'rep-btn-primary'; ?> plan-card-cta">
                    <?php echo esc_html( $plan['cta_text'] ); ?>
                </a>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <?php if ( ! empty( $footer_note ) ) : ?>
        <p class="replanta-plans-footer"><?php echo wp_kses_post( $footer_note ); ?></p>
    <?php endif; ?>
</section>
