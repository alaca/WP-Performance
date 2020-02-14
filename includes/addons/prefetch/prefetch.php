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

        // If add-on is turned on and the cache is on
        if ( Option::boolval( 'prefetch_pages' ) && Option::boolval( 'cache' ) ) {
            // Parse links
            add_filter( 'wpp_parsed_content', [ $this, 'parseLinks' ] );
            // Load add-on scripts in footer
            add_action( 'wp_footer', [ $this, 'loadScript' ] );
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
     * @param HtmlDOM $parsed
     * @return string
     * @since 1.1.3
     */
    public function parseLinks( $html ) {

        $html = new HtmlDOM( $html, false, false );

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
     * Load add-on script
     *
     * @return string
     * @since 1.1.3
     */
    public function loadScript() {

        if ( file_exists( $script = WPP_ADDONS_DIR . 'prefetch/assets/prefetch.js' ) ) {
            printf( '<script data-wpp-addon="prefetch">%s</script>' . PHP_EOL, file_get_contents( $script ) );
        }

    }


}