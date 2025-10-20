<?php
/**
 * QuickTrends.in - Educational Blog
 * Dynamic content management system
 */

// Load blog content from JSON database
function loadBlogContent() {
    $file = __DIR__ . '/blog-content.json';
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    return ['posts' => [], 'categories' => [], 'settings' => []];
}

$__qt_courses_feed_fn_added = true;
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

$blog_data = loadBlogContent();
$blog_posts = array_filter($blog_data['posts'], function($post) {
    return $post['status'] === 'published';
});

// Sort by admin-defined order (the order of items in blog-content.json 'posts')
$adminOrderIndex = [];
foreach ($blog_data['posts'] as $idx => $p) {
    $adminOrderIndex[$p['id']] = $idx;
}
usort($blog_posts, function($a, $b) use ($adminOrderIndex) {
    $ia = $adminOrderIndex[$a['id']] ?? PHP_INT_MAX;
    $ib = $adminOrderIndex[$b['id']] ?? PHP_INT_MAX;
    return $ia <=> $ib;
});

// Pagination setup
$posts_per_page = $blog_data['settings']['posts_per_page'] ?? 6;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$total_posts = count($blog_posts);
$total_pages = ceil($total_posts / $posts_per_page);
$offset = ($current_page - 1) * $posts_per_page;

$post_id = $_GET['id'] ?? null;
$current_post = null;

