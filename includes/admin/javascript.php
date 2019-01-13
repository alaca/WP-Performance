<?php namespace WPP;

defined('ABSPATH') or exit; 

$minified           = Option::get( 'js_minify', [] );
$combined           = Option::get( 'js_combine', [] );
$inlined            = Option::get( 'js_inline', [] );
$disabled           = Option::get( 'js_disable', [] );
$disabled_positions = Option::get( 'js_disable_position', [] );
$theme_files        = Option::get( 'theme_js_list', [] );
$plugin_files       = Option::get( 'plugin_js_list', [] );
$external_list      = Option::get( 'external_js_list', [] );

?>

<div class="wpp-page-wrapper">

    <div class="wpp-content-section">
    
        <?php if ( ! empty( $theme_files + $plugin_files ) ): ?>

            <table class="wpp-file-list">

                <thead>
                    <tr>
                        <th><?php _e( 'File', 'wpp' ); ?></th>
                        <th><?php _e( 'Minify', 'wpp' ); ?></th>
                        <th><?php _e( 'Inline', 'wpp' ); ?></th>
                        <th><?php _e( 'Combine', 'wpp' ); ?></th>
                        <th><?php _e( 'Disable', 'wpp' ); ?></th>
                    </tr>
                </thead>

                <tbody>
                    
                    <tr class="wpp-bulk-update">
                        <td><em><span class="dashicons dashicons-info"></span> <?php _e( 'Update all files in list below', 'wpp' ); ?></td>
                        <td><input type="checkbox" class="wpp-update-checkboxes" data-wpp-group="js-minify" /></td>
                        <td><input type="checkbox" class="wpp-update-checkboxes" data-wpp-group="js-inline" /></td>
                        <td><input type="checkbox" class="wpp-update-checkboxes" data-wpp-group="js-combine" /></td>
                        <td><input type="checkbox" class="wpp-update-checkboxes" data-wpp-group="js-disable" /></td>
                    </tr>
                
                    <?php if ( ! empty( $theme_files ) ) : ?>

                        <tr class="wpp-files-type">
                            <td colspan="5"><h3><?php _e( 'Theme', 'wpp' ); ?></h3></td>
                        </tr>

                        <?php 
                        
                            wpp_load_template( 'includes/local_files', [
                                'type'     => 'js',
                                'list'     => $theme_files,
                                'minified' => $minified,
                                'combined' => $combined,
                                'inlined'  => $inlined,
                                'disabled' => $disabled,
                                'disabled_positions' => $disabled_positions,
                            ] );

                        ?>

                    <?php endif; ?>

                    <?php if ( ! empty( $plugin_files ) ) : ?>

                        <tr class="wpp-files-type">
                            <td colspan="5"><h3><?php _e( 'Plugins', 'wpp' ); ?></h3></td>
                        </tr>

                        <?php 

                            wpp_load_template( 'includes/local_files', [
                                'type'     => 'js',
                                'list'     => $plugin_files,
                                'minified' => $minified,
                                'combined' => $combined,
                                'inlined'  => $inlined,
                                'disabled' => $disabled,
                                'disabled_positions' => $disabled_positions,
                            ] );

                        ?>

                    <?php endif; ?>


                    <?php if ( ! empty( $external_list ) ) : ?>

                        <tr class="wpp-files-type">
                            <td colspan="5"><h3><?php _e( 'External resources', 'wpp' ); ?></h3></td>
                        </tr>

                        <?php 

                            wpp_load_template( 'includes/external_files', [
                                'type'     => 'js',
                                'list'     => $external_list,
                                'disabled' => $disabled,
                                'disabled_positions' => $disabled_positions,
                            ] );

                        ?>

                    <?php endif; ?>

                </tbody>

            </table>

            <br />

            <em><span class="dashicons dashicons-info"></span> <?php _e( 'If some of the files are missing from this list, enable Cache preloading option.', 'wpp' ); ?></em> 

            <br /><br />

            <input type="submit" class="button-primary" value="<?php _e( 'Save changes', 'wpp' ); ?>" name="wpp-save-settings" form="wpp-settings" />

            <a 
                data-description="<?php printf( __( 'Clear collected %s files list.%s This option will also clear the cache.', 'wpp' ), 'JavaScript', '<br />' ); ?>" 
                href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=' . WPP_PLUGIN_ADMIN_URL . '&clear=javascript'), 'clear-list', 'nonce' ); ?>" 
                class="button wpp-load-settings">
                <?php _e( 'Clear files list', 'wpp' ); ?>
            </a> 

        <?php else: ?>

            <h2><?php _e('JavaScript files not found', 'wpp'); ?></h2>

            <br />

            <input type="submit" class="button-primary" value="<?php _e('Reload', 'wpp'); ?>" name="wpp-save-settings" form="wpp-settings" />

        <?php endif; ?>
               

    </div>

    <div class="wpp-side-section">

        <h3><?php _e('JavaScript settings', 'wpp'); ?></h3>
    
        <hr />     

        <?php do_action( 'wpp-javascript-side-section-top'); ?>
          
        <label class="wpp-info">
            <input type="checkbox" value="1" name="js_minify_inline" form="wpp-settings" <?php wpp_checked('js_minify_inline'); ?> />
            <?php _e('Minify inline JavaScript', 'wpp'); ?>
        </label>

        <br /><br />

        <label class="wpp-info">
            <input type="checkbox" value="1" data-wpp-checkbox="js_defer" name="js_defer" form="wpp-settings" <?php wpp_checked( 'js_defer' ); ?> />
            <?php _e( 'Asynchronously load JavaScript', 'wpp' ); ?>
        </label>

        <br /><br />
        <em><span class="dashicons dashicons-info"></span> <?php _e( 'Eliminates render-blocking JavaScript', 'wpp' ); ?></em> 

        <br />
        
        <label class="wpp-info">
            <input type="checkbox" value="1" name="js_disable_loggedin" form="wpp-settings" <?php wpp_checked( 'js_disable_loggedin' ); ?> />
            <?php _e( 'Disable JavaScript optimization for logged-in users', 'wpp' ); ?>
        </label>

        <br />

        <?php if ( ! empty( $prefetch = Option::get( 'prefetch_js_list', [] ) ) ): ?>

            <br /><br />

            <div>

                <h3><?php _e( 'Resource Hints', 'wpp' ); ?></h3>

                <hr />

                <table>
                                        
                    <tr>
                        <th><?php _e( 'Origins', 'wpp' ); ?></th>
                        <th>DNS Prefetch</th>
                        <th>Preconnect</th>
                    </tr>
                    <?php foreach ( $prefetch as $js ): ?>
                        <tr>
                            <td><?php echo $js; ?></td>
                            <td>                        
                                <input 
                                    type="checkbox" 
                                    value="1" 
                                    name="js_prefetch[<?php echo $js; ?>]" 
                                    <?php if ( wpp_key_exists( $js, Option::get( 'js_prefetch', [] ) ) ) echo 'checked'; ?> 
                                    form="wpp-settings" />
                            </td>
                            <td>                        
                                <input 
                                    type="checkbox" 
                                    value="1" 
                                    name="js_preconnect[<?php echo $js; ?>]"
                                    <?php if ( wpp_key_exists( $js, Option::get( 'js_preconnect', [] ) ) ) echo 'checked'; ?> 
                                    form="wpp-settings" />
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <hr />
                <em><span class="dashicons dashicons-info"></span> <?php _e( 'DNS prefetching can make external resources load faster', 'wpp' ); ?></em> 
                <em><span class="dashicons dashicons-info"></span> <?php _e( 'Preconnect can remove additional roundtrips and reduce request latency', 'wpp' ); ?></em> 
                
            </div>

        <?php endif; ?>

        <br /> 

        <h3><?php _e('Exclude URL(s) from JavaScript optimization', 'wpp'); ?></h3>

        <hr />

        <div>

            <?php $excluded_urls = Option::get( 'js_url_exclude', [] ); ?>

            <div id="wpp-exclude-url-js-container">

                <?php if ( ! empty( $pages = Option::get( 'js_post_exclude', [] ) ) ): ?>
        
                    <?php foreach( $pages as $id ): $link = get_permalink( $id ); ?>
                        <div class="wpp-dynamic-input-container">

                            <input class="wpp-dynamic-input" value="<?php echo $link; ?>" type="text" readonly /> &nbsp; 
                            <a 
                                href="#" 
                                class="button wpp-remove-manually-excluded" 
                                data-id="<?php echo $id; ?>" 
                                data-type="js" 
                                data-description="<?php printf( __( 'Remove %s from excluded URL(s)?', 'wpp' ), $link ); ?>">
                                    <?php _e( 'Remove', 'wpp' ); ?>
                            </a>
                        </div>
                    <?php endforeach; ?>

                <?php endif; ?>

                <?php foreach( $excluded_urls as $url ): ?>
                    <div data-dynamic-container="js_url_exclude[]" class="wpp-dynamic-input-container">
                        <input name="js_url_exclude[]" value="<?php echo $url; ?>" placeholder="<?php echo trailingslashit( site_url() ); ?>" class="wpp-dynamic-input" form="wpp-settings" type="text" required> &nbsp; 
                        <a href="#" data-name="js_url_exclude[]" class="button wpp-remove-input"><?php _e('Remove', 'wpp'); ?></a>
                    </div>
                <?php endforeach; ?>

            </div>

            <?php if( ! empty( $excluded_urls ) ) : ?>
                <div data-info-name="js_url_exclude[]">
                    <em><span class="dashicons dashicons-info"></span> <?php _e('Part of the URL will also work', 'wpp'); ?></em>
                    <em><span class="dashicons dashicons-info"></span> <?php _e('Use {numbers} to match only numbers', 'wpp'); ?></em>
                    <em><span class="dashicons dashicons-info"></span> <?php _e('Use {letters} to match only letters', 'wpp'); ?></em>
                    <em><span class="dashicons dashicons-info"></span> <?php _e('Use {any} to match any string', 'wpp'); ?></em>
                    <br>
                </div>
            <?php endif; ?>

            <br /> 

            <a href="#" 
                class="button" 
                data-add-input="js_url_exclude[]" 
                data-placeholder="<?php echo trailingslashit( site_url() ); ?>" 
                data-info="<?php _e('Part of the URL will also work', 'wpp'); ?>|<?php _e('Use {numbers} to match only numbers', 'wpp'); ?>|<?php _e('Use {letters} to match only letters', 'wpp'); ?>|<?php _e('Use {any} to match any string', 'wpp'); ?>" 
                data-container="#wpp-exclude-url-js-container">
                
                <?php _e('Add URL', 'wpp'); ?>
                
            </a>

        </div>


        <?php do_action( 'wpp-javascript-side-section-bottom' ); ?>


    </div>

</div>