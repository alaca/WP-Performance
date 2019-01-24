<?php namespace WPP;

defined('ABSPATH') or exit; 

$minified           = Option::get( 'css_minify', [] );
$combined           = Option::get( 'css_combine', [] );
$inlined            = Option::get( 'css_inline', [] );
$disabled           = Option::get( 'css_disable', [] );
$disabled_positions = Option::get( 'css_disable_position', [] );
$theme_files        = Option::get( 'theme_css_list', [] );
$plugin_files       = Option::get( 'plugin_css_list', [] );
$external_list      = Option::get( 'external_css_list', [] );

?>

<div class="wpp-page-wrapper">

    <div class="wpp-content-section">

        <?php if ( ! empty( $theme_files ) ): ?>
        
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
                        <td><em><span class="dashicons dashicons-info"></span> <?php _e( 'Update all files in list below', 'wpp' ); ?></em></td>
                        <td><input type="checkbox" class="wpp-update-checkboxes" data-wpp-group="css-minify" /></td>
                        <td><input type="checkbox" class="wpp-update-checkboxes" data-wpp-group="css-inline" /></td>
                        <td><input type="checkbox" class="wpp-update-checkboxes" data-wpp-group="css-combine" /></td>
                        <td><input type="checkbox" class="wpp-update-checkboxes" data-wpp-group="css-disable" /></td>
                    </tr>

                    <?php if ( ! empty( $theme_files ) ) : ?>

                        <tr class="wpp-files-type">
                            <td colspan="5"><h3><?php _e( 'Theme', 'wpp' ); ?></h3></td>
                        </tr>

                        <?php 
                        
                            wpp_load_template( 'includes/local_files', [
                                'type'     => 'css',
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
                                'type'     => 'css',
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
                                'type'     => 'css',
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
                data-description="<?php printf( __( 'Clear collected %s files list.%s This option will also clear the cache.', 'wpp' ), 'CSS', '<br />' ); ?>" 
                href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=' . WPP_PLUGIN_ADMIN_URL . '&clear=css'), 'clear-list', 'nonce' ); ?>" 
                class="button wpp-load-settings">
                <?php _e( 'Clear files list', 'wpp' ); ?>
            </a> 

        <?php else: ?>

            <h2><?php _e('CSS files not found', 'wpp'); ?></h2>

            <br />

            <input type="submit" class="button-primary" value="<?php _e('Reload', 'wpp'); ?>" name="wpp-save-settings" form="wpp-settings" />

        <?php endif; ?>

    </div>

    <div class="wpp-side-section">

        <h3><?php _e('CSS settings', 'wpp'); ?></h3>
    
        <hr />    

        <?php do_action( 'wpp-css-side-section-top'); ?> 

        
        <?php 
        
        $css_custom_path         = Option::get( 'css_custom_path_def' ); 
        $css_custom_path_defined = boolval( trim( $css_custom_path ) );
        
        ?>

        <label class="wpp-info">
            <input type="checkbox" value="1" name="css_minify_inline" form="wpp-settings" <?php wpp_checked( 'css_minify_inline' ); ?> />
            <?php _e( 'Minify inline CSS', 'wpp' ); ?>
        </label>

        <br /><br />

        <label class="wpp-info">

            <input type="checkbox" value="1" data-wpp-checkbox="css_defer" name="css_defer" form="wpp-settings" <?php wpp_checked( 'css_defer' ); ?> />
            <?php _e( 'Asynchronously load CSS', 'wpp' ); ?>

            <?php if ( $css_custom_path_defined ): ?>
                           
                <span data-wpp-show-checked="css_defer">

                    <a href="#" 
                        id="wpp-show-hide-critical-css"
                        class="button alignright" 
                        data-wpp-toggle-id="css-path" 
                        data-wpp-toggle-show="<?php _e( 'Show critical CSS', 'wpp' ); ?>" 
                        data-wpp-toggle-hide="<?php _e( 'Hide critical CSS', 'wpp' ); ?>">
                        <?php echo ( $css_custom_path_defined ) ?  __( 'Show critical CSS', 'wpp' ) : __( 'Hide critical CSS', 'wpp' ); ?>
                    </a>

                </span>

            <?php endif; ?>

        </label>

        <br /><br />
        <em><span class="dashicons dashicons-info"></span> <?php _e( 'Eliminates render-blocking CSS', 'wpp' ); ?></em> 

        <br />

        <span data-wpp-show-checked="css_defer" data-wpp-toggle="css-path" class="<?php if ( $css_custom_path_defined ) echo 'wpp-hidden'; ?>">
            
            <em class="wpp-warning"><span class="dashicons dashicons-info"></span> <?php printf( __( 'Loading CSS asynchronously will produce FOUC effect (a Flash Of Unstyled Content). In order to minimize the FOUC effect, use critical CSS path.', 'wpp' ), WPP_PLUGIN_NAME ); ?></em> 
            <br />

        </span>
        
        
        <div data-wpp-show-checked="css_defer" data-wpp-toggle="css-path" class="<?php if ( $css_custom_path_defined ) echo 'wpp-hidden'; ?>">

            <strong><?php _e( 'Critical CSS path', 'wpp' ); ?></strong>

            <textarea name="css_custom_path_def" id="wpp-css-custom-path-def" form="wpp-settings"><?php echo $css_custom_path; ?></textarea> 
            <br />

            <em><span class="dashicons dashicons-info"></span> <?php _e( 'Enter critical CSS path definitions', 'wpp' ); ?></em> 
            <br />

            <?php if ( ! wpp_is_localhost() ): ?>
                <a href="#" class="button <?php if ( $css_custom_path_defined ) echo 'wpp-hidden'; ?>" <?php if ( $css_custom_path_defined ) echo 'disabled'; ?> id="wpp-get-critical-css" data-wpp-toggle="css-path">
                    <?php _e( 'Generate critical CSS path', 'wpp' ); ?>
                </a>
                <hr />
            <?php endif; ?>

        </div>



        <?php $prefetch = Option::get( 'prefetch_css_list', [] ); ?>

        <?php if ( wpp_in_array( 'fonts.googleapis.com', $prefetch ) ): ?>

            <label class="wpp-info">
                <input type="checkbox" value="1" name="css_combine_fonts" form="wpp-settings" <?php wpp_checked( 'css_combine_fonts' ); ?> />
                <?php _e( 'Combine Google Fonts', 'wpp' ); ?>
            </label>

            <br /><br />

            <em><span class="dashicons dashicons-info"></span> <?php _e( 'Load all google fonts in one HTTP request', 'wpp' ); ?></em> 

            <br />

        <?php endif; ?>
        
        
        <label class="wpp-info">
            <input type="checkbox" value="1" name="css_disable_loggedin" form="wpp-settings" <?php wpp_checked( 'css_disable_loggedin' ); ?> />
            <?php _e( 'Disable CSS optimization for logged-in users', 'wpp' ); ?>
        </label>

        <br /> 

        <?php if ( ! empty( $prefetch ) ): ?>

            <br /><br />

            <div>

                <h3><?php _e( 'Resource Hints', 'wpp' ); ?></h3>

                <hr />

                <table class="wpp-resource-hints-table">
                                        
                    <tr>
                        <th><?php _e( 'Origins', 'wpp' ); ?></th>
                        <th>DNS Prefetch</th>
                        <th>Preconnect</th>
                    </tr>
                    <?php foreach ( $prefetch as $css ): ?>
                        <tr>
                            <td><?php echo $css; ?></td>
                            <td>                        
                                <input 
                                    type="checkbox" 
                                    value="1" 
                                    name="css_prefetch[<?php echo $css; ?>]" 
                                    <?php if ( wpp_key_exists( $css, Option::get( 'css_prefetch', [] ) ) ) echo 'checked'; ?> 
                                    form="wpp-settings" />
                            </td>
                            <td>                        
                                <input 
                                    type="checkbox" 
                                    value="1" 
                                    name="css_preconnect[<?php echo $css; ?>]"
                                    <?php if ( wpp_key_exists( $css, Option::get( 'css_preconnect', [] ) ) ) echo 'checked'; ?> 
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

        <h3><?php _e('Exclude URL(s) from CSS optimization', 'wpp'); ?></h3>

        <hr />

        <div>

            <?php $excluded_urls = Option::get( 'css_url_exclude', [] ); ?>

            <div id="wpp-exclude-url-css-container">

                <?php if ( ! empty( $pages = Option::get( 'css_post_exclude', [] ) ) ): ?>
            
                    <?php foreach( $pages as $id ): $link = get_permalink( $id ); ?>
                        <div class="wpp-dynamic-input-container">

                            <input class="wpp-dynamic-input" value="<?php echo $link; ?>" type="text" readonly /> &nbsp; 
                            <a 
                                href="#" 
                                class="button wpp-remove-manually-excluded" 
                                data-id="<?php echo $id; ?>" 
                                data-type="css" 
                                data-description="<?php printf( __( 'Remove %s from excluded URL(s)?', 'wpp' ), $link ); ?>">
                                    <?php _e( 'Remove', 'wpp' ); ?>
                            </a>
                        </div>
                    <?php endforeach; ?>

                <?php endif; ?>

                <?php foreach( $excluded_urls as $url ): ?>
                    <div data-dynamic-container="css_url_exclude[]" class="wpp-dynamic-input-container">
                        <input name="css_url_exclude[]" value="<?php echo $url; ?>" placeholder="<?php echo trailingslashit( site_url() ); ?>" class="wpp-dynamic-input" form="wpp-settings" type="text" required> &nbsp; 
                        <a href="#" data-name="css_url_exclude[]" class="button wpp-remove-input"><?php _e('Remove', 'wpp'); ?></a>
                    </div>
                <?php endforeach; ?>

            </div>

            <?php if( ! empty( $excluded_urls ) ) : ?>
                <div data-info-name="css_url_exclude[]">
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
                data-add-input="css_url_exclude[]" 
                data-placeholder="<?php echo trailingslashit( site_url() ); ?>" 
                data-info="<?php _e('Part of the URL will also work', 'wpp'); ?>|<?php _e('Use {numbers} to match only numbers', 'wpp'); ?>|<?php _e('Use {letters} to match only letters', 'wpp'); ?>|<?php _e('Use {any} to match any string', 'wpp'); ?>" 
                data-container="#wpp-exclude-url-css-container">

                <?php _e('Add URL', 'wpp'); ?>

            </a>

        </div>


        <?php do_action( 'wpp-css-side-section-bottom'); ?>

    </div>

</div>