// ============================================================
//  TerraChain v2 — Admin Panel JavaScript (Production)
// ============================================================

const API_BASE = '../api';
const CONTRACT_ADDRESS = '0x8a8937bb4cea0a6e00102ed9b9fcf8217d311d04';

// Only the functions we actually call from the contract
const CONTRACT_ABI = [
  "function recordLandOwnership(string documentHash, address owner) returns (uint256)",
  "function transferOwnership(string documentHash, address newOwner)",
  "function updateOwnershipAfterDispute(string documentHash, address newOwner, string resolution)",
  "function verifyDocumentHash(string documentHash) view returns (bool exists, uint256 propertyId, address owner)",
  "function admin() view returns (address)",
  "function paused() view returns (bool)",
  "function getTotalProperties() view returns (uint256)"
];

let signer = null;
let contract = null;

// ── Initialize Blockchain Connection ─────────────────
async function initBlockchain() {
  if (!window.ethereum) {
    console.warn('MetaMask not detected');
    return false;
  }

  try {
    const provider = new ethers.BrowserProvider(window.ethereum);
    signer = await provider.getSigner();
    contract = new ethers.Contract(CONTRACT_ADDRESS, CONTRACT_ABI, signer);

    // Check if admin's wallet matches contract admin
    const adminAddress = await signer.getAddress();
    const contractAdmin = await contract.admin();

    console.log('Connected wallet:', adminAddress);
    console.log('Contract admin:', contractAdmin);

    if (adminAddress.toLowerCase() !== contractAdmin.toLowerCase()) {
      console.warn('Connected wallet is NOT the contract admin');
    }

    return true;
  } catch (err) {
    console.error('Blockchain init error:', err);
    return false;
  }
}

// ══════════════════════════════════════════════════════
//  AUTO-CONNECT WALLET WHEN NEEDED
// ══════════════════════════════════════════════════════
async function ensureWalletConnected() {
  if (window.contract && window.signer) {
    return true;
  }

  if (window.ethereum) {
    try {
      const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });

      if (accounts.length > 0) {
        const provider = new ethers.BrowserProvider(window.ethereum);
        const s = await provider.getSigner();
        const c = new ethers.Contract(CONTRACT_ADDRESS, CONTRACT_ABI, s);

        // Set BOTH local and global
        signer = s;
        contract = c;
        window.signer = s;
        window.contract = c;

        await api('/auth/wallet', 'POST', { wallet_address: accounts[0] });

        console.log('✅ Wallet connected and contract initialized');
        return true;
      }
    } catch (err) {
      console.error('Auto-connect failed:', err.message);
    }
  }

  return false;
}

// ── Tab Switching ────────────────────────────────────
// ── Tab Switching ────────────────────────────────────
function switchTab(tabName, el) {
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    const targetTab = document.getElementById('tab-' + tabName);
    if (targetTab) targetTab.classList.add('active');

    document.querySelectorAll('.sidebar-nav .nav-item').forEach(item => item.classList.remove('active'));
    if (el) el.classList.add('active');

    const titles = {
        'overview': 'Admin Overview',
        'registrations': 'Land Registrations',
        'kyc': 'KYC Verification',
        'transfers': 'Transfer Requests',
        'disputes': 'Disputes',
        'settings': 'Wallet Settings',
        'blockchain': 'Blockchain Status'
    };
    const titleEl = document.getElementById('tabTitle');
    if (titleEl) titleEl.textContent = titles[tabName] || tabName;

    // Track current tab for auto-refresh
    currentTab = tabName;
    // Refresh immediately when switching tabs
    setTimeout(() => refreshCurrentTab(), 500);
}

// ── Toast Notifications ──────────────────────────────
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
        background: #1a1f25;
        border: 1px solid #242c35;
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
  setTimeout(() => { if (toastEl.parentElement) toastEl.remove(); }, 6000);
}

