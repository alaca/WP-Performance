<?php
/**
* WP Performance Optimizer - Cache loader
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/


if ( ! empty( $_POST ) ) {
    return false;
}

// Get settings
$settings = _wpp_get_site_settings();

// Check if cache option is ON and cache is not disabled
if ( empty( $settings) || ! $settings[ 'cache' ] || $settings[ 'disable' ] )
    return false;

// Get cache file and check if is readble
$cache_file = _wpp_get_cache_file( $settings );

if ( ! file_exists( $cache_file ) )
    return false;

// Check excluded URLs
if ( ! empty( $settings[ 'exclude' ] ) ) {

    $current_url = _wpp_get_current_url();

    $wildcards = [
        '{any}'     => '[^/]+',
        '{numbers}' => '[0-9]+',
        '{letters}' => '[A-Za-z]+',
        '{all}'     => '.*'
    ];

    foreach( $settings[ 'exclude' ] as $excluded_url ) {

        $excluded_url = preg_quote( $excluded_url );

        $excluded_url = str_replace( 
            array_keys( $wildcards ), 
            array_values( $wildcards ), 
            stripslashes( $excluded_url ) 
        );
    
        // Try simple match first
        if ( $excluded_url === $current_url ) {
            return false;
        }

        if ( preg_match( '#^' . $excluded_url . '$#', $current_url ) ) {
            return false;
        }

    }

}

// Check WP cookies
if ( ! empty( $_COOKIE ) ) {

    $cookies = '/^(wordpress_logged_in_|wp-postpass_|wptouch_switch_toggle|comment_author_|comment_author_email_)/';

    foreach ( $_COOKIE as $cookie => $value ) {
        if ( preg_match( $cookies, $cookie ) ) {
            return false;
        }
    }

}

// Check if cache file has expired
if ( time() - intval( $settings[ 'expire' ] ) < filemtime( $cache_file ) ) {

    header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', filemtime( $cache_file ) ) . ' GMT' );

    // Getting If-Modified-Since headers
    if ( function_exists( 'apache_request_headers' ) ) {
        $apache_headers = apache_request_headers();
        $modified_since = isset( $apache_headers[ 'If-Modified-Since' ] ) ? $apache_headers[ 'If-Modified-Since' ] : '';
        $accept_encoding = isset( $headers[ 'Accept-Encoding' ] ) ? $headers[ 'Accept-Encoding' ] : '';
    } else {
        $modified_since = isset( $_SERVER[ 'HTTP_IF_MODIFIED_SINCE' ] ) ? $_SERVER[ 'HTTP_IF_MODIFIED_SINCE' ] : '' ;
        $accept_encoding = isset( $_SERVER[ 'HTTP_ACCEPT_ENCODING' ] ) ? $_SERVER[ 'HTTP_ACCEPT_ENCODING' ] : '';
    }

    // Check headers
    if ( 
        ! empty( $modified_since ) 
        && ( strtotime( $modified_since ) === filemtime( $cache_file ) ) 
    ) {
        // Client's cache is up to date
        header( $_SERVER[ 'SERVER_PROTOCOL' ] . ' 304 Not Modified', true, 304 );
        exit;
    }

    // Check if gzip is enabled
    if ( 
        false !== strpos( $accept_encoding, 'gzip' ) 
        && function_exists( 'readgzfile' ) 
        && file_exists( $cache_file . '.gz' )
    ) {
        readgzfile( $cache_file . '.gz' );
    } else {
        include $cache_file;
    }

    exit;

}


/**
 * Get cache file path
 *
 * @return string
 */
function _wpp_get_cache_file( $settings ) {

    $file = WP_CONTENT_DIR . '/cache/wpp-cache/' . $_SERVER[ 'HTTP_HOST' ] ;

    // Check if site is using permalinks
    if ( $settings[ 'permalinks' ] ) {

        if ( ! empty( $_GET ) ) {
            $uri = parse_url( $_SERVER[ 'REQUEST_URI' ] );
            $file .= $uri[ 'path' ] . md5( $uri[ 'query' ] ) . '.html';
        } else {
            $file .=  $_SERVER[ 'REQUEST_URI' ] . 'index.html';
        }
        
    } else {
        $file .= '/' . md5( _wpp_get_current_url() ) . '.html';
    }
    
    // Is mobile device and mobile cache is ON
    if ( $settings[ 'mobile_cache' ] && _wpp_is_mobile() ) {
        $file .= '.mobile';
    }

    // Is AMP? Why not save this as mobile?
    $amp_tag = defined( 'WPP_AMP_TAG' ) ? WPP_AMP_TAG : 'amp';

    if ( 
        isset( $_GET[ $amp_tag ] ) 
        || preg_match( '/' . $amp_tag . '$/', _wpp_get_current_url() ) 
    ) {
        $file .= '.amp';
    }

    return $file;
}


/**
 * Get site settings
 *
 * @return array
 */
function _wpp_get_site_settings() {

    $settings_file = sprintf(
        '%s/%s.settings.json',
        WP_CONTENT_DIR . '/cache/wpp-cache',
        $_SERVER[ 'HTTP_HOST' ]
    );

    if ( file_exists( $settings_file ) ) {
        return json_decode( 
            file_get_contents( $settings_file ), 
            true 
        ); 
    }

    return [];

}


/**
 * Check if is mobile device
 *
 * @return bool
 */
function _wpp_is_mobile() {

    if ( empty( $_SERVER[ 'HTTP_USER_AGENT' ] ) ) {
        return false;
    } 
    
    if ( strpos( $_SERVER[ 'HTTP_USER_AGENT' ], 'Mobile' ) !== false 
        || strpos( $_SERVER[ 'HTTP_USER_AGENT' ], 'Android' ) !== false
        || strpos( $_SERVER[ 'HTTP_USER_AGENT' ], 'Silk/' ) !== false
        || strpos( $_SERVER[ 'HTTP_USER_AGENT' ], 'Kindle' ) !== false
        || strpos( $_SERVER[ 'HTTP_USER_AGENT' ], 'BlackBerry' ) !== false
        || strpos( $_SERVER[ 'HTTP_USER_AGENT' ], 'Opera Mini' ) !== false
        || strpos( $_SERVER[ 'HTTP_USER_AGENT' ], 'Opera Mobi' ) !== false 
    ) {
        return true;
    } 

    return false;
}


/**
 * Get current URL
 *
 * @return string
 */
function _wpp_get_current_url() {

    // Get URL protocol
    $protocol = ( ! isset( $_SERVER[ 'HTTPS' ] ) || $_SERVER[ 'HTTPS' ] !== 'on' )  ? 'http' : 'https';

    return $protocol . '://' . $_SERVER[ 'HTTP_HOST' ] . $_SERVER[ 'REQUEST_URI' ];
    
}