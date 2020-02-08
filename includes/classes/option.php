<?php namespace WPP;
/**
* WP Performance Optimizer - Options
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

class Option
{
    
    /**
    * Add option
    * 
    * @see https://developer.wordpress.org/reference/functions/add_option/
    *
    * @since 1.0.0
    *
    * @param string $option
    * @param mixed $value
    * @param string $deprecated
    * @param mixed $autoload
    *
    * @return boolean
    */
    public static function add( $option, $value = '', $deprecated = '', $autoload = 'yes'  ) {
        return add_option( wpp_get_prefix( $option ), $value, $deprecated, $autoload );
    }

    
    /**
    * Get option
    *
    * @see https://developer.wordpress.org/reference/functions/get_option/
    *
    * @since 1.0.0
    *
    * @param string $option
    * @param mixed $default
    *
    * @return mixed
    */
    public static function get( $option, $default = false ) {

        $value = get_option( wpp_get_prefix( $option ), $default );

        return ( ! $value ) ? $default : $value;
  
    }


    /**
     * Get option boolean value
     *
     * @since 1.0.0
     * @param string $option
     * @param mixed $default
     * 
     * @return boolean
     */
    public static function boolval( $option, $default = false ) {
        return boolval( Option::get( $option, $default ) );
    }
    
    /**
    * Update option
    * 
    * @see https://developer.wordpress.org/reference/functions/update_option/
    * 
    * @since 1.0.0 
    *
    * @param string $option
    * @param mixed $value
    * @param mixed $autoload
    *
    * @return boolean
    */
    public static function update( $option, $value, $autoload = true ) {
        return update_option( wpp_get_prefix( $option ), $value, $autoload );
    }


    /** 
    * Remove option
    *
    * @see https://developer.wordpress.org/reference/functions/delete_option/
    * 
    * @since 1.0.0
    * @param string $option
    *
    * @return boolean
    */
    public static function remove( $option ) {
        return delete_option( wpp_get_prefix( $option ) );
    }


    /**
     * Remove all options
     * 
     * @since 1.1.6
     * @return void
     */
    public static function removeAll() {

        $GLOBALS['wpdb']->query( 
            sprintf( 
                'DELETE FROM %s WHERE option_name LIKE "%s%%"', 
                $GLOBALS['wpdb']->options,
                wpp_get_prefix()
            ) 
        );

    }

    /**
     * Get all options
     * 
     * @since 1.1.6
     * @return array
     */
    public static function getAll() {

        $options = [];

        $result = $GLOBALS['wpdb']->get_results( 
            sprintf( 
                'SELECT option_name, option_value FROM %s WHERE option_name LIKE "%s%%"', 
                $GLOBALS['wpdb']->options,
                wpp_get_prefix()
            ) 
        );

        foreach( $result as $option ) 
            $options[ $option->option_name ] = maybe_unserialize( $option->option_value );

        return $options;

    }


}