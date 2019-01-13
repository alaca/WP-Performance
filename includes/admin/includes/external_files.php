<?php namespace WPP;

defined('ABSPATH') or exit; 

?>

<?php foreach ( $list as $key => $name ): ?>

    <?php $resource = wpp_get_file_clean_name( $name ); ?>

    <tr class="<?php if ( wpp_key_exists( $resource, $disabled ) ) echo 'wpp-disabled-row'; ?>">

        <td colspan="4" class="wpp-list-filename">

            <strong><?php echo urldecode( $name ); ?></strong>

            <?php if ( wpp_key_exists( $resource, $disabled ) ): ?>

                <div class="wpp-disable-select" data-wpp-option="<?php echo $type ?>_position_<?php echo $resource; ?>">
                    
                    <select 
                        class="wpp-disable-select-position" 
                        data-wpp-file="<?php echo $resource; ?>" 
                        data-wpp-index="<?php echo $key; ?>" 
                        data-wpp-prefix="<?php echo $type ?>" 
                        data-wpp-container="wpp-option-<?php echo $type ?>-<?php echo $resource; ?>" 
                        name="<?php echo $type ?>_disable_position[<?php echo $resource; ?>]" 
                        form="wpp-settings">
                            
                        <option value="everywhere" <?php if ( wpp_key_exists( 'everywhere', $disabled_positions, $resource ) ) echo 'selected="selected"'; ?>>
                            <?php _e( 'Disable everywhere', 'wpp' ); ?>
                        </option>

                        <option value="selected" <?php if ( wpp_key_exists( 'selected', $disabled_positions, $resource )  ) echo 'selected="selected"'; ?>>
                            <?php _e( 'Disable only on selected URL', 'wpp' ); ?>
                        </option>

                        <option value="except" <?php if ( wpp_key_exists( 'except', $disabled_positions, $resource )  ) echo 'selected="selected"'; ?>>
                            <?php _e( 'Disable everywhere except on selected URL', 'wpp' ); ?>
                        </option>

                    </select>       

                    <div class="wpp-disabled-options-container" id="wpp-option-<?php echo $type ?>-<?php echo $resource; ?>">
                    
                        <?php $selected_found = false; ?>
                        
                        <?php foreach( Option::get( $type . '_disable_selected', [] ) as $file => $urls ): ?>
                        
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

                                    <a href="#" data-name="<?php echo $type ?>_disable_selected[<?php echo $resource; ?>][]" class="button wpp-remove-input">
                                        <?php _e('Remove', 'wpp'); ?>
                                    </a>

                                </div>

                            <?php endforeach; ?>

                        <?php endforeach; ?>

                        <?php $everywhere_except = false; ?>

                        <?php foreach( Option::get( $type . '_disable_except', [] ) as $file => $urls ): ?>
                        
                            <?php if ( $file != $resource ) continue; $everywhere_except = true; ?>

                            <?php foreach( $urls as $url ): ?>

                                <div data-dynamic-container="<?php echo $type ?>_disable_except[<?php echo $resource; ?>][]" class="wpp-dynamic-input-container">

                                    <input 
                                        name="<?php echo $type ?>_disable_except[<?php echo $resource; ?>][]" 
                                        value="<?php echo $url; ?>" placeholder="<?php echo trailingslashit( site_url() ); ?>" 
                                        class="wpp-dynamic-input" 
                                        form="wpp-settings" 
                                        type="text" 
                                        required> &nbsp; 
                                    
                                    <a href="#" data-name="<?php echo $type ?>_disable_except[<?php echo $resource; ?>][]" class="button wpp-remove-input">
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
                            data-add-input="<?php echo $type ?>_disable_selected[<?php echo $resource; ?>][]"  
                            data-container="#wpp-option-<?php echo $type ?>-<?php echo $resource; ?>">
                            <?php _e( 'Add URL', 'wpp' ); ?></a>
                    
                    <?php endif; ?>

                    <?php if ( $everywhere_except ): ?>

                        <a href="#" 
                            data-placeholder="<?php echo trailingslashit( site_url() ); ?>" 
                            class="button wpp-disable-container-options-btn" 
                            data-add-input="<?php echo $type ?>_disable_except[<?php echo $resource; ?>][]"  
                            data-container="#wpp-option-<?php echo $type ?>-<?php echo $resource; ?>">
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
                data-wpp-index="<?php echo $resource; ?>" 
                data-wpp-file="<?php echo $resource; ?>" 
                data-wpp-name="<?php echo $type ?>_disable_position" 
                data-wpp-prefix="<?php echo $type ?>" 
                data-wpp-show-option="<?php echo $type ?>_position_<?php echo $resource; ?>" 
                data-wpp-option-data="" 
                data-wpp-group="<?php echo $type ?>-disable" 
                data-wpp-disable-option="" 
                name="<?php echo $type ?>_disable[<?php echo $resource; ?>]" 
                <?php if ( wpp_key_exists( $resource, $disabled ) ) echo 'checked="checked"'; ?> 
                form="wpp-settings" />

            <span class="wpp-visible-mobile">
                <?php _e( 'Disable', 'wpp' ) ?>
            </span>
        
        </td>
    </tr>


<?php endforeach; ?>