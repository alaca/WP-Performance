/**
 * WP Performance Admin
 */
jQuery(document).ready(function ($) {

    jconfirm.defaults = {
        title: WPP.lang.confirm,
        theme: 'supervan',
        useBootstrap: false,
        draggable: false,
    };

    $(document).on('click', '#wpp_tabs_menu li a', function (e) {
        e.preventDefault();
        var tab = $(this).data('wpp-page-id');
        $('#wpp_tab').val(tab);
        $('#wpp_tabs_menu a, .wpp_page').removeClass('active');
        $('[data-wpp-page="' + tab + '"]').add(this).addClass('active');
    });

    $(document).on('click', '[data-wpp-show-page]', function (e) {
        e.preventDefault();
        var tab = $(this).data('wpp-show-page');
        var highlight = $(this).data('wpp-highlight');

        $('[data-wpp-page-id="' + tab + '"]').click();

        if (highlight) {
            var container =  $('[data-wpp-highlight-id="' + highlight + '"]');
            container.addClass('wpp-highlight');
            setTimeout( function(){
                container.one( 'hover', function(){
                    $(this).removeClass('wpp-highlight');
                } );
            }, 500 );
        }
    });

    $(document).on('change', '#wpp_mobile_menu select', function (e) {
        e.preventDefault();
        var tab = $(this).val();
        $('#wpp_tab').val(tab);
        $('#wpp_tabs_menu a, .wpp_page').removeClass('active');
        $('[data-wpp-page="' + tab + '"]').add(this).addClass('active');
    });

    /**
     * Select rules
     */
    $(document).on('click', '.wpp-select-rules', function(e){
        e.preventDefault();
        $('.wpp-rules-textarea').select();
    });


    /**
     * Remove excluded page
     */
     $('.wpp-remove-manually-excluded').on('click', function(e){
        e.preventDefault();

        var that = this;
        var id = $(this).data('id');
        var type = $(this).data('type');

        $.confirm({
            content: $(this).data('description'),
            buttons: {
                Confirm: function () {

                    $.ajax({
                        method: 'POST',
                        url: ajaxurl,
                        data: {
                            id: id,
                            type: type,
                            action: 'wpp_remove_post_options',
                            nonce: WPP.nonce
                        }
                    }).done(function () {
                        $(that).parent('.wpp-dynamic-input-container').fadeOut();
                    });

                },
                Cancel: function () { }
            }
        });

     });


    /**
     * Checkbox confirmation
     */
     $('.wpp-action-confirm').on('click', function(e){

        var that = this;

        if (  $(that).is(':checked') ) {

            $.confirm({
                content: $(this).data('description'),
                buttons: {
                    Confirm: function () {
    
                        $(that).attr('checked', true );
    
                    },
                    Cancel: function () {
                        $(that).attr('checked', false );
                    }
                }
            });

        } else {
            $(that).attr('checked', false );
        }

     });


    /**
     * Toggle options
     */
     $('[data-wpp-toggle-id]').on('click', function(e){

        e.preventDefault();

        var id = $(this).data('wpp-toggle-id');
        var show = $(this).data('wpp-toggle-show');
        var hide = $(this).data('wpp-toggle-hide');
        var toggle = $('[data-wpp-toggle="' + id + '"]');

        if ( toggle.hasClass('wpp-hidden') ) {
            toggle.removeClass('wpp-hidden');
            $(this).text(hide);
        } else {
            toggle.addClass('wpp-hidden');
            $(this).text(show);
        }

     });

    /**
     * Show hide containers depending on checked options
     */
    $('[data-wpp-checkbox]').each(function () {

        var name =  $(this).data('wpp-checkbox');

        if ( $(this).is(':checked') ) {

            if ( name.indexOf('|') !== -1 ) {

                var names = name.split('|');

                for( var i in names ) {
                    $('[data-wpp-show-checked="' + names[i] + '"]').show();
                }

            } else {
                $('[data-wpp-show-checked="' + name + '"]').show();
            }
            
        }

    });


    /**
     * Show hide containers on click
     */
    $(document).on('click', '[data-wpp-checkbox]', function (e) {

        var containers = [];

        var name = $(this).data('wpp-checkbox');
        
        if ( name.indexOf('|') !== -1 ) {

            var names = name.split('|');

            for( var n in names ) {
                containers.push( names[ n ] );
            }

        } else {
            containers.push( name );
        }

        for( var j in containers ) {

            var variations = $('[data-wpp-checkbox*="' + containers[ j ] + '"]');

            if (variations.length > 1) {

                var checked = false;
    
                $.each(variations, function (i, v) {
    
                    if ($(v).is(':checked')) {
                        checked = true;
                        return;
                    }
    
                });
    
                if (checked) {
                    $('[data-wpp-show-checked="' + containers[ j ] + '"]').show();
                } else {
                    $('[data-wpp-show-checked="' + containers[ j ] + '"]').hide();
                }
    
            } else {
                $('[data-wpp-show-checked="' + containers[ j ] + '"]').toggle();
            }
    
        }

    });

    /**
     * Clear cache
     */
    $(document).on('click', '#wpp-clear-cache, li.wpp_clear_cache a', function (e) {

        e.preventDefault();

        $.confirm({
            content: $(this).data('description') || $(this).attr('title'),
            buttons: {
                Confirm: function () {

                    $('body').append('<div id="wpp_overlay"><img id="wpp_loader" src="' + WPP.path + 'loader.svg" /></div>');

                    $.ajax({
                        method: 'POST',
                        url: ajaxurl,
                        dataType: 'json',
                        data: {
                            action: 'wpp_clear_cache'
                        }
                    }).done(function () {
                        $('#wpp_overlay').remove();
                        $('#wpp-cache-size span').html('0.00');
                    });

                },
                Cancel: function () { }
            }
        });

    });


    /**
     * Get critical css path
     */
    $(document).on('click', '#wpp-get-critical-css', function (e) {

        e.preventDefault();

        var btn = $(this);

        if ( btn.attr('disabled') === 'disabled' ) return false;

        btn.attr( 'disabled', true );

        btn.after( '<img id="wpp-ajax-loader" src="' + WPP.admin_url + 'images/spinner.gif">' );

        $.ajax({
            method: 'POST',
            url: ajaxurl,
            dataType: 'json',
            data: {
                action: 'wpp_get_critical_css_path',
                nonce: WPP.nonce
            }
        }).done(function ( response ) {

            if ( response.status ) {
                $('#wpp-css-custom-path-def').val( response.data );
            } else {
                console.log( response.message );
                btn.attr( 'disabled', false );
            }

            $('#wpp-ajax-loader').remove();
            
        }).fail(function(){

            btn.attr( 'disabled', false );
            $('#wpp-ajax-loader').remove();

        });

    });


    /**
     * Enable get critical css path btn
     */
    $(document).on('keyup', '#wpp-css-custom-path-def', function (e) {

        if ( ! $(this).val() ) {
            $('#wpp-get-critical-css').removeAttr('disabled');
        }

    });



    /**
     * Update group of options
     */
    $(document).on('click', '.wpp-update-checkboxes', function (e) {

        var that = this;

        var items = $('[data-wpp-group="' + $(this).data('wpp-group') + '"]').not(':disabled');

        $.each(items, function(i, e){

            var checked = $(that).is(':checked');
            var show = $(e).data('wpp-show-option');

            $(e).prop('checked', checked);

            if( show && checked ){

                var index = $(e).data('wpp-index');
                var file = $(e).data('wpp-file');
                var name = $(e).data('wpp-name');
                var prefix = $(e).data('wpp-prefix');
                var data = $(e).data('wpp-option-data');

                $(this).parents('tr').addClass('wpp-disabled-row');

                // Check if option already exists
                if( ! $('[data-wpp-option="' + show + '"]' ).length ) {
    
                    // Add option
                    var select  = '<div class="wpp-disable-select" data-wpp-option="' + show + '">';
                    select += '<select class="wpp-disable-select-position" data-wpp-options="' + data + '" data-wpp-prefix="' + prefix + '" data-wpp-file="' + file + '" data-wpp-container="wpp-option-' + prefix + '-' + index + '" name="' + name + '[' + file + ']" form="wpp-settings">';
                    select += '<option value="everywhere">' + WPP.lang.disable_everywhere + '</option>';
                    select += '<option value="selected">' + WPP.lang.disable_selected_url + '</option>';
                    select += '<option value="except">' + WPP.lang.disable_everywhere_except + '</option></select>';
                    select += '<div class="wpp-disabled-options-container" id="wpp-option-' + prefix + '-' + index + '"></div></div>';
        
                        $(this).parent().siblings('.wpp-list-filename').append( select );

                }

            }else{

                $(this).parents('tr').removeClass('wpp-disabled-row');

                $( '[data-wpp-option="' + show + '"]' ).remove();

            }

            var option = $(e).data('wpp-disable-option');

            if (option) {

                var options = option.split('|');

                for ( var i in options ) {
                    $('input[name="' + options[i] + '"]').not('[data-disabled]').attr('disabled', $(e).is(':checked') );

                    if( show && checked ) {
                        $( '[data-wpp-option="' + show + '"]' ).removeClass('wpp-hidden');
                    } else {
                        $( '[data-wpp-option="' + show + '"]' ).addClass('wpp-hidden');
                    }

                }
    
            }

        });
    });


    /**
     * Disable option
     */
    $('[data-wpp-disable-option]').on('click', function(){

        var option  = $(this).data('wpp-disable-option');
        var checked = $(this).is(':checked');
        var show    = $(this).data('wpp-show-option');
        
        
        if( show && checked ){

            var index = $(this).data('wpp-index');
            var file = $(this).data('wpp-file');
            var name = $(this).data('wpp-name');
            var prefix = $(this).data('wpp-prefix');
            var data = $(this).data('wpp-option-data');


            $(this).parents('tr').addClass('wpp-disabled-row');

            // Remove if already exists
            $('[data-wpp-option="' + show + '"]' ).remove();

            // Add option
            var select  = '<div class="wpp-disable-select" data-wpp-option="' + show + '">';
                select += '<select class="wpp-disable-select-position" data-wpp-options="' + data + '" data-wpp-prefix="' + prefix + '" data-wpp-file="' + file + '" data-wpp-container="wpp-option-' + prefix + '-' + index + '" name="' + name + '[' + file + ']" form="wpp-settings">';
                select += '<option value="everywhere">' + WPP.lang.disable_everywhere + '</option>';
                select += '<option value="selected">' + WPP.lang.disable_selected_url + '</option>';
                select += '<option value="except">' + WPP.lang.disable_everywhere_except + '</option></select>';
                select += '<div class="wpp-disabled-options-container" id="wpp-option-' + prefix + '-' + index + '"></div></div>';

                $(this).parent().siblings('.wpp-list-filename').append( select );

        }else{

            if( show ) {
                $(this).parents('tr').removeClass('wpp-disabled-row');
            }

            
            $( '[data-wpp-option="' + show + '"]' ).remove();

        }

        if( option ) {

            var options = option.split('|');

            for ( var i in options ) {

                $('input[name="' + options[i] + '"]').not('[data-disabled]').attr('disabled', checked );

                if( show && checked ) {
                    $( '[data-wpp-option="' + show + '"]' ).removeClass('wpp-hidden');
                } else {
                    $( '[data-wpp-option="' + show + '"]' ).addClass('wpp-hidden');
                }
            }

        }

    });

    /**
     * Disable position options
     */
    $(document).on('change', '.wpp-disable-select-position', function(){

        var position = $(this).val();
        var file = $(this).data('wpp-file');
        var prefix = $(this).data('wpp-prefix');
        var container = $(this).data('wpp-container');
        var data_options = $(this).data('wpp-options');

        var options = ( data_options ) ? data_options.split('|') : false ;

        switch( position ) {
            case 'selected':
            case 'except':

                $('#' + container).html('');
                $('[data-container="#' + container + '"]').remove();

                $('#' + container).after('<a href="#" data-placeholder="' + WPP.site_url + '" class="button wpp-disable-container-options-btn" data-add-input="' + prefix + '_disable_' + position + '[' + file + '][]"  data-container="#' + container + '">' + WPP.lang.add_url + '</a>');

                $('[data-add-input="' + prefix + '_disable_' + position + '[' + file + '][]"]').click();

                $.each(options, function(i, name ){

                    $('input[name="' + name + '[' + file + ']"]').not('[data-disabled]').attr('disabled', false );

                });
                
                break;
            default:

                $('#' + container).html('');
                $('[data-container="#' + container + '"]').remove();

                if ( options ) {

                    $.each(options , function(i, name ){

                        $('input[name="' + name + '[' + file + ']"]').attr('disabled', true );
    
                    });

                }

        }
        
    });

    /**
     * Add image size
     */
    $(document).on('click', '#wpp-add-image-size', function (e) {

        e.preventDefault();

        var row = '<tr class="wpp-add-image-size-row"><td><input type="text" placeholder="name" size="12" name="name" />&nbsp;</td>';
        row += '<td><input type="text" placeholder="width" size="3" maxlength="4" name="width" />&nbsp;</td>';
        row += '<td><input type="text" placeholder="height" size="3" maxlength="4" name="height" />&nbsp;</td>';
        row += '<td><input type="checkbox" name="crop" />&nbsp;</td>';
        row += '<td><a href="#" class="button wpp-save-image-size">Save</a> <a href="#" class="button wpp-remove-user-image-size">x</a></td></tr>';

        var lastSize = $('#wpp-image-sizes-table tbody tr:last-child');

        if (lastSize.length > 0) {
            lastSize.after(row);
        } else {
            $('#wpp-image-sizes-table tbody').html(row);
        }

    });


    /**
     * Remove image size
     */
    $(document).on('click', '.wpp-remove-user-image-size', function (e) {

        e.preventDefault();

        var self = $(this);
        var name = self.data('size-name');
        var description = self.data('description');

        if (name) {

            $.confirm({
                content: description,
                buttons: {
                    Confirm: function () {

                        self.parents('tr').remove();

                        $.ajax({
                            method: 'POST',
                            url: ajaxurl,
                            dataType: 'json',
                            data: {
                                action: 'wpp_images_action',
                                image_action: 'remove_size',
                                size: name,
                                nonce: WPP.nonce
                            }
                        }).done(function (response) {

                            if (!response.status) {
                                $('#wpp-image-errors').text(response.error);
                            }

                        });

                    },
                    Cancel: function () { }
                }
            });

        } else {

            self.parents('tr').remove();

        }

    });


    /**
     * Restore original image sizes
     */
    $(document).on('click', '#wpp-restore-image-sizes', function (e) {

        e.preventDefault();

        var description = $(this).data('description');

        $.confirm({
            content: description,
            buttons: {
                Confirm: function () {

                    $.ajax({
                        method: 'POST',
                        url: ajaxurl,
                        dataType: 'json',
                        data: {
                            action: 'wpp_images_action',
                            image_action: 'restore_sizes',
                            nonce: WPP.nonce
                        },
                        
                    }).done(function (response) {

                        if (response.status) {

                            // response from first request? hmm...
                            $.post(ajaxurl, { action: 'wpp_images_action', image_action: 'get_all_sizes', nonce: WPP.nonce }, function (data) {

                                var tableBody = $('#wpp-image-sizes-table tbody');

                                tableBody.html('');

                                data.sizes.forEach(function (size) {

                                    var row = '<tr><td>' + size.name + '</td>';
                                    row += '<td>' + size.width + ' px</td>';
                                    row += (size.height > 0) ? '<td>' + size.height + ' px</td>' : '<td></td>';
                                    row += '<td>' + ( ( size.crop > 0 ) ? WPP.lang.yes : '' ) + '</td>';
                                    row += '<td><a href="#" data-size-name="' + size.name + '" class="button wpp-remove-user-image-size">x</a></td></tr>';

                                    tableBody.append(row);

                                });

                            });

                        } else {

                            $('#wpp-image-errors').text(response.error);

                        }

                    });

                },
                Cancel: function () { }
            }
        });


    });


    /**
     * Save image size
     */
    $(document).on('click', '.wpp-save-image-size', function (e) {

        e.preventDefault();

        $('#wpp-image-errors').text('');

        var that = $(this);
        var errors = false;
        var inputs = $(this).parents('tr').find('input');

        $.each(inputs, function (i, input) {

            var element = $(input);

            if (element.attr('name') == 'crop') {
                return true;
            }

            element.one('keyup', function () {
                element.removeClass('wpp-border-error');
            });

            if (!element.val()) {
                errors = true;
                element.addClass('wpp-border-error');
            }

            if (element.attr('name') == 'name') {

                if (/[^a-zA-Z0-9-_]/.test(element.val())) {
                    errors = true;
                    element.addClass('wpp-border-error');
                }

            } else {

                if (isNaN(element.val())) {
                    errors = true;
                    element.addClass('wpp-border-error');
                }

            }


        });

        if (!errors) {

            $.ajax({
                method: 'POST',
                url: ajaxurl,
                dataType: 'json',
                data: {
                    action: 'wpp_images_action',
                    image_action: 'add_size',
                    size: inputs.serialize(),
                    nonce: WPP.nonce

                }
            }).done(function (response) {

                if (response.status) {

                    $.each(inputs, function (i, input) {

                        var element = $(input);

                        if (element.attr('name') == 'crop') {

                            if (element.is(':checked')) {
                                element.replaceWith(WPP.lang.yes);
                            } else {
                                element.replaceWith('');
                            }

                            return true;
                        }

                        if (element.attr('name') != 'name') {
                            element.replaceWith(element.val() + ' px');
                        } else {
                            element.replaceWith(element.val());
                        }

                    });

                    that.remove();

                } else {

                    $('#wpp-image-errors').text(response.error);

                    setTimeout(function () {
                        $('#wpp-image-errors').text('');
                    }, 3000);

                }

            });

        }

    });


    /**
     * Regenerate thumbnails
     */
    $(document).on('click', '#wpp-regenerate-thumbnails', function (e) {

        e.preventDefault();

        var that = $(this);
        var title = that.data( 'title' );
        var removeFlag = true;

        var regenerateThumbnails = function ( dialog ) {
        
            return $.ajax({
                method: 'POST',
                url: ajaxurl,
                dataType: 'json',
                async: false,
                data: {
                    action: 'wpp_images_action',
                    image_action: 'regenerate_thumbnails',
                    remove_flag: removeFlag,
                    nonce: WPP.nonce
                }
            }).done(function (response) {
                
                dialog.setContent('<div><img height="100" width="100" src="' + WPP.path + 'loader.svg" /><div class="wpp-thumb-loader">' + response.percent + '%</div></div><br />' );
                dialog.setContentAppend('<div>' + response.info + '</div>' );

                removeFlag = false;

                if ( response.process ) {
                    regenerateThumbnails( dialog )
                }
                

            }).fail(function(e){

                console.log( e.statusText, e.responseText )

                dialog.setTitle( 'Error' );
                dialog.setContent('<div>' + WPP.lang.something_went_wrong + '</div>' );

            });

        };

        $.confirm({
            title: title,
            content: '<div>' + WPP.lang.regenerate_thumbs_info + '</div>',
            buttons: {
                Confirm: {
                    action: function () {
                        // hm..
                        $('.jconfirm-content-pane').removeClass('no-scroll').removeAttr('style')
                        this.buttons.Confirm.hide();
                        this.buttons.Cancel.hide();
                        this.setTitle( WPP.lang.regenerate_thumbs );
                        this.setContent('<div><img height="100" width="100" src="' + WPP.path + 'loader.svg" /><div class="wpp-thumb-loader">0%</div></div><br />' );
                        return regenerateThumbnails( this );
                    }
                },
                Cancel: function () { }
            }
        });

    });


    /**
     * Perform database actions
     */
    $(document).on('click', '.wpp-db-action', function (e) {

        e.preventDefault();

        var action = $(this).data('action');
        var description = $(this).data('description');
        var count = $(this).data('count');

        $.confirm({
            content: description,
            buttons: {
                Confirm: function () {

                    $('body').append('<div id="wpp_overlay"><img id="wpp_loader" src="' + WPP.path + 'loader.svg" /></div>');

                    $.ajax({
                        method: 'POST',
                        url: ajaxurl,
                        dataType: 'json',
                        data: {
                            action: 'wpp_clean_database',
                            db_action: action,
                            nonce: WPP.nonce
                        }
                    }).done(function () {
                        
                        $('#wpp_overlay').remove();

                        if(action == 'all') {
                            $('.wpp-db-count').text('0');
                        } else {
                            var prev = $('#wpp-all-count').text();
                            $('#wpp-' + action + '-count').text('0');
                            $('#wpp-all-count').text( parseInt( prev ) - parseInt( count ) )
                        }
                        
                    });

                },
                Cancel: function () { }
            }
        });


    });


    /**
     * Load settings
     */
    $(document).on('click', '.wpp-load-settings', function (e) {

        e.preventDefault();

        var href = $(this).attr('href');
        var description = $(this).data('description');

        $.confirm({
            content: description,
            buttons: {
                Confirm: function () {
                    window.location.href = href;
                },
                Cancel: function () {
                }
            }
        });

    });

    /**
     * Show hide add-on settings
     */
    $(document).on('click', '.wpp-addon-settings-toggle', function (e) {

        e.preventDefault();

        var container = $(this).data('addon-settings');

        $('[data-addon-settings-content="' + container + '"]').slideToggle();

    });


    /**
     * Add input element
     */
    $(document).on('click', '[data-add-input]', function(e){

        e.preventDefault();

        var name  = $(this).data('add-input');
        var info = $(this).attr('data-info');
        var placeholder = $(this).attr('data-placeholder') || '';
        var container = $(this).data('container');

        var input  = '<div data-dynamic-container="' + name + '" class="wpp-dynamic-input-container">';
            input += '<input type="text" name="' + name + '" placeholder="' + placeholder + '" class="wpp-dynamic-input" form="wpp-settings" required /> &nbsp; ';
            input += '<a href="#" data-name="' + name + '" class="button wpp-remove-input">' + WPP.lang.remove + '</a>';
            input += '</div>';

        $(container).append(input);

        if ( info && $('[data-info-name="' + name + '"]').length == 0 ) {

            if ( info.indexOf('|') !== -1 ) {

                var notes = info.split('|');
                var output = '';

                for( var i in notes ) {
                    output += '<em><span class="dashicons dashicons-info"></span> ' + notes[i] + '</em>';
                }

                $(container).after('<div data-info-name="' + name + '">' + output + '<br /></div>');

            } else {
                $(container).after('<div data-info-name="' + name + '"><em><span class="dashicons dashicons-info"></span> ' + info + '</em><br /></div>');
            }
                        
        }

    });

    /**
     * Remove input element
     */
    $(document).on('click', '.wpp-remove-input', function(e){

        e.preventDefault();

        $(this).parent().remove();

        if( $('[data-dynamic-container="' + $(this).data('name') + '"]').length == 0 ) {
            $('[data-info-name="' + $(this).data('name') + '"]').remove();
        }

    });


    /**
     * Remove dismissible notice
     */
    var notice = $('.wpp-notice');

    if ( notice.length && notice.hasClass('is-dismissible') ) {

        window.setTimeout(function(){
            notice.fadeOut();
        }, 5000 );

    }

    
    /**
     * Autoload log content
     */
    var log_textarea = $('.wpp-log-textarea');

    if ( log_textarea.length ) {

        var interval = setInterval(function(){
            
            $.ajax({
                method: 'POST',
                url: ajaxurl,
                data: {
                    action: 'wpp_get_log_content',
                    nonce: WPP.nonce
                }
            }).done(function ( data ) {
                log_textarea.val( data )
            }).fail(function(){
                clearInterval( interval );
            })

        }, 10000 );

    }

});