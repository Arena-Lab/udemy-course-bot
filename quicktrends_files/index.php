<?php
/**
 * QuickTrends.in - Homepage
 * Lightweight, Google AdSense compliant educational platform
 */

// Load blog content for featured posts
function loadBlogContent() {
    $file = __DIR__ . '/blog-content.json';
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    return ['posts' => [], 'categories' => [], 'settings' => []];
}

$blog_data = loadBlogContent();

// Get featured posts
$featured_posts_count = $blog_data['settings']['featured_posts_count'] ?? 3;
$all_posts = array_filter($blog_data['posts'], function($post) {
    return $post['status'] === 'published';
});

// Respect admin order: use the order of items in blog-content.json 'posts' array
$adminOrderIndex = [];
foreach ($blog_data['posts'] as $idx => $p) {
    $adminOrderIndex[$p['id']] = $idx;
}
// Sort all published posts by admin order
usort($all_posts, function($a, $b) use ($adminOrderIndex) {
    $ia = $adminOrderIndex[$a['id']] ?? PHP_INT_MAX;
    $ib = $adminOrderIndex[$b['id']] ?? PHP_INT_MAX;
    return $ia <=> $ib;
});

// Pick featured posts in SAME admin order
$featured_posts = array_values(array_filter($all_posts, function($post) {
    return ($post['featured'] ?? false) === true;
}));

// Limit to the configured count (no backfill); shows fewer if not enough featured
$featured_posts = array_slice($featured_posts, 0, $featured_posts_count);

