/**
 * Finix Console Logger
 *
 * Captures JavaScript console messages and sends them to the server
 * for unified logging with PHP errors.
 *
 * @package Finix_WC_Subs
 * @since 1.8.1
 */

(function() {
    'use strict';

    // Only run if finixConsoleLogger settings are available
    if (typeof finixConsoleLogger === 'undefined') {
        return;
    }

    var enabled = finixConsoleLogger.enabled === '1';

    if (!enabled) {
        return;
    }

    // Store original console methods
    var originalConsole = {
        log: console.log,
        warn: console.warn,
        error: console.error,
        info: console.info
    };

    /**
     * Send log to server
     */
    function sendToServer(level, args) {
        // Convert arguments to string
        var message = Array.prototype.slice.call(args).map(function(arg) {
            if (typeof arg === 'object') {
                try {
                    return JSON.stringify(arg);
                } catch (e) {
                    return String(arg);
                }
            }
            return String(arg);
        }).join(' ');

        // Prepare context
        var context = {
            userAgent: navigator.userAgent,
            timestamp: new Date().toISOString()
        };

        // Add stack trace for errors
        if (level === 'error') {
            try {
                throw new Error();
            } catch (e) {
                if (e.stack) {
                    context.stack = e.stack;
                }
            }
        }

        // Send to server (async, non-blocking)
        if (typeof jQuery !== 'undefined') {
            jQuery.ajax({
                url: finixConsoleLogger.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'finix_log_console',
                    nonce: finixConsoleLogger.nonce,
                    level: level,
                    message: message,
                    context: JSON.stringify(context),
                    url: window.location.href
                },
                async: true // Don't block the browser
            });
        }
    }

    /**
     * Intercept console.log
     */
    console.log = function() {
        originalConsole.log.apply(console, arguments);

        // Only log messages that mention "finix" or "Finix"
        var message = Array.prototype.slice.call(arguments).join(' ');
        if (message.toLowerCase().indexOf('finix') !== -1) {
            sendToServer('log', arguments);
        }
    };

    /**
     * Intercept console.warn
     */
    console.warn = function() {
        originalConsole.warn.apply(console, arguments);

        var message = Array.prototype.slice.call(arguments).join(' ');
        if (message.toLowerCase().indexOf('finix') !== -1) {
            sendToServer('warn', arguments);
        }
    };

    /**
     * Intercept console.error
     */
    console.error = function() {
        originalConsole.error.apply(console, arguments);

        var message = Array.prototype.slice.call(arguments).join(' ');
        if (message.toLowerCase().indexOf('finix') !== -1) {
            sendToServer('error', arguments);
        }
    };

    /**
     * Intercept console.info
     */
    console.info = function() {
        originalConsole.info.apply(console, arguments);

        var message = Array.prototype.slice.call(arguments).join(' ');
        if (message.toLowerCase().indexOf('finix') !== -1) {
            sendToServer('log', arguments);
        }
    };

    /**
     * Catch unhandled errors
     */
    window.addEventListener('error', function(event) {
        // Only log if related to Finix
        if (event.message && event.message.toLowerCase().indexOf('finix') !== -1) {
            var errorInfo = [
                'Unhandled Error:',
                event.message,
                'at ' + event.filename + ':' + event.lineno + ':' + event.colno
            ];

            sendToServer('error', errorInfo);
        }
    });

    /**
     * Catch unhandled promise rejections
     */
    window.addEventListener('unhandledrejection', function(event) {
        var reason = event.reason;
        var message = reason ? (reason.message || String(reason)) : 'Unknown';

        // Only log if related to Finix
        if (message.toLowerCase().indexOf('finix') !== -1) {
            sendToServer('error', ['Unhandled Promise Rejection:', message]);
        }
    });

    // Log that console logger is active
    originalConsole.log('[Finix] Console logger active - Finix-related messages will be logged to server');

})();
