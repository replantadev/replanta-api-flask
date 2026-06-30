<?php
/**
 * PageSpeed API Key Tester
 * Script para diagnosticar problemas con la API de PageSpeed Insights
 */

// Load WordPress
require_once('../../../wp-config.php');
require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied. Admin privileges required.');
}

// Get the API key from WordPress options
$api_key = get_option('rphub_pagespeed_api_key', '');

echo "<h2>🔍 PageSpeed API Key Diagnostics</h2>\n";
echo "<pre>\n";

// Check 1: API Key exists
echo "1. Checking API Key Configuration...\n";
if (empty($api_key)) {
    echo "❌ ERROR: No API key found in database\n";
    echo "   Path: WordPress Options -> rphub_pagespeed_api_key\n\n";
} else {
    echo "✅ API Key found in database\n";
    echo "   Length: " . strlen($api_key) . " characters\n";
    echo "   First 10 chars: " . substr($api_key, 0, 10) . "...\n";
    echo "   Last 10 chars: ..." . substr($api_key, -10) . "\n\n";
}

// Check 2: Test API key format
echo "2. Validating API Key Format...\n";
if (strlen($api_key) < 30) {
    echo "⚠️  WARNING: API key seems too short (expected ~39 characters)\n";
} elseif (strlen($api_key) > 50) {
    echo "⚠️  WARNING: API key seems too long (expected ~39 characters)\n";
} else {
    echo "✅ API key length looks correct\n";
}

// Check if it contains only valid characters
if (preg_match('/^[A-Za-z0-9_-]+$/', $api_key)) {
    echo "✅ API key contains valid characters\n\n";
} else {
    echo "⚠️  WARNING: API key contains unusual characters\n\n";
}

// Check 3: Test a simple API call
echo "3. Testing API Call...\n";
if (!empty($api_key)) {
    $test_url = home_url(); // Use the current site
    $api_url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
    
    $params = [
        'url' => $test_url,
        'key' => $api_key,
        'strategy' => 'mobile',
        'locale' => 'es'
    ];
    
    // Add categories properly (this was the bug!)
    $category_params = '&category=performance';
    
    $full_url = $api_url . '?' . http_build_query($params) . $category_params;
    
    echo "Testing URL: $test_url\n";
    echo "API Endpoint: $api_url\n";
    echo "Full Request URL: " . substr($full_url, 0, 100) . "...\n\n";
    
    $response = wp_remote_get($full_url, [
        'timeout' => 30,
        'headers' => [
            'User-Agent' => 'Replanta Hub PageSpeed Tester'
        ]
    ]);
    
    if (is_wp_error($response)) {
        echo "❌ WordPress HTTP Error: " . $response->get_error_message() . "\n";
    } else {
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        echo "Response Code: $code\n";
        
        if ($code == 200) {
            echo "✅ SUCCESS: API call successful!\n";
            $data = json_decode($body, true);
            if ($data && isset($data['lighthouseResult'])) {
                echo "✅ Valid PageSpeed data received\n";
                $score = $data['lighthouseResult']['categories']['performance']['score'] ?? 0;
                echo "Performance Score: " . round($score * 100) . "/100\n";
            }
        } elseif ($code == 403) {
            echo "❌ ERROR 403: API Key Forbidden\n";
            echo "This usually means:\n";
            echo "- API key is invalid or expired\n";
            echo "- PageSpeed Insights API is not enabled for this key\n";
            echo "- API key restrictions (IPs, referrers) are blocking the request\n";
        } elseif ($code == 400) {
            echo "❌ ERROR 400: Bad Request\n";
            $error_data = json_decode($body, true);
            if ($error_data && isset($error_data['error'])) {
                echo "API Error: " . $error_data['error']['message'] . "\n";
            }
        } else {
            echo "❌ ERROR: HTTP $code\n";
        }
        
        // Show first 500 characters of response for debugging
        echo "\nResponse preview:\n";
        echo substr($body, 0, 500) . "\n";
    }
}

echo "\n4. Troubleshooting Steps:\n";
echo "If you're getting 'API Key inválida' errors:\n\n";
echo "a) Verify your API key in Google Cloud Console:\n";
echo "   - Go to: https://console.cloud.google.com/apis/credentials\n";
echo "   - Check that PageSpeed Insights API is enabled\n";
echo "   - Verify the API key is active and not restricted\n\n";
echo "b) Check API restrictions:\n";
echo "   - HTTP referrers (if set, should include your domain)\n";
echo "   - IP addresses (if set, should include your server IP)\n";
echo "   - API restrictions (should include PageSpeed Insights API)\n\n";
echo "c) Test the API key directly:\n";
echo "   - Try this URL in your browser:\n";
echo "   https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=https://google.com&key=YOUR_API_KEY\n\n";
echo "d) Common issues:\n";
echo "   - Extra spaces before/after the API key\n";
echo "   - Wrong API key (should be 39 characters)\n";
echo "   - API key from wrong Google Cloud project\n";
echo "   - PageSpeed API not enabled in Google Cloud Console\n\n";

echo "🔧 Current API Key (for manual testing):\n";
echo "$api_key\n\n";

echo "</pre>\n";
?>
