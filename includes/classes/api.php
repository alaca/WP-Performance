<?php namespace WPP;

use WP_Error;

/**
 * API class
 *
 * @since 1.0.8
 */
abstract class API {

    protected $methods;
    protected $api_url;
    protected $namespace = null;
    protected $headers = [];
    protected $timeout = 45;
    protected $sslverify = false;
    
    /**
     * Make request
     *
     * @param string $url
     * @param string $method
     * @param array $options
     * @param headers $options
     * @param integer $cache
     * @return array|WP_error
     * @since 1.0.8
     */
    public function request( $url, $method, $options = [], $cache = 0 ) {

        // Set timeout
        set_time_limit( $this->timeout );

        $url    = $this->getUrl( $url );
        $method = strtoupper( $method );

        // Get headers
        $headers = wp_parse_args( 
            $this->headers, 
            [
                'Content-Type'  => 'application/json'
            ] 
        );

        // Check for additional headers
        if ( isset( $options[ 'headers' ] ) && is_array( $options[ 'headers' ] ) ) {

            $headers = array_merge( $headers, $options[ 'headers' ] );

            // Remove headers from options
            unset( $options[ 'headers' ] );

        }


        // Set args
        $args = [
            'timeout'   => $this->timeout,
            'method'    => $method,
            'sslverify' => $this->sslverify,
            'body'      => $options,
            'headers'   => $headers
        ];
        

        if ( $cache ) {
            // Generate unique cache key for every request
            $key = md5( $url . $method . serialize( $options) );
            
            if ( $data = $this->getCache( $key ) ) {
                return $data;
            }

        }

        // Make request
        $request = wp_remote_request( $url, $args );

        $response = [
            'code'      => wp_remote_retrieve_response_code( $request ), 
            'message'   => wp_remote_retrieve_response_message( $request ), 
            'body'      => wp_remote_retrieve_body( $request ),
            'cache_key' => ''
        ];


        // Check for error
        if ( is_wp_error( $request ) || ! $this->checkStatusCode( $response ) ) {

            wpp_log( sprintf( 'API request error: %s %s %s', strtoupper( $method ), $url, $response[ 'message' ] ) );   

            return new WP_Error( $response[ 'code' ], $response[ 'message' ], $response );

        } 
    
        // Cache result ?
        if ( $cache ) {
            $this->setCache( $key, $response, $cache );
            $response['cache_key'] = $key;
        }
    
        return $response;

    }


    /**
     * Make API request with method
     *
     * @param string $method
     * @param array $args
     * @return WP_Error|array
     * @since 1.0.8
     */
    public function __call( $method, $args ) {

        $method = strtoupper( $method );

        if ( in_array( $method, $this->methods ) ) {

            $url     = isset( $args[0] ) ? $args[0] : '/';
            $options = isset( $args[1] ) ? $args[1] : [];
            $cache   = isset( $args[2] ) ? $args[2] : 0;
    
            return $this->request( $url, $method, $options, $cache );

        }

        // Log error
        wpp_log( sprintf( __( 'Call to undefined method %s', 'wpp' ), $method ) );

    }


    /**
     * Set response cache
     * 
     * Uses transients to store request response 
     *
     * @param string $key
     * @param mixed $data
     * @param int $cache numbers of seconds
     * @return boolean
     * @since 1.0.8
     */
    public function setCache( $key, $data, $cache = 300 ) {
        return set_transient( wpp_get_prefix( $key ), $data, $cache );
    }

    /**
     * Get cached data
     *
     * @param string $key
     * @return array|boolean
     * @since 1.0.8
     */
    public function getCache( $key ) {

        $data = get_transient( wpp_get_prefix( $key ) );

        if ( $data !== false ) {
            return $data;
        }

        return false;
        
    }
    
    /**
     * Clear cache
     * 
     * If cache key is not provided, all cache will be deleted
     *
     * @param string $key
     * @return boolean
     * @since 1.0.8
     */
    public function clearCache( $key = null ) {

        if ( is_null( $key ) ) {

            return $GLOBALS[ 'wpdb' ]->query( 
               'DELETE FROM ' . $GLOBALS[ 'wpdb' ]->prefix . 'options WHERE option_name LIKE ("%transient_' . esc_sql( wpp_get_prefix() ) . '%")'
            );

        }

        return delete_transient( $this->cache_prefix . $key );
    }


    /**
     * Get API url
     *
     * @param string $url
     * @return string
     * @since 1.0.8
     */
    public function getUrl( $url = '' ) {

        // Check if its custom API url
        if ( strstr( $url, 'http://' ) || strstr( $url, 'https://' ) ) {
            return $url;
        }

        $namespace = ! is_null( $this->namespace ) ? trailingslashit( $this->namespace ) : '';
        return trailingslashit( $this->api_url ) . $namespace . ltrim( $url, '/' ); 
    }


    /**
     * Check API response status code
     *
     * @param array $response
     * @return boolean
     * @since 1.0.8
     */
    public function checkStatusCode( $response ) {
        return ( $response['code'] >= 200 && $response['code'] < 300 ) ? true : false;
    }


}