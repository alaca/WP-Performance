<?php namespace WPP\Addons\Cloudflare;

/**
* WP Performance - Cloudflare add-on
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use WPP\UI;
use WPP\Input;
use WPP\Option;
use WPP\Instance;

class Cloudflare {

    /**
     * Use Instance trait
     */
    use Instance;

    /**
     * Register Cloudflare add-on actions
     *
     * @since 1.1.0
     */
    private function __construct() {

        // Save Cloudflare settings
        add_action( 'init',                       [ $this, 'saveCloudflareSettings' ] );
        // Update settings option
        add_action( 'wpp-save-settings',          [ $this, 'saveSettings' ] );
        // Add ajax purge cache action
        add_action( 'wp_ajax_wpp_clear_cf_cache', [ $this, 'ajaxPurgeCache' ] );

        // Display menu items and option in add-on section
        add_action( 'admin_init', function() {
            
            add_action( 'wpp-display-addons', function() {

                include trailingslashit( __DIR__ ) . 'views/addon.php';

            } );

            // If Add-on is
            if ( Option::boolval( 'cf_enabled' ) ) {

                // Add page to WPP UI
                UI::addPage( 'cloudflare', 'Cloudflare',  trailingslashit( __DIR__ ) . 'views/admin.php' );

                // Add topbar links
                UI::addLink( 'cloudflare', 'Cloudflare' );
                UI::addLink( 'cloudflare_clear_cache', __( 'Clear Cloudflare cache', 'wpp' ), 'wpp_clear_cf_cache' );

            }

        } );

    }

    /**
     * Save option data
     * Turn Cloudflare add-on on or off
     *
     * @return void
     * @since 1.1.0
     */
    public function saveSettings() {
        Option::update( 'cf_enabled', Input::post( 'cf_enabled', 'boolean' ) );
    }

    /**
     * Save Cloudflare settings
     *
     * @return void
     * @since 1.1.0
     */
    public function saveCloudflareSettings() {

        if ( 
            ! Input::post( 'wpp-cf-save-settings' ) 
            || ! wp_verify_nonce( Input::post( 'wpp-nonce' ), 'save-settings' ) 
        ) return false;

        // Check if API credentials are set
        if ( 
            ! Input::post( 'cf_api_key' ) 
            || ! Input::post( 'cf_email' )  
            || ! Input::post( 'cf_zone_id' )
        ) {
            return wpp_notify( __( 'Cloudflare API credentials not set', 'wpp' ), 'error' );
        }

        // API Credentials
        Option::update( 'cf_api_key',   Input::post( 'cf_api_key' ) );
        Option::update( 'cf_email',     Input::post( 'cf_email' ) );
        Option::update( 'cf_zone_id',   Input::post( 'cf_zone_id' ) );

        // Cloudflare API instance
        $API = Cloudflare_API::instance();

        // Get zone details
        $response = $API->getZoneDetails();

        if ( is_wp_error( $response ) ) {

            return wpp_notify( __( ' Cloudflare cannot get zone details. Check your Cloudflare credentials', 'wpp' ), 'error' );

        } else {

            $data = json_decode( $response[ 'body' ] );

            if ( $data->result->status == 'deactivated' ) {
                return wpp_notify( sprintf( __( ' Cloudflare zone %s is not activated. Please go to your Cloudflare dashboard and activate it.', 'wpp' ), $data->result->name ), 'error' );
            }

            if ( $data->result->status == 'pending' ) {
                wpp_notify( sprintf( __( ' Cloudflare zone %s status is pending and these settings won\'t have any effect on your website.', 'wpp' ), $data->result->name ), 'warning' );
            }
            
        }


        // Erros array, used to collect errors
        $errors = [];

        ### API requests ###

        // Set development mode
        $mode = Input::post( 'cf_dev_mode', 'boolean' ) ? 'on' : 'off';

        if ( is_wp_error( $API->setDevelopmentMode( $mode ) ) ) {
            $errors[] =  __( ' Cloudflare development mode not set', 'wpp' );
        } else {
            Option::update( 'cf_dev_mode', Input::post( 'cf_dev_mode', 'boolean' ) );
        }

        // Set cache level
        $cache_level = Input::post( 'cf_cache_level' );

        if ( is_wp_error( $API->setCacheLevel( $cache_level ) ) ) {
            $errors[] =  __( ' Cloudflare cache level not set', 'wpp' );
        } else {
            Option::update( 'cf_cache_level', $cache_level );
        }


        // Set browser cache expiration
        $cache_expire = Input::post( 'cf_browser_expire', 'int' );

        if ( is_wp_error( $API->setBrowserCacheExpiration( $cache_expire ) ) ) {
            $errors[] =  __( ' Cloudflare browser cache expiration not set', 'wpp' );
        } else {
            Option::update( 'cf_browser_expire', $cache_expire );
        }

        
        // Set minify
        $values = [
            'css'  => Input::post( 'cf_minify_css' ) ? 'on' : 'off',
            'js'   => Input::post( 'cf_minify_js' ) ? 'on' : 'off',
            'html' => Input::post( 'cf_minify_html' ) ? 'on' : 'off',
        ];

        if ( is_wp_error( $API->setMinify( $values ) ) ) {
            $errors[] =  __( ' Cloudflare minify not set', 'wpp' );
        } else {
            Option::update( 'cf_minify_css', Input::post( 'cf_minify_css' ) );
            Option::update( 'cf_minify_js', Input::post( 'cf_minify_js' ) );
            Option::update( 'cf_minify_html', Input::post( 'cf_minify_html' ) );
        }


        // Set Rocket loader mode
        $mode = Input::post( 'cf_dev_mode', 'boolean' ) ? 'on' : 'off';

        if ( is_wp_error( $API->setRocketLoaderMode( $mode ) ) ) {
            $errors[] =  __( ' Cloudflare Rocket loader mode not set', 'wpp' );
        } else {
            Option::update( 'cf_rocket_loader', Input::post( 'cf_rocket_loader', 'boolean' ) );
        }


        // Set Brotli mode
        $mode = Input::post( 'cf_brotli', 'boolean' ) ? 'on' : 'off';

        if ( is_wp_error( $API->setBrotliMode( $mode ) ) ) {
            $errors[] =  __( ' Cloudflare Brotli mode not set', 'wpp' );
        } else {
            Option::update( 'cf_brotli', Input::post( 'cf_brotli', 'boolean' ) );
        }


        // Check for errors
        if ( ! empty( $errors ) ) {
            foreach( $errors as $error ) {
                wpp_notify( $error, 'error' );
            }
        } else {
            wpp_notify( __( 'Cloudflare settings saved', 'wpp' ), 'success' );
        }

    }


    /**
     * Purge Cloudflare cache - ajax action
     *
     * @return array
     * @since 1.1.0
     */
    public function ajaxPurgeCache() {

        check_ajax_referer( 'wpp-ajax', 'nonce' );

        $response = Cloudflare_API::instance()->purgeCache(); 
    
        if ( is_wp_error( $response ) ) {
    
            $data = [
                'status'  => 0,
                'message' => __( 'Something went wrong. Cannot purge Cloudflare cache', 'wpp' )
            ];
    
        } else {
            $data = [ 'status'  => 1 ];
        }
    
        wp_send_json( $data );

    }

}