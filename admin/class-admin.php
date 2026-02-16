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

    private static function card($title, $value, $state = 'good', $tooltip = null) {

    $tooltip_html = '';

    if ($tooltip && in_array($state, ['warn','bad'])) {

        $tooltip_html = '
        <div class="wpsa-tooltip">
            <span class="wpsa-tooltip-icon">i</span>
            <div class="wpsa-tooltip-content">
                <strong>Thresholds</strong><br>
                Good: '.$tooltip['good'].'<br>
                Warn: '.$tooltip['warn'].'<br>
                Bad: '.$tooltip['bad'].'
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

    $stats = WP_Site_Audit_DB::get_db_stats();

    $tables = $stats['tables'];
    $total_size = $stats['total_size'];
    $total_overhead = $stats['total_overhead'];

    //$size_mb = round($total_size/1024/1024,2);
    $size_mb = 301;
    $overhead_mb = round($total_overhead/1024/1024,2);

    $revisions = WP_Site_Audit_DB::get_post_revisions();
    $transients = WP_Site_Audit_DB::get_transients();
    $spam = WP_Site_Audit_DB::get_spam_comments();

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
        <td>-</td>
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

    $data = self::get_performance_data();
    $req_health  = WP_Site_Audit_Health::requests($data['requests']);
    $size_mb     = round($data['size'] / 1024 / 1024, 2);
    $size_health = WP_Site_Audit_Health::page_size_mb($size_mb);
    $cache_state = ($data['cache'] === 'No cache plugin detected') ? 'bad' : 'good';
    
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
            size_format($data['size']),
            $size_health['state'],
            [
                'good' => '< 2MB',
                'warn' => '2MB – 4MB',
                'bad'  => '> 4MB'
            ]
        );
        echo self::card(
            'Cache', 
            $data['cache'], 
            $cache_state,
            [
                'good' => '1 Cache Plugin',
                'warn' => 'More than 1 Cache Plugins',
                'bad'  => 'No Cache Plugin'
            ]
            );
        echo self::card('Load Time', 'Browser Test Needed', 'warn');

        echo '</div>';

        echo '<div class="wpsa-table-card">';
        echo '<h3>Largest Resources</h3>';

        echo '<table class="wpsa-table">
        <tr><th>File</th><th>Size</th></tr>';

        foreach ($data['largest'] as $res) {
            echo '<tr>
                    <td>'.$res['name'].'</td>
                    <td>'.size_format($res['size']).'</td>
                </tr>';
        }

        echo '</table></div>';


    }

    private static function get_performance_data() {

    global $wp_scripts, $wp_styles;

    $requests = 0;
    $total_size = 0;
    $resources = [];

    // Scripts
    foreach ($wp_scripts->registered as $script) {
        if (!empty($script->src)) {
            $file = ABSPATH . str_replace(site_url('/'), '', $script->src);
            if (file_exists($file)) {
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
            $file = ABSPATH . str_replace(site_url('/'), '', $style->src);
            if (file_exists($file)) {
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
        'cache' => self::detect_cache_plugin()
    ];
}

private static function detect_cache_plugin() {

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

    return 'No cache plugin detected';
}


/* -----------------------Security tab ---------------------------*/
    public static function render_security_tab() {

    $data = self::get_security_data();

    echo '<div class="wpsa-card-grid">';

    echo self::card('Security Plugins', implode('<br>', $data['security_plugins']));
    echo self::card('Firewall', $data['firewall']);
    echo self::card('Outdated Plugins', $data['updates']['plugins']);
    echo self::card('Outdated Themes', $data['updates']['themes']);
    echo self::card('Login Safety', implode('<br>', $data['login']));

    echo '</div>';

    echo '<div class="wpsa-table-card"><h3>Suspicious Files</h3>';

    if ($data['suspicious']) {
        echo '<ul>';
        foreach ($data['suspicious'] as $file) {
            echo '<li>'.$file.'</li>';
        }
        echo '</ul>';
    } else {
        echo 'No suspicious patterns found';
    }

    echo '</div>';


    }

    private static function get_security_data() {

    return [
        'security_plugins' => self::detect_security_plugins(),
        'updates' => self::check_updates(),
        'firewall' => self::detect_firewall(),
        'login' => self::check_login_security(),
        'suspicious' => self::scan_suspicious_files()
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

/* -----------------------Files tab ---------------------------*/
    public static function render_files_tab() {

    $data = self::get_filesystem_data();

    echo '<div class="wpsa-card-grid">';

    echo self::card('Total Site Size', size_format($data['total']));
    echo self::card('Uploads', size_format($data['uploads']));
    echo self::card('Temp/Cache', size_format($data['temp']));
    echo self::card('Log Files', count($data['logs']));

    echo '</div>';

    echo '<div class="wpsa-table-card"><h3>Log Files</h3>';

    if ($data['logs']) {

        echo '<table class="wpsa-table">
        <tr><th>File</th><th>Size</th></tr>';

        foreach ($data['logs'] as $log) {
            echo '<tr>
                    <td>'.$log['name'].'</td>
                    <td>'.size_format($log['size']).'</td>
                </tr>';
        }

        echo '</table>';

    } else {
        echo 'No log files found';
    }

    echo '</div>';


    echo '<div class="wpsa-table-card"><h3>Large Files (>10MB)</h3>';

    if ($data['large']) {

        echo '<table class="wpsa-table">
        <tr><th>File</th><th>Size</th></tr>';

        foreach ($data['large'] as $file) {
            echo '<tr>
                    <td>'.$file['name'].'</td>
                    <td>'.size_format($file['size']).'</td>
                </tr>';
        }

        echo '</table>';

    } else {
        echo 'No unusually large files found';
    }

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

private static function scan_logs() {

    $logs = [];

    foreach (glob(ABSPATH . '*.log') as $file) {
        $logs[] = [
            'name' => basename($file),
            'size' => filesize($file)
        ];
    }

    if (file_exists(ABSPATH . 'error_log')) {
        $logs[] = [
            'name' => 'error_log',
            'size' => filesize(ABSPATH . 'error_log')
        ];
    }

    return $logs;
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

    echo '<div class="wpsa-card-grid">';

    echo self::card('Active Plugins', $active_count);
    echo self::card('Inactive Plugins', $total_plugins - $active_count);
    echo self::card('PHP Version', $data['compat']['php']);
    echo self::card('WordPress', $data['compat']['wp']);

    echo '</div>';

    echo '<div class="wpsa-table-card"><h3>Plugins</h3>';

    echo '<table class="wpsa-table">
    <tr><th>Name</th><th>Version</th><th>Status</th></tr>';

    foreach ($data['plugins'] as $p) {

        $status = $p['active']
            ? '<span class="wpsa-ok">Active</span>'
            : '<span class="wpsa-bad">Inactive</span>';

        echo "<tr>
                <td>{$p['name']}</td>
                <td>{$p['version']}</td>
                <td>{$status}</td>
            </tr>";
    }

    echo '</table></div>';

    echo '<div class="wpsa-table-card"><h3>Themes</h3>';

    echo '<table class="wpsa-table">
    <tr><th>Name</th><th>Version</th><th>Status</th></tr>';

    foreach ($data['themes'] as $t) {

        $status = $t['active']
            ? '<span class="wpsa-ok">Active</span>'
            : 'Inactive';

        echo "<tr>
                <td>{$t['name']}</td>
                <td>{$t['version']}</td>
                <td>{$status}</td>
            </tr>";
    }

    echo '</table></div>';

    if ($data['redundancy']) {

    echo '<div class="wpsa-table-card wpsa-warning">';
    echo '<h3>⚠ Plugin Conflicts</h3>';

    foreach ($data['redundancy'] as $w) {
        echo "<p>$w</p>";
    }

    echo '</div>';
}


    }

    private static function get_plugins_data() {

    return [
        'plugins' => self::get_plugins_list(),
        'themes'  => self::get_themes_list(),
        'compat'  => self::compatibility_check(),
        'redundancy' => self::detect_redundancy()
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

private static function detect_redundancy() {

    $groups = [
        'cache' => [
            'litespeed-cache',
            'wp-super-cache',
            'w3-total-cache',
            'wp-rocket'
        ],
        'security' => [
            'wordfence',
            'defender-security',
            'ithemes-security',
            'sucuri'
        ],
        'pagebuilder' => [
            'elementor',
            'js_composer',
            'wpbakery'
        ]
    ];

    $active = get_option('active_plugins');

    $warnings = [];

    foreach ($groups as $type => $keywords) {

        $found = [];

        foreach ($active as $plugin) {
            foreach ($keywords as $key) {
                if (strpos($plugin, $key) !== false) {
                    $found[] = $plugin;
                }
            }
        }

        if (count($found) > 1) {
            $warnings[] = ucfirst($type) . ' plugins conflict: ' . count($found);
        }
    }

    return $warnings;
}



/* -----------------------Export tab ---------------------------*/
    public static function render_export_tab() {

    echo '<div class="wpsa-export">';

    echo '<h2>Export Audit Report</h2>';

    echo '<a href="' . admin_url('admin-post.php?action=wpsa_export&type=html') . '" class="button button-primary">Download HTML</a> ';

echo '<a href="' . admin_url('admin-post.php?action=wpsa_export&type=csv') . '" class="button">Download CSV</a> ';

echo '<a href="' . admin_url('admin-post.php?action=wpsa_export&type=json') . '" class="button">Download JSON</a>';

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

private static function export_html($data) {

    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename=wp-site-audit-report.html');

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
        background: #0f1117;
        color: #e5e7eb;
    }

    /* ===============================
   HEADER
================================ */
    .header {
        padding: 40px;
        text-align: center;
        background: radial-gradient(circle,
                rgba(238, 174, 202, 1) 0%,
                rgba(148, 187, 233, 1) 100%);
        color: #111;
    }

    .header h1 {
        margin: 0;
        font-size: 34px;
        font-weight: 700;
    }

    .header small {
        opacity: .8;
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
        border-left: 5px solid #94bbe9;
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
        color: #94bbe9;
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
    }
    </style>
</head>

<body>

    <div class="header">
        <h1>WP Site Audit Report</h1>
        <small>Generated on <?php echo $date; ?></small>
    </div>

    <div class="container">

        <?php
/* =================================================
   DATABASE
================================================= */
$db = $data['database'];
?>

        <div class="section">
            <h2>Database Overview</h2>

            <div class="cards">
                <div class="card">
                    <h3>Total Tables</h3>
                    <p><?php echo count($db['tables']); ?></p>
                </div>

                <div class="card">
                    <h3>Total Size</h3>
                    <p><?php echo $db['total_size']; ?></p>
                </div>
            </div>

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
                    <td colspan="2">TOTAL</td>
                    <td><?php echo $db['total_size']; ?></td>
                </tr>
            </table>
        </div>


        <?php
/* =================================================
   PERFORMANCE
================================================= */
$p = $data['performance'];
?>

        <div class="section">
            <h2>Performance Environment</h2>

            <div class="cards">
                <div class="card">
                    <h3>PHP Version</h3>
                    <p><?php echo $p['php_version']; ?></p>
                </div>

                <div class="card">
                    <h3>Memory Usage</h3>
                    <p><?php echo $p['php_memory']; ?></p>
                </div>

                <div class="card">
                    <h3>Server</h3>
                    <p style="font-size:13px"><?php echo $p['server']; ?></p>
                </div>
            </div>
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
});