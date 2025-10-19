<?php
/**
 * QuickTrends Blog Content Management System
 * Professional CMS for managing blog posts, links, and images
 */

session_start();
require_once 'config.php';

// Admin authentication
$admin_password = qt_env('QT_ADMIN_PASSWORD', '');

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: blog-manager.php');
    exit;
}

// Handle login
if (!isset($_SESSION['blog_admin'])) {
    if (isset($_POST['password']) && $_POST['password'] === $admin_password) {
        $_SESSION['blog_admin'] = true;
        header('Location: blog-manager.php');
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
        <title>Blog Manager Login</title>
        <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Ctext y='14' font-size='14'%3E📝%3C/text%3E%3C/svg%3E">
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
            .logo { font-size: 3rem; margin-bottom: 10px; }
            h1 { color: #1f2937; margin-bottom: 10px; font-size: 1.8rem; }
            .subtitle { color: #64748b; margin-bottom: 30px; }
            input {
                width: 100%;
                padding: 15px;
                border: 2px solid #e5e7eb;
                border-radius: 10px;
                font-size: 1rem;
                margin-bottom: 20px;
                transition: border-color 0.3s;
            }
            input:focus { outline: none; border-color: #667eea; }
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
            button:hover { transform: translateY(-2px); }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="logo">📝</div>
            <h1>Blog Manager</h1>
            <p class="subtitle">Content Management System</p>
            <form method="post">
                <input type="password" name="password" placeholder="Enter admin password" required>
                <button type="submit">Access Blog Manager</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Load blog content
function loadBlogContent() {
    $file = __DIR__ . '/blog-content.json';
    if (file_exists($file)) {
        return json_decode(file_get_contents($file), true);
    }
    return ['posts' => [], 'categories' => [], 'settings' => []];
}

// Save blog content
function saveBlogContent($data) {
    $file = __DIR__ . '/blog-content.json';
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Handle form submissions
$message = '';
$blog_data = loadBlogContent();

if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_course':
                $post_id = (int)$_POST['post_id'];
                $course_id = $_POST['course_id'];
                $course_data = [
                    'id' => $course_id,
                    'title' => $_POST['course_title'],
                    'description' => $_POST['course_description'],
                    'url' => $_POST['course_url'],
                    'image' => $_POST['course_image'],
                    'rating' => $_POST['course_rating'],
                    'duration' => $_POST['course_duration'],
                    'students' => $_POST['course_students'],
                    'status' => $_POST['course_status']
                ];
                
                if (!isset($blog_data['udemy_courses'])) {
                    $blog_data['udemy_courses'] = [];
                }
                if (!isset($blog_data['udemy_courses'][$post_id])) {
                    $blog_data['udemy_courses'][$post_id] = [];
                }
                
                // Update existing course or add new one
                $found = false;
                foreach ($blog_data['udemy_courses'][$post_id] as &$course) {
                    if ($course['id'] === $course_id) {
                        $course = $course_data;
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    $blog_data['udemy_courses'][$post_id][] = $course_data;
                }
                
                if (saveBlogContent($blog_data)) {
                    echo json_encode(['success' => true, 'message' => 'Course updated successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to save course data']);
                }
                exit;
                
            case 'approve_course':
                $post_id = (int)$_POST['post_id'];
                $course_id = $_POST['course_id'];
                
                if (isset($blog_data['udemy_courses'][$post_id])) {
                    foreach ($blog_data['udemy_courses'][$post_id] as &$course) {
                        if ($course['id'] === $course_id) {
                            $course['status'] = 'active';
                            $course['approved_at'] = date('Y-m-d H:i:s');
                            $course['approved_by'] = 'admin';
                            break;
                        }
                    }
                }
                
                if (saveBlogContent($blog_data)) {
                    echo json_encode(['success' => true, 'message' => 'Course approved and is now live!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to approve course']);
                }
                exit;
                
            case 'reject_course':
                $post_id = (int)$_POST['post_id'];
                $course_id = $_POST['course_id'];
                
                if (isset($blog_data['udemy_courses'][$post_id])) {
                    foreach ($blog_data['udemy_courses'][$post_id] as &$course) {
                        if ($course['id'] === $course_id) {
                            $course['status'] = 'rejected';
                            $course['rejected_at'] = date('Y-m-d H:i:s');
                            $course['rejected_by'] = 'admin';
                            break;
                        }
                    }
                }
                
                if (saveBlogContent($blog_data)) {
                    echo json_encode(['success' => true, 'message' => 'Course rejected successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to reject course']);
                }
                exit;

            case 'delete_course':
                $post_id = (int)$_POST['post_id'];
                $course_id = $_POST['course_id'];
                
                if (isset($blog_data['udemy_courses'][$post_id])) {
                    $blog_data['udemy_courses'][$post_id] = array_filter(
                        $blog_data['udemy_courses'][$post_id],
                        function($course) use ($course_id) {
                            return $course['id'] !== $course_id;
                        }
                    );
                    $blog_data['udemy_courses'][$post_id] = array_values($blog_data['udemy_courses'][$post_id]);
                }
                
                if (saveBlogContent($blog_data)) {
                    echo json_encode(['success' => true, 'message' => 'Course deleted successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to save course data']);
                }
                exit;
                
            case 'reorder_posts':
                $post_order = json_decode($_POST['post_order'], true);
                
                if (is_array($post_order)) {
                    $current_posts = $blog_data['posts'];
                    $reordered_posts = [];
                    
                    // Reorder posts based on the new order
                    foreach ($post_order as $post_id) {
                        foreach ($current_posts as $post) {
                            if ($post['id'] == $post_id) {
                                $reordered_posts[] = $post;
                                break;
                            }
                        }
                    }
                    
                    $blog_data['posts'] = $reordered_posts;
                    
                    if (saveBlogContent($blog_data)) {
                        echo json_encode(['success' => true, 'message' => 'Post order updated successfully!']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to save post order']);
                    }
                }
                exit;

            case 'reorder_courses':
                $post_id = (int)$_POST['post_id'];
                $course_order = json_decode($_POST['course_order'], true);
                
                if (isset($blog_data['udemy_courses'][$post_id]) && is_array($course_order)) {
                    $current_courses = $blog_data['udemy_courses'][$post_id];
                    $reordered_courses = [];
                    
                    // Reorder courses based on the new order
                    foreach ($course_order as $course_id) {
                        foreach ($current_courses as $course) {
                            if ($course['id'] === $course_id) {
                                $reordered_courses[] = $course;
                                break;
                            }
                        }
                    }
                    
                    $blog_data['udemy_courses'][$post_id] = $reordered_courses;
                    
                    if (saveBlogContent($blog_data)) {
                        echo json_encode(['success' => true, 'message' => 'Course order updated successfully!']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to save course order']);
                    }
                }
                exit;

            case 'save_settings':
                $blog_data['settings'] = [
                    'site_title' => $_POST['site_title'] ?: 'QuickTrends Educational Blog',
                    'posts_per_page' => max(1, min(20, (int)$_POST['posts_per_page'])),
                    'courses_per_page' => max(1, min(10, (int)$_POST['courses_per_page'])),
                    'featured_posts_count' => max(1, min(6, (int)$_POST['featured_posts_count']))
                ];
                
                if (saveBlogContent($blog_data)) {
                    $message = 'Settings saved successfully!';
                } else {
                    $message = 'Error saving settings.';
                }
                header('Location: blog-manager.php?section=settings&message=' . urlencode($message));
                exit;

            case 'add_course':
                $post_id = (int)$_POST['post_id'];
                $course_id = 'course_' . time() . '_' . rand(1000, 9999);
                $course_data = [
                    'id' => $course_id,
                    'title' => $_POST['course_title'] ?: 'New Course',
                    'description' => $_POST['course_description'] ?: 'Course description',
                    'url' => $_POST['course_url'] ?: 'https://www.udemy.com/course/example/',
                    'image' => $_POST['course_image'] ?: 'https://img-c.udemycdn.com/course/240x135/placeholder.jpg',
                    'rating' => $_POST['course_rating'] ?: '4.0',
                    'duration' => $_POST['course_duration'] ?: '10 Hours',
                    'students' => $_POST['course_students'] ?: '1K+',
                    'status' => 'pending', // Always set new courses as pending
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => 'system'
                ];
                
                if (!isset($blog_data['udemy_courses'])) {
                    $blog_data['udemy_courses'] = [];
                }
                if (!isset($blog_data['udemy_courses'][$post_id])) {
                    $blog_data['udemy_courses'][$post_id] = [];
                }
                
                $blog_data['udemy_courses'][$post_id][] = $course_data;
                
                if (saveBlogContent($blog_data)) {
                    echo json_encode(['success' => true, 'message' => 'Course added successfully! It is pending admin approval.', 'course' => $course_data]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to save course data']);
                }
                exit;
                
            case 'save_post':
                $post_id = (int)$_POST['post_id'];
                $post_data = [
                    'id' => $post_id,
                    'title' => $_POST['title'],
                    'excerpt' => $_POST['excerpt'],
                    'content' => $_POST['content'],
                    'category' => $_POST['category'],
                    'date' => $_POST['date'],
                    'image' => $_POST['image'],
                    'featured' => isset($_POST['featured']),
                    'status' => $_POST['status'],
                    'resources' => []
                ];
                // Handle resources
                if (isset($_POST['resource_name'])) {
                    for ($i = 0; $i < count($_POST['resource_name']); $i++) {
                        if (!empty($_POST['resource_name'][$i])) {
                            $resource_url = $_POST['resource_url'][$i];
                            // Allow emojis and non-URL text in resource URLs
                            $post_data['resources'][] = [
                                'name' => $_POST['resource_name'][$i],
                                'url' => $resource_url, // No URL validation - accept any text including emojis
                                'type' => $_POST['resource_type'][$i],
                                'description' => $_POST['resource_desc'][$i] ?? ''
                            ];
                        }
                    }
                }
                
                // Update or add post
                $found = false;
                foreach ($blog_data['posts'] as &$post) {
                    if ($post['id'] == $post_id) {
                        $post = $post_data;
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    $blog_data['posts'][] = $post_data;
                }
                
                saveBlogContent($blog_data);
                $message = "Post saved successfully!";
                break;
                
            case 'delete_post':
                $post_id = (int)$_POST['post_id'];
                $blog_data['posts'] = array_filter($blog_data['posts'], function($post) use ($post_id) {
                    return $post['id'] != $post_id;
                });
                saveBlogContent($blog_data);
                $message = "Post deleted successfully!";
                break;

            case 'move_post_category':
                $post_id = (int)$_POST['post_id'];
                $new_category = trim($_POST['category'] ?? '');
                $updated = false;
                if ($new_category !== '') {
                    foreach ($blog_data['posts'] as &$post) {
                        if ($post['id'] == $post_id) {
                            $post['category'] = $new_category;
                            $updated = true;
                            break;
                        }
                    }
                }
                if ($updated && saveBlogContent($blog_data)) {
                    echo json_encode(['success' => true, 'message' => 'Post moved to category']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to move post']);
                }
                exit;
        }
    }
}

$current_section = $_GET['section'] ?? 'posts';
$edit_post_id = $_GET['edit'] ?? null;
$edit_post = null;

if ($edit_post_id) {
    foreach ($blog_data['posts'] as $post) {
        if ($post['id'] == $edit_post_id) {
            $edit_post = $post;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Content Manager</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #1f2937;
        }
        
        .drag-handle:hover {
            color: #3b82f6 !important;
            cursor: grab !important;
        }
        
        .drag-handle:active {
            cursor: grabbing !important;
        }
        .drag-handle {
            -webkit-user-drag: element;
            user-select: none;
        }
        
        .drag-over {
            background: #f0f9ff !important;
            transform: scale(1.02) !important;
            transition: all 0.2s ease !important;
        }
        
        .course-item[draggable="true"],
        .post-item[draggable="true"] {
            cursor: default;
        }
        
        .course-item[draggable="true"]:hover .drag-handle,
        .post-item[draggable="true"]:hover .drag-handle {
            color: #3b82f6 !important;
        }
        
        .course-item.dragging,
        .post-item.dragging {
            opacity: 0.9 !important;
            transform: scale(1.01) !important;
            box-shadow: 0 8px 20px rgba(0,0,0,0.12) !important;
            transition: box-shadow 0.15s ease, transform 0.15s ease !important;
        }
        
        .course-item.drag-over,
        .post-item.drag-over {
            border-top: 3px solid #3b82f6 !important;
            background: #f0f9ff !important;
            transition: background 0.15s ease, border-top 0.15s ease !important;
        }
        
        /* Placeholder to stabilize layout during dragging */
        .drag-placeholder {
            border: 2px dashed #93c5fd;
            background: #eff6ff;
            border-radius: 8px;
            margin: 0; /* will inherit spacing from container */
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
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .logout-btn {
            background: #ef4444;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: #dc2626;
        }
        
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
            display: inline-block;
        }
        
        .btn:hover { background: #2563eb; }
        .btn-success { background: #10b981; }
        .btn-success:hover { background: #059669; }
        .btn-warning { background: #f59e0b; }
        .btn-warning:hover { background: #d97706; }
        .btn-danger { background: #ef4444; }
        .btn-danger:hover { background: #dc2626; }
        
        /* Forms */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
        }
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
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
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-published { background: #dcfce7; color: #166534; }
        .status-draft { background: #fef3c7; color: #92400e; }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .message.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .resources-section {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .resource-item {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr auto;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px;
            background: #f8fafc;
            border-radius: 6px;
        }
        
        .resource-item input {
            padding: 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
        }
        
        .remove-resource {
            background: #ef4444;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
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
            
            .form-row {
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
                <h1>📝 Blog Manager</h1>
                <p class="subtitle">Content Management System</p>
            </div>
            
            <nav class="nav-menu">
                <a href="?section=posts" class="nav-item <?= $current_section === 'posts' ? 'active' : '' ?>">
                    <i>📄</i> All Posts
                </a>
                <a href="?section=new-post" class="nav-item <?= $current_section === 'new-post' ? 'active' : '' ?>">
                    <i>➕</i> New Post
                </a>
                <a href="?section=categories" class="nav-item <?= $current_section === 'categories' ? 'active' : '' ?>">
                    <i>🏷️</i> Categories
                </a>
                <a href="?section=courses" class="nav-item <?= $current_section === 'courses' ? 'active' : '' ?>">
                    <i>🎓</i> Udemy Courses
                </a>
                <a href="?section=settings" class="nav-item <?= $current_section === 'settings' ? 'active' : '' ?>">
                    <i>⚙️</i> Settings
                </a>
                <a href="blog.php" class="nav-item" target="_blank">
                    <i>👁️</i> View Blog
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
                        'posts' => 'All Posts',
                        'new-post' => 'Create New Post',
                        'categories' => 'Manage Categories',
                        'courses' => 'Udemy Course Management',
                        'settings' => 'Blog Settings'
                    ];
                    echo $titles[$current_section] ?? 'Blog Manager';
                    ?>
                </h1>
                <div>
                    <a href="blog.php" class="btn" target="_blank" style="margin-right: 10px;">View Blog</a>
                    <a href="?logout=1" class="logout-btn">Logout</a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="message success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <?php if ($current_section === 'posts'): ?>
                <!-- All Posts -->
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">Blog Posts</h2>
                        <div style="display: flex; gap: 15px; align-items: center;">
                            <?php 
                            // Count pending courses
                            $pending_count = 0;
                            foreach ($blog_data['udemy_courses'] ?? [] as $post_courses) {
                                foreach ($post_courses as $course) {
                                    if ($course['status'] === 'pending') {
                                        $pending_count++;
                                    }
                                }
                            }
                            if ($pending_count > 0): ?>
                                <a href="?section=courses" style="background: #f59e0b; color: white; padding: 8px 16px; border-radius: 20px; text-decoration: none; font-size: 14px; font-weight: 600;">
                                    ⏳ <?= $pending_count ?> Course<?= $pending_count > 1 ? 's' : '' ?> Pending Approval
                                </a>
                            <?php endif; ?>
                            <a href="?section=new-post" class="btn btn-success">+ New Post</a>
                        </div>
                    </div>
                    <?php if (!empty($blog_data['categories'])): ?>
                    <!-- Drag target bar to move post to another category -->
                    <div class="category-dropbar" style="display:flex; gap:8px; flex-wrap:wrap; padding:10px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:8px; margin-bottom:12px;">
                        <span style="color:#64748b; font-weight:600;">Move to category:</span>
                        <?php foreach (($blog_data['categories'] ?? []) as $cat): ?>
                            <span class="category-drop-target" data-category="<?= htmlspecialchars($cat) ?>" style="padding:6px 10px; background:#e2e8f0; border-radius:6px; font-weight:600; cursor:copy;">
                                <?= htmlspecialchars($cat) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (empty($blog_data['posts'])): ?>
                        <div style="text-align: center; padding: 40px; color: #64748b;">
                            <h3>No Posts Yet</h3>
                            <p>Create your first blog post to get started.</p>
                            <a href="?section=new-post" class="btn btn-success" style="margin-top: 15px;">Create First Post</a>
                        </div>
                    <?php else: ?>
                        <div class="posts-grid sortable-posts" style="display: grid; gap: 20px;">
                            <?php foreach ($blog_data['posts'] as $post): ?>
                                <div class="post-item" draggable="true" data-post-id="<?= htmlspecialchars($post['id']) ?>" style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                                    <div style="display: flex; gap: 15px; align-items: start;">
                                        <div class="drag-handle" draggable="true" style="display: flex; align-items: center; justify-content: center; width: 20px; height: 60px; color: #9ca3af; cursor: grab; font-size: 16px; user-select: none;" title="Drag to reorder">
                                            ⋮⋮
                                        </div>
                                        <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0;">
                                            <?= $post['image'] ?>
                                        </div>
                                        <div style="flex: 1;">
                                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                                                <div>
                                                    <h3 style="margin: 0; color: #1e293b; font-size: 1.2rem;">
                                                        <?= htmlspecialchars($post['title']) ?>
                                                        <?php if ($post['featured'] ?? false): ?>
                                                            <span style="color: #f59e0b; font-size: 0.9rem;">⭐ Featured</span>
                                                        <?php endif; ?>
                                                    </h3>
                                                    <p style="margin: 5px 0 0 0; color: #64748b; font-size: 0.9rem;">
                                                        <?= htmlspecialchars($post['category']) ?> • <?= date('M j, Y', strtotime($post['date'])) ?> • <?= count($post['resources'] ?? []) ?> resources
                                                    </p>
                                                </div>
                                                <span class="status-badge status-<?= $post['status'] ?>" style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; <?= $post['status'] === 'published' ? 'background: #10b981; color: white;' : 'background: #f59e0b; color: white;' ?>">
                                                    <?= ucfirst($post['status']) ?>
                                                </span>
                                            </div>
                                            <p style="color: #6b7280; font-size: 0.9rem; margin-bottom: 15px; line-height: 1.4;">
                                                <?= htmlspecialchars(substr($post['excerpt'], 0, 120)) ?>...
                                            </p>
                                            <div style="display: flex; gap: 10px;">
                                                <a href="?section=new-post&edit=<?= $post['id'] ?>" class="btn btn-sm" style="background: #3b82f6; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 14px;">
                                                    📝 Edit
                                                </a>
                                                <form method="post" style="display: inline;" onsubmit="return confirm('Delete this post?');">
                                                    <input type="hidden" name="action" value="delete_post">
                                                    <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                                    <button type="submit" class="btn btn-danger" style="background: #ef4444; color: white; padding: 6px 12px; border-radius: 6px; border: none; font-size: 14px; cursor: pointer;">
                                                        🗑️ Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
            <?php elseif ($current_section === 'new-post' || $edit_post): ?>
                <!-- New/Edit Post -->
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title"><?= $edit_post ? 'Edit Post' : 'Create New Post' ?></h2>
                    </div>
                    
                    <form method="post">
                        <input type="hidden" name="action" value="save_post">
                        <input type="hidden" name="post_id" value="<?= $edit_post['id'] ?? (max(array_column($blog_data['posts'], 'id')) + 1) ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Post Title</label>
                                <input type="text" name="title" value="<?= htmlspecialchars($edit_post['title'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Category</label>
                                <select name="category" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($blog_data['categories'] ?? [] as $category): ?>
                                        <option value="<?= htmlspecialchars($category) ?>" <?= ($edit_post['category'] ?? '') === $category ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Excerpt (Short Description)</label>
                            <textarea name="excerpt" required><?= htmlspecialchars($edit_post['excerpt'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Full Content (Supports Markdown)</label>
                            <textarea name="content" style="min-height: 300px;" required><?= htmlspecialchars($edit_post['content'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Featured Image URL (or Emoji)</label>
                                <input type="text" name="image" value="<?= htmlspecialchars($edit_post['image'] ?? '') ?>" placeholder="https://images.unsplash.com/... or 🐍">
                            </div>
                            <div class="form-group">
                                <label>Publish Date</label>
                                <input type="date" name="date" value="<?= $edit_post['date'] ?? date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" required>
                                    <option value="published" <?= ($edit_post['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                                    <option value="draft" <?= ($edit_post['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label style="display: flex; align-items: center; gap: 10px;">
                                    <input type="checkbox" name="featured" <?= ($edit_post['featured'] ?? false) ? 'checked' : '' ?>>
                                    Featured Post
                                </label>
                            </div>
                        </div>
                        
                        <!-- Resources Section -->
                        <div class="resources-section">
                            <h3 style="margin-bottom: 15px;">Resources & Links</h3>
                            <div id="resources-container">
                                <?php if (!empty($edit_post['resources'])): ?>
                                    <?php foreach ($edit_post['resources'] as $resource): ?>
                                        <div class="resource-item">
                                            <input type="text" name="resource_name[]" placeholder="Resource Name" value="<?= htmlspecialchars($resource['name']) ?>">
                                            <input type="text" name="resource_url[]" placeholder="https://... or emoji" value="<?= htmlspecialchars($resource['url']) ?>">
                                            <select name="resource_type[]">
                                                <option value="Free Course" <?= $resource['type'] === 'Free Course' ? 'selected' : '' ?>>Free Course</option>
                                                <option value="Free Certification" <?= $resource['type'] === 'Free Certification' ? 'selected' : '' ?>>Free Certification</option>
                                                <option value="Tutorial" <?= $resource['type'] === 'Tutorial' ? 'selected' : '' ?>>Tutorial</option>
                                                <option value="Book" <?= $resource['type'] === 'Book' ? 'selected' : '' ?>>Book</option>
                                                <option value="Documentation" <?= $resource['type'] === 'Documentation' ? 'selected' : '' ?>>Documentation</option>
                                                <option value="Interactive" <?= $resource['type'] === 'Interactive' ? 'selected' : '' ?>>Interactive</option>
                                            </select>
                                            <input type="text" name="resource_desc[]" placeholder="Description" value="<?= htmlspecialchars($resource['description'] ?? '') ?>">
                                            <button type="button" class="remove-resource" onclick="this.parentElement.remove()">×</button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="btn" onclick="addResource()">+ Add Resource</button>
                        </div>
                        
                        <div style="margin-top: 30px;">
                            <button type="submit" class="btn btn-success">Save Post</button>
                            <a href="?section=posts" class="btn" style="margin-left: 10px;">Cancel</a>
                        </div>
                    </form>
                </div>
                
            <?php elseif ($current_section === 'categories'): ?>
                <!-- Categories Management -->
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">Categories</h2>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
                        <?php foreach ($blog_data['categories'] ?? [] as $category): ?>
                            <div style="background: #f8fafc; padding: 15px; border-radius: 8px; text-align: center;">
                                <strong><?= htmlspecialchars($category) ?></strong>
                                <br>
                                <small><?= count(array_filter($blog_data['posts'], function($p) use ($category) { return $p['category'] === $category; })) ?> posts</small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
            <?php elseif ($current_section === 'courses'): ?>
                <!-- Courses Section -->
                <div class="content-section">
                    <div class="section-header">
                        <h2>Udemy Course Management</h2>
                        <p>Manage Udemy courses that appear in each blog post. Update expired links and course details.</p>
                    </div>
                    
                    <div class="course-management">
                        <?php foreach ($blog_data['posts'] as $post): ?>
                            <?php $post_courses = $blog_data['udemy_courses'][$post['id']] ?? []; ?>
                            <div class="post-courses-section" style="background: white; border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f1f5f9;">
                                    <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                                        <?= $post['image'] ?>
                                    </div>
                                    <div>
                                        <h3 style="margin: 0; color: #1e293b; font-size: 1.2rem;"><?= htmlspecialchars($post['title']) ?></h3>
                                        <p style="margin: 5px 0 0 0; color: #64748b; font-size: 0.9rem;">Post ID: <?= $post['id'] ?> | <?= $post['category'] ?></p>
                                    </div>
                                </div>
                                
                                <?php if (!empty($post_courses)): ?>
                                    <?php $per_page = (int)($blog_data['settings']['courses_per_page'] ?? 4); if ($per_page < 1) $per_page = 4; ?>
                                    <div class="courses-grid sortable-courses" data-post-id="<?= $post['id'] ?>" data-per-page="<?= $per_page ?>" style="display: grid; gap: 20px;">
                                        <?php foreach ($post_courses as $index => $course): ?>
                                            <?php 
                                                $border_color = '#10b981'; // default green
                                                if ($course['status'] === 'pending') $border_color = '#f59e0b';
                                                elseif ($course['status'] === 'rejected') $border_color = '#ef4444';
                                            ?>
                                            <div class="course-item" draggable="true" data-course-id="<?= htmlspecialchars($course['id']) ?>" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; border-left: 4px solid <?= $border_color ?>;">
                                                <div style="display: flex; gap: 15px; align-items: start;">
                                                    <div class="drag-handle" draggable="true" style="display: flex; align-items: center; justify-content: center; width: 20px; height: 80px; color: #9ca3af; cursor: grab; font-size: 16px; user-select: none;" title="Drag to reorder">
                                                        ⋮⋮
                                                    </div>
                                                    <img src="<?= htmlspecialchars($course['image']) ?>" alt="Course Image" style="width: 120px; height: 80px; object-fit: cover; border-radius: 6px; flex-shrink: 0;">
                                                    <div style="flex: 1;">
                                                        <!-- Status Badge -->
                                                        <div style="margin-bottom: 10px;">
                                                            <?php 
                                                            $status_colors = [
                                                                'active' => ['bg' => '#10b981', 'text' => 'white', 'label' => 'LIVE'],
                                                                'pending' => ['bg' => '#f59e0b', 'text' => 'white', 'label' => 'PENDING APPROVAL'],
                                                                'rejected' => ['bg' => '#ef4444', 'text' => 'white', 'label' => 'REJECTED'],
                                                                'inactive' => ['bg' => '#6b7280', 'text' => 'white', 'label' => 'INACTIVE']
                                                            ];
                                                            $status_info = $status_colors[$course['status']] ?? $status_colors['inactive'];
                                                            ?>
                                                            <span style="background: <?= $status_info['bg'] ?>; color: <?= $status_info['text'] ?>; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                                                                <?= $status_info['label'] ?>
                                                            </span>
                                                        </div>
                                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                                            <div>
                                                                <label for="course-title-<?= $course['id'] ?>" style="display: block; font-weight: 600; color: #374151; margin-bottom: 5px;">Course Title</label>
                                                                <input type="text" class="course-title" id="course-title-<?= $course['id'] ?>" name="course_title_<?= $course['id'] ?>" data-course-id="<?= $course['id'] ?>" value="<?= htmlspecialchars($course['title']) ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                                                            </div>
                                                            <div>
                                                                <label for="course-url-<?= $course['id'] ?>" style="display: block; font-weight: 600; color: #374151; margin-bottom: 5px;">Course URL</label>
                                                                <input type="text" class="course-url" id="course-url-<?= $course['id'] ?>" name="course_url_<?= $course['id'] ?>" data-course-id="<?= $course['id'] ?>" value="<?= htmlspecialchars($course['url']) ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                                                            </div>
                                                        </div>
                                                        
                                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                                            <div>
                                                                <label for="course-image-<?= $course['id'] ?>" style="display: block; font-weight: 600; color: #374151; margin-bottom: 5px;">Image URL</label>
                                                                <input type="text" class="course-image" id="course-image-<?= $course['id'] ?>" name="course_image_<?= $course['id'] ?>" data-course-id="<?= $course['id'] ?>" value="<?= htmlspecialchars($course['image']) ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                                                            </div>
                                                            <div>
                                                                <label for="course-description-<?= $course['id'] ?>" style="display: block; font-weight: 600; color: #374151; margin-bottom: 5px;">Description</label>
                                                                <input type="text" class="course-description" id="course-description-<?= $course['id'] ?>" name="course_description_<?= $course['id'] ?>" data-course-id="<?= $course['id'] ?>" value="<?= htmlspecialchars($course['description']) ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                                                            </div>
                                                        </div>
                                                        
                                                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                                            <div>
                                                                <label for="course-rating-<?= $course['id'] ?>" style="display: block; font-weight: 600; color: #374151; margin-bottom: 5px;">Rating</label>
                                                                <input type="text" class="course-rating" id="course-rating-<?= $course['id'] ?>" name="course_rating_<?= $course['id'] ?>" data-course-id="<?= $course['id'] ?>" value="<?= htmlspecialchars($course['rating']) ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                                                            </div>
                                                            <div>
                                                                <label for="course-duration-<?= $course['id'] ?>" style="display: block; font-weight: 600; color: #374151; margin-bottom: 5px;">Duration</label>
                                                                <input type="text" class="course-duration" id="course-duration-<?= $course['id'] ?>" name="course_duration_<?= $course['id'] ?>" data-course-id="<?= $course['id'] ?>" value="<?= htmlspecialchars($course['duration']) ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                                                            </div>
                                                            <div>
                                                                <label for="course-students-<?= $course['id'] ?>" style="display: block; font-weight: 600; color: #374151; margin-bottom: 5px;">Students</label>
                                                                <input type="text" class="course-students" id="course-students-<?= $course['id'] ?>" name="course_students_<?= $course['id'] ?>" data-course-id="<?= $course['id'] ?>" value="<?= htmlspecialchars($course['students']) ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                                                            </div>
                                                            <div>
                                                                <label for="course-status-<?= $course['id'] ?>" style="display: block; font-weight: 600; color: #374151; margin-bottom: 5px;">Status</label>
                                                                <select class="course-status" id="course-status-<?= $course['id'] ?>" name="course_status_<?= $course['id'] ?>" data-course-id="<?= $course['id'] ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                                                                    <option value="active" <?= $course['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                                    <option value="inactive" <?= $course['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        
                                                        <div style="display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap;">
                                                            <?php if ($course['status'] === 'pending'): ?>
                                                                <button class="approve-course-btn" data-post-id="<?= $post['id'] ?>" data-course-id="<?= htmlspecialchars($course['id']) ?>" style="background: #10b981; color: white; padding: 8px 16px; border: none; border-radius: 6px; font-size: 14px; cursor: pointer;">
                                                                    Approve & Make Live
                                                                </button>
                                                                <button class="reject-course-btn" data-post-id="<?= $post['id'] ?>" data-course-id="<?= htmlspecialchars($course['id']) ?>" style="background: #f59e0b; color: white; padding: 8px 16px; border: none; border-radius: 6px; font-size: 14px; cursor: pointer;">
                                                                    Reject
                                                                </button>
                                                            <?php endif; ?>
                                                            <button class="update-course-btn" data-post-id="<?= $post['id'] ?>" data-course-id="<?= htmlspecialchars($course['id']) ?>" style="background: #3b82f6; color: white; padding: 8px 16px; border: none; border-radius: 6px; font-size: 14px; cursor: pointer;">
                                                                Update Course
                                                            </button>
                                                            <button class="delete-course-btn" data-post-id="<?= $post['id'] ?>" data-course-id="<?= htmlspecialchars($course['id']) ?>" style="background: #ef4444; color: white; padding: 8px 16px; border: none; border-radius: 6px; font-size: 14px; cursor: pointer;">
                                                                Delete Course
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <!-- Pagination controls -->
                                    <?php $total_pages = (int)ceil(max(1, count($post_courses)) / $per_page); ?>
                                    <?php if ($total_pages > 1): ?>
                                    <div class="course-pager" data-post-id="<?= $post['id'] ?>" style="display:flex; align-items:center; gap:10px; justify-content:flex-end; margin-top:10px;">
                                        <button class="btn course-page-prev" data-post-id="<?= $post['id'] ?>" style="padding:6px 10px;">◀ Prev</button>
                                        <span class="course-page-indicator" data-post-id="<?= $post['id'] ?>" style="color:#64748b; font-weight:600;">Page 1 / <?= $total_pages ?></span>
                                        <button class="btn course-page-next" data-post-id="<?= $post['id'] ?>" style="padding:6px 10px;">Next ▶</button>
                                    </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div style="text-align: center; padding: 40px; color: #64748b;">
                                        <p style="margin: 0; font-size: 16px;">No courses configured for this post yet.</p>
                                    </div>
                                <?php endif; ?>
                                
                                <div style="margin-top: 20px; text-align: center;">
                                    <button class="add-course-btn" data-post-id="<?= $post['id'] ?>" style="background: #3b82f6; color: white; padding: 12px 24px; border: none; border-radius: 8px; font-size: 14px; cursor: pointer; font-weight: 600;">
                                        + Add New Course to This Post
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php elseif ($current_section === 'settings'): ?>
                <!-- Settings -->
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">Blog Settings</h2>
                        <p>Configure your blog's appearance and pagination settings.</p>
                    </div>
                    
                    <form method="post" style="max-width: 600px;">
                        <input type="hidden" name="action" value="save_settings">
                        
                        <div class="form-group">
                            <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 5px;">Site Title</label>
                            <input type="text" name="site_title" value="<?= htmlspecialchars($blog_data['settings']['site_title'] ?? 'QuickTrends Educational Blog') ?>" style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 16px;">
                            <small style="color: #6b7280;">This appears in the browser title and header.</small>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">
                            <div class="form-group">
                                <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 5px;">Posts Per Page</label>
                                <input type="number" name="posts_per_page" value="<?= $blog_data['settings']['posts_per_page'] ?? 6 ?>" min="1" max="20" style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 16px;">
                                <small style="color: #6b7280;">Number of blog posts to show per page.</small>
                            </div>
                            
                            <div class="form-group">
                                <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 5px;">Courses Per Page</label>
                                <input type="number" name="courses_per_page" value="<?= $blog_data['settings']['courses_per_page'] ?? 4 ?>" min="1" max="10" style="width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 16px;">
                                <small style="color: #6b7280;">Number of courses to show per post.</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 5px;">Featured Posts Count</label>
                            <input type="number" name="featured_posts_count" value="<?= $blog_data['settings']['featured_posts_count'] ?? 3 ?>" min="1" max="6" style="width: 200px; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 16px;">
                            <small style="color: #6b7280;">Number of featured posts to highlight on homepage.</small>
                        </div>
                        
                        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
                            <button type="submit" class="btn btn-success" style="background: #10b981; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer;">
                                💾 Save Settings
                            </button>
                            <p style="margin-top: 10px; color: #6b7280; font-size: 14px;">
                                <strong>Note:</strong> Changes will take effect immediately on your blog.
                            </p>
                        </div>
                    </form>
                </div>
                
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // IMMEDIATE TEST - This should show in console right away
        console.log('SCRIPT TAG LOADED - JavaScript is working!');
        
        function addResource() {
            const container = document.getElementById('resources-container');
            const resourceItem = document.createElement('div');
            resourceItem.className = 'resource-item';
            resourceItem.innerHTML = `
                <input type="text" name="resource_name[]" placeholder="Resource Name">
                <input type="text" name="resource_url[]" placeholder="https://... or emoji">
                <select name="resource_type[]">
                    <option value="Free Course">Free Course</option>
                    <option value="Free Certification">Free Certification</option>
                    <option value="Tutorial">Tutorial</option>
                    <option value="Book">Book</option>
                    <option value="Documentation">Documentation</option>
                    <option value="Interactive">Interactive</option>
                </select>
                <input type="text" name="resource_desc[]" placeholder="Description">
                <button type="button" class="remove-resource" onclick="this.parentElement.remove()">×</button>
            `;
            container.appendChild(resourceItem);
        }
        
        // Course Management Functions
        function updateCourse(postId, courseId) {
            const courseTitle = document.querySelector('.course-title[data-course-id="' + courseId + '"]').value;
            const courseUrl = document.querySelector('.course-url[data-course-id="' + courseId + '"]').value;
            const courseImage = document.querySelector('.course-image[data-course-id="' + courseId + '"]').value;
            const courseDescription = document.querySelector('.course-description[data-course-id="' + courseId + '"]').value;
            const courseRating = document.querySelector('.course-rating[data-course-id="' + courseId + '"]').value;
            const courseDuration = document.querySelector('.course-duration[data-course-id="' + courseId + '"]').value;
            const courseStudents = document.querySelector('.course-students[data-course-id="' + courseId + '"]').value;
            const courseStatus = document.querySelector('.course-status[data-course-id="' + courseId + '"]').value;
            
            const formData = new FormData();
            formData.append('action', 'update_course');
            formData.append('post_id', postId);
            formData.append('course_id', courseId);
            formData.append('course_title', courseTitle);
            formData.append('course_url', courseUrl);
            formData.append('course_image', courseImage);
            formData.append('course_description', courseDescription);
            formData.append('course_rating', courseRating);
            formData.append('course_duration', courseDuration);
            formData.append('course_students', courseStudents);
            formData.append('course_status', courseStatus);
            
            fetch('blog-manager.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error updating course: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating course');
            });
        }
        
        function deleteCourse(postId, courseId) {
            if (!confirm('Are you sure you want to delete this course?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_course');
            formData.append('post_id', postId);
            formData.append('course_id', courseId);
            
            fetch('blog-manager.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error deleting course: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting course');
            });
        }
        
        function approveCourse(postId, courseId) {
            if (!confirm('Approve this course and make it live on the website?')) return;
            
            const formData = new FormData();
            formData.append('action', 'approve_course');
            formData.append('post_id', postId);
            formData.append('course_id', courseId);
            
            fetch('blog-manager.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error approving course: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error approving course');
            });
        }
        
        function rejectCourse(postId, courseId) {
            if (!confirm('Reject this course? It will not appear on the website.')) return;
            
            const formData = new FormData();
            formData.append('action', 'reject_course');
            formData.append('post_id', postId);
            formData.append('course_id', courseId);
            
            fetch('blog-manager.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error rejecting course: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error rejecting course');
            });
        }

        function addNewCourse(postId) {
            const courseTitle = prompt('Enter course title:');
            if (!courseTitle) return;
            
            const courseUrl = prompt('Enter course URL:');
            if (!courseUrl) return;
            
            const courseImage = prompt('Enter course image URL (240x135):');
            if (!courseImage) return;
            
            const formData = new FormData();
            formData.append('action', 'add_course');
            formData.append('post_id', postId);
            formData.append('course_title', courseTitle);
            formData.append('course_url', courseUrl);
            formData.append('course_image', courseImage);
            formData.append('course_description', 'New course description - please update');
            formData.append('course_rating', '4.5');
            formData.append('course_duration', '10 Hours');
            formData.append('course_students', '25K+');
            
            fetch('blog-manager.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error adding course: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding course');
            });
        }
        
        // Auto-save draft functionality
        let autoSaveTimer;
        const formInputs = document.querySelectorAll('input, textarea, select');
        
        formInputs.forEach(input => {
            input.addEventListener('input', () => {
                clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(() => {
                    const postEl = document.querySelector('[data-post-id]');
                    if (!postEl) return; // Guard: only auto-save when a post context exists
                    const postId = postEl.dataset.postId;
                    const formData = new FormData();
                    formData.append('action', 'auto_save_draft');
                    formData.append('post_id', postId);
                    
                    fetch('blog-manager.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log('Auto-saving draft...');
                        } else {
                            console.error('Error auto-saving draft:', data.message || 'Unknown error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
                }, 3000);
            });
        });
        
        // Test if JavaScript is working
        console.log('JavaScript loaded successfully!');
        
        // GeeksforGeeks Real-time Drag-and-Drop Solution with Live Positioning
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded - Initializing drag and drop');
            initializeDragAndDrop();
            initializeCoursePagination();
            initializeCategoryDropbar();
        });
        
        function initializeDragAndDrop() {
            let draggedItem = null;
            let dragImageEl = null;
            let placeholderEl = null;
            let mouseDragging = false;
            let mouseOffsetY = 0;
            let mouseContainer = null;
            let autoScrollRaf = null;
            let lastMouseClientY = 0;
            let pageSwitchCooldown = false;
            
            // Get all sortable containers
            const containers = document.querySelectorAll('.sortable-courses, .sortable-posts');
            console.log('Found containers:', containers.length);
            
            // Ensure items are draggable for native HTML5 DnD
            document.querySelectorAll('.course-item, .post-item').forEach(el => {
                el.setAttribute('draggable', 'true');
            });
            
            if (containers.length === 0) {
                console.log('No sortable containers found! Looking for .sortable-courses, .sortable-posts');
                console.log('Available elements with "sortable":', document.querySelectorAll('[class*="sortable"]'));
                console.log('Available elements with "courses":', document.querySelectorAll('[class*="courses"]'));
                console.log('Available course items:', document.querySelectorAll('.course-item'));
                return;
            }
            
            // Attach drag listeners to drag handles for consistent behavior
            document.querySelectorAll('.drag-handle').forEach(handle => {
                // Prefer custom mouse-based DnD for reliability and smoothness
                handle.setAttribute('draggable', 'false');
                handle.addEventListener('mousedown', startMouseDrag);
                // Keep native DnD as a fallback (won't trigger due to draggable=false)
                handle.addEventListener('dragstart', handleDragStart);
                handle.addEventListener('dragend', handleDragEnd);
            });

            containers.forEach(container => {
                // Add event listeners for container-level drag events
                container.addEventListener('dragenter', handleDragEnter);
                container.addEventListener('dragover', handleDragOver);
                container.addEventListener('drop', handleDrop);
            });
            
            // Also add global listeners to prevent prohibited cursor
            document.addEventListener('dragenter', function(e) {
                if (draggedItem) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Global dragenter - preventing prohibited cursor');
                } else {
                    console.log('Global dragenter - no draggedItem');
                }
            });
            
            document.addEventListener('dragover', function(e) {
                if (draggedItem) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.dataTransfer.dropEffect = 'move';
                    console.log('Global dragover - setting move cursor');
                } else {
                    console.log('Global dragover - no draggedItem');
                }
            });
            // Ensure continuous dragging even over non-droppable children
            document.addEventListener('drop', function(e) {
                if (draggedItem) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
            
            // Drag start event handler
            let startOrderSnapshot = null;
            let startIndex = -1;
            let lastPlaceholderIndex = -1;
            let movedDuringDrag = false;
            function handleDragStart(event) {
                // Must start from a handle
                const item = event.target.closest('.course-item') || event.target.closest('.post-item');
                if (!event.target.classList.contains('drag-handle') && !event.target.closest('.drag-handle')) {
                    event.preventDefault();
                    return false;
                }
                if (!item) { event.preventDefault(); return false; }
                
                if (item) {
                    event.stopPropagation();
                    draggedItem = item;
                    event.dataTransfer.effectAllowed = 'move';
                    // Non-empty data improves reliability on some browsers
                    event.dataTransfer.setData('text/plain', 'drag');

                    // Create a lightweight drag image to prevent layout jitter
                    const rect = item.getBoundingClientRect();
                    dragImageEl = item.cloneNode(true);
                    dragImageEl.style.width = rect.width + 'px';
                    dragImageEl.style.pointerEvents = 'none';
                    dragImageEl.style.opacity = '0.85';
                    dragImageEl.style.transform = 'none';
                    dragImageEl.style.boxShadow = '0 8px 20px rgba(0,0,0,0.18)';
                    dragImageEl.style.position = 'absolute';
                    dragImageEl.style.top = '-10000px';
                    dragImageEl.style.left = '-10000px';
                    document.body.appendChild(dragImageEl);
                    try { event.dataTransfer.setDragImage(dragImageEl, 20, 20); } catch (e) {}
                    
                    // Create and insert placeholder at the item's current position
                    placeholderEl = document.createElement('div');
                    placeholderEl.className = 'drag-placeholder';
                    placeholderEl.style.height = rect.height + 'px';
                    placeholderEl.style.width = rect.width + 'px';
                    item.parentNode.insertBefore(placeholderEl, item);

                    // Snapshot initial order of the container
                    const container = item.parentNode;
                    if (container.classList.contains('sortable-courses')) {
                        startOrderSnapshot = Array.from(container.querySelectorAll('.course-item')).map(i => i.dataset.courseId);
                        startIndex = Array.from(container.querySelectorAll('.course-item')).indexOf(item);
                    } else if (container.classList.contains('sortable-posts')) {
                        startOrderSnapshot = Array.from(container.querySelectorAll('.post-item')).map(i => i.dataset.postId);
                        startIndex = Array.from(container.querySelectorAll('.post-item')).indexOf(item);
                    } else {
                        startOrderSnapshot = null;
                        startIndex = -1;
                    }
                    lastPlaceholderIndex = startIndex;
                    movedDuringDrag = false;
                    
                    // Add dragging class for visual feedback
                    item.classList.add('dragging');
                    
                    console.log('Drag started successfully for item:', item.dataset.courseId || item.dataset.postId);
                    console.log('draggedItem set to:', draggedItem);
                } else {
                    console.log('No item found for drag start');
                }
            }
            
            // Drag enter event handler - CRITICAL for preventing prohibited cursor
            function handleDragEnter(event) {
                event.preventDefault();
                event.stopPropagation();
                console.log('Drag entered container');
            }
            
            // Drag over event handler - REAL-TIME POSITIONING
            function handleDragOver(event) {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                
                if (!draggedItem) return;
                
                const container = event.currentTarget;
                const afterElement = getDragAfterElement(container, event.clientY);
                
                // Clear all visual feedback first
                container.querySelectorAll('.course-item, .post-item').forEach(el => {
                    el.classList.remove('drag-over');
                });
                
                // Move placeholder only to reduce layout thrash; item follows at dragend
                let newIndex;
                if (afterElement == null) {
                    container.appendChild(placeholderEl);
                    newIndex = Array.from(container.children).indexOf(placeholderEl);
                } else {
                    afterElement.classList.add('drag-over');
                    container.insertBefore(placeholderEl, afterElement);
                    newIndex = Array.from(container.children).indexOf(placeholderEl);
                }
                if (typeof newIndex === 'number' && newIndex !== lastPlaceholderIndex) {
                    movedDuringDrag = true;
                    lastPlaceholderIndex = newIndex;
                }
                
                console.log('Item repositioned in real-time');
            }
            
            // Get the element that should come after the dragged element
            function getDragAfterElement(container, y) {
                const draggableElements = [...container.querySelectorAll('.course-item:not(.dragging), .post-item:not(.dragging)')];
                
                return draggableElements.reduce((closest, child) => {
                    const box = child.getBoundingClientRect();
                    const offset = y - box.top - box.height / 2;
                    
                    if (offset < 0 && offset > closest.offset) {
                        return { offset: offset, element: child };
                    } else {
                        return closest;
                    }
                }, { offset: Number.NEGATIVE_INFINITY }).element;
            }
            
            // Drop event handler
            function handleDrop(event) {
                event.preventDefault();
                event.stopPropagation();
                console.log('Drop event triggered');
                // The item is already in the correct position from dragover
                // Just need to save the order
                return false;
            }
            
            // Drag end event handler
            function handleDragEnd(event) {
                if (draggedItem) {
                    // Remove dragging class
                    draggedItem.classList.remove('dragging');
                    
                    // Clear all visual feedback
                    document.querySelectorAll('.course-item, .post-item').forEach(el => {
                        el.classList.remove('drag-over');
                        el.style.borderTop = '';
                        el.style.borderBottom = '';
                    });
                    
                    // Place the dragged item at the placeholder position
                    const container = placeholderEl ? placeholderEl.parentNode : draggedItem.parentNode;
                    if (placeholderEl) {
                        container.insertBefore(draggedItem, placeholderEl);
                        placeholderEl.remove();
                        placeholderEl = null;
                    }

                    // Save the new order ONLY IF changed
                    let changed = movedDuringDrag;
                    if (container.classList.contains('sortable-courses')) {
                        const current = Array.from(container.querySelectorAll('.course-item')).map(i => i.dataset.courseId);
                        if (!changed) changed = JSON.stringify(current) !== JSON.stringify(startOrderSnapshot);
                        if (changed) saveCourseOrder(container);
                    } else if (container.classList.contains('sortable-posts')) {
                        const current = Array.from(container.querySelectorAll('.post-item')).map(i => i.dataset.postId);
                        if (!changed) changed = JSON.stringify(current) !== JSON.stringify(startOrderSnapshot);
                        if (changed) savePostOrder(container);
                    }
                    
                    draggedItem = null;
                    if (dragImageEl && dragImageEl.parentNode) {
                        dragImageEl.parentNode.removeChild(dragImageEl);
                    }
                    dragImageEl = null;
                    console.log('Drag ended - order ' + (changed ? 'changed and saved' : 'unchanged, skipped saving'));
                }
            }

            // Mouse-based DnD (fallback) — used by mousedown on .drag-handle
            function startMouseDrag(e) {
                const handle = e.target.closest('.drag-handle');
                const item = handle && (handle.closest('.course-item') || handle.closest('.post-item'));
                if (!item) return;
                e.preventDefault();

                // Identify container
                mouseContainer = item.parentElement;
                if (!mouseContainer || (!mouseContainer.classList.contains('sortable-courses') && !mouseContainer.classList.contains('sortable-posts'))) return;

                // Snapshot initial order
                if (mouseContainer.classList.contains('sortable-courses')) {
                    startOrderSnapshot = Array.from(mouseContainer.querySelectorAll('.course-item')).map(i => i.dataset.courseId);
                } else {
                    startOrderSnapshot = Array.from(mouseContainer.querySelectorAll('.post-item')).map(i => i.dataset.postId);
                }

                // Create placeholder
                const rect = item.getBoundingClientRect();
                placeholderEl = document.createElement('div');
                placeholderEl.className = 'drag-placeholder';
                placeholderEl.style.height = rect.height + 'px';
                placeholderEl.style.width = rect.width + 'px';
                mouseContainer.insertBefore(placeholderEl, item);

                // Prepare dragged item (absolute over page)
                mouseOffsetY = e.clientY - rect.top;
                item.classList.add('dragging');
                item.style.position = 'fixed';
                item.style.left = rect.left + 'px';
                item.style.top = (e.clientY - mouseOffsetY) + 'px';
                item.style.width = rect.width + 'px';
                item.style.zIndex = '10000';
                item.style.pointerEvents = 'none';
                draggedItem = item;
                mouseDragging = true;
                movedDuringDrag = false;

                // UX: prevent text selection and show grabbing cursor
                document.body.style.userSelect = 'none';
                document.body.style.cursor = 'grabbing';

                window.addEventListener('mousemove', onMouseMove);
                window.addEventListener('mouseup', onMouseUp, { once: true });

                // Initialize auto-scroll loop
                lastMouseClientY = e.clientY;
                startAutoScrollLoop();
            }

            function onMouseMove(e) {
                if (!mouseDragging || !draggedItem) return;
                // Move the item with cursor
                draggedItem.style.top = (e.clientY - mouseOffsetY) + 'px';
                lastMouseClientY = e.clientY;
                const after = getDragAfterElement(mouseContainer, e.clientY);
                if (after == null) {
                    mouseContainer.appendChild(placeholderEl);
                } else {
                    mouseContainer.insertBefore(placeholderEl, after);
                }
                movedDuringDrag = true;

                // If dragging within a paginated courses container, support page switching
                if (mouseContainer && mouseContainer.classList.contains('sortable-courses')) {
                    maybeSwitchCoursePageOnEdge(mouseContainer, e.clientY);
                }
            }

            function onMouseUp() {
                if (!mouseDragging || !draggedItem) return;
                // Place item at placeholder
                if (placeholderEl && placeholderEl.parentNode) {
                    placeholderEl.parentNode.insertBefore(draggedItem, placeholderEl);
                    placeholderEl.remove();
                    placeholderEl = null;
                }

                // Cleanup styles
                draggedItem.classList.remove('dragging');
                draggedItem.style.position = '';
                draggedItem.style.left = '';
                draggedItem.style.top = '';
                draggedItem.style.width = '';
                draggedItem.style.zIndex = '';
                draggedItem.style.pointerEvents = '';

                // Save if changed
                let changed = false;
                if (mouseContainer.classList.contains('sortable-courses')) {
                    const current = Array.from(mouseContainer.querySelectorAll('.course-item')).map(i => i.dataset.courseId);
                    changed = JSON.stringify(current) !== JSON.stringify(startOrderSnapshot);
                    if (changed) saveCourseOrder(mouseContainer);
                } else if (mouseContainer.classList.contains('sortable-posts')) {
                    const current = Array.from(mouseContainer.querySelectorAll('.post-item')).map(i => i.dataset.postId);
                    changed = JSON.stringify(current) !== JSON.stringify(startOrderSnapshot);
                    if (changed) savePostOrder(mouseContainer);
                }

                // Reset state
                draggedItem = null;
                mouseDragging = false;
                mouseContainer = null;
                window.removeEventListener('mousemove', onMouseMove);

                // Restore body styles
                document.body.style.userSelect = '';
                document.body.style.cursor = '';

                // Stop auto-scroll loop
                if (autoScrollRaf) {
                    cancelAnimationFrame(autoScrollRaf);
                    autoScrollRaf = null;
                }
            }

            // Auto-scroll near viewport edges while dragging
            function startAutoScrollLoop() {
                if (autoScrollRaf) return;
                const loop = () => {
                    if (!mouseDragging) { autoScrollRaf = null; return; }
                    const edge = 80; // px threshold near top/bottom
                    const y = lastMouseClientY;
                    let scrolled = false;
                    if (y < edge) {
                        const amount = Math.ceil((edge - y) / 6);
                        window.scrollBy(0, -amount);
                        scrolled = true;
                    } else if (window.innerHeight - y < edge) {
                        const amount = Math.ceil((edge - (window.innerHeight - y)) / 6);
                        window.scrollBy(0, amount);
                        scrolled = true;
                    }
                    // If we scrolled, re-evaluate placeholder position for current Y
                    if (scrolled && mouseContainer && placeholderEl) {
                        const after = getDragAfterElement(mouseContainer, y);
                        if (after == null) {
                            mouseContainer.appendChild(placeholderEl);
                        } else {
                            mouseContainer.insertBefore(placeholderEl, after);
                        }
                        if (mouseContainer.classList.contains('sortable-courses')) {
                            maybeSwitchCoursePageOnEdge(mouseContainer, y);
                        }
                    }
                    autoScrollRaf = requestAnimationFrame(loop);
                };
                autoScrollRaf = requestAnimationFrame(loop);
            }

            // If near top/bottom of container and container is paginated, switch pages with cooldown
            function maybeSwitchCoursePageOnEdge(container, clientY) {
                const perPage = parseInt(container.dataset.perPage || '0', 10);
                if (!perPage) return;
                const allItems = Array.from(container.querySelectorAll('.course-item'));
                const totalPages = Math.max(1, Math.ceil(allItems.length / perPage));
                const pager = container.parentElement.querySelector('.course-pager');
                if (!pager) return;
                const indicator = pager.querySelector('.course-page-indicator');
                const currentPage = parseInt(container.dataset.currentPage || '1', 10);
                const rect = container.getBoundingClientRect();
                const edge = 60;
                const atTop = (clientY - rect.top) < edge;
                const atBottom = (rect.bottom - clientY) < edge;
                if (pageSwitchCooldown) return;
                if (atTop && currentPage > 1) {
                    switchCoursePage(container, currentPage - 1, indicator);
                    pageSwitchCooldown = true;
                    setTimeout(() => pageSwitchCooldown = false, 400);
                } else if (atBottom && currentPage < totalPages) {
                    switchCoursePage(container, currentPage + 1, indicator);
                    pageSwitchCooldown = true;
                    setTimeout(() => pageSwitchCooldown = false, 400);
                }
            }
        }

        // ---------------- Pagination for courses ----------------
        function initializeCoursePagination() {
            document.querySelectorAll('.sortable-courses').forEach(container => {
                const perPage = parseInt(container.dataset.perPage || '0', 10);
                if (!perPage) return;
                const allItems = Array.from(container.querySelectorAll('.course-item'));
                const totalPages = Math.max(1, Math.ceil(allItems.length / perPage));
                container.dataset.currentPage = container.dataset.currentPage || '1';
                container.dataset.totalPages = String(totalPages);
                const pager = container.parentElement.querySelector('.course-pager');
                const indicator = pager ? pager.querySelector('.course-page-indicator') : null;
                applyCoursePagination(container, parseInt(container.dataset.currentPage, 10), indicator);
            });

            // Button handlers
            document.querySelectorAll('.course-page-prev').forEach(btn => {
                btn.addEventListener('click', () => {
                    const postId = btn.dataset.postId;
                    const container = document.querySelector('.sortable-courses[data-post-id="' + postId + '"]');
                    const indicator = document.querySelector('.course-page-indicator[data-post-id="' + postId + '"]');
                    const page = parseInt(container.dataset.currentPage || '1', 10);
                    if (page > 1) switchCoursePage(container, page - 1, indicator);
                });
            });
            document.querySelectorAll('.course-page-next').forEach(btn => {
                btn.addEventListener('click', () => {
                    const postId = btn.dataset.postId;
                    const container = document.querySelector('.sortable-courses[data-post-id="' + postId + '"]');
                    const indicator = document.querySelector('.course-page-indicator[data-post-id="' + postId + '"]');
                    const page = parseInt(container.dataset.currentPage || '1', 10);
                    const total = parseInt(container.dataset.totalPages || '1', 10);
                    if (page < total) switchCoursePage(container, page + 1, indicator);
                });
            });
        }

        function switchCoursePage(container, newPage, indicator) {
            const perPage = parseInt(container.dataset.perPage || '0', 10);
            if (!perPage) return;
            const allItems = Array.from(container.querySelectorAll('.course-item'));
            const totalPages = Math.max(1, Math.ceil(allItems.length / perPage));
            const page = Math.max(1, Math.min(totalPages, newPage));
            container.dataset.currentPage = String(page);
            container.dataset.totalPages = String(totalPages);
            applyCoursePagination(container, page, indicator);
        }

        function applyCoursePagination(container, page, indicator) {
            const perPage = parseInt(container.dataset.perPage || '0', 10);
            if (!perPage) return;
            const allItems = Array.from(container.querySelectorAll('.course-item'));
            const totalPages = Math.max(1, Math.ceil(allItems.length / perPage));
            const start = (page - 1) * perPage;
            const end = start + perPage;
            allItems.forEach((item, idx) => {
                item.style.display = (idx >= start && idx < end) ? '' : 'none';
            });
            const postId = container.dataset.postId;
            const ind = indicator || document.querySelector('.course-page-indicator[data-post-id="' + postId + '"]');
            if (ind) ind.textContent = 'Page ' + page + ' / ' + totalPages;
        }

        // ---------------- Move posts between categories ----------------
        function initializeCategoryDropbar() {
            // Make post items provide data on native dragstart for category drop
            document.addEventListener('dragstart', function(e) {
                const postItem = e.target.closest('.post-item');
                if (!postItem) return;
                // Only allow when starting from drag-handle or item itself
                if (!e.target.classList.contains('drag-handle') && !e.target.closest('.drag-handle') && !e.target.classList.contains('post-item')) return;
                e.dataTransfer.setData('text/post-id', postItem.dataset.postId);
                e.dataTransfer.effectAllowed = 'copyMove';
            });

            document.querySelectorAll('.category-drop-target').forEach(target => {
                target.addEventListener('dragover', function(e) {
                    // accept post drags
                    if (e.dataTransfer && e.dataTransfer.types && Array.from(e.dataTransfer.types).some(t => t.indexOf('post-id') !== -1)) {
                        e.preventDefault();
                        target.style.background = '#dbeafe';
                    }
                });
                target.addEventListener('dragleave', function() {
                    target.style.background = '#e2e8f0';
                });
                target.addEventListener('drop', function(e) {
                    const data = e.dataTransfer.getData('text/post-id');
                    if (!data) return;
                    e.preventDefault();
                    target.style.background = '#e2e8f0';
                    const postId = data;
                    const newCat = target.dataset.category;
                    const formData = new FormData();
                    formData.append('action', 'move_post_category');
                    formData.append('post_id', postId);
                    formData.append('category', newCat);
                    fetch('blog-manager.php', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(d => {
                            if (d.success) {
                                const msg = document.createElement('div');
                                msg.textContent = 'Post moved to ' + newCat;
                                msg.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #3b82f6; color: white; padding: 10px 20px; border-radius: 6px; z-index: 1000; font-weight: 600;';
                                document.body.appendChild(msg);
                                setTimeout(() => msg.remove(), 2000);
                                location.reload();
                            } else {
                                alert('Failed to move post');
                            }
                        })
                        .catch(err => { console.error(err); alert('Error moving post'); });
                });
            });
        }
        
        // Add event listeners for course management buttons
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('approve-course-btn')) {
                const postId = e.target.dataset.postId;
                const courseId = e.target.dataset.courseId;
                approveCourse(postId, courseId);
            } else if (e.target.classList.contains('reject-course-btn')) {
                const postId = e.target.dataset.postId;
                const courseId = e.target.dataset.courseId;
                rejectCourse(postId, courseId);
            } else if (e.target.classList.contains('update-course-btn')) {
                const postId = e.target.dataset.postId;
                const courseId = e.target.dataset.courseId;
                updateCourse(postId, courseId);
            } else if (e.target.classList.contains('delete-course-btn')) {
                const postId = e.target.dataset.postId;
                const courseId = e.target.dataset.courseId;
                deleteCourse(postId, courseId);
            } else if (e.target.classList.contains('add-course-btn')) {
                const postId = e.target.dataset.postId;
                addNewCourse(postId);
            }
        });
        
        function saveCourseOrder(container) {
            const postId = container.dataset.postId;
            const courseItems = container.querySelectorAll('.course-item');
            const courseOrder = Array.from(courseItems).map(item => item.dataset.courseId);
            
            const formData = new FormData();
            formData.append('action', 'reorder_courses');
            formData.append('post_id', postId);
            formData.append('course_order', JSON.stringify(courseOrder));
            
            fetch('blog-manager.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message briefly
                    const message = document.createElement('div');
                    message.textContent = 'Course order updated!';
                    message.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #10b981; color: white; padding: 10px 20px; border-radius: 6px; z-index: 1000; font-weight: 600';
                    document.body.appendChild(message);
                    setTimeout(() => message.remove(), 2000);
                } else {
                    alert('Error updating course order: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating course order');
            });
        }
        
        function savePostOrder(container) {
            const postItems = container.querySelectorAll('.post-item');
            const postOrder = Array.from(postItems).map(item => item.dataset.postId);
            
            const formData = new FormData();
            formData.append('action', 'reorder_posts');
            formData.append('post_order', JSON.stringify(postOrder));
            
            fetch('blog-manager.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message briefly
                    const message = document.createElement('div');
                    message.textContent = 'Post order updated!';
                    message.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #10b981; color: white; padding: 10px 20px; border-radius: 6px; z-index: 1000; font-weight: 600;';
                    document.body.appendChild(message);
                    setTimeout(() => message.remove(), 2000);
                } else {
                    alert('Error updating post order: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating post order');
            });
        }
    </script>
</body>
</html>
