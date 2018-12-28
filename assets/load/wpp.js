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

    var container = [];

    function showImages() {

        for (var i = 0; i < container.length; i++) {

            if (inView(container[i])) {

                if (container[i].getAttribute('data-src')) {
                    // swap src
                    container[i].src = container[i].getAttribute('data-src');
                    container[i].removeAttribute('data-src');

                    // add scrcset
                    if (container[i].getAttribute('data-srcset')) {
                        container[i].srcset = container[i].getAttribute('data-srcset');
                        container[i].removeAttribute('data-srcset');
                    }

                    // remove image from container
                    if (container.indexOf(container[i]) !== -1) {
                        container.splice(i, 1);
                    }

                }

            }
        }

    }


    var inView = function (image) {

        var rect = image.getBoundingClientRect();

        return ((rect.top >= 0 && rect.left >= 0 && rect.top) <= (window.innerHeight || document.documentElement.clientHeight));

    };


    function getImages() {

        var images = document.querySelectorAll('img[data-src]');

        for (var i = 0; i < images.length; i++) {
            container.push(images[i]);
        }

        showImages();

    };

    getImages()

    document.addEventListener('scroll', showImages, false);


    // Load CSS
    var loadCSS = function(href, media){

        var link = document.createElement('link');
                                       
        link.rel = 'stylesheet';
        link.href = href;
        link.media = media || 'all';
        document.head.appendChild(link);
    }

    function supportsPreload(){
        var list = document.createElement('link').relList;
        if (!list || !list.supports) {
            return false;
        }
        return list.supports('preload');
    };  

    var load = function(){   
        // get all link elements and load them with loadCSS 
        var links = document.getElementsByTagName('link');

        for (var i in links) {
            if (links[i].rel === 'preload' && links[i].getAttribute('as') === 'style') { 
                loadCSS(links[i].href, links[i].getAttribute('media') || 'all');
            }
        }

    };

    // check if browser doesn't supports preload
    if (!supportsPreload()) {

        load()

    }

    window.loadCSS = loadCSS;


    // Load Script

    var get = function (scripts) {

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

        // run
        get(data).then(function (codes) {
            for (var i in codes) {
                try {
                    (0, eval)(codes[i]);
                } catch (e) {
                    console.log(e.name, e.message);
                }
            }
        });

    }

    loadJS()

})(window, document);
