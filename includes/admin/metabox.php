<?php namespace WPP;

defined('ABSPATH') or exit; 

?>

<?php if ( Option::boolval( 'cache' ) ): ?>
    <div class="wpp-meta-option">

        <label title="<?php _e( 'Page URL will be added to exclude list', 'wpp' ); ?>">

            <input 
                type="checkbox" 
                name="cache_post_exclude" 
                value="<?php echo $post->ID; ?>" 
                <?php if ( in_array( $post->ID, Option::get( 'cache_post_exclude', [] ) ) ) echo 'checked'; ?> />

            <?php _e( 'Exclude from cache', 'wpp' ); ?>

        </label><br />

    </div>
<?php endif; ?>

<?php if ( wpp_maybe_show_exclude_option( 'css' ) ) : ?>
    <div class="wpp-meta-option">

        <label title="<?php _e( 'Page URL will be added to exclude list', 'wpp' ); ?>">

            <input 
                type="checkbox" 
                name="css_post_exclude" 
                value="<?php echo $post->ID; ?>" 
                <?php if ( in_array( $post->ID, Option::get( 'css_post_exclude', [] ) ) ) echo 'checked'; ?> />

            <?php _e( 'Exclude from CSS optimization', 'wpp' ); ?>

        </label>

    </div>
<?php endif; ?>

<?php if ( wpp_maybe_show_exclude_option( 'js' ) ) : ?>
    <div class="wpp-meta-option">

        <label title="<?php _e( 'Page URL will be added to exclude list', 'wpp' ); ?>">

            <input 
                type="checkbox" 
                name="js_post_exclude" 
                value="<?php echo $post->ID; ?>" 
                <?php if ( in_array( $post->ID, Option::get( 'js_post_exclude', [] ) ) ) echo 'checked'; ?> />

            <?php _e( 'Exclude from JS optimization', 'wpp' ); ?>

        </label>

    </div>
<?php endif; ?>