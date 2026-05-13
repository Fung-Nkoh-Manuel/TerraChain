<?php
// public/contact.php
session_start();
$loggedIn = isset($_SESSION['user_id']);
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
            <?php if ($loggedIn): ?>
                <a href="dashboard.php" class="btn btn-outline">Dashboard</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline">Sign In</a>
                <a href="register.php" class="btn btn-primary">Get Started</a>
            <?php endif; ?>
        </div>
    </header>

    <main style="max-width:600px;margin:100px auto 40px;padding:0 24px;">
        <h1 style="font-family:Syne,sans-serif;font-size:36px;margin-bottom:8px;">Contact Us</h1>
        <p style="color:#8a9bb0;margin-bottom:30px;">Have questions? We'd love to hear from you.</p>

        <div id="contactAlert"></div>

        <div class="card" style="background: #1a1f26; border-radius: 12px; padding: 24px; border: 1px solid #2d3748;">
            <form id="contactForm">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; color: #fff; font-size: 14px;">Your Name *</label>
                    <input type="text" name="name" id="name" required placeholder="John Doe" style="width: 100%; background: #0a0e14; border: 1px solid #2d3748; padding: 12px; border-radius: 8px; color: #fff;">
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; color: #fff; font-size: 14px;">Email Address *</label>
                    <input type="email" name="email" id="email" required placeholder="john@example.com" style="width: 100%; background: #0a0e14; border: 1px solid #2d3748; padding: 12px; border-radius: 8px; color: #fff;">
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; color: #fff; font-size: 14px;">Subject *</label>
                    <select name="subject" id="subject" required style="width: 100%; background: #0a0e14; border: 1px solid #2d3748; padding: 12px; border-radius: 8px; color: #fff;">
                        <option value="">Select a topic...</option>
                        <option value="General Inquiry">General Inquiry</option>
                        <option value="Technical Support">Technical Support</option>
                        <option value="KYC Issue">KYC / Verification Issue</option>
                        <option value="Land Registration">Land Registration Help</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 24px;">
                    <label style="display: block; margin-bottom: 8px; color: #fff; font-size: 14px;">Message *</label>
                    <textarea name="message" id="message" rows="5" required placeholder="Describe your issue or question..." style="width: 100%; background: #0a0e14; border: 1px solid #2d3748; padding: 12px; border-radius: 8px; color: #fff; resize: vertical;"></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-full" id="submitBtn" style="padding: 14px; font-weight: 600;">
                    <span class="btn-text">Send Message</span>
                    <span class="spinner" style="display:none;"></span>
                </button>
            </form>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:20px;">
            <div class="card" style="text-align:center; background: #1a1f26; border-radius: 12px; padding: 20px; border: 1px solid #2d3748;">
                <div style="font-size:32px; margin-bottom: 8px;">📧</div>
                <strong style="color:#e8edf2; display: block;">Email</strong>
                <a href="mailto:terrachain16@gmail.com" style="color:#8a9bb0;font-size:13px; text-decoration: none;">terrachain16@gmail.com</a>
            </div>
            <div class="card" style="text-align:center; background: #1a1f26; border-radius: 12px; padding: 20px; border: 1px solid #2d3748;">
                <div style="font-size:32px; margin-bottom: 8px;">🌐</div>
                <strong style="color:#e8edf2; display: block;">Social</strong>
                <p style="color:#8a9bb0;font-size:13px; margin-top: 4px;">@TerraChainOfficial</p>
            </div>
        </div>
    </main>

    <footer style="margin-top: 60px; padding: 40px 24px; border-top: 1px solid #1a1f26;">
        <div class="footer-content" style="max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <div class="footer-logo">
                <span style="font-family: Syne, sans-serif; font-weight: 800; font-size: 20px;">🌍 Terra<span style="color: #00e5a0;">Chain</span></span>
                <p style="color: #64748b; font-size: 14px; margin-top: 4px;">Secure Land Registry System © <?php echo date('Y'); ?></p>
            </div>
            <div class="footer-links" style="display: flex; gap: 24px;">
                <a href="about.php" style="color: #8a9bb0; text-decoration: none; font-size: 14px;">About</a>
                <a href="privacy.php" style="color: #8a9bb0; text-decoration: none; font-size: 14px;">Privacy Policy</a>
                <a href="terms.php" style="color: #8a9bb0; text-decoration: none; font-size: 14px;">Terms of Service</a>
                <a href="contact.php" style="color: #00e5a0; text-decoration: none; font-size: 14px; font-weight: 600;">Contact</a>
            </div>
        </div>
    </footer>

    <script>
        document.getElementById('contactForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const btnText = btn.querySelector('.btn-text');
            const spinner = btn.querySelector('.spinner');
            const alertDiv = document.getElementById('contactAlert');
            
            btn.disabled = true;
            btnText.style.display = 'none';
            spinner.style.display = 'inline-block';
            alertDiv.innerHTML = '';
            
            const formData = {
                name: document.getElementById('name').value,
                email: document.getElementById('email').value,
                subject: document.getElementById('subject').value,
                message: document.getElementById('message').value
            };
            
            try {
                const res = await fetch('../api/public/contact', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                const data = await res.json();
                
                if (data.success) {
                    alertDiv.innerHTML = `
                        <div style="background: rgba(0, 229, 160, 0.1); border: 1px solid #00e5a0; color: #00e5a0; padding: 16px; border-radius: 12px; margin-bottom: 24px;">
                            <strong>Message Sent!</strong> We'll get back to you soon.
                        </div>
                    `;
                    this.reset();
                } else {
                    throw new Error(data.data?.error || data.error || 'Failed to send message');
                }
            } catch (err) {
                alertDiv.innerHTML = `
                    <div style="background: rgba(255, 77, 77, 0.1); border: 1px solid #ff4d4d; color: #ff4d4d; padding: 16px; border-radius: 12px; margin-bottom: 24px;">
                        <strong>Error:</strong> ${err.message}
                    </div>
                `;
            } finally {
                btn.disabled = false;
                btnText.style.display = '';
                spinner.style.display = 'none';
            }
        });
    </script>
</body>
</html>
