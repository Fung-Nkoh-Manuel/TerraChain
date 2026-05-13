<?php
// public/privacy.php
session_start();
$loggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy — TerraChain</title>
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
        <h1 style="font-family:Syne,sans-serif;font-size:36px;margin-bottom:8px;">Privacy Policy</h1>
        <p style="color:#8a9bb0;margin-bottom:30px;">Last updated: <?php echo date('F Y'); ?></p>

        <div class="card" style="margin-bottom:20px; background: #1a1f26; border-radius: 12px; padding: 24px; border: 1px solid #2d3748;">
            <h2 style="font-family:Syne,sans-serif;margin-bottom:12px; color: #fff;">1. Information We Collect</h2>
            <p style="color:#8a9bb0;line-height:1.7;">
                We collect information you provide directly: full name, email address, phone number, 
                national ID, and identity verification documents. We also collect land ownership 
                documents and transaction records.
            </p>
        </div>

        <div class="card" style="margin-bottom:20px; background: #1a1f26; border-radius: 12px; padding: 24px; border: 1px solid #2d3748;">
            <h2 style="font-family:Syne,sans-serif;margin-bottom:12px; color: #fff;">2. How We Use Your Information</h2>
            <p style="color:#8a9bb0;line-height:1.7;">
                Your information is used solely for land registry purposes: identity verification, 
                ownership recording, transfer processing, and dispute resolution. We do not sell 
                or share your personal data with third parties.
            </p>
        </div>

        <div class="card" style="margin-bottom:20px; background: #1a1f26; border-radius: 12px; padding: 24px; border: 1px solid #2d3748;">
            <h2 style="font-family:Syne,sans-serif;margin-bottom:12px; color: #fff;">3. Document Storage</h2>
            <p style="color:#8a9bb0;line-height:1.7;">
                Land documents are cryptographically hashed and stored on IPFS (InterPlanetary File System). 
                Document hashes are recorded on the blockchain for immutability. Only authorized 
                administrators can access full document contents.
            </p>
        </div>

        <div class="card" style="margin-bottom:20px; background: #1a1f26; border-radius: 12px; padding: 24px; border: 1px solid #2d3748;">
            <h2 style="font-family:Syne,sans-serif;margin-bottom:12px; color: #fff;">4. Blockchain Transparency</h2>
            <p style="color:#8a9bb0;line-height:1.7;">
                Land ownership records are stored on a public blockchain for transparency. The blockchain 
                stores document hashes and wallet addresses — not your personal information. Your identity 
                is protected while ownership is publicly verifiable.
            </p>
        </div>

        <div class="card" style="background: #1a1f26; border-radius: 12px; padding: 24px; border: 1px solid #2d3748;">
            <h2 style="font-family:Syne,sans-serif;margin-bottom:12px; color: #fff;">5. Contact</h2>
            <p style="color:#8a9bb0;line-height:1.7;">
                For privacy concerns, contact us at <a href="mailto:terrachain16@gmail.com" style="color:#00e5a0; text-decoration: none;">terrachain16@gmail.com</a>.
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
