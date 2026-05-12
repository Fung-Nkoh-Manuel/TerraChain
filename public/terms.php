<?php
// public/terms.php
session_start();
$loggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service — TerraChain</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <header class="landing-header">
        <a href="./" class="logo">
            <span class="logo-icon">🌍</span>
            <span>Terra<span class="accent">Chain</span></span>
        </a>
        <div class="header-actions">
            <a href="./" class="btn btn-outline">← Back</a>
        </div>
    </header>

    <main style="max-width:800px;margin:100px auto 40px;padding:0 24px;">
        <h1 style="font-family:Syne,sans-serif;font-size:36px;margin-bottom:8px;">Terms of Service</h1>
        <p style="color:#8a9bb0;margin-bottom:30px;">Last updated: <?php echo date('F Y'); ?></p>

        <div class="card" style="margin-bottom:20px; background: #1a1f26; border-radius: 12px; padding: 24px; border: 1px solid #2d3748;">
            <h2 style="font-family:Syne,sans-serif;margin-bottom:12px; color: #fff;">1. Acceptance of Terms</h2>
            <p style="color:#8a9bb0;line-height:1.7;">
                By accessing and using TerraChain, you agree to be bound by these Terms of Service. 
                If you do not agree, please do not use the platform.
            </p>
        </div>

        <div class="card" style="margin-bottom:20px; background: #1a1f26; border-radius: 12px; padding: 24px; border: 1px solid #2d3748;">
            <h2 style="font-family:Syne,sans-serif;margin-bottom:12px; color: #fff;">2. User Responsibilities</h2>
            <p style="color:#8a9bb0;line-height:1.7;">
                You are responsible for providing accurate information during registration, KYC verification, 
                and land registration. Submitting false documents or fraudulent information is prohibited 
                and may result in account termination and legal action.
            </p>
        </div>

        <div class="card" style="margin-bottom:20px; background: #1a1f26; border-radius: 12px; padding: 24px; border: 1px solid #2d3748;">
            <h2 style="font-family:Syne,sans-serif;margin-bottom:12px; color: #fff;">3. Land Registration</h2>
            <p style="color:#8a9bb0;line-height:1.7;">
                TerraChain records land ownership but does not replace legal land registry processes. 
                All registrations are subject to administrative review. The platform provides a digital 
                record that complements official land title documents.
            </p>
        </div>

        <div class="card" style="background: #1a1f26; border-radius: 12px; padding: 24px; border: 1px solid #2d3748;">
            <h2 style="font-family:Syne,sans-serif;margin-bottom:12px; color: #fff;">4. Limitation of Liability</h2>
            <p style="color:#8a9bb0;line-height:1.7;">
                TerraChain is provided "as is" without warranties. We are not liable for damages arising 
                from the use of the platform, including but not limited to data loss, unauthorized access, 
                or errors in land records.
            </p>
        </div>
    </main>

    <footer>
        <div class="footer-content">
            <div class="footer-logo">
                <span>🌍 Terra<span class="accent">Chain</span></span>
                <p>Secure Land Registry System © <?php echo date('Y'); ?></p>
            </div>
            <div class="footer-links">
                <a href="about.php">About</a>
                <a href="privacy.php">Privacy Policy</a>
                <a href="terms.php">Terms of Service</a>
                <a href="contact.php">Contact</a>
            </div>
        </div>
    </footer>
</body>
</html>
