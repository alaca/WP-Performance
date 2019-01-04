<?php 
/**
* WP Performance Optimizer - Resources helpers
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use WPP\Url;
use WPP\Cache;
use WPP\Option;

/**
 * Check if css/js optimization is disabled for logged in users
 *
 * @param string $type
 * @return boolean
 * @since 1.0.0
 */
function wpp_is_optimization_disabled_for( $type ) {

    if ( ! in_array( $type, [ 'js', 'css' ] ) ) {
        return false;
    }

    if ( Option::boolval( $type . '_disable_loggedin' ) ) {
        if ( is_user_logged_in() ) return true;
    }

    return false;

}


/**
 * Check if resource is disabled for current url
 * 
 * @since 1.0.0
 * @return bool
 */
function wpp_is_resource_disabled( $type, $resource ) {

    if ( ! in_array( $type, [ 'js', 'css' ] ) ) {
        return false;
    }

    $disabled_positions = Option::get( $type . '_disable_position', [] );

    // File is disabled everywhere
    if ( wpp_key_exists( 'everywhere', $disabled_positions, $resource ) ) {
        return true;
    }
 

    // File is disabled only for selected urls
    if ( wpp_key_exists( 'selected', $disabled_positions, $resource ) ) {

        foreach( Option::get( $type . '_disable_selected', [] ) as $file => $urls ) {

            if ( $file == $resource ) {
    
                foreach( $urls as $url ) {

                    $url = trailingslashit( wpp_url_replace_wildcards( $url ) );

                    // Try simple match first
                    if ( $url == Url::current() ) {
                        return true;
                    }

                    if ( stristr( Url::current(), $url ) ) {
                        return true;
                    }

                    preg_match( '#^' . $url . '$#', Url::current(), $match );

                    if ( isset( $match[0] ) ) {
                        return true;
                    }
                            
                }
    
            }
    
        }

        return false;

    }

    // File is disabled everywhere except for current URL
    if ( wpp_key_exists( 'except', $disabled_positions, $resource ) ) {

        $found = false;

        foreach( Option::get( $type . '_disable_except', [] ) as $file => $urls ) {

            if ( $file == $resource ) {

                $found = true;

                foreach( $urls as $url ) {

                    $url = trailingslashit( wpp_url_replace_wildcards( $url ) );

                    // Try simple match first
                    if ( $url == Url::current() ) {
                        return false;
                    }

                    if ( stristr( Url::current(), $url ) ) {
                        return false;
                    }

                    preg_match( '#^' . $url . '$#', trailingslashit( Url::current() ), $match );

                    if ( isset( $match[0] ) ) {
                        return false;
                    }

                }

            }

        }

        // If file is found on page
        if ( $found ) {
            return true;
        }

    }

    return false;

}


/**
 * Get critical CSS path from wpp server
 *
 * @since 1.0.0
 * @return array
 */
function wpp_get_critical_css_path() {

    // Disable plugin
    Option::update( 'wpp_disable', true );

    // Clear the cache
    Cache::clear( false );

    $response = wp_remote_post( 
        'https://www.wp-performance.com/api', [
            'timeout' => 90,
            'body' => [
                'url' => site_url()
            ]
        ]
    );

    if ( is_wp_error( $response ) ) {

        $json = [
            'status' => 0,
            'message' => $response->get_error_message()
        ];

        wpp_log( sprintf( 'Generating critical CSS error %s', $response->get_error_message() ) ); 

    } else {

        $json = [
            'status' => 1,
            'data' => wp_remote_retrieve_body( $response )
        ];

        wpp_log( 'Critical CSS generated', 'notice' ); 

    }

    // Re-enable the plugin
    Option::update( 'wpp_disable', false );

    wp_send_json( $json );

}