// ── API Helper ───────────────────────────────────────
async function api(endpoint, method = 'GET', body = null) {
  const opts = {
    method,
    headers: body ? { 'Content-Type': 'application/json' } : {},
    credentials: 'same-origin'
  };
  if (body) opts.body = JSON.stringify(body);
  try {
    const res = await fetch(API_BASE + endpoint, opts);
    return await res.json();
  } catch (e) {
    console.error('API error:', e);
    return { success: false, error: 'Network error' };
  }
}

// ══════════════════════════════════════════════════════
//  REGISTRATION APPROVAL (Auto-connect + Blockchain)
// ══════════════════════════════════════════════════════
async function approveRegistration(regId) {
  if (!confirm('Approve this registration? This will record on blockchain.')) return;

  toast('Processing...', 'info');

  const res = await api('/parcels/approve', 'POST', { registration_id: regId });

  if (!res.success) {
    toast(res.data?.error || 'Approval failed', 'error');
    return;
  }

  if (res.data.status === 'pending_blockchain') {
    const docHash = res.data.document_hash;
    const wallet = res.data.wallet_used;

    if (!docHash || !wallet) {
      toast('Missing blockchain data', 'error');
      return;
    }

    if (!window.ethereum) {
      toast('❌ MetaMask required! Connect wallet in Settings tab first.', 'error');
      return;
    }

    try {
      const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
      const provider = new ethers.BrowserProvider(window.ethereum);
      const signer = await provider.getSigner();
      const c = new ethers.Contract(CONTRACT_ADDRESS, CONTRACT_ABI, signer);

      toast('🦊 Confirm transaction in MetaMask...', 'info');
      const tx = await c.recordLandOwnership(docHash, wallet);

      toast('⛏ Mining transaction...', 'info');
      await tx.wait();

      // Send tx hash back
      const finalRes = await api('/parcels/approve', 'POST', {
        registration_id: regId,
        tx_hash: tx.hash
      });

      if (finalRes.success) {
        toast('✅ Approved & recorded on blockchain!\nTX: ' + tx.hash.substring(0, 10) + '...', 'success');
      } else {
        toast('⚠️ Blockchain OK but DB error: ' + (finalRes.data?.error || ''), 'warn');
      }

    } catch (err) {
      if (err.code === 4001) {
        toast('❌ Cancelled - you rejected the MetaMask transaction.\nRegistration NOT approved.', 'error');
      } else {
        toast('❌ Error: ' + (err.reason || err.message), 'error');
      }
    }
  }

  setTimeout(() => location.reload(), 4000);
}

// ══════════════════════════════════════════════════════
//  REGISTRATION REJECTION
// ══════════════════════════════════════════════════════
async function rejectRegistration(regId) {
  const reason = prompt('Please enter the rejection reason:');
  if (!reason || !reason.trim()) {
    toast('Rejection cancelled', 'warn');
    return;
  }

  toast('Rejecting registration...', 'info');

  try {
    const res = await api('/parcels/reject', 'POST', {
      registration_id: regId,
      reason: reason.trim()
    });

    if (res.success) {
      toast('Registration rejected', 'warn');
      setTimeout(() => location.reload(), 1500);
    } else {
      toast(res.data?.error || 'Rejection failed', 'error');
    }
  } catch (err) {
    toast('Network error', 'error');
  }
}

