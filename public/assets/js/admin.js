// ============================================================
//  TerraChain v2 — Admin Panel JavaScript
// ============================================================

const API_BASE = "../api";

// ── Tab Switching ────────────────────────────────────
function switchTab(tabName, el) {
  // Hide all tabs
  document.querySelectorAll(".tab-content").forEach((tab) => {
    tab.classList.remove("active");
  });

  // Show selected tab
  const targetTab = document.getElementById("tab-" + tabName);
  if (targetTab) {
    targetTab.classList.add("active");
  }

  // Update active nav item
  document.querySelectorAll(".sidebar-nav .nav-item").forEach((item) => {
    item.classList.remove("active");
  });
  if (el) {
    el.classList.add("active");
  }

  // Update page title
  const titles = {
    overview: "Admin Overview",
    registrations: "Land Registrations",
    kyc: "KYC Verification",
    transfers: "Transfer Requests",
    disputes: "Disputes",
    settings: "Wallet Settings",
    blockchain: "Blockchain Status",
  };
  const titleEl = document.getElementById("tabTitle");
  if (titleEl) {
    titleEl.textContent = titles[tabName] || tabName;
  }
}

// ── Toast Notifications ──────────────────────────────
function toast(message, type = "info") {
  let container = document.getElementById("toast-container");
  if (!container) {
    container = document.createElement("div");
    container.id = "toast-container";
    container.style.cssText =
      "position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;";
    document.body.appendChild(container);
  }

  const icons = { success: "✅", error: "❌", info: "ℹ️", warn: "⚠️" };
  const colors = {
    success: "var(--accent)",
    error: "var(--danger)",
    info: "#4d9eff",
    warn: "var(--warn)",
  };

  const toastEl = document.createElement("div");
  toastEl.style.cssText = `
        background: var(--surface);
        border: 1px solid var(--border);
        border-left: 3px solid ${colors[type] || colors.info};
        border-radius: var(--radius);
        padding: 14px 18px;
        max-width: 420px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
        font-size: 13px;
        color: var(--text);
        box-shadow: 0 8px 32px rgba(0,0,0,0.4);
        animation: slideIn 0.3s ease;
        cursor: pointer;
    `;
  toastEl.innerHTML = `
        <span style="font-size:16px;flex-shrink:0;">${icons[type] || "🔔"}</span>
        <span style="flex:1;line-height:1.5;">${message}</span>
        <span style="cursor:pointer;color:var(--text3);font-size:14px;" onclick="this.parentElement.remove()">✕</span>
    `;
  toastEl.addEventListener("click", () => toastEl.remove());
  container.appendChild(toastEl);

  setTimeout(() => {
    if (toastEl.parentElement) {
      toastEl.style.opacity = "0";
      toastEl.style.transform = "translateX(100%)";
      toastEl.style.transition = "all 0.3s ease";
      setTimeout(() => toastEl.remove(), 300);
    }
  }, 5000);
}

// ── API Helper ───────────────────────────────────────
async function api(endpoint, method = "GET", body = null) {
  const opts = {
    method,
    headers: { "Content-Type": "application/json" },
    credentials: "same-origin",
  };
  if (body && method !== "GET") opts.body = JSON.stringify(body);

  try {
    const res = await fetch(API_BASE + endpoint, opts);
    return await res.json();
  } catch (e) {
    console.error("API error:", e);
    return { success: false, error: "Network error" };
  }
}

// ── Registration Actions ─────────────────────────────
async function approveRegistration(regId) {
  const reason = prompt("Add notes (optional):") || "";

  if (
    !confirm("Approve this registration? This will be recorded permanently.")
  ) {
    return;
  }

  toast("Approving registration...", "info");

  try {
    const res = await api("/parcels/approve", "POST", {
      registration_id: regId,
      notes: reason,
    });

    if (res.success) {
      toast(
        "Registration approved! " +
          (res.data?.blockchain_tx ? "⛓ Recorded on-chain" : ""),
        "success",
      );
      // Reload page after short delay
      setTimeout(() => location.reload(), 1500);
    } else {
      toast(res.data?.error || res.error || "Approval failed", "error");
    }
  } catch (err) {
    toast("Error: " + err.message, "error");
  }
}

