<?php
if (!defined('ABSPATH')) exit;
define('WPSA_URL', plugin_dir_url(__FILE__));
define('WPSA_PATH', plugin_dir_path(__FILE__));


class WP_Site_Audit_Admin {

    public static function init() {
        add_menu_page(
            'Site Audit',
            'Site Audit',
            'manage_options',
            'wp-site-audit',
            [__CLASS__, 'render_dashboard'],
            'dashicons-analytics',
            3
        );
    }

    private static function get_report() {
        $report = get_transient('wpsa_last_report');

        if (!$report) {
            $report = WP_Site_Audit_Report::generate();
            set_transient('wpsa_last_report', $report, 300);
        }

        return $report;
    }


    private static function card($title, $value, $state = 'good', $tooltip = null) {

    $tooltip_html = '';

    if ($tooltip && is_array($tooltip)) {

    $tooltip_html = '
    <div class="wpsa-tooltip">
        <span class="wpsa-tooltip-icon wpsa-tooltip-icon-'.$state.'">i</span>
        <div class="wpsa-tooltip-content">';

    foreach ($tooltip as $key => $message) {

        $icon = '';
        if ($key === 'good') {
            $icon = '<span class="good">✓ </span>';
        } elseif ($key === 'warn') {
            $icon = '<span class="warn">! </span>';
        } elseif ($key === 'bad') {
            $icon = '<span class="bad">X </span>';
        }

        $tooltip_html .= '<p>'.$icon.$message.'</p>';
    }

    $tooltip_html .= '
        </div>
    </div>';
    }


    return '
    <div class="wpsa-card">
        <h3>'.$title.'</h3>
        <span class="wpsa-value">'.$value.'</span>
        <div class="wpsa-card-'.$state.'"></div>
        '.$tooltip_html.'
    </div>';
}


    public static function render_dashboard() {

    $tabs = [
        'db' => 'Database',
        'performance' => 'Performance',
        'security' => 'Security',
        'files' => 'Files',
        'plugins' => 'Plugins',
        'export' => 'Export',
    ];

    $current_tab = $_GET['tab'] ?? 'db';

    /* ======================
       HEADER + LOGO
    ====================== */

    echo '<div class="wpsa-header-container">
        <div class="wpsa-header">
            <img src="' . esc_url(WPSA_URL . 'assets/wp-site-audit-logo.png') . '">
            <div class="wp-audit-logo">WP Site Audit</div>
          </div>';



    /* ======================
       TABS
    ====================== */

    echo '<div class="wpsa-tabs">';

    foreach ($tabs as $key => $label) {

        $active = ($current_tab === $key) ? 'wpsa-tab-active' : '';

        echo '<a class="wpsa-tab '.$active.'" href="?page=wp-site-audit&tab='.$key.'">'.$label.'</a>';
    }

    echo '</div>
    </div>';

    echo '<div class="wrap wp-site-audit-wrap">';

    echo '<div class="wp-site-audit-content">';

    switch($current_tab) {
        case 'db': self::render_db_tab(); break;
        case 'performance': self::render_performance_tab(); break;
        case 'security': self::render_security_tab(); break;
        case 'files': self::render_files_tab(); break;
        case 'plugins': self::render_plugins_tab(); break;
        case 'export': self::render_export_tab(); break;
    }

    echo '</div></div>';
}

/* -----------------------Database tab ---------------------------*/

    private static function render_db_tab() {

    $report = self::get_report();
    $db = $report['database'];

    $tables = $db['tables'];
    $total_rows = $db['total_rows'];

    $size_mb = $db['total_size_mb'];
    $overhead_mb = $db['total_overhead_mb'];

    $revisions = $db['revisions'];
    $transients = $db['transients'];
    $spam = $db['spam_comments'];

    $req_size_mb = WP_Site_Audit_Health::db_size_mb($size_mb);
    $req_overhead_size_mb = WP_Site_Audit_Health::db_overhead_size_mb($overhead_mb);
    $req_revisions = WP_Site_Audit_Health::db_revisions($revisions);
    $req_transients = WP_Site_Audit_Health::db_transients($transients);
    $req_spam = WP_Site_Audit_Health::db_spam($spam);

    /* ======================
       STAT CARDS
    ====================== */

    echo '<div class="wpsa-card-grid">';
    echo self::card(
            'Total DB Size',
            $size_mb. ' MB',
            $req_size_mb['state'],
            [
                'good' => '< 100 MB',
                'warn' => '100 – 300 MB',
                'bad'  => '> 300 MB'
            ]
        );

    echo self::card(
            'Overhead',
            $overhead_mb. ' MB',
            $req_overhead_size_mb['state'],
            [
                'good' => '< 5 MB',
                'warn' => '< 20 MB',
                'bad'  => '> 20 MB'
            ]
        );

    echo self::card(
            'Revisions',
            $revisions,
            $req_revisions['state'],
            [
                'good' => '< 500',
                'warn' => '500 – 2000',
                'bad'  => '> 2000'
            ]
        );

    echo self::card(
            'Transients',
            $transients,
            $req_transients['state'],
            [
                'good' => '< 50',
                'warn' => '50 – 300',
                'bad'  => '> 300'
            ]
        );

    echo self::card(
            'Spam Comments',
            $spam,
            $req_spam['state'],
            [
                'good' => '< 5',
                'warn' => '5 – 30',
                'bad'  => '> 30'
            ]
        );

    echo '</div>';


    /* ======================
       TABLE
    ====================== */

    echo '<div class="table-container">
    <table class="widefat striped wpsa-table">
    <thead>
        <tr>
            <th>Table</th>
            <th>Rows</th>
            <th>Size (MB)</th>
            <th>Overhead (MB)</th>
        </tr>
    </thead><tbody>';

    foreach($tables as $table){
        echo '<tr>
            <td>'.$table['name'].'</td>
            <td>'.$table['rows'].'</td>
            <td>'.round($table['size']/1024/1024,2).'</td>
            <td>'.round($table['overhead']/1024/1024,2).'</td>
        </tr>';
    }

    echo '<tr class="wpsa-total-row">
        <td>Total</td>
        <td>'.$total_rows.'</td>
        <td>'.$size_mb.'</td>
        <td>'.$overhead_mb.'</td>
    </tr>';

    echo '</tbody></table>';


    /* ======================
       ORPHANS
    ====================== */

    $orphans = WP_Site_Audit_DB::get_orphan_tables();

    if(empty($orphans)){
        echo '<div class="orphan-tables">✔ No orphan tables detected</div>';
    } else {
        echo '<div class="orphan-tables"><strong>Orphan Tables Detected:</strong><ul>';
        foreach($orphans as $table){
            echo '<li>'.$table.'</li>';
        }
        echo '</ul></div>';
    }

    echo '</div>';
}

/* -----------------------Performance tab ---------------------------*/

