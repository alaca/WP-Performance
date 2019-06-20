<?php 
/**
* WP Performance Optimizer - Frontend actions
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use WPP\Cache;

add_action( 'wpp_frontend_init', function(){

    add_action( 'init', function(){

        // Check if user is logged in
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            wpp_add_top_menu_item();
        } 

        // Cleanup header
        if ( apply_filters( 'wpp_cleanup_header', true ) ) {
            wpp_cleanup_header();
        }

        // Referesh page cache on POST request
        if ( isset( $_POST ) ) {

            $page = Cache::getFileName();

            if ( file_exists( $page ) ) {
                unlink( $page );
            }
        }

    });
          
    // Hook up
    add_action( 'wp', function() {

        if ( apply_filters( 'wpp_parse_template', true ) ) {
            is_404() || ob_start( [ 'WPP\Parser', 'init' ] );
        }
                
    } );

} );