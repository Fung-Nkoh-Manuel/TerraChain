<?php
// public/register.php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — TerraChain</title>
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
            <h1 class="auth-title">Create Account</h1>
            <p class="auth-subtitle">Join TerraChain to manage your land records</p>
            
            <div id="formErrors" style="display:none;"></div>
            
            <form id="registerForm" class="auth-form" novalidate>
                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" required 
                               placeholder="John Doe" autocomplete="name"
                               pattern="^[A-Za-z\s\-']{2,50}$"
                               title="2-50 letters, spaces, hyphens, and apostrophes only">
                    </div>
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" required 
                               placeholder="johndoe" autocomplete="username"
                               pattern="^[A-Za-z0-9_]{3,20}$"
                               title="3-20 letters, numbers, and underscores only">
                        <small style="color:var(--text3);font-size:11px;">3-20 characters, letters/numbers/underscore only</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="john@example.com" autocomplete="email"
                           pattern="^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$"
                           title="Enter a valid email address (e.g., name@domain.com)">
                    <small style="color:var(--text3);font-size:11px;">Must be a valid email address</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" 
                               placeholder="+237 6XX XXX XXX"
                               pattern="^(\+?[0-9]{1,4}[\s\-]?)?[0-9\s\-]{7,15}$"
                               title="Enter a valid phone number">
                    </div>
                    <div class="form-group">
                        <label for="national_id">National ID</label>
                        <input type="text" id="national_id" name="national_id" 
                               placeholder="ID number"
                               pattern="^[A-Za-z0-9\-]{5,30}$"
                               title="5-30 letters, numbers, and hyphens only">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password *</label>
                    <div class="password-input">
                        <input type="password" id="password" name="password" required 
                               placeholder="Min. 8 characters" minlength="8"
                               autocomplete="new-password">
                        <button type="button" class="toggle-password" onclick="togglePassword()">👁</button>
                    </div>
                    <div class="password-strength" id="passwordStrength"></div>
                    <div class="password-requirements" id="passwordRequirements" style="font-size:11px;color:var(--text3);margin-top:4px;">
                        <span id="reqLength">⬜ At least 8 characters</span><br>
                        <span id="reqUpperLower">⬜ Uppercase & lowercase letters</span><br>
                        <span id="reqNumber">⬜ At least one number</span><br>
                        <span id="reqSpecial">⬜ At least one special character (!@#$%^&*)</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           placeholder="Re-enter your password" autocomplete="new-password">
                    <div class="password-match" id="passwordMatch"></div>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="termsCheckbox" required> I agree to the 
                        <a href="terms.php" class="terms-link">Terms of Service</a> and 
                        <a href="privacy.php" class="terms-link">Privacy Policy</a>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full" id="registerBtn">
                    <span class="btn-text">Create Account</span>
                    <span class="spinner" style="display:none;"></span>
                </button>
            </form>
            
            <p class="auth-footer">
                Already have an account? <a href="login.php">Sign in</a>
            </p>
        </div>
    </div>

    <script>
        const API_BASE = (function () {
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

        // ── Password Requirements Tracking ──────────────────
        function updatePasswordRequirements(password) {
            const checks = {
                length: password.length >= 8,
                upperLower: /[a-z]/.test(password) && /[A-Z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[^A-Za-z0-9]/.test(password)
            };

            const icons = {
                length: document.getElementById('reqLength'),
                upperLower: document.getElementById('reqUpperLower'),
                number: document.getElementById('reqNumber'),
                special: document.getElementById('reqSpecial')
            };

            Object.keys(checks).forEach(key => {
                const el = icons[key];
                if (checks[key]) {
                    el.innerHTML = el.innerHTML.replace('⬜', '✅');
                    el.style.color = '#00e5a0';
                } else {
                    el.innerHTML = el.innerHTML.replace('✅', '⬜');
                    el.style.color = 'var(--text3)';
                }
            });

            return checks;
        }

        // ── Password Strength Checker ──────────────────────
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            // Update requirements
            const checks = updatePasswordRequirements(password);
            
            // Calculate strength
            let strength = 0;
            if (checks.length) strength++;
            if (checks.upperLower) strength++;
            if (checks.number) strength++;
            if (checks.special) strength++;
            
            const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
            const colors = ['', '#ff3b5c', '#ffcc00', '#4d9eff', '#00e5a0'];
            const widths = ['', '25%', '50%', '75%', '100%'];
            
            if (password.length === 0) {
                strengthDiv.textContent = '';
                strengthDiv.style.background = '';
                return;
            }
            
            strengthDiv.textContent = labels[strength];
            strengthDiv.style.color = colors[strength];
            strengthDiv.style.background = `linear-gradient(to right, ${colors[strength]} ${widths[strength]}, var(--bg2) ${widths[strength]})`;
            strengthDiv.style.padding = '2px 8px';
            strengthDiv.style.borderRadius = '4px';
            strengthDiv.style.border = `1px solid ${colors[strength]}`;
        });

        // ── Password Match Checker ──────────────────────────
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (this.value === '') {
                matchDiv.textContent = '';
            } else if (this.value === password) {
                matchDiv.textContent = '✓ Passwords match';
                matchDiv.style.color = '#00e5a0';
            } else {
                matchDiv.textContent = '✗ Passwords do not match';
                matchDiv.style.color = '#ff3b5c';
            }
        });

        // ── Email Validation ─────────────────────────────────
        document.getElementById('email').addEventListener('input', function() {
            const email = this.value;
            const valid = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(email);
            
            if (email.length > 0 && !valid) {
                this.style.borderColor = '#ff3b5c';
                this.title = 'Please enter a valid email address';
            } else {
                this.style.borderColor = '';
                this.title = '';
            }
        });

        // ── Username Validation ─────────────────────────────
        document.getElementById('username').addEventListener('input', function() {
            const username = this.value;
            const valid = /^[A-Za-z0-9_]{3,20}$/.test(username);
            
            if (username.length > 0 && !valid) {
                this.style.borderColor = '#ff3b5c';
                this.title = '3-20 characters, letters/numbers/underscore only';
            } else {
                this.style.borderColor = '';
                this.title = '';
            }
        });

        // ── Form Submission ──────────────────────────────────
        document.getElementById('registerForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // ── Clear previous errors ──────────────────────
            const errorContainer = document.getElementById('formErrors');
            errorContainer.style.display = 'none';
            errorContainer.innerHTML = '';
            
            // ── Get values ──────────────────────────────────
            const fullName = document.getElementById('full_name').value.trim();
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const nationalId = document.getElementById('national_id').value.trim();
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const termsChecked = document.getElementById('termsCheckbox').checked;
            
            const errors = [];
            
            // ── Full Name Validation ──────────────────────
            if (fullName.length < 2 || fullName.length > 50) {
                errors.push('Full name must be 2-50 characters');
            }
            if (!/^[A-Za-z\s\-']{2,50}$/.test(fullName)) {
                errors.push('Full name can only contain letters, spaces, hyphens, and apostrophes');
            }
            
            // ── Username Validation ────────────────────────
            if (!/^[A-Za-z0-9_]{3,20}$/.test(username)) {
                errors.push('Username must be 3-20 characters (letters, numbers, underscores only)');
            }
            
            // ── Email Validation ────────────────────────────
            if (!/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(email)) {
                errors.push('Please enter a valid email address');
            }
            
            // ── Phone Validation (optional) ─────────────────
            if (phone && !/^(\+?[0-9]{1,4}[\s\-]?)?[0-9\s\-]{7,15}$/.test(phone)) {
                errors.push('Please enter a valid phone number');
            }
            
            // ── National ID Validation (optional) ───────────
            if (nationalId && !/^[A-Za-z0-9\-]{5,30}$/.test(nationalId)) {
                errors.push('National ID must be 5-30 characters (letters, numbers, hyphens only)');
            }
            
            // ── Password Validation ─────────────────────────
            if (password.length < 8) {
                errors.push('Password must be at least 8 characters');
            }
            if (!/[a-z]/.test(password) || !/[A-Z]/.test(password)) {
                errors.push('Password must contain both uppercase and lowercase letters');
            }
            if (!/[0-9]/.test(password)) {
                errors.push('Password must contain at least one number');
            }
            if (!/[^A-Za-z0-9]/.test(password)) {
                errors.push('Password must contain at least one special character (!@#$%^&*)');
            }
            
            // ── Password Match ──────────────────────────────
            if (password !== confirm) {
                errors.push('Passwords do not match');
            }
            
            // ── Terms Check ─────────────────────────────────
            if (!termsChecked) {
                errors.push('You must agree to the Terms of Service and Privacy Policy');
            }
            
            // ── Show errors ─────────────────────────────────
            if (errors.length > 0) {
                errorContainer.style.display = 'block';
                errorContainer.className = 'alert alert-error';
                errorContainer.innerHTML = `
                    <span class="alert-icon">❌</span>
                    <div>
                        <strong>Please fix the following errors:</strong>
                        <ul style="margin:8px 0 0 18px;color:var(--text2);">
                            ${errors.map(e => `<li>${e}</li>`).join('')}
                        </ul>
                    </div>
                `;
                // Scroll to errors
                errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            
            // ── Submit Registration ─────────────────────────
            const btn = document.getElementById('registerBtn');
            const btnText = btn.querySelector('.btn-text');
            const spinner = btn.querySelector('.spinner');
            
            btn.disabled = true;
            btnText.style.display = 'none';
            spinner.style.display = 'inline-block';
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            delete data.confirm_password;
            delete data.termsCheckbox;
            
            try {
                const res = await fetch(`${API_BASE}/auth/register`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await res.json();
                
                if (result.success) {
                    window.location.href = 'login.php?registered=1';
                } else {
                    const msg = result.data?.error || result.error || 'Registration failed';
                    errorContainer.style.display = 'block';
                    errorContainer.className = 'alert alert-error';
                    errorContainer.innerHTML = `
                        <span class="alert-icon">❌</span>
                        <div>
                            <strong>Registration Failed</strong>
                            <p style="margin-top:4px;">${escapeHtml(msg)}</p>
                        </div>
                    `;
                }
            } catch(err) {
                errorContainer.style.display = 'block';
                errorContainer.className = 'alert alert-error';
                errorContainer.innerHTML = `
                    <span class="alert-icon">❌</span>
                    <div>
                        <strong>Network Error</strong>
                        <p style="margin-top:4px;">Please check your internet connection and try again.</p>
                    </div>
                `;
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
        
        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    </script>
</body>
</html>