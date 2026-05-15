describe("Contact Form", () => {
  it("submits the form and shows success message", () => {
    cy.visit("/contact.php");

    cy.get("#name").type("Cypress Bot");
    cy.get("#email").type("bot@test.com");
    cy.get("#subject").select("Technical Support");
    cy.get("#message").type("Hello! This is an automated test of the new secure contact form.");

    cy.get("#submitBtn").click();

    // Verification: Look for the custom success message
    cy.contains("Message Sent!").should("be.visible");
    
    // Form should be cleared
    cy.get("#name").should("have.value", "");
  });
});
