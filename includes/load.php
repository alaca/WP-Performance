<?php defined('ABSPATH') or exit; 
/**
* WP Performance Optimizer - Functions loader
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

foreach( glob( WPP_FUNCTIONS_DIR . '*.php' ) as $function ) {
    include_once $function;
}