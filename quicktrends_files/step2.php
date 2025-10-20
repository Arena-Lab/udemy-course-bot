<?php
/**
 * QuickTrends.in - Step 2: Final Course Access
 * Revenue-optimized second step with enhanced ad placement
 */

require_once 'config.php';

// Get and validate target URL
$target_url = $_GET['u'] ?? '';

if (!$target_url || !filter_var($target_url, FILTER_VALIDATE_URL)) {
    header('Location: index.php');
    exit;
}

// Security check
if (!isValidDomain($target_url)) {
    http_response_code(403);
    die('Domain not allowed');
}

// Enhanced analytics tracking
$client_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$referrer = $_SERVER['HTTP_REFERER'] ?? '';

// Log step 2 access
logDetailedClick($target_url . ' (step2)', $client_ip, $user_agent, $referrer);

// Load course data from bot-exported feed
function qt_load_courses_feed() {
    $file = __DIR__ . '/courses.json';
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)) return $data;
    }
    return ['courses' => []];
}

function qt_find_course_from_feed($target_url) {
    $feed = qt_load_courses_feed();
    $all = is_array($feed['courses'] ?? null) ? $feed['courses'] : [];
    $slug = '';
    $path = parse_url($target_url, PHP_URL_PATH);
    if (preg_match('/\/course\/([^\/]+)/', (string)$path, $m)) {
        $slug = strtolower($m[1]);
    }
    foreach ($all as $c) {
        $u = (string)($c['url'] ?? '');
        $p = parse_url($u, PHP_URL_PATH) ?? '';
        if ($slug && preg_match('/\/course\/([^\/]+)/', $p, $mm)) {
            if (strtolower($mm[1]) === $slug) {
                return $c;
            }
        }
    }
    return null;
}

function qt_hd_image($url) {
    // Try to upgrade Udemy image to HD if pattern matches; otherwise return original
    if (is_string($url) && strpos($url, 'img-c.udemycdn.com') !== false && strpos($url, '/course/750x422/') !== false) {
        return str_replace('/course/750x422/', '/course/1250x720/', $url);
    }
    return $url;
}

function qt_get_trending_courses($limit = 8) {
    $cache_file = __DIR__ . '/cache_trending_courses.json';
    // Check cache (2h)
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 7200) {
        $cached = json_decode(file_get_contents($cache_file), true);
        if (is_array($cached)) return array_slice($cached, 0, $limit);
    }

    $feed = qt_load_courses_feed();
    $all = is_array($feed['courses'] ?? null) ? $feed['courses'] : [];

    usort($all, function($a, $b) {
        $ra = (float)($a['rating'] ?? 0);
        $rb = (float)($b['rating'] ?? 0);
        if ($ra === $rb) return 0;
        return ($rb <=> $ra);
    });

    $selected = array_slice($all, 0, $limit);
    file_put_contents($cache_file, json_encode($selected));
    return $selected;
}

function qt_get_related_by_category($category, $limit = 8) {
    $feed = qt_load_courses_feed();
    $all = is_array($feed['courses'] ?? null) ? $feed['courses'] : [];
    if (!$category) return array_slice($all, 0, $limit);
    $filtered = array_values(array_filter($all, function($c) use ($category) {
        return isset($c['category']) && strtolower(trim($c['category'])) === strtolower(trim($category));
    }));
    usort($filtered, function($a, $b) {
        $rb = (float)($b['rating'] ?? 0);
        $ra = (float)($a['rating'] ?? 0);
        return $rb <=> $ra;
    });
    return array_slice($filtered, 0, $limit);
}

// Extract course info
function extractCourseInfo($url) {
    $info = [
        'title' => 'Premium Educational Course',
        'platform' => 'Udemy',
        'category' => 'Professional Development',
        'instructor' => 'Expert Instructor'
    ];
    
    if (strpos($url, 'udemy.com') !== false) {
        $path = parse_url($url, PHP_URL_PATH);
        if (preg_match('/\/course\/([^\/]+)/', $path, $matches)) {
            $slug = str_replace(['-', '_'], ' ', $matches[1]);
            $info['title'] = ucwords($slug);
        }
    }
    
    return $info;
}

