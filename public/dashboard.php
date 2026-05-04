<?php
// public/dashboard.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/KYC.php';
require_once __DIR__ . '/../models/Parcel.php';
require_once __DIR__ . '/../services/NotificationService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

$auth = new AuthMiddleware();
$user = $auth->requireAuth();

if ($user['role'] === 'admin') {
    header('Location: admin.php');
    exit;
}

$kycModel = new KYC();
$parcelModel = new Parcel();
$notifService = new NotificationService();

$kyc = $kycModel->getUserKYC($user['id']);
$parcels = $parcelModel->getUserParcels($user['id']);
$notifications = $notifService->getUserNotifications($user['id']);
$unreadCount = $notifService->getUnreadCount($user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — TerraChain</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <a href="dashboard.php" class="sidebar-logo">
                <span class="logo-icon">🌍</span>
                <span>Terra<span class="accent">Chain</span></span>
            </a>
            
            <nav class="sidebar-nav">
                <div class="nav-section">Overview</div>
                <a href="dashboard.php" class="nav-item active">
                    <span class="nav-icon">📊</span> Dashboard
                </a>
                
                <div class="nav-section">Properties</div>
                <a href="#" class="nav-item" onclick="showSection('my-properties')">
                    <span class="nav-icon">🏠</span> My Properties
                </a>
                <a href="#" class="nav-item" onclick="showSection('register')">
                    <span class="nav-icon">➕</span> Register Land
                </a>
                <a href="#" class="nav-item" onclick="showSection('browse')">
                    <span class="nav-icon">🔍</span> Browse All
                </a>
                
                <div class="nav-section">Account</div>
                <a href="#" class="nav-item" onclick="showSection('kyc')">
                    <span class="nav-icon">🪪</span> KYC Verification
                    <?php if (!$kyc || $kyc['status'] === 'not_submitted'): ?>
                        <span class="badge badge-yellow">Required</span>
                    <?php elseif ($kyc['status'] === 'verified'): ?>
                        <span class="badge badge-green">✓</span>
                    <?php endif; ?>
                </a>
                <a href="#" class="nav-item" onclick="showSection('transfers')">
                    <span class="nav-icon">⇄</span> My Transfers
                </a>
                <a href="#" class="nav-item" onclick="showSection('disputes')">
                    <span class="nav-icon">⚖️</span> Disputes
                </a>
                
                <div class="nav-section">Settings</div>
                <a href="#" class="nav-item" onclick="showSection('profile')">
                    <span class="nav-icon">👤</span> Profile
                </a>
                <a href="logout.php" class="nav-item">
                    <span class="nav-icon">🚪</span> Sign Out
                </a>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="main-header">
                <div class="header-left">
                    <h1 class="page-title" id="pageTitle">Dashboard</h1>
                    <p class="page-subtitle" id="pageSubtitle">Welcome back, <?php echo htmlspecialchars($user['full_name']); ?></p>
                </div>
                <div class="header-right">
                    <!-- Notifications -->
                    <div class="notifications-dropdown">
                        <button class="notif-btn" onclick="toggleNotifications()">
                            🔔
                            <?php if ($unreadCount > 0): ?>
                                <span class="notif-badge"><?php echo $unreadCount; ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="notif-panel" id="notifPanel" style="display:none;">
                            <div class="notif-header">
                                <span>Notifications</span>
                                <button onclick="markAllRead()" class="notif-action">Mark all read</button>
                            </div>
                            <div class="notif-list" id="notifList">
                                <?php if (empty($notifications)): ?>
                                    <div class="notif-empty">No notifications</div>
                                <?php else: ?>
                                    <?php foreach ($notifications as $notif): ?>
                                        <div class="notif-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                                            <div class="notif-content">
                                                <strong><?php echo htmlspecialchars($notif['title']); ?></strong>
                                                <p><?php echo htmlspecialchars($notif['message']); ?></p>
                                                <span class="notif-time"><?php echo date('M d, H:i', strtotime($notif['created_at'])); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- User Menu -->
                    <div class="user-menu">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user['full_name'] ?? $user['username'], 0, 2)); ?>
                        </div>
                        <span class="user-name"><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></span>
                    </div>
                </div>
            </header>
            
            <!-- Dashboard Overview -->
            <section id="section-dashboard" class="content-section active">
                <!-- KYC Alert -->
                <?php if (!$kyc || $kyc['status'] === 'not_submitted'): ?>
                    <div class="alert alert-warning">
                        <span class="alert-icon">⚠️</span>
                        <div>
                            <strong>KYC Required</strong>
                            <p>You need to complete identity verification before registering land. 
                               <a href="#" onclick="showSection('kyc')">Complete KYC now →</a></p>
                        </div>
                    </div>
                <?php elseif ($kyc['status'] === 'pending'): ?>
                    <div class="alert alert-info">
                        <span class="alert-icon">⏳</span>
                        <div>
                            <strong>KYC Under Review</strong>
                            <p>Your identity verification is being reviewed. This usually takes 1-2 business days.</p>
                        </div>
                    </div>
                <?php elseif ($kyc['status'] === 'rejected'): ?>
                    <div class="alert alert-error">
                        <span class="alert-icon">❌</span>
                        <div>
                            <strong>KYC Rejected</strong>
                            <p><?php echo htmlspecialchars($kyc['rejection_reason'] ?? 'Please resubmit your documents.'); ?></p>
                            <a href="#" onclick="showSection('kyc')">Resubmit KYC →</a>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">🏠</div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo count($parcels); ?></div>
                            <div class="stat-label">My Properties</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">⏳</div>
                        <div class="stat-info">
                            <div class="stat-value" id="statPending">—</div>
                            <div class="stat-label">Pending Actions</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">✅</div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo $kyc && $kyc['status'] === 'verified' ? '✓' : '—'; ?></div>
                            <div class="stat-label">KYC Status</div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Properties -->
                <div class="card">
                    <div class="card-header">
                        <h2>My Properties</h2>
                        <button class="btn btn-sm btn-outline" onclick="showSection('register')">+ Register New</button>
                    </div>
                    <?php if (empty($parcels)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">🏚️</div>
                            <p>No properties registered yet</p>
                            <button class="btn btn-primary" onclick="showSection('register')">Register Your First Property</button>
                        </div>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Parcel #</th>
                                        <th>Title</th>
                                        <th>Location</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($parcels as $parcel): ?>
                                        <tr>
                                            <td><span class="badge badge-blue"><?php echo htmlspecialchars($parcel['parcel_number']); ?></span></td>
                                            <td><?php echo htmlspecialchars($parcel['title']); ?></td>
                                            <td><?php echo htmlspecialchars($parcel['location_address']); ?></td>
                                            <td><?php echo ucfirst($parcel['property_type']); ?></td>
                                            <td><?php echo statusBadge($parcel['status']); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline" onclick="viewParcel(<?php echo $parcel['id']; ?>)">View</button>
                                                <?php if ($parcel['status'] === 'owned'): ?>
                                                    <button class="btn btn-sm btn-outline" onclick="openTransferModal('<?php echo htmlspecialchars($parcel['parcel_number']); ?>')">Transfer</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            
            <!-- Other Sections (loaded via AJAX) -->
            <section id="section-my-properties" class="content-section"></section>
            <section id="section-register" class="content-section"></section>
            <section id="section-browse" class="content-section"></section>
            <section id="section-kyc" class="content-section"></section>
            <section id="section-transfers" class="content-section"></section>
            <section id="section-disputes" class="content-section"></section>
            <section id="section-profile" class="content-section"></section>
        </main>
    </div>
    
    <script src="assets/js/app.js"></script>
    <script>
        // Toast notification
        function toast(message, type = 'info') {
            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;';
                document.body.appendChild(container);
            }

            const icons = { success: '✅', error: '❌', info: 'ℹ️', warn: '⚠️' };
            const colors = {
                success: '#00e5a0',
                error: '#ff3b5c',
                info: '#4d9eff',
                warn: '#ffcc00'
            };

            const toastEl = document.createElement('div');
            toastEl.style.cssText = `
                background: var(--surface, #1a1f25);
                border: 1px solid var(--border, #242c35);
                border-left: 3px solid ${colors[type] || colors.info};
                border-radius: 8px;
                padding: 14px 18px;
                max-width: 420px;
                display: flex;
                align-items: flex-start;
                gap: 10px;
                font-size: 13px;
                color: #e8edf2;
                box-shadow: 0 8px 32px rgba(0,0,0,0.4);
                cursor: pointer;
            `;
            toastEl.innerHTML = `
                <span style="font-size:16px;">${icons[type] || '🔔'}</span>
                <span style="flex:1;">${message}</span>
                <span style="cursor:pointer;color:#4a5a6a;" onclick="this.parentElement.remove()">✕</span>
            `;
            toastEl.addEventListener('click', () => toastEl.remove());
            container.appendChild(toastEl);

            setTimeout(() => toastEl.remove(), 5000);
        }

        // Section navigation
        function showSection(section) {
            document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
            
            const sectionEl = document.getElementById('section-' + section);
            if (sectionEl) sectionEl.classList.add('active');
            
            // Highlight nav
            event?.target?.closest('.nav-item')?.classList.add('active');
            
            // Load section content
            loadSectionContent(section);
        }

        async function loadSectionContent(section) {
            const el = document.getElementById('section-' + section);
            if (!el || el.dataset.loaded === 'true') return;
            
            // Show loading
            el.innerHTML = '<div class="empty-state"><p>Loading...</p></div>';
            
            try {
                // Load from API instead of direct PHP file
                const endpoints = {
                    'my-properties': '../api/parcels/my',
                    'register': null,  // Form is inline
                    'browse': '../api/parcels/all',
                    'kyc': '../api/kyc/status',
                    'transfers': '../api/transfers/my',
                    'disputes': '../api/disputes/all',
                    'profile': null
                };
                
                if (endpoints[section]) {
                    const res = await fetch(endpoints[section], { credentials: 'same-origin' });
                    const data = await res.json();
                    
                    if (data.success) {
                        renderSectionContent(section, data.data);
                    } else {
                        el.innerHTML = '<div class="empty-state"><p>No data available</p></div>';
                    }
                } else if (section === 'register') {
                    // Registration form is handled separately
                    await loadRegistrationForm(el);
                } else if (section === 'profile') {
                    // Profile section
                    await loadProfileSection(el);
                }
                
                el.dataset.loaded = 'true';
            } catch(e) {
                console.error('Failed to load section:', e);
                el.innerHTML = '<div class="error-state">Failed to load section</div>';
            }
        }

        function renderSectionContent(section, data) {
            const el = document.getElementById('section-' + section);
            
            switch(section) {
                case 'my-properties':
                    renderMyProperties(el, data);
                    break;
                case 'browse':
                    renderBrowseProperties(el, data);
                    break;
                case 'kyc':
                    renderKYCStatus(el, data);
                    break;
                case 'transfers':
                    renderTransfers(el, data);
                    break;
                case 'disputes':
                    renderDisputes(el, data);
                    break;
            }
        }

        function renderMyProperties(el, properties) {
            if (!properties || properties.length === 0) {
                el.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">🏚️</div>
                        <p>No properties registered yet</p>
                        <button class="btn btn-primary" onclick="showSection('register')">Register Your First Property</button>
                    </div>`;
                return;
            }
            
            el.innerHTML = `
                <div class="property-grid">
                    ${properties.map(p => `
                        <div class="property-card">
                            <div class="property-card-header">
                                <div>
                                    <div class="property-card-title">${escapeHtml(p.title)}</div>
                                    <div class="property-card-number">${escapeHtml(p.parcel_number)}</div>
                                </div>
                                <span class="badge badge-green">${escapeHtml(p.status)}</span>
                            </div>
                            <div class="property-card-location">📍 ${escapeHtml(p.location_address)}</div>
                            <div class="property-card-details">
                                <div class="property-detail">
                                    <div class="property-detail-label">Type</div>
                                    <div class="property-detail-value">${escapeHtml(p.property_type)}</div>
                                </div>
                                <div class="property-detail">
                                    <div class="property-detail-label">Size</div>
                                    <div class="property-detail-value">${p.size_sqm || '—'} m²</div>
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>`;
        }

        function renderBrowseProperties(el, properties) {
            if (!properties || properties.length === 0) {
                el.innerHTML = '<div class="empty-state"><p>No properties found</p></div>';
                return;
            }
            
            el.innerHTML = `
                <div class="card">
                    <div class="card-header">
                        <h2>All Registered Properties</h2>
                        <input type="text" id="searchInput" placeholder="Search..." 
                               onkeyup="searchProperties(this.value)" 
                               style="max-width:300px;">
                    </div>
                    <div class="property-grid" id="browseGrid">
                        ${properties.map(p => `
                            <div class="property-card" data-search="${escapeHtml(p.title)} ${escapeHtml(p.location_address)}">
                                <div class="property-card-header">
                                    <div>
                                        <div class="property-card-title">${escapeHtml(p.title)}</div>
                                        <div class="property-card-number">${escapeHtml(p.parcel_number)}</div>
                                    </div>
                                    <span class="badge badge-${p.status === 'owned' ? 'green' : 'yellow'}">${escapeHtml(p.status)}</span>
                                </div>
                                <div class="property-card-location">📍 ${escapeHtml(p.location_address)}</div>
                                <div class="property-card-details">
                                    <div class="property-detail">
                                        <div class="property-detail-label">Type</div>
                                        <div class="property-detail-value">${escapeHtml(p.property_type)}</div>
                                    </div>
                                    <div class="property-detail">
                                        <div class="property-detail-label">Owner</div>
                                        <div class="property-detail-value">${escapeHtml(p.owner_name || 'N/A')}</div>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>`;
        }

        function renderKYCStatus(el, kycData) {
            const status = kycData?.status || 'not_submitted';
            
            const statusConfig = {
                'not_submitted': {
                    class: 'not-submitted',
                    icon: '📋',
                    title: 'KYC Not Submitted',
                    text: 'You need to verify your identity before registering land.',
                    action: '<button class="btn btn-primary" onclick="showKYCDropzone()">Submit KYC Now</button>'
                },
                'pending': {
                    class: 'pending',
                    icon: '⏳',
                    title: 'KYC Under Review',
                    text: 'Your documents are being reviewed. This usually takes 1-2 business days.',
                    action: ''
                },
                'verified': {
                    class: 'verified',
                    icon: '✅',
                    title: 'KYC Verified',
                    text: 'Your identity has been verified. You can now register land.',
                    action: ''
                },
                'rejected': {
                    class: 'rejected',
                    icon: '❌',
                    title: 'KYC Rejected',
                    text: kycData?.rejection_reason || 'Your documents were not accepted.',
                    action: '<button class="btn btn-primary" onclick="showKYCDropzone()">Resubmit KYC</button>'
                }
            };
            
            const cfg = statusConfig[status] || statusConfig['not_submitted'];
            
            el.innerHTML = `
                <div class="card">
                    <div class="card-header">
                        <h2>KYC Verification</h2>
                    </div>
                    <div class="kyc-status ${cfg.class}">
                        <div class="kyc-status-icon">${cfg.icon}</div>
                        <div class="kyc-status-title">${cfg.title}</div>
                        <div class="kyc-status-text">${cfg.text}</div>
                        ${cfg.action ? `<div style="margin-top:16px;">${cfg.action}</div>` : ''}
                    </div>
                    <div id="kycDropzone" style="display:none;">
                        <form id="kycForm" enctype="multipart/form-data">
                            <div class="file-upload-area">
                                <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="file-upload-text">
                                    <div style="font-size:32px;">🪪</div>
                                    <p>Drop KYC documents or <span>click to browse</span></p>
                                    <p style="font-size:12px;color:var(--text3);">National ID, Passport, Utility Bill</p>
                                </div>
                            </div>
                    <button type="submit" class="btn btn-primary btn-full" style="margin-top:16px;">Submit KYC</button>
                </form>
            </div>
        </div>`;
    
    // ✅ Re-initialize file upload areas
    initFileUploadAreas();
    
    // Initialize KYC form
    const kycForm = document.getElementById('kycForm');
    if (kycForm) {
        kycForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            try {
                const res = await fetch('../api/kyc/submit', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (data.success) {
                    toast('KYC submitted! Awaiting verification.', 'success');
                    this.reset();
                    document.getElementById('kycDropzone').style.display = 'none';
                } else {
                    toast(data.data?.error || 'Submission failed', 'error');
                }
            } catch(err) {
                toast('Network error', 'error');
            }
        });
    }
}

        function renderTransfers(el, transfers) {
            if (!transfers || transfers.length === 0) {
                el.innerHTML = `
                    <div class="card">
                        <div class="card-header"><h2>My Transfers</h2></div>
                        <div class="empty-state">
                            <div class="empty-icon">⇄</div>
                            <p>No transfers yet</p>
                        </div>
                    </div>`;
                return;
            }
            
            el.innerHTML = `
                <div class="card">
                    <div class="card-header"><h2>My Transfers</h2></div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr><th>ID</th><th>Parcel</th><th>Type</th><th>Status</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                ${transfers.map(t => `
                                    <tr>
                                        <td>#${t.id}</td>
                                        <td>${escapeHtml(t.parcel_title || 'N/A')}</td>
                                        <td>${escapeHtml(t.transfer_type)}</td>
                                        <td><span class="badge badge-${t.status === 'completed' ? 'green' : 'yellow'}">${t.status}</span></td>
                                        <td>${formatDate(t.created_at)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>`;
        }

        function renderDisputes(el, disputes) {
            if (!disputes || disputes.length === 0) {
                el.innerHTML = `
                    <div class="card">
                        <div class="card-header"><h2>My Disputes</h2></div>
                        <div class="empty-state">
                            <div class="empty-icon">⚖️</div>
                            <p>No disputes filed</p>
                        </div>
                    </div>`;
                return;
            }
            
            el.innerHTML = `
                <div class="card">
                    <div class="card-header"><h2>My Disputes</h2></div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr><th>ID</th><th>Parcel</th><th>Type</th><th>Status</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                ${disputes.map(d => `
                                    <tr>
                                        <td>#${d.id}</td>
                                        <td>${escapeHtml(d.parcel_title || 'N/A')}</td>
                                        <td>${escapeHtml(d.dispute_type)}</td>
                                        <td><span class="badge badge-${d.status === 'open' ? 'yellow' : 'blue'}">${d.status}</span></td>
                                        <td>${formatDate(d.created_at)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>`;
        }

        async function loadRegistrationForm(el) {
            el.innerHTML = `
                <div class="card">
                    <div class="card-header">
                        <h2>Register New Land</h2>
                    </div>
                    <form id="registrationForm" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Title *</label>
                                <input type="text" name="title" required placeholder="e.g. Plot 45A Mfoundi">
                            </div>
                            <div class="form-group">
                                <label>Location *</label>
                                <input type="text" name="location_address" required placeholder="e.g. Yaoundé, Centre, Cameroon">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Size (m²)</label>
                                <input type="number" name="size_sqm" placeholder="500">
                            </div>
                            <div class="form-group">
                                <label>Property Type</label>
                                <select name="property_type">
                                    <option value="residential">Residential</option>
                                    <option value="commercial">Commercial</option>
                                    <option value="agricultural">Agricultural</option>
                                    <option value="industrial">Industrial</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" placeholder="Describe the property..."></textarea>
                        </div>
                        <div class="file-upload-area">
                            <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png">
                            <div class="file-upload-text">
                                <div style="font-size:32px;">📄</div>
                                <p>Drop documents or <span>click to browse</span></p>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-full" style="margin-top:16px;">
                            Submit Registration
                        </button>
                    </form>
                </div>`;
            
            // ✅ Re-initialize file upload areas after content loads
            initFileUploadAreas();
            
            // Initialize form handler
            document.getElementById('registrationForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                try {
                    const res = await fetch('../api/parcels/submit', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    });
                    const data = await res.json();
                    if (data.success) {
                        toast('Registration submitted!', 'success');
                        this.reset();
                        // Reset file upload text
                        const uploadText = this.querySelector('.file-upload-text');
                        if (uploadText) {
                            uploadText.innerHTML = `
                                <div style="font-size:32px;">📄</div>
                                <p>Drop documents or <span>click to browse</span></p>
                            `;
                        }
                    } else {
                        toast(data.data?.error || 'Submission failed', 'error');
                    }
                } catch(err) {
                    toast('Network error', 'error');
                }
            });
        }

        async function loadProfileSection(el) {
            const res = await fetch('../api/auth/me', { credentials: 'same-origin' });
            const data = await res.json();
            const user = data.data?.user || {};
            
            el.innerHTML = `
                <div class="card">
                    <div class="card-header"><h2>My Profile</h2></div>
                    <div class="info-row">
                        <span>Username</span>
                        <span>${escapeHtml(user.username || '—')}</span>
                    </div>
                    <div class="info-row">
                        <span>Email</span>
                        <span>${escapeHtml(user.email || '—')}</span>
                    </div>
                    <div class="info-row">
                        <span>Full Name</span>
                        <span>${escapeHtml(user.full_name || '—')}</span>
                    </div>
                    <div class="info-row">
                        <span>Role</span>
                        <span class="badge badge-blue">${escapeHtml(user.role || 'user')}</span>
                    </div>
                </div>`;
        }

        function searchProperties(query) {
            const cards = document.querySelectorAll('#browseGrid .property-card');
            const q = query.toLowerCase();
            cards.forEach(card => {
                const searchData = card.dataset.search?.toLowerCase() || '';
                card.style.display = searchData.includes(q) ? '' : 'none';
            });
        }

        function showKYCDropzone() {
            const dropzone = document.getElementById('kycDropzone');
            if (dropzone) dropzone.style.display = 'block';
        }

        function formatDate(dateStr) {
            if (!dateStr) return '—';
            return new Date(dateStr).toLocaleDateString('en-US', { 
                month: 'short', day: 'numeric', year: 'numeric' 
            });
        }

        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function toggleNotifications() {
            const panel = document.getElementById('notifPanel');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        }

        function markAllRead() {
            fetch('api/notifications/read-all', { method: 'POST' });
        }

        // ── File Upload "Click to Browse" Handlers ───────────

        // Make all file-upload-area divs clickable
        document.addEventListener('DOMContentLoaded', function() {
            initFileUploadAreas();
        });

        // Also re-initialize after section content loads
        function initFileUploadAreas() {
            document.querySelectorAll('.file-upload-area').forEach(area => {
                // Remove existing handler to prevent duplicates
                area.removeEventListener('click', handleFileAreaClick);
                area.addEventListener('click', handleFileAreaClick);
                
                // Find the input inside
                const input = area.querySelector('input[type="file"]');
                if (input) {
                    // Show file name when selected
                    input.addEventListener('change', function() {
                        const textDiv = area.querySelector('.file-upload-text');
                        if (textDiv && this.files.length > 0) {
                            const fileNames = Array.from(this.files).map(f => f.name).join(', ');
                            textDiv.innerHTML = `
                                <div style="font-size:32px;">✅</div>
                                <p class="file-selected">${fileNames}</p>
                                <p style="font-size:12px;color:var(--text3);">${this.files.length} file(s) selected</p>
                            `;
                        }
                    });
                }
                
                // Drag and drop
                area.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    area.classList.add('drag-over');
                });
                
                area.addEventListener('dragleave', function() {
                    area.classList.remove('drag-over');
                });
                
                area.addEventListener('drop', function(e) {
                    e.preventDefault();
                    area.classList.remove('drag-over');
                    if (input && e.dataTransfer.files.length) {
                        // Assign dropped files to the input
                        const dt = new DataTransfer();
                        for (let file of e.dataTransfer.files) {
                            dt.items.add(file);
                        }
                        input.files = dt.files;
                        input.dispatchEvent(new Event('change'));
                    }
                });
            });
        }

        function handleFileAreaClick(e) {
            // Find the hidden file input and trigger it
            const input = this.querySelector('input[type="file"]');
            if (input) {
                input.click();
            }
        }
    </script>
</body>
</html>

<?php
function statusBadge($status) {
    $badges = [
        'owned' => '<span class="badge badge-green">Owned</span>',
        'pending' => '<span class="badge badge-yellow">Pending</span>',
        'transferred' => '<span class="badge badge-blue">Transferred</span>',
        'disputed' => '<span class="badge badge-red">Disputed</span>',
        'restricted' => '<span class="badge badge-orange">Restricted</span>',
        'rejected' => '<span class="badge badge-red">Rejected</span>',
    ];
    return $badges[$status] ?? "<span class=\"badge\">$status</span>";
}
?>