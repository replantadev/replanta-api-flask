<?php
namespace TEW;

use TEW\Reporting\Report_Migrator;
use function get_option;
use function wp_json_encode;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cli_Command {

    /**
     * Registra comandos WP-CLI del plugin.
     *
     * @return void
     */
    public static function register() {
        if ( ! defined( '\\WP_CLI' ) || ! \WP_CLI || ! class_exists( '\\WP_CLI' ) ) {
            return;
        }

        \WP_CLI::add_command( 'tew migrate-reports', [ __CLASS__, 'migrate_reports' ] );
        \WP_CLI::add_command( 'tew migrate-run', [ __CLASS__, 'migrate_run' ] );
        \WP_CLI::add_command( 'tew migrate-status', [ __CLASS__, 'migrate_status' ] );
        \WP_CLI::add_command( 'tew storage-mode', [ __CLASS__, 'storage_mode' ] );
    }

    /**
     * Migra informes legacy a tabla custom por lotes.
     *
     * ## OPTIONS
     *
     * [--batch=<n>]
     * : Tamano del lote. Default: 500
     *
     * [--offset=<n>]
     * : Offset inicial. Default: 0
     *
     * ## EXAMPLES
     *
     *     wp tew migrate-reports --batch=1000 --offset=0
     *
     * @param array $args
     * @param array $assoc_args
     * @return void
     */
    public static function migrate_reports( $args, $assoc_args ) {
        $batch  = isset( $assoc_args['batch'] ) ? (int) $assoc_args['batch'] : 500;
        $offset = isset( $assoc_args['offset'] ) ? (int) $assoc_args['offset'] : 0;

        $migrator = new Report_Migrator();
        $result   = $migrator->migrate_batch( $batch, $offset );

        if ( empty( $result['ok'] ) ) {
            \WP_CLI::error( isset( $result['message'] ) ? $result['message'] : 'Error desconocido en migracion.' );
            return;
        }

        \WP_CLI::success(
            sprintf(
                'Migrados: %d | Fallidos: %d | Omitidos: %d | Next offset: %d | Total: %d | Done: %s',
                (int) $result['migrated'],
                (int) $result['failed'],
                (int) $result['skipped'],
                (int) $result['next'],
                (int) $result['total'],
                ! empty( $result['done'] ) ? 'yes' : 'no'
            )
        );
    }

    /**
     * Ejecuta migracion en bucle hasta completar o alcanzar limite de iteraciones.
     *
     * ## OPTIONS
     *
     * [--batch=<n>]
     * : Tamano del lote. Default: 500
     *
     * [--offset=<n>]
     * : Offset inicial. Default: 0
     *
     * [--max-iterations=<n>]
     * : Iteraciones maximas por ejecucion. Default: 50
     *
     * @param array $args
     * @param array $assoc_args
     * @return void
     */
    public static function migrate_run( $args, $assoc_args ) {
        $batch         = isset( $assoc_args['batch'] ) ? (int) $assoc_args['batch'] : 500;
        $offset        = isset( $assoc_args['offset'] ) ? (int) $assoc_args['offset'] : 0;
        $max_iterations = isset( $assoc_args['max-iterations'] ) ? (int) $assoc_args['max-iterations'] : 50;

        $batch          = max( 1, $batch );
        $offset         = max( 0, $offset );
        $max_iterations = max( 1, $max_iterations );

        $migrator = new Report_Migrator();

        for ( $i = 1; $i <= $max_iterations; $i++ ) {
            $result = $migrator->migrate_batch( $batch, $offset );

            if ( empty( $result['ok'] ) ) {
                \WP_CLI::error( isset( $result['message'] ) ? $result['message'] : 'Error desconocido en migracion.' );
                return;
            }

            \WP_CLI::log(
                sprintf(
                    '[%d/%d] Migrados: %d | Fallidos: %d | Omitidos: %d | Next: %d | Done: %s',
                    $i,
                    $max_iterations,
                    (int) $result['migrated'],
                    (int) $result['failed'],
                    (int) $result['skipped'],
                    (int) $result['next'],
                    ! empty( $result['done'] ) ? 'yes' : 'no'
                )
            );

            if ( ! empty( $result['done'] ) ) {
                \WP_CLI::success( 'Migracion completada.' );
                return;
            }

            $offset = (int) $result['next'];
        }

        \WP_CLI::warning( 'Se alcanzo max-iterations sin completar migracion. Reejecuta el comando.' );
    }

    /**
     * Muestra estado de migracion y paridad entre legacy/custom.
     *
     * ## OPTIONS
     *
     * [--sample=<n>]
     * : Cuantos IDs pendientes mostrar. Default: 10
     *
     * @param array $args
     * @param array $assoc_args
     * @return void
     */
    public static function migrate_status( $args, $assoc_args ) {
        $sample   = isset( $assoc_args['sample'] ) ? (int) $assoc_args['sample'] : 10;
        $migrator = new Report_Migrator();
        $status   = $migrator->status( $sample );

        if ( empty( $status['ok'] ) ) {
            \WP_CLI::error( 'No se pudo calcular estado de migracion.' );
            return;
        }

        \WP_CLI::line( 'Storage mode: ' . self::current_storage_mode() );
        \WP_CLI::line( 'Legacy total: ' . (int) $status['legacy_total'] );
        \WP_CLI::line( 'Custom total: ' . (int) $status['custom_total'] );
        \WP_CLI::line( 'Missing: ' . (int) $status['missing_count'] );
        \WP_CLI::line( 'Coverage: ' . (float) $status['coverage_pct'] . '%' );

        if ( ! empty( $status['missing_sample'] ) ) {
            \WP_CLI::line( 'Missing sample IDs: ' . implode( ',', (array) $status['missing_sample'] ) );
        }

        if ( ! empty( $status['progress'] ) ) {
            \WP_CLI::line( 'Progress: ' . wp_json_encode( $status['progress'] ) );
        }
    }

    /**
     * Obtiene/cambia modo de almacenamiento.
     *
     * ## OPTIONS
     *
     * [--set=<mode>]
     * : legacy | dual_write | custom_read
     *
     * @param array $args
     * @param array $assoc_args
     * @return void
     */
    public static function storage_mode( $args, $assoc_args ) {
        if ( empty( $assoc_args['set'] ) ) {
            \WP_CLI::success( 'storage_mode=' . self::current_storage_mode() );
            return;
        }

        $mode = (string) $assoc_args['set'];

        if ( ! in_array( $mode, [ 'legacy', 'dual_write', 'custom_read' ], true ) ) {
            \WP_CLI::error( 'Modo invalido. Usa: legacy, dual_write, custom_read.' );
            return;
        }

        $settings                 = get_option( 'tew_settings', [] );
        $settings['storage_mode'] = $mode;
        \update_option( 'tew_settings', $settings, false );

        \WP_CLI::success( 'storage_mode actualizado a ' . $mode );
    }

    /**
     * @return string
     */
    private static function current_storage_mode() {
        $settings = get_option( 'tew_settings', [] );
        $mode     = isset( $settings['storage_mode'] ) ? (string) $settings['storage_mode'] : 'legacy';

        return in_array( $mode, [ 'legacy', 'dual_write', 'custom_read' ], true ) ? $mode : 'legacy';
    }
}
