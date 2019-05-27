<?php 
/**
* WP Performance Optimizer - Settings helpers
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use WPP\Cache;
use WPP\File;
use WPP\Input;
use WPP\Option;

/**
 * Export settings file for download
 *
 * @return void
 * @since 1.0.0
 */
function wpp_export_settings_file() {

    header( 'Content-disposition: attachment; filename=' . site_url() . '.json' );
    header( 'Content-type: application/json' );
    
    $options = wpp_get_options();
    
    $data = [];
    
    foreach ( $options as $option_name => $option_value ) {
        $name = str_replace( wpp_get_prefix(), '', $option_name );
        $data[ $name ] = Option::get( $name );
    }

    wpp_log( 'Settings exported' ); 
        
    exit( json_encode( $data ) );

}


/**
 * Import settings
 *
 * @param array $file
 * @return void
 * @since 1.0.0
 */
function wpp_import_settings( $file ) {

    if ( 
        $file[ 'wpp_import_settings' ][ 'error' ] == 0 
        && file_exists( $file[ 'wpp_import_settings' ][ 'tmp_name' ] ) 
    ) {

        if ( $file[ 'wpp_import_settings' ][ 'type' ] !== 'application/json' ) {

            wpp_notify( 'Invalid settings file', 'warning' );

        } else {

            $data = json_decode( file_get_contents( $file[ 'wpp_import_settings' ][ 'tmp_name' ] ), true );

            if ( ! empty( $data ) ) {
                
                $options      = wpp_get_options();
                $list_options = wpp_get_list_options();

                foreach( $data as $option => $value ) {

                    if ( array_key_exists( wpp_get_prefix( $option ), $options ) ) {

                        if ( ! in_array( $option, $list_options ) ) {
                            Option::update( $option, $value );
                        }
                    }
                    
                }

                wpp_log( 'Settings imported' ); 

                Cache::clear();

            }

        }
            
    }

}



/**
 * Save plugin settings
 *
 * @param boolean $notify
 * @return void
 * @since 1.0.0
 */
