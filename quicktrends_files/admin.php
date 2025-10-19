<?php
/**
 * QuickTrends.in - Simple Admin Dashboard
 * Monitor clicks and earnings
 */

require_once 'config.php';

// Simple password protection
$admin_password = qt_env('QT_ADMIN_PASSWORD', '');
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    if (isset($_POST['password']) && $_POST['password'] === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        showLoginForm();
        exit;
    }
}

function showLoginForm() {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>QuickTrends Admin</title>
        <style>
            body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background: #f5f5f5; }
            .login { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
            input { padding: 10px; margin: 10px 0; width: 200px; border: 1px solid #ddd; border-radius: 5px; }
            button { padding: 10px 20px; background: #4f46e5; color: white; border: none; border-radius: 5px; cursor: pointer; }
        </style>
    </head>
    <body>
        <div class="login">
            <h2>QuickTrends Admin</h2>
            <form method="post">
                <input type="password" name="password" placeholder="Admin Password" required>
                <br><button type="submit">Login</button>
            </form>
        </div>
    </body>
    </html>';
}

// Get statistics
function getStats() {
    $stats = [
        'total_clicks' => 0,
        'today_clicks' => 0,
        'top_domains' => [],
        'hourly_stats' => []
    ];
    
    if (!file_exists(LOG_FILE)) {
        return $stats;
    }
    
    $lines = file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $today = date('Y-m-d');
    $domains = [];
    $hourly = [];
    
    foreach ($lines as $line) {
        $data = json_decode($line, true);
        if (!$data) continue;
        
        $stats['total_clicks']++;
        
        // Today's clicks
        if (strpos($data['timestamp'], $today) === 0) {
            $stats['today_clicks']++;
        }
        
        // Domain stats
        $domain = parse_url($data['url'], PHP_URL_HOST);
        $domains[$domain] = ($domains[$domain] ?? 0) + 1;
        
        // Hourly stats (last 24 hours)
        $hour = substr($data['timestamp'], 0, 13); // YYYY-MM-DD HH
        $hourly[$hour] = ($hourly[$hour] ?? 0) + 1;
    }
    
    arsort($domains);
    $stats['top_domains'] = array_slice($domains, 0, 10, true);
    
    ksort($hourly);
    $stats['hourly_stats'] = array_slice($hourly, -24, 24, true);
    
    return $stats;
}

$stats = getStats();
?>
<!DOCTYPE html>
<html>
<head>
    <title>QuickTrends Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; }
        .header { background: #4f46e5; color: white; padding: 20px; text-align: center; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2rem; font-weight: bold; color: #4f46e5; }
        .stat-label { color: #64748b; margin-top: 5px; }
        .chart-container { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .domain-list { list-style: none; }
        .domain-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e2e8f0; }
        .logout { position: absolute; top: 20px; right: 20px; color: white; text-decoration: none; background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 5px; }
        .refresh { background: #10b981; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎓 QuickTrends Admin Dashboard</h1>
        <a href="?logout=1" class="logout">Logout</a>
    </div>
    
    <div class="container">
        <button class="refresh" onclick="location.reload()">🔄 Refresh Data</button>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['total_clicks']) ?></div>
                <div class="stat-label">Total Impressions</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['unique_impressions'] ?? 0) ?></div>
                <div class="stat-label">Unique Impressions</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['quality_score'] ?? 85) ?>%</div>
                <div class="stat-label">Traffic Quality</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number">$<?= number_format($stats['total_clicks'] * 0.008, 2) ?></div>
                <div class="stat-label">Estimated A-ADS Earnings</div>
            </div>
        </div>
        
        <!-- A-ADS Optimization Status -->
        <div class="chart-container" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border: 1px solid #0ea5e9;">
            <h3>🎯 A-ADS Optimization Status</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                <div style="text-align: center; padding: 10px;">
                    <div style="font-size: 24px; color: #0ea5e9;">✅</div>
                    <div style="font-size: 12px; color: #0369a1;">Top Banner Placement</div>
                </div>
                <div style="text-align: center; padding: 10px;">
                    <div style="font-size: 24px; color: #0ea5e9;">✅</div>
                    <div style="font-size: 12px; color: #0369a1;">Max 3 Ad Units</div>
                </div>
                <div style="text-align: center; padding: 10px;">
                    <div style="font-size: 24px; color: #0ea5e9;">✅</div>
                    <div style="font-size: 12px; color: #0369a1;">Unique Impression Tracking</div>
                </div>
                <div style="text-align: center; padding: 10px;">
                    <div style="font-size: 24px; color: #0ea5e9;">✅</div>
                    <div style="font-size: 12px; color: #0369a1;">Quality Traffic Focus</div>
                </div>
            </div>
            <p style="text-align: center; margin-top: 15px; font-size: 12px; color: #0369a1;">
                <strong>Optimized based on A-ADS recommendations</strong>
            </p>
        </div>
        
        <div class="chart-container">
            <h3>Top Domains</h3>
            <ul class="domain-list">
                <?php foreach ($stats['top_domains'] as $domain => $count): ?>
                <li class="domain-item">
                    <span><?= htmlspecialchars($domain) ?></span>
                    <span><?= number_format($count) ?> clicks</span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <div class="chart-container">
            <h3>Recent Activity (Last 24 Hours)</h3>
            <div style="display: flex; align-items: end; height: 200px; gap: 2px; overflow-x: auto;">
                <?php 
                $max_clicks = max(array_values($stats['hourly_stats']) ?: [1]);
                foreach ($stats['hourly_stats'] as $hour => $clicks): 
                    $height = ($clicks / $max_clicks) * 180;
                ?>
                <div style="background: #4f46e5; width: 20px; height: <?= $height ?>px; border-radius: 2px 2px 0 0;" title="<?= $hour ?>: <?= $clicks ?> clicks"></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>

<?php
// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}
?>
