const cron = require('node-cron');
const execa = require('execa');
const path = require('path');

// Resolve the repository root (parent directory of node-runner)
const repoRoot = path.resolve(__dirname, '..');

// Track if a job is currently running to prevent overlapping executions
let isRunning = false;

/**
 * Execute the tmr command to check for new slots
 */
async function runCheckNewSlots() {
  // Skip if a previous run is still in progress
  if (isRunning) {
    console.log(`[${new Date().toISOString()}] Skipping: Previous run still in progress`);
    return;
  }

  isRunning = true;
  const startTime = new Date().toISOString();
  
  console.log(`[${startTime}] Starting check-new-slots...`);

  try {
    await execa('php', ['tmr', 'app:check-new-slots'], {
      cwd: repoRoot,
      stdio: 'inherit'
    });
    
    const endTime = new Date().toISOString();
    console.log(`[${endTime}] Successfully completed check-new-slots`);
  } catch (error) {
    const endTime = new Date().toISOString();
    console.error(`[${endTime}] Error running check-new-slots:`, error.message);
  } finally {
    isRunning = false;
  }
}

// Schedule the job to run every hour at minute 0
// Cron expression: '0 * * * *' means "at minute 0 of every hour"
cron.schedule('0 * * * *', runCheckNewSlots);

console.log('Cron scheduler started. Job will run every hour at minute 0.');
console.log('Running initial check...');

// Run immediately on startup
runCheckNewSlots().catch((error) => {
  console.error('Error during initial run:', error.message);
});

