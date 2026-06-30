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
$base_plans  = array();
$addon_plans = array();
$addon_plan  = null;

foreach ( $plans as $slug => $plan ) {
    if ( ! empty( $plan['is_addon'] ) ) {
        $addon_plans[ $slug ] = $plan;
        continue;
    }

    $base_plans[ $slug ] = $plan;
}

if ( ! empty( $addon_plans ) ) {
    $addon_plan = reset( $addon_plans );
}

$feature_kind_resolver = static function( $raw_text ) {
    $text = html_entity_decode( strip_tags( (string) $raw_text ), ENT_QUOTES, 'UTF-8' );
    $text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );

    if ( preg_match( '/backup|backups|copia|copias|retenci[oó]n|snapshot/', $text ) ) {
        return 'backup';
    }

    return 'generic';
};
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
        <?php foreach ( $base_plans as $slug => $plan ) :
            $card_class  = 'replanta-pricing-card';
            $card_class .= $plan['featured'] ? ' replanta-pricing-featured' : '';
            $plan_badge   = Replanta_Prices_Shortcodes::getPlanBadgeData( $plan );
            $price       = Replanta_Prices_Cache::format_plan_price( $plan, 'monthly' );
            $order_url   = ! empty( $plan['cta_url'] ) ? esc_url_raw( $plan['cta_url'] ) : Replanta_Prices_Geo::get_order_url( $plan['pid'], $plan );
            $price_suffix = ! empty( $plan['price_suffix'] ) ? $plan['price_suffix'] : __( 'mes', 'replanta-prices' );
            $plan_currency = Replanta_Prices_Cache::get_effective_currency( $plan );
            $base_amount   = Replanta_Prices_Cache::get_localized_amount( $plan, 'monthly' );
            $addon_amount  = 0;
            $price_with_addon = $price;
            $addon_delta_price = '';
            $toggle_id = 'addon-toggle-' . $slug;

            if ( ! empty( $addon_plan ) ) {
                $addon_amount = Replanta_Prices_Cache::get_localized_amount( $addon_plan, 'monthly' );
                $addon_delta_price = Replanta_Prices_Geo::format_price_html( $addon_amount, $plan_currency );
                $price_with_addon  = Replanta_Prices_Geo::format_price_html( $base_amount + $addon_amount, $plan_currency );
            }
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
                    <span class="rep-price-base"><?php echo wp_kses_post( $price ); ?></span>
                    <span class="rep-price-with-addon" hidden><?php echo wp_kses_post( $price_with_addon ); ?></span>
                    <span class="rep-text-small">/<?php echo esc_html( $price_suffix ); ?></span>
                </div>
            </header>

            <?php if ( ! empty( $addon_plan ) ) : ?>
            <div class="rep-addon-toggle" aria-label="<?php esc_attr_e( 'Añadir complemento ecommerce', 'replanta-prices' ); ?>">
                <input class="rep-addon-toggle__input" type="checkbox" id="<?php echo esc_attr( $toggle_id ); ?>" data-addon-toggle="1" aria-controls="plan-<?php echo esc_attr( $slug ); ?>">
                <label class="rep-addon-toggle__label" for="<?php echo esc_attr( $toggle_id ); ?>">
                    <span class="rep-addon-toggle__title"><?php echo esc_html( $addon_plan['name'] ); ?></span>
                    <span class="rep-addon-toggle__hint">+ <?php echo wp_kses_post( $addon_delta_price ); ?>/<?php esc_html_e( 'mes', 'replanta-prices' ); ?></span>
                </label>
            </div>
            <?php endif; ?>

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

                    $feature_kind = $feature_kind_resolver( $text );
                    $feature_class = 'generic' !== $feature_kind ? ' rep-feature--' . $feature_kind : '';
                ?>
                    <li class="rep-feature<?php echo esc_attr( $feature_class ); ?>">
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

                <?php if ( ! empty( $addon_plan['features'] ) ) : ?>
                    <?php foreach ( $addon_plan['features'] as $addon_feat ) :
                        $addon_text = is_array( $addon_feat ) ? $addon_feat['text'] : $addon_feat;
                        $addon_tip  = is_array( $addon_feat ) && isset( $addon_feat['tip'] ) ? $addon_feat['tip'] : '';
                        $addon_feature_kind = $feature_kind_resolver( $addon_text );
                        $addon_feature_class = 'generic' !== $addon_feature_kind ? ' rep-addon-feature--' . $addon_feature_kind : '';
                    ?>
                    <li class="rep-addon-feature<?php echo esc_attr( $addon_feature_class ); ?>" hidden>
                        <svg class="tick" width="18" height="18" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M229.66,77.66l-128,128a8,8,0,0,1-11.32,0l-56-56a8,8,0,0,1,11.32-11.32L96,188.69,218.34,66.34a8,8,0,0,1,11.32,11.32Z"/></svg>
                        <span>
                            <?php echo wp_kses_post( $addon_text ); ?>
                            <?php if ( ! empty( $addon_tip ) ) : ?>
                            <span class="rep-tipwrap">
                                <button class="rep-tip rep-tip--mini rep-tip--sup" type="button" aria-label="<?php esc_attr_e( 'Más info', 'replanta-prices' ); ?>">i</button>
                                <span class="rep-tooltip" role="tooltip"><?php echo wp_kses_post( $addon_tip ); ?></span>
                            </span>
                            <?php endif; ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
            <?php if ( ! empty( $addon_plan['features'] ) ) : ?>
                <p class="rep-addon-feature-hint"><?php esc_html_e( 'Activa Impulso Ecommerce para ver y añadir coberturas extra en este plan.', 'replanta-prices' ); ?></p>
            <?php endif; ?>

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

    <?php if ( ! empty( $addon_plans ) ) : ?>
        <div class="plans-addon-stack" aria-label="Add-ons de mantenimiento">
            <?php foreach ( $addon_plans as $slug => $plan ) :
                $price        = Replanta_Prices_Cache::format_plan_price( $plan, 'monthly' );
                $price_suffix = ! empty( $plan['price_suffix'] ) ? $plan['price_suffix'] : __( 'mes', 'replanta-prices' );
            ?>
            <section class="replanta-addon-band" id="addon-<?php echo esc_attr( $slug ); ?>" data-plan="<?php echo esc_attr( $slug ); ?>" aria-label="<?php esc_attr_e( 'Módulo add-on ecommerce', 'replanta-prices' ); ?>">

                <!-- Top bar: module label + inline price -->
                <div class="replanta-addon-band__topbar">
                    <span class="replanta-addon-band__module-label">
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true"><rect x="1" y="1" width="10" height="10" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M4 6h4M6 4v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                        <?php esc_html_e( 'MODULE', 'replanta-prices' ); ?> <span class="replanta-addon-band__sep">·</span> <?php esc_html_e( 'ADD-ON OPCIONAL', 'replanta-prices' ); ?>
                    </span>
                    <div class="replanta-addon-band__topbar-price">
                        <span class="replanta-addon-band__topbar-label"><?php esc_html_e( '+ sobre tu plan', 'replanta-prices' ); ?></span>
                        <span class="replanta-addon-band__topbar-amount"><?php echo wp_kses_post( $price ); ?><span class="replanta-addon-band__topbar-period">/<?php echo esc_html( $price_suffix ); ?></span></span>
                    </div>
                </div>

                <!-- Body row: intro + action -->
                <div class="replanta-addon-band__body">
                    <div class="replanta-addon-band__intro">
                        <h3 class="replanta-addon-band__title"><?php echo esc_html( $plan['name'] ); ?></h3>
                        <p class="replanta-addon-band__lede"><?php echo esc_html( $plan['subtitle'] ); ?></p>
                    </div>
                    <div class="replanta-addon-band__action">
                        <p class="replanta-addon-band__action-note">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M7 1v6l3.5 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="7" cy="7" r="6" stroke="currentColor" stroke-width="1.2"/></svg>
                            <?php esc_html_e( 'Se activa sobre Semilla, Raíz o Ecosistema.', 'replanta-prices' ); ?>
                        </p>
                    </div>
                </div>

                <!-- Feature chips -->
                <ul class="replanta-addon-band__chips" aria-label="<?php esc_attr_e( 'Coberturas incluidas', 'replanta-prices' ); ?>">
                    <?php foreach ( $plan['features'] as $feat ) :
                        $text = is_array( $feat ) ? $feat['text'] : $feat;
                        $tip  = is_array( $feat ) && isset( $feat['tip'] ) ? $feat['tip'] : '';
                    ?>
                    <li class="replanta-addon-band__chip">
                        <svg width="10" height="10" viewBox="0 0 10 10" fill="none" aria-hidden="true"><circle cx="5" cy="5" r="3" fill="currentColor"/></svg>
                        <?php echo wp_kses_post( $text ); ?>
                        <?php if ( ! empty( $tip ) ) : ?>
                        <span class="rep-tipwrap">
                            <button class="rep-tip rep-tip--mini rep-tip--sup" type="button" aria-label="<?php esc_attr_e( 'Más info', 'replanta-prices' ); ?>">i</button>
                            <span class="rep-tooltip" role="tooltip"><?php echo wp_kses_post( $tip ); ?></span>
                        </span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $footer_note ) ) : ?>
        <p class="replanta-plans-footer"><?php echo wp_kses_post( $footer_note ); ?></p>
    <?php endif; ?>
