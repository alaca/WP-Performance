<?php namespace WPP;

defined('ABSPATH') or exit; 

$cache_excluded_posts = Option::get( 'cache_post_exclude', [] ); 
$css_excluded_posts   = Option::get( 'css_post_exclude', [] ); 
$js_excluded_posts    = Option::get( 'js_post_exclude', [] ); 

?>

<?php if ( Option::boolval( 'cache' ) ): ?>
    <div class="wpp-meta-option">
        <label>
            <input type="checkbox" name="cache_url_exclude" value="<?php echo $post->ID; ?>" <?php if ( in_array( $post->ID, $cache_excluded_posts ) ) echo 'checked'; ?> />
            <?php _e( 'Exclude from cache', 'wpp' ); ?>
        </label>
    </div>
<?php endif; ?>

<?php if ( wpp_maybe_show_exclude_option( 'css' ) ) : ?>
    <div class="wpp-meta-option">
        <label>
            <input type="checkbox" name="css_url_exclude" value="<?php echo $post->ID; ?>" <?php if ( in_array( $post->ID, $css_excluded_posts ) ) echo 'checked'; ?> />
            <?php _e( 'Exclude from CSS optimization', 'wpp' ); ?>
        </label>
    </div>
<?php endif; ?>

<?php if ( wpp_maybe_show_exclude_option( 'js' ) ) : ?>
    <div class="wpp-meta-option">
        <label>
            <input type="checkbox" name="js_url_exclude" value="<?php echo $post->ID; ?>" <?php if ( in_array( $post->ID, $js_excluded_posts ) ) echo 'checked'; ?> />
            <?php _e( 'Exclude from JS optimization', 'wpp' ); ?>
        </label>
    </div>
<?php endif; ?>