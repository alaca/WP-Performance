<?php 
/**
* WP Performance Optimizer - WPP Init actions
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use WPP\Option;

/**
 * WPP Init actions
 */
add_action( 'wpp_init', function(){

    // Load language
    load_plugin_textdomain( 
        'wpp', 
        false, 
        plugin_basename( WPP_DIR ) . '/languages' 
    );

    // Schedule cron tasks
    
    // Register schedules
    add_filter( 
        'cron_schedules', 
        'wpp_get_cron_schedules' 
    );

    // Sitemaps cache preload
    if ( Option::get( 'sitemaps_list' ) ) {

        add_action( 
            'wpp_prepare_preload', 
            'wpp_cron_prepare_preload' 
        );

        add_action( 
            'wpp_preload_cache',   
            'wpp_cron_preload_cache' 
        );

        // Prepare preload
        if ( ! wp_next_scheduled( 'wpp_prepare_preload' ) ) {
            wp_schedule_event( 
                time(), 
                'wpp_every_5_minutes', 
                'wpp_prepare_preload' 
            );
        }

        // Preload cache
        if ( ! wp_next_scheduled( 'wpp_preload_cache' ) ) {
            wp_schedule_event( 
                time(), 
                'wpp_every_minute', 
                'wpp_preload_cache' 
            );
        }

    }

    // Database cleanup
    if ( $frequency = Option::get( 'db_cleanup_frequency' ) ) {

        add_action( 
            'wpp_db_cleanup', 
            'wpp_cron_db_cleanup' 
        );

        if ( ! wp_next_scheduled( 'wpp_db_cleanup' ) ) {
            wp_schedule_event( 
                time(),  
                $frequency, 
                'wpp_db_cleanup' 
            );
        }

    }


} );