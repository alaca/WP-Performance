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
        return $host;
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


/**
 * Check if url is found in excluded urls list
 *
 * @param string $url
 * @param array $excluded_urls
 * @return boolean
 * @since 1.0.9
 */
function wpp_is_url_excluded( $url, $excluded_urls ) {

    foreach( $excluded_urls as $excluded_url ) {

        $excluded_url = trailingslashit( wpp_url_replace_wildcards( $excluded_url ) );

        // Try simple match first
        if ( $excluded_url == $url ) {
            return true;
        }

        // if ( stristr( $url, $excluded_url ) ) {
        //    return true;
        // }

        preg_match( '#^' . $excluded_url . '$#', trailingslashit( $url ), $match );

        if ( isset( $match[0] ) ) {
            return true;
        }

    }

    return false;
}