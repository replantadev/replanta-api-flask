<?php
/**
 * Title: Hero — Replanta Eco
 * Slug: replanta-theme/hero-eco
 * Categories: featured, replanta
 * Description: Hero principal con titular, sub y dos CTAs.
 * Keywords: hero, intro, landing
 * Block Types: core/post-content
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"6rem","bottom":"6rem","left":"1.5rem","right":"1.5rem"}}},"backgroundColor":"surface","layout":{"type":"constrained","contentSize":"900px"}} -->
<div class="wp-block-group alignfull has-surface-background-color has-background" style="padding:6rem 1.5rem">
	<!-- wp:heading {"textAlign":"center","level":1,"fontSize":"4xl"} -->
	<h1 class="wp-block-heading has-text-align-center has-4-xl-font-size"><?php esc_html_e( 'Reforestación que sí mide CO₂', 'replanta-theme' ); ?></h1>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"align":"center","fontSize":"lg","textColor":"muted"} -->
	<p class="has-text-align-center has-muted-color has-text-color has-lg-font-size"><?php esc_html_e( 'Plantamos donde más impacto genera. Trazabilidad real, datos abiertos.', 'replanta-theme' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
	<div class="wp-block-buttons">
		<!-- wp:button -->
		<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'Empezar ahora', 'replanta-theme' ); ?></a></div>
		<!-- /wp:button -->
		<!-- wp:button {"className":"is-style-outline"} -->
		<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'Ver casos', 'replanta-theme' ); ?></a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
