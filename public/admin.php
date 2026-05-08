<?php
// public/admin.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/KYC.php';
require_once __DIR__ . '/../models/Parcel.php';
require_once __DIR__ . '/../models/Transfer.php';
require_once __DIR__ . '/../models/Dispute.php';
require_once __DIR__ . '/../services/BlockchainService.php';
require_once __DIR__ . '/../services/NotificationService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

$auth = new AuthMiddleware();
$admin = $auth->requireAdmin();

$kycModel = new KYC();
$parcelModel = new Parcel();
$transferModel = new Transfer();
$disputeModel = new Dispute();
$blockchain = new BlockchainService();
$notifService = new NotificationService();

$pendingKYC = $kycModel->getPendingKYC();
$pendingRegistrations = $parcelModel->getPendingRegistrations();
$pendingTransfers = $transferModel->getAll();
$disputes = $disputeModel->getAll();
$notifications = $notifService->getUserNotifications($admin['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel — TerraChain</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ethers/6.7.0/ethers.umd.min.js"></script>
</head>
<body>
    <div class="app-layout">
        <!-- Admin Sidebar -->
        <aside class="sidebar admin-sidebar">
            <a href="/admin.php" class="sidebar-logo">
                <span class="logo-icon">⚙️</span>
                <span>Admin <span class="accent">Panel</span></span>
            </a>
            
            <nav class="sidebar-nav">
                <a href="#" class="nav-item active" onclick="switchTab('overview', this)">
                    📊 Overview
                    <?php if (count($pendingKYC) + count($pendingRegistrations) > 0): ?>
                        <span class="badge badge-red"><?php echo count($pendingKYC) + count($pendingRegistrations); ?></span>
                    <?php endif; ?>
                </a>
                <a href="#" class="nav-item" onclick="switchTab('registrations', this)">
                    📋 Registrations
                    <?php if (count($pendingRegistrations) > 0): ?>
                        <span class="badge badge-yellow"><?php echo count($pendingRegistrations); ?></span>
                    <?php endif; ?>
                </a>
                <a href="#" class="nav-item" onclick="switchTab('kyc', this)">
                    🪪 KYC Verification
                    <?php if (count($pendingKYC) > 0): ?>
                        <span class="badge badge-yellow"><?php echo count($pendingKYC); ?></span>
                    <?php endif; ?>
                </a>
                <a href="#" class="nav-item" onclick="switchTab('transfers', this)">
                    ⇄ Transfers
                </a>
                <a href="#" class="nav-item" onclick="switchTab('disputes', this)">
                    ⚖️ Disputes
                </a>
                <a href="#" class="nav-item" onclick="switchTab('settings', this)">
                    ⚙ Settings
                </a>
                <a href="#" class="nav-item" onclick="switchTab('blockchain', this)">
                    🔗 Blockchain
                    <span class="badge <?php echo $blockchain->isEnabled() ? 'badge-green' : 'badge-red'; ?>" style="font-size:10px;">
                        <?php echo $blockchain->isEnabled() ? 'ON' : 'OFF'; ?>
                    </span>
                </a>
                <a href="logout.php" class="nav-item">
                    🚪 Sign Out
                </a>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <header class="main-header">
                <div class="header-left">
                    <h1 id="tabTitle">Admin Overview</h1>
                    <p>Welcome, <?php echo htmlspecialchars($admin['full_name']); ?></p>
                </div>
                <div class="header-right">
                    <!-- Wallet Status -->
                    <?php if (!empty($admin['wallet_address'])): ?>
                        <div class="wallet-status connected">
                            <span class="wallet-dot"></span>
                            <span class="wallet-addr"><?php echo substr($admin['wallet_address'], 0, 6) . '...' . substr($admin['wallet_address'], -4); ?></span>
                        </div>
                    <?php else: ?>
                        <div class="wallet-status disconnected">
                            <span class="wallet-dot"></span>
                            <span>Wallet not linked</span>
                            <button class="btn btn-sm btn-outline" onclick="connectWallet()">Connect</button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Blockchain Status -->
                    <div class="blockchain-status <?php echo $blockchain->isEnabled() ? 'online' : 'offline'; ?>">
                        <span class="status-dot"></span>
                        Sepolia Testnet
                    </div>
                </div>
            </header>
            
            <!-- Tab Content -->
            <div id="tab-overview" class="tab-content active">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">📋</div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo count($pendingRegistrations); ?></div>
                            <div class="stat-label">Pending Registrations</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">🪪</div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo count($pendingKYC); ?></div>
                            <div class="stat-label">Pending KYC</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">⇄</div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo count($pendingTransfers); ?></div>
                            <div class="stat-label">Total Transfers</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">⚖️</div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo count($disputes); ?></div>
                            <div class="stat-label">Active Disputes</div>
                        </div>
                    </div>
                </div>
                
                <!-- All Registered Properties -->
                <div class="card">
                    <div class="card-header">
                        <h2>All Registered Properties</h2>
                        <span class="badge badge-blue"><?php 
                            $allParcels = $parcelModel->getAllActive();
                            echo count($allParcels); 
                        ?> total</span>
                    </div>
                    <?php if (empty($allParcels)): ?>
                        <div class="empty-state">No properties registered yet</div>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Parcel #</th>
                                        <th>Title</th>
                                        <th>Location</th>
                                        <th>Type</th>
                                        <th>Owner</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allParcels as $parcel): ?>
                                        <tr>
                                            <td><span class="badge badge-blue"><?php echo htmlspecialchars($parcel['parcel_number']); ?></span></td>
                                            <td><?php echo htmlspecialchars($parcel['title']); ?></td>
                                            <td><?php echo htmlspecialchars($parcel['location_address'] ?? $parcel['location'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($parcel['property_type'] ?? $parcel['propertyType'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($parcel['owner_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo statusBadge($parcel['status']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h2>Recent Activity</h2>
                    </div>
                    <div id="activityLog">
                        <div class="empty-state">Loading activity...</div>
                    </div>
                </div>
            </div>
            
            <!-- Registrations Tab -->
            <div id="tab-registrations" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h2>Pending Land Registrations</h2>
                        <span class="badge badge-yellow"><?php echo count($pendingRegistrations); ?> pending</span>
                    </div>
                    
                    <?php if (empty($pendingRegistrations)): ?>
                        <div class="empty-state">No pending registrations</div>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Applicant</th>
                                        <th>Title</th>
                                        <th>Location</th>
                                        <th>Documents</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingRegistrations as $reg): ?>
                                        <tr>
                                            <td>#<?php echo $reg['id']; ?></td>
                                            <td>
                                                <div><?php echo htmlspecialchars($reg['applicant_name']); ?></div>
                                                <small><?php echo htmlspecialchars($reg['applicant_email']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($reg['title']); ?></td>
                                            <td><?php echo htmlspecialchars($reg['location_address']); ?></td>
                                            <td>
                                                <?php if (!empty($reg['document_url'])): ?>
                                                    <a href="<?php echo $reg['document_url']; ?>" target="_blank" class="btn btn-sm btn-outline">
                                                        📎 View on IPFS
                                                    </a>
                                                    <small class="hash">Hash: <?php echo substr($reg['document_hash'], 0, 16); ?>...</small>
                                                <?php elseif (!empty($reg['document_hash'])): ?>
                                                    <span class="badge badge-blue">📄 Hashed</span>
                                                    <small class="hash">SHA-256: <?php echo substr($reg['document_hash'], 0, 16); ?>...</small>
                                                <?php else: ?>
                                                    <span class="text-muted">No documents</span>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($reg['documents'])): ?>
                                                    <div style="margin-top:4px;">
                                                        <small><?php echo count($reg['documents']); ?> file(s) attached</small>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($reg['submitted_at'])); ?></td>
                                            <td class="action-buttons">
                                                <button class="btn btn-sm btn-success" onclick="approveRegistration(<?php echo $reg['id']; ?>)">
                                                    ✓ Approve & Record
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="rejectRegistration(<?php echo $reg['id']; ?>)">
                                                    ✕ Reject
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- KYC Tab -->
            <div id="tab-kyc" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h2>Pending KYC Verifications</h2>
                        <span class="badge badge-yellow"><?php echo count($pendingKYC); ?> pending</span>
                    </div>
                    
                    <?php if (empty($pendingKYC)): ?>
                        <div class="empty-state">No pending KYC submissions</div>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Documents</th>
                                        <th>Submitted</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingKYC as $kyc): ?>
                                        <tr>
                                            <td>
                                                <div><?php echo htmlspecialchars($kyc['full_name']); ?></div>
                                                <small><?php echo htmlspecialchars($kyc['email']); ?></small>
                                                <?php if (!empty($kyc['national_id'])): ?>
                                                    <small class="hash">ID: <?php echo htmlspecialchars($kyc['national_id']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($kyc['document_url'])): ?>
                                                    <a href="<?php echo $kyc['document_url']; ?>" target="_blank" class="btn btn-sm btn-outline">
                                                        📎 View Documents on IPFS
                                                    </a>
                                                    <small class="hash">Hash: <?php echo substr($kyc['document_hash'], 0, 16); ?>...</small>
                                                <?php elseif (!empty($kyc['document_hash'])): ?>
                                                    <span class="badge badge-blue">📄 Hashed (local)</span>
                                                    <small class="hash">SHA-256: <?php echo substr($kyc['document_hash'], 0, 16); ?>...</small>
                                                <?php else: ?>
                                                    <span class="text-muted">No documents</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($kyc['submitted_at'])); ?></td>
                                            <td class="action-buttons">
                                                <button class="btn btn-sm btn-success" onclick="verifyKYC(<?php echo $kyc['id']; ?>, true)">
                                                    ✓ Verify
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="verifyKYC(<?php echo $kyc['id']; ?>, false)">
                                                    ✕ Reject
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Transfers Tab -->
            <div id="tab-transfers" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h2>Transfer Requests</h2>
                    </div>
                    <?php if (empty($pendingTransfers)): ?>
                        <div class="empty-state">No transfer requests</div>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Parcel</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingTransfers as $transfer): ?>
                                        <tr>
                                            <td>#<?php echo $transfer['id']; ?></td>
                                            <td><?php echo htmlspecialchars($transfer['parcel_title']); ?></td>
                                            <td><?php echo htmlspecialchars($transfer['sender_name']); ?></td>
                                            <td><?php echo htmlspecialchars($transfer['recipient_name']); ?></td>
                                            <td><?php echo ucfirst($transfer['transfer_type']); ?></td>
                                            <td><span class="badge badge-<?php echo $transfer['status'] === 'pending' ? 'yellow' : ($transfer['status'] === 'approved' ? 'green' : 'red'); ?>"><?php echo ucfirst($transfer['status']); ?></span></td>
                                            <td>
                                                <?php if ($transfer['status'] === 'pending'): ?>
                                                    <button class="btn btn-sm btn-success" onclick="approveTransfer(<?php echo $transfer['id']; ?>)">
                                                        ✓ Approve & Record
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="rejectTransfer(<?php echo $transfer['id']; ?>)">
                                                        ✕ Reject
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Disputes Tab -->
            <div id="tab-disputes" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h2>Active Disputes</h2>
                    </div>
                    <?php if (empty($disputes)): ?>
                        <div class="empty-state">No disputes filed</div>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Parcel</th>
                                        <th>Complainant</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($disputes as $dispute): ?>
                                        <tr>
                                            <td>#<?php echo $dispute['id']; ?></td>
                                            <td><?php echo htmlspecialchars($dispute['parcel_title']); ?></td>
                                            <td><?php echo htmlspecialchars($dispute['complainant_name']); ?></td>
                                            <td><?php echo ucfirst($dispute['dispute_type']); ?></td>
                                            <td><?php echo statusBadge($dispute['status']); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline" onclick="viewDispute(<?php echo $dispute['id']; ?>)">View</button>
                                                <?php if ($dispute['status'] === 'under_review'): ?>
                                                    <button class="btn btn-sm btn-primary" onclick="resolveDispute(<?php echo $dispute['id']; ?>)">Resolve</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Settings Tab -->
            <div id="tab-settings" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h2>Wallet Settings</h2>
                        <p>Link your blockchain wallet for recording critical operations on-chain</p>
                    </div>
                    
                    <?php if (empty($admin['wallet_address'])): ?>
                        <div class="wallet-connect-section">
                            <p>No wallet linked. Connect MetaMask to enable blockchain recording.</p>
                            <button class="btn btn-primary" onclick="connectWallet()">
                                🦊 Connect MetaMask
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="wallet-info">
                            <div class="info-row">
                                <span>Linked Wallet:</span>
                                <code><?php echo $admin['wallet_address']; ?></code>
                            </div>
                            <button class="btn btn-outline" onclick="connectWallet()">Change Wallet</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Blockchain Tab -->
            <div id="tab-blockchain" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h2>Blockchain Status</h2>
                    </div>
                    <div class="blockchain-info">
                        <div class="info-row">
                            <span>Status:</span>
                            <span class="badge <?php echo $blockchain->isEnabled() ? 'badge-green' : 'badge-red'; ?>">
                                <?php echo $blockchain->isEnabled() ? 'Enabled' : 'Disabled'; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span>Network:</span>
                            <span>Sepolia Testnet</span>
                        </div>
                        <div class="info-row">
                            <span>Contract:</span>
                            <code><?php echo LAND_REGISTRY_CONTRACT; ?></code>
                        </div>
                    </div>
                    
                    <div class="alert alert-info" style="margin-top:16px;">
                        <span class="alert-icon">ℹ️</span>
                        <div>
                            <strong>Blockchain Usage</strong>
                            <p>Blockchain is only used when admin approves:</p>
                            <ul>
                                <li>Land registration (records document hash)</li>
                                <li>Transfer approval (records ownership change)</li>
                                <li>Dispute resolution (if ownership changes)</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="assets/js/admin.js"></script>
    </script>
</body>
</html>

<?php
function statusBadge($status) {
    $badges = [
        'open' => '<span class="badge badge-yellow">Open</span>',
        'under_review' => '<span class="badge badge-blue">Under Review</span>',
        'resolved_complainant' => '<span class="badge badge-green">Resolved</span>',
        'resolved_respondent' => '<span class="badge badge-green">Resolved</span>',
        'dismissed' => '<span class="badge badge-red">Dismissed</span>',
    ];
    return $badges[$status] ?? "<span class=\"badge\">$status</span>";
}
?>