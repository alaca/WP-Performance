<?php 
/**
* WP Performance Optimizer - Helpers
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use WPP\DB;
use WPP\Url;
use WPP\File;
use WPP\Cache;
use WPP\Input;
use WPP\Image;
use WPP\Option;


/**
 * WPP activate
 *
 * @return void
 * @since 1.0.0
 */
function wpp_activate() {

    // Backup htaccess file
    if ( file_exists( $htaccess = trailingslashit( ABSPATH ) . '.htaccess' ) ) {
        if( ! file_exists( $backup = WPP_DATA_DIR . 'backup/htaccess.backup' ) ) {
            copy( $htaccess, $backup );    
            wpp_log( '.htaccess backup created', 'notice' );            
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
* Print admin notice
*    
* @since 1.0.0   
* @param string $msg
* @param string $type
* @param boolean $preformatted 
*
* @return void
*/
function wpp_notify( $msg, $type = 'success', $preformatted = true ) {

    add_action( 'admin_notices', function() use ( $msg, $type, $preformatted ){
        
        if ( $preformatted ) {
                echo '<div class="notice notice-' . $type . ' is-dismissible wpp-notice"><p><strong>' . __( ucfirst( $type ), 'wpp' ) . '</strong>: ' . __( $msg, 'wpp' ) . '</p></div>';    
        } else {
            echo '<div class="notice notice-' . $type . '"><p>' . $msg . '</p></div>';      
        }
        
    } ); 
    
}


/**
 * Check if array key exists
 *
 * @since 1.0.0   
 * @param string $key
 * @param array $array
 * @param mixed $array_key
 * 
 * @return bool
 */
function wpp_key_exists( $key, $array, $array_key = null ) {

    if ( ! $key ) return false;
    
    if ( ! is_null( $array_key ) ) {

        if ( isset( $array[ $array_key ] ) ) {
            return in_array( $key, array( $array[ $array_key ] ) );
        }

        return false;

    }
    if ( is_array( $array ) ) {
        return array_key_exists( $key, $array );
    }

    return false;
}

/**
* Check if key or array of keys exists in array or string
*
* @since 1.0.0   
* @param mixed $needle
* @param mixed $haystack
*
* @return bool
*/
function wpp_in_array( $needle, $haystack ) {

    if ( is_array( $needle ) ) {

        foreach( $needle as $_needle ) {

            if ( empty( $_needle ) ) continue;

            if ( is_array( $haystack ) ) {

                foreach( $haystack as $_haystack ) {
                    if ( stristr( $_haystack, $_needle ) ) {
                        return true;
                    }
                }

            }

            if ( stristr( $haystack, $_needle ) ) {
                return true;
            }

        }
        
        return false;
    }

    if ( is_array( $haystack ) ) {

        foreach ( $haystack as $_haystack ) {
            if ( stristr( $_haystack, $needle ) ) {
                return true;
            }
        }

        return false;
    }

    if ( empty( $needle ) ) return false;
    
    return stristr( $haystack, $needle );

}

/**
 * Check if resource is disabled for current url
 * 
 * @since 1.0.0
 * @return bool
 */
function wpp_is_resource_disabled( $type, $resource ) {

    if ( ! in_array( $type, [ 'js', 'css' ] ) ) {
        return false;
    }

    $disabled_positions = Option::get( $type . '_disable_position', [] );

    // File is disabled everywhere
    if ( wpp_key_exists( 'everywhere', $disabled_positions, $resource ) ) {
        return true;
    }
 

    // File is disabled only for selected urls
    if ( wpp_key_exists( 'selected', $disabled_positions, $resource ) ) {

        foreach( Option::get( $type . '_disable_selected', [] ) as $file => $urls ) {

            if ( $file == $resource ) {
    
                foreach( $urls as $url ) {

                    $url = trailingslashit( wpp_replace_wildcards( $url ) );

                    // Try simple match first
                    if ( $url == Url::current() ) {
                        return true;
                    }

                    if ( stristr( Url::current(), $url ) ) {
                        return true;
                    }

                    preg_match( '#^' . $url . '$#', Url::current(), $match );

                    if ( isset( $match[0] ) ) {
                        return true;
                    }
                            
                }
    
            }
    
        }

        return false;

    }

    // File is disabled everywhere except for current URL
    if ( wpp_key_exists( 'except', $disabled_positions, $resource ) ) {

        $found = false;

        foreach( Option::get( $type . '_disable_except', [] ) as $file => $urls ) {

            if ( $file == $resource ) {

                $found = true;

                foreach( $urls as $url ) {

                    $url = trailingslashit( wpp_replace_wildcards( $url ) );

                    // Try simple match first
                    if ( $url == Url::current() ) {
                        return false;
                    }

                    if ( stristr( Url::current(), $url ) ) {
                        return false;
                    }

                    preg_match( '#^' . $url . '$#', trailingslashit( Url::current() ), $match );

                    if ( isset( $match[0] ) ) {
                        return false;
                    }

                }

            }

        }

        // If file is found on page
        if ( $found ) {
            return true;
        }

    }

    return false;

}

/**
 * Get clean file name
 * 
 * @since 1.0.0
 * @return string
 */
function wpp_get_file_clean_name( $file ) {
    return sanitize_title( urldecode( $file ) );
}


/**
 * Get file hostname
 * 
 * @since 1.0.0
 * @return string
 */
function wpp_get_file_hostname( $file ) {

    if ( $host = parse_url( $file, PHP_URL_HOST ) ) {
        return '//' .  $host;
    }

    return $file;

}

/**
 * Replace wildcards with regex pattern
 * 
 * @since 1.0.0
 * @return string
 */
function wpp_replace_wildcards( $pattern ) {

    $pattern = preg_quote( $pattern );

    $wildcards = [
        '{any}'     => '[^/]+',
        '{numbers}' => '[0-9]+',
        '{letters}' => '[A-Za-z]+',
        '{all}'     => '.*'
    ];

    return str_replace( array_keys( $wildcards ), array_values( $wildcards ), stripslashes( $pattern ) );

}

/**
 * Check if is ajax call
 * 
 * @since 1.0.0
 * @return bool
 */
function wpp_is_ajax() {
    
    if( ! empty( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) && strtolower( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) == 'xmlhttprequest' ){    
        return true;
    }

    return false;
}

/**
 * Get cache size for given file type extension
 * 
 * @since 1.0.0
 * @param string $type
 * @return int
 */
function wpp_cache_files_size( $type ) {

    $size = 0;

    $files = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator( WPP_CACHE_DIR, \RecursiveDirectoryIterator::SKIP_DOTS ),
        \RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ( $files as $file ) {

        if ( $file->isFile() ) {

            if ( $file->getExtension() == $type ) {
                $size += $file->getSize();
            }

        }
    }

    return $size;

}


/**
 * Get human readable file size
 *
 * @since 1.0.0
 * @param integer $bytes
 * @param integer $decimals
 * @return string
 */
function wpp_filesize( $bytes, $decimals = 2 ) {

    $size   = array( 'B', 'KB', 'MB', 'GB', 'TB' );
    $factor = floor( ( strlen( $bytes ) - 1 ) / 3 );

    return sprintf( "%.{$decimals}f", $bytes / pow( 1024, $factor ) ) . @$size[ $factor ];
}


/**
 * Cleanup site header
 *
 * @since 1.0.0
 * @return void
 */
function wpp_cleanup_header() {

    remove_action( 'wp_head', 'wp_generator' );     
    remove_action( 'wp_head', 'wlwmanifest_link' );         
    remove_action( 'wp_head', 'rsd_link' );       
    remove_action( 'wp_head', 'wp_shortlink_wp_head' );
    remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );    
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );

    add_filter( 'the_generator', '__return_false' ); 

}


/**
* Check option against the value, print selected if true
*       
* @since 1.0.0
* @param mixed $name
* @param mixed $value
* @param mixed $default
* @return void
*/
function wpp_selected( $name, $value, $default = false ) {

    if ( Option::get( $name, $default ) == $value ) {
        echo ' selected'; 
    } 
    
} 


/**
 * Check option, print checked if true
 *
 * @since 1.0.0
 * @param string $name
 * @param boolean $default
 * @return void
 */
function wpp_checked( $name, $default = false ) {

    if ( Option::get( $name, $default ) ) {
        echo ' checked';  
    } 

}


/**
 * Is tab active
 *
 * @since 1.0.0
 * @param string $tab
 * @param boolean $default
 * @param string $output
 * @return string
 */
function wpp_active( $tab, $default = false, $output = 'active' ) {

    if( Input::post( 'wpp-tab' ) == $tab ) {
    
        echo $output;    
        
    } else {

        if ( Input::get( 'load' ) ) {
            if ( $tab == 'settings' ) echo $output; 
        } else {
            if ( $default && empty( Input::post( 'wpp-tab' ) ) ) echo $output; 
        } 
            
    } 

}  

/**
 * Add menu item and register menu page
 *
 * @return void
 * @since 1.0.0
 */
function wpp_add_menu_item() {

    add_menu_page( WPP_PLUGIN_NAME, WPP_PLUGIN_NAME, 'manage_options', WPP_PLUGIN_ADMIN_URL, function() { 
        include WPP_ADMIN_DIR . 'admin.php';
    }, 'dashicons-performance' );

}


/**
 * Add wpp admin menu
 *
 * @since 1.0.0
 * @return void
 */
function wpp_add_top_menu_item() {
    
    // Insert scripts
    if ( ! is_admin() ) {               

        add_action( 'wp_enqueue_scripts', function() {

            wp_enqueue_style( 'wpp-overlay', WPP_ASSET_URL . 'overlay.css' );  
            wp_enqueue_script( 'wpp-cache-js', WPP_ASSET_URL . 'cache.js', [ 'jquery' ], null, true );
            wp_localize_script( 'wpp-cache-js', 'WPP', [
                'path' => WPP_ASSET_URL,
                'ajax' => admin_url( 'admin-ajax.php' ) 
            ] );

        }); 
        
    }

    // Add top bar link
    add_action( 'admin_bar_menu', function( $admin_bar ) {
    
        $admin_bar->add_node( [
            'id'    => 'wpp',
            'title' => '<span class="ab-icon"></span><span class="ab-label">' . WPP_PLUGIN_NAME . '</span>', 
            'href'  => admin_url( 'admin.php?page=' . WPP_PLUGIN_ADMIN_URL ), 
            'meta'  => [
                'class' => 'wpp_toolbar_link', 
                'title' => WPP_PLUGIN_NAME
            ]   
        ] );
        
        $admin_bar->add_node( [
            'id'     => 'wpp_settings',
            'title'  => __( 'Settings', 'wpp' ), 
            'href'   => admin_url( 'admin.php?page=' . WPP_PLUGIN_ADMIN_URL ),
            'parent' => 'wpp', 
            'meta'   => [
                'title' => __( 'Settings', 'wpp' )
            ]
        ] );
        
        $admin_bar->add_node( [
            'id'     => 'wpp_clear_cache',
            'title'  => __( 'Clear cache', 'wpp' ), 
            'href'   => '#',
            'parent' => 'wpp', 
            'meta'   => [
                'class' => 'wpp_clear_cache', 
                'title' => __( 'Clear cache', 'wpp' )
            ]
        ] );

    }, 99 );
    
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
    
        array_push( $incompatiblePlugins, [
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
            'wp-js/wp-js.php'
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
 * Add disable/enable link in plugins list
 *
 * @param array $links
 * @return void
 * @since 1.0.0
 */
function wpp_add_disable_link( $links ) {

    if ( Option::boolval( 'wpp_disable' ) ) {
        $action = 'enable';
        $status =  __( 'Enable', 'wpp' );
    } else {
        $action = 'disable';
        $status =  __( 'Temporarily disable', 'wpp' );
    }

    array_push( $links, '<a href="' . wp_nonce_url( admin_url( 'plugins.php?wpp-action=' . $action ), 'temp-disable', 'wpp-nonce' ) . '">' . $status . '</a>' );

    return $links;

}


/**
 * Enqueue back-end scripts and styles
 *
 * @return void
 * @since 1.0.0
 */
function wpp_enqueue_backend_assets() {

    // Enqueue scripts and styles
    wp_enqueue_script( 'wpp-confirms', WPP_ASSET_URL . 'confirm.js', [ 'jquery' ] );
    wp_enqueue_script( 'wpp-settings', WPP_ASSET_URL . 'admin.js', [ 'jquery' ] );
    
    wp_localize_script( 'wpp-settings', 'WPP', [
        'path' => WPP_ASSET_URL,
        'site_url' => trailingslashit( site_url() ),
        'admin_url' => trailingslashit( admin_url() ),
        'lang' => [
            'confirm' => __( 'Are you sure?', 'wpp' ),
            'remove'  => __( 'Remove', 'wpp' ),
            'add_url'  => __( 'Add URL', 'wpp' ),
            'disable_everywhere'  => __( 'Disable everywhere', 'wpp' ),
            'disable_selected_url'  => __( 'Disable only on selected URL', 'wpp' ),
            'disable_everywhere_except'  => __( 'Disable everywhere except on selected URL', 'wpp' ),
            'something_went_wrong' => __( 'Something went wrong', 'wpp' ),
            'regenerate_thumbs' => __( 'Regenerating thumbs', 'wpp' ),
            'regenerate_thumbs_info' => __( 'Regenerate thumbnails may take a long time. Do not close your browser.', 'wpp' ),
        ]
    ] );

    wp_enqueue_style( 'wpp-admin-css', WPP_ASSET_URL . 'style.css' );    
    wp_enqueue_style( 'wpp-overlay', WPP_ASSET_URL . 'overlay.css' );   
    wp_enqueue_style( 'wpp-confirm', WPP_ASSET_URL . 'confirm.css' ); 

}


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

    wpp_log( 'Settings exported', 'notice' ); 
        
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

                wpp_log( 'Settings imported', 'notice' ); 

                Cache::clear();

            }

        }
            
    }

}



