<?php namespace WPP;
/**
* WP Performance Optimizer
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use WPP\DB;
use WPP\Url;
use WPP\File;
use WPP\Input;
use WPP\Option;


class WP_Performance
{
    private static $instance; 
    private $data = []; 
              
    private function __construct() {  
        
        // Load language
        load_plugin_textdomain( 'wpp', false, plugin_basename( WPP_DIR ) . '/languages' );

        // Include helpers
        include WPP_DIR . 'includes/helpers.php';

        // Define plugin name and plugin admin url
        define( 'WPP_PLUGIN_NAME'     , 'WP Performance' );
        define( 'WPP_PLUGIN_ADMIN_URL', sanitize_title( WPP_PLUGIN_NAME ) );
             
        // Register autoloader   
        spl_autoload_register( [ $this, 'autoload' ] ); 

        $this->init();

    } 

    private function __clone(){}

    /**
     *  WP Performance initialize
     *
     * @return WPP
     */
    public static function instance() {

        if ( is_null( static::$instance ) ) {
            static::$instance = new static();    
        }
        
        return static::$instance;
        
    }


    /**
     * Class autoloader
     *
     * @param string $class
     * @return void
     * @since 1.0.0
     */
    private function autoload( $class ) {

        // Check namespace
        if ( false === strpos( $class, __NAMESPACE__ ) ) {
            return;
        }

        $file = str_replace( '\\', DIRECTORY_SEPARATOR, $class );
        $file = WPP_CLASSES_DIR . strtolower( basename( $file ) ) . '.php';

        if ( file_exists( $file ) ) { 
            include_once $file;
        }

    }
    
    /**
    *  Run WP Performance
    * 
    */
    public function run() {        
        return is_admin() ? $this->backend() : $this->frontend();   
    }


    /**
     * Init function
     *
     * @return void
     * @since 1.0.0
     */
    private function init() {

        // Schedule cron tasks
        
        // Register schedules
        add_filter( 'cron_schedules', 'wpp_get_cron_schedules' );

        // Sitemaps cache preload
        if ( Option::get( 'sitemaps_list' ) ) {

            add_action( 'wpp_prepare_preload', 'wpp_prepare_preload' );
            add_action( 'wpp_preload_cache',   'wpp_preload_cache' );

            // Prepare preload
            if ( ! wp_next_scheduled( 'wpp_prepare_preload' ) ) {
                wp_schedule_event( time(), 'wpp_every_5_minutes', 'wpp_prepare_preload' );
            }

            // Preload cache
            if ( ! wp_next_scheduled( 'wpp_preload_cache' ) ) {
                wp_schedule_event( time(), 'wpp_every_minute', 'wpp_preload_cache' );
            }

        }

        // Database cleanup
        if ( $frequency = Option::get( 'db_cleanup_frequency' ) ) {

            add_action( 'wpp_db_cleanup', 'wpp_db_cleanup' );

            if ( ! wp_next_scheduled( 'wpp_db_cleanup' ) ) {
                wp_schedule_event( time(),  $frequency, 'wpp_db_cleanup' );
            }

        }
    }


    /**
    * WP Performance front-end
    * 
    */
    private function frontend() {    

        // Is plugin disabled
        if ( Option::boolval( 'wpp_disable' ) ) {
            return false;
        }

        // Include pluggable because we need is_user_logged_in() function  
        include_once( trailingslashit( ABSPATH ) . 'wp-includes/pluggable.php' ); 
                
        // Check if user is logged in
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {

            wpp_add_top_menu_item();

        } else {

            // Exclude WooCommerce pages
            add_filter( 'wpp_exclude_urls', 'wpp_exclude_woocommerce_pages' );

            // Exclude EDD pages
            add_filter( 'wpp_exclude_urls', 'wpp_exclude_edd_pages' );
            
            // load cache
            if ( Option::boolval( 'cache' ) && ! isset( $_GET[ 'nocache' ] ) ) {
                Cache::load();
            }

        }

        if ( ! isset( $_GET[ 'noparse' ] ) ) {

            // Cleanup header
            if ( apply_filters( 'wpp_cleanup_header', true ) ) {
                wpp_cleanup_header();
            }

            // Hook up
            add_action( 'wp', function() {
                if ( ! is_404() ) add_filter( 'template_include', [ 'WPP\Parser', 'init' ], 99999 );
            } );


        }
        
    }

    /**
    * WP Performance back-end
    * 
    */
    private function backend() {

        // Register WP plugin hooks
        register_activation_hook( WPP_SELF,   'wpp_activate' );
        register_deactivation_hook( WPP_SELF, 'wpp_deactivate' );
        register_uninstall_hook( WPP_SELF,    'wpp_uninstall' );
                
        // Init admin
        add_action( 'init', function() {

            // Check user
            if ( ! current_user_can( 'manage_options' ) ) return false;
        
            // Check if cache dir exists
            if ( ! is_dir( WPP_CACHE_DIR ) ) {
                if( ! mkdir( WPP_CACHE_DIR, 0755, true ) ) {
                    wpp_notify( sprintf( '%s: missing writing permissions for directory <code>wp-content/cache</code>', WPP_PLUGIN_NAME ), 'error', false );
                } else {
                    touch( WPP_CACHE_DIR . 'index.php' );
                }
            }
    
            // Get initial CSS and JS files by loading the front page
            if ( ! Option::get( 'local_css' ) || ! Option::get( 'local_js' ) ) {
                wpp_preload_homepage();
            }   

            // load settings file
            if ( Input::get( 'load' ) && wp_verify_nonce( Input::get( 'nonce' ), 'load-config' ) ) {
                wpp_load_settings( Input::get( 'load' ) );
            }

            // Clear files list
            if ( Input::get( 'clear' ) && wp_verify_nonce( Input::get( 'nonce' ), 'clear-list' ) ) {
                wpp_clear_files_list( Input::get( 'clear' ) );
            }

            // Enable disable plugin
            if ( Input::get( 'wpp-action' ) && wp_verify_nonce( Input::get( 'wpp-nonce' ), 'temp-disable' ) ) {
                Option::update( 'wpp_disable', ( Input::get( 'wpp-action' ) == 'disable' ) ? true : false );
            }

            // Save settings
            if ( Input::post( 'wpp-save-settings' ) && wp_verify_nonce( Input::post( 'wpp-nonce' ), 'save-settings' ) ) {
                wpp_save_settings();
            }

            // Clear log file
            if ( Input::post( 'wpp-clear-log' ) && wp_verify_nonce( Input::post( 'wpp-nonce' ), 'save-settings' ) ) {
                wpp_clear_log();
            }
                                  
            // Export settings
            if ( Input::get( 'wpp-export-settings' ) && wp_verify_nonce( Input::get( 'wpp-nonce' ), 'export-settings' ) ) {
                wpp_export_settings_file();
            }

            // Import settings
            if ( isset( $_FILES[ 'wpp_import_settings' ] ) ) {
                wpp_import_settings( $_FILES );
            }
            

            // Clear cache after post is added or updated
            if ( Option::boolval( 'update_clear' ) ) {
                add_action( 'save_post', [ 'WPP\Cache', 'clear' ], 999 );
            }

            // Clear cache after post is deleted
            if ( Option::boolval( 'delete_clear' ) ) {
                add_action( 'delete_post', [ 'WPP\Cache', 'clear' ], 999 );
            }

            // Admin actions
            add_action( 'admin_init', function() {
                                                            
                // Clear cache ajax action          
                add_action( 'wp_ajax_wpp_clear_cache',           [ 'WPP\Cache', 'clear' ] );
                // Clear database ajax actions
                add_action( 'wp_ajax_wpp_clean_database',        [ 'WPP\DB', 'registerActions' ] );
                // Images ajax actions
                add_action( 'wp_ajax_wpp_images_action',         [ 'WPP\Image', 'registerActions' ] );
                // Deactivate incompatible plugin
                add_action( 'admin_post_deactivate_plugin',      'wpp_deactivate_incompatible_plugin' ); 
                // Get critical CSS ajax action
                add_action( 'wp_ajax_wpp_get_critical_css_path', 'wpp_get_critical_css_path' );
                // Get log content
                add_action( 'wp_ajax_wpp_get_log_content',       'wpp_ajax_get_log_content' );
                // Add disable enable link
                add_filter( 'plugin_action_links_' . plugin_basename( WPP_SELF ), 'wpp_add_disable_link' );

                // Check if plugin is disabled
                if ( Option::boolval( 'wpp_disable' ) ) {
                    wpp_notify( sprintf( '%s %s - %s', WPP_PLUGIN_NAME,  __( 'is temporarily disabled', 'wpp' ), '<a href="' . wp_nonce_url( admin_url( 'admin.php?page=' . WPP_PLUGIN_ADMIN_URL . '&wpp-action=enable' ), 'temp-disable', 'wpp-nonce' ) . '">' . __( 'Enable', 'wpp' ) . '</a>'), 'warning', false );
                }

                // Check the size of log file
                if ( Option::boolval( 'enable_log' ) && wpp_is_log_file_too_large() ) {
                    wpp_notify( sprintf( __( '%s: Log file is getting large, either clear the log or disable troubleshooting logging', 'wpp' ), WPP_PLUGIN_NAME ), 'warning', false );
                }

                // Check if htaccess is writable and server is apache
                if ( 'apache' === wpp_get_server_software() && ! wpp_is_htaccess_writable() ) {
                    wpp_notify( sprintf( __( '%s: missing writing permissions for file <code>.htaccess</code>', 'wpp' ), WPP_PLUGIN_NAME ), 'error', false );
                }

            } );

            // Plugin compatibility check
            add_action( 'admin_init',                        'wpp_compatibility_check' );
            // Add top bar menu
            add_action( 'admin_init',                        'wpp_add_top_menu_item' );
            // Enqueue back-end scripts and styles
            add_action( 'admin_init',                        'wpp_enqueue_backend_assets' );
            // WPP admin page
            add_action( 'admin_menu',                        'wpp_add_menu_item' );
            // Set position
            add_action( 'activated_plugin',                  'wpp_set_plugin_position' );
            // Filter image sizes
            add_filter( 'intermediate_image_sizes_advanced', 'wpp_get_defined_image_sizes' );
                        
        } );


        // Clear cache from frontend ajax action   
        add_action( 'wp_ajax_nopriv_wpp_clear_cache', [ 'WPP\Cache', 'clear' ] );

        // Varnish auto purge cache
        if ( Option::boolval( 'varnish_auto_purge' ) ) {
            add_action( 'wpp-after-cache-delete', 'wpp_varnish_clear_domain' );
        }

        // Clear the cache after switching theme
        add_action( 'after_switch_theme', function(){
            wpp_delete_list_options();
            Cache::clear();
        } );

    }
       
}