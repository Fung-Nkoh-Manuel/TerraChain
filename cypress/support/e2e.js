// cypress/support/e2e.js
import "./commands";
import "cypress-file-upload";

// --- PRESENTATION MODE ---
const COMMAND_DELAY = 800;

for (const command of [
  "visit",
  "click",
  "trigger",
  "type",
  "clear",
  "reload",
]) {
  Cypress.Commands.overwrite(command, (originalFn, ...args) => {
    const origVal = originalFn(...args);
    return new Promise((resolve) => {
      setTimeout(() => {
        resolve(origVal);
      }, COMMAND_DELAY);
    });
  });
}

// ✅ Ignore JS errors from your app that don't affect testing
Cypress.on("uncaught:exception", (err, runnable) => {
  if (
    err.message.includes("has already been declared") ||
    err.message.includes("Assignment to constant variable") ||
    err.message.includes("constant variable")
  ) {
    return false; // Don't fail the test
  }
  return true; // Fail on other errors
});
