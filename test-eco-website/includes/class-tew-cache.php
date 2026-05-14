<?php
namespace TEW;

use function absint;
use function delete_transient;
use function get_transient;
use function set_transient;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cache {

    const KEY_PREFIX = 'tew_audit_';

    /**
     * Obtiene datos cacheados para una URL.
     *
     * @param string $url URL normalizada.
     *
     * @return array|null
     */
    public function get( $url ) {
        $key = $this->build_key( $url );
        $data = get_transient( $key );

        return false === $data ? null : $data;
    }

    /**
     * Guarda datos cacheados.
     *
     * @param string $url
     * @param array  $data
     * @param int    $hours
     */
    public function set( $url, $data, $hours ) {
        $key = $this->build_key( $url );
    $expires = ( defined( 'HOUR_IN_SECONDS' ) ? HOUR_IN_SECONDS : 3600 ) * max( 1, absint( $hours ) );
    set_transient( $key, $data, $expires );
    }

    /**
     * Borra cache para una URL.
     *
     * @param string $url
     */
    public function delete( $url ) {
        delete_transient( $this->build_key( $url ) );
    }

    /**
     * Genera un identificador de cache consistente.
     */
    private function build_key( $url ) {
        $hash = md5( strtolower( trim( $url ) ) );

        return self::KEY_PREFIX . $hash;
    }
}
