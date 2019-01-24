<?php 
/**
* WP Performance Optimizer - Admin UI helpers
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use WPP\Input;
use WPP\Option;

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

    if( Input::request( 'wpp-tab' ) == $tab ) {
    
        echo $output;    
        
    } else {

        if ( Input::get( 'load' ) ) {
            if ( $tab == 'settings' ) echo $output; 
        } else if ( Input::get( 'clear' ) ) {
            if ( $tab == Input::get( 'clear' ) ) echo $output; 
        } else {
            if ( $default && ! Input::request( 'wpp-tab' ) ) echo $output; 
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
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'wpp-ajax' )
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
            'id'     => 'wpp_cache_topbar_link',
            'title'  => __( 'Cache', 'wpp' ), 
            'href'   => admin_url( 'admin.php?page=' . WPP_PLUGIN_ADMIN_URL ),
            'parent' => 'wpp', 
            'meta'   => [
                'title' => __( 'Settings', 'wpp' )
            ]
        ] );

        $admin_bar->add_node( [
            'id'     => 'wpp_css_topbar_link',
            'title'  => 'CSS', 
            'href'   => admin_url( 'admin.php?page=' . WPP_PLUGIN_ADMIN_URL . '&wpp-tab=css' ),
            'parent' => 'wpp', 
            'meta'   => [
                'title' => 'CSS'
            ]
        ] );

        $admin_bar->add_node( [
            'id'     => 'wpp_js_topbar_link',
            'title'  => 'JavaScript', 
            'href'   => admin_url( 'admin.php?page=' . WPP_PLUGIN_ADMIN_URL . '&wpp-tab=javascript' ),
            'parent' => 'wpp', 
            'meta'   => [
                'title' => 'JavaScript'
            ]
        ] );

        $admin_bar->add_node( [
            'id'     => 'wpp_images_topbar_link',
            'title'  => __( 'Images', 'wpp' ), 
            'href'   => admin_url( 'admin.php?page=' . WPP_PLUGIN_ADMIN_URL . '&wpp-tab=image-optimization' ),
            'parent' => 'wpp', 
            'meta'   => [
                'title' => __( 'Images', 'wpp' )
            ]
        ] );


        $admin_bar->add_node( [
            'id'     => 'wpp_database_topbar_link',
            'title'  => __( 'Database', 'wpp' ), 
            'href'   => admin_url( 'admin.php?page=' . WPP_PLUGIN_ADMIN_URL . '&wpp-tab=database' ),
            'parent' => 'wpp', 
            'meta'   => [
                'title' => __( 'Database', 'wpp' )
            ]
        ] );



        $admin_bar->add_node( [
            'id'     => 'wpp_db_topbar_link',
            'title'  => 'CDN', 
            'href'   => admin_url( 'admin.php?page=' . WPP_PLUGIN_ADMIN_URL . '&wpp-tab=cdn' ),
            'parent' => 'wpp', 
            'meta'   => [
                'title' => 'CDN'
            ]
        ] );

        $admin_bar->add_node( [
            'id'     => 'wpp_settings_topbar_link',
            'title'  => __( 'Settings', 'wpp' ), 
            'href'   => admin_url( 'admin.php?page=' . WPP_PLUGIN_ADMIN_URL . '&wpp-tab=settings' ),
            'parent' => 'wpp', 
            'meta'   => [
                'title' => __( 'Settings', 'wpp' )
            ]
        ] );


        // Cloudflare
        if ( Option::boolval( 'cf_enabled' ) ) {

            $admin_bar->add_node( [
                'id'     => 'wpp_cloudflare_topbar_link',
                'title'  => 'Cloudflare', 
                'href'   => admin_url( 'admin.php?page=' . WPP_PLUGIN_ADMIN_URL . '&wpp-tab=cloudflare' ),
                'parent' => 'wpp', 
                'meta'   => [
                    'title' => 'Cloudflare'
                ]
            ] );


            $admin_bar->add_node( [
                'id'     => 'wpp_clear_cf_cache',
                'title'  => __( 'Clear Cloudflare cache', 'wpp' ), 
                'href'   => '#',
                'parent' => 'wpp', 
                'meta'   => [
                    'class' => 'wpp_clear_cf_cache', 
                    'title' => __( 'Clear Cloudflare cache', 'wpp' )
                ]
            ] );

        }
        
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
 * Add WPP metabox
 *
 * @return void
 * @since 1.0.3
 */
function wpp_add_metabox() {

    add_meta_box(
        'wpp_page_cache_metabox',         
        sprintf( '%s %s', WPP_PLUGIN_NAME, __( 'settings', 'wpp' ) ),  
        'wpp_display_cache_metabox',  
        null,
        'side',
        'high'  
    );

}

/**
 * Display metabox
 *
 * @param WP_Post $post
 * @return void
 * @since 1.0.3
 */
function wpp_display_cache_metabox( $post ) {
    include WPP_ADMIN_DIR . 'metabox.php';
}



/**
 * Determine if exclude options needs to be shown
 *
 * @param string $type
 * @return boolean
 * @since 1.0.3
 */
function wpp_maybe_show_exclude_option( $type ) {

    if ( ! in_array( $type, [ 'js', 'css' ] ) ) {
        return false;
    }

    if ( 
        Option::get( $type . '_minify' ) 
        || Option::get( $type . '_combine' ) 
        || Option::get( $type . '_inline' ) 
        || Option::get( $type . '_disable' ) 
        || Option::get( $type . '_disable_position' ) 
        || Option::get( $type . '_minify_inline' ) 
        || Option::get( $type . '_defer' ) 
    ) {
        return true;
    }

    return false;

}


/**
 * Check if current page is plugin admin page
 *
 * @return boolean
 * @since 1.0.3
 */
function wpp_is_plugin_page() {

    if ( isset( $_GET[ 'page' ] ) && $_GET[ 'page' ] == WPP_PLUGIN_ADMIN_URL ) {
        return true;
    }

    return false;

}


/**
 * Load admin template
 *
 * @param string $template
 * @param array $vars
 * @return string
 * @since 1.0.6
 */
function wpp_load_template( $template, $vars = [] ) {

    if ( file_exists( $file = WPP_ADMIN_DIR . $template . '.php' ) ) {

        ob_start();
        extract( $vars );
        include $file;
        $content = ob_get_clean();

        echo $content;
        
    }

}