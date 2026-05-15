describe("Landing Page", () => {
  beforeEach(() => {
    cy.visit("/index.php");
  });

  it("loads stats from the public API", () => {
    // Wait for the stats to load
    cy.get("#statParcels").should("not.have.text", "...");
    cy.get("#statUsers").should("not.have.text", "...");
  });

  it("navigates to About and Privacy Policy correctly", () => {
    cy.contains("About").click();
    cy.url().should("include", "about.php");
    
    cy.go("back");
    
    cy.contains("Privacy Policy").click();
    cy.url().should("include", "privacy.php");
  });
});
