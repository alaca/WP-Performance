<?php namespace WPP;
/**
* WP Performance Optimizer - Cache
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;


class Cache
{

    /**
    * Load cache
    * 
    * @since 1.0.0
    * @return void
    */
    public static function load() {

        $excluded = Option::get( 'cache_url_exclude', [] );

        if ( empty( $_POST ) && ! wpp_in_array( $excluded, Url::current() ) ) {

            $file = Cache::getFileName();
            $gzip = false !== strpos( $_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip' );

            // Check is mobile
            if ( Option::boolval( 'mobile_cache' ) && wp_is_mobile() ) {
                $file .= '_mobile';
            }

            // GZIP enabled ?
            if ( $gzip ) $file .= '_gzip';

            if ( file_exists( $file ) && is_readable( $file ) ) {

                if ( time() - intval( Option::get( 'cache_time', 3600 ) * Option::get( 'cache_length', 24 ) ) < filemtime( $file ) ) { 

                    header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', filemtime( $file ) ) . ' GMT' );

                    // Getting If-Modified-Since headers
                    if ( function_exists( 'apache_request_headers' ) ) {
                        $apache_headers = apache_request_headers();
                        $modified_since = isset( $apache_headers[ 'If-Modified-Since' ] ) ? $apache_headers[ 'If-Modified-Since' ] : '';
                    } else {
                        $modified_since = isset( $_SERVER[ 'HTTP_IF_MODIFIED_SINCE' ] )  ? $_SERVER[ 'HTTP_IF_MODIFIED_SINCE' ] : '';
                    }

                    // Check cache
                    if ( 
                        ! empty( $modified_since ) 
                        && ( strtotime( $modified_since ) === filemtime( $file ) ) 
                    ) {
                        // Client's cache is up to date
                        header( $_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified', true, 304 );
                        exit;
                    }

                    if ( $gzip && function_exists( 'readgzfile' ) ) {
                        readgzfile( $file );
                    } else {
                        include $file;
                    }

                    wpp_log( sprintf( 'Loaded cache for page %s', Url::current() ), 'notice' );

                    exit;

                }

            }

        }

    }



    /**
     * Save cache
     *
     * @param string $html
     * @since 1.0.0
     * @return void
     */
    public static function save( $html ) {

        $file = Cache::getFileName();

        if ( 
            get_option( 'permalink_structure', false ) 
            && empty( $_GET ) 
            && ! file_exists( $file ) 
        ) {

            $cache_dir = dirname( $file );

            if ( ! is_dir( $cache_dir ) ) {
                mkdir( $cache_dir, 0775, true );
            }

        }

        // Check mobile cache
        if ( Option::boolval( 'mobile_cache' ) && wp_is_mobile() ) {
            $file .= '_mobile';
        }

        // Allow others to use this
        $content = apply_filters( 'wpp_save_cache', $html );

        /**
         * Before saving cache hook
         * @since 1.0.0
         */
        do_action( 'wpp_before_cache_save', $file, $content );

        File::save( $file, $content );

        if ( function_exists( 'gzencode' ) ) {
            File::save( $file . '_gzip', gzencode( $content, apply_filters( 'wpp_gzencode_compression_level', 3 ) ) );
        }

        /**
         * After saving cache hook
         * @since 1.0.0
         */
        do_action( 'wpp_after_cache_save', $file, $content );

        include $file;

        wpp_log( sprintf( 'Cache saved for URL %s', Url::current() ), 'notice' );

        exit;

    }
   

    /**
     * Clar cache
     *
     * @since 1.0.0
     * @param boolean $preload
     * @return void
     */
    public static function clear( $preload = true ) {

        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            check_ajax_referer( 'wpp-ajax', 'nonce' );
        }

        /**
         * Hook fired right before deleting the cache files
         * @since 1.0.0
         */
        do_action( 'wpp-before-cache-delete' );

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( WPP_CACHE_DIR, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ( $files as $file ) {

            if ( $file->isDir() ) {
                    
                rmdir( $file->getRealPath() );

            } else {

                if ( $file->getFilename() != 'index.php' ) {
                    unlink( $file->getRealPath() );
                }
                
            }

        }

        wpp_log( 'Cache deleted', 'notice' );

        /**
         * Hook fired right after deleting the cache files
         * @since 1.0.0
         */
        do_action( 'wpp-after-cache-delete' );

        if ( $preload ) wpp_preload_homepage(); 

    }


    /**
     * Check if cache file exists
     *
     * @param string $url
     * @return boolean
     * @since 1.0.0
     */
    public static function exists( $url ) {
        return file_exists( Cache::getFileName( $url ) );
    }


    /**
     * Get cache file name
     *
     * @param string|null $url
     * @return string
     * @since 1.0.0
     */
    private static function getFileName( $url = null ) {

        if ( get_option( 'permalink_structure', true ) && empty( $_GET ) ) {

            return WPP_CACHE_DIR . trailingslashit( $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ) . 'index.html';

        } else {

            $name = is_null( $url ) ? Url::current() : $url;

            return WPP_CACHE_DIR . md5( $name ) . '.html';

        }

    }

}