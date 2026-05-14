<?php
/**
 * Dashboard profile tab.
 *
 * @var object $affiliate
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$saved = false;
if ( ! empty( $_POST['raff_save_profile'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'raff_save_profile' ) ) {
    $update = array();
    if ( isset( $_POST['payment_method'] ) ) {
        $update['payment_method'] = sanitize_text_field( $_POST['payment_method'] );
    }
    if ( isset( $_POST['paypal_email'] ) ) {
        $update['paypal_email'] = sanitize_email( $_POST['paypal_email'] );
    }
    if ( isset( $_POST['bank_iban'] ) ) {
        $update['bank_iban'] = sanitize_text_field( $_POST['bank_iban'] );
    }
    if ( isset( $_POST['bank_swift'] ) ) {
        $update['bank_swift'] = sanitize_text_field( $_POST['bank_swift'] );
    }
    if ( isset( $_POST['bank_holder'] ) ) {
        $update['bank_holder'] = sanitize_text_field( $_POST['bank_holder'] );
    }
    if ( ! empty( $update ) ) {
        Raff_DB::update_affiliate( $affiliate->id, $update );
        $affiliate = Raff_DB::get_affiliate( $affiliate->id );
        $saved = true;
    }
}
?>

<h3><?php esc_html_e( 'Mi perfil', 'replanta-affiliates' ); ?></h3>

<?php if ( $saved ) : ?>
    <div class="raff-notice raff-notice--success">
        <p><?php esc_html_e( 'Perfil actualizado correctamente.', 'replanta-affiliates' ); ?></p>
    </div>
<?php endif; ?>

<form method="post" class="raff-form raff-form--profile">
    <?php wp_nonce_field( 'raff_save_profile' ); ?>
    <input type="hidden" name="raff_save_profile" value="1" />

    <div class="raff-form-grid">
        <div class="raff-field raff-field--readonly">
            <label><?php esc_html_e( 'Nombre', 'replanta-affiliates' ); ?></label>
            <input type="text" readonly value="<?php echo esc_attr( $affiliate->name ); ?>" />
        </div>
        <div class="raff-field raff-field--readonly">
            <label><?php esc_html_e( 'Email', 'replanta-affiliates' ); ?></label>
            <input type="text" readonly value="<?php echo esc_attr( $affiliate->email ); ?>" />
        </div>
        <div class="raff-field raff-field--readonly">
            <label><?php esc_html_e( 'Código de referido', 'replanta-affiliates' ); ?></label>
            <input type="text" readonly value="<?php echo esc_attr( $affiliate->ref_code ); ?>" />
        </div>
        <div class="raff-field raff-field--readonly">
            <label><?php esc_html_e( 'Comisión', 'replanta-affiliates' ); ?></label>
            <input type="text" readonly value="<?php echo esc_attr( $affiliate->commission_pct ); ?>%" />
        </div>
    </div>

    <h4><?php esc_html_e( 'Datos de pago', 'replanta-affiliates' ); ?></h4>
    <div class="raff-form-grid">
        <div class="raff-field">
            <label for="raff-payment-method"><?php esc_html_e( 'Método preferido', 'replanta-affiliates' ); ?></label>
            <select id="raff-payment-method" name="payment_method">
                <option value="paypal" <?php selected( $affiliate->payment_method, 'paypal' ); ?>>PayPal</option>
                <option value="bank" <?php selected( $affiliate->payment_method, 'bank' ); ?>><?php esc_html_e( 'Transferencia bancaria', 'replanta-affiliates' ); ?></option>
            </select>
        </div>
        <div class="raff-field" id="raff-paypal-fields">
            <label for="raff-paypal-email"><?php esc_html_e( 'Email de PayPal', 'replanta-affiliates' ); ?></label>
            <input type="email" id="raff-paypal-email" name="paypal_email" value="<?php echo esc_attr( $affiliate->paypal_email ?? '' ); ?>" />
        </div>
        <div class="raff-field raff-bank-fields">
            <label for="raff-bank-holder"><?php esc_html_e( 'Titular de la cuenta', 'replanta-affiliates' ); ?></label>
            <input type="text" id="raff-bank-holder" name="bank_holder" value="<?php echo esc_attr( $affiliate->bank_holder ?? '' ); ?>" />
        </div>
        <div class="raff-field raff-bank-fields">
            <label for="raff-bank-iban"><?php esc_html_e( 'IBAN', 'replanta-affiliates' ); ?></label>
            <input type="text" id="raff-bank-iban" name="bank_iban" value="<?php echo esc_attr( $affiliate->bank_iban ?? '' ); ?>" />
        </div>
        <div class="raff-field raff-bank-fields">
            <label for="raff-bank-swift"><?php esc_html_e( 'BIC/SWIFT', 'replanta-affiliates' ); ?></label>
            <input type="text" id="raff-bank-swift" name="bank_swift" value="<?php echo esc_attr( $affiliate->bank_swift ?? '' ); ?>" />
        </div>
    </div>

    <button type="submit" class="raff-btn raff-btn--primary"><?php esc_html_e( 'Guardar cambios', 'replanta-affiliates' ); ?></button>
</form>
