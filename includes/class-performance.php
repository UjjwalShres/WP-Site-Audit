<?php
if (!defined('ABSPATH')) exit;

class WP_Site_Audit_Performance {

    public static function get_performance_data() {

        global $wp_scripts, $wp_styles;

        $requests = 0;
        $total_size = 0;
        $resources = [];

        $content_dirs = [WP_CONTENT_DIR]; // Only scan wp-content for scripts/styles

        // Helper function to safely get local file path
        $get_local_file = function($src) use ($content_dirs) {
            $relative = str_replace(site_url('/'), '', $src);
            $path = ABSPATH . $relative;

            // Only include if in wp-content
            foreach ($content_dirs as $dir) {
                if (strpos(realpath($path), realpath($dir)) === 0 && file_exists($path)) {
                    return $path;
                }
            }
            return false;
        };

        // Scripts
        foreach ($wp_scripts->registered as $script) {
            if (!empty($script->src)) {
                $file = $get_local_file($script->src);
                if ($file) {
                    $size = filesize($file);
                    $resources[] = ['name' => basename($file), 'size' => $size];
                    $total_size += $size;
                    $requests++;
                }
            }
        }

        // Styles
        foreach ($wp_styles->registered as $style) {
            if (!empty($style->src)) {
                $file = $get_local_file($style->src);
                if ($file) {
                    $size = filesize($file);
                    $resources[] = ['name' => basename($file), 'size' => $size];
                    $total_size += $size;
                    $requests++;
                }
            }
        }

        usort($resources, fn($a, $b) => $b['size'] <=> $a['size']);

        return [
            'requests' => $requests,
            'size' => $total_size,
            'largest' => array_slice($resources, 0, 5),
            'cache' => self::detect_cache_plugin(),
            'php_version' => PHP_VERSION,
            'memory_limit' => WP_MEMORY_LIMIT
        ];
    }


    public static function detect_cache_plugin() {

        $plugins = [
            'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache',
            'wp-super-cache/wp-cache.php' => 'WP Super Cache',
            'w3-total-cache/w3-total-cache.php' => 'W3 Total Cache',
            'wp-rocket/wp-rocket.php' => 'WP Rocket'
        ];

        foreach ($plugins as $file => $name) {
            if (is_plugin_active($file)) {
                return $name . ' (Active)';
            }
        }

        return 'No Cache Plugin';
    }
}