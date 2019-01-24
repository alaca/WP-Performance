<?php namespace WPP; 

defined('ABSPATH') or exit; ?>

<div class="wpp-page-wrapper">

    <div class="wpp-content-section">
    
        <table>

            <tr>
                <td><strong><?php _e( 'Enable CDN', 'wpp' ); ?></strong></td>
                <td>
                    <label class="wpp-info">
                        <input type="checkbox" value="1" name="cdn" form="wpp-settings" <?php wpp_checked( 'cdn' ); ?> />
                        <?php _e( 'All URL(s) of static files ( CSS, JavaScript and Images ) will be rewritten to the CDN hostname you provide. ', 'wpp' ); ?>
                    </label>

                </td>
            </tr>

            <tr>
                <td><strong><?php _e( 'CDN hostname', 'wpp' ); ?></strong></td>
                <td>
                    <input type="text" name="cdn_hostname" class="wpp-dynamic-input" form="wpp-settings" value="<?php echo Option::get( 'cdn_hostname' ); ?>" />
                </td>
            </tr>

        </table>

        <br />

        <input type="submit" class="button-primary" value="<?php _e( 'Save changes', 'wpp' ); ?>" name="wpp-save-settings" form="wpp-settings" />

        <br /><br />

    </div>

    <div class="wpp-side-section">
    
        <div>

            <h3><?php _e( 'Exclude file(s) from CDN', 'wpp' ); ?></h3>

            <hr />

            <div>

                <?php $excluded_files = Option::get( 'cdn_exclude', [] ); ?>

                <div id="wpp-exclude-cdn-container">

                    <?php foreach( $excluded_files as $file ): ?>
                        <div data-dynamic-container="cdn_exclude[]" class="wpp-dynamic-input-container">

                            <input 
                                name="cdn_exclude[]" 
                                value="<?php echo $file; ?>" 
                                placeholder="<?php echo site_url(); ?>" 
                                class="wpp-dynamic-input" 
                                form="wpp-settings" 
                                type="text" 
                                required
                            /> &nbsp; 

                            <a href="#" data-name="cdn_exclude[]" class="button wpp-remove-input"><?php _e('Remove', 'wpp'); ?></a>

                        </div>
                    <?php endforeach; ?>

                </div>

                <?php if( ! empty( $excluded_files ) ) : ?>
                    <div data-info-name="cdn_exclude[]">
                        <em><span class="dashicons dashicons-info"></span> <?php _e( 'Use {numbers} to match only numbers', 'wpp' ); ?></em>
                        <em><span class="dashicons dashicons-info"></span> <?php _e( 'Use {letters} to match only letters', 'wpp' ); ?></em>
                        <em><span class="dashicons dashicons-info"></span> <?php _e( 'Use {any} to match any string', 'wpp' ); ?></em>
                        <br>
                    </div>
                <?php endif; ?>

                <br />
                
                <a href="#" 
                    class="button" 
                    data-add-input="cdn_exclude[]" 
                    data-placeholder="<?php echo site_url(); ?>" 
                    data-info="<?php _e( 'Use {numbers} to match only numbers', 'wpp' ); ?>|<?php _e( 'Use {letters} to match only letters', 'wpp' ); ?>|<?php _e( 'Use {any} to match any string', 'wpp' ); ?>" 
                    data-container="#wpp-exclude-cdn-container">
                    
                    <?php _e( 'Add file', 'wpp' ); ?>

                </a>

            </div>

        </div>  
        
        
    </div>

</div>