<?php
$_SERVER['HTTP_HOST']='127.0.0.1:8899';
require __DIR__.'/wp/wp-load.php';
define('WP_UNINSTALL_PLUGIN','seo-boster/seo-boster.php');
include WP_PLUGIN_DIR.'/seo-boster/uninstall.php';
global $wpdb;
echo 'table after: ', var_export($wpdb->get_var("SELECT name FROM sqlite_master WHERE name='wp_seob_placements'"),true), "\n";
echo 'meta left: ', $wpdb->get_var("SELECT COUNT(*) FROM wp_postmeta WHERE meta_key LIKE '_seob%'"), "\n";
echo 'options left: ', $wpdb->get_var("SELECT COUNT(*) FROM wp_options WHERE option_name LIKE 'seob%'"), "\n";