    public static function render_performance_tab() {
    
    $report = self::get_report();
    $data = $report['performance'];
    
    $req_health  = WP_Site_Audit_Health::requests($data['requests']);
    $size_health = WP_Site_Audit_Health::page_size_mb($data['page_size_mb']);
    $cache_state = ($data['cache_plugin'] === 'No Cache Plugin') ? 'bad' : 'good';
    $php_health = WP_Site_Audit_Health::php_version($data['php_version']);
    $memory_health = WP_Site_Audit_Health::memory_limit_mb($data['memory_limit']);

        echo '<div class="wpsa-card-grid">';

        echo self::card(
            'Requests',
            $data['requests'],
            $req_health['state'],
            [
                'good' => '< 60',
                'warn' => '60 – 120',
                'bad'  => '> 120'
            ]
        );
        echo self::card(
            'Page Size',
            $data['page_size_mb'] . ' MB',
            $size_health['state'],
            [
                'good' => '< 2MB',
                'warn' => '2MB – 4MB',
                'bad'  => '> 4MB'
            ]
        );
        echo self::card(
            'Cache',
            $data['cache_plugin'],
            $cache_state,
            [
                'good' => '1 Cache Plugin',
                'warn' => 'More than 1 Cache Plugins',
                'bad'  => 'No Cache Plugin'
            ]
            );
        echo self::card('Load Time', 'Test Needed', 'warn');

        echo '</div>';


?> <div class="bar-chart-section">
    <div class="bar-chart">
        <h3>Largest Resources</h3>

        <div class="chart-container">
            <?php
                    $largest = $data['largest_resources'];
                    // $largest = [
                    // ['name' => 'jquery.js', 'size' => 1048576],       // 1 MB
                    // //['name' => 'style.css', 'size' => 524288],        // 0.5 MB
                    // ['name' => 'main.js', 'size' => 2097152],         // 2 MB
                    // ['name' => 'plugin.js', 'size' => 3145728],       // 3 MB
                    // ['name' => 'extra.css', 'size' => 1572864],       // 1.5 MB
                    // ['name' => 'extra-large.css', 'size' => 9097152],   
                    //     ];
                    $max_size = max(array_column($largest, 'size')); // largest file size
                    $y_steps = 6; // number of horizontal lines on Y-axis
                    $step_value = ceil($max_size / $y_steps); // size per step
                    ?>
            <!-- Y-axis -->
            <div class="chart-y-axis">
                <?php for($i = $y_steps; $i >= 0; $i--):
                        $value_bytes = $i * $step_value;
                        // Convert to MB with 1 decimal
                        $value_mb = $value_bytes / (1024*1024);
                        $label = ($value_mb >= 1) ? round($value_mb, 1).' MB' : round($value_bytes / 1024, 0).' KB';
                    ?>
                <div class="y-label"><?php echo $label; ?></div>
                <?php endfor; ?>
            </div>

            <!-- Bars -->
            <div class="chart-bars">
                <?php foreach($largest as $item):
                        $height_percent = ($item['size'] / ($y_steps * $step_value)) * 100;
                    ?>
                <div class="bar" style="height: <?php echo $height_percent; ?>%">
                    <div class="bar-value"><?php echo size_format($item['size']); ?></div>
                </div>
                <?php endforeach; ?>

                <div class="bar-label-group">
                    <?php foreach($largest as $label): ?>
                    <span class="bar-label"><?php echo $label['name']; ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>

    <div class="bar-side-section">
        <?php 
            echo self::card(
                'PHP Version',
                $data['php_version'],
                $php_health['state'],
                [
                    'good' => '>= 8.1',
                    'warn' => '8.0',
                    'bad'  => '< 8.0'
                ]
            );

            echo self::card(
                'Memory Usage',
                $data['memory_limit'] . 'B',
                $memory_health['state'],
                [
                    'good' => '>= 256MB',
                    'warn' => '128MB – 255MB',
                    'bad'  => '< 128MB'
                ]
            );
            ?>
    </div>

</div>

<?php

    }


/* -----------------------Security tab ---------------------------*/
    public static function render_security_tab() {

    $data = self::get_security_data();
    $security = WP_Site_Audit_Health::security_plugins_status($data['security_plugins']);
    $firewall_status = WP_Site_Audit_Health::firewall_status($data['firewall']);
    $outdated_plugins_status = WP_Site_Audit_Health::outdated_plugins_status($data['updates']['plugins']);
    $outdated_themes_status = WP_Site_Audit_Health::outdated_themes_status($data['updates']['plugins']);
    $login_safety_status = WP_Site_Audit_Health::login_safety_status($data['login']);

    echo '<div class="wpsa-card-grid">';

    echo self::card(
        'Security Plugins',
        $security['value'],
        $security['state'],
        $security['tooltip']
    );
    echo self::card(
        'Firewall', 
        $firewall_status['value'],
        $firewall_status['state'],
        $firewall_status['tooltip']
    );
    echo self::card(
        'Outdated Plugins', 
        $outdated_plugins_status['value'],
        $outdated_plugins_status['state'],
        $outdated_plugins_status['tooltip']
        );
    echo self::card(
        'Outdated Themes', 
        $outdated_themes_status['value'],
        $outdated_themes_status['state'],
        $outdated_themes_status['tooltip']
        );
    echo self::card('Login Safety', $login_safety_status['value'], $login_safety_status['state'], $login_safety_status['tooltip']);

    echo '</div>';

    echo '<div class="wpsa-security-large-section">
    <div class="wpsa-suspicious-list"><h3>Suspicious Files</h3>';

    if ($data['suspicious']) {
        echo '<ul>';
        foreach ($data['suspicious'] as $file) {
            echo '<li>'.$file.'</li>';
        }
        echo '</ul>';
    } else {
        echo 'No suspicious patterns found';
    }

    echo '</div>
    
    <div class="wpsa-security-side-section">';
        echo self::card(
            'Debug',
            $data['debug_mode']['value'],
            $data['debug_mode']['state'],
            [
                'good' => 'Disabled',
                'bad'  => 'Enabled'
            ]
        );

        // echo self::card(
        //     'Debug Log',
        //     $data['debug_log']['value'],
        //     $data['debug_log']['state'],
        //     [
        //         'good' => 'Disabled',
        //         'warn' => 'Enabled'
        //     ]
        // );

        echo self::card(
            'File Editor',
            $data['file_edit']['value'],
            $data['file_edit']['state'],
            [
                'good' => 'Disabled',
                'bad'  => 'Enabled'
            ]
        );
                
    echo '</div>

    </div>';


    }

