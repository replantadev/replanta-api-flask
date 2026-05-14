<?php
namespace TEW\Reporting;

use TEW\Utils;
use function current_time;
use function esc_url_raw;
use function sanitize_text_field;
use function wp_json_encode;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Custom_Report_Repository {

    /** @var Custom_Report_Table */
    private $table;

    public function __construct() {
        $this->table = new Custom_Report_Table();
    }

    /**
     * @return bool
     */
    public function is_available() {
        return $this->table->exists();
    }

    /**
     * Guarda o actualiza un informe asociado al post legacy.
     *
     * @param int   $legacy_post_id
     * @param array $report
     * @return bool
     */
    public function upsert_for_legacy_post( $legacy_post_id, array $report ) {
        if ( ! $this->is_available() ) {
            return false;
        }

        global $wpdb;

        $table_name   = $this->table->name();
        $url          = isset( $report['url'] ) ? esc_url_raw( $report['url'] ) : '';
        $url_hash     = md5( strtolower( trim( $url ) ) );
        $domain       = Utils::get_domain( $url );
        $generated_at = isset( $report['metadata']['generated_at'] ) ? $report['metadata']['generated_at'] : current_time( 'mysql', true );

        $score = null;
        if ( isset( $report['summary']['score'] ) ) {
            $score = (float) $report['summary']['score'];
        } elseif ( isset( $report['summary']['overall_score'] ) ) {
            $score = (float) $report['summary']['overall_score'];
        }

        $grade = isset( $report['summary']['grade'] ) ? sanitize_text_field( $report['summary']['grade'] ) : null;

        $is_green = 0;
        if ( isset( $report['metrics']['greenweb']['is_green'] ) ) {
            $is_green = (int) ! empty( $report['metrics']['greenweb']['is_green'] );
        } elseif ( isset( $report['metrics']['green_hosting']['is_green'] ) ) {
            $is_green = (int) ! empty( $report['metrics']['green_hosting']['is_green'] );
        }

        $co2_per_view = null;
        if ( isset( $report['metrics']['websitecarbon']['co2_per_view'] ) ) {
            $co2_per_view = (float) $report['metrics']['websitecarbon']['co2_per_view'];
        } elseif ( isset( $report['metrics']['carbon']['co2_per_view'] ) ) {
            $co2_per_view = (float) $report['metrics']['carbon']['co2_per_view'];
        }

        $hosting_provider = null;
        if ( isset( $report['metrics']['greenweb']['provider'] ) ) {
            $hosting_provider = sanitize_text_field( $report['metrics']['greenweb']['provider'] );
        } elseif ( isset( $report['metrics']['green_hosting']['hosted_by'] ) ) {
            $hosting_provider = sanitize_text_field( $report['metrics']['green_hosting']['hosted_by'] );
        } elseif ( isset( $report['metrics']['green_hosting']['hosting_provider'] ) ) {
            $hosting_provider = sanitize_text_field( $report['metrics']['green_hosting']['hosting_provider'] );
        }

        $user_email = isset( $report['metadata']['user_email'] ) ? \sanitize_email( $report['metadata']['user_email'] ) : null;

        $payload_json = wp_json_encode( $report );
        if ( false === $payload_json || empty( $url ) ) {
            return false;
        }

        $now = current_time( 'mysql', true );

        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE legacy_post_id = %d LIMIT 1",
                $legacy_post_id
            )
        );

        $data = [
            'legacy_post_id'    => (int) $legacy_post_id,
            'url'               => $url,
            'url_hash'          => $url_hash,
            'domain'            => $domain,
            'generated_at'      => $generated_at,
            'score'             => $score,
            'grade'             => $grade,
            'is_green'          => $is_green,
            'co2_per_view'      => $co2_per_view,
            'hosting_provider'  => $hosting_provider,
            'user_email'        => $user_email,
            'payload_json'      => $payload_json,
            'updated_at'        => $now,
        ];

        if ( $existing_id ) {
            return false !== $wpdb->update( $table_name, $data, [ 'id' => (int) $existing_id ] );
        }

        $data['report_uuid']      = \wp_generate_uuid4();
        $data['payload_version']  = 1;
        $data['created_at']       = $now;

        return false !== $wpdb->insert( $table_name, $data );
    }

    /**
     * @param int $legacy_post_id
     * @return string|null
     */
    public function get_payload_by_legacy_post( $legacy_post_id ) {
        if ( ! $this->is_available() ) {
            return null;
        }

        global $wpdb;

        $table_name = $this->table->name();
        $payload    = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT payload_json FROM {$table_name} WHERE legacy_post_id = %d LIMIT 1",
                $legacy_post_id
            )
        );

        return is_string( $payload ) && '' !== $payload ? $payload : null;
    }

    /**
     * @param string $url
     * @param int    $limit
     * @return array
     */
    public function get_recent_by_url( $url, $limit = 5 ) {
        if ( ! $this->is_available() ) {
            return [];
        }

        global $wpdb;

        $table_name = $this->table->name();
        $url_hash   = md5( strtolower( trim( esc_url_raw( $url ) ) ) );
        $limit      = max( 1, (int) $limit );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT legacy_post_id, score, generated_at
                 FROM {$table_name}
                 WHERE url_hash = %s
                 ORDER BY generated_at DESC
                 LIMIT %d",
                $url_hash,
                $limit
            ),
            \ARRAY_A
        );

        return is_array( $rows ) ? $rows : [];
    }

    /**
     * @param int    $legacy_post_id
     * @param string $email
     * @return bool
     */
    public function update_email_by_legacy_post( $legacy_post_id, $email ) {
        if ( ! $this->is_available() ) {
            return false;
        }

        global $wpdb;

        $table_name = $this->table->name();
        $email      = \sanitize_email( $email );

        if ( empty( $email ) ) {
            return false;
        }

        $updated = $wpdb->update(
            $table_name,
            [
                'user_email' => $email,
                'updated_at' => current_time( 'mysql', true ),
            ],
            [ 'legacy_post_id' => (int) $legacy_post_id ]
        );

        return false !== $updated;
    }

    /**
     * @return int
     */
    public function countRows() {
        if ( ! $this->is_available() ) {
            return 0;
        }

        global $wpdb;

        $table_name = $this->table->name();
        $count      = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );

        return (int) $count;
    }

    /**
     * Cuenta informes legacy con payload que aun no existen en tabla custom.
     *
     * @return int
     */
    public function countMissingLegacyRecords() {
        if ( ! $this->is_available() ) {
            return 0;
        }

        global $wpdb;

        $table_name = $this->table->name();

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm
                    ON pm.post_id = p.ID
                   AND pm.meta_key = %s
                 LEFT JOIN {$table_name} t
                    ON t.legacy_post_id = p.ID
                 WHERE p.post_type = %s
                   AND p.post_status = 'publish'
                   AND t.id IS NULL",
                Report_Storage::META_PAYLOAD,
                Report_Storage::POST_TYPE
            )
        );

        return (int) $count;
    }

    /**
     * Devuelve una muestra de IDs legacy pendientes de migrar.
     *
     * @param int $limit
     * @return array<int>
     */
    public function sampleMissingLegacyPostIds( $limit = 20 ) {
        if ( ! $this->is_available() ) {
            return [];
        }

        global $wpdb;

        $table_name = $this->table->name();
        $limit      = max( 1, (int) $limit );

        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT p.ID
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm
                    ON pm.post_id = p.ID
                   AND pm.meta_key = %s
                 LEFT JOIN {$table_name} t
                    ON t.legacy_post_id = p.ID
                 WHERE p.post_type = %s
                   AND p.post_status = 'publish'
                   AND t.id IS NULL
                 ORDER BY p.ID ASC
                 LIMIT %d",
                Report_Storage::META_PAYLOAD,
                Report_Storage::POST_TYPE,
                $limit
            )
        );

        return array_map( 'intval', (array) $rows );
    }
}
