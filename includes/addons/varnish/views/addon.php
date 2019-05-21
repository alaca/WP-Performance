<?php namespace WPP;

defined('ABSPATH') or exit; ?>

<div class="wpp-addon">

    <div class="wpp-addon-content">

        <?php if ( Option::boolval( 'varnish_auto_purge' ) ): ?>

            <a href="#" data-addon-settings="varnish" class="wpp-addon-settings-link wpp-addon-settings-toggle">
                <i class="dashicons dashicons-admin-generic"></i>
            </a>

        <?php endif; ?>

        <img src="<?php echo WPP_ADDONS_URL ?>varnish/assets/varnish.png">

        <div>
            <?php _e( 'If Varnish runs on your server, you must activate this add-on.', 'wpp' ); ?>
        </div>

        <div class="wpp-addon-settings" data-addon-settings-content="varnish">

            <hr />

            <p>
                <?php _e( 'Custom host', 'wpp' ); ?>
            </p>

            <input 
                type="text" 
                placeholder="http://"
                name="varnish_custom_host" 
                value="<?php echo Option::get( 'varnish_custom_host' ); ?>" 
                form="wpp-settings"
                class="wpp-addon-input" />  
                    
            <br /><br />

            <em><span class="dashicons dashicons-info"></span> <?php _e( 'If you are using proxy, you may need this option', 'wpp' ); ?></em>

            <br /><br />

        </div>

        <label class="wpp-addon-info">
            <input type="checkbox" value="1" data-wpp-checkbox="varnish_auto_purge" value="1" name="varnish_auto_purge" form="wpp-settings" <?php wpp_checked( 'varnish_auto_purge' ); ?> />
            <?php _e( 'Enable', 'wpp' ); ?> Varnish
        </label>

    </div>

</div>
