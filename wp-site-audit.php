<?php
/*
Plugin Name: WP Site Audit
Plugin URI:  https://example.com
Description: Audits your WordPress site for database, performance, and file system stats.
Version:     1.0.0
Author:      Ujjwal Shrestha
Author URI:  https://example.com
License:     GPL2
Text Domain: wp-site-audit
*/

if (!defined('ABSPATH')) exit;

define('WP_SITE_AUDIT_PATH', plugin_dir_path(__FILE__));
define('WP_SITE_AUDIT_URL', plugin_dir_url(__FILE__));

// Include classes
require_once WP_SITE_AUDIT_PATH . 'admin/class-admin.php';
require_once WP_SITE_AUDIT_PATH . 'includes/class-db.php';
require_once WP_SITE_AUDIT_PATH . 'includes/class-files.php';
require_once WP_SITE_AUDIT_PATH . 'includes/class-performance.php';
require_once WP_SITE_AUDIT_PATH . 'includes/class-health-evaluator.php';

// initialize menu only
add_action('admin_menu', ['WP_Site_Audit_Admin', 'init']);

// register export EARLY
add_action('admin_post_wpsa_export', ['WP_Site_Audit_Admin', 'handle_export']);