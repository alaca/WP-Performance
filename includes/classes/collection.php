<?php namespace WPP;
/**
* WP Performance Optimizer - Collection
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

class Collection
{
    private static $collection = [];

    /**
     * Add data to collection
     * 
     * @since 1.0.0
     * 
     * @param string $name
     * @param string $type
     * @param mixed $file
     * @param boolean $unique
     * 
     * @return void
     */
    public static function add( $name, $type, $file, $unique = false ) {

        static::$collection[ $name ][ $type ][] = $file;

        if ( $unique ) {
            static::$collection[ $name ][ $type ] = array_unique( static::$collection[ $name ][ $type ] );
        }

    }


    /**
     * Get collection
     *
     * @since 1.0.0
     * 
     * @param string $name
     * @param string $type
     * @param mixed $fallback default array
     * 
     * @return array
     */
    public static function get( $name, $type = null, $fallback = [] ) {

        if ( ! isset( static::$collection[ $name ] ) ) {
            return $fallback;
        }

        if ( ! is_null( $type ) ) {

            if ( isset( static::$collection[ $name ][ $type ] ) ) {
                return static::$collection[ $name ][ $type ];
            }

            return [];

        }

        return static::$collection[ $name ];

    }


    /**
     * Remove items from collection
     *
     * @param string $name
     * @param string $type
     * @return void
     * @since 1.0.0
     */
    public static function remove( $name, $type = null ) {

        if ( isset( static::$collection[ $name ] ) ) {

            if ( ! is_null( $type ) ) {

                if ( isset( static::$collection[ $name ][ $type ] ) ) {
                    unset( static::$collection[ $name ][ $type ] );
                }
    
            }
    
            unset( static::$collection[ $name ] );
        
        }

    }

}