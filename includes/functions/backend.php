<?php 
/**
* WP Performance Optimizer - Backend actions
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use WPP\Cache;
use WPP\File;
use WPP\Input;
use WPP\Option;

add_action( 'wpp_backend_init', function(){

    // Clear cache from frontend ajax action   
    add_action( 
        'wp_ajax_nopriv_wpp_clear_cache', 
        [ 'WPP\Cache', 'clear' ] 
    );

    // Clear the cache after switching theme
    add_action( 
        'after_switch_theme', 
        function(){
            wpp_delete_list_options();
            Cache::clear();
        } 
    );

    // Init admin
    add_action( 'init', function() {

        // Check user
        if ( ! current_user_can( 'manage_options' ) ) 
            return false;
    
        // Check if cache dir exists
        if ( ! is_dir( WPP_CACHE_DIR ) ) {
            if( ! mkdir( WPP_CACHE_DIR, 0755, true ) ) {
                wpp_notify( sprintf( '%s: missing writing permissions for directory <code>wp-content/cache</code>', WPP_PLUGIN_NAME ), 'error', false );
            } else {
                touch( WPP_CACHE_DIR . 'index.php' );
            }
        }
        
        // Check if advanced-cache.php exists
        if ( ! file_exists( trailingslashit( WP_CONTENT_DIR ) . 'advanced-cache.php' ) ) {

            wpp_notify( 
                sprintf( 
                    __( 'File <code>advanced-cache.php</code> is missing. If you updated your installation of %s recently, try to deactivate and then activate the plugin again.', 'wpp' ),
                    WPP_PLUGIN_NAME 
                ), 
                'error',
                false
            );
            
        }

        // Get initial CSS and JS files by loading the front page
        if ( ! Option::get( 'local_css' ) ) {
            wpp_preload_homepage();
        }   

        // Clear files list
        if ( 
            Input::get( 'clear' ) 
            && wp_verify_nonce( Input::get( 'nonce' ), 'clear-list' ) 
        ) {
            wpp_delete_files_list( Input::get( 'clear' ) );
        }

        // Enable disable plugin
        if ( 
            Input::get( 'wpp-action' ) 
            && wp_verify_nonce( Input::get( 'wpp-nonce' ), 'temp-disable' ) 
        ) {
            $status = ( Input::get( 'wpp-action' ) == 'disable' ) ? true : false;
            Option::update( 'wpp_disable', $status );
            File::saveSiteSettings( [ 'disable' => $status ] );
        }

        // Save settings
        if ( 
            Input::post( 'wpp-save-settings' ) 
            && wp_verify_nonce( Input::post( 'wpp-nonce' ), 'save-settings' ) 
        ) {
            wpp_save_settings();
        }

        // Clear log file
        if ( 
            Input::post( 'wpp-clear-log' ) 
            && wp_verify_nonce( Input::post( 'wpp-nonce' ), 'save-settings' ) 
        ) {
            wpp_clear_log();
        }
                                
        // Export settings
        if ( 
            Input::get( 'wpp-export-settings' ) 
            && wp_verify_nonce( Input::get( 'wpp-nonce' ), 'export-settings' ) 
        ) {
            wpp_export_settings_file();
        }

        // Import settings
        if ( isset( $_FILES[ 'wpp_import_settings' ] ) ) {
            wpp_import_settings( $_FILES );
        }
        

        // Clear cache after post is added or updated
        if ( Option::boolval( 'update_clear' ) ) {
            add_action( 
                'save_post', 
                [ 'WPP\Cache', 'clear' ], 
                999 
            );
        }

        // Clear cache after post is deleted
        if ( Option::boolval( 'delete_clear' ) ) {
            add_action( 
                'delete_post', 
                [ 'WPP\Cache', 'clear' ], 
                999 
            );
        }

        // Save post options
        add_action( 
            'save_post',                         
            'wpp_save_post_options' 
        );

        // Plugin compatibility check
        add_action( 
            'admin_init',                        
            'wpp_compatibility_check' 
        );

        // Add top bar menu
        add_action( 
            'admin_init',                        
            'wpp_add_top_menu_item' 
        );

        // Enqueue back-end scripts and styles
        add_action( 
            'admin_init',                        
            'wpp_enqueue_backend_assets' 
        );

        // Initialize UI elements registered by add-ons
        add_action( 
            'admin_init',                        
            [ 'WPP\UI', 'register' ] 
        );

        // WPP admin page
        add_action( 
            'admin_menu',                        
            'wpp_add_menu_item' 
        );

        // Filter image sizes
        add_filter( 
            'intermediate_image_sizes_advanced', 
            'wpp_get_defined_image_sizes' 
        );

        // Add page meta box
        add_action( 
            'add_meta_boxes',                    
            'wpp_add_metabox' 
        );

                    
    } );

    // Admin actions
    add_action( 'admin_init', function() {
                                                
        // Clear cache ajax action          
        add_action( 
            'wp_ajax_wpp_clear_cache',           
            [ 'WPP\Cache', 'clear' ] 
        );

        // Clear database ajax actions
        add_action( 
            'wp_ajax_wpp_clean_database',        
            'wpp_ajax_database_actions' 
        );

        // Images ajax actions
        add_action( 
            'wp_ajax_wpp_images_action',         
            'wpp_ajax_image_actions' 
        );

        // Deactivate incompatible plugin
        add_action( 
            'admin_post_deactivate_plugin',      
            'wpp_deactivate_incompatible_plugin' 
        ); 

        // Get critical CSS ajax action
        add_action( 
            'wp_ajax_wpp_get_critical_css_path', 
            'wpp_get_critical_css_path' 
        );

        // Get log content
        add_action( 
            'wp_ajax_wpp_get_log_content',       
            'wpp_ajax_get_log_content' 
        );

        // Remove excluded page
        add_action( 
            'wp_ajax_wpp_remove_post_options',   
            'wpp_ajax_remove_post_options' 
        );

        // Add disable enable link
        add_filter( 
            'plugin_action_links_' . plugin_basename( WPP_SELF ), 
            'wpp_add_disable_link' 
        );

        // Check if plugin is disabled
        if ( Option::boolval( 'wpp_disable' ) ) {
            wpp_notify( 
                sprintf( '%s %s - %s', WPP_PLUGIN_NAME,  __( 'is temporarily disabled', 'wpp' ), 
                '<a href="' . wp_nonce_url( admin_url( 'admin.php?page=' . WPP_PLUGIN_ADMIN_URL . '&wpp-action=enable' ), 'temp-disable', 'wpp-nonce' ) . '">' . __( 'Enable', 'wpp' ) . '</a>'), 
                'warning', 
                false 
            );
        }

        // Check the size of log file
        if ( 
            Option::boolval( 'enable_log' ) 
            && wpp_is_log_file_too_large() 
        ) {
            wpp_notify( 
                sprintf( __( '%s: Log file is getting large, either clear the log or disable troubleshooting logging', 'wpp' ), WPP_PLUGIN_NAME ), 
                'warning', 
                false 
            );
        }

        // Check if htaccess is writable and server is apache
        if ( 
            wpp_get_server_software( 'apache' ) 
            && ! wpp_is_htaccess_writable() 
        ) {
            wpp_notify( 
                sprintf( __( '%s: missing writing permissions for file <code>.htaccess</code>', 'wpp' ), WPP_PLUGIN_NAME ), 
                'error', 
                false 
            );
        }

        // Check if Cloudflare api key and email are set
        if ( 
            Option::boolval( 'cf_enabled' ) 
            && ! Option::boolval( 'cf_api_key' ) 
            && ! Option::boolval( 'cf_email' ) 
        ) {
            wpp_notify( 
                sprintf( __( '%s: enter Cloudflare credentials', 'wpp' ), WPP_PLUGIN_NAME ), 
                'warning', 
                false 
            );
        }

        // load settings file
        if ( 
            Input::get( 'load' ) 
            && wp_verify_nonce( Input::get( 'nonce' ), 'load-config' ) 
        ) {
            wpp_load_settings( Input::get( 'load' ) );
        }

    } );

} );