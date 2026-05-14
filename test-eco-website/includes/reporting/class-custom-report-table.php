<?php
namespace TEW\Reporting;

use function dbDelta;
use function get_option;
use function update_option;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Custom_Report_Table {

    const SCHEMA_VERSION = '1.0.0';
    const VERSION_OPTION = 'tew_reports_table_version';

    /**
     * Nombre completo de la tabla.
     *
     * @return string
     */
    public function name() {
        global $wpdb;

        return $wpdb->prefix . 'tew_reports';
    }

    /**
     * Crea o actualiza la tabla con dbDelta.
     *
     * @return void
     */
    public function maybe_create_table() {
        if ( ! function_exists( 'dbDelta' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        global $wpdb;

        $table_name      = $this->name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            report_uuid char(36) NOT NULL,
            legacy_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            url varchar(2048) NOT NULL,
            url_hash char(32) NOT NULL,
            domain varchar(255) NOT NULL,
            generated_at datetime NOT NULL,
            score decimal(5,2) DEFAULT NULL,
            grade varchar(8) DEFAULT NULL,
            is_green tinyint(1) NOT NULL DEFAULT 0,
            co2_per_view decimal(12,6) DEFAULT NULL,
            hosting_provider varchar(255) DEFAULT NULL,
            user_email varchar(190) DEFAULT NULL,
            payload_json longtext NOT NULL,
            payload_version smallint(5) unsigned NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY report_uuid (report_uuid),
            UNIQUE KEY legacy_post_id (legacy_post_id),
            KEY url_hash_generated (url_hash, generated_at),
            KEY domain_generated (domain, generated_at),
            KEY generated_at (generated_at),
            KEY score (score),
            KEY is_green (is_green)
        ) {$charset_collate};";

        dbDelta( $sql );

        update_option( self::VERSION_OPTION, self::SCHEMA_VERSION, false );
    }

    /**
     * Ejecuta migración solo cuando falta tabla o cambia versión.
     *
     * @return void
     */
    public function maybe_upgrade() {
        $installed = (string) get_option( self::VERSION_OPTION, '' );

        if ( ! $this->exists() || self::SCHEMA_VERSION !== $installed ) {
            $this->maybe_create_table();
        }
    }

    /**
     * Verifica si la tabla existe físicamente.
     *
     * @return bool
     */
    public function exists() {
        global $wpdb;

        $table_name = $this->name();
        $found      = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

        return $found === $table_name;
    }
}
