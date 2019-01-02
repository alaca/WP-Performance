<?php namespace WPP;

defined('ABSPATH') or exit; ?>

<div class="wpp-page-wrapper">

    <div class="wpp-content-section">
    
        <table>
            <tr>
                <td colspan="2">
                    <h3><?php _e( 'Settings', 'wpp' ); ?></h3>
                </td>
            </tr>
            <tr>
                <td><strong><?php _e( 'Import settings', 'wpp' ); ?></strong></td>
                <td>
                    <input type="file" accept=".json,application/json" form="wpp-settings" name="wpp_import_settings" />
                </td>
            </tr>
            <tr>
                <td><strong><?php _e( 'Export settings', 'wpp' ); ?></strong></td>
                <td>
                    <a class="button" href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=' . WPP_PLUGIN_ADMIN_URL . '&wpp-export-settings=1' ), 'export-settings', 'wpp-nonce' ); ?>">
                        <?php _e( 'Export', 'wpp' ); ?>
                    </a>
                </td>
            </tr>
            <?php if ( wpp_get_server_software() == 'nginx' || defined( 'WPP_SHOW_NGINX_REWRITE_RULES' ) && WPP_SHOW_NGINX_REWRITE_RULES ) : ?>

                <tr data-wpp-show-checked="nginx_rules">
                    <td colspan="2">
                        <h3><?php _e( 'Server', 'wpp' ); ?></h3>
                    </td>
                </tr>

                <tr data-wpp-show-checked="nginx_rules" data-wpp-highlight-id="nginx_configuration">
                    <td><strong><?php _e( 'Nginx configuration', 'wpp' ); ?></strong></td>
                    <td>
                        <textarea class="wpp-rules-textarea" readonly="readonly"><?php echo wpp_get_nginx_rewrite_rules(); ?></textarea>
                        <em><span class="dashicons dashicons-info"></span> 
                            <?php printf( __( '%s will work out of the box on Nginx servers, this rewrite rules are not required. However, to optimize your website even more, you should add this rules to your Nginx configuration file.', 'wpp' ), WPP_PLUGIN_NAME ); ?>
                        </em> <br />
                        <a href="#" class="button"><?php _e( 'Select all', 'wpp' ); ?></a>
                    </td>
                </tr>

            <?php endif; ?>
            <tr>
                <td colspan="2">
                    <h3><?php _e( 'Logs', 'wpp' ); ?></h3>
                </td>
            </tr>
            <tr>
                <td><strong><?php _e('Troubleshooting logging', 'wpp'); ?></strong></td>
                <td>
                    <label class="wpp-info">
                        <input type="checkbox" value="1" name="enable_log" form="wpp-settings" <?php wpp_checked( 'enable_log' ); ?> />
                        <?php _e( 'Enable troubleshooting logging', 'wpp' ); ?> 
                    </label>
                    <br /><br />
                    <em><span class="dashicons dashicons-info"></span> <?php _e( 'These logs can be helpful for troubleshooting problems', 'wpp' ); ?></em>
                </td>
            </tr>

            <?php if( Option::boolval( 'enable_log' ) ): ?>
                <tr>
                    <td><strong><?php _e('Log file content', 'wpp'); ?></strong></td>
                    <td>
                        <textarea class="wpp-log-textarea" readonly="readonly"><?php echo File::get( wpp_get_log_file() ); ?></textarea>      
                        <em><span class="dashicons dashicons-info"></span> <?php _e( 'Content is auto refreshed every 10 seconds', 'wpp' ); ?></em>                  
                    </td>
                </tr>
            <?php endif; ?>

        </table>

        <br /><br />

        <input type="submit" class="button-primary" value="<?php _e( 'Save changes', 'wpp' ); ?>" name="wpp-save-settings" form="wpp-settings" /> 
        
        <?php if( Option::boolval( 'enable_log' ) ): ?>
            <input type="submit" class="button" value="<?php _e( 'Clear log file', 'wpp' ); ?>" name="wpp-clear-log" form="wpp-settings" />
        <?php endif; ?>

    </div>

    <div class="wpp-side-section">
    
        <h3><?php _e('Previous configuration settings', 'wpp'); ?></h3>
        
        <hr />       
        
        <ul class="wpp-side-section-list">
            <?php 
            foreach ( array_reverse( glob( WPP_DATA_DIR . 'settings/*.json' ) ) as $i => $settings ): 

                if( $i >= 5 ) {
                    unlink( $settings );
                    continue;
                }

                $timestamp = filemtime( $settings );
            ?>
            <li>
                <?php _e( 'Saved', 'wpp' ); ?> <?php echo $saved = date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ); ?>

                <a 
                    data-description="<?php echo __( 'Load configuration settings saved on ', 'wpp' ) . ' ' . $saved; ?>" 
                    href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=' . WPP_PLUGIN_ADMIN_URL . '&load=' . $timestamp ), 'load-config', 'nonce' ); ?>" 
                    class="button alignright wpp-load-settings <?php if( $timestamp == Option::get( 'current_settings' ) ) echo 'wpp-hidden'; ?>">
                    <?php _e( 'Load', 'wpp' ); ?>
                </a> 

            </li>
            <?php endforeach; ?>
        </ul>
        
        
        <br />
        
        
    </div>

</div>