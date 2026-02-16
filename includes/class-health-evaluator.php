<?php
if (!defined('ABSPATH')) exit;

class WP_Site_Audit_Health {

    /* =================================================
       GENERIC STATUS HELPER
    ================================================= */

    private static function result($state, $label = '') {

        return [
            'state' => $state, // good | warn | bad
            'label' => $label
        ];
    }

    private static function compare($value, $good, $warn, $reverse = false) {

        // reverse = higher is better (memory, php version etc)

        if (!$reverse) {
            if ($value <= $good) return self::result('good');
            if ($value <= $warn) return self::result('warn');
            return self::result('bad');
        } else {
            if ($value >= $good) return self::result('good');
            if ($value >= $warn) return self::result('warn');
            return self::result('bad');
        }
    }

    /* =================================================
       DATABASE
    ================================================= */

    public static function db_tables($count) {
        return self::compare($count, 25, 60);
    }

    public static function db_size_mb($mb) {
        return self::compare($mb, 100, 300);
    }

    public static function db_overhead_size_mb($mb) {
        return self::compare($mb, 5, 20);
    }

    public static function options_rows($rows) {
        return self::compare($rows, 500, 1500);
    }

    public static function db_revisions($rev)  {
        return self::compare($rev, 500, 2000);
    }

    public static function db_transients($trans)  {
        return self::compare($trans, 50, 300);
    }

    public static function db_spam($spam)  {
        return self::compare($spam, 5, 30);
    }

    /* =================================================
       PERFORMANCE
    ================================================= */

    public static function php_version($version) {

        if (version_compare($version, '8.1', '>=')) return self::result('good');
        if (version_compare($version, '7.4', '>=')) return self::result('warn');

        return self::result('bad');
    }

    public static function memory_limit_mb($mb) {
        return self::compare($mb, 128, 64, true);
    }

    public static function requests($count) {
        return self::compare($count, 60, 120);
    }

    public static function page_size_mb($mb) {
        return self::compare($mb, 2, 4);
    }

    /* =================================================
       SECURITY
    ================================================= */

    public static function debug($enabled) {
        return $enabled
            ? self::result('bad', 'Disable in production')
            : self::result('good');
    }

    public static function file_edit($enabled) {
        return $enabled
            ? self::result('warn', 'Better disable')
            : self::result('good');
    }

    public static function ssl($enabled) {
        return $enabled
            ? self::result('good')
            : self::result('bad');
    }

    public static function security_plugins($count) {

        if ($count === 1) return self::result('good');
        if ($count === 0) return self::result('bad');

        return self::result('warn', 'Multiple firewalls may conflict');
    }

    /* =================================================
       FILES
    ================================================= */

    public static function site_size_mb($mb) {
        return self::compare($mb, 1000, 3000);
    }

    public static function large_files($count) {
        return self::compare($count, 2, 6);
    }

    public static function log_size_mb($mb) {
        return self::compare($mb, 10, 50);
    }

    /* =================================================
       PLUGINS
    ================================================= */

    public static function plugin_count($count) {
        return self::compare($count, 15, 30);
    }

    public static function inactive_plugins($count) {
        return self::compare($count, 2, 5);
    }
}