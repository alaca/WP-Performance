jQuery(document).ready(function ($) {

    /**
     * Clear Cloudflare cache
     */
    $(document).on('click', '#wpp-clear-cf-cache, #wpp-clear-cf-custom, #wp-admin-bar-cloudflare_clear_cache a, #wp-admin-bar-cloudflare_clear_custom a', function (e) {

        e.preventDefault();

        var action = $(this).attr('href').replace( '#', '');

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
                            action: action,
                            nonce: WPP.nonce
                        }
                    }).done(function ( response ) {

                        if ( ! response.status ) {
                            console.log( response.message );
                        }

                        $('#wpp_overlay').remove();

                    });

                },
                Cancel: function () { }
            }
        });

    });

});
