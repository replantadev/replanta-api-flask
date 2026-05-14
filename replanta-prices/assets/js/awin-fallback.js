/**
 * Awin AWC Fallback Script
 *
 * Modifies links to target domain to include AWC parameter.
 * Used as fallback for links not generated server-side.
 *
 * @package Replanta_Prices
 */
(function() {
  'use strict';

  // Config passed from PHP
  var config = window.replantaAwin || {};
  
  if (!config.enabled || !config.awc || !config.targetDomain) {
    return;
  }

  var awcParam = config.awcParam || 'awc';
  var targetDomain = config.targetDomain;
  var awcValue = config.awc;

  /**
   * Check if URL is a target URL.
   * @param {string} href
   * @return {boolean}
   */
  function isTargetUrl(href) {
    if (!href) return false;
    try {
      var url = new URL(href, window.location.origin);
      return url.hostname === targetDomain || 
             url.hostname.endsWith('.' + targetDomain);
    } catch (e) {
      return false;
    }
  }

  /**
   * Append AWC to URL if not present.
   * @param {string} href
   * @return {string}
   */
  function appendAwc(href) {
    try {
      var url = new URL(href, window.location.origin);
      if (!url.searchParams.has(awcParam)) {
        url.searchParams.set(awcParam, awcValue);
      }
      return url.toString();
    } catch (e) {
      // Fallback: simple append
      var sep = href.indexOf('?') !== -1 ? '&' : '?';
      return href + sep + awcParam + '=' + encodeURIComponent(awcValue);
    }
  }

  /**
   * Process all target links on the page.
   */
  function processLinks() {
    var links = document.querySelectorAll('a[href*="' + targetDomain + '"]');
    
    links.forEach(function(link) {
      var href = link.getAttribute('href');
      if (isTargetUrl(href)) {
        var newHref = appendAwc(href);
        if (newHref !== href) {
          link.setAttribute('href', newHref);
          link.setAttribute('data-awc-modified', '1');
        }
      }
    });
  }

  // Run on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', processLinks);
  } else {
    processLinks();
  }

  // Observe DOM for dynamically added links
  if (typeof MutationObserver !== 'undefined') {
    var observer = new MutationObserver(function(mutations) {
      var needsProcess = false;
      
      mutations.forEach(function(mutation) {
        mutation.addedNodes.forEach(function(node) {
          if (node.nodeType === 1) { // Element
            if (node.tagName === 'A' || node.querySelector && node.querySelector('a')) {
              needsProcess = true;
            }
          }
        });
      });
      
      if (needsProcess) {
        processLinks();
      }
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  }

  // Legacy: Intercept click events as final fallback
  document.addEventListener('click', function(e) {
    var link = e.target.closest ? e.target.closest('a') : null;
    
    if (!link) return;
    
    var href = link.getAttribute('href');
    if (isTargetUrl(href) && !link.hasAttribute('data-awc-modified')) {
      var newHref = appendAwc(href);
      if (newHref !== href) {
        link.setAttribute('href', newHref);
      }
    }
  }, true);

})();
