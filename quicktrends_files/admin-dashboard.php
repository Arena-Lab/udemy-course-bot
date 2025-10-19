<?php
/**
 * QuickTrends.in - Enterprise Admin Dashboard
 * Comprehensive management system for courses, analytics, and content
 */

session_start();
require_once 'config.php';

// Admin authentication
$admin_password = qt_env('QT_ADMIN_PASSWORD', '');

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin-dashboard.php');
    exit;
}

// Handle login
if (!isset($_SESSION['admin_logged_in'])) {
    if (isset($_POST['password']) && $_POST['password'] === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin-dashboard.php');
        exit;
    } else {
        showLoginForm();
        exit;
    }
}

function showLoginForm() {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>QuickTrends Admin Login</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .login-container {
                background: white;
                padding: 40px;
                border-radius: 20px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                text-align: center;
                min-width: 400px;
            }
            .logo {
                font-size: 3rem;
                margin-bottom: 10px;
            }
            h1 {
                color: #1f2937;
                margin-bottom: 10px;
                font-size: 1.8rem;
            }
            .subtitle {
                color: #64748b;
                margin-bottom: 30px;
            }
            input {
                width: 100%;
                padding: 15px;
                border: 2px solid #e5e7eb;
                border-radius: 10px;
                font-size: 1rem;
                margin-bottom: 20px;
                transition: border-color 0.3s;
            }
            input:focus {
                outline: none;
                border-color: #667eea;
            }
            button {
                width: 100%;
                padding: 15px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 10px;
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                transition: transform 0.2s;
            }
            button:hover {
                transform: translateY(-2px);
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="logo">🎓</div>
            <h1>QuickTrends Admin</h1>
            <p class="subtitle">Enterprise Dashboard</p>
            <form method="post">
                <input type="password" name="password" placeholder="Enter admin password" required>
                <button type="submit">Access Dashboard</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Get real statistics from actual data
function getStats() {
    $stats = [
        'total_clicks' => 0,
        'today_clicks' => 0,
        'unique_visitors' => 0,
        'conversion_rate' => 0,
        'revenue' => 0,
        'top_courses' => [],
        'recent_activity' => []
    ];
    
    // Get real contact messages count
    $logs_dir = __DIR__ . DIRECTORY_SEPARATOR . 'logs';
    $message_count = 0;
    if (is_dir($logs_dir)) {
        $files = glob($logs_dir . DIRECTORY_SEPARATOR . '*.txt');
        foreach ($files as $file) {
            $filename = basename($file);
            if ($filename !== 'contact_messages.txt' && $filename !== 'debug_test.txt') {
                $message_count++;
            }
        }
    }
    $stats['messages'] = $message_count;
    
    // Get real click data from log file if it exists
    if (defined('LOG_FILE') && file_exists(LOG_FILE)) {
        $lines = file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $today = date('Y-m-d');
        $domains = [];
        
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
        }
        
        arsort($domains);
        $stats['top_courses'] = array_slice($domains, 0, 10, true);
        
        // Calculate estimated revenue (A-ADS rate: ~$0.008 per click)
        $stats['revenue'] = $stats['total_clicks'] * 0.008;
        
        // Calculate conversion rate (clicks vs unique visitors estimate)
        $stats['unique_visitors'] = count(array_unique(array_column(array_map('json_decode', $lines), 'ip'))) ?: 1;
        $stats['conversion_rate'] = ($stats['total_clicks'] / max($stats['unique_visitors'], 1)) * 100;
    } else {
        // No log file exists yet - show zeros instead of fake data
        $stats['total_clicks'] = 0;
        $stats['today_clicks'] = 0;
        $stats['unique_visitors'] = 0;
        $stats['conversion_rate'] = 0;
        $stats['revenue'] = 0;
    }
    
    return $stats;
}

$stats = getStats();
$current_section = $_GET['section'] ?? 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuickTrends Enterprise Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #1f2937;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            padding: 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 30px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .sidebar-header h1 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        
        .sidebar-header .subtitle {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .nav-menu {
            padding: 20px 0;
        }
        
        .nav-item {
            display: block;
            padding: 15px 25px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        
        .nav-item:hover, .nav-item.active {
            background: rgba(255,255,255,0.1);
            border-left-color: #60a5fa;
        }
        
        .nav-item i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }
        
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1f2937;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logout-btn {
            background: #ef4444;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: #dc2626;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border-left: 5px solid;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.primary { border-left-color: #3b82f6; }
        .stat-card.success { border-left-color: #10b981; }
        .stat-card.warning { border-left-color: #f59e0b; }
        .stat-card.danger { border-left-color: #ef4444; }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .stat-title {
            font-size: 0.9rem;
            color: #64748b;
            font-weight: 600;
        }
        
        .stat-icon {
            font-size: 1.5rem;
        }
        
        .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 8px;
        }
        
        .stat-change {
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .stat-change.positive { color: #10b981; }
        .stat-change.negative { color: #ef4444; }
        
        /* Content Sections */
        .content-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .section-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1f2937;
        }
        
        .btn {
            background: #3b82f6;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #2563eb;
        }
        
        .btn-success { background: #10b981; }
        .btn-success:hover { background: #059669; }
        
        .btn-warning { background: #f59e0b; }
        .btn-warning:hover { background: #d97706; }
        
        .btn-danger { background: #ef4444; }
        .btn-danger:hover { background: #dc2626; }
        
        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .data-table th,
        .data-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .data-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }
        
        .data-table tr:hover {
            background: #f8fafc;
        }
        
        /* Toggle Switches */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #10b981;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h1>🎓 QuickTrends</h1>
                <p class="subtitle">Enterprise Dashboard</p>
            </div>
            
            <nav class="nav-menu">
                <a href="?section=dashboard" class="nav-item <?= $current_section === 'dashboard' ? 'active' : '' ?>">
                    <i>📊</i> Dashboard
                </a>
                <a href="?section=courses" class="nav-item <?= $current_section === 'courses' ? 'active' : '' ?>">
                    <i>📚</i> Course Management
                </a>
                <a href="?section=analytics" class="nav-item <?= $current_section === 'analytics' ? 'active' : '' ?>">
                    <i>📈</i> Analytics & Revenue
                </a>
                <a href="?section=messages" class="nav-item <?= $current_section === 'messages' ? 'active' : '' ?>">
                    <i>💬</i> Messages
                </a>
                <a href="?section=links" class="nav-item <?= $current_section === 'links' ? 'active' : '' ?>">
                    <i>🔗</i> Link Management
                </a>
                <a href="?section=monetization" class="nav-item <?= $current_section === 'monetization' ? 'active' : '' ?>">
                    <i>💰</i> Monetization
                </a>
                <a href="?section=settings" class="nav-item <?= $current_section === 'settings' ? 'active' : '' ?>">
                    <i>⚙️</i> Settings
                </a>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <h1 class="page-title">
                    <?php
                    $titles = [
                        'dashboard' => 'Dashboard Overview',
                        'courses' => 'Course Management',
                        'analytics' => 'Analytics & Revenue',
                        'messages' => 'Message Center',
                        'links' => 'Link Management',
                        'monetization' => 'Monetization Hub',
                        'settings' => 'System Settings'
                    ];
                    echo $titles[$current_section] ?? 'Dashboard';
                    ?>
                </h1>
                <div class="user-menu">
                    <span>Admin Panel</span>
                    <a href="?logout=1" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <?php if ($current_section === 'dashboard'): ?>
                <!-- Dashboard Overview -->
                <div class="stats-grid">
                    <div class="stat-card primary">
                        <div class="stat-header">
                            <span class="stat-title">Total Clicks</span>
                            <span class="stat-icon">🎯</span>
                        </div>
                        <div class="stat-value"><?= number_format($stats['total_clicks']) ?></div>
                        <div class="stat-change <?= $stats['total_clicks'] > 0 ? 'positive' : '' ?>">
                            <?= $stats['total_clicks'] > 0 ? '↗ Growing' : 'No data yet' ?>
                        </div>
                    </div>
                    
                    <div class="stat-card success">
                        <div class="stat-header">
                            <span class="stat-title">Revenue</span>
                            <span class="stat-icon">💰</span>
                        </div>
                        <div class="stat-value">$<?= number_format($stats['revenue'], 2) ?></div>
                        <div class="stat-change <?= $stats['revenue'] > 0 ? 'positive' : '' ?>">
                            <?= $stats['revenue'] > 0 ? '↗ A-ADS Earnings' : 'No earnings yet' ?>
                        </div>
                    </div>
                    
                    <div class="stat-card warning">
                        <div class="stat-header">
                            <span class="stat-title">Conversion Rate</span>
                            <span class="stat-icon">📊</span>
                        </div>
                        <div class="stat-value"><?= number_format($stats['conversion_rate'], 1) ?>%</div>
                        <div class="stat-change <?= $stats['conversion_rate'] > 0 ? 'positive' : '' ?>">
                            <?= $stats['conversion_rate'] > 0 ? '↗ Traffic Quality' : 'No data yet' ?>
                        </div>
                    </div>
                    
                    <div class="stat-card danger">
                        <div class="stat-header">
                            <span class="stat-title">Messages</span>
                            <span class="stat-icon">💬</span>
                        </div>
                        <div class="stat-value"><?= $stats['messages'] ?></div>
                        <div class="stat-change <?= $stats['messages'] > 0 ? 'positive' : '' ?>">
                            <?= $stats['messages'] > 0 ? 'Active inquiries' : 'No messages' ?>
                        </div>
                    </div>
                </div>
                
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">Quick Actions</h2>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                        <a href="admin-messages.php" class="btn">📧 View Messages</a>
                        <a href="?section=courses" class="btn btn-success">📚 Manage Courses</a>
                        <a href="?section=analytics" class="btn btn-warning">📈 View Analytics</a>
                        <a href="?section=links" class="btn btn-danger">🔗 Update Links</a>
                    </div>
                </div>
                
            <?php elseif ($current_section === 'courses'): ?>
                <!-- Course Management -->
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">Course Management</h2>
                        <button class="btn btn-success">+ Add New Course</button>
                    </div>
                    
                    <?php if (empty($stats['top_courses'])): ?>
                        <div style="text-align: center; padding: 40px; color: #64748b;">
                            <h3>No Course Data Available</h3>
                            <p>Start by adding courses or generating traffic to see analytics here.</p>
                            <button class="btn btn-success" style="margin-top: 15px;">+ Add Your First Course</button>
                        </div>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Domain/Course</th>
                                    <th>Clicks</th>
                                    <th>Estimated Revenue</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['top_courses'] as $domain => $clicks): ?>
                                <tr>
                                    <td><?= htmlspecialchars($domain) ?></td>
                                    <td><?= number_format($clicks) ?></td>
                                    <td>$<?= number_format($clicks * 0.008, 2) ?></td>
                                    <td>
                                        <button class="btn" style="padding: 5px 10px; font-size: 0.8rem;">Manage</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
            <?php elseif ($current_section === 'analytics'): ?>
                <!-- Analytics -->
                <div class="stats-grid">
                    <div class="stat-card primary">
                        <div class="stat-header">
                            <span class="stat-title">Today's Clicks</span>
                            <span class="stat-icon">📅</span>
                        </div>
                        <div class="stat-value"><?= number_format($stats['today_clicks']) ?></div>
                    </div>
                    
                    <div class="stat-card success">
                        <div class="stat-header">
                            <span class="stat-title">Unique Visitors</span>
                            <span class="stat-icon">👥</span>
                        </div>
                        <div class="stat-value"><?= number_format($stats['unique_visitors']) ?></div>
                    </div>
                </div>
                
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">Revenue Analytics</h2>
                    </div>
                    <p>Detailed analytics and revenue tracking will be displayed here.</p>
                </div>
                
            <?php elseif ($current_section === 'messages'): ?>
                <!-- Messages -->
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">Message Center</h2>
                        <a href="admin-messages.php" class="btn">Open Full Message Panel</a>
                    </div>
                    <p>You have <strong><?= $stats['messages'] ?></strong> messages waiting for review.</p>
                </div>
                
            <?php elseif ($current_section === 'links'): ?>
                <!-- Link Management -->
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">Link Management</h2>
                        <button class="btn btn-success">+ Add New Link</button>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="slider"></span>
                        </label>
                        <span style="margin-left: 10px;">Auto-update expired links</span>
                    </div>
                    
                    <div style="text-align: center; padding: 40px; color: #64748b;">
                        <h3>No Links Created Yet</h3>
                        <p>Your shortened links and Udemy course links will appear here once you start using the system.</p>
                        <div style="margin-top: 20px;">
                            <button class="btn btn-success">+ Create First Link</button>
                            <button class="btn" style="margin-left: 10px;">Import from Bot</button>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($current_section === 'monetization'): ?>
                <!-- Monetization -->
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">Monetization Settings</h2>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                        <div style="border: 1px solid #e5e7eb; padding: 20px; border-radius: 10px;">
                            <h3>A-ADS Integration</h3>
                            <p>Current earnings: $<?= number_format($stats['revenue'], 2) ?></p>
                            <label class="toggle-switch" style="margin-top: 10px;">
                                <input type="checkbox" checked>
                                <span class="slider"></span>
                            </label>
                            <span style="margin-left: 10px;">Enabled</span>
                        </div>
                        
                        <div style="border: 1px solid #e5e7eb; padding: 20px; border-radius: 10px;">
                            <h3>URL Shortener</h3>
                            <p>Active links: 15</p>
                            <label class="toggle-switch" style="margin-top: 10px;">
                                <input type="checkbox" checked>
                                <span class="slider"></span>
                            </label>
                            <span style="margin-left: 10px;">Enabled</span>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($current_section === 'settings'): ?>
                <!-- Settings -->
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">System Settings</h2>
                    </div>
                    
                    <div style="display: grid; gap: 20px;">
                        <div>
                            <h4>General Settings</h4>
                            <div style="margin-top: 10px;">
                                <label class="toggle-switch">
                                    <input type="checkbox" checked>
                                    <span class="slider"></span>
                                </label>
                                <span style="margin-left: 10px;">Enable analytics tracking</span>
                            </div>
                            <div style="margin-top: 10px;">
                                <label class="toggle-switch">
                                    <input type="checkbox" checked>
                                    <span class="slider"></span>
                                </label>
                                <span style="margin-left: 10px;">Auto-backup data</span>
                            </div>
                        </div>
                        
                        <div>
                            <h4>Security Settings</h4>
                            <button class="btn btn-warning">Change Admin Password</button>
                        </div>
                    </div>
                </div>
                
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