    private static function get_security_data() {

    return [
        'security_plugins' => self::detect_security_plugins(),
        'updates' => self::check_updates(),
        'firewall' => self::detect_firewall(),
        'login' => self::check_login_security(),
        'suspicious' => self::scan_suspicious_files(),
        'debug_mode' => self::check_debug_mode(),
        //'debug_log'  => self::check_debug_log(),
        'file_edit'  => self::check_file_edit()
    ];
    }

    private static function detect_security_plugins() {

    $known = [
        'wordfence/wordfence.php' => 'Wordfence',
        'defender-security/wp-defender.php' => 'Defender',
        'ithemes-security-pro/ithemes-security-pro.php' => 'iThemes Security',
        'sucuri-scanner/sucuri.php' => 'Sucuri'
    ];

    $results = [];

    foreach ($known as $file => $name) {
        if (is_plugin_active($file)) {
            $results[] = $name . ' (Active)';
        }
    }

    if (!$results) {
        $results[] = 'No security plugin detected';
    }

    return $results;
    }

    private static function detect_firewall() {

        if (file_exists(ABSPATH . '.htaccess')) {
            $ht = file_get_contents(ABSPATH . '.htaccess');

            if (strpos($ht, 'Wordfence') !== false) return 'Wordfence Firewall Active';
            if (strpos($ht, 'LiteSpeed') !== false) return 'LiteSpeed Server Firewall';

            return 'Basic .htaccess rules detected';
        }

        return 'No firewall rules detected';
    }

    private static function check_updates() {

    require_once ABSPATH . 'wp-admin/includes/update.php';

    wp_update_plugins();
    wp_update_themes();

    $plugins = get_site_transient('update_plugins');
    $themes  = get_site_transient('update_themes');

    return [
        'plugins' => count($plugins->response ?? []),
        'themes'  => count($themes->response ?? [])
    ];
    }

    private static function check_login_security() {

    $issues = [];

    if (get_option('users_can_register')) {
        $issues[] = 'Anyone can register';
    }

    if (!file_exists(ABSPATH . '.htaccess')) {
        $issues[] = 'No server protection (.htaccess missing)';
    }

    if (!$issues) $issues[] = 'Basic protection OK';

    return $issues;
    }

