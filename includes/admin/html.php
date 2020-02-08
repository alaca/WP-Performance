<?php namespace WPP;

defined('ABSPATH') or exit; ?>

<div class="wpp-page-wrapper">

    <div class="wpp-content-section">

        <table>

            <tr>
                <td><strong><?php _e( 'HTML optimization', 'wpp' ); ?></strong></td>
                <td>
                    <label class="wpp-info">
                        <input type="checkbox" value="1" data-wpp-checkbox="html" name="html_optimization" form="wpp-settings" <?php wpp_checked( 'html_optimization' ); ?> />
                        <?php _e( 'Enable', 'wpp' ); ?>
                    </label>
                </td>
            </tr>

            <tr data-wpp-show-checked="html">
                <td><strong><?php _e( 'Minify', 'wpp' ); ?></strong></td>
                <td>

                    <label class="wpp-info">
                        <input type="checkbox" value="1" name="html_minify_normal" form="wpp-settings" <?php wpp_checked( 'html_minify_normal' ); ?> />
                        <?php _e( 'Normal', 'wpp' ); ?>
                    </label>

                    <br /><br />

                    <label class="wpp-info">
                        <input type="checkbox" value="1" name="html_minify_aggressive" form="wpp-settings" <?php wpp_checked( 'html_minify_aggressive' ); ?> />
                        <?php _e( 'Aggressive', 'wpp' ); ?>
                    </label>

                </td>
            </tr>

            <tr data-wpp-show-checked="html">
                <td><strong><?php _e( 'Remove comments', 'wpp' ); ?></strong></td>
                <td>

                    <label class="wpp-info">
                        <input type="checkbox" value="1" name="html_remove_comments" form="wpp-settings" <?php wpp_checked( 'html_remove_comments' ); ?> />
                        <?php _e( 'Enable', 'wpp' ); ?>
                    </label>        
                    <br /><br />
                    <em><span class="dashicons dashicons-info"></span> <?php _e( 'Conditional comments will be preserved', 'wpp' ); ?></em> 
                </td>
            </tr>

            <tr data-wpp-show-checked="html">
                <td><strong><?php _e( 'Remove type attribute', 'wpp' ); ?></strong></td>
                <td>
                    <label class="wpp-info">
                        <input type="checkbox" value="1" name="html_remove_link_type" form="wpp-settings" <?php wpp_checked( 'html_remove_link_type' ); ?> />
                        <?php _e( 'Link', 'wpp' ); ?>
                    </label>   
                    <br /><br />
                    <em><span class="dashicons dashicons-info"></span> <?php _e( 'Remove type="text/css" from link tag', 'wpp' ); ?></em>      

                    <br />

                    <label class="wpp-info">
                        <input type="checkbox" value="1" name="html_remove_script_type" form="wpp-settings" <?php wpp_checked( 'html_remove_script_type' ); ?> />
                        <?php _e( 'Script', 'wpp' ); ?>
                    </label>        
                    <br /><br />
                    <em><span class="dashicons dashicons-info"></span> <?php _e( 'Remove type="text/javascript" from script tag', 'wpp' ); ?></em> 
                </td>
            </tr>


            <tr data-wpp-show-checked="html">
                <td><strong><?php _e( 'Remove quotes', 'wpp' ); ?></strong></td>
                <td>

                    <label class="wpp-info">
                        <input type="checkbox" value="1" name="html_remove_qoutes" form="wpp-settings" <?php wpp_checked( 'html_remove_qoutes' ); ?> />
                        <?php _e( 'Enable', 'wpp' ); ?>
                    </label>
                    <br /><br />
                    <em><span class="dashicons dashicons-info"></span> <?php _e( 'Remove quotes from attirbutes.', 'wpp' ); ?></em>
                </td>
            </tr>

        </table>

        <br />

        <input type="submit" class="button-primary" value="<?php _e( 'Save changes', 'wpp' ); ?>" name="wpp-save-settings" form="wpp-settings" />
        
        <br /><br />

    </div>

    <div class="wpp-side-section">
    

        <?php do_action( 'wpp-html-side-section-top' ); ?> 
        
        <div>

            <h3><?php _e('Exclude URL(s) from HTML optimization', 'wpp'); ?></h3>

            <hr />    

            <?php $excluded_urls = Option::get( 'html_url_exclude', [] ); ?>

            <div id="wpp-exclude-url-html-container">

                <?php if ( ! empty( $pages = Option::get( 'html_post_exclude', [] ) ) ): ?>
            
                    <?php foreach( $pages as $id ): $link = get_permalink( $id ); ?>
                        <div class="wpp-dynamic-input-container">

                            <input class="wpp-dynamic-input" value="<?php echo $link; ?>" type="text" readonly /> &nbsp; 
                            <a 
                                href="#" 
                                class="button wpp-remove-manually-excluded" 
                                data-id="<?php echo $id; ?>" 
                                data-type="html" 
                                data-description="<?php printf( __( 'Remove %s from excluded URL(s)?', 'wpp' ), $link ); ?>">
                                    <?php _e( 'Remove', 'wpp' ); ?>
                            </a>
                        </div>
                    <?php endforeach; ?>

                <?php endif; ?>

                <?php foreach( $excluded_urls as $url ): ?>
                    <div data-dynamic-container="html_url_exclude[]" class="wpp-dynamic-input-container">
                        <input name="html_url_exclude[]" value="<?php echo $url; ?>" placeholder="<?php echo trailingslashit( site_url() ); ?>" class="wpp-dynamic-input" form="wpp-settings" type="text" required> &nbsp; 
                        <a href="#" data-name="html_url_exclude[]" class="button wpp-remove-input"><?php _e('Remove', 'wpp'); ?></a>
                    </div>
                <?php endforeach; ?>

            </div>

            <?php if( ! empty( $excluded_urls ) ) : ?>
                <div data-info-name="html_url_exclude[]">
                    <em><span class="dashicons dashicons-info"></span> <?php _e('Use {numbers} to match only numbers', 'wpp'); ?></em>
                    <em><span class="dashicons dashicons-info"></span> <?php _e('Use {letters} to match only letters', 'wpp'); ?></em>
                    <em><span class="dashicons dashicons-info"></span> <?php _e('Use {any} to match any string', 'wpp'); ?></em>
                    <em><span class="dashicons dashicons-info"></span> <?php _e('Use {all} to match all', 'wpp'); ?></em>
                    <br>
                </div>
            <?php endif; ?>

            <a href="#" 
                class="button" 
                data-add-input="html_url_exclude[]" 
                data-placeholder="<?php echo trailingslashit( site_url() ); ?>" 
                data-info="<?php _e('Use {numbers} to match only numbers', 'wpp'); ?>|<?php _e('Use {letters} to match only letters', 'wpp'); ?>|<?php _e('Use {any} to match any string', 'wpp'); ?>|<?php _e('Use {all} to match all', 'wpp'); ?>" 
                data-container="#wpp-exclude-url-html-container">

                <?php _e('Add URL', 'wpp'); ?>

            </a>

        </div>

        <?php do_action( 'wpp-html-side-section-bottom'); ?>
        
        
    </div>

</div>