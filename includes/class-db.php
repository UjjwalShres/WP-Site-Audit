<?php
if (!defined('ABSPATH')) exit;

class WP_Site_Audit_DB {

    /*
    ---------------------------------
    Get all table stats + totals
    ---------------------------------
    */
    public static function get_db_stats() {
        global $wpdb;

        $tables = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);

        $data = [];
        $total_size = 0;
        $total_overhead = 0;
        $total_rows = 0;

        foreach($tables as $table){

            $size = $table['Data_length'] + $table['Index_length'];
            $overhead = $table['Data_free'];
            $rows = $table['Rows'];

            $total_size += $size;
            $total_overhead += $overhead;
            $total_rows += $rows;

            $data[] = [
                'name' => $table['Name'],
                'rows' => $table['Rows'],
                'size' => $size,
                'overhead' => $overhead
            ];
        }

        return [
            'tables' => $data,
            'total_size' => $total_size,
            'total_overhead' => $total_overhead,
            'total_rows' => $total_rows
        ];
    }


    /*
    ---------------------------------
    Core WordPress tables
    ---------------------------------
    */
    private static function get_core_tables() {
        global $wpdb;

        return [
            $wpdb->posts,
            $wpdb->postmeta,
            $wpdb->users,
            $wpdb->usermeta,
            $wpdb->comments,
            $wpdb->commentmeta,
            $wpdb->terms,
            $wpdb->termmeta,
            $wpdb->term_taxonomy,
            $wpdb->term_relationships,
            $wpdb->options,
            $wpdb->links
        ];
    }


    /*
    ---------------------------------
    Detect orphan tables
    (not core WP tables)
    ---------------------------------
    */
    public static function get_orphan_tables() {
        global $wpdb;

        $all_tables = $wpdb->get_col("SHOW TABLES");
        $core_tables = self::get_core_tables();

        $orphans = [];

        foreach ($all_tables as $table) {
            if (!in_array($table, $core_tables)) {
                $orphans[] = $table;
            }
        }

        return $orphans;
    }


    /*
    ---------------------------------
    Counts
    ---------------------------------
    */
    public static function get_post_revisions() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type='revision'");
    }

    public static function get_transients() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
    }

    public static function get_spam_comments() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved='spam'");
    }
}