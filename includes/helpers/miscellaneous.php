<?php 
/**
* WP Performance Optimizer - Miscellaneous helpers
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use WPP\Image;

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
