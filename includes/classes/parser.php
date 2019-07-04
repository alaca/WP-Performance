<?php namespace WPP;
/**
* WP Performance Optimizer - Parser
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

class Parser
{
    private static $instance;
    private $html;
    private $time;
    private static $amp = null;
    
    private function __construct( $template ) {

        if ( ! empty( $template ) ) {

            $this->time = microtime( true ); 

            // load template
            $this->html = new HtmlDOM( $template, false, false );

            if ( $this->html ) {

                $this->htmltag = $this->html->find( 'html', 0 );
                $this->body = $this->html->find( 'body', 0 );
                $this->head = $this->html->find( 'head', 0 );
                
                // Do not parse amp templates
                if ( ! $this->is_amp() )
                    $this->parseTemplate();

                // Should we cache this page ?
                if ( 
                    Option::boolval( 'cache' ) 
                    && empty( $_POST ) 
                    && ! is_user_logged_in() 
                ) {
                    $this->saveCache();
                }

                return $this->html;

            }

        }

        return $template;

    }
    
    /**
     * Initialize
     *
     * @param string $template
     * @return Parser 
     */
    public static function init( $template ) {

        if ( is_null( static::$instance ) ) {
            static::$instance = new static( $template );    
        }
        
        return static::$instance;
    }

    /**
     * Parse template
     *
     * @return void
     * @since 1.0.9
     */
    private function parseTemplate() {

        // Check for head and body tag
        if ( ! $this->head || ! $this->body ) {

            // Check if is XML file
            $headers = headers_list();

            foreach( $headers as $header ) {

                if ( strstr( $header, 'text/xml' ) ) {
                    return $this->html;
                }

            }
    
            $this->html .= sprintf( 
                '<!-- %s %s: %s -->', 
                WPP_PLUGIN_NAME, 
                __( 'error', 'wpp' ), 
                __( 'both head and body tag should be present in template file', 'wpp' )
            );

            wpp_log( __( 'both head and body tag should be present in template file', 'wpp' ) ); 

            return $this->html;

        }

        // CSS optimization

        /**
         * Exclude URL from CSS optimization filter
         * @since 1.0.3
         */
        $css_url_exclude = apply_filters( 'wpp_css_url_exclude', Option::get( 'css_url_exclude', [] ) );

        if ( 
            ! wpp_is_optimization_disabled_for( 'css' ) 
            && ! wpp_is_url_excluded( Url::current(), $css_url_exclude ) 
        ) {
            $this->parseCSS();
        }

        // JS optimization

        /**
         * Exclude URL from JavaScript optimization filter
         * @since 1.0.3
         */
        $js_url_exclude = apply_filters( 'wpp_js_url_exclude', Option::get( 'js_url_exclude', [] ) );

        if ( 
            ! wpp_is_optimization_disabled_for( 'js' ) 
            && ! wpp_is_url_excluded( Url::current(), $js_url_exclude ) 
        ) {
            $this->parseJS();
        }

        // Images optimization
        
        /**
         * Exclude URL from Images optimization filter
         * @since 1.0.4
         */
        $image_url_exclude = apply_filters( 'wpp_image_url_exclude', Option::get( 'image_url_exclude', [] ) );

        if ( ! wpp_is_url_excluded( Url::current(), $image_url_exclude ) ) 
            $this->parseImages();

        /**
         * Exclude URL from iframe lazyload filter
         * @since 1.1.6
         */
        $video_url_exclude = apply_filters( 'wpp_video_url_exclude', Option::get( 'video_url_exclude', [] ) );

        if ( 
            ! wpp_is_url_excluded( Url::current(), $video_url_exclude ) 
            && Option::boolval( 'videos_lazy' ) 
        ) {
            $this->parseIframes();
        }

        // Rebuild the template
        $this->buildTemplate(); 

    }
    
    
    /**
    * Parse CSS
    * 
    * @return void
    */
    private function parseCSS() {

        // Add skip attribute to links in noscript
        foreach( $this->html->find( 'noscript link[rel=stylesheet]' ) as $link ) {
            $link->{'data-skip'} = 'true';
        }        
        // Minify inline CSS?
        if ( Option::boolval( 'css_minify_inline' ) ) {
            
            foreach ( $this->html->find( 'style' ) as $style ) {
                $style->innertext = Minify::code( $style->innertext, Url::current() );
            }

        }

            
        // Parse link stylesheets
        foreach ( $this->html->find( 'link[rel=stylesheet]' ) as $link ) {

            if (
                $link->{'data-skip'} == 'true' 
                || strstr( $link->href, WPP_ASSET_URL )
                || strstr( $link->href, WPP_ADDONS_URL )
                || ( is_user_logged_in() && strstr( $link->href, 'wp-includes' ) )
            ) {
                continue;
            }
                        
            $href = Url::getClean( $link->href );
            
            if ( File::isLocal( $href ) ) {

                if ( $link->media !== 'print' ) 
                    Collection::add( 'critical', 'css', $href );
                
                // Is this a plugin file ?
                if ( strstr( $link->href, plugins_url() ) ) {
                    Collection::add( 'plugin', 'css', $href );
                } else {
                    Collection::add( 'theme', 'css', $href );
                }
                
                // Check if resource is disabled
                if ( wpp_is_resource_disabled( 'css', $href ) ) {
                    $link->outertext = ''; 
                    continue;
                }

            } else {

                if ( $link->href && ! strstr( $link->href, site_url() ) ) {

                    Collection::add( 'external', 'css', $link->href );

                    // Check if external resource is disabled
                    if ( wpp_is_resource_disabled( 'css', wpp_get_file_clean_name( $link->href ) ) ) {

                        $link->outertext = ''; 
                        continue;

                    } else {

                        Collection::add( 'prefetch', 'css', wpp_get_file_hostname( $link->href ), true );
                        
                    }


                    // Combine Google fonts
                    if ( Option::boolval( 'css_combine_fonts' ) ) {

                        if ( strstr( $link->href, 'fonts.googleapis.com' ) ) {

                            list( , $url ) = explode( 'family=', $link->href );

                            Collection::add( 'combine', 'google_fonts', html_entity_decode( urldecode( $url ) ), true );

                            $link->outertext = ''; 
                            
                            continue;

                        }

                    }

                }                  

            }


            // Inline CSS
            if ( wpp_in_array( array_keys( Option::get( 'css_inline', [] ) ), $href ) ) {

                if ( File::isLocal( $href ) ) {

                    $code = File::get( File::path( $href ) );

                    // Minify file
                    if ( wpp_in_array( array_keys( Option::get( 'css_minify', [] ) ), $href ) ) {
                        $code = Minify::code( $code, $href );
                    } else {
                        $code = Minify::replacePaths( $code, $href );
                    }

                    $link->outertext = '<style data-wpp-inline="' . $href . '">' . $code . '</style>'; 

                    continue;
                    
                }
            
            }

            
            // Combine CSS
            if ( Option::boolval( 'css_combine' ) ) {

                if ( File::isLocal( $href ) ) {

                    if ( wpp_in_array( array_keys( Option::get( 'css_combine' ) ), $href ) ) {

                        Collection::add( 'combine', 'css', [
                            'href'  => $href,
                            'media' => !$link->media ? 'all' : $link->media
                        ] );

                        $link->outertext = ''; 
                        
                        continue;

                    } 

                }

            }  

            // Minify CSS
            if ( 
                Option::boolval( 'css_minify' ) 
                && File::isLocal( $href ) 
            ) {
                if ( wpp_in_array( array_keys( Option::get( 'css_minify' ) ), $href ) ) {
                    $link->href = Minify::resource( $href );
                }
            }
            
            // Defer CSS
            if ( Option::boolval( 'css_defer', false ) ) {

                Collection::add( 'defer', 'css', $link->outertext );
            
                $link->rel = 'preload';
                $link->as = 'style';
                $link->onload = "this.rel='stylesheet'";
            }

            // CDN
            if ( 
                Option::boolval( 'cdn' ) 
                && File::isLocal( $href ) 
                && ! wpp_in_array( Option::get( 'cdn_exclude', [] ), $href )
            ) {
                $link->href = str_replace( site_url(), Option::get( 'cdn_hostname'), $link->href ); 
            }

        }
        
    }
    
    /**
    * Parse JavaScript
    * 
    * @return void
    */
    private function parseJS() {

        // Parse scripts
        foreach ( $this->html->find( 'script' ) as $script ) { 

            if (
                $script->type == 'application/ld+json' 
                || $script->{'data-skip'} == 'true' 
                || strstr( $script->src, WPP_ASSET_URL )
                || strstr( $script->src, WPP_ADDONS_URL )
                || ( is_user_logged_in() && strstr( $script->src, 'wp-includes' ) )
            ) {
                continue;
            }
            

            $src = Url::getClean( $script->src );
            
            if ( File::isLocal( $src ) ) {

                // Is this a plugin file ?
                if ( strstr( $script->src, plugins_url() ) ) {
                    Collection::add( 'plugin', 'js', $src );
                } else {
                    Collection::add( 'theme', 'js', $src );
                }       

                // Check if resource is disabled
                if ( wpp_is_resource_disabled( 'js', $src ) ) {
                    $script->outertext = ''; 
                    continue;
                }

            } else {

                if ( $script->src && ! strstr( $script->src, site_url() ) ) {

                    Collection::add( 'external', 'js', $script->src );

                    // Check if external resource is disabled
                    if ( wpp_is_resource_disabled( 'js', wpp_get_file_clean_name( $script->src ) ) ) {

                        $script->outertext = ''; 
                        continue;

                    } else {

                        Collection::add( 'prefetch', 'js', wpp_get_file_hostname( $script->src ), true );

                    }   

                }     

            }

            
            // Inline JS
            if ( wpp_in_array( array_keys( Option::get( 'js_inline', [] ) ), $src ) ) {

                if ( File::isLocal( $src ) ) {

                    $code = File::get( File::path( $src ) );

                    // Minify file
                    if ( wpp_in_array( array_keys( Option::get( 'js_minify', [] ) ), $src ) ) {
                        $code = Minify::code( $code );
                    }

                    // Defer ?
                    $type = Option::boolval( 'js_defer' ) ? 'wppscript' : 'javascript';

                    $script->outertext = '<script type="text/' . $type . '" data-wpp-inline="' . $src . '">' . $code . '</script>';   

                    continue;
                    
                }
            
            }

            // Combine JS
            if ( wpp_in_array( array_keys( Option::get( 'js_combine', [] ) ), $src ) ) {

                if ( File::isLocal( $src ) ) {
                    
                    Collection::add( 'combine', 'js', [ 
                        'file' => $src 
                    ] );

                    $script->outertext = ''; 

                } elseif ( ! $script->src) {

                    Collection::add( 'combine', 'js', [ 
                        'code' => $script->innertext
                    ] );

                    $script->outertext = '';  
                    
                } 
                
                continue;

            }


            // Minify JS
            if ( File::isLocal( $src ) ) {

                if ( wpp_in_array( array_keys( Option::get( 'js_minify', [] ) ), $src ) ) {
                    $script->src = Minify::resource( $src );
                }

            } elseif ( ! $script->src && Option::get( 'js_minify_inline', false ) ) {

                $script->innertext = Minify::code( $script->innertext );
                
            } 

            // CDN
            if ( 
                Option::boolval( 'cdn' ) 
                && File::isLocal( $src ) 
                && ! wpp_in_array( Option::get( 'cdn_exclude', [] ), $src )
            ) {

                if ( $script->src ) {
                    $script->src = str_replace( site_url(), Option::get( 'cdn_hostname'), $script->src ); 
                }
               
            }
            
            // Defer JS
            if ( Option::boolval( 'js_defer' ) ) {
                // set type
                $script->type = 'text/wppscript';
                // change src
                if ($script->src) {
                    $script->{'data-src'} = $script->src;  
                    $script->removeAttribute( 'src' );  
                }
                               
            }
        }
        
    }

    /**
     * Parse images
     *
     * @return void
     */
    private function parseImages() {

        $upload = wp_upload_dir();

        // Check if images in specific containers are excluded
        if ( ! empty( $containers = Option::get( 'images_containers_ids', [] ) ) ) {

            $containers = array_map( 'trim', $containers );

            foreach( $containers as $container ) {

                if ( empty( $container ) ) continue;

                foreach( $this->html->find( $container . ' img' ) as $img ) {
                    $img->{'data-skip'} = 'true';
                }

            }
        }

        $images   = [];
        $excluded = Option::get( 'images_exclude', [] );

        // Get images
        foreach( $this->html->find( 'img' ) as $img ) {

            // skip image if it has skip attribute or src is empty and its excluded from admin
            if ( 
                $img->{'data-skip'} === 'true' 
                || ! $img->src 
                || wpp_in_array( $excluded, $img->src ) 
            ) {
                continue;
            }

            $images[] = $img;

        }

        // load responsive images
        if ( 
            Option::boolval( 'images_resp' ) 
            && ! empty( $images ) 
        ) {

            foreach( $images as $img ) {

                // only images from media library
                if ( strstr( $img->src, $upload[ 'baseurl' ] ) ) {

                    // check image width
                    if ( ! file_exists( $image = File::path( $img->src ) ) ) {
                        wpp_log( sprintf( 'Image %s is found on site, but looks like it does not exists', $img->src ) );
                        continue;
                    }

                    $size = @getimagesize( $image );

                    // skip images smaller than 640px width
                    if ( isset( $size[ 0 ] ) && $size[ 0 ] < 640 ) {
                        continue;
                    }

                    $variations = Image::getAllVariations( $img->src );

                    if ( ! empty( $variations ) ) {

                        // Remove attributes
                        $img->removeAttribute( 'sizes' );
                        $img->removeAttribute( 'width' );
                        $img->removeAttribute( 'height' );
                        $img->srcset = '';

                        foreach ( $variations as $width => $image ) {
                            $img->srcset .=  sprintf( '%s %sw,', $image, $width ); 
                        }  
                        
                        $img->srcset = rtrim( $img->srcset, ',' );

                    }

                }

            }

        }

        // lazy load images
        if ( 
            Option::boolval( 'images_lazy' ) 
            && ! empty( $images ) 
        ) {

            // If lazy load is disabled for mobile devices
            if ( wp_is_mobile() && Option::boolval( 'disable_lazy_mobile' ) ) {
                return false;
            }
  
            foreach( $images as $img ) {

                $img->loading = 'lazy';
                $img->{'data-srcset'} = $img->srcset;
                $img->{'data-src'} = $img->src;

                $img->removeAttribute( 'srcset' );

                $img->src = WPP_ASSET_URL . 'placeholder.png';

            }

        }

        // CDN
        if ( 
            Option::boolval( 'cdn' ) 
            && Option::boolval( 'cdn_hostname') 
            && ! empty( $images ) 
        ) {

            foreach( $images as $img ) {

                if ( strstr( $img->src, $upload[ 'baseurl' ] ) || strstr( $img->src, 'data:image' )   ) {

                    if ( wpp_in_array( Option::get( 'cdn_exclude', [] ), $img->src ) ) {
                        continue;
                    }

                    $img->src = str_replace( site_url(), Option::get( 'cdn_hostname'), $img->src );

                    // Responsive
                    if ( Option::boolval( 'images_resp' )  ) {
                        $img->srcset = str_replace( site_url(), Option::get( 'cdn_hostname'), $img->srcset );
                    }

                    // Lazy
                    if ( Option::boolval( 'images_lazy' ) ) {
                        $img->{'data-src'}    = str_replace( site_url(), Option::get( 'cdn_hostname'), $img->{'data-src'} );
                        $img->{'data-srcset'} = str_replace( site_url(), Option::get( 'cdn_hostname'), $img->{'data-srcset'} );
                    }

                }

            }

        }


    }


    /**
     * Parse iframes
     *
     * @return void
     */
    private function parseIframes() {

        // lazy load iframes
        foreach( $this->html->find( 'iframe' ) as $iframe ) {

            // skip iframe if it has skip attribute or src is empty
            if ( 
                $iframe->{'data-skip'} === 'true' 
                || ! $iframe->src 
            ) {
                continue;
            }

            $iframe->loading = 'lazy';
            $iframe->{'data-src'} = $iframe->src;
            $iframe->src = 'about:blank';

        }

    }
    
    /**
     * Rebuild the template
     *
     * @return void
     */
    private function buildTemplate() {

        // Build lists
        foreach( [ 'theme', 'plugin', 'external', 'prefetch', 'critical' ]  as $list ) {

            foreach( Collection::get( $list ) as $type => $items ) {

                $option = sprintf( '%s_%s_list', $list, $type );

                $data = Option::get( $option, [] );

                foreach( $items as $item ) {

                    if ( ! in_array( $item, $data ) ) {
                        $data[] = $item;
                    }

                }

                Option::update( $option, $data );

            }

        }

    
        if ( 
            Option::boolval( 'css_defer' ) 
            && boolval( trim( Option::get( 'css_custom_path_def' ) ) )
        ) {
            // custom css path
            $this->head->innertext 
                .= '<style data-wpp="custom-critical-css-path">' 
                . Option::get( 'css_custom_path_def' ) 
                . '</style>' 
                . PHP_EOL;

        }


        // Combined css files
        if ( ! empty( $combined_css = Collection::get( 'combine', 'css' ) ) ) {

            // combine css links grouped by media
            $linksMedia = [];

            foreach ( $combined_css as $link ) {
                $linksMedia[] = $link[ 'media' ];    
            }     

            foreach ( array_unique( $linksMedia ) as $media ) {

                $code = '';

                foreach ( $combined_css as $link ) {

                    if ( $link[ 'media' ] == $media ) {

                        $originalCode = File::get( File::path( $link[ 'href' ] ) );  

                        if (
                            Option::get( 'css_minify' ) 
                            && wpp_in_array( array_keys( Option::get( 'css_minify' ) ), $link[ 'href' ] )
                        ) {
                            $code .= Minify::code( $originalCode, $link[ 'href' ] ); 
                        } else {
                            $code .= Minify::replacePaths( $originalCode, $link[ 'href' ] );
                        }
                        
                    }
                }      
                
                $filename = md5( $media ) . '.css';
                
                File::save( WPP_CACHE_DIR . $filename, $code );

                touch( WPP_CACHE_DIR . $filename, time() - 3600 );

                // CDN?
                $url = ( Option::get( 'cdn' ) && Option::get( 'cdn_hostname' ) ) 
                     ? str_replace( site_url(), Option::get( 'cdn_hostname' ), WPP_CACHE_URL )
                     : WPP_CACHE_URL;

                if (  Option::boolval( 'css_defer' ) ) {
                    $stylesheet = '<link rel="preload" as="style" href="' . $url . $filename . '" media="' . $media . '" onload="this.rel=\'stylesheet\'" />'; 
                    // fallback
                    Collection::add( 'defer', 'css', '<link rel="stylesheet" href="' . $url . $filename . '" media="' . $media . '" />' );
                } else {
                    $stylesheet = '<link rel="stylesheet" href="' . $url . $filename . '" media="' . $media . '" />';
                }

                $this->head->innertext .= $stylesheet . PHP_EOL;
                
            }        

        }

        // Combine Google fonts
        if ( ! empty( $google_fonts = Collection::get( 'combine', 'google_fonts' ) ) ) {

            $href = '//fonts.googleapis.com/css?family=' . implode( '|', $google_fonts );

            if ( Option::boolval( 'css_defer' ) ) {

                $stylesheet = '<link rel="preload" as="style" href="' . $href . '" type="text/css" onload="this.rel=\'stylesheet\'" />'; 
                // fallback
                Collection::add( 'defer', 'css', '<link rel="stylesheet" href="' . $href . '" />' );

            } else {
                $stylesheet = '<link rel="stylesheet" href="' . $href . '" />';
            }

            $this->head->innertext .= $stylesheet . PHP_EOL;

        }

        // combined js files
        if ( ! empty( $combined_js = Collection::get( 'combine', 'js' ) ) ) {

            $code = '';

            foreach ( $combined_js as $scripts ) {   
                // proccess by type
                foreach ( $scripts as $type => $script ) {

                    if ( $type == 'file' ) {

                        $extension = pathinfo( $script , PATHINFO_EXTENSION ); 

                        $originalCode = ( $extension == 'php' ) 
                            ? wp_remote_retrieve_body( wp_remote_get( $script ) ) 
                            : File::get( File::path( $script ) );

                        $code .= wpp_in_array( array_keys( Option::get( 'js_minify', [] ) ), $script ) 
                            ? Minify::code( $originalCode ) 
                            : $originalCode;

                    } else {
                        $code .= Minify::code( $script );
                    }
                    // ASI
                    $code .= ';' . PHP_EOL;
                } 

            }

            $filename = md5( $code ) . '.js';

            File::save( WPP_CACHE_DIR . $filename, $code );

            // CDN?
            $url = ( Option::get( 'cdn' ) && Option::get( 'cdn_hostname' ) ) 
                 ? str_replace( site_url(), Option::get( 'cdn_hostname' ), WPP_CACHE_URL )
                 : WPP_CACHE_URL;

            // Defer js
            if ( Option::boolval( 'js_defer' ) ) {
                $this->body->innertext .= '<script type="text/wppscript" data-src="' . $url . $filename . '"></script>' . PHP_EOL;
            } else {
                $this->body->innertext .= '<script src="' . $url . $filename . '"></script>' . PHP_EOL;
            }

        }
        
        // Insert noscript fallback
        if ( ! empty( $defered_css = Collection::get( 'defer', 'css' ) ) ) {
            $this->head->innertext .= '<noscript>' . implode( PHP_EOL, $defered_css ) . '</noscript>' . PHP_EOL;
        }

        // Prefetch
        $prefetch = array_unique( 
            array_merge( 
                Option::get( 'js_prefetch', [] ), 
                Option::get( 'css_prefetch', [] ) 
            ) 
        );

        if ( ! empty( $prefetch ) ) {

            $included = [];
            
            foreach( $this->html->find( 'link[rel=dns-prefetch]' ) as $link ) {
                $included[] = $link->href;
            }

            foreach( array_keys( $prefetch ) as $dns  ) {
                if ( ! wpp_in_array( $dns, $included ) ) {
                    $this->head->innertext = '<link rel="dns-prefetch" href="//' . $dns . '" />' . PHP_EOL . $this->head->innertext;
                }
            }

        }

        // Preconnect
        $preconnect = array_unique( 
            array_merge( 
                Option::get( 'js_preconnect', [] ), 
                Option::get( 'css_preconnect', [] ) 
            ) 
        );

        if ( ! empty( $preconnect ) ) {

            $included = [];
            
            foreach( $this->html->find( 'link[rel=preconnect]' ) as $link ) {
                $included[] = $link->href;
            }

            foreach( array_keys( $preconnect ) as $dns  ) {
                if ( ! wpp_in_array( $dns, $included ) ) {
                    $this->head->innertext = '<link rel="preconnect" href="//' . $dns . '" />' . PHP_EOL . $this->head->innertext;
                }
            }

        }

        $vars = [
            'css'    => Option::boolval( 'css_defer' ),
            'js'     => Option::boolval( 'js_defer' ),
            'images' => Option::boolval( 'images_lazy' ),
            'videos' => Option::boolval( 'videos_lazy' )
        ];

        // Clear cache
        if ( Option::boolval( 'cache' ) ) {
            $vars[ 'expire' ]   = time() + intval( Option::get( 'cache_time', 3600 ) * Option::get( 'cache_length', 24 ) );
            $vars[ 'ajax_url' ] = admin_url( 'admin-ajax.php');
        }

        // WPP JS
        $this->body->innertext .= '<script>' . str_replace( '{}', json_encode( $vars ),  File::get( WPP_ASSET_DIR . 'load/wpp.min.js' ) ) . '</script>' . PHP_EOL;  
        
        /**
         * Filter parsed content
         * 
         * @since 1.0.8
         */
        $this->html = apply_filters( 'wpp_parsed_content', $this->html );

        // Minify html
        if ( apply_filters( 'wpp_minify_html', true ) ) {

            $this->html = preg_replace( 
                [ '/<!--(?!\[|\<).*-->/', '/[[:blank:]]+/' ], 
                [ '',' ' ], 
                str_replace( "\t", '', $this->html )
            );

        }

        // Footprint
        $this->html .= PHP_EOL . sprintf( '<!-- %s %s %s -->', __( 'Optimized by', 'wpp' ), WPP_PLUGIN_NAME, WPP_VERSION ) . PHP_EOL;
        
    }

    /**
     * Save cache file
     *
     * @return void
     */
    private function saveCache() {

        /**
         * Filter excluded urls
         * 
         * @since 1.0.0
         */
        $excluded = apply_filters( 'wpp_exclude_urls', Option::get( 'cache_url_exclude', [] ) );

        // Check if page is excluded
        if ( ! wpp_is_url_excluded( Url::current(), $excluded ) ) {

            if ( ! $this->is_amp() ) {

                $this->html .= sprintf( 
                    '<!-- ' . __( 'Cache file was created in %s seconds on %s at %s', 'wpp' ) . ' -->',  
                    number_format( ( microtime( true ) - $this->time ), 2 ), 
                    date( get_option( 'date_format' ) ),
                    date( get_option( 'time_format' ) ) 
                );

            }
            
            Cache::save( $this->html, $this->is_amp() );
            
        }

    }


    /**
     * Check if current template is an AMP template
     *
     * @return boolean
     */
    private function is_amp() {

        if ( ! is_null( static::$amp ) ) 
            return static::$amp;

        static::$amp = ( 
            $this->htmltag 
            && isset( $this->htmltag->{'⚡'} )
            || isset( $this->htmltag->amp )
        ) ? true : false;
            
        return static::$amp;

    }


    /**
     * Return html content
     *
     * @return string
     * @since 1.0.9
     */
    public function __toString() {
        return strval( $this->html );
    }
        
}