function wpp_save_settings( $notify = true ) {
    
    // Cache
    Option::update( 'cache',                 Input::post( 'cache', 'boolean' ) );
    Option::update( 'cache_time',            Input::post( 'cache_time', 'number_int' ) );
    Option::update( 'cache_length',          Input::post( 'cache_length', 'number_int' ) );
    Option::update( 'update_clear',          Input::post( 'update_clear', 'boolean' ) );
    Option::update( 'save_clear',            Input::post( 'save_clear', 'boolean' ) );
    Option::update( 'delete_clear',          Input::post( 'delete_clear', 'boolean' ) );
    Option::update( 'mobile_cache',          Input::post( 'mobile_cache', 'boolean' ) );
    Option::update( 'cache_url_exclude',     Input::post( 'cache_url_exclude', 'string', FILTER_REQUIRE_ARRAY ) );
    Option::update( 'browser_cache',         Input::post( 'browser_cache', 'boolean' ) );
    Option::update( 'gzip_compression',      Input::post( 'gzip_compression', 'boolean' ) );
    Option::update( 'sitemaps_list',         Input::post( 'sitemaps_list', 'string', FILTER_REQUIRE_ARRAY ) );
    Option::update( 'user_agents_exclude',   Input::post( 'user_agents_exclude', 'string', FILTER_REQUIRE_ARRAY ) );
    Option::update( 'search_bots_exclude',   Input::post( 'search_bots_exclude', 'boolean' ) );

    // CSS
    Option::update( 'css_minify',            Input::post( 'css_minify', 'string', FILTER_REQUIRE_ARRAY ) );
    Option::update( 'css_minify_inline',     Input::post( 'css_minify_inline', 'boolean' ) );
    Option::update( 'css_combine',           Input::post( 'css_combine', 'string', FILTER_REQUIRE_ARRAY ) );
    Option::update( 'css_inline',            Input::post( 'css_inline', 'string', FILTER_REQUIRE_ARRAY ) );
    Option::update( 'css_disable',           Input::post( 'css_disable', 'string', FILTER_REQUIRE_ARRAY ) );
    Option::update( 'css_disable_position',  Input::post( 'css_disable_position', 'string', FILTER_REQUIRE_ARRAY ) );
    Option::update( 'css_disable_selected',  Input::post( 'css_disable_selected', 'string', FILTER_REQUIRE_ARRAY ) );
    Option::update( 'css_disable_except',    Input::post( 'css_disable_except', 'string', FILTER_REQUIRE_ARRAY ) );
    Option::update( 'css_defer',             Input::post( 'css_defer', 'boolean' ) );
    Option::update( 'css_prefetch',          Input::post( 'css_prefetch', 'string', FILTER_REQUIRE_ARRAY ) );
    Option::update( 'css_preconnect',        Input::post( 'css_preconnect', 'string', FILTER_REQUIRE_ARRAY ) );
    Option::update( 'css_combine_fonts',     Input::post( 'css_combine_fonts', 'boolean' ) );
    Option::update( 'css_url_exclude',       Input::post( 'css_url_exclude', 'string', FILTER_REQUIRE_ARRAY ) );
    Option::update( 'css_custom_path_def',   stripslashes( Input::post( 'css_custom_path_def' ) ) );
    Option::update( 'css_disable_loggedin',  Input::post( 'css_disable_loggedin', 'boolean' ) );  

    // JavaScript
    Option::update( 'js_minify',              Input::post( 'js_minify', 'string', FILTER_REQUIRE_ARRAY ) );
    Option::update( 'js_minify_inline',       Input::post( 'js_minify_inline', 'boolean' ) );
    Option::update( 'js_combine',             Input::post( 'js_combine', 'string', FILTER_REQUIRE_ARRAY ) );
    Option::update( 'js_inline',              Input::post( 'js_inline', 'string', FILTER_REQUIRE_ARRAY ) );
    Option::update( 'js_defer',               Input::post( 'js_defer', 'boolean' ) );   
    Option::update( 'js_prefetch',            Input::post( 'js_prefetch', 'string', FILTER_REQUIRE_ARRAY ) );
    Option::update( 'js_preconnect',          Input::post( 'js_preconnect', 'string', FILTER_REQUIRE_ARRAY ) );            
    Option::update( 'js_url_exclude',         Input::post( 'js_url_exclude', 'string', FILTER_REQUIRE_ARRAY ) );
    Option::update( 'js_disable',             Input::post( 'js_disable', 'string', FILTER_REQUIRE_ARRAY ) );
    Option::update( 'js_disable_position',    Input::post( 'js_disable_position', 'string', FILTER_REQUIRE_ARRAY ) );
    Option::update( 'js_disable_selected',    Input::post( 'js_disable_selected', 'string', FILTER_REQUIRE_ARRAY ) );
    Option::update( 'js_disable_except',      Input::post( 'js_disable_except', 'string', FILTER_REQUIRE_ARRAY ) );
    Option::update( 'js_disable_loggedin',    Input::post( 'js_disable_loggedin', 'boolean' ) );  

    // Images
    Option::update( 'images_resp',           Input::post( 'images_resp', 'boolean' ) );
    Option::update( 'images_force',          Input::post( 'images_force', 'boolean' ) );
    Option::update( 'images_lazy',           Input::post( 'images_lazy', 'boolean' ) );
    Option::update( 'disable_lazy_mobile',   Input::post( 'disable_lazy_mobile', 'boolean' ) );
    Option::update( 'images_containers_ids', Input::post( 'images_containers_ids', 'string', FILTER_REQUIRE_ARRAY  ) );
    Option::update( 'images_exclude',        Input::post( 'images_exclude', 'string', FILTER_REQUIRE_ARRAY ) );
    Option::update( 'image_url_exclude',     Input::post( 'image_url_exclude', 'string', FILTER_REQUIRE_ARRAY ) );

    // Settings
    Option::update( 'enable_log',            Input::post( 'enable_log', 'boolean' ) );

    // CDN
    Option::update( 'cdn',                   Input::post( 'cdn', 'boolean' ) );
    Option::update( 'cdn_hostname',          Input::post( 'cdn_hostname', 'url' ) );
    Option::update( 'cdn_exclude',           Input::post( 'cdn_exclude', 'string', FILTER_REQUIRE_ARRAY ) );

    // Database
    Option::update( 'db_cleanup_transients', Input::post( 'db_cleanup_transients', 'boolean' ) );
    Option::update( 'db_cleanup_revisions',  Input::post( 'db_cleanup_revisions', 'boolean' ) );
    Option::update( 'db_cleanup_spam',       Input::post( 'db_cleanup_spam', 'boolean' ) );
    Option::update( 'db_cleanup_trash',      Input::post( 'db_cleanup_trash', 'boolean' ) );
    Option::update( 'db_cleanup_cron',       Input::post( 'db_cleanup_cron', 'boolean' ) );
    Option::update( 'db_cleanup_autodrafts', Input::post( 'db_cleanup_autodrafts', 'boolean' ) );

    // Cleanup schedule
    $frequency = Input::post( 'automatic_cleanup_frequency' );

    if ( Option::get( 'db_cleanup_frequency' ) != $frequency ) {
        
        Option::update( 'db_cleanup_frequency', $frequency );

        $schedules = wpp_get_cron_schedules();

        if ( array_key_exists( $frequency, $schedules ) ) {
            Option::update( 'db_cleanup_next', ( time() + $schedules[ $frequency ][ 'interval' ] ) );
        } else {
            Option::remove( 'db_cleanup_next' );
        }
        
    }

    // Update htaccess
    if ( 'apache' === wpp_get_server_software() ) {

        // Browser cache
        wpp_update_htaccess( Input::post( 'browser_cache', 'boolean'  ), 'expire' );

        // Gzip compression
        wpp_update_htaccess( Input::post( 'gzip_compression', 'boolean' ), 'gzip' );

        // Htaccess load cache
        if ( ! is_multisite() ) {
            wpp_update_htaccess( Input::post( 'cache', 'boolean'  ), 'cache' );
        }

    }
    

    // Save configuration settings
    $settings = array_diff_key( $_POST, [
        'wpp-tab'           => true, 
        'wpp-nonce'         => true, 
        '_wp_http_referer'  => true, 
        'wpp-save-settings' => true
    ] );

    $timestamp = time();

    File::save( WPP_CACHE_DIR . $timestamp . '.settings.json', json_encode( $settings ) );

    Option::update( 'current_settings', $timestamp );

    // Site settings
    $exclude_urls = Input::post( 'cache_url_exclude', 'string', FILTER_REQUIRE_ARRAY );

    if ( Input::post( 'search_bots_exclude' ) ) {
        $exclude_urls = array_merge( $exclude_urls, wpp_get_search_engines() );
    }

    $settings = [
        'cache'        => boolval( Input::post( 'cache' ) ),
        'disable'      => Option::boolval( 'wpp_disable' ),
        'mobile_cache' => boolval( Input::post( 'mobile_cache' ) ),
        'expire'       => time() + intval( Input::post( 'cache_time', 'number_int' ) * Input::post( 'cache_length', 'number_int' )  ),
        'exclude'      => apply_filters( 'wpp_exclude_urls', $exclude_urls ),
        'permalinks'   => boolval( get_option( 'permalink_structure' ) )
    ];

    File::saveSiteSettings( $settings );
    

    /**
     * Save settings hook
     * @since 1.1.0
     */
    do_action( 'wpp-save-settings' );

    // Clear cache
    if ( Input::post( 'save_clear', 'boolean' ) ) {
        Cache::clear();                
    }
 
    if ( $notify )  wpp_notify( 'Settings saved' );

}


