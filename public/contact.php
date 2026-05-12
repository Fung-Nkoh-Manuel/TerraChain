<?php
// public/contact.php
session_start();
$loggedIn = isset($_SESSION['user_id']);
$sent = $_GET['sent'] ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact — TerraChain</title>
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

    <main style="max-width:600px;margin:100px auto 40px;padding:0 24px;">
        <h1 style="font-family:Syne,sans-serif;font-size:36px;margin-bottom:8px;">Contact Us</h1>
        <p style="color:#8a9bb0;margin-bottom:30px;">Have questions? We'd love to hear from you.</p>

        <?php if ($sent): ?>
            <div class="alert alert-success" style="background: rgba(0, 229, 160, 0.1); border: 1px solid #00e5a0; color: #00e5a0; padding: 16px; border-radius: 8px; margin-bottom: 24px; display: flex; gap: 12px; align-items: center;">
                <span class="alert-icon" style="font-size: 20px;">✅</span>
                <div><strong>Message Sent!</strong><p style="margin-top: 4px; font-size: 14px; opacity: 0.9;">We'll get back to you within 24 hours.</p></div>
            </div>
        <?php endif; ?>

        <div class="card" style="background: #1a1f26; border-radius: 12px; padding: 24px; border: 1px solid #2d3748;">
            <form method="POST" action="">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; color: #fff; font-size: 14px;">Your Name *</label>
                    <input type="text" name="name" required placeholder="John Doe" style="width: 100%; background: #0a0e14; border: 1px solid #2d3748; padding: 12px; border-radius: 8px; color: #fff;">
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; color: #fff; font-size: 14px;">Email Address *</label>
                    <input type="email" name="email" required placeholder="john@example.com" style="width: 100%; background: #0a0e14; border: 1px solid #2d3748; padding: 12px; border-radius: 8px; color: #fff;">
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; color: #fff; font-size: 14px;">Subject *</label>
                    <select name="subject" required style="width: 100%; background: #0a0e14; border: 1px solid #2d3748; padding: 12px; border-radius: 8px; color: #fff;">
                        <option value="">Select a topic...</option>
                        <option value="general">General Inquiry</option>
                        <option value="support">Technical Support</option>
                        <option value="kyc">KYC / Verification Issue</option>
                        <option value="registration">Land Registration Help</option>
                        <option value="dispute">Dispute Resolution</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 24px;">
                    <label style="display: block; margin-bottom: 8px; color: #fff; font-size: 14px;">Message *</label>
                    <textarea name="message" rows="5" required placeholder="Describe your issue or question..." style="width: 100%; background: #0a0e14; border: 1px solid #2d3748; padding: 12px; border-radius: 8px; color: #fff; resize: vertical;"></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-weight: 600;">Send Message</button>
            </form>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:20px;">
            <div class="card" style="text-align:center; background: #1a1f26; border-radius: 12px; padding: 20px; border: 1px solid #2d3748;">
                <div style="font-size:32px; margin-bottom: 8px;">📧</div>
                <strong style="color:#e8edf2; display: block;">Email</strong>
                <p style="color:#8a9bb0;font-size:13px; margin-top: 4px;">support@terrachain16.com</p>
            </div>
            <div class="card" style="text-align:center; background: #1a1f26; border-radius: 12px; padding: 20px; border: 1px solid #2d3748;">
                <div style="font-size:32px; margin-bottom: 8px;">📞</div>
                <strong style="color:#e8edf2; display: block;">Phone</strong>
                <p style="color:#8a9bb0;font-size:13px; margin-top: 4px;">+237 6XX XXX XXX</p>
            </div>
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
