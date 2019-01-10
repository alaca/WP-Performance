<?php namespace WPP;

defined('ABSPATH') or exit; ?>

<?php 

    $spam       = DB::getSpamCount();
    $trash      = DB::getTrashCount();
    $revisions  = DB::getRevisionsCount();
    $transients = DB::getTransientsCount();
    $cron_tasks = DB::getCronTasksCount();
    $drafts     = DB::getAutoDraftsCount();

?>

<div class="wpp-page-wrapper">

    <div class="wpp-content-section">

        <table class="wpp-database-table">

            <tr>
                <td>

                    <a 
                        href="#" 
                        class="button alignright wpp-db-action" 
                        data-action="trash" 
                        data-count="<?php echo $trash; ?>" 
                        data-description="<?php _e( 'Delete all pages, posts and comments from trash', 'wpp' ); ?>">
                            <?php _e( 'Delete', 'wpp' ); ?> 
                    </a>

                    <strong><?php _e( 'Trash', 'wpp' ); ?> (<span class="wpp-db-count" id="wpp-trash-count"><?php echo $trash; ?></span>)</strong>
                    <em><?php _e( 'Delete all pages, posts and comments from trash', 'wpp' ); ?></em>

                    <?php if ( Option::get( 'db_cleanup_frequency' ) !== 'none' ): ?>
                        <br />
                        <label class="wpp-info">
                            <input type="checkbox" value="1" name="db_cleanup_trash" form="wpp-settings" <?php wpp_checked( 'db_cleanup_trash' ); ?> />
                            <?php _e( 'Enable automatic cleanup', 'wpp' ); ?>
                        </label>
                    <?php endif; ?>

                </td>

                <td>

                    <a 
                        href="#" 
                        class="button alignright wpp-db-action" 
                        data-action="spam" 
                        data-count="<?php echo $spam; ?>" 
                        data-description="<?php _e( 'Delete all spam comments', 'wpp' ); ?>">
                            <?php _e( 'Delete', 'wpp' ); ?> 
                    </a>

                    <strong><?php _e( 'Spam', 'wpp' ); ?> (<span class="wpp-db-count" id="wpp-spam-count"><?php echo $spam; ?></span>)</strong>
                    <em><?php _e( 'Delete all spam comments', 'wpp' ); ?></em>

                    <?php if ( Option::get( 'db_cleanup_frequency' ) !== 'none' ): ?>
                        <br />
                        <label class="wpp-info">
                            <input type="checkbox" value="1" name="db_cleanup_spam" form="wpp-settings" <?php wpp_checked( 'db_cleanup_spam' ); ?> />
                            <?php _e( 'Enable automatic cleanup', 'wpp' ); ?>
                        </label>
                    <?php endif; ?>

                </td>
            </tr>

            <tr>

                <td>

                    <a 
                        href="#" 
                        class="button alignright wpp-db-action" 
                        data-action="revisions" 
                        data-count="<?php echo $revisions; ?>" 
                        data-description="<?php _e( 'Delete all revisions', 'wpp' ); ?>">
                            <?php _e( 'Delete', 'wpp' ); ?> 
                    </a>

                    <strong><?php _e( 'Revisions', 'wpp' ); ?> (<span class="wpp-db-count" id="wpp-revisions-count"><?php echo $revisions; ?></span>)</strong>
                    <em><?php _e( 'Delete all revisions', 'wpp' ); ?></em>

                    <?php if ( Option::get( 'db_cleanup_frequency' ) !== 'none' ): ?>
                        <br />
                        <label class="wpp-info">
                            <input type="checkbox" value="1" name="db_cleanup_revisions" form="wpp-settings" <?php wpp_checked( 'db_cleanup_revisions' ); ?> />
                            <?php _e( 'Enable automatic cleanup', 'wpp' ); ?>
                        </label>
                    <?php endif; ?>

                </td>

                <td>
                    <a 
                        href="#" 
                        class="button alignright wpp-db-action" 
                        data-action="transients" 
                        data-count="<?php echo $transients; ?>" 
                        data-description="<?php _e( 'Delete all expired transients', 'wpp' ); ?>">
                            <?php _e( 'Delete', 'wpp' ); ?> 
                    </a>

                    <strong><?php _e( 'Transients', 'wpp' ); ?> (<span class="wpp-db-count" id="wpp-transients-count"><?php echo $transients; ?></span>)</strong>
                    <em><?php _e( 'Delete all expired transients', 'wpp' ); ?></em>

                    <?php if ( Option::get( 'db_cleanup_frequency' ) !== 'none' ): ?>
                        <br />
                        <label class="wpp-info">
                            <input type="checkbox" value="1" name="db_cleanup_transients" form="wpp-settings" <?php wpp_checked( 'db_cleanup_transients' ); ?> />
                            <?php _e( 'Enable automatic cleanup', 'wpp' ); ?>
                        </label>
                    <?php endif; ?>

                </td>
            </tr>

            <tr>

                <td>

                    <a 
                        href="#" 
                        class="button alignright wpp-db-action" 
                        data-action="drafts" 
                        data-count="<?php echo $drafts; ?>" 
                        data-description="<?php _e( 'Delete all auto drafts', 'wpp' ); ?>">
                            <?php _e( 'Delete', 'wpp' ); ?> 
                    </a>

                    <strong><?php _e( 'Auto Drafts', 'wpp' ); ?> (<span class="wpp-db-count" id="wpp-drafts-count"><?php echo $drafts; ?></span>)</strong>
                    <em><?php _e( 'Delete all auto drafts', 'wpp' ); ?></em>

                    <?php if ( Option::get( 'db_cleanup_frequency' ) !== 'none' ): ?>
                        <br />
                        <label class="wpp-info">
                            <input type="checkbox" value="1" name="db_cleanup_autodrafts" form="wpp-settings" <?php wpp_checked( 'db_cleanup_autodrafts' ); ?> />
                            <?php _e( 'Enable automatic cleanup', 'wpp' ); ?>
                        </label>
                    <?php endif; ?>

                </td>


                <td>

                    <a 
                        href="#" 
                        class="button alignright wpp-db-action" 
                        data-action="cron" 
                        data-count="<?php echo $cron_tasks; ?>" 
                        data-description="<?php _e( 'Delete all unused scheduled tasks', 'wpp' ); ?>">
                            <?php _e( 'Delete', 'wpp' ); ?> 
                    </a>

                    <strong><?php _e( 'Scheduled tasks', 'wpp' ); ?> (<span class="wpp-db-count" id="wpp-cron-count"><?php echo $cron_tasks; ?></span>)</strong>
                    <em><?php _e( 'Delete all unused scheduled tasks', 'wpp' ); ?></em>

                    <?php if ( Option::get( 'db_cleanup_frequency' ) !== 'none' ): ?>
                        <br />
                        <label class="wpp-info">
                            <input type="checkbox" value="1" name="db_cleanup_cron" form="wpp-settings" <?php wpp_checked( 'db_cleanup_cron' ); ?> />
                            <?php _e( 'Enable automatic cleanup', 'wpp' ); ?>
                        </label>
                    <?php endif; ?>

                </td>

            </tr>

        </table>
    
        <br /><br />
        
        <em><span class="dashicons dashicons-info"></span> <?php _e( 'Backup your database before running any of these cleanup actions. Once a database cleanup has been performed, there is no way to undo it.', 'wpp' ); ?></em>
        
        <br />

        <input type="submit" class="button-primary" value="<?php _e( 'Save changes', 'wpp' ); ?>" name="wpp-save-settings" form="wpp-settings" />
        
        <a href="#" class="button wpp-db-action" data-action="all" data-description="<?php _e( 'Delete all', 'wpp' ); ?>">
            <?php _e( 'Delete all', 'wpp' ); ?> (<span class="wpp-db-count" id="wpp-all-count"><?php echo ( $spam + $trash + $revisions + $transients + $cron_tasks + $drafts ); ?></span>)
        </a> 
                

    </div>

    <div class="wpp-side-section">
                 
        <h3><?php _e( 'Automatic database cleanup', 'wpp' ); ?></h3>
 
        <hr />
        
        <ul class="wpp-side-section-list">
            <li>
                <strong><?php _e( 'Schedule', 'wpp' ); ?></strong> 
                <select form="wpp-settings" name="automatic_cleanup_frequency">
                    <option value="none"><?php _e( 'Not scheduled', 'wpp' ); ?></option>
                    <option value="wpp_daily" <?php wpp_selected( 'db_cleanup_frequency', 'wpp_daily' ); ?>><?php _e( 'Daily', 'wpp' ); ?></option>
                    <option value="wpp_weekly" <?php wpp_selected( 'db_cleanup_frequency', 'wpp_weekly' ); ?>><?php _e( 'Weekly', 'wpp' ); ?></option>
                    <option value="wpp_monthly" <?php wpp_selected( 'db_cleanup_frequency', 'wpp_monthly' ); ?>><?php _e( 'Monthly', 'wpp' ); ?></option>
                </select>
                <br /><br /><br />
                <?php _e( 'Next run', 'wpp' ); ?>: 
                <?php 
                    
                    if ( $next_schedule  = Option::get( 'db_cleanup_next' ) ) {
                        echo date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_schedule );
                    } else {
                        _e( 'Not set', 'wpp' );
                    }
                    
                ?> 
                
            </li>
        </ul>
                
        
    </div>

</div>