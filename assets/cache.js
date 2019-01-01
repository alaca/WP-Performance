window.addEventListener('DOMContentLoaded', function() {

    var clear_cache = document.querySelector('li.wpp_clear_cache a');

    if ( null !== clear_cache ) {

        clear_cache.addEventListener('click', function(e) {

            e.preventDefault();
    
            document.body.innerHTML += '<div id="wpp_overlay"><img id="wpp_loader" src="' + WPP.path + 'loader.svg" /></div>';
    
            var xhr = new XMLHttpRequest();
            xhr.open('POST', WPP.ajax_url, true);
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhr.send('action=wpp_clear_cache');
            xhr.onload = function(){
                var overlay = document.getElementById('wpp_overlay');
                overlay.parentNode.removeChild(overlay);
            };
    
        }, true );

    }

});