<?php 
/**
* WP Performance Optimizer - Exclude pages
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use WPP\Option;

/**
 * Exclude manually added pages from 
 * Cache
 * CSS optimization
 * JavaScript optimization
 * Image optimization
 * Iframe lazyload
 *
 * @param array $exclude
 * @return array
 * @since 1.0.3
 */
add_action( 'init', function() {

    $hooks = [
        'wpp_css_url_exclude'   => 'css_post_exclude',
        'wpp_js_url_exclude'    => 'js_post_exclude',
        'wpp_exclude_urls'      => 'cache_post_exclude',
        'wpp_image_url_exclude' => 'image_url_exclude',
        'wpp_video_url_exclude' => 'video_url_exclude',
    ];

    foreach( $hooks as $filter => $option ) {

        add_filter( $filter, function( $exclude ) use( $option ) {

            $pages = Option::get( $option, [] );

            foreach ( $pages as $id ) {
                $exclude[] = get_permalink( $id );
            }
        
            return $exclude;

        } );

    }

} );