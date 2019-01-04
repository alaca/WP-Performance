<?php namespace WPP;
/**
* WP Performance Optimizer - Input helper
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*
* @see Filters http://php.net/manual/en/filter.filters.php
*/

class Input
{
    /**
     * Get filtered input value
     *
     * @param string $name
     * @param array $args
     * @return mixed
     * @since 1.0.0
     */
    public static function __callStatic( $name, $args ) {
        return call_user_func_array( [ 'WPP\Input', 'filter' ], [ $name, $args ] );
    }

    /**
     * Filter input value
     *
     * @param string $name
     * @param array $args
     * @return mixed
     * @since 1.0.0
     */
    private static function filter( $name, $args ) {   

        $params = array_pad( $args, 3, null );

        if ( $name === 'request' ) {

            if ( isset( $_REQUEST[ $params[ 0 ] ] ) ) {
                return filter_var( $_REQUEST[ $params[ 0 ] ], Input::getFilter( $params[ 1 ] ), $params[ 2 ] );
            }

            return $params[ 2 ];
            
        }

        return filter_input( Input::getMethod( $name ), $params[ 0 ], Input::getFilter( $params[ 1 ] ), $params[ 2 ] );

    }

    /**
     * Get input method
     *
     * @param string $name
     * @return int
     * @since 1.0.0
     */
    private static function getMethod( $name ) {

        switch ( $name ) {
            case 'get': 
                return INPUT_GET;
            case 'post':
                return INPUT_POST;
            case 'cookie':
                return INPUT_COOKIE;
            case 'session':
                return INPUT_SESSION;
            case 'server':
                return INPUT_SERVER;
            case 'env':
                return INPUT_ENV;
            default:
                return 'request';


        }
    }


    /**
     * Get filter
     *
     * @param string $name
     * @return void
     * @since 1.0.0
     */
    private static function getFilter( $name ) {
        
        if ( in_array( $name, filter_list() ) ) {
            return filter_id( $name );
        }

        return FILTER_SANITIZE_STRING;
    }

}