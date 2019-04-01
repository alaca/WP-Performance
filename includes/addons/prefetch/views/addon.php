<?php namespace WPP;

defined('ABSPATH') or exit; ?>

<div class="wpp-addon">

    <div class="wpp-addon-content">

        <h2><?php _e( 'Dynamic page preload', 'wpp' ); ?></h2>

        <div>
            <?php _e( 'Preloads a page right before a user clicks on link', 'wpp' ); ?>
        </div>


        <label class="wpp-addon-info">
            <input type="checkbox" value="1" name="prefetch_pages" form="wpp-settings" <?php wpp_checked( 'prefetch_pages' ); ?> />
            <?php _e( 'Enable', 'wpp' ); ?> <?php _e( 'Dynamic page preload', 'wpp' ); ?>
        </label>

    </div>

</div>