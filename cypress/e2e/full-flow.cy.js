// full-flow-tour.cy.js
describe("TerraChain — Full Tour Mode (No Recording)", () => {
  const admin = {
    username: "admin",
    password: "password",
  };

  const user1 = {
    username: "testuser_tour",
    email: "testuser_tour@test.com",
    password: "TestPass123!",
    fullName: "Tour Test User",
    phone: "+237 600 000 001",
    nationalId: "CM123456789",
  };

  const user2 = {
    username: "testuser2_tour",
    email: "testuser2_tour@test.com",
    password: "TestPass456!",
    fullName: "Tour Test User 2",
    phone: "+237 600 000 002",
    nationalId: "CM987654321",
  };

  // ── Mock Data ──────────────────────────────────────────
  const mockParcelNumber = "TC-TOUR-001";
  const mockKYCId = 999;
  const mockRegistrationId = 888;
  const mockTransferId = 777;
  const mockDisputeId = 666;
  const mockTxHash = "0x" + "a".repeat(64);

  // ── API Intercepts ─────────────────────────────────────
  function interceptAllAPIs() {
    // ── Auth ──────────────────────────────────────────────
    cy.intercept("POST", "**/api/auth/login", (req) => {
      req.reply({
        success: true,
        data: { user_id: 1, requires_otp: true },
      });
    }).as("login");

    cy.intercept("POST", "**/api/auth/verify-otp", (req) => {
      req.reply({
        success: true,
        data: {
          user: {
            id: 1,
            username: req.body.username || "admin",
            full_name: "Admin User",
            role: req.body.username === "admin" ? "admin" : "user",
            email: "admin@terrachain.com",
            wallet_address: "0x123456789abcdef",
          },
        },
      });
    }).as("verifyOtp");

    cy.intercept("POST", "**/api/auth/logout", { success: true });

    // ── KYC ──────────────────────────────────────────────
    cy.intercept("POST", "**/api/kyc/submit", {
      success: true,
      data: {
        id: mockKYCId,
        status: "pending",
        message: "KYC submitted successfully",
      },
    }).as("kycSubmit");

    cy.intercept("GET", "**/api/kyc/status", (req) => {
      const statusMap = {
        testuser_tour: "verified",
        testuser2_tour: "verified",
        admin: "verified",
      };
      const username = req.headers.cookie?.match(/username=([^;]+)/)?.[1] || "";
      req.reply({
        success: true,
        data: {
          status: statusMap[username] || "verified",
          full_name:
            username === "testuser_tour" ? "Tour Test User" : "Admin User",
          email:
            username === "testuser_tour"
              ? "testuser_tour@test.com"
              : "admin@terrachain.com",
          submitted_at: new Date().toISOString(),
        },
      });
    }).as("kycStatus");

    cy.intercept("POST", "**/api/kyc/verify", {
      success: true,
      data: { status: "verified" },
    }).as("kycVerify");

    cy.intercept("GET", "**/api/kyc/pending", {
      success: true,
      data: [
        {
          id: 1,
          full_name: "Test User",
          email: "test@test.com",
          submitted_at: new Date().toISOString(),
        },
      ],
    }).as("kycPending");

    // ── Parcels ──────────────────────────────────────────
    cy.intercept("POST", "**/api/parcels/submit", {
      success: true,
      data: {
        id: mockRegistrationId,
        parcel_number: mockParcelNumber,
        status: "pending",
        message: "Registration submitted successfully",
      },
    }).as("parcelSubmit");

    cy.intercept("POST", "**/api/parcels/approve", (req) => {
      if (req.body.tx_hash) {
        // Final approval with tx_hash
        req.reply({
          success: true,
          data: {
            status: "approved",
            tx_hash: req.body.tx_hash,
            parcel_number: mockParcelNumber,
          },
        });
      } else {
        // Initial approval
        req.reply({
          success: true,
          data: {
            status: "pending_blockchain",
            document_hash: "Qm" + "b".repeat(44),
            wallet_used: "0x123456789abcdef",
          },
        });
      }
    }).as("parcelApprove");

    cy.intercept("POST", "**/api/parcels/reject", {
      success: true,
      data: { status: "rejected" },
    }).as("parcelReject");

    cy.intercept("GET", "**/api/parcels/pending", {
      success: true,
      data: [
        {
          id: mockRegistrationId,
          title: "Tour Test Plot Alpha",
          location_address: "123 Test Street, Yaoundé",
          applicant_name: "Tour Test User",
          applicant_email: "testuser_tour@test.com",
          submitted_at: new Date().toISOString(),
          document_url: "https://gateway.pinata.cloud/ipfs/QmTest123",
        },
      ],
    }).as("parcelsPending");

    cy.intercept("GET", "**/api/parcels/my", {
      success: true,
      data: [
        {
          id: 1,
          parcel_number: mockParcelNumber,
          title: "Tour Test Plot Alpha",
          location_address: "123 Test Street, Yaoundé",
          status: "owned",
          owner_name: "Tour Test User",
          property_type: "residential",
          size_sqm: 500,
          gps_lat: 3.8721,
          gps_lng: 11.5082,
          description: "Tour test parcel",
        },
      ],
    }).as("parcelsMy");

    cy.intercept("GET", "**/api/parcels/all", {
      success: true,
      data: [
        {
          id: 1,
          parcel_number: mockParcelNumber,
          title: "Tour Test Plot Alpha",
          location_address: "123 Test Street, Yaoundé",
          status: "owned",
          owner_name: "Tour Test User",
          property_type: "residential",
        },
        {
          id: 2,
          parcel_number: "TC-TOUR-002",
          title: "Tour Test Plot Beta",
          location_address: "456 Tour Avenue, Douala",
          status: "owned",
          owner_name: "Tour Test User 2",
          property_type: "commercial",
        },
      ],
    }).as("parcelsAll");

    cy.intercept("GET", "**/api/parcels/get*", (req) => {
      req.reply({
        success: true,
        data: {
          id: 1,
          parcel_number: mockParcelNumber,
          title: "Tour Test Plot Alpha",
          location_address: "123 Test Street, Yaoundé",
          status: "owned",
          owner_name: "Tour Test User",
          property_type: "residential",
          size_sqm: 500,
          description: "Tour test parcel",
          documents: [{ file_name: "land-deed.pdf", ipfs_hash: "QmTest123" }],
        },
      });
    }).as("parcelGet");

    // ── Transfers ─────────────────────────────────────────
    cy.intercept("POST", "**/api/transfers/request", {
      success: true,
      data: {
        id: mockTransferId,
        status: "pending",
        message: "Transfer request submitted",
      },
    }).as("transferRequest");

    cy.intercept("POST", "**/api/transfers/approve", (req) => {
      if (req.body.tx_hash) {
        req.reply({
          success: true,
          data: {
            status: "approved",
            tx_hash: req.body.tx_hash,
          },
        });
      } else {
        req.reply({
          success: true,
          data: {
            status: "pending_blockchain",
            document_hash: "Qm" + "c".repeat(44),
            new_owner_wallet: "0x987654321fedcba",
          },
        });
      }
    }).as("transferApprove");

    cy.intercept("POST", "**/api/transfers/reject", {
      success: true,
      data: { status: "rejected" },
    }).as("transferReject");

    cy.intercept("GET", "**/api/transfers/all", {
      success: true,
      data: [
        {
          id: mockTransferId,
          parcel_title: "Tour Test Plot Alpha",
          sender_name: "Tour Test User",
          recipient_name: "Tour Test User 2",
          transfer_type: "sale",
          status: "pending",
          created_at: new Date().toISOString(),
        },
      ],
    }).as("transfersAll");

    cy.intercept("GET", "**/api/transfers/my", {
      success: true,
      data: [
        {
          id: mockTransferId,
          parcel_title: "Tour Test Plot Alpha",
          transfer_type: "sale",
          status: "pending",
          created_at: new Date().toISOString(),
        },
      ],
    }).as("transfersMy");

    // ── Disputes ──────────────────────────────────────────
    cy.intercept("POST", "**/api/disputes/file", {
      success: true,
      data: {
        id: mockDisputeId,
        status: "open",
        message: "Dispute filed successfully",
      },
    }).as("disputeFile");

    cy.intercept("POST", "**/api/disputes/resolve", {
      success: true,
      data: {
        status: "resolved",
        document_hash: "Qm" + "d".repeat(44),
        new_owner_wallet: "0x123456789abcdef",
      },
    }).as("disputeResolve");

    cy.intercept("POST", "**/api/disputes/get", {
      success: true,
      data: {
        id: mockDisputeId,
        parcel_title: "Tour Test Plot Alpha",
        parcel_number: mockParcelNumber,
        location_address: "123 Test Street, Yaoundé",
        complainant_name: "Tour Test User",
        complainant_email: "testuser_tour@test.com",
        respondent_name: "Tour Test User 2",
        respondent_email: "testuser2_tour@test.com",
        dispute_type: "ownership",
        description: "Test dispute - transfer was in error.",
        status: "open",
        created_at: new Date().toISOString(),
        votes: [],
        votes_for: 0,
        votes_against: 0,
      },
    }).as("disputeGet");

    cy.intercept("GET", "**/api/disputes/all", {
      success: true,
      data: [
        {
          id: mockDisputeId,
          parcel_title: "Tour Test Plot Alpha",
          complainant_name: "Tour Test User",
          dispute_type: "ownership",
          status: "open",
          created_at: new Date().toISOString(),
        },
      ],
    }).as("disputesAll");

    // ── Notifications ─────────────────────────────────────
    cy.intercept("GET", "**/api/notifications/list", {
      success: true,
      data: {
        notifications: [
          {
            id: 1,
            title: "Welcome",
            message: "Welcome to TerraChain!",
            is_read: false,
            created_at: new Date().toISOString(),
          },
        ],
        unread_count: 1,
      },
    }).as("notifList");

    cy.intercept("POST", "**/api/notifications/read-all", {
      success: true,
    }).as("notifReadAll");

    cy.intercept("POST", "**/api/notifications/mark-read-one", {
      success: true,
    }).as("notifReadOne");

    // ── Wallet ────────────────────────────────────────────
    cy.intercept("POST", "**/api/auth/wallet", {
      success: true,
      data: { wallet_address: "0x123456789abcdef" },
    }).as("walletConnect");

    // ── Blockchain Mock ───────────────────────────────────
    cy.intercept("POST", "**/api/blockchain/*", {
      success: true,
      data: { tx_hash: mockTxHash },
    }).as("blockchainCall");

    // ── Admin Details ─────────────────────────────────────
    cy.intercept("GET", "**/api/admin/get-transfer-details*", {
      success: true,
      transfer: {
        id: mockTransferId,
        parcel_title: "Tour Test Plot Alpha",
        parcel_number: mockParcelNumber,
        sender_name: "Tour Test User",
        sender_email: "testuser_tour@test.com",
        recipient_name: "Tour Test User 2",
        recipient_email: "testuser2_tour@test.com",
        transfer_type: "sale",
        status: "pending",
        documents: [
          { document_name: "transfer-agreement.pdf", ipfs_cid: "QmTest456" },
        ],
      },
    }).as("transferDetails");

    // ── User Info ─────────────────────────────────────────
    cy.intercept("GET", "**/api/auth/me", (req) => {
      const cookie = req.headers.cookie || "";
      let username = "admin";
      if (cookie.includes("testuser_tour")) username = "testuser_tour";
      else if (cookie.includes("testuser2_tour")) username = "testuser2_tour";

      req.reply({
        success: true,
        data: {
          user: {
            id: username === "admin" ? 0 : 1,
            username: username,
            email:
              username === "admin"
                ? "admin@terrachain.com"
                : username + "@test.com",
            full_name:
              username === "admin"
                ? "Admin User"
                : username === "testuser_tour"
                  ? "Tour Test User"
                  : "Tour Test User 2",
            role: username === "admin" ? "admin" : "user",
          },
        },
      });
    }).as("authMe");
  }

  // ── Login Helper (with mocked OTP) ──────────────────
  function loginWithMock(username, password, role = "user") {
    cy.visit("/login.php");
    cy.wait(500);

    cy.get("#username").type(username);
    cy.get("#password").type(password);
    cy.get("#loginBtn").click();

    // Wait for OTP form
    cy.get("#otpForm", { timeout: 15000 }).should("be.visible");
    cy.get("#otp").type("123456");
    cy.get("#verifyBtn").click();

    // Wait for redirect
    const expectedUrl = role === "admin" ? "admin.php" : "dashboard.php";
    cy.url({ timeout: 15000 }).should("include", expectedUrl);
  }

  // ── Helper to fill forms ──────────────────────────────
  function fillRegistrationForm() {
    cy.get("#regTitle").type("Tour Test Plot Alpha");
    cy.get("#regLocation").type("123 Test Street, Yaoundé, Cameroon");
    cy.get("#regSize").type("500");
    cy.get("#regType").select("residential");
    cy.get("#regGPS").type("3.8721, 11.5082");
    cy.get("#regDesc").type("Tour test parcel created by Cypress");
    cy.get("#regFileInput").attachFile("test-docs/land-deed.pdf");
  }

  // ═══════════════════════════════════════════════════════════
  //  TEST SUITE — FULL TOUR (NO DATABASE WRITES)
  // ═══════════════════════════════════════════════════════════

  before(() => {
    // Set up all intercepts before any tests
    interceptAllAPIs();
  });

  // ── 1. REGISTRATION ─────────────────────────────────────

  it("shows the registration page", () => {
    cy.visit("/register.php");
    cy.get("#full_name").should("be.visible");
    cy.get("#username").should("be.visible");
    cy.get("#email").should("be.visible");
    cy.get("#phone").should("be.visible");
    cy.get("#national_id").should("be.visible");
    cy.get("#password").should("be.visible");
    cy.get("#confirm_password").should("be.visible");
    cy.get('input[type="checkbox"]').should("be.visible");
    cy.get("#registerBtn").should("be.visible");

    // Fill registration form (but it won't actually save)
    cy.get("#full_name").type("Tour Test User");
    cy.get("#username").type("testuser_tour");
    cy.get("#email").type("testuser_tour@test.com");
    cy.get("#phone").type("+237 600 000 001");
    cy.get("#national_id").type("CM123456789");
    cy.get("#password").type("TestPass123!");
    cy.get("#confirm_password").type("TestPass123!");
    cy.get('input[type="checkbox"]').check();
    cy.get("#registerBtn").click();

    // Should redirect to login
    cy.url({ timeout: 10000 }).should("include", "login.php");
    cy.contains("Sign In").should("be.visible");
  });

  it("shows the login page", () => {
    cy.visit("/login.php");
    cy.get("#username").should("be.visible");
    cy.get("#password").should("be.visible");
    cy.get("#loginBtn").should("be.visible");
    cy.contains("Don't have an account?").should("be.visible");
  });

  // ── 2. DASHBOARD TOUR ──────────────────────────────────

  it("logs in as user and shows dashboard", () => {
    loginWithMock("testuser_tour", "TestPass123!");

    // Dashboard should be visible
    cy.get(".page-title").should("contain", "Dashboard");
    cy.get(".stats-grid").should("be.visible");
    cy.get(".stat-card").should("have.length.at.least", 3);

    // Sidebar navigation should be visible
    cy.contains("My Properties").should("be.visible");
    cy.contains("Register Land").should("be.visible");
    cy.contains("Browse All").should("be.visible");
    cy.contains("KYC Verification").should("be.visible");
    cy.contains("My Transfers").should("be.visible");
    cy.contains("Disputes").should("be.visible");
    cy.contains("Profile").should("be.visible");
  });

  // ── 3. KYC TOUR ────────────────────────────────────────

  it("shows KYC section with submit option", () => {
    // Already logged in from previous test
    cy.contains("KYC Verification").click();
    cy.wait(500);

    cy.get(".kyc-status").should("be.visible");
    cy.contains("Submit KYC Now").click();
    cy.get("#kycFileInput").should("be.visible");
    cy.get("#kycSubmitBtn").should("be.visible");
  });

  // ── 4. REGISTER LAND TOUR ─────────────────────────────

  it("shows registration form and submits (mocked)", () => {
    cy.contains("Register Land").click();
    cy.wait(500);

    fillRegistrationForm();
    cy.get("#submitRegBtn").click();

    // Should show success message
    cy.contains("Registration submitted", { timeout: 10000 }).should(
      "be.visible",
    );
  });

  // ── 5. BROWSE PROPERTIES TOUR ──────────────────────────

  it("shows all properties", () => {
    cy.contains("Browse All").click();
    cy.wait(500);

    cy.get(".table-wrapper").should("be.visible");
    cy.get("tbody tr").should("have.length.at.least", 1);
    cy.contains("Tour Test Plot Alpha").should("be.visible");
  });

  // ── 6. MY PROPERTIES TOUR ─────────────────────────────

  it("shows user's properties", () => {
    cy.contains("My Properties").click();
    cy.wait(500);

    cy.get(".table-wrapper").should("be.visible");
    cy.contains("Tour Test Plot Alpha").should("be.visible");
    cy.get("button:contains('Transfer')").should("be.visible");
  });

  // ── 7. TRANSFER TOUR ───────────────────────────────────

  it("shows transfer modal and submits", () => {
    cy.contains("My Properties").click();
    cy.wait(500);

    cy.get("button:contains('Transfer')").first().click();
    cy.wait(500);

    cy.get("#recipientEmail").should("be.visible");
    cy.get("#recipientEmail").type("testuser2_tour@test.com");
    cy.get("#transferType").select("sale");
    cy.get("#supportingDoc").attachFile("test-docs/transfer-agreement.pdf");
    cy.get("button:contains('Submit Transfer Request')").click();

    cy.contains("Transfer request submitted", { timeout: 10000 }).should(
      "be.visible",
    );
  });

  // ── 8. DISPUTE TOUR ────────────────────────────────────

  it("shows dispute modal and submits", () => {
    cy.contains("Disputes").click();
    cy.wait(500);

    cy.contains("File New Dispute").click();
    cy.wait(500);

    cy.get("#disputeParcelNumber").type("TC-TOUR-001");
    cy.get("#disputeType").select("ownership");
    cy.get("#disputeRespondentEmail").type("testuser2_tour@test.com");
    cy.get("#disputeDescription").type("Test dispute - transfer was in error.");
    cy.get("#disputeEvidence").attachFile("test-docs/dispute-evidence.pdf");
    cy.get("button:contains('File Dispute')").click();

    cy.contains("Dispute filed", { timeout: 10000 }).should("be.visible");
  });

  // ── 9. TRANSFERS LIST TOUR ────────────────────────────

  it("shows transfers list", () => {
    cy.contains("My Transfers").click();
    cy.wait(500);

    cy.get(".table-wrapper").should("be.visible");
    cy.contains("sale").should("be.visible");
  });

  // ── 10. PROFILE TOUR ──────────────────────────────────

  it("shows user profile", () => {
    cy.contains("Profile").click();
    cy.wait(500);

    cy.get(".info-row").should("have.length.at.least", 3);
    cy.contains("testuser_tour").should("be.visible");
    cy.contains("testuser_tour@test.com").should("be.visible");
  });

  // ── 11. LOGOUT ─────────────────────────────────────────

  it("logs out successfully", () => {
    cy.contains("Sign Out").click();
    cy.url({ timeout: 5000 }).should("include", "login.php");
  });

  // ── 12. ADMIN TOUR ─────────────────────────────────────

  it("logs in as admin", () => {
    loginWithMock("admin", "password", "admin");
    cy.url().should("include", "admin.php");
    cy.get(".page-title").should("contain", "Admin Overview");
  });

  it("shows admin overview with stats", () => {
    cy.get(".stats-grid").should("be.visible");
    cy.get(".stat-card").should("have.length.at.least", 4);
  });

  it("shows all registered properties in admin", () => {
    cy.get(".card:contains('All Registered Properties')").should("be.visible");
    cy.contains("Tour Test Plot Alpha").should("be.visible");
  });

  it("shows registrations tab", () => {
    cy.contains("Registrations").click();
    cy.wait(500);

    cy.get(".table-wrapper").should("be.visible");
    cy.contains("Tour Test Plot Alpha").should("be.visible");
    cy.get("button:contains('Approve & Record')").should("be.visible");
    cy.get("button:contains('Reject')").should("be.visible");
  });

  it("shows KYC tab in admin", () => {
    cy.contains("KYC Verification").click();
    cy.wait(500);

    cy.get(".table-wrapper").should("be.visible");
    cy.get("button:contains('Verify')").should("be.visible");
    cy.get("button:contains('Reject')").should("be.visible");
  });

  it("shows transfers tab in admin", () => {
    cy.contains("Transfers").click();
    cy.wait(500);

    cy.get(".table-wrapper").should("be.visible");
    cy.get("button:contains('Approve & Record')").should("be.visible");
    cy.get("button:contains('Reject')").should("be.visible");
  });

  it("shows disputes tab in admin", () => {
    cy.contains("Disputes").click();
    cy.wait(500);

    cy.get(".table-wrapper").should("be.visible");
    cy.get("button:contains('View')").should("be.visible");
    cy.get("button:contains('Resolve')").should("be.visible");
  });

  it("shows settings tab", () => {
    cy.contains("Settings").click();
    cy.wait(500);

    cy.contains("Wallet Settings").should("be.visible");
    cy.get("button:contains('Connect MetaMask')").should("be.visible");
  });

  it("shows blockchain tab", () => {
    cy.contains("Blockchain").click();
    cy.wait(500);

    cy.contains("Blockchain Status").should("be.visible");
    cy.contains("Sepolia Testnet").should("be.visible");
  });

  it("admin logs out", () => {
    cy.contains("Sign Out").click();
    cy.url({ timeout: 5000 }).should("include", "login.php");
  });

  // ── 13. USER 2 TOUR ────────────────────────────────────

  it("logs in as user 2", () => {
    loginWithMock("testuser2_tour", "TestPass456!");
    cy.url().should("include", "dashboard.php");
  });

  it("user 2 sees dashboard", () => {
    cy.get(".page-title").should("contain", "Dashboard");
    cy.get(".stats-grid").should("be.visible");
  });

  it("user 2 browses properties", () => {
    cy.contains("Browse All").click();
    cy.wait(500);

    cy.get(".table-wrapper").should("be.visible");
    cy.contains("Tour Test Plot Alpha").should("be.visible");
    cy.contains("Tour Test Plot Beta").should("be.visible");
  });

  it("user 2 views their transfers", () => {
    cy.contains("My Transfers").click();
    cy.wait(500);

    cy.get(".table-wrapper").should("be.visible");
  });

  it("user 2 logs out", () => {
    cy.contains("Sign Out").click();
    cy.url({ timeout: 5000 }).should("include", "login.php");
  });

  // ── 14. PARCEL HISTORY TOUR ────────────────────────────

  it("shows parcel history lookup", () => {
    loginWithMock("admin", "password", "admin");

    // Scroll to history section
    cy.get("#historyQuery").should("be.visible");
    cy.get("#historyQuery").type("TC-TOUR-001");
    cy.get("#historySearchBtn").click();

    // Wait for mocked response
    cy.wait(1000);
    cy.get("#historyResults").should("be.visible");
    cy.get("#resParcelId").should("contain", "1");
    cy.get("#resCurrentOwner").should("be.visible");
    cy.get("#timelineContainer").should("be.visible");
  });

  it("shows ownership verification tool", () => {
    cy.get("#checkOwnerAddr").should("be.visible");
    cy.get("#checkOwnerTime").should("be.visible");
    cy.get("#verifyTimeBtn").should("be.visible");

    cy.get("#checkOwnerAddr").type("0x123456789abcdef");
    cy.get("#checkOwnerTime").type("2024-01-01T12:00");
    cy.get("#verifyTimeBtn").click();

    cy.wait(1000);
    cy.get("#verifyTimeResult").should("be.visible");
  });

  it("admin logs out", () => {
    cy.contains("Sign Out").click();
    cy.url({ timeout: 5000 }).should("include", "login.php");
  });

  // ── 15. FINAL TOUR COMPLETE ────────────────────────────

  it("completes the full tour", () => {
    cy.visit("/");
    cy.contains("TerraChain").should("be.visible");
    cy.contains("Blockchain Land Registry").should("be.visible");
    cy.log("✅ Full tour completed successfully! No data was recorded.");
  });
});
