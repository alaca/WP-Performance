<?php namespace WPP;
/**
* Plugin Name: WP Performance
* Plugin URI: https://www.wp-performance.com
* Description: WP Performance Optimizer
* Version: 1.1.7
* Author: Ante Laca
* Author URI: https://www.antelaca.xyz
* Licence: GPLv2
* Text Domain: wpp
* Domain Path: /languages  
*/

defined( 'ABSPATH' ) or exit;

// WP Performance
define( 'WPP_VERSION'       , '1.1.7' );
define( 'WPP_SELF'          , __FILE__ );
define( 'WPP_URI'           , plugin_dir_url( __FILE__ ) );
define( 'WPP_DIR'           , plugin_dir_path( __FILE__ ) ); 
define( 'WPP_ASSET_DIR'     , trailingslashit( WPP_DIR )        . 'assets/' );
define( 'WPP_ASSET_URL'     , trailingslashit( WPP_URI )        . 'assets/' );
define( 'WPP_CLASSES_DIR'   , trailingslashit( WPP_DIR )        . 'includes/classes/' );
define( 'WPP_ADDONS_DIR'    , trailingslashit( WPP_DIR )        . 'includes/addons/' );
define( 'WPP_ADDONS_URL'    , trailingslashit( WPP_URI )        . 'includes/addons/' );
define( 'WPP_FUNCTIONS_DIR' , trailingslashit( WPP_DIR )        . 'includes/functions/' );
define( 'WPP_ADMIN_DIR'     , trailingslashit( WPP_DIR )        . 'includes/admin/' );
define( 'WPP_DATA_DIR'      , trailingslashit( WPP_DIR )        . 'includes/data/' );
define( 'WPP_CACHE_DIR'     , trailingslashit( WP_CONTENT_DIR ) . 'cache/wpp-cache/' );
define( 'WPP_CACHE_URL'     , trailingslashit( WP_CONTENT_URL ) . 'cache/wpp-cache/' );
// Define plugin name and plugin admin url
define( 'WPP_PLUGIN_NAME'     , 'WP Performance' );
define( 'WPP_PLUGIN_ADMIN_URL', sanitize_title( WPP_PLUGIN_NAME ) );

require WPP_CLASSES_DIR . 'wpp.php';

// WP CLI
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require WPP_CLASSES_DIR . 'cli.php';
}

WP_Performance::instance()->run();