    private static function scan_suspicious_files() {

    $suspects = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(ABSPATH)
    );

    $patterns = ['base64_decode(', 'eval(', 'gzinflate(', 'shell_exec('];

    foreach ($iterator as $file) {

        if ($file->getExtension() !== 'php') continue;

        $content = @file_get_contents($file->getPathname());

        foreach ($patterns as $p) {
            if (strpos($content, $p) !== false) {
                $suspects[] = $file->getFilename();
                break;
            }
        }

        if (count($suspects) >= 10) break;
    }

    return $suspects;
    }

    private static function check_debug_mode() {

    if (defined('WP_DEBUG') && WP_DEBUG) {
        return [
            'value' => 'Enabled',
            'state' => 'bad'
        ];
    }

    return [
        'value' => 'Disabled',
        'state' => 'good'
    ];
    }

    private static function check_file_edit() {

    if (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT) {
        return [
            'value' => 'Disabled',
            'state' => 'good'
        ];
    }

    return [
        'value' => 'Enabled',
        'state' => 'bad'
    ];
    }



/* -----------------------Files tab ---------------------------*/
    public static function render_files_tab() {

    $data = self::get_filesystem_data();
    $site_size_mb_status = WP_Site_Audit_Health::site_size_mb($data['total']);
    $uploads_size_status = WP_Site_Audit_Health::uploads_size($data['uploads']);
    $temp_size_status = WP_Site_Audit_Health::temp_size($data['temp']);
    $log_size_status = WP_Site_Audit_Health::log_size($data['logs']['total']);

    echo '<div class="wpsa-card-grid">';

    //echo self::card('Total Site Size', size_format($data['total']));
    echo self::card(
        'Total Site Size',
        $site_size_mb_status['value'] . ' MB',
        $site_size_mb_status['state'],
        $site_size_mb_status['tooltip']
    );
    //echo self::card('Uploads', size_format($data['uploads']));
    echo self::card(
        'Uploads',
        $uploads_size_status['value'] . ' MB',
        $uploads_size_status['state'],
        $uploads_size_status['tooltip']
    );
    //echo self::card('Temp/Cache', size_format($data['temp']));
    echo self::card(
        'Temp/Cache',
        $temp_size_status['value'] . ' MB',
        $temp_size_status['state'],
        $temp_size_status['tooltip']
    );
    //echo self::card('Log Files', count($data['logs']));
    echo self::card(
        'Log Files',
        $log_size_status['value'] . ' MB',
        $log_size_status['state'],
        $log_size_status['tooltip']
    );

    echo '</div>';

    echo '<div class="wpsa-files-large-section">
    <div class="wpsa-log-list"><h3>Log Files</h3>';

    if ($data['logs']['files']) {
        echo '<ul>';
        foreach ($data['logs']['files'] as $log) {
            echo '<li>'.$log['name'].' ('. size_format($log['size']).')</li>';
        }
        echo '</ul>';
    } else {
        echo 'No log files found';
    }

    echo '<h3>Large Files (>10MB)</h3>';

    if ($data['large']) {
        echo '<ul>';
        foreach ($data['large'] as $file) {
            echo '<li>'.$file['name'].' ('. size_format($file['size']).')</li>';
        }
        echo '</ul>';
    } else {
        echo 'No unusually large files found';
    }

    echo '</div>
    
    <div class="wpsa-files-side-section">';

    echo self::gauge($data['total']);                    

    echo '</div>';

    echo '</div>';

    }

    private static function get_filesystem_data() {

    return [
        'total'   => self::folder_size(ABSPATH),
        'uploads' => self::folder_size(wp_upload_dir()['basedir']),
        'logs'    => self::scan_logs(),
        'temp'    => self::scan_temp(),
        'large'   => self::scan_large_files(ABSPATH)
    ];
}

private static function folder_size($path) {

    $size = 0;

    if (!is_dir($path)) return 0;

    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    ) as $file) {
        $size += $file->getSize();
    }

    return $size;
}

private static function gauge($bytes) {

    // Convert to MB for logic
    $value_mb = $bytes / 1024 / 1024;

    // Max 3GB (in MB)
    $max_mb = 3072;

    $percentage = min(100, ($value_mb / $max_mb) * 100);

    // Determine color
    if ($value_mb < 300) {
        $color = '#ceeac2'; // green
    } elseif ($value_mb < 1000) {
        $color = '#ffd0b3'; // orange
    } else {
        $color = '#ffc6c6'; // red
    }

    // Format display nicely
    $display = size_format($bytes, 1);

    ob_start();
    ?>

<svg viewBox="0 0 200 120">

    <!-- Background arc -->
    <path d="M20 100 A80 80 0 0 1 180 100" stroke="#eee" stroke-width="14" fill="none" />

    <!-- Value arc -->
    <path d="M20 100 A80 80 0 0 1 180 100" stroke="<?php echo $color; ?>" stroke-width="14" fill="none"
        stroke-dasharray="<?php echo $percentage * 2.83; ?> 999" stroke-linecap="round" />

    <!-- Center Value -->
    <text x="100" y="85" text-anchor="middle" class="wpsa-gauge-value">
        <?php echo $display; ?>
    </text>
    <text x="100" y="100" text-anchor="middle" class="wpsa-gauge-text">
        Total Site Size
    </text>

</svg>

<?php
    return ob_get_clean();
}



