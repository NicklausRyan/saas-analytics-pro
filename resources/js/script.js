! function(w) {
    'use strict';

    /**
     * Send the request
     * @param event
     * @param referrer Needed for SPAs dynamic history push
     * @param options Additional options for the request
     */
    function sendRequest(event, referrer, options) {
        // Tracking code element
        var trackingCode = document.getElementById('ZwSg9rf6GA');

        if (trackingCode.getAttribute('data-dnt') === 'true') {
            // If the user's has DNT enabled
            if (navigator.doNotTrack) {
                // Cancel the request
                return false;
            }
        }

        // Request parameters
        var params = {};

        // If a referrer is set
        if (referrer) {
            params.referrer = referrer;
        } else {
            // Get the referrer
            params.referrer = w.document.referrer;
        }

        // Get the current page
        params.page = w.location.href.replace(/#.+$/,'');

        // Get the screen resolution
        params.screen_resolution = screen.width + 'x' + screen.height;

        if (event) {
            params.event = event;
        }

        // Merge any additional options
        if (options && typeof options === 'object') {
            for (var key in options) {
                if (options.hasOwnProperty(key)) {
                    params[key] = options[key];
                }
            }
        }

        // Default to client-side event tracking
        var endpoint = "/api/event";

        // Use webhook endpoint if specified
        if (options && options.useWebhook === true) {
            endpoint = "/api/webhook";
        }

        // Send the request
        var request = new XMLHttpRequest();
        request.open("POST", trackingCode.getAttribute('data-host') + endpoint, true);
        request.setRequestHeader("Content-Type", "application/json; charset=utf-8");
        
        // Add domain key if available
        var domainKey = trackingCode.getAttribute('data-key');
        if (domainKey) {
            request.setRequestHeader("X-Domain-Key", domainKey);
        }
        
        request.send(JSON.stringify(params));

        return {
            then: function(callback) {
                request.onreadystatechange = function() {
                    if (request.readyState === 4) {
                        callback({
                            status: request.status,
                            response: request.responseText ? JSON.parse(request.responseText) : null
                        });
                    }
                };
                return this;
            },
            catch: function(callback) {
                request.onerror = function() {
                    callback(new Error('Network error occurred'));
                };
                return this;
            }
        };
    }

    try {
        // Rewrite the push state function to detect path changes in SPAs
        var pushState = history.pushState;
        history.pushState = function () {
            var referrer = w.location.href.replace(/#.+$/,'');
            pushState.apply(history, arguments);
            sendRequest(null, referrer);
        };

        // Listen to the browser's back & forward buttons
        w.onpopstate = function(event) {
            sendRequest(null);
        };

        // Define the event method
        w.pa = {}; 
        w.pa.track = sendRequest;

        // Add webhook tracking method
        w.pa.webhook = function(event, options) {
            options = options || {};
            options.useWebhook = true;
            options.domain = w.location.hostname.replace('www.', '');
            return sendRequest(event, null, options);
        };

        // Send the initial request
        sendRequest(null);
    } catch (e) {
        console.log(e.message);
    }
}(window);