<?php namespace WPP;
/**
* WP Performance Optimizer - CLI 
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

use WP_CLI;
use WP_CLI_Command;

class CLI extends WP_CLI_Command 
{

	/**
	 * Clear cache
	 *
	 * @since 1.0.0 
	 * @return void
	 */
	public function flush() {

		WP_CLI::line( esc_html__( 'Flushing the cache...', 'wpp' ) );
		Cache::clear( false );
        WP_CLI::success( esc_html__( 'Cache flushed.', 'wpp' ) );
        
	}

	/**
	 * Temporarily disable plugin
	 *
	 * @since 1.0.0 
	 * @return void
	 */
	public function disable() {

		WP_CLI::line( esc_html__( sprintf( 'Disabling %s...', WPP_PLUGIN_NAME ), 'wpp' ) );
		Option::update( 'wpp_disable', true );
        WP_CLI::success( esc_html__( sprintf( '%s disabled', WPP_PLUGIN_NAME ), 'wpp' ) );
        
	}

	/**
	 * Enable plugin
	 *
	 * @since 1.0.0 
	 * @return void
	 */
	public function enable() {

		WP_CLI::line( esc_html__( sprintf( 'Enabling %s...', WPP_PLUGIN_NAME ), 'wpp' ) );
		Option::update( 'wpp_disable', false );
        WP_CLI::success( esc_html__( sprintf( '%s enabled', WPP_PLUGIN_NAME ), 'wpp' ) );
        
	}

}

WP_CLI::add_command( 'wpp', 'WPP\CLI' );