private static function scan_logs() {

    $log_files = [];
    $total_size = 0;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(ABSPATH, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {

        if (!$file->isFile()) continue;

        $filename  = $file->getFilename();
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Only real .log files
        if ($extension === 'log') {

            $size = $file->getSize();

            $log_files[] = [
                'name' => $filename,
                'size' => $size
            ];

            $total_size += $size;
        }
    }

    return [
        'files' => $log_files,
        'total' => $total_size
    ];
}


private static function scan_temp() {

    $folders = [
        'cache',
        'tmp',
        'temp',
        'wp-content/cache',
        'wp-content/litespeed'
    ];

    $total = 0;

    foreach ($folders as $folder) {
        $path = ABSPATH . $folder;
        if (is_dir($path)) {
            $total += self::folder_size($path);
        }
    }

    return $total;
}

private static function scan_large_files($path, $limit = 10485760) {

    $large = [];

    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    ) as $file) {

        if ($file->getSize() > $limit) {
            $large[] = [
                'name' => $file->getFilename(),
                'size' => $file->getSize()
            ];
        }

        if (count($large) >= 10) break;
    }

    return $large;
}

/* -----------------------Plugins tab ---------------------------*/
    public static function render_plugins_tab() {

    $data = self::get_plugins_data();

    $active_count = count(array_filter($data['plugins'], fn($p) => $p['active']));
    $total_plugins = count($data['plugins']);
    $plugin_count_status = WP_Site_Audit_Health::plugin_count($active_count);
    $inactive_plugin_count_status = WP_Site_Audit_Health::inactive_plugins($total_plugins - $active_count);
    $wp_version_status = WP_Site_Audit_Health::wp_version($data['compat']['wp']);

    echo '<div class="wpsa-card-grid">';

    echo self::card(
        'Active Plugins',
        $plugin_count_status['value'],
        $plugin_count_status['state'],
        $plugin_count_status['tooltip']
    );
    echo self::card(
        'Inactive Plugins',
        $inactive_plugin_count_status['value'],
        $inactive_plugin_count_status['state'],
        $inactive_plugin_count_status['tooltip']
    );
    echo self::card(
        'WordPress Version',
        $wp_version_status['value'],
        $wp_version_status['state'],
        $wp_version_status['tooltip']
    );

    echo '</div>';

    echo '<div class="wpsa-table-container">';
    echo '<div class="wpsa-first-table-card"><h3>Plugins</h3>';

    echo '<table class="wpsa-plugin-table">
    <tr><th>Name</th><th>Version</th><th>Status</th></tr>';

    foreach ($data['plugins'] as $p) {

        $status = $p['active']
            ? '<span class="wpsa-plugin-active">Active</span>'
            : '<span class="wpsa-plugin-inactive">Inactive</span>';

        echo "<tr>
                <td>{$p['name']}</td>
                <td>{$p['version']}</td>
                <td>{$status}</td>
            </tr>";
    }

    echo '</table></div>';

    echo '<div class="wpsa-second-table-card"><h3>Themes</h3>';

    echo '<table class="wpsa-theme-table">
    <tr><th>Name</th><th>Version</th><th>Status</th></tr>';

    foreach ($data['themes'] as $t) {

        $status = $t['active']
            ? '<span class="wpsa-theme-active">Active</span>'
            : '<span class="wpsa-theme-inactive">Inactive</span>';

        echo "<tr>
                <td>{$t['name']}</td>
                <td>{$t['version']}</td>
                <td>{$status}</td>
            </tr>";
    }

    echo '</table></div>';
    echo '</div>';

    }

    private static function get_plugins_data() {

    return [
        'plugins' => self::get_plugins_list(),
        'themes'  => self::get_themes_list(),
        'compat'  => self::compatibility_check()
    ];
}

private static function get_plugins_list() {

    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    $all_plugins = get_plugins();
    $active = get_option('active_plugins');

    $list = [];

    foreach ($all_plugins as $path => $plugin) {

        $list[] = [
            'name' => $plugin['Name'],
            'version' => $plugin['Version'],
            'active' => in_array($path, $active)
        ];
    }

    return $list;
}

private static function get_themes_list() {

    $themes = wp_get_themes();
    $active = wp_get_theme()->get_stylesheet();

    $list = [];

    foreach ($themes as $slug => $theme) {

        $list[] = [
            'name' => $theme->get('Name'),
            'version' => $theme->get('Version'),
            'active' => ($slug === $active)
        ];
    }

    return $list;
}

private static function compatibility_check() {

    return [
        'php' => PHP_VERSION,
        'wp'  => get_bloginfo('version'),
        'php_ok' => version_compare(PHP_VERSION, '8.0', '>='),
        'wp_ok'  => version_compare(get_bloginfo('version'), '6.0', '>=')
    ];
}

