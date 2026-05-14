<?php
namespace TEW;

use function __;
use function esc_url_raw;
use function filter_var;
use function is_wp_error;
use function sprintf;
use function wp_parse_url;
use function wp_remote_get;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_header;
use function wp_remote_retrieve_response_code;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Utils {

    /**
     * Valida y normaliza una URL para auditoría.
     *
     * @param string $url
     *
     * @return string|false
     */
    public static function normalize_url( $url ) {
        $url = trim( $url );
        if ( empty( $url ) ) {
            return false;
        }

        if ( false === strpos( $url, '://' ) ) {
            $url = 'https://' . $url;
        }

        $sanitized = esc_url_raw( $url );
        if ( empty( $sanitized ) ) {
            return false;
        }

        return rtrim( $sanitized, '/' );
    }

    /**
     * Devuelve el dominio a partir de la URL.
     *
     * @param string $url
     *
     * @return string
     */
    public static function get_domain( $url ) {
        $parts = wp_parse_url( $url );
        if ( ! $parts || empty( $parts['host'] ) ) {
            return $url;
        }

        return $parts['host'];
    }

    /**
     * Convierte valores tipo "2.5 s" en milisegundos.
     */
    public static function value_to_ms( $value ) {
        if ( is_numeric( $value ) ) {
            return (float) $value;
        }

        if ( is_string( $value ) ) {
            if ( false !== strpos( $value, 'ms' ) ) {
                return (float) str_replace( [ 'ms', ',', ' ' ], '', $value );
            }

            if ( false !== strpos( $value, 's' ) ) {
                return 1000 * (float) str_replace( [ 's', ',', ' ' ], '', $value );
            }
        }

        return null;
    }

    /**
     * Convierte milisegundos a segundos con precisión.
     */
    public static function ms_to_seconds( $ms, $precision = 2 ) {
        if ( null === $ms ) {
            return null;
        }

        return round( $ms / 1000, $precision );
    }

    /**
     * Pre-valida que una URL sea accesible y devuelva contenido HTML válido.
     * 
     * Esta verificación rápida evita llamadas API costosas para URLs inválidas.
     *
     * @param string $url URL normalizada a verificar
     * @return array ['valid' => bool, 'error' => string|null, 'details' => array]
     */
    public static function pre_validate_url( $url ) {
        $result = [
            'valid'   => false,
            'error'   => null,
            'details' => [
                'status_code'    => null,
                'content_type'   => null,
                'has_html'       => false,
                'response_time'  => null,
                'dns_resolved'   => false,
            ],
        ];

        // 1. Validación de formato de URL
        $parsed = wp_parse_url( $url );
        if ( ! $parsed || empty( $parsed['host'] ) ) {
            $result['error'] = __( 'Formato de URL inválido.', 'test-eco-website' );
            return $result;
        }

        // 2. Verificación DNS
        $host = $parsed['host'];
        if ( ! self::check_dns( $host ) ) {
            $result['error'] = sprintf(
                __( 'El dominio "%s" no se puede resolver. Verifica que el sitio web existe.', 'test-eco-website' ),
                $host
            );
            return $result;
        }
        $result['details']['dns_resolved'] = true;

        // 3. Request HTTP con timeout corto
        $start_time = microtime( true );
        $response   = wp_remote_get(
            $url,
            [
                'timeout'     => 15, // Más tiempo para sitios lentos
                'redirection' => 5,
                'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 EcoAnalyzer/1.0',
                'sslverify'   => false,
                'headers'     => [
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                    'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Cache-Control' => 'no-cache',
                    'Pragma' => 'no-cache',
                ],
            ]
        );
        $response_time = round( ( microtime( true ) - $start_time ) * 1000 );
        $result['details']['response_time'] = $response_time;

        // 4. Verificar errores de conexión
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            
            // Verificar si el dominio resuelve correctamente (DNS OK)
            // Si DNS está OK pero hay error de conexión, probablemente es firewall
            if ( $result['details']['dns_resolved'] ) {
                // DNS OK + error de conexión = probablemente firewall/Cloudflare
                // Intentar detectar Cloudflare haciendo una verificación simple
                $dns_records = @dns_get_record( $host, DNS_A );
                $is_cloudflare_ip = false;
                
                if ( ! empty( $dns_records ) ) {
                    foreach ( $dns_records as $record ) {
                        if ( isset( $record['ip'] ) ) {
                            // IPs de Cloudflare comienzan con rangos conocidos
                            // 104.16-31, 172.64-71, 188.114, 198.41, etc.
                            $ip = $record['ip'];
                            if ( 
                                strpos( $ip, '104.1' ) === 0 || 
                                strpos( $ip, '104.2' ) === 0 ||
                                strpos( $ip, '104.3' ) === 0 ||
                                strpos( $ip, '172.6' ) === 0 ||
                                strpos( $ip, '172.7' ) === 0 ||
                                strpos( $ip, '188.114' ) === 0 ||
                                strpos( $ip, '198.41' ) === 0
                            ) {
                                $is_cloudflare_ip = true;
                                break;
                            }
                        }
                    }
                }
                
                if ( $is_cloudflare_ip ) {
                    // Es Cloudflare, continuar con el análisis
                    $result['valid'] = true;
                    $result['details']['cloudflare_detected'] = true;
                    $result['details']['bypass_403'] = true;
                    return $result;
                }
                
                // Si DNS OK pero no es Cloudflare, probablemente es otro firewall
                // Continuar de todas formas porque PageSpeed puede tener mejor acceso
                $result['valid'] = true;
                $result['details']['firewall_detected'] = true;
                $result['details']['bypass_403'] = true;
                return $result;
            }
            
            // DNS falló realmente, error legítimo
            if ( strpos( $error_message, 'cURL error 6' ) !== false || strpos( $error_message, 'resolve host' ) !== false ) {
                $result['error'] = sprintf(
                    __( 'No se puede conectar con "%s". El sitio podría estar caído o no existir.', 'test-eco-website' ),
                    $host
                );
            } elseif ( strpos( $error_message, 'timed out' ) !== false ) {
                $result['error'] = sprintf(
                    __( 'El sitio "%s" tardó demasiado en responder. Intenta de nuevo más tarde.', 'test-eco-website' ),
                    $host
                );
            } else {
                $result['error'] = sprintf(
                    __( 'Error al conectar con el sitio: %s', 'test-eco-website' ),
                    $error_message
                );
            }
            return $result;
        }

        // 5. Verificar código de estado HTTP
        $status_code = wp_remote_retrieve_response_code( $response );
        $result['details']['status_code'] = $status_code;

        if ( $status_code < 200 || $status_code >= 400 ) {
            if ( $status_code === 404 ) {
                $result['error'] = __( 'La página no existe (Error 404). Verifica la URL.', 'test-eco-website' );
            } elseif ( $status_code === 403 ) {
                // Detectar si es Cloudflare u otro firewall conocido
                $server_header = wp_remote_retrieve_header( $response, 'server' );
                $cf_ray = wp_remote_retrieve_header( $response, 'cf-ray' );
                $is_cloudflare = ( ! empty( $cf_ray ) || stripos( $server_header, 'cloudflare' ) !== false );
                
                if ( $is_cloudflare ) {
                    // Para Cloudflare, continuar de todas formas ya que PageSpeed podrá analizarlo
                    $result['valid'] = true;
                    $result['details']['cloudflare_detected'] = true;
                    $result['details']['bypass_403'] = true;
                    return $result;
                }
                
                // Para otros 403, mostrar error pero permitir bypass
                $result['error'] = __( 'Acceso denegado al sitio web (Error 403). El sitio podría bloquear análisis automáticos.', 'test-eco-website' );
            } elseif ( $status_code === 500 || $status_code >= 500 ) {
                $result['error'] = sprintf(
                    __( 'El servidor del sitio web tiene un error interno (Error %d).', 'test-eco-website' ),
                    $status_code
                );
            } else {
                $result['error'] = sprintf(
                    __( 'El sitio respondió con código de error %d.', 'test-eco-website' ),
                    $status_code
                );
            }
            return $result;
        }

        // 6. Verificar Content-Type
        $content_type = wp_remote_retrieve_header( $response, 'content-type' );
        $result['details']['content_type'] = $content_type;

        if ( ! empty( $content_type ) && strpos( $content_type, 'text/html' ) === false ) {
            // Permitir algunos tipos MIME relacionados con HTML
            $allowed_types = [ 'application/xhtml', 'application/xml' ];
            $is_allowed = false;
            foreach ( $allowed_types as $type ) {
                if ( strpos( $content_type, $type ) !== false ) {
                    $is_allowed = true;
                    break;
                }
            }

            if ( ! $is_allowed ) {
                $result['error'] = sprintf(
                    __( 'La URL no apunta a una página web HTML (tipo: %s). Solo se pueden analizar sitios web.', 'test-eco-website' ),
                    $content_type
                );
                return $result;
            }
        }

        // 7. Verificar que el contenido contiene HTML
        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            $result['error'] = __( 'El sitio no devolvió contenido. Podría estar vacío o inaccesible.', 'test-eco-website' );
            return $result;
        }

        // Buscar indicadores de HTML válido
        $has_html = (
            stripos( $body, '<html' ) !== false ||
            stripos( $body, '<!doctype html' ) !== false ||
            stripos( $body, '<head' ) !== false ||
            stripos( $body, '<body' ) !== false
        );

        $result['details']['has_html'] = $has_html;

        if ( ! $has_html ) {
            $result['error'] = __( 'La URL no contiene HTML válido. Verifica que sea un sitio web.', 'test-eco-website' );
            return $result;
        }

        // ✅ Todo OK - sitio válido
        $result['valid'] = true;
        return $result;
    }

    /**
     * Verifica si un dominio tiene registros DNS válidos.
     *
     * @param string $host Dominio a verificar
     * @return bool
     */
    private static function check_dns( $host ) {
        // Evitar verificación DNS en localhost/IPs
        if ( in_array( $host, [ 'localhost', '127.0.0.1', '::1' ], true ) ) {
            return true;
        }

        // Si es una IP, no verificar DNS
        if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
            return true;
        }

        // Verificar registros A o AAAA
        $dns_records = @dns_get_record( $host, DNS_A + DNS_AAAA );
        return ! empty( $dns_records );
    }
}
