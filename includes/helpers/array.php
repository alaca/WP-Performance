<?php 
/**
* WP Performance Optimizer - Array helpers
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/


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