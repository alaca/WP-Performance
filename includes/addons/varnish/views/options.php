<?php namespace WPP;

defined('ABSPATH') or exit; 

/**
 * Varnish options
 */

?>

<tr data-wpp-show-checked="cache">
    <td><strong><?php _e( 'Varnish', 'wpp' ); ?></strong></td>
    <td>
        <label class="wpp-info">
            <input type="checkbox"  data-wpp-checkbox="varnish_auto_purge" value="1" name="varnish_auto_purge" form="wpp-settings" <?php wpp_checked( 'varnish_auto_purge' ); ?> />
            <?php _e( 'Enable', 'wpp' ); ?>
        </label>
        <br /> <br />
        <em><span class="dashicons dashicons-info"></span> <?php _e( 'If Varnish runs on your server, you need to activate this option.', 'wpp' ); ?></em>

        <div data-wpp-show-checked="varnish_auto_purge">
            <br />
            <div><?php _e( 'Custom host', 'wpp' ); ?></div><br />
            <input 
                type="text" 
                placeholder="http://"
                name="varnish_custom_host" 
                value="<?php echo Option::get( 'varnish_custom_host' ); ?>" 
                form="wpp-settings"
                class="wpp-dynamic-input" />  
                    
                <br /><br />

            <em><span class="dashicons dashicons-info"></span> <?php _e( 'If you are using proxy, you may need this option', 'wpp' ); ?></em>

        </div>

    </td>
</tr>