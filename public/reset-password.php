<?php
// public/reset-password.php
session_start();

$token = $_GET['token'] ?? null;
$error = null;
$success = null;

if (!$token) {
    header('Location: login.php');
    exit;
}

// In a real app, you'd check the DB for the token. 
// For this simple implementation, we check the session.
if (!isset($_SESSION['reset_token']) || $_SESSION['reset_token'] !== $token || time() > $_SESSION['reset_expires']) {
    $error = "Invalid or expired reset link. Please request a new one.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — TerraChain</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-container">
        <a href="./" class="auth-logo">
            <span class="logo-icon">🌍</span>
            <span>Terra<span class="accent">Chain</span></span>
        </a>
        
        <div class="auth-card">
            <h1 class="auth-title">Set New Password</h1>
            <p class="auth-subtitle">Choose a strong password for your account.</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <a href="forgot-password.php" class="btn btn-outline btn-full">Request New Link</a>
            <?php else: ?>
                <form id="resetForm" class="auth-form">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" required minlength="8" placeholder="Min. 8 characters">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="Re-enter password">
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-full" id="resetBtn">
                        <span class="btn-text">Update Password</span>
                        <span class="spinner" style="display:none;"></span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.getElementById('resetForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password !== confirm) {
                showError('Passwords do not match');
                return;
            }
            
            const btn = document.getElementById('resetBtn');
            const btnText = btn.querySelector('.btn-text');
            const spinner = btn.querySelector('.spinner');
            
            btn.disabled = true;
            if (btnText) btnText.style.display = 'none';
            if (spinner) spinner.style.display = 'inline-block';
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            try {
                const res = await fetch('../api/auth/reset-password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await res.json();
                
                if (result.success) {
                    alert('Password updated successfully! Please login.');
                    window.location.href = 'login.php';
                } else {
                    showError(result.data?.error || 'Failed to update password');
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
