<?php namespace WPP;
/**
* WP Performance Optimizer
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

final class WP_Performance
{
    /**
     * @var WP_Performance class instance
     * @since 1.0.0
     */
    private static $instance; 
              
    /**
     * Instantiate the plugin
     *
     * @since 1.0.0
     * @return void
     */
    private function __construct() {  
    
        // Autoloader   
        require WPP_DIR . 'vendor/autoload.php';

        /**
         * WPP init actions hook
         * 
         * @since 1.1.5.1
         */
        do_action( 'wpp_init' );
            
    } 

    /**
     * Throw error on object clone
     *
     * @since 1.0.0
     * @return void
     */
    public function __clone() {
        _doing_it_wrong( 
            __FUNCTION__, 
            'Cloning instances of the class is forbidden', 
            '1.0.0' 
        );
    }

    /**
     * Disable unserializing of the class
     *
     * @since 1.1.0
     * @return void
     */
    public function __wakeup() {
        _doing_it_wrong( 
            __FUNCTION__, 
            'Unserializing instances of the class is forbidden', 
            '1.0.0' 
        );
    }


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
    *  Run WP Performance
    * 
    */
    public function run() {  
        
        // Don't run in CLI
        if ( defined( 'WP_CLI' ) ) {
            return null;
        }
        
        return is_admin() 
            ? $this->backend() 
            : $this->frontend();   
    }

    /**
    * WP Performance front-end actions
    * @since 1.0.0
    */
    private function frontend() {    

        // Is plugin disabled
        if ( Option::boolval( 'wpp_disable' ) ) {
            return false;
        }

        /**
         * Frontend actions hook
         * 
         * @since 1.0.8
         */
        do_action( 'wpp_frontend_init' );
        
    }

    /**
    * WP Performance back-end actions
    * @since 1.0.0
    */
    private function backend() {

        // Register WP plugin hooks
        register_activation_hook( 
            WPP_SELF,   
            'wpp_activate' 
        );

        register_deactivation_hook( 
            WPP_SELF, 
            'wpp_deactivate' 
        );

        register_uninstall_hook( 
            WPP_SELF,    
            'wpp_uninstall' 
        );

        /**
         * Backend actions hook
         * 
         * @since 1.1.5.1
         */
        do_action( 'wpp_backend_init' );

    }
       
}