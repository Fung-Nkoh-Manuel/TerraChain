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
$allTransfers = $transferModel->getAll();
$allDisputes = $disputeModel->getAll();
$allParcels = $parcelModel->getAllActive();
$notifications = $notifService->getUserNotifications($admin['id']);

// ✅ Count only PENDING transfers
$pendingTransfersCount = 0;
foreach ($allTransfers as $t) {
    if ($t['status'] === 'pending') {
        $pendingTransfersCount++;
    }
}

// ✅ Count only OPEN/UNDER_REVIEW disputes (not resolved/dismissed)
$activeDisputesCount = 0;
foreach ($allDisputes as $d) {
    if (in_array($d['status'], ['open', 'under_review'])) {
        $activeDisputesCount++;
    }
}

// Aliases for compatibility with the rest of the file
$pendingTransfers = $allTransfers;
$disputes = $allDisputes;
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
            <a href="admin.php" class="sidebar-logo">
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
                    <?php if ($pendingTransfersCount > 0): ?>
                        <span class="badge badge-yellow"><?php echo $pendingTransfersCount; ?></span>
                    <?php endif; ?>
                </a>
                <a href="#" class="nav-item" onclick="switchTab('disputes', this)">
                    ⚖️ Disputes
                    <?php if ($activeDisputesCount > 0): ?>
                        <span class="badge badge-red"><?php echo $activeDisputesCount; ?></span>
                    <?php endif; ?>
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
                    
                    <!-- Sign Out Button -->
                    <a href="logout.php" class="btn-logout-header" title="Sign Out">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0 -1 0v2z"/>
                            <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/>
                        </svg>
                    </a>
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
                            <div class="stat-value"><?php echo $pendingTransfersCount; ?></div>
                            <div class="stat-label">Pending Transfers</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">⚖️</div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo $activeDisputesCount; ?></div>
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
                                        <th>Actions</th>
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
                                            <td>
                                                <button class="btn btn-sm btn-outline" onclick="quickLookupHistory('<?php echo htmlspecialchars($parcel['document_hash']); ?>')">
                                                    🔍 History
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Parcel History Lookup -->
                <div class="card">
                    <div class="card-header">
                        <h2>🔍 Parcel History & Verification</h2>
                    </div>
                    <div class="card-body" style="padding: 20px;">
                        <p style="color: var(--text3); font-size: 13px; margin-bottom: 15px; line-height: 1.5;">
                            Query the smart contract directly using Parcel ID or Document Hash to retrieve current ownership, complete historical timeline, change counters, and verify historical ownership at any timestamp.
                        </p>
                        
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label for="historyQuery" style="font-weight: 600; font-size: 12px; display: block; margin-bottom: 6px; color: var(--text2);">Parcel ID or Document Hash</label>
                            <div style="display: flex; gap: 8px;">
                                <input type="text" id="historyQuery" class="form-control" placeholder="e.g. 1 or Qm... / 0x..." style="flex: 1; padding: 10px; border-radius: var(--radius); border: 1px solid var(--border); background: var(--input-bg); color: var(--text);">
                                <button class="btn btn-primary" onclick="lookupParcelHistory()" id="historySearchBtn">Search</button>
                            </div>
                        </div>

                        <!-- Results Container -->
                        <div id="historyResults" style="display: none; margin-top: 20px; border-top: 1px solid var(--border); padding-top: 20px;">
                            <!-- Header Info -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px;">
                                <div class="stat-card" style="padding: 12px; margin-bottom: 0; min-height: auto; background: rgba(255,255,255,0.01);">
                                    <div class="stat-info">
                                        <div class="stat-value" id="resChangeCount" style="font-size: 20px;">0</div>
                                        <div class="stat-label" style="font-size: 11px;">Ownership Changes</div>
                                    </div>
                                </div>
                                <div class="stat-card" style="padding: 12px; margin-bottom: 0; min-height: auto; background: rgba(255,255,255,0.01);">
                                    <div class="stat-info">
                                        <div class="stat-value" id="resParcelId" style="font-size: 20px;">-</div>
                                        <div class="stat-label" style="font-size: 11px;">Resolved Parcel ID</div>
                                    </div>
                                </div>
                            </div>

                            <div style="margin-bottom: 15px;">
                                <label style="font-weight: 600; font-size: 12px; color: var(--text2); display: block; margin-bottom: 4px;">Current Owner Address</label>
                                <div id="resCurrentOwner" style="font-family: 'DM Mono', monospace; font-size: 11px; background: rgba(0, 229, 160, 0.05); padding: 8px 12px; border-radius: 6px; border: 1px solid rgba(0, 229, 160, 0.2); word-break: break-all;">-</div>
                            </div>

                            <!-- Timeline -->
                            <div style="margin-bottom: 25px;">
                                <label style="font-weight: 600; font-size: 12px; color: var(--text2); display: block; margin-bottom: 10px;">Ownership Timeline</label>
                                <div id="timelineContainer" style="display: flex; flex-direction: column; gap: 12px; border-left: 2px solid var(--border); padding-left: 15px; margin-left: 8px;">
                                    <!-- Dynamic Timeline Entries -->
                                </div>
                            </div>

                            <!-- Ownership at Time Verification Tool -->
                            <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border); padding: 15px; border-radius: var(--radius);">
                                <h4 style="margin: 0 0 10px 0; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 6px;">
                                    ⏳ Historical Ownership Check
                                </h4>
                                <p style="color: var(--text3); font-size: 11px; margin-bottom: 12px;">
                                    Verify if a specific address was the owner of this parcel at a particular date and time.
                                </p>
                                <div class="form-group" style="margin-bottom: 10px;">
                                    <input type="text" id="checkOwnerAddr" class="form-control" placeholder="Owner Wallet Address (0x...)" style="width: 100%; padding: 8px; font-size: 12px; border-radius: var(--radius); border: 1px solid var(--border); background: var(--input-bg); color: var(--text); margin-bottom: 8px;">
                                    <input type="datetime-local" id="checkOwnerTime" class="form-control" style="width: 100%; padding: 8px; font-size: 12px; border-radius: var(--radius); border: 1px solid var(--border); background: var(--input-bg); color: var(--text);">
                                </div>
                                <button class="btn btn-outline btn-sm" onclick="verifyOwnershipAtTime()" id="verifyTimeBtn" style="width: 100%; justify-content: center; font-size: 12px; padding: 8px;">
                                    Verify Ownership
                                </button>
                                <div id="verifyTimeResult" style="display: none; margin-top: 10px; text-align: center;">
                                    <!-- Result Badge -->
                                </div>
                            </div>
                        </div>

                        <div id="historyEmptyState" class="empty-state" style="padding: 40px 20px;">
                            Enter a Parcel ID or Document Hash above to query history.
                        </div>
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
                                        <th>Documents</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingTransfers as $transfer): ?>
                                        <tr>
                                            <td>#<?php echo $transfer['id']; ?></td>
                                            <td>
                                                <div><?php echo htmlspecialchars($transfer['parcel_title']); ?></div>
                                                <small style="color: var(--text3); font-size: 10px;"><?php echo htmlspecialchars($transfer['parcel_number'] ?? ''); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($transfer['sender_name']); ?></td>
                                            <td><?php echo htmlspecialchars($transfer['recipient_name']); ?></td>
                                            <td><?php echo ucfirst($transfer['transfer_type']); ?></td>
                                            <td>
                                                <?php 
                                                // Fetch documents for this transfer
                                                $transferDocs = $transferModel->getTransferDocuments($transfer['id']);
                                                if (!empty($transferDocs)): 
                                                ?>
                                                    <div class="document-list">
                                                        <?php foreach ($transferDocs as $doc): ?>
                                                            <div class="doc-item" style="margin-bottom: 4px;">
                                                                <?php if (!empty($doc['ipfs_cid'])): ?>
                                                                    <a href="<?php echo 'https://gateway.pinata.cloud/ipfs/' . $doc['ipfs_cid']; ?>" 
                                                                       target="_blank" class="btn btn-sm btn-outline" style="font-size: 10px; padding: 2px 8px;">
                                                                        📎 <?php echo htmlspecialchars($doc['document_name'] ?? 'Document'); ?>
                                                                    </a>
                                                                    <small class="hash" style="display: block; font-size: 9px; color: var(--text3); margin-top: 2px;">
                                                                        CID: <?php echo substr($doc['ipfs_cid'], 0, 12); ?>...
                                                                    </small>
                                                                <?php elseif (!empty($doc['document_hash'])): ?>
                                                                    <span class="badge badge-blue" style="font-size: 9px;">📄 <?php echo htmlspecialchars($doc['document_name'] ?? 'Document'); ?></span>
                                                                    <small class="hash" style="display: block; font-size: 9px; color: var(--text3); margin-top: 2px;">
                                                                        SHA-256: <?php echo substr($doc['document_hash'], 0, 16); ?>...
                                                                    </small>
                                                                <?php else: ?>
                                                                    <span class="text-muted" style="font-size: 10px;">No document</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted" style="font-size: 11px;">No documents</span>
                                                <?php endif; ?>
                                            </td>
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
                                                <button class="btn btn-sm btn-outline" onclick="viewTransferDetails(<?php echo $transfer['id']; ?>)">
                                                    👁 View
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

    <!-- Transfer Details Modal -->
    <div id="transferModal" class="modal-overlay" style="display:none;">
        <div class="modal" style="max-width: 700px; width: 95%;">
            <div class="modal-header">
                <h2>Transfer Request Details</h2>
                <button class="modal-close" onclick="closeModal('transferModal')">&times;</button>
            </div>
            <div class="modal-body" id="transferDetailsBody">
                <!-- Populated by JavaScript -->
            </div>
        </div>
    </div>
    
    <script src="assets/js/admin.js"></script>
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