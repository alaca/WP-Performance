<?php 
/**
* WP Performance Optimizer - File helpers
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use WPP\File;

/**
 * Get clean file name
 * 
 * @since 1.0.0
 * @return string
 */
function wpp_get_file_clean_name( $file ) {
    return sanitize_title( urldecode( $file ) );
}




/**
 * Get cache size for given file type extension
 * 
 * @since 1.0.0
 * @param string $type
 * @return int
 */
function wpp_cache_files_size( $type ) {

    $size = 0;

    $files = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator( WPP_CACHE_DIR, \RecursiveDirectoryIterator::SKIP_DOTS ),
        \RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ( $files as $file ) {

        if ( $file->isFile() ) {

            if ( $file->getExtension() == $type ) {
                $size += $file->getSize();
            }

        }
    }

    return $size;

}


/**
 * Get human readable file size
 *
 * @since 1.0.0
 * @param integer $bytes
 * @param integer $decimals
 * @return string
 */
function wpp_filesize( $bytes, $decimals = 2 ) {

    $size   = array( 'B', 'KB', 'MB', 'GB', 'TB' );
    $factor = floor( ( strlen( $bytes ) - 1 ) / 3 );

    return sprintf( "%.{$decimals}f", $bytes / pow( 1024, $factor ) ) . @$size[ $factor ];
}


/**
 * Guess if file is minified
 *
 * This function will try to guess if file is already minified by checking the file name
 * Most of the time minified files have .min.* extension, so we check for that in file name
 * Also the function will return true for every file located in wp-includes directory
 * 
 * @since 1.0.0
 * @param string $file
 * @return boolean
 */
function wpp_is_minified( $file ) {
    
    if ( strstr( $file, '.min.' ) || strstr( $file, 'wp-includes/' ) ) {
        return true;
    }

    return false;

}


/**
 * Check if htaccess file is writable
 *
 * @return boolean
 * @since 1.0.0
 */
function wpp_is_htaccess_writable() {
    return is_writable( trailingslashit( ABSPATH ) . '.htaccess' );
}


/**
 * Add remove definitions from htaccess file
 *
 * @since 1.0.0
 * @param string $action
 * @param string $file
 * @return void
 */
function wpp_update_htaccess( $action, $file ) {

    $htaccess              = ABSPATH. '.htaccess';
    $definitionsFile       = WPP_DATA_DIR . 'definitions/' . $file . '.apache.txt';
    $customDefinitionsFile = WPP_DATA_DIR . 'definitions/' . 'custom.' . $file . '.apache.txt';

    // Use custom definitions if exists
    if ( file_exists( $customDefinitionsFile ) ) {
        $definitionsFile = $customDefinitionsFile;
    }

    if ( ! file_exists( $definitionsFile ) ) {

        wpp_log( sprintf( '%s not exists', $definitionsFile ) );

        return false;
    }


    switch ( $action ) {

        case true:
        case 'add':

            if ( ! file_exists( $htaccess ) ) {
                
                touch( $htaccess );

            } else {

                if ( ! wpp_is_htaccess_writable() ) {

                    wpp_log( '.htaccess file not writable' );

                    return false;
                }

            }
    
            $original    = File::get( $htaccess );
            $definitions = str_replace( '{BASEDIR}', wpp_get_basedir(), File::get( $definitionsFile ) );
    
            if ( ! strstr( $original, $definitions ) ) {

                if ( $file == 'cache' ) {
                    File::prepend( $htaccess, $definitions );
                } else {
                    File::append( $htaccess, $definitions );
                }
                
            }


            break;

        case false:
        case 'remove':

            if ( file_exists( $htaccess ) ) {

                $original    = File::get( $htaccess );
                $definitions = str_replace( '{BASEDIR}', wpp_get_basedir(), File::get( $definitionsFile ) );
    
                $content = str_replace( $definitions, '', $original );

                File::save( $htaccess, $content );
    
            }

            break;
    }

}


/**
 * Get site base directory 
 *
 * @return string
 * @since 1.0.0
 */
function wpp_get_basedir() {

    $root_dir = basename( $_SERVER[ 'DOCUMENT_ROOT' ] );
    $base_dir = basename( ABSPATH );

    if ( $root_dir != $base_dir ) {
        return sprintf( '/%s/', $base_dir );
    }

    return '/';

}


/**
 * Check if log file is too large
 *
 * @return void
 * @since 1.0.0
 */
function wpp_is_log_file_too_large() {

    /**
     * Set log size filter
     * @since 1.0.0
     */
    $log_size = apply_filters( 'wpp-log-size-kb', 20 );

    if ( file_exists( $log_file = wpp_get_log_file() ) ) {
        if ( filesize( $log_file ) > ( intval( $log_size ) * 1024 ) ) {
            return true;
        }
    }

    return false;

}