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
            
            <form id="registerForm" class="auth-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" required 
                               placeholder="John Doe">
                    </div>
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required 
                               placeholder="johndoe" autocomplete="username">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="john@example.com" autocomplete="email">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" 
                               placeholder="+237 6XX XXX XXX">
                    </div>
                    <div class="form-group">
                        <label for="national_id">National ID</label>
                        <input type="text" id="national_id" name="national_id" 
                               placeholder="ID number">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input">
                        <input type="password" id="password" name="password" required 
                               placeholder="Min. 8 characters" minlength="8"
                               autocomplete="new-password">
                        <button type="button" class="toggle-password" onclick="togglePassword()">👁</button>
                    </div>
                    <div class="password-strength" id="passwordStrength"></div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           placeholder="Re-enter your password" autocomplete="new-password">
                    <div class="password-match" id="passwordMatch"></div>
                </div>
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" required> I agree to the 
                        <a href="#" class="terms-link">Terms of Service</a> and 
                        <a href="#" class="terms-link">Privacy Policy</a>
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
        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
            const colors = ['', '#ff3b5c', '#ffcc00', '#4d9eff', '#00e5a0'];
            
            strengthDiv.textContent = labels[strength];
            strengthDiv.style.color = colors[strength];
        });
        
        // Password match checker
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
        
        // Form submission
        document.getElementById('registerForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password !== confirm) {
                showError('Passwords do not match');
                return;
            }
            
            if (password.length < 8) {
                showError('Password must be at least 8 characters');
                return;
            }
            
            const btn = document.getElementById('registerBtn');
            const btnText = btn.querySelector('.btn-text');
            const spinner = btn.querySelector('.spinner');
            
            btn.disabled = true;
            btnText.style.display = 'none';
            spinner.style.display = 'inline-block';
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            delete data.confirm_password;
            
            try {
                const res = await fetch('../api/auth/register', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await res.json();
                
                if (result.success) {
                    window.location.href = 'login.php?registered=1';
                } else {
                    showError(result.data?.error || 'Registration failed');
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