<?php
/**
 * QuickTrends.in - Terms of Service
 * Essential page for Google AdSense approval
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - QuickTrends | User Agreement</title>
    <meta name="description" content="QuickTrends Terms of Service - Read our user agreement, acceptable use policy, and terms for using our educational platform.">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://quicktrends.in/terms.php">
    
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
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
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
            <h1>Terms of Service</h1>
            <p>User agreement for QuickTrends platform</p>
            <p style="font-size: 0.9rem; opacity: 0.8;">Last updated: <?= date('F j, Y') ?></p>
        </div>
    </section>

    <main class="content">
        <div class="container">
            <!-- TOC -->
            <div class="highlight-box" style="background:#f8fafc; border:1px solid #e2e8f0; border-left:none; border-radius:8px;">
                <p style="margin-bottom:8px;"><strong>Contents</strong></p>
                <div style="display:flex; flex-wrap:wrap; gap:12px; font-size:.95rem;">
                    <a href="#accept" style="color:#1e40af; text-decoration:none;">Acceptance</a>
                    <a href="#service" style="color:#1e40af; text-decoration:none;">Service</a>
                    <a href="#acceptable-use" style="color:#1e40af; text-decoration:none;">Acceptable Use</a>
                    <a href="#ip" style="color:#1e40af; text-decoration:none;">Intellectual Property</a>
                    <a href="#privacy" style="color:#1e40af; text-decoration:none;">Privacy</a>
                    <a href="#third-party" style="color:#1e40af; text-decoration:none;">Third‑Party Services</a>
                    <a href="#ads" style="color:#1e40af; text-decoration:none;">Advertising</a>
                    <a href="#disclaimers" style="color:#1e40af; text-decoration:none;">Disclaimers</a>
                    <a href="#liability" style="color:#1e40af; text-decoration:none;">Limitation of Liability</a>
                    <a href="#indemnity" style="color:#1e40af; text-decoration:none;">Indemnification</a>
                    <a href="#termination" style="color:#1e40af; text-decoration:none;">Termination</a>
                    <a href="#law" style="color:#1e40af; text-decoration:none;">Governing Law</a>
                    <a href="#changes" style="color:#1e40af; text-decoration:none;">Changes</a>
                    <a href="#contact" style="color:#1e40af; text-decoration:none;">Contact</a>
                </div>
            </div>

            <div class="highlight-box">
                <p><strong>Important:</strong> Using QuickTrends means you agree to these Terms. If you do not agree, please do not use the service.</p>
            </div>

            <h2 id="accept">1. Acceptance of Terms</h2>
            <p>By accessing QuickTrends (“we”, “us”, “our”), you agree to this agreement.</p>

            <h2 id="service">2. Description of Service</h2>
            <ul>
                <li>Free course discovery and educational resources</li>
                <li>Educational blog and learning guides</li>
                <li>Outbound links to third‑party platforms</li>
            </ul>
            <p>This service is free and supported by advertising.</p>

            <h2 id="acceptable-use">3. Acceptable Use</h2>
            <ul>
                <li>No unlawful, harmful, or unauthorized use</li>
                <li>No attempts to disrupt, reverse engineer, or overload the service</li>
                <li>No automated scraping without prior written consent</li>
                <li>No collection of others’ personal data</li>
                <li>Users under 18 must use the service under guardian supervision</li>
            </ul>

            <h2 id="ip">4. Intellectual Property</h2>
            <ul>
                <li>Site content and brand belong to QuickTrends and licensors</li>
                <li>Third‑party content remains the property of respective owners</li>
            </ul>

            <h2 id="privacy">5. Privacy</h2>
            <p>See our <a href="privacy.php" style="color:#1e40af;">Privacy Policy</a> for how we collect, use, and protect data.</p>

            <h2 id="third-party">6. Third‑Party Services</h2>
            <p>We link to platforms we don’t control. Their terms and privacy policies apply to their services.</p>

            <h2 id="ads">7. Advertising</h2>
            <ul>
                <li>Ads may appear across the site</li>
                <li>Cookies and identifiers may be used for ad delivery</li>
                <li>We are not responsible for third‑party ad content</li>
            </ul>

            <h2 id="disclaimers">8. Disclaimers</h2>
            <ul>
                <li>Service may be unavailable at times for maintenance or issues</li>
                <li>We do not guarantee accuracy, availability, or outcomes of third‑party courses</li>
            </ul>

            <h2 id="liability">9. Limitation of Liability</h2>
            <p>To the fullest extent permitted by law, QuickTrends is not liable for indirect, incidental, or consequential damages arising from use of the service.</p>

            <h2 id="indemnity">10. Indemnification</h2>
            <p>You agree to defend and indemnify QuickTrends against claims arising from your use of the service or breach of these Terms.</p>

            <h2 id="termination">11. Termination</h2>
            <p>We may suspend or terminate access at any time for violations or to protect the service. Upon termination, your right to use the service ends.</p>

            <h2 id="law">12. Governing Law</h2>
            <p>These Terms are governed by applicable local laws. Failure to enforce a provision is not a waiver.</p>

            <h2 id="changes">13. Changes to Terms</h2>
            <p>We may update these Terms. Continued use after updates means you accept the new Terms.</p>

            <h2>14. Severability</h2>
            <p>If any provision of these Terms is held to be unenforceable or invalid, such provision will be changed and interpreted to accomplish the objectives of such provision to the greatest extent possible under applicable law and the remaining provisions will continue in full force and effect.</p>

            <h2 id="contact">14. Contact Information</h2>
            <p>If you have any questions about these Terms of Service, please contact us:</p>
            <ul>
                <li><strong>Email (Support & Legal):</strong> <a href="mailto:quicktrends.dcma@proton.me" style="color: #1e40af;">quicktrends.dcma@proton.me</a></li>
                <li><strong>Website:</strong> <a href="https://quicktrends.in" style="color: #1e40af;">https://quicktrends.in</a></li>
            </ul>

            <div class="highlight-box">
                <p><strong>Agreement:</strong> By continuing to access or use our service after any revisions become effective, you agree to be bound by the revised terms. If you do not agree to the new terms, please stop using the service.</p>
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
