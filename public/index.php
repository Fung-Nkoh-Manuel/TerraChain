<?php
// public/index.php
session_start();

// =========================================================================
// PRESENTATION SETTING: Toggle the "Close App" button live!
// - Set to true to show the button (adding it).
// - Set to false to hide the button (simulating initial state / removing it).
// =========================================================================
$ENABLE_CLOSER_BUTTON =true; 

// Safety net: you can also toggle it via URL query parameter (e.g., ?closer=0 or ?closer=1)
if (isset($_GET['closer'])) {
    $ENABLE_CLOSER_BUTTON = filter_var($_GET['closer'], FILTER_VALIDATE_BOOLEAN);
}

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
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@300;400;500&family=Playball&family=Great+Vibes&family=Pacifico&display=swap" rel="stylesheet">
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
                <?php if (isset($ENABLE_CLOSER_BUTTON) && $ENABLE_CLOSER_BUTTON): ?>
                <button id="closeAppBtn" class="btn btn-close-app" style="background:#ff3b5c;color:#fff;border:none;padding:10px 18px;border-radius:8px;font-weight:600;cursor:pointer;margin-left:10px;display:inline-flex;align-items:center;gap:6px;transition:all 0.3s cubic-bezier(0.4, 0, 0.2, 1);font-family:'DM Sans',sans-serif;box-shadow:0 4px 12px rgba(255,59,92,0.25);">
                    <span style="font-size:12px;">✕</span> Close App
                </button>
                <?php endif; ?>
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

        // Load real-time stats from the database
        async function loadStats() {
            try {
                const res = await fetch(`${API_BASE}/public/stats`);
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

    <!-- ============================================================ -->
    <!-- 3D GATEFOLD CLOSER ANIMATION OVERLAY & STYLES -->
    <!-- ============================================================ -->
    <div id="app-closer-overlay" class="closer-overlay" style="display:none;">
        <div class="gatefold-viewport">
            <!-- Center Inside Panel (The base card back) -->
            <div class="gatefold-center-base">
                <div class="watermark-center">
                    <span class="watermark-icon">🌍</span>
                    <h4 class="watermark-title">TerraChain</h4>
                    <p class="watermark-subtitle">System Secured</p>
                </div>
            </div>

            <!-- Left Flap (Folds inward from the left) -->
            <div class="gatefold-flap left-flap">
                <!-- Inside Page Content when open -->
                <div class="flap-inside">
                    <span style="font-size:24px; opacity:0.18;">🔒</span>
                </div>
                <!-- Outside Cover Page Content when closed -->
                <div class="flap-outside">
                    <div class="flap-cover-half left-cover-half"></div>
                </div>
            </div>

            <!-- Right Flap (Folds inward from the right) -->
            <div class="gatefold-flap right-flap">
                <!-- Inside Page Content when open -->
                <div class="flap-inside">
                    <span style="font-size:24px; opacity:0.18;">🔑</span>
                </div>
                <!-- Outside Cover Page Content when closed -->
                <div class="flap-outside">
                    <div class="flap-cover-half right-cover-half"></div>
                </div>
            </div>

            <!-- Seamless Front Calligraphy Cover (Fades in over the center base when closed) -->
            <div class="gatefold-front-cover">
                <div class="calligraphy-container">
                    <!-- Main Spiritual Quote (top/center group) -->
                    <div class="quote-wrapper">
                        <div class="text-a-man">A man</div>
                        <div class="banner-without">
                            <span class="banner-text">WITHOUT</span>
                        </div>
                        <div class="text-god-is">God is</div>
                        <div class="text-nothing">nothing</div>
                    </div>
                    
                    <!-- Bottom Signature Acknowledgement -->
                    <div class="text-thank-god">thank God</div>
                </div>
            </div>
        </div>
        
        <!-- Reopen Application Button -->
        <button id="reopenAppBtn" class="btn btn-reopen">
            ↺ Reopen Application
        </button>
    </div>

    <style>
        /* Smooth push-back transition for main landing container */
        .landing-container {
            transition: opacity 1.2s cubic-bezier(0.4, 0, 0.2, 1), transform 1.4s cubic-bezier(0.4, 0, 0.2, 1);
            transform-origin: center center;
        }
        .landing-container.closing-app {
            opacity: 0 !important;
            transform: scale(0.85) translateZ(-150px) !important;
            pointer-events: none !important;
        }

        /* 3D Closer Overlay */
        .closer-overlay {
            position: fixed;
            inset: 0;
            background: radial-gradient(circle at center, #13171d 0%, #07090b 100%);
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            perspective: 2000px;
            opacity: 0;
            transition: opacity 1.2s cubic-bezier(0.25, 1, 0.4, 1);
        }
        .closer-overlay.active {
            opacity: 1;
        }

        /* 3D Gatefold Layout */
        .gatefold-viewport {
            width: 85vw;
            height: 80vh;
            max-width: 1000px;
            max-height: 750px;
            position: relative;
            perspective: 2500px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Center base card panel */
        .gatefold-center-base {
            width: 50%;
            height: 100%;
            position: absolute;
            left: 25%;
            background: linear-gradient(135deg, #07090b 0%, #12151a 100%);
            border: 2px solid #242c35;
            z-index: 1;
            border-radius: 12px;
            box-shadow: 0 15px 45px rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .watermark-center {
            text-align: center;
            opacity: 0.2;
        }

        .watermark-icon {
            font-size: 72px;
            display: block;
            margin-bottom: 16px;
        }

        .watermark-title {
            color: #e8edf2;
            font-family: Syne, sans-serif;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 4px;
            text-transform: uppercase;
        }

        .watermark-subtitle {
            color: #8a9bb0;
            font-size: 13px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-top: 8px;
        }

        /* Symmetrical Gatefold Flaps */
        .gatefold-flap {
            height: 100%;
            width: 25%;
            position: absolute;
            top: 0;
            transform-style: preserve-3d;
            transition: transform 3.5s cubic-bezier(0.25, 1, 0.4, 1);
            z-index: 3;
        }

        .left-flap {
            left: 25%;
            transform-origin: left center;
            transform: rotateY(-180deg); /* Starts open, swung out to the left */
        }

        .right-flap {
            right: 25%;
            transform-origin: right center;
            transform: rotateY(180deg); /* Starts open, swung out to the right */
        }

        /* 3D Flap Faces */
        .flap-inside, .flap-outside {
            position: absolute;
            inset: 0;
            backface-visibility: hidden;
            border: 2px solid #242c35;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .flap-inside {
            background: linear-gradient(to right, #07090b, #12151a);
            z-index: 2;
        }

        .left-flap .flap-inside {
            border-right: none;
            border-radius: 12px 0 0 12px;
        }

        .right-flap .flap-inside {
            border-left: none;
            border-radius: 0 12px 12px 0;
        }

        .flap-outside {
            background: #090b0d;
            transform: rotateY(180deg);
            z-index: 1;
            box-shadow: 0 20px 50px rgba(0,0,0,0.6);
        }

        .left-flap .flap-outside {
            border-right: none;
            border-radius: 12px 0 0 12px;
        }

        .right-flap .flap-outside {
            border-left: none;
            border-radius: 0 12px 12px 0;
        }

        .flap-cover-half {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #090b0e 0%, #151b22 100%);
            box-shadow: inset 0 0 30px rgba(0,0,0,0.85);
        }

        /* Seamless Front Calligraphy Cover (Fades in over center when closed) */
        .gatefold-front-cover {
            width: 50%; /* Sits centered on the folded base card */
            height: 100%;
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%) scale(0.95);
            opacity: 0;
            pointer-events: none;
            z-index: 10;
            transition: all 3.2s cubic-bezier(0.25, 1, 0.4, 1) 1.2s; /* slow elegant fade as flaps meet */
        }

        /* Symmetrical Closed Triggers */
        .gatefold-viewport.closed-state .left-flap {
            transform: rotateY(0deg); /* swings closed to center */
        }

        .gatefold-viewport.closed-state .right-flap {
            transform: rotateY(0deg); /* swings closed to center */
        }

        .gatefold-viewport.closed-state .gatefold-front-cover {
            transform: translate(-50%, -50%) scale(1.08) translateZ(10px);
            opacity: 1;
            pointer-events: auto;
        }

        /* Calligraphy Premium Design */
        .calligraphy-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            height: 100%;
            padding: 30px 24px;
            background: linear-gradient(135deg, #07090b 0%, #151b22 100%);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.05);
            box-shadow: inset 0 0 50px rgba(0,0,0,0.95);
            position: relative;
        }
        
        .calligraphy-container::before {
            content: '';
            position: absolute;
            inset: 8px;
            border: 1px double rgba(255,255,255,0.03);
            border-radius: 10px;
            pointer-events: none;
        }

        /* Quote upper alignment wrapper */
        .quote-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex-grow: 1;
            width: 100%;
            margin-top: 20px;
        }

        .text-a-man {
            font-family: 'Playball', cursive;
            font-size: 68px;
            color: #f3f5f7;
            text-shadow: 0 4px 10px rgba(0,0,0,0.6);
            margin-bottom: 2px;
            transform: rotate(-2deg);
        }

        .banner-without {
            position: relative;
            background: #f3f5f7;
            color: #080a0c;
            font-family: 'Montserrat', sans-serif;
            font-weight: 900;
            font-style: italic;
            font-size: 28px;
            letter-spacing: 6px;
            padding: 6px 44px;
            margin: 18px 0;
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
            transform: rotate(-4deg);
            display: inline-block;
        }

        /* Banner Ribbon Fold Aesthetics */
        .banner-without::before, .banner-without::after {
            content: '';
            position: absolute;
            top: 0;
            border-style: solid;
        }
        .banner-without::before {
            left: -14px;
            border-width: 15px 14px 15px 0;
            border-color: #f3f5f7 transparent #f3f5f7 transparent;
        }
        .banner-without::after {
            right: -14px;
            border-width: 15px 0 15px 14px;
            border-color: #f3f5f7 transparent #f3f5f7 transparent;
        }

        .text-god-is {
            font-family: 'Playball', cursive;
            font-size: 74px;
            color: #00e5a0;
            margin-top: 4px;
            text-shadow: 0 0 15px rgba(0,229,160,0.25);
            transform: rotate(-1deg);
        }

        .text-nothing {
            font-family: 'Great Vibes', cursive;
            font-size: 96px;
            color: #f3f5f7;
            margin-top: -12px;
            line-height: 1;
            text-shadow: 0 4px 12px rgba(0,0,0,0.5);
        }

        .text-thank-god {
            font-family: 'Pacifico', cursive;
            font-size: 26px;
            color: rgba(255, 255, 255, 0.55);
            border-top: 1px solid rgba(255,255,255,0.06);
            padding-top: 16px;
            width: 75%;
            text-align: center;
            letter-spacing: 1.5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
            margin-top: auto;
            margin-bottom: 10px;
            flex-shrink: 0;
        }

        /* Reopen Button Styling */
        .btn-reopen {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            padding: 12px 28px;
            border-radius: 30px;
            color: #e8edf2;
            font-weight: 600;
            cursor: pointer;
            backdrop-filter: blur(10px);
            transition: all 0.5s cubic-bezier(0.25, 1, 0.5, 1);
            opacity: 0;
            pointer-events: none;
            box-shadow: 0 10px 25px rgba(0,0,0,0.25);
            font-family: 'DM Sans', sans-serif;
            letter-spacing: 0.5px;
            z-index: 100000;
        }
        .btn-reopen:hover {
            background: rgba(255,255,255,0.12);
            border-color: #00e5a0;
            color: #00e5a0;
            box-shadow: 0 10px 30px rgba(0,229,160,0.15);
            transform: translateX(-50%) translateY(-2px);
        }
        .btn-reopen.visible {
            opacity: 1;
            pointer-events: auto;
            transform: translateX(-50%) translateY(0);
        }

        /* Responsiveness */
        @media (max-width: 900px) {
            .gatefold-viewport {
                width: 90vw;
                height: 75vh;
            }
            .text-a-man { font-size: 52px; }
            .banner-without { font-size: 20px; padding: 4px 30px; margin: 18px 0; }
            .text-god-is { font-size: 56px; }
            .text-nothing { font-size: 72px; }
            .text-thank-god { font-size: 20px; margin-top: 40px; }
        }

        @media (max-width: 600px) {
            .gatefold-viewport {
                width: 95vw;
                height: 70vh;
            }
            .text-a-man { font-size: 38px; }
            .banner-without { font-size: 15px; padding: 2px 22px; margin: 12px 0; }
            .text-god-is { font-size: 40px; }
            .text-nothing { font-size: 52px; margin-top: -8px; }
            .text-thank-god { font-size: 16px; margin-top: 30px; }
        }
    </style>

    <script>
        // Execution sequence for Close/Open animations
        document.addEventListener('DOMContentLoaded', () => {
            const closeBtn = document.getElementById('closeAppBtn');
            const reopenBtn = document.getElementById('reopenAppBtn');
            const overlay = document.getElementById('app-closer-overlay');
            const landingContainer = document.querySelector('.landing-container');
            const gatefoldViewport = document.querySelector('.gatefold-viewport');

            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    // 1. Hide landing page smoothly
                    landingContainer.classList.add('closing-app');
                    
                    // 2. Display Overlay
                    overlay.style.display = 'flex';
                    
                    setTimeout(() => {
                        overlay.classList.add('active');
                    }, 50);

                    // 3. Symmetrically fold gatefold flaps inward to center
                    setTimeout(() => {
                        gatefoldViewport.classList.add('closed-state');
                    }, 100);

                    // 4. Fade in Reopen button
                    setTimeout(() => {
                        reopenBtn.classList.add('visible');
                    }, 4200);
                });
            }

            if (reopenBtn) {
                reopenBtn.addEventListener('click', () => {
                    // Remove button visibility
                    reopenBtn.classList.remove('visible');
                    
                    // 1. Fold open the gatefold flaps again
                    gatefoldViewport.classList.remove('closed-state');

                    // 2. Fade out overlay
                    setTimeout(() => {
                        overlay.classList.remove('active');
                    }, 2500);

                    // 3. Fully reset overlay & show landing page
                    setTimeout(() => {
                        overlay.style.display = 'none';
                        landingContainer.classList.remove('closing-app');
                    }, 3800);
                });
            }
        });
    </script>
</body>
</html>
