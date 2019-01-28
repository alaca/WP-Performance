<?php 
/**
* WP Performance Optimizer - Image helpers
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use WPP\Input;
use WPP\Image;
use WPP\Option;


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
 * Register image ajax actions
 *
 * @return void
 * @since 1.0.3
 */
function wpp_ajax_image_actions() {

    check_ajax_referer( 'wpp-ajax', 'nonce' );

    switch ( Input::post( 'image_action' ) ) {

        case 'add_size':

            // get all sizes
            $all_sizes = Image::getAllDefinedSizes();

            parse_str( Input::post( 'size' ), $size );

            // check size name
            if ( array_key_exists( $size[ 'name' ], $all_sizes ) ) {

                wpp_log( sprintf( 'Image size %s already in use', $size[ 'name' ] ), 'event' );

                wp_send_json( [ 
                    'status' => 0, 
                    'error' => __( 'Size name already in use', 'wpp' ) 
                ] );
                
            }

            $sizes = Option::get( 'image_sizes', [] );

            $sizes = array_merge( $sizes, [
                $size[ 'name' ] => [
                    intval( $size[ 'width' ] ), 
                    intval( $size[ 'height' ] ),
                    isset( $size[ 'crop' ] ) ? 1 : ''
                ]
            ]);

            if ( Option::update( 'image_sizes', $sizes ) ) {

                wpp_log( sprintf( 'Image size %s added', $size[ 'name' ] ), 'event' );

                wp_send_json( [ 'status' => 1 ] );
            }

            break;

        case 'remove_size':    
        
            $name = Input::post( 'size' );

            // Image sizes defined from WPP
            $wpp_sizes = Option::get( 'image_sizes', [] );
            
            if ( array_key_exists( $name, $wpp_sizes ) ) {

                unset( $wpp_sizes[ $name ] );

                if ( Option::update( 'image_sizes', $wpp_sizes ) ) {

                    wpp_log( sprintf( 'Image size %s removed', $name ), 'event' );

                    wp_send_json( [ 'status' => 1 ] );
                }

            }

            // The rest of image sizes
            if ( array_key_exists( $name, Image::sizes() ) ) {

                $removed_sizes = Option::get( 'image_sizes_remove', [] );

                if ( ! in_array( $name, $removed_sizes ) ) {

                    array_push( $removed_sizes, $name );
                    
                    if ( Option::update( 'image_sizes_remove', $removed_sizes ) ) {

                        wpp_log( sprintf( 'Image size %s removed', $name ), 'event' );

                        wp_send_json( [ 'status' => 1 ] );
                    }

                }

            }

            break;

        case 'restore_sizes':

            Option::update( 'image_sizes', [] );
            Option::update( 'image_sizes_remove', [] );

            wpp_log( 'Image sizes restored', 'event' );

            wp_send_json( [ 'status' => 1 ] );


            break;

        case 'get_all_sizes':

            wp_send_json( [ 'sizes' => Image::getAllDefinedSizesJson( false ) ] );

            break;

        case 'regenerate_thumbnails':

            if ( Input::post( 'remove_flag' ) === 'true' ) {
                delete_post_meta_by_key( 'wpp_thumb_regenerated' );
                wpp_log( 'Thumb regeneration start', 'event' );
            }
    
            if ( $image = Image::getNonRegenerated( 1 ) ) {

                if ( $file = get_attached_file( $image[ 0 ]->ID ) ) {
            
                    $info = pathinfo( $file );
        
                    // Delete old thumbs
                    foreach( glob( $info[ 'dirname' ] . DIRECTORY_SEPARATOR . $info[ 'filename' ] . '-*.' . $info[ 'extension' ] ) as $thumb ) {
                        unlink( $thumb );
                    }

                    $meta = wp_generate_attachment_metadata( $image[ 0 ]->ID, $file );

                    wp_update_attachment_metadata( $image[ 0 ]->ID, $meta );
        
                    add_post_meta( $image[ 0 ]->ID, 'wpp_thumb_regenerated', true );

                }

                $total   = count( Image::getAllImages() );
                $pending = count( Image::getNonRegenerated() );
                $percent = ( $total - $pending != 0 ) ? ( ( $total - $pending ) / $total ) * 100 : 0;

                $filename = str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( $file ) );
        
                wpp_log( sprintf( 'Thumbs regenerated for %s', $filename ), 'event' );

                wp_send_json( [
                    'process' => true,
                    'pending' => $pending,
                    'total'   => $total,
                    'info'    => sprintf( _n( '%s image left', '%s images left', $pending, 'wpp' ), $pending ),
                    'percent' => number_format( $percent, 2 )
                ] );

            } else {

                wpp_log( 'Thumb regeneration end', 'event' );

                wp_send_json( [
                    'process' => false,
                    'pending' => 0,
                    'total'   => 0,
                    'info'    => __( 'Done', 'wpp' ),
                    'percent' => 100
                ] );

            }

            break;

    }
}
