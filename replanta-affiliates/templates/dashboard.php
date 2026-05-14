<?php
/**
 * Affiliate dashboard template.
 *
 * @var object $affiliate
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$tab  = sanitize_text_field( $_GET['tab'] ?? 'resumen' ); // phpcs:ignore WordPress.Security.NonceVerification
$data = Raff_Dashboard::get_dashboard_data( $affiliate );
$base = remove_query_arg( array( 'tab', 'raff_token' ) );
?>
<div class="raff-dashboard">
    <header class="raff-dash-header">
        <h2><?php printf( esc_html__( 'Hola, %s', 'replanta-affiliates' ), esc_html( $affiliate->name ) ); ?></h2>
        <span class="raff-badge raff-badge--<?php echo esc_attr( $affiliate->status ); ?>">
            <?php echo esc_html( ucfirst( $affiliate->status ) ); ?>
        </span>
    </header>

    <nav class="raff-dash-tabs">
        <?php
        $tabs = array(
            'resumen' => __( 'Resumen', 'replanta-affiliates' ),
            'ventas'  => __( 'Mis Ventas', 'replanta-affiliates' ),
            'pagos'   => __( 'Mis Pagos', 'replanta-affiliates' ),
            'perfil'  => __( 'Mi Perfil', 'replanta-affiliates' ),
        );
        foreach ( $tabs as $slug => $label ) :
            $active = ( $tab === $slug ) ? ' raff-tab--active' : '';
            ?>
            <a href="<?php echo esc_url( add_query_arg( 'tab', $slug, $base ) ); ?>"
               class="raff-tab<?php echo esc_attr( $active ); ?>">
                <?php echo esc_html( $label ); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="raff-dash-content">
        <?php
        switch ( $tab ) {
            case 'ventas':
                include RAFF_DIR . 'templates/dashboard-sales.php';
                break;
            case 'pagos':
                include RAFF_DIR . 'templates/dashboard-payouts.php';
                break;
            case 'perfil':
                include RAFF_DIR . 'templates/dashboard-profile.php';
                break;
            default:
                include RAFF_DIR . 'templates/dashboard-summary.php';
                break;
        }
        ?>
    </div>
</div>
