<?php 
/**
* WP Performance Optimizer - Plugin helpers
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use WPP\Cache;
use WPP\Input;
use WPP\Option;


/**
 * WPP activate
 *
 * @return void
 * @since 1.0.0
 */
function wpp_activate() {

    if ( 'apache' !== wpp_get_server_software() ) return;

    $htaccess = trailingslashit( ABSPATH ) . '.htaccess';

    // Backup htaccess file
    if ( file_exists( $htaccess ) ) {
        if( ! file_exists( $backup = WPP_DATA_DIR . 'backup/htaccess.backup' ) ) {
            copy( $htaccess, $backup );    
            wpp_log( '.htaccess backup created', 'notice' );            
        }
    } else {
        // Create htaccess file if it doesn't exist
        if ( ! touch( $htaccess ) ) {
            wpp_log( 'Error while trying to create .htaccess file. You will need to create it manually' );
        }
    }
                  
}


/**
 * WPP deactivate
 *
 * @return void
 * @since 1.0.0
 */
function wpp_deactivate() {   
        
    // Restore htaccess backup
    if ( file_exists( $original = WPP_DATA_DIR . 'backup/htaccess.backup' ) ) {
        
        if ( file_exists( $current = trailingslashit( ABSPATH ) . '.htaccess' ) ) {
            copy( $original, $current );
            wpp_log( '.htaccess backup restored', 'notice' );   
        }

        unlink( $original );
    }
    
    // Clear cron tasks
    wp_clear_scheduled_hook( 'wpp_prepare_preload' );
    wp_clear_scheduled_hook( 'wpp_preload_cache' );
    wp_clear_scheduled_hook( 'wpp_db_cleanup' );

    // Clear the cache
    Cache::clear( false );

}


/**
 * WPP uninstall
 *
 * @return void
 * @since 1.0.0
 */
function wpp_uninstall() {   

    $GLOBALS['wpdb']->query( 
        sprintf( 
            'DELETE FROM %s WHERE option_name LIKE "%s%%"', 
            $GLOBALS['wpdb']->options,
            wpp_get_prefix()
        ) 
    );

}


/**
 * Compatibility check
 *
 * @since 1.0.0
 * @return void
 */
function wpp_compatibility_check() { 

    // Cache plugins
    $incompatiblePlugins = [
        'abovethefold.php',
        'w3-total-cache/w3-total-cache.php',
        'wp-super-cache/wp-cache.php',
        'wp-fastest-cache/wpFastestCache.php',
        'wp-rocket/wp-rocket.php',
        'litespeed-cache/litespeed-cache.php',
        'comet-cache/comet-cache.php',
        'breeze/breeze.php',
        'super-static-cache/super-static-cache.php',
        'simple-cache/simple-cache.php',
        'autoptimize/autoptimize.php',
        'gator-cache/gator-cache.php',
        'hyper-cache/plugin.php',
        'hyper-cache-extended/plugin.php',
        'cache-enabler/cache-enabler.php',
        'vendi-cache/vendi-cache.php',
        'cachify/cachify.php',
        'wp-speed-of-light/wp-speed-of-light.php',
        'wp-ffpc/wp-ffpc.php',
        'swift-performance-lite/performance.php',
        'swift-performance-pro/performance.php',
    ];

    // Minify plugins
    if ( 
        Option::boolval( 'css_minify' ) 
        || Option::boolval( 'js_minify' ) 
        || apply_filters( 'wpp_minify_html', false )
    ) {
    
        $incompatiblePlugins = array_merge( $incompatiblePlugins, [
            'bwp-minify/bwp-minify.php',
            'wp-minify-fix/wp-minify.php',
            'minqueue/plugin.php',
            'optimize-javascript/pfeiffers-merge-scripts.php',
            'minify-html-markup/minify-html.php',
            'scripts-to-footerphp/scripts-to-footer.php',
            'footer-javascript/footer-javascript.php',
            'combine-js/combine-js.php',
            'js-css-script-optimizer/js-css-script-optimizer.php',
            'scripts-gzip/scripts_gzip.php',
            'dependency-minification/dependency-minification.php',
            'css-optimizer/bpminifycss.php',
            'merge-minify-refresh/merge-minify-refresh.php',
            'async-js-and-css/asyncJSandCSS.php',
            'wp-js/wp-js.php',
            'fast-velocity-minify/fvm.php'
        ] );
        
    }

    $plugins = apply_filters( 'wpp_incompatible_plugins', $incompatiblePlugins );

    $incompatible = [];

    // Check active plugins
    foreach ( get_option( 'active_plugins' ) as $plugin ) {
        if ( in_array( $plugin, $plugins ) ) {
            $incompatible[] = $plugin;  
            wpp_log( sprintf( 'Incompatible plugin %s', $plugin ), 'warning' );         
        }
    }
    
    // Print notices
    if ( ! empty( $incompatible ) ) {

        $notice = '<div class="wpp-deactivate-notice"><strong>' . __( 'The following plugins are not compatible with', 'wpp' ) . ' ' . WPP_PLUGIN_NAME . '</strong></div>';
        
        foreach ( $incompatible as $plugin ) {

            $data  = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );
            
            $notice .= '<div class="wpp-deactivate-notice">' . $data[ 'Name' ];
            $notice .= '<a class="button wpp-button-deactivate" href="' . wp_nonce_url( admin_url( 'admin-post.php?action=deactivate_plugin&plugin=' . urlencode( $plugin ) ), 'deactivate') . '">' . __( 'Deactivate', 'wpp' ) . '</a>';
            $notice .= '</div>';
        }

        wpp_notify( $notice, 'error', false );

    }

}


/**
 * Deactivate incompatible plugin
 *
 * @return void
 * @since 1.0.0
 */
function wpp_deactivate_incompatible_plugin() {

    if ( wp_verify_nonce( Input::get( '_wpnonce' ), 'deactivate' ) ) {
        deactivate_plugins( Input::get( 'plugin' ) );
        wpp_log( sprintf( 'Incompatible plugin %s deactivated', Input::get( 'plugin' ) ), 'notice' );    
        wp_safe_redirect( wp_get_referer() );
    }

}


/**
 * Set plugin position
 *
 * @return void
 * @since 1.0.0
 */
function wpp_set_plugin_position() {

    $wpp  = plugin_basename( WPP_SELF );
    $list = get_option( 'active_plugins' );

    if ( $key = array_search( $wpp, $list ) ) {
        array_splice( $list, $key, 1 );
        array_unshift( $list, $wpp );
        update_option( 'active_plugins', $list );
    }

}