/**
 * Exclude WooCommerce pages
 *
 * @param array $exclude
 * @return array
 * @since 1.0.0
 */
function wpp_exclude_woocommerce_pages( $exclude ) {

    // Check if woocommerce class exists
    if ( class_exists( 'WooCommerce' ) ) {
        if ( 
            is_checkout() 
            || is_account_page() 
            || is_cart() 
        ) {
            array_push( $exclude, Url::current() );
        }
    }

    return $exclude;

}

/**
 * Exclude EDD pages checkout, account and cart
 *
 * @param array $exclude
 * @return array
 * @since 1.0.0
 */
function wpp_exclude_edd_pages( $exclude ) {

    if ( class_exists( 'Easy_Digital_Downloads' )) {

        if ( 
            edd_is_checkout() 
            || edd_is_success_page() 
            || edd_is_failed_transaction_page() 
            || edd_is_purchase_history_page() 
            || edd_is_test_mode()
        ) {
            array_push( $exclude, Url::current() );
        }
    
    }

    return $exclude;

}


/**
 * Guess if file is minified
 *
 * This function will try to guess if file is already minified by checking the file name
 * Most of the time minified files have .min.* extension, so we check for that in file name
 * Also the function will return true for every file located in wp-includes directory
 * 
 * @since 1.0.0
 * @param string $file
 * @return boolean
 */
