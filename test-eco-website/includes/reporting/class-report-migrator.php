<?php
namespace TEW\Reporting;

use WP_Query;
use function get_post_meta;
use function is_array;
use function json_decode;
use function update_option;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Report_Migrator {

    const PROGRESS_OPTION = 'tew_reports_migration_progress';

    /** @var Custom_Report_Repository */
    private $repository;

    public function __construct() {
        $this->repository = new Custom_Report_Repository();
    }

    /**
     * Migra un lote de informes legacy a tabla custom.
     *
     * @param int $batch_size
     * @param int $offset
     * @return array
     */
    public function migrate_batch( $batch_size = 500, $offset = 0 ) {
        $batch_size = max( 1, (int) $batch_size );
        $offset     = max( 0, (int) $offset );

        if ( ! $this->repository->is_available() ) {
            return [
                'ok'        => false,
                'migrated'  => 0,
                'failed'    => 0,
                'skipped'   => 0,
                'next'      => $offset,
                'message'   => 'Custom table no disponible.',
            ];
        }

        $query = new WP_Query(
            [
                'post_type'      => Report_Storage::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => $batch_size,
                'offset'         => $offset,
                'fields'         => 'ids',
                'orderby'        => 'ID',
                'order'          => 'ASC',
                'meta_key'       => Report_Storage::META_PAYLOAD,
                'no_found_rows'  => false,
            ]
        );

        $migrated = 0;
        $failed   = 0;
        $skipped  = 0;

        foreach ( (array) $query->posts as $post_id ) {
            $payload = (string) get_post_meta( $post_id, Report_Storage::META_PAYLOAD, true );

            if ( '' === $payload ) {
                $skipped++;
                continue;
            }

            $report = json_decode( $payload, true );
            if ( ! is_array( $report ) || empty( $report['url'] ) ) {
                $failed++;
                continue;
            }

            $email = (string) get_post_meta( $post_id, Report_Storage::META_USER_EMAIL, true );
            if ( '' !== $email ) {
                $report['metadata']['user_email'] = $email;
            }

            $ok = $this->repository->upsert_for_legacy_post( $post_id, $report );
            if ( $ok ) {
                $migrated++;
            } else {
                $failed++;
            }
        }

        $next = $offset + $batch_size;
        $done = $next >= (int) $query->found_posts;

        $progress = [
            'last_offset' => $offset,
            'next_offset' => $next,
            'batch_size'  => $batch_size,
            'found_posts' => (int) $query->found_posts,
            'migrated'    => $migrated,
            'failed'      => $failed,
            'skipped'     => $skipped,
            'done'        => $done,
            'updated_at'  => current_time( 'mysql', true ),
        ];

        update_option( self::PROGRESS_OPTION, $progress, false );

        return [
            'ok'        => true,
            'migrated'  => $migrated,
            'failed'    => $failed,
            'skipped'   => $skipped,
            'next'      => $next,
            'total'     => (int) $query->found_posts,
            'done'      => $done,
        ];
    }

    /**
     * Obtiene estado consolidado de migracion/paridad.
     *
     * @param int $sample_limit
     * @return array
     */
    public function status( $sample_limit = 10 ) {
        $legacy_total = $this->countLegacyWithPayload();
        $custom_total = $this->repository->countRows();

        $missing_count = 0;
        $missing_ids   = [];

        if ( $this->repository->is_available() ) {
            $missing_count = $this->repository->countMissingLegacyRecords();
            $missing_ids   = $this->repository->sampleMissingLegacyPostIds( $sample_limit );
        }

        $coverage = $legacy_total > 0
            ? round( ( ( $legacy_total - $missing_count ) / $legacy_total ) * 100, 2 )
            : 100.0;

        return [
            'ok'             => true,
            'legacy_total'   => $legacy_total,
            'custom_total'   => $custom_total,
            'missing_count'  => max( 0, $missing_count ),
            'coverage_pct'   => max( 0, min( 100, $coverage ) ),
            'missing_sample' => $missing_ids,
            'progress'       => get_option( self::PROGRESS_OPTION, [] ),
        ];
    }

    /**
     * @return int
     */
    private function countLegacyWithPayload() {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm
                    ON pm.post_id = p.ID
                   AND pm.meta_key = %s
                 WHERE p.post_type = %s
                   AND p.post_status = 'publish'",
                Report_Storage::META_PAYLOAD,
                Report_Storage::POST_TYPE
            )
        );

        return (int) $count;
    }
}
