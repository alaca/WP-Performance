<?php 
/**
* WP Performance Optimizer - Server helpers
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use WPP\File;
use WPP\Input;
use WPP\Option;


/**
 * Get the server software
 *
 * @return string (apache|nginx|unknown)
 * @since 1.0.2
 */
function wpp_get_server_software() {

    // Apache
    if ( preg_match( '#(apache|litespeed|shellrent)#i', Input::server( 'SERVER_SOFTWARE' ) ) ) {
        return 'apache';
    }

    // Nginx
    if ( preg_match( '#(nginx|flywheel)#i', Input::server( 'SERVER_SOFTWARE' ) ) ) {
        return 'nginx';
    }
    
    return 'unknown';

}

/**
 * Get Nginx rewrite rules
 *
 * @return string
 * @since 1.0.2
 */
function wpp_get_nginx_rewrite_rules() {

    $output = '';
    
    // Browser cache
    if ( Option::boolval( 'browser_cache' ) ) {
        $output .= File::get( WPP_DATA_DIR . 'definitions/expire.nginx.txt' );
    }

    // Gzip
    if ( Option::boolval( 'gzip_compression' ) ) {
        $output .= File::get( WPP_DATA_DIR . 'definitions/gzip.nginx.txt' );
    }

    // Cache
    if ( Option::boolval( 'cache' ) && get_option( 'permalink_structure', false ) ) {

        $definitions = File::get( WPP_DATA_DIR . 'definitions/cache.nginx.txt' );
        $definitions = str_replace( '{CACHEDIR}', WPP_CACHE_DIR, $definitions );
        $definitions = str_replace( '{CACHEDIR_BASENAME}', basename( WPP_CACHE_DIR ), $definitions );

        $output .= $definitions;

    }

    return $output;
    
}
