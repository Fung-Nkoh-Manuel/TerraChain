describe("User Registration", () => {
  const testUser = {
    full_name: "Cypress Tester",
    username: "tester_" + Date.now(),
    email: "test_" + Date.now() + "@example.com",
    password: "Password123!",
  };

  beforeEach(() => {
    cy.visit("/register.php");
  });

  it("successfully registers a new user", () => {
    cy.get("#full_name").type(testUser.full_name);
    cy.get("#username").type(testUser.username);
    cy.get("#email").type(testUser.email);
    cy.get("#phone").type("+237 600000000");
    cy.get("#national_id").type("ID" + Date.now());
    cy.get("#password").type(testUser.password);
    cy.get("#confirm_password").type(testUser.password);
    cy.get('input[type="checkbox"]').check();

    cy.get("#registerBtn").click();

    // Verification: Should redirect to login with a success message
    cy.url().should("include", "login.php?registered=1");
    cy.contains("Welcome Back").should("be.visible");
  });

  it("validates password strength and matching", () => {
    cy.get("#password").type("weak");
    cy.contains("Weak").should("be.visible");
    
    cy.get("#confirm_password").type("different");
    cy.contains("Passwords do not match").should("be.visible");
  });
});