function wpp_is_minified( $file ) {
    
    if ( strstr( $file, '.min.' ) || strstr( $file, 'wp-includes/' ) ) {
        return true;
    }

    return false;

}

/**
 * Check if htaccess file is writable
 *
 * @return boolean
 * @since 1.0.0
 */
function wpp_is_htaccess_writable() {

    $htaccess = ABSPATH. '.htaccess';

    if ( file_exists( $htaccess ) && is_writable( $htaccess ) ) {
        return true;
    }

    return false;

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
 * Check if css/js optimization is disabled for logged in users
 *
 * @param string $type
 * @return boolean
 * @since 1.0.0
 */
function wpp_is_optimization_disabled_for( $type ) {

    $types = [ 'css', 'js' ];

    if ( in_array( $type, $types ) && Option::get( $type . '_disable_loggedin', false ) ) {
        if ( is_user_logged_in() ) return true;
    }

    return false;

}


/**
 * Get critical CSS path from wpp server
 *
 * @since 1.0.0
 * @return array
 */
function wpp_get_critical_css_path() {

    // Disable plugin
    Option::update( 'wpp_disable', true );

    // Clear the cache
    Cache::clear( false );

    $response = wp_remote_post( 
        'https://www.wp-performance.com/api', [
            'timeout' => 90,
            'body' => [
                'url' => site_url()
            ]
        ]
    );

    if ( is_wp_error( $response ) ) {

        $json = [
            'status' => 0,
            'message' => $response->get_error_message()
        ];

        wpp_log( sprintf( 'Generating critical CSS error %s', $response->get_error_message() ) ); 

    } else {

        $json = [
            'status' => 1,
            'data' => wp_remote_retrieve_body( $response )
        ];

        wpp_log( 'Critical CSS generated', 'notice' ); 

    }

    // Re-enable the plugin
    Option::update( 'wpp_disable', false );

    wp_send_json( $json );

}


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
 * Prepare cache preload
 *
 * @return void
 * @since 1.0.0
 */
function wpp_prepare_preload() {
    
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

            wpp_log( sprintf( 'Invalid sitemap url: %s', $url ), 'warning' );

            continue;
        }


        // Get the sitemap
        $request = wp_remote_get( $url );
    
        if ( is_wp_error( $request ) ) {

            wpp_log( sprintf( 'Error fetching sitemap url: %s %s', $url, $request->get_error_message() ), 'error' );

            return false;
        } 

        $data = wp_remote_retrieve_body( $request );

        if ( empty( $data ) ) {

            wpp_log( sprintf( 'Sitemap %s is empty', $url ), 'error' );

            return false;
        }

        // Load XML
        libxml_use_internal_errors( true );

        $xml = simplexml_load_string( $data );
    
        if ( false === $xml ) {

            libxml_clear_errors();

            wpp_log( sprintf( 'Invalid XML in sitemap %s', $url ), 'error' );

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

        wpp_log( sprintf( 'Collected %s URLs from %s sitemap(s) for cache preloading', count( $urls ), count( $sitemaps ) ), 'notice' );

    } else {

        wpp_log( 'URLs for cache preloading not found', 'notice' );

    }


}


