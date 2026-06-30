<?php

namespace Replanta\AiChat;

defined( 'ABSPATH' ) || exit;

class Installer {

    const DB_VERSION_OPTION = 'replanta_ai_chat_db_version';
    const DB_VERSION        = '1.0';

    public static function activate(): void {
        self::create_tables();
        self::set_defaults();
        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    public static function maybe_upgrade(): void {
        $installed = get_option( self::DB_VERSION_OPTION, '0' );
        if ( version_compare( $installed, self::DB_VERSION, '<' ) ) {
            self::create_tables();
            update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
        }
    }

    private static function create_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( "CREATE TABLE {$wpdb->prefix}replanta_embeddings (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id    BIGINT UNSIGNED NOT NULL,
            chunk_type    VARCHAR(50)     NOT NULL,
            chunk_key     VARCHAR(100)    NULL,
            content       LONGTEXT        NOT NULL,
            embedding     LONGBLOB        NULL,
            content_hash  VARCHAR(64)     NOT NULL,
            created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_product_id  (product_id),
            INDEX idx_chunk_type  (chunk_type),
            INDEX idx_content_hash (content_hash)
        ) $charset;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}replanta_conversations (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id      VARCHAR(64)     NOT NULL,
            user_id         BIGINT UNSIGNED NULL,
            started_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ended_at        DATETIME        NULL,
            outcome         VARCHAR(20)     NULL,
            product_ids     LONGTEXT        NULL,
            total_messages  INT UNSIGNED    NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            INDEX idx_session_id  (session_id),
            INDEX idx_started_at  (started_at)
        ) $charset;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}replanta_messages (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            conversation_id  BIGINT UNSIGNED NOT NULL,
            role             VARCHAR(20)     NOT NULL,
            content          LONGTEXT        NOT NULL,
            tool_calls       LONGTEXT        NULL,
            tool_results     LONGTEXT        NULL,
            products_cited   LONGTEXT        NULL,
            tokens_used      INT UNSIGNED    NULL,
            created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_conversation_id (conversation_id)
        ) $charset;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}replanta_feedback (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            message_id  BIGINT UNSIGNED NOT NULL,
            rating      TINYINT         NOT NULL,
            reason      VARCHAR(255)    NULL,
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_message_id (message_id)
        ) $charset;" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}replanta_indexing_jobs (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            type         VARCHAR(20)     NOT NULL DEFAULT 'full',
            status       VARCHAR(20)     NOT NULL DEFAULT 'pending',
            total        INT UNSIGNED    NOT NULL DEFAULT 0,
            processed    INT UNSIGNED    NOT NULL DEFAULT 0,
            failed       INT UNSIGNED    NOT NULL DEFAULT 0,
            log          LONGTEXT        NULL,
            started_at   DATETIME        NULL,
            completed_at DATETIME        NULL,
            created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;" );
    }

    private static function set_defaults(): void {
        $defaults = Options::defaults();
        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                update_option( $key, $value, false );
            }
        }
    }
}
