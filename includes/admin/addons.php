<?php namespace WPP; 

defined('ABSPATH') or exit; ?>

<div class="wpp-page-wrapper">

    <div class="wpp-content-section">

        <div class="wpp-addons">

            <?php do_action( 'wpp-display-addons' ); ?>
            
        </div>

        <br /><br />

        <input type="submit" class="button-primary" value="<?php _e( 'Save changes', 'wpp' ); ?>" name="wpp-save-settings" form="wpp-settings" />

        <br /><br />

    </div>

    <div class="wpp-side-section">
    
        <div>

            <h3><?php _e( 'Add-ons', 'wpp' ); ?></h3>

            <hr />

            <?php printf( __( 'Add more features to %s with add-ons.', 'wpp' ), WPP_PLUGIN_NAME ); ?>

        </div>  
        
        
    </div>

</div>