/**
 * Preload cache
 *
 * @return void
 * @since 1.0.0
 */
function wpp_preload_cache() {

    // Bail if preload file not exists
    if ( ! file_exists( $file = WPP_CACHE_DIR . 'preload.json' ) ) {
        return false;
    }

    $data = File::getJson( $file );

    if ( empty( $data ) ) {
        wpp_log( 'Cache preloading stop - nothing to preload', 'notice' );
        exit;
    }

    wpp_log( 'Cache preloading start', 'notice' );

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
            wpp_log( sprintf( 'Error while trying to preload cache for page %s %s', $url, $request->get_error_message() ), 'error' );
        } 
        
        unset( $data[ $j ] );

        if ( $i == $max_url ) break;

        $i++;

    }

    File::saveJson( $file, $data );

}

/**
 * Preload home page
 * 
 * @return void
 * @since 1.0.0
 */
function wpp_preload_homepage() {

    $request = wp_remote_get( site_url(), [
        'timeout' => 5
    ] );

    if ( is_wp_error( $request ) ) {
        wpp_log( sprintf( 'Error while trying to preload cache for home page %s', $request->get_error_message() ) );
    }

}


/**
 * Clear DB and update next schedule info
 *
 * @return void
 * @since 1.0.0
 */
function wpp_db_cleanup() {

    DB::clear();
    
    $schedules = wpp_get_cron_schedules();
    $frequency = Option::get( 'db_cleanup_frequency' );

    if ( array_key_exists( $frequency, $schedules ) ) {
        Option::update( 'db_cleanup_next', ( time() + $schedules[ $frequency ][ 'interval' ] ) );
    } else {
        Option::remove( 'db_cleanup_next' );
    }

}


