<?php namespace WPP;

defined('ABSPATH') or exit; ?>

<div class="wpp-addon">

    <div class="wpp-addon-content">

        <?php if ( Option::boolval( 'cf_enabled' ) ): ?>

            <a href="#" data-wpp-show-page="cloudflare" class="wpp-addon-settings-link">
                <i class="dashicons dashicons-admin-generic"></i>
            </a>

        <?php endif; ?>

        <img src="<?php echo WPP_ADDONS_URL ?>cloudflare/assets/cf-logo-h.svg">

        <div>
            <?php _e( 'Integrate your Cloudflare account with this add-on.', 'wpp' ); ?>
        </div>

        <br />

        <label class="wpp-addon-info">
            <input type="checkbox" value="1" name="cf_enabled" form="wpp-settings" <?php wpp_checked( 'cf_enabled' ); ?> />
            <?php _e( 'Enable', 'wpp' ); ?> Cloudflare
        </label>

    </div>

</div>