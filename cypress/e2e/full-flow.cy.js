describe("TerraChain — Complete Flow", () => {
  const admin = {
    username: "admin",
    password: "password",
  };

  const user1 = {
    username: "testuser_" + Date.now(),
    email: "testuser_" + Date.now() + "@test.com",
    password: "TestPass123!",
    fullName: "Cypress Test User",
    phone: "+237 600 000 001",
    nationalId: "CM" + Date.now(),
  };

  const user2 = {
    username: "testuser2_" + Date.now(),
    email: "testuser2_" + Date.now() + "@test.com",
    password: "TestPass456!",
    fullName: "Cypress Test User 2",
    phone: "+237 600 000 002",
    nationalId: "CM" + (Date.now() + 1),
  };

  let parcelNumber = "";

  // ── Helper: Login with OTP bypass ──────────────────
  function loginWithOTP(username, password) {
    // Set up intercept FIRST
    cy.intercept("POST", "**/api/auth/login").as("loginReq");

    // Then type and click
    cy.get("#username").type(username);
    cy.get("#password").type(password);
    cy.get("#loginBtn").click();

    // Wait for API response
    cy.wait("@loginReq", { timeout: 30000 });

    // Force show OTP form
    cy.get("#otpForm").invoke("attr", "style", "display: block");
    cy.get("#loginForm").invoke("attr", "style", "display: none");

    // Fill OTP and submit
    cy.get("#otp").type("123456");
    cy.get("#verifyBtn").click();

    cy.url({ timeout: 15000 }).should("not.include", "login.php");
  }

  // ══════════════════════════════════════════════════
  //  STEP 1: REGISTER TWO USERS
  // ══════════════════════════════════════════════════

  it("registers user 1", () => {
    cy.visit("/register.php");
    cy.get("#full_name").type(user1.fullName);
    cy.get("#username").type(user1.username);
    cy.get("#email").type(user1.email);
    cy.get("#phone").type(user1.phone);
    cy.get("#national_id").type(user1.nationalId);
    cy.get("#password").type(user1.password);
    cy.get("#confirm_password").type(user1.password);
    cy.get('input[type="checkbox"]').check();
    cy.get("#registerBtn").click();
    cy.url({ timeout: 15000 }).should("include", "login.php");
  });

  it("registers user 2", () => {
    cy.visit("/register.php");
    cy.get("#full_name").type(user2.fullName);
    cy.get("#username").type(user2.username);
    cy.get("#email").type(user2.email);
    cy.get("#phone").type(user2.phone);
    cy.get("#national_id").type(user2.nationalId);
    cy.get("#password").type(user2.password);
    cy.get("#confirm_password").type(user2.password);
    cy.get('input[type="checkbox"]').check();
    cy.get("#registerBtn").click();
    cy.url({ timeout: 15000 }).should("include", "login.php");
  });

  // ══════════════════════════════════════════════════
  //  STEP 2: USERS SUBMIT KYC
  // ══════════════════════════════════════════════════

  it("user 1 submits KYC", () => {
    cy.visit("/login.php");
    loginWithOTP(user1.username, user1.password);
    cy.url().should("include", "dashboard.php");
    cy.contains("KYC Verification").click();
    cy.wait(500);
    cy.get("body").then(($body) => {
      if ($body.find("button:contains('Submit KYC Now')").length > 0) {
        cy.contains("Submit KYC Now").click();
        cy.wait(300);
        cy.get("#kycFileInput").attachFile("test-docs/sample-id.pdf");
        cy.get("#kycSubmitBtn").click();
        cy.wait(2000);
      }
    });
    cy.contains("Sign Out").click();
  });

  it("user 2 submits KYC", () => {
    cy.visit("/login.php");
    loginWithOTP(user2.username, user2.password);
    cy.url().should("include", "dashboard.php");
    cy.contains("KYC Verification").click();
    cy.wait(500);
    cy.get("body").then(($body) => {
      if ($body.find("button:contains('Submit KYC Now')").length > 0) {
        cy.contains("Submit KYC Now").click();
        cy.wait(300);
        cy.get("#kycFileInput").attachFile("test-docs/sample-id.pdf");
        cy.get("#kycSubmitBtn").click();
        cy.wait(2000);
      }
    });
    cy.contains("Sign Out").click();
  });

  // ══════════════════════════════════════════════════
  //  STEP 3: ADMIN APPROVES BOTH KYCs
  // ══════════════════════════════════════════════════

  it("admin approves KYC for both users", () => {
    cy.visit("/login.php");
    loginWithOTP(admin.username, admin.password);
    cy.url().should("include", "admin.php");
    cy.contains("KYC Verification").click();
    cy.wait(1000);

    // Verify user 1
    cy.contains("td", user1.email).parent().find(".btn-success").click();
    cy.wait(500);

    // Verify user 2
    cy.contains("td", user2.email).parent().find(".btn-success").click();
    cy.wait(500);

    cy.contains("Sign Out").click();
  });

  // ══════════════════════════════════════════════════
  //  STEP 4: USER 1 REGISTERS LAND → ADMIN APPROVES
  // ══════════════════════════════════════════════════

  it("user 1 submits land registration", () => {
    cy.visit("/login.php");
    loginWithOTP(user1.username, user1.password);
    cy.url().should("include", "dashboard.php");
    cy.contains("Register Land").click();
    cy.wait(500);

    cy.get("#regTitle").type("Cypress Test Plot Alpha");
    cy.get("#regLocation").type("123 Test Street, Yaoundé, Cameroon");
    cy.get("#regSize").type("500");
    cy.get("#regType").select("residential");
    cy.get("#regGPS").type("3.8721, 11.5082");
    cy.get("#regDesc").type("Automated test parcel created by Cypress");
    cy.get("#regFileInput").attachFile("test-docs/land-deed.pdf");
    cy.get("#submitRegBtn").click();
    cy.wait(2000);
    cy.contains("awaiting admin review", { timeout: 10000 }).should(
      "be.visible",
    );
    cy.contains("Sign Out").click();
  });

  it("admin approves the land registration", () => {
    cy.visit("/login.php");
    loginWithOTP(admin.username, admin.password);
    cy.url().should("include", "admin.php");
    cy.contains("Registrations").click();
    cy.wait(1000);

    cy.contains("td", "Cypress Test Plot Alpha")
      .parent()
      .find(".btn-success")
      .click();
    cy.wait(1000);

    // Capture parcel number
    cy.contains("td", "Cypress Test Plot Alpha")
      .parent()
      .find("td:first-child")
      .invoke("text")
      .then((text) => {
        parcelNumber = text.trim().replace("#", "");
        cy.log("Parcel Number: " + parcelNumber);
      });

    cy.contains("Sign Out").click();
  });

  // ══════════════════════════════════════════════════
  //  STEP 5: TRANSFER → DISPUTE → RESOLVE
  // ══════════════════════════════════════════════════

  it("user 1 transfers land to user 2", () => {
    cy.visit("/login.php");
    loginWithOTP(user1.username, user1.password);
    cy.url().should("include", "dashboard.php");
    cy.contains("My Properties").click();
    cy.wait(500);

    cy.contains("td", "Cypress Test Plot Alpha")
      .parent()
      .find("button:contains('Transfer')")
      .click();
    cy.wait(500);

    cy.get("#recipientEmail").type(user2.email);
    cy.get("#transferType").select("sale");
    cy.get("#supportingDoc").attachFile("test-docs/transfer-agreement.pdf");
    cy.get("button:contains('Submit Transfer Request')").click();
    cy.wait(2000);
    cy.contains("Transfer request submitted").should("be.visible");
    cy.contains("Sign Out").click();
  });

  it("admin approves the transfer", () => {
    cy.visit("/login.php");
    loginWithOTP(admin.username, admin.password);
    cy.url().should("include", "admin.php");
    cy.contains("Transfers").click();
    cy.wait(1000);

    cy.contains("td", "Cypress Test Plot Alpha")
      .parent()
      .find(".btn-success")
      .click();
    cy.wait(1000);
    cy.contains("Sign Out").click();
  });

  it("user 1 files dispute and admin resolves", () => {
    // File dispute as user 1
    cy.visit("/login.php");
    loginWithOTP(user1.username, user1.password);
    cy.url().should("include", "dashboard.php");
    cy.contains("Disputes").click();
    cy.wait(500);
    cy.contains("File New Dispute").click();
    cy.wait(500);

    // Only type parcelNumber if it has a value
    cy.get("#disputeParcelNumber").then(($el) => {
      if (parcelNumber) {
        cy.wrap($el).type(parcelNumber);
      }
    });
    cy.get("#disputeType").select("ownership");
    cy.get("#disputeRespondentEmail").type(user2.email);
    cy.get("#disputeDescription").type("Test dispute - transfer was in error.");
    cy.get("#disputeEvidence").attachFile("test-docs/dispute-evidence.pdf");
    cy.get("button:contains('File Dispute')").click();
    cy.wait(2000);
    cy.contains("Dispute filed").should("be.visible");
    cy.contains("Sign Out").click();

    // Admin resolves
    cy.visit("/login.php");
    loginWithOTP(admin.username, admin.password);
    cy.url().should("include", "admin.php");
    cy.contains("Disputes").click();
    cy.wait(1000);

    cy.get("button:contains('Resolve')").first().click();
    cy.wait(500);
    cy.get("#resolveOutcome").select("resolved_complainant");
    cy.get("#resolveOwnership").select("ownership_changed");
    cy.get("#resolveNotes").type("Resolved in favor of complainant.");
    cy.get("button:contains('Resolve Dispute')").click();
    cy.wait(2000);
    cy.contains("Dispute resolved").should("be.visible");
  });
});