$course_info = extractCourseInfo($target_url);
$current_course = qt_find_course_from_feed($target_url);
if ($current_course) {
    if (!empty($current_course['title'])) $course_info['title'] = $current_course['title'];
    if (!empty($current_course['category'])) $course_info['category'] = $current_course['category'];
    $course_info['platform'] = 'Udemy';
}
$trending_courses = $current_course ? qt_get_related_by_category($current_course['category'] ?? '', 8) : qt_get_trending_courses(8);
$display_url = parse_url($target_url, PHP_URL_HOST);

function logDetailedClick($url, $ip, $user_agent, $referrer) {
    $log_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'url' => $url,
        'ip' => $ip,
        'user_agent' => $user_agent,
        'referrer' => $referrer,
        'step' => 'final'
    ];
    
    $log_file = __DIR__ . '/logs/detailed_clicks.log';
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, json_encode($log_data) . "\n", FILE_APPEND | LOCK_EX);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access <?= htmlspecialchars($course_info['title']) ?> - QuickTrends</title>
    <meta name="description" content="Final step to access your free course. Get instant access to premium educational content.">
    <meta name="robots" content="noindex, nofollow">
    
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎓</text></svg>">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #2d3748;
            background: #f7fafc;
        }
        
        /* Header */
        .top-banner {
            background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
            color: white;
            padding: 12px 0;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .banner-content {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .banner-text {
            font-weight: 600;
            font-size: 16px;
        }
        
        .telegram-btn {
            background: #0088cc;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            position: relative;
        }
        
        /* Side Ads */
        .side-ad-left, .side-ad-right {
            position: fixed;
            top: 50%;
            transform: translateY(-50%);
            width: 300px;
            z-index: 100;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 15px;
            text-align: center;
        }
        
        .side-ad-left {
            left: 20px;
        }
        
        .side-ad-right {
            right: 20px;
        }
        
        .side-ad-left .ad-label, .side-ad-right .ad-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        /* Access Ready Section */
        .access-ready-section {
            background: white;
            margin: 20px auto;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .access-badge {
            display: inline-block;
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .access-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 25px;
            line-height: 1.3;
        }
        .course-hero-image {
            width: 100%;
            max-width: 720px;
            height: auto;
            border-radius: 12px;
            margin: 12px auto 0 auto;
            display: block;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }
        
        .course-meta-final {
            display: flex;
            justify-content: center;
            gap: 25px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .meta-item-final {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #4a5568;
            font-weight: 500;
            font-size: 14px;
        }
        
        .verification-status {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .status-item {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            color: #4a5568;
        }
        
        .status-icon {
            font-size: 16px;
        }
        
        /* Learning Path Section */
        .learning-path-section {
            background: white;
            margin: 30px auto;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .path-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .step-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            border-radius: 12px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            transition: all 0.3s;
        }
        
        .step-item.completed {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border-color: #48bb78;
        }
        
        .step-item.active {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-color: #f6ad55;
            transform: scale(1.02);
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #4a5568;
        }
        
        .step-item.completed .step-number {
            background: #48bb78;
            color: white;
        }
        
        .step-item.active .step-number {
            background: #f6ad55;
            color: white;
        }
        
        .step-content h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 5px;
        }
        
        .step-content p {
            font-size: 13px;
            color: #4a5568;
            margin: 0;
        }
        
        /* Success Stories Section */
        .success-stories-section {
            background: white;
            margin: 30px auto;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
        }
        
        .story-card {
            display: flex;
            gap: 15px;
            padding: 25px;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            border: 2px solid #e2e8f0;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }
        
        .story-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .story-link {
            text-decoration: none;
            color: inherit;
            cursor: pointer;
        }
        
        .story-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.15);
            border-color: #667eea;
            background: linear-gradient(135deg, #ffffff 0%, #f0f4ff 100%);
        }
        
        .story-card:hover::before {
            transform: scaleX(1);
        }
        
        .story-link:hover {
            text-decoration: none;
            color: inherit;
        }
        
        .story-avatar {
            font-size: 3rem;
            flex-shrink: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
            transition: all 0.3s ease;
        }
        
        .story-card:hover .story-avatar {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }
        
        .story-content h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 10px;
        }
        
        .story-content p {
            font-size: 15px;
            color: #4a5568;
            margin-bottom: 12px;
            line-height: 1.5;
            font-style: italic;
        }
        
        .story-meta {
            font-size: 13px;
            color: #667eea;
            font-weight: 600;
            background: rgba(102, 126, 234, 0.1);
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
        }
        
        .story-cta {
            font-size: 14px;
            color: #667eea;
            font-weight: 700;
            margin-top: 12px;
            opacity: 0;
            transition: all 0.4s ease;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .story-card:hover .story-cta {
            opacity: 1;
            transform: translateX(8px);
        }
        
        /* Tips Section */
        .tips-section {
            background: white;
            margin: 30px auto;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .tips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }
        
        .tip-card {
            text-align: center;
            padding: 25px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }
        
        .tip-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #667eea;
        }
        
        .tip-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .tip-card h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 10px;
        }
        
        .tip-card p {
            color: #4a5568;
            font-size: 14px;
            line-height: 1.5;
        }
        
        /* Articles Section */
        .articles-section {
            background: white;
            margin: 30px auto;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .articles-container {
            display: flex;
            gap: 30px;
            align-items: flex-start;
        }
        
        .articles-grid {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .article-card {
            display: flex;
            gap: 20px;
            padding: 25px;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            border: 2px solid #e2e8f0;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .article-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .article-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.15);
            border-color: #667eea;
            background: linear-gradient(135deg, #ffffff 0%, #f0f4ff 100%);
        }
        
        .article-card:hover::before {
            transform: scaleX(1);
        }
        
        .article-image {
            font-size: 3rem;
            flex-shrink: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            width: 70px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
            transition: all 0.3s ease;
        }
        
        .article-card:hover .article-image {
            transform: scale(1.1) rotate(-5deg);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .article-content h3 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 12px;
        }
        
        .article-content p {
            color: #4a5568;
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 18px;
        }
        
        .article-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .article-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
        }
        
        .article-card:hover .article-link::after {
            width: 100%;
        }
        
        .article-card:hover .article-link {
            transform: translateX(5px);
        }
        
        /* Side Ad */
        .side-ad {
            flex-shrink: 0;
            width: 180px;
        }
        
        /* Simple Enroll Section */
        .simple-enroll-section {
            background: white;
            margin: 40px auto;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .simple-enroll-content h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 10px;
        }
        
        .simple-enroll-content p {
            color: #4a5568;
            margin-bottom: 25px;
        }
        
        .simple-enroll-btn {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 15px 40px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 18px;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .simple-enroll-btn:hover {
            background: #4f46e5;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        /* Explore Categories Section */
        .explore-categories-section {
            background: white;
            margin: 30px auto;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .explore-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .explore-card {
            display: block;
            text-decoration: none;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .explore-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .explore-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.15);
            border-color: #667eea;
            background: linear-gradient(135deg, #ffffff 0%, #f0f4ff 100%);
        }
        
        .explore-card:hover::before {
            transform: scaleX(1);
        }
        
        .explore-card.featured {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .explore-card.featured::before {
            background: linear-gradient(135deg, #ffffff 0%, rgba(255,255,255,0.3) 100%);
        }
        
        .explore-card.featured:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 25px 50px rgba(102, 126, 234, 0.4);
        }
        
        .explore-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            width: 70px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px auto;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
            transition: all 0.3s ease;
        }
        
        .explore-card:hover .explore-icon {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .explore-card.featured .explore-icon {
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.3);
        }
        
        .explore-card.featured:hover .explore-icon {
            background: rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 25px rgba(255, 255, 255, 0.4);
        }
        
        .explore-card h3 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 10px;
        }
        
        .explore-card.featured h3 {
            color: white;
        }
        
        .explore-card p {
            color: #4a5568;
            font-size: 14px;
            margin: 0 0 15px 0;
            font-weight: 500;
        }
        
        .explore-card.featured p {
            color: rgba(255,255,255,0.9);
        }
        
        /* Ad Sections */
        .ad-section {
            background: white;
            margin: 30px auto;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .ad-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        /* Trending Courses */
        .trending-section {
            background: white;
            margin: 40px auto;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .section-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1a202c;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
        }

        /* Carousel next button (mobile only) */
        .scroll-next { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); width: 34px; height: 34px; border-radius: 9999px; border: 1px solid #e5e7f0; background: rgba(255,255,255,.95); display: none; place-items: center; font-size: 20px; line-height: 1; box-shadow: 0 8px 24px rgba(0,0,0,.15); cursor: pointer; }
        
        .course-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
            border-color: #667eea;
        }
        
        .course-image {
            width: 100%;
            height: 160px;
            object-fit: cover;
        }
        
        .course-content {
            padding: 20px;
        }
        
        .course-card-title {
            font-size: 16px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 12px;
            line-height: 1.4;
            min-height: 44px;
        }
        
        .course-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            font-size: 14px;
            color: #718096;
        }
        
        .rating {
            display: flex;
            align-items: center;
            gap: 4px;
            font-weight: 600;
            color: #f6ad55;
        }
        
        .course-btn {
            display: block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px;
            text-align: center;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .course-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        /* Course Ad Card */
        .course-ad-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 400px;
        }
        
        .course-ad-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
            border-color: #667eea;
        }
        
        .course-ad-content {
            padding: 20px;
            text-align: center;
            width: 100%;
        }
        
        .course-ad-content .ad-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        /* Footer */
        .footer {
            background: #1a202c;
            color: white;
            padding: 40px 0;
            margin-top: 50px;
            text-align: center;
        }
        
        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Hide side ads on smaller screens */
        @media (max-width: 1600px) {
            .side-ad-left, .side-ad-right {
                display: none;
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .access-title {
                font-size: 1.5rem;
            }
            
            .course-meta-final {
                flex-direction: column;
                gap: 15px;
            }
            
            .verification-status {
                grid-template-columns: 1fr;
            }
            
            .tips-grid {
                grid-template-columns: 1fr;
            }
            
            .articles-container {
                flex-direction: column;
            }
            
            .side-ad {
                width: 100%;
                text-align: center;
            }
            
            .stories-grid {
                grid-template-columns: 1fr;
            }
            
            .courses-grid {
                grid-template-columns: 1fr;
            }
            
            .explore-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
            
            .simple-enroll-btn {
                padding: 15px 30px;
                font-size: 16px;
            }
        }
        
        /* Loading animation for ads */
        .ad-placeholder {
            min-height: 250px;
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #718096;
            font-size: 14px;
            position: relative;
            overflow: hidden;
        }
        
        .ad-placeholder::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
    </style>
</head>
<body>
    <!-- Top Banner -->
    <div class="top-banner">
        <div class="banner-content">
            <span class="banner-text">🎉 Course Access Confirmed!</span>
            <a href="https://t.me/quicktrends_channel" class="telegram-btn" target="_blank">
                Join Our Community
            </a>
        </div>
    </div>
    
    <!-- Left Side Ad -->
    <?php $ad_left = getAadsAdCode('300x600', 6); if (!empty($ad_left)): ?>
    <div class="side-ad-left">
        <div class="ad-label">Advertisement</div>
        <div class="ad-placeholder" style="min-height: 600px;">
            <?= $ad_left ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Right Side Ad -->
    <?php $ad_right = getAadsAdCode('300x600', 7); if (!empty($ad_right)): ?>
    <div class="side-ad-right">
        <div class="ad-label">Advertisement</div>
        <div class="ad-placeholder" style="min-height: 600px;">
            <?= $ad_right ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="container">
        <!-- Top Ad - First Thing Users See -->
        <?php $ad_top = getAadsAdCode('728x90', 2); if (!empty($ad_top)): ?>
        <div class="ad-section">
            <div class="ad-label">Advertisement</div>
            <div class="ad-placeholder">
                <?= $ad_top ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Course Access Ready Section -->
        <div class="access-ready-section">
            <div class="access-badge">✅ Course Access Ready</div>
            <h1 class="access-title"><?= htmlspecialchars($course_info['title']) ?></h1>
            <?php if (!empty($current_course['image'])): ?>
            <img src="<?= htmlspecialchars($current_course['image']) ?>" alt="<?= htmlspecialchars($course_info['title']) ?>" class="course-hero-image">
            <?php endif; ?>
            
            <div class="course-meta-final">
                <div class="meta-item-final">
                    <span>🏷️</span>
                    <span><?= htmlspecialchars($course_info['category']) ?></span>
                </div>
                <div class="meta-item-final">
                    <span>🌐</span>
                    <span><?= htmlspecialchars($course_info['platform']) ?></span>
                </div>
                <div class="meta-item-final">
                    <span>💰</span>
                    <span>100% FREE</span>
                </div>
                <div class="meta-item-final">
                    <span>⏰</span>
                    <span>Limited Time</span>
                </div>
            </div>
            
            <div class="verification-status">
                <div class="status-item">
                    <span class="status-icon">✅</span>
                    <span>Coupon Verified</span>
                </div>
                <div class="status-item">
                    <span class="status-icon">🔒</span>
                    <span>Secure Link</span>
                </div>
                <div class="status-item">
                    <span class="status-icon">⚡</span>
                    <span>Instant Access</span>
                </div>
                <div class="status-item">
                    <span class="status-icon">🎓</span>
                    <span>Certificate Included</span>
                </div>
            </div>
        </div>
        
        <!-- More Learning Tips Section -->
        <div class="tips-section">
            <h2 class="section-title">💡 Learning Tips & Strategies</h2>
            <div class="tips-grid">
                <div class="tip-card">
                    <div class="tip-icon">⏰</div>
                    <h3>Set Learning Schedule</h3>
                    <p>Dedicate 30-60 minutes daily for consistent progress and better retention.</p>
                </div>
                <div class="tip-card">
                    <div class="tip-icon">📝</div>
                    <h3>Take Notes</h3>
                    <p>Write down key concepts and create your own reference guide.</p>
                </div>
                <div class="tip-card">
                    <div class="tip-icon">🛠️</div>
                    <h3>Practice Projects</h3>
                    <p>Build real projects to apply what you learn and create a portfolio.</p>
                </div>
                <div class="tip-card">
                    <div class="tip-icon">👥</div>
                    <h3>Join Communities</h3>
                    <p>Connect with other learners for support and networking opportunities.</p>
                </div>
            </div>
        </div>

        <!-- Rectangle Ad -->
        <?php $ad_rect = getAadsAdCode('300x250', 2); if (!empty($ad_rect)): ?>
        <div class="ad-section" style="max-width: 400px;">
            <div class="ad-label">Advertisement</div>
            <div class="ad-placeholder" style="min-height: 250px;">
                <?= $ad_rect ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Success Stories Section -->
        <div class="success-stories-section">
            <h2 class="section-title">🌟 Student Success Stories</h2>
            <div class="stories-grid">
                <a href="blog.php?id=2" class="story-card story-link">
                    <div class="story-avatar">👨‍💻</div>
                    <div class="story-content">
                        <h4>Alex M.</h4>
                        <p>"Completed 5 free courses and landed my dream job as a software developer!"</p>
                        <div class="story-meta">Web Developer • 6 months ago</div>
                        <div class="story-cta">→ Explore Web Development Courses</div>
                    </div>
                </a>
                <a href="blog.php?id=5" class="story-card story-link">
                    <div class="story-avatar">👩‍🎨</div>
                    <div class="story-content">
                        <h4>Sarah K.</h4>
                        <p>"The design courses helped me transition from marketing to UX design."</p>
                        <div class="story-meta">UX Designer • 3 months ago</div>
                        <div class="story-cta">→ Explore Design Courses</div>
                    </div>
                </a>
                <a href="blog.php?id=4" class="story-card story-link">
                    <div class="story-avatar">👨‍💼</div>
                    <div class="story-content">
                        <h4>Mike R.</h4>
                        <p>"Free data science courses gave me the skills to get promoted!"</p>
                        <div class="story-meta">Data Analyst • 2 months ago</div>
                        <div class="story-cta">→ Explore Data Science Courses</div>
                    </div>
                </a>
            </div>
        </div>
        
        <!-- Trending Courses -->
        <?php if (!empty($trending_courses)): ?>
        <div class="trending-section">
            <h2 class="section-title">🔥 Trending Free Courses</h2>
            <div class="courses-grid" id="trendgrid">
                <?php foreach ($trending_courses as $item): ?>
                    <div class="course-card">
                        <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="course-image">
                        <div class="course-content">
                            <h3 class="course-card-title"><?= htmlspecialchars($item['title']) ?></h3>
                            <div class="course-stats">
                                <div class="rating">
                                    <span>⭐</span>
                                    <span><?= number_format((float)($item['rating'] ?? 0), 1) ?></span>
                                </div>
                                <span>👥 <?= htmlspecialchars($item['students'] ?? '—') ?></span>
                                <span>🕒 <?= htmlspecialchars($item['duration'] ?? '') ?></span>
                            </div>
                            <a href="go.php?u=<?= urlencode($item['url']) ?>" class="course-btn">
                                Get Free Access
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="scroll-next" data-target="#trendgrid" aria-label="Next">›</button>
        </div>
        <?php endif; ?>
        
        <!-- Learning Articles Section -->
        <div class="articles-section">
            <h2 class="section-title">📖 Essential Learning Resources</h2>
            <div class="articles-container">
                <div class="articles-grid">
                    <article class="article-card">
                        <div class="article-image">📚</div>
                        <div class="article-content">
                            <h3>How to Learn Programming Effectively</h3>
                            <p>Discover proven strategies and techniques that successful programmers use to master new technologies quickly and efficiently.</p>
                            <a href="blog.php?id=1" class="article-link">Read More →</a>
                        </div>
                    </article>
                    
                    <article class="article-card">
                        <div class="article-image">💡</div>
                        <div class="article-content">
                            <h3>Building Your First Portfolio Project</h3>
                            <p>Step-by-step guide to creating impressive portfolio projects that will help you land your dream job in tech.</p>
                            <a href="blog.php?id=2" class="article-link">Read More →</a>
                        </div>
                    </article>
                    
                    <article class="article-card">
                        <div class="article-image">🎯</div>
                        <div class="article-content">
                            <h3>Career Transition to Tech Industry</h3>
                            <p>Complete roadmap for professionals looking to switch careers and break into the lucrative tech industry.</p>
                            <a href="blog.php?id=8" class="article-link">Read More →</a>
                        </div>
                    </article>
                    
                    <article class="article-card">
                        <div class="article-image">🚀</div>
                        <div class="article-content">
                            <h3>Mastering Online Learning Skills</h3>
                            <p>Essential techniques and strategies to maximize your online learning experience and retain knowledge effectively.</p>
                            <a href="blog.php?id=5" class="article-link">Read More →</a>
                        </div>
                    </article>
                </div>
                
                <!-- Side Ad -->
                <?php $ad_side = getAadsAdCode('160x600', 1); if (!empty($ad_side)): ?>
                <div class="side-ad">
                    <div class="ad-label">Advertisement</div>
                    <div class="ad-placeholder" style="min-height: 600px;">
                        <?= $ad_side ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Mid-Content Ad -->
        <?php $ad_mid = getAadsAdCode('728x90', 4); if (!empty($ad_mid)): ?>
        <div class="ad-section">
            <div class="ad-label">Advertisement</div>
            <div class="ad-placeholder">
                <?= $ad_mid ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Explore More Categories -->
        <div class="explore-categories-section">
            <h2 class="section-title">📚 Explore More Free Courses</h2>
            <div class="explore-grid">
                <a href="blog.php?id=1" class="explore-card">
                    <div class="explore-icon">💻</div>
                    <h3>Programming</h3>
                    <p>50+ Free Courses</p>
                </a>
                <a href="blog.php?id=2" class="explore-card">
                    <div class="explore-icon">🌐</div>
                    <h3>Web Development</h3>
                    <p>40+ Free Courses</p>
                </a>
                <a href="blog.php?id=3" class="explore-card">
                    <div class="explore-icon">📊</div>
                    <h3>Data Science</h3>
                    <p>35+ Free Courses</p>
                </a>
                <a href="blog.php?id=10" class="explore-card featured">
                    <div class="explore-icon">🔥</div>
                    <h3>All Courses</h3>
                    <p>200+ Free Courses</p>
                </a>
            </div>
        </div>
        
        <!-- Final Simple Enroll Button -->
        <div class="simple-enroll-section">
            <div class="simple-enroll-content">
                <h3>Ready to Start Learning?</h3>
                <p>Your course is verified and ready. Click below to enroll now.</p>
                <a href="<?= htmlspecialchars($target_url) ?>" target="_blank" class="simple-enroll-btn" onclick="trackFinalClick()">
                    Enroll Now
                </a>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <p>&copy; <?= date('Y') ?> QuickTrends.in - Supporting Free Education Worldwide</p>
            <p style="margin-top: 10px; opacity: 0.7; font-size: 14px;">
                Thank you for choosing QuickTrends for your learning journey!
            </p>
        </div>
    </footer>
    
    <script>
        // Track final click conversion
        function trackFinalClick() {
            // Send conversion data
            if (navigator.sendBeacon) {
                const data = new FormData();
                data.append('action', 'track_conversion');
                data.append('url', '<?= htmlspecialchars($target_url) ?>');
                data.append('timestamp', Date.now());
                
                navigator.sendBeacon('analytics.php', data);
            }
            
            // Optional: Small delay to ensure tracking
            setTimeout(() => {
                console.log('Conversion tracked successfully');
            }, 100);
        }
        
        // Enhanced engagement tracking
        document.addEventListener('DOMContentLoaded', function() {
            let startTime = Date.now();
            let adViews = 0;
            
            // Track ad visibility
            const ads = document.querySelectorAll('.ad-section');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        adViews++;
                        console.log('Ad viewed, total views:', adViews);
                    }
                });
            }, { threshold: 0.5 });
            
            ads.forEach(ad => observer.observe(ad));
            
            // Track page engagement
            window.addEventListener('beforeunload', function() {
                const timeSpent = Math.round((Date.now() - startTime) / 1000);
                
                if (navigator.sendBeacon) {
                    const data = new FormData();
                    data.append('action', 'track_step2_engagement');
                    data.append('time_spent', timeSpent);
                    data.append('ad_views', adViews);
                    data.append('url', window.location.href);
                    
                    navigator.sendBeacon('analytics.php', data);
                }
            });
        });
        
        // Smooth scroll for better UX
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>
