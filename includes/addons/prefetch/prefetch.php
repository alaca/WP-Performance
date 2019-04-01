<?php namespace WPP\Addons\Prefetch;

/**
* WP Performance - Dynamic page prefetch add-on
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use WPP\Input;
use WPP\Option;
use WPP\Instance;
use WPP\HtmlDOM;

class Prefetch {

    /**
     * Use Instance trait
     */
    use Instance;

    /**
     * Register Dynamic page prefetch add-on actions
     *
     * @since 1.1.3
     */
    private function __construct() {

        // If add-on is turned on
        if ( Option::boolval( 'prefetch_pages' ) ) {
            // Parse links
            add_action( 'wpp_parsed_content', [ $this, 'parseLinks' ] );
            // Enqueue scripts
            add_action( 'wp_enqueue_scripts', [ $this, 'enqueueScripts' ]);
        }

        // Update options
        add_action( 'wpp-save-settings', [ $this, 'updateOptions' ] );

        // Add Prefetch admin options
        add_action( 'admin_init', function() {

            // Display addon section
            add_action( 'wpp-display-addons', function() {
                include trailingslashit( __DIR__ ) . 'views/addon.php';
            } );

        } );

    }

    /**
     * Update options
     * Turn Dynamic prefetch add-on on or off
     *
     * @return void
     * @since 1.1.3
     */
    public function updateOptions() {
        Option::update( 'prefetch_pages',  Input::post( 'prefetch_pages', 'boolean' ) );
    }


    /**
     * Parse links
     * Add data-prefetch attribute to inbound links
     *
     * @param HtmlDOM $html
     * @return string
     * @since 1.1.3
     */
    public function parseLinks( $html ) {

        $links = $html->find( 'a' );

        foreach( $links as $link ) {

            if ( 
                $link->href 
                && strpos( $link->href, '/' ) === 0
                || stristr( $link->href, site_url() )
            ) {
                $link->{ 'data-prefetch' } = 'true';
            }

        }

        return $html;

    }


    /**
     * Enqueue script
     *
     * @return void
     * @since 1.1.3
     */
    public function enqueueScripts() {

        wp_enqueue_script( 'wpp-prefetch', WPP_ADDONS_URL . 'prefetch/assets/prefetch.js', [], WPP_VERSION, true );

        // Defer
        add_filter( 'script_loader_tag', function( $tag, $handle, $src ){

            if ( $handle != 'wpp-prefetch' ) {
                return $tag;
            }
        
            return str_replace( '<script', '<script defer', $tag );

        }, 10, 3 );
    }


}