/* -----------------------Export tab ---------------------------*/
    public static function render_export_tab() {

    echo '<div class="wpsa-export-container">';
        echo '<div class="wpsa-export-large-sec">';
            echo '<h2>Export Audit Report</h2>';

            echo '<div class="wpsa-export-options">
            <label>
                <input type="radio" name="wpsa_export_type" value="html" checked>
                HTML Report
            </label>

            <label>
                <input type="radio" name="wpsa_export_type" value="txt">
                TXT Report
            </label>

            <label>
                <input type="radio" name="wpsa_export_type" value="json">
                JSON Report
            </label>

            <label>
                <input type="radio" name="wpsa_export_type" value="csv">
                CSV Report
            </label>

            <label>
                <input type="radio" name="wpsa_export_type" value="pdf">
                PDF (Print)
            </label>

        </div>';

        /* ACTION BUTTONS */
        echo '<div class="wpsa-export-actions">
            <button id="wpsa-download" class="button button-primary">Download</button>
            <a id="wpsa-preview" class="button" target="_blank">Preview</a>
        </div>';

            
        echo '</div>';
        echo '<div class="wpsa-export-small-sec">';
            echo '<h2>Helpful Links</h2>';

            echo '<a>';
            echo '<div class="wpsa-links-sec">';
            echo '<p><span class="icon-alert"></span>Request Feature</p>';
            echo '</div>';
            echo '</a>';

            echo '<a>';
            echo '<div class="wpsa-links-sec">';
            echo '<p><span class="icon-question"></span>Get Support</p>';
            echo '</div>';
            echo '</a>';

            echo '<a>';
            echo '<div class="wpsa-links-sec">';
            echo '<p><span class="icon-tick"></span>Rate our Plugin</p>';
            echo '</div>';
            echo '</a>';

        echo '</div>';
    echo '</div>';
}

private static function get_database_data() {
global $wpdb;

$tables = $wpdb->get_results("SHOW TABLE STATUS");

$total = 0;
$rows = [];

foreach ($tables as $t) {
$size = $t->Data_length + $t->Index_length;
$total += $size;

$rows[] = [
'name' => $t->Name,
'rows' => $t->Rows,
'size' => size_format($size)
];
}

return [
'tables' => $rows,
'total_size' => size_format($total)
];
}

private static function get_site_performance_data() {

return [
'php_memory' => size_format(memory_get_peak_usage(true)),
'php_version' => PHP_VERSION,
'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
];
}

private static function get_site_security_data() {

include_once ABSPATH . 'wp-admin/includes/plugin.php';

return [
'debug' => defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled',
'file_edit' => defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT ? 'Disabled' : 'Enabled',
'ssl' => is_ssl() ? 'Yes' : 'No'
];
}


private static function collect_full_report() {

    return [
        'database'    => self::get_database_data(),
        'performance' => self::get_site_performance_data(),
        'security'    => self::get_site_security_data(),
        'files'       => self::get_filesystem_data(),
        'plugins'     => self::get_plugins_data()
    ];
}



public static function handle_export() {

    if (!current_user_can('manage_options')) {
        wp_die('No permission');
    }

    $type = $_GET['type'] ?? 'html';

    $report = self::collect_full_report();

    // CRITICAL
    while (ob_get_level()) ob_end_clean();

    switch ($type) {

    case 'json':
        self::export_json($report);
        break;

    case 'csv':
        self::export_csv($report);
        break;

    case 'txt':
        self::export_txt($report);
        break;

    case 'pdf':
        self::export_pdf($report);
        break;
    
    case 'preview':
        self::preview_html($report);
        break;


    case 'html':
    default:
        self::export_html($report);
        break;
}

exit;

}



private static function export_json($data) {

header('Content-Type: application/json');
header('Content-Disposition: attachment; filename=wp-audit-report.json');

echo json_encode($data, JSON_PRETTY_PRINT);
}

private static function export_csv($data) {

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename=wp-audit-report.csv');

$out = fopen('php://output', 'w');

foreach ($data as $section => $values) {

fputcsv($out, [$section]);

foreach ($values as $k => $v) {

    // simple values
    if (!is_array($v)) {
        echo "<tr><td>$k</td><td>$v</td></tr>";
    }

    // nested arrays → render table
    else {

        echo "<tr><td colspan='2'><strong>$k</strong></td></tr>";

        foreach ($v as $item) {

            if (is_array($item)) {
                echo '<tr><td colspan="2"><pre>'.esc_html(print_r($item, true)).'</pre></td></tr>';
            }
        }
    }
}


fputcsv($out, []);
}

fclose($out);
}

private static function export_txt($data) {

    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename=wp-site-audit.txt');

    foreach ($data as $section => $values) {

        echo strtoupper($section) . "\n";
        echo str_repeat("=", 30) . "\n";

        foreach ($values as $k => $v) {

            if (!is_array($v)) {
                echo "$k: $v\n";
            } else {
                echo "$k:\n";
                foreach ($v as $item) {
                    if (is_array($item)) {
                        echo " - " . implode(", ", $item) . "\n";
                    }
                }
            }
        }

        echo "\n\n";
    }
}

private static function preview_html($data) {

    // no content-disposition = browser renders
    header('Content-Type: text/html');

    // reuse same UI
    self::render_html_template($data);

    exit;
}

