<?php namespace WPP;
/**
* WP Performance Optimizer - Instance Trait
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

trait Instance
{

    /**
     * Class instance
     *
     * @var class
     * @since 1.1.0
     */
    private static $instance;

    /**
     *  Get class instance
     *
     * @return class
     * @since 1.1.0
     */
    public static function instance() {

        if ( is_null( static::$instance ) ) {
            static::$instance = new static();    
        }
        
        return static::$instance;
        
    }

}