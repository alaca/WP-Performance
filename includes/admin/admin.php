<?php namespace WPP;

defined('ABSPATH') or exit; 

?>

<div class="wrap">

    <h1>
        <?php echo WPP_PLUGIN_NAME; ?> 
        <span id="wpp_version"><?php echo WPP_VERSION; ?></span>
    </h1>
    
    <form id="wpp-settings" name="wpp-settings" method="post" action="<?php echo admin_url( 'admin.php?page=' . WPP_PLUGIN_ADMIN_URL ); ?>" enctype="multipart/form-data">
        <input type="hidden" id="wpp_tab" name="wpp-tab" value="<?php if ( ! Input::request( 'wpp-tab' ) ) echo 'cache'; else echo Input::request( 'wpp-tab' ); ?>" />
        <?php wp_nonce_field('save-settings', 'wpp-nonce'); ?>
    </form>
        
    <br />
    
    <div id="wpp_tabs_menu">
        <ul>
            <li><a href="#" class="<?php wpp_active( 'cache', true ); ?>" data-wpp-page-id="cache"><?php _e( 'Cache', 'wpp' ); ?></a></li>
            <li><a href="#" class="<?php wpp_active( 'css' ); ?>" data-wpp-page-id="css">CSS</a></li>
            <li><a href="#" class="<?php wpp_active( 'javascript' ); ?>" data-wpp-page-id="javascript">JavaScript</a></li>
            <li><a href="#" class="<?php wpp_active( 'html' ); ?>" data-wpp-page-id="html">HTML</a></li>
            <li><a href="#" class="<?php wpp_active( 'media' ); ?>" data-wpp-page-id="media"><?php _e( 'Media', 'wpp' ); ?></a></li>
            <li><a href="#" class="<?php wpp_active( 'database' ); ?>" data-wpp-page-id="database"><?php _e( 'Database', 'wpp' ); ?></a></li>
            <li><a href="#" class="<?php wpp_active( 'cdn' ); ?>" data-wpp-page-id="cdn">CDN</a></li>
            <li><a href="#" class="<?php wpp_active( 'settings' ); ?>" data-wpp-page-id="settings"><?php _e( 'Settings', 'wpp' ); ?></a></li>
            <li><a href="#" class="<?php wpp_active( 'addons' ); ?>" data-wpp-page-id="addons"><?php _e( 'Add-ons', 'wpp' ); ?></a></li>
            <?php do_action( 'wpp-admin-menu' ); ?>
        </ul>
    </div>

    <div id="wpp_mobile_menu">

        <select name="wpp-mobile-menu" id="wpp-mobile-menu-select">
            <option value="cache" <?php wpp_active( 'cache', true, 'selected' ); ?>><?php _e( 'Cache', 'wpp' ); ?></option>
            <option value="css" <?php wpp_active( 'css', false, 'selected' ); ?>>CSS</option>
            <option value="html" <?php wpp_active( 'html', true, 'selected' ); ?>>HTML</option>
            <option value="javascript" <?php wpp_active( 'javascript', false, 'selected' ); ?>>JavaScript</option>
            <option value="media" <?php wpp_active( 'media', false, 'selected' ); ?>><?php _e( 'Media', 'wpp' ); ?></option>
            <option value="database" <?php wpp_active( 'database', false, 'selected' ); ?>><?php _e( 'Database', 'wpp' ); ?></option>
            <option value="cdn" <?php wpp_active( 'cdn', false, 'selected' ); ?>>CDN</option>
            <option value="settings" <?php wpp_active( 'settings', false, 'selected' ); ?>><?php _e( 'Settings', 'wpp' ); ?></option>
            <option value="addons" <?php wpp_active( 'addons', false, 'selected' ); ?>><?php _e( 'Add-ons', 'wpp' ); ?></option>
            <?php do_action( 'wpp-admin-menu-mobile' ); ?>
        </select>

    </div>
    
    <div class="wpp_page <?php wpp_active( 'cache', true ); ?>" data-wpp-page="cache">   
        <?php include WPP_ADMIN_DIR . 'cache.php'; ?>
    </div>
    
    <div class="wpp_page <?php wpp_active( 'css' ); ?>" data-wpp-page="css">
        <?php include WPP_ADMIN_DIR . 'css.php'; ?>
    </div>  

    <div class="wpp_page <?php wpp_active( 'html' ); ?>" data-wpp-page="html">
        <?php include WPP_ADMIN_DIR . 'html.php'; ?>
    </div>  

    <div class="wpp_page <?php wpp_active( 'javascript' ); ?>" data-wpp-page="javascript">
        <?php include WPP_ADMIN_DIR . 'javascript.php'; ?>
    </div>  
    
    <div class="wpp_page <?php wpp_active( 'media' ); ?>" data-wpp-page="media">
        <?php include WPP_ADMIN_DIR . 'media.php'; ?>
    </div>      
      
    <div class="wpp_page <?php wpp_active( 'database' ); ?>" data-wpp-page="database">
        <?php include WPP_ADMIN_DIR . 'database.php'; ?>
    </div>  

    <div class="wpp_page <?php wpp_active( 'cdn' ); ?>" data-wpp-page="cdn">
        <?php include WPP_ADMIN_DIR . 'cdn.php'; ?>
    </div>    

    <div class="wpp_page <?php wpp_active( 'addons' ); ?>" data-wpp-page="addons">
        <?php include WPP_ADMIN_DIR . 'addons.php'; ?>
    </div>    
    
    <div class="wpp_page <?php wpp_active( 'settings' ); ?>" data-wpp-page="settings">
        <?php include WPP_ADMIN_DIR . 'settings.php'; ?>
    </div>
          
    <?php do_action( 'wpp-admin-page-content' ); ?>
    
</div>