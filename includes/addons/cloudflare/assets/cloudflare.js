jQuery(document).ready(function ($) {

    /**
     * Clear Cloudflare cache
     */
    $(document).on('click', '#wpp-clear-cf-cache, #wp-admin-bar-cloudflare_clear_cache a', function (e) {

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
                            action: 'wpp_clear_cf_cache',
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
