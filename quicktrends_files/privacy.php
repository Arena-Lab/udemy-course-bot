<?php
/**
 * QuickTrends.in - Privacy Policy
 * CRITICAL page for Google AdSense approval
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - QuickTrends | Data Protection & Privacy</title>
    <meta name="description" content="QuickTrends Privacy Policy - Learn how we collect, use, and protect your personal information. Your privacy is important to us.">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://quicktrends.in/privacy.php">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
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
            color: #4f46e5;
            text-decoration: none;
        }
        
        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
        }
        
        .nav-links a {
            text-decoration: none;
            color: #64748b;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .nav-links a:hover {
            color: #1e40af;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .page-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            color: white;
            padding: 3rem 0;
            text-align: center;
        }
        
        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .content {
            padding: 3rem 0;
        }
        
        .content h2 {
            color: #1f2937;
            margin-bottom: 1rem;
            margin-top: 2rem;
            font-size: 1.5rem;
        }
        
        .content h2:first-child {
            margin-top: 0;
        }
        
        .content h3 {
            color: #374151;
            margin-bottom: 0.5rem;
            margin-top: 1.5rem;
        }
        
        .content p {
            margin-bottom: 1rem;
            color: #4b5563;
        }
        
        .content ul {
            margin-left: 2rem;
            margin-bottom: 1rem;
            color: #4b5563;
        }
        
        .content li {
            margin-bottom: 0.5rem;
        }
        
        .highlight-box {
            background: #f0f9ff;
            border-left: 4px solid #0ea5e9;
            padding: 1rem;
            margin: 1.5rem 0;
            border-radius: 0 8px 8px 0;
        }
        
        .footer {
            background: #111827;
            color: #9ca3af;
            padding: 2rem 0;
            text-align: center;
            margin-top: 3rem;
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
            .nav-links { display: none; }
            .page-header h1 { font-size: 2rem; }
            .footer-links { flex-direction: column; gap: 1rem; }
        }
    </style>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "EducationalOrganization",
        "name": "QuickTrends",
        "url": "https://quicktrends.in",
        "contactPoint": {
            "@type": "ContactPoint",
            "email": "quicktrends.dcma@proton.me",
            "contactType": "customer service"
        }
    }
    </script>
</head>
<body>
    <header class="header">
        <nav class="nav">
            <a href="/" class="logo">🎓 QuickTrends</a>
            <ul class="nav-links">
                <li><a href="/">Home</a></li>
                <li><a href="blog.php">Blog</a></li>
                <li><a href="about.php">About</a></li>
                <li><a href="contact.php">Contact</a></li>
            </ul>
        </nav>
    </header>

    <section class="page-header">
        <div class="container">
            <h1>Privacy Policy</h1>
            <p>Your privacy is important to us</p>
            <p style="font-size: 0.9rem; opacity: 0.8;">Last updated: <?= date('F j, Y') ?></p>
        </div>
    </section>

    <main class="content">
        <div class="container">
            <!-- Table of Contents -->
            <div class="highlight-box" style="background:#f8fafc; border:1px solid #e2e8f0; border-left:none; border-radius:8px;">
                <p style="margin-bottom:8px;"><strong>Contents</strong></p>
                <div style="display:flex; flex-wrap:wrap; gap:12px; font-size:.95rem;">
                    <a href="#info-collect" style="color:#1e40af; text-decoration:none;">Information We Collect</a>
                    <a href="#use" style="color:#1e40af; text-decoration:none;">How We Use Info</a>
                    <a href="#cookies" style="color:#1e40af; text-decoration:none;">Cookies</a>
                    <a href="#cookie-controls" style="color:#1e40af; text-decoration:none;">Cookie Controls</a>
                    <a href="#third-parties" style="color:#1e40af; text-decoration:none;">Third-Party Services</a>
                    <a href="#sharing" style="color:#1e40af; text-decoration:none;">Data Sharing</a>
                    <a href="#security" style="color:#1e40af; text-decoration:none;">Security</a>
                    <a href="#rights" style="color:#1e40af; text-decoration:none;">Your Rights</a>
                    <a href="#dsr" style="color:#1e40af; text-decoration:none;">Data Subject Requests</a>
                    <a href="#retention" style="color:#1e40af; text-decoration:none;">Retention</a>
                    <a href="#changes" style="color:#1e40af; text-decoration:none;">Changes</a>
                    <a href="#contact" style="color:#1e40af; text-decoration:none;">Contact</a>
                </div>
            </div>
            <div class="highlight-box">
                <p><strong>Quick Summary:</strong> QuickTrends respects your privacy. We collect minimal data to provide our educational services, use cookies for functionality and analytics, and never sell your personal information. We comply with GDPR, CCPA, and other privacy regulations.</p>
            </div>

            <h2 id="info-collect">1. Information We Collect</h2>
            
            <h3>Information You Provide</h3>
            <ul>
                <li>Contact information when you email us (name, email address)</li>
                <li>Feedback and suggestions you submit</li>
                <li>Any information you voluntarily provide in communications</li>
            </ul>

            <h3>Information Automatically Collected</h3>
            <ul>
                <li>IP address and general location information</li>
                <li>Browser type and version</li>
                <li>Device information (type, operating system)</li>
                <li>Pages visited and time spent on our site</li>
                <li>Referral source (how you found our website)</li>
                <li>Click data and user interactions</li>
            </ul>

            <h2 id="use">2. How We Use Your Information</h2>
            <p>We use the collected information for the following purposes:</p>
            <ul>
                <li><strong>Service Provision:</strong> To provide access to educational content and maintain our platform</li>
                <li><strong>Communication:</strong> To respond to your inquiries and provide customer support</li>
                <li><strong>Analytics:</strong> To understand how our website is used and improve user experience</li>
                <li><strong>Security:</strong> To protect against fraud, abuse, and security threats</li>
                <li><strong>Legal Compliance:</strong> To comply with applicable laws and regulations</li>
            </ul>

            <h2 id="cookies">3. Cookies and Tracking Technologies</h2>
            <p>We use cookies and similar technologies to enhance your browsing experience:</p>
            
            <h3>Essential Cookies</h3>
            <ul>
                <li>Required for basic website functionality</li>
                <li>Cannot be disabled without affecting site performance</li>
            </ul>

            <h3>Analytics Cookies</h3>
            <ul>
                <li>Help us understand website usage patterns</li>
                <li>Used to improve our services and user experience</li>
                <li>May include Google Analytics and similar services</li>
            </ul>

            <h3>Advertising Cookies</h3>
            <ul>
                <li>Used to display relevant advertisements</li>
                <li>May be set by third-party advertising networks</li>
                <li>Help support our free educational platform</li>
            </ul>

            <h2 id="cookie-controls">4. Cookie Controls</h2>
            <ul>
                <li><strong>Browser settings:</strong> You can block or delete cookies in your browser preferences. Disabling essential cookies may impact site functionality.</li>
                <li><strong>Analytics opt-out:</strong> If Google Analytics is used, you can install the <a href="https://tools.google.com/dlpage/gaoptout" target="_blank" rel="noopener" style="color:#1e40af;">GA Opt-out Add-on</a>.</li>
                <li><strong>Ad settings:</strong> Manage Google Ad settings at <a href="https://adssettings.google.com" target="_blank" rel="noopener" style="color:#1e40af;">adssettings.google.com</a>.</li>
            </ul>

            <h2 id="third-parties">5. Third-Party Services</h2>
            <p>We work with trusted third-party services to provide our platform:</p>
            
            <h3>Google Services</h3>
            <ul>
                <li><strong>Google Analytics:</strong> Website analytics and user behavior tracking</li>
                <li><strong>Google AdSense:</strong> Advertising services to support our platform</li>
                <li>Subject to Google's Privacy Policy</li>
            </ul>

            <h3>Educational Platforms</h3>
            <ul>
                <li>We redirect users to external educational platforms (Udemy, Coursera, etc.)</li>
                <li>These platforms have their own privacy policies</li>
                <li>We are not responsible for their data practices</li>
            </ul>

            <h2 id="sharing">6. Data Sharing and Disclosure</h2>
            <p>We do not sell, trade, or rent your personal information. We may share information in these limited circumstances:</p>
            <ul>
                <li><strong>Service Providers:</strong> With trusted partners who help operate our website</li>
                <li><strong>Legal Requirements:</strong> When required by law or to protect our rights</li>
                <li><strong>Business Transfers:</strong> In case of merger, acquisition, or sale of assets</li>
                <li><strong>Consent:</strong> When you explicitly consent to sharing</li>
            </ul>

            <h2 id="security">7. Data Security</h2>
            <p>We implement appropriate security measures to protect your information:</p>
            <ul>
                <li>SSL encryption for data transmission</li>
                <li>Regular security updates and monitoring</li>
                <li>Limited access to personal information</li>
                <li>Secure hosting infrastructure</li>
            </ul>

            <h2 id="rights">8. Your Privacy Rights</h2>
            <p>Depending on your location, you may have the following rights:</p>
            
            <h3>GDPR Rights (EU Residents)</h3>
            <ul>
                <li>Right to access your personal data</li>
                <li>Right to rectify inaccurate information</li>
                <li>Right to erasure ("right to be forgotten")</li>
                <li>Right to restrict processing</li>
                <li>Right to data portability</li>
                <li>Right to object to processing</li>
            </ul>

            <h3>CCPA Rights (California Residents)</h3>
            <ul>
                <li>Right to know what personal information is collected</li>
                <li>Right to delete personal information</li>
                <li>Right to opt-out of sale of personal information</li>
                <li>Right to non-discrimination</li>
            </ul>

            <h2 id="dsr">9. Data Subject Requests (DSR)</h2>
            <p>You can request access, correction, deletion, or objection to processing of your personal data.</p>
            <ol style="margin-left:1.25rem; color:#4b5563;">
                <li>Email <a href="mailto:quicktrends.dcma@proton.me" style="color:#1e40af;">quicktrends.dcma@proton.me</a> with subject “Privacy Request”.</li>
                <li>Include: your name, the request type (access/rectify/delete/object), and the email used on our site.</li>
                <li>We will verify your identity and respond within 30 days (or as required by law).</li>
            </ol>

            <h2 id="children">10. Children's Privacy</h2>
            <p>Our website is not intended for children under 13 years of age. We do not knowingly collect personal information from children under 13. If you believe we have collected information from a child under 13, please contact us immediately.</p>

            <h2 id="international">11. International Data Transfers</h2>
            <p>Your information may be transferred to and processed in countries other than your own. We ensure appropriate safeguards are in place to protect your information during such transfers.</p>

            <h2 id="retention">12. Data Retention</h2>
            <p>We retain your information only as long as necessary to provide our services and comply with legal obligations. Analytics data is typically retained for 26 months, while contact information is kept until you request deletion.</p>

            <h2 id="changes">13. Changes to This Policy</h2>
            <p>We may update this Privacy Policy from time to time. We will notify you of any material changes by posting the new policy on this page and updating the "Last updated" date. Your continued use of our website after changes constitutes acceptance of the updated policy.</p>

            <h2 id="contact">14. Contact Information</h2>
            <p>If you have questions about this Privacy Policy or want to exercise your privacy rights, please contact us:</p>
            <ul>
                <li><strong>Email:</strong> <a href="mailto:quicktrends.dcma@proton.me" style="color: #1e40af;">quicktrends.dcma@proton.me</a></li>
                <li><strong>Address:</strong> QuickTrends, Online Education Platform</li>
            </ul>

            <div class="highlight-box">
                <p><strong>Questions?</strong> We're committed to transparency about our data practices. If you have any questions or concerns about how we handle your information, please don't hesitate to reach out to us.</p>
            </div>
        </div>
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
</body>
</html>
