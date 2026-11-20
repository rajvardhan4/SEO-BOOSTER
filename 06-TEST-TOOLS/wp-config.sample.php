<?php
define('DB_NAME','wp'); define('DB_USER','u'); define('DB_PASSWORD','p'); define('DB_HOST','localhost');
define('DB_CHARSET','utf8'); define('DB_COLLATE','');
define('DB_DIR', __DIR__.'/wp-content/database/'); define('DB_FILE','wp.sqlite');
$table_prefix='wp_';
define('WP_DEBUG',true); define('WP_DEBUG_LOG',__DIR__.'/debug.log'); define('WP_DEBUG_DISPLAY',false);
define('WP_HOME','http://127.0.0.1:8899'); define('WP_SITEURL','http://127.0.0.1:8899');
define('AUTH_KEY','a'); define('SECURE_AUTH_KEY','b'); define('LOGGED_IN_KEY','c'); define('NONCE_KEY','d');
define('AUTH_SALT','e'); define('SECURE_AUTH_SALT','f'); define('LOGGED_IN_SALT','g'); define('NONCE_SALT','h');
if(!defined('ABSPATH')) define('ABSPATH', __DIR__.'/');
require_once ABSPATH.'wp-settings.php';
