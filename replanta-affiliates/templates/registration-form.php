<?php
/**
 * Registration form template.
 *
 * @var array $errors
 * @var array $countries
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="raff-register-wrap" id="raff-register">

    <?php if ( ! empty( $errors ) ) : ?>
        <div class="raff-notice raff-notice--error">
            <ul>
                <?php foreach ( $errors as $e ) : ?>
                    <li><?php echo esc_html( $e ); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="raff-form">
        <?php wp_nonce_field( 'raff_register', 'raff_register_nonce' ); ?>

        <!-- Honeypot -->
        <div style="position:absolute;left:-9999px;" aria-hidden="true">
            <input type="text" name="raff_website_url" tabindex="-1" autocomplete="off" />
        </div>

        <div class="raff-form__grid">
            <!-- Name -->
            <div class="raff-field">
                <label for="raff_name"><?php esc_html_e( 'Nombre completo *', 'replanta-affiliates' ); ?></label>
                <input type="text" id="raff_name" name="raff_name" required
                       value="<?php echo esc_attr( $_POST['raff_name'] ?? '' ); ?>"
                       placeholder="<?php esc_attr_e( 'Tu nombre y apellidos', 'replanta-affiliates' ); ?>" />
            </div>

            <!-- Email -->
            <div class="raff-field">
                <label for="raff_email"><?php esc_html_e( 'Email *', 'replanta-affiliates' ); ?></label>
                <input type="email" id="raff_email" name="raff_email" required
                       value="<?php echo esc_attr( $_POST['raff_email'] ?? '' ); ?>"
                       placeholder="tu@email.com" />
            </div>

            <!-- Phone -->
            <div class="raff-field">
                <label for="raff_phone"><?php esc_html_e( 'Teléfono', 'replanta-affiliates' ); ?></label>
                <input type="tel" id="raff_phone" name="raff_phone"
                       value="<?php echo esc_attr( $_POST['raff_phone'] ?? '' ); ?>"
                       placeholder="+34 600 000 000" />
            </div>

            <!-- Country -->
            <div class="raff-field">
                <label for="raff_country"><?php esc_html_e( 'País *', 'replanta-affiliates' ); ?></label>
                <select id="raff_country" name="raff_country" required>
                    <option value=""><?php esc_html_e( 'Selecciona tu país', 'replanta-affiliates' ); ?></option>
                    <?php foreach ( $countries as $code => $label ) : ?>
                        <option value="<?php echo esc_attr( $code ); ?>"
                            <?php selected( $code, $_POST['raff_country'] ?? '' ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Website / social -->
        <div class="raff-field">
            <label for="raff_site"><?php esc_html_e( 'Web o perfil de redes sociales', 'replanta-affiliates' ); ?></label>
             <input type="text" id="raff_site" name="raff_site" inputmode="url" autocapitalize="off" autocorrect="off" spellcheck="false"
                   value="<?php echo esc_attr( $_POST['raff_site'] ?? '' ); ?>"
                 placeholder="tudominio.com" />
             <p class="raff-help"><?php esc_html_e( 'Puedes escribirla sin https:// (lo añadimos automáticamente).', 'replanta-affiliates' ); ?></p>
        </div>

        <!-- How will they promote -->
        <div class="raff-field">
            <label for="raff_promo"><?php esc_html_e( '¿Cómo vas a promocionar Replanta?', 'replanta-affiliates' ); ?></label>
            <textarea id="raff_promo" name="raff_promo" rows="3"
                      placeholder="<?php esc_attr_e( 'Blog, YouTube, redes sociales, comunidad de WordPress...', 'replanta-affiliates' ); ?>"><?php echo esc_textarea( $_POST['raff_promo'] ?? '' ); ?></textarea>
        </div>

        <hr class="raff-divider" />
        <h3><?php esc_html_e( 'Documento de identidad', 'replanta-affiliates' ); ?></h3>
        <p class="raff-help"><?php esc_html_e( 'Necesario para generar facturas. Se almacena de forma segura y nunca se comparte.', 'replanta-affiliates' ); ?></p>

        <div class="raff-form__grid">
            <!-- Doc type -->
            <div class="raff-field">
                <label for="raff_doc_type"><?php esc_html_e( 'Tipo de documento *', 'replanta-affiliates' ); ?></label>
                <select id="raff_doc_type" name="raff_doc_type" required>
                    <option value="dni" <?php selected( 'dni', $_POST['raff_doc_type'] ?? 'dni' ); ?>>DNI</option>
                    <option value="nif" <?php selected( 'nif', $_POST['raff_doc_type'] ?? '' ); ?>>NIF / CIF</option>
                    <option value="passport" <?php selected( 'passport', $_POST['raff_doc_type'] ?? '' ); ?>><?php esc_html_e( 'Pasaporte', 'replanta-affiliates' ); ?></option>
                </select>
            </div>

            <!-- Doc number -->
            <div class="raff-field">
                <label for="raff_doc_number"><?php esc_html_e( 'Número de documento *', 'replanta-affiliates' ); ?></label>
                <input type="text" id="raff_doc_number" name="raff_doc_number" required
                       value="<?php echo esc_attr( $_POST['raff_doc_number'] ?? '' ); ?>"
                       placeholder="12345678A" />
            </div>
        </div>

        <!-- File upload -->
        <div class="raff-field">
            <label for="raff_doc_file"><?php esc_html_e( 'Copia del documento (PDF, JPG o PNG, máx 5 MB) *', 'replanta-affiliates' ); ?></label>
            <input type="file" id="raff_doc_file" name="raff_doc_file" required
                   accept=".pdf,.jpg,.jpeg,.png" />
        </div>

        <?php $raff_turnstile_sitekey = Raff_Registration::get_turnstile_sitekey(); ?>
        <?php if ( '' !== $raff_turnstile_sitekey ) : ?>
            <div class="raff-field" style="margin-top:12px;">
                <div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $raff_turnstile_sitekey ); ?>" data-theme="light"></div>
            </div>
        <?php endif; ?>

        <button type="submit" class="raff-btn raff-btn--primary">
            <?php esc_html_e( 'Enviar solicitud', 'replanta-affiliates' ); ?>
        </button>

        <p class="raff-legal">
            <?php esc_html_e( 'Al enviar este formulario aceptas que revisemos tu solicitud y almacenemos tus datos según nuestra política de privacidad.', 'replanta-affiliates' ); ?>
        </p>
    </form>
</div>