/**
 * Add remove definitions from htaccess file
 *
 * @since 1.0.0
 * @param string $action
 * @param string $file
 * @return void
 */
function wpp_update_htaccess( $action, $file ) {

    $htaccess              = ABSPATH. '.htaccess';
    $definitionsFile       = WPP_DATA_DIR . 'definitions/' . $file . '.txt';
    $customDefinitionsFile = WPP_DATA_DIR . 'definitions/' . 'custom.' . $file . '.txt';

    // Use custom definitions if exists
    if ( file_exists( $customDefinitionsFile ) ) {
        $definitionsFile = $customDefinitionsFile;
    }

    if ( ! file_exists( $definitionsFile ) ) {

        wpp_log( sprintf( '%s not exists', $definitionsFile ) );

        return false;
    }


    switch ( $action ) {

        case true:
        case 'add':

            if ( ! file_exists( $htaccess ) ) {
                
                touch( $htaccess );

            } else {

                if ( ! wpp_is_htaccess_writable() ) {

                    wpp_log( '.htaccess file not writable' );

                    return false;
                }

            }
    
            $original    = File::get( $htaccess );
            $definitions = str_replace( '{BASEDIR}', wpp_get_basedir(), File::get( $definitionsFile ) );
    
            if ( ! strstr( $original, $definitions ) ) {

                if ( $file == 'cache' ) {
                    File::prepend( $htaccess, $definitions );
                } else {
                    File::append( $htaccess, $definitions );
                }
                
            }


            break;

        case false:
        case 'remove':

            if ( file_exists( $htaccess ) ) {

                $original    = File::get( $htaccess );
                $definitions = str_replace( '{BASEDIR}', wpp_get_basedir(), File::get( $definitionsFile ) );
    
                $content = str_replace( $definitions, '', $original );

                File::save( $htaccess, $content );
    
            }

            break;
    }

}

