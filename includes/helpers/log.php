<?php 
/**
* WP Performance Optimizer - Log helpers
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use WPP\File;
use WPP\Option;


/**
 * Get site log file path
 *
 * @return void
 * @since 1.0.0
 */
function wpp_get_log_file() {
    return WPP_DATA_DIR . 'logs/log.txt';
}

/**
 * Write log
 *
 * @param string $action
 * @param string $type
 * @return void
 * @since 1.0.0
 */
function wpp_log( $action, $type = 'error' ) {

    if ( ! Option::boolval( 'enable_log' ) ) return;

    $types = [
        'error'   => __( 'Error', 'wpp' ),
        'warning' => __( 'Warning', 'wpp' ),
        'notice'  => __( 'Notice', 'wpp' ),
        'event'   => __( 'Event', 'wpp' )
    ];

    $name = ( array_key_exists( $type, $types ) ) ? $types[ $type ] : $type;

    File::append( wpp_get_log_file(), sprintf( 
        '[%s] %s: %s', 
        date( 'Y-m-d H:i:s' ),
        $name, 
        $action . PHP_EOL
    ) );

}



/**
 * Clear the log file
 *
 * @return void
 * @since 1.0.0
 */
function wpp_clear_log() {
    
    if ( file_exists( $file = wpp_get_log_file() ) ) {
        unlink( $file );
    }

    wpp_notify( 'Log file cleared' );

}


/**
 * Get log content via ajax
 *
 * @return void
 * @since 1.0.0
 */
function wpp_ajax_get_log_content() {

    if ( file_exists( $file = wpp_get_log_file() ) ) {
        echo File::get( $file ); 
    }

    exit;

}
