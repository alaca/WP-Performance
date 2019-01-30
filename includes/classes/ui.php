<?php namespace WPP;
/**
* WP Performance Optimizer - UI helper
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

class UI
{

    /**
     * Top bar links
     *
     * @var array
     * @since 1.1.0
     */
    private static $links = [];

    /**
     * WPP pages
     *
     * @var array
     * @since 1.1.0
     */
    private static $pages = [];


    /**
     * Add WPP page
     *
     * @param string $id
     * @param string $name
     * @param string $template
     * @return void
     * @since 1.1.0
     */
    public static function addPage( $id, $name, $template ) {

        static::$pages[ $id ] = [ 
            'name'     => $name, 
            'template' => $template 
        ];

    }


    /**
     * Add top-bar link
     *
     * @param string $id
     * @param string $title
     * @param string $href
     * @param array $attributes
     * @return void
     * @since 1.1.0
     */
    public static function addLink( $id, $title, $href = null, $attributes = [] ) {

        $attributes = wp_parse_args( $attributes, [
            'id'    => $id,
            'class' => ''
        ] );

        static::$links[ $id ] = [             
            'title'      => $title, 
            'href'       => $href,
            'attributes' => $attributes
        ];

    }


    /**
     * Remove registered page
     *
     * @param string $id
     * @return void
     * @since 1.1.0
     */
    public static function removePage( $id ) {

        if ( array_key_exists( $id, static::$pages) ) {
            unset( static::$pages[ $id ] ); 
        }

    }


    /**
     * Remove registered top-bar link
     *
     * @param string $id
     * @return void
     * @since 1.1.0
     */
    public static function removeLink( $id ) {

        if ( array_key_exists( $id, static::$links) ) {
            unset( static::$links[ $id ] ); 
        }

    }


    /**
     * Get registered pages
     *
     * @return array
     * @since 1.1.0
     */
    public static function getPages() {
        return static::$pages;
    }


    /**
     * Get registered top-bar links
     *
     * @return array
     * @since 1.1.0
     */
    public static function getLinks() {
        return static::$links;
    }


    /**
     * Register WPP pages and top-bar links
     *
     * @return void
     * @since 1.1.0
     */
    public static function register() {

        // Register pages
        foreach( static::getPages() as $id => $data ) {

            // WPP tab
            add_action( 'wpp-admin-menu', function() use( $id, $data ) {
                echo '<li><a href="#" class="';
                wpp_active( $id );
                echo '" data-wpp-page-id="' . $id . '">' . $data[ 'name' ] . '</a></li>';
            } );

            // WPP mobile option
            add_action( 'wpp-admin-menu-mobile', function() use( $id, $data ) {
                echo '<option value="' . $id . '" ';
                wpp_active( $id, false, 'selected' );
                echo '>' . $data[ 'name' ] . '</option>';
            } );

            // WPP tab content
            add_action( 'wpp-admin-page-content', function() use( $data ) {

                if ( file_exists( $data[ 'template' ]  ) ) {
                    include $data[ 'template' ];
                } else {
                    wpp_log( sprintf( 'Template %s not exists', $data[ 'template' ] ) );
                }

            } );

        }


        // Register top bar items
        foreach( static::getLinks() as $id => $data ) {

            add_action( 'wpp-admin-bar', function( $admin_bar ) use( $id, $data ) {

                $href = is_null( $data[ 'href' ] ) 
                      ? admin_url( 'admin.php?page=' . WPP_PLUGIN_ADMIN_URL . '&wpp-tab=' . $id )
                      : $data[ 'href' ];

                $admin_bar->add_node( [
                    'id'     => $data[ 'attributes' ][ 'id' ],
                    'title'  => $data[ 'title' ], 
                    'href'   => $href,
                    'parent' => 'wpp', 
                    'meta'   => [
                        'class' => $data[ 'attributes' ][ 'class' ],
                        'title' => $data[ 'title' ]
                    ]
                ] );

            } );
            
        }

    }

}