/**
 * Get site base directory 
 *
 * @return string
 * @since 1.0.0
 */
function wpp_get_basedir() {

    $root_dir = basename( $_SERVER[ 'DOCUMENT_ROOT' ] );
    $base_dir = basename( ABSPATH );

    if ( $root_dir != $base_dir ) {
        return sprintf( '/%s/', $base_dir );
    }

    return '/';

}

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


/**
 * Check if site is on localhost or .dev domain
 *
 * @return boolean
 * @since 1.0.0
 */
function wpp_is_localhost() {

    $host = parse_url( site_url(), PHP_URL_HOST );

	if ( 'localhost' === $host || pathinfo( $host, PATHINFO_EXTENSION ) === 'dev' ) {
	    return true;
    }
    
    return false;

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
    Option::update( 'varnish_auto_purge',    Input::post( 'varnish_auto_purge', 'boolean' ) );
    Option::update( 'varnish_custom_host',   Input::post( 'varnish_custom_host', 'url' ) );

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

    // Settings
    Option::update( 'enable_log',            Input::post( 'enable_log', 'boolean' ) );

    // CDN
    Option::update( 'cdn',                   Input::post( 'cdn', 'boolean' ) );
    Option::update( 'cdn_hostname',          Input::post( 'cdn_hostname', 'url' ) );
    Option::update( 'cdn_exclude',           Input::post( 'cdn_exclude', 'string', FILTER_REQUIRE_ARRAY ));

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
    
    // Browser cache
    wpp_update_htaccess( Input::post( 'browser_cache', 'boolean'  ), 'expire' );

    // Gzip compression
    wpp_update_htaccess( Input::post( 'gzip_compression', 'boolean' ), 'gzip' );

    // Htaccess load cache
    if ( ! is_multisite() ) {
        wpp_update_htaccess( Input::post( 'cache', 'boolean'  ), 'cache' );
    }

    // Save configuration settings
    $settings = array_diff_key( $_POST, [
        'wpp-tab'           => true, 
        'wpp-nonce'         => true, 
        '_wp_http_referer'  => true, 
        'wpp-save-settings' => true
    ] );

    $timestamp = time();

    File::save( WPP_DATA_DIR . 'settings/' . $timestamp . '.json', json_encode( $settings ) );

    Option::update( 'current_settings', $timestamp );
    
    wpp_log( 'Settings saved', 'notice' ); 

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

    if ( file_exists( $file = WPP_DATA_DIR . 'settings/' . basename( $filename ) . '.json' ) ) {

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

                    wpp_update_htaccess( $action, 'expire' );

                    break;
                
                case 'gzip_compression':

                    wpp_update_htaccess( $action, 'gzip' );

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
 * Check if log file is too large
 *
 * @return void
 * @since 1.0.0
 */
function wpp_is_log_file_too_large() {

    /**
     * Set log size filter
     * @since 1.0.0
     */
    $log_size = apply_filters( 'wpp-log-size-kb', 20 );

    if ( file_exists( $log_file = wpp_get_log_file() ) ) {
        if ( filesize( $log_file ) > ( intval( $log_size ) * 1024 ) ) {
            return true;
        }
    }

    return false;

}



/**
 * Get defined image sizes
 *
 * @param array $sizes
 * @return void
 * @since 1.0.0
 */
function wpp_get_defined_image_sizes( $sizes ) {

    $sizes = [];

    $custom_sizes = Image::getAllDefinedSizes();

    foreach( $custom_sizes as $name => $size ) {

        $sizes[ $name ] = [
            'width'  => $size[ 0 ],
            'height' => $size[ 1 ],
            'crop'   => isset( $size[ 2 ] ) ? $size[ 2 ] : ''
        ];

    }

    return $sizes;

}


/**
 * Clear Varnish cache for entire domain
 *
 * @return void
 * @since 1.0.0
 */
function wpp_varnish_clear_domain() {
    return wpp_varnish_http_purge( site_url(), true );
}


/**
 * Clear url from Varnish cache
 *
 * @param string $url
 * @param boolean $regex
 * @return void
 * @since 1.0.0
 */
function wpp_varnish_http_purge( $url, $regex = false ) {

    $data        = parse_url( $url );
    $custom_host = Option::get( 'varnish_custom_host' );

    if ( filter_var( $custom_host, FILTER_VALIDATE_URL ) ) {

        $custom_data = parse_url( $custom_host );
        $host = sprintf( '%s://%s', $custom_data[ 'scheme' ], $custom_data[ 'host' ] );

    } else {
        $host = sprintf( '%s://%s', $data[ 'scheme' ], $data[ 'host' ] );
    }

    $purge_url = $host . ( isset( $data[ 'path' ] ) ? $data[ 'path' ] : '' ) . ( $regex ? '.*' : '' );

    wpp_log( $purge_url, 'notice' );

    $request = wp_remote_request( $purge_url, 
        [ 
            'method'      => 'PURGE', 
            'blocking'    => false,
			'redirection' => 0,
            'headers' => [ 
                'host'           => parse_url( site_url(), PHP_URL_HOST ), 
                'X-Purge-Method' => $regex ? 'regex' : 'exact'
            ]
        ]
    );

    if ( is_wp_error( $request ) ) {
        wpp_log( sprintf( 'Varnish error: %s', $request->get_error_message() ) );
    } else {
        wpp_log( 'Varnish cache cleared', 'notice' );
    }
    
}