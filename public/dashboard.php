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
            
            try {
                const res = await fetch('api/sections/' + section);
                const html = await res.text();
                el.innerHTML = html;
                el.dataset.loaded = 'true';
            } catch(e) {
                el.innerHTML = '<div class="error-state">Failed to load section</div>';
            }
        }
        
        function logout() {
            fetch('api/auth/logout', { method: 'POST' })
                .then(() => window.location.href = 'index.php');
        }
        
        function toggleNotifications() {
            const panel = document.getElementById('notifPanel');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        }
        
        function markAllRead() {
            fetch('api/notifications/read-all', { method: 'POST' });
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