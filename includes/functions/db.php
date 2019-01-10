<?php 
/**
* WP Performance Optimizer - Database helpers
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/


use WPP\DB;
use WPP\Input;


/**
 * Register database ajax actions
 *
 * @return json
 * @since 1.0.0
 */
function wpp_ajax_database_actions() {

    switch( Input::post( 'db_action' ) ) {
        
        case 'revisions':
        
            DB::clearRevisions();
            wp_send_json( [ 'status' => 1 ] );    
                        
            break;
            
        case 'spam':
        
            DB::clearSpam();
            wp_send_json(['status' => 1]);    

            break;
            
        case 'trash':
        
            DB::clearTrash();
            wp_send_json(['status' => 1]);  
                        
            break;
            
        case 'transients':
        
            DB::clearTransients();
            wp_send_json(['status' => 1]); 
                        
            break;

        case 'cron':
        
            DB::clearCronTasks();
            wp_send_json(['status' => 1]); 
                        
            break;

        case 'drafts':
        
            DB::clearAutoDrafts();
            wp_send_json(['status' => 1]); 
                        
            break;
            
        case 'all':
        
            DB::clearRevisions();
            DB::clearSpam();
            DB::clearTrash();
            DB::clearTransients();
            DB::clearCronTasks();
            DB::clearAutoDrafts();

            wp_send_json(['status' => 1]);   
        
            break;
            
    } 

    wp_send_json( [
        'status' => 0, 
        'note'   => __( 'You are doing it wrong', 'wpp' )
    ] );   
    
}