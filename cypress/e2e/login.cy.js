describe("Login Flow (with OTP Bypass)", () => {
  
  it("successfully logs in using the test mode bypass", () => {
    cy.visit("/login.php");

    // 1. Enter Password
    cy.get("#username").type("admin"); 
    cy.get("#password").type("password");
    cy.get("#loginBtn").click();

    // 2. Handle OTP Screen
    cy.contains("Verify Identity").should("be.visible");
    
    // Use the Test Mode bypass code (123456)
    cy.get("#otp").type("123456");
    cy.get("#verifyBtn").click();

    // 3. Success!
    cy.url().should("include", "admin.php");
    cy.contains("Admin Overview").should("be.visible");
  });

  it("shows error for wrong password", () => {
    cy.visit("/login.php");
    cy.get("#username").type("admin");
    cy.get("#password").type("wrong_pass");
    cy.get("#loginBtn").click();

    cy.contains("Invalid credentials").should("be.visible");
  });
});
