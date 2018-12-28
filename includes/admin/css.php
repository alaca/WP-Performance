<?php namespace WPP;

defined('ABSPATH') or exit; ?>

<div class="wpp-page-wrapper">

    <div class="wpp-content-section">

        <?php if ( ! empty( $list = Option::get( 'local_css_list' ) ) ): ?>

            <table class="wpp-file-list">

                <thead>
                    <tr>
                        <th><?php _e('File', 'wpp'); ?></th>
                        <th><?php _e('Minify', 'wpp'); ?></th>
                        <th><?php _e('Inline', 'wpp'); ?></th>
                        <th><?php _e('Combine', 'wpp'); ?></th>
                        <th><?php _e('Disable', 'wpp'); ?></th>
                    </tr>
                </thead>

                <tbody>
                    <tr class="wpp-bulk-update">
                        <td><?php _e('Update all files in list below', 'wpp'); ?></td>
                        <td><input type="checkbox" class="wpp-update-checkboxes" data-wpp-group="css-minify" /></td>
                        <td><input type="checkbox" class="wpp-update-checkboxes" data-wpp-group="css-inline" /></td>
                        <td><input type="checkbox" class="wpp-update-checkboxes" data-wpp-group="css-combine" /></td>
                        <td><input type="checkbox" class="wpp-update-checkboxes" data-wpp-group="css-disable" /></td>
                    </tr>
                        <?php 

                        $minified = Option::get( 'css_minify', [] );
                        $combined = Option::get( 'css_combine', [] );
                        $inlined  = Option::get( 'css_inline', [] );
                        $disabled = Option::get( 'css_disable', [] );
                        $disabled_positions = Option::get( 'css_disable_position', [] );

                        ?>

                        <?php foreach ( $list as $i => $css ): ?>

                            <tr class="<?php if ( wpp_key_exists( $css, $disabled ) ) echo 'wpp-disabled-row'; ?>">

                                <td class="wpp-list-filename">

                                    <?php $info = pathinfo( $css ); ?>

                                    <strong><?php echo $info[ 'basename' ]; ?></strong>
                                    <em><?php echo site_url(  $info[ 'dirname' ] ); ?></em>

                                    <?php if ( wpp_key_exists( $css, $disabled ) ): ?>

                                        <div class="wpp-disable-select" data-wpp-option="css_position_<?php echo $i; ?>">
                                            
                                            <select 
                                                class="wpp-disable-select-position" 
                                                data-wpp-file="<?php echo $css; ?>" 
                                                data-wpp-index="<?php echo $i; ?>" 
                                                data-wpp-prefix="css" 
                                                data-wpp-options="css_minify|css_inline|css_combine" 
                                                data-wpp-container="wpp-option-css-<?php echo $i; ?>" 
                                                name="css_disable_position[<?php echo $css; ?>]" 
                                                form="wpp-settings">

                                                <option value="everywhere" <?php if ( wpp_key_exists( 'everywhere', $disabled_positions, $css ) ) echo 'selected="selected"'; ?>>
                                                    <?php _e( 'Disable everywhere', 'wpp' ); ?>
                                                </option>

                                                <option value="selected" <?php if ( wpp_key_exists('selected', $disabled_positions, $css )  ) echo 'selected="selected"'; ?>>
                                                    <?php _e( 'Disable only on selected URL', 'wpp' ); ?>
                                                </option>

                                                <option value="except" <?php if ( wpp_key_exists( 'except', $disabled_positions, $css )  ) echo 'selected="selected"'; ?>>
                                                    <?php _e( 'Disable everywhere execpt on selected URL', 'wpp' ); ?>
                                                </option>

                                            </select>       

                                            <div class="wpp-disabled-options-container" id="wpp-option-css-<?php echo $i; ?>">
                                            
                                                <?php $selected_found = false; ?>
                                                
                                                <?php foreach( Option::get('css_disable_selected', [] ) as $file => $urls ): ?>
                                                
                                                    <?php if ( $file != $css ) continue; $selected_found = true; ?>

                                                    <?php foreach( $urls as $url ): ?>

                                                        <div data-dynamic-container="css_disable_selected[<?php echo $css; ?>][]" class="wpp-dynamic-input-container">

                                                            <input 
                                                                name="css_disable_selected[<?php echo $css; ?>][]" 
                                                                value="<?php echo $url; ?>" 
                                                                placeholder="<?php echo trailingslashit( site_url() ); ?>" 
                                                                class="wpp-dynamic-input" 
                                                                form="wpp-settings" 
                                                                type="text" 
                                                                required> &nbsp; 

                                                            <a href="#" data-name="css_disable_selected[<?php echo $css; ?>][]" class="button wpp-remove-input"><?php _e('Remove', 'wpp'); ?></a>

                                                        </div>

                                                    <?php endforeach; ?>

                                                <?php endforeach; ?>

                                                <?php $everywhere_except = false; ?>

                                                <?php foreach( Option::get('css_disable_except', [] ) as $file => $urls ): ?>
                                                
                                                    <?php if ( $file != $css ) continue; $everywhere_except = true; ?>

                                                    <?php foreach( $urls as $url ): ?>

                                                        <div data-dynamic-container="css_disable_except[<?php echo $css; ?>][]" class="wpp-dynamic-input-container">

                                                            <input 
                                                                name="css_disable_except[<?php echo $css; ?>][]" 
                                                                value="<?php echo $url; ?>" 
                                                                placeholder="<?php echo trailingslashit( site_url() ); ?>" 
                                                                class="wpp-dynamic-input" 
                                                                form="wpp-settings" 
                                                                type="text" 
                                                                required> &nbsp; 

                                                            <a href="#" data-name="css_disable_except[<?php echo $css; ?>][]" class="button wpp-remove-input"><?php _e('Remove', 'wpp'); ?></a>

                                                        </div>

                                                    <?php endforeach; ?>

                                                <?php endforeach; ?>

                                            </div>

                                            <?php if ( $selected_found ): ?>

                                                <a href="#" 
                                                    data-placeholder="<?php echo trailingslashit( site_url() ); ?>" 
                                                    class="button wpp-disable-container-options-btn" 
                                                    data-add-input="css_disable_selected[<?php echo $css; ?>][]"
                                                    data-container="#wpp-option-css-<?php echo $i; ?>">
                                                    <?php _e( 'Add URL', 'wpp' ); ?></a>

                                            <?php endif; ?>

                                            <?php if ( $everywhere_except ): ?>

                                                <a href="#" 
                                                    data-placeholder="<?php echo trailingslashit( site_url() ); ?>" 
                                                    class="button wpp-disable-container-options-btn" 
                                                    data-add-input="css_disable_except[<?php echo $css; ?>][]"  
                                                    data-container="#wpp-option-css-<?php echo $i; ?>">
                                                    <?php _e( 'Add URL', 'wpp' ); ?></a>
                                            
                                            <?php endif; ?>

                                        </div>

                                    <?php endif;  ?>

                                </td>

                                <td>

                                    <input 
                                        type="checkbox" 
                                        value="1" 
                                        <?php 

                                        if ( wpp_is_minified( $css ) ) {
                                            echo 'data-disabled="true" disabled="disabled" checked="checked"'; 
                                        } else { 
                                            if ( wpp_key_exists( 'everywhere', $disabled_positions, $css ) ) { 
                                                echo 'disabled="disabled"'; 
                                            } else { 
                                                if ( wpp_key_exists( $css, $minified ) ) echo 'checked="checked"';
                                            } 
                                        } 

                                        ?> 
                                        data-wpp-group="css-minify" 
                                        name="css_minify[<?php echo $css; ?>]" 
                                        form="wpp-settings" />
                                </td>

                                <td>

                                    <input 
                                        type="checkbox" 
                                        value="1" 
                                        data-wpp-group="css-inline" 
                                        data-wpp-disable-option="css_combine[<?php echo $css; ?>]" 
                                        name="css_inline[<?php echo $css; ?>]" 
                                        <?php if ( wpp_key_exists( $css, $inlined ) ) echo 'checked="checked"'; ?> 
                                        <?php if ( wpp_key_exists( 'everywhere', $disabled_positions, $css ) || wpp_key_exists( $css, $combined ) ) echo 'disabled="disabled"'; ?> 
                                        form="wpp-settings" />

                                </td>

                                <td>

                                    <input 
                                        type="checkbox" 
                                        value="1" 
                                        data-wpp-group="css-combine" 
                                        data-wpp-disable-option="css_inline[<?php echo $css; ?>]" 
                                        name="css_combine[<?php echo $css; ?>]" 
                                        <?php if ( wpp_key_exists( $css, $combined ) ) echo 'checked="checked"'; ?> 
                                        <?php if ( wpp_key_exists( 'everywhere', $disabled_positions, $css ) || wpp_key_exists( $css, $inlined ) ) echo 'disabled="disabled"'; ?> 
                                        form="wpp-settings" />

                                </td>

                                <td>
                                
                                    <input 
                                        type="checkbox" 
                                        value="1" 
                                        class="wpp-disable-option" 
                                        data-wpp-index="<?php echo $i; ?>" 
                                        data-wpp-file="<?php echo $css; ?>" 
                                        data-wpp-name="css_disable_position" 
                                        data-wpp-prefix="css" 
                                        data-wpp-show-option="css_position_<?php echo $i; ?>" 
                                        data-wpp-option-data="css_combine|css_inline|css_minify" 
                                        data-wpp-group="css-disable" 
                                        data-wpp-disable-option="css_combine[<?php echo $css; ?>]|css_inline[<?php echo $css; ?>]|css_minify[<?php echo $css; ?>]" 
                                        name="css_disable[<?php echo $css; ?>]" 
                                        <?php if ( wpp_key_exists( $css, $disabled ) ) echo 'checked="checked"'; ?> 
                                        form="wpp-settings" />
                                        
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php if ( ! empty( $external = Option::get( 'external_css_list', [] ) ) ): ?>

                        <tr class="wpp-bulk-update">
                            <td colspan="5"><h3><?php _e( 'External resources', 'wpp' ); ?></h3></td>
                        </tr>

                        <?php foreach ( $external as $key => $name ): ?>

                            <?php $css = wpp_get_file_clean_name( $name ); ?>

                            <tr class="<?php if ( wpp_key_exists( $css, $disabled ) ) echo 'wpp-disabled-row'; ?>">
                            
                                <td colspan="4" class="wpp-list-filename">

                                    <strong><?php echo urldecode( $name ); ?></strong>

                                    <?php if ( wpp_key_exists( $css, $disabled ) ): ?>

                                        <div class="wpp-disable-select" data-wpp-option="css_position_<?php echo $css; ?>">
                                            
                                            <select 
                                                class="wpp-disable-select-position" 
                                                data-wpp-index="<?php echo $key; ?>" 
                                                data-wpp-container="wpp-option-css-<?php echo $css; ?>" 
                                                name="css_disable_position[<?php echo $css; ?>]" 
                                                form="wpp-settings">
                                                    
                                                <option value="everywhere" <?php if ( wpp_key_exists( 'everywhere', $disabled_positions, $css ) ) echo 'selected="selected"'; ?>>
                                                    <?php _e( 'Disable everywhere', 'wpp' ); ?>
                                                </option>

                                                <option value="selected_url" <?php if ( wpp_key_exists('selected_url', $disabled_positions, $css )  ) echo 'selected="selected"'; ?>>
                                                    <?php _e( 'Disable only on selected URL', 'wpp' ); ?>
                                                </option>

                                                <option value="everywhere_except" <?php if ( wpp_key_exists( 'everywhere_except', $disabled_positions, $css )  ) echo 'selected="selected"'; ?>>
                                                    <?php _e( 'Disable everywhere except on selected URL', 'wpp' ); ?>
                                                </option>

                                            </select>       

                                            <div class="wpp-disabled-options-container" id="wpp-option-css-<?php echo $css; ?>">
                                            
                                                <?php $selected_found = false; ?>
                                                
                                                <?php foreach( Option::get('css_disable_selected', [] ) as $file => $urls ): ?>
                                                
                                                    <?php if ( $file != $css ) continue; $selected_found = true; ?>

                                                    <?php foreach( $urls as $url ): ?>

                                                        <div data-dynamic-container="css_disable_selected[<?php echo $css; ?>][]" class="wpp-dynamic-input-container">

                                                            <input 
                                                                name="css_disable_selected[<?php echo $css; ?>][]" 
                                                                value="<?php echo $url; ?>" 
                                                                placeholder="<?php echo trailingslashit( site_url() ); ?>" 
                                                                class="wpp-dynamic-input" 
                                                                form="wpp-settings" 
                                                                type="text" 
                                                                required> &nbsp; 

                                                            <a href="#" data-name="css_disable_selected[<?php echo $css; ?>][]" class="button wpp-remove-input">
                                                                <?php _e('Remove', 'wpp'); ?>
                                                            </a>

                                                        </div>

                                                    <?php endforeach; ?>

                                                <?php endforeach; ?>

                                                <?php $everywhere_except = false; ?>

                                                <?php foreach( Option::get('css_disable_except', [] ) as $file => $urls ): ?>
                                                
                                                    <?php if ( $file != $css ) continue; $everywhere_except = true; ?>

                                                    <?php foreach( $urls as $url ): ?>

                                                        <div data-dynamic-container="css_disable_except[<?php echo $css; ?>][]" class="wpp-dynamic-input-container">

                                                            <input 
                                                                name="css_disable_except[<?php echo $css; ?>][]" 
                                                                value="<?php echo $url; ?>" placeholder="<?php echo trailingslashit( site_url() ); ?>" 
                                                                class="wpp-dynamic-input" 
                                                                form="wpp-settings" 
                                                                type="text" 
                                                                required> &nbsp; 
                                                            
                                                            <a href="#" data-name="css_disable_except[<?php echo $css; ?>][]" class="button wpp-remove-input">
                                                                <?php _e('Remove', 'wpp'); ?>
                                                            </a>

                                                        </div>

                                                    <?php endforeach; ?>

                                                <?php endforeach; ?>


                                            </div>

                                            <?php if ( $selected_found ): ?>

                                                <a href="#" 
                                                    data-placeholder="<?php echo trailingslashit( site_url() ); ?>" 
                                                    class="button wpp-disable-container-options-btn" 
                                                    data-add-input="css_disable_selected[<?php echo $css; ?>][]"  
                                                    data-container="#wpp-option-css-<?php echo $css; ?>">
                                                    <?php _e( 'Add URL', 'wpp' ); ?></a>
                                            
                                            <?php endif; ?>

                                            <?php if ( $everywhere_except ): ?>

                                                <a href="#" 
                                                    data-placeholder="<?php echo trailingslashit( site_url() ); ?>" 
                                                    class="button wpp-disable-container-options-btn" 
                                                    data-add-input="css_disable_except[<?php echo $css; ?>][]"  
                                                    data-container="#wpp-option-css-<?php echo $css; ?>">
                                                    <?php _e( 'Add URL', 'wpp' ); ?></a>
                                            
                                            <?php endif; ?>

                                        </div>

                                    <?php endif; ?>

                                </td>
                                <td>

                                    <input 
                                        type="checkbox" 
                                        value="1" 
                                        class="wpp-disable-option" 
                                        data-wpp-index="<?php echo $css; ?>" 
                                        data-wpp-show-option="css_position_<?php echo $css; ?>" 
                                        data-wpp-group="css-disable" 
                                        data-wpp-disable-option="" 
                                        name="css_disable[<?php echo $css; ?>]" 
                                        <?php if ( wpp_key_exists( $css, $disabled ) ) echo 'checked="checked"'; ?> 
                                        form="wpp-settings" />
                                
                                </td>
                            </tr>


                        <?php endforeach; ?>

                    <?php endif;  ?>

                </tbody>

            </table>

            <br />

            <em><span class="dashicons dashicons-info"></span> <?php _e( 'If some of the files are missing from this list, enable Cache preloading option.', 'wpp' ); ?></em> 
            
            <br /><br />

            <input type="submit" class="button-primary" value="<?php _e('Save changes', 'wpp'); ?>" name="wpp-save-settings" form="wpp-settings" />

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

        <label class="wpp-info">
            <input type="checkbox" value="1" name="css_minify_inline" form="wpp-settings" <?php wpp_checked( 'css_minify_inline' ); ?> />
            <?php _e( 'Minify inline CSS', 'wpp' ); ?>
        </label>

        <br /><br />

        <label class="wpp-info">
            <input type="checkbox" value="1" data-wpp-checkbox="css_defer" name="css_defer" form="wpp-settings" <?php wpp_checked( 'css_defer' ); ?> />
            <?php _e( 'Asynchronously load CSS', 'wpp' ); ?>
        </label>

        <br /><br />
        <em><span class="dashicons dashicons-info"></span> <?php _e( 'Eliminates render-blocking JavaScript', 'wpp' ); ?></em> 

        <span data-wpp-show-checked="css_defer">
            <br />
            <em class="wpp-warning"><span class="dashicons dashicons-info"></span> <?php printf( __( 'Loading CSS asynchronously will produce FOUC effect (a Flash Of Unstyled Content). In order to minimize the FOUC effect, use critical CSS path.', 'wpp' ), WPP_PLUGIN_NAME ); ?></em> 
        </span>
        
        <br />

        <div data-wpp-show-checked="css_defer">

            <strong><?php _e( 'Critical CSS path', 'wpp' ); ?></strong>

            <textarea name="css_custom_path_def" id="wpp-css-custom-path-def" form="wpp-settings"><?php echo Option::get( 'css_custom_path_def' ); ?></textarea> 
            <br />

            <em><span class="dashicons dashicons-info"></span> <?php _e( 'Enter critical CSS path definitions', 'wpp' ); ?></em> 
            <br />

            <?php if ( ! wpp_is_localhost() ): ?>
                <a href="#" class="button" <?php if ( boolval( trim( Option::get( 'css_custom_path_def' ) ) ) ) echo 'disabled'; ?> id="wpp-get-critical-css">
                    <?php _e( 'Generate critical CSS path', 'wpp' ); ?>
                </a>
                <br /><br />
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

                <h3><?php _e( 'Prefetch external CSS resources', 'wpp' ); ?></h3>

                <hr />

                <ul>
                    <?php foreach ( $prefetch as $css ): ?>
                        <li>
                            <label class="wpp-info">                        
                                <input type="checkbox" value="1" name="css_prefetch[<?php echo $css; ?>]" <?php if ( wpp_key_exists( $css, Option::get( 'css_prefetch', [] ) ) ) echo 'checked'; ?> form="wpp-settings" />
                                <?php echo $css; ?>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <em><span class="dashicons dashicons-info"></span> <?php _e( 'DNS prefetching can make external resources load faster', 'wpp' ); ?></em> 
                
            </div>

        <?php endif; ?>

        <br />

        <h3><?php _e('Exclude URL(s) from CSS optimization', 'wpp'); ?></h3>

        <hr />

        <div>

            <?php $excluded_urls = Option::get( 'css_url_exclude', [] ); ?>

            <div id="wpp-exclude-url-css-container">

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