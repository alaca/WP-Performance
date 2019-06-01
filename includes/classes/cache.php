<?php namespace WPP;
/**
* WP Performance Optimizer - Cache
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use DirectoryIterator;


class Cache
{

    /**
     * Save cache
     *
     * @param string $html
     * @param bool $amp
     * @since 1.0.0
     * @return void
     */
    public static function save( $html, $amp = false ) {

        $file = Cache::getFileName();

        if ( ! file_exists( $file ) ) {

            $cache_dir = dirname( $file );

            if ( ! is_dir( $cache_dir ) ) 
                mkdir( $cache_dir, 0755, true );

        }

        // Check mobile cache
        if ( Option::boolval( 'mobile_cache' ) && wp_is_mobile() ) 
            $file .= '_mobile';

        // Allow others to use this
        $content = apply_filters( 'wpp_save_cache', $html );

        /**
         * Before saving cache hook
         * @since 1.0.0
         */
        do_action( 'wpp_before_cache_save', $file, $content );

        File::save( ( $amp ) ? $file . '_amp' : $file , $content );   

        if ( function_exists( 'gzencode' ) ) {

            $content = gzencode( $content, apply_filters( 'wpp_gzencode_compression_level', 3 ) );

            File::save( ( $amp ) ? $file . '_amp_gz' : $file . '_gz' , $content );
  
        }

        /**
         * After saving cache hook
         * @since 1.0.0
         */
        do_action( 'wpp_after_cache_save', $file, $content );

        wpp_log( sprintf( 'Cache saved for URL %s', Url::current() ) );

    }
   

    /**
     * Clar cache
     *
     * @since 1.0.0
     * @param boolean $preload
     * @return void
     */
    public static function clear( $preload = true ) {

        /**
         * Hook fired right before deleting the cache files
         * @since 1.0.0
         */
        do_action( 'wpp-before-cache-delete' );

        $cache_dir = WPP_CACHE_DIR;

        if ( is_multisite() ) {

            $uri = parse_url( get_bloginfo( 'url' ) );

            $cache_dir = WPP_CACHE_DIR . $uri[ 'host' ] . $uri[ 'path' ];

            // Delete minified CSS and JS files in root cache directory
            $root_files = new DirectoryIterator( WPP_CACHE_DIR );
    
            foreach ( $root_files as $file ) {
    
                if ( $file->isFile() ) {

                    if ( 
                        ! in_array( $file->getExtension(), [ 'log', 'json' ] ) 
                        && $file->getFilename() != 'index.php' 
                    ) 
                        unlink( $file->getPathname() );
                    
                }
    
            }

        } 

        if ( ! is_dir( $cache_dir ) )
            return false;

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $cache_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ( $files as $file ) {

            if ( $file->isDir() ) {
                    
                rmdir( $file->getRealPath() );

            } else {

                if ( 
                    $file->getFilename() != 'index.php' 
                    && ! strstr( $file->getFilename(), 'wpp.json' ) 
                    && ! strstr( $file->getFilename(), 'settings.json' ) 
                ) {
                    unlink( $file->getRealPath() );
                }
                
            }

        }

        wpp_log( 'Cache deleted' );

        /**
         * Hook fired right after deleting the cache files
         * @since 1.0.0
         */
        do_action( 'wpp-after-cache-delete' );

        if ( $preload ) wpp_preload_homepage(); 

    }


    /**
     * Delete everything in WPP cache directory and after that delete directory itself
     *
     * @return void
     * @since 1.1.1
     */
    public static function clearEverything() {

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( WPP_CACHE_DIR, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ( $files as $file ) {

            if ( $file->isDir() ) {
                rmdir( $file->getRealPath() );
            } else {
                unlink( $file->getRealPath() );
            }

        }

        // Remove wpp cache dir
        rmdir( WPP_CACHE_DIR );

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

        if ( get_option( 'permalink_structure', true ) ) {

            if ( ! empty( $_GET ) ) {
                $uri = parse_url( Input::server( 'REQUEST_URI' ) );
                return WPP_CACHE_DIR . Input::server( 'HTTP_HOST' ) . $uri[ 'path' ] . md5( $uri[ 'query' ] ) . '.html';
            }

            return WPP_CACHE_DIR . trailingslashit( Input::server( 'HTTP_HOST' ) .  Input::server( 'REQUEST_URI' ) ) . 'index.html';

        } else {
            
            $name = is_null( $url ) ? Url::current() : $url;
            return WPP_CACHE_DIR . trailingslashit( Input::server( 'HTTP_HOST' ) ) . md5( $name ) . '.html';

        }

    }

}