</section>
<script>
(function() {
    function initAddonToggles() {
        var sections = document.querySelectorAll('.replanta-plans--mantenimiento');
        if (!sections.length) return;

        sections.forEach(function(section) {
            var cards = section.querySelectorAll('.replanta-pricing-card');
            if (!cards.length) return;

            cards.forEach(function(card) {
                var toggle = card.querySelector('[data-addon-toggle="1"]');
                if (!toggle) return;

                /* Avoid double-binding if Elementor re-runs init */
                if (toggle.dataset.addonBound === '1') return;
                toggle.dataset.addonBound = '1';

                var basePrice = card.querySelector('.rep-price-base');
                var addonPrice = card.querySelector('.rep-price-with-addon');
                var addonFeatures = card.querySelectorAll('.rep-addon-feature');
                var baseBackupFeatures = card.querySelectorAll('.rep-feature--backup');
                var addonBackupFeatures = card.querySelectorAll('.rep-addon-feature--backup');
                var addonHint = card.querySelector('.rep-addon-feature-hint');

                function syncCard() {
                    var on = !!toggle.checked;
                    if (basePrice) basePrice.hidden = on;
                    if (addonPrice) addonPrice.hidden = !on;

                    addonFeatures.forEach(function(item) {
                        item.hidden = !on;
                    });

                    var hasAddonBackup = on && addonBackupFeatures.length > 0;
                    baseBackupFeatures.forEach(function(item) {
                        item.hidden = hasAddonBackup;
                    });

                    if (addonHint) addonHint.hidden = on;

                    card.classList.toggle('is-addon-active', on);
                }

                toggle.addEventListener('change', syncCard);
                syncCard();
            });
        });
    }

    /* Run immediately if DOM is ready, otherwise wait */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAddonToggles);
    } else {
        initAddonToggles();
    }

    /* Re-run after Elementor frontend fully boots (handles deferred/lazy init) */
    window.addEventListener('elementor/frontend/init', initAddonToggles);

    /* Re-run on load as last-resort safety net */
    window.addEventListener('load', initAddonToggles);
})();
</script>
