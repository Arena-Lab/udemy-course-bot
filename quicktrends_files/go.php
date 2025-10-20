<?php
/**
 * QuickTrends.in - Professional Course Access Portal
 * Inspired by StudyBullet.com design and monetization strategy
 * Two-step revenue optimization with user tracking
 */

require_once 'config.php';

// Get and validate target URL
$target_url = $_GET['u'] ?? '';
if (!$target_url || !filter_var($target_url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    die('Invalid URL provided');
}

// Determine if a course is active (not expired) using common keys
function qt_parse_time($v) {
    if (is_numeric($v)) return (int)$v;
    if (is_string($v)) {
        $t = strtotime($v);
        if ($t !== false && $t > 0) return $t;
    }
    return 0;
}

function qt_is_course_active($c) {
    if (!is_array($c)) return true;
    // explicit flags
    if (isset($c['expired']) && $c['expired']) return false;
    if (isset($c['active']) && !$c['active']) return false;
    if (isset($c['coupon_status'])) {
        $st = strtolower((string)$c['coupon_status']);
        if (!in_array($st, ['active','valid','available','live','working'])) return false;
    }
    // expiry-like fields
    $keys = ['expires_at','expiry','expires','coupon_expiry','valid_till','end_date','coupon_end','expiry_time'];
    foreach ($keys as $k) {
        if (!empty($c[$k])) {
            $ts = qt_parse_time($c[$k]);
            if ($ts && time() > $ts) return false;
        }
    }
    return true;
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

// Security check
if (!isValidDomain($target_url)) {
    http_response_code(403);
    die('Domain not allowed');
}

// User tracking and analytics
$client_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$referrer = $_SERVER['HTTP_REFERER'] ?? '';

// Log detailed analytics
logDetailedClick($target_url, $client_ip, $user_agent, $referrer);

// Load course data for recommendations
function qt_load_blog_content() {
    $file = __DIR__ . '/blog-content.json';
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)) return $data;
    }
    return ['udemy_courses' => []];
}

function qt_load_courses_feed() {
    $file = __DIR__ . '/courses.json';
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)) return $data;
    }
    return ['courses' => []];
}

function qt_hd_image($url) {
    if (is_string($url) && strpos($url, 'img-c.udemycdn.com') !== false && strpos($url, '/course/750x422/') !== false) {
        return str_replace('/course/750x422/', '/course/1250x720/', $url);
    }
    return $url;
}


function qt_feed_stats() {
    $file = __DIR__ . '/courses.json';
    $feed = qt_load_courses_feed();
    $courses = is_array($feed['courses'] ?? null) ? $feed['courses'] : [];
    $active = 0; $latest = file_exists($file) ? filemtime($file) : 0;
    foreach ($courses as $c) {
        if (qt_is_course_active($c)) $active++;
        foreach (['updated_at','published_at','timestamp','scraped_at','date'] as $k) {
            if (!empty($c[$k])) $latest = max($latest, qt_parse_time($c[$k]));
        }
    }
    if (!$latest) $latest = time();
    return ['active_count'=>$active, 'updated_ts'=>$latest, 'updated_human'=>qt_human_time($latest)];
}

function qt_get_related_by_category($category, $exclude_slug = '', $limit = 6) {
    $feed = qt_load_courses_feed();
    $all = is_array($feed['courses'] ?? null) ? $feed['courses'] : [];
    $filtered = array_values(array_filter($all, function($c) use ($category, $exclude_slug) {
        if ($exclude_slug) {
            $u = (string)($c['url'] ?? '');
            $p = parse_url($u, PHP_URL_PATH) ?? '';
            if (preg_match('/\/course\/([^\/]+)/', $p, $mm)) {
                if (strtolower($mm[1]) === strtolower($exclude_slug)) return false;
            }
        }
        if ($category) {
            return qt_is_course_active($c) && isset($c['category']) && strtolower(trim($c['category'])) === strtolower(trim($category));
        }
        return qt_is_course_active($c) && !empty($c['url']);
    }));
    usort($filtered, function($a, $b) {
        $rb = (float)($b['rating'] ?? 0);
        $ra = (float)($a['rating'] ?? 0);
        return $rb <=> $ra;
    });
    return array_slice($filtered, 0, $limit);
}

function qt_get_related_courses($target_url, $limit = 6) {
    $cache_file = __DIR__ . '/cache_related_courses.json';
    $cache_key = md5($target_url);
    // Check cache (6h)
    if (file_exists($cache_file)) {
        $cache = json_decode(file_get_contents($cache_file), true);
        if (isset($cache[$cache_key]) && (time() - $cache[$cache_key]['timestamp']) < 21600) {
            return array_slice($cache[$cache_key]['courses'], 0, $limit);
        }
    }

    $feed = qt_load_courses_feed();
    $all = is_array($feed['courses'] ?? null) ? $feed['courses'] : [];

    // Exclude current course by comparing slug if possible
    $exclude_slug = '';
    $path = parse_url($target_url, PHP_URL_PATH);
    if (preg_match('/\/course\/([^\/]+)/', $path, $m)) {
        $exclude_slug = strtolower($m[1]);
    }

    $filtered = array_values(array_filter($all, function($c) use ($exclude_slug) {
        $u = (string)($c['url'] ?? '');
        if ($exclude_slug && preg_match('/\/course\/([^\/]+)/', parse_url($u, PHP_URL_PATH) ?? '', $mm)) {
            if (strtolower($mm[1]) === $exclude_slug) return false;
        }
        return qt_is_course_active($c) && !empty($c['url']);
    }));

    // Sort by rating desc, then recent (implicit order), then random small jitter
    usort($filtered, function($a, $b) {
        $ra = (float)($a['rating'] ?? 0);
        $rb = (float)($b['rating'] ?? 0);
        if ($ra === $rb) return 0;
        return ($rb <=> $ra);
    });

    $selected = array_slice($filtered, 0, $limit);

    // Update cache
    $cache = file_exists($cache_file) ? json_decode(file_get_contents($cache_file), true) : [];
    $cache[$cache_key] = [
        'timestamp' => time(),
        'courses' => $selected
    ];
    file_put_contents($cache_file, json_encode($cache));
    return $selected;
}

