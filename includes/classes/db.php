<?php namespace WPP;
/**
* WP Performance Optimizer - Database
*
* @author Ante Laca <ante.laca@gmail.com>
* @package WPP
*/

class DB
{       
    /**
     * Get revisions count
     *
     * @return integer
     * @since 1.0.0
     */
    public static function getRevisionsCount() {
        $result = $GLOBALS['wpdb']->get_row('
            SELECT COUNT(ID) as num 
            FROM ' . $GLOBALS['wpdb']->posts . '
            WHERE post_type = "revision"'
        );

        return empty($result) ? 0 : $result->num;    
    }
    
    /**
     * Get spam comments count
     *
     * @return integer
     * @since 1.0.0
     */
    public static function getSpamCount() {
        $result = $GLOBALS['wpdb']->get_row('
            SELECT COUNT(*) as num 
            FROM ' . $GLOBALS['wpdb']->comments . ' 
            WHERE comment_approved = "spam" 
            OR comment_approved = "trash"'
        );

        return empty($result) ? 0 : $result->num;    
    }
    
    /**
     * Get items in trash count
     *
     * @return integer
     * @since 1.0.0
     */
    public static function getTrashCount() {
        $result = $GLOBALS['wpdb']->get_row('
            SELECT COUNT(ID) as num 
            FROM ' . $GLOBALS['wpdb']->posts . ' 
            WHERE post_status = "trash"'
        );
        
        return empty($result) ? 0 : $result->num; 

    }
    
    /**
     * Get transients count
     *
     * @return integer
     * @since 1.0.0
     */
    public static function getTransientsCount() {
        
        list(, $seconds) = explode(' ', microtime());
        
        $result = $GLOBALS['wpdb']->get_row('
            SELECT COUNT(*) as num 
            FROM ' . $GLOBALS['wpdb']->options . ' 
            WHERE option_name LIKE "%_transient_timeout_%"
            AND option_value < ' . $seconds            
        ); 
        
        return empty($result) ? 0 : $result->num;
           
    }

    /**
     * Get transients count
     *
     * @return integer
     * @since 1.0.3
     */
    public static function getCronTasksCount() {
        
        $result = $GLOBALS['wpdb']->get_row('
            SELECT option_value as tasks  
            FROM ' . $GLOBALS[ 'wpdb' ]->options . ' 
            WHERE option_name = "cron"'
        );

        $count = 0;

        if ( empty( $result ) ) {
            return $count;
        }

        $tasks = unserialize( $result->tasks );

        if ( is_array( $tasks ) ) {

            foreach( $tasks as $id => $task ) {

                if ( is_array( $task ) ) {

                    foreach( $task as $hook => $data ) {

                        // Check if hook has action
                        if ( ! has_action( $hook ) ) {
                            $count++;
                        }
                    }   

                }

            }

        }


        return $count; 
           
    }

    /**
     * Run all db cleanups
     *
     * @return void
     * @since 1.0.0
     */
    public static function clear() {
        DB::clearRevisions();
        DB::clearSpam();
        DB::clearTrash();
        DB::clearTransients();
        DB::clearCronTasks();

        wpp_log( 'DB optimized', 'notice' );
    }
    

    /**
     * Cleanup revisions
     *
     * @return void
     * @since 1.0.0
     */
    public static function clearRevisions() {

        wpp_log( 'DB revisions deleted', 'notice' );

        return $GLOBALS['wpdb']->query( 
            'DELETE FROM ' . $GLOBALS['wpdb']->posts .' WHERE post_type = "revision"' 
        );  
    }
    

    /**
     * Cleanup spam
     *
     * @return void
     * @since 1.0.0
     */
    public static function clearSpam() {

        wpp_log( 'DB spam deleted', 'notice' );

        return $GLOBALS['wpdb']->query( 
            'DELETE FROM ' . $GLOBALS['wpdb']->comments .' WHERE comment_approved = "spam" OR comment_approved = "trash"'
        );
    }
    
    
    /**
     * Cleanup trash
     *
     * @return void
     * @since 1.0.0
     */
    public static function clearTrash() {

        wpp_log( 'DB trash deleted', 'notice' );

        return $GLOBALS['wpdb']->query( 
            'DELETE FROM ' . $GLOBALS['wpdb']->posts . ' WHERE post_status = "trash"' 
        );    
    }
    
    
    /**
     * Cleanup transients
     *
     * @return void
     * @since 1.0.0
     */
    public static function clearTransients() {

        wpp_log( 'DB transients deleted', 'notice' );

        return $GLOBALS['wpdb']->query( 
            'DELETE FROM ' . $GLOBALS['wpdb']->options . ' WHERE option_name LIKE "%_transient_%"' 
        );
    }


    /**
     * Cleanup cron tasks
     *
     * @return void
     * @since 1.0.0
     */
    public static function clearCronTasks() {

        wpp_log( 'DB cron tasks deleted', 'notice' );

        delete_option( 'cron' );
    }

    
}