<?php 
/**
* WP Performance Optimizer - Options helpers
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use WPP\Cache;
use WPP\Option;

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

    wpp_log( 'List options deleted', 'notice' ); 
    
}


/**
 * Get list options
 *
 * @return array
 * @since 1.0.0
 */
function wpp_get_list_options() {

    $defaults = [ 
        'local_css_list', 
        'local_js_list', 
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
        sprintf( 'local_%s_list', $type ), 
        sprintf( 'external_%s_list', $type ), 
        sprintf( 'prefetch_%s_list', $type )
    ];

    foreach ( wpp_get_list_options() as $option ) {
        if ( in_array( $option, $list_names ) ) {
            Option::remove( $option );
        }
    }

    wpp_log( sprintf( '%s list files cleared', $type ), 'notice' );
    
    Cache::clear();       
    
    if ( $notify ) wpp_notify( 'Files list cleared' );
    
}
