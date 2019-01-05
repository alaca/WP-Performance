<?php 
/**
* WP Performance Optimizer - Varnish helpers
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/


use WPP\Option;

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