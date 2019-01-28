/**
 * WPP Scripts
 * [c]2018 Ante Laca <ante.laca@gmail.com>
 */
(function(window, document) {

    'use strict';

    if ( typeof WPP.expire != 'undefined' ) {
        if ( Math.floor(new Date().getTime() / 1000) > WPP.expire) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', WPP.ajax_url, true);
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhr.send('action=wpp_clear_cache');
        }    
    }

    /**
     * All images
     */
    var images_container = [];

    /**
     * Show images in viewport
     */
    function showImages() {

        for (var i = 0; i < images_container.length; i++) {

            if (inViewport(images_container[i])) {

                if (images_container[i].getAttribute('data-src')) {
                    // swap src
                    images_container[i].src = images_container[i].getAttribute('data-src');
                    images_container[i].removeAttribute('data-src');

                    // add scrcset
                    if (images_container[i].getAttribute('data-srcset')) {
                        images_container[i].srcset = images_container[i].getAttribute('data-srcset');
                        images_container[i].removeAttribute('data-srcset');
                    }

                    // remove image from images_container
                    if (images_container.indexOf(images_container[i]) !== -1) {
                        images_container.splice(i, 1);
                    }

                }

            }
        }

    }


    /**
     * Determine if image is in view
     * @param {string} image 
     */
    function inViewport(image) {

        var rect = image.getBoundingClientRect();

        return ((rect.top >= 0 && rect.left >= 0 && rect.top) <= (window.innerHeight || document.documentElement.clientHeight));

    };


    /**
     * Initialize LazyLoad
     */
    function initLazyLoad() {

        document.addEventListener('scroll', showImages, false);

        var images = document.querySelectorAll('img[data-src]');

        for (var i = 0; i < images.length; i++) {
            images_container.push(images[i]);
        }

        // Triger scroll to show images in viewport
        document.dispatchEvent(new Event('scroll'));

    };

    /**
     * Load CSS file
     * 
     * @param {string} href 
     * @param {string} media 
     */
    function loadCSS(href, media){

        var link = document.createElement('link');
                                       
        link.rel = 'stylesheet';
        link.href = href;
        link.media = media || 'all';
        document.head.appendChild(link);
    }

    /**
     * Check if browser supports preload
     */
    function supportsPreload(){
        var list = document.createElement('link').relList;
        if (!list || !list.supports) {
            return false;
        }
        return list.supports('preload');
    };  

    /**
     * Load styles with rel=preload attribute
     */
    function preloadStyles(){   
        // get all link elements and load them with loadCSS 
        var links = document.getElementsByTagName('link');

        for (var i in links) {
            if (links[i].rel === 'preload' && links[i].getAttribute('as') === 'style') { 
                loadCSS(links[i].href, links[i].getAttribute('media') || 'all');
            }
        }

    };


    /**
     *  Load scripts asynchronously  
     * 
     * @param {array} scripts 
     */
    function getScripts(scripts) {

        var promises = [];

        scripts.forEach(function (script) {

            var promise = new Promise(function (resolve, reject) {

                if (script.url) {

                    var xhr = new XMLHttpRequest();

                    xhr.open('get', script.url);
                    xhr.onload = function () {
                        // check status
                        if (xhr.status == 200) {
                            resolve(xhr.response);

                        } else {
                            reject(Error(xhr.statusText));
                        }

                    };

                    // xhr never timeout, so we do it ourselves
                    setTimeout( function() {
                        if (xhr.readyState < 4) {
                            xhr.abort();
                            return reject(new Error(script.url + ' timeout'));
                        }
                    }, 10000);

                    xhr.send();

                } else {
                    resolve(script.code);
                }

            });

            // add promise
            promises.push(promise);

        });

        // return promises
        return Promise.all(promises);

    }


    /**
     * Load JavaScript with type="text/localscript" attribute
     */
    function loadJS() {
        
        var data = [],
            scripts = document.getElementsByTagName('script');

        // process all script tags
        for (var i in scripts) {
            if (scripts[i].type == 'text/localscript') {
                data.push({
                    url: scripts[i].getAttribute('data-src') || null,
                    code: scripts[i].innerHTML || null
                });
            }
        }

        /**
         * Run scripts
         */
        getScripts(data).then(function (codes) {
            for (var i in codes) {
                try {
                    (0, eval)(codes[i]);
                } catch (e) {
                    console.log(e.name, e.message);
                }
            }
        });

    }

    /**
     * Initialize all WPP scripts
     */
    function WPPinit() {
        initLazyLoad();
        showImages();
        loadJS();
    }

    /**
     * check if browser supports preload
     * we don't need to wait DOMContentLoaded as this is defered script and the DOM is almost loaded
     */
    if ( ! supportsPreload() ) {
        preloadStyles();
    }

    /**
     * Load everything on DOMContentLoaded
     */
    if (document.readyState === 'loading') { 
        document.addEventListener('DOMContentLoaded', WPPinit );
    } else {
        WPPinit();
    }

})(window, document);
