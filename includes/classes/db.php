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
    * Hook up ajax actions
    * 
    */
    public static function registerActions() {

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
                
            case 'all':
            
                DB::clearRevisions();
                DB::clearSpam();
                DB::clearTrash();
                DB::clearTransients();
                wp_send_json(['status' => 1]);   
            
                break;
                
        } 

        wp_send_json( [
            'status' => 0, 
            'note'   => __( 'You are doing it wrong', 'wpp' )
        ] );   
    }
       
    /**
    * Get revisions count
    * 
    */
    public static function getRevisionsCount() {
        $result = $GLOBALS['wpdb']->get_row('
            SELECT COUNT(ID) as num 
            FROM ' . $GLOBALS['wpdb']->prefix . 'posts
            WHERE post_type = "revision"'
        );

        return empty($result) ? 0 : $result->num;    
    }
    
    /**
    * Get spam comments count
    * 
    */
    public static function getSpamCount() {
        $result = $GLOBALS['wpdb']->get_row('
            SELECT COUNT(*) as num 
            FROM ' . $GLOBALS['wpdb']->prefix . 'comments 
            WHERE comment_approved = "spam" 
            OR comment_approved = "trash"'
        );

        return empty($result) ? 0 : $result->num;    
    }
    
    /**
    * Get items in trash count
    * 
    */
    public static function getTrashCount() {
        $result = $GLOBALS['wpdb']->get_row('
            SELECT COUNT(ID) as num 
            FROM ' . $GLOBALS['wpdb']->prefix . 'posts 
            WHERE post_status = "trash"'
        );
        
        return empty($result) ? 0 : $result->num; 

    }
    
    /**
    * Get transients count
    * 
    */
    public static function getTransientsCount() {
        
        list(, $seconds) = explode(' ', microtime());
        
        $result = $GLOBALS['wpdb']->get_row('
            SELECT COUNT(*) as num 
            FROM ' . $GLOBALS['wpdb']->prefix . 'options 
            WHERE option_name LIKE "%_transient_timeout_%"
            AND option_value < ' . $seconds            
        ); 
        
        return empty($result) ? 0 : $result->num;
           
    }

    /**
     * Clear all
     *
     */
    public static function clear() {
        DB::clearRevisions();
        DB::clearSpam();
        DB::clearTrash();
        DB::clearTransients();

        wpp_log( 'DB optimized', 'notice' );
    }
    
    private static function clearRevisions() {

        wpp_log( 'DB revisions deleted', 'notice' );

        return $GLOBALS['wpdb']->query( 
            'DELETE FROM ' . $GLOBALS['wpdb']->prefix .'posts WHERE post_type = "revision"' 
        );  
    }
    
    private static function clearSpam() {

        wpp_log( 'DB spam deleted', 'notice' );

        return $GLOBALS['wpdb']->query( 
            'DELETE FROM ' . $GLOBALS['wpdb']->prefix .'comments WHERE comment_approved = "spam" OR comment_approved = "trash"'
        );
    }
    
    
    private static function clearTrash() {

        wpp_log( 'DB trash deleted', 'notice' );

        return $GLOBALS['wpdb']->query( 
            'DELETE FROM ' . $GLOBALS['wpdb']->prefix . 'posts WHERE post_status = "trash"' 
        );    
    }
    
    
    private static function clearTransients() {

        wpp_log( 'DB transients deleted', 'notice' );

        return $GLOBALS['wpdb']->query( 
            'DELETE FROM ' . $GLOBALS['wpdb']->prefix . 'options WHERE option_name LIKE "%_transient_%"' 
        );
    }

    
}