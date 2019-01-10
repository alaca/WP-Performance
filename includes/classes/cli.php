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
        WP_CLI::line( esc_html__( 'Cache flushed.', 'wpp' ) );
        
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
        WP_CLI::line( esc_html__( sprintf( '%s disabled', WPP_PLUGIN_NAME ), 'wpp' ) );
        
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
        WP_CLI::line( esc_html__( sprintf( '%s enabled', WPP_PLUGIN_NAME ), 'wpp' ) );
        
	}

	/**
	 * Cleanup database
	 *
	 * @since 1.0.5 
	 * @return void
	 */
	public function cleanup( $args ) {

		if ( isset( $args[ 0 ] ) ) {

			switch( $args[ 0 ] ) {

				case 'transients':

					WP_CLI::line( esc_html__( 'Running tranisent cleanup', 'wpp' ) );
					DB::clearTransients();
					WP_CLI::line( esc_html__( 'Transient cleanup complete', 'wpp' ) );

					break;

				case 'revisions':

					WP_CLI::line( esc_html__( 'Running revisions cleanup', 'wpp' ) );
					DB::clearRevisions();
					WP_CLI::line( esc_html__( 'Revisions cleanup complete', 'wpp' ) );

					break;

				case 'drafts':

					WP_CLI::line( esc_html__( 'Running drafts cleanup', 'wpp' ) );
					DB::clearDrafts();
					WP_CLI::line( esc_html__( 'Drafts cleanup complete', 'wpp' ) );

					break;

				case 'trash':

					WP_CLI::line( esc_html__( 'Running trash cleanup', 'wpp' ) );
					DB::clearTrash();
					WP_CLI::line( esc_html__( 'Trash cleanup complete', 'wpp' ) );

					break;

				case 'spam':

					WP_CLI::line( esc_html__( 'Running spam cleanup', 'wpp' ) );
					DB::clearSpam();
					WP_CLI::line( esc_html__( 'Spam cleanup complete', 'wpp' ) );

					break;

				case 'cron':

					WP_CLI::line( esc_html__( 'Running cron tasks cleanup', 'wpp' ) );
					DB::clearCronTasks();
					WP_CLI::line( esc_html__( 'Cron tasks cleanup complete', 'wpp' ) );

					break;

			}

		} else {

			/**
			 * Clear all
			 */
			WP_CLI::line( esc_html__( 'Running database cleanup', 'wpp' ) );
			DB::clear();
			WP_CLI::line( esc_html__( 'Database cleanup complete', 'wpp' ) );

		}
        
	}

}

WP_CLI::add_command( 'wpp', 'WPP\CLI' );