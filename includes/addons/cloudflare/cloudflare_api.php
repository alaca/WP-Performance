<?php namespace WPP\Addons\Cloudflare;
/**
* WP Performance Optimizer - Cloudflare API helper
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use WP_Error;
use WPP\API;
use WPP\Option;

class Cloudflare_API extends API {

    private static $instance = null;

	private function __construct() {

        // API url
        $this->api_url   = 'https://api.cloudflare.com/client/v4/zones';

		// Namespace
        $this->namespace = Option::get( 'cf_zone_id' );

        // Set default headers
        $this->headers = [
            'X-Auth-Email' => Option::get( 'cf_email' ),
            'X-Auth-Key'   => Option::get( 'cf_api_key' )
        ];

		// Allowed API request methods
		$this->methods   = [ 'GET', 'PUT', 'PATCH', 'POST' ];
        
	} 

	/**
     * Get the class instance
     *
     * @return Cloudflare class instance
     * @since 1.0.8
     */
	public static function instance() {

		if ( is_null( static::$instance ) ) {
			static::$instance = new self;
		}

		return static::$instance;
    }

    
    /**
     * Get Zone details
     *
     * @see https://api.cloudflare.com/#zone-zone-details
     * 
     * @return WP_error|json
     * @since 1.0.8
     */
    public function getZoneDetails() {
        return $this->GET( '/' );
    }


    /**
     * Set Development mode setting
     *
     * @see https://api.cloudflare.com/#zone-settings-change-development-mode-setting
     * 
     * @return WP_error|array
     * @since 1.0.8
     */
    public function setDevelopmentMode( $mode ) {

        $supported = [ 'off', 'on' ];

        // Check if mode is supported
        if ( ! in_array( $mode, $supported ) ) {

            $error = sprintf( 'Cloudflare Development mode: Unsupported argument value %s. Supported values are ( %s )', $mode, implode( ',', $supported ) );

            wpp_log( $error );

            return new WP_Error( '', $error );

        }

        return $this->PATCH( 'settings/development_mode', json_encode( 
            [ 
                'value' => $mode
            ] 
        ) );

    }


    /**
     * Set Cache level
     * 
     * @see https://api.cloudflare.com/#zone-settings-change-cache-level-setting
     * 
     * @param string $mode
     * @return WP_error|array
     * @since 1.0.8
     */
    public function setCacheLevel( $mode ) {

        $supported = [ 'aggressive', 'basic', 'simplified' ];

        // Check if mode is supported
        if ( ! in_array( $mode, $supported ) ) {

            $error = sprintf( 'Cloudflare Cache level: Unsupported argument value %s. Supported values are ( %s )', $mode, implode( ',', $supported ) );

            wpp_log( $error );

            return new WP_Error( '', $error );

        }

        return $this->PATCH( 'settings/cache_level', json_encode( 
            [ 
                'value' => $mode
            ] 
        ) );


    }

    /**
     * Set Browser Cache Expiration
     * 
     * @see https://api.cloudflare.com/#zone-settings-change-browser-cache-ttl-setting
     * 
     * @param int $value
     * @return WP_error|array
     * @since 1.0.8
     */
    public function setBrowserCacheExpiration( $value ) {

        $supported = [
            0, 30, 60, 300, 1200, 1800, 3600, 7200, 10800, 14400, 18000, 28800, 43200, 57600, 72000, 86400, 172800, 259200, 345600, 432000, 691200, 1382400, 2073600, 2678400, 5356800, 16070400, 31536000
        ];

        // Check if mode is supported
        if ( ! in_array( $value, $supported ) ) {

            $error = sprintf( 'Cloudflare Browser Cache expiration: Unsupported argument value %s. Supported values are ( %s )', $value, implode( ',', $supported ) ) ;

            wpp_log( $error );

            return new WP_Error( '', $error );

        }

        return $this->PATCH( 'settings/browser_cache_ttl', json_encode( 
            [ 
                'value' => $value
            ] 
        ) );

    }


    /**
     * Set Minify settings
     *
     * @see https://api.cloudflare.com/#zone-settings-change-minify-setting
     * 
     * @param array $args
     * @return WP_error|array
     * @since 1.0.8
     */
    public function setMinify( $args ) {

        $defaults = [
            'css'  => 'off',
            'js'   => 'off',
            'html' => 'off'
        ];

        $args = wp_parse_args( $args, $defaults );

        $supported = [ 'off', 'on' ];

        foreach( $args as $option => $value ) {

            // Check if mode is supported
            if ( ! in_array( $value, $supported ) ) {

                $error = sprintf( 'Cloudflare Minify: Unsupported argument value %s. Supported values are ( %s )', $value, implode( ',', $supported ) );

                wpp_log( $error );
    
                return new WP_Error( '', $error );

            }

        }

        return $this->PATCH( 'settings/minify', json_encode( 
            [ 
                'value' => $args
            ] 
        ) );

    }


    /**
     * Set Brotli mode setting
     *
     * @see https://api.cloudflare.com/#zone-settings-change-brotli-setting
     * 
     * @param string $mode
     * @return WP_error|array
     * @since 1.0.8
     */
    public function setBrotliMode( $mode ) {

        $supported = [ 'off', 'on' ];

        // Check if mode is supported
        if ( ! in_array( $mode, $supported ) ) {

            $error = sprintf( 'Cloudflare Brotli: Unsupported argument value %s. Supported values are ( %s )', $mode, implode( ',', $supported ) );

            wpp_log( $error );

            return new WP_Error( '', $error );

        }

        return $this->PATCH( 'settings/brotli', json_encode( 
            [ 
                'value' => $mode
            ] 
        ) );

    }


    /**
     * Set Rocket Loader mode setting
     *
     * @see https://api.cloudflare.com/#zone-settings-change-rocket-loader-setting
     * 
     * @param string $mode
     * @return WP_error|array
     * @since 1.0.8
     */
    public function setRocketLoaderMode( $mode ) {

        $supported = [ 'off', 'on' ];

        // Check if mode is supported
        if ( ! in_array( $mode, $supported ) ) {

            $error = sprintf( 'Cloudflare Rocket Loader: Unsupported argument value %s. Supported values are ( %s )', $mode, implode( ',', $supported ) );

            wpp_log( $error );

            return new WP_Error( '', $error );

        }

        return $this->PATCH( 'settings/rocket_loader', json_encode( 
            [ 
                'value' => $mode
            ] 
        ) );

    }


    /**
     * Purge Cache
     *
     * @see https://api.cloudflare.com/#zone-purge-individual-files-by-url-and-cache-tags
     * 
     * @return WP_error|array
     * @since 1.0.8
     */
    public function purgeCache() {

        return $this->POST( 'purge_cache', json_encode( 
            [ 
                'purge_everything' => true 
            ] 
        ) );

    }

    /**
     * Purge custom URL cache
     *
     * @see https://api.cloudflare.com/#zone-purge-individual-files-by-url-and-cache-tags
     * 
     * @param array $files
     * 
     * @return WP_error|array
     * @since 1.1.7
     */
    public function purgeCacheCustomUrl( $files ) {

        return $this->POST( 'purge_cache', json_encode( 
            [ 
                'files' => array_values( $files ) 
            ] 
        ) );

    }

}