private static function render_html_template($data) {
    $date = date('Y-m-d H:i:s');
    ?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>WP Site Audit Report</title>

    <style>
    /* ===============================
            GLOBAL
            ================================ */
    body {
        margin: 0;
        font-family: Inter, Arial, sans-serif;
        background: #F0F0F1;
        color: #e5e7eb;
    }

    /* ===============================
            HEADER
            ================================ */
    .header {
        padding: 40px;
        text-align: center;
        background-color: #fff;
        color: #111;
    }

    h2,
    h3 {
        color: #181C25;
    }

    .card h3 {
        color: #e5e7eb;
    }

    .header h1 {
        margin: 0;
        font-size: 34px;
        font-weight: 700;
    }

    .header small {
        opacity: .8;
    }

    .wpsa-logo {
        width: 78px;
        height: 78px;
    }

    /* ===============================
            LAYOUT
            ================================ */
    .container {
        padding: 40px;
        max-width: 1200px;
        margin: auto;
    }

    .section {
        margin-bottom: 50px;
    }

    .section h2 {
        font-size: 20px;
        margin-bottom: 15px;
        border-left: 5px solid #ff914d;
        padding-left: 10px;
    }

    /* ===============================
            CARDS
            ================================ */
    .cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
    }

    .cards-margin {
        margin-top: 15px;
    }

    .card {
        background: #181c25;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 0 0 1px #222;
    }

    .card h3 {
        margin: 0 0 8px;
        font-size: 14px;
        opacity: .7;
    }

    .card p {
        font-size: 20px;
        font-weight: 600;
        margin: 0;
    }

    /* ===============================
            TABLE
            ================================ */
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
        background: #181c25;
        border-radius: 10px;
        overflow: hidden;
    }

    th,
    td {
        padding: 10px 12px;
        border-bottom: 1px solid #222;
        font-size: 14px;
    }

    th {
        text-align: left;
        background: #20242f;
    }

    tr:last-child td {
        border-bottom: none;
    }

    .total {
        font-weight: 700;
        color: #ff914d;
    }

    /* badges */
    .badge-ok {
        color: #22c55e;
        font-weight: 600;
    }

    .badge-bad {
        color: #ef4444;
        font-weight: 600;
    }

    .badge-warn {
        color: #f59e0b;
        font-weight: 600;
    }

    .footer {
        text-align: center;
        padding: 30px;
        opacity: .5;
        font-size: 12px;
        color: #181C25;
    }
    </style>
</head>

