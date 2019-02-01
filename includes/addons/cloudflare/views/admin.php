<?php namespace WPP;

defined('ABSPATH') or exit; ?>

<div class="wpp_page <?php wpp_active( 'cloudflare' ); ?>" data-wpp-page="cloudflare">

    <div class="wpp-page-wrapper">

        <div class="wpp-content-section">
        
            <table>
                <tr>
                    <td colspan="2">
                        <h3>
                            <?php _e( 'Caching', 'wpp' ); ?>
                        </h3>
                    </td>
                </tr>

                <tr>
                    <td><strong><?php _e( 'Cloudflare cache', 'wpp' ); ?></strong></td>
                    <td>
                        <a href="#" id="wpp-clear-cf-cache" data-description="<?php _e( 'Clear all Cloudflare cache', 'wpp' ); ?>" class="button"><?php _e( 'Clear cache', 'wpp' ); ?></a> 
                    </td>
                </tr>

                <tr>
                    <td><strong><?php _e( 'Development mode', 'wpp' ); ?></strong></td>
                    <td>
                        <label class="wpp-info">
                            <input type="checkbox" value="1" name="cf_dev_mode" form="wpp-settings" <?php wpp_checked( 'cf_dev_mode' ); ?> />
                            <?php _e( 'Enable', 'wpp' ); ?>
                        </label>
                        <br /><br />                    
                        <em><span class="dashicons dashicons-info"></span> <?php _e( 'Temporarily activate development mode on your website.', 'wpp' ); ?></em> 
                        <em><span class="dashicons dashicons-info"></span> <?php _e( 'This setting will automatically turn off after 3 hours.', 'wpp' ); ?></em> 
                    </td>
                </tr>

                <tr>
                    <td><strong><?php _e( 'Cache level', 'wpp' ); ?></strong></td>
                    <td>
                        <select name="cf_cache_level" form="wpp-settings">
                            <option value="aggressive" <?php wpp_selected( 'cf_cache_level', 'aggressive', 'aggressive' ); ?>><?php _e( 'Aggressive - Standard', 'wpp' ); ?></option>
                            <option value="simplified" <?php wpp_selected( 'cf_cache_level', 'simplified' ); ?>><?php _e( 'Simplified - Ignore query string', 'wpp' ); ?></option>
                            <option value="basic" <?php wpp_selected( 'cf_cache_level', 'basic' ); ?>><?php _e( 'Basic - No query string', 'wpp' ); ?></option>
                        </select>

                        <br /><br />

                        <em><span class="dashicons dashicons-info"></span> <?php _e( 'Aggressive: Delivers a different resource each time the query string changes.', 'wpp' ); ?></em> 
                        <em><span class="dashicons dashicons-info"></span> <?php _e( 'Simplified: Delivers the same resource to everyone independent of the query string.', 'wpp' ); ?></em> 
                        <em><span class="dashicons dashicons-info"></span> <?php _e( 'Basic: Only delivers resources from cache when there is no query string.', 'wpp' ); ?></em> 

                    </td>
                </tr>

                <?php 
                
                $options = [
                    30, 60, 300, 1200, 1800, 3600, 7200, 10800, 14400, 18000, 28800, 43200, 57600, 72000, 86400, 172800, 259200, 345600, 432000, 691200, 1382400, 2073600, 2678400, 5356800, 16070400, 31536000 
                ];

                ?>

                <tr>
                    <td>
                        <strong><?php _e( 'Browser Cache Expiration', 'wpp' ); ?></strong></td>
                    <td>
                        <select name="cf_browser_expire" form="wpp-settings">
                            <option value=""><?php _e( 'Respect Existing Headers', 'wpp' ); ?></option>
                            <?php foreach( $options as $option ): ?>
                                <option value="<?php echo $option; ?>" <?php wpp_selected( 'cf_browser_expire', $option, 14400 ); ?>>
                                    <?php echo $option; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <br /><br />

                        <em><span class="dashicons dashicons-info"></span> <?php _e( 'Browser cache TTL (in seconds) specifies how long CloudFlare-cached resources will remain on your visitors computers.', 'wpp' ); ?></em> 
                        <em><span class="dashicons dashicons-info"></span> <?php _e( 'The minimum TTL available depends on the plan level of the zone.', 'wpp' ); ?></em> 

                    </td>
                </tr>
                
                <tr>
                    <td colspan="2">
                        <h3>
                            <?php _e( 'Content', 'wpp' ); ?>
                        </h3>
                    </td>
                </tr>

                <tr>
                    <td><strong><?php _e( 'Minify', 'wpp' ); ?></strong></td>
                    <td>
                        <label class="wpp-info">
                            <input type="checkbox" value="1" name="cf_minify_css" form="wpp-settings" <?php wpp_checked( 'cf_minify_css' ); ?> />
                            CSS
                        </label>

                        <br /><br />

                        <label class="wpp-info">
                            <input type="checkbox" value="1" name="cf_minify_js" form="wpp-settings" <?php wpp_checked( 'cf_minify_js' ); ?> />
                            JavaScript
                        </label>

                        <br /><br />

                        <label class="wpp-info">
                            <input type="checkbox" value="1" name="cf_minify_html" form="wpp-settings" <?php wpp_checked( 'cf_minify_html' ); ?> />
                            HTML
                        </label>

                    </td>
                </tr>

                <tr>
                    <td><strong><?php _e( 'Rocket Loader', 'wpp' ); ?></strong></td>
                    <td>
                        <label class="wpp-info">
                            <input type="checkbox" value="1" name="cf_rocket_loader" form="wpp-settings" <?php wpp_checked( 'cf_rocket_loader' ); ?> />
                            <?php _e( 'Enable', 'wpp' ); ?>
                        </label>
                        <br /><br />                    
                        <em><span class="dashicons dashicons-info"></span> <?php _e( 'Rocket Loader is a asynchronous JavaScript loader.', 'wpp' ); ?></em> 
                    </td>
                </tr>

                <tr>
                    <td><strong><?php _e( 'Brotli', 'wpp' ); ?></strong></td>
                    <td>
                        <label class="wpp-info">
                            <input type="checkbox" value="1" name="cf_brotli" form="wpp-settings" <?php wpp_checked( 'cf_brotli' ); ?> />
                            <?php _e( 'Enable', 'wpp' ); ?>
                        </label>
                        <br /><br />                    
                        <em><span class="dashicons dashicons-info"></span> <?php _e( 'Speed up page load times for your visitor’s HTTPS traffic by applying Brotli compression.', 'wpp' ); ?></em> 
                    </td>
                </tr>

            </table>

            <br /><br />

            <input type="submit" class="button-primary" value="<?php _e( 'Save changes', 'wpp' ); ?>" name="wpp-cf-save-settings" form="wpp-settings" />

            <br /><br />


        </div>

        <div class="wpp-side-section">
        
            <h3><?php _e( 'Cloudflare credentials', 'wpp' ); ?></h3>
            
            <hr />     

            <strong><?php _e( 'API key', 'wpp' ); ?></strong>
            <input name="cf_api_key" class="wpp-cf-input" value="<?php echo Option::get( 'cf_api_key' ); ?>" type="text" form="wpp-settings" />

            <a href="https://support.cloudflare.com/hc/en-us/articles/200167836-Where-do-I-find-my-Cloudflare-API-key-" target="_blank">Find your API key</a>

            <br /><br />

            <strong><?php _e( 'Account email', 'wpp' ); ?></strong>
            <input name="cf_email" class="wpp-cf-input" value="<?php echo Option::get( 'cf_email' ); ?>" type="email" form="wpp-settings" />

            <br /><br />

            <strong><?php _e( 'Zone ID', 'wpp' ); ?></strong>
            <input name="cf_zone_id" class="wpp-cf-input" value="<?php echo Option::get( 'cf_zone_id' ); ?>" type="text" form="wpp-settings" />


            <br /><br />

        </div>

    </div>
    
</div>