async function rejectRegistration(regId) {
    // Ask for reason ONCE
    const reason = prompt('Please enter the rejection reason:');
    
    // If user cancels or leaves empty, do nothing
    if (reason === null || reason.trim() === '') {
        toast('Rejection cancelled - no reason provided', 'warn');
        return;
    }
    
    toast('Rejecting registration...', 'info');
    
    try {
        const res = await fetch(API_BASE + '/parcels/reject', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                registration_id: regId,
                reason: reason.trim()
            }),
            credentials: 'same-origin'
        });
        
        const data = await res.json();
        
        if (data.success) {
            toast('Registration rejected', 'warn');
            setTimeout(() => location.reload(), 1500);
        } else {
            toast(data.data?.error || 'Rejection failed', 'error');
        }
    } catch (err) {
        console.error('Reject error:', err);
        toast('Network error', 'error');
    }
}

// ── KYC Actions ──────────────────────────────────────
async function verifyKYC(kycId, approved) {
    if (approved) {
        // Approve - just confirm
        if (!confirm('Approve this KYC verification?')) {
            return;
        }
        
        toast('Verifying KYC...', 'info');
        
        try {
            const res = await fetch(API_BASE + '/kyc/verify', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    kyc_id: kycId,
                    approved: true
                }),
                credentials: 'same-origin'
            });
            
            const data = await res.json();
            
            if (data.success) {
                toast('KYC verified successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                toast(data.data?.error || 'Verification failed', 'error');
            }
        } catch (err) {
            console.error('KYC verify error:', err);
            toast('Network error', 'error');
        }
        
    } else {
        // Reject - ask for reason ONCE
        const reason = prompt('Please enter the rejection reason:');
        
        // If user cancels the prompt, do nothing
        if (reason === null || reason.trim() === '') {
            toast('Rejection cancelled - no reason provided', 'warn');
            return;
        }
        
        toast('Rejecting KYC...', 'info');
        
        try {
            const res = await fetch(API_BASE + '/kyc/verify', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    kyc_id: kycId,
                    approved: false,
                    reason: reason.trim()
                }),
                credentials: 'same-origin'
            });
            
            const data = await res.json();
            
            if (data.success) {
                toast('KYC rejected', 'warn');
                setTimeout(() => location.reload(), 1500);
            } else {
                toast(data.data?.error || 'Rejection failed', 'error');
            }
        } catch (err) {
            console.error('KYC reject error:', err);
            toast('Network error', 'error');
        }
    }
}

// ── Transfer Actions ─────────────────────────────────
async function approveTransfer(transferId) {
  if (
    !confirm("Approve this transfer? Ownership will be permanently changed.")
  ) {
    return;
  }

  toast("Approving transfer...", "info");

  try {
    const res = await api("/transfers/approve", "POST", {
      transfer_id: transferId,
    });

    if (res.success) {
      toast(
        "Transfer approved! Ownership updated." +
          (res.data?.blockchain_tx ? " ⛓ Recorded on-chain" : ""),
        "success",
      );
      setTimeout(() => location.reload(), 1500);
    } else {
      toast(res.data?.error || res.error || "Approval failed", "error");
    }
  } catch (err) {
    toast("Error: " + err.message, "error");
  }
}