/**
 * Load saved settings from file
 *
 * @param string $filename
 * @param boolean $notify
 * @return void
 * @since 1.0.0
 */
function wpp_load_settings( $filename, $notify = true ) {

    if ( file_exists( $file = WPP_CACHE_DIR . basename( $filename ) . '.settings.json' ) ) {

        $settings = File::getJson( $file );

        $special = [
            'css_minify',
            'css_combine',
            'css_inline',
            'css_disable',
            'css_disable_position',
            'js_minify',
            'js_combine',
            'js_inline',
            'js_disable',
            'js_disable_position'
        ];

        foreach( $special as $name ) {
            if ( ! array_key_exists( $name, $settings ) ) {
                Option::remove( $name );
            }
        }

        foreach ( $settings as $setting => $value ) {

            $action = ! empty( $value ) ? 'add' : 'remove';

            switch ( $setting ) {

                case 'automatic_cleanup_frequency':

                     Option::update( 'db_cleanup_frequency', $value );

                    break;
                case 'browser_cache':

                    if ( 'apache' === wpp_get_server_software() ) {
                        wpp_update_htaccess( $action, 'expire' );
                    }

                    break;
                
                case 'gzip_compression':

                    if ( 'apache' === wpp_get_server_software() ) {
                        wpp_update_htaccess( $action, 'gzip' );
                    }

                    break;

                default: 
                    Option::update( $setting, $value );
            }

        }

        Option::update( 'current_settings', $filename ); 

        if ( $notify ) wpp_notify( 'Settings file loaded' );

    } 

}


