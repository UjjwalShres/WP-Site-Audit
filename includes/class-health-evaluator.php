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
        return self::compare($mb, 128, 256);
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

    public static function security_plugins_status($plugins) {
        if (!empty($plugins)) {
            if($plugins > 1){
                return [
                    'state' => 'bad',
                    'value' => implode('<br>', $plugins),
                    'tooltip' => [
                        'good' => 'Security plugin detected.',
                        'warn' => 'No security plugin detected.',
                        'bad' => 'More than 1 Security plugins detected.'
                    ]
                ];
            }

            else {
                return [
                    'state' => 'good',
                    'value' => implode('<br>', $plugins),
                    'tooltip' => [
                        'good' => 'Security plugin detected.',
                        'warn' => 'No security plugin detected.',
                        'bad' => 'More than 1 Security plugins detected.'
                    ]
                ];
            }
        }

        return [
            'state' => 'warn',
            'value' => 'None detected',
            'tooltip' => [
                'good' => 'Security plugin detected.',
                'warn' => 'No security plugin detected.',
                'bad' => 'More than 1 Security plugins detected.'
            ]
        ];
    }

    public static function firewall_status($firewall) {
        if ($firewall === 'Basic .htaccess rules detected') {
            return [
                'state' => 'good',
                'value' => 'Basic .htaccess rules detected',
                'tooltip' => [
                    'good' => 'Basic .htaccess rules detected',
                    'bad'  => 'No firewall rules detected'
                ]
            ];
        }

        return [
            'state' => 'bad',
            'value' => 'No firewall rules detected',
            'tooltip' => [
                'good' => 'Basic .htaccess rules detected',
                'bad'  => 'No firewall rules detected'
            ]
        ];
    }

    public static function outdated_plugins_status($count) {
        if ($count == 0) {
            $state = 'good';
        } elseif ($count <= 3) {
            $state = 'warn';
        } else {
            $state = 'bad';
        }

        return [
            'state' => $state,
            'value' => $count,
            'tooltip' => [
                'good' => '0 outdated plugins',
                'warn' => '1 or more outdated plugins',
                'bad'  => '>5 outdated plugins'
            ]
        ];
    }

    public static function outdated_themes_status($count) {

        if ($count == 0) {
            $state = 'good';
        } elseif ($count <= 2) {
            $state = 'warn';
        } else {
            $state = 'bad';
        }

        return [
            'state' => $state,
            'value' => $count,
            'tooltip' => [
                'good' => '0 outdated themes',
                'warn' => '1 or more outdated themes',
                'bad'  => '>5 outdated themes'
            ]
        ];
    }

    public static function login_safety_status($login_array) {

        // If the only message is "Basic protection OK"
        if (count($login_array) === 1 && $login_array[0] === 'Basic protection OK') {

            return [
                'state' => 'good',
                'value' => 'Basic protection OK',
                'tooltip' => [
                    'good' => 'User registration is closed and .htaccess exists',
                    'warn' => 'Some login protections missing',
                    'bad'  => 'Critical login security issues detected'
                ]
            ];
        }

        // Otherwise there are issues
        $issue_count = count($login_array);

        if ($issue_count === 1) {
            $state = 'warn';
        } else {
            $state = 'bad';
        }

        return [
            'state' => $state,
            'value' => implode('<br>', $login_array),
            'tooltip' => [
                'good' => 'User registration is closed and .htaccess exists',
                'warn' => 'Some login protections missing',
                'bad'  => 'Critical login security issues detected'
            ]
        ];
    }



    /* =================================================
       FILES
    ================================================= */

    public static function site_size_mb($bytes) {
        $mb = $bytes / 1024 / 1024;
        $mb = round($mb, 2);
        if($mb < 300){
            $state = 'good';
        }elseif($mb < 1000){
            $state = 'warn';
        }else {
            $state = 'bad';
        }

        return [
            'state' => $state,
            'value' => $mb,
            'tooltip' => [
                'good' => '< 300MB (normal small site)',
                'warn' => '< 300MB - 1GB (media heavy or growing)',
                'bad'  => '> 1GB (check uploads, backups, logs)'
            ]
        ];
    }

    public static function uploads_size($uploads_bytes) {
        $uploads_mb = $uploads_bytes / 1024 / 1024;
        $uploads_mb = round($uploads_mb, 2);

        if($uploads_mb < 500){
            $state = 'good';
        }elseif($uploads_mb < 2048){
            $state = 'warn';
        }else {
            $state = 'bad';
        }

        return [
            'state' => $state,
            'value' => $uploads_mb,
            'tooltip' => [
                'good' => '< 500MB (normal site)',
                'warn' => '500MB – 2GB (media heavy)',
                'bad'  => '> 2GB (optimize images / offload)'
            ]
        ];
    }

    public static function temp_size($temp_bytes) {
        $temp_mb = $temp_bytes / 1024 / 1024;
        $temp_mb = round($temp_mb, 2);

        if($temp_mb < 50){
            $state = 'good';
        }elseif($temp_mb < 200){
            $state = 'warn';
        }else {
            $state = 'bad';
        }

        return [
            'state' => $state,
            'value' => $temp_mb,
            'tooltip' => [
                'good' => '< 50MB (healthy)',
                'warn' => '50MB – 200MB (clear cache)',
                'bad'  => '> 200MB (misconfigured cache)'
            ]
        ];
    }

    public static function log_size($log_bytes) {
        $log_mb = $log_bytes / 1024 / 1024;
        $log_mb = round($log_mb, 2);

        if($log_mb < 5){
            $state = 'good';
        }elseif($log_mb < 50){
            $state = 'warn';
        }else {
            $state = 'bad';
        }

        return [
            'state' => $state,
            'value' => $log_mb,
            'tooltip' => [
                'good' => '< 5MB (normal)',
                'warn' => '5MB – 50MB (check recurring errors)',
                'bad'  => '> 50MB (error flood / debug active)'
            ]
        ];
    }

    /* =================================================
       PLUGINS
    ================================================= */

    public static function plugin_count($count) {
        if ($count <= 20) {
            $state = 'good';
        } elseif ($count <= 40) {
            $state = 'warn';
        } else {
            $state = 'bad';
        }

        return [
            'state' => $state,
            'value' => $count,
            'tooltip' => [
                'good' => '0 – 20 plugins (healthy)',
                'warn' => '21 – 40 plugins (may affect performance)',
                'bad'  => '> 40 plugins (high maintenance & risk)'
            ]
        ];
    }

    public static function inactive_plugins($count) {
        if ($count <= 2) {
            $state = 'good';
        } elseif ($count <= 5) {
            $state = 'warn';
        } else {
            $state = 'bad';
        }
        //return self::compare($count, 2, 5);

        return [
            'state' => $state,
            'value' => $count,
            'tooltip' => [
                'good' => '0 – 2 inactive plugins',
                'warn' => '3 – 5 unused plugins',
                'bad'  => '> 5 unused plugins (cleanup recommended)'
            ]
        ];
    }

    public static function wp_version($current,) {
        $update_core = get_site_transient('update_core');
        $latest = $update_core->updates[0]->current ?? $current;

        $current_parts = explode('.', $current);
        $latest_parts  = explode('.', $latest);

        if ($current === $latest) {

            $state = 'good';

        } elseif ($current_parts[0] < $latest_parts[0]) {

            // major difference
            $state = 'bad';

        } else {

            // minor or patch difference
            $state = 'warn';
        }

        return [
            'state' => $state,
            'value' => $current,
            'tooltip' => [
                'good' => 'Latest version installed',
                'warn' => 'Update available (recommended)',
                'bad'  => 'Outdated version (security risk)'
            ]
        ];

    }
}