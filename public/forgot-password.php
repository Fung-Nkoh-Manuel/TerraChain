<?php
// public/forgot-password.php
session_start();
$step = $_GET['step'] ?? 'request'; // request | sent
$error = $_SESSION['reset_error'] ?? null;
$success = $_SESSION['reset_success'] ?? null;
unset($_SESSION['reset_error'], $_SESSION['reset_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — TerraChain</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-container">
        <a href="./" class="auth-logo">
            <span class="logo-icon">🌍</span>
            <span>Terra<span class="accent">Chain</span></span>
        </a>
        
        <a href="login.php" class="back-link">← Back to Login</a>
        
        <div class="auth-card">
            <?php if ($step === 'sent'): ?>
                <h1 class="auth-title">Check Your Email</h1>
                <p class="auth-subtitle">If an account exists with that email, we've sent password reset instructions.</p>
                <div class="alert alert-blue" style="margin: 20px 0; padding: 16px; border-radius: 8px; background: rgba(77, 158, 255, 0.1); border: 1px solid #4d9eff; color: #4d9eff;">
                    <span class="alert-icon">📧</span>
                    <div><strong>Email Sent</strong><p style="margin-top:4px; font-size:14px; opacity:0.8;">Check your inbox and spam folder. The link expires in 30 minutes.</p></div>
                </div>
                <a href="login.php" class="btn btn-outline btn-full">Return to Login</a>
            <?php else: ?>
                <h1 class="auth-title">Forgot Password</h1>
                <p class="auth-subtitle">Enter your email and we'll send you a reset link.</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <form id="forgotForm" class="auth-form">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required placeholder="john@example.com">
                    </div>
                    <button type="submit" class="btn btn-primary btn-full" id="resetBtn">
                        <span class="btn-text">Send Reset Link</span>
                        <span class="spinner" style="display:none;"></span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.getElementById('forgotForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('resetBtn');
            const btnText = btn.querySelector('.btn-text');
            const spinner = btn.querySelector('.spinner');
            
            btn.disabled = true;
            if (btnText) btnText.style.display = 'none';
            if (spinner) spinner.style.display = 'inline-block';
            
            const email = document.getElementById('email').value.trim();
            
            try {
                const res = await fetch('../api/auth/forgot-password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: email })
                });
                const result = await res.json();
                
                if (result.success) {
                    window.location.href = 'forgot-password.php?step=sent';
                } else {
                    showError(result.data?.error || 'Failed to send reset email');
                }
            } catch(err) {
                showError('Network error. Please try again.');
            } finally {
                btn.disabled = false;
                if (btnText) btnText.style.display = '';
                if (spinner) spinner.style.display = 'none';
            }
        });

        function showError(msg) {
            const existing = document.querySelector('.alert-error');
            if (existing) existing.remove();
            const alert = document.createElement('div');
            alert.className = 'alert alert-error';
            alert.textContent = msg;
            document.querySelector('.auth-card').insertBefore(alert, document.querySelector('.auth-form'));
        }
    </script>
</body>
</html>
