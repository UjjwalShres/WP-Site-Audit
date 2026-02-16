<?php
if (!defined('ABSPATH')) exit;

class WP_Site_Audit_Files {

    public static function folder_size($dir) {
        $size = 0;
        foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file){
            $size += $file->getSize();
        }
        return $size;
    }
}