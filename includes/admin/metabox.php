<?php namespace WPP;

defined('ABSPATH') or exit;

$permalink =  get_permalink( $post );


?>


<div class="wpp-meta-option">

    <label title="<?php _e( 'Page URL will be added to exclude list', 'wpp' ); ?>">

        <?php 
        
            /**
             * Exclude URL from Cache filter
             * @since 1.0.0
             */
            $cache_url_exclude = apply_filters( 'wpp_exclude_urls', Option::get( 'cache_url_exclude', [] ) ); 
        
        ?>

        
        <?php 
        
            if ( 
                Option::boolval( 'cache' ) 
                && ! in_array( $post->ID, Option::get( 'cache_post_exclude', [] ) ) 
                && wpp_is_url_excluded( $permalink, $cache_url_exclude ) 
            ) : 
            
            ?>

            <input 
                type="checkbox" 
                checked="checked"
                disabled="disabled" />

            <?php _e( 'Exclude from cache', 'wpp' ); ?>

            <br />

            <?php if ( $post->ID == get_option( 'page_on_front' ) ): ?>
                <em class="wpp-warning"><?php _e( 'This page is your website home page and it is affected by Exclude URL(s) from cache option. Usually a home page is not excluded from cache. Please check your exclude settings.', 'wpp' ); ?></em>
            <?php else: ?>

                <em><?php _e( 'This page is affected by URL(s) exclude options on Cache page', 'wpp' ); ?></em>

            <?php endif; ?>

        <?php else: ?>

            <input 
                type="checkbox" 
                name="cache_post_exclude" 
                value="<?php echo $post->ID; ?>" 
                <?php if ( in_array( $post->ID, Option::get( 'cache_post_exclude', [] ) ) ) echo 'checked'; ?>

                <?php if ( $post->ID == get_option( 'page_on_front' ) ): ?>
                    class="wpp-action-confirm"
                    data-description="<?php _e( 'This page is your website home page.<br />Do you want to exclude home page from cache?', 'wpp' ); ?>"
                <?php endif; ?>

                />

            <?php _e( 'Exclude from cache', 'wpp' ); ?>

        <?php endif; ?>


    </label><br />

</div>


<div class="wpp-meta-option">

    <label title="<?php _e( 'Page URL will be added to exclude list', 'wpp' ); ?>">

        <?php 
        
        /**
         * Exclude URL from HTML optimization filter
         * @since 1.1.7.5
         */
        $html_url_exclude = apply_filters( 'wpp_html_url_exclude', Option::get( 'html_url_exclude', [] ) ); 
        
        ?>

        <?php 
        
            if ( 
                ! in_array( $post->ID, Option::get( 'html_post_exclude', [] ) ) 
                && wpp_is_url_excluded( $permalink, $html_url_exclude ) 
            ) : 
            
            ?>

            <input 
                type="checkbox" 
                name="html_post_exclude" 
                checked="checked"
                disabled="disabled" />

            <?php _e( 'Exclude from HTML optimization', 'wpp' ); ?>

            <br />

            <em><?php _e( 'This page is affected by URL(s) exclude options on HTML page', 'wpp' ); ?></em>

        <?php else: ?>

            <input 
                type="checkbox" 
                name="html_post_exclude" 
                value="<?php echo $post->ID; ?>" 
                <?php if ( in_array( $post->ID, Option::get( 'html_post_exclude', [] ) ) ) echo 'checked'; ?> />

            <?php _e( 'Exclude from HTML optimization', 'wpp' ); ?>

        <?php endif; ?>

    </label>

</div>


<div class="wpp-meta-option">

    <label title="<?php _e( 'Page URL will be added to exclude list', 'wpp' ); ?>">

        <?php 
        
        /**
         * Exclude URL from CSS optimization filter
         * @since 1.0.3
         */
        $css_url_exclude = apply_filters( 'wpp_css_url_exclude', Option::get( 'css_url_exclude', [] ) ); 
        
        ?>

        <?php 
        
            if ( 
                ! in_array( $post->ID, Option::get( 'css_post_exclude', [] ) ) 
                && wpp_is_url_excluded( $permalink, $css_url_exclude ) 
            ) : 
            
            ?>

            <input 
                type="checkbox" 
                name="css_post_exclude" 
                checked="checked"
                disabled="disabled" />

            <?php _e( 'Exclude from CSS optimization', 'wpp' ); ?>

            <br />

            <em><?php _e( 'This page is affected by URL(s) exclude options on CSS page', 'wpp' ); ?></em>

        <?php else: ?>

            <input 
                type="checkbox" 
                name="css_post_exclude" 
                value="<?php echo $post->ID; ?>" 
                <?php if ( in_array( $post->ID, Option::get( 'css_post_exclude', [] ) ) ) echo 'checked'; ?> />

            <?php _e( 'Exclude from CSS optimization', 'wpp' ); ?>

        <?php endif; ?>

    </label>

