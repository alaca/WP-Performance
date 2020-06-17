<?php
/**
 * WP Performance Optimizer - Frontend actions
 *
 * @author Ante Laca <ante.laca@gmail.com>
 * @package WPP
 */

use WPP\Url;
use WPP\Cache;
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


// Save cache file
add_filter( 'wpp_template', function( $template, $is_amp, $time ) {

    // Should we cache this page ?
    if (
        Option::boolval( 'cache' )
        && empty( $_POST )
        && ! is_user_logged_in()
    ) {

        /**
         * Filter excluded urls
         *
         * @since 1.0.0
         */
        $excluded = apply_filters( 'wpp_exclude_urls', Option::get( 'cache_url_exclude', [] ) );

        // Check if page is excluded
        if ( ! wpp_is_url_excluded( Url::current(), $excluded ) ) {

            if ( ! $is_amp ) {

                $template .= sprintf(
                    '<!-- ' . __( 'Cache file was created in %s seconds on %s at %s', 'wpp' ) . ' -->',
                    number_format( ( microtime( true ) - $time ), 2 ),
                    date( get_option( 'date_format' ) ),
                    date( get_option( 'time_format' ) )
                );

            }

            Cache::save( $template, $is_amp );

        }

    }

}, 10, 3);