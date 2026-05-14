<?php
/**
 * Template: Hosting pricing grid (3 cards + billing toggle).
 *
 * Outputs the EXACT same HTML structure and class names as the original
 * Elementor template so the existing CSS works out of the box.
 *
 * Available vars: $category, $region, $type
 *
 * @package Replanta_Prices
 */

defined( 'ABSPATH' ) || exit;

$plans    = $category['plans'];
$first    = reset( $plans );
$currency = Replanta_Prices_Cache::get_effective_currency( $first );
?>
<section class="replanta-plans replanta-plans--hosting<?php if ( $discount > 0 ) echo ' replanta-plans--discount'; ?>">

    <!-- Plans bar -->
    <div class="plans-bar">
        <div class="left">
            <h2 class="elementor-heading-title rep-heading-2 rep-text-forest"><?php echo wp_kses_post( $category['title'] ); ?></h2>
            <div class="rep-text-body"><?php echo wp_kses_post( $category['subtitle'] ); ?></div>
        </div>
        <div class="right">
            <div class="billing-toggle" role="tablist" aria-label="<?php esc_attr_e( 'Facturación', 'replanta-prices' ); ?>">
                <input type="radio" name="billing" id="bill-m" checked>
                <label for="bill-m" role="tab" aria-selected="true"><?php esc_html_e( 'Mensual', 'replanta-prices' ); ?></label>
                <input type="radio" name="billing" id="bill-y">
                <label for="bill-y" role="tab" aria-selected="false"><?php esc_html_e( 'Anual', 'replanta-prices' ); ?> <span class="rep-text-small"><?php
                    /* translators: billing discount note shown next to "Annual" toggle */
                    echo esc_html__( '(-2 meses)', 'replanta-prices' );
                ?></span></label>
            </div>
        </div>
    </div>

    <!-- Cards grid -->
    <div class="plans-grid">
        <?php foreach ( $plans as $slug => $plan ) :
            $card_class  = 'replanta-pricing-card';
            $card_class .= $plan['featured'] ? ' replanta-pricing-featured' : '';
            $price_m      = Replanta_Prices_Cache::format_plan_price( $plan, 'monthly' );
            $price_y      = Replanta_Prices_Cache::format_plan_price( $plan, 'annual' );
            $order_url    = Replanta_Prices_Geo::get_order_url( $plan['pid'], $plan );
            $orig_price_m = $price_m;
            $orig_price_y = $price_y;
            if ( $discount > 0 ) {
                $plan_currency = Replanta_Prices_Cache::get_effective_currency( $plan );
                $raw_m         = Replanta_Prices_Cache::get_localized_amount( $plan, 'monthly' );
                $raw_y         = Replanta_Prices_Cache::get_localized_amount( $plan, 'annual' );
                $price_m       = Replanta_Prices_Geo::format_price_html( round( $raw_m * ( 1 - $discount / 100 ), 2 ), $plan_currency );
                $price_y       = Replanta_Prices_Geo::format_price_html( round( $raw_y * ( 1 - $discount / 100 ), 2 ), $plan_currency );
            }
        ?>
        <div class="<?php echo esc_attr( $card_class ); ?>" data-plan="<?php echo esc_attr( $slug ); ?>">
            <?php if ( $discount > 0 ) : ?><span class="rep-discount-tag">-<?php echo esc_html( $discount ); ?>% solidario</span><?php endif; ?>
            <div style="display:flex;justify-content:space-between;align-items:baseline;gap:12px">
                <div>
                    <div class="rep-heading-3"><?php echo esc_html( $plan['name'] ); ?></div>
                    <div class="plan-subtitle"><?php echo esc_html( $plan['subtitle'] ); ?></div>
                </div>
                <div class="rep-heading-3 price" data-plan="<?php echo esc_attr( $slug ); ?>">
                    <span class="original"><?php if ( $discount > 0 ) echo wp_kses_post( $orig_price_m ); ?></span>
                    <?php if ( $discount > 0 ) : ?><span class="discount-original-y"><?php echo wp_kses_post( $orig_price_y ); ?></span><?php endif; ?>
                    <span class="amount amount--m"><?php echo wp_kses_post( $price_m ); ?></span><span class="rep-text-small period period--m">/<?php esc_html_e( 'mes', 'replanta-prices' ); ?></span>
                    <span class="amount amount--y"><?php echo wp_kses_post( $price_y ); ?></span><span class="rep-text-small period period--y">/<?php esc_html_e( 'año', 'replanta-prices' ); ?></span>
                </div>
            </div>

            <!-- Features -->
            <ul class="rep-text-body" style="margin-top:10px">
                <?php foreach ( $plan['features'] as $feat ) : ?>
                    <li><svg class="tick" width="18" height="18" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M229.66,77.66l-128,128a8,8,0,0,1-11.32,0l-56-56a8,8,0,0,1,11.32-11.32L96,188.69,218.34,66.34a8,8,0,0,1,11.32,11.32Z"/></svg><span><?php echo wp_kses_post( $feat ); ?></span></li>
                <?php endforeach; ?>
            </ul>

            <!-- Extra features (expandable) -->
            <?php if ( ! empty( $plan['features_extra'] ) ) : ?>
            <details>
                <summary class="toggle">
                    <span class="more"><strong><?php esc_html_e( 'Ver más', 'replanta-prices' ); ?></strong></span>
                    <span class="less"><strong><?php esc_html_e( 'Ver menos', 'replanta-prices' ); ?></strong></span>
                </summary>
                <ul class="rep-text-body">
                    <?php foreach ( $plan['features_extra'] as $feat ) : ?>
                        <li><svg class="tick" width="18" height="18" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M229.66,77.66l-128,128a8,8,0,0,1-11.32,0l-56-56a8,8,0,0,1,11.32-11.32L96,188.69,218.34,66.34a8,8,0,0,1,11.32,11.32Z"/></svg><span><?php echo wp_kses_post( $feat ); ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <?php endif; ?>

            <!-- CTA -->
            <div class="plan-cta-wrap" style="margin-top:auto;padding-top:16px">
                <a href="<?php echo esc_url( $order_url ); ?>"
                   class="elementor-button rep-btn-accent plan-card-cta" style="width:100%;justify-content:center"><?php echo esc_html( $plan['cta_text'] ); ?></a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<script>
(function() {
    var sections = document.querySelectorAll('.replanta-plans--hosting');
    if (!sections.length) return;

    sections.forEach(function(section) {
        var billM = section.querySelector('#bill-m');
        var billY = section.querySelector('#bill-y');
        if (!billM && !billY) return;

        function syncBcm() {
            var bcm = (billY && billY.checked) ? '12' : '1';
            section.querySelectorAll('a.plan-card-cta[href]').forEach(function(a) {
                try {
                    var u = new URL(a.getAttribute('href'), window.location.origin);
                    u.searchParams.set('bcm', bcm);
                    a.setAttribute('href', u.toString());
                    a.setAttribute('data-bcm', bcm);
                } catch (e) {
                    // Ignore malformed URLs and keep current href.
                }
            });
        }

        if (billM) billM.addEventListener('change', syncBcm);
        if (billY) billY.addEventListener('change', syncBcm);
        syncBcm();
    });
})();
</script>
