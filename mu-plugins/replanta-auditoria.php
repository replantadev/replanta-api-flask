<?php
/**
 * Plugin Name: Replanta · Auditoría gratuita (REST + admin_post)
 * Description: Endpoint REST/POST para la auditoría. Verifica Turnstile y envía email.
 */

add_action('rest_api_init', function () {
    register_rest_route('replanta/v1', '/auditoria', [
        'methods'  => 'POST',
        'callback' => 'replanta_auditoria_handle_rest',
        'permission_callback' => '__return_true',
    ]);
});

add_action('admin_post_nopriv_replanta_auditoria', 'replanta_auditoria_handle_adminpost');
add_action('admin_post_replanta_auditoria',       'replanta_auditoria_handle_adminpost');

function replanta_auditoria_handle_rest( WP_REST_Request $req ) {
    $data = $req->get_json_params();
    $resp = replanta_auditoria_core( $data );
    if ( is_wp_error($resp) ) {
        return new WP_REST_Response(['ok'=>false,'message'=>$resp->get_error_message()], (int)($resp->get_error_data()['status'] ?? 400));
    }
    return new WP_REST_Response(['ok'=>true,'message'=>'¡Gracias! Te contactamos en breve.'], 200);
}

function replanta_auditoria_handle_adminpost() {
    $resp = replanta_auditoria_core( $_POST );
    $dest = home_url('/contacto/');
    if ( is_wp_error($resp) ) {
        $msg = rawurlencode( $resp->get_error_message() );
        wp_safe_redirect( add_query_arg(['audit'=>'error','m'=>$msg], $dest) );
    } else {
        wp_safe_redirect( add_query_arg(['audit'=>'ok'], $dest) );
    }
    exit;
}

function replanta_auditoria_core( $arr ) {
    $url   = isset($arr['url'])   ? esc_url_raw($arr['url'])   : '';
    $email = isset($arr['email']) ? sanitize_email($arr['email']) : '';
    $name  = isset($arr['name'])  ? sanitize_text_field($arr['name']) : '';
    $note  = isset($arr['note'])  ? wp_kses_post($arr['note']) : '';

    // Token Turnstile: 'token' (AJAX) o 'cf-turnstile-response' (auto)
    $token = '';
    if ( isset($arr['token']) ) {
        $token = sanitize_text_field($arr['token']);
    } elseif ( isset($arr['cf-turnstile-response']) ) {
        $token = sanitize_text_field($arr['cf-turnstile-response']);
    }

    if ( empty($url) || empty($email) || empty($token) ) {
        return new WP_Error('missing_fields','Faltan datos (url, email o verificación).', ['status'=>400]);
    }
    if ( ! is_email($email) ) {
        return new WP_Error('invalid_email','Email no válido.', ['status'=>400]);
    }

    $secret = defined('CF_TURNSTILE_SECRET') ? CF_TURNSTILE_SECRET : getenv('CF_TURNSTILE_SECRET');
    if ( ! $secret ) {
        return new WP_Error('missing_secret','Falta la clave secreta de Turnstile.', ['status'=>500]);
    }

    // Verificar Turnstile (con min logs)
    $verify = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
        'timeout' => 10,
        'body'    => [
            'secret'   => $secret,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ],
    ]);
    if ( is_wp_error($verify) ) {
        error_log('[replanta_auditoria] Turnstile request error: '.$verify->get_error_message());
        return new WP_Error('ts_request_failed','No se pudo verificar el captcha.', ['status'=>400]);
    }
    $body = json_decode( wp_remote_retrieve_body($verify), true );
    if ( empty($body['success']) ) {
        error_log('[replanta_auditoria] Turnstile failed: '.wp_json_encode($body));
        return new WP_Error('ts_failed','Verificación Turnstile fallida.', ['status'=>400]);
    }

    // Email
    $to      = 'info@replanta.net';
    $subject = 'Auditoría gratuita — Mantenimiento WP';
    $message = "Nombre: {$name}\nURL: {$url}\nEmail: {$email}\nNota: {$note}\nIP: ".($_SERVER['REMOTE_ADDR'] ?? '')."\nUA: ".($_SERVER['HTTP_USER_AGENT'] ?? '')."\nFecha: ".wp_date('Y-m-d H:i:s');

    // From con dominio propio para mejorar entregabilidad (SPF/DMARC)
    $from    = 'Replanta <no-reply@replanta.net>';
    $headers = [
        'Content-Type: text/plain; charset=UTF-8',
        'From: '.$from,
        'Reply-To: '.$email,
    ];

    // Log de errores de wp_mail (solo si falla)
    add_action('wp_mail_failed', function($wp_error){
        error_log('[replanta_auditoria] wp_mail_failed: '. $wp_error->get_error_message());
    });

    if ( ! wp_mail($to, $subject, $message, $headers) ) {
        return new WP_Error('mail_failed','No se pudo enviar el email.', ['status'=>500]);
    }
    return true;
}
