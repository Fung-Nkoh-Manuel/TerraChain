<?php
// public/login.php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — TerraChain</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body class="auth-page">
    <div class="auth-container">
        <a href="./" class="auth-logo">
            <span class="logo-icon">🌍</span>
            <span>Terra<span class="accent">Chain</span></span>
        </a>
        
        <a href="./" class="back-link">
            ← Back to Home
        </a>
        
        <div class="auth-card">
            <h1 class="auth-title">Welcome Back</h1>
            <p class="auth-subtitle">Sign in to your TerraChain account</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form id="loginForm" class="auth-form">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" required 
                           placeholder="Enter your username or email" autocomplete="username">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input">
                        <input type="password" id="password" name="password" required 
                               placeholder="Enter your password" autocomplete="current-password">
                        <button type="button" class="toggle-password" onclick="togglePassword()">👁</button>
                    </div>
                </div>
                
                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember"> Remember me
                    </label>
                    <a href="#" class="forgot-link">Forgot password?</a>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full" id="loginBtn">
                    <span class="btn-text">Sign In</span>
                    <span class="spinner" style="display:none;"></span>
                </button>
            </form>
            
            <div class="auth-divider">
                <span>or</span>
            </div>
            
            <p class="auth-footer">
                Don't have an account? <a href="register.php">Create one</a>
            </p>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('loginBtn');
            const btnText = btn.querySelector('.btn-text');
            const spinner = btn.querySelector('.spinner');
            
            btn.disabled = true;
            btnText.style.display = 'none';
            spinner.style.display = 'inline-block';
            
            const formData = new FormData(this);
            const data = {
                username: formData.get('username'),
                password: formData.get('password')
            };
            
            try {
                const res = await fetch('../api/auth/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await res.json();
                
                if (result.success) {
                    window.location.href = result.data.user.role === 'admin' ? 'admin.php' : 'dashboard.php';
                } else {
                    showError(result.data?.error || result.error || 'Login failed');
                }
            } catch(err) {
                showError('Network error. Please try again.');
            } finally {
                btn.disabled = false;
                btnText.style.display = '';
                spinner.style.display = 'none';
            }
        });
        
        function showError(msg) {
            const existing = document.querySelector('.alert');
            if (existing) existing.remove();
            
            const alert = document.createElement('div');
            alert.className = 'alert alert-error';
            alert.textContent = msg;
            document.querySelector('.auth-card').insertBefore(alert, document.querySelector('.auth-form'));
        }
        
        function togglePassword() {
            const input = document.getElementById('password');
            input.type = input.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>
</html>