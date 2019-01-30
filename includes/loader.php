<?php defined('ABSPATH') or exit; 
/**
* WP Performance Optimizer - Functions & Addons loader
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

foreach( [ WPP_FUNCTIONS_DIR . '*.php', WPP_ADDONS_DIR . '*/init.php' ] as $dir ) {

    foreach( glob( $dir ) as $file ) {
        include_once $file;
    }

}
