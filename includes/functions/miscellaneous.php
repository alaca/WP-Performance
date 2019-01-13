<?php 
/**
* WP Performance Optimizer - Miscellaneous helpers
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use WPP\DB;
use WPP\Url;
use WPP\File;
use WPP\Input;
use WPP\Cache;
use WPP\Option;


/**
 * Check if array key exists
 *
 * @since 1.0.0   
 * @param string $key
 * @param array $array
 * @param mixed $array_key
 * 
 * @return bool
 */
function wpp_key_exists( $key, $array, $array_key = null ) {

    if ( ! $key ) return false;
    
    if ( ! is_null( $array_key ) ) {

        if ( isset( $array[ $array_key ] ) ) {
            return in_array( $key, array( $array[ $array_key ] ) );
        }

        return false;

    }
    if ( is_array( $array ) ) {
        return array_key_exists( $key, $array );
    }

    return false;
}

/**
* Check if key or array of keys exists in array or string
*
* @since 1.0.0   
* @param mixed $needle
* @param mixed $haystack
*
* @return bool
*/
function wpp_in_array( $needle, $haystack ) {

    if ( is_array( $needle ) ) {

        foreach( $needle as $_needle ) {

            if ( empty( $_needle ) ) continue;

            if ( is_array( $haystack ) ) {

                foreach( $haystack as $_haystack ) {
                    if ( stristr( $_haystack, $_needle ) ) {
                        return true;
                    }
                }

            }

            if ( stristr( $haystack, $_needle ) ) {
                return true;
            }

        }
        
        return false;
    }

    if ( is_array( $haystack ) ) {

        foreach ( $haystack as $_haystack ) {
            if ( stristr( $_haystack, $needle ) ) {
                return true;
            }
        }

        return false;
    }

    if ( empty( $needle ) ) return false;
    
    return stristr( $haystack, $needle );

}


/**
 * Preload home page
 * 
 * @return void
 * @since 1.0.0
 */
function wpp_preload_homepage() {

    $request = wp_remote_get( site_url(), [
        'timeout' => 5
    ] );

    if ( is_wp_error( $request ) ) {
        wpp_log( sprintf( 'Error while trying to preload cache for home page %s', $request->get_error_message() ) );
    }

}


/**
 * Cleanup site header
 *
 * @since 1.0.0
 * @return void
 */
function wpp_cleanup_header() {

    remove_action( 'wp_head', 'wp_generator' );     
    remove_action( 'wp_head', 'wlwmanifest_link' );         
    remove_action( 'wp_head', 'rsd_link' );       
    remove_action( 'wp_head', 'wp_shortlink_wp_head' );
    remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );    
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );

    add_filter( 'the_generator', '__return_false' ); 

}

   

/**
 * Enqueue back-end scripts and styles
 *
 * @return void
 * @since 1.0.0
 */
function wpp_enqueue_backend_assets() {

    // Enqueue scripts and styles
    wp_enqueue_script( 'wpp-confirms', WPP_ASSET_URL . 'confirm.js', [ 'jquery' ] );
    wp_enqueue_script( 'wpp-settings', WPP_ASSET_URL . 'admin.js', [ 'jquery' ] );
    
    wp_localize_script( 'wpp-settings', 'WPP', [
        'path' => WPP_ASSET_URL,
        'site_url' => trailingslashit( site_url() ),
        'admin_url' => trailingslashit( admin_url() ),
        'nonce' => wp_create_nonce( 'wpp-ajax' ),   
        'lang' => [
            'confirm' => __( 'Are you sure?', 'wpp' ),
            'remove'  => __( 'Remove', 'wpp' ),
            'add_url'  => __( 'Add URL', 'wpp' ),
            'disable_everywhere'  => __( 'Disable everywhere', 'wpp' ),
            'disable_selected_url'  => __( 'Disable only on selected URL', 'wpp' ),
            'disable_everywhere_except'  => __( 'Disable everywhere except on selected URL', 'wpp' ),
            'something_went_wrong' => __( 'Something went wrong', 'wpp' ),
            'regenerate_thumbs' => __( 'Regenerating thumbs', 'wpp' ),
            'regenerate_thumbs_info' => __( 'Regenerate thumbnails may take a long time. Do not close your browser.', 'wpp' ),
        ]
    ] );

    wp_enqueue_style( 'wpp-admin-css', WPP_ASSET_URL . 'style.css' );    
    wp_enqueue_style( 'wpp-overlay', WPP_ASSET_URL . 'overlay.css' );   
    wp_enqueue_style( 'wpp-confirm', WPP_ASSET_URL . 'confirm.css' ); 

}


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

    /**
     * Check nonce
     * @since 1.0.6
     */
    check_ajax_referer( 'wpp-ajax', 'nonce' );

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
