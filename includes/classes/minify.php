<?php namespace WPP;
/**
* WP Performance Optimizer - Minify
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

class Minify
{

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

        // Only CSS should be context aware
        if ( $context ) {
            // Replace relative paths with absoulte paths
            $code = Minify::replacePaths( $code, $context );
            // Strips leading 0 on decimal values (converts 0.5px into .5px)
            $code = preg_replace( '/(:| )0\.([0-9]+)(%|em|ex|px|in|cm|mm|pt|pc)/i', '${1}.${2}${3}', $code );
            // Strips units if value is 0 (converts 0px to 0)
            $code = preg_replace( '/(:| )(\.?)0(%|em|ex|px|in|cm|mm|pt|pc)/i', '${1}0', $code );
            // Shortern 6-character hex color codes to 3-character where possible
            $code = preg_replace( '/#([a-f0-9])\\1([a-f0-9])\\2([a-f0-9])\\3/i', '#\1\2\3', $code );
            // Cleanup
            $code = preg_replace( '/(\/\*(.|\s)*?\*\/|(\n|\t|\r|\v|\f|\a){1,}|\s(?=\s)|(?<=})\s|(?<={)\s|\s(?={)|\s(?=})|(?<=;)\s|[[:blank:]](?=;)|(?<=:)[[:blank:]]|(?<=,)[[:blank:]])/', '', $code );

        } else {

            // Try to remove single line comments
            $code = preg_replace( '/^\/\/(?!http).*/', '', $code );
            // Replace true with !0
            $code = preg_replace( '#\btrue\b#', '!0', $code );
            // Replace false with !1
            $code = preg_replace( '#\bfalse\b#', '!1', $code );
            // Minify object attributes except JSON attributes from {'foo':'bar'} to {foo:'bar'}
            $code = preg_replace( '#([\{,])([\'])(\d+|[a-z_][a-z0-9_]*)\2(?=\:)#i', '$1$3', $code );
            // Replace foo['bar'] with foo.bar
            $code = preg_replace( '#([a-z0-9_\)\]])\[([\'"])([a-z_][a-z0-9_]*)\2\]#i', '$1.$3', $code );
            // Remove multiline comments
            $code = preg_replace('~//?\s*\*[\s\S]*?\*\s*//?~', '', $code);
            // Removes single line '//' comments, treats blank characters
            $code = preg_replace('![ \t]*//.*[ \t]*[\r\n]!', '', $code);
            //  Strip blank lines
            $code = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $code);
            // Remove space before and after , ; : * < > = && + { } ( ) 
            $code = preg_replace( '/ (\.|,|;|:|\*|<|>|=|&&|\+|\{|}|\(|\)) /', '$1', $code );

        }

        // Remove multiple new lines caharacters
        // $code = preg_replace("/[\r\n]+/" , "\n", $code);
        // Remove ; before }
        $code = preg_replace( '/;(?=\s*})/', '', $code );
        $code = preg_replace( '/[\t]+/', ' ', $code );

        return $code;

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

        preg_match_all( "/url\((\"|\')?(.*?)(\"|\')?\)/i", $code, $matches, PREG_PATTERN_ORDER );  

        foreach( $matches[2] as $i => $match) { 

            $url = str_replace( basename( $context ), '', $context ) . $match;
            $url = Url::path( File::path( $url ) ); 
            
            if ( ! empty( $url ) ) {
                $code = str_replace( $match, $url, $code );    
            }
        }

        return $code;

    }

}