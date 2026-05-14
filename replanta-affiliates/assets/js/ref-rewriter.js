/**
 * Replanta Affiliates — Ref Rewriter.
 *
 * When a visitor arrives via ?ref=CODE, this script:
 * 1. Stores the ref code in a first-party cookie (90 days)
 * 2. Rewrites all checkout links on the page to include &coupons=CODE
 *
 * On subsequent visits (no ?ref= in URL), the cookie is read and links
 * are still rewritten — giving affiliates a 90-day attribution window.
 *
 * Works even behind full-page caches (CDN/LiteSpeed) because it reads
 * the cookie client-side as a fallback.
 */
(function () {
    'use strict';

    var COOKIE_NAME = 'replanta_aff_ref';
    var COOKIE_DAYS = 90;

    /* ── Cookie helpers ─────────────────────────────────── */
    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? decodeURIComponent(match[2]) : '';
    }

    function setCookie(name, value, days) {
        var d = new Date();
        d.setTime(d.getTime() + days * 86400000);
        document.cookie = name + '=' + encodeURIComponent(value) +
            ';expires=' + d.toUTCString() +
            ';path=/;SameSite=Lax;Secure';
    }

    /* ── Determine ref code ─────────────────────────────── */
    // Priority: URL param > server-localized > cookie
    var urlRef = '';
    try {
        var params = new URLSearchParams(window.location.search);
        urlRef = (params.get('ref') || '').trim().toUpperCase();
    } catch (e) { /* IE fallback not needed — modern browsers only */ }

    var serverRef = (window.raffRef && raffRef.code) ? raffRef.code : '';
    var cookieRef = getCookie(COOKIE_NAME);

    // Determine the active ref code with first-click priority
    var code = '';
    if (urlRef) {
        // Fresh click — set/refresh cookie
        code = urlRef;
        setCookie(COOKIE_NAME, code, COOKIE_DAYS);
    } else if (serverRef) {
        // Server already validated from its own cookie read
        code = serverRef;
    } else if (cookieRef) {
        // Fallback: page was served from cache without raffRef
        code = cookieRef;
    }

    if (!code) {
        return;
    }

    /* ── GTM / dataLayer integration ────────────────────── */
    window.dataLayer = window.dataLayer || [];
    var dlPayload = {
        event: 'raff_visit',
        raff_code: code,
        raff_source: urlRef ? 'url' : (serverRef ? 'server' : 'cookie')
    };
    if (urlRef) {
        dlPayload.raff_landing = window.location.pathname;
    }
    window.dataLayer.push(dlPayload);

    function normalizeHost(raw) {
        raw = (raw || '').toString().trim().toLowerCase();
        if (!raw) return 'clientes.replanta.net';

        try {
            if (raw.indexOf('://') !== -1) {
                return new URL(raw).hostname.toLowerCase();
            }
        } catch (e) { }

        raw = raw.replace(/^\/+/, '');
        raw = raw.split('/')[0] || raw;
        raw = raw.replace(/:\d+$/, '');
        return raw || 'clientes.replanta.net';
    }

    /* ── Determine checkout host ────────────────────────── */
    var host = normalizeHost((window.raffRef && raffRef.checkout_host) ? raffRef.checkout_host : 'clientes.replanta.net');

    /* ── Rewrite checkout links ─────────────────────────── */
    function rewriteLinks() {
        var links = document.querySelectorAll('a[href]');
        links.forEach(function (a) {
            var url;
            try {
                url = new URL(a.href);
            } catch (e) {
                return;
            }

            if ((url.hostname || '').toLowerCase() !== host) return;

            // Don't overwrite existing coupon
            if (url.searchParams.get('coupons')) return;

            url.searchParams.set('coupons', code);
            a.href = url.toString();
        });
    }

    /* ── Track affiliate link clicks ──────────────────────── */
    function trackClicks() {
        var links = document.querySelectorAll('a[href]');
        links.forEach(function (a) {
            var url;
            try {
                url = new URL(a.href);
            } catch (e) {
                return;
            }

            if ((url.hostname || '').toLowerCase() !== host) return;

            if (a.dataset.raffTracked) return;
            a.dataset.raffTracked = '1';
            a.addEventListener('click', function () {
                window.dataLayer.push({
                    event: 'raff_click',
                    raff_code: code,
                    raff_destination: a.href
                });
            });
        });
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            rewriteLinks();
            trackClicks();
        });
    } else {
        rewriteLinks();
        trackClicks();
    }

    // Observe for dynamically added links (pricing tabs, AJAX content)
    var observer = new MutationObserver(function (mutations) {
        var hasNewLinks = mutations.some(function (m) {
            return m.addedNodes.length > 0;
        });
        if (hasNewLinks) {
            rewriteLinks();
            trackClicks();
        }
    });

    observer.observe(document.body || document.documentElement, {
        childList: true,
        subtree: true,
    });
})();