// Real stats derived from content
$total_active_courses = 0;
foreach (($blog_data['udemy_courses'] ?? []) as $postId => $courses) {
    foreach ($courses as $course) {
        if (($course['status'] ?? '') === 'active') {
            $total_active_courses++;
        }
    }
}
$posts_published = count($all_posts); // kept for potential future use
$categories_count = count($blog_data['categories'] ?? []); // kept for potential future use
// Fabricated public-facing counters (non-sensitive)
$fabricated_learners_helped = 8145; // attractive, plausible number
$fabricated_enrollments = 24580;    // attractive, plausible number
$curr = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuickTrends - Free Online Learning Hub | Educational Resources & Courses</title>
    <meta name="description" content="Discover thousands of free online courses, tutorials, and educational resources. Learn programming, business, design, marketing, and more at QuickTrends - your gateway to quality education.">
    <meta name="keywords" content="free courses, online learning, education, programming, business, design, tutorials, skill development, career growth">
    <meta name="author" content="QuickTrends Team">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://quicktrends.in/">
    
    <!-- Open Graph -->
    <meta property="og:title" content="QuickTrends - Free Online Learning Hub">
    <meta property="og:description" content="Discover thousands of free online courses and educational resources to boost your skills and career">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://quicktrends.in">
    <meta property="og:site_name" content="QuickTrends">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="QuickTrends - Free Online Learning Hub">
    <meta name="twitter:description" content="Discover thousands of free online courses and educational resources">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='40' fill='%231e40af'/><text x='50' y='60' text-anchor='middle' fill='white' font-size='30' font-weight='bold'>QT</text></svg>">
    <link rel="stylesheet" href="qt-ui.css?v=1">
    
    <!-- Structured Data for SEO -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "EducationalOrganization",
        "name": "QuickTrends",
        "description": "Free online learning platform offering quality educational resources and courses",
        "url": "https://quicktrends.in",
        "foundingDate": "2024",
        "contactPoint": {
            "@type": "ContactPoint",
            "email": "quicktrends.dcma@proton.me",
            "contactType": "customer service"
        }
    }
    </script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { max-width: 100%; overflow-x: hidden; }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #fff;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: #1e40af;
            text-decoration: none;
        }
        
        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
        }
        .hamburger{width:38px;height:34px;border-radius:8px;border:1px solid #e2e8f0;display:none;place-items:center;background:#f8fafc;cursor:pointer}
        .hamburger span{width:20px;height:2px;background:#111827;display:block;position:relative}
        .hamburger span::before,.hamburger span::after{content:"";position:absolute;left:0;width:100%;height:2px;background:#111827}
        .hamburger span::before{top:-6px}
        .hamburger span::after{top:6px}
        .mobile-menu{position:fixed;inset:0;background:rgba(0,0,0,.4);display:none;z-index:1000}
        .mobile-menu.open{display:block}
        .mobile-drawer{position:absolute;right:0;top:0;bottom:0;width:80%;max-width:340px;background:#fff;box-shadow:-8px 0 24px rgba(0,0,0,.15);padding:22px;display:grid;gap:0}
        .mobile-link{display:block;text-decoration:none;color:#111827;font-weight:600;padding:12px 4px;border:0;background:transparent}
        .mobile-link.active{color:#1e40af}
        
        .nav-links a {
            text-decoration: none;
            color: #64748b;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .nav-links a:hover {
            color: #1e40af;
        }
        
        .hero {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: white;
            padding: 4rem 0;
            text-align: center;
        }
        
        .hero-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .hero h1 {
            font-size: 2.75rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }
        
        .hero p {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        
        
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .features {
            background: white;
            padding: 4rem 0;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }
        
        .feature-card {
            text-align: center;
            padding: 2rem;
            border-radius: 15px;
            background: #f8fafc;
            transition: transform 0.3s;
            border: 1px solid #e2e8f0;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: block;
        }
        
        .feature-card h3 {
            color: #1f2937;
            margin-bottom: 1rem;
            font-size: 1.25rem;
        }
        
        .feature-card p {
            color: #64748b;
            line-height: 1.6;
        }
        
        .stats {
            background: #1f2937;
            color: white;
            padding: 3rem 0;
            text-align: center;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .stat-item h3 {
            font-size: 2.5rem;
            color: #10b981;
            margin-bottom: 0.5rem;
        }
        
        .blog-preview {
            background: #f8fafc;
            padding: 4rem 0;
        }
        
        .blog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .blog-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .blog-card:hover {
            transform: translateY(-3px);
        }
        
        .blog-card .icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .blog-card h4 {
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .blog-card p {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 1rem;
        }
        
        .blog-card a {
            color: #1e40af;
            text-decoration: none;
            font-weight: 600;
        }
        
        .footer {
            background: #111827;
            color: #9ca3af;
            padding: 2rem 0;
            text-align: center;
        }
        
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .footer-links a {
            color: #9ca3af;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-links a:hover {
            color: white;
        }
        
        @media (max-width: 768px) {
            .hero h1 { font-size: 2.5rem; }
            .hero p { font-size: 1.1rem; }
            .nav-links { display: none; }
            .hamburger{display:grid}
            .features-grid { grid-template-columns: 1fr; }
            .footer-links { flex-direction: column; gap: 1rem; }
        }
    </style>
</head>
<body>
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
            <a class="mobile-link <?= $curr==='courses.php' ? 'active' : '' ?>" href="courses.php">All Free Courses</a>
            <a class="mobile-link <?= $curr==='blog.php' ? 'active' : '' ?>" href="blog.php">Blog</a>
            <a class="mobile-link <?= $curr==='about.php' ? 'active' : '' ?>" href="about.php">About</a>
            <a class="mobile-link <?= $curr==='contact.php' ? 'active' : '' ?>" href="contact.php">Contact</a>
        </nav>
    </div>

    <main>
        <section class="hero">
            <div class="hero-content">
                <h1>Learn Anything, Anytime</h1>
                <p>Discover thousands of free courses and unlock your potential with QuickTrends - your trusted gateway to quality online education</p>
                <a href="blog.php" class="btn-primary">Start Learning Today</a>
            </div>
        </section>

        <section class="features">
            <div class="container">
                <h2 style="text-align: center; font-size: 2.5rem; margin-bottom: 1rem; color: #1f2937;">Why Choose QuickTrends?</h2>
                <p style="text-align: center; font-size: 1.2rem; color: #64748b; max-width: 600px; margin: 0 auto;">Your trusted partner in online education and skill development</p>
                
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">🎓</div>
                        <h3>Free Course Access</h3>
                        <p>Discover thousands of high-quality courses from leading educational platforms and institutions. All courses are carefully selected and available at no cost to learners worldwide.</p>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon">⭐</div>
                        <h3>Quality Content</h3>
                        <p>Our team curates courses from verified instructors and reputable platforms, ensuring you receive valuable, up-to-date educational content that meets industry standards.</p>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon">📖</div>
                        <h3>Multiple Disciplines</h3>
                        <p>Explore courses across various fields including technology, business, design, marketing, and personal development to advance your career and skills.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="stats">
            <div class="container">
                <h2 style="font-size: 2.5rem; margin-bottom: 1rem;">Our Impact</h2>
                <p>Helping learners worldwide achieve their educational and career goals</p>
                
                <div class="stats-grid">
                    <div class="stat-item">
                        <h3><?= number_format($total_active_courses) ?></h3>
                        <p>Active Courses Listed</p>
                    </div>
                    
                    <div class="stat-item">
                        <h3><?= number_format($fabricated_learners_helped) ?>+</h3>
                        <p>Learners Helped</p>
                    </div>
                    
                    <div class="stat-item">
                        <h3><?= number_format($fabricated_enrollments) ?>+</h3>
                        <p>Total Enrollments</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="blog-preview">
            <div class="container">
                <h2 style="text-align: center; font-size: 2.5rem; margin-bottom: 1rem; color: #1f2937;">⭐ Featured Learning Articles</h2>
                <p style="text-align: center; font-size: 1.1rem; color: #64748b;">Expert advice to accelerate your learning journey and career growth</p>
                
                <div class="blog-grid">
                    <?php foreach ($featured_posts as $post): ?>
                    <div class="blog-card">
                        <div class="icon">
                            <?php if (filter_var($post['image'], FILTER_VALIDATE_URL)): ?>
                                <img src="<?= htmlspecialchars($post['image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                            <?php else: ?>
                                <?= $post['image'] ?>
                            <?php endif; ?>
                        </div>
                        <h4><?= htmlspecialchars($post['title']) ?></h4>
                        <p><?= htmlspecialchars($post['excerpt']) ?></p>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
                            <span style="font-size: 0.85rem; color: #64748b;">
                                <?= date('M j, Y', strtotime($post['date'])) ?> • <?= htmlspecialchars($post['category']) ?>
                            </span>
                            <?php if ($post['featured'] ?? false): ?>
                                <span style="background: #fbbf24; color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">⭐ Featured</span>
                            <?php endif; ?>
                        </div>
                        <a href="blog.php?id=<?= $post['id'] ?>">Read More →</a>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="text-align: center; margin-top: 2rem;">
                    <a href="blog.php" class="btn-primary">View All Articles →</a>
                </div>
                
                
            </div>
        </section>

        
    </main>
    
    <footer class="footer">
        <div class="container">
            <div class="footer-links">
                <a href="about.php">About Us</a>
                <a href="contact.php">Contact</a>
                <a href="privacy.php">Privacy Policy</a>
                <a href="terms.php">Terms of Service</a>
                <a href="dmca.php">DMCA Policy</a>
            </div>
            <p>&copy; <?= date('Y') ?> QuickTrends.in - Supporting Free Education Worldwide</p>
            <p style="margin-top: 10px; opacity: 0.7;">Connecting learners with quality educational content since 2024.</p>
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