function qt_to_list($value) {
    if (is_array($value)) {
        $out = [];
        foreach ($value as $v) {
            if (is_string($v)) {
                $t = trim($v);
                if ($t !== '' && strlen($t) > 1) $out[] = $t;
            }
        }
        return $out;
    }
    if (is_string($value)) {
        $value = str_replace(["•", "\r"], ["\n", ""], $value);
        $parts = preg_split('/\n|;|\.|,\s*(?=[A-Z])/', $value);
        $out = [];
        foreach ($parts as $p) {
            $t = trim($p, " -\t\n");
            if ($t !== '' && strlen($t) > 3) $out[] = $t;
        }
        return $out;
    }
    return [];
}

function qt_human_time($ts) {
    $t = is_numeric($ts) ? (int)$ts : strtotime((string)$ts);
    if (!$t) return '';
    $diff = time() - $t;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff/60) . ' min ago';
    if ($diff < 86400) return floor($diff/3600) . ' hr ago';
    return floor($diff/86400) . ' days ago';
}

function qt_slug_from_url($url) {
    $p = parse_url((string)$url, PHP_URL_PATH) ?? '';
    if (preg_match('/\/course\/([^\/]+)/', $p, $m)) return strtolower($m[1]);
    return '';
}

// Extract course info from URL
function extractCourseInfo($url) {
    $info = [
        'title' => 'Premium Educational Course',
        'platform' => 'Udemy',
        'category' => 'Professional Development'
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
$curr = basename($_SERVER['PHP_SELF']);
$current_course = qt_find_course_from_feed($target_url);
$current_active = qt_is_course_active($current_course ?? []);
// Prefer category-based related from feed
$exclude_slug = '';
$path_for_exclude = parse_url($target_url, PHP_URL_PATH);
if (preg_match('/\/course\/([^\/]+)/', (string)$path_for_exclude, $m)) {
    $exclude_slug = strtolower($m[1]);
}
$related_all = qt_get_related_by_category($current_course['category'] ?? '', $exclude_slug, 20);
$display_url = parse_url($target_url, PHP_URL_HOST);

// Enhanced logging function
function logDetailedClick($url, $ip, $user_agent, $referrer) {
    $log_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'url' => $url,
        'ip' => $ip,
        'user_agent' => $user_agent,
        'referrer' => $referrer,
        'step' => 'landing'
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
    <title><?= htmlspecialchars($course_info['title']) ?> - Free Access | QuickTrends</title>
    <meta name="description" content="Get free access to <?= htmlspecialchars($course_info['title']) ?> and thousands of other premium courses. Join our learning community today.">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎓</text></svg>">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #111827; /* darker for better readability on white */
            background: #f7fafc;
        }
        
        /* Header inspired by StudyBullet */
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
        
        .top-banner-content {
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
        
        .telegram-btn:hover {
            background: #006699;
            transform: translateY(-1px);
        }
        
        /* Main header */
        .header {
            background: white;
            padding: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #1a202c;
            margin-bottom: 0;
        }
        
        .tagline {
            color: #718096;
            font-size: 16px;
            font-style: italic;
            margin-left: 16px;
        }

        /* Mobile navigation */
        .hamburger {
            width: 38px; height: 34px; border-radius: 8px; border: 1px solid #e2e8f0; display: grid; place-items: center; background: #f8fafc; cursor: pointer;
        }
        .hamburger span { width: 20px; height: 2px; background: #111827; display: block; position: relative; }
        .hamburger span::before, .hamburger span::after { content: ""; position: absolute; left: 0; width: 100%; height: 2px; background: #111827; }
        .hamburger span::before { top: -6px; }
        .hamburger span::after { top: 6px; }

        .mobile-menu { position: fixed; inset: 0; background: rgba(0,0,0,.4); display: none; z-index: 1100; }
        .mobile-menu.open { display: block; }
        .mobile-drawer { position: absolute; right: 0; top: 0; bottom: 0; width: 80%; max-width: 340px; background: #ffffff; box-shadow: -8px 0 24px rgba(0,0,0,0.15); padding: 22px; display: grid; gap: 0; }
        .mobile-link { display: block; text-decoration: none; color: #111827; font-weight: 700; padding: 10px 0; border: 0; background: transparent; }
        .mobile-link.active { color: #1e40af; }
        
        /* Carousel next button (mobile only) */
        .scroll-next { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); width: 34px; height: 34px; border-radius: 9999px; border: 1px solid #e5e7eb; background: rgba(255,255,255,.95); display: none; place-items: center; font-size: 20px; line-height: 1; box-shadow: 0 8px 24px rgba(0,0,0,.15); cursor: pointer; }
        @media (max-width: 768px) { .scroll-next { display: grid; } }
        
        /* Container */
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
        
        /* Top Ad Banner */
        .top-ad-banner {
            background: white;
            margin: 20px auto;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-align: center;
            border: 2px solid #e2e8f0;
        }
        
        .ad-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        /* Course Hero Section */
        .course-hero {
            background: white;
            margin: 20px auto;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            text-align: center;
        }

        /* New dark hero (Udemy-like) */
        .hero-dark {
            background: linear-gradient(180deg, #0f172a 0%, #111827 100%);
            color: #e5e7eb;
            margin: 20px auto 10px auto;
            padding: 32px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
        }
        .hero-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 28px; align-items: center; }
        .hero-title { font-size: 2.2rem; font-weight: 800; color: #fff; line-height: 1.2; margin-bottom: 10px; }
        .hero-sub { color: #cbd5e1; font-size: 15px; max-width: 720px; }
        .hero-badges { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; }
        .chip { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); color: #e5e7eb; padding: 6px 10px; border-radius: 9999px; font-size: 12px; font-weight: 700; letter-spacing: .2px; }
        .hero-summary { padding: 0; overflow: hidden; background: linear-gradient(180deg,#ffffff 0%, #f8fafc 100%); color: #1f2937; border:1px solid #e5e7eb; border-radius:12px; box-shadow: 0 20px 50px rgba(0,0,0,0.35); }
        .hero-summary .summary-title { color: #111827; }
        .hero-summary .summary-list, .hero-summary .summary-list li { color: #374151; }
        .hero-image-cover { width: 100%; height: 160px; object-fit: cover; display: block; border-top-left-radius: 12px; border-top-right-radius: 12px; }
        .hero-summary .summary-list { padding-top: 8px; }

        /* Summary CTA buttons */
        .summary-cta { display: block; text-align: center; margin: 12px 16px 16px 16px; padding: 12px 14px; border-radius: 10px; font-weight: 800; text-decoration: none; color: #fff; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 10px 25px rgba(102, 126, 234, 0.35); border: 1px solid rgba(102, 126, 234, 0.4); transition: transform .15s ease, box-shadow .2s ease; }
        .summary-cta:hover { transform: translateY(-2px); box-shadow: 0 16px 32px rgba(102, 126, 234, 0.45); }
        .summary-cta.secondary { background: #e2e8f0; color: #1f2937; border-color: #cbd5e1; box-shadow: 0 8px 20px rgba(203, 213, 225, 0.55); font-weight: 700; }
        .summary-cta.secondary:hover { transform: translateY(-2px); box-shadow: 0 14px 28px rgba(148, 163, 184, 0.6); }

        /* Telegram promo */
        .tele-promo { background: linear-gradient(135deg,#0ea5e9, #2563eb); color: #fff; border-radius: 16px; padding: 22px; display: flex; align-items: center; justify-content: space-between; gap: 16px; margin: 18px 0; box-shadow: 0 12px 28px rgba(37,99,235,0.35); }
        .tele-title { font-size: 1.1rem; font-weight: 800; }
        .tele-sub { opacity: .95; font-size: .95rem; }
        .tele-btn { display: inline-block; background:#fff; color:#1d4ed8; font-weight: 800; border-radius: 10px; padding: 10px 16px; text-decoration: none; border:1px solid rgba(255,255,255,0.8); box-shadow: 0 8px 24px rgba(255,255,255,0.25); }
        .tele-btn:hover { transform: translateY(-2px); }

        /* Similar coupons row (top) */
        .similar-section { background: white; margin: 16px auto 0 auto; padding: 22px; border-radius: 16px; box-shadow: 0 8px 25px rgba(0,0,0,0.08); position: relative; }
        .similar-row { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(220px, 1fr); gap: 16px; overflow-x: auto; padding-bottom: 8px; scroll-snap-type: x proximity; }
        .similar-card { display: block; text-decoration: none; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: #fff; color: #1f2937; scroll-snap-align: start; }
        .similar-thumb { width: 100%; height: 120px; object-fit: cover; display: block; }
        .similar-title { font-size: 14px; font-weight: 700; padding: 10px 12px 4px 12px; line-height: 1.3; height: 42px; overflow: hidden; }
        .similar-meta { padding: 0 12px 12px 12px; font-size: 12px; color: #64748b; }
        
        .course-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .course-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 15px;
            line-height: 1.2;
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
        
        .course-meta {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #4a5568;
            font-weight: 500;
        }
        
        .course-description {
            font-size: 18px;
            color: #4a5568;
            margin-bottom: 30px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .course-main { 
            background: white; 
            margin: 30px auto; 
            padding: 40px; 
            border-radius: 16px; 
            box-shadow: 0 8px 25px rgba(0,0,0,0.1); 
            display: grid; 
            grid-template-columns: 1fr; 
            gap: 30px; 
        }
        .details-tabs { }
        .tab-buttons { display: flex; gap: 10px; flex-wrap: wrap; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 15px; }
        .tab-btn { border: 1px solid #e2e8f0; background: #f8fafc; color: #4a5568; padding: 8px 12px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; }
        .tab-btn.active { background: #1e40af; color: #fff; border-color: #1e40af; }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }
        .panel-title { font-size: 1.1rem; font-weight: 700; color: #1a202c; margin: 6px 0 10px; }
        .panel-list { margin-left: 18px; color: #4a5568; line-height: 1.7; }
        .panel-text { color: #4a5568; line-height: 1.8; }
        .panel-text.clamped { display: -webkit-box; -webkit-line-clamp: 6; -webkit-box-orient: vertical; overflow: hidden; }
        .readmore-btn { background: transparent; border: none; color: #1e40af; font-weight: 800; cursor: pointer; padding: 6px 0; }

        .right-rail { display: grid; gap: 16px; }
        .summary-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; }
        .summary-title { font-size: 1rem; font-weight: 800; color: #1a202c; margin-bottom: 10px; }
        .summary-list { list-style: none; display: grid; gap: 8px; font-size: 14px; color: #4a5568; }
        .summary-cta { display: block; background: #1e40af; color: #fff; text-align: center; padding: 12px; border-radius: 8px; margin-top: 12px; font-weight: 700; text-decoration: none; }
        .ad-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; text-align: center; }
        .ad-card .ad-label { margin-bottom: 10px; }

        @media (max-width: 1024px) { .course-main { grid-template-columns: 1fr; } }
        
        .verification-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin: 30px 0;
        }
        
        .verification-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .verification-text {
            opacity: 0.9;
            margin-bottom: 20px;
        }
        
        .access-btn {
            display: inline-block;
            background: white;
            color: #667eea;
            padding: 15px 40px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            font-size: 18px;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .access-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        /* Final Continue Section */
        .final-continue-section {
            background: white;
            margin: 40px auto;
            padding: 50px 40px;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .continue-content h2 {
            font-size: 2rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 15px;
        }
        
        .continue-content p {
            color: #4a5568;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }
        
        .final-continue-btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 18px 50px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 20px;
            transition: all 0.3s;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }
        
        .final-continue-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        /* Related Courses Section */
        .related-section {
            background: white;
            margin: 30px auto;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
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
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }
        
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
            height: 180px;
            object-fit: cover;
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
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
        
        /* Rectangle Ad */
        .rectangle-ad {
            background: white;
            margin: 30px auto;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
        }
        
        /* Benefits Section */
        .benefits-section {
            background: white;
            margin: 30px auto;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }
        
        .benefit-card {
            text-align: center;
            padding: 25px;
            border-radius: 12px;
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }
        
        .benefit-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #667eea;
        }
        
        .benefit-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .benefit-card h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 10px;
        }
        
        .benefit-card p {
            color: #4a5568;
            font-size: 14px;
            line-height: 1.5;
        }
        
        /* Categories Section */
        .categories-section {
            background: white;
            margin: 30px auto;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .categories-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .category-card {
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
        
        .category-card::before {
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
        
        .category-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.15);
            border-color: #667eea;
            background: linear-gradient(135deg, #ffffff 0%, #f0f4ff 100%);
        }
        
        .category-card:hover::before {
            transform: scaleX(1);
        }
        
        .category-card.featured {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .category-card.featured::before {
            background: linear-gradient(135deg, #ffffff 0%, rgba(255,255,255,0.3) 100%);
        }
        
        .category-card.featured:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 25px 50px rgba(102, 126, 234, 0.4);
        }
        
        .category-icon {
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
        
        .category-card:hover .category-icon {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .category-card.featured .category-icon {
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.3);
        }
        
        .category-card.featured:hover .category-icon {
            background: rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 25px rgba(255, 255, 255, 0.4);
        }
        
        .category-card h3 {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 10px;
        }
        
        .category-card.featured h3 {
            color: white;
        }
        
        .category-card p {
            color: #4a5568;
            font-size: 14px;
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        .category-card.featured p {
            color: rgba(255,255,255,0.9);
        }
        
        .category-count {
            display: inline-block;
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            padding: 6px 16px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .category-card:hover .category-count {
            background: rgba(102, 126, 234, 0.2);
            transform: scale(1.05);
        }
        
        .category-card.featured .category-count {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        .category-card.featured:hover .category-count {
            background: rgba(255,255,255,0.3);
        }
        
        /* Category Ad Boxes */
        .category-ad-box {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .category-ad-box .ad-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        /* Cookie Consent */
        .cookie-consent {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #1a202c;
            color: white;
            padding: 20px;
            z-index: 1001;
            transform: translateY(100%);
            transition: transform 0.3s;
        }
        
        .cookie-consent.show {
            transform: translateY(0);
        }
        
        .cookie-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .cookie-text {
            flex: 1;
            font-size: 14px;
        }
        
        .cookie-buttons {
            display: flex;
            gap: 10px;
        }
        
        .cookie-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .cookie-accept {
            background: #667eea;
            color: white;
        }
        
        .cookie-decline {
            background: #e2e8f0;
            color: #4a5568;
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
        
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .footer-links a {
            color: #a0aec0;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-links a:hover {
            color: white;
        }
        
        /* Hide scrollbars on swipe rows for cleaner mobile feel */
        .similar-row, .related-section .courses-grid, .categories-section .categories-row { scrollbar-width: none; }
        .similar-row::-webkit-scrollbar, .related-section .courses-grid::-webkit-scrollbar, .categories-section .categories-row::-webkit-scrollbar { display: none; }

        /* Tablet Responsive */
        @media (max-width: 1024px) {
            .categories-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        /* Hide side ads on smaller screens */
        @media (max-width: 1600px) {
            .side-ad-left, .side-ad-right {
                display: none;
            }
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .header-content { flex-wrap: wrap; gap: 10px; }
            .tagline { width: 100%; font-size: 14px; margin-left: 0; text-align: left; opacity: .9; }
            .course-title {
                font-size: 2rem;
            }
            .hero-grid { grid-template-columns: 1fr; gap: 16px; }
            .hero-title { font-size: 1.6rem; }
            .hero-summary { margin-top: 12px; }
            
            .course-meta {
                flex-direction: column;
                gap: 15px;
            }
            /* swipeable rows for related courses */
            .related-section .courses-grid {
                display: grid; 
                grid-auto-flow: column; 
                grid-auto-columns: minmax(240px, 80%);
                gap: 16px; 
                overflow-x: auto; 
                padding-bottom: 8px; 
                -webkit-overflow-scrolling: touch;
                scroll-snap-type: x proximity;
            }
            .related-section .course-card { scroll-snap-align: start; }
            
            .benefits-grid {
                grid-template-columns: 1fr;
            }
            
            /* swipeable rows for categories: show one full card per view */
            .categories-section .categories-row {
                display: grid; 
                grid-auto-flow: column; 
                grid-template-columns: none; /* override desktop columns */
                grid-auto-columns: 100%;
                gap: 16px; 
                overflow-x: auto; 
                padding-bottom: 8px; 
                -webkit-overflow-scrolling: touch;
                scroll-snap-type: x mandatory;
            }
            .categories-section .category-card { scroll-snap-align: center; scroll-snap-stop: always; }
            
            .cookie-content {
                flex-direction: column;
                text-align: center;
            }
            
            .top-banner-content {
                flex-direction: column;
                gap: 10px;
            }
            
            .banner-text {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <!-- Top Banner -->
    <div class="top-banner">
        <div class="top-banner-content">
            <span class="banner-text">🎓 Check Today's 30+ Free Courses!</span>
            <a href="https://t.me/udemyzap" class="telegram-btn" target="_blank">
                Join Telegram Channel
            </a>
        </div>
    </div>
    
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div style="display:flex; align-items:center; gap:8px;">
                <div class="logo">QuickTrends.in</div>
                <div class="tagline">Curated Free Udemy Coupons and Courses</div>
            </div>
            <button class="hamburger" id="hamburger" aria-label="Open Menu"><span></span></button>
        </div>
    </header>

    <!-- Mobile Menu Drawer -->
    <div class="mobile-menu" id="mobileMenu" aria-hidden="true">
        <nav class="mobile-drawer">
            <a class="mobile-link <?= $curr==='index.php' ? 'active' : '' ?>" href="index.php">Home</a>
            <a class="mobile-link <?= $curr==='courses.php' ? 'active' : '' ?>" href="courses.php">All Free Courses</a>
            <a class="mobile-link <?= $curr==='blog.php' ? 'active' : '' ?>" href="blog.php">Categories</a>
            <a class="mobile-link <?= $curr==='about.php' ? 'active' : '' ?>" href="about.php">About</a>
            <a class="mobile-link <?= $curr==='contact.php' ? 'active' : '' ?>" href="contact.php">Contact</a>
        </nav>
    </div>

    
    
    <!-- Left Side Ad -->
    <?php $ad_left = getAadsAdCode('300x600', 4); if (!empty($ad_left)): ?>
    <div class="side-ad-left">
        <div class="ad-label">Advertisement</div>
        <div class="ad-placeholder" style="min-height: 600px;">
            <?= $ad_left ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Right Side Ad -->
    <?php $ad_right = getAadsAdCode('300x600', 5); if (!empty($ad_right)): ?>
    <div class="side-ad-right">
        <div class="ad-label">Advertisement</div>
        <div class="ad-placeholder" style="min-height: 600px;">
            <?= $ad_right ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="container">
        <!-- Top Ad Banner -->
        <?php $ad_top = getAadsAdCode('728x90', 1); if (!empty($ad_top)): ?>
        <div class="top-ad-banner">
            <div class="ad-label">Advertisement</div>
            <div style="min-height: 90px; background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 14px;">
                <?= $ad_top ?>
            </div>
        </div>
        <?php endif; ?>

        <?php 
            $desc = trim((string)($current_course['description'] ?? ''));
            $learn = qt_to_list($current_course['learn'] ?? ($current_course['what_you_will_learn'] ?? ($current_course['objectives'] ?? '')));
            $reqs = qt_to_list($current_course['requirements'] ?? '');
            $aud  = qt_to_list($current_course['audience'] ?? ($current_course['who_is_for'] ?? ''));
            $hasTabs = ($desc !== '') || !empty($learn) || !empty($reqs) || !empty($aud);

            // Subtitle: prefer explicit, else first sentence of description
            $subtitle = trim((string)($current_course['subtitle'] ?? ''));
            if ($subtitle === '' && $desc !== '') {
                $firstSentence = preg_split('/[.!?]\s+/', $desc)[0] ?? '';
                $subtitle = substr($firstSentence, 0, 160);
            }

            // Prepare similar/top row and bottom related without duplicates
            $curSlug = qt_slug_from_url($current_course['url'] ?? $target_url);
            $seen = [$curSlug => true];
            $top_similar = [];
            $bottom_related = [];
            foreach ($related_all as $rc) {
                $s = qt_slug_from_url($rc['url'] ?? '');
                if ($s === '' || isset($seen[$s])) continue;
                $seen[$s] = true;
                if (count($top_similar) < 6) $top_similar[] = $rc; else if (count($bottom_related) < 5) $bottom_related[] = $rc;
            }
            // Fallback: if either section is empty, backfill with general high‑quality related
            if (empty($top_similar) || empty($bottom_related)) {
                $fallback = qt_get_related_courses($target_url, 12);
                foreach ($fallback as $fc) {
                    $s = qt_slug_from_url($fc['url'] ?? '');
                    if ($s === '' || isset($seen[$s])) continue;
                    $seen[$s] = true;
                    if (count($top_similar) < 6) { $top_similar[] = $fc; continue; }
                    if (count($bottom_related) < 5) { $bottom_related[] = $fc; }
                    if (count($top_similar) >= 6 && count($bottom_related) >= 5) break;
                }
            }
        ?>

        
        
        <!-- Hero (Udemy-like, dark) -->
        <div class="hero-dark">
            <div class="hero-grid">
                <div>
                    <div class="hero-badges">
                        <span class="chip"><?= htmlspecialchars($current_course['category'] ?? 'Course') ?></span>
                        <span class="chip"><?= htmlspecialchars($course_info['platform']) ?></span>
                        <span class="chip">Free Coupon</span>
                    </div>
                    <div class="hero-title"><?= htmlspecialchars($course_info['title']) ?></div>
                    <?php if (!empty($subtitle)): ?>
                    <div class="hero-sub"><?= htmlspecialchars($subtitle) ?></div>
                    <?php endif; ?>
                </div>
                <aside>
                    <div class="summary-card hero-summary">
                        <?php if (!empty($current_course['image'])): ?>
                        <img src="<?= htmlspecialchars($current_course['image']) ?>" alt="<?= htmlspecialchars($course_info['title']) ?>" class="hero-image-cover">
                        <?php endif; ?>
                        <ul class="summary-list">
                            <?php if (!empty($current_course['rating'])): ?><li>⭐ <?= number_format((float)$current_course['rating'], 1) ?> average rating</li><?php endif; ?>
                            <?php if (!empty($current_course['students'])): ?><li>👥 <?= number_format((float)$current_course['students']) ?> students</li><?php endif; ?>
                            <?php if (!empty($current_course['level'])): ?><li>🧩 Level: <?= htmlspecialchars($current_course['level']) ?></li><?php endif; ?>
                            <?php if (!empty($current_course['language'])): ?><li>🗣️ Language: <?= htmlspecialchars($current_course['language']) ?></li><?php endif; ?>
                            <?php if (!empty($current_course['duration'])): ?><li>⏱️ Duration: <?= htmlspecialchars($current_course['duration']) ?></li><?php endif; ?>
                            <?php if (!empty($current_course['lectures'])): ?><li>🎯 Lectures: <?= htmlspecialchars($current_course['lectures']) ?></li><?php endif; ?>
                        </ul>
                        <a href="courses.php" class="summary-cta secondary">Browse All Free Courses</a>
                    </div>
                </aside>
            </div>
        </div>

        <?php if (!empty($top_similar)): ?>
        <div class="similar-section">
            <div class="section-title" style="text-align:left; margin-bottom:16px;">Similar coupons</div>
            <div class="similar-row" id="simrow">
                <?php foreach ($top_similar as $course): ?>
                <a class="similar-card" href="go.php?u=<?= urlencode($course['url']) ?>">
                    <img class="similar-thumb" src="<?= htmlspecialchars($course['image']) ?>" alt="<?= htmlspecialchars($course['title']) ?>">
                    <div class="similar-title"><?= htmlspecialchars($course['title']) ?></div>
                    <div class="similar-meta">⭐ <?= number_format((float)($course['rating'] ?? 0), 1) ?> · 👥 <?= htmlspecialchars($course['students'] ?? '—') ?></div>
                </a>
                <?php endforeach; ?>
            </div>
            <button class="scroll-next" data-target="#simrow" aria-label="Next">›</button>
        </div>
        <?php endif; ?>

        <!-- Course Details -->
        <div class="course-main">
                <?php if ($hasTabs): ?>
                <div class="details-tabs">
                    <div class="tab-buttons">
                        <?php if ($desc !== ''): ?><button class="tab-btn active" data-tab="#tab-desc">Description</button><?php endif; ?>
                        <?php if (!empty($learn)): ?><button class="tab-btn <?= $desc === '' ? 'active' : '' ?>" data-tab="#tab-learn">What you'll learn</button><?php endif; ?>
                        <?php if (!empty($reqs)): ?><button class="tab-btn" data-tab="#tab-reqs">Requirements</button><?php endif; ?>
                        <?php if (!empty($aud)): ?><button class="tab-btn" data-tab="#tab-aud">Audience</button><?php endif; ?>
                    </div>
                    <?php if ($desc !== ''): ?>
                    <div id="tab-desc" class="tab-panel active">
                        <div class="panel-title">Course Description</div>
                        <div id="desc-content" class="panel-text clamped"><?= nl2br(htmlspecialchars($desc)) ?></div>
                        <button id="toggle-desc" class="readmore-btn" type="button">Read more</button>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($learn)): ?>
                    <div id="tab-learn" class="tab-panel <?= $desc === '' ? 'active' : '' ?>">
                        <div class="panel-title">What you'll learn</div>
                        <ul class="panel-list">
                            <?php foreach ($learn as $li): ?><li><?= htmlspecialchars($li) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($reqs)): ?>
                    <div id="tab-reqs" class="tab-panel">
                        <div class="panel-title">Requirements</div>
                        <ul class="panel-list">
                            <?php foreach ($reqs as $li): ?><li><?= htmlspecialchars($li) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($aud)): ?>
                    <div id="tab-aud" class="tab-panel">
                        <div class="panel-title">Who this course is for</div>
                        <ul class="panel-list">
                            <?php foreach ($aud as $li): ?><li><?= htmlspecialchars($li) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                    <div class="panel-text" style="color:#64748b;">Course details will appear here when available.</div>
                <?php endif; ?>
        </div>

        

        

        <!-- Course Categories Navigation -->
        <div class="categories-section">
            <h2 class="section-title">📚 Explore More Free Courses</h2>
            
            <?php
            // Define all categories
            $categories = [
                ['id' => 1, 'icon' => '💻', 'title' => 'Programming', 'desc' => 'Python, JavaScript, Java & More', 'count' => '50+ Courses'],
                ['id' => 2, 'icon' => '🌐', 'title' => 'Web Development', 'desc' => 'HTML, CSS, React, Node.js', 'count' => '40+ Courses'],
                ['id' => 3, 'icon' => '📊', 'title' => 'Data Science', 'desc' => 'Machine Learning, Analytics', 'count' => '35+ Courses'],
                ['id' => 4, 'icon' => '🎨', 'title' => 'Design', 'desc' => 'UI/UX, Graphic Design', 'count' => '25+ Courses'],
                ['id' => 5, 'icon' => '📈', 'title' => 'Digital Marketing', 'desc' => 'SEO, Social Media, Ads', 'count' => '30+ Courses'],
                ['id' => 10, 'icon' => '🔥', 'title' => 'All Courses', 'desc' => 'Browse Complete Collection', 'count' => '200+ Courses', 'featured' => true]
            ];
            
            // Define ad boxes
            $ads = [
                ['label' => '💡 Learning Tools', 'code' => 2],
                ['label' => '🚀 Career Resources', 'code' => 3]
            ];
            
            // Create array with categories and ads
            $items = $categories;
            
            // Randomly insert ads at different positions
            $ad_positions = [rand(2, 3), rand(5, 7)]; // Random positions for ads
            sort($ad_positions); // Sort to insert in correct order
            
            foreach ($ad_positions as $index => $pos) {
                array_splice($items, $pos + $index, 0, [['type' => 'ad', 'data' => $ads[$index]]]);
            }
            
            // Display items in rows of 4
            $chunks = array_chunk($items, 4);
            foreach ($chunks as $i => $chunk): ?>
            <div class="categories-row" id="catrow<?= $i ?>">
                <?php foreach ($chunk as $item): ?>
                    <?php if (isset($item['type']) && $item['type'] === 'ad'): ?>
                        <?php $ad_box = getAadsAdCode('300x250', $item['data']['code']); if (!empty($ad_box)): ?>
                        <!-- Ad Box -->
                        <div class="category-ad-box">
                            <div class="ad-label"><?= $item['data']['label'] ?></div>
                            <div class="ad-placeholder" style="min-height: 200px;">
                                <?= $ad_box ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Category Card -->
                        <a href="blog.php?id=<?= $item['id'] ?>" class="category-card <?= isset($item['featured']) ? 'featured' : '' ?>">
                            <div class="category-icon"><?= $item['icon'] ?></div>
                            <h3><?= $item['title'] ?></h3>
                            <p><?= $item['desc'] ?></p>
                            <span class="category-count"><?= $item['count'] ?></span>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <button class="scroll-next" data-target="#catrow<?= $i ?>" aria-label="Next">›</button>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Related Courses Section -->
        <?php if (!empty($bottom_related)): ?>
        <div class="related-section" style="position:relative;">
            <h2 class="section-title">🔥 Students Also Enrolled In</h2>
            <div class="courses-grid" id="relrow">
                <?php foreach ($bottom_related as $course): ?>
                <div class="course-card">
                    <img src="<?= htmlspecialchars($course['image']) ?>" alt="<?= htmlspecialchars($course['title']) ?>" class="course-image">
                    <div class="course-content">
                        <h3 class="course-card-title"><?= htmlspecialchars($course['title']) ?></h3>
                        <div class="course-stats">
                            <div class="rating">
                                <span>⭐</span>
                                <span><?= number_format((float)($course['rating'] ?? 0), 1) ?></span>
                            </div>
                            <span>👥 <?= htmlspecialchars($course['students'] ?? '—') ?></span>
                            <span>🕒 <?= htmlspecialchars($course['duration'] ?? '') ?></span>
                        </div>
                        <a href="go.php?u=<?= urlencode($course['url']) ?>" class="course-btn">
                            Get Free Access
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Telegram Channel Promo -->
        <div class="tele-promo">
            <div>
                <div class="tele-title">Join our Telegram: <span style="font-weight:900">@udemyzap</span></div>
                <div class="tele-sub">Get instant alerts for new 100% FREE Udemy coupons and deals.</div>
            </div>
            <a href="https://t.me/udemyzap" target="_blank" rel="noopener" class="tele-btn">Join Channel</a>
        </div>

        <!-- Final Continue Button -->
        <div class="final-continue-section">
            <div class="continue-content">
                <h2>Ready to Access Your Course?</h2>
                <p>Everything is verified and ready. Click below to proceed to the final step.</p>
                <a href="step2.php?u=<?= urlencode($target_url) ?>" class="final-continue-btn">
                    Continue to Course Access →
                </a>
                <div style="margin-top:14px;">
                    <a href="courses.php" class="btn-secondary" style="text-decoration:none; padding:10px 16px; border-radius:8px; border:1px solid #cbd5e1; background:#e2e8f0; color:#334155; font-weight:700;">See All Free Courses</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cookie Consent -->
    <div class="cookie-consent" id="cookieConsent">
        <div class="cookie-content">
            <div class="cookie-text">
                🍪 We use cookies to enhance your experience, analyze site traffic, and personalize content. By continuing to browse, you agree to our use of cookies.
            </div>
            <div class="cookie-buttons">
                <button class="cookie-btn cookie-accept" onclick="acceptCookies()">Accept All</button>
                <button class="cookie-btn cookie-decline" onclick="declineCookies()">Decline</button>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-links">
                <a href="index.php">Home</a>
                <a href="blog.php">Blog</a>
                <a href="about.php">About</a>
                <a href="privacy.php">Privacy Policy</a>
                <a href="dmca.php">DMCA</a>
                <a href="contact.php">Contact</a>
            </div>
            <p>&copy; <?= date('Y') ?> QuickTrends.in - Supporting Free Education Worldwide</p>
            <p style="margin-top: 10px; opacity: 0.7; font-size: 14px;">
                Connecting learners with quality educational content since 2025.
            </p>
        </div>
    </footer>
    
    <script>
        // Cookie consent management
        function showCookieConsent() {
            if (!localStorage.getItem('cookieConsent')) {
                setTimeout(() => {
                    document.getElementById('cookieConsent').classList.add('show');
                }, 2000);
            }
        }
        
        function acceptCookies() {
            localStorage.setItem('cookieConsent', 'accepted');
            document.getElementById('cookieConsent').classList.remove('show');
            
            // Initialize tracking
            initializeTracking();
        }
        
        function declineCookies() {
            localStorage.setItem('cookieConsent', 'declined');
            document.getElementById('cookieConsent').classList.remove('show');
        }
        
        function initializeTracking() {
            // Google Analytics (if accepted)
            if (typeof gtag !== 'undefined') {
                gtag('consent', 'update', {
                    'analytics_storage': 'granted'
                });
            }
            
            // Track user engagement
            trackUserEngagement();
        }
        
        function trackUserEngagement() {
            let startTime = Date.now();
            let scrollDepth = 0;
            let maxScroll = 0;
            
            // Track scroll depth
            window.addEventListener('scroll', function() {
                const scrollTop = window.pageYOffset;
                const docHeight = document.body.scrollHeight - window.innerHeight;
                scrollDepth = Math.round((scrollTop / docHeight) * 100);
                maxScroll = Math.max(maxScroll, scrollDepth);
            });
            
            // Track time on page and engagement
            window.addEventListener('beforeunload', function() {
                const timeSpent = Math.round((Date.now() - startTime) / 1000);
                
                // Send engagement data
                if (navigator.sendBeacon) {
                    const data = new FormData();
                    data.append('action', 'track_engagement');
                    data.append('time_spent', timeSpent);
                    data.append('max_scroll', maxScroll);
                    data.append('url', window.location.href);
                    
                    navigator.sendBeacon('analytics.php', data);
                }
            });
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Tabs handler for course details
            const tabBtns = document.querySelectorAll('.tab-buttons .tab-btn');
            tabBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const target = this.getAttribute('data-tab');
                    // toggle active button
                    tabBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    // toggle panels
                    const panels = document.querySelectorAll('.details-tabs .tab-panel');
                    panels.forEach(p => p.classList.remove('active'));
                    const el = document.querySelector(target);
                    if (el) el.classList.add('active');
                });
            });
            showCookieConsent();
            
            // If cookies already accepted, initialize tracking
            if (localStorage.getItem('cookieConsent') === 'accepted') {
                initializeTracking();
            }

            // Mobile menu toggle
            const hamb = document.getElementById('hamburger');
            const menu = document.getElementById('mobileMenu');
            if (hamb && menu) {
                const toggleMenu = (open) => {
                    menu.classList.toggle('open', open);
                    menu.setAttribute('aria-hidden', open ? 'false' : 'true');
                    document.body.style.overflow = open ? 'hidden' : '';
                };
                hamb.addEventListener('click', () => toggleMenu(!menu.classList.contains('open')));
                menu.addEventListener('click', (e) => { if (e.target === menu) toggleMenu(false); });
                document.addEventListener('keydown', (e) => { if (e.key === 'Escape') toggleMenu(false); });
            }

            // Horizontal carousel next buttons
            document.querySelectorAll('.scroll-next').forEach(btn => {
                const target = btn.getAttribute('data-target');
                const el = target ? document.querySelector(target) : null;
                if (!el) return;
                btn.addEventListener('click', () => {
                    const delta = Math.max(240, Math.min(el.clientWidth * 0.9, 480));
                    el.scrollBy({ left: delta, behavior: 'smooth' });
                });
            });

            // Read more toggler for description
            const desc = document.getElementById('desc-content');
            const btn = document.getElementById('toggle-desc');
            if (desc && btn) {
                btn.addEventListener('click', () => {
                    const isClamped = desc.classList.contains('clamped');
                    desc.classList.toggle('clamped');
                    btn.textContent = isClamped ? 'Show less' : 'Read more';
                });
            }
        });
        
        // Enhanced ad visibility tracking
        function trackAdVisibility() {
            const ads = document.querySelectorAll('.top-ad-banner, .rectangle-ad');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        // Track ad impression
                        console.log('Ad viewed:', entry.target.className);
                    }
                });
            }, { threshold: 0.5 });
            
            ads.forEach(ad => observer.observe(ad));
        }
        
        // Initialize ad tracking
        document.addEventListener('DOMContentLoaded', trackAdVisibility);
    </script>
    
    <!-- Google Analytics (if needed) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=GA_MEASUREMENT_ID"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'GA_MEASUREMENT_ID', {
            'analytics_storage': 'denied' // Default denied, granted after consent
        });
    </script>
</body>
</html>