<?php
/*
 * CSRF client for admin and employee layouts (include inside <head> after
 * BASE_URL / API_BASE are defined). Exposes CSRF_TOKEN and attaches it as
 * X-CSRF-Token to every same-origin request made through XMLHttpRequest
 * (jQuery $.ajax, FormData uploads) or fetch(), so existing page scripts
 * need no change. api-gateway.php (APIs) and routes.php (pages posting to
 * themselves) reject non-GET requests without a valid token.
 */
require_once __DIR__ . '/Csrf.php';
?>
<script>
    const CSRF_TOKEN = "<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>";

    (function () {
        function isSameOriginUrl(url) {
            try {
                var target = new URL(String(url), window.location.href);
                return target.origin === window.location.origin;
            } catch (e) {
                return false;
            }
        }

        var nativeOpen = XMLHttpRequest.prototype.open;
        var nativeSetHeader = XMLHttpRequest.prototype.setRequestHeader;
        var nativeSend = XMLHttpRequest.prototype.send;

        XMLHttpRequest.prototype.open = function (method, url) {
            this._crmCsrfSameOrigin = isSameOriginUrl(url);
            this._crmCsrfSet = false;
            return nativeOpen.apply(this, arguments);
        };

        XMLHttpRequest.prototype.setRequestHeader = function (name) {
            if (String(name).toLowerCase() === 'x-csrf-token') {
                this._crmCsrfSet = true;
            }
            return nativeSetHeader.apply(this, arguments);
        };

        XMLHttpRequest.prototype.send = function () {
            if (this._crmCsrfSameOrigin && !this._crmCsrfSet) {
                nativeSetHeader.call(this, 'X-CSRF-Token', CSRF_TOKEN);
            }
            return nativeSend.apply(this, arguments);
        };

        if (window.fetch) {
            var nativeFetch = window.fetch;
            window.fetch = function (input, init) {
                var url = typeof input === 'string' ? input : (input && input.url);
                if (isSameOriginUrl(url)) {
                    init = init || {};
                    var headers = new Headers(init.headers || (typeof input !== 'string' && input.headers) || {});
                    if (!headers.has('X-CSRF-Token')) {
                        headers.set('X-CSRF-Token', CSRF_TOKEN);
                    }
                    init.headers = headers;
                }
                return nativeFetch.call(this, input, init);
            };
        }
    })();
</script>