// ══════════════════════════════════════════════════════
//  TRANSFER APPROVAL (Auto-connect + Blockchain)
// ══════════════════════════════════════════════════════
async function approveTransfer(transferId) {
  if (!confirm('Approve this transfer?\n\nOwnership will be permanently changed on the blockchain.')) return;

  toast('Step 1/3: Approving transfer in database...', 'info');

  try {
    const res = await api('/transfers/approve', 'POST', { transfer_id: transferId });

    if (!res.success) {
      toast(res.data?.error || 'Approval failed', 'error');
      return;
    }

    const responseData = res.data;
    console.log('Transfer response:', responseData);

    const documentHash = responseData.document_hash;
    const newOwnerWallet = responseData.new_owner_wallet;

    if (!documentHash || !newOwnerWallet) {
      toast('Transfer approved in database but missing blockchain data.', 'warn');
      setTimeout(() => location.reload(), 2000);
      return;
    }

    // Step 2: Auto-connect wallet
    toast('Step 2/3: Connecting to wallet...', 'info');

    const walletReady = await ensureWalletConnected();

    if (!walletReady) {
      toast('❌ MetaMask not available. Transfer approved in database only.', 'warn');
      setTimeout(() => location.reload(), 3000);
      return;
    }

    // Step 3: Record on blockchain
    try {
      toast('Step 3/3: Confirm transfer in MetaMask...', 'info');
      console.log('Calling transferOwnership:', documentHash, newOwnerWallet);

      const tx = await contract.transferOwnership(documentHash, newOwnerWallet);
      console.log('Transaction sent:', tx.hash);

      toast('⛏ Waiting for confirmation...', 'info');
      await tx.wait();

      toast(`✅ Transfer recorded on blockchain!\nTX: ${tx.hash.substring(0, 10)}...`, 'success');

      await api('/transfers/update-blockchain', 'POST', {
        transfer_id: transferId,
        tx_hash: tx.hash
      });

    } catch (blockchainErr) {
      console.error('Blockchain error:', blockchainErr);
      if (blockchainErr.code === 4001) {
        toast('⚠️ Transaction rejected in MetaMask. Transfer approved in database only.', 'warn');
      } else {
        toast(`⚠️ Blockchain error: ${blockchainErr.reason || blockchainErr.message}`, 'warn');
      }
    }

    setTimeout(() => location.reload(), 3000);

  } catch (err) {
    console.error('Transfer error:', err);
    toast('Error: ' + err.message, 'error');
  }
}

async function rejectTransfer(transferId) {
  const reason = prompt('Please enter the rejection reason:');
  if (!reason || !reason.trim()) {
    toast('Rejection cancelled', 'warn');
    return;
  }

  toast('Rejecting transfer...', 'info');

  try {
    const res = await api('/transfers/reject', 'POST', {
      transfer_id: transferId,
      reason: reason.trim()
    });

    if (res.success) {
      toast('Transfer rejected', 'warn');
      setTimeout(() => location.reload(), 1500);
    } else {
      toast(res.data?.error || 'Rejection failed', 'error');
    }
  } catch (err) {
    toast('Network error', 'error');
  }
}

// ══════════════════════════════════════════════════════
//  KYC VERIFICATION
// ══════════════════════════════════════════════════════
async function verifyKYC(kycId, approved) {
  if (approved) {
    if (!confirm('Approve this KYC verification?')) return;

    toast('Verifying KYC...', 'info');

    const res = await api('/kyc/verify', 'POST', { kyc_id: kycId, approved: true });

    if (res.success) {
      toast('KYC verified!', 'success');
      setTimeout(() => location.reload(), 1500);
    } else {
      toast(res.data?.error || 'Verification failed', 'error');
    }
  } else {
    const reason = prompt('Please enter the rejection reason:');
    if (!reason || !reason.trim()) {
      toast('Rejection cancelled', 'warn');
      return;
    }

    toast('Rejecting KYC...', 'info');

    const res = await api('/kyc/verify', 'POST', {
      kyc_id: kycId,
      approved: false,
      reason: reason.trim()
    });

    if (res.success) {
      toast('KYC rejected', 'warn');
      setTimeout(() => location.reload(), 1500);
    } else {
      toast(res.data?.error || 'Rejection failed', 'error');
    }
  }
}

// ══════════════════════════════════════════════════════
//  DISPUTE ACTIONS
// ══════════════════════════════════════════════════════
async function viewDispute(disputeId) {
  toast('Loading dispute #' + disputeId + '...', 'info');
}

