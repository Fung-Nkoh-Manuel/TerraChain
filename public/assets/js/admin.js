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
  // ✅ If clicking Overview, just reload the page to get fresh PHP data
  if (tabName === 'overview') {
    window.location.reload();
    return;
  }

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
  if (!confirm('Approve this transfer? This will record on blockchain.')) return;

  toast('Processing...', 'info');

  // Step 1: Call backend (no tx_hash yet)
  const res = await api('/transfers/approve', 'POST', { transfer_id: transferId });

  if (!res.success) {
    toast(res.data?.error || 'Approval failed', 'error');
    return;
  }

  // Step 2: If blockchain needed, call MetaMask
  if (res.data.status === 'pending_blockchain') {
    const docHash = res.data.document_hash;
    const newWallet = res.data.new_owner_wallet;

    if (!docHash || !newWallet) {
      toast('Missing blockchain data', 'error');
      return;
    }

    if (!window.ethereum) {
      toast('MetaMask required! Connect wallet first.', 'error');
      return;
    }

    try {
      const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
      const provider = new ethers.BrowserProvider(window.ethereum);
      const signer = await provider.getSigner();
      const contract = new ethers.Contract(CONTRACT_ADDRESS, CONTRACT_ABI, signer);

      toast('Confirm transaction in MetaMask...', 'info');
      const tx = await contract.transferOwnership(docHash, newWallet);

      toast('Waiting for confirmation...', 'info');
      await tx.wait();

      console.log('Transfer tx confirmed:', tx.hash);

      // ✅ Step 3: Send tx_hash back to SAME endpoint
      const finalRes = await api('/transfers/approve', 'POST', {
        transfer_id: transferId,
        tx_hash: tx.hash
      });

      if (finalRes.success) {
        toast('✅ Transfer approved & recorded!', 'success');
      } else {
        toast('⚠️ Blockchain OK but database error: ' + (finalRes.data?.error || ''), 'warn');
      }

    } catch (err) {
      if (err.code === 4001) {
        toast('❌ Transaction rejected in MetaMask. Transfer NOT approved.', 'error');
      } else {
        toast('❌ Error: ' + (err.reason || err.message), 'error');
      }
    }
  } else {
    toast('✅ Transfer approved!', 'success');
  }

  setTimeout(() => location.reload(), 3000);
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
// ══════════════════════════════════════════════════════
//  VIEW DISPUTE DETAILS
// ══════════════════════════════════════════════════════
async function viewDispute(disputeId) {
  toast('Loading dispute details...', 'info');

  try {
    // Fetch dispute details from API
    const res = await api('/disputes/get', 'POST', { id: disputeId });

    if (!res.success) {
      toast('Failed to load dispute details', 'error');
      return;
    }

    const d = res.data;

    // Build the modal
    const existing = document.querySelector('.modal-overlay');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;z-index:1000;';

    const statusColors = {
      'open': 'badge-yellow',
      'under_review': 'badge-blue',
      'resolved_complainant': 'badge-green',
      'resolved_respondent': 'badge-green',
      'dismissed': 'badge-red'
    };

    const statusLabels = {
      'open': 'Open',
      'under_review': 'Under Review',
      'resolved_complainant': 'Resolved (Complainant)',
      'resolved_respondent': 'Resolved (Respondent)',
      'dismissed': 'Dismissed'
    };

    const typeLabels = {
      'ownership': 'Ownership',
      'boundary': 'Boundary',
      'fraud': 'Fraud / Forgery',
      'transfer': 'Unauthorized Transfer',
      'public_land': 'Public Land',
      'other': 'Other'
    };

    modal.innerHTML = `
            <div style="background:var(--surface,#1a1f25);border:1px solid var(--border,#242c35);border-radius:12px;padding:28px;max-width:650px;width:90%;max-height:85vh;overflow-y:auto;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <h3 style="font-family:Syne,sans-serif;color:#e8edf2;margin:0;">⚖️ Dispute #${d.id}</h3>
                    <button onclick="this.closest('.modal-overlay').remove()" style="background:none;border:none;color:#4a5a6a;font-size:22px;cursor:pointer;">✕</button>
                </div>
                
                <!-- Status Banner -->
                <div style="background:rgba(${d.status === 'open' ? '255,204,0' : d.status.includes('resolved') ? '0,229,160' : '255,59,92'},0.08);border:1px solid rgba(${d.status === 'open' ? '255,204,0' : d.status.includes('resolved') ? '0,229,160' : '255,59,92'},0.2);border-radius:8px;padding:12px 16px;margin-bottom:20px;">
                    <span class="badge ${statusColors[d.status] || 'badge-yellow'}">${statusLabels[d.status] || d.status}</span>
                    <span style="margin-left:8px;color:#e8edf2;font-size:14px;">Filed on ${formatDate(d.created_at)}</span>
                </div>
                
                <!-- Parcel Info -->
                <div style="margin-bottom:16px;">
                    <h4 style="color:#8a9bb0;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Parcel Information</h4>
                    <div style="background:#111418;border:1px solid #242c35;border-radius:8px;padding:14px;">
                        <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                            <span style="color:#8a9bb0;">Parcel:</span>
                            <span style="color:#e8edf2;font-weight:600;">${escapeHtml(d.parcel_title || 'N/A')}</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                            <span style="color:#8a9bb0;">Number:</span>
                            <span style="color:#00e5a0;font-family:monospace;">${escapeHtml(d.parcel_number || 'N/A')}</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;">
                            <span style="color:#8a9bb0;">Location:</span>
                            <span style="color:#e8edf2;">${escapeHtml(d.location_address || 'N/A')}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Parties -->
                <div style="margin-bottom:16px;">
                    <h4 style="color:#8a9bb0;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Parties</h4>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <div style="background:#111418;border:1px solid #242c35;border-radius:8px;padding:12px;">
                            <div style="color:#ffcc00;font-size:11px;margin-bottom:4px;">COMPLAINANT</div>
                            <div style="color:#e8edf2;font-weight:600;">${escapeHtml(d.complainant_name || 'N/A')}</div>
                            <div style="color:#8a9bb0;font-size:12px;">${escapeHtml(d.complainant_email || '')}</div>
                        </div>
                        <div style="background:#111418;border:1px solid #242c35;border-radius:8px;padding:12px;">
                            <div style="color:#4d9eff;font-size:11px;margin-bottom:4px;">RESPONDENT</div>
                            <div style="color:#e8edf2;font-weight:600;">${escapeHtml(d.respondent_name || 'Not specified')}</div>
                            <div style="color:#8a9bb0;font-size:12px;">${escapeHtml(d.respondent_email || '')}</div>
                        </div>
                    </div>
                </div>
                
                <!-- Dispute Details -->
                <div style="margin-bottom:16px;">
                    <h4 style="color:#8a9bb0;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Dispute Details</h4>
                    <div style="background:#111418;border:1px solid #242c35;border-radius:8px;padding:14px;">
                        <div style="margin-bottom:8px;">
                            <span style="color:#8a9bb0;">Type: </span>
                            <span class="badge badge-orange">${typeLabels[d.dispute_type] || d.dispute_type}</span>
                        </div>
                        <div>
                            <span style="color:#8a9bb0;">Description:</span>
                            <p style="color:#e8edf2;margin-top:6px;line-height:1.6;white-space:pre-wrap;">${escapeHtml(d.description || 'No description')}</p>
                        </div>
                    </div>
                </div>
                
                <!-- Evidence -->
                ${d.evidence_ipfs_hash ? `
                <div style="margin-bottom:16px;">
                    <h4 style="color:#8a9bb0;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Evidence</h4>
                    <a href="https://gateway.pinata.cloud/ipfs/${d.evidence_ipfs_hash.replace('ipfs://', '')}" target="_blank" 
                       style="display:inline-flex;align-items:center;gap:6px;padding:10px 16px;background:rgba(0,102,255,0.1);color:#4d9eff;border:1px solid rgba(0,102,255,0.2);border-radius:8px;text-decoration:none;">
                        📎 View Evidence on IPFS
                    </a>
                </div>
                ` : ''}
                
                <!-- Resolution (if resolved) -->
                ${d.status !== 'open' && d.status !== 'under_review' ? `
                <div style="margin-bottom:16px;">
                    <h4 style="color:#8a9bb0;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Resolution</h4>
                    <div style="background:#111418;border:1px solid #242c35;border-radius:8px;padding:14px;">
                        <div style="margin-bottom:6px;">
                            <span style="color:#8a9bb0;">Outcome: </span>
                            <span style="color:#e8edf2;">${(d.outcome || 'no_change').replace(/_/g, ' ')}</span>
                        </div>
                        <div>
                            <span style="color:#8a9bb0;">Notes:</span>
                            <p style="color:#e8edf2;margin-top:4px;">${escapeHtml(d.resolution_notes || 'No notes')}</p>
                        </div>
                    </div>
                </div>
                ` : ''}
                
                <!-- Votes -->
                ${d.votes && d.votes.length > 0 ? `
                <div style="margin-bottom:16px;">
                    <h4 style="color:#8a9bb0;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Validator Votes (${d.votes_for || 0} For / ${d.votes_against || 0} Against)</h4>
                    <div style="background:#111418;border:1px solid #242c35;border-radius:8px;padding:14px;">
                        ${d.votes.map(v => `
                            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(36,44,53,0.5);">
                                <span style="color:#e8edf2;">${escapeHtml(v.full_name || v.validator_wallet)}</span>
                                <span class="badge badge-${v.vote === 'support' ? 'green' : 'red'}">${v.vote === 'support' ? '✓ For' : '✕ Against'}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
                ` : ''}
                
                <!-- Actions -->
                ${d.status === 'open' || d.status === 'under_review' ? `
                <div style="display:flex;gap:10px;margin-top:20px;">
                    <button class="btn btn-primary" onclick="this.closest('.modal-overlay').remove(); resolveDispute(${d.id});" style="flex:1;">
                        ✓ Resolve Dispute
                    </button>
                </div>
                ` : ''}
            </div>
        `;

    document.body.appendChild(modal);
    modal.addEventListener('click', function (e) { if (e.target === modal) modal.remove(); });

  } catch (err) {
    console.error('View dispute error:', err);
    toast('Failed to load dispute details', 'error');
  }
}
function resolveDispute(disputeId) {
  const existing = document.querySelector('.modal-overlay');
  if (existing) existing.remove();

  const modal = document.createElement('div');
  modal.className = 'modal-overlay';
  modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:center;z-index:1000;';
  modal.innerHTML = `
        <div style="background:#1a1f25;border:1px solid #242c35;border-radius:12px;padding:28px;max-width:500px;width:90%;">
            <h3 style="font-family:Syne,sans-serif;color:#e8edf2;margin-bottom:16px;">Resolve Dispute #${disputeId}</h3>
            
            <div style="margin-bottom:14px;">
                <label style="color:#8a9bb0;font-size:13px;display:block;margin-bottom:4px;">Resolution *</label>
                <select id="resolveOutcome" style="width:100%;padding:10px;background:#111418;border:1px solid #242c35;border-radius:6px;color:#e8edf2;">
                    <option value="resolved_complainant">Resolved - In Favor of Complainant</option>
                    <option value="resolved_respondent">Resolved - In Favor of Respondent</option>
                    <option value="dismissed">Dismissed</option>
                </select>
            </div>
            
            <div style="margin-bottom:14px;">
                <label style="color:#8a9bb0;font-size:13px;display:block;margin-bottom:4px;">Ownership Change?</label>
                <select id="resolveOwnership" style="width:100%;padding:10px;background:#111418;border:1px solid #242c35;border-radius:6px;color:#e8edf2;">
                    <option value="no_change">No Change</option>
                    <option value="ownership_changed">Change Ownership (requires blockchain)</option>
                </select>
            </div>
            
            <div style="margin-bottom:14px;">
                <label style="color:#8a9bb0;font-size:13px;display:block;margin-bottom:4px;">Notes *</label>
                <textarea id="resolveNotes" rows="4" required placeholder="Explain your decision..." style="width:100%;padding:10px;background:#111418;border:1px solid #242c35;border-radius:6px;color:#e8edf2;resize:vertical;"></textarea>
            </div>
            
            <div style="display:flex;gap:10px;margin-top:16px;">
                <button onclick="this.closest('.modal-overlay').remove()" style="flex:1;padding:10px;background:transparent;border:1px solid #242c35;border-radius:8px;color:#e8edf2;cursor:pointer;">Cancel</button>
                <button onclick="submitResolution(${disputeId})" style="flex:2;padding:10px;background:#00e5a0;color:#000;border:none;border-radius:8px;font-weight:600;cursor:pointer;">✓ Resolve Dispute</button>
            </div>
        </div>
    `;
  document.body.appendChild(modal);
  modal.addEventListener('click', function (e) { if (e.target === modal) modal.remove(); });
}

async function submitResolution(disputeId) {
  const status = document.getElementById('resolveOutcome').value;
  const outcome = document.getElementById('resolveOwnership').value;
  const notes = document.getElementById('resolveNotes').value.trim();

  if (!notes) {
    toast('Please enter resolution notes.', 'warn');
    return;
  }

  // Close modal
  document.querySelector('.modal-overlay').remove();

  toast('Resolving dispute...', 'info');

  const res = await api('/disputes/resolve', 'POST', {
    dispute_id: disputeId,
    status: status,
    outcome: outcome,
    notes: notes
  });

  if (res.success) {
    if (outcome === 'ownership_changed' && window.ethereum) {
      toast('Ownership change requires blockchain. Connecting wallet...', 'info');

      try {
        const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
        const provider = new ethers.BrowserProvider(window.ethereum);
        const signer = await provider.getSigner();
        const contract = new ethers.Contract(CONTRACT_ADDRESS, CONTRACT_ABI, signer);

        toast('Confirm transaction in MetaMask...', 'info');
        const tx = await contract.updateOwnershipAfterDispute(
          res.data.document_hash,
          res.data.new_owner_wallet,
          notes
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
      toast('✅ Dispute resolved!', 'success');
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
    const activeTab = document.querySelector('.tab-content.active');
    if (!activeTab) return;

    const tabId = activeTab.id.replace('tab-', '');
    currentTab = tabId;

    // Update badge counts (always)
    await updateBadges();

    // Refresh specific tab content
    switch (tabId) {
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
        // ✅ Skip overview — PHP handles it on page load/reload
    }
}

// Update badge counts in sidebar
async function updateBadges() {
  try {
    // Get all data
    const [kycRes, regRes, transferRes, disputeRes] = await Promise.all([
      api('/kyc/pending', 'GET'),
      api('/parcels/pending', 'GET'),
      api('/transfers/all', 'GET'),
      api('/disputes/all', 'GET')
    ]);

    // KYC badge - only pending
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

    // Registrations badge - only pending
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

    // ✅ Transfers badge - only PENDING (not approved/rejected)
    const transferBadge = document.querySelector('.nav-item[onclick*="transfers"] .badge');
    if (transferBadge && transferRes.success) {
      const pendingCount = transferRes.data.filter(t => t.status === 'pending').length;
      if (pendingCount > 0) {
        transferBadge.textContent = pendingCount;
        transferBadge.className = 'badge badge-yellow';
        transferBadge.style.display = 'inline';
      } else {
        transferBadge.style.display = 'none';
      }
    }

    // ✅ Disputes badge - only OPEN or UNDER_REVIEW (not resolved/dismissed)
    const disputeBadge = document.querySelector('.nav-item[onclick*="disputes"] .badge');
    if (disputeBadge && disputeRes.success) {
      const activeCount = disputeRes.data.filter(d => d.status === 'open' || d.status === 'under_review').length;
      if (activeCount > 0) {
        disputeBadge.textContent = activeCount;
        disputeBadge.className = 'badge badge-red';
        disputeBadge.style.display = 'inline';
      } else {
        disputeBadge.style.display = 'none';
      }
    }

    // Overview badge - total pending items
    const overviewBadge = document.querySelector('.nav-item[onclick*="overview"] .badge');
    if (overviewBadge) {
      const kycCount = kycRes.success ? (kycRes.data?.length || 0) : 0;
      const regCount = regRes.success ? (regRes.data?.length || 0) : 0;
      const transferCount = transferRes.success ? transferRes.data.filter(t => t.status === 'pending').length : 0;
      const disputeCount = disputeRes.success ? disputeRes.data.filter(d => d.status === 'open' || d.status === 'under_review').length : 0;
      const total = kycCount + regCount + transferCount + disputeCount;

      if (total > 0) {
        overviewBadge.textContent = total;
        overviewBadge.className = 'badge badge-red';
        overviewBadge.style.display = 'inline';
      } else {
        overviewBadge.style.display = 'none';
      }
    }

  } catch (e) {
    console.error('Badge update error:', e);
  }
}

// Refresh overview stats — only update badges, not stat card numbers
async function refreshOverviewStats() {
    // ✅ Only update badge counts — the stat cards are rendered by PHP
    // and we don't want JavaScript to overwrite them
    await updateBadges();
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