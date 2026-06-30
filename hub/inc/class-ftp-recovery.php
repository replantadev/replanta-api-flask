<?php
/**
 * FTP Emergency Recovery
 *
 * Uploads the Hub-cached Care ZIP to a managed site via FTP when the site's
 * REST API is unreachable (e.g. Care has a fatal error blocking all requests).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class RP_Hub_FTP_Recovery {

    /* ------------------------------------------------------------------
       Credential management
    ------------------------------------------------------------------ */

    public static function save_credentials( int $site_id, array $data ): void {
        RPHUB_Database::update_site_meta( $site_id, 'ftp_host', sanitize_text_field( $data['ftp_host'] ?? '' ) );
        RPHUB_Database::update_site_meta( $site_id, 'ftp_user', sanitize_text_field( $data['ftp_user'] ?? '' ) );
        RPHUB_Database::update_site_meta( $site_id, 'ftp_port', intval( $data['ftp_port'] ?? 21 ) ?: 21 );
        RPHUB_Database::update_site_meta( $site_id, 'ftp_ssl',  ! empty( $data['ftp_ssl'] ) ? '1' : '0' );
        RPHUB_Database::update_site_meta( $site_id, 'ftp_path', rtrim( sanitize_text_field( $data['ftp_path'] ?? '' ), '/' ) );
        // Only update password if explicitly provided
        if ( ! empty( $data['ftp_pass'] ) ) {
            RPHUB_Database::update_site_meta( $site_id, 'ftp_pass_enc', self::encrypt_password( $data['ftp_pass'] ) );
        }
    }

    public static function get_credentials( int $site_id ): ?array {
        $host = RPHUB_Database::get_site_meta( $site_id, 'ftp_host' );
        $user = RPHUB_Database::get_site_meta( $site_id, 'ftp_user' );
        if ( empty( $host ) || empty( $user ) ) {
            return null;
        }
        return [
            'host' => $host,
            'user' => $user,
            'pass' => self::decrypt_password( RPHUB_Database::get_site_meta( $site_id, 'ftp_pass_enc' ) ?? '' ),
            'port' => intval( RPHUB_Database::get_site_meta( $site_id, 'ftp_port' ) ?: 21 ),
            'ssl'  => RPHUB_Database::get_site_meta( $site_id, 'ftp_ssl' ) === '1',
            'path' => RPHUB_Database::get_site_meta( $site_id, 'ftp_path' ) ?? '',
        ];
    }

    public static function has_credentials( int $site_id ): bool {
        return ! empty( RPHUB_Database::get_site_meta( $site_id, 'ftp_host' ) );
    }

    /* ------------------------------------------------------------------
       Public API
    ------------------------------------------------------------------ */

    /**
     * Test FTP connection and return status.
     */
    public static function test_connection( int $site_id ): array {
        $creds = self::get_credentials( $site_id );
        if ( ! $creds ) {
            return [ 'success' => false, 'error' => 'Sin credenciales FTP configuradas' ];
        }
        $conn = self::connect( $creds );
        if ( ! $conn ) {
            return [ 'success' => false, 'error' => "No se pudo conectar a {$creds['host']}:{$creds['port']}" ];
        }
        $path = $creds['path'] ?: '/';
        $list = @ftp_nlist( $conn, $path );
        ftp_close( $conn );
        if ( $list === false ) {
            return [ 'success' => false, 'error' => "Conexion OK pero no se puede leer: {$path}" ];
        }
        return [ 'success' => true, 'message' => "FTP OK — {$path} (" . count( $list ) . ' entradas)' ];
    }

    /**
     * Upload the cached Care ZIP to a site's plugin directory via FTP.
     *
     * @return array{success: bool, message?: string, error?: string}
     */
    public static function update_care( int $site_id ): array {
        $creds = self::get_credentials( $site_id );
        if ( ! $creds ) {
            return [ 'success' => false, 'error' => 'Sin credenciales FTP' ];
        }
        if ( empty( $creds['path'] ) ) {
            return [ 'success' => false, 'error' => 'Ruta plugins FTP no configurada' ];
        }

        $zip_path = self::get_care_zip_path();
        if ( ! $zip_path ) {
            return [ 'success' => false, 'error' => 'ZIP de Care no encontrado en cache del Hub' ];
        }

        $zip = new ZipArchive();
        if ( $zip->open( $zip_path ) !== true ) {
            return [ 'success' => false, 'error' => 'No se pudo abrir el ZIP de Care' ];
        }

        $conn = self::connect( $creds );
        if ( ! $conn ) {
            $zip->close();
            return [ 'success' => false, 'error' => "FTP: no se pudo conectar a {$creds['host']}" ];
        }

        set_time_limit( 180 );

        $plugins_dir  = rtrim( $creds['path'], '/' );
        $dirs_created = [];
        $errors       = [];
        $uploaded     = 0;

        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $entry = $zip->getNameIndex( $i );

            // Skip the ZIP root folder entry itself
            if ( $entry === 'replanta-care/' ) {
                continue;
            }

            // ZIP structure: replanta-care/path/to/file
            // Remote target:  {plugins_dir}/replanta-care/path/to/file
            $remote_path = $plugins_dir . '/' . $entry;

            if ( substr( $entry, -1 ) === '/' ) {
                if ( ! in_array( $remote_path, $dirs_created, true ) ) {
                    @ftp_mkdir( $conn, $remote_path );
                    $dirs_created[] = $remote_path;
                }
                continue;
            }

            // Ensure parent directory exists
            $parent = dirname( $remote_path );
            if ( ! in_array( $parent, $dirs_created, true ) ) {
                self::mkdirp( $conn, $parent, $dirs_created );
            }

            $content = $zip->getFromIndex( $i );
            if ( $content === false ) {
                $errors[] = $entry;
                continue;
            }

            $tmp = tmpfile();
            fwrite( $tmp, $content );
            rewind( $tmp );

            if ( ftp_fput( $conn, $remote_path, $tmp, FTP_BINARY ) ) {
                $uploaded++;
            } else {
                $errors[] = $entry;
            }
            fclose( $tmp );
        }

        $zip->close();
        ftp_close( $conn );

        if ( ! empty( $errors ) ) {
            return [
                'success' => false,
                'error'   => sprintf(
                    '%d archivo(s) fallaron de %d. Primeros: %s',
                    count( $errors ),
                    $uploaded + count( $errors ),
                    implode( ', ', array_slice( $errors, 0, 3 ) )
                ),
            ];
        }

        return [ 'success' => true, 'message' => "Care actualizado via FTP ({$uploaded} archivos)" ];
    }

    /* ------------------------------------------------------------------
       Private helpers
    ------------------------------------------------------------------ */

    private static function get_care_zip_path(): ?string {
        $version = get_option( 'rphub_care_latest_version' );
        if ( ! $version ) {
            return null;
        }
        $uploads  = wp_upload_dir();
        $zip_path = $uploads['basedir'] . '/replanta-updates/replanta-care-' . $version . '.zip';
        return file_exists( $zip_path ) ? $zip_path : null;
    }

    /**
     * @return resource|false
     */
    private static function connect( array $creds ) {
        $conn = $creds['ssl']
            ? @ftp_ssl_connect( $creds['host'], $creds['port'], 30 )
            : @ftp_connect( $creds['host'], $creds['port'], 30 );

        if ( ! $conn ) {
            return false;
        }
        if ( ! @ftp_login( $conn, $creds['user'], $creds['pass'] ) ) {
            ftp_close( $conn );
            return false;
        }
        ftp_pasv( $conn, true );
        return $conn;
    }

    /** Recursively create $path on the FTP server. Updates $created list. */
    private static function mkdirp( $conn, string $path, array &$created ): void {
        $path  = rtrim( $path, '/' );
        $parts = array_filter( explode( '/', $path ) );
        $cur   = '';
        foreach ( $parts as $part ) {
            $cur .= '/' . $part;
            if ( ! in_array( $cur, $created, true ) ) {
                @ftp_mkdir( $conn, $cur );
                $created[] = $cur;
            }
        }
    }

    private static function encrypt_password( string $plain ): string {
        if ( empty( $plain ) ) {
            return '';
        }
        $key = substr( hash( 'sha256', ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . ( defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : '' ) ), 0, 32 );
        $iv  = openssl_random_pseudo_bytes( 16 );
        $enc = openssl_encrypt( $plain, 'aes-256-cbc', $key, 0, $iv );
        return base64_encode( base64_encode( $iv ) . ':' . $enc );
    }

    private static function decrypt_password( string $encrypted ): string {
        if ( empty( $encrypted ) ) {
            return '';
        }
        $key   = substr( hash( 'sha256', ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . ( defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : '' ) ), 0, 32 );
        $raw   = base64_decode( $encrypted );
        $parts = explode( ':', $raw, 2 );
        if ( count( $parts ) !== 2 ) {
            return $encrypted; // legacy plain text
        }
        $result = openssl_decrypt( $parts[1], 'aes-256-cbc', $key, 0, base64_decode( $parts[0] ) );
        return $result !== false ? $result : '';
    }
}