async function rejectTransfer(transferId) {
    // Ask for reason ONCE
    const reason = prompt('Please enter the rejection reason:');
    
    if (reason === null || reason.trim() === '') {
        toast('Rejection cancelled - no reason provided', 'warn');
        return;
    }
    
    toast('Rejecting transfer...', 'info');
    
    try {
        const res = await fetch(API_BASE + '/transfers/reject', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                transfer_id: transferId,
                reason: reason.trim()
            }),
            credentials: 'same-origin'
        });
        
        const data = await res.json();
        
        if (data.success) {
            toast('Transfer rejected', 'warn');
            setTimeout(() => location.reload(), 1500);
        } else {
            toast(data.data?.error || 'Rejection failed', 'error');
        }
    } catch (err) {
        console.error('Reject error:', err);
        toast('Network error', 'error');
    }
}

// ── Dispute Actions ──────────────────────────────────
function viewDispute(disputeId) {
  toast("Dispute #" + disputeId + " details loading...", "info");
  // In production, this would open a modal with dispute details
}

function resolveDispute(disputeId) {
  const resolution = prompt("Resolution notes:");
  if (!resolution) return;

  const outcome = confirm(
    'Does ownership change? Click OK for "ownership_changed", Cancel for "no_change".',
  );

  toast("Resolving dispute...", "info");

  api("/disputes/resolve", "POST", {
    dispute_id: disputeId,
    status: "resolved_complainant",
    outcome: outcome ? "ownership_changed" : "no_change",
    notes: resolution,
  })
    .then((res) => {
      if (res.success) {
        toast(
          "Dispute resolved!" +
            (res.data?.blockchain_tx ? " ⛓ Recorded on-chain" : ""),
          "success",
        );
        setTimeout(() => location.reload(), 1500);
      } else {
        toast(res.data?.error || res.error || "Resolution failed", "error");
      }
    })
    .catch((err) => {
      toast("Error: " + err.message, "error");
    });
}

// ── Wallet Connection (Admin only) ───────────────────
async function connectWallet() {
  if (!window.ethereum) {
    toast("MetaMask not detected. Please install MetaMask extension.", "error");
    return;
  }

  try {
    toast("Connecting to MetaMask...", "info");

    const accounts = await window.ethereum.request({
      method: "eth_requestAccounts",
    });
    const walletAddress = accounts[0];

    // Save wallet to backend
    const res = await api("/auth/wallet", "POST", {
      wallet_address: walletAddress,
    });

    if (res.success) {
      toast(
        "Wallet linked successfully! " +
          walletAddress.slice(0, 6) +
          "..." +
          walletAddress.slice(-4),
        "success",
      );
      setTimeout(() => location.reload(), 1500);
    } else {
      toast(res.data?.error || "Failed to link wallet", "error");
    }
  } catch (err) {
    if (err.code === 4001) {
      toast("Connection rejected by user", "warn");
    } else {
      toast("Error: " + err.message, "error");
    }
  }
}

// ── Logout ───────────────────────────────────────────
async function logout() {
  try {
    await api("/auth/logout", "POST");
  } catch (e) {
    console.error("Logout error:", e);
  }
  // Always redirect to landing page at root level
  window.location.href = "./";
}

// ── Load Activity Log ────────────────────────────────
async function loadActivityLog() {
  const logContainer = document.getElementById("activityLog");
  if (!logContainer) return;

  try {
    const res = await api("/parcels/pending"); // Using pending as activity indicator
    // In production, you'd have a dedicated activity endpoint

    if (res.success) {
      logContainer.innerHTML =
        '<div class="empty-state"><p>Admin panel ready. Use the sidebar to manage registrations, KYC, and transfers.</p></div>';
    }
  } catch (e) {
    logContainer.innerHTML =
      '<div class="empty-state"><p>Unable to load activity</p></div>';
  }
}

// ── Initialize ───────────────────────────────────────
document.addEventListener("DOMContentLoaded", function () {
  loadActivityLog();

  // Handle browser back/forward for tabs
  window.addEventListener("popstate", function (e) {
    if (e.state && e.state.tab) {
      switchTab(
        e.state.tab,
        document.querySelector(`[onclick*="${e.state.tab}"]`),
      );
    }
  });
});
