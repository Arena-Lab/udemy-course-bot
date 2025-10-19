<?php
/**
 * QuickTrends.in - Analytics & User Tracking System
 * Collects user engagement data for revenue optimization
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

// Get client information
$client_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$timestamp = date('Y-m-d H:i:s');

// Process different tracking actions
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'track_engagement':
        trackEngagement();
        break;
    case 'track_conversion':
        trackConversion();
        break;
    case 'track_step2_engagement':
        trackStep2Engagement();
        break;
    default:
        http_response_code(400);
        exit(json_encode(['error' => 'Invalid action']));
}

function trackEngagement() {
    global $client_ip, $user_agent, $timestamp;
    
    $data = [
        'timestamp' => $timestamp,
        'ip' => hashIP($client_ip),
        'user_agent' => substr($user_agent, 0, 200),
        'time_spent' => (int)($_POST['time_spent'] ?? 0),
        'max_scroll' => (int)($_POST['max_scroll'] ?? 0),
        'url' => $_POST['url'] ?? '',
        'type' => 'engagement'
    ];
    
    logAnalytics($data, 'engagement.log');
    echo json_encode(['status' => 'success']);
}

function trackConversion() {
    global $client_ip, $user_agent, $timestamp;
    
    $data = [
        'timestamp' => $timestamp,
        'ip' => hashIP($client_ip),
        'user_agent' => substr($user_agent, 0, 200),
        'target_url' => $_POST['url'] ?? '',
        'conversion_time' => (int)($_POST['timestamp'] ?? time()),
        'type' => 'conversion'
    ];
    
    logAnalytics($data, 'conversions.log');
    echo json_encode(['status' => 'success']);
}

function trackStep2Engagement() {
    global $client_ip, $user_agent, $timestamp;
    
    $data = [
        'timestamp' => $timestamp,
        'ip' => hashIP($client_ip),
        'user_agent' => substr($user_agent, 0, 200),
        'time_spent' => (int)($_POST['time_spent'] ?? 0),
        'ad_views' => (int)($_POST['ad_views'] ?? 0),
        'url' => $_POST['url'] ?? '',
        'type' => 'step2_engagement'
    ];
    
    logAnalytics($data, 'step2_engagement.log');
    echo json_encode(['status' => 'success']);
}

function hashIP($ip) {
    // Hash IP for privacy compliance while maintaining uniqueness
    return hash('sha256', $ip . 'quicktrends_salt_2024');
}

function logAnalytics($data, $filename) {
    $log_dir = __DIR__ . '/logs/analytics';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_file = $log_dir . '/' . $filename;
    $log_entry = json_encode($data) . "\n";
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    // Rotate logs daily
    rotateLogIfNeeded($log_file);
}

function rotateLogIfNeeded($log_file) {
    if (!file_exists($log_file)) return;
    
    $file_date = date('Y-m-d', filemtime($log_file));
    $today = date('Y-m-d');
    
    if ($file_date !== $today && filesize($log_file) > 1024 * 1024) { // 1MB
        $archived_name = $log_file . '.' . $file_date;
        rename($log_file, $archived_name);
        
        // Compress old logs
        if (function_exists('gzencode')) {
            $content = file_get_contents($archived_name);
            file_put_contents($archived_name . '.gz', gzencode($content));
            unlink($archived_name);
        }
    }
}

// Clean up old logs (keep last 30 days)
function cleanupOldLogs() {
    $log_dir = __DIR__ . '/logs/analytics';
    if (!is_dir($log_dir)) return;
    
    $files = glob($log_dir . '/*.gz');
    $cutoff = time() - (30 * 24 * 60 * 60); // 30 days
    
    foreach ($files as $file) {
        if (filemtime($file) < $cutoff) {
            unlink($file);
        }
    }
}

// Run cleanup occasionally (1% chance)
if (rand(1, 100) === 1) {
    cleanupOldLogs();
}
?>
