<?php
if (!defined('ABSPATH')) exit;

class WP_Site_Audit_Report {

    public static function generate() {

        return [

            'database' => WP_Site_Audit_Data::database(),

            'performance' => WP_Site_Audit_Data::performance(),

            //'security' => WP_Site_Audit_Data::security(),

            //'files' => WP_Site_Audit_Data::files(),

            //'plugins' => WP_Site_Audit_Data::plugins()

        ];

    }

}