<?php
require_once 'config.php';

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

$curr = basename($_SERVER['PHP_SELF']);

$feed = qt_load_courses_feed();
$courses = is_array($feed['courses'] ?? null) ? $feed['courses'] : [];

$q = trim($_GET['q'] ?? '');
$cat = trim($_GET['cat'] ?? '');
$sort = $_GET['sort'] ?? 'rating';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 24;

$categories = [];
foreach ($courses as $c) {
    $catName = trim((string)($c['category'] ?? ''));
    if ($catName !== '') $categories[$catName] = true;
}
ksort($categories);
$categories = array_keys($categories);

$filtered = array_values(array_filter($courses, function($c) use ($q, $cat) {
    if ($cat !== '' && strtolower(trim((string)($c['category'] ?? ''))) !== strtolower(trim($cat))) {
        return false;
    }
    if ($q !== '') {
        $t = strtolower((string)($c['title'] ?? ''));
        $u = strtolower((string)($c['url'] ?? ''));
        if (strpos($t, strtolower($q)) === false && strpos($u, strtolower($q)) === false) return false;
    }
    return true;
}));

usort($filtered, function($a, $b) use ($sort) {
    if ($sort === 'newest') {
        $sa = strtotime((string)($a['scraped_at'] ?? '')) ?: 0;
        $sb = strtotime((string)($b['scraped_at'] ?? '')) ?: 0;
        return $sb <=> $sa;
    }
    $ra = (float)($a['rating'] ?? 0);
    $rb = (float)($b['rating'] ?? 0);
    if ($rb == $ra) return 0;
    return ($rb <=> $ra);
});

$total = count($filtered);
$total_pages = max(1, (int)ceil($total / $per_page));
$offset = ($page - 1) * $per_page;
$paged = array_slice($filtered, $offset, $per_page);

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Courses - QuickTrends</title>
<meta name="description" content="Browse the latest free Udemy coupons by category with lightweight filters.">
<link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎓</text></svg>">
<link rel="stylesheet" href="qt-ui.css?v=1">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;line-height:1.6;color:#1f2937;background:#f8fafc}
.header{background:#fff;box-shadow:0 2px 12px rgba(0,0,0,.08);position:sticky;top:0;z-index:50}
.nav{max-width:1200px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;padding:14px 20px}
.logo{font-weight:800;color:#1e40af;text-decoration:none}
.nav-links{display:flex;gap:18px;list-style:none}
.nav-links a{text-decoration:none;color:#475569}
.container{max-width:1200px;margin:0 auto;padding:20px}
.toolbar{display:flex;gap:12px;align-items:center;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px;flex-wrap:wrap;margin-top:16px}
.select, .input, .button{border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px;background:#fff;color:#1f2937}
.button{background:#1e40af;color:#fff;font-weight:700;cursor:pointer}
.clear{background:#e2e8f0;color:#334155}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px;margin-top:18px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;transition:transform .2s,box-shadow .2s}
.card:hover{transform:translateY(-3px);box-shadow:0 12px 24px rgba(0,0,0,.08);border-color:#cbd5e1}
.thumb{width:100%;height:158px;object-fit:cover;background:#f1f5f9}
.card-body{padding:12px 14px 14px}
.category{display:inline-block;background:#f1f5f9;border:1px solid #e5e7eb;color:#475569;font-size:12px;border-radius:6px;padding:2px 6px;margin-bottom:6px}
.title{font-size:15px;font-weight:800;color:#0f172a;line-height:1.35;margin-bottom:6px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.subtitle{font-size:12px;color:#64748b;margin-bottom:8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.meta{display:flex;justify-content:space-between;color:#64748b;font-size:12px;margin-bottom:8px}
.meta small{opacity:.9}
.cta{display:block;text-align:center;background:#1e40af;color:#fff;text-decoration:none;border-radius:8px;padding:9px;font-weight:700;font-size:14px}
.pager{display:flex;gap:8px;justify-content:center;margin:20px 0}
.pager a{padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;color:#1f2937;text-decoration:none}
.pager .active{background:#1e40af;color:#fff;border-color:#1e40af}
@media(max-width:768px){.nav-links{display:none}}
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
  </nav>
</header>
<main class="container">
  <h1>Latest Free Udemy Coupons</h1>
  <form class="toolbar" method="get" action="courses.php">
    <select name="cat" class="select">
      <option value="">All Categories</option>
      <?php foreach ($categories as $c): $sel = ($c===$cat)?'selected':''; ?>
        <option value="<?= htmlspecialchars($c) ?>" <?= $sel ?>><?= htmlspecialchars($c) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="q" class="input" placeholder="Search title or URL" value="<?= htmlspecialchars($q) ?>">
    <select name="sort" class="select">
      <option value="rating" <?= $sort==='rating'?'selected':'' ?>>Top Rated</option>
      <option value="newest" <?= $sort==='newest'?'selected':'' ?>>Newest</option>
    </select>
    <button class="button" type="submit">Apply</button>
    <a class="button clear" href="courses.php">Clear</a>
  </form>

  <div class="grid">
    <?php foreach ($paged as $item): ?>
      <div class="card">
        <img class="thumb" src="<?= htmlspecialchars($item['image'] ?? '') ?>" alt="<?= htmlspecialchars($item['title'] ?? '') ?>">
        <div class="card-body">
          <?php $catTxt = trim((string)($item['category'] ?? '')); if ($catTxt !== ''): ?>
            <div class="category"><?= htmlspecialchars($catTxt) ?></div>
          <?php endif; ?>
          <div class="title"><?= htmlspecialchars($item['title'] ?? '') ?></div>
          <?php if (!empty($item['instructor'])): ?>
            <div class="subtitle">👨‍🏫 <?= htmlspecialchars($item['instructor']) ?></div>
          <?php endif; ?>
          <div class="meta">
            <small>⭐ <?= number_format((float)($item['rating'] ?? 0), 1) ?></small>
            <small>👥 <?= htmlspecialchars(number_format((float)($item['students'] ?? 0))) ?></small>
          </div>
          <div class="meta">
            <small>🌎 <?= htmlspecialchars($item['language'] ?? '') ?></small>
            <small>🕒 <?= htmlspecialchars($item['duration'] ?? '') ?></small>
          </div>
          <a class="cta" href="go.php?u=<?= urlencode($item['url'] ?? '') ?>">Get Free Access</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($total_pages > 1): ?>
  <div class="pager">
    <?php for ($p = 1; $p <= $total_pages; $p++): $u = http_build_query(['q'=>$q,'cat'=>$cat,'sort'=>$sort,'page'=>$p]); ?>
      <a href="courses.php?<?= $u ?>" class="<?= $p===$page?'active':'' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</main>
</body>
</html>
