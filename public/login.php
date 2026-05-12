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
            <h1 class="auth-title" id="authTitle">Welcome Back</h1>
            <p class="auth-subtitle" id="authSubtitle">Sign in to your TerraChain account</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <!-- Standard Login Form -->
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
                    <a href="forgot-password.php" class="forgot-link">Forgot password?</a>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full" id="loginBtn">
                    <span class="btn-text">Sign In</span>
                    <span class="spinner" style="display:none;"></span>
                </button>
            </form>

            <!-- OTP Verification Form (Hidden by default) -->
            <form id="otpForm" class="auth-form" style="display: none;">
                <div class="alert alert-blue" id="otpMessage">
                    A verification code has been sent to your email.
                </div>
                <div class="form-group">
                    <label for="otp">Verification Code (6 digits)</label>
                    <input type="text" id="otp" name="otp" required maxlength="6" pattern="\d{6}"
                           placeholder="000000" style="text-align: center; font-size: 2rem; letter-spacing: 0.5rem; font-family: 'DM Mono', monospace;">
                </div>
                <input type="hidden" id="otpUserId" name="user_id">
                
                <button type="submit" class="btn btn-primary btn-full" id="verifyBtn">
                    <span class="btn-text">Verify & Sign In</span>
                    <span class="spinner" style="display:none;"></span>
                </button>

                <div class="auth-footer" style="margin-top: 1.5rem;">
                    Didn't receive code? <a href="javascript:void(0)" onclick="location.reload()">Try again</a>
                </div>
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
        const loginForm = document.getElementById('loginForm');
        const otpForm = document.getElementById('otpForm');
        const authTitle = document.getElementById('authTitle');
        const authSubtitle = document.getElementById('authSubtitle');

        // Handle Initial Login
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('loginBtn');
            const btnText = btn.querySelector('.btn-text');
            const spinner = btn.querySelector('.spinner');
            
            setLoading(btn, true);
            
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
                    if (result.data.status === 'otp_required') {
                        // Switch to OTP form
                        loginForm.style.display = 'none';
                        otpForm.style.display = 'block';
                        authTitle.textContent = 'Verify Identity';
                        authSubtitle.textContent = 'Enter the code sent to ' + result.data.email;
                        document.getElementById('otpUserId').value = result.data.user_id;
                    } else {
                        // Direct login (if status wasn't otp_required)
                        window.location.href = result.data.user.role === 'admin' ? 'admin.php' : 'dashboard.php';
                    }
                } else {
                    showError(result.data?.error || result.error || 'Login failed');
                }
            } catch(err) {
                showError('Network error. Please try again.');
            } finally {
                setLoading(btn, false);
            }
        });

        // Handle OTP Verification
        otpForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('verifyBtn');
            setLoading(btn, true);
            
            const data = {
                user_id: document.getElementById('otpUserId').value,
                otp: document.getElementById('otp').value
            };
            
            try {
                const res = await fetch('../api/auth/verify-otp', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await res.json();
                
                if (result.success) {
                    window.location.href = result.data.user.role === 'admin' ? 'admin.php' : 'dashboard.php';
                } else {
                    showError(result.data || result.error || 'Verification failed');
                }
            } catch(err) {
                showError('Network error. Please try again.');
            } finally {
                setLoading(btn, false);
            }
        });
        
        function setLoading(btn, isLoading) {
            const btnText = btn.querySelector('.btn-text');
            const spinner = btn.querySelector('.spinner');
            btn.disabled = isLoading;
            if (btnText) btnText.style.display = isLoading ? 'none' : '';
            if (spinner) spinner.style.display = isLoading ? 'inline-block' : 'none';
        }

        function showError(msg) {
            const existing = document.querySelector('.alert-error');
            if (existing) existing.remove();
            
            const alert = document.createElement('div');
            alert.className = 'alert alert-error';
            alert.textContent = msg;
            document.querySelector('.auth-card').insertBefore(alert, authTitle.nextSibling);
        }
        
        function togglePassword() {
            const input = document.getElementById('password');
            input.type = input.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>
</html>