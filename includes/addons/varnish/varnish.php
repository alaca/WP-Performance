<?php namespace WPP\Addons\Varnish;

/**
* WP Performance - Varnish add-on
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use WPP\Input;
use WPP\Option;
use WPP\Instance;

class Varnish {

    /**
     * Use Instance trait
     */
    use Instance;

    /**
     * Register Varnish add-on actions
     *
     * @since 1.1.0
     */
    private function __construct() {

        // Update options
        add_action( 'wpp-save-settings', [ $this, 'updateOptions' ] );

        // Clear domain action
        add_action( 'init', function() {

            // Varnish auto purge cache
            if ( Option::boolval( 'varnish_auto_purge' ) ) {
                add_action( 'wpp-after-cache-delete', [ $this, 'clearDomain' ] );
            }

        } );

        // Add Varnish admin options
        add_action( 'admin_init', function() {

            // Display addon section
            add_action( 'wpp-display-addons', function() {
                include trailingslashit( __DIR__ ) . 'views/addon.php';
            } );

        } );

    }

    /**
     * Update options
     * Turn Cloudflare add-on on or off
     *
     * @return void
     * @since 1.1.0
     */
    public function updateOptions() {
        Option::update( 'varnish_auto_purge',  Input::post( 'varnish_auto_purge', 'boolean' ) );
        Option::update( 'varnish_custom_host', Input::post( 'varnish_custom_host', 'url' ) );
    }


    /**
     * Clear Varnish cache for entire domain
     *
     * @return void
     * @since 1.1.0
     */
    public function clearDomain() {
        return $this->httpPurge( site_url(), true );
    }

    /**
     * Clear url from Varnish cache
     *
     * @param string $url
     * @param boolean $regex
     * @return void
     * @since 1.1.0
     */
    public function httpPurge( $url, $regex = false ) {

        $data        = parse_url( $url );
        $custom_host = Option::get( 'varnish_custom_host' );

        if ( filter_var( $custom_host, FILTER_VALIDATE_URL ) ) {

            $custom_data = parse_url( $custom_host );
            $host = sprintf( '%s://%s', $custom_data[ 'scheme' ], $custom_data[ 'host' ] );

        } else {
            $host = sprintf( '%s://%s', $data[ 'scheme' ], $data[ 'host' ] );
        }

        $purge_url = $host . ( isset( $data[ 'path' ] ) ? $data[ 'path' ] : '' ) . ( $regex ? '.*' : '' );

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
            wpp_log( 'Varnish cache cleared' );
        }
        
    }

}