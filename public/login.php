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
                <div class="alert alert-error" id="sessionError"><?php echo htmlspecialchars($error); ?></div>
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
                    <span id="otpTimer" style="float: right; font-weight: bold;"></span>
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
                    Didn't receive code? <a href="javascript:void(0)" onclick="resendOTP()" id="resendLink">Resend OTP</a>
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
        var API_BASE = (function () {
            const path = window.location.pathname;
            if (path.includes('/public/')) {
                const base = path.substring(0, path.indexOf('/public/'));
                return `${base}/public/api`;
            }
            const segments = path.split('/').filter(Boolean);
            if (segments.length > 1) {
                return `/${segments[0]}/api`;
            }
            return '/api';
        })();

        const loginForm = document.getElementById('loginForm');
        const otpForm = document.getElementById('otpForm');
        const authTitle = document.getElementById('authTitle');
        const authSubtitle = document.getElementById('authSubtitle');
        const otpTimer = document.getElementById('otpTimer');
        let otpCountdown = null;
        let otpTimeLeft = 120; // 2 minutes

        // Handle Initial Login
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('loginBtn');
            setLoading(btn, true);
            
            // ✅ Clear any existing error messages
            removeError();
            
            const formData = new FormData(this);
            const data = {
                username: formData.get('username'),
                password: formData.get('password')
            };
            
            try {
                const res = await fetch(`${API_BASE}/auth/login`, {
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
                        document.getElementById('otp').focus();
                        
                        // Start 2-minute countdown
                        startOTPTimer();
                        
                        // ✅ Clear session error
                        await clearSessionError();
                    } else {
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
            
            // ✅ Clear any existing error
            removeError();
            
            const data = {
                user_id: document.getElementById('otpUserId').value,
                otp: document.getElementById('otp').value
            };
            
            try {
                const res = await fetch(`${API_BASE}/auth/verify-otp`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await res.json();
                
                if (result.success) {
                    window.location.href = result.data.user.role === 'admin' ? 'admin.php' : 'dashboard.php';
                } else {
                    showError(result.data || result.error || 'Invalid verification code. Please try again.');
                    document.getElementById('otp').value = '';
                    document.getElementById('otp').focus();
                }
            } catch(err) {
                showError('Network error. Please try again.');
            } finally {
                setLoading(btn, false);
            }
        });
        
        // ✅ Clear session error via API
        async function clearSessionError() {
            try {
                await fetch(`${API_BASE}/auth/clear-error`, {
                    method: 'POST'
                });
            } catch(e) {
                // Silent fail - not critical
            }
        }
        
        // ✅ Remove error from UI
        function removeError() {
            const existing = document.querySelector('.alert-error');
            if (existing) existing.remove();
            
            const sessionError = document.getElementById('sessionError');
            if (sessionError) sessionError.remove();
        }
        
        // ✅ Show error message
        function showError(msg) {
            removeError();
            
            const alert = document.createElement('div');
            alert.className = 'alert alert-error';
            alert.textContent = msg;
            document.querySelector('.auth-card').insertBefore(alert, authTitle.nextSibling);
        }
        
        // ✅ Start 2-minute countdown timer
        function startOTPTimer() {
            otpTimeLeft = 120; // 2 minutes
            const resendLink = document.getElementById('resendLink');
            resendLink.style.display = 'none'; // Hide resend link initially
            
            clearInterval(otpCountdown);
            otpCountdown = setInterval(() => {
                otpTimeLeft--;
                
                const mins = Math.floor(otpTimeLeft / 60);
                const secs = otpTimeLeft % 60;
                otpTimer.textContent = `⏱ ${mins}:${secs.toString().padStart(2, '0')}`;
                
                if (otpTimeLeft <= 0) {
                    clearInterval(otpCountdown);
                    otpTimer.textContent = '⏱ Expired';
                    otpTimer.style.color = '#ef4444';
                    resendLink.style.display = 'inline';
                }
            }, 1000);
            
            otpTimer.style.color = '#00e5a0';
        }
        
        // ✅ Resend OTP
        async function resendOTP() {
            const userId = document.getElementById('otpUserId').value;
            if (!userId) return;
            
            const resendLink = document.getElementById('resendLink');
            const originalText = resendLink.textContent;
            resendLink.textContent = '⏳ Sending...';
            resendLink.style.pointerEvents = 'none';
            
            try {
                const res = await fetch(`${API_BASE}/auth/resend-otp`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: userId })
                });
                
                const result = await res.json();
                
                if (result.success) {
                    const msg = document.getElementById('otpMessage');
                    msg.innerHTML = '🔑 New verification code sent to your email. <span id="otpTimer" style="float: right; font-weight: bold;"></span>';
                    msg.className = 'alert alert-green';
                    
                    // Restart timer
                    startOTPTimer();
                    
                    // Reset OTP input
                    document.getElementById('otp').value = '';
                    document.getElementById('otp').focus();
                } else {
                    showError(result.error || 'Failed to resend OTP. Please try again.');
                }
            } catch(err) {
                showError('Network error. Please try again.');
            } finally {
                resendLink.textContent = 'Resend OTP';
                resendLink.style.pointerEvents = 'auto';
            }
        }
        
        function setLoading(btn, isLoading) {
            const btnText = btn.querySelector('.btn-text');
            const spinner = btn.querySelector('.spinner');
            btn.disabled = isLoading;
            if (btnText) btnText.style.display = isLoading ? 'none' : '';
            if (spinner) spinner.style.display = isLoading ? 'inline-block' : 'none';
        }
        
        function togglePassword() {
            const input = document.getElementById('password');
            input.type = input.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>
</html>