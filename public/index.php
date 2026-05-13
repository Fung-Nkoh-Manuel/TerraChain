<?php
// public/index.php
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/database.php';
    
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT role FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            if ($user['role'] === 'admin') {
                header('Location: admin.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;
        }
    } catch (Exception $e) {
        // Database error — just show the landing page
        session_destroy();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TerraChain — Land Registry</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
    <div class="landing-container">
        <!-- Header -->
        <header class="landing-header">
            <div class="logo">
                <div class="logo-icon">🌍</div>
                <span>Terra<span class="accent">Chain</span></span>
            </div>
            <div class="header-actions">
                <a href="login.php" class="btn btn-outline">Sign In</a>
                <a href="register.php" class="btn btn-primary">Get Started</a>
            </div>
        </header>

        <!-- Hero -->
        <section class="hero">
            <div class="hero-content">
                <div class="hero-badge">Secure Land Registry System</div>
                <h1 class="hero-title">
                    Your Land,<br>
                    <span class="accent-gradient">Securely Registered</span>
                </h1>
                <p class="hero-description">
                    TerraChain provides a transparent, secure, and efficient land registry system. 
                    Register your property, manage transfers, and resolve disputes — all in one place.
                </p>
                <div class="hero-actions">
                    <a href="register.php" class="btn btn-primary btn-lg">Register Now</a>
                    <a href="#features" class="btn btn-outline btn-lg">Learn More</a>
                </div>
                <div class="hero-stats">
                    <div class="stat-item">
                        <span class="stat-number" id="statParcels">—</span>
                        <span class="stat-label">Registered Parcels</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number" id="statUsers">—</span>
                        <span class="stat-label">Verified Users</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">99.9%</span>
                        <span class="stat-label">Uptime</span>
                    </div>
                </div>
            </div>
            <div class="hero-visual">
                <div class="hero-card">
                    <div class="hero-card-icon">🏠</div>
                    <div class="hero-card-title">Property Registration</div>
                    <div class="hero-card-text">Submit your land documents for secure registration and verification</div>
                </div>
                <div class="hero-card offset">
                    <div class="hero-card-icon">⇄</div>
                    <div class="hero-card-title">Ownership Transfer</div>
                    <div class="hero-card-text">Transfer property ownership with full transparency and audit trail</div>
                </div>
                <div class="hero-card">
                    <div class="hero-card-icon">⚖️</div>
                    <div class="hero-card-title">Dispute Resolution</div>
                    <div class="hero-card-text">File and resolve land disputes through our structured process</div>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section id="features" class="features">
            <h2 class="section-title">Why Choose <span class="accent-green">TerraChain</span>?</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🔒</div>
                    <h3>Immutable Records</h3>
                    <p>Once verified, land records cannot be tampered with, ensuring permanent proof of ownership.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">⚡</div>
                    <h3>Fast Processing</h3>
                    <p>Streamlined verification process with quick turnaround on registrations and transfers.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📋</div>
                    <h3>Document Verification</h3>
                    <p>All documents are cryptographically hashed and stored with IPFS for secure retrieval.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">👥</div>
                    <h3>Multi-Role System</h3>
                    <p>Separate roles for users, validators, and administrators ensure proper checks and balances.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔍</div>
                    <h3>Transparent Process</h3>
                    <p>Every action is logged in an audit trail, providing complete transparency for all stakeholders.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🛡️</div>
                    <h3>Dispute Protection</h3>
                    <p>Built-in dispute resolution mechanism with validator voting to protect your rights.</p>
                </div>
            </div>
        </section>

        <!-- How It Works -->
        <section class="how-it-works">
            <h2 class="section-title">How It <span class="accent-green">Works</span></h2>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Create Account</h3>
                    <p>Register with your details and verify your identity through our KYC process.</p>
                </div>
                <div class="step-connector"></div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Submit Documents</h3>
                    <p>Upload your land documents. They are securely stored and cryptographically hashed.</p>
                </div>
                <div class="step-connector"></div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Admin Review</h3>
                    <p>Administrators review and verify your registration for approval.</p>
                </div>
                <div class="step-connector"></div>
                <div class="step">
                    <div class="step-number">4</div>
                    <h3>Confirmation</h3>
                    <p>Once approved, your ownership is confirmed and permanently recorded.</p>
                </div>
            </div>
        </section>

        <!-- Footer -->
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
    </div>

    <script>
        // Load real-time stats from the database
        async function loadStats() {
            try {
                const res = await fetch('../api/public/stats');
                const result = await res.json();
                if (result.success) {
                    // Update Registered Parcels count
                    const parcelEl = document.getElementById('statParcels');
                    if (parcelEl) parcelEl.textContent = result.data.parcels.toLocaleString();
                    
                    // Update Verified Users count
                    const userEl = document.getElementById('statUsers');
                    if (userEl) userEl.textContent = result.data.users.toLocaleString();
                }
            } catch(e) {
                console.error("Failed to load landing stats:", e);
            }
        }
        
        // Initial load
        document.addEventListener('DOMContentLoaded', loadStats);
    </script>
    <script>
        // Remove hash on page load
        if (window.location.hash) {
            setTimeout(function() {
                window.scrollTo({ top: 0, behavior: 'smooth' });
                if (history.replaceState) {
                    history.replaceState(null, null, window.location.pathname + window.location.search);
                }
            }, 100);
        }
        
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    </script>
</body>
</html>