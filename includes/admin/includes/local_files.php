<?php namespace WPP;

defined('ABSPATH') or exit; ?>

<?php foreach ( $list as $i => $resource ): ?>

    <tr class="<?php if ( wpp_key_exists( $resource, $disabled ) ) echo 'wpp-disabled-row'; ?>">

        <td class="wpp-list-filename">

            <?php $info = pathinfo( $resource ); ?>

            <strong><?php echo $info[ 'basename' ]; ?></strong>
            <em><?php echo $info[ 'dirname' ]; ?></em>

            <?php if ( wpp_key_exists( $resource, $disabled ) ): ?>

                <div class="wpp-disable-select" data-wpp-option="<?php echo $type ?>_position_<?php echo $i; ?>">
                    
                    <select 
                        class="wpp-disable-select-position" 
                        data-wpp-file="<?php echo $resource; ?>" 
                        data-wpp-index="<?php echo $i; ?>" 
                        data-wpp-prefix="<?php echo $type ?>" 
                        data-wpp-options="<?php echo $type ?>_minify|<?php echo $type ?>_inline|<?php echo $type ?>_combine" 
                        data-wpp-container="wpp-option-<?php echo $type ?>-<?php echo $i; ?>" 
                        name="<?php echo $type ?>_disable_position[<?php echo $resource; ?>]" 
                        form="wpp-settings">

                        <option value="everywhere" <?php if ( wpp_key_exists( 'everywhere', $disabled_positions, $resource ) ) echo 'selected="selected"'; ?>>
                            <?php _e( 'Disable everywhere', 'wpp' ); ?>
                        </option>

                        <option value="selected" <?php if ( wpp_key_exists('selected', $disabled_positions, $resource )  ) echo 'selected="selected"'; ?>>
                            <?php _e( 'Disable only on selected URL', 'wpp' ); ?>
                        </option>

                        <option value="except" <?php if ( wpp_key_exists( 'except', $disabled_positions, $resource )  ) echo 'selected="selected"'; ?>>
                            <?php _e( 'Disable everywhere except on selected URL', 'wpp' ); ?>
                        </option>

                    </select>       

                    <div class="wpp-disabled-options-container" id="wpp-option-<?php echo $type ?>-<?php echo $i; ?>">
                    
                        <?php $selected_found = false; ?>
                        
                        <?php foreach( Option::get('<?php echo $type ?>_disable_selected', [] ) as $file => $urls ): ?>
                        
                            <?php if ( $file != $resource ) continue; $selected_found = true; ?>

                            <?php foreach( $urls as $url ): ?>

                                <div data-dynamic-container="<?php echo $type ?>_disable_selected[<?php echo $resource; ?>][]" class="wpp-dynamic-input-container">

                                    <input 
                                        name="<?php echo $type ?>_disable_selected[<?php echo $resource; ?>][]" 
                                        value="<?php echo $url; ?>" 
                                        placeholder="<?php echo trailingslashit( site_url() ); ?>" 
                                        class="wpp-dynamic-input" 
                                        form="wpp-settings" 
                                        type="text" 
                                        required> &nbsp; 

                                    <a href="#" data-name="<?php echo $type ?>_disable_selected[<?php echo $resource; ?>][]" class="button wpp-remove-input"><?php _e('Remove', 'wpp'); ?></a>

                                </div>

                            <?php endforeach; ?>

                        <?php endforeach; ?>

                        <?php $everywhere_except = false; ?>

                        <?php foreach( Option::get('<?php echo $type ?>_disable_except', [] ) as $file => $urls ): ?>
                        
                            <?php if ( $file != $resource ) continue; $everywhere_except = true; ?>

                            <?php foreach( $urls as $url ): ?>

                                <div data-dynamic-container="<?php echo $type ?>_disable_except[<?php echo $resource; ?>][]" class="wpp-dynamic-input-container">

                                    <input 
                                        name="<?php echo $type ?>_disable_except[<?php echo $resource; ?>][]" 
                                        value="<?php echo $url; ?>" 
                                        placeholder="<?php echo trailingslashit( site_url() ); ?>" 
                                        class="wpp-dynamic-input" 
                                        form="wpp-settings" 
                                        type="text" 
                                        required> &nbsp; 

                                    <a href="#" data-name="<?php echo $type ?>_disable_except[<?php echo $resource; ?>][]" class="button wpp-remove-input"><?php _e('Remove', 'wpp'); ?></a>

                                </div>

                            <?php endforeach; ?>

                        <?php endforeach; ?>

                    </div>

                    <?php if ( $selected_found ): ?>

                        <a href="#" 
                            data-placeholder="<?php echo trailingslashit( site_url() ); ?>" 
                            class="button wpp-disable-container-options-btn" 
                            data-add-input="<?php echo $type ?>_disable_selected[<?php echo $resource; ?>][]"
                            data-container="#wpp-option-<?php echo $type ?>-<?php echo $i; ?>">
                            <?php _e( 'Add URL', 'wpp' ); ?></a>

                    <?php endif; ?>

                    <?php if ( $everywhere_except ): ?>

                        <a href="#" 
                            data-placeholder="<?php echo trailingslashit( site_url() ); ?>" 
                            class="button wpp-disable-container-options-btn" 
                            data-add-input="<?php echo $type ?>_disable_except[<?php echo $resource; ?>][]"  
                            data-container="#wpp-option-<?php echo $type ?>-<?php echo $i; ?>">
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

                if ( wpp_is_minified( $resource ) ) {
                    echo 'data-disabled="true" disabled="disabled" checked="checked"'; 
                } else { 
                    if ( wpp_key_exists( 'everywhere', $disabled_positions, $resource ) ) { 
                        echo 'disabled="disabled"'; 
                    } else { 
                        if ( wpp_key_exists( $resource, $minified ) ) echo 'checked="checked"';
                    } 
                } 

                ?> 
                data-wpp-group="<?php echo $type ?>-minify" 
                name="<?php echo $type ?>_minify[<?php echo $resource; ?>]" 
                form="wpp-settings" />

            <span class="wpp-visible-mobile">
                <?php _e( 'Minify', 'wpp' ) ?>
            </span>

        </td>

        <td>

            <input 
                type="checkbox" 
                value="1" 
                data-wpp-group="<?php echo $type ?>-inline" 
                data-wpp-disable-option="<?php echo $type ?>_combine[<?php echo $resource; ?>]" 
                name="<?php echo $type ?>_inline[<?php echo $resource; ?>]" 
                <?php if ( wpp_key_exists( $resource, $inlined ) ) echo 'checked="checked"'; ?> 
                <?php if ( wpp_key_exists( 'everywhere', $disabled_positions, $resource ) || wpp_key_exists( $resource, $combined ) ) echo 'disabled="disabled"'; ?> 
                form="wpp-settings" />

            <span class="wpp-visible-mobile">
                <?php _e( 'Inline', 'wpp' ) ?>
            </span>

        </td>

        <td>

            <input 
                type="checkbox" 
                value="1" 
                data-wpp-group="<?php echo $type ?>-combine" 
                data-wpp-disable-option="<?php echo $type ?>_inline[<?php echo $resource; ?>]" 
                name="<?php echo $type ?>_combine[<?php echo $resource; ?>]" 
                <?php if ( wpp_key_exists( $resource, $combined ) ) echo 'checked="checked"'; ?> 
                <?php if ( wpp_key_exists( 'everywhere', $disabled_positions, $resource ) || wpp_key_exists( $resource, $inlined ) ) echo 'disabled="disabled"'; ?> 
                form="wpp-settings" />

            <span class="wpp-visible-mobile">
                <?php _e( 'Combine', 'wpp' ) ?>
            </span>

        </td>

        <td>
        
            <input 
                type="checkbox" 
                value="1" 
                class="wpp-disable-option" 
                data-wpp-index="<?php echo $i; ?>" 
                data-wpp-file="<?php echo $resource; ?>" 
                data-wpp-name="<?php echo $type ?>_disable_position" 
                data-wpp-prefix="<?php echo $type ?>" 
                data-wpp-show-option="<?php echo $type ?>_position_<?php echo $i; ?>" 
                data-wpp-option-data="<?php echo $type ?>_combine|<?php echo $type ?>_inline|<?php echo $type ?>_minify" 
                data-wpp-group="<?php echo $type ?>-disable" 
                data-wpp-disable-option="<?php echo $type ?>_combine[<?php echo $resource; ?>]|<?php echo $type ?>_inline[<?php echo $resource; ?>]|<?php echo $type ?>_minify[<?php echo $resource; ?>]" 
                name="<?php echo $type ?>_disable[<?php echo $resource; ?>]" 
                <?php if ( wpp_key_exists( $resource, $disabled ) ) echo 'checked="checked"'; ?> 
                form="wpp-settings" />

            <span class="wpp-visible-mobile">
                <?php _e( 'Disable', 'wpp' ) ?>
            </span>
                
        </td>
    </tr>

<?php endforeach; ?>