<?php
if (!defined('ABSPATH')) exit;

class WP_Site_Audit_Performance {

    public static function get_homepage_stats() {
        $start = microtime(true);
        $response = wp_remote_get(home_url());
        $end = microtime(true);

        $load_time = $end - $start;
        $body_size = isset($response['body']) ? strlen($response['body']) : 0;

        return [
            'load_time' => $load_time,
            'body_size' => $body_size
        ];
    }
}