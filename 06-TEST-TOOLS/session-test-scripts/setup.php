<?php
define('WP_INSTALLING', true);
$_SERVER['HTTP_HOST']='127.0.0.1:8899';
require __DIR__.'/wp/wp-load.php';
require_once ABSPATH.'wp-admin/includes/upgrade.php';
require_once ABSPATH.'wp-admin/includes/plugin.php';
wp_install('Inc Legal Services','admin','a@example.com',true,'','testpass123');
