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

    // Define WP_CACHE
    wpp_define_wp_cache();

    // Copy advanced-cache.php file
    if ( ! copy( 
        WPP_DIR . 'includes/advanced-cache.php', 
        trailingslashit( WP_CONTENT_DIR ) . 'advanced-cache.php' 
    ) ) {
        wpp_log( sprintf( 'Error: cannot copy advanced-cache.php to %s directory. Check writing permissions', WP_CONTENT_DIR ) );
    }

    if ( 'apache' !== wpp_get_server_software() ) return;

    $backup   = trailingslashit( ABSPATH ) . '.htaccess_wpp_backup';
    $htaccess = trailingslashit( ABSPATH ) . '.htaccess';
    
    // Backup htaccess file
    if ( file_exists( $htaccess ) ) {

        if( ! file_exists( $backup ) ) {
            // Create htaccess backup
            if ( copy( $htaccess, $backup ) )
                wpp_log( '.htaccess backup created' );  
                     
        }

    } else {
        // Create htaccess file if it doesn't exist
        if ( ! touch( $htaccess ) ) 
            wpp_log( 'Error while trying to create .htaccess file. You will need to create it manually' );
        
    }
                  
}


/**
 * WPP deactivate
 *
 * @return void
 * @since 1.0.0
 */
function wpp_deactivate() {   

    $backup = trailingslashit( ABSPATH ) . '.htaccess_wpp_backup';
    $htaccess = trailingslashit( ABSPATH ) . '.htaccess'; 

    // Don't remove advanced-cache.php on multisite 
    if ( ! is_multisite() ) {

        // Delete advanced-cache.php file
        if ( file_exists( $advanced_cache = trailingslashit( WP_CONTENT_DIR ) . 'advanced-cache.php' ) )
            unlink( $advanced_cache );

        // Remove WP_CACHE constant
        wpp_define_wp_cache( false );

    }

    // Restore htaccess backup
    if ( file_exists( $backup ) ) {
        
        if ( file_exists( $htaccess ) ) {
            copy( $backup, $htaccess );
            wpp_log( '.htaccess backup restored' );   
        }

        unlink( $backup );

    } else {

        // .htaccess may not be present when WP performance is activated and thats why there is no htaccess backup
        // lets do additional check if htaccess file exists so we can clean it up
        if ( file_exists( $htaccess ) ) {

            // Browser cache
            wpp_update_htaccess( 'remove', 'expire' );
            // Gzip compression
            wpp_update_htaccess( 'remove', 'gzip' );
            // Htaccess load cache
            wpp_update_htaccess( 'remove', 'cache' );

        }

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

    // Delete advanced-cache.php file
    if ( file_exists( $advanced_cache = trailingslashit( WP_CONTENT_DIR ) . 'advanced-cache.php' ) ) {
        unlink( $advanced_cache );
        // Remove WP_CACHE constant
        wpp_define_wp_cache( false );
    }
            
    // Remove all options from database
    Option::removeAll();

    // Clear everything from the cache directory
    Cache::clearEverything();

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
        'w3-total-cache/w3-total-cache.php',
        'wp-super-cache/wp-cache.php',
        'wp-fastest-cache/wpFastestCache.php',
        'wp-rocket/wp-rocket.php',
        'litespeed-cache/litespeed-cache.php',
        'comet-cache/comet-cache.php',
        'breeze/breeze.php',
        'super-static-cache/super-static-cache.php',
        'simple-cache/simple-cache.php',
        'gator-cache/gator-cache.php',
        'hyper-cache/plugin.php',
        'hyper-cache-extended/plugin.php',
        'cache-enabler/cache-enabler.php',
        'cachify/cachify.php',
        'wp-speed-of-light/wp-speed-of-light.php',
        'wp-ffpc/wp-ffpc.php',
        'swift-performance-lite/performance.php',
        'swift-performance/performance.php',
        'hummingbird-performance/wp-hummingbird.php',
    ];

    // Minify plugins
    if ( 
        Option::boolval( 'css_minify' ) 
        || Option::boolval( 'js_minify' ) 
        || Option::boolval( 'css_combine' ) 
        || Option::boolval( 'js_combine' ) 
        || apply_filters( 'wpp_minify_html', true )
    ) {
    
        $incompatiblePlugins = array_merge( $incompatiblePlugins, [
            'autoptimize/autoptimize.php',
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
            'fast-velocity-minify/fvm.php',
            'wp-asset-clean-up/wpacu.php'
        ] );
        
    }

    $plugins = apply_filters( 'wpp_incompatible_plugins', $incompatiblePlugins );

    $incompatible = [];

    // Check active plugins
    foreach ( get_option( 'active_plugins' ) as $plugin ) {
        if ( in_array( $plugin, $plugins ) ) {
            $incompatible[] = $plugin;  
            wpp_log( sprintf( 'Incompatible plugin %s', $plugin ) );         
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
        wpp_log( sprintf( 'Incompatible plugin %s deactivated', Input::get( 'plugin' ) ) ); 
        
        $redirect_url = wp_get_referer();
        $url_query    = parse_url( $redirect_url, PHP_URL_QUERY );

        parse_str( $url_query, $args );

        if ( isset( $args[ 'page' ] ) ) {
            /**
            *  Check if plugin name is in referrer URL so we don't redirect to the non-existent admin page after we deactivate the plugin.
            *  This is not 100% accurate, but it will handle well most cases
            */
            if ( stristr( Input::get( 'plugin' ), $args[ 'page' ] ) ) {
                $redirect_url = admin_url();
            }

        }

        wp_safe_redirect( $redirect_url );

    }

}


/**
 * Define WP_CACHE constant
 *
 * @param boolean $cache
 * @return void
 * @since 1.1.4
 */
function wpp_define_wp_cache( $cache = true ) {

    $wp_config = trailingslashit( ABSPATH ) . 'wp-config.php';

    if ( file_exists( $wp_config ) && is_readable( $wp_config ) ) {

        $found = false;
        $code  = file( $wp_config );

        foreach( $code as $i => $line ) {

            if ( strstr( $line, 'WP_CACHE' ) ) {

                $found = true;

                $expression =  ( $cache ) 
                    ? 'define( "WP_CACHE", true ); // WP Performance' . PHP_EOL
                    : '';

                // Defined on the same line as opening PHP tag?
                if ( strstr( $line, '<?php' ) ) {
                    $expression = '<?php ' . $expression;
                }

                $code[ $i ] = $expression;

                break;
            }

        }


        if ( ! $found ) {

            $expression =  ( $cache ) 
                ? 'define( "WP_CACHE", true ); // WP Performance' . PHP_EOL
                : '';

            $code[ 0 ] = '<?php' . PHP_EOL . $expression;

        }

        file_put_contents( $wp_config, $code );

    }



}