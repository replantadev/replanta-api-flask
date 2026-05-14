<?php
/**
 * Landing page template — unified affiliate hub.
 *
 * @var array $errors
 * @var bool  $registered
 * @var array $countries
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="raff-landing">

    <!-- ====== HERO ====== -->
    <section class="raff-landing__hero">
        <div class="raff-landing__hero-bg"></div>
        <div class="raff-landing__hero-content">
            <h1><?php esc_html_e( 'Programa de Afiliados', 'replanta-affiliates' ); ?></h1>
            <p class="raff-landing__subtitle"><?php esc_html_e( 'Gana un 20% de comisión por cada venta. Sin intermediarios. Empieza hoy.', 'replanta-affiliates' ); ?></p>
            <div class="raff-landing__stats">
                <div class="raff-landing__stat">
                    <span class="raff-landing__stat-val">20%</span>
                    <span class="raff-landing__stat-label"><?php esc_html_e( 'Comisión fija', 'replanta-affiliates' ); ?></span>
                </div>
                <div class="raff-landing__stat">
                    <span class="raff-landing__stat-val"><?php esc_html_e( 'Directo', 'replanta-affiliates' ); ?></span>
                    <span class="raff-landing__stat-label"><?php esc_html_e( 'Sin Awin ni redes', 'replanta-affiliates' ); ?></span>
                </div>
                <div class="raff-landing__stat">
                    <span class="raff-landing__stat-val"><?php esc_html_e( '~40€', 'replanta-affiliates' ); ?></span>
                    <span class="raff-landing__stat-label"><?php esc_html_e( 'Por venta media', 'replanta-affiliates' ); ?></span>
                </div>
            </div>
            <div class="raff-landing__hero-ctas">
                <a href="#raff-register" class="raff-btn raff-btn--primary raff-btn--lg"><?php esc_html_e( 'Quiero ser afiliado', 'replanta-affiliates' ); ?></a>
                <a href="#raff-login" class="raff-btn raff-btn--ghost raff-btn--lg"><?php esc_html_e( 'Ya soy afiliado → Dashboard', 'replanta-affiliates' ); ?></a>
            </div>
        </div>
    </section>

    <!-- ====== BENEFITS ====== -->
    <section class="raff-landing__benefits">
        <div class="raff-landing__container">
            <h2><?php esc_html_e( '¿Por qué unirte?', 'replanta-affiliates' ); ?></h2>
            <div class="raff-landing__grid">
                <div class="raff-landing__benefit">
                    <div class="raff-landing__benefit-icon"><i class="ph ph-coins"></i></div>
                    <h3><?php esc_html_e( '20% por cada venta', 'replanta-affiliates' ); ?></h3>
                    <p><?php esc_html_e( 'Comisión fija sobre el pago anual. Sin topes. Sin escalas. Desde el primer día.', 'replanta-affiliates' ); ?></p>
                </div>
                <div class="raff-landing__benefit">
                    <div class="raff-landing__benefit-icon"><i class="ph ph-ticket"></i></div>
                    <h3><?php esc_html_e( 'Cupón personalizado', 'replanta-affiliates' ); ?></h3>
                    <p><?php esc_html_e( 'Recibes tu código único al registrarte. Tu cliente ahorra 10%, tú ganas 20%.', 'replanta-affiliates' ); ?></p>
                </div>
                <div class="raff-landing__benefit">
                    <div class="raff-landing__benefit-icon"><i class="ph ph-chart-line-up"></i></div>
                    <h3><?php esc_html_e( 'Dashboard en tiempo real', 'replanta-affiliates' ); ?></h3>
                    <p><?php esc_html_e( 'Consulta ventas, comisiones y pagos desde tu panel. Sin login de WordPress.', 'replanta-affiliates' ); ?></p>
                </div>
                <div class="raff-landing__benefit">
                    <div class="raff-landing__benefit-icon"><i class="ph ph-rocket-launch"></i></div>
                    <h3><?php esc_html_e( 'Toolkit profesional', 'replanta-affiliates' ); ?></h3>
                    <p><?php esc_html_e( 'Mensajes, posts y enlaces listos para copiar y publicar en tus redes.', 'replanta-affiliates' ); ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- ====== LOGIN (ya soy afiliado) ====== -->
    <section class="raff-landing__section raff-landing__section--dark" id="raff-login">
        <div class="raff-landing__container">
            <div class="raff-landing__login-card">
                <h2><?php esc_html_e( 'Accede a tu Dashboard', 'replanta-affiliates' ); ?></h2>
                <p><?php esc_html_e( 'Introduce tu email de afiliado y te enviaremos un enlace de acceso directo (sin contraseña).', 'replanta-affiliates' ); ?></p>
                <form id="raff-magic-link-form" class="raff-landing__login-form">
                    <div class="raff-landing__input-group">
                        <input type="email" id="raff-login-email" required placeholder="tu@email.com" />
                        <button type="submit" class="raff-btn raff-btn--primary"><?php esc_html_e( 'Enviar enlace', 'replanta-affiliates' ); ?></button>
                    </div>
                </form>
                <div id="raff-login-message" class="raff-landing__message" style="display:none;"></div>
                <p class="raff-landing__login-note"><?php esc_html_e( 'Recibirás un email con un enlace válido 24h. Revisa spam si no lo ves.', 'replanta-affiliates' ); ?></p>
            </div>
        </div>
    </section>

    <!-- ====== REGISTRATION ====== -->
    <section class="raff-landing__section" id="raff-register">
        <div class="raff-landing__container">

            <?php if ( $registered ) : ?>
                <div class="raff-landing__success">
                    <div class="raff-landing__success-icon"><i class="ph ph-check-circle"></i></div>
                    <h2><?php esc_html_e( '¡Solicitud enviada!', 'replanta-affiliates' ); ?></h2>
                    <p><?php esc_html_e( 'Hemos recibido tu solicitud. Revisamos los datos y te contactaremos por email con tu cupón personalizado en menos de 24h.', 'replanta-affiliates' ); ?></p>
                    <a href="<?php echo esc_url( home_url( '/mediakit/affiliates.html' ) ); ?>" class="raff-btn raff-btn--outline"><?php esc_html_e( 'Mientras tanto → explora el Toolkit', 'replanta-affiliates' ); ?></a>
                </div>
            <?php else : ?>

                <div class="raff-landing__register-header">
                    <h2><?php esc_html_e( 'Únete al programa', 'replanta-affiliates' ); ?></h2>
                    <p><?php esc_html_e( 'Completa el formulario y en menos de 24h tendrás tu cupón activo y acceso al dashboard.', 'replanta-affiliates' ); ?></p>
                </div>

                <?php if ( ! empty( $errors ) ) : ?>
                    <div class="raff-landing__errors">
                        <ul>
                            <?php foreach ( $errors as $e ) : ?>
                                <li><?php echo esc_html( $e ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data" class="raff-landing__form">
                    <?php wp_nonce_field( 'raff_register', 'raff_register_nonce' ); ?>

                    <!-- Honeypot -->
                    <div style="position:absolute;left:-9999px;" aria-hidden="true">
                        <input type="text" name="raff_website_url" tabindex="-1" autocomplete="off" />
                    </div>

                    <div class="raff-landing__form-grid">
                        <div class="raff-landing__field">
                            <label for="raff_name"><?php esc_html_e( 'Nombre completo', 'replanta-affiliates' ); ?> *</label>
                            <input type="text" id="raff_name" name="raff_name" required
                                   value="<?php echo esc_attr( $_POST['raff_name'] ?? '' ); ?>"
                                   placeholder="<?php esc_attr_e( 'Tu nombre y apellidos', 'replanta-affiliates' ); ?>" />
                        </div>

                        <div class="raff-landing__field">
                            <label for="raff_email"><?php esc_html_e( 'Email', 'replanta-affiliates' ); ?> *</label>
                            <input type="email" id="raff_email" name="raff_email" required
                                   value="<?php echo esc_attr( $_POST['raff_email'] ?? '' ); ?>"
                                   placeholder="tu@email.com" />
                        </div>

                        <div class="raff-landing__field">
                            <label for="raff_phone"><?php esc_html_e( 'Teléfono', 'replanta-affiliates' ); ?></label>
                            <input type="tel" id="raff_phone" name="raff_phone"
                                   value="<?php echo esc_attr( $_POST['raff_phone'] ?? '' ); ?>"
                                   placeholder="+34 600 000 000" />
                        </div>

                        <div class="raff-landing__field">
                            <label for="raff_country"><?php esc_html_e( 'País', 'replanta-affiliates' ); ?> *</label>
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

                    <div class="raff-landing__field">
                        <label for="raff_site"><?php esc_html_e( 'Web o perfil de redes sociales', 'replanta-affiliates' ); ?></label>
                           <input type="text" id="raff_site" name="raff_site" inputmode="url" autocapitalize="off" autocorrect="off" spellcheck="false"
                               value="<?php echo esc_attr( $_POST['raff_site'] ?? '' ); ?>"
                               placeholder="tudominio.com" />
                           <p class="raff-landing__help"><?php esc_html_e( 'Puedes escribirla sin https:// (lo añadimos automáticamente).', 'replanta-affiliates' ); ?></p>
                    </div>

                    <div class="raff-landing__field">
                        <label for="raff_promo"><?php esc_html_e( '¿Cómo vas a promocionar Replanta?', 'replanta-affiliates' ); ?></label>
                        <textarea id="raff_promo" name="raff_promo" rows="3"
                                  placeholder="<?php esc_attr_e( 'Blog, YouTube, redes sociales, comunidad WordPress...', 'replanta-affiliates' ); ?>"><?php echo esc_textarea( $_POST['raff_promo'] ?? '' ); ?></textarea>
                    </div>

                    <hr class="raff-landing__divider" />

                    <h3><?php esc_html_e( 'Documento de identidad', 'replanta-affiliates' ); ?></h3>
                    <p class="raff-landing__help"><?php esc_html_e( 'Necesario para generar facturas. Se almacena de forma segura y nunca se comparte.', 'replanta-affiliates' ); ?></p>

                    <div class="raff-landing__form-grid">
                        <div class="raff-landing__field">
                            <label for="raff_doc_type"><?php esc_html_e( 'Tipo de documento', 'replanta-affiliates' ); ?> *</label>
                            <select id="raff_doc_type" name="raff_doc_type" required>
                                <option value="dni" <?php selected( 'dni', $_POST['raff_doc_type'] ?? 'dni' ); ?>>DNI</option>
                                <option value="nif" <?php selected( 'nif', $_POST['raff_doc_type'] ?? '' ); ?>>NIF / CIF</option>
                                <option value="passport" <?php selected( 'passport', $_POST['raff_doc_type'] ?? '' ); ?>><?php esc_html_e( 'Pasaporte', 'replanta-affiliates' ); ?></option>
                            </select>
                        </div>

                        <div class="raff-landing__field">
                            <label for="raff_doc_number"><?php esc_html_e( 'Número de documento', 'replanta-affiliates' ); ?> *</label>
                            <input type="text" id="raff_doc_number" name="raff_doc_number" required
                                   value="<?php echo esc_attr( $_POST['raff_doc_number'] ?? '' ); ?>"
                                   placeholder="12345678A" />
                        </div>
                    </div>

                    <div class="raff-landing__field">
                        <label for="raff_doc_file"><?php esc_html_e( 'Copia del documento (PDF, JPG o PNG, máx 5 MB)', 'replanta-affiliates' ); ?> *</label>
                        <input type="file" id="raff_doc_file" name="raff_doc_file" required
                               accept=".pdf,.jpg,.jpeg,.png" />
                    </div>

                    <div class="raff-landing__field raff-landing__field--check">
                        <label>
                            <input type="checkbox" name="raff_accept_terms" value="1" required />
                            <?php printf(
                                /* translators: %s = link to terms page */
                                esc_html__( 'He leído y acepto los %s del Programa de Afiliados.', 'replanta-affiliates' ),
                                '<a href="' . esc_url( home_url( '/terminos-y-condiciones-afiliados/' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'términos y condiciones', 'replanta-affiliates' ) . '</a>'
                            ); ?>
                        </label>
                    </div>

                    <?php $raff_turnstile_sitekey = Raff_Registration::get_turnstile_sitekey(); ?>
                    <?php if ( '' !== $raff_turnstile_sitekey ) : ?>
                        <div class="raff-landing__field">
                            <div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $raff_turnstile_sitekey ); ?>" data-theme="light"></div>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="raff-btn raff-btn--primary raff-btn--lg raff-btn--full">
                        <?php esc_html_e( 'Enviar solicitud', 'replanta-affiliates' ); ?>
                    </button>

                    <p class="raff-landing__legal">
                        <?php esc_html_e( 'Al enviar este formulario aceptas que revisemos tu solicitud y almacenemos tus datos según nuestra política de privacidad.', 'replanta-affiliates' ); ?>
                    </p>
                </form>

            <?php endif; ?>
        </div>
    </section>

    <!-- ====== FOOTER LINKS ====== -->
    <section class="raff-landing__footer">
        <div class="raff-landing__container">
            <a href="<?php echo esc_url( home_url( '/mediakit/programa.html' ) ); ?>"><i class="ph ph-file-text"></i> <?php esc_html_e( 'Guía completa del programa', 'replanta-affiliates' ); ?></a>
            <a href="<?php echo esc_url( home_url( '/mediakit/affiliates.html' ) ); ?>"><i class="ph ph-rocket-launch"></i> <?php esc_html_e( 'Toolkit de afiliados', 'replanta-affiliates' ); ?></a>
            <a href="<?php echo esc_url( home_url( '/mediakit/' ) ); ?>"><i class="ph ph-palette"></i> <?php esc_html_e( 'Media Kit', 'replanta-affiliates' ); ?></a>
        </div>
    </section>

</div>
