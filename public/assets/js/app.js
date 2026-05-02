// ============================================================
//  TerraChain v2 — Frontend Application
// ============================================================

const API_BASE = '/terrachain-v2/api';

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

  const toast = document.createElement("div");
  toast.style.cssText = `
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
  toast.innerHTML = `
        <span style="font-size:16px;flex-shrink:0;">${icons[type] || "🔔"}</span>
        <span style="flex:1;line-height:1.5;">${message}</span>
        <span style="cursor:pointer;color:var(--text3);font-size:14px;" onclick="this.parentElement.remove()">✕</span>
    `;
  toast.addEventListener("click", () => toast.remove());
  container.appendChild(toast);

  setTimeout(() => {
    if (toast.parentElement) {
      toast.style.opacity = "0";
      toast.style.transform = "translateX(100%)";
      toast.style.transition = "all 0.3s ease";
      setTimeout(() => toast.remove(), 300);
    }
  }, 5000);
}

// ── Section Navigation ───────────────────────────────
async function showSection(sectionName) {
  // Hide all sections
  document
    .querySelectorAll(".content-section")
    .forEach((s) => s.classList.remove("active"));

  // Show target section
  const sectionEl = document.getElementById("section-" + sectionName);
  if (!sectionEl) {
    console.error("Section not found:", sectionName);
    return;
  }

  sectionEl.classList.add("active");

  // Load content if not already loaded
  if (!sectionEl.dataset.loaded) {
    sectionEl.innerHTML = '<div class="empty-state"><p>Loading...</p></div>';
    try {
      const res = await fetch(`${API_BASE}/sections/${sectionName}.php`);
      if (res.ok) {
        const html = await res.text();
        sectionEl.innerHTML = html;
        sectionEl.dataset.loaded = "true";

        // Initialize section-specific scripts
        if (sectionName === "register") initRegistrationForm();
        if (sectionName === "kyc") initKYCForm();
        if (sectionName === "transfers") loadTransfers();
        if (sectionName === "disputes") loadDisputes();
      } else {
        sectionEl.innerHTML =
          '<div class="error-state">Failed to load section</div>';
      }
    } catch (e) {
      sectionEl.innerHTML = '<div class="error-state">Network error</div>';
    }
  }

  // Update active nav
  document
    .querySelectorAll(".nav-item")
    .forEach((n) => n.classList.remove("active"));
  const navItem = document.querySelector(
    `.nav-item[onclick*="${sectionName}"]`,
  );
  if (navItem) navItem.classList.add("active");

  // Update page title
  const titles = {
    "my-properties": "My Properties",
    register: "Register Land",
    browse: "Browse Properties",
    kyc: "KYC Verification",
    transfers: "My Transfers",
    disputes: "Disputes",
    profile: "My Profile",
  };
  const titleEl = document.getElementById("pageTitle");
  const subtitleEl = document.getElementById("pageSubtitle");
  if (titleEl) titleEl.textContent = titles[sectionName] || sectionName;
  if (subtitleEl) subtitleEl.textContent = "";
}

// ── API Helpers ──────────────────────────────────────
async function api(endpoint, method = "GET", body = null) {
  const opts = {
    method,
    headers: { "Content-Type": "application/json" },
    credentials: "same-origin",
  };
  if (body && method !== "GET") opts.body = JSON.stringify(body);

  try {
    const res = await fetch(`${API_BASE}${endpoint}`, opts);
    return await res.json();
  } catch (e) {
    return { success: false, error: "Network error" };
  }
}

async function apiUpload(endpoint, formData) {
  try {
    const res = await fetch(`${API_BASE}${endpoint}`, {
      method: "POST",
      body: formData,
      credentials: "same-origin",
    });
    return await res.json();
  } catch (e) {
    return { success: false, error: "Upload failed" };
  }
}

// ── KYC Functions ────────────────────────────────────
function initKYCForm() {
  const form = document.getElementById("kycForm");
  if (!form) return;

  form.addEventListener("submit", async function (e) {
    e.preventDefault();
    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Submitting...';

    const formData = new FormData(form);
    try {
      const res = await apiUpload("/kyc/submit", formData);
      if (res.success) {
        toast("KYC submitted successfully! Awaiting verification.", "success");
        form.reset();
        document.getElementById("kycStatus").innerHTML = `
                    <div class="alert alert-info">
                        <span class="alert-icon">⏳</span>
                        <div><strong>KYC Under Review</strong>
                        <p>Your documents are being reviewed.</p></div>
                    </div>`;
      } else {
        toast(res.data?.error || res.error || "Submission failed", "error");
      }
    } catch (err) {
      toast("Network error", "error");
    } finally {
      btn.disabled = false;
      btn.innerHTML = "Submit KYC";
    }
  });
}

// ── Registration Functions ───────────────────────────
function initRegistrationForm() {
  const form = document.getElementById("registrationForm");
  if (!form) return;

  form.addEventListener("submit", async function (e) {
    e.preventDefault();
    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Submitting...';

    const formData = new FormData(form);
    try {
      const res = await apiUpload("/parcels/submit", formData);
      if (res.success) {
        toast("Registration submitted! Awaiting admin review.", "success");
        form.reset();
      } else {
        toast(res.data?.error || res.error || "Submission failed", "error");
      }
    } catch (err) {
      toast("Network error", "error");
    } finally {
      btn.disabled = false;
      btn.innerHTML = "Submit Registration";
    }
  });
}

// ── Transfer Functions ───────────────────────────────
async function loadTransfers() {
  const container = document.getElementById("transfersList");
  if (!container) return;

  container.innerHTML =
    '<div class="empty-state"><p>Loading transfers...</p></div>';

  try {
    const res = await api("/transfers/my");
    if (res.success && res.data.length > 0) {
      container.innerHTML = renderTransfersTable(res.data);
    } else {
      container.innerHTML =
        '<div class="empty-state"><div class="empty-icon">⇄</div><p>No transfers yet</p></div>';
    }
  } catch (e) {
    container.innerHTML =
      '<div class="error-state">Failed to load transfers</div>';
  }
}

function renderTransfersTable(transfers) {
  return `
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Parcel</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    ${transfers
                      .map(
                        (t) => `
                        <tr>
                            <td><span class="badge badge-blue">#${t.id}</span></td>
                            <td>${escapeHtml(t.parcel_title || "N/A")}</td>
                            <td>${escapeHtml(t.transfer_type)}</td>
                            <td>${statusBadge(t.status)}</td>
                            <td>${formatDate(t.created_at)}</td>
                        </tr>
                    `,
                      )
                      .join("")}
                </tbody>
            </table>
        </div>
    `;
}

function openTransferModal(parcelNumber) {
  const modal = document.createElement("div");
  modal.className = "modal-overlay";
  modal.style.cssText =
    "position:fixed;inset:0;background:rgba(0,0,0,0.7);display:flex;align-items:center;justify-content:center;z-index:1000;";
  modal.innerHTML = `
        <div class="modal" style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:28px;max-width:500px;width:90%;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h3 style="font-family:'Syne',sans-serif;">Transfer Ownership</h3>
                <button onclick="this.closest('.modal-overlay').remove()" style="background:none;border:none;color:var(--text3);font-size:20px;cursor:pointer;">✕</button>
            </div>
            <form id="transferForm">
                <input type="hidden" id="transferParcelNumber" value="${parcelNumber}">
                <div class="form-group">
                    <label>Parcel Number</label>
                    <input type="text" value="${parcelNumber}" readonly>
                </div>
                <div class="form-group">
                    <label>Recipient Email *</label>
                    <input type="email" id="recipientEmail" required placeholder="recipient@example.com">
                </div>
                <div class="form-group">
                    <label>Transfer Type</label>
                    <select id="transferType">
                        <option value="sale">Sale</option>
                        <option value="gift">Gift</option>
                        <option value="inheritance">Inheritance</option>
                        <option value="court_order">Court Order</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Supporting Document</label>
                    <input type="file" id="supportingDoc" accept=".pdf,.jpg,.jpeg,.png">
                </div>
                <button type="submit" class="btn btn-primary btn-full">Submit Transfer Request</button>
            </form>
        </div>
    `;
  document.body.appendChild(modal);

  modal.addEventListener("click", function (e) {
    if (e.target === modal) modal.remove();
  });

  document
    .getElementById("transferForm")
    .addEventListener("submit", async function (e) {
      e.preventDefault();
      const formData = new FormData();
      formData.append("parcel_number", parcelNumber);
      formData.append(
        "recipient_email",
        document.getElementById("recipientEmail").value,
      );
      formData.append(
        "transfer_type",
        document.getElementById("transferType").value,
      );

      const docFile = document.getElementById("supportingDoc").files[0];
      if (docFile) formData.append("supporting_doc", docFile);

      try {
        const res = await apiUpload("/transfers/request", formData);
        if (res.success) {
          toast("Transfer request submitted!", "success");
          modal.remove();
        } else {
          toast(res.data?.error || "Transfer failed", "error");
        }
      } catch (err) {
        toast("Network error", "error");
      }
    });
}

// ── Dispute Functions ────────────────────────────────
async function loadDisputes() {
  const container = document.getElementById("disputesList");
  if (!container) return;

  container.innerHTML =
    '<div class="empty-state"><p>Loading disputes...</p></div>';

  try {
    const res = await api("/disputes/all");
    if (res.success && res.data.length > 0) {
      container.innerHTML = renderDisputesTable(res.data);
    } else {
      container.innerHTML =
        '<div class="empty-state"><div class="empty-icon">⚖️</div><p>No disputes filed</p></div>';
    }
  } catch (e) {
    container.innerHTML =
      '<div class="error-state">Failed to load disputes</div>';
  }
}

function renderDisputesTable(disputes) {
  return `
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Parcel</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    ${disputes
                      .map(
                        (d) => `
                        <tr>
                            <td><span class="badge badge-blue">#${d.id}</span></td>
                            <td>${escapeHtml(d.parcel_title || "N/A")}</td>
                            <td>${escapeHtml(d.dispute_type)}</td>
                            <td>${statusBadge(d.status)}</td>
                            <td>${formatDate(d.created_at)}</td>
                        </tr>
                    `,
                      )
                      .join("")}
                </tbody>
            </table>
        </div>
    `;
}

// ── Notifications ────────────────────────────────────
function toggleNotifications() {
  const panel = document.getElementById("notifPanel");
  if (panel) {
    panel.style.display = panel.style.display === "none" ? "block" : "none";
  }
}

function markAllRead() {
  fetch(`${API_BASE}/notifications/read-all`, {
    method: "POST",
    credentials: "same-origin",
  }).then(() => {
    const badge = document.querySelector(".notif-badge");
    if (badge) badge.remove();
    toast("All notifications marked as read", "info");
  });
}

// ── Logout ───────────────────────────────────────────
async function logout() {
  try {
    await fetch("../api/auth/logout", {
      method: "POST",
      credentials: "same-origin",
    });
  } catch (e) {
    console.error("Logout error:", e);
  }
  window.location.href = "./";
}

// ── Utility Functions ────────────────────────────────
function statusBadge(status) {
  const badges = {
    owned: '<span class="badge badge-green">Owned</span>',
    pending: '<span class="badge badge-yellow">Pending</span>',
    approved: '<span class="badge badge-green">Approved</span>',
    completed: '<span class="badge badge-green">Completed</span>',
    rejected: '<span class="badge badge-red">Rejected</span>',
    open: '<span class="badge badge-yellow">Open</span>',
    under_review: '<span class="badge badge-blue">Under Review</span>',
    resolved_complainant: '<span class="badge badge-green">Resolved</span>',
    resolved_respondent: '<span class="badge badge-green">Resolved</span>',
    dismissed: '<span class="badge badge-red">Dismissed</span>',
    transferred: '<span class="badge badge-blue">Transferred</span>',
    disputed: '<span class="badge badge-red">Disputed</span>',
  };
  return badges[status] || `<span class="badge">${status}</span>`;
}

function formatDate(dateStr) {
  if (!dateStr) return "—";
  const d = new Date(dateStr);
  return d.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

function escapeHtml(str) {
  if (!str) return "";
  const div = document.createElement("div");
  div.textContent = str;
  return div.innerHTML;
}

function shortenAddr(addr) {
  if (!addr) return "—";
  return addr.slice(0, 6) + "..." + addr.slice(-4);
}

// ── File Drop Zone ───────────────────────────────────
document.addEventListener("DOMContentLoaded", function () {
  const dropZones = document.querySelectorAll(".file-upload-area");
  dropZones.forEach((zone) => {
    zone.addEventListener("dragover", (e) => {
      e.preventDefault();
      zone.classList.add("drag-over");
    });
    zone.addEventListener("dragleave", () => {
      zone.classList.remove("drag-over");
    });
    zone.addEventListener("drop", (e) => {
      e.preventDefault();
      zone.classList.remove("drag-over");
      const input = zone.querySelector('input[type="file"]');
      if (input && e.dataTransfer.files.length) {
        input.files = e.dataTransfer.files;
        input.dispatchEvent(new Event("change"));
      }
    });
  });

  // Close notifications on outside click
  document.addEventListener("click", function (e) {
    const panel = document.getElementById("notifPanel");
    const btn = document.querySelector(".notif-btn");
    if (panel && btn && !btn.contains(e.target) && !panel.contains(e.target)) {
      panel.style.display = "none";
    }
  });
});
