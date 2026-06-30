<?php
/**
 * RPHUB_Scheduler — centralized task queue wrapper
 *
 * Abstracts Action Scheduler so every integration class uses
 * one consistent API instead of raw WP Cron calls scattered
 * across 20+ files.
 *
 * Usage:
 *   RPHUB_Scheduler::schedule('my_hook', 'hourly');
 *   RPHUB_Scheduler::is_scheduled('my_hook');
 *   RPHUB_Scheduler::cancel('my_hook');
 *   RPHUB_Scheduler::run_now('my_hook', ['arg' => 'value']);
 */

if (!defined('ABSPATH')) {
    exit;
}

class RPHUB_Scheduler {

    const GROUP = 'replanta-hub';

    /**
     * Convert WP Cron interval name to seconds for Action Scheduler.
     *
     * @param string $name  WP Cron interval name or numeric seconds
     * @return int  seconds
     */
    public static function interval_seconds(string $name): int {
        $map = [
            'rphub_every_minute' => MINUTE_IN_SECONDS,
            'twicehourly'        => 30 * MINUTE_IN_SECONDS,
            'hourly'             => HOUR_IN_SECONDS,
            'twicedaily'         => 12 * HOUR_IN_SECONDS,
            'fourhourly'         => 4 * HOUR_IN_SECONDS,
            'sixhourly'          => 6 * HOUR_IN_SECONDS,
            'daily'              => DAY_IN_SECONDS,
            'twicedaily_day'     => 12 * HOUR_IN_SECONDS,
            'weekly'             => WEEK_IN_SECONDS,
            'monthly'            => 30 * DAY_IN_SECONDS,
        ];

        if (isset($map[$name])) {
            return $map[$name];
        }

        // If it is already numeric (e.g. from cron_schedules filter), use it directly.
        if (is_numeric($name)) {
            return (int) $name;
        }

        // Fallback: query WP cron_schedules so custom intervals defined elsewhere work.
        $schedules = wp_get_schedules();
        if (isset($schedules[$name]['interval'])) {
            return (int) $schedules[$name]['interval'];
        }

        return DAY_IN_SECONDS; // safe fallback
    }

    /**
     * Returns true if Action Scheduler is available AND its data store is initialized.
     * Returning false causes schedule() to fall back to WP Cron silently, avoiding
     * "called before the Action Scheduler data store was initialized" notices.
     */
    public static function as_available(): bool {
        if (!function_exists('as_schedule_recurring_action')) {
            return false;
        }
        if (class_exists('ActionScheduler_DataController')
            && method_exists('ActionScheduler_DataController', 'is_data_store_initialized')
            && !ActionScheduler_DataController::is_data_store_initialized()) {
            return false;
        }
        return true;
    }

    /**
     * Schedule a recurring action (once if not already scheduled).
     *
     * @param string $hook       WP action hook name
     * @param string $interval   WP Cron interval name (e.g. 'hourly', 'daily')
     * @param array  $args       Arguments passed to the action
     * @param int    $delay      Initial delay in seconds (default: spread 0–3600 s to avoid thundering herd)
     */
    public static function schedule(string $hook, string $interval, array $args = [], int $delay = -1): void {
        if (!self::as_available()) {
            // Fallback: WP Cron
            if (!wp_next_scheduled($hook, $args)) {
                $offset = ($delay >= 0) ? $delay : rand(0, 3600);
                wp_schedule_event(time() + $offset, $interval, $hook, $args);
            }
            return;
        }

        if (!as_next_scheduled_action($hook, $args, self::GROUP)) {
            $offset   = ($delay >= 0) ? $delay : rand(0, 3600);
            $interval_s = self::interval_seconds($interval);
            as_schedule_recurring_action(time() + $offset, $interval_s, $hook, $args, self::GROUP);
        }
    }

    /**
     * Returns next scheduled timestamp, or false if not scheduled.
     *
     * @param string $hook
     * @param array  $args
     * @return int|false
     */
    public static function next_run(string $hook, array $args = []) {
        if (self::as_available()) {
            $ts = as_next_scheduled_action($hook, $args, self::GROUP);
            return ($ts !== false) ? $ts : false;
        }
        return wp_next_scheduled($hook, $args);
    }

    /**
     * Alias for next_run — returns bool (BC with wp_next_scheduled checks).
     */
    public static function is_scheduled(string $hook, array $args = []): bool {
        return self::next_run($hook, $args) !== false;
    }

    /**
     * Cancel all pending instances of a recurring action.
     *
     * @param string $hook
     * @param array  $args
     */
    public static function cancel(string $hook, array $args = []): void {
        if (self::as_available()) {
            as_unschedule_all_actions($hook, $args, self::GROUP);
            return;
        }
        wp_clear_scheduled_hook($hook, $args);
    }

    /**
     * Queue a one-time action to run immediately (or with optional delay).
     *
     * @param string $hook
     * @param array  $args
     * @param int    $delay  seconds from now (default 0 = immediate)
     */
    public static function run_now(string $hook, array $args = [], int $delay = 0): void {
        if (self::as_available()) {
            as_schedule_single_action(time() + $delay, $hook, $args, self::GROUP);
            return;
        }
        // WP Cron fallback: schedule single event 1 second from now
        wp_schedule_single_event(time() + max(1, $delay), $hook, $args);
    }
}