// ══════════════════════════════════════════════════════
//  DISPUTE RESOLUTION (Auto-connect + Blockchain if ownership changes)
// ══════════════════════════════════════════════════════
async function resolveDispute(disputeId) {
  const notes = prompt('Resolution notes:');
  if (!notes || !notes.trim()) {
    toast('Resolution cancelled', 'warn');
    return;
  }

  const changeOwnership = confirm('Does ownership change?\n\nOK = Ownership Changes (will call blockchain)\nCancel = No Change (database only)');

  toast('Resolving dispute...', 'info');

  const res = await api('/disputes/resolve', 'POST', {
    dispute_id: disputeId,
    status: 'resolved_complainant',
    outcome: changeOwnership ? 'ownership_changed' : 'no_change',
    notes: notes.trim()
  });

  if (res.success) {
    // If ownership changed, auto-connect and call blockchain
    if (changeOwnership && res.data.document_hash && res.data.new_owner_wallet) {

      toast('Ownership change requires blockchain. Connecting wallet...', 'info');

      const walletReady = await ensureWalletConnected();

      if (!walletReady) {
        toast('❌ MetaMask not available. Dispute resolved in database only.', 'warn');
        setTimeout(() => location.reload(), 3000);
        return;
      }

      try {
        toast('Recording ownership change on blockchain...', 'info');
        const tx = await contract.updateOwnershipAfterDispute(
          res.data.document_hash,
          res.data.new_owner_wallet,
          notes.trim()
        );
        await tx.wait();
        toast('✅ Dispute resolved & recorded on blockchain!', 'success');
      } catch (err) {
        if (err.code === 4001) {
          toast('⚠️ Transaction rejected. Dispute resolved in database only.', 'warn');
        } else {
          toast('⚠️ Blockchain error: ' + (err.reason || err.message), 'warn');
        }
      }
    } else {
      toast('Dispute resolved!', 'success');
    }
    setTimeout(() => location.reload(), 2500);
  } else {
    toast(res.data?.error || 'Resolution failed', 'error');
  }
}

// ══════════════════════════════════════════════════════
//  WALLET CONNECTION
// ══════════════════════════════════════════════════════
async function connectWallet() {
  if (!window.ethereum) {
    toast('MetaMask not detected. Please install MetaMask extension.', 'error');
    window.open('https://metamask.io/download/', '_blank');
    return;
  }

  try {
    toast('Connecting to MetaMask...', 'info');

    const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
    const walletAddress = accounts[0];

    // Initialize contract connection
    const initialized = await initBlockchain();

    if (initialized) {
      // Save wallet to backend
      const res = await api('/auth/wallet', 'POST', { wallet_address: walletAddress });

      if (res.success) {
        const chainId = await window.ethereum.request({ method: 'eth_chainId' });
        const networkName = chainId === '0xaa36a7' ? 'Sepolia' : 'Unknown Network';

        toast(`Wallet connected!\n${walletAddress.substring(0, 6)}...${walletAddress.substring(38)}\nNetwork: ${networkName}`, 'success');

        // Check if on Sepolia
        if (chainId !== '0xaa36a7') {
          try {
            await window.ethereum.request({
              method: 'wallet_switchEthereumChain',
              params: [{ chainId: '0xaa36a7' }]
            });
          } catch (switchErr) {
            toast('Please switch to Sepolia Testnet manually in MetaMask', 'warn');
          }
        }

        setTimeout(() => location.reload(), 2000);
      } else {
        toast(res.data?.error || 'Failed to save wallet', 'error');
      }
    } else {
      toast('Failed to connect to contract. Check if MetaMask is unlocked.', 'error');
    }
  } catch (err) {
    if (err.code === 4001) {
      toast('Connection rejected by user', 'warn');
    } else {
      toast('Error: ' + err.message, 'error');
    }
  }
}

// ══════════════════════════════════════════════════════
//  LOGOUT
// ══════════════════════════════════════════════════════
async function logout() {
  try {
    await api('/auth/logout', 'POST');
  } catch (e) { }
  window.location.href = './';
}

