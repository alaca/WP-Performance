<?php 
/**
* WP Performance Optimizer - Cron helpers
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use WPP\DB;
use WPP\File;
use WPP\Cache;
use WPP\Option;

/**
 * Get cron schedule intervals
 *
 * @param array $schedules
 * @return array
 * @since 1.0.0
 */
function wpp_get_cron_schedules( $schedules = [] ) {


    $schedules[ 'wpp_daily' ] = [
        'interval' => strtotime( '+ 1 day') - time(),
        'display'  => __( 'Daily', 'wpp' )
    ];

    $schedules[ 'wpp_weekly' ] = [
        'interval' => strtotime( '+ 1 week') - time(),
        'display'  => __( 'Weekly', 'wpp' )
    ];

    $schedules[ 'wpp_monthly' ] = [
        'interval' => strtotime( '+ 1 month') - time(),
        'display'  => __( 'Monthly', 'wpp' )
    ];

    $schedules[ 'wpp_every_minute' ] = [
        'interval' => 60,
        'display'  => __( 'Every minute', 'wpp' )
    ];

    $schedules[ 'wpp_every_5_minutes' ] = [
        'interval' => 60 * 5,
        'display'  => __( 'Every 5 minutes', 'wpp' )
    ];
 
    return $schedules;

}

/**
 * Preload cache
 *
 * @return void
 * @since 1.0.0
 */
function wpp_cron_preload_cache() {

    // Bail if preload file not exists
    if ( ! file_exists( $file = WPP_CACHE_DIR . 'preload.json' ) ) {
        return false;
    }

    $data = File::getJson( $file );

    if ( empty( $data ) ) {
        wpp_log( 'Cache preloading stop - nothing to preload' );
        exit;
    }

    wpp_log( 'Cache preloading start' );

    $max_url = defined( 'WPP_PRELOAD_URL_NUMBER' ) ? WPP_PRELOAD_URL_NUMBER : 5;

    $i = 1;

    foreach( $data as $j => $url ) {

        // Check if cache file already exists
        if ( Cache::exists( $url ) ) {
            unset( $data[ $j ] );
            continue;
        }

        $url = esc_url( $url );

        $request = wp_remote_get( $url, [
            'timeout' => 3
        ] );

        if ( is_wp_error( $request ) ) {
            wpp_log( sprintf( 'Error while trying to preload cache for page %s %s', $url, $request->get_error_message() ) );
        } 
        
        unset( $data[ $j ] );

        if ( $i == $max_url ) break;

        $i++;

    }

    File::saveJson( $file, $data );

}



/**
 * Prepare cache preload
 *
 * @return void
 * @since 1.0.0
 */
function wpp_cron_prepare_preload() {
    
    // Bail if preload file already exists
    if ( file_exists( WPP_CACHE_DIR . 'preload.json' ) ) {
        return false;
    }

    $urls     = [];
    $sitemaps = Option::get( 'sitemaps_list', [] );

    foreach( $sitemaps as $url ) {

        $url = esc_url( $url );

        // Validate sitemap url
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {

            wpp_log( sprintf( 'Invalid sitemap url: %s', $url ) );

            continue;
        }


        // Get the sitemap
        $request = wp_remote_get( $url );
    
        if ( is_wp_error( $request ) ) {

            wpp_log( sprintf( 'Error fetching sitemap url: %s %s', $url, $request->get_error_message() ) );

            return false;
        } 

        $data = wp_remote_retrieve_body( $request );

        if ( empty( $data ) ) {

            wpp_log( sprintf( 'Sitemap %s is empty', $url ) );

            return false;
        }

        // Load XML
        libxml_use_internal_errors( true );

        $xml = simplexml_load_string( $data );
    
        if ( false === $xml ) {

            libxml_clear_errors();

            wpp_log( sprintf( 'Invalid XML in sitemap %s', $url ) );

            return false;
        }


        // Get urls
        $num = count( $xml->url );

        if ( $num > 0 ) {
            for ( $i = 0; $i < $num; $i++ ) {
                $urls[] = strval( $xml->url[ $i ]->loc );
            }
        }
    
    }

    // Save file
    if ( ! empty( $urls ) ) {

        File::saveJson( WPP_CACHE_DIR . 'preload.json', $urls );

        wpp_log( sprintf( 'Collected %s URLs from %s sitemap(s) for cache preloading', count( $urls ), count( $sitemaps ) ) );

    } else {

        wpp_log( 'URLs for cache preloading not found' );

    }


}



/**
 * Clear DB and update next schedule info
 *
 * @return void
 * @since 1.0.0
 */
function wpp_cron_db_cleanup() {

    wpp_log( 'Automatic database cleanup started' );

    // Clear trash
    if ( Option::boolval( 'db_cleanup_trash' ) ) {
        DB::clearTrash();
    }

    // Clear spam
    if ( Option::boolval( 'db_cleanup_spam' ) ) {
        DB::clearSpam();
    }

    // Clear revisions
    if ( Option::boolval( 'db_cleanup_revisions' ) ) {
        DB::clearRevisions();
    }

    // Clear revisions
    if ( Option::boolval( 'db_cleanup_transients' ) ) {
        DB::clearTransients();
    }

    // Clear cron tasks
    if ( Option::boolval( 'db_cleanup_cron' ) ) {
        DB::clearCronTasks();
    }

    // Clear auto drafts
    if ( Option::boolval( 'db_cleanup_autodrafts' ) ) {
        DB::clearAutoDrafts();
    }
    
    $schedules = wpp_get_cron_schedules();
    $frequency = Option::get( 'db_cleanup_frequency' );

    if ( array_key_exists( $frequency, $schedules ) ) {
        Option::update( 'db_cleanup_next', ( time() + $schedules[ $frequency ][ 'interval' ] ) );
    } else {
        Option::remove( 'db_cleanup_next' );
    }

}