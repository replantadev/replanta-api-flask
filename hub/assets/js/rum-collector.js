// Real User Monitoring JavaScript Collector for Replanta Hub Professional
(function() {
    'use strict';
    
    // Check if web-vitals library is available
    if (typeof webVitals === 'undefined') {
        console.debug('Web Vitals library not loaded');
        return;
    }
    
    // Check if RUM configuration is available
    if (typeof rphubRUM === 'undefined') {
        console.debug('RUM configuration not available');
        return;
    }
    
    // Configuration
    const config = {
        batchSize: 10,
        batchTimeout: 5000,
        maxRetries: 3,
        sampleRate: 1.0, // Collect 100% of sessions
        enableDebug: false
    };
    
    // Data collection queue
    let dataQueue = [];
    let batchTimer = null;
    let sessionId = generateSessionId();
    
    // Initialize Web Vitals collection
    function initWebVitals() {
        // Collect Core Web Vitals
        webVitals.getCLS(sendWebVital);
        webVitals.getFID(sendWebVital);
        webVitals.getFCP(sendWebVital);
        webVitals.getLCP(sendWebVital);
        webVitals.getTTFB(sendWebVital);
        
        // Collect experimental metrics if available
        if (webVitals.getINP) {
            webVitals.getINP(sendWebVital);
        }
    }
    
    // Send Web Vital metric
    function sendWebVital(metric) {
        const vitalData = {
            name: metric.name,
            value: metric.value,
            id: metric.id,
            delta: metric.delta,
            rating: getVitalRating(metric.name, metric.value),
            entries: metric.entries.map(entry => ({
                name: entry.name,
                startTime: entry.startTime,
                duration: entry.duration
            }))
        };
        
        queueData('web_vital', vitalData);
        
        if (config.enableDebug) {
            console.debug('Web Vital collected:', metric.name, metric.value);
        }
    }
    
    // Get Web Vital rating based on thresholds
    function getVitalRating(name, value) {
        const thresholds = {
            'CLS': { good: 0.1, poor: 0.25 },
            'FID': { good: 100, poor: 300 },
            'FCP': { good: 1800, poor: 3000 },
            'LCP': { good: 2500, poor: 4000 },
            'TTFB': { good: 800, poor: 1800 },
            'INP': { good: 200, poor: 500 }
        };
        
        const threshold = thresholds[name];
        if (!threshold) return 'unknown';
        
        if (value <= threshold.good) return 'good';
        if (value <= threshold.poor) return 'needs-improvement';
        return 'poor';
    }
    
    // Collect additional performance metrics
    function collectAdditionalMetrics() {
        // Memory usage (if available)
        if (performance.memory) {
            queueData('memory', {
                usedJSHeapSize: performance.memory.usedJSHeapSize,
                totalJSHeapSize: performance.memory.totalJSHeapSize,
                jsHeapSizeLimit: performance.memory.jsHeapSizeLimit
            });
        }
        
        // Network information (if available)
        if (navigator.connection || navigator.mozConnection || navigator.webkitConnection) {
            const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
            queueData('network', {
                effectiveType: connection.effectiveType,
                downlink: connection.downlink,
                rtt: connection.rtt,
                saveData: connection.saveData
            });
        }
        
        // Screen information
        queueData('screen', {
            width: screen.width,
            height: screen.height,
            pixelRatio: window.devicePixelRatio || 1,
            orientation: screen.orientation ? screen.orientation.type : 'unknown'
        });
        
        // Viewport information
        queueData('viewport', {
            width: window.innerWidth,
            height: window.innerHeight,
            scrollX: window.scrollX,
            scrollY: window.scrollY
        });
    }
    
    // Collect page visibility metrics
    function initPageVisibility() {
        let visibilityStart = Date.now();
        let totalVisibleTime = 0;
        let isVisible = !document.hidden;
        
        function handleVisibilityChange() {
            const now = Date.now();
            
            if (document.hidden) {
                if (isVisible) {
                    totalVisibleTime += now - visibilityStart;
                    isVisible = false;
                }
            } else {
                if (!isVisible) {
                    visibilityStart = now;
                    isVisible = true;
                }
            }
            
            queueData('visibility', {
                hidden: document.hidden,
                totalVisibleTime: totalVisibleTime,
                timestamp: now
            });
        }
        
        document.addEventListener('visibilitychange', handleVisibilityChange);
        
        // Track when user leaves the page
        window.addEventListener('beforeunload', function() {
            const now = Date.now();
            if (isVisible) {
                totalVisibleTime += now - visibilityStart;
            }
            
            queueData('page_exit', {
                totalVisibleTime: totalVisibleTime,
                timestamp: now
            });
            
            // Send any remaining data
            flushQueue(true);
        });
    }
    
    // Collect user interaction metrics
    function initUserInteractions() {
        let clickCount = 0;
        let scrollDepth = 0;
        let maxScrollDepth = 0;
        
        // Track clicks
        document.addEventListener('click', function(event) {
            clickCount++;
            queueData('interaction', {
                type: 'click',
                target: event.target.tagName,
                x: event.clientX,
                y: event.clientY,
                count: clickCount,
                timestamp: Date.now()
            });
        });
        
        // Track scroll depth
        function updateScrollDepth() {
            const windowHeight = window.innerHeight;
            const documentHeight = document.documentElement.scrollHeight;
            const scrollTop = window.scrollY;
            
            scrollDepth = Math.round((scrollTop + windowHeight) / documentHeight * 100);
            maxScrollDepth = Math.max(maxScrollDepth, scrollDepth);
        }
        
        let scrollTimer;
        window.addEventListener('scroll', function() {
            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(function() {
                updateScrollDepth();
                queueData('scroll', {
                    depth: scrollDepth,
                    maxDepth: maxScrollDepth,
                    timestamp: Date.now()
                });
            }, 100);
        });
    }
    
    // Queue data for batch sending
    function queueData(type, data) {
        // Sample rate check
        if (Math.random() > config.sampleRate) {
            return;
        }
        
        const dataPoint = {
            type: type,
            data: data,
            sessionId: sessionId,
            url: window.location.href,
            timestamp: Date.now(),
            userAgent: navigator.userAgent
        };
        
        dataQueue.push(dataPoint);
        
        // Check if we should send the batch
        if (dataQueue.length >= config.batchSize) {
            flushQueue();
        } else if (!batchTimer) {
            batchTimer = setTimeout(flushQueue, config.batchTimeout);
        }
    }
    
    // Send batched data to server
    function flushQueue(isBeforeUnload = false) {
        if (dataQueue.length === 0) {
            return;
        }
        
        const batchData = [...dataQueue];
        dataQueue = [];
        
        if (batchTimer) {
            clearTimeout(batchTimer);
            batchTimer = null;
        }
        
        const payload = {
            action: 'rphub_collect_rum',
            nonce: rphubRUM.nonce,
            type: 'batch',
            data: JSON.stringify(batchData),
            site_id: rphubRUM.siteId,
            session_id: sessionId,
            page_type: rphubRUM.pageType,
            device_type: rphubRUM.deviceType,
            connection_type: rphubRUM.connectionType,
            batch_size: batchData.length,
            timestamp: Date.now()
        };
        
        // Use sendBeacon for unload events, otherwise use fetch
        if (isBeforeUnload && navigator.sendBeacon) {
            const formData = new FormData();
            Object.keys(payload).forEach(key => {
                formData.append(key, payload[key]);
            });
            
            navigator.sendBeacon(rphubRUM.ajaxUrl, formData);
        } else {
            sendDataWithRetry(payload);
        }
        
        if (config.enableDebug) {
            console.debug('RUM batch sent:', batchData.length, 'items');
        }
    }
    
    // Send data with retry logic
    function sendDataWithRetry(payload, retryCount = 0) {
        fetch(rphubRUM.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(payload)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (config.enableDebug) {
                console.debug('RUM data sent successfully:', data);
            }
        })
        .catch(error => {
            if (retryCount < config.maxRetries) {
                setTimeout(() => {
                    sendDataWithRetry(payload, retryCount + 1);
                }, Math.pow(2, retryCount) * 1000); // Exponential backoff
            } else {
                console.debug('Failed to send RUM data after', config.maxRetries, 'retries:', error);
            }
        });
    }
    
    // Generate unique session ID
    function generateSessionId() {
        return 'rum_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    // Collect long tasks (if supported)
    function initLongTaskObserver() {
        if (typeof PerformanceObserver !== 'undefined') {
            try {
                const longTaskObserver = new PerformanceObserver(function(list) {
                    const longTasks = list.getEntries().map(entry => ({
                        name: entry.name,
                        startTime: entry.startTime,
                        duration: entry.duration,
                        attribution: entry.attribution ? entry.attribution.map(attr => ({
                            name: attr.name,
                            containerType: attr.containerType,
                            containerSrc: attr.containerSrc,
                            containerId: attr.containerId,
                            containerName: attr.containerName
                        })) : []
                    }));
                    
                    if (longTasks.length > 0) {
                        queueData('long_tasks', longTasks);
                    }
                });
                
                longTaskObserver.observe({entryTypes: ['longtask']});
            } catch (e) {
                console.debug('Long task observer not supported');
            }
        }
    }
    
    // Collect layout shift information
    function initLayoutShiftObserver() {
        if (typeof PerformanceObserver !== 'undefined') {
            try {
                const layoutShiftObserver = new PerformanceObserver(function(list) {
                    const shifts = list.getEntries().map(entry => ({
                        value: entry.value,
                        startTime: entry.startTime,
                        hadRecentInput: entry.hadRecentInput,
                        sources: entry.sources ? entry.sources.map(source => ({
                            node: source.node ? source.node.tagName : 'unknown',
                            currentRect: source.currentRect,
                            previousRect: source.previousRect
                        })) : []
                    }));
                    
                    if (shifts.length > 0) {
                        queueData('layout_shifts', shifts);
                    }
                });
                
                layoutShiftObserver.observe({entryTypes: ['layout-shift']});
            } catch (e) {
                console.debug('Layout shift observer not supported');
            }
        }
    }
    
    // Initialize all collectors
    function init() {
        // Wait for page to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
            return;
        }
        
        // Initialize all monitoring
        initWebVitals();
        collectAdditionalMetrics();
        initPageVisibility();
        initUserInteractions();
        initLongTaskObserver();
        initLayoutShiftObserver();
        
        // Send initial page load data
        queueData('page_load', {
            url: window.location.href,
            referrer: document.referrer,
            timestamp: Date.now(),
            performance: performance.timing ? {
                navigationStart: performance.timing.navigationStart,
                loadEventEnd: performance.timing.loadEventEnd,
                domContentLoadedEventEnd: performance.timing.domContentLoadedEventEnd
            } : null
        });
        
        if (config.enableDebug) {
            console.debug('RUM collector initialized for site:', rphubRUM.siteId);
        }
    }
    
    // Start initialization
    init();
    
    // Expose some methods for debugging
    if (config.enableDebug) {
        window.rphubRUMDebug = {
            flushQueue: flushQueue,
            getQueueSize: () => dataQueue.length,
            getSessionId: () => sessionId,
            setDebug: (enabled) => { config.enableDebug = enabled; }
        };
    }
    
})();