<body>

    <div class="header">
        <div class="wpsa-header">
            <img class="wpsa-logo" src="<?php echo esc_url(WPSA_URL . 'assets/wp-site-audit-logo.png'); ?>">
        </div>
        <h1>WP Site Audit Report</h1>
        <small>Generated on <?php echo $date; ?></small>
    </div>

    <div class="container">

        <?php
            /* =================================================
            DATABASE
            ================================================= */
            $report = self::get_report();
            $db = $report['database'];
            ?>

        <div class="section">
            <h2>Database Overview</h2>

            <div class="cards">
                <div class="card">
                    <h3>Total Tables</h3>
                    <p><?php echo count($db['tables']); ?></p>
                </div>

                <div class="card">
                    <h3>Total Database Size</h3>
                    <p><?php echo $db['total_size_mb'];  ?> MB</p>
                </div>
            </div>

            <div class="cards cards-margin">
                <div class="card">
                    <h3>Overhead</h3>
                    <p><?php echo $db['total_overhead_mb'];  ?> MB</p>
                </div>

                <div class="card">
                    <h3>Revisions</h3>
                    <p><?php echo $db['revisions'];  ?></p>
                </div>

                <div class="card">
                    <h3>Transients</h3>
                    <p><?php echo $db['transients'];  ?></p>
                </div>

                <div class="card">
                    <h3>Spam Comments</h3>
                    <p><?php echo $db['spam_comments'];  ?></p>
                </div>
            </div>

            <h3>Database Table</h3>
            <table>
                <tr>
                    <th>Table</th>
                    <th>Rows</th>
                    <th>Size</th>
                </tr>

                <?php foreach($db['tables'] as $t): ?>
                <tr>
                    <td><?php echo $t['name']; ?></td>
                    <td><?php echo $t['rows']; ?></td>
                    <td><?php echo $t['size']; ?></td>
                </tr>
                <?php endforeach; ?>

                <tr class="total">
                    <td>TOTAL</td>
                    <td><?php echo $db['total_rows']; ?></td>
                    <td><?php echo $db['total_size_mb']; ?> MB</td>
                </tr>
            </table>
        </div>


        <?php
            /* =================================================
            PERFORMANCE
            ================================================= */
            $report = self::get_report();
            $perf = $report['performance'];
            ?>

        <div class="section">
            <h2>Performance Environment</h2>

            <div class="cards">
                <div class="card">
                    <h3>Requests</h3>
                    <p><?php echo $perf['requests']; ?></p>
                </div>

                <div class="card">
                    <h3>Page Size</h3>
                    <p><?php echo $perf['page_size_mb']; ?> MB</p>
                </div>
            </div>

            <div class="cards cards-margin">
                <div class="card">
                    <h3>Cache</h3>
                    <p><?php echo $perf['cache_plugin']; ?></p>
                </div>

                <div class="card">
                    <h3>PHP Version</h3>
                    <p><?php echo $perf['php_version']; ?></p>
                </div>

                <div class="card">
                    <h3>Memory Usage</h3>
                    <p><?php echo $perf['memory_limit']; ?></p>
                </div>
            </div>

            <h3>Largest Resources</h3>
            <table>
                <tr>
                    <th>Resource</th>
                    <th>Size</th>
                </tr>

                <?php foreach($perf['largest_resources'] as $t): ?>
                <tr>
                    <?php $resource_size = round($t['size']/(1024*1024), 2) ?>
                    <td><?php echo $t['name']; ?></td>
                    <td><?php echo $resource_size; ?> MB</td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>


        <?php
            /* =================================================
            SECURITY
            ================================================= */
            $s = $data['security'];

            function badge($val){
                if($val === 'Yes' || $val === 'Disabled') return 'badge-ok';
                if($val === 'Enabled') return 'badge-warn';
                return 'badge-bad';
            }
            ?>

        <div class="section">
            <h2>Security Status</h2>

            <div class="cards">
                <?php foreach($s as $k => $v): ?>
                <div class="card">
                    <h3><?php echo ucfirst($k); ?></h3>
                    <p class="<?php echo badge($v); ?>"><?php echo $v; ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
            /* =================================================
            FILES
            ================================================= */
            $f = $data['files'];
            ?>

        <div class="section">
            <h2>Files Overview</h2>

            <div class="cards">
                <div class="card">
                    <h3>Total Site Size</h3>
                    <p><?php echo $f['total']; ?></p>
                </div>

                <div class="card">
                    <h3>Uploads</h3>
                    <p><?php echo $f['uploads']; ?></p>
                </div>

                <div class="card">
                    <h3>Temp/Cache</h3>
                    <p><?php echo $f['temp']; ?></p>
                </div>

                <div class="card">
                    <h3>Log Files</h3>
                    <p><?php echo count($f['logs']); ?></p>
                </div>
            </div>

            <!-- Logs Table -->
            <?php if(!empty($f['logs'])): ?>
            <table>
                <tr>
                    <th>File</th>
                    <th>Size</th>
                </tr>
                <?php foreach($f['logs'] as $log): ?>
                <tr>
                    <td><?php echo $log['name']; ?></td>
                    <td><?php echo $log['size']; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>

            <!-- Large Files Table -->
            <?php if(!empty($f['large'])): ?>
            <h3>Large Files (&gt;10MB)</h3>
            <table>
                <tr>
                    <th>File</th>
                    <th>Size</th>
                </tr>
                <?php foreach($f['large'] as $file): ?>
                <tr>
                    <td><?php echo $file['name']; ?></td>
                    <td><?php echo $file['size']; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </div>
        <?php
            /* =================================================
            PLUGINS & THEMES
            ================================================= */
            $p = $data['plugins'];
            ?>

        <div class="section">
            <h2>Plugins Overview</h2>

            <div class="cards">
                <div class="card">
                    <h3>Active Plugins</h3>
                    <p><?php echo count(array_filter($p['plugins'], fn($pl) => $pl['active'])); ?></p>
                </div>

                <div class="card">
                    <h3>Inactive Plugins</h3>
                    <p><?php echo count($p['plugins']) - count(array_filter($p['plugins'], fn($pl) => $pl['active'])); ?>
                    </p>
                </div>
            </div>

            <!-- Plugins Table -->
            <table>
                <tr>
                    <th>Name</th>
                    <th>Version</th>
                    <th>Status</th>
                </tr>
                <?php foreach($p['plugins'] as $pl): ?>
                <tr>
                    <td><?php echo $pl['name']; ?></td>
                    <td><?php echo $pl['version']; ?></td>
                    <td><?php echo $pl['active'] ? 'Active' : 'Inactive'; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>

            <!-- Themes Table -->
            <h3>Themes</h3>
            <table>
                <tr>
                    <th>Name</th>
                    <th>Version</th>
                    <th>Status</th>
                </tr>
                <?php foreach($p['themes'] as $t): ?>
                <tr>
                    <td><?php echo $t['name']; ?></td>
                    <td><?php echo $t['version']; ?></td>
                    <td><?php echo $t['active'] ? 'Active' : 'Inactive'; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>


    </div>

    <div class="footer">
        Generated by WP Site Audit Plugin
    </div>

</body>

</html>


<?php
}




private static function export_html($data) {

    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename=wp-site-audit-report.html');

    self::render_html_template($data);
    exit;
}



}

/* style enqueue */
add_action('admin_enqueue_scripts', function($hook){
if($hook !== 'toplevel_page_wp-site-audit') return;

wp_enqueue_style(
'wp-site-audit-admin',
WP_SITE_AUDIT_URL . 'admin/css/admin-style.css',
[],
'1.2'
);

/* script enqueue */
wp_enqueue_script(
    'wpsa-export-js',
    WP_SITE_AUDIT_URL . 'admin/js/export.js',
    [],
    '1.0',
    true
);

});