<?php namespace WPP;

defined('ABSPATH') or exit; ?>

<div class="wpp-addon">

    <?php if ( Option::boolval( 'cf_enabled' ) ): ?>

        <a href="#" data-wpp-show-page="cloudflare" class="wpp-addon-settings-link">
            <i class="dashicons dashicons-admin-generic"></i>
        </a>

    <?php endif; ?>

    <strong><?php _e( 'Cloudflare', 'wpp' ); ?></strong>

    <div>
        <?php _e( 'Integrate your Cloudflare account with this add-on.', 'wpp' ); ?>
    </div>

    <br />

    <label class="wpp-info">
        <input type="checkbox" value="1" name="cf_enabled" form="wpp-settings" <?php wpp_checked( 'cf_enabled' ); ?> />
        <?php _e( 'Enable', 'wpp' ); ?> Cloudflare
    </label>

</div>