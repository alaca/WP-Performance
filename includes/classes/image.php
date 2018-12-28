<?php namespace WPP;
/**
* WP Performance Optimizer - Image
*
* @author Ante Laca <ante.laca@gmail.com>
*/

use WP_Query;

class Image
{

    /**
     * Get image sizes
     *
     * @return array
     * @since 1.0.0
     */
    public static function sizes() {

        $sizes   = [];
        $default = get_intermediate_image_sizes();

        foreach ( $default as $size ) {

            if ( $width = intval( get_option( $size . '_size_w' ) ) ) {

                $sizes[ $size ] = [
                    $width, 
                    intval( get_option( $size . '_size_h' ) ), 
                    boolval( get_option( $size . '_crop' ) ) 
                ];

            }

        }

        if ( 
            isset( $GLOBALS[ '_wp_additional_image_sizes' ] ) 
            && count( $GLOBALS[ '_wp_additional_image_sizes' ] ) 
        ) {

            foreach ( $GLOBALS[ '_wp_additional_image_sizes' ] as $name => $size ) {

                if ( isset( $size[ 'width' ] ) ) {
                    $sizes[ $name ] = [ 
                        $size[ 'width' ], 
                        $size[ 'height' ], 
                        $size[ 'crop' ] 
                    ];
                }

            }
           
        }

        $removed_sizes = Option::get( 'image_sizes_remove', [] );

        foreach( $removed_sizes as $name ) {

            if ( array_key_exists( $name, $sizes ) ) {
                unset( $sizes[ $name ] );
            }

        }

        asort( $sizes );

        return $sizes;
    
    }


    /**
     * Get all images with non regenerated thumbs
     *
     * @param integer $number
     * @return array
     * @since 1.0.0
     */
    public static function getNonRegenerated( $number = -1 ) {

        $result = new WP_Query( [
            'post_type'      => 'attachment',
            'post_mime_type' => [ 'image/jpeg', 'image/gif', 'image/png' ],
            'post_status'    => 'inherit',
            'posts_per_page' => $number,
            'meta_query' => [
                [
                    'key' => 'wpp_thumb_regenerated',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ] );

        return $result->posts;

    }


    /**
     * Get all images from media library
     *
     * @return array
     * @since 1.0.0
     */
    public static function getAllImages() {

        $result = new WP_Query( [
            'post_type'      => 'attachment',
            'post_mime_type' => [ 'image/jpeg', 'image/gif', 'image/png' ],
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
        ] );

        return $result->posts;

    }


    /**
     *  Get all defined image sizes
     *
     * @return array
     * @since 1.0.0
     */
    public static function getAllDefinedSizes() {
        return array_merge( static::sizes(), Option::get( 'image_sizes', [] ) );
    }


    /**
     * Return defined image sizes in json format
     *
     * @param boolean $encode
     * @return array|json
     * @since 1.0.0
     */
    public static function getAllDefinedSizesJson( $encode = true ) {

        $sizes = static::getAllDefinedSizes();

        $response = [];

        foreach ( $sizes as $name => $size ) {
            $response[] = [
                'name'   => $name,
                'width'  => $size[ 0 ],
                'height' => $size[ 1 ],
                'crop'   => $size[ 2 ] ? 1 : 0
            ];
        }

        if ( $encode ) {
            return json_encode( $response );
        }

        return $response;

    }

    /**
     * Get all variations of an image
     *
     * @param string $src
     * @return array
     */
    public static function getAllVariations( $src ) {

        // check if image exists
        if( ! file_exists( $file = File::path( $src ) ) ) {
            return [];
        }

        $images   = [];
        $sizes    = static::getAllDefinedSizes();
        $path     = pathinfo( $file );
        $existing = glob( $path[ 'dirname' ] . DIRECTORY_SEPARATOR . $path[ 'filename' ] . '*' );

        foreach( $sizes as $name => $data ) {

            // Woocommerce have some strange numbers for height, hm...
            if ( $data[ 1 ] > ( $data[ 0 ] * 3 ) ) {
                continue;
            }

            $filename = sprintf(
                '%s-%sx%s.%s', 
                $path[ 'dirname' ] . DIRECTORY_SEPARATOR . $path[ 'filename' ], 
                $data[ 0 ], $data[ 1 ], 
                $path[ 'extension' ]
            );

            // Get exact image size
            if ( file_exists( $filename ) ) {

                $images[ $data[ 0 ] ] = Url::path( $filename );

            } else {

                $found = false;

                // find image by width attribute
                foreach ( $existing as $existing_image ) {

                    $pattern = $path[ 'filename' ] . '-' . $data[ 0 ] . '(.*)\.' . $path[ 'extension' ];

                    if ( preg_match( "/$pattern/", $existing_image ) ) {
                        $images[ $data[ 0 ] ] = Url::path( $existing_image );
                        $found = true;
                        break;
                    }

                }

                // Image not found
                if ( ! $found ) {
                    // Fallback
                    $images[ $data[ 0 ] ] = Url::path( $src );  

                }

            }

        }

        ksort( $images );

        return $images;

    }
    


    /**
     * Register ajax actions
     *
     * @return void
     * @since 1.0.0
     */
    public static function registerActions() {

        switch ( Input::post( 'image_action' ) ) {

            case 'add_size':

                // get all sizes
                $all_sizes = static::getAllDefinedSizes();

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
                if ( array_key_exists( $name, static::sizes() ) ) {

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

                wp_send_json( [ 'sizes' => static::getAllDefinedSizesJson( false ) ] );

                break;

            case 'regenerate_thumbnails':

                if ( isset( $_POST[ 'remove_flag' ] ) && $_POST[ 'remove_flag' ] === 'true' ) {
                    delete_post_meta_by_key( 'wpp_thumb_regenerated' );
                    wpp_log( 'Thumb regeneration start', 'event' );
                }
        
                if ( $image = static::getNonRegenerated( 1 ) ) {

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

                    $total   = count( static::getAllImages() );
                    $pending = count( static::getNonRegenerated() );
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


}