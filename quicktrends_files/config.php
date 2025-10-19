<?php
/**
 * QuickTrends.in - Configuration File
 * Optimized for Toronto VPS deployment
 */

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 in production
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Minimal .env loader with fallback to environment variables
function qt_env($key, $default = null) {
    static $env = null;
    if ($env === null) {
        $env = [];
        $paths = [__DIR__ . '/.env', dirname(__DIR__) . '/.env'];
        foreach ($paths as $p) {
            if (is_readable($p)) {
                $lines = @file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || strpos($line, '#') === 0) continue;
                    $pos = strpos($line, '=');
                    if ($pos === false) continue;
                    $k = trim(substr($line, 0, $pos));
                    $v = trim(substr($line, $pos + 1));
                    if ($v !== '' && ($v[0] === '"' || $v[0] === "'")) {
                        $v = trim($v, "\"'");
                    }
                    if ($k !== '') $env[$k] = $v;
                }
                break; // stop at first readable .env
            }
        }
    }
    $val = getenv($key);
    if ($val !== false && $val !== null && $val !== '') return $val;
    return $env[$key] ?? $default;
}

// Site configuration
define('SITE_NAME', 'QuickTrends');
define('SITE_URL', qt_env('QT_SITE_URL', 'https://quicktrends.in'));
define('SITE_DESCRIPTION', 'Your gateway to free educational courses and trending content');

// Security settings
define('ALLOWED_DOMAINS', [
    'udemy.com',
    'www.udemy.com',
    'coursera.org',
    'www.coursera.org',
    'edx.org',
    'www.edx.org'
]);

// Analytics settings
define('ENABLE_ANALYTICS', true);
define('LOG_FILE', __DIR__ . '/logs/clicks.log');
define('MAX_LOG_SIZE', 10 * 1024 * 1024); // 10MB

// A-ADS Integration (Based on OFFICIAL A-ADS Guidelines)
// Disabled by default; enable only when your own ad unit IDs are configured
define('AADS_ENABLED', filter_var(qt_env('QT_AADS_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN));
define('AADS_AD_UNITS', [
    // ONLY 1-2 UNITS PER PAGE (A-ADS Official Recommendation)
    '728x90' => qt_env('QT_AADS_728x90', ''),     // TOP BANNER - Most visible placement
    '300x250' => qt_env('QT_AADS_300x250', ''),   // RECTANGLE - Secondary placement only
]);

// A-ADS Official Optimization Settings
define('MAX_AD_UNITS_PER_PAGE', 2); // A-ADS Official: "no more than one or two ad units per page"

// Video Gate System (optional) [removed]

// Rate limiting (prevent abuse)
define('RATE_LIMIT_ENABLED', true);
define('MAX_REQUESTS_PER_HOUR', 100);
define('MAX_REQUESTS_PER_IP', 20);

// Cache settings
define('CACHE_ENABLED', true);
define('CACHE_DURATION', 3600); // 1 hour

// Timezone
date_default_timezone_set('America/Toronto');

/**
 * Validate URL against allowed domains
 */
function isValidDomain($url) {
    $parsed = parse_url($url);
    if (!$parsed || !isset($parsed['host'])) {
        return false;
    }
    
    $host = strtolower($parsed['host']);
    foreach (ALLOWED_DOMAINS as $allowed) {
        if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
            return true;
        }
    }
    return false;
}

/**
 * Log click with rate limiting and unique impression tracking
 */
function logClick($url, $ip) {
    if (!ENABLE_ANALYTICS) return true;
    
    // Create logs directory if it doesn't exist
    $logDir = dirname(LOG_FILE);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Rate limiting check
    if (RATE_LIMIT_ENABLED && !checkRateLimit($ip)) {
        return false;
    }
    
    // Track unique impressions (Emmanuel's emphasis)
    $isUniqueImpression = trackUniqueImpression($ip);
    
    // Log the click with unique impression data
    $logEntry = json_encode([
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $ip,
        'url' => $url,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'referer' => $_SERVER['HTTP_REFERER'] ?? '',
        'unique_impression' => $isUniqueImpression,
        'quality_score' => calculateTrafficQuality($ip, $_SERVER['HTTP_USER_AGENT'] ?? '')
    ]) . "\n";
    
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Rotate log if too large
    if (file_exists(LOG_FILE) && filesize(LOG_FILE) > MAX_LOG_SIZE) {
        rename(LOG_FILE, LOG_FILE . '.' . date('Y-m-d-H-i-s'));
    }
    
    return true;
}

/**
 * Track unique impressions (A-ADS optimization)
 */
function trackUniqueImpression($ip) {
    $uniqueFile = __DIR__ . '/cache/unique_' . date('Y-m-d') . '_' . md5($ip);
    
    if (file_exists($uniqueFile)) {
        return false; // Not unique today
    }
    
    // Create cache directory if needed
    $cacheDir = dirname($uniqueFile);
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    file_put_contents($uniqueFile, time(), LOCK_EX);
    return true; // Unique impression
}

/**
 * Calculate traffic quality score (A-ADS optimization)
 */
function calculateTrafficQuality($ip, $userAgent) {
    $score = 100; // Start with perfect score
    
    // Reduce score for suspicious patterns
    if (empty($userAgent)) $score -= 30;
    if (strpos($userAgent, 'bot') !== false) $score -= 50;
    if (strpos($userAgent, 'crawler') !== false) $score -= 50;
    
    // Bonus for educational referrers
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (strpos($referer, 'telegram') !== false) $score += 10;
    if (strpos($referer, 'education') !== false) $score += 10;
    
    return max(0, min(100, $score));
}

/**
 * Simple rate limiting
 */
function checkRateLimit($ip) {
    $cacheFile = __DIR__ . '/cache/rate_limit_' . md5($ip);
    $now = time();
    
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if ($data && $now - $data['timestamp'] < 3600) { // 1 hour window
            if ($data['count'] >= MAX_REQUESTS_PER_IP) {
                return false;
            }
            $data['count']++;
        } else {
            $data = ['timestamp' => $now, 'count' => 1];
        }
    } else {
        $data = ['timestamp' => $now, 'count' => 1];
    }
    
    // Create cache directory if it doesn't exist
    $cacheDir = dirname($cacheFile);
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    file_put_contents($cacheFile, json_encode($data), LOCK_EX);
    return true;
}

/**
 * Get A-ADS ad code with smart rotation
 */
function getAadsAdCode($size = '300x250', $position = 1) {
    // Render nothing unless explicitly enabled and configured with a valid unit ID
    if (!AADS_ENABLED) return '';
    $adKey = $size;
    $adUnitId = AADS_AD_UNITS[$adKey] ?? '';
    if (!$adUnitId) return '';

    // A-ADS Official iframe format (from documentation)
    $dimensions = explode('x', $size);
    $width = $dimensions[0];
    $height = $dimensions[1];
    return '<iframe data-aa="' . $adUnitId . '" src="//acceptable.a-ads.com/1" style="border:0px; padding:0; width:' . $width . 'px; height:' . $height . 'px; overflow:hidden; background-color: transparent;"></iframe>';
}

 
?>