if ($post_id) {
    foreach ($blog_posts as $post) {
        if ($post['id'] == $post_id) {
            $current_post = $post;
            break;
        }
    }
}
$curr = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $current_post ? htmlspecialchars($current_post['title']) . ' - ' : '' ?>QuickTrends Blog</title>
    <meta name="description" content="<?= $current_post ? htmlspecialchars($current_post['excerpt']) : 'Educational blog with tips, guides, and insights for learners and professionals.' ?>">
    <link rel="stylesheet" href="qt-ui.css?v=1">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html, body { max-width: 100%; overflow-x: hidden; }
        
        .pagination-btn:hover {
            background: #e2e8f0 !important;
            border-color: #cbd5e1 !important;
            transform: translateY(-1px);
        }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f8fafc;
        }
        .header{background:#fff;box-shadow:0 2px 12px rgba(0,0,0,.08);position:sticky;top:0;z-index:50}
        .nav{max-width:1200px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;padding:14px 20px}
        .logo{font-weight:800;color:#1e40af;text-decoration:none}
        .nav-links{display:flex;gap:18px;list-style:none}
        .nav-links a{text-decoration:none;color:#475569}
        .hamburger{width:38px;height:34px;border-radius:8px;border:1px solid #e2e8f0;display:none;place-items:center;background:#f8fafc;cursor:pointer}
        .hamburger span{width:20px;height:2px;background:#111827;display:block;position:relative}
        .hamburger span:before,.hamburger span:after{content:"";position:absolute;left:0;width:100%;height:2px;background:#111827}
        .hamburger span:before{top:-6px}
        .hamburger span:after{top:6px}
        .mobile-menu{position:fixed;inset:0;background:rgba(0,0,0,.4);display:none;z-index:1000}
        .mobile-menu.open{display:block}
        .mobile-drawer{position:absolute;right:0;top:0;bottom:0;width:80%;max-width:340px;background:#fff;box-shadow:-8px 0 24px rgba(0,0,0,.15);padding:22px;display:grid;gap:0}
        .mobile-link{display:block;text-decoration:none;color:#111827;font-weight:700;padding:10px 0;border:0;background:transparent}
        .mobile-link.active{color:#1e40af}
        .blog-hero-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .blog-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }
        .posts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
        }
        .main-content {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        .ad-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        .ad-label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .post-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .post-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .post-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        .post-icon {
            font-size: 3rem;
        }
        .post-meta {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 15px;
        }
        .post-title {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: #1f2937;
        }
        .post-title a {
            color: inherit;
            text-decoration: none;
        }
        .post-title a:hover {
            color: #4f46e5;
        }
        .post-excerpt {
            color: #64748b;
            margin-bottom: 20px;
        }
        .read-more {
            display: inline-block;
            background: #1e40af;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }
        .read-more:hover {
            background: #1e3a8a;
            transform: translateY(-2px);
        }
        .post-content {
            font-size: 1.1rem;
            line-height: 1.8;
            margin: 30px 0;
        }
        .back-link {
            display: inline-block;
            color: #1e40af;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .related-posts {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .related-post {
            padding: 15px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .related-post:last-child {
            border-bottom: none;
        }
        .related-post h4 {
            font-size: 14px;
            margin-bottom: 5px;
        }
        .related-post h4 a {
            color: #1f2937;
            text-decoration: none;
        }
        .related-post h4 a:hover {
            color: #1e40af;
        }
        .related-post .meta {
            font-size: 12px;
            color: #64748b;
        }
        .featured-section {
            background: white;
            border-radius: 15px;
            padding: 40px;
            margin-bottom: 40px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        .cta-section {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            border-radius: 15px;
            padding: 40px;
            margin: 40px 0;
            text-align: center;
        }
        .cta-button {
            display: inline-block;
            background: white;
            color: #1e40af;
            padding: 15px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 20px;
            transition: transform 0.3s;
        }
        .cta-button:hover {
            transform: translateY(-2px);
        }
        @media (max-width: 768px) {
            .posts-container { grid-template-columns: 1fr; }
            .container { padding: 20px 15px; }
            .post-card { padding: 20px; }
            .featured-section { padding: 25px; }
            .nav-links { display: none; }
            .hamburger { display: grid; }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <header class="header">
        <nav class="nav">
            <a href="index.php" class="logo">🎓 QuickTrends</a>
            <ul class="nav-links">
                <li><a href="index.php" class="<?= $curr==='index.php' ? 'active' : '' ?>">Home</a></li>
                <li><a href="blog.php" class="<?= $curr==='blog.php' ? 'active' : '' ?>">Blog</a></li>
                <li><a href="courses.php" class="<?= $curr==='courses.php' ? 'active' : '' ?>">Courses</a></li>
                <li><a href="about.php" class="<?= $curr==='about.php' ? 'active' : '' ?>">About</a></li>
                <li><a href="contact.php" class="<?= $curr==='contact.php' ? 'active' : '' ?>">Contact</a></li>
            </ul>
            <button class="hamburger" id="hamburger" aria-label="Open Menu"><span></span></button>
        </nav>
    </header>
    <div class="mobile-menu" id="mobileMenu" aria-hidden="true">
        <nav class="mobile-drawer">
            <a class="mobile-link <?= $curr==='index.php' ? 'active' : '' ?>" href="index.php">Home</a>
            <a class="mobile-link <?= $curr==='blog.php' ? 'active' : '' ?>" href="blog.php">Blog</a>
            <a class="mobile-link <?= $curr==='courses.php' ? 'active' : '' ?>" href="courses.php">All Free Courses</a>
            <a class="mobile-link <?= $curr==='about.php' ? 'active' : '' ?>" href="about.php">About</a>
            <a class="mobile-link <?= $curr==='contact.php' ? 'active' : '' ?>" href="contact.php">Contact</a>
        </nav>
    </div>
    
    <!-- Blog Header -->
    <div style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%); color: white; padding: 60px 20px; text-align: center;">
        <h1 style="font-size: 2.75rem; margin-bottom: 15px;">📚 Learning Hub</h1>
        <p style="font-size: 1.1rem; opacity: 0.9;">Expert insights and comprehensive guides to accelerate your learning journey</p>
    </div>

    <!-- Latest Courses from Live Feed -->
    <?php 
        $feed = qt_load_courses_feed();
        $all = is_array($feed['courses'] ?? null) ? $feed['courses'] : [];
        usort($all, function($a,$b){
            $sb = strtotime((string)($b['scraped_at'] ?? '')) ?: 0;
            $sa = strtotime((string)($a['scraped_at'] ?? '')) ?: 0;
            return $sb <=> $sa;
        });
        $latest = array_slice($all, 0, 8);
    ?>
    <?php if (!empty($latest)): ?>
    <div class="container" style="padding-top: 0;">
        <div class="featured-section" style="text-align:left;">
            <h2 style="margin-bottom: 10px;">Latest Free Courses</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px;">
                <?php foreach ($latest as $c): ?>
                <a href="go.php?u=<?= urlencode($c['url']) ?>" style="text-decoration:none;color:inherit;">
                    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;transition:transform .2s,box-shadow .2s;">
                        <img src="<?= htmlspecialchars($c['image']) ?>" alt="<?= htmlspecialchars($c['title']) ?>" style="width:100%;height:150px;object-fit:cover;background:#f1f5f9;">
                        <div style="padding:12px;">
                            <?php $catTxt = trim((string)($c['category'] ?? '')); if ($catTxt !== ''): ?>
                                <div style="display:inline-block;background:#f1f5f9;border:1px solid #e5e7eb;color:#475569;font-size:12px;border-radius:6px;padding:2px 6px;margin-bottom:6px;"><?= htmlspecialchars($catTxt) ?></div>
                            <?php endif; ?>
                            <div style="font-weight:800;color:#0f172a;font-size:15px;line-height:1.35;min-height:42px;"><?= htmlspecialchars($c['title']) ?></div>
                            <div style="display:flex;justify-content:space-between;color:#64748b;font-size:12px;margin-top:6px;">
                                <span>⭐ <?= number_format((float)($c['rating'] ?? 0),1) ?></span>
                                <span>👥 <?= htmlspecialchars(number_format((float)($c['students'] ?? 0))) ?></span>
                            </div>
                            <div style="display:flex;justify-content:space-between;color:#64748b;font-size:12px;margin-top:4px;">
                                <span>🌎 <?= htmlspecialchars($c['language'] ?? '') ?></span>
                                <span>🕒 <?= htmlspecialchars($c['duration'] ?? '') ?></span>
                            </div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <div style="text-align:right;margin-top:15px;">
                <a href="courses.php" class="btn-primary">Browse All Courses →</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="container">
        <?php if ($current_post): ?>
            <!-- Single Post View -->
            <div class="main-content">
                <a href="blog.php" class="back-link">← Back to Blog</a>
                
                <div class="post-header">
                    <div class="post-icon"><?= $current_post['image'] ?></div>
                    <div>
                        <h1 class="post-title"><?= htmlspecialchars($current_post['title']) ?></h1>
                        <div class="post-meta">
                            <?= date('F j, Y', strtotime($current_post['date'])) ?> • 
                            <?= $current_post['category'] ?> • 
                            5 min read
                        </div>
                    </div>
                </div>
                
                <div class="post-content">
                    <?php if ($current_post['category'] == 'Programming' && strpos($current_post['title'], 'Python') !== false): // Python Learning Path ?>
                        <h2 style="color: #1e40af; margin-bottom: 20px;">🐍 Complete Python Learning Roadmap</h2>
                        <p style="font-size: 1.1rem; margin-bottom: 30px;"><strong>Python is the #1 programming language for beginners and professionals alike.</strong> This comprehensive roadmap will take you from complete beginner to job-ready Python developer in 6-12 months.</p>
                        
                        <div style="display: grid; gap: 25px; margin: 30px 0;">
                            <!-- Phase 1: Fundamentals -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #64748b; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">📋</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">Phase 1: Python Fundamentals</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">Weeks 1-4 • Foundation Building</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Week 1: Variables & Data Types</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Learn strings, numbers, lists, dictionaries</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Week 2: Control Flow</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">If statements, loops (for, while), break/continue</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Week 3: Functions</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Creating reusable code, parameters, return values</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Week 4: File Handling</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Reading/writing files, CSV processing</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Phase 2: Intermediate -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #374151; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">🔧</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">Phase 2: Intermediate Python</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">Weeks 5-8 • Skill Building</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Object-Oriented Programming</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Classes, objects, inheritance</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Error Handling</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Try/except blocks, debugging techniques</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Modules & Packages</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Importing libraries, creating your own modules</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Popular Libraries</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">requests, pandas, matplotlib, numpy</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Phase 3: Specialization -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #1f2937; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">🚀</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">Phase 3: Choose Your Specialization</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">Weeks 9-16 • Career Path</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                                    <div style="padding: 20px; background: white; border-radius: 6px; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center;">
                                        <div style="font-size: 2rem; margin-bottom: 10px;">🌐</div>
                                        <strong style="color: #374151; font-size: 1.1rem;">Web Development</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Django, Flask, FastAPI</span>
                                    </div>
                                    <div style="padding: 20px; background: white; border-radius: 6px; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center;">
                                        <div style="font-size: 2rem; margin-bottom: 10px;">📊</div>
                                        <strong style="color: #374151; font-size: 1.1rem;">Data Science</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Pandas, NumPy, Scikit-learn</span>
                                    </div>
                                    <div style="padding: 20px; background: white; border-radius: 6px; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center;">
                                        <div style="font-size: 2rem; margin-bottom: 10px;">🤖</div>
                                        <strong style="color: #374151; font-size: 1.1rem;">Automation</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Selenium, Beautiful Soup</span>
                                    </div>
                                    <div style="padding: 20px; background: white; border-radius: 6px; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center;">
                                        <div style="font-size: 2rem; margin-bottom: 10px;">🖥️</div>
                                        <strong style="color: #374151; font-size: 1.1rem;">Desktop Apps</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Tkinter, PyQt, Kivy</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Phase 4: Projects -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #1e40af; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">💼</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">Phase 4: Build Your Portfolio</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">Portfolio Projects • Get Job Ready</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 12px;">
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1e40af; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                            <span style="background: #1e40af; color: white; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 0.85rem;">1</span>
                                            <strong style="color: #374151; font-size: 1rem;">Web Scraper</strong>
                                        </div>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Extract data from websites using BeautifulSoup</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1e40af; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                            <span style="background: #1e40af; color: white; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 0.8rem;">2</span>
                                            <strong style="color: #374151; font-size: 0.95rem;">Data Analysis</strong>
                                        </div>
                                        <span style="color: #6b7280; font-size: 0.85rem;">Analyze datasets with pandas and visualizations</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1e40af; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                            <span style="background: #1e40af; color: white; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 0.8rem;">3</span>
                                            <strong style="color: #374151; font-size: 0.95rem;">Web Application</strong>
                                        </div>
                                        <span style="color: #6b7280; font-size: 0.85rem;">Build a full-stack web app with Flask/Django</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1e40af; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                                            <span style="background: #1e40af; color: white; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 0.8rem;">4</span>
                                            <strong style="color: #374151; font-size: 0.95rem;">API Project</strong>
                                        </div>
                                        <span style="color: #6b7280; font-size: 0.85rem;">Create REST APIs with authentication</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($current_post['category'] == 'Web Development'): // Web Development ?>
                        <h2 style="color: #1e40af; margin-bottom: 20px;">🌐 Complete Web Development Roadmap</h2>
                        <p style="font-size: 1.1rem; margin-bottom: 30px;"><strong>Web development is one of the most accessible and lucrative tech careers.</strong> Follow this structured path to become a full-stack developer.</p>
                        
                        <div style="display: grid; gap: 25px; margin: 30px 0;">
                            <!-- Frontend Development -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #64748b; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">🎨</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">Frontend Development</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">Months 1-3 • User Interface & Experience</p>
                                    </div>
                                </div>
                                
                                <div style="margin-bottom: 20px;">
                                    <h4 style="color: #374151; margin: 0 0 12px 0; font-size: 1.1rem;">📝 HTML & CSS (Weeks 1-4)</h4>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px; margin-bottom: 15px;">
                                        <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                            <strong style="color: #374151; font-size: 1rem;">HTML5:</strong> <span style="color: #6b7280; font-size: 0.9rem;">Semantic elements, forms, accessibility</span>
                                        </div>
                                        <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                            <strong style="color: #374151; font-size: 1rem;">CSS3:</strong> <span style="color: #6b7280; font-size: 0.9rem;">Flexbox, Grid, animations, responsive design</span>
                                        </div>
                                        <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                            <strong style="color: #374151; font-size: 1rem;">Tools:</strong> <span style="color: #6b7280; font-size: 0.9rem;">VS Code, browser dev tools, Git basics</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div style="margin-bottom: 20px;">
                                    <h4 style="color: #374151; margin: 0 0 12px 0; font-size: 1.1rem;">⚡ JavaScript (Weeks 5-8)</h4>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px; margin-bottom: 15px;">
                                        <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                            <strong style="color: #374151; font-size: 1rem;">Fundamentals:</strong> <span style="color: #6b7280; font-size: 0.9rem;">Variables, functions, arrays, objects</span>
                                        </div>
                                        <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                            <strong style="color: #374151; font-size: 1rem;">DOM Manipulation:</strong> <span style="color: #6b7280; font-size: 0.9rem;">Selecting elements, event handling</span>
                                        </div>
                                        <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                            <strong style="color: #374151; font-size: 1rem;">ES6+:</strong> <span style="color: #6b7280; font-size: 0.9rem;">Arrow functions, destructuring, modules</span>
                                        </div>
                                        <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                            <strong style="color: #374151; font-size: 1rem;">Async JavaScript:</strong> <span style="color: #6b7280; font-size: 0.9rem;">Promises, async/await, fetch API</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <h4 style="color: #374151; margin: 0 0 12px 0; font-size: 1.1rem;">⚛️ React Framework (Weeks 9-12)</h4>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">
                                        <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                            <strong style="color: #374151; font-size: 1rem;">React.js:</strong> <span style="color: #6b7280; font-size: 0.9rem;">Components, state, props, hooks</span>
                                        </div>
                                        <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                            <strong style="color: #374151; font-size: 1rem;">State Management:</strong> <span style="color: #6b7280; font-size: 0.9rem;">Context API, Redux (optional)</span>
                                        </div>
                                        <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                            <strong style="color: #374151; font-size: 1rem;">Routing:</strong> <span style="color: #6b7280; font-size: 0.9rem;">React Router for single-page apps</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Backend Development -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #374151; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">🔧</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">Backend Development</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">Months 4-6 • Server & Database</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Node.js & Express</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Server setup, routing, middleware</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Databases</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">MongoDB/PostgreSQL, database design</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">APIs</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">RESTful services, authentication, JWT</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Deployment</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Heroku, Netlify, AWS basics</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Portfolio Projects -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #1e40af; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">💼</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">Build Your Portfolio</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">Portfolio Projects • Get Job Ready</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 12px;">
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1e40af; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                            <span style="background: #1e40af; color: white; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 0.85rem;">1</span>
                                            <strong style="color: #374151; font-size: 1rem;">Personal Portfolio</strong>
                                        </div>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Showcase your skills with a professional website</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1e40af; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                            <span style="background: #1e40af; color: white; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 0.85rem;">2</span>
                                            <strong style="color: #374151; font-size: 1rem;">Todo App</strong>
                                        </div>
                                        <span style="color: #6b7280; font-size: 0.9rem;">CRUD operations with database integration</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1e40af; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                            <span style="background: #1e40af; color: white; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 0.85rem;">3</span>
                                            <strong style="color: #374151; font-size: 1rem;">E-commerce Site</strong>
                                        </div>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Shopping cart, payments, user authentication</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1e40af; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                            <span style="background: #1e40af; color: white; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 0.85rem;">4</span>
                                            <strong style="color: #374151; font-size: 1rem;">Social Media App</strong>
                                        </div>
                                        <span style="color: #6b7280; font-size: 0.9rem;">User authentication, posts, real-time features</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($current_post['id'] == 3): // Digital Marketing ?>
                        <h2 style="color: #1e40af; margin-bottom: 20px;">📊 Complete Digital Marketing Roadmap</h2>
                        <p style="font-size: 1.1rem; margin-bottom: 30px;"><strong>Digital marketing is essential for every business in 2025.</strong> Get certified for free and build a lucrative marketing career.</p>
                        
                        <div style="display: grid; gap: 25px; margin: 30px 0;">
                            <!-- Core Marketing Fundamentals -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #64748b; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">🎯</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">Core Marketing Fundamentals</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">Foundation • Essential Skills</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Marketing Strategy:</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Target audience, buyer personas, positioning</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Content Marketing:</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Blog writing, video content, storytelling</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Social Media:</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Platform strategies, content calendars, engagement</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Email Marketing:</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">List building, automation, segmentation</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Paid Advertising -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #374151; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">📈</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">Paid Advertising Mastery</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">Advanced • Revenue Generation</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Google Ads:</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Search, display, shopping campaigns</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Facebook Ads:</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Targeting, creative testing, optimization</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">LinkedIn Ads:</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">B2B marketing, lead generation</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Analytics:</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Conversion tracking, ROI measurement</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- SEO & Content Strategy -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #1f2937; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">🔍</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">SEO & Content Strategy</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">Organic Growth • Long-term Success</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1f2937; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">On-Page SEO:</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Keyword research, content optimization</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1f2937; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Technical SEO:</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Site speed, mobile optimization</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1f2937; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Link Building:</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Authority building, outreach strategies</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1f2937; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Local SEO:</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Google My Business, local citations</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Free Certifications -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #1e40af; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">🏆</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">Free Certifications to Get</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">Credentials • Career Advancement</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 12px;">
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1e40af; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                            <span style="background: #1e40af; color: white; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 0.85rem;">1</span>
                                            <strong style="color: #374151; font-size: 1rem;">Google Digital Marketing Certificate</strong>
                                        </div>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Comprehensive foundation in digital marketing</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1e40af; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                            <span style="background: #1e40af; color: white; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 0.85rem;">2</span>
                                            <strong style="color: #374151; font-size: 1rem;">HubSpot Content Marketing</strong>
                                        </div>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Content strategy expertise and best practices</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1e40af; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                            <span style="background: #1e40af; color: white; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 0.85rem;">3</span>
                                            <strong style="color: #374151; font-size: 1rem;">Facebook Blueprint</strong>
                                        </div>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Social media advertising mastery</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1e40af; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                            <span style="background: #1e40af; color: white; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 0.85rem;">4</span>
                                            <strong style="color: #374151; font-size: 1rem;">Google Analytics</strong>
                                        </div>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Data analysis and insights skills</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($current_post['category'] == 'DevOps'): // DevOps Learning Path ?>
                        <h2 style="color: #1e40af; margin-bottom: 20px;">🚀 Complete DevOps Learning Roadmap</h2>
                        <p style="font-size: 1.1rem; margin-bottom: 30px;"><strong>DevOps bridges the gap between development and operations.</strong> This comprehensive roadmap will guide you from beginner to expert DevOps engineer.</p>
                        
                        <div style="display: grid; gap: 25px; margin: 30px 0;">
                            <!-- Phase 1: Foundation -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #64748b; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">🐧</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">Phase 1: DevOps Fundamentals</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">Weeks 1-8 • Foundation Building</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Linux Administration</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Command line, file systems, permissions, processes</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Git Version Control</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Repositories, branching, merging, collaboration</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Networking Basics</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">TCP/IP, DNS, load balancing, firewalls</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Shell Scripting</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Bash scripting, automation, system tasks</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Phase 2: Core Tools -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #374151; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">🐳</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">Phase 2: Core DevOps Tools</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">Weeks 9-16 • Tool Mastery</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Docker Containerization</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Images, containers, Dockerfile, registries</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Kubernetes Orchestration</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Pods, services, deployments, scaling</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Infrastructure as Code</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Terraform, CloudFormation, resource management</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Configuration Management</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Ansible playbooks, automation, server config</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Phase 3: Advanced -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #1f2937; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">⚙️</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">Phase 3: Advanced DevOps</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">Weeks 17-24 • Production Ready</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1f2937; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">CI/CD Pipelines</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Jenkins, GitLab CI, automated testing, deployment</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1f2937; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Cloud Platforms</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">AWS, Azure, GCP services and best practices</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1f2937; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Monitoring & Logging</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Prometheus, Grafana, ELK stack, alerting</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1f2937; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Security & Compliance</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">DevSecOps, vulnerability scanning, compliance</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Phase 4: Career Paths -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #1e40af; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">🎯</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">Phase 4: Career Specialization</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">Choose Your Path • Expert Level</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                                    <div style="padding: 20px; background: white; border-radius: 6px; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center;">
                                        <div style="font-size: 2rem; margin-bottom: 10px;">☁️</div>
                                        <strong style="color: #374151; font-size: 1.1rem;">Cloud Engineer</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">AWS, Azure, GCP specialization</span>
                                    </div>
                                    <div style="padding: 20px; background: white; border-radius: 6px; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center;">
                                        <div style="font-size: 2rem; margin-bottom: 10px;">🔄</div>
                                        <strong style="color: #374151; font-size: 1.1rem;">Site Reliability Engineer</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">System reliability, performance</span>
                                    </div>
                                    <div style="padding: 20px; background: white; border-radius: 6px; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center;">
                                        <div style="font-size: 2rem; margin-bottom: 10px;">🔒</div>
                                        <strong style="color: #374151; font-size: 1.1rem;">DevSecOps Engineer</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Security integration, compliance</span>
                                    </div>
                                    <div style="padding: 20px; background: white; border-radius: 6px; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center;">
                                        <div style="font-size: 2rem; margin-bottom: 10px;">🏗️</div>
                                        <strong style="color: #374151; font-size: 1.1rem;">Platform Engineer</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Infrastructure platforms, tooling</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($current_post['category'] == 'Programming' && strpos($current_post['title'], 'Python') === false): // Programming Learning Path ?>
                        <h2 style="color: #1e40af; margin-bottom: 20px;">💻 Complete Programming Learning Roadmap</h2>
                        <p style="font-size: 1.1rem; margin-bottom: 30px;"><strong>Programming is the foundation of software development.</strong> This roadmap covers multiple programming languages to help you become a versatile developer.</p>
                        
                        <div style="display: grid; gap: 25px; margin: 30px 0;">
                            <!-- JavaScript -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #64748b; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">⚡</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">JavaScript - The Universal Language</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">8-12 weeks • Frontend & Backend</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Fundamentals</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Variables, functions, objects, DOM manipulation</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">ES6+ Features</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Arrow functions, async/await, modules</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">React Framework</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Components, state, hooks, routing</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Node.js Backend</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Express, APIs, database integration</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Java -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #374151; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">☕</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">Java - Enterprise Development</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">10-14 weeks • Enterprise Applications</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Core Java</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">OOP, collections, exception handling</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Spring Framework</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Dependency injection, Spring Boot</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Database Integration</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">JDBC, JPA, Hibernate</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Microservices</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Spring Cloud, REST APIs</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- C++ -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #1f2937; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">⚙️</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">C++ - System Programming</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">12-16 weeks • Performance Critical</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1f2937; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Core Concepts</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Pointers, memory management, OOP</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1f2937; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">STL & Templates</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Standard library, generic programming</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1f2937; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Advanced Topics</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Multithreading, design patterns</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1f2937; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Applications</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Game development, system programming</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- PHP -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #1e40af; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">🐘</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">PHP - Web Development</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">6-10 weeks • Server-Side Web</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1e40af; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">PHP Basics</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Syntax, forms, sessions, file handling</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1e40af; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Laravel Framework</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">MVC, Eloquent ORM, routing</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1e40af; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Database Integration</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">MySQL, migrations, relationships</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #1e40af; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">API Development</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">RESTful APIs, authentication</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($current_post['category'] == 'Data Science'): // Data Science ?>
                        <h2 style="color: #1e40af; margin-bottom: 20px;">📊 Complete Data Science Learning Roadmap</h2>
                        <p style="font-size: 1.1rem; margin-bottom: 30px;"><strong>Data Science is transforming every industry.</strong> This roadmap will guide you from beginner to expert data scientist.</p>
                        
                        <div style="display: grid; gap: 25px; margin: 30px 0;">
                            <!-- Phase 1: Foundations -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #64748b; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">📈</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">Phase 1: Data Science Foundations</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">Weeks 1-8 • Math & Programming</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Python Programming</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">NumPy, Pandas, data manipulation</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Statistics & Math</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Descriptive stats, probability, linear algebra</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Data Visualization</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Matplotlib, Seaborn, Plotly</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">SQL & Databases</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Query writing, data extraction</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Phase 2: Machine Learning -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #374151; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">🤖</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">Phase 2: Machine Learning</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">Weeks 9-16 • ML Algorithms</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Supervised Learning</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Regression, classification, decision trees</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Unsupervised Learning</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Clustering, dimensionality reduction</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Model Evaluation</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Cross-validation, metrics, tuning</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Scikit-learn</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">ML library, pipelines, preprocessing</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($current_post['category'] == 'Design'): // UI/UX Design ?>
                        <h2 style="color: #1e40af; margin-bottom: 20px;">🎨 Complete UI/UX Design Learning Roadmap</h2>
                        <p style="font-size: 1.1rem; margin-bottom: 30px;"><strong>UI/UX Design shapes digital experiences.</strong> This roadmap will guide you from beginner to expert designer.</p>
                        
                        <div style="display: grid; gap: 25px; margin: 30px 0;">
                            <!-- Phase 1: Design Fundamentals -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #64748b; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">🎯</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">Phase 1: Design Fundamentals</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">Weeks 1-6 • Design Principles</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Design Principles</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Color theory, typography, layout</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">User Research</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Personas, user interviews, surveys</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Wireframing</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Low-fi sketches, information architecture</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Design Tools</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Figma, Adobe XD, Sketch basics</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Phase 2: UI Design -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #374151; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">🖼️</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">Phase 2: UI Design Mastery</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">Weeks 7-12 • Visual Design</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Visual Design</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">High-fidelity mockups, style guides</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Component Systems</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Design systems, reusable components</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Responsive Design</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Mobile-first, breakpoints, grids</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Prototyping</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Interactive prototypes, animations</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($current_post['category'] == 'Cybersecurity'): // Cybersecurity ?>
                        <h2 style="color: #1e40af; margin-bottom: 20px;">🔒 Complete Cybersecurity Learning Roadmap</h2>
                        <p style="font-size: 1.1rem; margin-bottom: 30px;"><strong>Cybersecurity protects our digital world.</strong> This roadmap will guide you from beginner to expert security professional.</p>
                        
                        <div style="display: grid; gap: 25px; margin: 30px 0;">
                            <!-- Phase 1: Security Fundamentals -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #64748b; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">🛡️</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">Phase 1: Security Fundamentals</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">Weeks 1-8 • Core Concepts</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Network Security</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Firewalls, VPNs, network protocols</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Cryptography</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Encryption, hashing, digital signatures</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Risk Assessment</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Threat modeling, vulnerability analysis</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Security Policies</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Compliance, governance, frameworks</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Phase 2: Ethical Hacking -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #374151; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">🎯</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">Phase 2: Ethical Hacking</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">Weeks 9-16 • Penetration Testing</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Penetration Testing</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Methodology, tools, reporting</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Web Application Security</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">OWASP Top 10, SQL injection, XSS</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Digital Forensics</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Evidence collection, analysis tools</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Security Tools</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Kali Linux, Metasploit, Nmap</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($current_post['category'] == 'Digital Marketing'): // Digital Marketing ?>
                        <h2 style="color: #1e40af; margin-bottom: 20px;">📈 Complete Digital Marketing Learning Roadmap</h2>
                        <p style="font-size: 1.1rem; margin-bottom: 30px;"><strong>Digital marketing is essential for modern business success.</strong> This roadmap will guide you from beginner to expert marketer.</p>
                        
                        <div style="display: grid; gap: 25px; margin: 30px 0;">
                            <!-- Phase 1: Marketing Fundamentals -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #64748b; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">🎯</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">Phase 1: Marketing Fundamentals</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">Foundation • Essential Skills</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Marketing Strategy</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Target audience, buyer personas, positioning</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Content Marketing</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Blog writing, video content, storytelling</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Social Media</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Platform strategies, content calendars, engagement</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #64748b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Email Marketing</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">List building, automation, segmentation</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Phase 2: Paid Advertising -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 25px; border-radius: 8px;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #374151; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">📈</span>
                                    <div>
                                        <h3 style="color: #1f2937; margin: 0; font-size: 1.3rem;">Phase 2: Paid Advertising</h3>
                                        <p style="color: #64748b; margin: 3px 0 0 0; font-size: 0.9rem;">Advanced • Revenue Generation</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;">
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Google Ads</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Search, display, shopping campaigns</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Facebook Ads</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Targeting, creative testing, optimization</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">SEO & Analytics</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">Organic growth, Google Analytics</span>
                                    </div>
                                    <div style="padding: 15px; background: white; border-radius: 6px; border-left: 3px solid #374151; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                        <strong style="color: #374151; font-size: 1rem;">Conversion Optimization</strong><br>
                                        <span style="color: #6b7280; font-size: 0.9rem;">A/B testing, landing pages, funnels</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    <?php elseif ($current_post['category'] == 'Courses'): // All Courses Category ?>
                        <h2 style="color: #1e40af; margin-bottom: 20px;">🎓 All Free Udemy Courses</h2>
                        <p style="font-size: 1.1rem; margin-bottom: 30px;"><strong>Browse all free Udemy courses from every category.</strong> Updated daily with new course offers - enroll quickly as they expire!</p>
                        
                        <?php 
                        // Live courses from courses.json feed
                        $feed = qt_load_courses_feed();
                        $all_courses = is_array($feed['courses'] ?? null) ? $feed['courses'] : [];
                        // Newest first
                        usort($all_courses, function($a,$b){
                            $sb = strtotime((string)($b['scraped_at'] ?? '')) ?: 0;
                            $sa = strtotime((string)($a['scraped_at'] ?? '')) ?: 0;
                            return $sb <=> $sa;
                        });
                        // Pagination
                        $courses_per_page = 12; // Show more courses per page
                        $course_page = max(1, (int)($_GET['course_page'] ?? 1));
                        $total_courses = count($all_courses);
                        $total_course_pages = (int)ceil($total_courses / max(1,$courses_per_page));
                        $course_offset = ($course_page - 1) * $courses_per_page;
                        $display_courses = array_slice($all_courses, $course_offset, $courses_per_page);
                        ?>
                        
                        <?php if (!empty($display_courses)): ?>
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; margin: 30px 0;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                                    <span style="background: #1e40af; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">🎓</span>
                                    <div>
                                        <h3 style="margin: 0; color: #1f2937; font-size: 1.3rem;">Free Udemy Courses (<?= $total_courses ?> Available)</h3>
                                        <p style="margin: 3px 0 0 0; color: #64748b; font-size: 0.9rem;">Limited Time Offers • Enroll Quickly</p>
                                    </div>
                                </div>
                                
                                <div style="display: grid; gap: 12px;">
                                    <?php foreach ($display_courses as $course): ?>
                                        <div style="background: white; padding: 18px; border-radius: 6px; border-left: 3px solid #1e40af; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; gap: 15px; align-items: center;">
                                            <img src="<?= htmlspecialchars($course['image']) ?>" alt="<?= htmlspecialchars($course['title']) ?>" style="width: 80px; height: 60px; object-fit: cover; border-radius: 4px; flex-shrink: 0;">
                                            <div style="flex: 1;">
                                                <h4 style="margin-bottom: 8px;">
                                                    <a href="go.php?u=<?= htmlspecialchars($course['url']) ?>" target="_blank" style="color: #374151; text-decoration: none; font-size: 1rem;">
                                                        <?= htmlspecialchars($course['title']) ?>
                                                    </a>
                                                </h4>
                                                <p style="color: #6b7280; font-size: 0.9rem; margin-bottom: 8px;"><?= htmlspecialchars($course['description'] ?? '') ?></p>
                                                <span style="background: #e5e7eb; color: #374151; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem;">
                                                    ⭐ <?= number_format((float)$course['rating'], 1) ?> Rating | 🕒 <?= htmlspecialchars($course['duration']) ?> | 👥 <?= htmlspecialchars($course['students']) ?> Students
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Course Pagination -->
                                <?php if ($total_course_pages > 1): ?>
                                    <div class="course-pagination" style="display: flex; justify-content: center; align-items: center; gap: 8px; margin: 20px 0; flex-wrap: wrap;">
                                        <?php if ($course_page > 1): ?>
                                            <a href="blog.php?id=<?= $current_post['id'] ?>&course_page=<?= $course_page - 1 ?>" style="padding: 8px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; text-decoration: none; color: #374151; font-size: 14px; transition: all 0.2s;">
                                                ← Prev
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php 
                                        $start_page = max(1, $course_page - 2);
                                        $end_page = min($total_course_pages, $course_page + 2);
                                        for ($i = $start_page; $i <= $end_page; $i++): 
                                        ?>
                                            <a href="blog.php?id=<?= $current_post['id'] ?>&course_page=<?= $i ?>" 
                                               style="padding: 8px 12px; background: <?= $i == $course_page ? '#1e40af' : '#f8fafc' ?>; 
                                                      border: 1px solid <?= $i == $course_page ? '#1e40af' : '#e2e8f0' ?>; 
                                                      border-radius: 4px; text-decoration: none; 
                                                      color: <?= $i == $course_page ? 'white' : '#374151' ?>; 
                                                      font-size: 14px; transition: all 0.2s;">
                                                <?= $i ?>
                                            </a>
                                        <?php endfor; ?>
                                        
                                        <?php if ($course_page < $total_course_pages): ?>
                                            <a href="blog.php?id=<?= $current_post['id'] ?>&course_page=<?= $course_page + 1 ?>" style="padding: 8px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; text-decoration: none; color: #374151; font-size: 14px; transition: all 0.2s;">
                                                Next →
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 40px; margin: 30px 0; text-align: center;">
                                <h3 style="color: #1f2937; margin-bottom: 10px;">🎓 Courses Coming Soon!</h3>
                                <p style="color: #6b7280; margin-bottom: 0;">We're adding new free courses daily. Check back soon for amazing learning opportunities!</p>
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <p><?= htmlspecialchars($current_post['content']) ?></p>
                        
                        <p>This comprehensive guide covers everything you need to know about this topic. Whether you're a beginner or looking to advance your skills, you'll find valuable insights and actionable tips.</p>
                        
                        <h3 style="margin: 30px 0 15px 0;">Key Takeaways:</h3>
                        <ul style="margin-left: 20px; margin-bottom: 20px;">
                            <li>Start with the fundamentals and build a strong foundation</li>
                            <li>Practice consistently and apply what you learn immediately</li>
                            <li>Join communities and connect with like-minded learners</li>
                            <li>Stay updated with industry trends and best practices</li>
                        </ul>
                        
                        <p>Remember, learning is a journey, not a destination. Take your time, be patient with yourself, and celebrate small wins along the way.</p>
                    <?php endif; ?>
                    
                    <!-- Free Resources Section -->
                    <?php if (isset($current_post['resources']) && $current_post['category'] != 'Courses'): ?>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; margin: 30px 0;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                            <span style="background: #1e40af; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">🎁</span>
                            <h3 style="margin: 0; color: #1f2937; font-size: 1.3rem;">Free Learning Resources</h3>
                        </div>
                        <div style="display: grid; gap: 12px;">
                            <?php foreach ($current_post['resources'] as $resource): ?>
                            <div style="background: white; padding: 15px; border-radius: 6px; border-left: 3px solid #1e40af; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong><a href="<?= $resource['url'] ?>" target="_blank" style="color: #374151; text-decoration: none; font-size: 1rem;"><?= $resource['name'] ?></a></strong>
                                        <span style="background: #e5e7eb; color: #374151; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem; margin-left: 10px;"><?= $resource['type'] ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Free Udemy Courses Section -->
                    <?php if ($current_post['category'] != 'Courses'): ?>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; margin: 30px 0;">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                            <span style="background: #1e40af; color: white; padding: 8px; border-radius: 6px; font-size: 1.2rem;">🎓</span>
                            <div>
                                <h3 style="margin: 0; color: #1f2937; font-size: 1.3rem;">Free Udemy Courses</h3>
                                <p style="margin: 3px 0 0 0; color: #64748b; font-size: 0.9rem;">Limited Time Offers • Enroll Quickly</p>
                            </div>
                        </div>
                        <p style="margin-bottom: 20px; color: #6b7280; font-size: 0.95rem;">These courses are sourced live from our bot feed.</p>
                        
                        <?php 
                        // Live courses feed filtered by current post category
                        $feed = qt_load_courses_feed();
                        $all = is_array($feed['courses'] ?? null) ? $feed['courses'] : [];
                        $post_cat = strtolower(trim((string)($current_post['category'] ?? '')));
                        $map = [
                            'programming' => 'development',
                            'web development' => 'development',
                            'data science' => 'data',
                            'cybersecurity' => 'security',
                            'digital marketing' => 'marketing',
                            'marketing' => 'marketing',
                            'design' => 'design',
                            'business' => 'business',
                            'devops' => 'it',
                        ];
                        $needle = $map[$post_cat] ?? $post_cat;
                        $filtered = array_values(array_filter($all, function($c) use ($needle) {
                            $cat = strtolower((string)($c['category'] ?? ''));
                            if ($needle === '' || $needle === 'courses') return true;
                            return ($cat === $needle) || (strpos($cat, $needle) !== false);
                        }));
                        usort($filtered, function($a,$b){
                            $sb = strtotime((string)($b['scraped_at'] ?? '')) ?: 0;
                            $sa = strtotime((string)($a['scraped_at'] ?? '')) ?: 0;
                            return $sb <=> $sa;
                        });
                        // Pagination
                        $courses_per_page = $blog_data['settings']['courses_per_page'] ?? 4;
                        $course_page = max(1, (int)($_GET['course_page'] ?? 1));
                        $total_courses = count($filtered);
                        $total_course_pages = (int)ceil($total_courses / max(1,$courses_per_page));
                        $course_offset = ($course_page - 1) * $courses_per_page;
                        $post_courses = array_slice($filtered, $course_offset, $courses_per_page);
                        
                        if (!empty($post_courses)): ?>
                            <div style="display: grid; gap: 12px;">
                                <?php foreach ($post_courses as $course): ?>
                                        <div style="background: white; padding: 18px; border-radius: 6px; border-left: 3px solid #1e40af; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; gap: 15px; align-items: center;">
                                            <img src="<?= htmlspecialchars($course['image']) ?>" alt="<?= htmlspecialchars($course['title']) ?>" style="width: 80px; height: 60px; object-fit: cover; border-radius: 4px; flex-shrink: 0;">
                                            <div style="flex: 1;">
                                                <h4 style="margin-bottom: 8px;">
                                                    <a href="go.php?u=<?= htmlspecialchars($course['url']) ?>" target="_blank" style="color: #374151; text-decoration: none; font-size: 1rem;">
                                                        <?= htmlspecialchars($course['title']) ?>
                                                    </a>
                                                </h4>
                                                <p style="color: #6b7280; font-size: 0.9rem; margin-bottom: 8px;"><?= htmlspecialchars($course['description']) ?></p>
                                                <span style="background: #e5e7eb; color: #374151; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem;">
                                                    ⭐ <?= number_format((float)$course['rating'], 1) ?> Rating | 🕒 <?= htmlspecialchars($course['duration']) ?> | 👥 <?= htmlspecialchars($course['students']) ?> Students
                                                </span>
                                            </div>
                                        </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Course Pagination -->
                            <?php if ($total_course_pages > 1): ?>
                                <div class="course-pagination" style="display: flex; justify-content: center; align-items: center; gap: 8px; margin: 20px 0; flex-wrap: wrap;">
                                    <?php if ($course_page > 1): ?>
                                        <a href="blog.php?id=<?= $current_post['id'] ?>&course_page=<?= $course_page - 1 ?>" style="padding: 8px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; text-decoration: none; color: #374151; font-size: 14px; transition: all 0.2s;">
                                            ← Prev
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $total_course_pages; $i++): ?>
                                        <?php if ($i == $course_page): ?>
                                            <span style="padding: 8px 12px; background: #1e40af; color: white; border-radius: 4px; font-size: 14px; font-weight: 600;">
                                                <?= $i ?>
                                            </span>
                                        <?php else: ?>
                                            <a href="blog.php?id=<?= $current_post['id'] ?>&course_page=<?= $i ?>" style="padding: 8px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; text-decoration: none; color: #374151; font-size: 14px; transition: all 0.2s;">
                                                <?= $i ?>
                                            </a>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    
                                    <?php if ($course_page < $total_course_pages): ?>
                                        <a href="blog.php?id=<?= $current_post['id'] ?>&course_page=<?= $course_page + 1 ?>" style="padding: 8px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; text-decoration: none; color: #374151; font-size: 14px; transition: all 0.2s;">
                                            Next →
                                        </a>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="text-align: center; color: #6b7280; font-size: 12px; margin-bottom: 15px;">
                                    Showing <?= $course_offset + 1 ?>-<?= min($course_offset + $courses_per_page, $total_courses) ?> of <?= $total_courses ?> courses
                                </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div style="text-align: center; padding: 20px; color: #6b7280;">
                                <p>No courses available for this topic yet. Check back soon!</p>
                            </div>
                        <?php endif; ?>
                        
                        <div style="text-align: center; margin-top: 20px; padding: 15px; background: #fef3c7; border-radius: 8px;">
                            <p style="color: #92400e; font-weight: 600; margin: 0;">⚡ These courses are free for a limited time only! Enroll now before the offer expires.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- CTA Section -->
                <?php if ($current_post['category'] != 'Courses'): ?>
                <div class="cta-section">
                    <h3 style="margin-bottom: 15px;">🚀 Ready to Start Learning?</h3>
                    <p style="margin-bottom: 20px;">
                        Discover thousands of free courses on this topic
                    </p>
                    <a href="courses.php" class="cta-button">
                        Explore Free Courses →
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
        <?php else: ?>
            <!-- Blog List View -->
            <div class="posts-container" style="margin-top: 40px;">
                <?php 
                // Get posts for current page
                $paginated_posts = array_slice($blog_posts, $offset, $posts_per_page);
                foreach ($paginated_posts as $post): ?>
                <div class="post-card">
                    <div class="post-header">
                        <div class="post-icon">
                            <?php if (filter_var($post['image'], FILTER_VALIDATE_URL)): ?>
                                <img src="<?= htmlspecialchars($post['image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 12px;">
                            <?php else: ?>
                                <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                                    <?= $post['image'] ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div style="flex: 1;">
                            <div class="post-meta">
                                <?= date('F j, Y', strtotime($post['date'])) ?> • 
                                <?= $post['category'] ?>
                            </div>
                            <h2 class="post-title">
                                <a href="blog.php?id=<?= $post['id'] ?>"><?= htmlspecialchars($post['title']) ?></a>
                            </h2>
                        </div>
                    </div>
                    
                    <p class="post-excerpt" style="flex: 1;"><?= htmlspecialchars($post['excerpt']) ?></p>
                    
                    <div style="margin-top: auto; padding-top: 20px;">
                        <a href="blog.php?id=<?= $post['id'] ?>" class="read-more">
                            Read Full Article →
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination" style="display: flex; justify-content: center; align-items: center; gap: 10px; margin: 40px 0; flex-wrap: wrap;">
                    <?php if ($current_page > 1): ?>
                        <a href="blog.php?page=<?= $current_page - 1 ?>" class="pagination-btn" style="padding: 10px 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #374151; font-weight: 500; transition: all 0.2s;">
                            ← Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    // Calculate page range to show
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    // Show first page if not in range
                    if ($start_page > 1): ?>
                        <a href="blog.php?page=1" class="pagination-btn" style="padding: 10px 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #374151; font-weight: 500; transition: all 0.2s;">1</a>
                        <?php if ($start_page > 2): ?>
                            <span style="color: #9ca3af; padding: 0 5px;">...</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <?php if ($i == $current_page): ?>
                            <span class="pagination-current" style="padding: 10px 15px; background: #1e40af; color: white; border-radius: 6px; font-weight: 600;">
                                <?= $i ?>
                            </span>
                        <?php else: ?>
                            <a href="blog.php?page=<?= $i ?>" class="pagination-btn" style="padding: 10px 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #374151; font-weight: 500; transition: all 0.2s;">
                                <?= $i ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php 
                    // Show last page if not in range
                    if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <span style="color: #9ca3af; padding: 0 5px;">...</span>
                        <?php endif; ?>
                        <a href="blog.php?page=<?= $total_pages ?>" class="pagination-btn" style="padding: 10px 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #374151; font-weight: 500; transition: all 0.2s;"><?= $total_pages ?></a>
                    <?php endif; ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="blog.php?page=<?= $current_page + 1 ?>" class="pagination-btn" style="padding: 10px 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #374151; font-weight: 500; transition: all 0.2s;">
                            Next →
                        </a>
                    <?php endif; ?>
                </div>
                
                <div style="text-align: center; color: #6b7280; font-size: 14px; margin-bottom: 20px;">
                    Showing <?= $offset + 1 ?>-<?= min($offset + $posts_per_page, $total_posts) ?> of <?= $total_posts ?> posts
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <footer style="background: #1f2937; color: white; padding: 40px 20px; text-align: center; margin-top: 60px;">
        <div style="max-width: 1200px; margin: 0 auto;">
            <p>&copy; 2025 QuickTrends.in - Supporting Free Education</p>
            <p style="margin-top: 15px; font-size: 14px;">
                <a href="index.php" style="color: #93c5fd; text-decoration: none; margin: 0 10px;">Home</a> |
                <a href="blog.php" style="color: #93c5fd; text-decoration: none; margin: 0 10px;">Blog</a> |
                <a href="dmca.php" style="color: #93c5fd; text-decoration: none; margin: 0 10px;">DMCA</a> |
                <a href="mailto:quicktrends.dcma@proton.me" style="color: #93c5fd; text-decoration: none; margin: 0 10px;">Contact</a>
            </p>
        </div>
    </footer>
    <script>
    (function(){
        const hamb=document.getElementById('hamburger');
        const menu=document.getElementById('mobileMenu');
        if(hamb&&menu){
            const toggle=(open)=>{menu.classList.toggle('open',open);menu.setAttribute('aria-hidden',open?'false':'true');document.body.style.overflow=open?'hidden':''};
            hamb.addEventListener('click',()=>toggle(!menu.classList.contains('open')));
            menu.addEventListener('click',e=>{if(e.target===menu)toggle(false)});
            document.addEventListener('keydown',e=>{if(e.key==='Escape')toggle(false)});
        }
    })();
    </script>
    </body>
    </html>
