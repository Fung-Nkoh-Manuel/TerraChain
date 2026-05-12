<?php
// public/about.php
session_start();
$loggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About — TerraChain</title>
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
            <?php if ($loggedIn): ?>
                <a href="dashboard.php" class="btn btn-outline">Dashboard</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline">Sign In</a>
                <a href="register.php" class="btn btn-primary">Get Started</a>
            <?php endif; ?>
        </div>
    </header>

    <main style="max-width:800px;margin:100px auto 40px;padding:0 24px;">
        <h1 style="font-family:Syne,sans-serif;font-size:36px;margin-bottom:16px;">About <span style="color:#00e5a0;">TerraChain</span></h1>
        <p style="color:#8a9bb0;font-size:18px;line-height:1.6;margin-bottom:30px;">
            A secure, transparent land registry system built for the modern era.
        </p>

        <div class="card" style="margin-bottom:20px; background: #1a1f26; border-radius: 12px; padding: 24px; border: 1px solid #2d3748;">
            <h2 style="font-family:Syne,sans-serif;margin-bottom:12px; color: #fff;">Our Mission</h2>
            <p style="color:#8a9bb0;line-height:1.7;">
                TerraChain aims to eliminate land fraud, reduce disputes, and provide a transparent, 
                immutable record of land ownership. By combining traditional land registry processes 
                with blockchain technology, we ensure that every land transaction is permanently 
                recorded and verifiable.
            </p>
        </div>

        <div class="card" style="margin-bottom:20px; background: #1a1f26; border-radius: 12px; padding: 24px; border: 1px solid #2d3748;">
            <h2 style="font-family:Syne,sans-serif;margin-bottom:12px; color: #fff;">How It Works</h2>
            <div style="display:grid;gap:16px;">
                <div style="display:flex;gap:14px;align-items:flex-start;">
                    <span style="background:#00e5a0;color:#000;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">1</span>
                    <div><strong style="color:#e8edf2;">Register & Verify</strong><p style="color:#8a9bb0;margin-top:4px;">Create an account and verify your identity through our KYC process.</p></div>
                </div>
                <div style="display:flex;gap:14px;align-items:flex-start;">
                    <span style="background:#00e5a0;color:#000;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">2</span>
                    <div><strong style="color:#e8edf2;">Submit Land Documents</strong><p style="color:#8a9bb0;margin-top:4px;">Upload your property documents. They are cryptographically hashed and stored securely.</p></div>
                </div>
                <div style="display:flex;gap:14px;align-items:flex-start;">
                    <span style="background:#00e5a0;color:#000;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">3</span>
                    <div><strong style="color:#e8edf2;">Admin Verification</strong><p style="color:#8a9bb0;margin-top:4px;">Trained administrators review and approve registrations.</p></div>
                </div>
                <div style="display:flex;gap:14px;align-items:flex-start;">
                    <span style="background:#00e5a0;color:#000;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">4</span>
                    <div><strong style="color:#e8edf2;">Permanent Record</strong><p style="color:#8a9bb0;margin-top:4px;">Once approved, ownership is recorded and becomes publicly verifiable.</p></div>
                </div>
            </div>
        </div>

        <div class="card" style="background: #1a1f26; border-radius: 12px; padding: 24px; border: 1px solid #2d3748;">
            <h2 style="font-family:Syne,sans-serif;margin-bottom:12px; color: #fff;">Technology</h2>
            <p style="color:#8a9bb0;line-height:1.7;">
                TerraChain uses a hybrid architecture: traditional web technology for user experience 
                and blockchain for critical ownership records. This ensures fast performance while 
                maintaining the security and immutability that blockchain provides.
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