// ══════════════════════════════════════════════════════
//  INITIALIZATION
// ══════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', async function () {
  // Try to auto-connect if wallet is already saved
  if (window.ethereum && window.ethereum.selectedAddress) {
    try {
      await initBlockchain();
      console.log('Auto-connected to blockchain');
    } catch (e) {
      console.log('Auto-connect failed:', e.message);
    }
  }

  // Load activity
  const logContainer = document.getElementById('activityLog');
  if (logContainer) {
    logContainer.innerHTML = '<div class="empty-state"><p>Admin panel ready. Use the sidebar to manage operations.</p></div>';
  }

  // Start auto-refresh
  startAutoRefresh();
  console.log('✅ Auto-refresh initialized');
});

// ══════════════════════════════════════════════════════
//  AUTO-REFRESH ADMIN DATA (Poll every 15 seconds)
// ══════════════════════════════════════════════════════

let autoRefreshInterval = null;
let currentTab = 'overview';

// Start auto-refresh
function startAutoRefresh() {
  // Clear existing interval
  if (autoRefreshInterval) {
    clearInterval(autoRefreshInterval);
  }

  // Refresh every 15 seconds
  autoRefreshInterval = setInterval(async () => {
    await refreshCurrentTab();
  }, 15000); // 15 seconds

  console.log('🔄 Auto-refresh started (every 15s)');
}

// Stop auto-refresh
function stopAutoRefresh() {
  if (autoRefreshInterval) {
    clearInterval(autoRefreshInterval);
    autoRefreshInterval = null;
  }
}

// Refresh the currently active tab
async function refreshCurrentTab() {
  // Get current active tab
  const activeTab = document.querySelector('.tab-content.active');
  if (!activeTab) return;

  const tabId = activeTab.id.replace('tab-', '');
  currentTab = tabId;

  console.log('🔄 Auto-refreshing tab:', tabId);

  // Update badge counts
  await updateBadges();

  // Refresh specific tab content
  switch (tabId) {
    case 'overview':
      await refreshOverviewStats();
      break;
    case 'registrations':
      await refreshRegistrations();
      break;
    case 'kyc':
      await refreshKYC();
      break;
    case 'transfers':
      await refreshTransfers();
      break;
    case 'disputes':
      await refreshDisputes();
      break;
  }
}

// Update badge counts in sidebar
async function updateBadges() {
  try {
    // Get pending counts
    const [kycRes, regRes] = await Promise.all([
      api('/kyc/pending', 'GET'),
      api('/parcels/pending', 'GET')
    ]);

    // Update KYC badge
    const kycBadge = document.querySelector('.nav-item[onclick*="kyc"] .badge');
    if (kycBadge && kycRes.success) {
      const count = kycRes.data?.length || 0;
      if (count > 0) {
        kycBadge.textContent = count;
        kycBadge.style.display = 'inline';
      } else {
        kycBadge.style.display = 'none';
      }
    }

    // Update registrations badge
    const regBadge = document.querySelector('.nav-item[onclick*="registrations"] .badge');
    if (regBadge && regRes.success) {
      const count = regRes.data?.length || 0;
      if (count > 0) {
        regBadge.textContent = count;
        regBadge.style.display = 'inline';
      } else {
        regBadge.style.display = 'none';
      }
    }

    // Update overview badge (combined)
    const overviewBadge = document.querySelector('.nav-item[onclick*="overview"] .badge');
    if (overviewBadge && kycRes.success && regRes.success) {
      const total = (kycRes.data?.length || 0) + (regRes.data?.length || 0);
      if (total > 0) {
        overviewBadge.textContent = total;
        overviewBadge.style.display = 'inline';
      } else {
        overviewBadge.style.display = 'none';
      }
    }

  } catch (e) {
    console.error('Badge update error:', e);
  }
}

