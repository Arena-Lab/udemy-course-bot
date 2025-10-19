<?php
/**
 * QuickTrends.in - Admin Messages Panel
 * View contact form submissions
 */

// Simple password protection
require_once 'config.php';
session_start();
$admin_password = qt_env('QT_ADMIN_PASSWORD', '');

if (isset($_POST['password'])) {
    if ($_POST['password'] === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $error = 'Invalid password';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin-messages.php');
    exit;
}

// Check if logged in
$logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'];

// Handle message deletion
if ($logged_in && $_POST) {
    if (isset($_POST['delete_selected']) && isset($_POST['selected_files'])) {
        // Delete selected individual files only
        $logs_dir = __DIR__ . DIRECTORY_SEPARATOR . 'logs';
        $deleted_count = 0;
        
        // Delete the individual files
        foreach ($_POST['selected_files'] as $filename) {
            $file_path = $logs_dir . DIRECTORY_SEPARATOR . basename($filename);
            if (file_exists($file_path) && $filename !== 'contact_messages.txt' && $filename !== 'debug_test.txt') {
                unlink($file_path);
                $deleted_count++;
            }
        }
        
        $success_message = "Deleted $deleted_count selected messages!";
    } elseif (isset($_POST['delete_individual'])) {
        // Delete single individual file only
        $logs_dir = __DIR__ . DIRECTORY_SEPARATOR . 'logs';
        $filename = basename($_POST['delete_individual']);
        $file_path = $logs_dir . DIRECTORY_SEPARATOR . $filename;
        
        if (file_exists($file_path) && $filename !== 'contact_messages.txt' && $filename !== 'debug_test.txt') {
            unlink($file_path);
            $success_message = 'Message deleted successfully!';
        }
    }
}

// Get individual message files only
$individual_files = [];
if ($logged_in) {
    $logs_dir = __DIR__ . DIRECTORY_SEPARATOR . 'logs';
    if (is_dir($logs_dir)) {
        $files = glob($logs_dir . DIRECTORY_SEPARATOR . '*.txt');
        foreach ($files as $file) {
            $filename = basename($file);
            // Skip the combined log file and debug files
            if ($filename !== 'contact_messages.txt' && $filename !== 'debug_test.txt') {
                $individual_files[] = [
                    'file' => $filename,
                    'content' => file_get_contents($file),
                    'time' => filemtime($file)
                ];
            }
        }
        // Sort by newest first
        usort($individual_files, function($a, $b) {
            return $b['time'] - $a['time'];
        });
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Messages - QuickTrends</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #1f2937;
            background: #f8fafc;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: #1e40af;
            margin-bottom: 10px;
        }
        
        .login-form {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            max-width: 400px;
            margin: 100px auto;
        }
        
        .login-form h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #1f2937;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .btn {
            background: #1e40af;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background: #1d4ed8;
        }
        
        .btn-logout {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: background 0.3s;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .btn-logout:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .error {
            background: #fef2f2;
            color: #dc2626;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #fecaca;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #1e40af;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .message-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #1e40af;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .message-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
        }
        
        .message-time {
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .message-content {
            white-space: pre-line;
            color: #374151;
            font-family: 'Courier New', monospace;
            background: #f8fafc;
            padding: 15px;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .no-messages {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 10px 20px;
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .tab.active {
            background: #1e40af;
            color: white;
            border-color: #1e40af;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .delete-controls h3 {
            margin-bottom: 15px;
            color: #1f2937;
        }
        
        .btn-danger {
            background: #dc2626;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        
        .btn-danger:hover {
            background: #b91c1c;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 0.8rem;
        }
        
        .message-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-container input[type="checkbox"] {
            transform: scale(1.2);
        }
        
        .bulk-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .select-all-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <?php if (!$logged_in): ?>
        <div class="login-form">
            <h2>🔐 Admin Login</h2>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn" style="width: 100%;">Login</button>
            </form>
        </div>
    <?php else: ?>
        <div class="container">
            <div class="header">
                <h1>📧 Contact Messages Admin Panel</h1>
                <p>View and manage contact form submissions</p>
                <a href="?logout=1" class="btn btn-logout">Logout</a>
            </div>
            
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($individual_files); ?></div>
                    <div class="stat-label">Total Messages</div>
                </div>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="success" id="successMessage">
                    ✅ <?php echo $success_message; ?>
                </div>
                <script>
                    // Auto-hide success message after 3 seconds
                    setTimeout(function() {
                        const msg = document.getElementById('successMessage');
                        if (msg) {
                            msg.style.opacity = '0';
                            msg.style.transition = 'opacity 0.5s';
                            setTimeout(() => msg.remove(), 500);
                        }
                    }, 3000);
                </script>
            <?php endif; ?>
            
            <div class="delete-controls">
                <h3>🗑️ Message Management</h3>
                <div class="bulk-actions">
                    <button type="button" class="btn" onclick="showBulkDelete()">Bulk Delete Selected</button>
                </div>
            </div>
            
            <div id="individual" class="tab-content active">
                <?php if (empty($individual_files)): ?>
                    <div class="no-messages">
                        <h3>No individual message files found</h3>
                        <p>Individual message files will appear here.</p>
                    </div>
                <?php else: ?>
                    <form id="bulkDeleteForm" method="POST" style="display: none;">
                        <div class="select-all-container">
                            <input type="checkbox" id="selectAll" onchange="toggleAll()">
                            <label for="selectAll"><strong>Select All</strong></label>
                            <button type="submit" name="delete_selected" class="btn-danger" onclick="return confirm('Are you sure you want to delete selected messages?');">Delete Selected</button>
                            <button type="button" class="btn" onclick="hideBulkDelete()">Cancel</button>
                        </div>
                        
                        <?php foreach ($individual_files as $index => $file): ?>
                            <div class="message-card">
                                <div class="message-header">
                                    <div class="message-actions">
                                        <div class="checkbox-container bulk-select" style="display: none;">
                                            <input type="checkbox" name="selected_files[]" value="<?php echo htmlspecialchars($file['file']); ?>" class="message-checkbox">
                                        </div>
                                        <div class="message-title"><?php echo $file['file']; ?></div>
                                        <div class="message-time"><?php echo date('Y-m-d H:i:s', $file['time']); ?></div>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this message?');">
                                            <input type="hidden" name="delete_individual" value="<?php echo htmlspecialchars($file['file']); ?>">
                                            <button type="submit" class="btn-danger btn-small">Delete</button>
                                        </form>
                                    </div>
                                </div>
                                <div class="message-content"><?php echo htmlspecialchars($file['content']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </form>
                    
                    <div id="normalView">
                        <?php foreach ($individual_files as $index => $file): ?>
                            <div class="message-card">
                                <div class="message-header">
                                    <div class="message-actions">
                                        <div class="message-title"><?php echo $file['file']; ?></div>
                                        <div class="message-time"><?php echo date('Y-m-d H:i:s', $file['time']); ?></div>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this message?');">
                                            <input type="hidden" name="delete_individual" value="<?php echo htmlspecialchars($file['file']); ?>">
                                            <button type="submit" class="btn-danger btn-small">Delete</button>
                                        </form>
                                    </div>
                                </div>
                                <div class="message-content"><?php echo htmlspecialchars($file['content']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <script>
        function showBulkDelete() {
            document.getElementById('normalView').style.display = 'none';
            document.getElementById('bulkDeleteForm').style.display = 'block';
            document.querySelectorAll('.bulk-select').forEach(el => {
                el.style.display = 'flex';
            });
        }
        
        function hideBulkDelete() {
            document.getElementById('normalView').style.display = 'block';
            document.getElementById('bulkDeleteForm').style.display = 'none';
            document.querySelectorAll('.bulk-select').forEach(el => {
                el.style.display = 'none';
            });
            // Uncheck all checkboxes
            document.querySelectorAll('.message-checkbox').forEach(cb => {
                cb.checked = false;
            });
            document.getElementById('selectAll').checked = false;
        }
        
        function toggleAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.message-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }
    </script>
</body>
</html>
