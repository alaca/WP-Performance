<?php 
/**
* WP Performance Optimizer - Url helpers
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/


/**
 * Check if site is on localhost or .dev domain
 *
 * @return boolean
 * @since 1.0.0
 */
function wpp_is_localhost() {

    $host = parse_url( site_url(), PHP_URL_HOST );

	if ( 'localhost' === $host || pathinfo( $host, PATHINFO_EXTENSION ) === 'dev' ) {
	    return true;
    }
    
    return false;

}

/**
 * Get file hostname
 * 
 * @since 1.0.0
 * @return string
 */
function wpp_get_file_hostname( $file ) {

    if ( $host = parse_url( $file, PHP_URL_HOST ) ) {
        return '//' .  $host;
    }

    return $file;

}



/**
 * Replace wildcards with regex pattern
 * 
 * @since 1.0.0
 * @return string
 */
function wpp_url_replace_wildcards( $pattern ) {

    $pattern = preg_quote( $pattern );

    $wildcards = [
        '{any}'     => '[^/]+',
        '{numbers}' => '[0-9]+',
        '{letters}' => '[A-Za-z]+',
        '{all}'     => '.*'
    ];

    return str_replace( array_keys( $wildcards ), array_values( $wildcards ), stripslashes( $pattern ) );

}