// Refresh overview stats
async function refreshOverviewStats() {
  try {
    const [kycRes, regRes, transferRes, disputeRes] = await Promise.all([
      api('/kyc/pending', 'GET'),
      api('/parcels/pending', 'GET'),
      api('/transfers/all', 'GET'),
      api('/disputes/all', 'GET')
    ]);

    // Update stat values
    const statValues = document.querySelectorAll('#tab-overview .stat-value');
    if (statValues.length >= 4) {
      if (regRes.success) statValues[0].textContent = regRes.data?.length || 0;
      if (kycRes.success) statValues[1].textContent = kycRes.data?.length || 0;
      if (transferRes.success) statValues[2].textContent = transferRes.data?.length || 0;
      if (disputeRes.success) statValues[3].textContent = disputeRes.data?.length || 0;
    }
  } catch (e) {
    console.error('Stats refresh error:', e);
  }
}

// Refresh registrations tab
async function refreshRegistrations() {
  try {
    const res = await api('/parcels/pending', 'GET');
    if (!res.success) return;

    const registrations = res.data || [];
    const tableBody = document.querySelector('#tab-registrations tbody');
    if (!tableBody) return;

    if (registrations.length === 0) {
      tableBody.innerHTML = '<tr><td colspan="7"><div class="empty-state">No pending registrations</div></td></tr>';
      return;
    }

    // Only update if data changed (compare first ID)
    const firstExistingId = tableBody.querySelector('tr:first-child td:first-child')?.textContent;
    const firstNewId = '#' + registrations[0]?.id;

    if (firstExistingId !== firstNewId) {
      // Data changed - rebuild table
      let html = '';
      registrations.forEach(reg => {
        html += `
                    <tr>
                        <td>#${reg.id}</td>
                        <td>
                            <div>${escapeHtml(reg.applicant_name || '')}</div>
                            <small>${escapeHtml(reg.applicant_email || '')}</small>
                        </td>
                        <td>${escapeHtml(reg.title || '')}</td>
                        <td>${escapeHtml(reg.location_address || '')}</td>
                        <td>
                            ${reg.document_url ? `<a href="${reg.document_url}" target="_blank" class="btn btn-sm btn-outline">📎 View</a>` : '<span class="text-muted">None</span>'}
                        </td>
                        <td>${formatDate(reg.submitted_at)}</td>
                        <td class="action-buttons">
                            <button class="btn btn-sm btn-success" onclick="approveRegistration(${reg.id})">✓ Approve & Record</button>
                            <button class="btn btn-sm btn-danger" onclick="rejectRegistration(${reg.id})">✕ Reject</button>
                        </td>
                    </tr>
                `;
      });
      tableBody.innerHTML = html;
    }
  } catch (e) {
    console.error('Registrations refresh error:', e);
  }
}

// Refresh KYC tab
async function refreshKYC() {
  try {
    const res = await api('/kyc/pending', 'GET');
    if (!res.success) return;

    const kycList = res.data || [];
    const tableBody = document.querySelector('#tab-kyc tbody');
    if (!tableBody) return;

    if (kycList.length === 0) {
      tableBody.innerHTML = '<tr><td colspan="4"><div class="empty-state">No pending KYC</div></td></tr>';
      return;
    }

    const firstExistingId = tableBody.querySelector('tr:first-child td:first-child div')?.textContent;
    const firstNewName = kycList[0]?.full_name;

    if (firstExistingId !== firstNewName) {
      let html = '';
      kycList.forEach(kyc => {
        html += `
                    <tr>
                        <td>
                            <div>${escapeHtml(kyc.full_name || '')}</div>
                            <small>${escapeHtml(kyc.email || '')}</small>
                        </td>
                        <td>
                            ${kyc.document_url ? `<a href="${kyc.document_url}" target="_blank" class="btn btn-sm btn-outline">📎 View</a>` : '<span>Hashed</span>'}
                        </td>
                        <td>${formatDate(kyc.submitted_at)}</td>
                        <td class="action-buttons">
                            <button class="btn btn-sm btn-success" onclick="verifyKYC(${kyc.id}, true)">✓ Verify</button>
                            <button class="btn btn-sm btn-danger" onclick="verifyKYC(${kyc.id}, false)">✕ Reject</button>
                        </td>
                    </tr>
                `;
      });
      tableBody.innerHTML = html;
    }
  } catch (e) {
    console.error('KYC refresh error:', e);
  }
}

