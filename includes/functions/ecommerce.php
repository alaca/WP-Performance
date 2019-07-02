<?php 
/**
* WP Performance Optimizer - Ecommerce helpers
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use WPP\Url;

/**
 * Exclude WooCommerce pages
 *
 * @param array $exclude
 * @return array
 * @since 1.0.0
 */
function wpp_exclude_woocommerce_pages( $exclude ) {

    // Check if woocommerce class exists
    if ( class_exists( 'WooCommerce' ) ) {
        if ( 
            is_checkout() 
            || is_account_page() 
            || is_cart() 
        ) {
            array_push( $exclude, Url::current() );
        }
    }

    return $exclude;

}

// Exclude WooCommerce pages
add_filter( 'wpp_exclude_urls', 'wpp_exclude_woocommerce_pages' );

/**
 * Exclude EDD pages checkout, account and cart
 *
 * @param array $exclude
 * @return array
 * @since 1.0.0
 */
function wpp_exclude_edd_pages( $exclude ) {

    if ( class_exists( 'Easy_Digital_Downloads' )) {

        if ( 
            edd_is_checkout() 
            || edd_is_success_page() 
            || edd_is_failed_transaction_page() 
            || edd_is_purchase_history_page() 
            || edd_is_test_mode()
        ) {
            array_push( $exclude, Url::current() );
        }
    
    }

    return $exclude;

}

// Exclude EDD pages
add_filter( 'wpp_exclude_urls', 'wpp_exclude_edd_pages' );