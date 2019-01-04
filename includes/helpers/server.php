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



/**
 * Clear Varnish cache for entire domain
 *
 * @return void
 * @since 1.0.0
 */
function wpp_varnish_clear_domain() {
    return wpp_varnish_http_purge( site_url(), true );
}


/**
 * Clear url from Varnish cache
 *
 * @param string $url
 * @param boolean $regex
 * @return void
 * @since 1.0.0
 */
function wpp_varnish_http_purge( $url, $regex = false ) {

    $data        = parse_url( $url );
    $custom_host = Option::get( 'varnish_custom_host' );

    if ( filter_var( $custom_host, FILTER_VALIDATE_URL ) ) {

        $custom_data = parse_url( $custom_host );
        $host = sprintf( '%s://%s', $custom_data[ 'scheme' ], $custom_data[ 'host' ] );

    } else {
        $host = sprintf( '%s://%s', $data[ 'scheme' ], $data[ 'host' ] );
    }

    $purge_url = $host . ( isset( $data[ 'path' ] ) ? $data[ 'path' ] : '' ) . ( $regex ? '.*' : '' );

    wpp_log( $purge_url, 'notice' );

    $request = wp_remote_request( $purge_url, 
        [ 
            'method'      => 'PURGE', 
            'blocking'    => false,
			'redirection' => 0,
            'headers' => [ 
                'host'           => parse_url( site_url(), PHP_URL_HOST ), 
                'X-Purge-Method' => $regex ? 'regex' : 'exact'
            ]
        ]
    );

    if ( is_wp_error( $request ) ) {
        wpp_log( sprintf( 'Varnish error: %s', $request->get_error_message() ) );
    } else {
        wpp_log( 'Varnish cache cleared', 'notice' );
    }
    
}