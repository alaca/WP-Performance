<?php 
/**
* WP Performance Optimizer - Frontend actions
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use WPP\Cache;
use WPP\Input;
use WPP\Option;

add_action( 'wpp_frontend_init', function() {

    add_action( 'init', function() {
        // Check if user is logged in
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) )
            wpp_add_top_menu_item();
    });

    // Disable emoji
    if ( Option::boolval( 'disable_emoji' ) ) 
        add_action( 'init', 'wpp_disable_emoji' );

    // Disable embeds
    if ( Option::boolval( 'disable_embeds' ) ) 
        add_action( 'init', 'wpp_disable_embeds', 99999 );
          
    // Hook up
    add_action( 'wp', function() {
        // Do not parse template if it's an ajax request
        if ( ! wpp_is_ajax() )
            is_404() || ob_start( [ 'WPP\Parser', 'init' ] ); 
    } );

    // Referesh page cache on POST request
    if ( ! empty( $_POST ) ) {

        if ( file_exists( $page = Cache::getFileName() ) ) {

            unlink( $page );

            foreach( [ '_gz', '_amp' ] as $extension ) {
                if ( file_exists( $page . $extension ) ) 
                    unlink( $page . $extension );
            }

        }
            
    }    

} );