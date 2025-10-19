<?php
/**
 * QuickTrends.in - Contact Page
 * Professional contact page with enhanced design
 */

$message_sent = isset($_GET['success']) && $_GET['success'] == '1';
$success_email = isset($_GET['email']) ? $_GET['email'] : '';
$error_message = '';

// Handle form submission
if ($_POST && isset($_POST['name']) && isset($_POST['email']) && isset($_POST['message'])) {
    $name = htmlspecialchars(trim($_POST['name']));
    $email = htmlspecialchars(trim($_POST['email']));
    $subject = htmlspecialchars(trim($_POST['subject']));
    $inquiry_type = htmlspecialchars(trim($_POST['inquiry_type']));
    $message = htmlspecialchars(trim($_POST['message']));
    
    // Validate required fields
    if (empty($name) || empty($email) || empty($subject) || empty($message) || empty($inquiry_type)) {
        $error_message = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        // Prepare email content
        $to = 'quicktrends.dcma@proton.me';
        $email_subject = '[QuickTrends Contact] ' . $subject;
        
        $email_body = "New contact form submission from QuickTrends website:\n\n";
        $email_body .= "Name: " . $name . "\n";
        $email_body .= "Email: " . $email . "\n";
        $email_body .= "Inquiry Type: " . $inquiry_type . "\n";
        $email_body .= "Subject: " . $subject . "\n\n";
        $email_body .= "Message:\n" . $message . "\n\n";
        $email_body .= "---\n";
        $email_body .= "Submitted on: " . date('Y-m-d H:i:s') . "\n";
        $email_body .= "IP Address: " . $_SERVER['REMOTE_ADDR'] . "\n";
        
        // Email headers
        $headers = array(
            'From' => 'noreply@quicktrends.in',
            'Reply-To' => $email,
            'X-Mailer' => 'PHP/' . phpversion(),
            'Content-Type' => 'text/plain; charset=UTF-8'
        );
        
        // Always save messages to files (regardless of email sending)
        $log_dir = __DIR__ . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        // Save to combined log
        $log_file = $log_dir . DIRECTORY_SEPARATOR . 'contact_messages.txt';
        $log_entry = "\n" . str_repeat("=", 50) . "\n";
        $log_entry .= "Contact Form Submission - " . date('Y-m-d H:i:s') . "\n";
        $log_entry .= str_repeat("=", 50) . "\n";
        $log_entry .= $email_body . "\n";
        
        // Create email-based filename with counter for duplicates
        $email_safe = preg_replace('/[^a-zA-Z0-9@._-]/', '', $email);
        $base_filename = $email_safe . '.txt';
        $counter = 1;
        $final_filename = $base_filename;
        
        // Check if file exists and increment counter
        while (file_exists($log_dir . DIRECTORY_SEPARATOR . $final_filename)) {
            $final_filename = str_replace('.txt', '_' . $counter . '.txt', $base_filename);
            $counter++;
        }
        
        // Save both files (try without LOCK_EX first)
        $combined_saved = file_put_contents($log_file, $log_entry, FILE_APPEND);
        $individual_saved = file_put_contents($log_dir . DIRECTORY_SEPARATOR . $final_filename, $email_body);
        
        // Immediate debug - write to a test file to verify writing works
        $debug_info = "Test write at " . date('Y-m-d H:i:s') . "\n";
        $debug_info .= "Log entry length: " . strlen($log_entry) . "\n";
        $debug_info .= "Email body length: " . strlen($email_body) . "\n";
        $debug_info .= "Combined saved: " . ($combined_saved ? 'YES (' . $combined_saved . ' bytes)' : 'NO') . "\n";
        $debug_info .= "Individual saved: " . ($individual_saved ? 'YES (' . $individual_saved . ' bytes)' : 'NO') . "\n";
        $debug_info .= "Log file path: " . $log_file . "\n";
        $debug_info .= "Individual file path: " . $log_dir . DIRECTORY_SEPARATOR . $final_filename . "\n";
        $debug_info .= "Log file exists after write: " . (file_exists($log_file) ? 'YES' : 'NO') . "\n";
        $debug_info .= "Log file size after write: " . (file_exists($log_file) ? filesize($log_file) : '0') . " bytes\n";
        file_put_contents($log_dir . DIRECTORY_SEPARATOR . 'debug_test.txt', $debug_info);
        
        // Wait a moment and check if file still exists
        sleep(1);
        $debug_info .= "Log file exists after 1 second: " . (file_exists($log_file) ? 'YES' : 'NO') . "\n";
        $debug_info .= "Log file size after 1 second: " . (file_exists($log_file) ? filesize($log_file) : '0') . " bytes\n";
        file_put_contents($log_dir . DIRECTORY_SEPARATOR . 'debug_test.txt', $debug_info);
        
        if ($combined_saved && $individual_saved) {
            $message_sent = true;
            
            // Try to send email (optional - doesn't affect success)
            if (function_exists('mail') && ini_get('sendmail_path')) {
                mail($to, $email_subject, $email_body, implode("\r\n", array_map(function($k, $v) { return "$k: $v"; }, array_keys($headers), $headers)));
            }
        } else {
            $error_message = 'Sorry, there was an error saving your message. Please try again or email us directly at quicktrends.dcma@proton.me';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - QuickTrends | Get in Touch</title>
    <meta name="description" content="Contact QuickTrends for support, feedback, or partnership opportunities. We're here to help with your online learning journey.">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://quicktrends.in/contact.php">
    <link rel="stylesheet" href="qt-ui.css?v=1">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.7;
            color: #1f2937;
            background: #ffffff;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px 0;
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
            padding: 0 30px;
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
            gap: 40px;
        }
        
        .nav-links a {
            text-decoration: none;
            color: #64748b;
            font-weight: 500;
            font-size: 16px;
            transition: color 0.3s;
        }
        
        .nav-links a:hover, .nav-links a.active {
            color: #1e40af;
        }
        
        .hero-section {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: white;
            padding: 100px 0;
            text-align: center;
        }
        
        .hero-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 30px;
        }
        
        .hero-content h1 { 
            font-size: 2.75rem; 
            font-weight: 700; 
            margin-bottom: 25px; 
            line-height: 1.2; 
        }
        
        .hero-content p {
            font-size: 1.3rem;
            opacity: 0.9;
            margin-bottom: 40px;
        }
        
        .hero-features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 40px;
            margin-top: 60px;
        }
        
        .feature-item {
            text-align: center;
        }
        
        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .feature-text {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .section {
            padding: 80px 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 30px;
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 60px;
        }
        
        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 20px;
        }
        
        .section-subtitle {
            font-size: 1.2rem;
            color: #64748b;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .contact-info {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .contact-methods h2 {
            font-size: 1.8rem;
            color: #1f2937;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .contact-methods > p {
            font-size: 1rem;
            color: #4b5563;
            margin-bottom: 40px;
            line-height: 1.6;
        }
        
        .contact-details {
            display: grid;
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .contact-item {
            background: #f8fafc;
            padding: 25px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .contact-item h3 {
            font-size: 1.2rem;
            color: #1f2937;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .contact-item p {
            color: #4b5563;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 8px;
        }
        
        .contact-item a {
            color: #1e40af;
            text-decoration: none;
        }
        
        .contact-primary {
            background: #f8fafc;
            padding: 30px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            margin-bottom: 40px;
            text-align: center;
        }
        
        .contact-primary h3 {
            font-size: 1.4rem;
            color: #1f2937;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .contact-primary p {
            color: #4b5563;
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .email-contact {
            background: white;
            padding: 20px;
            border-radius: 6px;
            border: 2px solid #1e40af;
            display: inline-block;
        }
        
        .email-contact p {
            margin: 0;
            font-size: 1.1rem;
        }
        
        .email-contact a {
            color: #1e40af;
            text-decoration: none;
            font-weight: 600;
        }
        
        .email-contact a:hover {
            text-decoration: underline;
        }
        
        .contact-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .info-item {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .info-item h4 {
            font-size: 1.1rem;
            color: #1f2937;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .info-item p {
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.6;
            margin: 0;
        }
        
        .response-info {
            background: white;
            padding: 25px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        
        .response-info h3 {
            font-size: 1.2rem;
            color: #1f2937;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .contact-wrapper {
            display: block;
        }
        
        .contact-form-section h2 {
            font-size: 1.8rem;
            color: #1f2937;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .contact-form-section > p {
            color: #64748b;
            margin-bottom: 30px;
            font-size: 1rem;
        }
        
        .contact-form {
            background: #f8fafc;
            padding: 30px;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 0.95rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
            background: white;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #1e40af;
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }
        
        .form-group select {
            cursor: pointer;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .submit-btn {
            background: #1e40af;
            color: white;
            padding: 14px 32px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s, transform 0.2s;
            width: 100%;
        }
        
        .submit-btn:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }
        
        .contact-info-section {
            background: #f8fafc;
            padding: 40px 30px;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            margin-top: 40px;
        }
        
        .contact-info-section h3 {
            font-size: 1.8rem;
            color: #1f2937;
            margin-bottom: 30px;
            font-weight: 600;
            text-align: center;
        }
        
        .contact-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .contact-details .contact-item {
            background: white;
            padding: 25px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .contact-details h4 {
            font-size: 1.1rem;
            color: #1f2937;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .contact-details p {
            color: #64748b;
            font-size: 0.95rem;
            margin: 0;
            line-height: 1.6;
        }
        
        .contact-details a {
            color: #1e40af;
            text-decoration: none;
            font-weight: 500;
        }
        
        .contact-details a:hover {
            text-decoration: underline;
        }
        
        .inquiry-types h4 {
            font-size: 1.1rem;
            color: #1f2937;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .inquiry-types ul {
            list-style: none;
            padding: 0;
        }
        
        .inquiry-types li {
            padding: 8px 0;
            font-size: 0.9rem;
            color: #64748b;
            line-height: 1.5;
        }
        
        .inquiry-types strong {
            color: #374151;
        }
        
        .success-message {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .success-message h3 {
            color: #166534;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
        
        .success-message p {
            color: #15803d;
            margin: 0;
        }
        
        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .error-message h3 {
            color: #dc2626;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
        
        .error-message p {
            color: #dc2626;
            margin: 0;
        }
        
        .btn-primary {
            background: #1e40af;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            margin: 0 10px;
            display: inline-block;
            transition: background 0.3s;
        }
        
        .btn-primary:hover {
            background: #1d4ed8;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            margin: 0 10px;
            display: inline-block;
            transition: background 0.3s;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .response-section {
            background: #f8fafc;
            padding: 80px 0;
        }
        
        .response-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
        }
        
        .response-card {
            background: white;
            padding: 40px 30px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .response-card .icon {
            font-size: 2.5rem;
            margin-bottom: 20px;
        }
        
        .response-card h4 {
            font-size: 1.3rem;
            color: #1f2937;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .response-card p {
            color: #64748b;
            font-size: 1rem;
            line-height: 1.6;
        }
        
        .faq-section {
            padding: 80px 0;
        }
        
        .faq-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .faq-item {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
            transition: box-shadow 0.3s;
        }
        
        .faq-item:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .faq-question {
            padding: 25px 30px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            color: #1f2937;
            font-size: 1.1rem;
            transition: background 0.3s;
        }
        
        .faq-question:hover {
            background: #f8fafc;
        }
        
        .faq-question .icon {
            font-size: 1.2rem;
            color: #1e40af;
            transition: transform 0.3s;
        }
        
        .faq-item.active .faq-question .icon {
            transform: rotate(45deg);
        }
        
        .faq-answer {
            padding: 0 30px 25px;
            color: #64748b;
            line-height: 1.7;
            display: none;
        }
        
        .faq-item.active .faq-answer {
            display: block;
        }
        
        .cta-section {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: white;
            padding: 80px 0;
            text-align: center;
        }
        
        .cta-content {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .cta-content h3 {
            font-size: 2.2rem;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .cta-content p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 40px;
        }
        
        .cta-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-primary {
            background: white;
            color: #1e40af;
            padding: 15px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255,255,255,0.3);
        }
        
        .btn-secondary {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 15px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            border: 1px solid rgba(255,255,255,0.3);
            transition: background 0.3s;
        }
        
        .btn-secondary:hover {
            background: rgba(255,255,255,0.25);
        }
        
        .footer {
            background: #111827;
            color: #9ca3af;
            padding: 40px 0 20px;
            text-align: center;
        }
        
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 30px;
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
        
        .footer-bottom {
            border-top: 1px solid #374151;
            padding-top: 20px;
        }
        
        @media (max-width: 768px) {
            .nav-links { display: none; }
            .hero-content h1 { font-size: 2.5rem; }
            .hero-content p { font-size: 1.1rem; }
            .form-row { 
                grid-template-columns: 1fr; 
                gap: 15px; 
            }
            .contact-form { 
                padding: 20px; 
            }
            .contact-info-section { 
                padding: 20px; 
            }
            .contact-details {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .btn-primary, .btn-secondary {
                display: block;
                margin: 10px 0;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <?php $curr = basename($_SERVER['PHP_SELF']); ?>
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

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <h1>Contact QuickTrends</h1>
            <p>Get in touch with our team for support, partnerships, or general inquiries about our educational platform.</p>
        </div>
    </section>

    <!-- Contact Form Section -->
    <section class="section">
        <div class="container">
            <div class="contact-wrapper">
                <div class="contact-form-section">
                    <h2>Send us a Message</h2>
                    <p>Fill out the form below and we'll get back to you within 24-48 hours.</p>
                    
                    <?php if ($message_sent): ?>
                        <div class="success-message">
                            <h3>✅ Query Submitted Successfully!</h3>
                            <p>Thank you for contacting us! We've received your message and will respond to <strong><?php echo htmlspecialchars($success_email); ?></strong> within 24-48 hours.</p>
                        </div>
                        <script>
                            // Auto refresh after 3 seconds to clear the form
                            setTimeout(function() {
                                window.location.href = 'contact.php';
                            }, 3000);
                        </script>
                    <?php elseif ($error_message): ?>
                        <div class="error-message">
                            <h3>❌ Error</h3>
                            <p><?php echo $error_message; ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$message_sent): ?>
                    <form class="contact-form" action="" method="POST">
                        <div class="form-group">
                            <label for="inquiry-type">Inquiry Type *</label>
                            <select id="inquiry-type" name="inquiry_type" required>
                                <option value="">Select your inquiry type</option>
                                <option value="course-support">🎓 Course Support</option>
                                <option value="technical-issue">🔧 Technical Issue</option>
                                <option value="partnership">🤝 Content Partnership</option>
                                <option value="legal">⚖️ Legal/DMCA</option>
                                <option value="feedback">💬 General Feedback</option>
                                <option value="other">❓ Other</option>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Full Name *</label>
                                <input type="text" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">Subject *</label>
                            <input type="text" id="subject" name="subject" value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Message *</label>
                            <textarea id="message" name="message" rows="6" required placeholder="Please provide details about your inquiry..."><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="submit-btn">Send Message</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
                
                <div class="contact-info-section">
                    <h3>Contact Information</h3>
                    <div class="contact-details">
                        <div class="contact-item">
                            <h4>📧 Email</h4>
                            <p><a href="mailto:quicktrends.dcma@proton.me">quicktrends.dcma@proton.me</a></p>
                        </div>
                        
                        <div class="contact-item">
                            <h4>⏰ Response Time</h4>
                            <p>24-48 hours during business days</p>
                        </div>
                        
                        <div class="contact-item">
                            <h4>🌍 Support Hours</h4>
                            <p>Monday - Friday<br>9:00 AM - 6:00 PM (UTC)</p>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </section>


    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
            <div class="footer-links">
                <a href="about.php">About Us</a>
                <a href="contact.php">Contact</a>
                <a href="privacy.php">Privacy Policy</a>
                <a href="terms.php">Terms of Service</a>
                <a href="dmca.php">DMCA Policy</a>
            </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> QuickTrends.in - Supporting Free Education Worldwide</p>
                <p style="margin-top: 10px; opacity: 0.7;">Connecting learners with quality educational content since 2024.</p>
            </div>
        </div>
    </footer>

</body>
</html>