/**
 * Save page metabox options
 *
 * @param integer $post_id
 * @return void
 * @since 1.0.3
 */
function wpp_save_post_options( $post_id ) {

    foreach( [ 'cache_post_exclude', 'css_post_exclude', 'js_post_exclude', 'image_post_exclude' ] as $option ) {

        $options = Option::get( $option, [] ); 

        if ( Input::post( $option ) ) {

            if ( ! in_array( $post_id, $options ) ) {
                $options[] = $post_id;
            }
            
        }  else {
            $options = array_diff( $options, [ $post_id ] );
        }
    
        Option::update( $option, $options );

    }

}

/**
 * Remove post options added trough metabox
 *
 * @return void
 * @since 1.0.3
 */
function wpp_ajax_remove_post_options() {

    check_ajax_referer( 'wpp-ajax', 'nonce' );

    $options = [
        'js'    => 'js_post_exclude',
        'css'   => 'css_post_exclude',
        'cache' => 'cache_post_exclude',
        'image' => 'image_post_exclude'
    ];

    if ( array_key_exists( Input::post( 'type' ), $options ) ) {

        $option      = $options[ Input::post( 'type' ) ];
        $option_data = Option::get( $option, [] ); 
        $option_data = array_diff( $option_data, [ Input::post( 'id' ) ] );

        Option::update( $option, $option_data );

    }

    exit;

}

/**
 * Get options prefix
 * 
 * @string $name
 * @since 1.0.0
 * @return string
 */
function wpp_get_prefix( $name = '' ) {
    return sprintf( '%s_%s', 'wpp', $name );
}


/**
 * Delete list options
 * Used after clearing the cache
 *
 * @return void
 * @since 1.0.0
 */
function wpp_delete_list_options() {

    $options = wpp_get_list_options();

    foreach ( $options as $option ) {
        Option::remove( $option );
    }

    wpp_log( 'List options deleted' ); 
    
}


/**
 * Get list options
 *
 * @return array
 * @since 1.0.0
 */
function wpp_get_list_options() {

    $defaults = [ 
        'plugin_css_list', 
        'theme_css_list', 
        'plugin_js_list', 
        'theme_js_list', 
        'external_css_list', 
        'external_js_list', 
        'prefetch_css_list', 
        'prefetch_js_list',
        'css_custom_path_def'
    ]; 

    /**
     * Filter options list which will be deleted after clearing the cache
     * @since 1.0.0
     */
    $options = apply_filters( 'wpp-delete-list-options', $defaults );

    return $options;

}


/**
 * Get options
 *
 * @return array
 * @since 1.0.0
 */
function wpp_get_options() {

    $options = [];

    // Get all options
    $result = $GLOBALS[ 'wpdb' ]->get_results( sprintf( 
        'SELECT option_name, option_value FROM %s WHERE option_name LIKE "%s%%"', 
        $GLOBALS['wpdb']->options, 
        wpp_get_prefix() 
    ) );

    foreach( $result as $row ) {
        $options[ $row->option_name ] = $row->option_value;
    }

    return $options;

}


/**
 * Clear files list and cache
 *
 * @param string $list
 * @param boolean $notify
 * @return void
 * @since 1.0.2
 */
function wpp_delete_files_list( $type, $notify = true  ) {

    $type = str_replace( 'javascript', 'js', $type );

    if ( ! in_array( $type, [ 'js', 'css' ] ) ) {
        return false;
    }

    $list_names = [
        sprintf( 'theme_%s_list', $type ), 
        sprintf( 'plugin_%s_list', $type ), 
        sprintf( 'external_%s_list', $type ), 
        sprintf( 'prefetch_%s_list', $type )
    ];

    foreach ( wpp_get_list_options() as $option ) {
        if ( in_array( $option, $list_names ) ) {
            Option::remove( $option );
        }
    }

    wpp_log( sprintf( '%s list files cleared', $type ) );
    
    Cache::clear();       
    
    if ( $notify ) wpp_notify( 'Files list cleared' );
    
}