</div>

<div class="wpp-meta-option">

    <label title="<?php _e( 'Page URL will be added to exclude list', 'wpp' ); ?>">

    
        <?php 
        
        /**
         * Exclude URL from JavaScript optimization filter
         * @since 1.0.3
         */
        $js_url_exclude = apply_filters( 'wpp_js_url_exclude', Option::get( 'js_url_exclude', [] ) ); 
        
        ?>

        <?php 
        
            if ( 
                ! in_array( $post->ID, Option::get( 'js_post_exclude', [] ) ) 
                && wpp_is_url_excluded( $permalink, $js_url_exclude ) 
            ) : 
            
            ?>

            <input 
                type="checkbox" 
                checked="checked"
                disabled="disabled" />

            <?php _e( 'Exclude from CSS optimization', 'wpp' ); ?>

            <br />
            
            <em><?php _e( 'This page is affected by URL(s) exclude options on JavaScript page', 'wpp' ); ?></em>

        <?php else: ?>

            <input 
                type="checkbox" 
                name="js_post_exclude" 
                value="<?php echo $post->ID; ?>" 
                <?php if ( in_array( $post->ID, Option::get( 'js_post_exclude', [] ) ) ) echo 'checked'; ?> />

            <?php _e( 'Exclude from JS optimization', 'wpp' ); ?>

        <?php endif; ?>

    </label>

</div>


<div class="wpp-meta-option">

    <label title="<?php _e( 'Page URL will be added to exclude list', 'wpp' ); ?>">

        <?php 
        
        /**
         * Exclude URL from Image optimization filter
         * @since 1.0.3
         */
        $image_url_exclude = apply_filters( 'wpp_image_url_exclude', Option::get( 'image_url_exclude', [] ) );
        
        ?>

        <?php 
        
            if ( 
                ! in_array( $post->ID, Option::get( 'image_post_exclude', [] ) ) 
                && wpp_is_url_excluded( $permalink, $image_url_exclude ) 
            ) : 
            
            ?>

            <input 
                type="checkbox" 
                checked="checked"
                disabled="disabled" />

            <?php _e( 'Exclude from Image optimization', 'wpp' ); ?>

            <br />
            
            <em><?php _e( 'This page is affected by URL(s) exclude options on Media page', 'wpp' ); ?></em>

        <?php else: ?>

            <input 
                type="checkbox" 
                name="image_post_exclude" 
                value="<?php echo $post->ID; ?>" 
                <?php if ( in_array( $post->ID, Option::get( 'image_post_exclude', [] ) ) ) echo 'checked'; ?> />

            <?php _e( 'Exclude from Image optimization', 'wpp' ); ?>

        <?php endif; ?>

    </label>

</div>


<div class="wpp-meta-option">

    <label title="<?php _e( 'Page URL will be added to exclude list', 'wpp' ); ?>">

        <?php 
        
        /**
         * Exclude URL from video/iframe lazyload filter
         * @since 1.1.6
         */
        $video_url_exclude = apply_filters( 'wpp_video_url_exclude', Option::get( 'video_url_exclude', [] ) );
        
        ?>

        <?php 
        
            if ( 
                ! in_array( $post->ID, Option::get( 'video_post_exclude', [] ) ) 
                && wpp_is_url_excluded( $permalink, $video_url_exclude ) 
            ) : 
            
            ?>

            <input 
                type="checkbox" 
                checked="checked"
                disabled="disabled" />

            <?php _e( 'Exclude from Video lazyload', 'wpp' ); ?>

            <br />
            
            <em><?php _e( 'This page is affected by URL(s) exclude options on Media page', 'wpp' ); ?></em>

        <?php else: ?>

            <input 
                type="checkbox" 
                name="video_post_exclude" 
                value="<?php echo $post->ID; ?>" 
                <?php if ( in_array( $post->ID, Option::get( 'video_post_exclude', [] ) ) ) echo 'checked'; ?> />

            <?php _e( 'Exclude from Video lazyload', 'wpp' ); ?>

        <?php endif; ?>

    </label>

</div>
