<?php
/**
 * Mirror admin metabox + "Refresh Mirror" action.
 *
 * Adds a metabox to rt_page and page posts that have been Mirror-imported.
 * Shows source URL, imported_at and a button that re-fetches the page,
 * regenerates CSS and assets, and overwrites content in place.
 *
 * @package ReplantaTheme
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class RT_Mirror_Admin {

	public function register(): void {
		add_action( 'add_meta_boxes', [ $this, 'add_box' ] );
	}

	public function add_box(): void {
		foreach ( [ 'page', RT_CPT_Page::POST_TYPE ] as $pt ) {
			add_meta_box(
				'rt-mirror-box',
				__( 'Mirror Import', 'replanta-theme' ),
				[ $this, 'render' ],
				$pt,
				'side',
				'default'
			);
		}
	}

	public function render( WP_Post $post ): void {
		$src = (string) get_post_meta( $post->ID, RT_Mirror_Importer::META_SOURCE_URL, true );
		if ( $src === '' ) {
			echo '<p style="color:#666">' . esc_html__( 'Esta entrada no es un Mirror.', 'replanta-theme' ) . '</p>';
			return;
		}
		$at      = (int) get_post_meta( $post->ID, RT_Mirror_Importer::META_IMPORTED_AT, true );
		$css     = (array) get_post_meta( $post->ID, RT_Mirror_Importer::META_CSS_FILES, true );
		$assets  = (array) get_post_meta( $post->ID, RT_Mirror_Importer::META_ASSETS, true );
		$css_n   = count( $css );
		$img_n   = count( $assets );
		$nonce   = wp_create_nonce( 'wp_rest' );
		$rest    = esc_url_raw( rest_url( RT_REST::NAMESPACE . '/import/mirror/refresh' ) );
		echo '<p><strong>' . esc_html__( 'Origen', 'replanta-theme' ) . ':</strong><br><a href="' . esc_url( $src ) . '" target="_blank" rel="noopener">' . esc_html( $src ) . '</a></p>';
		if ( $at > 0 ) {
			echo '<p><strong>' . esc_html__( 'Importado', 'replanta-theme' ) . ':</strong> ' . esc_html( wp_date( 'Y-m-d H:i', $at ) ) . '</p>';
		}
		echo '<p>' . sprintf(
			/* translators: 1: css files count, 2: image assets count */
			esc_html__( '%1$d hojas CSS · %2$d imágenes locales', 'replanta-theme' ),
			(int) $css_n,
			(int) $img_n
		) . '</p>';
		?>
		<p>
			<button type="button" class="button button-primary" id="rt-mirror-refresh-btn"><?php esc_html_e( 'Refresh Mirror', 'replanta-theme' ); ?></button>
			<span id="rt-mirror-refresh-status" style="margin-left:6px"></span>
		</p>
		<script>
		(function(){
			var btn=document.getElementById('rt-mirror-refresh-btn');
			if(!btn)return;
			btn.addEventListener('click',function(){
				if(!confirm(<?php echo wp_json_encode( __( 'Se descargará de nuevo la página origen y se sobrescribirá el contenido y los assets locales. ¿Continuar?', 'replanta-theme' ) ); ?>))return;
				btn.disabled=true;
				var st=document.getElementById('rt-mirror-refresh-status');
				st.textContent=<?php echo wp_json_encode( __( 'Actualizando…', 'replanta-theme' ) ); ?>;
				fetch(<?php echo wp_json_encode( $rest ); ?>,{
					method:'POST',
					headers:{'Content-Type':'application/json','X-WP-Nonce':<?php echo wp_json_encode( $nonce ); ?>},
					body:JSON.stringify({id:<?php echo (int) $post->ID; ?>})
				}).then(function(r){return r.json().then(function(d){return {ok:r.ok,d:d}})}).then(function(o){
					btn.disabled=false;
					if(o.ok&&o.d&&o.d.ok){
						st.innerHTML=<?php echo wp_json_encode( __( 'Actualizado.', 'replanta-theme' ) ); ?>+' '+(o.d.css_count||0)+' CSS · '+(o.d.img_count||0)+' img.';
						setTimeout(function(){location.reload()},800);
					}else{
						st.textContent=<?php echo wp_json_encode( __( 'Error: ', 'replanta-theme' ) ); ?>+(o.d&&o.d.error?o.d.error:'unknown');
					}
				}).catch(function(e){
					btn.disabled=false;
					st.textContent=<?php echo wp_json_encode( __( 'Error de red', 'replanta-theme' ) ); ?>;
				});
			});
		})();
		</script>
		<?php
	}
}
