// cypress/support/e2e.js
// This is the default support file for Cypress.
// You can add global hooks or custom commands here.
import './commands';

// --- PRESENTATION MODE ---
// Slows down Cypress tests so they are easier to watch during presentations
const COMMAND_DELAY = 800; // 800ms (0.8 seconds) delay after each action

// We only overwrite action commands. 'contains' and 'get' are queries and 
// should be handled differently or skipped to avoid errors in newer Cypress versions.
for (const command of ['visit', 'click', 'trigger', 'type', 'clear', 'reload']) {
    Cypress.Commands.overwrite(command, (originalFn, ...args) => {
        const origVal = originalFn(...args);
        return new Promise((resolve) => {
            setTimeout(() => {
                resolve(origVal);
            }, COMMAND_DELAY);
        });
    });
}