// Refresh transfers tab
async function refreshTransfers() {
  try {
    const res = await api('/transfers/all', 'GET');
    if (!res.success) return;

    const transfers = res.data || [];
    const tableBody = document.querySelector('#tab-transfers tbody');
    if (!tableBody) return;

    if (transfers.length === 0) {
      tableBody.innerHTML = '<tr><td colspan="7"><div class="empty-state">No transfers</div></td></tr>';
      return;
    }

    const firstExistingId = tableBody.querySelector('tr:first-child td:first-child')?.textContent;
    const firstNewId = '#' + transfers[0]?.id;

    if (firstExistingId !== firstNewId) {
      let html = '';
      transfers.forEach(t => {
        const statusClass = t.status === 'pending' ? 'yellow' : (t.status === 'approved' ? 'green' : 'red');
        html += `
                    <tr>
                        <td>#${t.id}</td>
                        <td>${escapeHtml(t.parcel_title || '')}</td>
                        <td>${escapeHtml(t.sender_name || '')}</td>
                        <td>${escapeHtml(t.recipient_name || '')}</td>
                        <td>${escapeHtml(t.transfer_type || '')}</td>
                        <td><span class="badge badge-${statusClass}">${t.status}</span></td>
                        <td>
                            ${t.status === 'pending' ? `
                                <button class="btn btn-sm btn-success" onclick="approveTransfer(${t.id})">✓ Approve</button>
                                <button class="btn btn-sm btn-danger" onclick="rejectTransfer(${t.id})">✕ Reject</button>
                            ` : '—'}
                        </td>
                    </tr>
                `;
      });
      tableBody.innerHTML = html;
    }
  } catch (e) {
    console.error('Transfers refresh error:', e);
  }
}

// Refresh disputes tab
async function refreshDisputes() {
  try {
    const res = await api('/disputes/all', 'GET');
    if (!res.success) return;

    const disputes = res.data || [];
    const tableBody = document.querySelector('#tab-disputes tbody');
    if (!tableBody) return;

    if (disputes.length === 0) {
      tableBody.innerHTML = '<tr><td colspan="6"><div class="empty-state">No disputes</div></td></tr>';
      return;
    }

    const firstExistingId = tableBody.querySelector('tr:first-child td:first-child')?.textContent;
    const firstNewId = '#' + disputes[0]?.id;

    if (firstExistingId !== firstNewId) {
      let html = '';
      disputes.forEach(d => {
        const statusClass = d.status === 'open' ? 'yellow' : (d.status === 'under_review' ? 'blue' : 'green');
        html += `
                    <tr>
                        <td>#${d.id}</td>
                        <td>${escapeHtml(d.parcel_title || '')}</td>
                        <td>${escapeHtml(d.complainant_name || '')}</td>
                        <td>${escapeHtml(d.dispute_type || '')}</td>
                        <td><span class="badge badge-${statusClass}">${(d.status || '').replace(/_/g, ' ')}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline" onclick="viewDispute(${d.id})">View</button>
                            ${d.status === 'under_review' ? `<button class="btn btn-sm btn-primary" onclick="resolveDispute(${d.id})">Resolve</button>` : ''}
                        </td>
                    </tr>
                `;
      });
      tableBody.innerHTML = html;
    }
  } catch (e) {
    console.error('Disputes refresh error:', e);
  }
}

// Helper
function escapeHtml(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function formatDate(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return dateStr;
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}