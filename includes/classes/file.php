<?php namespace WPP;
/**
* WP Performance Optimizer - File helper
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

class File
{
    /**
    * Get absolute file path from url
    * 
    * @since 1.0.0
    * @param string $file_url
    * @return string
    */
    public static function path( $file_url ) {
        return realpath( ABSPATH . str_replace( site_url(), '', strtok( $file_url, '?#' ) ) );
    }

    /**
    * Check if file is local from given url
    * 
    * @since 1.0.0
    * @param string $file_url
    * @return boolean
    */
    public static function isLocal( $file_url ) {
        return is_file( static::path( $file_url ) );
    }

    /**
     * Get file contents
     *
     * @since 1.0.0
     * @param string $file
     * 
     * @return string
     */
    public static function get( $file ) {

        if ( file_exists( $file ) ) {
            return file_get_contents( $file );
        }
        
        return false;

    }


    /**
     * Get file contents and decode it to json.
     * Providing the file extension (.json) is not mandatory
     *
     * @since 1.0.0
     * @param string $file
     * @param boolean $assoc
     * 
     * @return array
     */
    public static function getJson( $file, $assoc = true ) {

        if( ! strstr( $file, '.json' ) ) {
            $file .= '.json';
        }

        if ( $content = static::get( $file ) ) {
            return json_decode( $content , $assoc );
        }

        return false;

    }



    /**
     * Save file content as json
     * Providing the file extension (.json) is not mandatory
     *
     * @since 1.0.0
     * @param string $file
     * @param array $data
     * 
     * @return void
     */
    public static function saveJson( $file, $data ) {

        if( ! strstr( $file, '.json' ) ) {
            $file .= '.json';
        }

        return File::save( $file, json_encode( $data ) );

    }



    /**
     * Save content to file
     *
     * @see php.net/manual/en/function.file-put-contents.php
     * 
     * @since 1.0.0
     * @param string $file
     * @param mixed $content
     * @param int $lock 
     * 
     * @return bool
     */
    public static function save( $file, $content, $lock = LOCK_EX ) {

        if ( is_null( $lock ) ) {
            return file_put_contents( $file, $content );
        }
        
        return file_put_contents( $file, $content, $lock );
    }

    /**
     * Append content to file
     *
     * @see php.net/manual/en/function.file-put-contents.php
     * 
     * @since 1.0.0
     * @param string $file
     * @param mixed $content
     * @param int $lock 
     * 
     * @return bool
     */
    public static function append( $file, $content, $lock = LOCK_EX ) {

        if ( is_null( $lock ) ) {
            return file_put_contents( $file, $content, FILE_APPEND );
        }

        return file_put_contents( $file, $content, FILE_APPEND | $lock );
    }


    /**
     * Prepend content to file
     *
     * @see php.net/manual/en/function.file-put-contents.php
     * 
     * @since 1.0.0
     * @param string $file
     * @param mixed $content
     * @param int $lock 
     * 
     * @return bool
     */
    public static function prepend( $file, $content, $lock = LOCK_EX  ) {

        $old_content = file_exists( $file ) ? file_get_contents( $file ) : '';

        if ( is_null( $lock ) ) {
            return file_put_contents( $file, $content . $old_content );
        }

        return file_put_contents( $file, $content . $old_content, $lock );

    }

}