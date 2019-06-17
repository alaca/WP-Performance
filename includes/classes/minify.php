<?php namespace WPP;
/**
* WP Performance Optimizer - Minify
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use MatthiasMullie\Minify as Minifier;

class Minify {

    /**
    * Minify code
    *  
    * @since 1.0.0
    *
    * @param string $code
    * @param string $context
    *
    * @return string
    */
    public static function code( $code, $context = null ) {

        $minifier = is_null( $context ) 
            ? new Minifier\JS( $code ) 
            : new Minifier\CSS( Minify::replacePaths( $code, $context ) );

        return $minifier->minify();

    }

    /**
     * Get resource from url and minify the code
     * Create a new file with minified code.
     * Returns the name of the new file.
     *     
     * @since 1.0.0
     * @param string $url
     * 
     * @return string
     */
    public static function resource( $url ) {

        if ( is_file( $file = File::path( $url ) ) ) {
            
            $extension = pathinfo( $file, PATHINFO_EXTENSION );  
            $filename  = md5( $file ) . '.' . $extension;

            if ( ! file_exists( $cached = WPP_CACHE_DIR . $filename ) ) {

                // Check if extension is php
                if ( $extension == 'php' ) {
                    $code = wp_remote_retrieve_body( wp_remote_get( $file ) );
                } else {
                    $code = File::get( $file );  
                }

                $minified = $extension == 'css' 
                    ? Minify::code( $code, $url ) 
                    : Minify::code( $code );

                File::save( $cached, $minified );

                touch( $cached, time() - 3600 );

            }

            return WPP_CACHE_URL . $filename;
        }

        return $url;   

    }


    /**
     * Replace relative paths with absolute paths
     *
     * @since 1.0.0
     * 
     * @param string $code
     * @param string $context
     * 
     * @return string
     */
    public static function replacePaths( $code, $context ) {

        // Process images
        preg_match_all( "/url\((\"|\')?(.*?)(\"|\')?\)/i", $code, $matches, PREG_PATTERN_ORDER );  

        foreach( $matches[2] as $i => $match) { 

            $url = str_replace( basename( $context ), '', $context ) . $match;
            $url = Url::path( File::path( $url ) ); 
            
            if ( ! empty( $url ) ) {
                $code = str_replace( $match, $url, $code );    
            }
        }

        // Process imports
        preg_match_all("/@import[ ]*['\"]*(?:url\()*['\"]*([^;'\"\)]*)['\"\)]*/ui", $code, $matches );

        foreach( $matches[1] as $i => $match) { 

            $url = str_replace( basename( $context ), '', $context ) . $match;
            $url = Url::path( File::path( $url ) ); 
            
            if ( ! empty( $url ) ) {
                $code = str_replace( $match, $url, $code );    
            }
        }

        return $code;

    }

}